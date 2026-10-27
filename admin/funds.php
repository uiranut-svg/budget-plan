<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();
$y = admin_year_or_back();
$yid = (int)$y['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['act']) && $_POST['act'] == 'seed') {
        require BP_ROOT . '/inc/sample.php';
        $n = bp_seed_funds($yid);
        bp_flash($n ? 'ใส่แหล่งเงินมาตรฐาน ' . $n . ' รายการแล้ว — กรอกยอดได้เลย' : 'ปีนี้มีแหล่งเงินอยู่แล้ว', $n ? 'ok' : 'err');
        bp_redirect('admin/funds.php?id=' . $yid);
    }
    $rows = json_decode(isset($_POST['payload']) ? (string)$_POST['payload'] : '', true);
    if (!is_array($rows)) {
        bp_flash('ข้อมูลที่ส่งมาไม่ถูกต้อง ยังไม่ได้บันทึก', 'err');
        bp_redirect('admin/funds.php?id=' . $yid);
    }
    $old = array();
    foreach (bp_rows('SELECT id FROM ' . bp_t('fund') . ' WHERE year_id=' . $yid) as $r) $old[(int)$r['id']] = true;
    $keep = array();
    $parent = null;
    $sortTop = 0;
    $sortKid = 0;
    $ok = bp_query('START TRANSACTION');
    foreach ($rows as $r) {
        if (!$ok || !is_array($r)) continue;
        $name = mb_substr(trim((string)(isset($r['name']) ? $r['name'] : '')), 0, 255);
        if ($name === '') continue;
        $child = !empty($r['level']) && $parent !== null;
        $color = isset($r['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $r['color']) ? $r['color'] : '';
        $set = 'name=' . q($name) . ',color=' . q($child ? '' : $color)
            . ',estimate=' . bp_parse_amount(isset($r['estimate']) ? $r['estimate'] : 0)
            . ',carry_in=' . bp_parse_amount(isset($r['carry_in']) ? $r['carry_in'] : 0)
            . ',received=' . bp_parse_amount(isset($r['received']) ? $r['received'] : 0)
            . ',note=' . q(mb_substr(trim((string)(isset($r['note']) ? $r['note'] : '')), 0, 500))
            . ',parent_id=' . ($child ? (int)$parent : 'NULL')
            . ',sort=' . ($child ? $sortKid++ : $sortTop++);
        $id = isset($r['id']) ? (int)$r['id'] : 0;
        if ($id && isset($old[$id]) && !isset($keep[$id])) {
            $ok = bp_query('UPDATE ' . bp_t('fund') . ' SET ' . $set . ' WHERE id=' . $id);
        } else {
            $ok = bp_query('INSERT INTO ' . bp_t('fund') . ' SET year_id=' . $yid . ',' . $set);
            $id = bp_insert_id();
        }
        $keep[$id] = true;
        if (!$child) { $parent = $id; $sortKid = 0; }
    }
    $del = array();
    foreach ($old as $id => $t) if (!isset($keep[$id])) $del[] = $id;
    if ($ok && $del) {
        $ok = bp_query('UPDATE ' . bp_t('project') . ' SET fund_id=NULL WHERE fund_id IN (' . implode(',', $del) . ')')
            && bp_query('DELETE FROM ' . bp_t('fund') . ' WHERE id IN (' . implode(',', $del) . ')');
    }
    if ($ok) {
        bp_query('COMMIT');
        admin_touch_year($yid);
        bp_done('funds', 'บันทึกแหล่งเงินปี ' . $y['year'] . ' แล้ว (' . count($keep) . ' รายการ)');
    } else {
        bp_query('ROLLBACK');
        bp_flash('บันทึกไม่สำเร็จ ข้อมูลเดิมยังอยู่', 'err');
    }
    bp_redirect('admin/funds.php?id=' . $yid);
}

$funds = bp_funds($yid);
$init = array();
foreach ($funds['funds'] as $f) {
    $raw = bp_row('SELECT estimate,carry_in,received FROM ' . bp_t('fund') . ' WHERE id=' . (int)$f['id']);
    $init[] = array('id' => (int)$f['id'], 'level' => 0, 'name' => $f['name'], 'color' => $f['color'], 'estimate' => (float)$raw['estimate'],
        'carry_in' => (float)$raw['carry_in'], 'received' => (float)$raw['received'], 'note' => $f['note']);
    foreach ($f['children'] as $c) $init[] = array('id' => (int)$c['id'], 'level' => 1, 'name' => $c['name'], 'color' => '', 'estimate' => $c['estimate'],
        'carry_in' => $c['carry_in'], 'received' => $c['received'], 'note' => $c['note']);
}

admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'funds');
?>
<?php if (!$init) { ?>
<div class="panel">
  <p>ปีนี้ยังไม่มีแหล่งเงิน</p>
  <form method="post"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="seed">
    <button class="btn" type="submit">ใส่แหล่งเงินมาตรฐาน (เงินงบประมาณ / เงินอุดหนุน / เงินรายได้ / เงินบริจาค / เงินอื่น)</button></form>
  <p class="muted small">หรือเพิ่มเองทีละรายการด้านล่าง</p>
</div>
<?php } ?>
<form method="post" id="fundForm">
  <?php echo bp_csrf_field(); ?>
  <input type="hidden" name="payload" id="payload">
  <div class="ed-help muted small">
    <b>ประมาณการ</b> = รายรับที่คาดว่าจะได้ทั้งปี · <b>ยกมา</b> = เงินคงเหลือจากปีก่อน · <b>รับจริง</b> = เงินที่ได้รับแล้ว (อัปเดตระหว่างปี) ·
    แหล่งเงินหลักที่มีแหล่งย่อย ยอดจะรวมจากแหล่งย่อยอัตโนมัติ
  </div>
  <div class="panel flush scroll-x">
    <table class="ed-items fund-ed" id="fundTbl">
      <thead><tr><th></th><th>แหล่งเงิน</th><th class="num">ประมาณการ</th><th class="num">ยกมา</th><th class="num">รับจริง</th><th>หมายเหตุ</th><th></th></tr></thead>
      <tbody></tbody>
    </table>
  </div>
  <p><button type="button" class="btn btn-ghost" id="addTop">+ เพิ่มแหล่งเงินหลัก</button></p>
  <div class="savebar">
    <span>ประมาณการรวม <b id="gEst">0.00</b> · ยกมา <b id="gCarry">0.00</b> · รับจริง <b id="gRecv">0.00</b> บาท</span>
    <button class="btn" type="submit">บันทึก</button>
  </div>
</form>
<?php admin_foot('<script>window.FD=' . bp_json($init) . ';</script><script src="' . h(bp_url('assets/js/funds.js')) . '?v=1"></script>'); ?>
