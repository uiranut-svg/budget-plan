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
    // หลัง Cloudflare Tunnel: ผู้ใช้เข้า https แต่เครื่องเห็นเป็น http
    if (bp_from_local_proxy() && isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') return true;
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
    $p = array('#b3141b', '#1f3a68', '#c9a227', '#2f7d4f', '#7a4a9e', '#0e7490', '#d9480f', '#6b5b4e', '#e05a6a', '#475569');
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
    if ($u === false) $u = bp_row('SELECT id,username,name,role FROM ' . bp_t('user') . ' WHERE id=' . (int)$_SESSION['uid']);
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
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    // ผ่าน Cloudflare Tunnel: คำขอมาจาก 127.0.0.1 ทั้งหมด → ใช้ IP จริงจาก header (เชื่อเฉพาะเมื่อมาจากเครื่องตัวเอง)
    if (bp_from_local_proxy() && !empty($_SERVER['HTTP_CF_CONNECTING_IP']) && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return $ip;
}
/** คำขอมาจากเครื่องตัวเอง (เช่น cloudflared) และมี header ของ proxy */
function bp_from_local_proxy()
{
    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    return ($ip === '127.0.0.1' || $ip === '::1') && (isset($_SERVER['HTTP_CF_CONNECTING_IP']) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']));
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

// ---------- อัปเกรดฐานข้อมูล (สร้างตารางที่เพิ่มในรุ่นใหม่ — ทุกคำสั่งเป็น IF NOT EXISTS) ----------
define('BP_SCHEMA_VER', 4);
function bp_migrate()
{
    if ((int)bp_setting('schema_ver', '1') >= BP_SCHEMA_VER) return;
    foreach (require BP_ROOT . '/inc/schema.php' as $sql) {
        if (!bp_query(str_replace('{p}', $GLOBALS['BP_CFG']['prefix'], $sql))) return;
    }
    // คอลัมน์ที่เพิ่มในตารางเดิม
    foreach (require BP_ROOT . '/inc/schema_columns.php' as $table => $cols) {
        $have = array();
        foreach (bp_rows('SHOW COLUMNS FROM ' . bp_t($table)) as $c) $have[$c['Field']] = true;
        foreach ($cols as $col => $def) {
            if (!isset($have[$col]) && !bp_query('ALTER TABLE ' . bp_t($table) . ' ADD `' . $col . '` ' . $def)) return;
        }
    }
    bp_setting_set('schema_ver', BP_SCHEMA_VER);
}

// ---------- บันทึกการแก้ไข ----------
function bp_log($action, $detail = '')
{
    $u = bp_user();
    bp_query('INSERT INTO ' . bp_t('log') . ' (user_id,username,action,detail,ip,at) VALUES (' . ($u ? (int)$u['id'] : 'NULL') . ',' . q($u ? $u['username'] : '') . ','
        . q(mb_substr($action, 0, 64)) . ',' . q(mb_substr((string)$detail, 0, 500)) . ',' . q(bp_client_ip()) . ",'" . date('Y-m-d H:i:s') . "')");
}

// ---------- วันที่ภาษาไทย ----------
function bp_thai_months($short = true)
{
    return $short ? array(1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.')
        : array(1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม');
}
/** '2026-10-27 13:05:00' → '27 ต.ค. 2569' (+ เวลา) */
function bp_thai_date($dt, $time = false, $short = true)
{
    $t = is_numeric($dt) ? (int)$dt : strtotime((string)$dt);
    if (!$t) return '';
    $m = bp_thai_months($short);
    return (int)date('j', $t) . ' ' . $m[(int)date('n', $t)] . ' ' . ((int)date('Y', $t) + 543) . ($time ? ' เวลา ' . date('H:i', $t) . ' น.' : '');
}
/** 'YYYY-MM' → 'ต.ค. 2568' */
function bp_thai_month($ym)
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', (string)$ym, $x)) return '';
    $m = bp_thai_months(true);
    return $m[(int)$x[2]] . ' ' . ((int)$x[1] + 543);
}
/** เดือนของปีงบประมาณ พ.ศ. (ต.ค. ปีก่อน → ก.ย.) เป็น 'YYYY-MM' ค.ศ. */
function bp_fy_months($yearBE)
{
    $o = array();
    $y = (int)$yearBE - 544;
    for ($i = 0; $i < 12; $i++) {
        $m = 10 + $i;
        $yy = $y + ($m > 12 ? 1 : 0);
        $o[] = sprintf('%04d-%02d', $yy, $m > 12 ? $m - 12 : $m);
    }
    return $o;
}

// ---------- หน่วยงาน ----------
/** หน่วยงานทั้งหมด: array(id => row) + 'path' = "ฝ่าย › งาน" */
function bp_units($reload = false)
{
    static $c = null;
    if ($c !== null && !$reload) return $c;
    $c = array();
    $rows = bp_rows('SELECT * FROM ' . bp_t('unit') . ' ORDER BY sort,id');
    foreach ($rows as $r) $c[$r['id']] = $r;
    foreach ($c as $id => $r) {
        $p = $r['parent_id'] && isset($c[$r['parent_id']]) ? $c[$r['parent_id']] : null;
        $c[$id]['top_id'] = $p ? $p['id'] : $r['id'];
        $c[$id]['top'] = $p ? $p['name'] : $r['name'];
    }
    return $c;
}
/** ต้นไม้หน่วยงาน: array of ฝ่าย + 'children' */
function bp_unit_tree()
{
    $tops = array();
    $kids = array();
    foreach (bp_units() as $u) {
        if ($u['parent_id']) $kids[$u['parent_id']][] = $u;
        else $tops[] = $u;
    }
    foreach ($tops as $i => $t) $tops[$i]['children'] = isset($kids[$t['id']]) ? $kids[$t['id']] : array();
    return $tops;
}

// ---------- แหล่งเงิน ----------
/** แหล่งเงินของปี เป็นต้นไม้ 2 ชั้น · ยอดของแหล่งหลักที่มีแหล่งย่อย = ผลรวมแหล่งย่อย */
function bp_funds($yid)
{
    $rows = bp_rows('SELECT * FROM ' . bp_t('fund') . ' WHERE year_id=' . (int)$yid . ' ORDER BY sort,id');
    $tops = array();
    $kids = array();
    foreach ($rows as $r) {
        foreach (array('estimate', 'carry_in', 'received') as $k) $r[$k] = round((float)$r[$k], 2);
        if ($r['parent_id']) $kids[$r['parent_id']][] = $r;
        else $tops[] = $r;
    }
    $sum = array('estimate' => 0, 'carry_in' => 0, 'received' => 0);
    foreach ($tops as $i => $t) {
        $t['color'] = bp_color($t['color'], $i);
        $t['children'] = isset($kids[$t['id']]) ? $kids[$t['id']] : array();
        if ($t['children']) {
            foreach (array('estimate', 'carry_in', 'received') as $k) {
                $s = 0;
                foreach ($t['children'] as $ch) $s += $ch[$k];
                $t[$k] = round($s, 2);
            }
        }
        foreach ($sum as $k => $v) $sum[$k] += $t[$k];
        $tops[$i] = $t;
    }
    return array('funds' => $tops, 'sum' => $sum);
}
/** id แหล่งเงิน => array(name, path, color, top_id) */
function bp_fund_index($funds)
{
    $o = array();
    foreach ($funds['funds'] as $t) {
        $o[$t['id']] = array('name' => $t['name'], 'path' => $t['name'], 'color' => $t['color'], 'top_id' => $t['id']);
        foreach ($t['children'] as $ch) $o[$ch['id']] = array('name' => $ch['name'], 'path' => $t['name'] . ' › ' . $ch['name'], 'color' => $t['color'], 'top_id' => $t['id']);
    }
    return $o;
}

// ---------- โครงการ ----------
function bp_project_statuses()
{
    return array(
        'plan' => 'ตามแผน (ยังไม่เริ่ม)',
        'doing' => 'กำลังดำเนินการ',
        'done' => 'ดำเนินการแล้วเสร็จ',
        'reserve' => 'โครงการสำรอง',
        'cancel' => 'ยกเลิก / ไม่ได้รับอนุมัติ',
    );
}
/** หมวดรายจ่ายมาตรฐาน (ใช้เป็นตัวเลือกในฟอร์มโครงการ) */
function bp_expense_types()
{
    return array(
        'งบบุคลากร' => array('เงินเดือน', 'ค่าจ้างประจำ', 'ค่าจ้างชั่วคราว', 'ค่าตอบแทนพนักงานราชการ'),
        'งบดำเนินงาน' => array('ค่าตอบแทน', 'ค่าใช้สอย', 'ค่าวัสดุ', 'ค่าสาธารณูปโภค'),
        'งบลงทุน' => array('ค่าครุภัณฑ์', 'ค่าที่ดินและสิ่งก่อสร้าง'),
        'งบเงินอุดหนุน' => array('เงินอุดหนุน'),
        'งบรายจ่ายอื่น' => array('รายจ่ายอื่น'),
    );
}
/** โครงการของปี + ความสอดคล้อง (align_ids) · $public = ไม่รวมที่ยกเลิก */
function bp_projects($yid, $public = false)
{
    $rows = bp_rows('SELECT * FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$yid . ($public ? " AND status<>'cancel'" : '') . ' ORDER BY code,id');
    if (!$rows) return array();
    $ids = array();
    foreach ($rows as $r) $ids[] = (int)$r['id'];
    $links = array();
    foreach (bp_rows('SELECT project_id,align_id FROM ' . bp_t('project_align') . ' WHERE project_id IN (' . implode(',', $ids) . ')') as $l) $links[$l['project_id']][] = (int)$l['align_id'];
    foreach ($rows as $i => $r) {
        foreach (array('requested', 'approved', 'spent') as $k) $rows[$i][$k] = round((float)$r[$k], 2);
        $rows[$i]['align_ids'] = isset($links[$r['id']]) ? $links[$r['id']] : array();
    }
    return $rows;
}
function bp_quarters_label($q)
{
    $q = trim((string)$q);
    if ($q === '') return '';
    return 'ไตรมาส ' . str_replace(',', ', ', $q);
}

// ---------- ความสอดคล้อง ----------
/** ชุดความสอดคล้องของปี: array of set + 'items' (แต่ละข้อมี 'children') */
function bp_aligns($yid)
{
    $rows = bp_rows('SELECT * FROM ' . bp_t('align') . ' WHERE year_id=' . (int)$yid . ' ORDER BY sort,id');
    $by = array();
    foreach ($rows as $r) $by[(int)$r['parent_id']][] = $r;
    $sets = isset($by[0]) ? $by[0] : array();
    foreach ($sets as $i => $s) {
        $items = isset($by[$s['id']]) ? $by[$s['id']] : array();
        foreach ($items as $j => $it) $items[$j]['children'] = isset($by[$it['id']]) ? $by[$it['id']] : array();
        $sets[$i]['items'] = $items;
    }
    return $sets;
}
/** สรุปโครงการ: จำนวน ยอด แยกตามฝ่าย/ไตรมาส/สถานะ (โครงการสำรองไม่นับรวมยอด) */
function bp_project_summary($projects)
{
    $units = bp_units();
    $s = array('n' => 0, 'n_reserve' => 0, 'n_done' => 0, 'approved' => 0, 'spent' => 0, 'requested' => 0,
        'status' => array(), 'by_div' => array(), 'by_q' => array(1 => 0, 2 => 0, 3 => 0, 4 => 0), 'top' => array());
    foreach ($projects as $p) {
        if (!isset($s['status'][$p['status']])) $s['status'][$p['status']] = 0;
        $s['status'][$p['status']]++;
        if ($p['status'] == 'reserve') { $s['n_reserve']++; continue; }
        if ($p['status'] == 'cancel') continue;
        $s['n']++;
        if ($p['status'] == 'done') $s['n_done']++;
        $s['approved'] += $p['approved'];
        $s['spent'] += $p['spent'];
        $s['requested'] += $p['requested'];
        $div = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['top'] : 'ไม่ระบุหน่วยงาน';
        if (!isset($s['by_div'][$div])) $s['by_div'][$div] = array('approved' => 0, 'spent' => 0, 'n' => 0);
        $s['by_div'][$div]['approved'] += $p['approved'];
        $s['by_div'][$div]['spent'] += $p['spent'];
        $s['by_div'][$div]['n']++;
        $qs = array();
        foreach (explode(',', $p['quarters']) as $q) if ((int)$q >= 1 && (int)$q <= 4) $qs[] = (int)$q;
        foreach ($qs as $q) $s['by_q'][$q] += $p['approved'] / count($qs);
        $s['top'][] = $p;
    }
    uasort($s['by_div'], 'bp_cmp_approved');
    usort($s['top'], 'bp_cmp_approved');
    $s['top'] = array_slice($s['top'], 0, 8);
    return $s;
}
function bp_cmp_approved($a, $b)
{
    if ($a['approved'] == $b['approved']) return 0;
    return $a['approved'] < $b['approved'] ? 1 : -1;
}
/** นับโครงการ/งบอนุมัติ ต่อข้อความสอดคล้อง (ข้อหลักรวมข้อย่อย ไม่นับซ้ำ) */
function bp_align_stats($sets, $projects)
{
    $by = array();
    foreach ($projects as $p) {
        if ($p['status'] == 'reserve' || $p['status'] == 'cancel') continue;
        foreach ($p['align_ids'] as $a) $by[$a][$p['id']] = $p['approved'];
    }
    $out = array();
    foreach ($sets as $set) {
        $setP = array();
        $items = array();
        foreach ($set['items'] as $it) {
            $ps = isset($by[$it['id']]) ? $by[$it['id']] : array();
            $kids = array();
            foreach ($it['children'] as $ch) {
                $cp = isset($by[$ch['id']]) ? $by[$ch['id']] : array();
                $ps = $ps + $cp;
                $kids[] = array('name' => $ch['name'], 'n' => count($cp), 'amount' => array_sum($cp));
            }
            $setP = $setP + $ps;
            $items[] = array('name' => $it['name'], 'n' => count($ps), 'amount' => array_sum($ps), 'children' => $kids);
        }
        $out[] = array('name' => $set['name'], 'n' => count($setP), 'amount' => array_sum($setP), 'items' => $items);
    }
    return $out;
}

// ---------- สิทธิ์ผู้ใช้ ----------
// admin = ผู้ดูแลระบบ (ทุกอย่าง) · unit = ผู้กรอกข้อมูลหน่วยงาน (แก้ได้เฉพาะความก้าวหน้า/ผล/ไฟล์ของโครงการในหน่วยงานที่ได้รับมอบหมาย)
function bp_roles()
{
    return array('admin' => 'ผู้ดูแลระบบ', 'unit' => 'ผู้กรอกข้อมูลหน่วยงาน');
}
function bp_is_admin()
{
    $u = bp_user();
    return $u && $u['role'] !== 'unit';
}
function bp_require_admin()
{
    bp_require_login();
    if (!bp_is_admin()) bp_redirect('admin/my.php');
}
/** หน่วยงานที่ผู้ใช้ดูแล (รวมงาน/แผนกในฝ่ายที่ได้รับมอบหมาย) */
function bp_user_unit_ids($uid)
{
    $ids = array();
    foreach (bp_rows('SELECT unit_id FROM ' . bp_t('user_unit') . ' WHERE user_id=' . (int)$uid) as $r) $ids[(int)$r['unit_id']] = true;
    foreach (bp_units() as $u) if ($u['parent_id'] && isset($ids[(int)$u['parent_id']])) $ids[(int)$u['id']] = true;
    return array_keys($ids);
}
function bp_can_edit_project($p)
{
    if (bp_is_admin()) return true;
    $u = bp_user();
    return $u && $p['unit_id'] && in_array((int)$p['unit_id'], bp_user_unit_ids($u['id']), true);
}

// ---------- ตรวจความผิดปกติของตัวเลข ----------
/** คืนค่า array of array(ข้อความ, ลิงก์ในหน้าผู้ดูแล) */
function bp_checks($yid)
{
    $yid = (int)$yid;
    $w = array();
    $data = bp_year_data($yid);
    $funds = bp_funds($yid);
    $avail = $funds['sum']['estimate'] + $funds['sum']['carry_in'];
    if ($funds['funds'] && $avail > 0 && $data['total'] > $avail + 0.004) {
        $w[] = array('งบรายจ่ายรวม ' . bp_money($data['total']) . ' บาท มากกว่าประมาณการรายรับ + ยกมา ' . bp_money($avail) . ' บาท', 'funds.php?id=' . $yid);
    }
    if (!$funds['funds']) $w[] = array('ยังไม่ได้ตั้งแหล่งเงินของปีนี้', 'funds.php?id=' . $yid);
    $fidx = bp_fund_index($funds);
    $useByTop = array();
    $projects = bp_projects($yid);
    foreach ($projects as $p) {
        $link = 'project.php?id=' . (int)$p['id'];
        $name = ($p['code'] !== '' ? $p['code'] . ' ' : '') . $p['title'];
        $active = $p['status'] != 'reserve' && $p['status'] != 'cancel';
        if ($p['spent'] > $p['approved'] + 0.004) $w[] = array($name . ': เบิกจ่าย ' . bp_money($p['spent']) . ' เกินงบอนุมัติ ' . bp_money($p['approved']), $link);
        if (!$active) continue;
        if ($p['approved'] <= 0) $w[] = array($name . ': ยังไม่มีงบอนุมัติ', $link);
        if ($p['status'] == 'done' && $p['spent'] <= 0 && $p['approved'] > 0) $w[] = array($name . ': สถานะแล้วเสร็จแต่ยังไม่มียอดเบิกจ่าย', $link);
        if (!$p['unit_id']) $w[] = array($name . ': ไม่ได้ระบุหน่วยงาน', $link);
        if (!$p['fund_id'] || !isset($fidx[$p['fund_id']])) $w[] = array($name . ': ไม่ได้ระบุแหล่งเงิน', $link);
        else {
            $t = $fidx[$p['fund_id']]['top_id'];
            if (!isset($useByTop[$t])) $useByTop[$t] = 0;
            $useByTop[$t] += $p['approved'];
        }
        if (trim($p['quarters']) === '') $w[] = array($name . ': ไม่ได้ระบุไตรมาสที่ดำเนินการ', $link);
        $qs = (float)$p['spent_q1'] + (float)$p['spent_q2'] + (float)$p['spent_q3'] + (float)$p['spent_q4'];
        if ($qs > 0 && abs($qs - $p['spent']) > 0.004) $w[] = array($name . ': ยอดเบิกจ่ายรายไตรมาสรวม ' . bp_money($qs) . ' ไม่เท่ากับยอดเบิกจ่ายรวม ' . bp_money($p['spent']), $link);
    }
    foreach ($funds['funds'] as $f) {
        $cap = $f['estimate'] + $f['carry_in'];
        if (isset($useByTop[$f['id']]) && $cap > 0 && $useByTop[$f['id']] > $cap + 0.004) {
            $w[] = array('โครงการที่ใช้' . $f['name'] . ' อนุมัติรวม ' . bp_money($useByTop[$f['id']]) . ' บาท เกินประมาณการ + ยกมา ' . bp_money($cap) . ' บาท', 'funds.php?id=' . $yid);
        }
    }
    foreach (bp_rows('SELECT * FROM ' . bp_t('procure') . ' WHERE year_id=' . $yid) as $r) {
        $link = 'procure_item.php?id=' . (int)$r['id'];
        if ($r['status'] == 'done' && ((float)$r['contract_amount'] <= 0 || trim($r['vendor']) === '')) $w[] = array('จัดซื้อ "' . $r['title'] . '": ดำเนินการแล้วแต่ยังไม่กรอกผู้ได้รับคัดเลือก/ราคาที่ตกลง', $link);
        if ((float)$r['budget'] > 0 && (float)$r['contract_amount'] > (float)$r['budget'] + 0.004) $w[] = array('จัดซื้อ "' . $r['title'] . '": ราคาที่ตกลงสูงกว่าวงเงินงบประมาณ', $link);
    }
    return $w;
}
/** เวลาแก้ไขข้อมูลล่าสุดของปี (ปี/โครงการ/จัดซื้อ/รายงาน) */
function bp_year_updated($yid)
{
    $yid = (int)$yid;
    $r = bp_row('SELECT GREATEST(IFNULL((SELECT updated_at FROM ' . bp_t('year') . ' WHERE id=' . $yid . "),'1970-01-01'),"
        . 'IFNULL((SELECT MAX(updated_at) FROM ' . bp_t('project') . ' WHERE year_id=' . $yid . "),'1970-01-01'),"
        . 'IFNULL((SELECT MAX(updated_at) FROM ' . bp_t('procure') . ' WHERE year_id=' . $yid . "),'1970-01-01'),"
        . 'IFNULL((SELECT MAX(updated_at) FROM ' . bp_t('report') . ' WHERE year_id=' . $yid . "),'1970-01-01')) t");
    return $r && $r['t'] > '1971' ? $r['t'] : '';
}
/** บันทึกลง log แล้วแสดงข้อความสำเร็จ */
function bp_done($action, $msg, $type = 'ok')
{
    bp_log($action, $msg);
    bp_flash($msg, $type);
}
/** เป้าหมายการเบิกจ่ายสะสม (%) ไตรมาส 1–4 */
function bp_disb_target()
{
    $t = array_map('intval', explode(',', bp_setting('disb_target', '30,52,75,100')));
    return count($t) == 4 ? $t : array(30, 52, 75, 100);
}
/** ยอดเบิกจ่ายสะสมของโครงการถึงสิ้นไตรมาส $q (ไม่มีข้อมูลรายไตรมาส = ใช้ยอดรวม เมื่อ $q = 4) */
function bp_spent_to($p, $q)
{
    $has = false;
    $s = 0;
    for ($i = 1; $i <= 4; $i++) {
        $v = isset($p['spent_q' . $i]) ? (float)$p['spent_q' . $i] : 0;
        if ($v != 0) $has = true;
        if ($i <= $q) $s += $v;
    }
    if (!$has) return $q >= 4 ? (float)$p['spent'] : 0;
    return round($s, 2);
}
function bp_project_files($pid)
{
    return bp_rows('SELECT * FROM ' . bp_t('project_file') . ' WHERE project_id=' . (int)$pid . ' ORDER BY kind DESC, id');
}

// ---------- รายงานรอบ 6 / 12 เดือน ----------
/** ไตรมาสล่าสุดที่จบแล้วของปีงบ (0 = ยังไม่จบไตรมาสแรก · 4 = จบปีแล้ว) */
function bp_fy_quarter_done($yearBE, $now = null)
{
    $now = $now === null ? time() : $now;
    $y = (int)$yearBE - 544; // ค.ศ. ของ 1 ต.ค.
    $ends = array(1 => mktime(0, 0, 0, 1, 1, $y + 1), 2 => mktime(0, 0, 0, 4, 1, $y + 1), 3 => mktime(0, 0, 0, 7, 1, $y + 1), 4 => mktime(0, 0, 0, 10, 1, $y + 1));
    $d = 0;
    foreach ($ends as $q => $t) if ($now >= $t) $d = $q;
    return $d;
}
/** ช่วงวันที่ของรอบรายงาน: 6 = 1 ต.ค.–31 มี.ค. · 12 = 1 ต.ค.–30 ก.ย. */
function bp_period_label($yearBE, $period)
{
    $y = (int)$yearBE;
    return '1 ตุลาคม ' . ($y - 1) . ' – ' . ($period == 6 ? '31 มีนาคม ' . $y : '30 กันยายน ' . $y);
}
/** สรุปการเบิกจ่ายถึงสิ้นรอบ ($period = 6 หรือ 12) */
function bp_period_data($yid, $yearBE, $period)
{
    $lastQ = $period == 6 ? 2 : 4;
    $units = bp_units();
    $funds = bp_funds($yid);
    $fidx = bp_fund_index($funds);
    $o = array('projects' => array(), 'approved' => 0, 'spent' => 0, 'n' => 0, 'n_done' => 0, 'q' => array(1 => 0, 2 => 0, 3 => 0, 4 => 0), 'has_q' => false,
        'by_fund' => array(), 'by_div' => array(), 'last_q' => $lastQ);
    foreach (bp_projects($yid, true) as $p) {
        if ($p['status'] == 'reserve') continue;
        $sp = bp_spent_to($p, $lastQ);
        for ($i = 1; $i <= 4; $i++) {
            $o['q'][$i] += (float)$p['spent_q' . $i];
            if ((float)$p['spent_q' . $i] != 0) $o['has_q'] = true;
        }
        $p['spent_period'] = $sp;
        $o['projects'][] = $p;
        $o['n']++;
        if ($p['status'] == 'done') $o['n_done']++;
        $o['approved'] += $p['approved'];
        $o['spent'] += $sp;
        $fk = $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$p['fund_id']]['top_id'] : 0;
        $fn = $fk ? null : 'ไม่ระบุแหล่งเงิน';
        if ($fk) foreach ($funds['funds'] as $f) if ($f['id'] == $fk) $fn = $f['name'];
        if (!isset($o['by_fund'][$fn])) $o['by_fund'][$fn] = array('approved' => 0, 'spent' => 0, 'n' => 0);
        $o['by_fund'][$fn]['approved'] += $p['approved'];
        $o['by_fund'][$fn]['spent'] += $sp;
        $o['by_fund'][$fn]['n']++;
        $dn = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['top'] : 'ไม่ระบุหน่วยงาน';
        if (!isset($o['by_div'][$dn])) $o['by_div'][$dn] = array('approved' => 0, 'spent' => 0, 'n' => 0);
        $o['by_div'][$dn]['approved'] += $p['approved'];
        $o['by_div'][$dn]['spent'] += $sp;
        $o['by_div'][$dn]['n']++;
    }
    return $o;
}
/** ร้อยละเบิกจ่ายสะสมรายไตรมาส (null = ไตรมาสที่ยังไม่จบ หรือไม่มีข้อมูลรายไตรมาส) */
function bp_cum_pct($qSpent, $approved, $doneQ)
{
    $o = array();
    $c = 0;
    for ($i = 1; $i <= 4; $i++) {
        $c += $qSpent[$i];
        $o[] = ($i <= $doneQ && $approved > 0) ? round($c * 100 / $approved, 2) : null;
    }
    return $o;
}
function bp_report_text($yid, $period)
{
    return bp_row('SELECT * FROM ' . bp_t('report') . ' WHERE year_id=' . (int)$yid . ' AND period=' . (int)$period);
}

// ---------- จัดซื้อจัดจ้าง ----------
function bp_procure_methods()
{
    return array('เฉพาะเจาะจง', 'ประกาศเชิญชวนทั่วไป (e-bidding)', 'ประกาศเชิญชวนทั่วไป (e-market)', 'คัดเลือก');
}
function bp_procure_statuses()
{
    return array('plan' => 'ตามแผน', 'doing' => 'อยู่ระหว่างดำเนินการ', 'done' => 'ทำสัญญา/ข้อตกลงแล้ว', 'cancel' => 'ยกเลิก');
}
function bp_procures($yid, $public = false)
{
    return bp_rows('SELECT * FROM ' . bp_t('procure') . ' WHERE year_id=' . (int)$yid . ($public ? " AND status<>'cancel'" : '') . ' ORDER BY seq, id');
}
/** แปลงวันที่จาก Excel/คนพิมพ์ → 'YYYY-MM-DD' (ค.ศ.) · รับ 15/01/2569, 15/1/2026, 2026-01-15, 15 ม.ค. 2569, เลขวันที่ของ Excel */
function bp_parse_date($s)
{
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('/^\d{5}(\.\d+)?$/', $s)) return date('Y-m-d', ((int)$s - 25569) * 86400); // เลขวันที่ของ Excel
    $d = $m = $y = 0;
    if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $x)) { $y = (int)$x[1]; $m = (int)$x[2]; $d = (int)$x[3]; }
    elseif (preg_match('/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})$/', $s, $x)) { $d = (int)$x[1]; $m = (int)$x[2]; $y = (int)$x[3]; }
    elseif (preg_match('/^(\d{1,2})\s*(\S+)\s*(\d{2,4})$/u', $s, $x)) { $d = (int)$x[1]; $m = bp_thai_month_no($x[2]); $y = (int)$x[3]; }
    if ($y > 0 && $y < 100) $y += $y > 40 ? 2500 : 2000; // 69 → 2569 · 26 → 2026
    if ($y > 2400) $y -= 543;
    return ($y && $m >= 1 && $m <= 12 && $d >= 1 && $d <= 31 && checkdate($m, $d, $y)) ? sprintf('%04d-%02d-%02d', $y, $m, $d) : '';
}
function bp_thai_month_no($s)
{
    $s = trim(str_replace(array('.', ' '), '', (string)$s));
    foreach (array(bp_thai_months(true), bp_thai_months(false)) as $list) {
        foreach ($list as $i => $n) if ($s === str_replace('.', '', $n)) return $i;
    }
    return (int)$s;
}
/** เดือนที่คาดว่าจะจัดหา → 'YYYY-MM' · รับ ม.ค. 2569, ม.ค.69, 01/2569, 2026-01, วันที่เต็ม · ไม่มีปี = เดาจากปีงบ */
function bp_parse_month($s, $fyBE = 0)
{
    $s = trim((string)$s);
    if ($s === '') return '';
    if (preg_match('/^(\d{4})-(\d{2})$/', $s)) return $s;
    $full = bp_parse_date($s);
    if ($full !== '') return substr($full, 0, 7);
    $m = 0; $y = 0;
    if (preg_match('/^(\d{1,2})[\/\-.](\d{2,4})$/', $s, $x)) { $m = (int)$x[1]; $y = (int)$x[2]; }
    elseif (preg_match('/^([^\d\s]+)\s*(\d{2,4})?$/u', $s, $x)) { $m = bp_thai_month_no($x[1]); $y = isset($x[2]) ? (int)$x[2] : 0; }
    if ($m < 1 || $m > 12) return '';
    if (!$y && $fyBE) $y = $m >= 10 ? $fyBE - 1 : $fyBE;
    if ($y > 0 && $y < 100) $y += $y > 40 ? 2500 : 2000;
    if ($y > 2400) $y -= 543;
    return $y ? sprintf('%04d-%02d', $y, $m) : '';
}

// ---------- ปรับแผน / โอนงบ ----------
/** รายการโอนงบของปี (เรียงตามวันที่) พร้อมชื่อ/รหัสโครงการต้นทาง-ปลายทาง */
function bp_transfers($yid, $pid = 0)
{
    return bp_rows('SELECT t.*, a.title from_title, a.code from_code, b.title to_title, b.code to_code FROM ' . bp_t('transfer') . ' t'
        . ' LEFT JOIN ' . bp_t('project') . ' a ON a.id=t.from_id LEFT JOIN ' . bp_t('project') . ' b ON b.id=t.to_id'
        . ' WHERE t.year_id=' . (int)$yid . ($pid ? ' AND (t.from_id=' . (int)$pid . ' OR t.to_id=' . (int)$pid . ')' : '') . ' ORDER BY t.doc_date, t.id');
}
/** ยอดที่เปลี่ยนไปจากการโอนของแต่ละโครงการ: array(project_id => +รับเข้า −โอนออก) · งบเดิม = งบอนุมัติปัจจุบัน − ค่านี้ */
function bp_transfer_net($yid)
{
    $net = array();
    foreach (bp_rows('SELECT from_id,to_id,amount FROM ' . bp_t('transfer') . ' WHERE year_id=' . (int)$yid) as $t) {
        if ($t['from_id']) $net[$t['from_id']] = (isset($net[$t['from_id']]) ? $net[$t['from_id']] : 0) - (float)$t['amount'];
        if ($t['to_id']) $net[$t['to_id']] = (isset($net[$t['to_id']]) ? $net[$t['to_id']] : 0) + (float)$t['amount'];
    }
    return $net;
}
/** ชื่อฝั่งต้นทาง/ปลายทางของรายการโอน */
function bp_transfer_side($t, $side)
{
    if ($t[$side . '_id']) return trim($t[$side . '_code'] . ' ' . $t[$side . '_title']);
    return $side == 'from' ? 'งบที่ยังไม่จัดสรร / เงินเหลือจ่าย' : 'คืนเป็นเงินเหลือจ่าย';
}

// ---------- ลิงก์เต็ม (ใช้หน้า ITA) ----------
function bp_abs_url($path = '')
{
    $host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', $_SERVER['HTTP_HOST']) : 'localhost';
    return (bp_https() ? 'https' : 'http') . '://' . $host . bp_url($path);
}

// ---------- ย่อรูปที่อัปโหลด ----------
/**
 * ย่อรูปให้ด้านยาวไม่เกิน $max px และบันทึกใหม่ (ตัดข้อมูล EXIF รวมพิกัด GPS ออก, หมุนตามการถือกล้อง)
 * jpg/webp บันทึกเป็นชนิดเดิม · png ที่ใหญ่และไม่มีพื้นโปร่งใส → jpg · gif ไม่แตะ (อาจเป็นภาพเคลื่อนไหว)
 * คืนค่านามสกุลใหม่ ('' = ไม่ได้เปลี่ยนไฟล์)
 */
function bp_image_shrink($path, $ext, $max = 1920)
{
    if ($ext == 'gif' || !function_exists('imagecreatetruecolor')) return '';
    $info = @getimagesize($path);
    if (!$info) return '';
    $w = (int)$info[0]; $h = (int)$info[1];
    if ($w < 1 || $h < 1 || $w * $h > 60000000) return '';
    $size = (int)@filesize($path);
    $big = max($w, $h) > $max;
    // png เล็ก/ไม่ต้องย่อ ปล่อยไว้ · jpg เขียนใหม่เสมอเพื่อลบพิกัด GPS ในข้อมูล EXIF
    if ($ext == 'png' && !$big && $size < 1500000) return '';
    if ($ext == 'webp' && (!$big || !function_exists('imagecreatefromwebp') || !function_exists('imagewebp'))) return '';
    $need = $w * $h * 5 + 30000000;
    $lim = bp_ini_bytes_fn(ini_get('memory_limit'));
    if ($lim > 0 && $lim < $need + memory_get_usage()) @ini_set('memory_limit', (string)(int)ceil(($need + memory_get_usage()) / 1048576 + 16) . 'M');
    if ($ext == 'jpg') $src = function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false;
    elseif ($ext == 'png') $src = function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false;
    else $src = @imagecreatefromwebp($path);
    if (!$src) return '';
    // หมุนตาม EXIF Orientation (รูปจากมือถือ)
    $rot = 0;
    if ($ext == 'jpg' && function_exists('exif_read_data')) {
        $ex = @exif_read_data($path);
        $o = $ex && isset($ex['Orientation']) ? (int)$ex['Orientation'] : 1;
        if ($o == 3) $rot = 180; elseif ($o == 6) $rot = -90; elseif ($o == 8) $rot = 90;
    }
    $alpha = false;
    if ($ext == 'png') {
        // มีพื้นโปร่งใสจริงไหม (สุ่มตรวจ) — มี = เก็บเป็น png
        $stepX = max(1, (int)($w / 40)); $stepY = max(1, (int)($h / 40));
        for ($y = 0; $y < $h && !$alpha; $y += $stepY) for ($x = 0; $x < $w; $x += $stepX) { $c = imagecolorsforindex($src, imagecolorat($src, $x, $y)); if ($c['alpha'] > 0) { $alpha = true; break; } }
    }
    $r = $big ? $max / max($w, $h) : 1;
    $nw = max(1, (int)round($w * $r)); $nh = max(1, (int)round($h * $r));
    $dst = imagecreatetruecolor($nw, $nh);
    if ($alpha) { imagealphablending($dst, false); imagesavealpha($dst, true); }
    else imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    if ($rot && function_exists('imagerotate')) { $t = imagerotate($dst, $rot, 0); if ($t) { imagedestroy($dst); $dst = $t; } }
    $out = $alpha ? 'png' : ($ext == 'webp' ? 'webp' : 'jpg');
    $tmp = $path . '.tmp';
    $ok = $out == 'png' ? imagepng($dst, $tmp, 8) : ($out == 'webp' ? imagewebp($dst, $tmp, 82) : imagejpeg($dst, $tmp, 82));
    imagedestroy($dst);
    if (!$ok || !is_file($tmp)) { @unlink($tmp); return ''; }
    // ไม่ได้ย่อแล้วไฟล์ใหม่ใหญ่กว่าเดิม (png/jpg ที่บีบมาดีแล้ว) — ใช้ของเดิม ยกเว้น jpg ที่ต้องลบ EXIF
    if (!$big && $out == $ext && $ext != 'jpg' && filesize($tmp) >= $size) { @unlink($tmp); return ''; }
    if (!@rename($tmp, $path)) { if (!@copy($tmp, $path)) { @unlink($tmp); return ''; } @unlink($tmp); }
    return $out;
}
function bp_ini_bytes_fn($v)
{
    $v = trim((string)$v);
    if ($v === '' || $v === '-1') return -1;
    $n = (float)$v;
    $u = strtolower(substr($v, -1));
    if ($u == 'g') $n *= 1073741824; elseif ($u == 'm') $n *= 1048576; elseif ($u == 'k') $n *= 1024;
    return (int)$n;
}
