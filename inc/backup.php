<?php
// สำรอง / กู้คืนฐานข้อมูล (เฉพาะตารางของระบบนี้ = ขึ้นต้นด้วย prefix) — ไฟล์ .sql.gz เก็บใน backups/ (ห้ามเข้าถึงผ่านเว็บ)

function bp_backup_dir()
{
    $d = BP_ROOT . '/backups/';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (!is_file($d . '.htaccess')) @file_put_contents($d . '.htaccess', "# ไฟล์สำรองข้อมูล — ห้ามเข้าถึงผ่านเว็บ\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    if (!is_file($d . 'index.html')) @file_put_contents($d . 'index.html', '');
    return $d;
}
function bp_backup_tables()
{
    $p = $GLOBALS['BP_CFG']['prefix'];
    $o = array();
    foreach (bp_rows("SHOW TABLES LIKE '" . mysqli_real_escape_string(bp_db(), str_replace('_', '\\_', $p)) . "%'") as $r) $o[] = reset($r);
    return $o;
}
/** สร้างไฟล์สำรอง · คืนชื่อไฟล์ หรือ '' ถ้าไม่สำเร็จ */
function bp_backup_create($label = 'manual')
{
    @set_time_limit(300);
    $dir = bp_backup_dir();
    $name = 'backup_' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9\-]/', '', $label) . '.sql.gz';
    $gz = @gzopen($dir . $name, 'wb6');
    if (!$gz) return '';
    gzwrite($gz, "-- budget-plan backup " . date('Y-m-d H:i:s') . " · schema " . bp_setting('schema_ver') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n");
    foreach (bp_backup_tables() as $t) {
        $c = bp_row('SHOW CREATE TABLE `' . $t . '`');
        $create = isset($c['Create Table']) ? $c['Create Table'] : '';
        gzwrite($gz, "\nDROP TABLE IF EXISTS `" . $t . "`;\n" . str_replace("\n", ' ', $create) . ";\n");
        $r = mysqli_query(bp_db(), 'SELECT * FROM `' . $t . '`', MYSQLI_USE_RESULT);
        if (!$r) continue;
        $batch = array();
        while ($row = mysqli_fetch_row($r)) {
            $v = array();
            foreach ($row as $x) $v[] = $x === null ? 'NULL' : "'" . mysqli_real_escape_string(bp_db(), $x) . "'";
            $batch[] = '(' . implode(',', $v) . ')';
            if (count($batch) >= 200) { gzwrite($gz, 'INSERT INTO `' . $t . '` VALUES ' . implode(',', $batch) . ";\n"); $batch = array(); }
        }
        mysqli_free_result($r);
        if ($batch) gzwrite($gz, 'INSERT INTO `' . $t . '` VALUES ' . implode(',', $batch) . ";\n");
    }
    gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
    gzclose($gz);
    return $name;
}
function bp_backup_list()
{
    $o = array();
    foreach ((array)glob(bp_backup_dir() . 'backup_*.sql.gz') as $f) $o[] = array('name' => basename($f), 'size' => filesize($f), 'time' => filemtime($f));
    usort($o, 'bp_backup_cmp');
    return $o;
}
function bp_backup_cmp($a, $b)
{
    return $b['time'] - $a['time'];
}
function bp_backup_valid_name($n)
{
    return (bool)preg_match('/^backup_\d{8}_\d{6}_[a-z0-9\-]*\.sql\.gz$/', (string)$n) && is_file(bp_backup_dir() . $n);
}
/** กู้คืนจากไฟล์ .sql.gz ที่สร้างโดยระบบนี้ · คืนค่า '' = สำเร็จ หรือข้อความผิดพลาด */
function bp_backup_restore($path)
{
    @set_time_limit(600);
    $sql = @gzdecode((string)@file_get_contents($path));
    if ($sql === false || strpos($sql, '-- budget-plan backup') !== 0) return 'ไฟล์นี้ไม่ใช่ไฟล์สำรองของระบบนี้';
    $p = $GLOBALS['BP_CFG']['prefix'];
    // ตรวจทุกคำสั่งก่อนรัน: ต้องเป็นตารางของระบบนี้เท่านั้น
    $stmts = array();
    foreach (explode(";\n", $sql) as $s) {
        $s = trim($s);
        if ($s === '' || strpos($s, '--') === 0 && strpos($s, "\n") === false) continue;
        $s = preg_replace('/^(--[^\n]*\n)+/', '', $s);
        if ($s === '') continue;
        if (!preg_match('/^(SET (NAMES|FOREIGN_KEY_CHECKS)|DROP TABLE IF EXISTS `([A-Za-z0-9_]+)`|CREATE TABLE `([A-Za-z0-9_]+)`|INSERT INTO `([A-Za-z0-9_]+)`)/', $s, $m)) return 'พบคำสั่งที่ไม่รู้จักในไฟล์สำรอง — ไม่กู้คืน';
        $t = isset($m[5]) && $m[5] !== '' ? $m[5] : (isset($m[4]) && $m[4] !== '' ? $m[4] : (isset($m[3]) ? $m[3] : ''));
        if ($t !== '' && strpos($t, $p) !== 0) return 'ไฟล์สำรองมีตารางที่ไม่ใช่ของระบบนี้ (' . $t . ') — ไม่กู้คืน';
        $stmts[] = $s;
    }
    foreach ($stmts as $s) {
        if (!mysqli_query(bp_db(), $s)) return 'กู้คืนไม่สำเร็จ: ' . mysqli_error(bp_db());
    }
    return '';
}
