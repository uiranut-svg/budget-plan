<?php
define('BP_DEPTH', 1);
require dirname(dirname(__FILE__)) . '/inc/bootstrap.php';
header('Cache-Control: no-store');

function admin_head($title, $active = '')
{
    $u = bp_user();
    if (bp_is_admin()) {
        $menu = array('index' => 'ปีงบประมาณ', 'units' => 'หน่วยงาน', 'books' => 'เล่มแผน', 'settings' => 'ตั้งค่า / ผู้ใช้', 'system' => 'ระบบ');
    } else {
        $menu = array('my' => 'โครงการของฉัน');
    }
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="csrf" content="' . h(bp_csrf_token()) . '">'
        . '<title>' . h($title) . ' · ผู้ดูแลระบบ</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(bp_url('assets/css/app.css')) . '?v=12"></head><body class="admin">'
        . '<header class="topbar"><div class="wrap topbar-in">'
        . '<a class="brand" href="' . h(bp_url(bp_is_admin() ? 'admin/index.php' : 'admin/my.php')) . '"><span class="brand-mark" aria-hidden="true">฿</span><span><b>' . h(bp_site_name()) . '</b><small>'
        . (bp_is_admin() ? 'ผู้ดูแลระบบ' : 'ผู้กรอกข้อมูลหน่วยงาน') . '</small></span></a>'
        . '<nav class="topnav">';
    foreach ($menu as $k => $label) {
        $href = $k == 'system' ? 'admin/log.php' : 'admin/' . $k . '.php';
        echo '<a class="' . ($active == $k ? 'on' : '') . '" href="' . h(bp_url($href)) . '">' . $label . '</a>';
    }
    echo '<a href="' . h(bp_url('index.php')) . '" target="_blank" rel="noopener">ดูหน้าเว็บ ↗</a>'
        . '<form method="post" action="' . h(bp_url('admin/logout.php')) . '" class="inline">' . bp_csrf_field() . '<button class="linkbtn" type="submit">ออกจากระบบ (' . h($u['username']) . ')</button></form>'
        . '</nav></div></header><main class="wrap main">';
    $f = bp_flash();
    if ($f) echo '<div class="alert alert-' . ($f[0] == 'ok' ? 'ok' : 'err') . '">' . nl2br(h($f[1])) . '</div>';
    echo '<h1 class="page-title">' . h($title) . '</h1>';
}
/** แท็บของปีงบประมาณ + คำเตือนความผิดปกติของตัวเลข */
function admin_year_tabs($y, $active)
{
    $id = (int)$y['id'];
    $tabs = array(
        'year' => array('year.php?id=' . $id, 'งบตามหมวด'),
        'funds' => array('funds.php?id=' . $id, 'แหล่งเงิน'),
        'projects' => array('projects.php?id=' . $id, 'โครงการ'),
        'transfer' => array('transfer.php?id=' . $id, 'ปรับแผน / โอนงบ'),
        'procure' => array('procure.php?id=' . $id, 'จัดซื้อจัดจ้าง'),
        'align' => array('align.php?id=' . $id, 'ความสอดคล้อง'),
        'reports' => array('reports.php?id=' . $id, 'รายงาน 6/12 เดือน'),
        'excel' => array('excel.php?id=' . $id, 'Excel'),
    );
    echo '<div class="seg admin-tabs">';
    foreach ($tabs as $k => $t) echo '<a class="seg-btn' . ($k == $active ? ' on' : '') . '" href="' . h($t[0]) . '">' . $t[1] . '</a>';
    echo '<a class="seg-btn" href="' . h(bp_url('index.php?y=' . (int)$y['year'])) . '" target="_blank" rel="noopener">ดูหน้าเว็บปี ' . (int)$y['year'] . ' ↗</a>'
        . '<a class="seg-btn" href="' . h(bp_url('ita.php?y=' . (int)$y['year'])) . '" target="_blank" rel="noopener">ลิงก์ ITA ↗</a></div>';
    $w = bp_checks($id);
    if ($w) {
        echo '<details class="checks"' . (count($w) <= 3 ? ' open' : '') . '><summary>⚠ พบจุดที่ควรตรวจสอบ ' . count($w) . ' รายการ</summary><ul>';
        foreach ($w as $x) echo '<li>' . ($x[1] !== '' ? '<a href="' . h($x[1]) . '">' . h($x[0]) . '</a>' : h($x[0])) . '</li>';
        echo '</ul></details>';
    }
}
/** หาปีจาก ?id= หรือกลับหน้ารายการปี */
function admin_year_or_back()
{
    $yid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
    if (!$y) bp_redirect('admin/index.php');
    return $y;
}
/** บันทึกเวลาแก้ไขล่าสุดของปี (ใช้แสดง "ข้อมูล ณ วันที่") */
function admin_touch_year($yid)
{
    bp_query('UPDATE ' . bp_t('year') . " SET updated_at='" . date('Y-m-d H:i:s') . "' WHERE id=" . (int)$yid);
}
/** แท็บของหน้า "ระบบ" */
function admin_system_tabs($active)
{
    $tabs = array('log' => 'บันทึกการแก้ไข', 'backup' => 'สำรอง / กู้คืนข้อมูล', 'update' => 'อัปเดตระบบ');
    echo '<div class="seg admin-tabs">';
    foreach ($tabs as $k => $t) echo '<a class="seg-btn' . ($k == $active ? ' on' : '') . '" href="' . $k . '.php">' . $t . '</a>';
    echo '</div>';
}
function bp_ini_bytes($v)
{
    $v = trim((string)$v);
    $n = (float)$v;
    $u = strtolower(substr($v, -1));
    if ($u == 'g') $n *= 1073741824; elseif ($u == 'm') $n *= 1048576; elseif ($u == 'k') $n *= 1024;
    return (int)$n;
}
function bp_max_upload()
{
    return min(bp_ini_bytes(ini_get('upload_max_filesize')), bp_ini_bytes(ini_get('post_max_size')));
}
function admin_foot($js = '')
{
    echo '</main>' . $js . '</body></html>';
}
