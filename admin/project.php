<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();
$isAdmin = bp_is_admin();

$pid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($pid) {
    $p = bp_row('SELECT * FROM ' . bp_t('project') . ' WHERE id=' . $pid);
    if (!$p) bp_redirect($isAdmin ? 'admin/index.php' : 'admin/my.php');
    $yid = (int)$p['year_id'];
} else {
    if (!$isAdmin) bp_redirect('admin/my.php');
    $yid = isset($_GET['y']) ? (int)$_GET['y'] : 0;
    $p = array('id' => 0, 'code' => '', 'title' => '', 'unit_id' => null, 'fund_id' => null, 'expense' => '', 'requested' => 0, 'approved' => 0, 'spent' => 0,
        'spent_q1' => 0, 'spent_q2' => 0, 'spent_q3' => 0, 'spent_q4' => 0, 'status' => 'plan', 'quarters' => '', 'owner' => '', 'objective' => '', 'target' => '',
        'note' => '', 'result' => '', 'result_pct' => null, 'problems' => '', 'updated_at' => '');
}
if ($pid && !bp_can_edit_project($p)) {
    bp_flash('คุณไม่มีสิทธิ์แก้ไขโครงการนี้', 'err');
    bp_redirect($isAdmin ? 'admin/index.php' : 'admin/my.php');
}
$y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
if (!$y) bp_redirect('admin/index.php');
$st = bp_project_statuses();
$fidx = bp_fund_index(bp_funds($yid));
$units = bp_units();
$self = 'admin/project.php?' . ($pid ? 'id=' . $pid : 'y=' . $yid);
$fileDir = BP_ROOT . '/files/projects/';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f = $_POST;
    $act = isset($f['act']) ? $f['act'] : 'save';
    // ---------- รูป / เอกสาร ----------
    if ($act == 'upload' && $pid) {
        $up = isset($_FILES['file']) ? $_FILES['file'] : null;
        $max = min(bp_max_upload(), 15 * 1048576);
        if (!$up || $up['error'] == UPLOAD_ERR_NO_FILE) bp_flash('กรุณาเลือกไฟล์', 'err');
        elseif ($up['error'] == UPLOAD_ERR_INI_SIZE || $up['error'] == UPLOAD_ERR_FORM_SIZE || $up['size'] > $max) bp_flash('ไฟล์ใหญ่เกิน ' . bp_human_size($max), 'err');
        elseif ($up['error'] != UPLOAD_ERR_OK || !is_uploaded_file($up['tmp_name'])) bp_flash('อัปโหลดไม่สำเร็จ (รหัส ' . (int)$up['error'] . ')', 'err');
        else {
            // ตรวจชนิดไฟล์จากเนื้อไฟล์จริง: รูป jpg/png/webp/gif หรือ PDF
            $ext = '';
            $kind = 'doc';
            $info = @getimagesize($up['tmp_name']);
            $types = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif');
            if (defined('IMAGETYPE_WEBP')) $types[IMAGETYPE_WEBP] = 'webp';
            if ($info && isset($types[$info[2]])) { $ext = $types[$info[2]]; $kind = 'photo'; }
            else {
                $fh = fopen($up['tmp_name'], 'rb');
                $head = $fh ? fread($fh, 1024) : '';
                if ($fh) fclose($fh);
                if (strpos($head, '%PDF-') !== false) $ext = 'pdf';
            }
            if ($ext === '') bp_flash('รับเฉพาะรูป (jpg, png, webp, gif) หรือ PDF', 'err');
            else {
                if (!is_dir($fileDir)) @mkdir($fileDir, 0755, true);
                $name = bp_random_hex(12) . '.' . $ext;
                if (!@move_uploaded_file($up['tmp_name'], $fileDir . $name)) bp_flash('บันทึกไฟล์ไม่ได้ — ให้สิทธิ์เขียนโฟลเดอร์ files/projects/ แก่เว็บเซิร์ฟเวอร์', 'err');
                else {
                    @chmod($fileDir . $name, 0644);
                    $orig = preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', '_', basename((string)$up['name']));
                    $title = trim(isset($f['title']) ? (string)$f['title'] : '');
                    bp_query('INSERT INTO ' . bp_t('project_file') . ' (project_id,kind,file,orig_name,title,size,created_at) VALUES (' . $pid . ',' . q($kind) . ',' . q($name) . ','
                        . q(mb_substr($orig, 0, 255)) . ',' . q(mb_substr($title, 0, 255)) . ',' . (int)filesize($fileDir . $name) . ",'" . date('Y-m-d H:i:s') . "')");
                    bp_query('UPDATE ' . bp_t('project') . " SET updated_at='" . date('Y-m-d H:i:s') . "' WHERE id=" . $pid);
                    bp_done('project.file', 'แนบ' . ($kind == 'photo' ? 'รูป' : 'เอกสาร') . ' "' . ($title !== '' ? $title : $orig) . '" ให้โครงการ ' . $p['code'] . ' แล้ว');
                }
            }
        }
        bp_redirect($self . '#files');
    }
    if ($act == 'delfile' && $pid) {
        $fr = bp_row('SELECT * FROM ' . bp_t('project_file') . ' WHERE id=' . (int)$f['fid'] . ' AND project_id=' . $pid);
        if ($fr) {
            if (preg_match('/^[0-9a-f]{24}\.(jpg|png|gif|webp|pdf)$/', $fr['file']) && is_file($fileDir . $fr['file'])) @unlink($fileDir . $fr['file']);
            bp_query('DELETE FROM ' . bp_t('project_file') . ' WHERE id=' . (int)$fr['id']);
            bp_done('project.file', 'ลบไฟล์ "' . ($fr['title'] !== '' ? $fr['title'] : $fr['orig_name']) . '" ของโครงการ ' . $p['code'] . ' แล้ว');
        }
        bp_redirect($self . '#files');
    }

    // ---------- บันทึกข้อมูลโครงการ ----------
    $txt = function ($k, $len) use ($f) { return q(mb_substr(trim((string)(isset($f[$k]) ? $f[$k] : '')), 0, $len)); };
    $status = isset($f['status']) && isset($st[$f['status']]) ? $f['status'] : 'plan';
    $qsum = 0;
    $qset = '';
    for ($i = 1; $i <= 4; $i++) {
        $v = bp_parse_amount(isset($f['spent_q' . $i]) ? $f['spent_q' . $i] : 0);
        $qsum += $v;
        $qset .= ',spent_q' . $i . '=' . $v;
    }
    $spent = $qsum > 0 ? round($qsum, 2) : bp_parse_amount(isset($f['spent']) ? $f['spent'] : 0);
    $pct = isset($f['result_pct']) && trim($f['result_pct']) !== '' ? max(0, min(100, (int)$f['result_pct'])) : 'NULL';
    // ส่วนที่ผู้กรอกข้อมูลหน่วยงานแก้ได้
    $set = 'status=' . q($status) . ',spent=' . $spent . $qset . ',result=' . $txt('result', 5000) . ',result_pct=' . $pct . ',problems=' . $txt('problems', 5000)
        . ",updated_at='" . date('Y-m-d H:i:s') . "'";
    if ($isAdmin) {
        $title = mb_substr(trim((string)(isset($f['title']) ? $f['title'] : '')), 0, 500);
        if ($title === '') {
            bp_flash('กรุณากรอกชื่อโครงการ', 'err');
            bp_redirect($self);
        }
        $unit = isset($f['unit_id']) && isset($units[(int)$f['unit_id']]) ? (int)$f['unit_id'] : 'NULL';
        $fund = isset($f['fund_id']) && isset($fidx[(int)$f['fund_id']]) ? (int)$f['fund_id'] : 'NULL';
        $qs = array();
        if (isset($f['q']) && is_array($f['q'])) foreach ($f['q'] as $q) if ((int)$q >= 1 && (int)$q <= 4) $qs[(int)$q] = (int)$q;
        ksort($qs);
        $set .= ',title=' . q($title) . ',code=' . $txt('code', 32) . ',unit_id=' . $unit . ',fund_id=' . $fund . ',expense=' . $txt('expense', 255)
            . ',requested=' . bp_parse_amount(isset($f['requested']) ? $f['requested'] : 0) . ',approved=' . bp_parse_amount(isset($f['approved']) ? $f['approved'] : 0)
            . ',quarters=' . q(implode(',', $qs)) . ',owner=' . $txt('owner', 255) . ',objective=' . $txt('objective', 5000) . ',target=' . $txt('target', 5000) . ',note=' . $txt('note', 500);
    } else {
        $title = $p['title'];
    }
    if ($pid) bp_query('UPDATE ' . bp_t('project') . ' SET ' . $set . ' WHERE id=' . $pid);
    else { bp_query('INSERT INTO ' . bp_t('project') . ' SET year_id=' . $yid . ',' . $set); $pid = bp_insert_id(); }
    if ($isAdmin) {
        // ความสอดคล้อง: เฉพาะข้อของปีนี้
        bp_query('DELETE FROM ' . bp_t('project_align') . ' WHERE project_id=' . $pid);
        $valid = array();
        foreach (bp_rows('SELECT id FROM ' . bp_t('align') . ' WHERE year_id=' . $yid . ' AND parent_id IS NOT NULL') as $r) $valid[(int)$r['id']] = true;
        $vals = array();
        if (isset($f['align']) && is_array($f['align'])) foreach ($f['align'] as $a) if (isset($valid[(int)$a])) $vals[(int)$a] = '(' . $pid . ',' . (int)$a . ')';
        if ($vals) bp_query('INSERT INTO ' . bp_t('project_align') . ' (project_id,align_id) VALUES ' . implode(',', $vals));
    }
    bp_done('project.save', 'บันทึกโครงการ "' . $title . '" แล้ว');
    if (!empty($f['again'])) bp_redirect('admin/project.php?y=' . $yid);
    bp_redirect($isAdmin ? 'admin/projects.php?id=' . $yid : 'admin/my.php');
}

