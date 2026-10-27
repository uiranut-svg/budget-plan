<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_admin();
$me = bp_user();
$roles = bp_roles();

$fields = array(
    'site_name' => array('ชื่อระบบ', 'แสดงหัวเว็บทุกหน้า'),
    'school_name' => array('ชื่อสถานศึกษา', ''),
    'intro' => array('คำอธิบายใต้หัวเรื่อง', ''),
    'unit_name' => array('หน่วยงานที่รับผิดชอบ', 'เช่น งานวางแผนและงบประมาณ'),
    'contact' => array('ข้อมูลติดต่อ (ท้ายหน้า)', 'โทร / อีเมล / ที่อยู่'),
    'disb_target' => array('เป้าหมายการเบิกจ่ายสะสม (%) ไตรมาส 1–4', 'คั่นด้วยจุลภาค ตามมาตรการเร่งรัดการเบิกจ่ายของรัฐในปีนั้น เช่น 30,52,75,100 — ใช้เทียบในกราฟและรายงาน'),
);

/** หน่วยงานที่เลือกในฟอร์ม → บันทึกให้ผู้ใช้ */
function settings_save_units($uid, $role)
{
    bp_query('DELETE FROM ' . bp_t('user_unit') . ' WHERE user_id=' . (int)$uid);
    if ($role != 'unit' || empty($_POST['units']) || !is_array($_POST['units'])) return 0;
    $units = bp_units();
    $vals = array();
    foreach ($_POST['units'] as $u) if (isset($units[(int)$u])) $vals[(int)$u] = '(' . (int)$uid . ',' . (int)$u . ')';
    if ($vals) bp_query('INSERT INTO ' . bp_t('user_unit') . ' (user_id,unit_id) VALUES ' . implode(',', $vals));
    return count($vals);
}
/** ผู้ลงนามในเอกสารพิมพ์: key => array(ป้าย, ตำแหน่งเริ่มต้น) */
function settings_doc_signers()
{
    $school = bp_setting('school_name');
    return array(
        'reporter' => array('ผู้รายงาน / ผู้จัดทำแผน', 'หัวหน้า' . bp_setting('unit_name', 'งานวางแผนและงบประมาณ')),
        'deputy' => array('ผู้ตรวจสอบ / ผู้เสนอ (รองผู้อำนวยการ)', 'รองผู้อำนวยการฝ่ายแผนงานและความร่วมมือ'),
        'director' => array('ผู้อำนวยการ (ผู้อนุมัติ)', 'ผู้อำนวยการ' . $school),
        'procure' => array('เจ้าหน้าที่พัสดุ (ผู้จัดทำแผนจัดซื้อจัดจ้าง)', 'เจ้าหน้าที่'),
        'procure_head' => array('หัวหน้าเจ้าหน้าที่ (ลงนาม สขร.1)', 'หัวหน้าเจ้าหน้าที่'),
    );
}
function settings_admin_count()
{
    $r = bp_row('SELECT COUNT(*) n FROM ' . bp_t('user') . " WHERE role<>'unit'");
    return (int)$r['n'];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'settings') {
        if (isset($_POST['disb_target'])) {
            $t = array();
            foreach (explode(',', (string)$_POST['disb_target']) as $x) if (trim($x) !== '') $t[] = max(0, min(100, (int)$x));
            $_POST['disb_target'] = count($t) == 4 ? implode(',', $t) : '';
        }
        foreach ($fields as $k => $f) if (isset($_POST[$k])) bp_setting_set($k, mb_substr(trim((string)$_POST[$k]), 0, 2000));
        bp_done('settings', 'บันทึกการตั้งค่าแล้ว');
    } elseif ($act == 'docs') {
        // ผู้ลงนามในเอกสารพิมพ์ + ตราสถานศึกษา
        foreach (settings_doc_signers() as $k => $s) foreach (array('name', 'pos') as $x) {
            $key = 'doc_' . $k . '_' . $x;
            if (isset($_POST[$key])) bp_setting_set($key, mb_substr(trim((string)$_POST[$key]), 0, 255));
        }
        $old = bp_setting('doc_logo');
        $oldOk = preg_match('/^doc-logo-[0-9a-f]{12}\.(png|jpg)$/', $old);
        $msg = 'บันทึกการตั้งค่าเอกสารแล้ว';
        if (!empty($_POST['logo_del']) && $oldOk) {
            @unlink(BP_ROOT . '/files/' . $old);
            bp_setting_set('doc_logo', '');
            $msg .= ' (ลบตราแล้ว)';
        }
        $up = isset($_FILES['logo']) ? $_FILES['logo'] : null;
        if ($up && $up['error'] == UPLOAD_ERR_OK && is_uploaded_file($up['tmp_name'])) {
            $info = @getimagesize($up['tmp_name']);
            $ext = $info && $info[2] == IMAGETYPE_PNG ? 'png' : ($info && $info[2] == IMAGETYPE_JPEG ? 'jpg' : '');
            if ($ext === '' || $up['size'] > 5 * 1048576) {
                bp_flash('ตราสถานศึกษาต้องเป็นรูป png หรือ jpg ไม่เกิน 5 MB', 'err');
                bp_redirect('admin/settings.php#docs');
            }
            $name = 'doc-logo-' . bp_random_hex(6) . '.' . $ext;
            if (@move_uploaded_file($up['tmp_name'], BP_ROOT . '/files/' . $name)) {
                @chmod(BP_ROOT . '/files/' . $name, 0644);
                $ne = bp_image_shrink(BP_ROOT . '/files/' . $name, $ext, 800);
                if ($ne !== '' && $ne !== $ext) { $nn = substr($name, 0, 21) . '.' . $ne; if (@rename(BP_ROOT . '/files/' . $name, BP_ROOT . '/files/' . $nn)) $name = $nn; }
                if ($oldOk && $old !== $name) @unlink(BP_ROOT . '/files/' . $old);
                bp_setting_set('doc_logo', $name);
                $msg .= ' (อัปโหลดตราแล้ว)';
            } else {
                bp_flash('บันทึกไฟล์ไม่ได้ — ให้สิทธิ์เขียนโฟลเดอร์ files/ แก่เว็บเซิร์ฟเวอร์', 'err');
                bp_redirect('admin/settings.php#docs');
            }
        }
        bp_done('settings', $msg);
        bp_redirect('admin/settings.php#docs');
    } elseif ($act == 'password') {
        $u = bp_row('SELECT * FROM ' . bp_t('user') . ' WHERE id=' . (int)$me['id']);
        $new = (string)$_POST['new'];
        if (!password_verify((string)$_POST['old'], $u['password'])) bp_flash('รหัสผ่านเดิมไม่ถูกต้อง', 'err');
        elseif (strlen($new) < 8) bp_flash('รหัสผ่านใหม่อย่างน้อย 8 ตัว', 'err');
        elseif ($new !== (string)$_POST['new2']) bp_flash('ยืนยันรหัสผ่านไม่ตรงกัน', 'err');
        else {
            bp_query('UPDATE ' . bp_t('user') . ' SET password=' . q(password_hash($new, PASSWORD_DEFAULT)) . ' WHERE id=' . (int)$me['id']);
            bp_done('password', 'เปลี่ยนรหัสผ่านแล้ว');
        }
    } elseif ($act == 'adduser') {
        $un = trim((string)$_POST['username']);
        $pw = (string)$_POST['password'];
        $role = isset($_POST['role']) && isset($roles[$_POST['role']]) ? $_POST['role'] : 'unit';
        if (!preg_match('/^[A-Za-z0-9_.\-]{3,64}$/', $un)) bp_flash('ชื่อผู้ใช้ 3–64 ตัว (A-Z 0-9 _ . -)', 'err');
        elseif (strlen($pw) < 8) bp_flash('รหัสผ่านอย่างน้อย 8 ตัว', 'err');
        elseif (bp_row('SELECT id FROM ' . bp_t('user') . ' WHERE username=' . q($un))) bp_flash('มีชื่อผู้ใช้นี้แล้ว', 'err');
        else {
            bp_query('INSERT INTO ' . bp_t('user') . ' (username,password,name,role,created_at) VALUES (' . q($un) . ',' . q(password_hash($pw, PASSWORD_DEFAULT)) . ','
                . q(mb_substr(trim((string)$_POST['name']), 0, 255)) . ',' . q($role) . ",'" . date('Y-m-d H:i:s') . "')");
            $n = settings_save_units(bp_insert_id(), $role);
            if ($role == 'unit' && !$n) bp_done('user.add', 'เพิ่มผู้ใช้ ' . $un . ' แล้ว — ยังไม่ได้เลือกหน่วยงาน ผู้ใช้นี้จะยังแก้โครงการไม่ได้', 'err');
            else bp_done('user.add', 'เพิ่มผู้ใช้ ' . $un . ' (' . $roles[$role] . ') แล้ว');
        }
    } elseif ($act == 'edituser') {
        $id = (int)$_POST['id'];
        $u = bp_row('SELECT * FROM ' . bp_t('user') . ' WHERE id=' . $id);
        $role = isset($_POST['role']) && isset($roles[$_POST['role']]) ? $_POST['role'] : 'unit';
        $pw = isset($_POST['password']) ? (string)$_POST['password'] : '';
        if (!$u) bp_flash('ไม่พบผู้ใช้', 'err');
        elseif ($id == (int)$me['id'] && $role == 'unit') bp_flash('ลดสิทธิ์บัญชีตัวเองไม่ได้', 'err');
        elseif ($pw !== '' && strlen($pw) < 8) bp_flash('รหัสผ่านอย่างน้อย 8 ตัว', 'err');
        else {
            bp_query('UPDATE ' . bp_t('user') . ' SET name=' . q(mb_substr(trim((string)$_POST['name']), 0, 255)) . ',role=' . q($role)
                . ($pw !== '' ? ',password=' . q(password_hash($pw, PASSWORD_DEFAULT)) : '') . ' WHERE id=' . $id);
            settings_save_units($id, $role);
            bp_done('user.edit', 'บันทึกผู้ใช้ ' . $u['username'] . ' แล้ว' . ($pw !== '' ? ' (ตั้งรหัสผ่านใหม่)' : ''));
        }
    } elseif ($act == 'deluser') {
        $id = (int)$_POST['id'];
        $u = bp_row('SELECT * FROM ' . bp_t('user') . ' WHERE id=' . $id);
        if ($id == (int)$me['id']) bp_flash('ลบบัญชีตัวเองไม่ได้', 'err');
        elseif ($u) {
            bp_query('DELETE FROM ' . bp_t('user_unit') . ' WHERE user_id=' . $id);
            bp_query('DELETE FROM ' . bp_t('user') . ' WHERE id=' . $id);
            bp_done('user.delete', 'ลบผู้ใช้ ' . $u['username'] . ' แล้ว');
        }
    }
    bp_redirect('admin/settings.php');
}

