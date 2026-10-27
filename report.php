<?php
// รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ รอบ 6 เดือน / 12 เดือน (ใช้ประกอบการประเมิน ITA)
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/layout.php';

$years = bp_years(true);
$cur = null;
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
foreach ($years as $y) if ($want && (int)$y['year'] === $want) $cur = $y;
if (!$cur && $years) $cur = $years[0];
$period = isset($_GET['p']) && (int)$_GET['p'] == 6 ? 6 : 12;
if ($cur && !isset($_GET['p']) && bp_fy_quarter_done($cur['year']) < 4) $period = 6;

$BP_NAV_YEAR = $cur ? (int)$cur['year'] : 0;
$BP_NAV_ON = 'report';
bp_head($cur ? 'รายงานผล รอบ ' . $period . ' เดือน ปี ' . (int)$cur['year'] : 'รายงานผล', '<script src="' . h(bp_url('assets/vendor/chart.umd.min.js')) . '"></script>');
if (!$cur) { echo '<main class="wrap main"><div class="empty">ยังไม่มีข้อมูล</div></main>'; bp_foot(); exit; }

$yid = (int)$cur['id'];
$doneQ = bp_fy_quarter_done($cur['year']);
$lastQ = $period == 6 ? 2 : 4;
$pd = bp_period_data($yid, $cur['year'], $period);
$data = bp_year_data($yid);
$funds = bp_funds($yid);
$txt = bp_report_text($yid, $period);
if ($txt && !$txt['published']) $txt = null;
$target = bp_disb_target();
$st = bp_project_statuses();
$units = bp_units();
$fidx = bp_fund_index($funds);
$cum = bp_cum_pct($pd['q'], $pd['approved'], min($doneQ, $lastQ));
$tq = $target[$lastQ - 1];
$pct = $pd['approved'] > 0 ? $pd['spent'] * 100 / $pd['approved'] : 0;
$asof = bp_year_updated($yid);
$notEnded = $doneQ < $lastQ;
?>
<section class="hero slim no-print">
  <div class="wrap">
    <p class="eyebrow">รายงานต่อสาธารณะ</p>
    <h1>รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ</h1>
    <div class="year-tabs">
      <?php foreach ($years as $y) { ?><a class="<?php echo $y['id'] == $cur['id'] ? 'on' : ''; ?>" href="?y=<?php echo (int)$y['year']; ?>&amp;p=<?php echo $period; ?>">ปีงบประมาณ <?php echo (int)$y['year']; ?></a><?php } ?>
    </div>
  </div>
</section>

