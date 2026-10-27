<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();

$per = 100;
$page = max(1, isset($_GET['p']) ? (int)$_GET['p'] : 1);
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$where = $q !== '' ? ' WHERE username LIKE ' . q('%' . $q . '%') . ' OR detail LIKE ' . q('%' . $q . '%') . ' OR action LIKE ' . q('%' . $q . '%') : '';
$tot = bp_row('SELECT COUNT(*) n FROM ' . bp_t('log') . $where);
$rows = bp_rows('SELECT * FROM ' . bp_t('log') . $where . ' ORDER BY id DESC LIMIT ' . (($page - 1) * $per) . ',' . $per);
$pages = max(1, (int)ceil($tot['n'] / $per));
$names = array(
    'year.add' => 'เพิ่มปี', 'year.save' => 'แก้งบตามหมวด', 'year.delete' => 'ลบปี', 'funds' => 'แหล่งเงิน', 'align' => 'ความสอดคล้อง', 'units' => 'หน่วยงาน',
    'project.save' => 'บันทึกโครงการ', 'project.delete' => 'ลบโครงการ', 'project.paste' => 'วางโครงการจาก Excel', 'project.file' => 'ไฟล์โครงการ',
    'procure.save' => 'บันทึกจัดซื้อ', 'procure.delete' => 'ลบจัดซื้อ', 'procure.paste' => 'วางจัดซื้อจาก Excel', 'report' => 'รายงาน 6/12 เดือน',
    'excel.import' => 'นำเข้า Excel', 'book.upload' => 'อัปโหลดเล่ม', 'book.edit' => 'แก้เล่ม', 'book.delete' => 'ลบเล่ม',
    'settings' => 'ตั้งค่า', 'password' => 'เปลี่ยนรหัสผ่าน', 'user.add' => 'เพิ่มผู้ใช้', 'user.edit' => 'แก้ผู้ใช้', 'user.delete' => 'ลบผู้ใช้',
    'backup' => 'สำรองข้อมูล', 'restore' => 'กู้คืนข้อมูล', 'update' => 'อัปเดตระบบ', 'login' => 'เข้าสู่ระบบ',
);
admin_head('ระบบ', 'system');
admin_system_tabs('log');
?>
<form class="filters" method="get">
  <input type="search" name="q" value="<?php echo h($q); ?>" placeholder="ค้นหาผู้ใช้ / รายละเอียด">
  <button class="btn btn-ghost" type="submit">ค้นหา</button>
  <span class="muted">ทั้งหมด <?php echo number_format($tot['n']); ?> รายการ</span>
</form>
<div class="panel flush scroll-x">
  <table class="tbl">
    <thead><tr><th>เวลา</th><th>ผู้ใช้</th><th>การกระทำ</th><th>รายละเอียด</th><th class="hide-sm">IP</th></tr></thead>
    <tbody>
    <?php if (!$rows) { ?><tr><td colspan="5" class="center muted">ยังไม่มีบันทึก</td></tr><?php } ?>
    <?php foreach ($rows as $r) { ?>
      <tr><td class="nowrap small"><?php echo h(bp_thai_date($r['at'], true)); ?></td><td><?php echo h($r['username']); ?></td>
        <td class="nowrap"><?php echo h(isset($names[$r['action']]) ? $names[$r['action']] : $r['action']); ?></td><td class="small"><?php echo h($r['detail']); ?></td>
        <td class="hide-sm small muted"><?php echo h($r['ip']); ?></td></tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1) { ?>
<p class="pager"><?php for ($i = 1; $i <= $pages; $i++) { ?><a class="btn btn-sm <?php echo $i == $page ? '' : 'btn-ghost'; ?>" href="?p=<?php echo $i; ?>&amp;q=<?php echo urlencode($q); ?>"><?php echo $i; ?></a> <?php } ?></p>
<?php } ?>
<?php admin_foot(); ?>
