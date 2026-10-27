<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();

$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid) {
    $p = bp_row('SELECT * FROM ' . bp_t('project') . ' WHERE id=' . $pid);
    if (!$p) bp_redirect('admin/index.php');
    $yid = (int)$p['year_id'];
} else {
    $yid = isset($_GET['y']) ? (int)$_GET['y'] : 0;
    $p = array('id' => 0, 'code' => '', 'title' => '', 'unit_id' => null, 'fund_id' => null, 'expense' => '', 'requested' => 0, 'approved' => 0, 'spent' => 0,
        'status' => 'plan', 'quarters' => '', 'owner' => '', 'objective' => '', 'target' => '', 'note' => '');
}
$y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
if (!$y) bp_redirect('admin/index.php');
$st = bp_project_statuses();
$fidx = bp_fund_index(bp_funds($yid));
$units = bp_units();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f = $_POST;
    $title = mb_substr(trim((string)(isset($f['title']) ? $f['title'] : '')), 0, 500);
    if ($title === '') {
        bp_flash('กรุณากรอกชื่อโครงการ', 'err');
        bp_redirect('admin/project.php?' . ($pid ? 'id=' . $pid : 'y=' . $yid));
    }
    $unit = isset($f['unit_id']) && isset($units[(int)$f['unit_id']]) ? (int)$f['unit_id'] : 'NULL';
    $fund = isset($f['fund_id']) && isset($fidx[(int)$f['fund_id']]) ? (int)$f['fund_id'] : 'NULL';
    $status = isset($f['status']) && isset($st[$f['status']]) ? $f['status'] : 'plan';
    $qs = array();
    if (isset($f['q']) && is_array($f['q'])) foreach ($f['q'] as $q) if ((int)$q >= 1 && (int)$q <= 4) $qs[(int)$q] = (int)$q;
    ksort($qs);
    $txt = function ($k, $len) use ($f) { return q(mb_substr(trim((string)(isset($f[$k]) ? $f[$k] : '')), 0, $len)); };
    $set = 'title=' . q($title) . ',code=' . $txt('code', 32) . ',unit_id=' . $unit . ',fund_id=' . $fund . ',expense=' . $txt('expense', 255)
        . ',requested=' . bp_parse_amount(isset($f['requested']) ? $f['requested'] : 0) . ',approved=' . bp_parse_amount(isset($f['approved']) ? $f['approved'] : 0)
        . ',spent=' . bp_parse_amount(isset($f['spent']) ? $f['spent'] : 0) . ',status=' . q($status) . ',quarters=' . q(implode(',', $qs))
        . ',owner=' . $txt('owner', 255) . ',objective=' . $txt('objective', 5000) . ',target=' . $txt('target', 5000) . ',note=' . $txt('note', 500)
        . ",updated_at='" . date('Y-m-d H:i:s') . "'";
    if ($pid) bp_query('UPDATE ' . bp_t('project') . ' SET ' . $set . ' WHERE id=' . $pid);
    else { bp_query('INSERT INTO ' . bp_t('project') . ' SET year_id=' . $yid . ',' . $set); $pid = bp_insert_id(); }
    // ความสอดคล้อง: เฉพาะข้อของปีนี้
    bp_query('DELETE FROM ' . bp_t('project_align') . ' WHERE project_id=' . $pid);
    $valid = array();
    foreach (bp_rows('SELECT id FROM ' . bp_t('align') . ' WHERE year_id=' . $yid . ' AND parent_id IS NOT NULL') as $r) $valid[(int)$r['id']] = true;
    $vals = array();
    if (isset($f['align']) && is_array($f['align'])) foreach ($f['align'] as $a) if (isset($valid[(int)$a])) $vals[(int)$a] = '(' . $pid . ',' . (int)$a . ')';
    if ($vals) bp_query('INSERT INTO ' . bp_t('project_align') . ' (project_id,align_id) VALUES ' . implode(',', $vals));
    bp_flash('บันทึกโครงการ "' . $title . '" แล้ว');
    bp_redirect(!empty($f['again']) ? 'admin/project.php?y=' . $yid : 'admin/projects.php?id=' . $yid);
}

