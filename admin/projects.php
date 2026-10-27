<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();
$y = admin_year_or_back();
$yid = (int)$y['id'];
$back = 'admin/projects.php?id=' . $yid;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    if ($act == 'delete') {
        $pid = (int)$_POST['pid'];
        $p = bp_row('SELECT * FROM ' . bp_t('project') . ' WHERE id=' . $pid . ' AND year_id=' . $yid);
        if ($p) {
            bp_query('DELETE FROM ' . bp_t('project_align') . ' WHERE project_id=' . $pid);
            bp_query('DELETE FROM ' . bp_t('project') . ' WHERE id=' . $pid);
            bp_flash('ลบโครงการ "' . $p['title'] . '" แล้ว');
        }
    } elseif ($act == 'paste') {
        // รหัส | ชื่อโครงการ | หน่วยงาน | แหล่งเงิน | งบที่เสนอ | งบอนุมัติ | เบิกจ่าย | ผู้รับผิดชอบ
        $unitBy = array();
        foreach (bp_units() as $u) $unitBy[bp_norm($u['name'])] = (int)$u['id'];
        $fundBy = array();
        foreach (bp_fund_index(bp_funds($yid)) as $fid => $f) {
            $fundBy[bp_norm($f['path'])] = $fid;
            if (!isset($fundBy[bp_norm($f['name'])])) $fundBy[bp_norm($f['name'])] = $fid;
        }
        $codes = array();
        foreach (bp_rows('SELECT id,code FROM ' . bp_t('project') . ' WHERE year_id=' . $yid . " AND code<>''") as $r) $codes[$r['code']] = (int)$r['id'];
        $add = 0; $upd = 0; $miss = array();
        foreach (preg_split('/\r?\n/', (string)$_POST['text']) as $line) {
            if (trim($line) === '') continue;
            $c = array_map('trim', explode("\t", $line));
            while (count($c) < 8) $c[] = '';
            if ($c[1] === '' || preg_match('/^(ชื่อโครงการ|โครงการ)$/u', $c[1])) continue;
            $code = mb_substr($c[0], 0, 32);
            $unit = 'NULL';
            if ($c[2] !== '') { if (isset($unitBy[bp_norm($c[2])])) $unit = $unitBy[bp_norm($c[2])]; else $miss['หน่วยงาน "' . $c[2] . '"'] = true; }
            $fund = 'NULL';
            if ($c[3] !== '') { $k = bp_norm(str_replace(array('>', '/'), '›', $c[3])); if (isset($fundBy[$k])) $fund = $fundBy[$k]; else $miss['แหล่งเงิน "' . $c[3] . '"'] = true; }
            if ($code !== '' && isset($codes[$code])) {
                // อัปเดต: ช่องว่าง = คงค่าเดิม (วางแค่ รหัส + ยอดเบิกจ่าย ก็ได้)
                $set = array("updated_at='" . date('Y-m-d H:i:s') . "'", 'title=' . q(mb_substr($c[1], 0, 500)));
                if ($unit !== 'NULL') $set[] = 'unit_id=' . $unit;
                if ($fund !== 'NULL') $set[] = 'fund_id=' . $fund;
                if ($c[4] !== '') $set[] = 'requested=' . bp_parse_amount($c[4]);
                if ($c[5] !== '') $set[] = 'approved=' . bp_parse_amount($c[5]);
                if ($c[6] !== '') $set[] = 'spent=' . bp_parse_amount($c[6]);
                if ($c[7] !== '') $set[] = 'owner=' . q(mb_substr($c[7], 0, 255));
                bp_query('UPDATE ' . bp_t('project') . ' SET ' . implode(',', $set) . ' WHERE id=' . $codes[$code]);
                $upd++;
            } else {
                $set = 'title=' . q(mb_substr($c[1], 0, 500)) . ',unit_id=' . $unit . ',fund_id=' . $fund
                    . ',requested=' . bp_parse_amount($c[4]) . ',approved=' . bp_parse_amount($c[5] !== '' ? $c[5] : $c[4]) . ',spent=' . bp_parse_amount($c[6])
                    . ',owner=' . q(mb_substr($c[7], 0, 255)) . ",updated_at='" . date('Y-m-d H:i:s') . "'";
                bp_query('INSERT INTO ' . bp_t('project') . ' SET year_id=' . $yid . ',code=' . q($code) . ",status='plan'," . $set);
                if ($code !== '') $codes[$code] = bp_insert_id();
                $add++;
            }
        }
        bp_flash('เพิ่ม ' . $add . ' · ปรับปรุง ' . $upd . ' โครงการ' . ($miss ? ' — ไม่พบ: ' . implode(', ', array_keys($miss)) . ' (เว้นว่างไว้ แก้ได้ทีละโครงการ)' : ''), $miss ? 'err' : 'ok');
    }
    bp_redirect($back);
}
function bp_norm($s)
{
    return preg_replace('/\s+/u', '', mb_strtolower((string)$s, 'UTF-8'));
}

