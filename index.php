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
$data = $cur ? bp_year_data($cur['id']) : array('categories' => array(), 'total' => 0);
$depts = bp_by_department($data);

// เปรียบเทียบย้อนหลังสูงสุด 5 ปี (เก่า → ใหม่) จับคู่หมวดด้วยชื่อ
$cmpYears = array_reverse(array_slice($years, 0, 5));
$cmp = array();      // ชื่อหมวด => array(ปี => ยอด)
$cmpTotal = array(); // ปี => ยอดรวม
$cmpColor = array();
foreach ($cmpYears as $y) {
    $d = ((int)$y['id'] === ($cur ? (int)$cur['id'] : 0)) ? $data : bp_year_data($y['id']);
    $cmpTotal[$y['year']] = $d['total'];
    foreach ($d['categories'] as $c) {
        $k = trim($c['name']);
        $cmp[$k][$y['year']] = $c['total'];
        if (!isset($cmpColor[$k])) $cmpColor[$k] = $c['color'];
    }
}
// เรียงหมวดตามปีที่เลือก
if ($cur) {
    $order = array();
    foreach ($data['categories'] as $c) $order[trim($c['name'])] = true;
    foreach ($cmp as $k => $vv) if (!isset($order[$k])) $order[$k] = true;
    $tmp = array();
    foreach ($order as $k => $t) $tmp[$k] = $cmp[$k];
    $cmp = $tmp;
}

$books = bp_rows('SELECT b.*, y.year FROM ' . bp_t('book') . ' b LEFT JOIN ' . bp_t('year') . ' y ON y.id=b.year_id ORDER BY y.year DESC, b.id DESC');

$ext = '<script src="' . h(bp_url('assets/vendor/chart.umd.min.js')) . '"></script>';
bp_head('', $ext);
?>
<section class="hero">
  <div class="wrap">
    <p class="eyebrow">ข้อมูลเผยแพร่ต่อสาธารณะ</p>
    <h1><?php echo h(bp_site_name()); ?></h1>
    <p class="lead"><?php echo h(bp_setting('intro')); ?></p>
    <?php if ($years) { ?>
    <div class="year-tabs" role="tablist" aria-label="เลือกปีงบประมาณ">
      <?php foreach ($years as $y) { ?>
        <a role="tab" class="<?php echo ($cur && $y['id'] == $cur['id']) ? 'on' : ''; ?>" href="?y=<?php echo (int)$y['year']; ?>#summary">ปีงบประมาณ <?php echo (int)$y['year']; ?></a>
      <?php } ?>
    </div>
    <?php } ?>
  </div>
</section>

