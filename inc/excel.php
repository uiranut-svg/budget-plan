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
/** แปลงแถวเป็น array(ชื่อหัว => ค่า) โดยหาหัวตารางจากแถวแรก · คืนค่า array(rows, missingHeads) */
function bp_excel_rows($sheet, $heads)
{
    if (!$sheet) return array(array(), $heads);
    $first = array_map('trim', $sheet[0]);
    $pos = array();
    foreach ($heads as $h) {
        $i = array_search($h, $first, true);
        if ($i !== false) $pos[$h] = $i;
    }
    $miss = array_values(array_diff($heads, array_keys($pos)));
    $rows = array();
    foreach (array_slice($sheet, 1) as $r) {
        $o = array();
        foreach ($heads as $h) $o[$h] = isset($pos[$h]) && isset($r[$pos[$h]]) ? trim($r[$pos[$h]]) : null;
        $rows[] = $o;
    }
    return array($rows, $miss);
}
function bp_excel_norm($s)
{
    return preg_replace('/\s+/u', '', mb_strtolower(str_replace(array('>', '/'), '›', (string)$s), 'UTF-8'));
}
function bp_excel_num($v)
{
    return $v === null || $v === '' ? null : bp_parse_amount($v);
}

/** นำเข้าแผ่นงานที่เลือก · คืนค่า array of ข้อความผลลัพธ์ */
function bp_excel_import($yid, $year, $book, $opts)
{
    $H = bp_excel_heads();
    $msg = array();
    $now = date('Y-m-d H:i:s');

    // ---------- งบตามหมวด: แทนที่ทั้งหมด ----------
    if (!empty($opts['budget']) && isset($book['งบตามหมวด'])) {
        list($rows, $miss) = bp_excel_rows($book['งบตามหมวด'], $H['งบตามหมวด']);
        if (in_array('หมวด', $miss) || in_array('จำนวนเงิน', $miss)) $msg[] = 'งบตามหมวด: ไม่พบหัวคอลัมน์ ' . implode(', ', $miss) . ' — ข้ามแผ่นนี้';
        else {
            $colors = array();
            foreach (bp_rows('SELECT name,color FROM ' . bp_t('category') . ' WHERE year_id=' . (int)$yid) as $c) $colors[$c['name']] = $c['color'];
            $cats = array();
            foreach ($rows as $r) {
                if ($r['หมวด'] === '' || $r['หมวด'] === null) continue;
                $k = $r['หมวด'];
                if (!isset($cats[$k])) $cats[$k] = array('amount' => 0, 'items' => array());
                if ($r['รายการ'] === '' || $r['รายการ'] === null) $cats[$k]['amount'] += bp_parse_amount($r['จำนวนเงิน']);
                else $cats[$k]['items'][] = $r;
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
                        bp_query('INSERT INTO ' . bp_t('item') . ' (category_id,name,department,amount,note,sort) VALUES (' . $cid . ',' . q(mb_substr($it['รายการ'], 0, 500)) . ',' . q(mb_substr((string)$it['หน่วยงาน'], 0, 255)) . ','
                            . bp_parse_amount($it['จำนวนเงิน']) . ',' . q(mb_substr((string)$it['หมายเหตุ'], 0, 500)) . ',' . $ii . ')');
                        $ni++;
                    }
                    $ci++;
                }
                bp_query('COMMIT');
                $msg[] = 'งบตามหมวด: แทนที่ด้วย ' . $ci . ' หมวด ' . $ni . ' รายการ';
            } else $msg[] = 'งบตามหมวด: ไม่มีข้อมูล — ไม่ได้เปลี่ยน';
        }
    }

    // ---------- แหล่งเงิน: อัปเดตยอดตามชื่อ / เพิ่มใหม่ (ไม่ลบของเดิม) ----------
    if (!empty($opts['funds']) && isset($book['แหล่งเงิน'])) {
        list($rows, $miss) = bp_excel_rows($book['แหล่งเงิน'], $H['แหล่งเงิน']);
        if (in_array('แหล่งเงินหลัก', $miss)) $msg[] = 'แหล่งเงิน: ไม่พบหัวคอลัมน์ แหล่งเงินหลัก — ข้ามแผ่นนี้';
        else {
            $top = array(); $kid = array(); $sort = 0;
            foreach (bp_rows('SELECT * FROM ' . bp_t('fund') . ' WHERE year_id=' . (int)$yid) as $f) {
                if ($f['parent_id']) $kid[$f['parent_id'] . '|' . $f['name']] = $f['id']; else { $top[$f['name']] = $f['id']; $sort = max($sort, (int)$f['sort'] + 1); }
            }
            $add = 0; $upd = 0;
            foreach ($rows as $r) {
                if ($r['แหล่งเงินหลัก'] === '' || $r['แหล่งเงินหลัก'] === null) continue;
                $tn = mb_substr($r['แหล่งเงินหลัก'], 0, 255);
                if (!isset($top[$tn])) {
                    bp_query('INSERT INTO ' . bp_t('fund') . ' (year_id,parent_id,name,color,sort) VALUES (' . (int)$yid . ',NULL,' . q($tn) . ',' . q(bp_palette($sort)) . ',' . ($sort++) . ')');
                    $top[$tn] = bp_insert_id(); $add++;
                }
                $id = $top[$tn];
                if ($r['แหล่งเงินย่อย'] !== '' && $r['แหล่งเงินย่อย'] !== null) {
                    $kn = mb_substr($r['แหล่งเงินย่อย'], 0, 255);
                    if (!isset($kid[$id . '|' . $kn])) {
                        bp_query('INSERT INTO ' . bp_t('fund') . ' (year_id,parent_id,name,sort) VALUES (' . (int)$yid . ',' . (int)$id . ',' . q($kn) . ',99)');
                        $kid[$id . '|' . $kn] = bp_insert_id(); $add++;
                    } else $upd++;
                    $id = $kid[$id . '|' . $kn];
                } else $upd++;
                $set = array();
                foreach (array('ประมาณการรายรับ' => 'estimate', 'ยกมา' => 'carry_in', 'รับจริง' => 'received') as $h => $col) { $v = bp_excel_num($r[$h]); if ($v !== null) $set[] = $col . '=' . $v; }
                if ($r['หมายเหตุ'] !== null) $set[] = 'note=' . q(mb_substr($r['หมายเหตุ'], 0, 500));
                if ($set) bp_query('UPDATE ' . bp_t('fund') . ' SET ' . implode(',', $set) . ' WHERE id=' . (int)$id);
            }
            $msg[] = 'แหล่งเงิน: เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' รายการ';
        }
    }

    // ---------- โครงการ: อัปเดตตามรหัส (ไม่มีรหัส = ตามชื่อ) / เพิ่มใหม่ ----------
    if (!empty($opts['projects']) && isset($book['โครงการ'])) {
        list($rows, $miss) = bp_excel_rows($book['โครงการ'], $H['โครงการ']);
        if (in_array('ชื่อโครงการ', $miss)) $msg[] = 'โครงการ: ไม่พบหัวคอลัมน์ ชื่อโครงการ — ข้ามแผ่นนี้';
        else {
            $unitBy = array();
            foreach (bp_units(true) as $u) $unitBy[bp_excel_norm($u['name'])] = (int)$u['id'];
            $fundBy = array();
            foreach (bp_fund_index(bp_funds($yid)) as $fid => $f) { $fundBy[bp_excel_norm($f['path'])] = $fid; if (!isset($fundBy[bp_excel_norm($f['name'])])) $fundBy[bp_excel_norm($f['name'])] = $fid; }
            $stBy = array();
            foreach (bp_project_statuses() as $k => $v) { $stBy[bp_excel_norm($v)] = $k; $stBy[$k] = $k; }
            $stBy[bp_excel_norm('แล้วเสร็จ')] = 'done'; $stBy[bp_excel_norm('ยกเลิก')] = 'cancel'; $stBy[bp_excel_norm('สำรอง')] = 'reserve'; $stBy[bp_excel_norm('ตามแผน')] = 'plan';
            $byCode = array(); $byTitle = array();
            foreach (bp_rows('SELECT id,code,title FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$yid) as $p) { if ($p['code'] !== '') $byCode[$p['code']] = (int)$p['id']; $byTitle[bp_excel_norm($p['title'])] = (int)$p['id']; }
            $add = 0; $upd = 0; $warn = array();
            foreach ($rows as $r) {
                if ($r['ชื่อโครงการ'] === '' || $r['ชื่อโครงการ'] === null) continue;
                $code = mb_substr((string)$r['รหัส'], 0, 32);
                $id = $code !== '' && isset($byCode[$code]) ? $byCode[$code] : ($code === '' && isset($byTitle[bp_excel_norm($r['ชื่อโครงการ'])]) ? $byTitle[bp_excel_norm($r['ชื่อโครงการ'])] : 0);
                $set = array('title=' . q(mb_substr($r['ชื่อโครงการ'], 0, 500)), "updated_at='" . $now . "'");
                if ($r['รหัส'] !== null) $set[] = 'code=' . q($code);
                if ($r['หน่วยงาน'] !== null && $r['หน่วยงาน'] !== '') { $k = bp_excel_norm($r['หน่วยงาน']); if (isset($unitBy[$k])) $set[] = 'unit_id=' . $unitBy[$k]; else $warn['หน่วยงาน "' . $r['หน่วยงาน'] . '"'] = 1; }
                if ($r['แหล่งเงิน'] !== null && $r['แหล่งเงิน'] !== '') { $k = bp_excel_norm($r['แหล่งเงิน']); if (isset($fundBy[$k])) $set[] = 'fund_id=' . $fundBy[$k]; else $warn['แหล่งเงิน "' . $r['แหล่งเงิน'] . '"'] = 1; }
                if ($r['หมวดรายจ่าย'] !== null) $set[] = 'expense=' . q(mb_substr($r['หมวดรายจ่าย'], 0, 255));
                foreach (array('งบที่เสนอ' => 'requested', 'งบอนุมัติ' => 'approved') as $h => $col) { $v = bp_excel_num($r[$h]); if ($v !== null) $set[] = $col . '=' . $v; }
                $qsum = 0; $anyQ = false;
                for ($i = 1; $i <= 4; $i++) { $v = bp_excel_num($r['เบิกจ่าย ไตรมาส ' . $i]); if ($v !== null) { $anyQ = true; $qsum += $v; $set[] = 'spent_q' . $i . '=' . $v; } }
                $tot = bp_excel_num($r['เบิกจ่ายรวม']);
                if ($anyQ && $qsum > 0) $set[] = 'spent=' . round($qsum, 2); elseif ($tot !== null) $set[] = 'spent=' . $tot;
                if ($r['สถานะ'] !== null && $r['สถานะ'] !== '') { $k = bp_excel_norm($r['สถานะ']); if (isset($stBy[$k])) $set[] = "status='" . $stBy[$k] . "'"; else $warn['สถานะ "' . $r['สถานะ'] . '"'] = 1; }
                if ($r['ไตรมาสที่ดำเนินการ'] !== null) { $qs = array(); foreach (preg_split('/[^1-4]+/', $r['ไตรมาสที่ดำเนินการ']) as $x) if ($x !== '') $qs[(int)$x] = (int)$x; ksort($qs); $set[] = 'quarters=' . q(implode(',', $qs)); }
                if ($r['ผลสำเร็จ (%)'] !== null) $set[] = 'result_pct=' . ($r['ผลสำเร็จ (%)'] === '' ? 'NULL' : max(0, min(100, (int)round((float)$r['ผลสำเร็จ (%)']))));
                foreach (array('ผู้รับผิดชอบ' => array('owner', 255), 'ผลการดำเนินงาน' => array('result', 5000), 'ปัญหาอุปสรรค' => array('problems', 5000), 'วัตถุประสงค์' => array('objective', 5000), 'เป้าหมาย' => array('target', 5000)) as $h => $c)
                    if ($r[$h] !== null) $set[] = $c[0] . '=' . q(mb_substr($r[$h], 0, $c[1]));
                if ($id) { bp_query('UPDATE ' . bp_t('project') . ' SET ' . implode(',', $set) . ' WHERE id=' . $id); $upd++; }
                else { bp_query('INSERT INTO ' . bp_t('project') . ' SET year_id=' . (int)$yid . ',' . implode(',', $set)); $id = bp_insert_id(); if ($code !== '') $byCode[$code] = $id; $add++; }
            }
            $msg[] = 'โครงการ: เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' โครงการ' . ($warn ? ' — ไม่พบ ' . implode(', ', array_keys($warn)) . ' (ข้ามค่านั้น แก้ทีละโครงการได้)' : '');
        }
    }

    // ---------- จัดซื้อจัดจ้าง: อัปเดตตามลำดับ / เพิ่มใหม่ ----------
    if (!empty($opts['procure']) && isset($book['จัดซื้อจัดจ้าง'])) {
        list($rows, $miss) = bp_excel_rows($book['จัดซื้อจัดจ้าง'], $H['จัดซื้อจัดจ้าง']);
        if (in_array('รายการ', $miss)) $msg[] = 'จัดซื้อจัดจ้าง: ไม่พบหัวคอลัมน์ รายการ — ข้ามแผ่นนี้';
        else {
            $seqs = array();
            foreach (bp_rows('SELECT id,seq FROM ' . bp_t('procure') . ' WHERE year_id=' . (int)$yid) as $r) $seqs[(int)$r['seq']] = (int)$r['id'];
            $max = $seqs ? max(array_keys($seqs)) : 0;
            $pcode = array();
            foreach (bp_rows('SELECT id,code FROM ' . bp_t('project') . ' WHERE year_id=' . (int)$yid . " AND code<>''") as $p) $pcode[$p['code']] = (int)$p['id'];
            $stBy = array();
            foreach (bp_procure_statuses() as $k => $v) { $stBy[bp_excel_norm($v)] = $k; $stBy[$k] = $k; }
            $add = 0; $upd = 0;
            foreach ($rows as $r) {
                if ($r['รายการ'] === '' || $r['รายการ'] === null) continue;
                $seq = (int)$r['ลำดับ'] > 0 ? (int)$r['ลำดับ'] : ++$max;
                if ($seq > $max) $max = $seq;
                $set = array('seq=' . $seq, 'title=' . q(mb_substr($r['รายการ'], 0, 500)), "updated_at='" . $now . "'");
                if ($r['รหัสโครงการ'] !== null) $set[] = 'project_id=' . (isset($pcode[$r['รหัสโครงการ']]) ? $pcode[$r['รหัสโครงการ']] : 'NULL');
                foreach (array('วงเงินงบประมาณ' => 'budget', 'ราคากลาง' => 'ref_price', 'ราคาที่ตกลง' => 'contract_amount') as $h => $col) { $v = bp_excel_num($r[$h]); if ($v !== null) $set[] = $col . '=' . $v; }
                foreach (array('วิธีจัดซื้อจัดจ้าง' => array('method', 64), 'ผู้ได้รับคัดเลือก' => array('vendor', 255), 'เลขที่สัญญา' => array('contract_no', 64), 'เหตุผลที่คัดเลือก' => array('reason', 255), 'ผู้เสนอราคาและราคา' => array('bidders', 2000)) as $h => $c)
                    if ($r[$h] !== null) $set[] = $c[0] . '=' . q(mb_substr($r[$h], 0, $c[1]));
                if ($r['เดือนที่คาดว่าจะจัดหา'] !== null) $set[] = 'plan_month=' . q(bp_parse_month($r['เดือนที่คาดว่าจะจัดหา'], $year));
                if ($r['วันที่สัญญา'] !== null) { $d = bp_parse_date($r['วันที่สัญญา']); $set[] = 'contract_date=' . ($d ? q($d) : 'NULL'); }
                $k = bp_excel_norm((string)$r['สถานะ']);
                if (isset($stBy[$k])) $set[] = "status='" . $stBy[$k] . "'";
                elseif ((string)$r['ผู้ได้รับคัดเลือก'] !== '') $set[] = "status='done'";
                if (isset($seqs[$seq])) { bp_query('UPDATE ' . bp_t('procure') . ' SET ' . implode(',', $set) . ' WHERE id=' . $seqs[$seq]); $upd++; }
                else { bp_query('INSERT INTO ' . bp_t('procure') . ' SET year_id=' . (int)$yid . ',' . implode(',', $set)); $seqs[$seq] = bp_insert_id(); $add++; }
            }
            $msg[] = 'จัดซื้อจัดจ้าง: เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' รายการ';
        }
    }
    bp_query('UPDATE ' . bp_t('year') . " SET updated_at='" . $now . "' WHERE id=" . (int)$yid);
    return $msg;
}
