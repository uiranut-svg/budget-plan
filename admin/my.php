<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();
$me = bp_user();
$ids = bp_is_admin() ? array() : bp_user_unit_ids($me['id']);
$st = bp_project_statuses();
$units = bp_units();
$years = bp_years(false);
admin_head('โครงการของฉัน', 'my');
?>
<?php if (bp_is_admin()) { ?>
  <p>บัญชีนี้เป็นผู้ดูแลระบบ — จัดการโครงการได้จากเมนู <a href="index.php">ปีงบประมาณ</a> › แท็บโครงการ</p>
<?php } elseif (!$ids) { ?>
  <div class="empty">บัญชีนี้ยังไม่ได้รับมอบหมายหน่วยงาน — ติดต่อผู้ดูแลระบบ (งานวางแผนและงบประมาณ)</div>
<?php } else {
    $names = array();
    foreach (bp_rows('SELECT unit_id FROM ' . bp_t('user_unit') . ' WHERE user_id=' . (int)$me['id']) as $r) if (isset($units[$r['unit_id']])) $names[] = $units[$r['unit_id']]['name'];
?>
  <p class="muted">หน่วยงานที่คุณดูแล: <b><?php echo h(implode(', ', $names)); ?></b> · กดที่โครงการเพื่ออัปเดตยอดเบิกจ่ายรายไตรมาส สถานะ ผลการดำเนินงาน และรูปกิจกรรม</p>
  <?php foreach ($years as $y) {
      $ps = bp_rows('SELECT * FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$y['id'] . ' AND unit_id IN (' . implode(',', $ids) . ") AND status<>'cancel' ORDER BY code,id");
      if (!$ps) continue; ?>
  <h2 class="sub">ปีงบประมาณ <?php echo (int)$y['year']; ?></h2>
  <div class="panel flush scroll-x">
    <table class="tbl">
      <thead><tr><th>โครงการ</th><th class="num">งบอนุมัติ</th><th class="num">เบิกจ่าย</th><th>สถานะ</th><th class="hide-sm">แก้ไขล่าสุด</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($ps as $p) { $pct = (float)$p['approved'] > 0 ? (float)$p['spent'] * 100 / (float)$p['approved'] : 0; ?>
        <tr>
          <td><a href="project.php?id=<?php echo (int)$p['id']; ?>"><?php echo h($p['title']); ?></a><small class="muted d-block"><?php echo h($p['code']); ?> · <?php echo h(isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : ''); ?></small></td>
          <td class="num"><?php echo bp_money($p['approved']); ?></td>
          <td class="num"><?php echo bp_money($p['spent']); ?><small class="muted d-block"><?php echo number_format($pct, 1); ?>%</small></td>
          <td><span class="tag st-<?php echo h($p['status']); ?>"><?php echo h(isset($st[$p['status']]) ? $st[$p['status']] : $p['status']); ?></span></td>
          <td class="hide-sm small muted"><?php echo h(bp_thai_date($p['updated_at'], true)); ?></td>
          <td class="actions"><a class="btn btn-sm" href="project.php?id=<?php echo (int)$p['id']; ?>">อัปเดต</a></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>
  <?php } ?>
<?php } ?>
<?php admin_foot(); ?>
