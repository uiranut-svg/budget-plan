<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();
$y = admin_year_or_back();
$yid = (int)$y['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == 'seed') {
        require BP_ROOT . '/inc/sample.php';
        $n = bp_seed_align($yid);
        bp_flash($n ? 'ใส่ชุดความสอดคล้องมาตรฐานแล้ว (' . $n . ' ข้อ)' : 'ปีนี้มีข้อมูลอยู่แล้ว', $n ? 'ok' : 'err');
        bp_redirect('admin/align.php?id=' . $yid);
    }
    // # = ชุด · บรรทัดปกติ = ข้อ · - หรือเว้นวรรค = ข้อย่อย
    $sets = array();
    foreach (preg_split('/\r?\n/', isset($_POST['text']) ? (string)$_POST['text'] : '') as $line) {
        if (trim($line) === '') continue;
        if (preg_match('/^\s*#+\s*(.+)$/u', $line, $m)) {
            $sets[] = array('name' => mb_substr(trim($m[1]), 0, 500), 'items' => array());
            continue;
        }
        if (!$sets) $sets[] = array('name' => 'ความสอดคล้อง', 'items' => array());
        $s = count($sets) - 1;
        $child = (bool)preg_match('/^(\s+|[-–•*]\s*)/u', $line);
        $name = mb_substr(trim(preg_replace('/^[\s\-–•*]+/u', '', $line)), 0, 500);
        if ($name === '') continue;
        if ($child && $sets[$s]['items']) $sets[$s]['items'][count($sets[$s]['items']) - 1]['children'][] = $name;
        else $sets[$s]['items'][] = array('name' => $name, 'children' => array());
    }
    $old = array();
    foreach (bp_rows('SELECT id,parent_id,name FROM ' . bp_t('align') . ' WHERE year_id=' . $yid) as $r) $old[(int)$r['parent_id'] . '|' . $r['name']] = (int)$r['id'];
    $keep = array();
    bp_query('START TRANSACTION');
    foreach ($sets as $i => $s) {
        $sid = align_put($yid, 0, $s['name'], $i, $old, $keep);
        foreach ($s['items'] as $j => $it) {
            $iid = align_put($yid, $sid, $it['name'], $j, $old, $keep);
            foreach ($it['children'] as $k => $c) align_put($yid, $iid, $c, $k, $old, $keep);
        }
    }
    $del = array();
    foreach ($old as $id) if (!isset($keep[$id])) $del[] = $id;
    if ($del) {
        bp_query('DELETE FROM ' . bp_t('project_align') . ' WHERE align_id IN (' . implode(',', $del) . ')');
        bp_query('DELETE FROM ' . bp_t('align') . ' WHERE id IN (' . implode(',', $del) . ')');
    }
    bp_query('COMMIT');
    bp_flash('บันทึกความสอดคล้องปี ' . $y['year'] . ' แล้ว');
    bp_redirect('admin/align.php?id=' . $yid);
}
/** ใช้ id เดิมถ้าชื่อเดิมอยู่ใต้ตัวแม่เดิม (โครงการที่ผูกไว้ไม่หลุด) */
function align_put($yid, $parent, $name, $sort, $old, &$keep)
{
    $k = (int)$parent . '|' . $name;
    if (isset($old[$k]) && !isset($keep[$old[$k]])) {
        $id = $old[$k];
        bp_query('UPDATE ' . bp_t('align') . ' SET sort=' . (int)$sort . ' WHERE id=' . $id);
    } else {
        bp_query('INSERT INTO ' . bp_t('align') . ' (year_id,parent_id,name,sort) VALUES (' . (int)$yid . ',' . ($parent ? (int)$parent : 'NULL') . ',' . q($name) . ',' . (int)$sort . ')');
        $id = bp_insert_id();
    }
    $keep[$id] = true;
    return $id;
}

$sets = bp_aligns($yid);
$text = '';
foreach ($sets as $s) {
    $text .= ($text !== '' ? "\n" : '') . '# ' . $s['name'] . "\n";
    foreach ($s['items'] as $it) {
        $text .= $it['name'] . "\n";
        foreach ($it['children'] as $c) $text .= '- ' . $c['name'] . "\n";
    }
}
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'align');
?>
<?php if (!$sets) { ?>
<div class="panel">
  <p>ปีนี้ยังไม่มีชุดความสอดคล้อง</p>
  <form method="post"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="seed">
    <button class="btn" type="submit">ใส่ชุดมาตรฐาน (ยุทธศาสตร์ชาติ / นโยบาย สอศ. / พันธกิจ / มาตรฐานการอาชีวศึกษา / เศรษฐกิจพอเพียง)</button></form>
</div>
<?php } ?>
<form method="post" class="panel">
  <?php echo bp_csrf_field(); ?>
  <h2>ยุทธศาสตร์ / นโยบาย / มาตรฐาน ที่โครงการต้องสอดคล้อง</h2>
  <p class="muted small">บรรทัดขึ้นต้นด้วย <code>#</code> = <b>ชุด</b> (เช่น # ยุทธศาสตร์ชาติ 20 ปี) · บรรทัดปกติ = <b>ข้อ</b> · ขึ้นต้นด้วย <code>-</code> = <b>ข้อย่อย</b> ·
    แก้ข้อความของข้อ = โครงการที่เลือกข้อนั้นไว้จะหลุด · เลือกความสอดคล้องของแต่ละโครงการได้ในแท็บ "โครงการ"</p>
  <textarea name="text" rows="28" spellcheck="false"><?php echo h($text); ?></textarea>
  <p><button class="btn" type="submit">บันทึก</button></p>
</form>
<?php admin_foot(); ?>
