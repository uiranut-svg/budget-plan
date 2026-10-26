<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();

$yid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
if (!$y) bp_redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = isset($_POST['title']) ? trim((string)$_POST['title']) : '';
    $note = isset($_POST['note']) ? trim((string)$_POST['note']) : '';
    $pub = !empty($_POST['published']) ? 1 : 0;
    $cats = json_decode(isset($_POST['payload']) ? (string)$_POST['payload'] : '', true);
    $valid = is_array($cats) && (!$cats || array_keys($cats) === range(0, count($cats) - 1));
    if ($valid) foreach ($cats as $c) if (!is_array($c) || (isset($c['items']) && !is_array($c['items']))) $valid = false;
    if (!$valid) {
        bp_flash('ข้อมูลที่ส่งมาไม่ถูกต้อง ยังไม่ได้บันทึก', 'err');
        bp_redirect('admin/year.php?id=' . $yid);
    }
    $ok = bp_query('START TRANSACTION');
    $ok = $ok && bp_query('UPDATE ' . bp_t('year') . ' SET title=' . q(mb_substr($title, 0, 255)) . ',note=' . q($note) . ',published=' . $pub . ",updated_at='" . date('Y-m-d H:i:s') . "' WHERE id=" . $yid);
    $ok = $ok && bp_query('DELETE i FROM ' . bp_t('item') . ' i JOIN ' . bp_t('category') . ' c ON c.id=i.category_id WHERE c.year_id=' . $yid);
    $ok = $ok && bp_query('DELETE FROM ' . bp_t('category') . ' WHERE year_id=' . $yid);
    $nItems = 0;
    foreach ($cats as $ci => $c) {
        if (!$ok) break;
        $name = isset($c['name']) ? trim((string)$c['name']) : '';
        $items = isset($c['items']) && is_array($c['items']) ? $c['items'] : array();
        if ($name === '' && !$items) continue;
        if ($name === '') $name = 'หมวดที่ ' . ($ci + 1);
        $color = isset($c['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $c['color']) ? $c['color'] : '';
        $ok = bp_query('INSERT INTO ' . bp_t('category') . ' (year_id,name,color,amount,sort) VALUES (' . $yid . ',' . q(mb_substr($name, 0, 255)) . ',' . q($color) . ','
            . bp_parse_amount(isset($c['amount']) ? $c['amount'] : 0) . ',' . (int)$ci . ')');
        $cid = bp_insert_id();
        $vals = array();
        foreach ($items as $ii => $it) {
            $iname = isset($it['name']) ? trim((string)$it['name']) : '';
            if ($iname === '') continue;
            $vals[] = '(' . $cid . ',' . q(mb_substr($iname, 0, 500)) . ',' . q(mb_substr(trim((string)(isset($it['department']) ? $it['department'] : '')), 0, 255)) . ','
                . bp_parse_amount(isset($it['amount']) ? $it['amount'] : 0) . ',' . q(mb_substr(trim((string)(isset($it['note']) ? $it['note'] : '')), 0, 500)) . ',' . (int)$ii . ')';
        }
        foreach (array_chunk($vals, 200) as $chunk) {
            if ($ok) $ok = bp_query('INSERT INTO ' . bp_t('item') . ' (category_id,name,department,amount,note,sort) VALUES ' . implode(',', $chunk));
        }
        $nItems += count($vals);
    }
    if ($ok) {
        bp_query('COMMIT');
        bp_flash('บันทึกปีงบประมาณ ' . $y['year'] . ' แล้ว (' . count($cats) . ' หมวด, ' . $nItems . ' รายการ)' . ($pub ? '' : ' — ยังเป็นฉบับร่าง'));
    } else {
        bp_query('ROLLBACK');
        bp_flash('บันทึกไม่สำเร็จ ข้อมูลเดิมยังอยู่', 'err');
    }
    bp_redirect('admin/year.php?id=' . $yid);
}

$data = bp_year_data($yid);
$init = array();
foreach ($data['categories'] as $c) {
    $items = array();
    foreach ($c['items'] as $it) $items[] = array('name' => $it['name'], 'department' => $it['department'], 'amount' => (float)$it['amount'], 'note' => $it['note']);
    $init[] = array('name' => $c['name'], 'color' => $c['color'], 'amount' => (float)$c['amount'], 'items' => $items);
}
$deps = array();
foreach (bp_rows('SELECT DISTINCT department FROM ' . bp_t('item') . " WHERE department<>'' ORDER BY department LIMIT 300") as $r) $deps[] = $r['department'];

admin_head('ปีงบประมาณ ' . $y['year'], 'index');
?>
<form method="post" id="yearForm">
  <?php echo bp_csrf_field(); ?>
  <input type="hidden" name="payload" id="payload">
  <div class="panel">
    <div class="form-row">
      <label class="fld grow"><span>ชื่อแผน</span><input name="title" value="<?php echo h($y['title']); ?>" placeholder="เช่น แผนปฏิบัติราชการ ประจำปีงบประมาณ พ.ศ. <?php echo (int)$y['year']; ?>"></label>
      <label class="chk big"><input type="checkbox" name="published" value="1" <?php echo $y['published'] ? 'checked' : ''; ?>> เผยแพร่ให้คนทั่วไปเห็น</label>
    </div>
    <label class="fld"><span>หมายเหตุ / คำอธิบาย (แสดงใต้กราฟ)</span><textarea name="note" rows="2"><?php echo h($y['note']); ?></textarea></label>
  </div>

  <div class="ed-help muted small">
    หมวดที่มี <b>รายการย่อย</b> = ยอดหมวดคำนวณจากผลรวมรายการ · ไม่มีรายการย่อย = ใช้ "ยอดหมวด" ที่กรอก ·
    ปุ่ม <b>วางจาก Excel</b> = คัดลอกคอลัมน์ <i>รายการ | หน่วยงาน | จำนวนเงิน | หมายเหตุ</i> จาก Excel มาวางได้ทีละหลายแถว
  </div>
  <div id="cats"></div>
  <p><button type="button" class="btn btn-ghost" id="addCat">+ เพิ่มหมวด</button></p>

  <div class="savebar">
    <span>รวมทั้งสิ้น <b id="grand">0.00</b> บาท</span>
    <a class="btn btn-ghost" href="index.php">ยกเลิก</a>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</form>
<datalist id="deps"><?php foreach ($deps as $d) echo '<option value="' . h($d) . '">'; ?></datalist>
<?php admin_foot('<script>window.ED=' . bp_json(array('cats' => $init)) . ';</script><script src="' . h(bp_url('assets/js/editor.js')) . '?v=1"></script>'); ?>
