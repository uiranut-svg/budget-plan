<?php
// ส่งออก/นำเข้าข้อมูลทั้งปีเป็นไฟล์ Excel (แผ่นงาน: งบตามหมวด · แหล่งเงิน · โครงการ · จัดซื้อจัดจ้าง)
// ไฟล์ที่ส่งออก = แม่แบบนำเข้า (หัวตารางแถวแรกต้องไม่เปลี่ยนชื่อ)
require_once BP_ROOT . '/inc/xlsx.php';

function bp_excel_heads()
{
    return array(
        'งบตามหมวด' => array('หมวด', 'รายการ', 'หน่วยงาน', 'จำนวนเงิน', 'หมายเหตุ'),
        'แหล่งเงิน' => array('แหล่งเงินหลัก', 'แหล่งเงินย่อย', 'ประมาณการรายรับ', 'ยกมา', 'รับจริง', 'หมายเหตุ'),
        'โครงการ' => array('รหัส', 'ชื่อโครงการ', 'หน่วยงาน', 'แหล่งเงิน', 'หมวดรายจ่าย', 'งบที่เสนอ', 'งบอนุมัติ', 'เบิกจ่าย ไตรมาส 1', 'เบิกจ่าย ไตรมาส 2',
            'เบิกจ่าย ไตรมาส 3', 'เบิกจ่าย ไตรมาส 4', 'เบิกจ่ายรวม', 'สถานะ', 'ไตรมาสที่ดำเนินการ', 'ผู้รับผิดชอบ', 'ผลสำเร็จ (%)', 'ผลการดำเนินงาน', 'ปัญหาอุปสรรค', 'วัตถุประสงค์', 'เป้าหมาย'),
        'จัดซื้อจัดจ้าง' => array('ลำดับ', 'รายการ', 'รหัสโครงการ', 'วงเงินงบประมาณ', 'ราคากลาง', 'วิธีจัดซื้อจัดจ้าง', 'เดือนที่คาดว่าจะจัดหา', 'สถานะ', 'ผู้ได้รับคัดเลือก',
            'ราคาที่ตกลง', 'เลขที่สัญญา', 'วันที่สัญญา', 'เหตุผลที่คัดเลือก', 'ผู้เสนอราคาและราคา'),
    );
}

/** ข้อมูลทั้งปีเป็นแผ่นงาน · $public = ไม่รวมโครงการ/รายการจัดซื้อที่ยกเลิก */
function bp_excel_sheets($yid, $public = false)
{
    $H = bp_excel_heads();
    $units = bp_units();
    $out = array();
    // งบตามหมวด
    $rows = array($H['งบตามหมวด']);
    $data = bp_year_data($yid);
    foreach ($data['categories'] as $c) {
        if (!$c['items']) $rows[] = array($c['name'], '', '', (float)$c['amount'], '');
        foreach ($c['items'] as $it) $rows[] = array($c['name'], $it['name'], $it['department'], (float)$it['amount'], $it['note']);
    }
    $out['งบตามหมวด'] = $rows;
    // แหล่งเงิน
    $rows = array($H['แหล่งเงิน']);
    $funds = bp_funds($yid);
    foreach ($funds['funds'] as $f) {
        if (!$f['children']) $rows[] = array($f['name'], '', (float)$f['estimate'], (float)$f['carry_in'], (float)$f['received'], $f['note']);
        foreach ($f['children'] as $ch) $rows[] = array($f['name'], $ch['name'], (float)$ch['estimate'], (float)$ch['carry_in'], (float)$ch['received'], $ch['note']);
    }
    $out['แหล่งเงิน'] = $rows;
    // โครงการ
    $rows = array($H['โครงการ']);
    $fidx = bp_fund_index($funds);
    $st = bp_project_statuses();
    $codes = array();
    foreach (bp_projects($yid, $public) as $p) {
        $codes[$p['id']] = $p['code'];
        $rows[] = array($p['code'], $p['title'], $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : '',
            $p['fund_id'] && isset($fidx[$p['fund_id']]) ? $fidx[$p['fund_id']]['path'] : '', $p['expense'], (float)$p['requested'], (float)$p['approved'],
            (float)$p['spent_q1'], (float)$p['spent_q2'], (float)$p['spent_q3'], (float)$p['spent_q4'], (float)$p['spent'],
            isset($st[$p['status']]) ? $st[$p['status']] : $p['status'], $p['quarters'], $p['owner'], $p['result_pct'] === null ? '' : (int)$p['result_pct'],
            (string)$p['result'], (string)$p['problems'], (string)$p['objective'], (string)$p['target']);
    }
    $out['โครงการ'] = $rows;
    // จัดซื้อจัดจ้าง
    $rows = array($H['จัดซื้อจัดจ้าง']);
    $pst = bp_procure_statuses();
    foreach (bp_procures($yid, $public) as $r) {
        $rows[] = array((int)$r['seq'], $r['title'], $r['project_id'] && isset($codes[$r['project_id']]) ? $codes[$r['project_id']] : '', (float)$r['budget'], (float)$r['ref_price'],
            $r['method'], bp_thai_month($r['plan_month']), $pst[$r['status']], $r['vendor'], (float)$r['contract_amount'], $r['contract_no'],
            $r['contract_date'] ? date('d/m/', strtotime($r['contract_date'])) . (date('Y', strtotime($r['contract_date'])) + 543) : '', $r['reason'], (string)$r['bidders']);
    }
    $out['จัดซื้อจัดจ้าง'] = $rows;
    return $out;
}

function bp_excel_send($yid, $year, $public)
{
    $bin = BpXlsx::build(bp_excel_sheets($yid, $public));
    $name = 'แผนงานงบประมาณ-' . (int)$year . ($public ? '' : '-ทั้งหมด') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="budget-' . (int)$year . '.xlsx"; filename*=UTF-8\'\'' . rawurlencode($name));
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: no-store');
    echo $bin;
    exit;
}

