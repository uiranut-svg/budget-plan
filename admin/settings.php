<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();
$me = bp_user();

$fields = array(
    'site_name' => array('ชื่อระบบ', 'แสดงหัวเว็บทุกหน้า'),
    'school_name' => array('ชื่อสถานศึกษา', ''),
    'intro' => array('คำอธิบายใต้หัวเรื่อง', ''),
    'unit_name' => array('หน่วยงานที่รับผิดชอบ', 'เช่น งานวางแผนและงบประมาณ'),
    'contact' => array('ข้อมูลติดต่อ (ท้ายหน้า)', 'โทร / อีเมล / ที่อยู่'),
);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'settings') {
        foreach ($fields as $k => $f) if (isset($_POST[$k])) bp_setting_set($k, mb_substr(trim((string)$_POST[$k]), 0, 2000));
        bp_flash('บันทึกการตั้งค่าแล้ว');
    } elseif ($act == 'password') {
        $u = bp_row('SELECT * FROM ' . bp_t('user') . ' WHERE id=' . (int)$me['id']);
        $new = (string)$_POST['new'];
        if (!password_verify((string)$_POST['old'], $u['password'])) bp_flash('รหัสผ่านเดิมไม่ถูกต้อง', 'err');
        elseif (strlen($new) < 8) bp_flash('รหัสผ่านใหม่อย่างน้อย 8 ตัว', 'err');
        elseif ($new !== (string)$_POST['new2']) bp_flash('ยืนยันรหัสผ่านไม่ตรงกัน', 'err');
        else {
            bp_query('UPDATE ' . bp_t('user') . ' SET password=' . q(password_hash($new, PASSWORD_DEFAULT)) . ' WHERE id=' . (int)$me['id']);
            bp_flash('เปลี่ยนรหัสผ่านแล้ว');
        }
    } elseif ($act == 'adduser') {
        $un = trim((string)$_POST['username']);
        $pw = (string)$_POST['password'];
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,64}$/', $un)) bp_flash('ชื่อผู้ใช้ 3–64 ตัว (A-Z 0-9 _ . -)', 'err');
        elseif (strlen($pw) < 8) bp_flash('รหัสผ่านอย่างน้อย 8 ตัว', 'err');
        elseif (bp_row('SELECT id FROM ' . bp_t('user') . ' WHERE username=' . q($un))) bp_flash('มีชื่อผู้ใช้นี้แล้ว', 'err');
        else {
            bp_query('INSERT INTO ' . bp_t('user') . ' (username,password,name,created_at) VALUES (' . q($un) . ',' . q(password_hash($pw, PASSWORD_DEFAULT)) . ',' . q(mb_substr(trim((string)$_POST['name']), 0, 255)) . ",'" . date('Y-m-d H:i:s') . "')");
            bp_flash('เพิ่มผู้ดูแล ' . $un . ' แล้ว');
        }
    } elseif ($act == 'deluser') {
        $id = (int)$_POST['id'];
        if ($id == (int)$me['id']) bp_flash('ลบบัญชีตัวเองไม่ได้', 'err');
        else {
            bp_query('DELETE FROM ' . bp_t('user') . ' WHERE id=' . $id);
            bp_flash('ลบผู้ดูแลแล้ว');
        }
    }
    bp_redirect('admin/settings.php');
}

$users = bp_rows('SELECT id,username,name,created_at FROM ' . bp_t('user') . ' ORDER BY id');
admin_head('ตั้งค่า', 'settings');
?>
<div class="panel">
  <h2>ข้อมูลที่แสดงบนเว็บ</h2>
  <form method="post">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="settings">
    <?php foreach ($fields as $k => $f) { ?>
      <label class="fld"><span><?php echo h($f[0]); ?></span>
        <?php if ($k == 'intro' || $k == 'contact') { ?><textarea name="<?php echo $k; ?>" rows="2"><?php echo h(bp_setting($k)); ?></textarea>
        <?php } else { ?><input name="<?php echo $k; ?>" value="<?php echo h($k == 'site_name' ? bp_site_name() : bp_setting($k)); ?>"><?php } ?>
        <?php if ($f[1]) { ?><small><?php echo h($f[1]); ?></small><?php } ?></label>
    <?php } ?>
    <p><button class="btn" type="submit">บันทึก</button></p>
  </form>
</div>

<div class="grid2">
  <div class="panel">
    <h2>เปลี่ยนรหัสผ่านของฉัน</h2>
    <form method="post">
      <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="password">
      <label class="fld"><span>รหัสผ่านเดิม</span><input type="password" name="old" autocomplete="current-password" required></label>
      <label class="fld"><span>รหัสผ่านใหม่ (อย่างน้อย 8 ตัว)</span><input type="password" name="new" autocomplete="new-password" required></label>
      <label class="fld"><span>ยืนยันรหัสผ่านใหม่</span><input type="password" name="new2" autocomplete="new-password" required></label>
      <p><button class="btn" type="submit">เปลี่ยนรหัสผ่าน</button></p>
    </form>
  </div>
  <div class="panel">
    <h2>ผู้ดูแลระบบ</h2>
    <table class="tbl">
      <?php foreach ($users as $u) { ?>
      <tr><td><b><?php echo h($u['username']); ?></b> <small class="muted"><?php echo h($u['name']); ?></small></td>
        <td class="actions"><?php if ($u['id'] != $me['id']) { ?>
          <form method="post" class="inline" onsubmit="return confirm('ลบผู้ดูแล <?php echo h($u['username']); ?>?');"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="deluser"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><button class="btn btn-sm btn-danger">ลบ</button></form>
        <?php } else echo '<span class="tag">คุณ</span>'; ?></td></tr>
      <?php } ?>
    </table>
    <form method="post" class="mt">
      <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="adduser">
      <label class="fld"><span>ชื่อผู้ใช้</span><input name="username" autocomplete="off" required></label>
      <label class="fld"><span>ชื่อ-สกุล</span><input name="name"></label>
      <label class="fld"><span>รหัสผ่าน (อย่างน้อย 8 ตัว)</span><input type="password" name="password" autocomplete="new-password" required></label>
      <p><button class="btn btn-ghost" type="submit">+ เพิ่มผู้ดูแล</button></p>
    </form>
  </div>
</div>
<?php admin_foot(); ?>
