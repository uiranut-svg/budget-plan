<?php
// รวมลิงก์ข้อมูลสำหรับกรอกแบบประเมิน ITA (ข้อมูลเปิดเผยต่อสาธารณะ OIT) — คัดลอกลิงก์ของแต่ละข้อไปวางได้ทันที
require dirname(__FILE__) . '/inc/bootstrap.php';
require BP_ROOT . '/inc/layout.php';

$years = bp_years(true);
$cur = null;
$want = isset($_GET['y']) ? (int)$_GET['y'] : 0;
foreach ($years as $y) if ($want && (int)$y['year'] === $want) $cur = $y;
if (!$cur && $years) $cur = $years[0];

$BP_NAV_YEAR = $cur ? (int)$cur['year'] : 0;
bp_head('ลิงก์ข้อมูลสำหรับ ITA');
if (!$cur) { echo '<main class="wrap main"><div class="empty">ยังไม่มีปีงบประมาณที่เผยแพร่</div></main>'; bp_foot(); exit; }

$Y = (int)$cur['year'];
$yid = (int)$cur['id'];
$doneQ = bp_fy_quarter_done($Y);
$projects = bp_projects($yid, true);
$procs = bp_procures($yid, true);
$nDone = 0;
$months = array();
foreach ($procs as $r) if ($r['status'] == 'done') { $nDone++; if ($r['contract_date']) $months[substr($r['contract_date'], 0, 7)] = 1; }
$books = bp_rows('SELECT id,title FROM ' . bp_t('book') . ' WHERE year_id=' . $yid . ' ORDER BY id');
$r6 = bp_report_text($yid, 6);
$r12 = bp_report_text($yid, 12);
$nTf = count(bp_transfers($yid));
$asof = bp_year_updated($yid);

// สถานะ: ok = พร้อม · wait = ยังไม่ถึงรอบ · none = ยังไม่มีข้อมูล
$items = array(
    array('แผนปฏิบัติการ / แผนการใช้จ่ายงบประมาณประจำปี', 'ภาพรวมงบประมาณ แหล่งเงิน รายการตามหมวด และโครงการทั้งปี', 'index.php?y=' . $Y,
        $projects || bp_year_data($yid) ? 'ok' : 'none', count($projects) . ' โครงการ'),
    array('รายละเอียดโครงการตามแผน', 'รายการโครงการทั้งหมด ผู้รับผิดชอบ งบอนุมัติ ระยะเวลา และผลการดำเนินงาน', 'projects.php?y=' . $Y, $projects ? 'ok' : 'none', count($projects) . ' โครงการ'),
    array('รายงานการกำกับติดตามการดำเนินงานและการใช้จ่ายงบประมาณ รอบ 6 เดือน', 'ผลการเบิกจ่ายไตรมาส 1–2 เทียบเป้าหมาย ผลรายโครงการ ปัญหาและข้อเสนอแนะ', 'report.php?y=' . $Y . '&p=6',
        $doneQ >= 2 ? 'ok' : 'wait', $doneQ >= 2 ? ($r6 && $r6['published'] ? 'มีบทสรุปจากผู้รับผิดชอบแล้ว' : 'ยังไม่ได้เขียนบทสรุป (ใช้สรุปอัตโนมัติ)') : 'รอบสิ้นสุด 31 มี.ค.'),
    array('รายงานผลการดำเนินงานและการใช้จ่ายงบประมาณประจำปี (12 เดือน)', 'ผลการดำเนินงานทั้งปีงบประมาณ', 'report.php?y=' . $Y . '&p=12',
        $doneQ >= 4 ? 'ok' : 'wait', $doneQ >= 4 ? ($r12 && $r12['published'] ? 'มีบทสรุปจากผู้รับผิดชอบแล้ว' : 'ยังไม่ได้เขียนบทสรุป (ใช้สรุปอัตโนมัติ)') : 'รอบสิ้นสุด 30 ก.ย.'),
    array('การปรับแผนและโอนเปลี่ยนแปลงงบประมาณ', 'รายการโอนงบระหว่างโครงการ พร้อมเหตุผลและเอกสารอนุมัติ (อยู่ในรายงานประจำปี)', 'report.php?y=' . $Y . '&p=12#transfers', 'ok', $nTf ? $nTf . ' รายการ' : 'ไม่มีการปรับแผน'),
    array('แผนการจัดซื้อจัดจ้าง', 'รายการที่จะจัดซื้อจัดจ้าง วงเงิน วิธีการ และช่วงเวลาที่คาดว่าจะจัดหา', 'procurement.php?y=' . $Y . '&t=plan', $procs ? 'ok' : 'none', count($procs) . ' รายการ'),
    array('สรุปผลการจัดซื้อจัดจ้าง (แบบ สขร.1) ทั้งปี', 'ผู้เสนอราคา ผู้ได้รับคัดเลือก ราคาที่ตกลง และเหตุผล', 'procurement.php?y=' . $Y . '&t=result', $nDone ? 'ok' : 'none', $nDone . ' รายการที่ดำเนินการแล้ว'),
    array('ข้อมูลทั้งปีแบบไฟล์ Excel (ข้อมูลเปิด)', 'ไฟล์ .xlsx: งบตามหมวด แหล่งเงิน โครงการ จัดซื้อจัดจ้าง', 'export.php?y=' . $Y, 'ok', 'ดาวน์โหลดได้'),
);
foreach ($books as $b) $items[] = array('เล่มแผน: ' . $b['title'], 'เปิดอ่านเล่มแผนฉบับ PDF แบบพลิกหน้า', 'book.php?id=' . (int)$b['id'], 'ok', 'E-book');
$stLabel = array('ok' => array('st-done', 'พร้อมใช้'), 'wait' => array('st-doing', 'ยังไม่ถึงรอบ'), 'none' => array('st-cancel', 'ยังไม่มีข้อมูล'));
// สขร.1 รายเดือน: ทุกเดือนของปีงบที่ผ่านมาแล้ว
$monthLinks = array();
foreach (bp_fy_months($Y) as $ym) if ($ym <= date('Y-m')) $monthLinks[] = $ym;
?>
<section class="hero slim">
  <div class="wrap">
    <p class="eyebrow">สำหรับผู้รับผิดชอบการประเมิน ITA</p>
    <h1>ลิงก์ข้อมูลเปิดเผยต่อสาธารณะ ปีงบประมาณ <?php echo $Y; ?></h1>
    <div class="year-tabs">
      <?php foreach ($years as $y) { ?><a class="<?php echo $y['id'] == $cur['id'] ? 'on' : ''; ?>" href="?y=<?php echo (int)$y['year']; ?>">ปีงบประมาณ <?php echo (int)$y['year']; ?></a><?php } ?>
    </div>
  </div>
