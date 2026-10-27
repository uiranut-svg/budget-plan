<?php
// หน้ารายละเอียดโครงการ (สาธารณะ)
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/layout.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$p = bp_row('SELECT p.*, y.year, y.published FROM ' . bp_t('project') . ' p JOIN ' . bp_t('year') . ' y ON y.id=p.year_id WHERE p.id=' . $id);
if (!$p || !$p['published'] || $p['status'] == 'cancel') {
    http_response_code(404);
    bp_head('ไม่พบโครงการ');
    echo '<main class="wrap main"><div class="empty">ไม่พบโครงการนี้ <a href="' . h(bp_url('projects.php')) . '">กลับไปรายการโครงการ</a></div></main>';
    bp_foot();
    exit;
}
$yid = (int)$p['year_id'];
$units = bp_units();
$fidx = bp_fund_index(bp_funds($yid));
$st = bp_project_statuses();
$u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']] : null;
$fd = $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$p['fund_id']] : null;
$files = bp_project_files($id);
$photos = array();
$docs = array();
foreach ($files as $f) { if ($f['kind'] == 'photo') $photos[] = $f; else $docs[] = $f; }
$aligns = array();
$al = array();
foreach (bp_aligns($yid) as $set) foreach ($set['items'] as $it) {
    $al[$it['id']] = array($set['name'], $it['name']);
    foreach ($it['children'] as $c) $al[$c['id']] = array($set['name'], $c['name']);
}
foreach (bp_rows('SELECT align_id FROM ' . bp_t('project_align') . ' WHERE project_id=' . $id) as $r) if (isset($al[$r['align_id']])) $aligns[$al[$r['align_id']][0]][] = $al[$r['align_id']][1];
$procs = bp_rows('SELECT * FROM ' . bp_t('procure') . ' WHERE project_id=' . $id . " AND status<>'cancel' ORDER BY seq");
$pst = bp_procure_statuses();
$approved = (float)$p['approved'];
$spent = (float)$p['spent'];
$pct = $approved > 0 ? $spent * 100 / $approved : 0;
$hasQ = (float)$p['spent_q1'] + (float)$p['spent_q2'] + (float)$p['spent_q3'] + (float)$p['spent_q4'] > 0;
$qsel = array_flip(array_filter(explode(',', (string)$p['quarters'])));
$tfs = bp_transfers($yid, $id);
$tfNet = 0;
foreach ($tfs as $t) $tfNet += ($t['to_id'] == $id ? 1 : -1) * (float)$t['amount'];

$BP_NAV_YEAR = (int)$p['year'];
$BP_NAV_ON = 'projects';
bp_head($p['title']);
?>
<section class="hero slim">
  <div class="wrap">
    <p class="eyebrow"><a class="hero-back" href="<?php echo h(bp_url('projects.php?y=' . (int)$p['year'])); ?>">← โครงการปีงบประมาณ <?php echo (int)$p['year']; ?></a></p>
    <h1><?php echo h($p['title']); ?></h1>
    <p class="lead"><?php echo h($p['code']); ?><?php echo $u ? ' · ' . h($u['parent_id'] ? $u['top'] . ' › ' . $u['name'] : $u['name']) : ''; ?>
      · <span class="tag st-<?php echo h($p['status']); ?>"><?php echo h($st[$p['status']]); ?></span></p>
  </div>
