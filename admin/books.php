<?php
require dirname(__FILE__) . '/_admin.php';
bp_require_login();

$dir = BP_ROOT . '/files/books/';

function bp_ini_bytes($v)
{
    $v = trim((string)$v);
    $n = (float)$v;
    $u = strtolower(substr($v, -1));
    if ($u == 'g') $n *= 1073741824; elseif ($u == 'm') $n *= 1048576; elseif ($u == 'k') $n *= 1024;
    return (int)$n;
}
$maxUp = min(bp_ini_bytes(ini_get('upload_max_filesize')), bp_ini_bytes(ini_get('post_max_size')));

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $act = isset($_POST['act']) ? $_POST['act'] : '';
    $yearId = isset($_POST['year_id']) && (int)$_POST['year_id'] > 0 ? (int)$_POST['year_id'] : null;
    if ($act == 'upload') {
        $f = isset($_FILES['pdf']) ? $_FILES['pdf'] : null;
        $title = trim(isset($_POST['title']) ? (string)$_POST['title'] : '');
        if (!$f || $f['error'] == UPLOAD_ERR_NO_FILE) bp_flash('กรุณาเลือกไฟล์ PDF', 'err');
        elseif ($f['error'] == UPLOAD_ERR_INI_SIZE || $f['error'] == UPLOAD_ERR_FORM_SIZE) bp_flash('ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับได้ (' . bp_human_size($maxUp) . ')', 'err');
        elseif ($f['error'] != UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) bp_flash('อัปโหลดไม่สำเร็จ (รหัส ' . (int)$f['error'] . ')', 'err');
        else {
            $fh = fopen($f['tmp_name'], 'rb');
            $head = $fh ? fread($fh, 1024) : '';
            if ($fh) fclose($fh);
            if (strpos($head, '%PDF-') === false) {
                bp_flash('ไฟล์นี้ไม่ใช่ PDF', 'err');
            } else {
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                $name = bp_random_hex(12) . '.pdf';
                if (!@move_uploaded_file($f['tmp_name'], $dir . $name)) {
                    bp_flash('บันทึกไฟล์ไม่ได้ — ให้สิทธิ์เขียนโฟลเดอร์ files/books/ แก่เว็บเซิร์ฟเวอร์', 'err');
                } else {
                    @chmod($dir . $name, 0644);
                    $orig = preg_replace('/[\\\\\/:*?"<>|\r\n]+/u', '_', basename((string)$f['name']));
                    if ($title === '') $title = preg_replace('/\.pdf$/i', '', $orig);
                    bp_query('INSERT INTO ' . bp_t('book') . ' (year_id,title,file,orig_name,size,created_at) VALUES ('
                        . ($yearId ? $yearId : 'NULL') . ',' . q(mb_substr($title, 0, 255)) . ',' . q($name) . ',' . q(mb_substr($orig, 0, 255)) . ',' . (int)filesize($dir . $name) . ",'" . date('Y-m-d H:i:s') . "')");
                    bp_flash('อัปโหลด "' . $title . '" แล้ว');
                }
            }
        }
    } elseif ($act == 'edit') {
        $id = (int)$_POST['id'];
        $title = trim((string)$_POST['title']);
        if ($title !== '') bp_query('UPDATE ' . bp_t('book') . ' SET title=' . q(mb_substr($title, 0, 255)) . ',year_id=' . ($yearId ? $yearId : 'NULL') . ' WHERE id=' . $id);
        bp_flash('บันทึกแล้ว');
    } elseif ($act == 'delete') {
        $b = bp_row('SELECT * FROM ' . bp_t('book') . ' WHERE id=' . (int)$_POST['id']);
        if ($b) {
            if (preg_match('/^[0-9a-f]{24}\.pdf$/', $b['file']) && is_file($dir . $b['file'])) @unlink($dir . $b['file']);
            bp_query('DELETE FROM ' . bp_t('book') . ' WHERE id=' . (int)$b['id']);
            bp_flash('ลบ "' . $b['title'] . '" แล้ว');
        }
    }
    bp_redirect('admin/books.php');
}

