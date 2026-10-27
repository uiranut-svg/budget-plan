<?php
define('BP_ROOT', dirname(__FILE__));
@ini_set('display_errors', '0');
date_default_timezone_set('Asia/Bangkok');
require BP_ROOT . '/inc/compat.php';
require BP_ROOT . '/inc/functions.php';
header('X-Frame-Options: SAMEORIGIN');

function setup_page($body)
{
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>ติดตั้งระบบ</title><link rel="stylesheet" href="assets/css/app.css?v=6"></head><body class="setup">'
        . '<main class="setup-box">' . $body . '</main></body></html>';
    exit;
}

if (is_file(BP_ROOT . '/inc/config.php')) {
    setup_page('<h1>ติดตั้งแล้ว</h1><p>ระบบติดตั้งเรียบร้อยแล้ว หน้านี้ปิดการทำงาน</p><p><a class="btn" href="index.php">ไปหน้าแรก</a></p>');
}

// เปิดได้เฉพาะจากเครื่องเซิร์ฟเวอร์ หรือ IP ที่อยู่ในไฟล์ inc/setup.allow (บรรทัดละ 1 IP)
$ip = bp_client_ip();
$allow = array('127.0.0.1', '::1');
if (is_file(BP_ROOT . '/inc/setup.allow')) {
    foreach (preg_split('/\s+/', file_get_contents(BP_ROOT . '/inc/setup.allow')) as $a) if ($a !== '') $allow[] = $a;
}
if (!in_array($ip, $allow, true)) {
    http_response_code(403);
    setup_page('<h1>ยังติดตั้งไม่ได้</h1><p>หน้าติดตั้งเปิดได้เฉพาะจากเครื่องเซิร์ฟเวอร์ (localhost)</p>'
        . '<p>ถ้าติดตั้งจากเครื่องอื่น ให้สร้างไฟล์ <code>inc/setup.allow</code> บนเซิร์ฟเวอร์ ใส่ IP นี้: <code>' . h($ip) . '</code></p>');
}

