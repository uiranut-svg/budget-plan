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
        . '<link rel="stylesheet" href="' . h(bp_url('assets/css/app.css')) . '?v=2">' . $extraHead . '</head><body>'
        . '<header class="topbar"><div class="wrap topbar-in">'
        . '<a class="brand" href="' . h(bp_url('index.php')) . '"><span class="brand-mark" aria-hidden="true">฿</span><span><b>' . h($site) . '</b>'
        . ($school !== '' ? '<small>' . h($school) . '</small>' : '') . '</span></a>'
        . '<nav class="topnav"><a href="' . h(bp_url('index.php')) . '#summary">ภาพรวม</a><a href="' . h(bp_url('index.php')) . '#detail">รายละเอียด</a>'
        . '<a href="' . h(bp_url('index.php')) . '#compare">เปรียบเทียบ</a><a href="' . h(bp_url('index.php')) . '#books">เล่มแผน</a></nav>'
        . '</div></header>';
}
function bp_foot()
{
    $unit = bp_setting('unit_name');
    $contact = bp_setting('contact');
    echo '<footer class="footer"><div class="wrap">'
        . ($unit !== '' ? '<b>' . h($unit) . '</b> ' : '') . h(bp_setting('school_name'))
        . ($contact !== '' ? '<div class="footer-contact">' . nl2br(h($contact)) . '</div>' : '')
        . '</div></footer></body></html>';
}
