<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();
$y = admin_year_or_back();
$yid = (int)$y['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $period = (int)$_POST['period'] == 6 ? 6 : 12;
    $v = array();
    foreach (array('summary', 'problems', 'suggestions') as $k) $v[$k] = q(mb_substr(trim((string)(isset($_POST[$k]) ? $_POST[$k] : '')), 0, 10000));
    $pub = !empty($_POST['published']) ? 1 : 0;
    bp_query('INSERT INTO ' . bp_t('report') . ' (year_id,period,summary,problems,suggestions,published,updated_at) VALUES (' . $yid . ',' . $period . ',' . $v['summary'] . ',' . $v['problems'] . ',' . $v['suggestions'] . ',' . $pub . ",'" . date('Y-m-d H:i:s') . "')"
        . ' ON DUPLICATE KEY UPDATE summary=VALUES(summary),problems=VALUES(problems),suggestions=VALUES(suggestions),published=VALUES(published),updated_at=VALUES(updated_at)');
    bp_done('report', 'บันทึกรายงานรอบ ' . $period . ' เดือน ปี ' . $y['year'] . ($pub ? ' (เผยแพร่ข้อความแล้ว)' : ' (ข้อความยังไม่เผยแพร่)'));
    bp_redirect('admin/reports.php?id=' . $yid);
}

$doneQ = bp_fy_quarter_done($y['year']);
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'reports');
?>
<div class="note">
  หน้ารายงาน <b>คำนวณตัวเลขให้อัตโนมัติ</b> จากยอดเบิกจ่ายรายไตรมาสของแต่ละโครงการ (แท็บโครงการ) — ที่นี่กรอกเฉพาะข้อความสรุป ปัญหาอุปสรรค และข้อเสนอแนะ ·
  ใช้เป็นหลักฐานการประเมิน ITA ข้อ <i>รายงานการกำกับติดตามการดำเนินงานและการใช้จ่ายงบประมาณ ประจำปี รอบ 6 เดือน</i> และ <i>รายงานผลการดำเนินงานประจำปี</i>
  — เปิดหน้ารายงานแล้วกด "พิมพ์ / บันทึกเป็น PDF" เพื่อทำเล่มเสนอผู้บริหารลงนาม หรือแนบลิงก์หน้ารายงานในแบบ ITA ได้เลย
</div>
<div class="layout-2 even mt">
<?php foreach (array(6, 12) as $period) {
    $r = bp_report_text($yid, $period);
    $ended = $doneQ >= ($period == 6 ? 2 : 4);
    $url = bp_url('report.php?y=' . (int)$y['year'] . '&p=' . $period);
?>
  <form method="post" class="panel">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="period" value="<?php echo $period; ?>">
    <h2>รอบ <?php echo $period; ?> เดือน <small class="muted">(<?php echo h(bp_period_label($y['year'], $period)); ?>)</small></h2>
    <p class="small"><?php echo $ended ? '<span class="tag st-done">รอบนี้สิ้นสุดแล้ว</span>' : '<span class="tag st-doing">ยังไม่สิ้นสุดรอบ</span>'; ?>
      <a href="<?php echo h($url); ?>" target="_blank" rel="noopener">เปิดหน้ารายงาน ↗</a>
      <?php if ($r && $r['updated_at']) echo ' · <span class="muted">แก้ไขล่าสุด ' . h(bp_thai_date($r['updated_at'], true)) . '</span>'; ?></p>
    <label class="fld"><span>สรุปผลการดำเนินงาน</span><textarea name="summary" rows="4" placeholder="เว้นว่าง = ระบบสรุปจากตัวเลขให้อัตโนมัติ"><?php echo h($r ? $r['summary'] : ''); ?></textarea></label>
    <label class="fld"><span>ปัญหา อุปสรรค</span><textarea name="problems" rows="4" placeholder="เว้นว่าง = ใช้ปัญหาที่หน่วยงานกรอกไว้ในแต่ละโครงการ"><?php echo h($r ? $r['problems'] : ''); ?></textarea></label>
    <label class="fld"><span>ข้อเสนอแนะ / แนวทางการพัฒนา</span><textarea name="suggestions" rows="3"><?php echo h($r ? $r['suggestions'] : ''); ?></textarea></label>
    <label class="chk big"><input type="checkbox" name="published" value="1" <?php echo $r && $r['published'] ? 'checked' : ''; ?>> แสดงข้อความนี้บนหน้ารายงาน</label>
    <p><button class="btn" type="submit">บันทึก</button></p>
  </form>
<?php } ?>
</div>
<?php admin_foot(); ?>