$projects = bp_projects($yid);
$units = bp_units();
$fidx = bp_fund_index(bp_funds($yid));
$st = bp_project_statuses();
$pj = bp_project_summary($projects);
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'projects');
?>
<div class="form-row tight" style="margin-bottom:12px">
  <a class="btn" href="project.php?y=<?php echo $yid; ?>">+ เพิ่มโครงการ</a>
  <button type="button" class="btn btn-ghost" onclick="document.getElementById('pasteDlg').showModal()">วาง/อัปเดตจาก Excel</button>
  <span class="muted"><?php echo $pj['n']; ?> โครงการ · อนุมัติ <?php echo bp_money($pj['approved']); ?> · เบิกจ่าย <?php echo bp_money($pj['spent']); ?> บาท (<?php echo bp_pct($pj['spent'], $pj['approved'], 1); ?>%)</span>
</div>
<?php if (!$units) { ?><div class="alert alert-err">ยังไม่มีหน่วยงาน — <a href="units.php">ตั้งหน่วยงาน</a> ก่อนเพื่อให้กรองโครงการตามหน่วยงานได้</div><?php } ?>
<?php if (!$fidx) { ?><div class="alert alert-err">ปีนี้ยังไม่มีแหล่งเงิน — <a href="funds.php?id=<?php echo $yid; ?>">ตั้งแหล่งเงิน</a></div><?php } ?>
<div class="panel flush scroll-x">
  <table class="tbl">
    <thead><tr><th>รหัส</th><th>โครงการ</th><th class="hide-sm">แหล่งเงิน</th><th class="num">อนุมัติ</th><th class="num hide-sm">เบิกจ่าย</th><th>สถานะ</th><th></th></tr></thead>
    <tbody>
    <?php if (!$projects) { ?><tr><td colspan="7" class="center muted">ยังไม่มีโครงการ</td></tr><?php } ?>
    <?php foreach ($projects as $p) { $u = $p['unit_id'] && isset($units[$p['unit_id']]) ? $units[$p['unit_id']]['name'] : ''; ?>
      <tr>
        <td class="nowrap"><?php echo h($p['code']); ?></td>
        <td><a href="project.php?id=<?php echo (int)$p['id']; ?>"><?php echo h($p['title']); ?></a><small class="muted d-block"><?php echo h($u); ?><?php echo $p['align_ids'] ? ' · สอดคล้อง ' . count($p['align_ids']) . ' ข้อ' : ''; ?></small></td>
        <td class="hide-sm small"><?php echo isset($fidx[$p['fund_id']]) ? h($fidx[$p['fund_id']]['path']) : '<span class="muted">–</span>'; ?></td>
        <td class="num"><?php echo bp_money($p['approved']); ?></td>
        <td class="num hide-sm"><?php echo bp_money($p['spent']); ?></td>
        <td><span class="tag st-<?php echo h($p['status']); ?>"><?php echo h(isset($st[$p['status']]) ? $st[$p['status']] : $p['status']); ?></span></td>
        <td class="actions">
          <a class="btn btn-sm" href="project.php?id=<?php echo (int)$p['id']; ?>">แก้ไข</a>
          <form method="post" class="inline" onsubmit="return confirm('ลบโครงการนี้?')"><?php echo bp_csrf_field(); ?>
            <input type="hidden" name="act" value="delete"><input type="hidden" name="pid" value="<?php echo (int)$p['id']; ?>">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button></form>
        </td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<p class="muted small">โครงการสถานะ "ยกเลิก / ไม่ได้รับอนุมัติ" ไม่แสดงบนหน้าเว็บสาธารณะ · โครงการสำรองแสดงแต่ไม่นับรวมยอด</p>

<dialog class="dlg" id="pasteDlg">
  <form method="post">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="paste">
    <h3>วาง/อัปเดตโครงการจาก Excel</h3>
    <p class="muted small">คัดลอกคอลัมน์ตามลำดับ: <b>รหัส</b> | <b>ชื่อโครงการ</b> | หน่วยงาน | แหล่งเงิน | งบที่เสนอ | งบอนุมัติ | เบิกจ่าย | ผู้รับผิดชอบ<br>
      รหัสซ้ำกับโครงการเดิม = <b>อัปเดต</b>ยอด (ใช้อัปเดตยอดเบิกจ่ายทั้งปีได้ในครั้งเดียว) · หน่วยงาน/แหล่งเงินต้องสะกดตรงกับที่ตั้งไว้ (แหล่งเงินย่อยพิมพ์แค่ชื่อย่อยได้)</p>
    <textarea name="text" rows="10" placeholder="วางที่นี่ (Ctrl+V)" required></textarea>
    <p class="dlg-foot"><button type="button" class="btn btn-ghost" onclick="this.closest('dialog').close()">ยกเลิก</button> <button class="btn" type="submit">นำเข้า</button></p>
  </form>
</dialog>
<?php admin_foot(); ?>
