<?php
// หัว/ท้ายหน้าสาธารณะ
function bp_head($title, $extraHead = '')
{
    $site = bp_site_name();
    $school = bp_setting('school_name');
    echo '<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . h($title !== '' ? $title . ' · ' . $site : $site) . '</title>'
        . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
        . '<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">'
        . '<link rel="stylesheet" href="' . h(bp_url('assets/css/app.css')) . '?v=11">' . $extraHead . '</head><body>'
        . '<header class="topbar"><div class="wrap topbar-in">'
        . '<a class="brand" href="' . h(bp_url('index.php')) . '"><span class="brand-mark" aria-hidden="true">฿</span><span><b>' . h($site) . '</b>'
        . ($school !== '' ? '<small>' . h($school) . '</small>' : '') . '</span></a>'
        . '<nav class="topnav">' . bp_nav_links() . '</nav>'
        . '</div></header>';
}
/** เมนูบน — ส่งต่อปีที่เลือก ($GLOBALS['BP_NAV_YEAR']) · $GLOBALS['BP_NAV_ON'] = เมนูที่เปิดอยู่ */
function bp_nav_links()
{
    $y = isset($GLOBALS['BP_NAV_YEAR']) ? (int)$GLOBALS['BP_NAV_YEAR'] : 0;
    $on = isset($GLOBALS['BP_NAV_ON']) ? $GLOBALS['BP_NAV_ON'] : '';
    $home = bp_url('index.php') . ($y ? '?y=' . $y : '');
    $menu = array(
        array('summary', $home . '#summary', 'ภาพรวม'),
        array('funds', $home . '#funds', 'แหล่งเงิน'),
        array('detail', $home . '#detail', 'งบประมาณ'),
        array('projects', bp_url('projects.php') . ($y ? '?y=' . $y : ''), 'โครงการ'),
        array('procure', bp_url('procurement.php') . ($y ? '?y=' . $y : ''), 'จัดซื้อจัดจ้าง'),
        array('report', bp_url('report.php') . ($y ? '?y=' . $y : ''), 'รายงานผล'),
        array('strategy', $home . '#strategy', 'ยุทธศาสตร์'),
        array('books', $home . '#books', 'เล่มแผน'),
    );
    $o = '';
    foreach ($menu as $m) $o .= '<a' . ($on === $m[0] ? ' class="on"' : '') . ' href="' . h($m[1]) . '">' . $m[2] . '</a>';
    return $o;
}
function bp_foot()
{
    $unit = bp_setting('unit_name');
    $contact = bp_setting('contact');
    echo '<footer class="footer"><div class="wrap">'
        . ($unit !== '' ? '<b>' . h($unit) . '</b> ' : '') . h(bp_setting('school_name'))
        . ($contact !== '' ? '<div class="footer-contact">' . nl2br(h($contact)) . '</div>' : '')
        . '<div class="footer-admin"><a href="' . h(bp_url('ita.php') . (isset($GLOBALS['BP_NAV_YEAR']) && $GLOBALS['BP_NAV_YEAR'] ? '?y=' . (int)$GLOBALS['BP_NAV_YEAR'] : '')) . '">ลิงก์ข้อมูลสำหรับ ITA</a> · <a href="' . h(bp_url('admin/login.php')) . '">สำหรับเจ้าหน้าที่</a></div>'
        . '</div></footer></body></html>';
}