$years = bp_years(false);
$books = bp_rows('SELECT b.*, y.year FROM ' . bp_t('book') . ' b LEFT JOIN ' . bp_t('year') . ' y ON y.id=b.year_id ORDER BY y.year DESC, b.id DESC');
function year_select($years, $sel)
{
    $s = '<select name="year_id"><option value="0">— ไม่ระบุปี —</option>';
    foreach ($years as $y) $s .= '<option value="' . (int)$y['id'] . '"' . ($sel == $y['id'] ? ' selected' : '') . '>ปี ' . (int)$y['year'] . '</option>';
    return $s . '</select>';
}
admin_head('เล่มแผน / เอกสาร', 'books');
?>
<div class="panel">
  <h2>อัปโหลดเล่มแผน (PDF)</h2>
  <form method="post" enctype="multipart/form-data" class="form-row" id="upForm">
    <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="upload">
    <label class="fld grow"><span>ชื่อเล่ม</span><input name="title" placeholder="เช่น แผนปฏิบัติราชการ ประจำปีงบประมาณ พ.ศ. 2569"></label>
    <label class="fld"><span>ปีงบประมาณ</span><?php echo year_select($years, $years ? $years[0]['id'] : 0); ?></label>
    <label class="fld grow"><span>ไฟล์ PDF (ไม่เกิน <?php echo bp_human_size($maxUp); ?>)</span><input type="file" name="pdf" accept="application/pdf,.pdf" required data-max="<?php echo (int)$maxUp; ?>"></label>
    <button class="btn" type="submit">อัปโหลด</button>
  </form>
  <p class="muted small">ไฟล์ใหญ่กว่านี้: ผู้ดูแลเซิร์ฟเวอร์ปรับ <code>upload_max_filesize</code> และ <code>post_max_size</code> ใน php.ini · คนทั่วไปเปิดอ่านแบบพลิกหน้าได้ และดาวน์โหลดไฟล์ต้นฉบับได้</p>
</div>

<div class="panel flush">
  <table class="tbl">
    <thead><tr><th>ชื่อเล่ม</th><th>ปี</th><th class="num hide-sm">ขนาด</th><th></th></tr></thead>
    <tbody>
    <?php if (!$books) { ?><tr><td colspan="4" class="center muted">ยังไม่มีเล่มแผน</td></tr><?php } ?>
    <?php foreach ($books as $b) { ?>
      <tr>
        <td colspan="2">
          <form method="post" class="form-row tight">
            <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="edit"><input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
            <input class="grow" name="title" value="<?php echo h($b['title']); ?>" aria-label="ชื่อเล่ม">
            <?php echo year_select($years, $b['year_id']); ?>
            <button class="btn btn-sm btn-ghost" type="submit">บันทึก</button>
          </form>
        </td>
        <td class="num hide-sm"><?php echo bp_human_size($b['size']); ?></td>
        <td class="actions">
          <a class="btn btn-sm" href="<?php echo h(bp_url('book.php?id=' . (int)$b['id'])); ?>" target="_blank" rel="noopener">เปิดอ่าน</a>
          <form method="post" class="inline" onsubmit="return confirm('ลบเล่มนี้และไฟล์ PDF?');">
            <?php echo bp_csrf_field(); ?><input type="hidden" name="act" value="delete"><input type="hidden" name="id" value="<?php echo (int)$b['id']; ?>">
            <button class="btn btn-sm btn-danger" type="submit">ลบ</button>
          </form>
        </td>
      </tr>
    <?php } ?>
    </tbody>
  </table>
</div>
<script>
document.getElementById('upForm').addEventListener('submit', function (e) {
  var f = this.pdf.files[0], max = +this.pdf.getAttribute('data-max');
  if (f && max && f.size > max) { e.preventDefault(); alert('ไฟล์ใหญ่เกินที่เซิร์ฟเวอร์รับได้'); return; }
  this.querySelector('button[type=submit]').disabled = true;
  this.querySelector('button[type=submit]').textContent = 'กำลังอัปโหลด…';
});
</script>
<?php admin_foot(); ?>
