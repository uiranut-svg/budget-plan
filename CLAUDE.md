# ระบบข้อมูลแผนงานและงบประมาณ (budget-plan — ชื่อชั่วคราว)

เว็บเผยแพร่แผนงาน/งบประมาณประจำปีของสถานศึกษา (เริ่ม 2026-10-26 อิงหน้า https://sbt.ac.th/basic-info-04) · แยกจาก SAM (stdac) · **หลายวิทยาลัย = แต่ละแห่งติดตั้งชุดของตัวเอง** แบบ SAM · คนทั่วไปดูได้ไม่ต้องล็อกอิน · UI ภาษาไทย

- **ชื่อระบบ/ชื่อโฟลเดอร์ยังไม่แน่นอน** (ผู้ใช้อาจเปลี่ยน): ชื่อระบบ = setting `site_name` (แก้ที่ admin › ตั้งค่า) · ห้ามฝังชื่อโฟลเดอร์ในโค้ด — ใช้ `bp_url()` (คำนวณจาก SCRIPT_NAME; หน้าในโฟลเดอร์ย่อยต้อง `define('BP_DEPTH',1)`)
- **ต้องรันได้ PHP 5.4–8.3 + MariaDB 5.5+** เหมือน SAM: ห้าม `??` `<=>` `fn` `[$a,$b]=` ฯลฯ · `inc/compat.php` (password_hash, hash_equals, random) · ตรวจด้วย `php C:\xampp\htdocs\stdac-master\_tools\check_php54.php C:\xampp\htdocs\budget-plan`
- เขียนไฟล์ UTF-8 ไม่มี BOM
- **ธีม** (ผู้ใช้สั่ง 2026-10-26): แดง-ขาว แบบเว็บหน่วยงานราชการ/กระทรวงศึกษา — แดง `--brand` #b3141b, แดงเข้ม `--brand-2/3`, เส้นทอง `--gold` #c9a227 · สว่างเสมอ (ไม่มีโหมดมืด ยกเว้นหน้าอ่าน E-book พื้นเข้ม) · ห้ามใส่ตรา/โลโก้/ชื่อกระทรวง (ไม่แอบอ้างหน่วยงาน) · สีหมวดในกราฟ `bp_palette()` + `PAL` ใน editor.js ต้องตรงกัน · แก้ CSS แล้วเพิ่ม `?v=` ทุกไฟล์ที่โหลด app.css

## โครงสร้าง
- `index.php` หน้าสาธารณะ: เลือกปี `?y=พ.ศ.` · การ์ดสรุป · กราฟ Chart.js (สัดส่วน/ตามหมวด/เทียบรายปี ≤5 ปี จับคู่หมวดด้วยชื่อ) · ตารางหมวด→รายการย่อย (กดเปิด, ค้นหา) · ยอดตามหน่วยงาน · ชั้นวางเล่มแผน (ปกวาดจากหน้าแรกของ PDF ด้วย pdf.js เมื่อเลื่อนถึง)
- **แหล่งเงิน / โครงการ / ความสอดคล้อง** (รวมจาก vec.plan-main 2026-10-27 — เอาเฉพาะโครงข้อมูล+ข้อมูลตัวอย่าง ไม่เอาสายอนุมัติ/สมุดบัญชี/AI/React/PHP 8): หน้าแรกมี section `#funds` (ประมาณการ/ยกมา/รับจริง) `#projects` (สรุป+กราฟตามฝ่าย/ไตรมาส) `#strategy` (แท็บชุดความสอดคล้อง แถบยอด) · `projects.php?y=` รายการโครงการทั้งหมด กรองหน่วยงาน/แหล่งเงิน/สถานะ/ไตรมาส กดดูรายละเอียด · โครงการสถานะ `cancel` ไม่แสดงสาธารณะ, `reserve` แสดงแต่ไม่นับยอด · เมนูบน `bp_nav_links()` ส่งต่อ `?y=`
- `book.php?id=` อ่าน PDF แบบพลิกหน้า: `assets/js/flipbook.js` = pdf.js 3.11.174 + StPageFlip 2.0.7 (โหมด HTML, วาดเฉพาะหน้าใกล้ๆ คืนหน่วยความจำหน้าที่ไกล >14) · จอแคบ = ทีละหน้า · ปุ่มขยาย = วาดความละเอียดสูงในหน้าต่างเลื่อนได้ · `#p=N` = หน้า · ไลบรารีเก็บใน `assets/vendor/` (ไม่พึ่ง CDN)
- `admin/` (ล็อกอิน, CSRF ทุก POST ผ่าน `bp_require_login()`): `index.php` ปีงบ (เพิ่ม = ฉบับร่าง, คัดลอกหมวด/รายการจากปีล่าสุด, ลบต้องพิมพ์ปี) · `year.php` ตัวแก้ไข (`assets/js/editor.js`) ส่งทั้งปีเป็น JSON ช่อง `payload` (เลี่ยง max_input_vars) → ลบแล้วเขียนใหม่ใน transaction · วางจาก Excel (แท็บ: รายการ|หน่วยงาน|จำนวนเงิน|หมายเหตุ) · `books.php` อัปโหลด PDF (ตรวจ `%PDF-`, ชื่อสุ่ม 24 hex ใน `files/books/`) · `funds.php?id=` แหล่งเงิน 2 ชั้น (`assets/js/funds.js`, JSON payload เก็บ id เดิม) · `projects.php?id=` รายการ + วาง/อัปเดตจาก Excel (รหัสซ้ำ = อัปเดต ช่องว่างคงค่าเดิม) · `project.php` ฟอร์มโครงการ + เลือกความสอดคล้อง · `align.php?id=` ข้อความ (`#` ชุด / ข้อ / `-` ข้อย่อย) · `units.php` หน่วยงานใช้ทุกปี (ข้อความ ฝ่าย / `- งาน`) — ทุกตัวแก้ไขจับคู่ด้วยชื่อเพื่อคง id ที่โครงการอ้างถึง · แท็บปี `admin_year_tabs()` · เพิ่มปี = คัดลอกโครงสร้างแหล่งเงิน/ความสอดคล้องจากปีล่าสุด (ไม่มี = ชุดมาตรฐานใน `inc/sample.php`) · `settings.php` ชื่อระบบ/สถานศึกษา/คำอธิบาย/หน่วยงาน/ติดต่อ + รหัสผ่าน + ผู้ดูแลหลายคน
- `setup.php` ติดตั้ง (เฉพาะ localhost หรือ IP ใน `inc/setup.allow`; มี `inc/config.php` แล้ว = ปิด) · ตาราง `inc/schema.php`
- อัปเกรดฐานข้อมูล: `bp_migrate()` ใน bootstrap รัน `inc/schema.php` (IF NOT EXISTS) เมื่อ setting `schema_ver` < `BP_SCHEMA_VER` — เพิ่มตาราง = เพิ่มใน schema แล้วเพิ่มเลขเวอร์ชัน (เปลี่ยนคอลัมน์ต้องเขียน ALTER เอง)
- ยอดหมวด: มีรายการย่อย = ผลรวมรายการ, ไม่มี = `category.amount` (`bp_year_data()`)
- ล็อกอินผิด 5 ครั้ง/15 นาที/IP = พัก (`login_fail`)

## ตาราง (prefix `bp_`)
`setting` · `user` · `login_fail` · `year` (year=พ.ศ. unique, published) · `category` (year_id, name, color, amount, sort) · `item` (category_id, name, department, amount, note, sort) · `book` (year_id null ได้, title, file, orig_name, size) · `unit` (parent_id, name — ฝ่าย→งาน ทุกปีใช้ร่วม) · `fund` (year_id, parent_id, name, color, estimate, carry_in, received, note — แหล่งหลักที่มีแหล่งย่อย = ผลรวม) · `project` (year_id, code, title, unit_id, fund_id, expense ข้อความ, requested/approved/spent, status plan|doing|done|reserve|cancel, quarters "1,2", owner, objective, target) · `align` (year_id, parent_id NULL=ชุด) · `project_align`

## เครื่องพัฒนา
- http://localhost/budget-plan/ · DB `budget_plan` · ข้อมูลตัวอย่าง 2567–2569 = ตัวเลขจาก sbt + รายการย่อยสมมติปี 2569 + แหล่งเงินทั้ง 3 ปี/หน่วยงาน/ความสอดคล้อง/โครงการ 19 โครงการปี 2569 (จาก seeder ของ vec.plan) · ทดสอบ POST ภาษาไทยจาก Git Bash แล้วเพี้ยน (codepage) — ใช้สคริปต์ PHP + curl ext แทน · เล่มทดสอบ = คู่มือ SAM
- รหัส admin ทดสอบอยู่ในไฟล์ scratchpad ของ session ที่สร้าง (ไม่อยู่ในโปรเจกต์) — ลืม = ตั้งใหม่ด้วย PHP `password_hash` ลง `bp_user`
- ทดสอบภาพ: Node + Edge headless คุย CDP (แผงเบราว์เซอร์ที่ถูกซ่อนไม่วาดภาพเคลื่อนไหวของ StPageFlip)

## สถานะ (พักงาน 2026-10-26 — ผู้ใช้จะกลับมาทำต่อ)
- รุ่นแรกเสร็จและทดสอบแล้วบนเครื่องพัฒนา (commit แรก `1e5e852`): หน้าสาธารณะ, E-book, admin ครบ · ยังไม่ติดตั้งที่วิทยาลัยไหน · ยังไม่ push GitHub · ยังไม่ทดสอบบน PHP 5.4 จริง (ผ่านแค่ตัวตรวจโค้ด)
- รอผู้ใช้ตัดสิน: ชื่อระบบ/ชื่อโฟลเดอร์จริง · จะทำข้อไหนต่อจากรายการด้านล่าง · เล่มแผนจริงของวิทยาลัย (ตอนนี้ใช้คู่มือ SAM ทดสอบ)
- เริ่มทำต่อ = เปิด session ใหม่ในโฟลเดอร์ `C:\xampp\htdocs\budget-plan`

## ยังไม่ได้ทำ (ไอเดีย)
โค้ด vec.plan-main อยู่ที่ `C:
mpp\htdocsec.plan-main` (ระบบภายใน PHP 8 + React: สายอนุมัติ สมุดบัญชีกองเงิน ฯลฯ ยังไม่ได้รวม) · อัปเดตตัวเองจาก GitHub แบบ SAM · นำเข้า Excel ทั้งไฟล์ · หมวดย่อยหลายชั้น/แผนงาน-โครงการ-ผลผลิต · งบที่ใช้จริง (เบิกจ่าย) เทียบแผน · ส่งออก Excel/PDF · ฟอนต์มาตรฐานของ pdf.js (`standardFontDataUrl`) สำหรับ PDF ที่ใช้ Dingbats
