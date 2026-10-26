<?php
// ทำให้รันได้ตั้งแต่ PHP 5.4 (CentOS 7) ถึง 8.x — ห้ามใช้ ?? <=> fn ฯลฯ ในโปรเจกต์นี้
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

if (!function_exists('hash_equals')) {
    function hash_equals($a, $b)
    {
        if (!is_string($a) || !is_string($b) || strlen($a) !== strlen($b)) return false;
        $r = 0;
        for ($i = 0; $i < strlen($a); $i++) $r |= ord($a[$i]) ^ ord($b[$i]);
        return $r === 0;
    }
}

function bp_random_hex($bytes)
{
    if (function_exists('random_bytes')) return bin2hex(random_bytes($bytes));
    if (function_exists('openssl_random_pseudo_bytes')) return bin2hex(openssl_random_pseudo_bytes($bytes));
    $s = '';
    for ($i = 0; $i < $bytes; $i++) $s .= chr(mt_rand(0, 255));
    return bin2hex($s);
}

if (!function_exists('password_hash')) {
    if (!defined('PASSWORD_DEFAULT')) define('PASSWORD_DEFAULT', 1);
    function password_hash($pass, $algo, $opt = array())
    {
        $salt = substr(strtr(base64_encode(pack('H*', bp_random_hex(16))), '+', '.'), 0, 22);
        $h = crypt($pass, '$2y$10$' . $salt);
        return strlen($h) == 60 ? $h : false;
    }
    function password_verify($pass, $hash)
    {
        $c = crypt($pass, $hash);
        return is_string($c) && strlen($c) == strlen($hash) && hash_equals($hash, $c);
    }
}