$users = bp_rows('SELECT id,username,name,role,created_at FROM ' . bp_t('user') . ' ORDER BY role, id');
$uu = array();
foreach (bp_rows('SELECT user_id,unit_id FROM ' . bp_t('user_unit')) as $r) $uu[$r['user_id']][(int)$r['unit_id']] = true;
$units = bp_units();
$tree = bp_unit_tree();

/** ช่องเลือกหน่วยงาน (ฝ่าย = ทุกงานในฝ่าย) */
function settings_unit_picker($tree, $sel)
{
    $o = '<div class="unit-pick">';
    foreach ($tree as $t) {
        $o .= '<div class="unit-pick-g"><label class="chk"><input type="checkbox" name="units[]" value="' . (int)$t['id'] . '"' . (isset($sel[$t['id']]) ? ' checked' : '') . '> <b>' . h($t['name']) . '</b> <small class="muted">(ทั้งฝ่าย)</small></label>';
        foreach ($t['children'] as $c) $o .= '<label class="chk d-block al-sub"><input type="checkbox" name="units[]" value="' . (int)$c['id'] . '"' . (isset($sel[$c['id']]) ? ' checked' : '') . '> ' . h($c['name']) . '</label>';
        $o .= '</div>';
    }
    return $o . '</div>';
}
admin_head('ตั้งค่า / ผู้ใช้', 'settings');
?>
<div class="panel">
  <h2>ข้อมูลที่แสดงบนเว็บ</h2>
  <form method="post">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="settings">
    <?php foreach ($fields as $k => $f) { ?>
      <label class="fld"><span><?php echo h($f[0]); ?></span>
        <?php if ($k == 'intro' || $k == 'contact') { ?><textarea name="<?php echo $k; ?>" rows="2"><?php echo h(bp_setting($k)); ?></textarea>
        <?php } else { ?><input name="<?php echo $k; ?>" value="<?php echo h($k == 'site_name' ? bp_site_name() : ($k == 'disb_target' ? implode(',', bp_disb_target()) : bp_setting($k))); ?>"><?php } ?>
        <?php if ($f[1]) { ?><small><?php echo h($f[1]); ?></small><?php } ?></label>
    <?php } ?>
    <p><button class="btn" type="submit">บันทึก</button></p>
  </form>