</section>
<main class="wrap main">
  <div class="cards">
    <div class="card" style="--c:var(--brand-2)"><span class="card-label">งบประมาณที่อนุมัติ</span><span class="card-num sm"><?php echo bp_money($approved); ?></span><span class="card-unit">บาท<?php echo abs($tfNet) >= 0.005 ? ' · งบเดิม ' . bp_money($approved - $tfNet) . ' (ปรับ ' . ($tfNet > 0 ? '+' : '') . bp_money($tfNet) . ')' : ((float)$p['requested'] > 0 ? ' · เสนอ ' . bp_money($p['requested']) : ''); ?></span></div>
    <div class="card" style="--c:var(--ok)"><span class="card-label">เบิกจ่ายแล้ว</span><span class="card-num sm"><?php echo bp_money($spent); ?></span><span class="card-unit"><?php echo number_format($pct, 1); ?>% ของงบอนุมัติ</span><span class="bar"><i style="width:<?php echo min(100, round($pct, 1)); ?>%"></i></span></div>
    <div class="card" style="--c:var(--gold)"><span class="card-label">ผลสำเร็จตามตัวชี้วัด</span><span class="card-num sm"><?php echo $p['result_pct'] === null ? '–' : (int)$p['result_pct'] . '%'; ?></span><span class="card-unit"><?php echo h($st[$p['status']]); ?></span>
      <?php if ($p['result_pct'] !== null) { ?><span class="bar"><i style="width:<?php echo (int)$p['result_pct']; ?>%"></i></span><?php } ?></div>
    <div class="card" style="--c:var(--brand)"><span class="card-label">ระยะเวลาดำเนินการ</span><span class="card-num sm"><?php echo $qsel ? h(bp_quarters_label($p['quarters'])) : '–'; ?></span><span class="card-unit">ปีงบประมาณ <?php echo (int)$p['year']; ?></span></div>
  </div>

  <div class="layout-2">
    <div>
      <?php if (trim((string)$p['result']) !== '' || trim((string)$p['problems']) !== '') { ?>
      <div class="panel rp-text">
        <h3>ผลการดำเนินงาน</h3>
        <?php if (trim((string)$p['result']) !== '') { ?><p><?php echo nl2br(h($p['result'])); ?></p><?php } ?>
        <?php if (trim((string)$p['problems']) !== '') { ?><h4>ปัญหา อุปสรรค และแนวทางแก้ไข</h4><p><?php echo nl2br(h($p['problems'])); ?></p><?php } ?>
      </div>
      <?php } ?>
      <div class="panel">
        <h3>รายละเอียดโครงการ</h3>
        <dl class="kv">
          <?php if (trim((string)$p['objective']) !== '') { ?><dt>วัตถุประสงค์</dt><dd><?php echo nl2br(h($p['objective'])); ?></dd><?php } ?>
          <?php if (trim((string)$p['target']) !== '') { ?><dt>เป้าหมาย / ตัวชี้วัด</dt><dd><?php echo nl2br(h($p['target'])); ?></dd><?php } ?>
          <?php if ($p['owner'] !== '') { ?><dt>ผู้รับผิดชอบ</dt><dd><?php echo h($p['owner']); ?></dd><?php } ?>
          <?php if ($u) { ?><dt>หน่วยงาน</dt><dd><?php echo h($u['parent_id'] ? $u['top'] . ' › ' . $u['name'] : $u['name']); ?></dd><?php } ?>
          <?php if ($fd) { ?><dt>แหล่งเงิน</dt><dd><span class="sw" style="background:<?php echo h($fd['color']); ?>"></span><?php echo h($fd['path']); ?></dd><?php } ?>
          <?php if ($p['expense'] !== '') { ?><dt>หมวดรายจ่าย</dt><dd><?php echo h($p['expense']); ?></dd><?php } ?>
          <?php if ($p['note'] !== '') { ?><dt>หมายเหตุ</dt><dd><?php echo h($p['note']); ?></dd><?php } ?>
          <?php if ($p['updated_at']) { ?><dt>ปรับปรุงข้อมูล</dt><dd><?php echo h(bp_thai_date($p['updated_at'], true)); ?></dd><?php } ?>
        </dl>
      </div>
      <?php if ($aligns) { ?>
      <div class="panel">
        <h3>ความสอดคล้องกับยุทธศาสตร์</h3>
        <dl class="kv"><?php foreach ($aligns as $sn => $list) { ?><dt><?php echo h($sn); ?></dt><dd><?php echo h(implode(' · ', $list)); ?></dd><?php } ?></dl>
      </div>
      <?php } ?>
    </div>
    <div class="stack">
      <div class="panel">
        <h3>การเบิกจ่ายรายไตรมาส</h3>
        <?php if ($hasQ) { $c = 0; ?>
        <div class="qbar">
          <?php for ($i = 1; $i <= 4; $i++) { $c += (float)$p['spent_q' . $i]; ?>
          <div><small class="muted">ไตรมาส <?php echo $i; ?></small><b><?php echo bp_money($p['spent_q' . $i]); ?></b><small class="muted">สะสม <?php echo $approved > 0 ? number_format($c * 100 / $approved, 1) : '0'; ?>%</small></div>
          <?php } ?>
        </div>
        <?php } else { ?><p class="muted">ยังไม่มีข้อมูลรายไตรมาส · เบิกจ่ายรวม <?php echo bp_money($spent); ?> บาท</p><?php } ?>
      </div>
      <?php if ($procs) { ?>
      <div class="panel flush">
        <h3 style="padding:14px 16px 0">การจัดซื้อจัดจ้างของโครงการ</h3>
        <table class="tbl">
          <thead><tr><th>รายการ</th><th class="num">วงเงิน</th><th class="num">ราคาที่ตกลง</th></tr></thead>
          <tbody><?php foreach ($procs as $r) { ?><tr><td><?php echo h($r['title']); ?><small class="muted d-block"><?php echo h($r['method']); ?> · <?php echo h($r['status'] == 'done' ? $r['vendor'] : $pst[$r['status']]); ?></small></td>
            <td class="num"><?php echo bp_money($r['budget']); ?></td><td class="num"><?php echo (float)$r['contract_amount'] ? bp_money($r['contract_amount']) : '–'; ?></td></tr><?php } ?></tbody>
        </table>
      </div>
      <?php } ?>
      <?php if ($tfs) { ?>
      <div class="panel">
        <h3>การปรับแผน / โอนงบ</h3>
        <ul class="tf-list">
          <?php foreach ($tfs as $t) { $in = $t['to_id'] == $id; ?>
          <li><b class="<?php echo $in ? 'text-ok' : 'text-err'; ?>"><?php echo ($in ? '+' : '−') . bp_money($t['amount']); ?></b> บาท
            <?php echo $in ? 'รับจาก ' . h(bp_transfer_side($t, 'from')) : 'โอนไป ' . h(bp_transfer_side($t, 'to')); ?>
            <small class="muted d-block"><?php echo h(bp_thai_date($t['doc_date'])); ?> · <?php echo h($t['reason']); ?><?php echo $t['doc_no'] !== '' ? ' · ' . h($t['doc_no']) : ''; ?></small></li>
          <?php } ?>
        </ul>
      </div>
      <?php } ?>
      <?php if ($docs) { ?>
      <div class="panel">
        <h3>เอกสารโครงการ</h3>
        <ul class="doc-list"><?php foreach ($docs as $d) { ?><li><a href="<?php echo h(bp_url('files/projects/' . $d['file'])); ?>" target="_blank" rel="noopener">📄 <?php echo h($d['title'] !== '' ? $d['title'] : $d['orig_name']); ?></a> <small class="muted">PDF <?php echo bp_human_size($d['size']); ?></small></li><?php } ?></ul>
      </div>
      <?php } ?>
    </div>
  </div>

  <?php if ($photos) { ?>
  <section class="section">
    <div class="section-head"><h2>ภาพกิจกรรม</h2></div>
    <div class="gallery">
      <?php foreach ($photos as $ph) { $url = bp_url('files/projects/' . $ph['file']); ?>
      <figure><a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><img src="<?php echo h($url); ?>" alt="<?php echo h($ph['title']); ?>" loading="lazy"></a><?php if ($ph['title'] !== '') { ?><figcaption><?php echo h($ph['title']); ?></figcaption><?php } ?></figure>
      <?php } ?>
    </div>
  </section>
  <?php } ?>
</main>
<?php bp_foot(); ?>