$v = array(
    'db_host' => 'localhost', 'db_user' => 'root', 'db_pass' => '', 'db_name' => 'budget_plan', 'prefix' => 'bp_',
    'school_name' => '', 'admin_user' => 'admin', 'admin_pass' => '', 'admin_pass2' => '',
);
$err = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($v as $k => $d) if (isset($_POST[$k])) $v[$k] = trim((string)$_POST[$k]);
    if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $v['db_name'])) $err = 'ชื่อฐานข้อมูลใช้ได้เฉพาะ A-Z a-z 0-9 _';
    elseif (!preg_match('/^[A-Za-z0-9_]{0,16}$/', $v['prefix'])) $err = 'คำนำหน้าตารางใช้ได้เฉพาะ A-Z a-z 0-9 _';
    elseif ($v['school_name'] === '') $err = 'กรุณากรอกชื่อสถานศึกษา';
    elseif (!preg_match('/^[A-Za-z0-9_.\-]{3,64}$/', $v['admin_user'])) $err = 'ชื่อผู้ใช้ผู้ดูแลระบบ 3–64 ตัว (A-Z 0-9 _ . -)';
    elseif (strlen($v['admin_pass']) < 8) $err = 'รหัสผ่านผู้ดูแลระบบอย่างน้อย 8 ตัว';
    elseif ($v['admin_pass'] !== $v['admin_pass2']) $err = 'ยืนยันรหัสผ่านไม่ตรงกัน';
    if ($err === '') {
        if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
        $db = @mysqli_connect($v['db_host'], $v['db_user'], $v['db_pass']);
        if (!$db) $err = 'เชื่อมต่อ MySQL/MariaDB ไม่ได้: ' . mysqli_connect_error();
    }
    if ($err === '') {
        @mysqli_set_charset($db, 'utf8mb4');
        if (!mysqli_select_db($db, $v['db_name'])) {
            if (!mysqli_query($db, 'CREATE DATABASE `' . $v['db_name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci') || !mysqli_select_db($db, $v['db_name'])) {
                $err = 'สร้างฐานข้อมูลไม่ได้: ' . mysqli_error($db);
            }
        }
    }
    if ($err === '') {
        $p = $v['prefix'];
        $r = mysqli_query($db, "SHOW TABLES LIKE '" . mysqli_real_escape_string($db, str_replace('_', '\\_', $p)) . "setting'");
        if ($r && mysqli_num_rows($r) > 0) $err = 'ฐานข้อมูลนี้มีตารางของระบบอยู่แล้ว (คำนำหน้า ' . h($p) . ') — ใช้ชื่อฐานข้อมูลหรือคำนำหน้าอื่น';
    }
    if ($err === '') {
        $created = array();
        foreach (require BP_ROOT . '/inc/schema.php' as $sql) {
            if (!mysqli_query($db, str_replace('{p}', $p, $sql))) {
                $err = 'สร้างตารางไม่ได้: ' . mysqli_error($db);
                break;
            }
            if (preg_match('/`\{p\}(\w+)`/', $sql, $m)) $created[] = $p . $m[1];
        }
        if ($err === '') {
            $set = array(
                'site_name' => 'ข้อมูลแผนงานและงบประมาณ',
                'school_name' => $v['school_name'],
                'intro' => 'ข้อมูลแผนปฏิบัติราชการ งบประมาณรายจ่ายประจำปี จำแนกตามหมวดงบประมาณ เพื่อเผยแพร่ต่อสาธารณะ',
                'unit_name' => 'งานวางแผนและงบประมาณ',
                'schema_ver' => (string)BP_SCHEMA_VER,
            );
            foreach ($set as $k => $val) {
                mysqli_query($db, "INSERT INTO `{$p}setting` (name,value) VALUES ('" . mysqli_real_escape_string($db, $k) . "','" . mysqli_real_escape_string($db, $val) . "')");
            }
            $ok = mysqli_query($db, "INSERT INTO `{$p}user` (username,password,name,created_at) VALUES ('"
                . mysqli_real_escape_string($db, $v['admin_user']) . "','" . mysqli_real_escape_string($db, password_hash($v['admin_pass'], PASSWORD_DEFAULT))
                . "','ผู้ดูแลระบบ','" . date('Y-m-d H:i:s') . "')");
            $cfg = array('db_host' => $v['db_host'], 'db_user' => $v['db_user'], 'db_pass' => $v['db_pass'], 'db_name' => $v['db_name'], 'prefix' => $p);
            $php = "<?php\n// ค่าเชื่อมต่อฐานข้อมูล (สร้างโดย setup.php) — ห้ามเผยแพร่ไฟล์นี้\nreturn " . var_export($cfg, true) . ";\n";
            if (!$ok || @file_put_contents(BP_ROOT . '/inc/config.php', $php) === false) {
                foreach ($created as $t) mysqli_query($db, 'DROP TABLE IF EXISTS `' . $t . '`');
                $err = $ok ? 'เขียนไฟล์ inc/config.php ไม่ได้ — ให้สิทธิ์เขียนโฟลเดอร์ inc/ แก่เว็บเซิร์ฟเวอร์' : 'สร้างบัญชีผู้ดูแลระบบไม่ได้: ' . mysqli_error($db);
            } else {
                setup_page('<h1>ติดตั้งเสร็จแล้ว</h1><p>เข้าสู่ระบบผู้ดูแลด้วยชื่อผู้ใช้ <b>' . h($v['admin_user']) . '</b> แล้วตั้ง <b>หน่วยงาน</b> (มีปุ่มใส่หน่วยงานมาตรฐาน) เพิ่มปีงบประมาณ แหล่งเงิน โครงการ และอัปโหลดเล่มแผนได้เลย</p>'
                    . '<p>ตรวจว่าโฟลเดอร์ <code>files/books/</code> เขียนได้ (สำหรับอัปโหลดเล่มแผน)</p>'
                    . '<p><a class="btn" href="admin/login.php">เข้าสู่ระบบผู้ดูแล</a> <a class="btn btn-ghost" href="index.php">หน้าแรก</a></p>');
            }
        }
    }
}

function fld($label, $name, $v, $type = 'text', $hint = '')
{
    return '<label class="fld"><span>' . h($label) . '</span><input type="' . $type . '" name="' . $name . '" value="' . ($type == 'password' ? '' : h($v[$name])) . '"' . ($type == 'password' ? ' autocomplete="new-password"' : '') . '>'
        . ($hint ? '<small>' . h($hint) . '</small>' : '') . '</label>';
}
setup_page('<h1>ติดตั้งระบบข้อมูลแผนงานและงบประมาณ</h1>'
    . ($err ? '<div class="alert alert-err">' . h($err) . '</div>' : '')
    . '<form method="post"><h2>ฐานข้อมูล</h2>'
    . fld('เครื่องฐานข้อมูล', 'db_host', $v) . fld('ชื่อผู้ใช้ฐานข้อมูล', 'db_user', $v) . fld('รหัสผ่านฐานข้อมูล', 'db_pass', $v, 'password')
    . fld('ชื่อฐานข้อมูล', 'db_name', $v, 'text', 'ไม่มี = สร้างให้') . fld('คำนำหน้าตาราง', 'prefix', $v)
    . '<h2>สถานศึกษา</h2>' . fld('ชื่อสถานศึกษา', 'school_name', $v, 'text', 'เช่น วิทยาลัยเทคนิค...')
    . '<h2>ผู้ดูแลระบบ</h2>' . fld('ชื่อผู้ใช้', 'admin_user', $v) . fld('รหัสผ่าน (อย่างน้อย 8 ตัว)', 'admin_pass', $v, 'password') . fld('ยืนยันรหัสผ่าน', 'admin_pass2', $v, 'password')
    . '<p><button class="btn" type="submit">ติดตั้ง</button></p></form>');
