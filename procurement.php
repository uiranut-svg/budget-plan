<?php
// แผนการจัดซื้อจัดจ้าง และสรุปผลการจัดซื้อจัดจ้างรายเดือน (แบบ สขร.1)
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/layout.php';

$years = bp_years(true);
$cur = null;
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
foreach ($years as $y) if ($want && (int)$y['year'] === $want) $cur = $y;
if (!$cur && $years) $cur = $years[0];
$tab = isset($_GET['t']) && $_GET['t'] == 'result' ? 'result' : 'plan';
$month = isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m']) ? $_GET['m'] : '';

$rows = $cur ? bp_procures($cur['id'], true) : array();
$st = bp_procure_statuses();
$sum = array('budget' => 0, 'done' => 0, 'contract' => 0, 'ref' => 0);
$byMonth = array();
foreach ($rows as $r) {
    $sum['budget'] += (float)$r['budget'];
    if ($r['status'] == 'done') {
        $sum['done']++;
        $sum['contract'] += (float)$r['contract_amount'];
        $sum['ref'] += (float)$r['budget'];
        $ym = $r['contract_date'] ? substr($r['contract_date'], 0, 7) : '';
        if (!isset($byMonth[$ym])) $byMonth[$ym] = 0;
        $byMonth[$ym]++;
    }
}
$projects = array();
if ($cur) foreach (bp_projects($cur['id']) as $p) $projects[$p['id']] = $p;

$BP_NAV_YEAR = $cur ? (int)$cur['year'] : 0;
$BP_NAV_ON = 'procure';
bp_head('จัดซื้อจัดจ้าง' . ($cur ? ' ปี ' . (int)$cur['year'] : ''));
$base = '?y=' . ($cur ? (int)$cur['year'] : 0);
?>
<section class="hero slim">
  <div class="wrap">
    <p class="eyebrow">ข้อมูลเผยแพร่ต่อสาธารณะ</p>
    <h1>การจัดซื้อจัดจ้าง<?php echo $cur ? ' ปีงบประมาณ ' . (int)$cur['year'] : ''; ?></h1>
    <?php if ($years) { ?><div class="year-tabs"><?php foreach ($years as $y) { ?><a class="<?php echo $cur && $y['id'] == $cur['id'] ? 'on' : ''; ?>" href="?y=<?php echo (int)$y['year']; ?>&amp;t=<?php echo $tab; ?>">ปีงบประมาณ <?php echo (int)$y['year']; ?></a><?php } ?></div><?php } ?>
  </div>
