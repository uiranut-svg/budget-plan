<?php
/*
 * อัปเดตระบบจาก GitHub (แบบ SAM แต่ง่ายกว่า: repo สาธารณะ ไม่ต้องใช้โทเคน)
 *  ผู้พัฒนา: แก้เลขใน VERSION (ปี ค.ศ. + ลำดับ 4 หลัก) → commit → สร้าง tag vYYYYNNNN → push ขึ้น GitHub
 *  เครื่องวิทยาลัย: ผู้ดูแล › ระบบ › อัปเดตระบบ → ตรวจ tag ล่าสุด → ดาวน์โหลด zip → สำรองฐานข้อมูล → เขียนเฉพาะไฟล์ที่เปลี่ยน
 *                   (เก็บไฟล์เดิมไว้ใน backups/rollback-…/) → ตารางใหม่สร้างเองเมื่อเปิดหน้าเว็บครั้งถัดไป (bp_migrate)
 *  ไม่แตะ: inc/config.php, inc/setup.allow, files/, backups/, .git/
 *  เครื่องที่มีโฟลเดอร์ .git (เครื่องพัฒนา) อัปเดตผ่านหน้าเว็บไม่ได้ — ใช้ git แทน
 */
// แหล่งอัปเดตล็อกไว้ในโค้ด (กันการชี้ไปดึงโค้ดจากที่อื่นผ่านหน้าเว็บ) — ว่าง = ยังไม่เปิดใช้ · ตั้งเป็น 'owner/repo' เมื่อ push ขึ้น GitHub แล้ว
define('BP_UPDATE_REPO', '');

function bp_version()
{
    return trim((string)@file_get_contents(BP_ROOT . '/VERSION'));
}
function bp_update_is_dev()
{
    return is_dir(BP_ROOT . '/.git');
}
function bp_update_skip($path)
{
    return (bool)preg_match('#^(inc/config\.php|inc/setup\.allow|files/|backups/|\.git/)#', $path);
}
/** ดึง URL · คืนค่า array(code, body) */
function bp_http_get($url, $timeout = 30)
{
    $ua = 'budget-plan-updater';
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => $ua, CURLOPT_HTTPHEADER => array('Accept: application/vnd.github+json')));
        $b = curl_exec($c);
        $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);
        return array($code, $b === false ? '' : $b);
    }
    $ctx = stream_context_create(array('http' => array('timeout' => $timeout, 'user_agent' => $ua, 'ignore_errors' => true, 'header' => "Accept: application/vnd.github+json\r\n")));
    $b = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m)) $code = (int)$m[1];
    return array($code, $b === false ? '' : $b);
}
/** tag ล่าสุดบน GitHub · คืนค่า array('ok'=>bool, 'tag'=>'v20260004', 'version'=>'20260004', 'error'=>...) */
function bp_update_check()
{
    if (BP_UPDATE_REPO === '') return array('ok' => false, 'error' => 'ยังไม่ได้ตั้งแหล่งอัปเดต (ผู้พัฒนาต้องนำโค้ดขึ้น GitHub ก่อน)');
    list($code, $body) = bp_http_get('https://api.github.com/repos/' . BP_UPDATE_REPO . '/tags?per_page=30');
    if ($code != 200) return array('ok' => false, 'error' => 'เชื่อมต่อ GitHub ไม่ได้ (รหัส ' . $code . ') — ตรวจอินเทอร์เน็ตของเซิร์ฟเวอร์');
    $best = null;
    foreach ((array)json_decode($body, true) as $t) {
        if (isset($t['name']) && preg_match('/^v(\d{8})$/', $t['name'], $m) && (!$best || $m[1] > $best[1])) $best = array($t['name'], $m[1]);
    }
    if (!$best) return array('ok' => false, 'error' => 'ยังไม่มีรุ่นที่ปล่อยบน GitHub');
    return array('ok' => true, 'tag' => $best[0], 'version' => $best[1]);
}
/** ติดตั้งรุ่น $tag · คืนค่า array('ok'=>bool, 'msg'=>...) */
function bp_update_install($tag)
{
    @set_time_limit(600);
    if (bp_update_is_dev()) return array('ok' => false, 'msg' => 'เครื่องนี้มีโฟลเดอร์ .git (เครื่องพัฒนา) — อัปเดตด้วย git แทน');
    if (!preg_match('/^v\d{8}$/', $tag) || BP_UPDATE_REPO === '') return array('ok' => false, 'msg' => 'รุ่นไม่ถูกต้อง');
    list($code, $zip) = bp_http_get('https://codeload.github.com/' . BP_UPDATE_REPO . '/zip/refs/tags/' . $tag, 120);
    if ($code != 200 || strlen($zip) < 1000) return array('ok' => false, 'msg' => 'ดาวน์โหลดไม่สำเร็จ (รหัส ' . $code . ')');
    require_once BP_ROOT . '/inc/xlsx.php';
    require_once BP_ROOT . '/inc/backup.php';
    try { $files = BpXlsx::unzip($zip); } catch (Exception $e) { return array('ok' => false, 'msg' => 'แตกไฟล์ไม่ได้: ' . $e->getMessage()); }
    // zip ของ GitHub มีโฟลเดอร์ชั้นบนสุด (repo-tag/) — ตัดออก
    $new = array();
    foreach ($files as $name => $content) {
        $p = preg_replace('#^[^/]+/#', '', $name);
        if ($p === '' || substr($p, -1) === '/' || strpos($p, '..') !== false || bp_update_skip($p)) continue;
        $new[$p] = $content;
    }
    if (!isset($new['VERSION']) || !isset($new['inc/functions.php'])) return array('ok' => false, 'msg' => 'ไฟล์รุ่นใหม่ไม่ครบ — ไม่ติดตั้ง');
    $dbBackup = bp_backup_create('before-update');
    if ($dbBackup === '') return array('ok' => false, 'msg' => 'สำรองฐานข้อมูลก่อนอัปเดตไม่ได้ — ไม่ติดตั้ง');
    $rb = bp_backup_dir() . 'rollback-' . date('Ymd_His') . '/';
    $changed = array();
    foreach ($new as $p => $content) {
        $dst = BP_ROOT . '/' . $p;
        if (is_file($dst) && md5_file($dst) === md5($content)) continue;
        $changed[] = $p;
    }
    $done = array();
    foreach ($changed as $p) {
        $dst = BP_ROOT . '/' . $p;
        if (is_file($dst)) { @mkdir(dirname($rb . $p), 0750, true); @copy($dst, $rb . $p); }
        if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
        if (@file_put_contents($dst, $new[$p], LOCK_EX) === false) {
            // เขียนไม่ได้ → ย้อนไฟล์ที่เขียนไปแล้ว
            foreach ($done as $q) { if (is_file($rb . $q)) @copy($rb . $q, BP_ROOT . '/' . $q); else @unlink(BP_ROOT . '/' . $q); }
            return array('ok' => false, 'msg' => 'เขียนไฟล์ ' . $p . ' ไม่ได้ — ย้อนกลับแล้ว (ให้สิทธิ์เขียนโฟลเดอร์ระบบแก่เว็บเซิร์ฟเวอร์)');
        }
        $done[] = $p;
        if (function_exists('opcache_invalidate')) @opcache_invalidate($dst, true);
    }
    return array('ok' => true, 'msg' => 'ติดตั้งรุ่น ' . substr($tag, 1) . ' แล้ว (เปลี่ยน ' . count($changed) . ' ไฟล์ · สำรองฐานข้อมูล ' . $dbBackup . ')');
}
