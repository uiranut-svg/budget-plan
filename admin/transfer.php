<?php
// ปรับแผน / โอนงบระหว่างโครงการ — งบอนุมัติของโครงการเปลี่ยนตาม และเก็บประวัติไว้แสดงต่อสาธารณะ
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();
$y = admin_year_or_back();
$yid = (int)$y['id'];
$self = 'admin/transfer.php?id=' . $yid;
$projects = array();
foreach (bp_projects($yid) as $p) $projects[(int)$p['id']] = $p;
$label = function ($p) { return trim($p['code'] . ' ' . $p['title']); };

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'add') {
        $from = (int)$_POST['from_id'];
        $to = (int)$_POST['to_id'];
        $amt = bp_parse_amount($_POST['amount']);
        $reason = trim((string)$_POST['reason']);
        $docNo = trim((string)$_POST['doc_no']);
        $date = bp_parse_date($_POST['doc_date']);
        $err = '';
        if ($from && !isset($projects[$from]) || $to && !isset($projects[$to])) $err = 'ไม่พบโครงการที่เลือก';
        elseif (!$from && !$to) $err = 'เลือกโครงการต้นทางหรือปลายทางอย่างน้อย 1 ฝั่ง';
        elseif ($from && $from == $to) $err = 'ต้นทางและปลายทางต้องเป็นคนละโครงการ';
        elseif ($amt <= 0) $err = 'กรอกจำนวนเงินที่โอน';
        elseif ($reason === '') $err = 'กรอกเหตุผลการปรับแผน (แสดงต่อสาธารณะ)';
        elseif ($from) {
            $left = (float)$projects[$from]['approved'] - (float)$projects[$from]['spent'];
            if ($amt > $left + 0.001) $err = 'โอนออกจาก "' . $label($projects[$from]) . '" ได้ไม่เกิน ' . bp_money(max(0, $left)) . ' บาท (งบอนุมัติ − เบิกจ่ายแล้ว)';
        }
        if ($err !== '') { bp_flash($err, 'err'); bp_redirect($self); }
        $u = bp_user();
        bp_query('START TRANSACTION');
        if ($from) bp_query('UPDATE ' . bp_t('project') . ' SET approved=approved-' . $amt . ",updated_at='" . date('Y-m-d H:i:s') . "' WHERE id=" . $from);
        if ($to) bp_query('UPDATE ' . bp_t('project') . ' SET approved=approved+' . $amt . ",updated_at='" . date('Y-m-d H:i:s') . "' WHERE id=" . $to);
        bp_query('INSERT INTO ' . bp_t('transfer') . ' (year_id,from_id,to_id,amount,doc_no,doc_date,reason,username,created_at) VALUES (' . $yid . ',' . ($from ? $from : 'NULL') . ',' . ($to ? $to : 'NULL') . ','
            . $amt . ',' . q(mb_substr($docNo, 0, 100)) . ',' . ($date ? q($date) : q(date('Y-m-d'))) . ',' . q(mb_substr($reason, 0, 500)) . ',' . q($u['username']) . ",'" . date('Y-m-d H:i:s') . "')");
        bp_query('COMMIT');
        admin_touch_year($yid);
        bp_done('transfer', 'ปรับแผนปี ' . $y['year'] . ': ' . ($from ? $label($projects[$from]) : 'งบที่ยังไม่จัดสรร') . ' → ' . ($to ? $label($projects[$to]) : 'เงินเหลือจ่าย') . ' ' . bp_money($amt) . ' บาท');
    } elseif ($act == 'delete') {
        $t = bp_row('SELECT * FROM ' . bp_t('transfer') . ' WHERE id=' . (int)$_POST['tid'] . ' AND year_id=' . $yid);
        if ($t) {
            $amt = (float)$t['amount'];
            // ยกเลิก = คืนเงินกลับ · โครงการปลายทางต้องยังมีงบเหลือพอ (ไม่ได้เบิกไปแล้ว)
            if ($t['to_id'] && isset($projects[$t['to_id']]) && (float)$projects[$t['to_id']]['approved'] - $amt < (float)$projects[$t['to_id']]['spent'] - 0.001) {
                bp_flash('ยกเลิกไม่ได้ — โครงการปลายทางเบิกจ่ายเงินส่วนนี้ไปแล้ว (บันทึกการโอนกลับเป็นรายการใหม่แทน)', 'err');
                bp_redirect($self);
            }
            bp_query('START TRANSACTION');
            if ($t['from_id']) bp_query('UPDATE ' . bp_t('project') . ' SET approved=approved+' . $amt . ' WHERE id=' . (int)$t['from_id']);
            if ($t['to_id']) bp_query('UPDATE ' . bp_t('project') . ' SET approved=approved-' . $amt . ' WHERE id=' . (int)$t['to_id']);
            bp_query('DELETE FROM ' . bp_t('transfer') . ' WHERE id=' . (int)$t['id']);
            bp_query('COMMIT');
            admin_touch_year($yid);
            bp_done('transfer', 'ยกเลิกรายการปรับแผนปี ' . $y['year'] . ' จำนวน ' . bp_money($amt) . ' บาท (คืนงบอนุมัติกลับตามเดิม)');
        }
    }
    bp_redirect($self);
}