</div>

<form class="panel" method="post" enctype="multipart/form-data" id="docs">
  <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="docs">
  <h2>เอกสารสำหรับพิมพ์ (PDF แบบทางการ)</h2>
  <p class="muted small">ชื่อและตำแหน่งที่จะพิมพ์ใต้ช่องลงนามในรายงาน 6/12 เดือน แผนปฏิบัติการ แผนจัดซื้อจัดจ้าง และ สขร.1 — เว้นชื่อว่างไว้ = พิมพ์เป็นเส้นประให้เขียนด้วยมือ</p>
  <div class="scroll-x"><table class="tbl">
    <thead><tr><th>ผู้ลงนาม</th><th>ชื่อ-สกุล (มีคำนำหน้า)</th><th>ตำแหน่ง</th></tr></thead>
    <tbody>
    <?php foreach (settings_doc_signers() as $k => $s) { ?>
      <tr><td class="small"><?php echo h($s[0]); ?></td>
        <td><input name="doc_<?php echo $k; ?>_name" value="<?php echo h(bp_setting('doc_' . $k . '_name')); ?>" placeholder="เช่น นางสาวสมใจ ใฝ่รู้"></td>
        <td><input name="doc_<?php echo $k; ?>_pos" value="<?php echo h(bp_setting('doc_' . $k . '_pos')); ?>" placeholder="<?php echo h($s[1]); ?>"></td></tr>
    <?php } ?>
    </tbody>
  </table></div>
  <?php $lg = bp_setting('doc_logo'); $lgOk = $lg !== '' && is_file(BP_ROOT . '/files/' . $lg); ?>
  <div class="form-row mt">
    <?php if ($lgOk) { ?><img src="<?php echo h(bp_url('files/' . $lg)); ?>" alt="ตราสถานศึกษา" style="width:72px;height:72px;object-fit:contain;border:1px solid var(--line);border-radius:8px;background:#fff"><?php } ?>
    <label class="fld grow"><span>ตราสถานศึกษา (ไม่บังคับ) — แสดงบนหน้าปกเอกสาร</span><input type="file" name="logo" accept="image/png,image/jpeg">
      <small>ใช้ตราของสถานศึกษาเอง (png พื้นโปร่งใสดีที่สุด) · ระบบไม่ใส่ตราหน่วยงานอื่นให้</small></label>
    <?php if ($lgOk) { ?><label class="chk"><input type="checkbox" name="logo_del" value="1"> ลบตรา</label><?php } ?>
  </div>
  <p><button class="btn" type="submit">บันทึก</button>
    <?php $yy = bp_years(true); if ($yy) { $y0 = (int)$yy[0]['year']; ?>
    <span class="muted small">ดูตัวอย่าง: <a href="<?php echo h(bp_url('doc.php?d=report&y=' . $y0)); ?>" target="_blank" rel="noopener">รายงาน</a> ·
      <a href="<?php echo h(bp_url('doc.php?d=plan&y=' . $y0)); ?>" target="_blank" rel="noopener">แผนปฏิบัติการ</a> ·
      <a href="<?php echo h(bp_url('doc.php?d=procure&y=' . $y0)); ?>" target="_blank" rel="noopener">แผนจัดซื้อจัดจ้าง</a> ·
      <a href="<?php echo h(bp_url('doc.php?d=sk1&y=' . $y0)); ?>" target="_blank" rel="noopener">สขร.1</a></span><?php } ?></p>