</section>
<main class="wrap main">
<?php if (!$rows) { ?>
  <div class="empty">ยังไม่มีข้อมูลการจัดซื้อจัดจ้างของปีนี้</div>
<?php } else { ?>
  <div class="cards">
    <div class="card" style="--c:var(--brand-2)"><span class="card-label">รายการตามแผน</span><span class="card-num sm"><?php echo count($rows); ?> รายการ</span><span class="card-unit">วงเงินรวม <?php echo bp_money($sum['budget']); ?> บาท</span></div>
    <div class="card" style="--c:var(--ok)"><span class="card-label">ทำสัญญา/ข้อตกลงแล้ว</span><span class="card-num sm"><?php echo $sum['done']; ?> รายการ</span><span class="card-unit"><?php echo bp_pct($sum['done'], count($rows), 0); ?>% ของแผน</span>
      <span class="bar"><i style="width:<?php echo bp_pct($sum['done'], count($rows), 1); ?>%"></i></span></div>
    <div class="card" style="--c:var(--brand)"><span class="card-label">มูลค่าที่ทำสัญญา</span><span class="card-num sm"><?php echo bp_money($sum['contract']); ?></span><span class="card-unit">บาท</span></div>
    <div class="card" style="--c:var(--gold)"><span class="card-label">ประหยัดงบประมาณ</span><span class="card-num sm"><?php echo bp_money(max(0, $sum['ref'] - $sum['contract'])); ?></span><span class="card-unit">บาท · <?php echo bp_pct(max(0, $sum['ref'] - $sum['contract']), $sum['ref']); ?>% ของวงเงินที่ทำสัญญาแล้ว</span></div>
  </div>

  <div class="report-tools">
    <div class="seg">
      <a class="seg-btn<?php echo $tab == 'plan' ? ' on' : ''; ?>" href="<?php echo $base; ?>&amp;t=plan">แผนการจัดซื้อจัดจ้าง</a>
      <a class="seg-btn<?php echo $tab == 'result' ? ' on' : ''; ?>" href="<?php echo $base; ?>&amp;t=result">สรุปผลการจัดซื้อจัดจ้าง (สขร.1)</a>
    </div>
    <span>
      <?php if ($tab == 'result') { ?>
      <select onchange="location.href='<?php echo $base; ?>&t=result&m='+this.value" aria-label="เลือกเดือน">
        <option value="">ทุกเดือน (<?php echo $sum['done']; ?> รายการ)</option>
        <?php foreach (bp_fy_months($cur['year']) as $ym) { ?><option value="<?php echo $ym; ?>" <?php echo $month == $ym ? 'selected' : ''; ?>><?php echo h(bp_thai_month($ym)); ?> (<?php echo isset($byMonth[$ym]) ? $byMonth[$ym] : 0; ?>)</option><?php } ?>
      </select>
      <?php } ?>
      <button class="btn btn-sm" type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
    </span>
  </div>

  <header class="report-head no-screen">
    <p class="report-school"><?php echo h(bp_setting('school_name')); ?></p>
    <h2><?php echo $tab == 'plan' ? 'แผนการจัดซื้อจัดจ้าง ประจำปีงบประมาณ พ.ศ. ' . (int)$cur['year'] : 'สรุปผลการดำเนินการจัดซื้อจัดจ้าง' . ($month ? ' ประจำเดือน ' . bp_thai_month($month) : ' ประจำปีงบประมาณ พ.ศ. ' . (int)$cur['year']); ?></h2>
  </header>

  <?php if ($tab == 'plan') { ?>
  <div class="panel flush scroll-x">
    <table class="tbl">
      <thead><tr><th>ลำดับ</th><th>รายการ</th><th class="num">วงเงินงบประมาณ (บาท)</th><th>วิธีจัดซื้อจัดจ้าง</th><th>ช่วงเวลาที่คาดว่าจะจัดหา</th><th class="hide-sm">โครงการ</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r) { $p = $r['project_id'] && isset($projects[$r['project_id']]) ? $projects[$r['project_id']] : null; ?>
        <tr><td><?php echo (int)$r['seq']; ?></td><td><?php echo h($r['title']); ?></td><td class="num"><?php echo bp_money($r['budget']); ?></td>
          <td><?php echo h($r['method']); ?></td><td class="nowrap"><?php echo h(bp_thai_month($r['plan_month'])); ?></td>
          <td class="hide-sm small"><?php echo $p ? '<a href="' . h(bp_url('project.php?id=' . (int)$p['id'])) . '">' . h($p['code']) . '</a>' : ''; ?></td>
          <td><span class="tag st-<?php echo $r['status'] == 'done' ? 'done' : ($r['status'] == 'doing' ? 'doing' : 'plan'); ?>"><?php echo h($st[$r['status']]); ?></span></td></tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><th></th><th>รวม <?php echo count($rows); ?> รายการ</th><th class="num"><?php echo bp_money($sum['budget']); ?></th><th></th><th></th><th class="hide-sm"></th><th></th></tr></tfoot>
    </table>
  </div>
  <?php } else {
      $done = array();
      foreach ($rows as $r) if ($r['status'] == 'done' && (!$month || substr((string)$r['contract_date'], 0, 7) == $month)) $done[] = $r;
  ?>
  <?php if (!$done) { ?><div class="empty">ไม่มีการจัดซื้อจัดจ้างที่ทำสัญญาแล้ว<?php echo $month ? 'ในเดือน ' . h(bp_thai_month($month)) : ''; ?></div><?php } else { ?>
  <div class="panel flush scroll-x">
    <table class="tbl skr1">
      <thead><tr><th>ลำดับ</th><th>งานที่จัดซื้อหรือจัดจ้าง</th><th class="num">วงเงินที่จัดซื้อ/จ้าง</th><th class="num">ราคากลาง</th><th>วิธีซื้อหรือจ้าง</th><th>รายชื่อผู้เสนอราคาและราคาที่เสนอ</th><th>ผู้ได้รับการคัดเลือกและราคาที่ตกลง</th><th>เหตุผลที่คัดเลือกโดยสังเขป</th><th>เลขที่และวันที่ของสัญญา</th></tr></thead>
      <tbody>
      <?php foreach ($done as $i => $r) { ?>
        <tr><td><?php echo $i + 1; ?></td><td><?php echo h($r['title']); ?></td><td class="num"><?php echo bp_money($r['budget']); ?></td>
          <td class="num"><?php echo (float)$r['ref_price'] ? bp_money($r['ref_price']) : bp_money($r['budget']); ?></td><td><?php echo h($r['method']); ?></td>
          <td class="small"><?php echo $r['bidders'] !== '' ? nl2br(h($r['bidders'])) : h($r['vendor']) . ' ' . bp_money($r['contract_amount']); ?></td>
          <td class="small"><?php echo h($r['vendor']); ?><br><b><?php echo bp_money($r['contract_amount']); ?></b> บาท</td>
          <td class="small"><?php echo h($r['reason']); ?></td>
          <td class="small nowrap"><?php echo h($r['contract_no']); ?><br><?php echo h(bp_thai_date($r['contract_date'])); ?></td></tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php } ?>
  <?php } ?>
  <p class="muted small">ข้อมูลตามประกาศเผยแพร่ของสถานศึกษา · สรุปผลรายเดือนจัดตามวันที่ทำสัญญา/ข้อตกลง</p>
<?php } ?>
</main>
<?php bp_foot(); ?>
