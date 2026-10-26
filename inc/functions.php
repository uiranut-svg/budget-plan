<?php
// ---------- ที่อยู่ของระบบ (ไม่ฝังชื่อโฟลเดอร์ — เปลี่ยนชื่อโฟลเดอร์ได้) ----------
function bp_base()
{
    static $b = null;
    if ($b !== null) return $b;
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $depth = defined('BP_DEPTH') ? BP_DEPTH : 0;
    for ($i = 0; $i < $depth; $i++) $dir = str_replace('\\', '/', dirname($dir));
    $b = rtrim($dir, '/') . '/';
    return $b;
}
function bp_url($path = '')
{
    return bp_base() . ltrim($path, '/');
}
function bp_https()
{
    if (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) != 'off') return true;
    if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) return true;
    return false;
}
function bp_redirect($path)
{
    header('Location: ' . (preg_match('#^(https?:)?/#', $path) ? $path : bp_url($path)));
    exit;
}

// ---------- ข้อความ/ตัวเลข ----------
function h($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function bp_money($v, $dec = 2)
{
    return number_format((float)$v, $dec);
}
function bp_pct($part, $total, $dec = 2)
{
    if ($total <= 0) return '0.00';
    return number_format($part * 100 / $total, $dec);
}
function bp_money_short($v)
{
    $v = (float)$v;
    if (abs($v) >= 1000000) return number_format($v / 1000000, 2) . ' ล้านบาท';
    return number_format($v, 2) . ' บาท';
}
function bp_parse_amount($s)
{
    $s = preg_replace('/[^0-9.\-]/', '', (string)$s);
    if ($s === '' || $s === '-' || $s === '.') return 0;
    return round((float)$s, 2);
}
function bp_json($v)
{
    $f = 0;
    if (defined('JSON_UNESCAPED_UNICODE')) $f |= JSON_UNESCAPED_UNICODE;
    if (defined('JSON_HEX_TAG')) $f |= JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
    return json_encode($v, $f);
}
function bp_palette($i)
{
    $p = array('#2563eb', '#16a34a', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2', '#db2777', '#65a30d', '#ea580c', '#475569');
    return $p[$i % count($p)];
}
function bp_color($c, $i)
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c) ? $c : bp_palette($i);
}

// ---------- ฐานข้อมูล ----------
function bp_connect($cfg)
{
    if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
    $db = @mysqli_connect($cfg['db_host'], $cfg['db_user'], $cfg['db_pass'], $cfg['db_name']);
    if (!$db) return false;
    if (!@mysqli_set_charset($db, 'utf8mb4')) mysqli_set_charset($db, 'utf8');
    return $db;
}
function bp_db()
{
    return $GLOBALS['BP_DB'];
}
function bp_t($name)
{
    return '`' . $GLOBALS['BP_CFG']['prefix'] . $name . '`';
}
function q($v)
{
    if ($v === null) return 'NULL';
    return "'" . mysqli_real_escape_string(bp_db(), (string)$v) . "'";
}
function bp_query($sql)
{
    $r = mysqli_query(bp_db(), $sql);
    if ($r === false) error_log('budget-plan SQL: ' . mysqli_error(bp_db()) . ' :: ' . substr($sql, 0, 500));
    return $r;
}
function bp_rows($sql)
{
    $r = bp_query($sql);
    $o = array();
    if ($r && $r !== true) {
        while ($x = mysqli_fetch_assoc($r)) $o[] = $x;
    }
    return $o;
}
function bp_row($sql)
{
    $a = bp_rows($sql);
    return $a ? $a[0] : null;
}
function bp_insert_id()
{
    return mysqli_insert_id(bp_db());
}

// ---------- ค่าตั้งค่า ----------
function bp_settings_all($reload = false)
{
    static $c = null;
    if ($c === null || $reload) {
        $c = array();
        foreach (bp_rows('SELECT name,value FROM ' . bp_t('setting')) as $r) $c[$r['name']] = $r['value'];
    }
    return $c;
}
function bp_setting($k, $def = '')
{
    $c = bp_settings_all();
    return (isset($c[$k]) && $c[$k] !== '') ? $c[$k] : $def;
}
function bp_setting_set($k, $v)
{
    bp_query('REPLACE INTO ' . bp_t('setting') . ' (name,value) VALUES (' . q($k) . ',' . q($v) . ')');
    bp_settings_all(true);
}
function bp_site_name()
{
    return bp_setting('site_name', 'ข้อมูลแผนงานและงบประมาณ');
}