// ---------------------------------------------------------------- นำเข้า
// รับทั้งไฟล์แม่แบบของระบบ และไฟล์แผนที่วิทยาลัยทำเอง: หัวตารางไม่ต้องอยู่แถวแรก, หัว 2 ชั้น (เซลล์ผสาน), ชื่อหัวหลายแบบ,
// เครื่องหมาย ✓ ในช่องไตรมาส/เดือน, งบแยกคอลัมน์ตามแหล่งเงิน, แถวหัวข้อฝ่าย และแถว "รวม"

/** ชื่อหัวคอลัมน์อื่นที่มีความหมายเดียวกัน (ต่อแผ่นงาน) */
function bp_excel_aliases()
{
    return array(
        'งบตามหมวด' => array(
            'หมวด' => array('หมวดงบประมาณ', 'หมวดเงิน'),
            'รายการ' => array('รายการงบประมาณ'),
            'หน่วยงาน' => array('หน่วยงานที่รับผิดชอบ'),
            'จำนวนเงิน' => array('งบประมาณ'),
        ),
        'แหล่งเงิน' => array(
            'แหล่งเงินหลัก' => array('แหล่งเงิน', 'ประเภทเงิน', 'แหล่งงบประมาณ', 'ประเภทรายรับ', 'รายการ'),
            'แหล่งเงินย่อย' => array('ประเภทย่อย'),
            'ประมาณการรายรับ' => array('ประมาณการ', 'ประมาณการรายได้', 'จำนวนเงิน'),
            'ยกมา' => array('เงินคงเหลือยกมา', 'คงเหลือยกมา', 'ยอดยกมา', 'เงินยกมา'),
            'รับจริง' => array('รายรับจริง', 'ได้รับจริง', 'รับจริงสะสม'),
        ),
        'โครงการ' => array(
            'รหัส' => array('รหัสโครงการ'),
            'ชื่อโครงการ' => array('โครงการ', 'โครงการ/กิจกรรม', 'ชื่อโครงการ/กิจกรรม', 'กิจกรรม/โครงการ', 'โครงการ/งาน', 'ชื่อโครงการ/งาน/กิจกรรม', 'รายการโครงการ', 'โครงการ/กิจกรรม/งาน'),
            'หน่วยงาน' => array('หน่วยงานที่รับผิดชอบ', 'หน่วยงานรับผิดชอบ', 'ฝ่าย/งาน', 'งาน/แผนก', 'แผนก/งาน', 'หน่วยงาน/แผนก', 'งาน/แผนกวิชา'),
            'แหล่งเงิน' => array('แหล่งงบประมาณ', 'ประเภทเงิน', 'แหล่งเงินงบประมาณ', 'ประเภทงบประมาณ'),
            'หมวดรายจ่าย' => array('หมวดงบประมาณ', 'ประเภทรายจ่าย', 'หมวดเงิน', 'งบรายจ่าย'),
            'งบที่เสนอ' => array('งบประมาณที่เสนอ', 'งบประมาณที่ขอ', 'เสนอขอ', 'งบที่ขอ'),
            'งบอนุมัติ' => array('งบประมาณ', 'รวมงบประมาณ', 'งบประมาณที่ได้รับอนุมัติ', 'งบประมาณอนุมัติ', 'จำนวนเงิน', 'รวม', 'รวมเงิน', 'งบประมาณทั้งสิ้น', 'งบประมาณที่ได้รับ', 'วงเงิน'),
            'เบิกจ่ายรวม' => array('เบิกจ่าย', 'ใช้จริง', 'ผลการเบิกจ่าย', 'เบิกจ่ายจริง'),
            'สถานะ' => array('สถานะโครงการ'),
            'ไตรมาสที่ดำเนินการ' => array('ระยะเวลาดำเนินการ', 'ไตรมาส', 'ช่วงเวลาดำเนินการ', 'ระยะเวลา'),
            'ผู้รับผิดชอบ' => array('ผู้รับผิดชอบโครงการ', 'ผู้รับผิดชอบหลัก'),
            'ผลสำเร็จ (%)' => array('ร้อยละความสำเร็จ', 'ผลสำเร็จ'),
            'เป้าหมาย' => array('เป้าหมาย/ตัวชี้วัด', 'ตัวชี้วัด', 'เป้าหมายเชิงปริมาณ', 'ตัวชี้วัดความสำเร็จ'),
        ),
        'จัดซื้อจัดจ้าง' => array(
            'ลำดับ' => array('ที่', 'ลำดับที่'),
            'รายการ' => array('รายการ/งานที่จะจัดซื้อจัดจ้าง', 'รายการพัสดุ', 'ชื่อรายการ', 'งานที่จะจัดซื้อจัดจ้าง', 'รายการที่จัดซื้อจัดจ้าง', 'งานที่จัดซื้อหรือจัดจ้าง', 'งานที่ซื้อหรือจ้าง', 'รายการที่จะจัดซื้อจัดจ้าง'),
            'วงเงินงบประมาณ' => array('วงเงิน', 'งบประมาณ', 'จำนวนเงิน', 'วงเงินที่จะซื้อหรือจ้าง', 'วงเงินที่จะจัดซื้อจัดจ้าง'),
            'วิธีจัดซื้อจัดจ้าง' => array('วิธีการจัดหา', 'วิธีจัดหา', 'วิธีการจัดซื้อจัดจ้าง', 'วิธีซื้อหรือจ้าง', 'วิธีจัดซื้อ', 'วิธีการซื้อหรือจ้าง'),
            'เดือนที่คาดว่าจะจัดหา' => array('กำหนดเวลาที่ต้องการใช้', 'ระยะเวลาจัดหา', 'ช่วงเวลาที่คาดว่าจะจัดหา', 'คาดว่าจะประกาศจัดซื้อจัดจ้าง', 'คาดว่าจะประกาศจัดซื้อจัดจ้าง(เดือน/ปี)', 'เดือน', 'กำหนดเวลา'),
            'ผู้ได้รับคัดเลือก' => array('ผู้ได้รับการคัดเลือก', 'ผู้รับจ้าง', 'ผู้ขาย', 'ผู้ได้รับคัดเลือกและราคาที่ตกลง'),
            'ราคาที่ตกลง' => array('ราคาที่ตกลงซื้อหรือจ้าง'),
            'ชื่อโครงการ' => array('โครงการ'),
        ),
    );
}
/** หัวที่ต้องมีอย่างน้อย (ใช้หาแผ่นงาน/แถวหัวตาราง) */
function bp_excel_keys()
{
    return array('งบตามหมวด' => 'หมวด', 'แหล่งเงิน' => 'แหล่งเงินหลัก', 'โครงการ' => 'ชื่อโครงการ', 'จัดซื้อจัดจ้าง' => 'รายการ');
}
function bp_excel_norm($s)
{
    return preg_replace('/\s+/u', '', mb_strtolower(str_replace(array('>', '/'), '›', (string)$s), 'UTF-8'));
}
/** ชื่อหัวคอลัมน์แบบเทียบได้ — ตัดหน่วย (บาท) ออก */
function bp_excel_hnorm($s)
{
    $n = bp_excel_norm($s);
    $n = preg_replace('/\((หน่วย:?)?บาท\)|บาท$|\(%\)$/u', '', $n);
    return $n;
}
function bp_excel_num($v)
{
    return $v === null || $v === '' ? null : bp_parse_amount($v);
}
/** ไตรมาสจากหัวคอลัมน์ "ไตรมาส 1" / "ต.ค.-ธ.ค. 69" / "พ.ย. 69" · 0 = ไม่ใช่ */
function bp_excel_quarter_head($s)
{
    $n = bp_excel_norm($s);
    if ($n === '' || mb_strpos($n, 'เบิก') !== false) return 0;
    if (preg_match('/^(ไตรมาส(ที่)?|q)([1-4])/u', $n, $m)) return (int)$m[3];
    $ranges = array(1 => 'ต\.?ค\.?[-–]ธ\.?ค', 2 => 'ม\.?ค\.?[-–]มี\.?ค', 3 => 'เม\.?ย\.?[-–]มิ\.?ย', 4 => 'ก\.?ค\.?[-–]ก\.?ย');
    foreach ($ranges as $q => $re) if (preg_match('/^' . $re . '/u', $n)) return $q;
    if (preg_match('/^([^\d]+?)\.?(\d{2,4})?$/u', $n, $m)) {
        $mo = bp_thai_month_no($m[1]);
        if ($mo >= 1 && $mo <= 12 && !ctype_digit($m[1])) return $mo >= 10 ? 1 : (int)(($mo - 1) / 3) + 2;
    }
    return 0;
}
/** เครื่องหมายในช่องไตรมาส/เดือน (✓, /, x, ตัวเลข) ถือว่าดำเนินการ */
function bp_excel_marked($v)
{
    $v = trim((string)$v);
    return $v !== '' && !in_array($v, array('0', '-', '–', '0.00'), true);
}
/** คีย์เทียบชื่อหน่วยงาน: ตัด ฝ่าย/แผนกวิชา/แผนก/งาน ข้างหน้า */
function bp_excel_unit_key($s)
{
    $n = bp_excel_norm($s);
    return preg_replace('/^(ฝ่าย|แผนกวิชา|แผนก|งาน)/u', '', $n);
}
function bp_excel_fund_key($s)
{
    return preg_replace('/^เงิน/u', '', bp_excel_norm($s));
}
/** หา id จากตาราง key=>id: ตรงกันก่อน แล้วค่อยดูว่าขึ้นต้นด้วยกัน (ต้องเจอรายการเดียว) */
function bp_excel_fuzzy($key, $map)
{
    if ($key === '') return 0;
    if (isset($map[$key])) return $map[$key];
    $hit = array();
    foreach ($map as $k => $id) if ($k !== '' && (mb_strpos($k, $key) === 0 || mb_strpos($key, $k) === 0)) $hit[$id] = 1;
    return count($hit) == 1 ? key($hit) : 0;
}
function bp_excel_head_lookup($sheetKey)
{
    $H = bp_excel_heads();
    $A = bp_excel_aliases();
    $heads = isset($H[$sheetKey]) ? $H[$sheetKey] : array();
    if ($sheetKey == 'จัดซื้อจัดจ้าง') { $heads[] = 'ชื่อโครงการ'; $heads[] = 'หมายเหตุ'; }
    $look = array();
    foreach ($heads as $h) $look[bp_excel_hnorm($h)] = $h;
    if (isset($A[$sheetKey])) foreach ($A[$sheetKey] as $h => $list) foreach ($list as $a) if (!isset($look[bp_excel_hnorm($a)])) $look[bp_excel_hnorm($a)] = $h;
    return array($heads, $look);
}

