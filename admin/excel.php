<?php
require dirname(__FILE__) . '/_admin.php';
require BP_ROOT . '/inc/excel.php';
bp_require_admin();
$y = admin_year_or_back();
$yid = (int)$y['id'];

if (isset($_GET['download'])) bp_excel_send($yid, $y['year'], false);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $f = isset($_FILES['xlsx']) ? $_FILES['xlsx'] : null;
    $opts = array();
    foreach (array('budget', 'funds', 'projects', 'procure') as $k) $opts[$k] = !empty($_POST[$k]);
    if (!$f || $f['error'] != UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) bp_flash('กรุณาเลือกไฟล์ .xlsx', 'err');
    elseif (!array_filter($opts)) bp_flash('กรุณาเลือกอย่างน้อย 1 แผ่นงานที่จะนำเข้า', 'err');
    else {
        try {
            $book = BpXlsx::read($f['tmp_name']);
            $msg = bp_excel_import($yid, $y['year'], $book, $opts);
            $bad = 0;
            foreach ($msg as $i => $m) if (substr($m, 0, 1) === '!') { $msg[$i] = substr($m, 1); $bad++; }
            bp_done('excel.import', 'นำเข้า Excel ปี ' . $y['year'] . "\n" . implode("\n", $msg), $bad && $bad == count($msg) ? 'err' : 'ok');
        } catch (Exception $e) {
            bp_flash('อ่านไฟล์ไม่ได้: ' . $e->getMessage() . ' — ใช้ไฟล์ .xlsx (Excel 2007 ขึ้นไป) ไม่ใช่ .xls หรือ .csv', 'err');
        }
    }
    bp_redirect('admin/excel.php?id=' . $yid);
}
admin_head('ปีงบประมาณ ' . $y['year'], 'index');
admin_year_tabs($y, 'excel');
?>
<div class="grid2">
  <div class="panel">
    <h2>ส่งออกเป็น Excel</h2>
    <p>ไฟล์เดียวมี 4 แผ่นงาน: <b>งบตามหมวด · แหล่งเงิน · โครงการ · จัดซื้อจัดจ้าง</b> — ใช้ทำเล่มแผน เสนอที่ประชุม หรือ<b>เป็นแม่แบบสำหรับนำเข้า</b></p>
    <p><a class="btn" href="?id=<?php echo $yid; ?>&amp;download=1">ดาวน์โหลด Excel ปี <?php echo (int)$y['year']; ?> (ข้อมูลทั้งหมด)</a></p>
    <p class="muted small">คนทั่วไปดาวน์โหลดฉบับเผยแพร่ (ไม่รวมรายการที่ยกเลิก) ได้จากหน้ารายงานผลบนเว็บ<?php echo $y['published'] ? ': <a href="' . h(bp_url('export.php?y=' . (int)$y['year'])) . '">ลิงก์ดาวน์โหลดสาธารณะ</a>' : ' (เมื่อเผยแพร่ปีนี้แล้ว)'; ?></p>
  </div>
  <form class="panel" method="post" enctype="multipart/form-data">
    <?php echo bp_csrf_field(); ?>
    <h2>นำเข้าจาก Excel</h2>
    <p class="small">อัปโหลดไฟล์แผนจาก Excel แทนการพิมพ์ทีละโครงการ — ใช้<b>ไฟล์แผนปฏิบัติการที่วิทยาลัยมีอยู่แล้ว</b>ได้เลย
      (<a href="<?php echo h(bp_url('samples/sample-plan-2570.xlsx')); ?>">ดูไฟล์ตัวอย่าง</a>) หรือไฟล์ที่ดาวน์โหลดจากกล่องซ้ายแล้วแก้ใน Excel</p>
    <label class="fld"><span><b>1.</b> เลือกไฟล์ .xlsx</span><input type="file" name="xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
    <div class="fld"><span><b>2.</b> ติ๊กเลือกข้อมูลที่จะนำเข้าจากไฟล์</span>
      <label class="chk d-block"><input type="checkbox" name="projects" value="1" checked><span><b>โครงการ</b><small class="muted d-block">เพิ่มโครงการใหม่ และอัปเดตโครงการที่มีอยู่แล้ว (ดูจากรหัสหรือชื่อโครงการ) · โครงการที่ไม่มีในไฟล์ยังอยู่เหมือนเดิม</small></span></label>
      <label class="chk d-block"><input type="checkbox" name="procure" value="1" checked><span><b>แผนจัดซื้อจัดจ้าง</b><small class="muted d-block">เพิ่ม/อัปเดตรายการตามเลขลำดับ และผูกกับโครงการที่ชื่อตรงกัน</small></span></label>
      <label class="chk d-block"><input type="checkbox" name="funds" value="1"><span><b>แหล่งเงิน (ประมาณการรายรับ)</b><small class="muted d-block">ติ๊กเมื่อไฟล์มีแผ่นประมาณการรายรับ · ปรับยอดตามชื่อแหล่งเงิน ไม่ลบของเดิม</small></span></label>
      <label class="chk d-block"><input type="checkbox" name="budget" value="1"><span><b>งบตามหมวด</b><small class="text-err d-block">ระวัง: ลบหมวดและรายการเดิมของปีนี้ทั้งหมด แล้วใส่ตามไฟล์แทน</small></span></label>
    </div>
    <p class="small"><b>3.</b> กด <b>นำเข้า</b> — ระบบแจ้งผลว่าเพิ่ม/อัปเดตกี่รายการ และบอกชื่อหน่วยงานหรือแหล่งเงินในไฟล์ที่หาไม่เจอ (แก้ทีหลังได้)</p>
    <p class="muted small">ก่อนนำเข้าครั้งใหญ่ ควรกด <a href="backup.php">สำรองข้อมูล</a> ไว้ก่อน ถ้าผลไม่ถูกต้องกู้คืนได้ · ชื่อหน่วยงานในไฟล์ต้องมีอยู่ในเมนู หน่วยงาน ก่อน</p>
    <p><button class="btn" type="submit">นำเข้า</button></p>
  </form>
</div>
<?php admin_foot(); ?>
