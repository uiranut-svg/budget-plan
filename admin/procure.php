<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();
$y = admin_year_or_back();
$yid = (int)$y['id'];
$back = 'admin/procure.php?id=' . $yid;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'delete') {
        $r = bp_row('SELECT * FROM ' . bp_t('procure') . ' WHERE id=' . (int)$_POST['pid'] . ' AND year_id=' . $yid);
        if ($r) {
            bp_query('DELETE FROM ' . bp_t('procure') . ' WHERE id=' . (int)$r['id']);
            admin_touch_year($yid);
            bp_done('procure.delete', 'ลบรายการจัดซื้อ "' . $r['title'] . '" แล้ว');
        }
    } elseif ($act == 'paste') {
        // ลำดับ | รายการ | วงเงินงบประมาณ | วิธีจัดซื้อ | เดือนที่คาดว่าจะจัดหา (เช่น ม.ค. 2569 หรือ 2026-01) | ผู้ได้รับคัดเลือก | ราคาที่ตกลง | เลขที่สัญญา | วันที่สัญญา
        $seqs = array();
        foreach (bp_rows('SELECT id,seq FROM ' . bp_t('procure') . ' WHERE year_id=' . $yid) as $r) $seqs[(int)$r['seq']] = (int)$r['id'];
        $max = $seqs ? max(array_keys($seqs)) : 0;
        $add = 0; $upd = 0;
        foreach (preg_split('/\r?\n/', (string)$_POST['text']) as $line) {
            if (trim($line) === '') continue;
            $c = array_map('trim', explode("\t", $line));
            while (count($c) < 9) $c[] = '';
            if ($c[1] === '' || preg_match('/^(รายการ|งานที่จัดซื้อ|ชื่อรายการ)/u', $c[1])) continue;
            $seq = (int)$c[0] > 0 ? (int)$c[0] : ++$max;
            if ($seq > $max) $max = $seq;
            $vendor = mb_substr($c[5], 0, 255);
            $amt = bp_parse_amount($c[6]);
            $cd = bp_parse_date($c[8]);
            $set = 'seq=' . $seq . ',title=' . q(mb_substr($c[1], 0, 500)) . ',budget=' . bp_parse_amount($c[2]) . ',method=' . q(mb_substr($c[3], 0, 64))
                . ',plan_month=' . q(bp_parse_month($c[4], $y['year'])) . ",updated_at='" . date('Y-m-d H:i:s') . "'";
            if ($vendor !== '' || $amt > 0) $set .= ',vendor=' . q($vendor) . ',contract_amount=' . $amt . ',contract_no=' . q(mb_substr($c[7], 0, 64)) . ',contract_date=' . ($cd ? q($cd) : 'NULL') . ",status='done'";
            if (isset($seqs[$seq])) { bp_query('UPDATE ' . bp_t('procure') . ' SET ' . $set . ' WHERE id=' . $seqs[$seq]); $upd++; }
            else { bp_query('INSERT INTO ' . bp_t('procure') . ' SET year_id=' . $yid . ',' . $set); $seqs[$seq] = bp_insert_id(); $add++; }
        }
        admin_touch_year($yid);
        bp_done('procure.paste', 'จัดซื้อจัดจ้าง: เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' รายการ');
    }
    bp_redirect($back);
}

$rows = bp_procures($yid);
$st = bp_procure_statuses();
$sum = array('budget' => 0, 'contract' => 0, 'done' => 0);
foreach ($rows as $r) {
    if ($r['status'] == 'cancel') continue;
    $sum['budget'] += (float)$r['budget'];
    if ($r['status'] == 'done') { $sum['done']++; $sum['contract'] += (float)$r['contract_amount']; }
}
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'procure');
?>
<div class="form-row tight" style="margin-bottom:12px">
  <a class="btn" href="procure_item.php?y=<?php echo $yid; ?>">+ เพิ่มรายการ</a>
  <button type="button" class="btn btn-ghost" onclick="document.getElementById('pasteDlg').showModal()">วาง/อัปเดตจาก Excel</button>
  <a class="btn btn-ghost" href="<?php echo h(bp_url('procurement.php?y=' . (int)$y['year'])); ?>" target="_blank" rel="noopener">ดูหน้าเว็บ ↗</a>
  <span class="muted"><?php echo count($rows); ?> รายการ · วงเงิน <?php echo bp_money($sum['budget']); ?> · ทำสัญญาแล้ว <?php echo $sum['done']; ?> รายการ <?php echo bp_money($sum['contract']); ?> บาท</span>