/**
 * หาตารางในแผ่นงาน: ลองทุกแถวใน 20 แถวแรก ทั้งหัวชั้นเดียวและหัว 2 ชั้น แล้วเลือกแบบที่จับคู่หัวได้มากที่สุด
 * $fundKeys = array(คีย์ชื่อแหล่งเงิน => id) สำหรับคอลัมน์งบแยกตามแหล่งเงิน (เฉพาะแผ่นโครงการ)
 * คืนค่า array('score', 'pos' => หัว=>คอลัมน์, 'q' => คอลัมน์=>ไตรมาส, 'fund' => คอลัมน์=>id, 'start' => แถวแรกของข้อมูล) หรือ null
 */
function bp_excel_table($sheet, $sheetKey, $fundKeys = array())
{
    list(, $look) = bp_excel_head_lookup($sheetKey);
    $keys = bp_excel_keys();
    $need = $keys[$sheetKey];
    $best = null;
    $n = min(20, count($sheet));
    for ($r = 0; $r < $n; $r++) {
        foreach (array(false, true) as $two) {
            if ($two && $r + 1 >= count($sheet)) continue;
            $top = $sheet[$r];
            $sub = $two ? $sheet[$r + 1] : array();
            $w = max(count($top), count($sub));
            $pos = array(); $qc = array(); $fc = array();
            $last = '';
            for ($c = 0; $c < $w; $c++) {
                $t = isset($top[$c]) ? trim($top[$c]) : '';
                $s = isset($sub[$c]) ? trim($sub[$c]) : '';
                if ($t !== '') $last = $t; elseif ($s !== '') $t = $last; // หัวบนที่ผสานเซลล์
                $cands = $s !== '' ? array($t . $s, $s) : array($t);
                foreach ($cands as $txt) {
                    if ($txt === '') continue;
                    $k = bp_excel_hnorm($txt);
                    if (isset($look[$k]) && !isset($pos[$look[$k]])) { $pos[$look[$k]] = $c; break; }
                    $q = bp_excel_quarter_head($txt);
                    if ($q && $txt === $s || $q && !$two) { $qc[$c] = $q; break; }
                    if ($fundKeys) { $fid = bp_excel_fuzzy(bp_excel_fund_key($txt), $fundKeys); if ($fid && ($txt === $s || !$two)) { $fc[$c] = $fid; break; } }
                }
            }
            if (!isset($pos[$need])) continue;
            $score = count($pos) + count($qc) + count($fc);
            if (!$best || $score > $best['score']) $best = array('score' => $score, 'pos' => $pos, 'q' => $qc, 'fund' => $fc, 'start' => $r + ($two ? 2 : 1));
        }
    }
    // คอลัมน์ไตรมาสต้องมีอย่างน้อย 2 ช่องถึงจะเชื่อ (กันหัวคอลัมน์บังเอิญเป็นชื่อเดือน)
    if ($best && count($best['q']) < 2) $best['q'] = array();
    return $best && $best['score'] >= 2 ? $best : null;
}
/** แปลงแถวข้อมูลเป็น array(หัว => ค่า) + '_q' (ไตรมาสที่มีเครื่องหมาย) + '_fund' (id => จำนวนเงิน) */
function bp_excel_table_rows($sheet, $sheetKey, $t)
{
    list($heads) = bp_excel_head_lookup($sheetKey);
    $rows = array();
    foreach (array_slice($sheet, $t['start']) as $r) {
        $o = array();
        foreach ($heads as $h) $o[$h] = isset($t['pos'][$h]) && isset($r[$t['pos'][$h]]) ? trim($r[$t['pos'][$h]]) : null;
        $o['_q'] = array();
        foreach ($t['q'] as $c => $q) if (isset($r[$c]) && bp_excel_marked($r[$c])) $o['_q'][$q] = $q;
        $o['_fund'] = array();
        foreach ($t['fund'] as $c => $fid) if (isset($r[$c]) && bp_parse_amount($r[$c]) != 0) $o['_fund'][$fid] = (isset($o['_fund'][$fid]) ? $o['_fund'][$fid] : 0) + bp_parse_amount($r[$c]);
        $rows[] = $o;
    }
    return $rows;
}
/** แถวสรุปยอด "รวม…" */
function bp_excel_is_total($s)
{
    return (bool)preg_match('/^(รวม|ยอดรวม|total)/iu', bp_excel_norm($s));
}
/** ตัดเลขข้อหน้าชื่อ "1. " "2.1) " */
function bp_excel_strip_no($s)
{
    return trim(preg_replace('/^\d+(\.\d+)*[.)]\s*/u', '', trim((string)$s)));
}
/** วิธีจัดซื้อจัดจ้างให้ตรงกับรายการของระบบ */
function bp_excel_method($s)
{
    $n = bp_excel_norm($s);
    if ($n === '') return '';
    if (strpos($n, 'bidding') !== false || mb_strpos($n, 'ประกวดราคา') !== false) return 'ประกาศเชิญชวนทั่วไป (e-bidding)';
    if (strpos($n, 'market') !== false || mb_strpos($n, 'ตลาดอิเล็กทรอนิกส์') !== false) return 'ประกาศเชิญชวนทั่วไป (e-market)';
    if (mb_strpos($n, 'เฉพาะเจาะจง') !== false) return 'เฉพาะเจาะจง';
    if (mb_strpos($n, 'คัดเลือก') !== false) return 'คัดเลือก';
    return mb_substr(trim($s), 0, 64);
}
/** เลือกแผ่นงานของแต่ละประเภท: ชื่อตรงกับแม่แบบก่อน ไม่งั้นแผ่นที่หัวตารางเข้ากันที่สุด · คืนค่า array(ประเภท => array(ชื่อแผ่น, ตาราง)) */
function bp_excel_pick($book, $want, $fundKeys)
{
    $out = array();
    $used = array();
    foreach ($want as $k) if (isset($book[$k])) {
        $t = bp_excel_table($book[$k], $k, $k == 'โครงการ' ? $fundKeys : array());
        if ($t) { $out[$k] = array($k, $t); $used[$k] = 1; }
    }
    foreach ($want as $k) {
        if (isset($out[$k])) continue;
        $best = null;
        foreach ($book as $name => $sheet) {
            if (isset($used[$name])) continue;
            $t = bp_excel_table($sheet, $k, $k == 'โครงการ' ? $fundKeys : array());
            if (!$t) continue;
            // จัดซื้อจัดจ้างต้องมีคอลัมน์วิธี/วงเงิน · โครงการต้องมีคอลัมน์เงินอย่างน้อย 1 ช่อง (กันสับกับแผ่นอื่น)
            if ($k == 'จัดซื้อจัดจ้าง' && !isset($t['pos']['วิธีจัดซื้อจัดจ้าง'])) continue;
            if ($k == 'โครงการ' && !$t['fund'] && !isset($t['pos']['งบอนุมัติ']) && !isset($t['pos']['งบที่เสนอ'])) continue;
            if ($k == 'แหล่งเงิน' && !isset($t['pos']['ประมาณการรายรับ']) && !isset($t['pos']['ยกมา']) && !isset($t['pos']['รับจริง'])) continue;
            if ($k == 'งบตามหมวด' && !isset($t['pos']['จำนวนเงิน'])) continue;
            if (!$best || $t['score'] > $best[1]['score']) $best = array($name, $t);
        }
        if ($best) { $out[$k] = $best; $used[$best[0]] = 1; }
    }
    return $out;
}

