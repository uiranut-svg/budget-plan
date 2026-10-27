<?php
// ดาวน์โหลดข้อมูลปีที่เผยแพร่เป็นไฟล์ Excel (ข้อมูลเปิด)
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/excel.php';
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
$y = null;
foreach (bp_years(true) as $r) if (!$y && (!$want || (int)$r['year'] === $want)) $y = $r;
if (!$y) { http_response_code(404); exit('ไม่พบข้อมูลปีนี้'); }
bp_excel_send($y['id'], $y['year'], true);