</form>

<div class="panel">
  <h2>ผู้ใช้ระบบ</h2>
  <p class="muted small"><b>ผู้ดูแลระบบ</b> = แก้ไขได้ทุกอย่าง · <b>ผู้กรอกข้อมูลหน่วยงาน</b> = เช่น หัวหน้างาน/หัวหน้าแผนก แก้ได้เฉพาะยอดเบิกจ่ายรายไตรมาส สถานะ ผลการดำเนินงาน และรูป/ไฟล์ ของโครงการในหน่วยงานที่เลือกให้</p>
  <div class="scroll-x">
  <table class="tbl">
    <thead><tr><th>ชื่อผู้ใช้</th><th>สิทธิ์</th><th class="hide-sm">หน่วยงานที่ดูแล</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $u) { $sel = isset($uu[$u['id']]) ? $uu[$u['id']] : array(); $names = array(); foreach ($sel as $id => $t) if (isset($units[$id])) $names[] = $units[$id]['name']; ?>
    <tr>
      <td><b><?php echo h($u['username']); ?></b><small class="muted d-block"><?php echo h($u['name']); ?></small></td>
      <td><span class="tag<?php echo $u['role'] == 'unit' ? '' : ' ok'; ?>"><?php echo h(isset($roles[$u['role']]) ? $roles[$u['role']] : $u['role']); ?></span><?php if ($u['id'] == $me['id']) echo ' <span class="tag">คุณ</span>'; ?></td>
      <td class="hide-sm small"><?php echo $u['role'] == 'unit' ? ($names ? h(implode(', ', $names)) : '<span class="muted">ยังไม่ได้เลือก</span>') : '<span class="muted">ทั้งหมด</span>'; ?></td>
      <td class="actions">
        <details class="inline-edit"><summary class="btn btn-sm">แก้ไข</summary>
          <form method="post" class="panel inline-edit-box">
            <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="edituser"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
            <label class="fld"><span>ชื่อ-สกุล</span><input name="name" value="<?php echo h($u['name']); ?>"></label>
            <label class="fld"><span>สิทธิ์</span><select name="role" class="role-sel"><?php foreach ($roles as $k => $v) { ?><option value="<?php echo $k; ?>" <?php echo $u['role'] == $k || ($k == 'admin' && $u['role'] != 'unit') ? 'selected' : ''; ?>><?php echo $v; ?></option><?php } ?></select></label>
            <div class="fld unit-fld"><span>หน่วยงานที่ดูแล</span><?php echo settings_unit_picker($tree, $sel); ?></div>
            <label class="fld"><span>ตั้งรหัสผ่านใหม่ (เว้นว่าง = ไม่เปลี่ยน)</span><input type="password" name="password" autocomplete="new-password"></label>
            <p><button class="btn" type="submit">บันทึก</button></p>
          </form>
        </details>
        <?php if ($u['id'] != $me['id']) { ?>
        <form method="post" class="inline" onsubmit="return confirm('ลบผู้ใช้ <?php echo h($u['username']); ?>?');"><?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="deluser"><input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>"><button class="btn btn-sm btn-danger">ลบ</button></form>
        <?php } ?>
      </td>
    </tr>
    <?php } ?>
    </tbody>
  </table>
  </div>