// ---------- เซสชัน / CSRF / ล็อกอิน ----------
function bp_session_start()
{
    if (session_id() !== '') return;
    session_name('BPSID');
    session_set_cookie_params(0, bp_base(), '', bp_https(), true);
    session_start();
}
function bp_csrf_token()
{
    bp_session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bp_random_hex(16);
    return $_SESSION['csrf'];
}
function bp_csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . h(bp_csrf_token()) . '">';
}
function bp_csrf_check()
{
    $t = isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? (string)$_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if ($t === '' || !hash_equals(bp_csrf_token(), $t)) {
        http_response_code(400);
        exit('คำขอไม่ถูกต้อง (หมดเวลา) กรุณาย้อนกลับแล้วลองใหม่');
    }
}
function bp_user()
{
    bp_session_start();
    if (empty($_SESSION['uid'])) return null;
    static $u = false;
    if ($u === false) $u = bp_row('SELECT id,username,name FROM ' . bp_t('user') . ' WHERE id=' . (int)$_SESSION['uid']);
    return $u;
}
function bp_require_login()
{
    if (!bp_user()) bp_redirect('admin/login.php');
    if ($_SERVER['REQUEST_METHOD'] == 'POST') bp_csrf_check();
}
function bp_flash($msg = null, $type = 'ok')
{
    bp_session_start();
    if ($msg !== null) {
        $_SESSION['flash'] = array($type, $msg);
        return null;
    }
    if (empty($_SESSION['flash'])) return null;
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $f;
}
function bp_client_ip()
{
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

// ---------- ข้อมูลงบประมาณ ----------
function bp_years($publishedOnly = true)
{
    return bp_rows('SELECT * FROM ' . bp_t('year') . ($publishedOnly ? ' WHERE published=1' : '') . ' ORDER BY year DESC');
}
/** หมวด + รายการ + ยอดรวม ของปีหนึ่ง (หมวดที่มีรายการ = ผลรวมรายการ, ไม่มี = ยอดที่กรอกตรง) */
function bp_year_data($yid)
{
    $yid = (int)$yid;
    $cats = bp_rows('SELECT * FROM ' . bp_t('category') . ' WHERE year_id=' . $yid . ' ORDER BY sort,id');
    $items = bp_rows('SELECT i.* FROM ' . bp_t('item') . ' i JOIN ' . bp_t('category') . ' c ON c.id=i.category_id WHERE c.year_id=' . $yid . ' ORDER BY i.sort,i.id');
    $by = array();
    foreach ($items as $it) $by[$it['category_id']][] = $it;
    $total = 0;
    $out = array();
    foreach ($cats as $i => $c) {
        $c['items'] = isset($by[$c['id']]) ? $by[$c['id']] : array();
        $c['color'] = bp_color($c['color'], $i);
        if ($c['items']) {
            $s = 0;
            foreach ($c['items'] as $it) $s += (float)$it['amount'];
            $c['total'] = round($s, 2);
        } else {
            $c['total'] = round((float)$c['amount'], 2);
        }
        $total += $c['total'];
        $out[] = $c;
    }
    return array('categories' => $out, 'total' => round($total, 2));
}
/** ยอดรวมตามแผนก/หน่วยงาน (จากรายการย่อยที่ระบุหน่วยงาน) */
function bp_by_department($data)
{
    $d = array();
    foreach ($data['categories'] as $c) {
        foreach ($c['items'] as $it) {
            $k = trim($it['department']);
            if ($k === '') $k = 'ไม่ระบุหน่วยงาน';
            if (!isset($d[$k])) $d[$k] = 0;
            $d[$k] += (float)$it['amount'];
        }
    }
    arsort($d);
    return $d;
}
function bp_human_size($b)
{
    if ($b >= 1048576) return number_format($b / 1048576, 1) . ' MB';
    return number_format($b / 1024, 0) . ' KB';
}
