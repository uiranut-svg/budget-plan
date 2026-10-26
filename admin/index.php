<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();

$defaultCats = array('งบบุคลากร', 'งบดำเนินงาน', 'งบลงทุน', 'งบเงินอุดหนุน', 'งบรายจ่ายอื่น');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'add') {
        $year = (int)$_POST['year'];
        if ($year < 2400 || $year > 2700) {
            bp_flash('ปีงบประมาณต้องเป็น พ.ศ. เช่น ' . (date('Y') + 543), 'err');
        } elseif (bp_row('SELECT id FROM ' . bp_t('year') . ' WHERE year=' . $year)) {
            bp_flash('มีปีงบประมาณ ' . $year . ' อยู่แล้ว', 'err');
        } else {
            bp_query('INSERT INTO ' . bp_t('year') . ' (year,title,note,published,updated_at) VALUES (' . $year . ',' . q(trim($_POST['title'])) . ",'',0,'" . date('Y-m-d H:i:s') . "')");
            $yid = bp_insert_id();
            // หมวด: คัดลอกชื่อ/สีจากปีล่าสุด (ไม่คัดลอกยอด) หรือใช้หมวดมาตรฐาน
            $src = bp_row('SELECT id FROM ' . bp_t('year') . ' WHERE id<>' . $yid . ' ORDER BY year DESC LIMIT 1');
            $cats = array();
            if ($src && !empty($_POST['copy'])) {
                foreach (bp_rows('SELECT name,color FROM ' . bp_t('category') . ' WHERE year_id=' . (int)$src['id'] . ' ORDER BY sort,id') as $c) $cats[] = $c;
                $copyItems = !empty($_POST['copy_items']);
            }
            if (!$cats) foreach ($defaultCats as $i => $n) $cats[] = array('name' => $n, 'color' => bp_palette($i));
            foreach ($cats as $i => $c) {
                bp_query('INSERT INTO ' . bp_t('category') . ' (year_id,name,color,amount,sort) VALUES (' . $yid . ',' . q($c['name']) . ',' . q($c['color']) . ',0,' . $i . ')');
                if (!empty($copyItems)) {
                    $cid = bp_insert_id();
                    $old = bp_row('SELECT id FROM ' . bp_t('category') . ' WHERE year_id=' . (int)$src['id'] . ' AND name=' . q($c['name']) . ' LIMIT 1');
                    if ($old) bp_query('INSERT INTO ' . bp_t('item') . ' (category_id,name,department,amount,note,sort) SELECT ' . $cid . ',name,department,0,note,sort FROM ' . bp_t('item') . ' WHERE category_id=' . (int)$old['id']);
                }
            }
            bp_flash('เพิ่มปีงบประมาณ ' . $year . ' แล้ว (ยังไม่เผยแพร่ — กรอกข้อมูลแล้วติ๊ก "เผยแพร่")');
            bp_redirect('admin/year.php?id=' . $yid);
        }
    } elseif ($act == 'delete') {
        $yid = (int)$_POST['id'];
        $y = bp_row('SELECT * FROM ' . bp_t('year') . ' WHERE id=' . $yid);
        if ($y && isset($_POST['confirm']) && trim($_POST['confirm']) === (string)$y['year']) {
            bp_query('DELETE i FROM ' . bp_t('item') . ' i JOIN ' . bp_t('category') . ' c ON c.id=i.category_id WHERE c.year_id=' . $yid);
            bp_query('DELETE FROM ' . bp_t('category') . ' WHERE year_id=' . $yid);
            bp_query('UPDATE ' . bp_t('book') . ' SET year_id=NULL WHERE year_id=' . $yid);
            bp_query('DELETE FROM ' . bp_t('year') . ' WHERE id=' . $yid);
            bp_flash('ลบปีงบประมาณ ' . $y['year'] . ' แล้ว (เล่มแผนของปีนี้ยังอยู่)');
        } else {
            bp_flash('ไม่ได้ลบ — พิมพ์ปีให้ตรงเพื่อยืนยัน', 'err');
        }
    }
    bp_redirect('admin/index.php');
}

$years = bp_years(false);
admin_head('ปีงบประมาณ', 'index');
?>
<div class="panel flush">
  <table class="tbl">
    <thead><tr><th>ปีงบประมาณ</th><th class="hide-sm">ชื่อแผน</th><th class="num">งบประมาณรวม (บาท)</th><th class="num hide-sm">หมวด / รายการ</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$years) { ?><tr><td colspan="6" class="center muted">ยังไม่มีปีงบประมาณ — เพิ่มด้านล่าง</td></tr><?php } ?>
    <?php foreach ($years as $y) { $d = bp_year_data($y['id']); $n = 0; foreach ($d['categories'] as $c) $n += count($c['items']); ?>
      <tr>
        <td><b><?php echo (int)$y['year']; ?></b></td>
        <td class="hide-sm"><?php echo h($y['title']); ?></td>
        <td class="num"><?php echo bp_money($d['total']); ?></td>
        <td class="num hide-sm"><?php echo count($d['categories']); ?> / <?php echo $n; ?></td>
        <td><?php echo $y['published'] ? '<span class="tag ok">เผยแพร่</span>' : '<span class="tag">ฉบับร่าง</span>'; ?></td>
        <td class="actions">
          <a class="btn btn-sm" href="year.php?id=<?php echo (int)$y['id']; ?>">แก้ไข</a>
          <form method="post" class="inline" onsubmit="var v=prompt('พิมพ์ปี <?php echo (int)$y['year']; ?> เพื่อยืนยันการลบ (ข้อมูลหมวด/รายการของปีนี้จะหายทั้งหมด)');if(!v)return false;this.confirm.value=v;">
            <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?php echo (int)$y['id']; ?>"><input type="hidden" name="confirm">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
          </form>
        </td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
</div>

<div class="panel">
  <h2>เพิ่มปีงบประมาณ</h2>
  <form method="post" class="form-row">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="add">
    <label class="fld"><span>ปี พ.ศ.</span><input type="number" name="year" value="<?php echo $years ? (int)$years[0]['year'] + 1 : (date('n') >= 10 ? date('Y') + 544 : date('Y') + 543); ?>" required></label>
    <label class="fld grow"><span>ชื่อแผน (ไม่บังคับ)</span><input name="title" placeholder="เช่น แผนปฏิบัติราชการ ประจำปีงบประมาณ พ.ศ. ..."></label>
    <?php if ($years) { ?>
    <label class="chk"><input type="checkbox" name="copy" value="1" checked> ใช้หมวดเดียวกับปี <?php echo (int)$years[0]['year']; ?></label>
    <label class="chk"><input type="checkbox" name="copy_items" value="1"> คัดลอกรายการย่อยด้วย (ยอดเป็น 0)</label>
    <?php } ?>
    <button class="btn" type="submit">เพิ่ม</button>
  </form>
  <p class="muted small">ปีใหม่จะเป็น "ฉบับร่าง" คนทั่วไปยังไม่เห็น จนกว่าจะติ๊กเผยแพร่ในหน้าแก้ไข</p>
</div>
<?php admin_foot(); ?>
