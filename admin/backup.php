<?php
require dirname(__FILE__) . '/_admin.php';
require BP_ROOT . '/inc/backup.php';
bp_require_admin();

if (isset($_GET['dl']) && bp_backup_valid_name($_GET['dl'])) {
    $f = bp_backup_dir() . $_GET['dl'];
    header('Content-Type: application/gzip');
    header('Content-Disposition: attachment; filename="' . basename($f) . '"');
    header('Content-Length: ' . filesize($f));
    readfile($f);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'create') {
        $n = bp_backup_create('manual');
        if ($n) bp_done('backup', 'สร้างไฟล์สำรอง ' . $n . ' แล้ว');
        else bp_flash('สร้างไฟล์สำรองไม่ได้ — ให้สิทธิ์เขียนโฟลเดอร์ backups/ แก่เว็บเซิร์ฟเวอร์', 'err');
    } elseif ($act == 'delete' && bp_backup_valid_name($_POST['name'])) {
        @unlink(bp_backup_dir() . $_POST['name']);
        bp_done('backup', 'ลบไฟล์สำรอง ' . $_POST['name'] . ' แล้ว');
    } elseif ($act == 'restore' || $act == 'upload') {
        if (trim((string)$_POST['confirm']) !== 'กู้คืน') bp_flash('ไม่ได้กู้คืน — พิมพ์คำว่า กู้คืน เพื่อยืนยัน', 'err');
        else {
            $path = '';
            if ($act == 'restore' && bp_backup_valid_name($_POST['name'])) $path = bp_backup_dir() . $_POST['name'];
            elseif ($act == 'upload' && isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['file']['tmp_name'])) $path = $_FILES['file']['tmp_name'];
            if ($path === '') bp_flash('ไม่พบไฟล์สำรอง', 'err');
            else {
                $before = bp_backup_create('before-restore');
                $err = bp_backup_restore($path);
                if ($err === '') {
                    bp_settings_all(true);
                    bp_done('restore', 'กู้คืนข้อมูลแล้ว (ข้อมูลก่อนกู้คืนเก็บไว้ที่ ' . $before . ')');
                } else bp_flash($err . ($before ? ' · ข้อมูลก่อนกู้คืนเก็บไว้ที่ ' . $before : ''), 'err');
            }
        }
    }
    bp_redirect('admin/backup.php');
}
$list = bp_backup_list();
admin_head('ระบบ', 'system');
admin_system_tabs('backup');
?>
<div class="grid2">
  <div class="panel">
    <h2>สำรองข้อมูลตอนนี้</h2>
    <p>เก็บข้อมูลทั้งหมดของระบบ (ปีงบประมาณ แหล่งเงิน โครงการ จัดซื้อ ผู้ใช้ ตั้งค่า บันทึก) เป็นไฟล์ <code>.sql.gz</code> ในเครื่องเซิร์ฟเวอร์ — <b>ดาวน์โหลดเก็บไว้นอกเครื่องด้วย</b> (เช่น Google Drive)</p>
    <form method="post"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="create"><button class="btn" type="submit">สำรองข้อมูลตอนนี้</button></form>
    <p class="muted small mt">ไฟล์เล่มแผน PDF และรูป/เอกสารโครงการอยู่ในโฟลเดอร์ <code>files/</code> — คัดลอกโฟลเดอร์นี้เก็บแยกต่างหาก · ระบบสำรองให้เองทุกครั้งก่อนกู้คืนและก่อนอัปเดต</p>
  </div>
  <form class="panel" method="post" enctype="multipart/form-data">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="upload">
    <h2>กู้คืนจากไฟล์ในเครื่องคุณ</h2>
    <p class="small text-err">การกู้คืน <b>แทนที่ข้อมูลปัจจุบันทั้งหมด</b> ด้วยข้อมูลในไฟล์ (ระบบสำรองข้อมูลปัจจุบันไว้ให้ก่อน)</p>
    <label class="fld"><span>ไฟล์ .sql.gz ที่ได้จากหน้านี้</span><input type="file" name="file" accept=".gz" required></label>
    <label class="fld"><span>พิมพ์คำว่า <b>กู้คืน</b> เพื่อยืนยัน</span><input name="confirm" autocomplete="off" required></label>
    <p><button class="btn btn-danger" type="submit">กู้คืน</button></p>
  </form>
</div>
<div class="panel flush scroll-x">
  <table class="tbl">
    <thead><tr><th>ไฟล์สำรองในเครื่องเซิร์ฟเวอร์</th><th>เมื่อ</th><th class="num">ขนาด</th><th></th></tr></thead>
    <tbody>
    <?php if (!$list) { ?><tr><td colspan="4" class="center muted">ยังไม่มีไฟล์สำรอง</td></tr><?php } ?>
    <?php foreach ($list as $b) { ?>
      <tr><td class="small"><?php echo h($b['name']); ?><?php if (strpos($b['name'], 'before-') !== false) echo ' <span class="tag">อัตโนมัติ</span>'; ?></td>
        <td class="nowrap small"><?php echo h(bp_thai_date($b['time'], true)); ?></td><td class="num small"><?php echo bp_human_size($b['size']); ?></td>
        <td class="actions">
          <a class="btn btn-sm" href="?dl=<?php echo urlencode($b['name']); ?>">ดาวน์โหลด</a>
          <form method="post" class="inline" onsubmit="var v=prompt('พิมพ์คำว่า กู้คืน เพื่อยืนยัน — ข้อมูลปัจจุบันจะถูกแทนที่ด้วยไฟล์นี้');if(!v)return false;this.confirm.value=v;">
            <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="restore"><input type="hidden" name="name" value="<?php echo h($b['name']); ?>"><input type="hidden" name="confirm">
            <button class="btn btn-sm btn-ghost" type="submit">กู้คืน</button></form>
          <form method="post" class="inline" onsubmit="return confirm('ลบไฟล์สำรองนี้?')"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="name" value="<?php echo h($b['name']); ?>"><button class="btn btn-sm btn-danger" type="submit">ลบ</button></form>
        </td></tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<?php admin_foot(); ?>