<main class="wrap main">
<?php if (!$cur) { ?>
  <div class="empty">ยังไม่มีข้อมูลงบประมาณ</div>
<?php } else { ?>

  <section id="summary" class="section">
    <div class="section-head">
      <h2>ภาพรวมงบประมาณ ปี <?php echo (int)$cur['year']; ?></h2>
      <?php if ($cur['title'] !== '') { ?><p class="muted"><?php echo h($cur['title']); ?></p><?php } ?>
    </div>
    <div class="cards">
      <div class="card card-total">
        <span class="card-label">งบประมาณรวมทั้งสิ้น</span>
        <span class="card-num"><?php echo bp_money($data['total']); ?></span>
        <span class="card-unit">บาท</span>
        <?php
        $prev = null;
        foreach ($years as $i => $y) if ($y['id'] == $cur['id'] && isset($years[$i + 1])) $prev = $years[$i + 1];
        if ($prev) {
            $pt = isset($cmpTotal[$prev['year']]) ? $cmpTotal[$prev['year']] : bp_year_data($prev['id']);
            if (is_array($pt)) $pt = $pt['total'];
            if ($pt > 0) {
                $ch = ($data['total'] - $pt) * 100 / $pt;
                echo '<span class="chg ' . ($ch >= 0 ? 'up' : 'down') . '">' . ($ch >= 0 ? '▲ ' : '▼ ') . number_format(abs($ch), 2) . '% จากปี ' . (int)$prev['year'] . '</span>';
            }
        }
        ?>
      </div>
      <?php foreach ($data['categories'] as $c) { ?>
      <div class="card" style="--c:<?php echo h($c['color']); ?>">
        <span class="card-label"><i class="dot"></i><?php echo h($c['name']); ?></span>
        <span class="card-num sm"><?php echo bp_money($c['total']); ?></span>
        <span class="card-unit">บาท · <?php echo bp_pct($c['total'], $data['total']); ?>%</span>
        <span class="bar"><i style="width:<?php echo bp_pct($c['total'], $data['total'], 1); ?>%"></i></span>
      </div>
      <?php } ?>
    </div>

    <div class="grid2">
      <div class="panel">
        <h3>สัดส่วนงบประมาณ</h3>
        <div class="chart-box"><canvas id="chShare" aria-label="กราฟสัดส่วนงบประมาณ"></canvas></div>
      </div>
      <div class="panel">
        <h3>ยอดตามหมวด</h3>
        <div class="chart-box"><canvas id="chCat" aria-label="กราฟยอดตามหมวด"></canvas></div>
      </div>
    </div>
    <?php if (trim((string)$cur['note']) !== '') { ?><div class="note"><?php echo nl2br(h($cur['note'])); ?></div><?php } ?>
  </section>

  <section id="detail" class="section">
    <div class="section-head">
      <h2>รายละเอียดงบประมาณ ปี <?php echo (int)$cur['year']; ?></h2>
      <div class="search"><input type="search" id="q" placeholder="ค้นหารายการ / โครงการ / หน่วยงาน" aria-label="ค้นหา"></div>
    </div>
    <div class="panel flush">
      <table class="tbl" id="tblDetail">
        <thead><tr><th>หมวด / รายการ</th><th class="hide-sm">หน่วยงาน</th><th class="num">จำนวนเงิน (บาท)</th><th class="num">ร้อยละ</th></tr></thead>
        <?php foreach ($data['categories'] as $ci => $c) { ?>
        <tbody class="cat" data-cat="<?php echo $ci; ?>">
          <tr class="cat-row<?php echo $c['items'] ? ' has-items' : ''; ?>" <?php echo $c['items'] ? 'tabindex="0" aria-expanded="false"' : ''; ?>>
            <td><span class="sw" style="background:<?php echo h($c['color']); ?>"></span><b><?php echo h($c['name']); ?></b>
              <?php if ($c['items']) { ?><span class="cnt"><?php echo count($c['items']); ?> รายการ</span><span class="caret">▾</span><?php } ?></td>
            <td class="hide-sm"></td>
            <td class="num"><b><?php echo bp_money($c['total']); ?></b></td>
            <td class="num"><?php echo bp_pct($c['total'], $data['total']); ?></td>
          </tr>
          <?php foreach ($c['items'] as $it) { ?>
          <tr class="item-row" hidden>
            <td><?php echo h($it['name']); ?><?php if ($it['note'] !== '') { ?><small class="muted d-block"><?php echo h($it['note']); ?></small><?php } ?>
              <?php if ($it['department'] !== '') { ?><small class="muted show-sm"><?php echo h($it['department']); ?></small><?php } ?></td>
            <td class="hide-sm"><?php echo h($it['department']); ?></td>
            <td class="num"><?php echo bp_money($it['amount']); ?></td>
            <td class="num muted"><?php echo bp_pct($it['amount'], $data['total']); ?></td>
          </tr>
          <?php } ?>
        </tbody>
        <?php } ?>
        <tfoot><tr><th>รวมทั้งสิ้น</th><th class="hide-sm"></th><th class="num"><?php echo bp_money($data['total']); ?></th><th class="num">100.00</th></tr></tfoot>
      </table>
    </div>
    <p class="muted small">กดที่หมวดเพื่อดูรายการย่อย</p>

    <?php if (count($depts) > 1 || (count($depts) == 1 && !isset($depts['ไม่ระบุหน่วยงาน']))) { ?>
    <h3 class="sub">ยอดตามหน่วยงาน</h3>
    <div class="panel flush">
      <table class="tbl">
        <thead><tr><th>หน่วยงาน</th><th class="num">จำนวนเงิน (บาท)</th><th class="num">ร้อยละ</th></tr></thead>
        <tbody>
        <?php foreach ($depts as $dn => $amt) { ?>
          <tr><td><?php echo h($dn); ?></td><td class="num"><?php echo bp_money($amt); ?></td><td class="num"><?php echo bp_pct($amt, $data['total']); ?></td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
    <?php } ?>
  </section>

  <?php if (count($cmpYears) >= 2) { $ys = array_keys($cmpTotal); $yA = $ys[count($ys) - 2]; $yB = $ys[count($ys) - 1]; ?>
  <section id="compare" class="section">
    <div class="section-head"><h2>เปรียบเทียบงบประมาณรายปี</h2></div>
    <div class="panel"><div class="chart-box tall"><canvas id="chCmp" aria-label="กราฟเปรียบเทียบรายปี"></canvas></div></div>
    <div class="panel flush scroll-x">
      <table class="tbl">
        <thead><tr><th>หมวด</th><?php foreach ($ys as $yy) { ?><th class="num"><?php echo (int)$yy; ?></th><?php } ?><th class="num">เปลี่ยนแปลง <?php echo (int)$yA; ?>→<?php echo (int)$yB; ?></th></tr></thead>
        <tbody>
        <?php foreach ($cmp as $name => $vals) { ?>
          <tr><td><span class="sw" style="background:<?php echo h($cmpColor[$name]); ?>"></span><?php echo h($name); ?></td>
          <?php foreach ($ys as $yy) { $a = isset($vals[$yy]) ? $vals[$yy] : null; ?>
            <td class="num"><?php echo $a === null ? '<span class="muted">–</span>' : bp_money($a) . '<small class="muted d-block">' . bp_pct($a, $cmpTotal[$yy]) . '%</small>'; ?></td>
          <?php } ?>
          <td class="num"><?php
            $a = isset($vals[$yA]) ? $vals[$yA] : 0; $b = isset($vals[$yB]) ? $vals[$yB] : 0;
            if ($a > 0) { $p = ($b - $a) * 100 / $a; echo '<span class="chg ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '▲ ' : '▼ ') . number_format(abs($p), 2) . '%</span>'; }
            else echo $b > 0 ? '<span class="chg up">ใหม่</span>' : '<span class="muted">–</span>';
          ?></td></tr>
        <?php } ?>
        </tbody>
        <tfoot><tr><th>รวมทั้งสิ้น</th><?php foreach ($ys as $yy) { ?><th class="num"><?php echo bp_money($cmpTotal[$yy]); ?></th><?php } ?>
          <th class="num"><?php $a = $cmpTotal[$yA]; $b = $cmpTotal[$yB]; if ($a > 0) { $p = ($b - $a) * 100 / $a; echo '<span class="chg ' . ($p >= 0 ? 'up' : 'down') . '">' . ($p >= 0 ? '▲ ' : '▼ ') . number_format(abs($p), 2) . '%</span>'; } ?></th></tr></tfoot>
      </table>
    </div>
  </section>
  <?php } ?>
<?php } ?>

  <section id="books" class="section">
    <div class="section-head"><h2>เล่มแผนปฏิบัติราชการ / เอกสาร</h2><p class="muted">กดที่เล่มเพื่อเปิดอ่านแบบพลิกหน้า</p></div>
    <?php if (!$books) { ?><div class="empty">ยังไม่มีเล่มแผน</div><?php } else { ?>
    <div class="shelf">
      <?php foreach ($books as $b) { ?>
      <a class="book" href="<?php echo h(bp_url('book.php?id=' . (int)$b['id'])); ?>">
        <span class="cover"><canvas data-pdf="<?php echo h(bp_url('files/books/' . $b['file'])); ?>"></canvas><span class="cover-ph"><?php echo h($b['title']); ?></span></span>
        <span class="book-title"><?php echo h($b['title']); ?></span>
        <span class="book-meta"><?php echo $b['year'] ? 'ปี ' . (int)$b['year'] . ' · ' : ''; ?>PDF <?php echo bp_human_size($b['size']); ?></span>
      </a>
      <?php } ?>
    </div>
    <?php } ?>
  </section>
</main>

<script>
window.BP = <?php
$share = array('labels' => array(), 'values' => array(), 'colors' => array());
foreach ($data['categories'] as $c) { $share['labels'][] = $c['name']; $share['values'][] = $c['total']; $share['colors'][] = $c['color']; }
$cmpJs = array('years' => array_keys($cmpTotal), 'series' => array());
foreach ($cmp as $name => $vals) {
    $row = array();
    foreach ($cmpTotal as $yy => $t) $row[] = isset($vals[$yy]) ? $vals[$yy] : 0;
    $cmpJs['series'][] = array('name' => $name, 'color' => $cmpColor[$name], 'values' => $row);
}
echo bp_json(array('share' => $share, 'cmp' => $cmpJs, 'pdfjs' => bp_url('assets/vendor/pdf.min.js'), 'worker' => bp_url('assets/vendor/pdf.worker.min.js')));
?>;
</script>
<script src="<?php echo h(bp_url('assets/js/app.js')); ?>?v=1"></script>
<?php bp_foot(); ?>
