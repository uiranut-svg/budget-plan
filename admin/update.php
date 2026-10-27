<?php
require dirname(__FILE__) . '/_admin.php';
require BP_ROOT . '/inc/update.php';
bp_require_admin();

$check = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'install') {
        $r = bp_update_install((string)$_POST['tag']);
        if ($r['ok']) bp_done('update', $r['msg']); else bp_flash($r['msg'], 'err');
        bp_redirect('admin/update.php');
    }
    $check = bp_update_check();
}
admin_head('ระบบ', 'system');
admin_system_tabs('update');
?>
<div class="panel">
  <h2>รุ่นที่ติดตั้ง: <?php echo h(bp_version()); ?></h2>
  <p class="muted small">PHP <?php echo h(PHP_VERSION); ?> · ฐานข้อมูล <?php echo h(mysqli_get_server_info(bp_db())); ?> · โครงสร้างข้อมูลรุ่น <?php echo h(bp_setting('schema_ver')); ?></p>
  <?php if (bp_update_is_dev()) { ?>
    <div class="note">เครื่องนี้เป็นเครื่องพัฒนา (มีโฟลเดอร์ .git) — อัปเดตด้วย git แทนหน้านี้</div>
  <?php } elseif (BP_UPDATE_REPO === '') { ?>
    <div class="note">ยังไม่ได้ตั้งแหล่งอัปเดต — เมื่อผู้พัฒนานำระบบขึ้น GitHub แล้ว หน้านี้จะตรวจและติดตั้งรุ่นใหม่ให้ได้ในคลิกเดียว (สำรองข้อมูลก่อนติดตั้งให้อัตโนมัติ)</div>
  <?php } else { ?>
    <form method="post"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="check"><button class="btn" type="submit">ตรวจหารุ่นใหม่</button></form>
    <?php if ($check) { ?>
      <?php if (!$check['ok']) { ?><div class="alert alert-err mt"><?php echo h($check['error']); ?></div>
      <?php } elseif ($check['version'] <= bp_version()) { ?><div class="alert alert-ok mt">ใช้รุ่นล่าสุดอยู่แล้ว (<?php echo h($check['version']); ?>)</div>
      <?php } else { ?>
        <div class="panel mt"><p>มีรุ่นใหม่: <b><?php echo h($check['version']); ?></b></p>
          <form method="post" onsubmit="return confirm('ติดตั้งรุ่นใหม่? ระบบจะสำรองฐานข้อมูลก่อน');"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="install"><input type="hidden" name="tag" value="<?php echo h($check['tag']); ?>">
            <button class="btn" type="submit">ติดตั้งรุ่นนี้</button></form></div>
      <?php } ?>
    <?php } ?>
  <?php } ?>
</div>
<?php admin_foot(); ?>
