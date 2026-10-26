<?php
require dirname(__FILE__) . '/_admin.php';
bp_session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    bp_csrf_check();
    $_SESSION = array();
    session_destroy();
}
bp_redirect('admin/login.php');
