# ระบบข้อมูลแผนงานและงบประมาณ (budget-plan — ชื่อชั่วคราว)

เว็บเผยแพร่แผนงาน/งบประมาณประจำปีของสถานศึกษา (เริ่ม 2026-10-26 อิงหน้า https://sbt.ac.th/basic-info-04) · แยกจาก SAM (stdac) · **หลายวิทยาลัย = แต่ละแห่งติดตั้งชุดของตัวเอง** แบบ SAM · คนทั่วไปดูได้ไม่ต้องล็อกอิน · UI ภาษาไทย · เน้นใช้ตอบการประเมิน ITA (รายงาน 6/12 เดือน, แผน/สรุปผลจัดซื้อจัดจ้าง สขร.1)

- **ชื่อระบบ/ชื่อโฟลเดอร์ยังไม่แน่นอน** (ผู้ใช้อาจเปลี่ยน): ชื่อระบบ = setting `site_name` (แก้ที่ admin › ตั้งค่า) · ห้ามฝังชื่อโฟลเดอร์ในโค้ด — ใช้ `bp_url()` (คำนวณจาก SCRIPT_NAME; หน้าในโฟลเดอร์ย่อยต้อง `define('BP_DEPTH',1)`)
- **ต้องรันได้ PHP 5.4–8.3 + MariaDB 5.5+** เหมือน SAM: ห้าม `??` `<=>` `fn` `[$a,$b]=` `intdiv` `array_fill` ฯลฯ (closure `function() use` ได้) · `inc/compat.php` (password_hash, hash_equals, random) · ตรวจด้วย `php C:\xampp\htdocs\stdac-master\_tools\check_php54.php C:\xampp\htdocs\budget-plan`
- เขียนไฟล์ UTF-8 ไม่มี BOM
- **ธีม** (ผู้ใช้สั่ง 2026-10-26): แดง-ขาว แบบเว็บหน่วยงานราชการ/กระทรวงศึกษา — แดง `--brand` #b3141b, แดงเข้ม `--brand-2/3`, เส้นทอง `--gold` #c9a227 · สว่างเสมอ (ไม่มีโหมดมืด ยกเว้นหน้าอ่าน E-book พื้นเข้ม) · ห้ามใส่ตรา/โลโก้/ชื่อกระทรวง (ไม่แอบอ้างหน่วยงาน) · สีหมวดในกราฟ `bp_palette()` + `PAL` ใน editor.js/funds.js ต้องตรงกัน · แก้ CSS แล้วเพิ่ม `?v=` ทุกไฟล์ที่โหลด app.css (ตอนนี้ v=11)
- **เต็มจอ** (ผู้ใช้สั่ง 2026-10-27): `.wrap` กว้างสุด 1680px · จอ ≥1200px ใช้ `.layout-2` (ตาราง | กราฟ `.stack`) · รายการย่อยในตารางกางไว้ตั้งแต่แรก (ปุ่ม `.js-fold` พับ)

## หน้าสาธารณะ
- `index.php?y=พ.ศ.` แถบหัวมีตัวเลขสำคัญ (`.hero-stats`) + "ข้อมูล ณ วันที่" (`bp_year_updated()`) · ภาพรวม · `#funds` แหล่งเงิน (ประมาณการ/ยกมา/รับจริง) · `#detail` หมวด→รายการ + ยอดตามหน่วยงาน · `#projects` สรุป + กราฟตามฝ่าย + แท่งแผนรายไตรมาส/เส้นเบิกจ่ายสะสมเทียบเป้า · `#strategy` ความสอดคล้อง · `#compare` เทียบรายปี ≤5 ปี (จับคู่หมวดด้วยชื่อ) · `#books` ชั้นวางเล่มแผน
- `projects.php?y=` รายการโครงการทั้งหมด กรองหน่วยงาน/แหล่งเงิน/สถานะ/ไตรมาส · `project.php?id=` หน้ารายละเอียด (ผล, เบิกจ่ายรายไตรมาส, จัดซื้อของโครงการ, เอกสาร, ภาพกิจกรรม) · สถานะ `cancel` ไม่แสดงสาธารณะ (404), `reserve` แสดงแต่ไม่นับยอด
- `procurement.php?y=&t=plan|result&m=YYYY-MM` แผนการจัดซื้อจัดจ้าง / สรุปผลรายเดือนแบบ สขร.1 (ตามวันที่สัญญา)
- `report.php?y=&p=6|12` รายงานผลการดำเนินงานและการใช้จ่ายงบ รอบ 6/12 เดือน — คำนวณจากยอดเบิกจ่ายรายไตรมาส (`bp_period_data()`), เทียบเป้าหมายสะสม (setting `disb_target` ค่าเริ่ม 30,52,75,100), ข้อความสรุป/ปัญหา/ข้อเสนอแนะจาก `bp_report` เมื่อ published (ไม่มี = สรุปอัตโนมัติ + ปัญหาจากโครงการ) · พิมพ์ A4 ได้ (มีช่องลงนาม `.no-screen`)
- `export.php?y=` ดาวน์โหลด Excel ปีที่เผยแพร่ (ไม่รวมรายการยกเลิก)
- `ita.php?y=` รวมลิงก์เต็ม (`bp_abs_url()`) ของทุกข้อมูลที่ ITA ขอ + ปุ่มคัดลอก + สถานะพร้อม/ยังไม่ถึงรอบ + ลิงก์ สขร.1 รายเดือน · ไม่ใส่เลขข้อ O (เปลี่ยนทุกปี) · ลิงก์จากท้ายเว็บและแท็บปีใน admin
- `book.php?id=` อ่าน PDF แบบพลิกหน้า: `assets/js/flipbook.js` = pdf.js 3.11.174 + StPageFlip 2.0.7 · ไลบรารีใน `assets/vendor/` (ไม่พึ่ง CDN)

## ผู้ดูแล (`admin/`)
- สิทธิ์ (`user.role`): `admin` = ทุกอย่าง (`bp_require_admin()`) · `unit` = ผู้กรอกข้อมูลหน่วยงาน (`bp_user_unit`; เลือกฝ่าย = ทุกงานในฝ่าย) เข้าได้แค่ `my.php` + `project.php` ของหน่วยงานตัวเอง แก้ได้เฉพาะ สถานะ/เบิกจ่ายรายไตรมาส/ผล/ปัญหา/ไฟล์ (`bp_can_edit_project()`) · CSRF ทุก POST ผ่าน `bp_require_login()`
- ทุกการบันทึกเรียก `bp_done(action, msg)` = log ลง `bp_log` + flash · `log.php` ดูบันทึก
- ปีงบ: `index.php` (เพิ่ม = ฉบับร่าง คัดลอกหมวด/แหล่งเงิน/ความสอดคล้องจากปีล่าสุด, ลบต้องพิมพ์ปี, แสดง ⚠ จำนวนจุดผิดปกติ) · แท็บปี `admin_year_tabs()` แสดงคำเตือนจาก `bp_checks()` ด้านบนทุกแท็บ:
  `year.php` งบตามหมวด (`editor.js`, JSON `payload` → ลบแล้วเขียนใหม่) · `funds.php` แหล่งเงิน 2 ชั้น (`funds.js`, คง id) · `projects.php` + วาง/อัปเดตจาก Excel (รหัสซ้ำ = อัปเดต ช่องว่างคงค่าเดิม) · `project.php` (เบิกจ่ายรายไตรมาส → spent = ผลรวม, ผล, ไฟล์รูป/PDF ตรวจจากเนื้อไฟล์ → `files/projects/` ชื่อสุ่ม · รูปผ่าน `bp_image_shrink()`: ด้านยาว ≤1920px, หมุนตาม EXIF, เขียน jpg ใหม่เสมอเพื่อลบ EXIF/GPS, png ทึบที่ใหญ่ → jpg, png โปร่งใสคง png, gif ไม่แตะ) · `transfer.php` ปรับแผน/โอนงบ (ตาราง `transfer`: from/to ว่าง = งบที่ยังไม่จัดสรร/เงินเหลือจ่าย · ปรับ `approved` ของโครงการทันที · งบเดิม = approved − `bp_transfer_net()` · โอนออกได้ไม่เกิน approved−spent · ยกเลิก = คืนเงิน ถ้าปลายทางยังไม่ได้เบิก · แสดงบน project.php และรายงานข้อ 5 `#transfers`) · `procure.php` + `procure_item.php` จัดซื้อจัดจ้าง + วางจาก Excel (ลำดับซ้ำ = อัปเดต) · `align.php` ความสอดคล้อง (ข้อความ `#` ชุด / ข้อ / `-` ข้อย่อย) · `reports.php` ข้อความรายงาน 6/12 เดือน · `excel.php` ส่งออก/นำเข้า
- `units.php` หน่วยงานใช้ทุกปี (ข้อความ ฝ่าย / `- งาน`) — ตัวแก้ไขข้อความจับคู่ด้วยชื่อเพื่อคง id ที่โครงการอ้างถึง
- `settings.php` ข้อมูลเว็บ + เป้าหมายเบิกจ่าย + ผู้ใช้ (สิทธิ์/หน่วยงาน/ตั้งรหัสใหม่) · `books.php` อัปโหลด PDF (`files/books/`)
- ระบบ: `log.php` · `backup.php` สำรอง/กู้คืน (`inc/backup.php`, .sql.gz ใน `backups/` ห้ามเข้าผ่านเว็บ, ตรวจทุกคำสั่งก่อนกู้ว่าเป็นตาราง prefix ตัวเอง, สำรองอัตโนมัติก่อนกู้คืน) · `update.php` อัปเดตจาก GitHub (`inc/update.php`: `BP_UPDATE_REPO` ว่าง = ปิด, tag `vYYYYNNNN` เทียบ `VERSION`, ดาวน์โหลด zip → สำรอง DB → เขียนเฉพาะไฟล์ที่เปลี่ยน เก็บของเดิมใน `backups/rollback-*`, ไม่แตะ config/files/backups · มี `.git` = ปิด)
- Excel: `inc/xlsx.php` (`BpXlsx` อ่าน/เขียน .xlsx หลายแผ่นด้วย zlib ไม่ต้องใช้ ext-zip — ดัดแปลงจาก vec.plan) · `inc/excel.php` แผ่น งบตามหมวด|แหล่งเงิน|โครงการ|จัดซื้อจัดจ้าง (ไฟล์ส่งออก = แม่แบบนำเข้า; จับคู่หัวคอลัมน์ด้วยชื่อ) · นำเข้า: โครงการ upsert ตามรหัส (ไม่มีรหัส = ตามชื่อ, โครงการใหม่ได้รหัส P{ปี2หลัก}-NNN), จัดซื้อตามลำดับ, แหล่งเงินตามชื่อ (ไม่ลบ), งบตามหมวด = แทนที่ทั้งหมด
  · **รับไฟล์แผนที่วิทยาลัยทำเอง** (`bp_excel_table()`): หาแผ่นงานจากหัวตารางถ้าชื่อแผ่นไม่ตรง (`bp_excel_pick`), หาแถวหัวใน 20 แถวแรก ทั้งหัวชั้นเดียว/2 ชั้น (เซลล์ผสาน), ชื่อหัวอื่นใน `bp_excel_aliases()`, ช่อง ✓ ใต้หัวไตรมาส/ช่วงเดือน/ชื่อเดือน → quarters, คอลัมน์ที่หัวเป็นชื่อแหล่งเงิน → fund (หลายแหล่ง = เลือกยอดมากสุด, approved = ผลรวม), ข้ามแถว `รวม…` และแถวหัวข้อฝ่าย (ใช้เป็นหน่วยงานของแถวถัดไป), ตัดเลขข้อหน้าชื่อ, จับคู่หน่วยงานแบบหลวม (แผนก=แผนกวิชา, ขึ้นต้นตรงกัน, ตัด ฝ่าย/แผนก/งาน), แผ่นแหล่งเงินคอลัมน์เดียวใช้ `- ชื่อ` เป็นแหล่งย่อย, วิธีจัดซื้อ e-bidding/e-market/เฉพาะเจาะจง ปรับให้ตรงรายการ
  · ไฟล์ตัวอย่าง `samples/sample-plan-2570.xlsx` (ข้อมูลสมมติ 22 โครงการ/จัดซื้อ 10/ประมาณการรายรับ — ลิงก์จากหน้า admin › Excel) สร้างด้วย Excel COM จาก scratchpad `make_sample.ps1` (ไม่อยู่ใน repo) · วันที่รับ 15/01/2569, ม.ค. 2569, เลขวันที่ Excel (`bp_parse_date/bp_parse_month`)

## ติดตั้ง / ฐานข้อมูล
- `setup.php` ติดตั้ง (เฉพาะ localhost หรือ IP ใน `inc/setup.allow`; มี `inc/config.php` แล้ว = ปิด)
- อัปเกรดอัตโนมัติ: `bp_migrate()` ใน bootstrap เมื่อ setting `schema_ver` < `BP_SCHEMA_VER` (ตอนนี้ 4) → รัน `inc/schema.php` (CREATE IF NOT EXISTS) + เพิ่มคอลัมน์ที่ขาดตาม `inc/schema_columns.php` · เพิ่มตาราง/คอลัมน์ = แก้ 2 ไฟล์นี้แล้วเพิ่มเลขเวอร์ชัน · setup ไม่ตั้ง schema_ver (ให้หน้าแรกอัปเกรดเอง)
- ยอดหมวด: มีรายการย่อย = ผลรวมรายการ, ไม่มี = `category.amount` · ยอดแหล่งเงินหลักที่มีแหล่งย่อย = ผลรวม
- ล็อกอินผิด 5 ครั้ง/15 นาที/IP = พัก (`login_fail`)
- ตาราง (prefix `bp_`): `setting` · `user` (role) · `user_unit` · `login_fail` · `log` · `year` · `category` · `item` · `book` · `unit` (parent_id — ฝ่าย→งาน) · `fund` (year_id, parent_id, estimate, carry_in, received) · `project` (code, unit_id, fund_id, expense ข้อความ, requested/approved/spent, spent_q1..q4, status plan|doing|done|reserve|cancel, quarters "1,2", owner, objective, target, result, result_pct, problems) · `project_file` (kind photo|doc) · `project_align` · `align` (parent_id NULL=ชุด) · `procure` (seq, project_id, method, budget, ref_price, plan_month YYYY-MM, bidders, vendor, contract_amount, contract_no, contract_date, reason, status plan|doing|done|cancel) · `report` (year_id+period 6|12 unique, summary, problems, suggestions, published) · `transfer` (year_id, from_id, to_id, amount, doc_no, doc_date, reason, username)
- ลบปี (admin/index.php) ลบทุกตารางของปีนั้น รวมไฟล์รูป/เอกสารโครงการ — เพิ่มตารางรายปีใหม่ต้องเพิ่มที่นี่ด้วย
- รุ่นโค้ด: ไฟล์ `VERSION` (YYYYNNNN แบบ SAM, ตอนนี้ 20260004)

## เครื่องพัฒนา
- http://localhost/budget-plan/ · DB `budget_plan` · ข้อมูลตัวอย่าง 2567–2569 = ตัวเลขจาก sbt + รายการย่อยสมมติปี 2569 + แหล่งเงินทั้ง 3 ปี/หน่วยงาน/ความสอดคล้อง/โครงการ 19 โครงการ (มีเบิกจ่ายรายไตรมาส + ผล) /จัดซื้อ 12 รายการ ปี 2569 (จาก seeder ของ vec.plan) · เล่มทดสอบ = คู่มือ SAM
- รหัส admin: ผู้ใช้ตั้งเองแล้ว (ถามผู้ใช้) — ลืม = ตั้งใหม่ด้วย PHP `password_hash` ลง `bp_user`
- ทดสอบ POST ภาษาไทยจาก Git Bash แล้วเพี้ยน (codepage) — ใช้สคริปต์ PHP + curl ext · `/tmp` ของ Git Bash ≠ ของ PHP Windows (ใช้ scratchpad)
- ทดสอบภาพ: Edge headless `--screenshot --virtual-time-budget=4000` (รอแอนิเมชันกราฟ; ความกว้าง < ~500px เพี้ยน — ใช้แผงเบราว์เซอร์ preset mobile แทน) · ตรวจ .xlsx ด้วย Excel ผ่าน COM (PowerShell) ได้ เครื่องนี้มี Office

## เว็บสาธิต (ตั้ง 2026-10-27)
- https://demo.school-activity.in.th = เครื่องนี้ผ่าน Cloudflare Tunnel (service `cloudflared` แบบ token, ingress ตั้งใน dashboard → `http://localhost:8080`) · vhost `127.0.0.1:8080` ใน `C:/xampp/apache/conf/extra/httpd-vhosts.conf` เปลี่ยนจาก SAM (`htdocs/stdac`) เป็น budget-plan (สำรอง `httpd-vhosts.conf.bak-20261027-before-budget-plan`) · บล็อก setup.php/phpmyadmin บน vhost นี้ · ไฟล์ชุดเดียวกับเครื่องพัฒนา (แก้โค้ด = ขึ้นเว็บทันที)
- ดู ingress ปัจจุบัน: `curl http://127.0.0.1:20241/config` · Apache รันจาก XAMPP Control Panel → รีสตาร์ทแบบ graceful: PowerShell `[Threading.EventWaitHandle]::OpenExisting('ap<PID แม่ของ httpd>_restart').Set()`
- หลัง tunnel ทุกคำขอมาจาก 127.0.0.1: `bp_client_ip()` ใช้ `CF-Connecting-IP`, `bp_https()` ใช้ `X-Forwarded-Proto` (เชื่อเฉพาะเมื่อมาจาก loopback — `bp_from_local_proxy()`)

## สถานะ (2026-10-27)
- ทำครบตามรายการที่เสนอ 8 ข้อ + (2026-10-27 รอบ 2) นำเข้าไฟล์แผนแบบวิทยาลัย/ไฟล์ตัวอย่าง, หน้า ITA, ปรับแผน/โอนงบ, ย่อรูป (รายงาน 6/12 เดือน, จัดซื้อจัดจ้าง, ผลโครงการ+ไฟล์, ผู้ใช้หลายระดับ, บันทึกการแก้ไข+ข้อมูล ณ วันที่, ตรวจตัวเลข, Excel, สำรอง/อัปเดต) · ทดสอบผ่าน HTTP แล้ว · ยังไม่ติดตั้งที่วิทยาลัยไหน · อยู่บน GitHub แล้ว https://github.com/uiranut-svg/budget-plan (public, ตั้ง 2026-10-27; `BP_UPDATE_REPO` ชี้แล้ว) · ปล่อยรุ่นใหม่ = เพิ่มเลข `VERSION` → commit → `git tag vYYYYNNNN` → `git push origin main --tags` · ยังไม่ทดสอบบน PHP 5.4 จริง (ผ่านแค่ตัวตรวจโค้ด)
- รอผู้ใช้ตัดสิน: ชื่อระบบ/ชื่อโฟลเดอร์จริง · ข้อมูล/เล่มแผนจริงของวิทยาลัย

## ยังไม่ได้ทำ (ไอเดีย)
โค้ด vec.plan-main อยู่ที่ `C:/xampp/htdocs/vec.plan-main` (ระบบภายใน PHP 8 + React: สายอนุมัติ สมุดบัญชีกองเงิน ฯลฯ ยังไม่ได้รวม) · หมวดย่อยหลายชั้น/แผนงาน-โครงการ-ผลผลิต · รับเงินรายไตรมาสของแหล่งเงิน (ตอนนี้รับจริงเป็นยอดเดียว) · แจ้งเตือนผู้กรอกข้อมูลหน่วยงานเมื่อใกล้สิ้นไตรมาส · ฟอนต์มาตรฐานของ pdf.js (`standardFontDataUrl`) สำหรับ PDF ที่ใช้ Dingbats
