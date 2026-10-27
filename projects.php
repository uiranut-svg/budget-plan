<?php
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/layout.php';

$years = bp_years(true);
$cur = null;
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
foreach ($years as $y) {
    if ($want && (int)$y['year'] === $want) $cur = $y;
}
if (!$cur && $years) $cur = $years[0];

$projects = $cur ? bp_projects($cur['id'], true) : array();
$units = bp_units();
$funds = $cur ? bp_funds($cur['id']) : array('funds' => array());
$fidx = bp_fund_index($funds);
$st = bp_project_statuses();
unset($st['cancel']);
$pj = bp_project_summary($projects);

// ข้อความสอดคล้อง: id => "ชุด: ข้อ"
$alignName = array();
if ($cur) {
    foreach (bp_aligns($cur['id']) as $set) {
        foreach ($set['items'] as $it) {
            $alignName[$it['id']] = array($set['name'], $it['name']);
            foreach ($it['children'] as $ch) $alignName[$ch['id']] = array($set['name'], $ch['name']);
        }
    }
}
// ตัวเลือกกรอง: เฉพาะหน่วยงาน/แหล่งเงินที่มีโครงการ
$usedUnit = array();
$usedFund = array();
foreach ($projects as $p) {
    if ($p['unit_id']) $usedUnit[$p['unit_id']] = true;
    if ($p['fund_id'] && isset($fidx[$p['fund_id']])) $usedFund[$fidx[$p['fund_id']]['top_id']] = true;
}

