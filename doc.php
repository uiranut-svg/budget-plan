<?php
// เอกสารทางการสำหรับพิมพ์/บันทึกเป็น PDF (A4 แบบหนังสือราชการ)
//   ?d=report&y=&p=6|12  รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ (ปก คำนำ เนื้อหา ลงนาม)
//   ?d=plan&y=           แผนปฏิบัติการประจำปี (ปก สรุปงบ ตารางโครงการตามฝ่าย)
//   ?d=procure&y=        แผนการจัดซื้อจัดจ้าง
//   ?d=sk1&y=&m=YYYY-MM  สรุปผลการจัดซื้อจัดจ้าง แบบ สขร.1 (ไม่ระบุเดือน = ทุกเดือนที่ผ่านมาของปี เดือนละหน้า)
require dirname(__FILE__) . '/inc/bootstrap.php';

$years = bp_years(true);
$cur = null;
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
foreach ($years as $yy) if ($want && (int)$yy['year'] === $want) $cur = $yy;
if (!$cur && $years) $cur = $years[0];
$d = isset($_GET['d']) ? (string)$_GET['d'] : 'report';
if (!in_array($d, array('report', 'plan', 'procure', 'sk1'), true)) $d = 'report';
if (!$cur) { http_response_code(404); echo 'ยังไม่มีปีงบประมาณที่เผยแพร่'; exit; }

$Y = (int)$cur['year'];
$yid = (int)$cur['id'];
$school = bp_setting('school_name');
$unitName = bp_setting('unit_name', 'งานวางแผนและงบประมาณ');
$logo = bp_setting('doc_logo');
$logoUrl = $logo !== '' && preg_match('/^doc-logo-[0-9a-f]{12}\.(png|jpg)$/', $logo) && is_file(BP_ROOT . '/files/' . $logo) ? bp_url('files/' . $logo) : '';
$units = bp_units();
$funds = bp_funds($yid);
$fidx = bp_fund_index($funds);
$qn = array(1 => 'ต.ค.–ธ.ค.', 2 => 'ม.ค.–มี.ค.', 3 => 'เม.ย.–มิ.ย.', 4 => 'ก.ค.–ก.ย.');

/** ผู้ลงนามจากหน้าตั้งค่า: array(ชื่อ, ตำแหน่ง) */
function doc_signer($key, $defPos)
{
    $pos = trim(bp_setting('doc_' . $key . '_pos'));
    return array(trim(bp_setting('doc_' . $key . '_name')), $pos !== '' ? $pos : $defPos);
}
/** ช่องลงนามแบบราชการ */
function doc_sign($s, $label = '')
{
    $name = $s[0] !== '' ? '(' . h($s[0]) . ')' : '(' . str_repeat('.', 52) . ')';
    return '<div class="sign">' . ($label !== '' ? '<div class="lbl">' . h($label) . '</div>' : '')
        . '<div class="dots">(ลงชื่อ) ' . str_repeat('.', 52) . '</div><div>' . $name . '</div><div>' . nl2br(h($s[1])) . '</div></div>';
}
function doc_money($v)
{
    return bp_money($v);
}
function doc_date_long($ymd)
{
    return bp_thai_date($ymd, false, false);
}
/** วันสุดท้ายของเดือน 'YYYY-MM' */
function doc_month_end($ym)
{
    return date('Y-m-t', strtotime($ym . '-01'));
}
$director = doc_signer('director', 'ผู้อำนวยการ' . $school);
$reporter = doc_signer('reporter', 'หัวหน้า' . $unitName);
$deputy = doc_signer('deputy', 'รองผู้อำนวยการฝ่ายแผนงานและความร่วมมือ');
$pOfficer = doc_signer('procure', 'เจ้าหน้าที่');
$pHead = doc_signer('procure_head', 'หัวหน้าเจ้าหน้าที่');

// ---------------------------------------------------------------- เตรียมข้อมูลตามชนิดเอกสาร
$titles = array('report' => 'รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ', 'plan' => 'แผนปฏิบัติการประจำปี', 'procure' => 'แผนการจัดซื้อจัดจ้าง', 'sk1' => 'สรุปผลการจัดซื้อจัดจ้าง (สขร.1)');
$period = isset($_GET['p']) && (int)$_GET['p'] == 6 ? 6 : 12;
if ($d == 'report' && !isset($_GET['p']) && bp_fy_quarter_done($Y) < 4) $period = 6;
$month = isset($_GET['m']) && preg_match('/^\d{4}-\d{2}$/', $_GET['m']) ? $_GET['m'] : '';
$docTitle = $titles[$d] . ' ปีงบประมาณ ' . $Y . ($d == 'report' ? ' รอบ ' . $period . ' เดือน' : '') . ($d == 'sk1' && $month ? ' ' . bp_thai_month($month) : '');
$back = array('report' => 'report.php?y=' . $Y . '&p=' . $period, 'plan' => 'projects.php?y=' . $Y, 'procure' => 'procurement.php?y=' . $Y, 'sk1' => 'procurement.php?y=' . $Y . '&t=result' . ($month ? '&m=' . $month : ''));
?><!doctype html>
<html lang="th">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo h($docTitle . ($school !== '' ? ' - ' . $school : '')); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo h(bp_url('assets/css/doc.css')); ?>?v=1">
<style id="pgStyle"></style>
</head>
<body>
<div class="doc-bar">
  <a href="<?php echo h(bp_url($back[$d])); ?>">← กลับ</a>
  <b><?php echo h($docTitle); ?></b>
  <span class="sp"></span>
  <?php if ($d == 'report') { ?>
  <select onchange="location.href='?d=report&y=<?php echo $Y; ?>&p='+this.value" aria-label="รอบรายงาน"><option value="6"<?php echo $period == 6 ? ' selected' : ''; ?>>รอบ 6 เดือน</option><option value="12"<?php echo $period == 12 ? ' selected' : ''; ?>>รอบ 12 เดือน</option></select>
  <?php } elseif ($d == 'sk1') { ?>
  <select onchange="location.href='?d=sk1&y=<?php echo $Y; ?>&m='+this.value" aria-label="เดือน"><option value="">ทุกเดือน (เดือนละหน้า)</option>
    <?php foreach (bp_fy_months($Y) as $ym) { ?><option value="<?php echo $ym; ?>"<?php echo $month == $ym ? ' selected' : ''; ?>><?php echo h(bp_thai_month($ym)); ?></option><?php } ?></select>
  <?php } ?>
  <label><input type="checkbox" id="thDigits"> เลขไทย</label>
  <button class="primary" type="button" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
