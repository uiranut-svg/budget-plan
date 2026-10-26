<?php
require dirname(__FILE__) . '/inc/bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$b = $id ? bp_row('SELECT b.*, y.year FROM ' . bp_t('book') . ' b LEFT JOIN ' . bp_t('year') . ' y ON y.id=b.year_id WHERE b.id=' . $id) : null;
if (!$b || !is_file(BP_ROOT . '/files/books/' . $b['file'])) {
    http_response_code(404);
    exit('<meta charset="utf-8">ไม่พบเล่มเอกสารนี้ <a href="' . h(bp_url('index.php')) . '">กลับหน้าแรก</a>');
}
$pdf = bp_url('files/books/' . $b['file']);
$dlName = $b['orig_name'] !== '' ? $b['orig_name'] : ($b['title'] . '.pdf');
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo h($b['title'] . ' · ' . bp_site_name()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo h(bp_url('assets/css/app.css')); ?>?v=2">
</head>
<body class="reader">
<header class="rd-bar">
  <a class="rd-btn" href="<?php echo h(bp_url('index.php')); ?>#books" title="กลับ" aria-label="กลับ">‹</a>
  <div class="rd-title"><b><?php echo h($b['title']); ?></b><small><?php echo $b['year'] ? 'ปีงบประมาณ ' . (int)$b['year'] . ' · ' : ''; ?><?php echo h(bp_setting('school_name')); ?></small></div>
  <a class="rd-btn" href="<?php echo h($pdf); ?>" download="<?php echo h($dlName); ?>" title="ดาวน์โหลด PDF" aria-label="ดาวน์โหลด PDF">⤓</a>
</header>

<main class="rd-stage" id="stage">
  <div class="rd-loading" id="loading"><span class="spin"></span><span id="loadTxt">กำลังเปิดเล่ม…</span></div>
  <div id="book" class="rd-book"></div>
</main>

<nav class="rd-tools" aria-label="เครื่องมือ">
  <button class="rd-btn" id="first" title="หน้าแรก" aria-label="หน้าแรก">«</button>
  <button class="rd-btn" id="prev" title="หน้าก่อน" aria-label="หน้าก่อน">‹</button>
  <label class="rd-page"><input id="pageNo" type="number" min="1" value="1" inputmode="numeric" aria-label="ไปหน้า"><span>/ <b id="pageCount">–</b></span></label>
  <span class="rd-range" id="range"></span>
  <button class="rd-btn" id="next" title="หน้าถัดไป" aria-label="หน้าถัดไป">›</button>
  <button class="rd-btn" id="last" title="หน้าสุดท้าย" aria-label="หน้าสุดท้าย">»</button>
  <span class="rd-sep"></span>
  <button class="rd-btn" id="zoom" title="ขยายหน้านี้" aria-label="ขยายหน้านี้">⊕</button>
  <button class="rd-btn" id="full" title="เต็มจอ" aria-label="เต็มจอ">⛶</button>
</nav>

<div class="rd-zoom" id="zoomBox" hidden>
  <div class="rd-zoom-bar">
    <button class="rd-btn" id="zOut" aria-label="ย่อ">−</button><span id="zPct">150%</span><button class="rd-btn" id="zIn" aria-label="ขยาย">+</button>
    <span class="rd-sep"></span><button class="rd-btn" id="zClose" aria-label="ปิด">✕</button>
  </div>
  <div class="rd-zoom-body" id="zoomBody"></div>
</div>

<script src="<?php echo h(bp_url('assets/vendor/pdf.min.js')); ?>"></script>
<script src="<?php echo h(bp_url('assets/vendor/page-flip.browser.js')); ?>"></script>
<script>window.BOOK = <?php echo bp_json(array('url' => $pdf, 'worker' => bp_url('assets/vendor/pdf.worker.min.js'), 'download' => $pdf)); ?>;</script>
<script src="<?php echo h(bp_url('assets/js/flipbook.js')); ?>?v=2"></script>
</body>
</html>