$sel = array();
if ($pid) foreach (bp_rows('SELECT align_id FROM ' . bp_t('project_align') . ' WHERE project_id=' . $pid) as $r) $sel[(int)$r['align_id']] = true;
$qsel = array_flip(array_filter(explode(',', (string)$p['quarters'])));
$money = function ($v) { return (float)$v ? bp_money($v) : ''; };
$files = $pid ? bp_project_files($pid) : array();
$hasQ = (float)$p['spent_q1'] + (float)$p['spent_q2'] + (float)$p['spent_q3'] + (float)$p['spent_q4'] > 0;
admin_head(($pid ? 'แก้ไขโครงการ' : 'เพิ่มโครงการ') . ' · ปี ' . $y['year'], $isAdmin ? 'index' : 'my');
if ($isAdmin) admin_year_tabs($y, 'projects');
$ro = $isAdmin ? '' : ' disabled';
?>
<form method="post" class="pj-form" id="pjForm">
  <?php echo bp_csrf_field(); ?>
  <div class="panel">
    <?php if (!$isAdmin) { ?><p class="muted small">ข้อมูลส่วนนี้แก้ได้เฉพาะผู้ดูแลระบบ — คุณแก้ได้ที่ส่วน "การดำเนินงานและการเบิกจ่าย" และ "ผลการดำเนินงาน" ด้านล่าง</p><?php } ?>
    <div class="form-row">
      <label class="fld" style="flex:0 1 160px"><span>รหัสโครงการ</span><input name="code" value="<?php echo h($p['code']); ?>" placeholder="เช่น P<?php echo substr($y['year'], -2); ?>-001"<?php echo $ro; ?>></label>
      <label class="fld grow"><span>ชื่อโครงการ *</span><input name="title" value="<?php echo h($p['title']); ?>" required<?php echo $ro; ?>></label>
    </div>
    <div class="form-row mt">
      <label class="fld grow"><span>หน่วยงานที่รับผิดชอบ</span>
        <select name="unit_id"<?php echo $ro; ?>><option value="">– ไม่ระบุ –</option>
          <?php foreach (bp_unit_tree() as $t) { ?>
            <option value="<?php echo (int)$t['id']; ?>" <?php echo $p['unit_id'] == $t['id'] ? 'selected' : ''; ?>><?php echo h($t['name']); ?></option>
            <?php foreach ($t['children'] as $c) { ?><option value="<?php echo (int)$c['id']; ?>" <?php echo $p['unit_id'] == $c['id'] ? 'selected' : ''; ?>>&nbsp;&nbsp;– <?php echo h($c['name']); ?></option><?php } ?>
          <?php } ?>
        </select></label>
      <label class="fld grow"><span>ผู้รับผิดชอบ</span><input name="owner" value="<?php echo h($p['owner']); ?>"<?php echo $ro; ?>></label>
    </div>
    <div class="form-row mt">
      <label class="fld grow"><span>แหล่งเงิน</span>
        <select name="fund_id"<?php echo $ro; ?>><option value="">– ไม่ระบุ –</option>
          <?php foreach ($fidx as $id => $fd) { ?><option value="<?php echo (int)$id; ?>" <?php echo $p['fund_id'] == $id ? 'selected' : ''; ?>><?php echo h($fd['path']); ?></option><?php } ?>
        </select></label>
      <label class="fld grow"><span>หมวดรายจ่าย</span><input name="expense" list="exp" value="<?php echo h($p['expense']); ?>" placeholder="เช่น งบดำเนินงาน › ค่าวัสดุ"<?php echo $ro; ?>></label>
    </div>
    <div class="form-row mt">
      <label class="fld"><span>งบที่เสนอ (บาท)</span><input class="num" name="requested" inputmode="decimal" value="<?php echo $money($p['requested']); ?>"<?php echo $ro; ?>></label>
      <label class="fld"><span>งบอนุมัติ (บาท)</span><input class="num" name="approved" id="approved" inputmode="decimal" value="<?php echo $money($p['approved']); ?>"<?php echo $ro; ?>></label>
      <div class="fld"><span>ไตรมาสที่ดำเนินการ</span>
        <div><?php $qn = array(1 => 'ต.ค.–ธ.ค.', 2 => 'ม.ค.–มี.ค.', 3 => 'เม.ย.–มิ.ย.', 4 => 'ก.ค.–ก.ย.'); foreach ($qn as $i => $m) { ?>
          <label class="chk" style="margin-right:12px" title="<?php echo $m; ?>"><input type="checkbox" name="q[]" value="<?php echo $i; ?>" <?php echo isset($qsel[$i]) ? 'checked' : ''; ?><?php echo $ro; ?>> ไตรมาส <?php echo $i; ?></label>
        <?php } ?></div>
      </div>
    </div>
  </div>

  <div class="panel">
    <h3>การดำเนินงานและการเบิกจ่าย</h3>
    <div class="form-row">
      <label class="fld"><span>สถานะ</span><select name="status"><?php foreach ($st as $k => $v) { ?><option value="<?php echo h($k); ?>" <?php echo $p['status'] == $k ? 'selected' : ''; ?>><?php echo h($v); ?></option><?php } ?></select></label>
    </div>
    <p class="muted small mt">กรอกยอด<b>เบิกจ่ายในแต่ละไตรมาส</b> (ไม่ใช่ยอดสะสม) — ใช้ทำรายงานรอบ 6 เดือน (ไตรมาส 1–2) และ 12 เดือน · ถ้าไม่ทราบรายไตรมาส กรอกเฉพาะยอดรวมได้</p>
    <div class="form-row">
      <?php foreach ($qn as $i => $m) { ?>
      <label class="fld"><span>ไตรมาส <?php echo $i; ?> <small>(<?php echo $m; ?>)</small></span><input class="num sq" name="spent_q<?php echo $i; ?>" inputmode="decimal" value="<?php echo $money($p['spent_q' . $i]); ?>"></label>
      <?php } ?>
      <label class="fld"><span>เบิกจ่ายรวม (บาท)</span><input class="num" name="spent" id="spentTotal" inputmode="decimal" value="<?php echo $money($p['spent']); ?>"<?php echo $hasQ ? ' readonly' : ''; ?>></label>
    </div>
    <p class="small" id="spentPct"></p>
  </div>

  <div class="panel">
    <h3>ผลการดำเนินงาน (แสดงบนหน้าเว็บ)</h3>
    <div class="form-row">
      <label class="fld" style="flex:0 1 220px"><span>ผลสำเร็จตามตัวชี้วัด (%)</span><input type="number" min="0" max="100" name="result_pct" value="<?php echo $p['result_pct'] === null ? '' : (int)$p['result_pct']; ?>" placeholder="0–100"></label>
    </div>
    <label class="fld mt"><span>ผลการดำเนินงาน / ผลที่ได้รับ</span><textarea name="result" rows="3" placeholder="เช่น จัดอบรม 2 รุ่น ผู้เข้าร่วม 92 คน ผ่านเกณฑ์ร้อยละ 85"><?php echo h($p['result']); ?></textarea></label>
    <label class="fld"><span>ปัญหา อุปสรรค และแนวทางแก้ไข</span><textarea name="problems" rows="2"><?php echo h($p['problems']); ?></textarea></label>
  </div>

  <?php if ($isAdmin) { ?>
  <div class="panel">
    <h3>รายละเอียดโครงการ</h3>
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
  <?php } ?>

  <div class="savebar">
    <span class="muted"><?php echo $pid && $p['updated_at'] ? 'แก้ไขล่าสุด ' . h(bp_thai_date($p['updated_at'], true)) : ''; ?></span>
    <a class="btn btn-ghost" href="<?php echo $isAdmin ? 'projects.php?id=' . $yid : 'my.php'; ?>">ยกเลิก</a>
    <?php if (!$pid) { ?><button class="btn btn-ghost" type="submit" name="again" value="1">บันทึกแล้วเพิ่มอีก</button><?php } ?>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</form>