</div>
<div class="doc-tip">ตอนกดพิมพ์: เลือกเครื่องพิมพ์เป็น <b>บันทึกเป็น PDF</b> (Save as PDF) · ขนาดกระดาษ A4 · <b>ปิด "ส่วนหัวและส่วนท้าย" (Headers and footers)</b> เพื่อไม่ให้มีที่อยู่เว็บติดบนกระดาษ ·
  ชื่อผู้ลงนามและตราสถานศึกษาตั้งได้ที่ ผู้ดูแลระบบ › ตั้งค่า › เอกสารสำหรับพิมพ์</div>
<div class="doc" id="doc">
<?php
// ================================================================ รายงาน 6/12 เดือน
if ($d == 'report') {
    $doneQ = bp_fy_quarter_done($Y);
    $lastQ = $period == 6 ? 2 : 4;
    $pd = bp_period_data($yid, $Y, $period);
    $data = bp_year_data($yid);
    $txt = bp_report_text($yid, $period);
    if ($txt && !$txt['published']) $txt = null;
    $target = bp_disb_target();
    $tq = $target[$lastQ - 1];
    $pct = $pd['approved'] > 0 ? $pd['spent'] * 100 / $pd['approved'] : 0;
    $st = bp_project_statuses();
    $endDate = ($Y - 543) . ($period == 6 ? '-03-31' : '-09-30');
    $tfs = array();
    foreach (bp_transfers($yid) as $tr) if (!$tr['doc_date'] || $tr['doc_date'] <= $endDate) $tfs[] = $tr;
    $asof = bp_year_updated($yid);
    $notEnded = $doneQ < $lastQ;
    $sec = 0;
    ?>
  <section class="sheet">
    <div class="cover">
      <?php echo $logoUrl ? '<img class="logo" src="' . h($logoUrl) . '" alt="">' : '<div class="logo-space"></div>'; ?>
      <h1>รายงานผลการดำเนินงาน<br>และการใช้จ่ายงบประมาณ</h1>
      <p class="sub">ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></p>
      <p class="sub">รอบ <?php echo $period; ?> เดือน</p>
      <p class="period">(<?php echo h(bp_period_label($Y, $period)); ?>)</p>
      <div class="fill"></div>
      <p class="org"><?php echo h($unitName); ?><?php if ($school !== '') { ?><small><?php echo h($school); ?></small><?php } ?></p>
    </div>
  </section>

  <section class="sheet">
    <div class="doc-title"><h2>คำนำ</h2></div>
    <p class="para">รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?> รอบ <?php echo $period; ?> เดือน
      (<?php echo h(bp_period_label($Y, $period)); ?>) ฉบับนี้ จัดทำขึ้นเพื่อรายงานผลการดำเนินงานตามแผนปฏิบัติการ และผลการใช้จ่ายงบประมาณ<?php echo $school !== '' ? 'ของ' . h($school) : ''; ?>
      ประกอบด้วย ภาพรวมการใช้จ่ายงบประมาณ ผลการเบิกจ่ายเทียบกับเป้าหมาย การใช้จ่ายจำแนกตามแหล่งเงินและหน่วยงาน ผลการดำเนินงานรายโครงการ
      การปรับแผนระหว่างปี ตลอดจนปัญหา อุปสรรค และข้อเสนอแนะ</p>
    <p class="para">รายงานฉบับนี้ใช้เป็นข้อมูลสำหรับผู้บริหารในการกำกับ ติดตาม และปรับปรุงการดำเนินงาน และเผยแพร่ต่อสาธารณะเพื่อความโปร่งใส
      ตามหลักธรรมาภิบาลและแนวทางการประเมินคุณธรรมและความโปร่งใสในการดำเนินงานของหน่วยงานภาครัฐ (ITA)</p>
    <div class="signs one" style="margin-top:1.2cm"><div class="sign"><div><?php echo h($unitName); ?></div><div><?php echo h(doc_date_long($asof ? $asof : date('Y-m-d'))); ?></div></div></div>

    <h3 class="sec" style="margin-top:1.4cm">สารบัญ</h3>
    <table class="dt" style="border:0">
      <?php $toc = array('สรุปภาพรวม', 'ผลการเบิกจ่ายรายไตรมาสเทียบเป้าหมาย', 'การใช้จ่ายงบประมาณจำแนกตามแหล่งเงินและหน่วยงาน', 'ผลการดำเนินงานรายโครงการ', 'การปรับแผนและโอนเปลี่ยนแปลงงบประมาณ', 'สรุปผล ปัญหา อุปสรรค และข้อเสนอแนะ');
      foreach ($toc as $i => $t) echo '<tr><td style="border:0;padding:1px 0">ส่วนที่ ' . ($i + 1) . '&nbsp;&nbsp;' . h($t) . '</td></tr>'; ?>
    </table>
  </section>

  <section class="sheet">
    <div class="doc-title"><h2>รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณ<br>ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></h2>
      <p>รอบ <?php echo $period; ?> เดือน (<?php echo h(bp_period_label($Y, $period)); ?>)<?php echo $school !== '' ? '<br>' . h($school) : ''; ?></p></div>
    <?php if ($notEnded) { ?><p class="center small"><b>หมายเหตุ</b> รอบรายงานนี้ยังไม่สิ้นสุด ข้อมูล ณ วันที่ <?php echo h(doc_date_long($asof ? $asof : date('Y-m-d'))); ?></p><?php } ?>

    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> สรุปภาพรวม</h3>
    <table class="dt kv">
      <thead><tr><th>รายการ</th><th>จำนวน</th></tr></thead>
      <tbody>
        <tr><td>งบประมาณรายจ่ายทั้งปี (ตามหมวดรายจ่าย)</td><td class="n"><?php echo doc_money($data['total']); ?> บาท</td></tr>
        <?php if ($funds['funds']) { ?>
        <tr><td>ประมาณการรายรับทั้งปี</td><td class="n"><?php echo doc_money($funds['sum']['estimate']); ?> บาท</td></tr>
        <tr><td>รายรับที่ได้รับจริง</td><td class="n"><?php echo doc_money($funds['sum']['received']); ?> บาท (ร้อยละ <?php echo bp_pct($funds['sum']['received'], $funds['sum']['estimate']); ?>)</td></tr>
        <?php } ?>
        <tr><td>จำนวนโครงการตามแผน</td><td class="n"><?php echo $pd['n']; ?> โครงการ (แล้วเสร็จ <?php echo $pd['n_done']; ?> โครงการ)</td></tr>
        <tr><td>งบประมาณที่อนุมัติให้โครงการ</td><td class="n"><?php echo doc_money($pd['approved']); ?> บาท</td></tr>
        <tr><td>เบิกจ่ายถึงสิ้นรอบรายงาน</td><td class="n"><?php echo doc_money($pd['spent']); ?> บาท</td></tr>
        <tr><td>ร้อยละการเบิกจ่ายเทียบงบอนุมัติ (เป้าหมายร้อยละ <?php echo $tq; ?>)</td><td class="n"><?php echo number_format($pct, 2); ?> — <?php echo $pct >= $tq ? 'เป็นไปตามเป้าหมาย' : 'ต่ำกว่าเป้าหมาย'; ?></td></tr>
      </tbody>
    </table>

    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> ผลการเบิกจ่ายรายไตรมาสเทียบเป้าหมาย</h3>
    <table class="dt">
      <thead><tr><th>ไตรมาส</th><th>เบิกจ่ายในไตรมาส<br>(บาท)</th><th>เบิกจ่ายสะสม<br>(บาท)</th><th>สะสม<br>(ร้อยละ)</th><th>เป้าหมาย<br>(ร้อยละ)</th><th>ผล</th></tr></thead>
      <tbody>
      <?php $c = 0; for ($i = 1; $i <= $lastQ; $i++) { $c += $pd['q'][$i]; $cp = $pd['approved'] > 0 ? $c * 100 / $pd['approved'] : 0; ?>
        <tr><td class="nw">ไตรมาสที่ <?php echo $i; ?> <span class="small">(<?php echo $qn[$i]; ?>)</span></td>
          <td class="n"><?php echo $pd['has_q'] ? doc_money($pd['q'][$i]) : '–'; ?></td><td class="n"><?php echo $pd['has_q'] ? doc_money($c) : '–'; ?></td>
          <td class="c"><?php echo $pd['has_q'] ? number_format($cp, 2) : '–'; ?></td><td class="c"><?php echo $target[$i - 1]; ?></td>
          <td class="c nw"><?php echo !$pd['has_q'] ? '–' : ($i > $doneQ ? 'ยังไม่สิ้นสุด' : ($cp >= $target[$i - 1] ? 'ผ่าน' : 'ไม่ผ่าน')); ?></td></tr>
      <?php } ?>
      </tbody>
    </table>
    <p class="small">หมายเหตุ เป้าหมายการเบิกจ่ายสะสมตามที่สถานศึกษากำหนด · นับเฉพาะโครงการตามแผน ไม่รวมโครงการสำรอง</p>

    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> การใช้จ่ายงบประมาณจำแนกตามแหล่งเงินและหน่วยงาน</h3>
    <?php foreach (array('by_fund' => array('3.1', 'แหล่งเงิน'), 'by_div' => array('3.2', 'ฝ่าย')) as $k => $lb) { ?>
    <div class="keep"><h4 class="sec2"><?php echo $lb[0]; ?> จำแนกตาม<?php echo $lb[1]; ?></h4>
    <table class="dt">
      <thead><tr><th><?php echo $lb[1]; ?></th><th>จำนวนโครงการ</th><th>งบอนุมัติ (บาท)</th><th>เบิกจ่าย (บาท)</th><th>ร้อยละ</th></tr></thead>
      <tbody><?php foreach ($pd[$k] as $name => $v) { ?><tr><td><?php echo h($name); ?></td><td class="c"><?php echo $v['n']; ?></td><td class="n"><?php echo doc_money($v['approved']); ?></td><td class="n"><?php echo doc_money($v['spent']); ?></td><td class="c"><?php echo bp_pct($v['spent'], $v['approved']); ?></td></tr><?php } ?></tbody>
      <tfoot><tr><td class="c">รวม</td><td class="c"><?php echo $pd['n']; ?></td><td class="n"><?php echo doc_money($pd['approved']); ?></td><td class="n"><?php echo doc_money($pd['spent']); ?></td><td class="c"><?php echo bp_pct($pd['spent'], $pd['approved']); ?></td></tr></tfoot>
    </table></div>
    <?php } ?>
  </section>

  <section class="sheet land">
    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> ผลการดำเนินงานรายโครงการ</h3>
    <table class="dt">
      <thead><tr><th style="width:1cm">ที่</th><th>โครงการ</th><th style="width:4.2cm">หน่วยงาน</th><th style="width:2.6cm">งบอนุมัติ (บาท)</th><th style="width:2.6cm">เบิกจ่ายถึงสิ้นรอบ (บาท)</th><th style="width:1.4cm">ร้อยละ</th><th style="width:2.2cm">สถานะ</th><th style="width:6.5cm">ผลการดำเนินงาน</th></tr></thead>
      <tbody>
      <?php foreach ($pd['projects'] as $i => $p) { $u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : ''; ?>
        <tr><td class="c"><?php echo $i + 1; ?></td><td><?php echo h($p['title']); ?><?php echo $p['code'] !== '' ? ' <span class="small">(' . h($p['code']) . ')</span>' : ''; ?></td><td><?php echo h($u); ?></td>
          <td class="n"><?php echo doc_money($p['approved']); ?></td><td class="n"><?php echo doc_money($p['spent_period']); ?></td><td class="c"><?php echo bp_pct($p['spent_period'], $p['approved'], 1); ?></td>
          <td class="c"><?php echo h($st[$p['status']]); ?></td>
          <td class="small"><?php if ($p['result_pct'] !== null) echo 'สำเร็จร้อยละ ' . (int)$p['result_pct'] . ' '; echo h($p['result']); ?><?php if (trim((string)$p['problems']) !== '') echo '<br>ปัญหา: ' . h($p['problems']); ?></td></tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><td></td><td>รวม <?php echo $pd['n']; ?> โครงการ</td><td></td><td class="n"><?php echo doc_money($pd['approved']); ?></td><td class="n"><?php echo doc_money($pd['spent']); ?></td><td class="c"><?php echo bp_pct($pd['spent'], $pd['approved'], 1); ?></td><td></td><td></td></tr></tfoot>
    </table>
  </section>

  <section class="sheet">
    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> การปรับแผนและโอนเปลี่ยนแปลงงบประมาณ</h3>
    <?php if (!$tfs) { ?><p class="para">ในรอบรายงานนี้ ไม่มีการปรับแผนหรือโอนเปลี่ยนแปลงงบประมาณระหว่างโครงการ</p>
    <?php } else { $tsum = 0; ?>
    <table class="dt">
      <thead><tr><th>ที่</th><th>วันที่อนุมัติ</th><th>จาก</th><th>ไปยัง</th><th>จำนวนเงิน (บาท)</th><th>เหตุผล / เอกสารอ้างอิง</th></tr></thead>
      <tbody><?php foreach ($tfs as $i => $tr) { $tsum += (float)$tr['amount']; ?>
        <tr><td class="c"><?php echo $i + 1; ?></td><td class="c"><?php echo h(bp_thai_date($tr['doc_date'])); ?></td><td><?php echo h(bp_transfer_side($tr, 'from')); ?></td><td><?php echo h(bp_transfer_side($tr, 'to')); ?></td>
          <td class="n"><?php echo doc_money($tr['amount']); ?></td><td><?php echo h($tr['reason']); ?><?php echo $tr['doc_no'] !== '' ? '<br>' . h($tr['doc_no']) : ''; ?></td></tr>
      <?php } ?></tbody>
      <tfoot><tr><td></td><td colspan="3" class="c">รวม <?php echo count($tfs); ?> รายการ</td><td class="n"><?php echo doc_money($tsum); ?></td><td></td></tr></tfoot>
    </table>
    <?php } ?>

    <h3 class="sec">ส่วนที่ <?php echo ++$sec; ?> สรุปผล ปัญหา อุปสรรค และข้อเสนอแนะ</h3>
    <?php
    $autoProb = array();
    foreach ($pd['projects'] as $p) if (trim((string)$p['problems']) !== '') $autoProb[] = $p['title'] . ': ' . trim($p['problems']);
    if ($pct < $tq) $autoProb[] = 'ผลการเบิกจ่ายสะสมร้อยละ ' . number_format($pct, 2) . ' ต่ำกว่าเป้าหมายร้อยละ ' . $tq;
    $paras = function ($s) { $o = ''; foreach (preg_split('/\R+/u', trim($s)) as $l) if (trim($l) !== '') $o .= '<p class="para">' . h(trim($l)) . '</p>'; return $o; };
    ?>
    <h4 class="sec2"><?php echo $sec; ?>.1 สรุปผลการดำเนินงาน</h4>
    <?php echo $txt && trim($txt['summary']) !== '' ? $paras($txt['summary']) : '<p class="para">ดำเนินโครงการตามแผน ' . $pd['n'] . ' โครงการ แล้วเสร็จ ' . $pd['n_done'] . ' โครงการ เบิกจ่ายถึงสิ้นรอบรายงาน ' . doc_money($pd['spent']) . ' บาท คิดเป็นร้อยละ ' . number_format($pct, 2) . ' ของงบประมาณที่อนุมัติ ' . doc_money($pd['approved']) . ' บาท ' . ($pct >= $tq ? 'เป็นไปตามเป้าหมาย' : 'ต่ำกว่าเป้าหมาย') . 'การเบิกจ่ายสะสมร้อยละ ' . $tq . '</p>'; ?>
    <h4 class="sec2"><?php echo $sec; ?>.2 ปัญหาและอุปสรรค</h4>
    <?php if ($txt && trim($txt['problems']) !== '') echo $paras($txt['problems']);
    elseif ($autoProb) { echo '<ul class="dash">'; foreach ($autoProb as $x) echo '<li>' . h($x) . '</li>'; echo '</ul>'; }
    else echo '<p class="para">ไม่พบปัญหาและอุปสรรคที่มีผลต่อการดำเนินงาน</p>'; ?>
    <h4 class="sec2"><?php echo $sec; ?>.3 ข้อเสนอแนะและแนวทางการพัฒนา</h4>
    <?php echo $txt && trim($txt['suggestions']) !== '' ? $paras($txt['suggestions']) : '<p class="dotlines">' . str_repeat('<span></span>', 3) . '</p>'; ?>

    <div class="signs">
      <?php echo doc_sign($reporter, 'ผู้รายงาน'); ?>
      <?php echo doc_sign($deputy, 'ผู้ตรวจสอบ'); ?>
    </div>
    <div class="approve">
      <b>ความเห็นของผู้อำนวยการ</b>
      <div class="opts" style="margin:.15cm 0 .1cm"><span><i class="box"></i>ทราบ</span><span><i class="box"></i>อนุมัติให้เผยแพร่ต่อสาธารณะ</span><span><i class="box"></i>อื่น ๆ ..................................</span></div>
      <div class="signs one" style="margin-top:.3cm"><?php echo doc_sign($director); ?></div>
      <div class="right" style="margin-top:.1cm">วันที่ ........ เดือน ............................ พ.ศ. ...............</div>
    </div>
  </section>
<?php
// ================================================================ แผนปฏิบัติการ
} elseif ($d == 'plan') {
    $projects = bp_projects($yid, true);
    $data = bp_year_data($yid);
    // จัดกลุ่มตามฝ่าย (ลำดับตามโครงสร้างหน่วยงาน)
    $groups = array();
    foreach (bp_unit_tree() as $t) $groups[$t['name']] = array();
    $groups['ไม่ระบุหน่วยงาน'] = array();
    $total = 0; $byQ = array(1 => 0, 2 => 0, 3 => 0, 4 => 0); $nPlan = 0;
    foreach ($projects as $p) {
        $g = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['top'] : 'ไม่ระบุหน่วยงาน';
        $groups[$g][] = $p;
        if ($p['status'] != 'reserve') { $total += (float)$p['approved']; $nPlan++; }
    }
    $byFund = array();
    foreach ($projects as $p) {
        if ($p['status'] == 'reserve') continue;
        $fn = $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$p['fund_id']]['path'] : 'ไม่ระบุแหล่งเงิน';
        $top = $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$fidx[$p['fund_id']]['top_id']]['name'] : 'ไม่ระบุแหล่งเงิน';
        if (!isset($byFund[$top])) $byFund[$top] = array('n' => 0, 'a' => 0);
        $byFund[$top]['n']++; $byFund[$top]['a'] += (float)$p['approved'];
    }
    ?>
  <section class="sheet">
    <div class="cover">
      <?php echo $logoUrl ? '<img class="logo" src="' . h($logoUrl) . '" alt="">' : '<div class="logo-space"></div>'; ?>
      <h1>แผนปฏิบัติการ</h1>
      <p class="sub">ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></p>
      <?php if (trim((string)$cur['title']) !== '' && strpos($cur['title'], (string)$Y) === false) { ?><p class="period"><?php echo h($cur['title']); ?></p><?php } ?>
      <div class="fill"></div>
      <p class="org"><?php echo h($school !== '' ? $school : $unitName); ?><?php if ($school !== '') { ?><small><?php echo h($unitName); ?></small><?php } ?></p>
    </div>
  </section>

  <section class="sheet">
    <div class="doc-title"><h2>สรุปแผนปฏิบัติการ ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></h2><?php echo $school !== '' ? '<p>' . h($school) . '</p>' : ''; ?></div>
    <?php if ($funds['funds']) { ?>
    <h3 class="sec">1. ประมาณการรายรับจำแนกตามแหล่งเงิน</h3>
    <table class="dt">
      <thead><tr><th>แหล่งเงิน</th><th>ประมาณการรายรับ (บาท)</th><th>เงินยกมา (บาท)</th><th>รวม (บาท)</th></tr></thead>
      <tbody>
      <?php foreach ($funds['funds'] as $f) { ?>
        <tr class="grp"><td><?php echo h($f['name']); ?></td><td class="n"><?php echo doc_money($f['estimate']); ?></td><td class="n"><?php echo doc_money($f['carry_in']); ?></td><td class="n"><?php echo doc_money($f['estimate'] + $f['carry_in']); ?></td></tr>
        <?php foreach ($f['children'] as $ch) { ?><tr><td style="padding-left:.8cm">– <?php echo h($ch['name']); ?></td><td class="n"><?php echo doc_money($ch['estimate']); ?></td><td class="n"><?php echo doc_money($ch['carry_in']); ?></td><td class="n"><?php echo doc_money($ch['estimate'] + $ch['carry_in']); ?></td></tr><?php } ?>
      <?php } ?>
      </tbody>
      <tfoot><tr><td class="c">รวมทั้งสิ้น</td><td class="n"><?php echo doc_money($funds['sum']['estimate']); ?></td><td class="n"><?php echo doc_money($funds['sum']['carry_in']); ?></td><td class="n"><?php echo doc_money($funds['sum']['estimate'] + $funds['sum']['carry_in']); ?></td></tr></tfoot>
    </table>
    <?php } ?>
    <?php if ($data['categories']) { ?>
    <div class="keep"><h3 class="sec"><?php echo $funds['funds'] ? 2 : 1; ?>. งบประมาณรายจ่ายจำแนกตามหมวด</h3>
    <table class="dt">
      <thead><tr><th>หมวดรายจ่าย</th><th>จำนวนเงิน (บาท)</th><th>ร้อยละ</th></tr></thead>
      <tbody><?php foreach ($data['categories'] as $c) { ?><tr><td><?php echo h($c['name']); ?></td><td class="n"><?php echo doc_money($c['total']); ?></td><td class="c"><?php echo bp_pct($c['total'], $data['total']); ?></td></tr><?php } ?></tbody>
      <tfoot><tr><td class="c">รวม</td><td class="n"><?php echo doc_money($data['total']); ?></td><td class="c">100.00</td></tr></tfoot>
    </table></div>
    <?php } ?>
    <div class="keep"><h3 class="sec"><?php echo ($funds['funds'] ? 1 : 0) + ($data['categories'] ? 1 : 0) + 1; ?>. โครงการตามแผนจำแนกตามแหล่งเงิน</h3>
    <table class="dt">
      <thead><tr><th>แหล่งเงิน</th><th>จำนวนโครงการ</th><th>งบประมาณ (บาท)</th><th>ร้อยละ</th></tr></thead>
      <tbody><?php foreach ($byFund as $n => $v) { ?><tr><td><?php echo h($n); ?></td><td class="c"><?php echo $v['n']; ?></td><td class="n"><?php echo doc_money($v['a']); ?></td><td class="c"><?php echo bp_pct($v['a'], $total); ?></td></tr><?php } ?></tbody>
      <tfoot><tr><td class="c">รวม</td><td class="c"><?php echo $nPlan; ?></td><td class="n"><?php echo doc_money($total); ?></td><td class="c">100.00</td></tr></tfoot>
    </table></div>
  </section>

  <section class="sheet land">
    <div class="doc-title"><h2>รายละเอียดโครงการ/กิจกรรมตามแผนปฏิบัติการ ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></h2><?php echo $school !== '' ? '<p>' . h($school) . '</p>' : ''; ?></div>
    <table class="dt">
      <thead>
        <tr><th rowspan="2" style="width:1cm">ที่</th><th rowspan="2">โครงการ/กิจกรรม</th><th rowspan="2" style="width:5.2cm">วัตถุประสงค์ / เป้าหมาย</th><th rowspan="2" style="width:2.5cm">งบประมาณ (บาท)</th><th rowspan="2" style="width:3cm">แหล่งเงิน</th><th colspan="4">ระยะเวลาดำเนินการ</th><th rowspan="2" style="width:3.6cm">ผู้รับผิดชอบ</th></tr>
        <tr><?php for ($i = 1; $i <= 4; $i++) echo '<th style="width:1.15cm;font-size:9.5pt;line-height:1.15">' . $qn[$i] . '</th>'; ?></tr>
      </thead>
      <tbody>
      <?php $no = 0; foreach ($groups as $g => $list) { if (!$list) continue; $gs = 0; ?>
        <tr class="grp"><td></td><td colspan="9"><?php echo h($g); ?></td></tr>
        <?php foreach ($list as $p) { $no++; $gs += $p['status'] == 'reserve' ? 0 : (float)$p['approved']; $qs = array_flip(array_filter(explode(',', (string)$p['quarters']))); $u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : ''; ?>
        <tr><td class="c"><?php echo $no; ?></td><td><?php echo h($p['title']); ?><?php echo $p['status'] == 'reserve' ? ' <i>(โครงการสำรอง)</i>' : ''; ?></td>
          <td class="small"><?php echo nl2br(h(trim($p['objective']))); ?><?php echo trim($p['target']) !== '' ? (trim($p['objective']) !== '' ? '<br>' : '') . '<b>เป้าหมาย</b> ' . nl2br(h(trim($p['target']))) : ''; ?></td>
          <td class="n"><?php echo doc_money($p['approved']); ?></td><td class="small"><?php echo $p['fund_id'] && isset($fidx[$p['fund_id']]) ? h($fidx[$p['fund_id']]['path']) : ''; ?></td>
          <?php for ($i = 1; $i <= 4; $i++) echo '<td class="ck">' . (isset($qs[$i]) ? '✓' : '') . '</td>'; ?>
          <td class="small"><?php echo h($p['owner']); ?><?php echo $u !== '' && $u !== $p['owner'] ? '<br>' . h($u) : ''; ?></td></tr>
        <?php } ?>
        <tr class="sum"><td colspan="3" class="right">รวม<?php echo h($g); ?></td><td class="n"><?php echo doc_money($gs); ?></td><td colspan="6"></td></tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><td colspan="3" class="right">รวมทั้งสิ้น <?php echo $nPlan; ?> โครงการ</td><td class="n"><?php echo doc_money($total); ?></td><td colspan="6"></td></tr></tfoot>
    </table>
    <div class="signs">
      <?php echo doc_sign($reporter, 'ผู้จัดทำ'); ?>
      <?php echo doc_sign($deputy, 'ผู้เสนอ'); ?>
      <?php echo doc_sign($director, 'ผู้อนุมัติ'); ?>
    </div>
  </section>
<?php
// ================================================================ แผนการจัดซื้อจัดจ้าง
} elseif ($d == 'procure') {
    $rows = bp_procures($yid, true);
    $pj = array();
    foreach (bp_projects($yid) as $p) $pj[$p['id']] = $p;
    $sum = 0;
    ?>
  <section class="sheet land">
    <div class="doc-title"><h2>แผนการจัดซื้อจัดจ้าง ประจำปีงบประมาณ พ.ศ. <?php echo $Y; ?></h2><?php echo $school !== '' ? '<p>' . h($school) . '</p>' : ''; ?></div>
    <table class="dt">
      <thead><tr><th style="width:1cm">ที่</th><th>รายการ/งานที่จะจัดซื้อจัดจ้าง</th><th style="width:3cm">วงเงินงบประมาณ (บาท)</th><th style="width:3.6cm">แหล่งเงิน</th><th style="width:4.2cm">วิธีจัดซื้อจัดจ้าง</th><th style="width:3.2cm">ช่วงเวลาที่คาดว่าจะจัดหา (เดือน/ปี)</th><th style="width:4.6cm">หมายเหตุ</th></tr></thead>
      <tbody>
      <?php if (!$rows) { ?><tr><td colspan="7" class="c">ไม่มีแผนการจัดซื้อจัดจ้าง</td></tr><?php } ?>
      <?php foreach ($rows as $i => $r) { $sum += (float)$r['budget']; $p = $r['project_id'] && isset($pj[$r['project_id']]) ? $pj[$r['project_id']] : null; ?>
        <tr><td class="c"><?php echo (int)$r['seq'] ? (int)$r['seq'] : $i + 1; ?></td><td><?php echo h($r['title']); ?></td><td class="n"><?php echo doc_money($r['budget']); ?></td>
          <td class="small"><?php echo $p && $p['fund_id'] && isset($fidx[$p['fund_id']]) ? h($fidx[$p['fund_id']]['path']) : ''; ?></td>
          <td><?php echo h($r['method']); ?></td><td class="c"><?php echo h(bp_thai_month($r['plan_month'])); ?></td>
          <td class="small"><?php echo $p ? 'โครงการ' . h(preg_replace('/^โครงการ\s*/u', '', $p['title'])) : ''; ?><?php echo $r['note'] !== '' ? ($p ? '<br>' : '') . h($r['note']) : ''; ?></td></tr>
      <?php } ?>
      </tbody>
      <tfoot><tr><td></td><td class="right">รวม <?php echo count($rows); ?> รายการ</td><td class="n"><?php echo doc_money($sum); ?></td><td colspan="4"></td></tr></tfoot>
    </table>
    <div class="signs">
      <?php echo doc_sign($pOfficer, 'ผู้จัดทำ'); ?>
      <?php echo doc_sign($pHead, 'ผู้ตรวจสอบ'); ?>
      <?php echo doc_sign($director, 'ผู้เห็นชอบ'); ?>
    </div>
  </section>
<?php
// ================================================================ สขร.1
} else {
    $rows = bp_procures($yid, true);
    $months = array();
    if ($month) $months[] = $month;
    else foreach (bp_fy_months($Y) as $ym) if ($ym <= date('Y-m')) $months[] = $ym;
    if (!$months) $months[] = bp_fy_months($Y)[0];
    foreach ($months as $ym) {
        $done = array();
        foreach ($rows as $r) if ($r['status'] == 'done' && substr((string)$r['contract_date'], 0, 7) == $ym) $done[] = $r;
        $mname = bp_thai_months(false);
        $mm = (int)substr($ym, 5, 2);
        $yy = (int)substr($ym, 0, 4) + 543;
        ?>
  <section class="sheet land">
    <div class="form-code">แบบ สขร.1</div>
    <div class="doc-title"><h2>สรุปผลการดำเนินการจัดซื้อจัดจ้างในรอบเดือน <?php echo h($mname[$mm]); ?> พ.ศ. <?php echo $yy; ?></h2>
      <p><?php echo h($school !== '' ? $school : $unitName); ?></p>
      <p>วันที่ <?php echo h(doc_date_long(doc_month_end($ym))); ?></p></div>
    <table class="dt">
      <thead><tr><th style="width:1cm">ลำดับที่</th><th>งานที่จัดซื้อหรือจัดจ้าง</th><th style="width:2.4cm">วงเงินที่จะซื้อหรือจ้าง (บาท)</th><th style="width:2.3cm">ราคากลาง (บาท)</th><th style="width:2.3cm">วิธีซื้อหรือจ้าง</th>
        <th style="width:3.8cm">รายชื่อผู้เสนอราคาและราคาที่เสนอ</th><th style="width:3.8cm">ผู้ได้รับการคัดเลือกและราคาที่ตกลงซื้อหรือจ้าง</th><th style="width:2.8cm">เหตุผลที่คัดเลือกโดยสังเขป</th><th style="width:2.7cm">เลขที่และวันที่ของสัญญาหรือข้อตกลงในการซื้อหรือจ้าง</th></tr></thead>
      <tbody>
      <?php if (!$done) { ?><tr><td colspan="9" class="c" style="padding:.4cm">ไม่มีการจัดซื้อจัดจ้างในรอบเดือนนี้</td></tr><?php } ?>
      <?php foreach ($done as $i => $r) { ?>
        <tr><td class="c"><?php echo $i + 1; ?></td><td><?php echo h($r['title']); ?></td><td class="n"><?php echo doc_money($r['budget']); ?></td>
          <td class="n"><?php echo doc_money((float)$r['ref_price'] ? $r['ref_price'] : $r['budget']); ?></td><td class="c"><?php echo h($r['method']); ?></td>
          <td class="small"><?php echo $r['bidders'] !== '' ? nl2br(h($r['bidders'])) : h($r['vendor']) . '<br>ราคาที่เสนอ ' . doc_money($r['contract_amount']) . ' บาท'; ?></td>
          <td class="small"><?php echo h($r['vendor']); ?><br>ราคาที่ตกลง <?php echo doc_money($r['contract_amount']); ?> บาท</td>
          <td class="small"><?php echo h($r['reason'] !== '' ? $r['reason'] : 'เสนอราคาต่ำสุดและคุณสมบัติถูกต้องตามเงื่อนไข'); ?></td>
          <td class="small"><?php echo h($r['contract_no']); ?><br>ลงวันที่ <?php echo h(bp_thai_date($r['contract_date'])); ?></td></tr>
      <?php } ?>
      </tbody>
    </table>
    <div class="signs one"><?php echo doc_sign($pHead); ?></div>
  </section>
<?php
    }
}
?>
</div>
<script>
(function () {
  // เลขไทย: แปลงตัวเลขในเนื้อเอกสาร (จำค่าไว้ในเครื่อง)
  var box = document.getElementById('thDigits'), doc = document.getElementById('doc'), orig = [];
  var walker = document.createTreeWalker(doc, NodeFilter.SHOW_TEXT, null, false), n;
  while ((n = walker.nextNode())) if (/[0-9]/.test(n.nodeValue)) orig.push([n, n.nodeValue]);
  var pg = document.getElementById('pgStyle');
  function apply(th) {
    orig.forEach(function (o) { o[0].nodeValue = th ? o[1].replace(/[0-9]/g, function (d) { return String.fromCharCode(0x0E50 + +d); }) : o[1]; });
    var c = "'- ' counter(page" + (th ? ', thai' : '') + ") ' -'";
    pg.textContent = '@page { @top-center { content: ' + c + '; font-family: Sarabun, sans-serif; font-size: 12pt; padding-top: 1.1cm; vertical-align: top; } }'
      + '@page land { @top-center { content: ' + c + '; font-family: Sarabun, sans-serif; font-size: 11pt; padding-top: .7cm; vertical-align: top; } }'
      + '@page :first { @top-center { content: none; } } @page land:first { @top-center { content: none; } }';
  }
  var th = true;
  try { th = localStorage.getItem('bp-doc-thai') !== '0'; } catch (e) {}
  box.checked = th; apply(th);
  box.addEventListener('change', function () { apply(box.checked); try { localStorage.setItem('bp-doc-thai', box.checked ? '1' : '0'); } catch (e) {} });
})();
</script>
</body>
</html>
