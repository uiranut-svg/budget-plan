<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == 'seed') {
        require BP_ROOT . '/inc/sample.php';
        $n = bp_seed_units();
        bp_flash($n ? 'ใส่หน่วยงานมาตรฐาน ' . $n . ' หน่วยงานแล้ว — แก้ไขให้ตรงกับสถานศึกษาได้เลย' : 'มีหน่วยงานอยู่แล้ว ไม่ได้เพิ่ม', $n ? 'ok' : 'err');
        bp_redirect('admin/units.php');
    }
    // แปลงข้อความ: บรรทัดปกติ = ฝ่าย, ขึ้นต้นด้วย - หรือเว้นวรรค/แท็บ = งาน/แผนกในฝ่ายด้านบน
    $tree = array();
    foreach (preg_split('/\r?\n/', isset($_POST['text']) ? (string)$_POST['text'] : '') as $line) {
        if (trim($line) === '') continue;
        $child = (bool)preg_match('/^(\s+|[-–•*]\s*)/u', $line);
        $name = mb_substr(trim(preg_replace('/^[\s\-–•*]+/u', '', $line)), 0, 255);
        if ($name === '') continue;
        if ($child && $tree) $tree[count($tree) - 1]['children'][] = $name;
        else $tree[] = array('name' => $name, 'children' => array());
    }
    $old = bp_units(true);
    $byTop = array();
    $byChild = array();
    $byName = array();
    foreach ($old as $u) {
        if ($u['parent_id']) $byChild[$u['parent_id'] . '|' . $u['name']] = $u['id'];
        else $byTop[$u['name']] = $u['id'];
        if (!isset($byName[$u['name']])) $byName[$u['name']] = $u['id'];
    }
    $keep = array();
    bp_query('START TRANSACTION');
    foreach ($tree as $i => $t) {
        if (isset($byTop[$t['name']])) {
            $tid = $byTop[$t['name']];
            bp_query('UPDATE ' . bp_t('unit') . ' SET sort=' . $i . ' WHERE id=' . (int)$tid);
        } else {
            bp_query('INSERT INTO ' . bp_t('unit') . ' (parent_id,name,sort) VALUES (NULL,' . q($t['name']) . ',' . $i . ')');
            $tid = bp_insert_id();
        }
        $keep[$tid] = true;
        foreach ($t['children'] as $j => $c) {
            $cid = isset($byChild[$tid . '|' . $c]) ? $byChild[$tid . '|' . $c] : null;
            // ย้ายฝ่าย: ใช้ id เดิมของหน่วยงานชื่อเดียวกัน (โครงการที่ผูกไว้ไม่หลุด)
            if (!$cid && isset($byName[$c]) && !isset($keep[$byName[$c]]) && $old[$byName[$c]]['parent_id']) $cid = $byName[$c];
            if ($cid) {
                bp_query('UPDATE ' . bp_t('unit') . ' SET parent_id=' . (int)$tid . ',sort=' . $j . ' WHERE id=' . (int)$cid);
            } else {
                bp_query('INSERT INTO ' . bp_t('unit') . ' (parent_id,name,sort) VALUES (' . (int)$tid . ',' . q($c) . ',' . $j . ')');
                $cid = bp_insert_id();
            }
            $keep[$cid] = true;
        }
    }
    $del = array();
    foreach ($old as $u) if (!isset($keep[$u['id']])) $del[] = (int)$u['id'];
    if ($del) {
        bp_query('UPDATE ' . bp_t('project') . ' SET unit_id=NULL WHERE unit_id IN (' . implode(',', $del) . ')');
        bp_query('DELETE FROM ' . bp_t('unit') . ' WHERE id IN (' . implode(',', $del) . ')');
    }
    bp_query('COMMIT');
    bp_done('units', 'บันทึกหน่วยงานแล้ว (' . count($keep) . ' หน่วยงาน' . ($del ? ', ลบ ' . count($del) : '') . ')');
    bp_redirect('admin/units.php');
}

$tree = bp_unit_tree();
$count = array();
foreach (bp_rows('SELECT unit_id, COUNT(*) n FROM ' . bp_t('project') . ' WHERE unit_id IS NOT NULL GROUP BY unit_id') as $r) $count[$r['unit_id']] = (int)$r['n'];
$text = '';
foreach ($tree as $t) {
    $text .= $t['name'] . "\n";
    foreach ($t['children'] as $c) $text .= '- ' . $c['name'] . "\n";
}
admin_head('หน่วยงาน', 'units');
?>
<div class="grid2">
  <form method="post" class="panel">
    <?php echo bp_csrf_field(); ?>
    <h2>แก้ไขรายชื่อหน่วยงาน</h2>
    <p class="muted small">บรรทัดละ 1 หน่วยงาน · บรรทัดปกติ = <b>ฝ่าย</b> · ขึ้นต้นด้วย <code>-</code> = <b>งาน / แผนกวิชา</b> ในฝ่ายด้านบน ·
      เปลี่ยนชื่อหน่วยงาน = โครงการที่เคยผูกไว้จะหลุด (ต้องเลือกใหม่) · ใช้ร่วมกันทุกปีงบประมาณ</p>
    <textarea name="text" rows="24" spellcheck="false" placeholder="ฝ่ายบริหารทรัพยากร&#10;- งานการเงิน&#10;- งานพัสดุ&#10;ฝ่ายวิชาการ&#10;- แผนกวิชาช่างยนต์"><?php echo h($text); ?></textarea>
    <p><button class="btn" type="submit">บันทึก</button></p>
  </form>
  <div class="panel">
    <h2>โครงสร้างปัจจุบัน</h2>
    <?php if (!$tree) { ?>
      <p class="muted">ยังไม่มีหน่วยงาน</p>
      <form method="post"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="seed">
        <button class="btn btn-ghost" type="submit">ใส่หน่วยงานมาตรฐานของวิทยาลัยอาชีวศึกษา (4 ฝ่าย)</button></form>
    <?php } ?>
    <?php foreach ($tree as $t) { ?>
      <h3 class="sub"><?php echo h($t['name']); ?></h3>
      <ul class="unit-list">
      <?php foreach ($t['children'] as $c) { ?>
        <li><?php echo h($c['name']); ?><?php if (!empty($count[$c['id']])) { ?> <span class="tag"><?php echo $count[$c['id']]; ?> โครงการ</span><?php } ?></li>
      <?php } ?>
      </ul>
    <?php } ?>
  </div>
</div>
<?php admin_foot(); ?>