<?php if ($pid) { ?>
<div class="panel" id="files">
  <h3>รูปกิจกรรม / เอกสารโครงการ</h3>
  <p class="muted small">แสดงบนหน้ารายละเอียดโครงการให้คนทั่วไปเห็น · รับรูป (jpg, png, webp, gif) และ PDF ไม่เกิน <?php echo bp_human_size(min(bp_max_upload(), 15 * 1048576)); ?> ต่อไฟล์ · ระวังอย่าใส่เอกสารที่มีข้อมูลส่วนบุคคล</p>
  <?php if ($files) { ?>
  <div class="pf-grid">
    <?php foreach ($files as $fl) { $url = bp_url('files/projects/' . $fl['file']); ?>
    <div class="pf">
      <?php if ($fl['kind'] == 'photo') { ?><a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><img src="<?php echo h($url); ?>" alt="" loading="lazy"></a>
      <?php } else { ?><a class="pf-doc" href="<?php echo h($url); ?>" target="_blank" rel="noopener">PDF</a><?php } ?>
      <div class="pf-name"><?php echo h($fl['title'] !== '' ? $fl['title'] : $fl['orig_name']); ?></div>
      <form method="post" onsubmit="return confirm('ลบไฟล์นี้?')"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delfile"><input type="hidden" name="fid" value="<?php echo (int)$fl['id']; ?>"><button class="btn btn-sm btn-danger">ลบ</button></form>
    </div>
    <?php } ?>
  </div>
  <?php } ?>
  <form method="post" enctype="multipart/form-data" class="form-row mt">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="upload">
    <label class="fld grow"><span>ไฟล์</span><input type="file" name="file" accept="image/jpeg,image/png,image/webp,image/gif,application/pdf" required></label>
    <label class="fld grow"><span>คำอธิบาย (ไม่บังคับ)</span><input name="title" placeholder="เช่น ภาพกิจกรรมวันที่ 12 ธ.ค. / รายงานสรุปโครงการ"></label>
    <button class="btn" type="submit">อัปโหลด</button>
  </form>
</div>
<?php } ?>
<datalist id="exp"><?php foreach (bp_expense_types() as $g => $list) { echo '<option value="' . h($g) . '">'; foreach ($list as $e) echo '<option value="' . h($g . ' › ' . $e) . '">'; } ?></datalist>
<script>
(function () {
  var money = function (v) { return Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
  var num = function (s) { var v = parseFloat(String(s).replace(/[^0-9.\-]/g, '')); return isNaN(v) ? 0 : v; };
  document.querySelectorAll('.pj-form input.num').forEach(function (i) {
    i.addEventListener('blur', function () { if (i.value.trim() !== '' && !i.readOnly) i.value = money(num(i.value)); });
  });
  var tot = document.getElementById('spentTotal'), ap = document.getElementById('approved'), out = document.getElementById('spentPct');
  function calc() {
    var s = 0, any = false;
    document.querySelectorAll('.sq').forEach(function (i) { if (i.value.trim() !== '') { any = true; s += num(i.value); } });
    tot.readOnly = any;
    if (any) tot.value = money(s);
    var a = num(ap.value), t = num(tot.value);
    out.textContent = a > 0 ? 'เบิกจ่ายแล้ว ' + (t * 100 / a).toFixed(1) + '% ของงบอนุมัติ' + (t > a ? ' — เกินงบอนุมัติ!' : '') : '';
    out.className = 'small ' + (t > a && a > 0 ? 'text-err' : 'muted');
  }
  document.querySelectorAll('.sq').forEach(function (i) { i.addEventListener('input', calc); });
  tot.addEventListener('input', calc); ap.addEventListener('input', calc); calc();
  function count() {
    document.querySelectorAll('.al-set').forEach(function (d) { var n = d.querySelectorAll('input:checked').length; d.querySelector('.al-count').textContent = n ? '(เลือก ' + n + ' ข้อ)' : ''; });
  }
  document.addEventListener('change', count); count();
})();
</script>
<?php admin_foot(); ?>
