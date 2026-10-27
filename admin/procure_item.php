<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id) {
    $r = bp_row('SELECT * FROM ' . bp_t('procure') . ' WHERE id=' . $id);
    if (!$r) bp_redirect('admin/index.php');
    $yid = (int)$r['year_id'];
} else {
    $yid = isset($_GET['y']) ? (int)$_GET['y'] : 0;
    $mx = bp_row('SELECT MAX(seq) m FROM ' . bp_t('procure') . ' WHERE year_id=' . $yid);
    $r = array('id' => 0, 'seq' => (int)$mx['m'] + 1, 'project_id' => null, 'title' => '', 'method' => 'เฉพาะเจาะจง', 'budget' => 0, 'ref_price' => 0, 'plan_month' => '',
        'bidders' => '', 'vendor' => '', 'contract_amount' => 0, 'contract_no' => '', 'contract_date' => null, 'reason' => '', 'status' => 'plan', 'note' => '', 'updated_at' => '');
}
$y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
if (!$y) bp_redirect('admin/index.php');
$st = bp_procure_statuses();
$projects = bp_projects($yid);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f = $_POST;
    $title = mb_substr(trim((string)$f['title']), 0, 500);
    if ($title === '') {
        bp_flash('กรุณากรอกชื่อรายการ', 'err');
        bp_redirect('admin/procure_item.php?' . ($id ? 'id=' . $id : 'y=' . $yid));
    }
    $pids = array();
    foreach ($projects as $p) $pids[(int)$p['id']] = true;
    $txt = function ($k, $len) use ($f) { return q(mb_substr(trim((string)(isset($f[$k]) ? $f[$k] : '')), 0, $len)); };
    $cd = bp_parse_date(isset($f['contract_date']) ? $f['contract_date'] : '');
    $status = isset($st[$f['status']]) ? $f['status'] : 'plan';
    $set = 'seq=' . max(0, (int)$f['seq']) . ',title=' . q($title) . ',project_id=' . (isset($pids[(int)$f['project_id']]) ? (int)$f['project_id'] : 'NULL')
        . ',method=' . $txt('method', 64) . ',budget=' . bp_parse_amount($f['budget']) . ',ref_price=' . bp_parse_amount($f['ref_price'])
        . ',plan_month=' . q(preg_match('/^\d{4}-\d{2}$/', (string)$f['plan_month']) ? $f['plan_month'] : '') . ',bidders=' . $txt('bidders', 2000)
        . ',vendor=' . $txt('vendor', 255) . ',contract_amount=' . bp_parse_amount($f['contract_amount']) . ',contract_no=' . $txt('contract_no', 64)
        . ',contract_date=' . ($cd ? q($cd) : 'NULL') . ',reason=' . $txt('reason', 255) . ',status=' . q($status) . ',note=' . $txt('note', 500)
        . ",updated_at='" . date('Y-m-d H:i:s') . "'";
    if ($id) bp_query('UPDATE ' . bp_t('procure') . ' SET ' . $set . ' WHERE id=' . $id);
    else bp_query('INSERT INTO ' . bp_t('procure') . ' SET year_id=' . $yid . ',' . $set);
    bp_done('procure.save', 'บันทึกรายการจัดซื้อ "' . $title . '" แล้ว');
    bp_redirect(!empty($f['again']) ? 'admin/procure_item.php?y=' . $yid : 'admin/procure.php?id=' . $yid);
}
$m = function ($v) { return (float)$v ? bp_money($v) : ''; };
admin_head(($id ? 'แก้ไข' : 'เพิ่ม') . 'รายการจัดซื้อจัดจ้าง · ปี ' . $y['year'], 'index');
admin_year_tabs($y, 'procure');
?>
<form method="post" class="pj-form">
  <?php echo bp_csrf_field(); ?>
  <div class="panel">
    <h3>แผนการจัดซื้อจัดจ้าง</h3>
    <div class="form-row">
      <label class="fld" style="flex:0 1 100px"><span>ลำดับ</span><input type="number" name="seq" min="0" value="<?php echo (int)$r['seq']; ?>"></label>
      <label class="fld grow"><span>รายการ / งานที่จัดซื้อจัดจ้าง *</span><input name="title" value="<?php echo h($r['title']); ?>" required></label>
    </div>
    <div class="form-row mt">
      <label class="fld"><span>วงเงินงบประมาณ (บาท)</span><input class="num" name="budget" value="<?php echo $m($r['budget']); ?>"></label>
      <label class="fld"><span>ราคากลาง (บาท)</span><input class="num" name="ref_price" value="<?php echo $m($r['ref_price']); ?>"></label>
      <label class="fld grow"><span>วิธีจัดซื้อจัดจ้าง</span><input name="method" list="methods" value="<?php echo h($r['method']); ?>"></label>
      <label class="fld"><span>ช่วงเวลาที่คาดว่าจะจัดหา</span>
        <select name="plan_month"><option value="">– ไม่ระบุ –</option><?php foreach (bp_fy_months($y['year']) as $ym) { ?><option value="<?php echo $ym; ?>" <?php echo $r['plan_month'] == $ym ? 'selected' : ''; ?>><?php echo h(bp_thai_month($ym)); ?></option><?php } ?></select></label>
    </div>
    <label class="fld mt"><span>โครงการที่เกี่ยวข้อง</span>
      <select name="project_id"><option value="">– ไม่ระบุ –</option><?php foreach ($projects as $p) { ?><option value="<?php echo (int)$p['id']; ?>" <?php echo $r['project_id'] == $p['id'] ? 'selected' : ''; ?>><?php echo h($p['code'] . ' ' . $p['title']); ?></option><?php } ?></select></label>
  </div>
  <div class="panel">
    <h3>ผลการจัดซื้อจัดจ้าง (แบบ สขร.1)</h3>
    <div class="form-row">
      <label class="fld"><span>สถานะ</span><select name="status"><?php foreach ($st as $k => $v) { ?><option value="<?php echo $k; ?>" <?php echo $r['status'] == $k ? 'selected' : ''; ?>><?php echo h($v); ?></option><?php } ?></select></label>
    </div>
    <label class="fld mt"><span>รายชื่อผู้เสนอราคาและราคาที่เสนอ</span><textarea name="bidders" rows="2" placeholder="เช่น ร้านเอ 45,000 บาท / หจก.บี 46,500 บาท"><?php echo h($r['bidders']); ?></textarea></label>
    <div class="form-row">
      <label class="fld grow"><span>ผู้ได้รับการคัดเลือก</span><input name="vendor" value="<?php echo h($r['vendor']); ?>"></label>
      <label class="fld"><span>ราคาที่ตกลงซื้อ/จ้าง (บาท)</span><input class="num" name="contract_amount" value="<?php echo $m($r['contract_amount']); ?>"></label>
    </div>
    <div class="form-row mt">
      <label class="fld grow"><span>เหตุผลที่คัดเลือก</span><input name="reason" list="reasons" value="<?php echo h($r['reason']); ?>"></label>
      <label class="fld"><span>เลขที่สัญญา/ใบสั่งซื้อ</span><input name="contract_no" value="<?php echo h($r['contract_no']); ?>"></label>
      <label class="fld"><span>วันที่สัญญา (วว/ดด/ปปปป)</span><input name="contract_date" value="<?php echo $r['contract_date'] ? h(date('d/m/', strtotime($r['contract_date'])) . (date('Y', strtotime($r['contract_date'])) + 543)) : ''; ?>" placeholder="15/01/<?php echo (int)$y['year']; ?>"></label>
    </div>
    <label class="fld mt"><span>หมายเหตุ</span><input name="note" value="<?php echo h($r['note']); ?>"></label>
  </div>
  <div class="savebar">
    <span class="muted"><?php echo $id && $r['updated_at'] ? 'แก้ไขล่าสุด ' . h(bp_thai_date($r['updated_at'], true)) : ''; ?></span>
    <a class="btn btn-ghost" href="procure.php?id=<?php echo $yid; ?>">ยกเลิก</a>
    <?php if (!$id) { ?><button class="btn btn-ghost" type="submit" name="again" value="1">บันทึกแล้วเพิ่มอีก</button><?php } ?>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</form>
<datalist id="methods"><?php foreach (bp_procure_methods() as $x) echo '<option value="' . h($x) . '">'; ?></datalist>
<datalist id="reasons"><option value="เสนอราคาต่ำสุด"><option value="มีคุณสมบัติตรงตามเงื่อนไขและเสนอราคาต่ำสุด"><option value="ราคาเหมาะสม อยู่ในวงเงินงบประมาณ"><option value="ใช้เกณฑ์ราคาประกอบเกณฑ์อื่น"></datalist>
<script>
document.querySelectorAll('.pj-form input.num').forEach(function (i) {
  i.addEventListener('blur', function () { var v = parseFloat(i.value.replace(/[^0-9.\-]/g, '')); if (!isNaN(v)) i.value = v.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); });
});
</script>
<?php admin_foot(); ?>