$list = bp_transfers($yid);
$net = bp_transfer_net($yid);
$units = bp_units();
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'transfer');
$opt = function ($sel) use ($projects, $label, $units) {
    $o = '';
    foreach ($projects as $id => $p) {
        if ($p['status'] == 'cancel') continue;
        $o .= '<option value="' . $id . '" data-left="' . ((float)$p['approved'] - (float)$p['spent']) . '"' . ($sel == $id ? ' selected' : '') . '>' . h($label($p)) . ' — งบ ' . bp_money($p['approved'], 0) . '</option>';
    }
    return $o;
};
?>
<div class="grid2">
  <form class="panel" method="post">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="add">
    <h2>บันทึกการปรับแผน / โอนงบ</h2>
    <p class="muted small">ใช้เมื่อที่ประชุมหรือผู้บริหารอนุมัติให้โอนงบระหว่างโครงการ เพิ่มงบจากเงินเหลือจ่าย หรือปรับลดงบโครงการ — ระบบปรับงบอนุมัติของโครงการให้ และ<b>เก็บงบเดิมไว้แสดงบนเว็บ</b>เพื่อความโปร่งใส</p>
    <label class="fld"><span>โอนจาก</span>
      <select name="from_id" id="tfFrom"><option value="0">– งบที่ยังไม่จัดสรร / เงินเหลือจ่าย (เพิ่มงบให้โครงการ) –</option><?php echo $opt(isset($_GET['from']) ? (int)$_GET['from'] : 0); ?></select></label>
    <p class="small muted" id="tfLeft"></p>
    <label class="fld"><span>ไปยัง</span>
      <select name="to_id"><option value="0">– คืนเป็นเงินเหลือจ่าย (ปรับลดงบโครงการ) –</option><?php echo $opt(0); ?></select></label>
    <div class="form-row">
      <label class="fld"><span>จำนวนเงิน (บาท) *</span><input class="num" name="amount" inputmode="decimal" required></label>
      <label class="fld"><span>วันที่อนุมัติ</span><input name="doc_date" placeholder="เช่น 15/03/<?php echo (int)$y['year']; ?>" value="<?php echo h(date('d/m/') . (date('Y') + 543)); ?>"></label>
      <label class="fld"><span>เลขที่บันทึก / มติที่ประชุม</span><input name="doc_no" placeholder="เช่น ที่ประชุมกรรมการบริหาร ครั้งที่ 3/<?php echo (int)$y['year']; ?>"></label>
    </div>
    <label class="fld"><span>เหตุผล * <small class="muted">(แสดงต่อสาธารณะ)</small></span><input name="reason" required placeholder="เช่น ดำเนินโครงการแล้วมีเงินเหลือจ่าย นำไปสมทบโครงการที่งบไม่เพียงพอ"></label>
    <p><button class="btn" type="submit">บันทึก</button></p>
  </form>
  <div class="panel">
    <h2>โครงการที่ปรับงบแล้ว</h2>
    <?php if (!$net) { ?><p class="muted">ยังไม่มี</p><?php } else { ?>
    <table class="tbl">
      <thead><tr><th>โครงการ</th><th class="num">งบเดิม</th><th class="num">ปรับ</th><th class="num">งบปัจจุบัน</th></tr></thead>
      <tbody>
      <?php foreach ($net as $pid => $d) { if (!isset($projects[$pid]) || abs($d) < 0.005) continue; $p = $projects[$pid]; ?>
        <tr><td><a href="project.php?id=<?php echo $pid; ?>"><?php echo h($label($p)); ?></a></td><td class="num"><?php echo bp_money($p['approved'] - $d); ?></td>
          <td class="num <?php echo $d > 0 ? 'text-ok' : 'text-err'; ?>"><?php echo ($d > 0 ? '+' : '') . bp_money($d); ?></td><td class="num"><b><?php echo bp_money($p['approved']); ?></b></td></tr>
      <?php } ?>
      </tbody>
    </table>
    <?php } ?>
  </div>
</div>

<div class="panel flush scroll-x">
  <table class="tbl">
    <thead><tr><th>วันที่</th><th>จาก</th><th>ไปยัง</th><th class="num">จำนวนเงิน</th><th>เหตุผล / เอกสาร</th><th></th></tr></thead>
    <tbody>
    <?php if (!$list) { ?><tr><td colspan="6" class="center muted">ยังไม่มีการปรับแผนในปีนี้</td></tr><?php } ?>
    <?php foreach ($list as $t) { ?>
      <tr><td class="nowrap small"><?php echo h(bp_thai_date($t['doc_date'])); ?></td>
        <td class="small"><?php echo h(bp_transfer_side($t, 'from')); ?></td>
        <td class="small"><?php echo h(bp_transfer_side($t, 'to')); ?></td>
        <td class="num"><?php echo bp_money($t['amount']); ?></td>
        <td class="small"><?php echo h($t['reason']); ?><?php if ($t['doc_no'] !== '') { ?><span class="muted d-block"><?php echo h($t['doc_no']); ?></span><?php } ?></td>
        <td class="actions"><form method="post" class="inline" onsubmit="return confirm('ยกเลิกรายการนี้? งบอนุมัติของโครงการจะกลับเป็นเหมือนก่อนโอน')"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="tid" value="<?php echo (int)$t['id']; ?>"><button class="btn btn-sm btn-danger">ยกเลิก</button></form></td></tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<script>
(function () {
  var s = document.getElementById('tfFrom'), out = document.getElementById('tfLeft');
  function show() {
    var o = s.options[s.selectedIndex], v = o.getAttribute('data-left');
    out.textContent = v === null ? '' : 'โอนออกได้ไม่เกิน ' + Number(v).toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท (งบอนุมัติ − เบิกจ่ายแล้ว)';
  }
  s.addEventListener('change', show); show();
})();
</script>
<?php admin_foot(); ?>