$BP_NAV_YEAR = $cur ? (int)$cur['year'] : 0;
$BP_NAV_ON = 'projects';
bp_head('โครงการ' . ($cur ? ' ปี ' . (int)$cur['year'] : ''));
?>
<section class="hero slim">
  <div class="wrap">
    <p class="eyebrow">ข้อมูลเผยแพร่ต่อสาธารณะ</p>
    <h1>โครงการตามแผนปฏิบัติราชการ<?php echo $cur ? ' ปี ' . (int)$cur['year'] : ''; ?></h1>
    <?php if ($years) { ?>
    <div class="year-tabs" role="tablist" aria-label="เลือกปีงบประมาณ">
      <?php foreach ($years as $y) { ?>
        <a role="tab" class="<?php echo ($cur && $y['id'] == $cur['id']) ? 'on' : ''; ?>" href="?y=<?php echo (int)$y['year']; ?>">ปีงบประมาณ <?php echo (int)$y['year']; ?></a>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</section>

<main class="wrap main">
<?php if (!$projects) { ?>
  <div class="empty">ยังไม่มีข้อมูลโครงการของปีนี้</div>
<?php } else { ?>
  <div class="cards">
    <div class="card" style="--c:var(--brand-2)"><span class="card-label">จำนวนโครงการ</span><span class="card-num sm"><?php echo number_format($pj['n']); ?></span><span class="card-unit">โครงการ<?php echo $pj['n_reserve'] ? ' · สำรอง ' . $pj['n_reserve'] : ''; ?></span></div>
    <div class="card" style="--c:var(--brand)"><span class="card-label">งบประมาณที่อนุมัติ</span><span class="card-num sm"><?php echo bp_money($pj['approved']); ?></span><span class="card-unit">บาท</span></div>
    <div class="card" style="--c:var(--ok)"><span class="card-label">เบิกจ่ายแล้ว</span><span class="card-num sm"><?php echo bp_money($pj['spent']); ?></span>
      <span class="card-unit"><?php echo bp_pct($pj['spent'], $pj['approved']); ?>% ของงบอนุมัติ</span><span class="bar"><i style="width:<?php echo min(100, (float)bp_pct($pj['spent'], $pj['approved'], 1)); ?>%"></i></span></div>
    <div class="card" style="--c:var(--gold)"><span class="card-label">ดำเนินการแล้วเสร็จ</span><span class="card-num sm"><?php echo $pj['n_done']; ?> / <?php echo $pj['n']; ?></span><span class="card-unit">โครงการ</span></div>
  </div>

  <div class="filters" role="search">
    <input type="search" id="fq" placeholder="ค้นหาชื่อโครงการ / รหัส / ผู้รับผิดชอบ" aria-label="ค้นหาโครงการ">
    <select id="funit" aria-label="กรองตามหน่วยงาน">
      <option value="">ทุกหน่วยงาน</option>
      <?php foreach (bp_unit_tree() as $t) {
          $kids = array();
          foreach ($t['children'] as $ch) if (isset($usedUnit[$ch['id']])) $kids[] = $ch;
          if (!$kids && !isset($usedUnit[$t['id']])) continue; ?>
        <option value="d<?php echo (int)$t['id']; ?>"><?php echo h($t['name']); ?> (ทั้งฝ่าย)</option>
        <?php foreach ($kids as $ch) { ?><option value="u<?php echo (int)$ch['id']; ?>">&nbsp;&nbsp;– <?php echo h($ch['name']); ?></option><?php } ?>
      <?php } ?>
    </select>
    <select id="ffund" aria-label="กรองตามแหล่งเงิน">
      <option value="">ทุกแหล่งเงิน</option>
      <?php foreach ($funds['funds'] as $f) if (isset($usedFund[$f['id']])) { ?><option value="<?php echo (int)$f['id']; ?>"><?php echo h($f['name']); ?></option><?php } ?>
    </select>
    <select id="fst" aria-label="กรองตามสถานะ">
      <option value="">ทุกสถานะ</option>
      <?php foreach ($st as $k => $v) if (isset($pj['status'][$k])) { ?><option value="<?php echo h($k); ?>"><?php echo h($v); ?> (<?php echo $pj['status'][$k]; ?>)</option><?php } ?>
    </select>
    <select id="fqt" aria-label="กรองตามไตรมาส">
      <option value="">ทุกไตรมาส</option>
      <?php for ($i = 1; $i <= 4; $i++) { ?><option value="<?php echo $i; ?>">ไตรมาส <?php echo $i; ?></option><?php } ?>
    </select>
  </div>
  <p class="pj-sum" id="pjSum" aria-live="polite"></p>

  <div class="panel flush scroll-x">
    <table class="tbl" id="tblPj">
      <thead><tr><th>โครงการ</th><th class="hide-sm">แหล่งเงิน</th><th class="num">งบอนุมัติ (บาท)</th><th class="hide-sm">เบิกจ่าย</th><th class="hide-sm">สถานะ</th></tr></thead>
      <tbody>
      <?php foreach ($projects as $p) {
          $u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']] : null;
          $fd = $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$p['fund_id']] : null;
          $pct = $p['approved'] > 0 ? min(100, $p['spent'] * 100 / $p['approved']) : 0;
          $search = $p['code'] . ' ' . $p['title'] . ' ' . $p['owner'] . ' ' . ($u ? $u['name'] : '');
      ?>
        <tr class="pj-row" tabindex="0" aria-expanded="false"
            data-q="<?php echo h(mb_strtolower($search, 'UTF-8')); ?>" data-unit="<?php echo $u ? (int)$u['id'] : 0; ?>" data-div="<?php echo $u ? (int)$u['top_id'] : 0; ?>"
            data-fund="<?php echo $fd ? (int)$fd['top_id'] : 0; ?>" data-st="<?php echo h($p['status']); ?>" data-qt="<?php echo h($p['quarters']); ?>"
            data-ap="<?php echo $p['status'] == 'reserve' ? 0 : $p['approved']; ?>" data-sp="<?php echo $p['spent']; ?>">
          <td><span class="pj-title"><?php echo h($p['title']); ?></span><span class="caret">▾</span>
            <span class="pj-meta"><?php echo h($p['code']); ?><?php echo $u ? ' · ' . h($u['name']) : ''; ?></span>
            <?php if ($fd) { ?><span class="pj-meta show-sm"><?php echo h($fd['path']); ?></span><?php } ?>
            <span class="show-sm"><span class="tag st-<?php echo h($p['status']); ?>"><?php echo h($st[$p['status']]); ?></span></span></td>
          <td class="hide-sm"><?php if ($fd) { ?><span class="sw" style="background:<?php echo h($fd['color']); ?>"></span><?php echo h($fd['path']); ?><?php } ?></td>
          <td class="num"><?php echo $p['status'] == 'reserve' ? '<span class="muted">' . bp_money($p['requested']) . '<small class="d-block">ที่เสนอ</small></span>' : bp_money($p['approved']); ?></td>
          <td class="hide-sm pj-prog"><?php if ($p['approved'] > 0) { ?><?php echo bp_money($p['spent']); ?> <small class="muted">(<?php echo number_format($pct, 1); ?>%)</small><span class="bar"><i style="width:<?php echo round($pct, 1); ?>%"></i></span><?php } else echo '<span class="muted">–</span>'; ?></td>
          <td class="hide-sm"><span class="tag st-<?php echo h($p['status']); ?>"><?php echo h($st[$p['status']]); ?></span></td>
        </tr>
        <tr class="pj-detail" hidden>
          <td colspan="5">
            <dl class="pj-dl">
              <?php if (trim((string)$p['objective']) !== '') { ?><dt>วัตถุประสงค์</dt><dd><?php echo nl2br(h($p['objective'])); ?></dd><?php } ?>
              <?php if (trim((string)$p['target']) !== '') { ?><dt>เป้าหมาย</dt><dd><?php echo nl2br(h($p['target'])); ?></dd><?php } ?>
              <?php if ($p['owner'] !== '') { ?><dt>ผู้รับผิดชอบ</dt><dd><?php echo h($p['owner']); ?></dd><?php } ?>
              <?php if ($u) { ?><dt>หน่วยงาน</dt><dd><?php echo h($u['parent_id'] ? $u['top'] . ' › ' . $u['name'] : $u['name']); ?></dd><?php } ?>
              <?php if ($fd) { ?><dt>แหล่งเงิน</dt><dd><?php echo h($fd['path']); ?></dd><?php } ?>
              <?php if ($p['expense'] !== '') { ?><dt>หมวดรายจ่าย</dt><dd><?php echo h($p['expense']); ?></dd><?php } ?>
              <dt>งบประมาณ</dt><dd>เสนอ <?php echo bp_money($p['requested']); ?> · อนุมัติ <?php echo bp_money($p['approved']); ?> · เบิกจ่าย <?php echo bp_money($p['spent']); ?> บาท</dd>
              <?php if ($p['quarters'] !== '') { ?><dt>ระยะเวลา</dt><dd><?php echo h(bp_quarters_label($p['quarters'])); ?></dd><?php } ?>
              <?php if ($p['align_ids']) { $grp = array(); foreach ($p['align_ids'] as $a) if (isset($alignName[$a])) $grp[$alignName[$a][0]][] = $alignName[$a][1]; ?>
              <dt>ความสอดคล้อง</dt><dd><ul><?php foreach ($grp as $sn => $list) { ?><li><b><?php echo h($sn); ?>:</b> <?php echo h(implode(' · ', $list)); ?></li><?php } ?></ul></dd>
              <?php } ?>
              <?php if ($p['note'] !== '') { ?><dt>หมายเหตุ</dt><dd><?php echo h($p['note']); ?></dd><?php } ?>
              <?php if (trim((string)$p['result']) !== '') { ?><dt>ผลการดำเนินงาน</dt><dd><?php echo $p['result_pct'] !== null ? '<b>' . (int)$p['result_pct'] . '%</b> ' : ''; ?><?php echo h($p['result']); ?></dd><?php } ?>
            </dl>
            <p><a class="btn btn-sm" href="<?php echo h(bp_url('project.php?id=' . (int)$p['id'])); ?>">ดูหน้ารายละเอียดโครงการ รูปกิจกรรม และเอกสาร →</a></p>
          </td>
        </tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><th>รวมที่แสดง <span id="pjN"></span></th><th class="hide-sm"></th><th class="num" id="pjAp"></th><th class="hide-sm" id="pjSp"></th><th class="hide-sm"></th></tr></tfoot>
    </table>
  </div>
  <p class="muted small">กดที่โครงการเพื่อดูรายละเอียด · โครงการสำรองแสดงยอดที่เสนอ (ยังไม่นับรวมงบอนุมัติ)</p>
<?php } ?>
</main>
<script>
(function () {
  'use strict';
  var rows = Array.prototype.slice.call(document.querySelectorAll('#tblPj .pj-row'));
  if (!rows.length) return;
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var $ = function (id) { return document.getElementById(id); };
  function toggle(r, open) {
    if (open === undefined) open = r.getAttribute('aria-expanded') !== 'true';
    r.setAttribute('aria-expanded', open ? 'true' : 'false');
    r.nextElementSibling.hidden = !open;
  }
  rows.forEach(function (r) {
    r.addEventListener('click', function () { toggle(r); });
    r.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(r); } });
  });
  function apply() {
    var q = $('fq').value.trim().toLowerCase(), u = $('funit').value, f = $('ffund').value, s = $('fst').value, qt = $('fqt').value;
    var n = 0, ap = 0, sp = 0;
    rows.forEach(function (r) {
      var d = r.dataset, ok = true;
      if (q && d.q.indexOf(q) < 0) ok = false;
      if (ok && u) ok = u.charAt(0) === 'd' ? d.div === u.slice(1) : d.unit === u.slice(1);
      if (ok && f) ok = d.fund === f;
      if (ok && s) ok = d.st === s;
      if (ok && qt) ok = (',' + d.qt + ',').indexOf(',' + qt + ',') >= 0;
      r.hidden = !ok;
      if (!ok) toggle(r, false);
      if (ok) { n++; ap += +d.ap; sp += +d.sp; }
    });
    $('pjN').textContent = n + ' โครงการ';
    $('pjAp').textContent = money(ap);
    $('pjSp').textContent = money(sp) + (ap ? ' (' + (sp * 100 / ap).toFixed(1) + '%)' : '');
    $('pjSum').textContent = n === rows.length ? 'แสดงทั้งหมด ' + n + ' โครงการ' : 'พบ ' + n + ' จาก ' + rows.length + ' โครงการ';
  }
  ['fq', 'funit', 'ffund', 'fst', 'fqt'].forEach(function (id) { $(id).addEventListener(id === 'fq' ? 'input' : 'change', apply); });
  apply();
})();
</script>
<?php bp_foot(); ?>