$sel = array();
if ($pid) foreach (bp_rows('SELECT align_id FROM ' . bp_t('project_align') . ' WHERE project_id=' . $pid) as $r) $sel[(int)$r['align_id']] = true;
$qsel = array_flip(array_filter(explode(',', (string)$p['quarters'])));
$money = function ($v) { return (float)$v ? bp_money($v) : ''; };
admin_head(($pid ? 'แก้ไขโครงการ' : 'เพิ่มโครงการ') . ' · ปี ' . $y['year'], 'index');
admin_year_tabs($y, 'projects');
?>
<form method="post" class="pj-form">
  <?php echo bp_csrf_field(); ?>
  <div class="panel">
    <div class="form-row">
      <label class="fld" style="flex:0 1 160px"><span>รหัสโครงการ</span><input name="code" value="<?php echo h($p['code']); ?>" placeholder="เช่น P<?php echo substr($y['year'], -2); ?>-001"></label>
      <label class="fld grow"><span>ชื่อโครงการ *</span><input name="title" value="<?php echo h($p['title']); ?>" required></label>
    </div>
    <div class="form-row mt">
      <label class="fld grow"><span>หน่วยงานที่รับผิดชอบ</span>
        <select name="unit_id"><option value="">– ไม่ระบุ –</option>
          <?php foreach (bp_unit_tree() as $t) { ?>
            <option value="<?php echo (int)$t['id']; ?>" <?php echo $p['unit_id'] == $t['id'] ? 'selected' : ''; ?>><?php echo h($t['name']); ?></option>
            <?php foreach ($t['children'] as $c) { ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $p['unit_id'] == $c['id'] ? 'selected' : ''; ?>>&nbsp;&nbsp;– <?php echo h($c['name']); ?></option><?php } ?>
          <?php } ?>
        </select></label>
      <label class="fld grow"><span>ผู้รับผิดชอบ</span><input name="owner" value="<?php echo h($p['owner']); ?>"></label>
    </div>
    <div class="form-row mt">
      <label class="fld grow"><span>แหล่งเงิน</span>
        <select name="fund_id"><option value="">– ไม่ระบุ –</option>
          <?php foreach ($fidx as $id => $fd) { ?><option value="<?php echo (int)$id; ?>" <?php echo $p['fund_id'] == $id ? 'selected' : ''; ?>><?php echo h($fd['path']); ?></option><?php } ?>
        </select></label>
      <label class="fld grow"><span>หมวดรายจ่าย</span><input name="expense" list="exp" value="<?php echo h($p['expense']); ?>" placeholder="เช่น งบดำเนินงาน › ค่าวัสดุ"></label>
    </div>
  </div>

  <div class="panel">
    <h3>งบประมาณและการดำเนินงาน</h3>
    <div class="form-row">
      <label class="fld"><span>งบที่เสนอ (บาท)</span><input class="num" name="requested" inputmode="decimal" value="<?php echo $money($p['requested']); ?>"></label>
      <label class="fld"><span>งบอนุมัติ (บาท)</span><input class="num" name="approved" inputmode="decimal" value="<?php echo $money($p['approved']); ?>"></label>
      <label class="fld"><span>เบิกจ่ายแล้ว (บาท)</span><input class="num" name="spent" inputmode="decimal" value="<?php echo $money($p['spent']); ?>"></label>
      <label class="fld"><span>สถานะ</span><select name="status"><?php foreach ($st as $k => $v) { ?><option value="<?php echo h($k); ?>" <?php echo $p['status'] == $k ? 'selected' : ''; ?>><?php echo h($v); ?></option><?php } ?></select></label>
    </div>
    <div class="fld mt"><span>ไตรมาสที่ดำเนินการ</span>
      <div><?php $qn = array(1 => 'ต.ค.–ธ.ค.', 2 => 'ม.ค.–มี.ค.', 3 => 'เม.ย.–มิ.ย.', 4 => 'ก.ค.–ก.ย.'); foreach ($qn as $i => $m) { ?>
        <label class="chk" style="margin-right:14px"><input type="checkbox" name="q[]" value="<?php echo $i; ?>" <?php echo isset($qsel[$i]) ? 'checked' : ''; ?>> ไตรมาส <?php echo $i; ?> <small class="muted">(<?php echo $m; ?>)</small></label>
      <?php } ?></div>
    </div>
  </div>

  <div class="panel">
    <h3>รายละเอียด (แสดงบนหน้าเว็บเมื่อกดที่โครงการ)</h3>
    <label class="fld"><span>วัตถุประสงค์</span><textarea name="objective" rows="3"><?php echo h($p['objective']); ?></textarea></label>
    <label class="fld"><span>เป้าหมาย / ตัวชี้วัด</span><textarea name="target" rows="3"><?php echo h($p['target']); ?></textarea></label>
    <label class="fld"><span>หมายเหตุ</span><input name="note" value="<?php echo h($p['note']); ?>"></label>
  </div>

  <div class="panel">
    <h3>ความสอดคล้อง</h3>
    <?php $sets = bp_aligns($yid); if (!$sets) { ?><p class="muted">ปีนี้ยังไม่มีชุดความสอดคล้อง — <a href="align.php?id=<?php echo $yid; ?>">ตั้งค่า</a></p><?php } ?>
    <?php foreach ($sets as $s) { ?>
      <details class="al-set" <?php foreach ($s['items'] as $it) { if (isset($sel[$it['id']])) { echo 'open'; break; } foreach ($it['children'] as $c) if (isset($sel[$c['id']])) { echo 'open'; break 2; } } ?>>
        <summary><b><?php echo h($s['name']); ?></b> <span class="muted small al-count"></span></summary>
        <?php foreach ($s['items'] as $it) { ?>
          <label class="chk d-block"><input type="checkbox" name="align[]" value="<?php echo (int)$it['id']; ?>" <?php echo isset($sel[$it['id']]) ? 'checked' : ''; ?>> <?php echo h($it['name']); ?></label>
          <?php foreach ($it['children'] as $c) { ?>
          <label class="chk d-block al-sub"><input type="checkbox" name="align[]" value="<?php echo (int)$c['id']; ?>" <?php echo isset($sel[$c['id']]) ? 'checked' : ''; ?>> <?php echo h($c['name']); ?></label>
          <?php } ?>
        <?php } ?>
      </details>
    <?php } ?>
  </div>

  <div class="savebar">
    <span class="muted"><?php echo $pid ? 'แก้ไขล่าสุด ' . h($p['updated_at']) : ''; ?></span>
    <a class="btn btn-ghost" href="projects.php?id=<?php echo $yid; ?>">ยกเลิก</a>
    <?php if (!$pid) { ?><button class="btn btn-ghost" type="submit" name="again" value="1">บันทึกแล้วเพิ่มอีก</button><?php } ?>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</form>
<datalist id="exp"><?php foreach (bp_expense_types() as $g => $list) { echo '<option value="' . h($g) . '">'; foreach ($list as $e) echo '<option value="' . h($g . ' › ' . $e) . '">'; } ?></datalist>
<script>
(function () {
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  document.querySelectorAll('.pj-form input.num').forEach(function (i) {
    i.addEventListener('blur', function () { var v = parseFloat(i.value.replace(/[^0-9.\-]/g, '')); if (!isNaN(v)) i.value = money(v); });
  });
  function count() {
    document.querySelectorAll('.al-set').forEach(function (d) { var n = d.querySelectorAll('input:checked').length; d.querySelector('.al-count').textContent = n ? '(เลือก ' + n + ' ข้อ)' : ''; });
  }
  document.addEventListener('change', count); count();
})();
</script>
<?php admin_foot(); ?>