</section>
<main class="wrap main">
  <div class="note no-print">กดปุ่ม <b>คัดลอก</b> แล้วนำลิงก์ไปวางในระบบ ITA ของข้อที่ตรงกัน · ลิงก์ของแต่ละปีไม่เปลี่ยน ใช้ซ้ำได้ตลอด ·
    เลขข้อ (เช่น O…) เปลี่ยนไปตามคู่มือการประเมินของแต่ละปี — ตรวจหัวข้อให้ตรงกับคู่มือปีนั้น · ข้อมูล ณ วันที่ <?php echo h(bp_thai_date($asof ? $asof : date('Y-m-d'), false, false)); ?></div>

  <div class="panel flush scroll-x">
    <table class="tbl ita">
      <thead><tr><th>#</th><th>ข้อมูล</th><th>ลิงก์</th><th>สถานะ</th></tr></thead>
      <tbody>
      <?php foreach ($items as $i => $it) { $url = bp_abs_url($it[2]); ?>
        <tr>
          <td class="muted"><?php echo $i + 1; ?></td>
          <td><b><?php echo h($it[0]); ?></b><small class="muted d-block"><?php echo h($it[1]); ?></small></td>
          <td class="ita-link"><a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><?php echo h($url); ?></a>
            <button class="btn btn-sm btn-ghost js-copy no-print" type="button" data-url="<?php echo h($url); ?>">คัดลอก</button></td>
          <td><span class="tag <?php echo $stLabel[$it[3]][0]; ?>"><?php echo $stLabel[$it[3]][1]; ?></span><small class="muted d-block"><?php echo h($it[4]); ?></small></td>
        </tr>
      <?php } ?>
      </tbody>
    </table>
  </div>

  <section class="section">
    <div class="section-head"><h2>สรุปผลการจัดซื้อจัดจ้างรายเดือน (สขร.1)</h2></div>
    <p class="muted small">ITA มักขอสรุปผลเป็นรายเดือน — แต่ละเดือนมีลิงก์ของตัวเอง (เดือนที่ไม่มีการจัดซื้อจัดจ้างจะแสดงว่า "ไม่มีการจัดซื้อจัดจ้าง")</p>
    <div class="panel flush scroll-x">
      <table class="tbl ita">
        <thead><tr><th>เดือน</th><th>ลิงก์</th><th>รายการ</th></tr></thead>
        <tbody>
        <?php if (!$monthLinks) { ?><tr><td colspan="3" class="center muted">ปีงบประมาณนี้ยังไม่เริ่ม</td></tr><?php } ?>
        <?php foreach ($monthLinks as $ym) { $url = bp_abs_url('procurement.php?y=' . $Y . '&t=result&m=' . $ym); $n = 0; foreach ($procs as $r) if ($r['status'] == 'done' && substr((string)$r['contract_date'], 0, 7) == $ym) $n++; ?>
          <tr><td class="nowrap"><?php echo h(bp_thai_month($ym)); ?></td>
            <td class="ita-link"><a href="<?php echo h($url); ?>" target="_blank" rel="noopener"><?php echo h($url); ?></a>
              <button class="btn btn-sm btn-ghost js-copy no-print" type="button" data-url="<?php echo h($url); ?>">คัดลอก</button></td>
            <td class="small"><?php echo $n ? $n . ' รายการ' : '<span class="muted">ไม่มีการจัดซื้อจัดจ้าง</span>'; ?></td></tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  </section>
  <p class="no-print"><button class="btn btn-ghost" type="button" onclick="window.print()">พิมพ์รายการลิงก์</button></p>
</main>
<script>
(function () {
  function copy(text, btn) {
    var done = function () { var t = btn.textContent; btn.textContent = 'คัดลอกแล้ว ✓'; setTimeout(function () { btn.textContent = t; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
    var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(ta);
  }
  document.querySelectorAll('.js-copy').forEach(function (b) { b.addEventListener('click', function () { copy(b.getAttribute('data-url'), b); }); });
})();
</script>
<?php bp_foot(); ?>