/** นำเข้าแผ่นงานที่เลือก · คืนค่า array of ข้อความผลลัพธ์ (ขึ้นต้น "!" = ผิดพลาด) */
function bp_excel_import($yid, $year, $book, $opts)
{
    $msg = array();
    $now = date('Y-m-d H:i:s');
    $fidx = bp_fund_index(bp_funds($yid));
    $fundKeys = array();
    foreach ($fidx as $fid => $f) {
        $fundKeys[bp_excel_fund_key($f['path'])] = $fid;
        if (!isset($fundKeys[bp_excel_fund_key($f['name'])])) $fundKeys[bp_excel_fund_key($f['name'])] = $fid;
    }
    $names = array('budget' => 'งบตามหมวด', 'funds' => 'แหล่งเงิน', 'projects' => 'โครงการ', 'procure' => 'จัดซื้อจัดจ้าง');
    $want = array();
    foreach ($names as $o => $k) if (!empty($opts[$o])) $want[] = $k;
    // แหล่งเงินต้องเข้าก่อนโครงการ (โครงการอ้างชื่อแหล่งเงิน) — หาแผ่นแหล่งเงินก่อน แล้วหาแผ่นอื่นพร้อมกัน
    $pick = bp_excel_pick($book, $want, $fundKeys);
    foreach ($want as $k) if (!isset($pick[$k])) $msg[] = '!' . $k . ': ไม่พบแผ่นงานที่มีหัวตารางของ' . $k . ' — ข้าม';
    $from = function ($k) use ($pick) { return $pick[$k][0] === $k ? '' : ' (จากแผ่น "' . $pick[$k][0] . '")'; };

    // ---------- งบตามหมวด: แทนที่ทั้งหมด ----------
    if (isset($pick['งบตามหมวด'])) {
        $rows = bp_excel_table_rows($book[$pick['งบตามหมวด'][0]], 'งบตามหมวด', $pick['งบตามหมวด'][1]);
        $colors = array();
        foreach (bp_rows('SELECT name,color FROM ' . bp_t('category') . ' WHERE year_id=' . (int)$yid) as $c) $colors[$c['name']] = $c['color'];
        $cats = array();
        foreach ($rows as $r) {
            if ($r['หมวด'] === '' || $r['หมวด'] === null || bp_excel_is_total($r['หมวด'])) continue;
            $k = bp_excel_strip_no($r['หมวด']);
            if (!isset($cats[$k])) $cats[$k] = array('amount' => 0, 'items' => array());
            if ($r['รายการ'] === '' || $r['รายการ'] === null) $cats[$k]['amount'] += bp_parse_amount($r['จำนวนเงิน']);
            elseif (!bp_excel_is_total($r['รายการ'])) $cats[$k]['items'][] = $r;
        }
        if ($cats) {
            bp_query('START TRANSACTION');
            bp_query('DELETE i FROM ' . bp_t('item') . ' i JOIN ' . bp_t('category') . ' c ON c.id=i.category_id WHERE c.year_id=' . (int)$yid);
            bp_query('DELETE FROM ' . bp_t('category') . ' WHERE year_id=' . (int)$yid);
            $ci = 0; $ni = 0;
            foreach ($cats as $name => $c) {
                bp_query('INSERT INTO ' . bp_t('category') . ' (year_id,name,color,amount,sort) VALUES (' . (int)$yid . ',' . q(mb_substr($name, 0, 255)) . ',' . q(isset($colors[$name]) ? $colors[$name] : bp_palette($ci)) . ',' . $c['amount'] . ',' . $ci . ')');
                $cid = bp_insert_id();
                foreach ($c['items'] as $ii => $it) {
                    bp_query('INSERT INTO ' . bp_t('item') . ' (category_id,name,department,amount,note,sort) VALUES (' . $cid . ',' . q(mb_substr(bp_excel_strip_no($it['รายการ']), 0, 500)) . ',' . q(mb_substr((string)$it['หน่วยงาน'], 0, 255)) . ','
                        . bp_parse_amount($it['จำนวนเงิน']) . ',' . q(mb_substr((string)$it['หมายเหตุ'], 0, 500)) . ',' . $ii . ')');
                    $ni++;
                }
                $ci++;
            }
            bp_query('COMMIT');
            $msg[] = 'งบตามหมวด' . $from('งบตามหมวด') . ': แทนที่ด้วย ' . $ci . ' หมวด ' . $ni . ' รายการ';
        } else $msg[] = 'งบตามหมวด: ไม่มีข้อมูล — ไม่ได้เปลี่ยน';
    }

    // ---------- แหล่งเงิน: อัปเดตยอดตามชื่อ / เพิ่มใหม่ (ไม่ลบของเดิม) ----------
    if (isset($pick['แหล่งเงิน'])) {
        $t = $pick['แหล่งเงิน'][1];
        $rows = bp_excel_table_rows($book[$pick['แหล่งเงิน'][0]], 'แหล่งเงิน', $t);
        $hasSub = isset($t['pos']['แหล่งเงินย่อย']);
        $top = array(); $kid = array(); $sort = 0;
        foreach (bp_rows('SELECT * FROM ' . bp_t('fund') . ' WHERE year_id=' . (int)$yid) as $f) {
            if ($f['parent_id']) $kid[$f['parent_id'] . '|' . bp_excel_norm($f['name'])] = $f['id']; else { $top[bp_excel_norm($f['name'])] = $f['id']; $sort = max($sort, (int)$f['sort'] + 1); }
        }
        $add = 0; $upd = 0; $cur = 0;
        foreach ($rows as $r) {
            $main = trim((string)$r['แหล่งเงินหลัก']);
            if ($main === '' || bp_excel_is_total($main)) continue;
            $child = $hasSub ? trim((string)$r['แหล่งเงินย่อย']) : '';
            // ไม่มีคอลัมน์แหล่งเงินย่อย: บรรทัดที่ขึ้นต้นด้วย - • = แหล่งย่อยของบรรทัดหลักก่อนหน้า
            if (!$hasSub && preg_match('/^[-–•·*]\s*(.+)$/u', $main, $m)) {
                if (!$cur) continue;
                $child = trim($m[1]);
                $id = $cur;
            } else {
                $main = bp_excel_strip_no($main);
                $tn = bp_excel_norm($main);
                if (!isset($top[$tn])) {
                    bp_query('INSERT INTO ' . bp_t('fund') . ' (year_id,parent_id,name,color,sort) VALUES (' . (int)$yid . ',NULL,' . q(mb_substr($main, 0, 255)) . ',' . q(bp_palette($sort)) . ',' . ($sort++) . ')');
                    $top[$tn] = bp_insert_id(); $add++;
                } elseif ($child === '') $upd++;
                $id = $cur = $top[$tn];
            }
            if ($child !== '') {
                $kn = bp_excel_norm($child);
                if (!isset($kid[$id . '|' . $kn])) {
                    bp_query('INSERT INTO ' . bp_t('fund') . ' (year_id,parent_id,name,sort) VALUES (' . (int)$yid . ',' . (int)$id . ',' . q(mb_substr($child, 0, 255)) . ',99)');
                    $kid[$id . '|' . $kn] = bp_insert_id(); $add++;
                } else $upd++;
                $id = $kid[$id . '|' . $kn];
            }
            $set = array();
            foreach (array('ประมาณการรายรับ' => 'estimate', 'ยกมา' => 'carry_in', 'รับจริง' => 'received') as $h => $col) { $v = bp_excel_num($r[$h]); if ($v !== null) $set[] = $col . '=' . $v; }
            if ($r['หมายเหตุ'] !== null) $set[] = 'note=' . q(mb_substr($r['หมายเหตุ'], 0, 500));
            if ($set) bp_query('UPDATE ' . bp_t('fund') . ' SET ' . implode(',', $set) . ' WHERE id=' . (int)$id);
        }
        $msg[] = 'แหล่งเงิน' . $from('แหล่งเงิน') . ': เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' รายการ';
        // แหล่งเงินใหม่ใช้จับคู่กับโครงการได้ทันที
        $fidx = bp_fund_index(bp_funds($yid));
        foreach ($fidx as $fid => $f) {
            if (!isset($fundKeys[bp_excel_fund_key($f['path'])])) $fundKeys[bp_excel_fund_key($f['path'])] = $fid;
            if (!isset($fundKeys[bp_excel_fund_key($f['name'])])) $fundKeys[bp_excel_fund_key($f['name'])] = $fid;
        }
        if (isset($pick['โครงการ'])) { $t2 = bp_excel_table($book[$pick['โครงการ'][0]], 'โครงการ', $fundKeys); if ($t2) $pick['โครงการ'][1] = $t2; }
    }

    // ---------- โครงการ: อัปเดตตามรหัส (ไม่มีรหัส = ตามชื่อ) / เพิ่มใหม่ ----------
    if (isset($pick['โครงการ']) && $pick['โครงการ'][1]) {
        $t = $pick['โครงการ'][1];
        $rows = bp_excel_table_rows($book[$pick['โครงการ'][0]], 'โครงการ', $t);
        // หน่วยงาน: ชื่อเต็ม ("แผนกวิชา" = "แผนก") → ขึ้นต้นตรงกัน → ชื่อที่ตัด ฝ่าย/แผนก/งาน ออก (ใช้เมื่อไม่ซ้ำกับหน่วยอื่น)
        $uv = function ($s) { return preg_replace('/^แผนกวิชา/u', 'แผนก', bp_excel_norm($s)); };
        $unitKeys = array(); $strip = array();
        foreach (bp_units(true) as $u) {
            $unitKeys[$uv($u['name'])] = (int)$u['id'];
            $sk = bp_excel_unit_key($u['name']);
            $strip[$sk] = isset($strip[$sk]) ? -1 : (int)$u['id'];
        }
        $unitOf = function ($s) use ($unitKeys, $strip, $uv) {
            $k = $uv($s);
            if ($k === '') return 0;
            if (isset($unitKeys[$k])) return $unitKeys[$k];
            $id = bp_excel_fuzzy($k, $unitKeys);
            if ($id) return $id;
            $sk = bp_excel_unit_key($s);
            return isset($strip[$sk]) && $strip[$sk] > 0 ? $strip[$sk] : 0;
        };
        $stBy = array();
        foreach (bp_project_statuses() as $k => $v) { $stBy[bp_excel_norm($v)] = $k; $stBy[$k] = $k; }
        $stBy[bp_excel_norm('แล้วเสร็จ')] = 'done'; $stBy[bp_excel_norm('ยกเลิก')] = 'cancel'; $stBy[bp_excel_norm('สำรอง')] = 'reserve'; $stBy[bp_excel_norm('ตามแผน')] = 'plan';
        $byCode = array(); $byTitle = array(); $codes = array();
        foreach (bp_rows('SELECT id,code,title FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$yid) as $p) { if ($p['code'] !== '') { $byCode[$p['code']] = (int)$p['id']; $codes[$p['code']] = 1; } $byTitle[bp_excel_norm($p['title'])] = (int)$p['id']; }
        $prefix = 'P' . substr((string)$year, -2) . '-';
        $nextCode = function () use (&$codes, $prefix) {
            for ($i = 1; $i < 10000; $i++) { $c = $prefix . sprintf('%03d', $i); if (!isset($codes[$c])) { $codes[$c] = 1; return $c; } }
            return '';
        };
        $add = 0; $upd = 0; $sec = 0; $multi = 0; $warn = array();
        $curUnit = 0;
        foreach ($rows as $r) {
            $title = bp_excel_strip_no($r['ชื่อโครงการ']);
            if ($title === '') continue;
            if (bp_excel_is_total($title) || bp_excel_is_total((string)$r['รหัส'])) continue;
            $money = 0;
            foreach (array('งบที่เสนอ', 'งบอนุมัติ', 'เบิกจ่ายรวม') as $h) $money += abs((float)bp_excel_num($r[$h]));
            $money += array_sum($r['_fund']);
            $other = trim($r['หน่วยงาน'] . $r['ผู้รับผิดชอบ'] . $r['วัตถุประสงค์'] . $r['เป้าหมาย'] . $r['หมวดรายจ่าย'] . $r['สถานะ']);
            // แถวหัวข้อ (เช่น "ฝ่ายบริหารทรัพยากร", "ยุทธศาสตร์ที่ 1 …") — ไม่มีเงิน ไม่มีรายละเอียด
            if ($money == 0 && !$r['_q'] && $other === '' && (string)$r['รหัส'] === '') {
                $u = $unitOf($title);
                if ($u || preg_match('/^(ฝ่าย|แผนก|งาน|ยุทธศาสตร์|กลยุทธ์|มาตรฐาน|ด้าน|แผนงาน|ผลผลิต)/u', $title)) { $curUnit = $u; $sec++; continue; }
            }
            $code = mb_substr(trim((string)$r['รหัส']), 0, 32);
            $id = $code !== '' && isset($byCode[$code]) ? $byCode[$code] : ($code === '' && isset($byTitle[bp_excel_norm($title)]) ? $byTitle[bp_excel_norm($title)] : 0);
            $set = array('title=' . q(mb_substr($title, 0, 500)), "updated_at='" . $now . "'");
            if ($code !== '') $set[] = 'code=' . q($code);
            elseif (!$id) { $code = $nextCode(); $set[] = 'code=' . q($code); }
            $unit = 0;
            if ($r['หน่วยงาน'] !== null && $r['หน่วยงาน'] !== '') { $unit = $unitOf($r['หน่วยงาน']); if (!$unit) $warn['หน่วยงาน "' . $r['หน่วยงาน'] . '"'] = 1; }
            if (!$unit && $curUnit) $unit = $curUnit;
            if ($unit) $set[] = 'unit_id=' . $unit;
            if ($r['แหล่งเงิน'] !== null && $r['แหล่งเงิน'] !== '') { $fid = bp_excel_fuzzy(bp_excel_fund_key($r['แหล่งเงิน']), $fundKeys); if ($fid) $set[] = 'fund_id=' . $fid; else $warn['แหล่งเงิน "' . $r['แหล่งเงิน'] . '"'] = 1; }
            elseif ($r['_fund']) { arsort($r['_fund']); $set[] = 'fund_id=' . (int)key($r['_fund']); if (count($r['_fund']) > 1) $multi++; }
            if ($r['หมวดรายจ่าย'] !== null) $set[] = 'expense=' . q(mb_substr($r['หมวดรายจ่าย'], 0, 255));
            $req = bp_excel_num($r['งบที่เสนอ']);
            if ($req !== null) $set[] = 'requested=' . $req;
            $ap = bp_excel_num($r['งบอนุมัติ']);
            if ($ap === null && $r['_fund']) $ap = round(array_sum($r['_fund']), 2);
            if ($ap !== null) $set[] = 'approved=' . $ap;
            $qsum = 0; $anyQ = false;
            for ($i = 1; $i <= 4; $i++) { $v = bp_excel_num($r['เบิกจ่าย ไตรมาส ' . $i]); if ($v !== null) { $anyQ = true; $qsum += $v; $set[] = 'spent_q' . $i . '=' . $v; } }
            $tot = bp_excel_num($r['เบิกจ่ายรวม']);
            if ($anyQ && $qsum > 0) $set[] = 'spent=' . round($qsum, 2); elseif ($tot !== null) $set[] = 'spent=' . $tot;
            if ($r['สถานะ'] !== null && $r['สถานะ'] !== '') { $k = bp_excel_norm($r['สถานะ']); if (isset($stBy[$k])) $set[] = "status='" . $stBy[$k] . "'"; else $warn['สถานะ "' . $r['สถานะ'] . '"'] = 1; }
            if ($r['_q']) { ksort($r['_q']); $set[] = 'quarters=' . q(implode(',', $r['_q'])); }
            elseif ($r['ไตรมาสที่ดำเนินการ'] !== null) { $qs = array(); foreach (preg_split('/[^1-4]+/', $r['ไตรมาสที่ดำเนินการ']) as $x) if ($x !== '') $qs[(int)$x] = (int)$x; ksort($qs); $set[] = 'quarters=' . q(implode(',', $qs)); }
            if ($r['ผลสำเร็จ (%)'] !== null) $set[] = 'result_pct=' . ($r['ผลสำเร็จ (%)'] === '' ? 'NULL' : max(0, min(100, (int)round((float)$r['ผลสำเร็จ (%)']))));
            foreach (array('ผู้รับผิดชอบ' => array('owner', 255), 'ผลการดำเนินงาน' => array('result', 5000), 'ปัญหาอุปสรรค' => array('problems', 5000), 'วัตถุประสงค์' => array('objective', 5000), 'เป้าหมาย' => array('target', 5000)) as $h => $c)
                if ($r[$h] !== null) $set[] = $c[0] . '=' . q(mb_substr($r[$h], 0, $c[1]));
            if ($id) { bp_query('UPDATE ' . bp_t('project') . ' SET ' . implode(',', $set) . ' WHERE id=' . $id); $upd++; }
            else {
                bp_query('INSERT INTO ' . bp_t('project') . ' SET year_id=' . (int)$yid . ',' . implode(',', $set));
                $id = bp_insert_id(); $add++;
                if ($code !== '') $byCode[$code] = $id;
                $byTitle[bp_excel_norm($title)] = $id;
            }
        }
        $msg[] = 'โครงการ' . $from('โครงการ') . ': เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' โครงการ' . ($sec ? ' (ข้ามแถวหัวข้อ ' . $sec . ' แถว)' : '')
            . ($multi ? ' — ' . $multi . ' โครงการใช้เงินหลายแหล่ง: บันทึกแหล่งที่ใช้มากที่สุด (ตรวจที่แท็บโครงการ)' : '')
            . ($warn ? ' — ไม่พบ ' . implode(', ', array_keys($warn)) . ' (ข้ามค่านั้น แก้ทีละโครงการได้)' : '');
    }

    // ---------- จัดซื้อจัดจ้าง: อัปเดตตามลำดับ / เพิ่มใหม่ ----------
    if (isset($pick['จัดซื้อจัดจ้าง'])) {
        $rows = bp_excel_table_rows($book[$pick['จัดซื้อจัดจ้าง'][0]], 'จัดซื้อจัดจ้าง', $pick['จัดซื้อจัดจ้าง'][1]);
        $seqs = array();
        foreach (bp_rows('SELECT id,seq FROM ' . bp_t('procure') . ' WHERE year_id=' . (int)$yid) as $r) $seqs[(int)$r['seq']] = (int)$r['id'];
        $max = $seqs ? max(array_keys($seqs)) : 0;
        $pcode = array(); $ptitle = array();
        foreach (bp_rows('SELECT id,code,title FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$yid) as $p) { if ($p['code'] !== '') $pcode[$p['code']] = (int)$p['id']; $ptitle[bp_excel_norm($p['title'])] = (int)$p['id']; }
        $stBy = array();
        foreach (bp_procure_statuses() as $k => $v) { $stBy[bp_excel_norm($v)] = $k; $stBy[$k] = $k; }
        $add = 0; $upd = 0; $nolink = 0;
        foreach ($rows as $r) {
            $title = bp_excel_strip_no($r['รายการ']);
            if ($title === '' || bp_excel_is_total($title) || bp_excel_is_total((string)$r['ลำดับ'])) continue;
            $seq = (int)$r['ลำดับ'] > 0 ? (int)$r['ลำดับ'] : ++$max;
            if ($seq > $max) $max = $seq;
            $set = array('seq=' . $seq, 'title=' . q(mb_substr($title, 0, 500)), "updated_at='" . $now . "'");
            if ($r['รหัสโครงการ'] !== null && $r['รหัสโครงการ'] !== '') $set[] = 'project_id=' . (isset($pcode[$r['รหัสโครงการ']]) ? $pcode[$r['รหัสโครงการ']] : 'NULL');
            elseif ($r['ชื่อโครงการ'] !== null && $r['ชื่อโครงการ'] !== '') {
                $k = bp_excel_norm(bp_excel_strip_no($r['ชื่อโครงการ']));
                $pid = isset($ptitle[$k]) ? $ptitle[$k] : bp_excel_fuzzy($k, $ptitle);
                $set[] = 'project_id=' . ($pid ? $pid : 'NULL');
                if (!$pid) $nolink++;
            } elseif ($r['รหัสโครงการ'] !== null) $set[] = 'project_id=NULL';
            foreach (array('วงเงินงบประมาณ' => 'budget', 'ราคากลาง' => 'ref_price', 'ราคาที่ตกลง' => 'contract_amount') as $h => $col) { $v = bp_excel_num($r[$h]); if ($v !== null) $set[] = $col . '=' . $v; }
            if ($r['วิธีจัดซื้อจัดจ้าง'] !== null) $set[] = 'method=' . q(bp_excel_method($r['วิธีจัดซื้อจัดจ้าง']));
            foreach (array('ผู้ได้รับคัดเลือก' => array('vendor', 255), 'เลขที่สัญญา' => array('contract_no', 64), 'เหตุผลที่คัดเลือก' => array('reason', 255), 'ผู้เสนอราคาและราคา' => array('bidders', 2000), 'หมายเหตุ' => array('note', 500)) as $h => $c)
                if ($r[$h] !== null) $set[] = $c[0] . '=' . q(mb_substr($r[$h], 0, $c[1]));
            if ($r['เดือนที่คาดว่าจะจัดหา'] !== null) $set[] = 'plan_month=' . q(bp_parse_month($r['เดือนที่คาดว่าจะจัดหา'], $year));
            if ($r['วันที่สัญญา'] !== null) { $d = bp_parse_date($r['วันที่สัญญา']); $set[] = 'contract_date=' . ($d ? q($d) : 'NULL'); }
            $k = bp_excel_norm((string)$r['สถานะ']);
            if (isset($stBy[$k])) $set[] = "status='" . $stBy[$k] . "'";
            elseif ((string)$r['ผู้ได้รับคัดเลือก'] !== '') $set[] = "status='done'";
            if (isset($seqs[$seq])) { bp_query('UPDATE ' . bp_t('procure') . ' SET ' . implode(',', $set) . ' WHERE id=' . $seqs[$seq]); $upd++; }
            else { bp_query('INSERT INTO ' . bp_t('procure') . ' SET year_id=' . (int)$yid . ',' . implode(',', $set)); $seqs[$seq] = bp_insert_id(); $add++; }
        }
        $msg[] = 'จัดซื้อจัดจ้าง' . $from('จัดซื้อจัดจ้าง') . ': เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' รายการ' . ($nolink ? ' — ' . $nolink . ' รายการหาโครงการที่ตรงชื่อไม่เจอ (ไม่ได้ผูกโครงการ)' : '');
    }
    bp_query('UPDATE ' . bp_t('year') . " SET updated_at='" . $now . "' WHERE id=" . (int)$yid);
    return $msg;
}
