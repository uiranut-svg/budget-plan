<?php
if (!defined('BP_ROOT')) define('BP_ROOT', dirname(dirname(__FILE__)));
@ini_set('display_errors', '0');
date_default_timezone_set('Asia/Bangkok');
require_once BP_ROOT . '/inc/compat.php';
require_once BP_ROOT . '/inc/functions.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

$cfgFile = BP_ROOT . '/inc/config.php';
if (!is_file($cfgFile)) bp_redirect('setup.php');
$BP_CFG = require $cfgFile;
$BP_DB = bp_connect($BP_CFG);
if (!$BP_DB) {
    http_response_code(503);
    exit('<meta charset="utf-8">เชื่อมต่อฐานข้อมูลไม่ได้ กรุณาลองใหม่ภายหลัง');
}
