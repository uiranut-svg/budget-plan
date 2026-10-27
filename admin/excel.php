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
    <p class="small">ใช้ได้ทั้ง<b>ไฟล์ที่ดาวน์โหลดจากทางซ้าย</b> และ<b>ไฟล์แผนปฏิบัติการ/แผนจัดซื้อจัดจ้างที่วิทยาลัยทำไว้เอง</b> — ระบบหาแถวหัวตารางให้เอง (หัวตาราง 2 ชั้นได้), ข้ามแถวชื่อฝ่ายและแถว "รวม",
      อ่านเครื่องหมาย ✓ ในช่องไตรมาส/เดือน และงบที่แยกคอลัมน์ตามแหล่งเงิน</p>
    <p class="small">ดูตัวอย่างไฟล์แบบที่อ่านได้: <a href="<?php echo h(bp_url('samples/sample-plan-2570.xlsx')); ?>">ไฟล์แผนปฏิบัติการตัวอย่าง (.xlsx)</a> <span class="muted">(ข้อมูลสมมติ)</span></p>
    <label class="fld"><span>ไฟล์ .xlsx</span><input type="file" name="xlsx" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></label>
    <label class="chk d-block"><input type="checkbox" name="projects" value="1" checked> <b>โครงการ</b> — รหัสตรงกับของเดิม = อัปเดต · รหัสใหม่ = เพิ่ม (ไม่ลบโครงการที่ไม่มีในไฟล์)</label>
    <label class="chk d-block"><input type="checkbox" name="procure" value="1" checked> <b>จัดซื้อจัดจ้าง</b> — ลำดับตรงกับของเดิม = อัปเดต</label>
    <label class="chk d-block"><input type="checkbox" name="funds" value="1"> <b>แหล่งเงิน</b> — อัปเดตยอดตามชื่อ / เพิ่มแหล่งใหม่ (ไม่ลบ)</label>
    <label class="chk d-block"><input type="checkbox" name="budget" value="1"> <b>งบตามหมวด</b> — <span class="text-err">แทนที่หมวดและรายการทั้งหมดของปีนี้</span></label>
    <p class="muted small">แนะนำให้ <a href="backup.php">สำรองข้อมูล</a> ก่อนนำเข้าครั้งใหญ่ · ติ๊ก <b>แหล่งเงิน</b> ด้วยถ้าไฟล์มีแผ่นประมาณการรายรับ · โครงการที่ชื่อตรงกับของเดิม = อัปเดต (ไม่สร้างซ้ำ) · ชื่อหน่วยงาน/แหล่งเงินที่ระบบหาไม่เจอจะแจ้งให้ทราบ</p>
    <p><button class="btn" type="submit">นำเข้า</button></p>
  </form>
</div>
<?php admin_foot(); ?>