<main class="wrap main report">
  <div class="report-tools no-print">
    <div class="seg">
      <a class="seg-btn<?php echo $period == 6 ? ' on' : ''; ?>" href="?y=<?php echo (int)$cur['year']; ?>&amp;p=6">รอบ 6 เดือน</a>
      <a class="seg-btn<?php echo $period == 12 ? ' on' : ''; ?>" href="?y=<?php echo (int)$cur['year']; ?>&amp;p=12">รอบ 12 เดือน (ประจำปี)</a>
    </div>
    <span>
      <a class="btn btn-ghost btn-sm" href="<?php echo h(bp_url('export.php?y=' . (int)$cur['year'])); ?>">ดาวน์โหลด Excel</a>
      <button class="btn btn-sm" type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
    </span>
  </div>

  <header class="report-head">
    <?php if (bp_setting('school_name') !== '') { ?><p class="report-school"><?php echo h(bp_setting('school_name')); ?></p><?php } ?>
    <h2>รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ ประจำปีงบประมาณ พ.ศ. <?php echo (int)$cur['year']; ?></h2>
    <p><b>รอบ <?php echo $period; ?> เดือน</b> (<?php echo h(bp_period_label($cur['year'], $period)); ?>)</p>
    <p class="muted small">ข้อมูล ณ วันที่ <?php echo h(bp_thai_date($asof ? $asof : date('Y-m-d'), false, false)); ?>
      <?php if ($notEnded) echo ' · <b class="text-err">รอบนี้ยังไม่สิ้นสุด ตัวเลขเป็นข้อมูลระหว่างรอบ</b>'; ?></p>
  </header>

  <section class="section">
    <h3 class="rp-h">1. ภาพรวม</h3>
    <div class="cards">
      <div class="card" style="--c:var(--brand-2)"><span class="card-label">งบประมาณรายจ่ายทั้งปี</span><span class="card-num sm"><?php echo bp_money($data['total']); ?></span><span class="card-unit">บาท · <?php echo count($data['categories']); ?> หมวด</span></div>
      <?php if ($funds['funds']) { ?>
      <div class="card" style="--c:var(--gold)"><span class="card-label">รายรับที่ได้รับจริง (ณ ปัจจุบัน)</span><span class="card-num sm"><?php echo bp_money($funds['sum']['received']); ?></span>
        <span class="card-unit">บาท · <?php echo bp_pct($funds['sum']['received'], $funds['sum']['estimate'], 1); ?>% ของประมาณการ</span></div>
      <?php } ?>
      <div class="card" style="--c:var(--brand)"><span class="card-label">โครงการตามแผน</span><span class="card-num sm"><?php echo $pd['n']; ?> โครงการ</span><span class="card-unit">งบอนุมัติ <?php echo bp_money($pd['approved']); ?> บาท</span></div>
      <div class="card" style="--c:var(--ok)"><span class="card-label">เบิกจ่ายโครงการถึงสิ้นรอบ</span><span class="card-num sm"><?php echo bp_money($pd['spent']); ?></span>
        <span class="card-unit"><?php echo number_format($pct, 2); ?>% · เป้าหมาย <?php echo $tq; ?>% <?php echo $pct >= $tq ? '<b class="text-ok">✓ ผ่านเป้า</b>' : '<b class="text-err">ต่ำกว่าเป้า</b>'; ?></span>
        <span class="bar"><i style="width:<?php echo min(100, round($pct, 1)); ?>%"></i></span></div>
    </div>
  </section>

  <section class="section">
    <h3 class="rp-h">2. ผลการเบิกจ่ายรายไตรมาสเทียบเป้าหมาย</h3>
    <div class="layout-2 even">
      <div class="panel flush scroll-x">
        <table class="tbl">
          <thead><tr><th>ไตรมาส</th><th class="num">เบิกจ่ายในไตรมาส</th><th class="num">สะสม</th><th class="num">สะสม (%)</th><th class="num">เป้าหมาย (%)</th><th>ผล</th></tr></thead>
          <tbody>
          <?php $c = 0; $qn = array(1 => 'ต.ค.–ธ.ค.', 2 => 'ม.ค.–มี.ค.', 3 => 'เม.ย.–มิ.ย.', 4 => 'ก.ค.–ก.ย.');
          for ($i = 1; $i <= $lastQ; $i++) { $c += $pd['q'][$i]; $cp = $pd['approved'] > 0 ? $c * 100 / $pd['approved'] : 0; $ended = $i <= $doneQ; ?>
            <tr><td class="nowrap">ไตรมาส <?php echo $i; ?> <small class="muted">(<?php echo $qn[$i]; ?>)</small></td>
              <td class="num"><?php echo $pd['has_q'] ? bp_money($pd['q'][$i]) : '–'; ?></td>
              <td class="num"><?php echo $pd['has_q'] ? bp_money($c) : '–'; ?></td>
              <td class="num"><?php echo $pd['has_q'] ? number_format($cp, 2) : '–'; ?></td>
              <td class="num"><?php echo $target[$i - 1]; ?></td>
              <td><?php if (!$pd['has_q']) echo '<span class="muted">ไม่มีข้อมูลรายไตรมาส</span>'; elseif (!$ended) echo '<span class="tag st-doing">ยังไม่สิ้นสุด</span>';
                else echo $cp >= $target[$i - 1] ? '<span class="tag st-done">ผ่านเป้า</span>' : '<span class="tag st-cancel">ต่ำกว่าเป้า ' . number_format($target[$i - 1] - $cp, 2) . '%</span>'; ?></td></tr>
          <?php } ?>
          </tbody>
        </table>
      </div>
      <div class="panel"><div class="chart-box"><canvas id="chCum" aria-label="กราฟการเบิกจ่ายสะสมเทียบเป้าหมาย"></canvas></div></div>
    </div>
    <p class="muted small">เป้าหมายการเบิกจ่ายสะสมตามที่สถานศึกษากำหนด (ไตรมาส 1–4: <?php echo implode('% / ', $target); ?>%) · ยอดเบิกจ่ายนับเฉพาะโครงการตามแผน (ไม่รวมโครงการสำรอง)</p>
  </section>

  <section class="section">
    <h3 class="rp-h">3. การเบิกจ่ายจำแนกตามแหล่งเงินและหน่วยงาน</h3>
    <div class="layout-2 even">
      <?php foreach (array('by_fund' => 'แหล่งเงิน', 'by_div' => 'ฝ่าย') as $k => $label) { ?>
      <div class="panel flush scroll-x">
        <table class="tbl">
          <thead><tr><th><?php echo $label; ?></th><th class="num">โครงการ</th><th class="num">งบอนุมัติ</th><th class="num">เบิกจ่าย</th><th class="num">%</th></tr></thead>
          <tbody>
          <?php foreach ($pd[$k] as $name => $v) { ?>
            <tr><td><?php echo h($name); ?></td><td class="num"><?php echo $v['n']; ?></td><td class="num"><?php echo bp_money($v['approved']); ?></td><td class="num"><?php echo bp_money($v['spent']); ?></td><td class="num"><?php echo bp_pct($v['spent'], $v['approved']); ?></td></tr>
          <?php } ?>
          </tbody>
          <tfoot><tr><th>รวม</th><th class="num"><?php echo $pd['n']; ?></th><th class="num"><?php echo bp_money($pd['approved']); ?></th><th class="num"><?php echo bp_money($pd['spent']); ?></th><th class="num"><?php echo bp_pct($pd['spent'], $pd['approved']); ?></th></tr></tfoot>
        </table>
      </div>
      <?php } ?>
    </div>
  </section>

  <section class="section">
    <h3 class="rp-h">4. ผลการดำเนินงานรายโครงการ</h3>
    <div class="panel flush scroll-x">
      <table class="tbl rp-projects">
        <thead><tr><th>#</th><th>โครงการ / หน่วยงาน</th><th class="num">งบอนุมัติ</th><th class="num">เบิกจ่ายถึงสิ้นรอบ</th><th class="num">%</th><th>สถานะ</th><th>ผลการดำเนินงาน</th></tr></thead>
        <tbody>
        <?php foreach ($pd['projects'] as $i => $p) { $u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : ''; ?>
          <tr>
            <td class="muted"><?php echo $i + 1; ?></td>
            <td><a href="<?php echo h(bp_url('project.php?id=' . (int)$p['id'])); ?>"><?php echo h($p['title']); ?></a><small class="muted d-block"><?php echo h($p['code']); ?><?php echo $u !== '' ? ' · ' . h($u) : ''; ?></small></td>
            <td class="num"><?php echo bp_money($p['approved']); ?></td>
            <td class="num"><?php echo bp_money($p['spent_period']); ?></td>
            <td class="num"><?php echo bp_pct($p['spent_period'], $p['approved'], 1); ?></td>
            <td><span class="tag st-<?php echo h($p['status']); ?>"><?php echo h($st[$p['status']]); ?></span></td>
            <td class="small"><?php if ($p['result_pct'] !== null) echo '<b>' . (int)$p['result_pct'] . '%</b> '; echo h($p['result']); ?>
              <?php if (trim((string)$p['problems']) !== '') { ?><span class="d-block text-err">ปัญหา: <?php echo h($p['problems']); ?></span><?php } ?></td>
          </tr>
        <?php } ?>
        </tbody>
        <tfoot><tr><th></th><th>รวม <?php echo $pd['n']; ?> โครงการ (แล้วเสร็จ <?php echo $pd['n_done']; ?>)</th><th class="num"><?php echo bp_money($pd['approved']); ?></th><th class="num"><?php echo bp_money($pd['spent']); ?></th><th class="num"><?php echo bp_pct($pd['spent'], $pd['approved'], 1); ?></th><th></th><th></th></tr></tfoot>
      </table>
    </div>
  </section>

  <section class="section">
    <h3 class="rp-h">5. สรุปผล ปัญหาอุปสรรค และข้อเสนอแนะ</h3>
    <?php
    $autoProb = array();
    foreach ($pd['projects'] as $p) if (trim((string)$p['problems']) !== '') $autoProb[] = $p['title'] . ': ' . trim($p['problems']);
    if ($pct < $tq) $autoProb[] = 'ผลการเบิกจ่ายสะสม ' . number_format($pct, 2) . '% ต่ำกว่าเป้าหมาย ' . $tq . '%';
    ?>
    <div class="panel rp-text">
      <h4>สรุปผลการดำเนินงาน</h4>
      <p><?php echo $txt && trim($txt['summary']) !== '' ? nl2br(h($txt['summary'])) : 'ดำเนินโครงการตามแผน ' . $pd['n'] . ' โครงการ แล้วเสร็จ ' . $pd['n_done'] . ' โครงการ เบิกจ่ายถึงสิ้นรอบ ' . bp_money($pd['spent']) . ' บาท คิดเป็นร้อยละ ' . number_format($pct, 2) . ' ของงบอนุมัติ'; ?></p>
      <h4>ปัญหา อุปสรรค</h4>
      <?php if ($txt && trim($txt['problems']) !== '') { ?><p><?php echo nl2br(h($txt['problems'])); ?></p>
      <?php } elseif ($autoProb) { ?><ul><?php foreach ($autoProb as $x) echo '<li>' . h($x) . '</li>'; ?></ul>
      <?php } else { ?><p class="muted">ไม่มี</p><?php } ?>
      <h4>ข้อเสนอแนะ / แนวทางการพัฒนา</h4>
      <p><?php echo $txt && trim($txt['suggestions']) !== '' ? nl2br(h($txt['suggestions'])) : '<span class="muted">–</span>'; ?></p>
    </div>
    <div class="sign no-screen">
      <div>ลงชื่อ ................................................ ผู้รายงาน<br>(................................................)<br><?php echo h(bp_setting('unit_name', 'งานวางแผนและงบประมาณ')); ?></div>
      <div>ลงชื่อ ................................................<br>(................................................)<br>ผู้อำนวยการ<?php echo h(bp_setting('school_name')); ?></div>
    </div>
  </section>
</main>
<script>
(function () {
  if (!window.Chart) return;
  var el = document.getElementById('chCum');
  Chart.defaults.font.family = "'Sarabun', system-ui, sans-serif";
  new Chart(el, {
    type: 'line',
    data: {
      labels: ['ไตรมาส 1', 'ไตรมาส 2', 'ไตรมาส 3', 'ไตรมาส 4'].slice(0, <?php echo $lastQ; ?>),
      datasets: [
        { label: 'เบิกจ่ายสะสมจริง (%)', data: <?php echo bp_json(array_slice($cum, 0, $lastQ)); ?>, borderColor: '#b3141b', backgroundColor: '#b3141b', borderWidth: 3, tension: .2, spanGaps: false },
        { label: 'เป้าหมาย (%)', data: <?php echo bp_json(array_slice($target, 0, $lastQ)); ?>, borderColor: '#c9a227', backgroundColor: '#c9a227', borderDash: [6, 5], borderWidth: 2 }
      ]
    },
    options: { maintainAspectRatio: false, animation: false, plugins: { legend: { position: 'bottom' } }, scales: { y: { min: 0, max: 100, ticks: { callback: function (v) { return v + '%'; } } } } }
  });
})();
</script>
<?php bp_foot(); ?>
