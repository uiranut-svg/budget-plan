<?php
require dirname(__FILE__) . '/_admin.php';
bp_session_start();
if (bp_user()) bp_redirect('admin/index.php');

$err = '';
$ip = bp_client_ip();
$fails = bp_row('SELECT COUNT(*) n FROM ' . bp_t('login_fail') . ' WHERE ip=' . q($ip) . " AND at > '" . date('Y-m-d H:i:s', time() - 900) . "'");
$locked = $fails && (int)$fails['n'] >= 5;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    bp_csrf_check();
    if ($locked) {
        $err = 'ล็อกอินผิดหลายครั้ง กรุณารอ 15 นาที';
    } else {
        $user = isset($_POST['username']) ? trim((string)$_POST['username']) : '';
        $pass = isset($_POST['password']) ? (string)$_POST['password'] : '';
        $u = $user !== '' ? bp_row('SELECT * FROM ' . bp_t('user') . ' WHERE username=' . q($user)) : null;
        if ($u && password_verify($pass, $u['password'])) {
            bp_query('DELETE FROM ' . bp_t('login_fail') . ' WHERE ip=' . q($ip));
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            $_SESSION['csrf'] = bp_random_hex(16);
            bp_redirect('admin/index.php');
        }
        bp_query('INSERT INTO ' . bp_t('login_fail') . ' (ip,at) VALUES (' . q($ip) . ",'" . date('Y-m-d H:i:s') . "')");
        bp_query('DELETE FROM ' . bp_t('login_fail') . " WHERE at < '" . date('Y-m-d H:i:s', time() - 86400) . "'");
        $err = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }
}
?><!doctype html>
<html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>เข้าสู่ระบบ · <?php echo h(bp_site_name()); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo h(bp_url('assets/css/app.css')); ?>?v=8"></head>
<body class="setup">
<main class="setup-box narrow">
  <h1>เข้าสู่ระบบผู้ดูแล</h1>
  <p class="muted"><?php echo h(bp_site_name()); ?> · <?php echo h(bp_setting('school_name')); ?></p>
  <?php if ($err) { ?><div class="alert alert-err"><?php echo h($err); ?></div><?php } ?>
  <form method="post">
    <?php echo bp_csrf_field(); ?>
    <label class="fld"><span>ชื่อผู้ใช้</span><input name="username" autocomplete="username" required autofocus></label>
    <label class="fld"><span>รหัสผ่าน</span><input type="password" name="password" autocomplete="current-password" required></label>
    <p><button class="btn btn-block" type="submit">เข้าสู่ระบบ</button></p>
  </form>
  <p class="center"><a href="<?php echo h(bp_url('index.php')); ?>">← กลับหน้าแรก</a></p>
</main>
</body></html>