</div>
<p class="muted small">ใช้ตอบ ITA ข้อ <i>แผนการจัดซื้อจัดจ้าง</i> และ <i>สรุปผลการจัดซื้อจัดจ้างรายเดือน (แบบ สขร.1)</i> · เมื่อกรอกผู้ได้รับคัดเลือกและราคาที่ตกลง รายการจะขึ้นในหน้าสรุปผลตามเดือนของวันที่สัญญา</p>
<div class="panel flush scroll-x">
  <table class="tbl">
    <thead><tr><th>#</th><th>รายการ</th><th class="num">วงเงิน</th><th class="hide-sm">วิธี</th><th class="hide-sm">ผู้ได้รับคัดเลือก</th><th class="num">ราคาที่ตกลง</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$rows) { ?><tr><td colspan="8" class="center muted">ยังไม่มีรายการจัดซื้อจัดจ้าง</td></tr><?php } ?>
    <?php foreach ($rows as $r) { ?>
      <tr>
        <td><?php echo (int)$r['seq']; ?></td>
        <td><a href="procure_item.php?id=<?php echo (int)$r['id']; ?>"><?php echo h($r['title']); ?></a><small class="muted d-block"><?php echo h(bp_thai_month($r['plan_month'])); ?></small></td>
        <td class="num"><?php echo bp_money($r['budget']); ?></td>
        <td class="hide-sm small"><?php echo h($r['method']); ?></td>
        <td class="hide-sm small"><?php echo h($r['vendor']); ?></td>
        <td class="num"><?php echo (float)$r['contract_amount'] ? bp_money($r['contract_amount']) : '<span class="muted">–</span>'; ?></td>
        <td><span class="tag st-<?php echo $r['status'] == 'done' ? 'done' : ($r['status'] == 'cancel' ? 'cancel' : ($r['status'] == 'doing' ? 'doing' : 'plan')); ?>"><?php echo h($st[$r['status']]); ?></span></td>
        <td class="actions"><a class="btn btn-sm" href="procure_item.php?id=<?php echo (int)$r['id']; ?>">แก้ไข</a>
          <form method="post" class="inline" onsubmit="return confirm('ลบรายการนี้?')"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="pid" value="<?php echo (int)$r['id']; ?>"><button class="btn btn-sm btn-danger">ลบ</button></form></td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<dialog class="dlg" id="pasteDlg">
  <form method="post">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="paste">
    <h3>วาง/อัปเดตรายการจัดซื้อจากตาราง Excel</h3>
    <p class="muted small">คอลัมน์ตามลำดับ: <b>ลำดับ</b> | <b>รายการ</b> | วงเงินงบประมาณ | วิธีจัดซื้อ | เดือนที่คาดว่าจะจัดหา (เช่น ม.ค. 2569) | ผู้ได้รับคัดเลือก | ราคาที่ตกลง | เลขที่สัญญา | วันที่สัญญา (เช่น 15/01/2569)<br>
      ลำดับซ้ำกับรายการเดิม = อัปเดต · มีผู้ได้รับคัดเลือกหรือราคา = สถานะ "ทำสัญญาแล้ว"</p>
    <textarea name="text" rows="10" placeholder="วางที่นี่ (Ctrl+V)" required></textarea>
    <p class="dlg-foot"><button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">ยกเลิก</button> <button class="btn" type="submit">นำเข้า</button></p>
  </form>
</dialog>
<?php admin_foot(); ?>
