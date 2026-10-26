<?php
define('BP_DEPTH', 1);
require dirname(dirname(__FILE__)) . '/inc/bootstrap.php';
header('Cache-Control: no-store');

function admin_head($title, $active = '')
{
    $u = bp_user();
    $menu = array('index' => 'ปีงบประมาณ', 'books' => 'เล่มแผน', 'settings' => 'ตั้งค่า');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="csrf" content="' . h(bp_csrf_token()) . '">'
        . '<title>' . h($title) . ' · ผู้ดูแลระบบ</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(bp_url('assets/css/app.css')) . '?v=3"></head><body class="admin">'
        . '<header class="topbar"><div class="wrap topbar-in">'
        . '<a class="brand" href="' . h(bp_url('admin/index.php')) . '"><span class="brand-mark" aria-hidden="true">฿</span><span><b>' . h(bp_site_name()) . '</b><small>ผู้ดูแลระบบ</small></span></a>'
        . '<nav class="topnav">';
    foreach ($menu as $k => $label) echo '<a class="' . ($active == $k ? 'on' : '') . '" href="' . h(bp_url('admin/' . $k . '.php')) . '">' . $label . '</a>';
    echo '<a href="' . h(bp_url('index.php')) . '" target="_blank" rel="noopener">ดูหน้าเว็บ ↗</a>'
        . '<form method="post" action="' . h(bp_url('admin/logout.php')) . '" class="inline">' . bp_csrf_field() . '<button class="linkbtn" type="submit">ออกจากระบบ (' . h($u['username']) . ')</button></form>'
        . '</nav></div></header><main class="wrap main">';
    $f = bp_flash();
    if ($f) echo '<div class="alert alert-' . ($f[0] == 'ok' ? 'ok' : 'err') . '">' . h($f[1]) . '</div>';
    echo '<h1 class="page-title">' . h($title) . '</h1>';
}
function admin_foot($js = '')
{
    echo '</main>' . $js . '</body></html>';
}