</div>

<div class="grid2">
  <div class="panel">
    <h2>เพิ่มผู้ใช้</h2>
    <form method="post">
      <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="adduser">
      <label class="fld"><span>ชื่อผู้ใช้</span><input name="username" autocomplete="off" required></label>
      <label class="fld"><span>ชื่อ-สกุล</span><input name="name"></label>
      <label class="fld"><span>สิทธิ์</span><select name="role" class="role-sel"><option value="unit">ผู้กรอกข้อมูลหน่วยงาน</option><option value="admin">ผู้ดูแลระบบ</option></select></label>
      <div class="fld unit-fld"><span>หน่วยงานที่ดูแล</span><?php echo $tree ? settings_unit_picker($tree, array()) : '<small>ยังไม่มีหน่วยงาน — ตั้งที่เมนู หน่วยงาน ก่อน</small>'; ?></div>
      <label class="fld"><span>รหัสผ่าน (อย่างน้อย 8 ตัว)</span><input type="password" name="password" autocomplete="new-password" required></label>
      <p><button class="btn" type="submit">+ เพิ่มผู้ใช้</button></p>
    </form>
  </div>
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
</div>
<script>
document.querySelectorAll('.role-sel').forEach(function (s) {
  var f = s.closest('form').querySelector('.unit-fld');
  var sync = function () { if (f) f.hidden = s.value !== 'unit'; };
  s.addEventListener('change', sync); sync();
});
</script>
<?php admin_foot(); ?>
