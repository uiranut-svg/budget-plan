<?php
// โครงสร้างตาราง — ใช้ได้กับ MariaDB 5.5 ขึ้นไป ({p} = prefix)
return array(
    "CREATE TABLE IF NOT EXISTS `{p}setting` (
        `name` varchar(64) NOT NULL,
        `value` text,
        PRIMARY KEY (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}user` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(64) NOT NULL,
        `password` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL DEFAULT '',
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}login_fail` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `ip` varchar(45) NOT NULL,
        `at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `ip_at` (`ip`,`at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}year` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year` int(11) NOT NULL,
        `title` varchar(255) NOT NULL DEFAULT '',
        `note` text,
        `published` tinyint(1) NOT NULL DEFAULT 1,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `year` (`year`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}category` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `name` varchar(255) NOT NULL,
        `color` varchar(16) NOT NULL DEFAULT '',
        `amount` decimal(15,2) NOT NULL DEFAULT 0,
        `sort` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}item` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_id` int(11) NOT NULL,
        `name` varchar(500) NOT NULL,
        `department` varchar(255) NOT NULL DEFAULT '',
        `amount` decimal(15,2) NOT NULL DEFAULT 0,
        `note` varchar(500) NOT NULL DEFAULT '',
        `sort` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `category_id` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}book` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) DEFAULT NULL,
        `title` varchar(255) NOT NULL,
        `file` varchar(64) NOT NULL,
        `orig_name` varchar(255) NOT NULL DEFAULT '',
        `size` int(11) NOT NULL DEFAULT 0,
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // หน่วยงาน (ต้นไม้ 2 ชั้น: ฝ่าย → งาน/แผนกวิชา) ใช้ร่วมทุกปี
    "CREATE TABLE IF NOT EXISTS `{p}unit` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `parent_id` int(11) DEFAULT NULL,
        `name` varchar(255) NOT NULL,
        `sort` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `parent_id` (`parent_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // แหล่งเงินของแต่ละปี (ต้นไม้ 2 ชั้น) + ประมาณการรายรับ / ยกมา / รับจริง
    "CREATE TABLE IF NOT EXISTS `{p}fund` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `name` varchar(255) NOT NULL,
        `color` varchar(16) NOT NULL DEFAULT '',
        `estimate` decimal(15,2) NOT NULL DEFAULT 0,
        `carry_in` decimal(15,2) NOT NULL DEFAULT 0,
        `received` decimal(15,2) NOT NULL DEFAULT 0,
        `note` varchar(500) NOT NULL DEFAULT '',
        `sort` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // โครงการตามแผนของแต่ละปี
    "CREATE TABLE IF NOT EXISTS `{p}project` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `code` varchar(32) NOT NULL DEFAULT '',
        `title` varchar(500) NOT NULL,
        `unit_id` int(11) DEFAULT NULL,
        `fund_id` int(11) DEFAULT NULL,
        `expense` varchar(255) NOT NULL DEFAULT '',
        `requested` decimal(15,2) NOT NULL DEFAULT 0,
        `approved` decimal(15,2) NOT NULL DEFAULT 0,
        `spent` decimal(15,2) NOT NULL DEFAULT 0,
        `status` varchar(16) NOT NULL DEFAULT 'plan',
        `quarters` varchar(16) NOT NULL DEFAULT '',
        `owner` varchar(255) NOT NULL DEFAULT '',
        `objective` text,
        `target` text,
        `note` varchar(500) NOT NULL DEFAULT '',
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // ความสอดคล้อง: parent_id NULL = ชุด (เช่น ยุทธศาสตร์ชาติ) · มี parent = ข้อในชุด (ข้อย่อยได้อีก 1 ชั้น)
    "CREATE TABLE IF NOT EXISTS `{p}align` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `parent_id` int(11) DEFAULT NULL,
        `name` varchar(500) NOT NULL,
        `sort` int(11) NOT NULL DEFAULT 0,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    "CREATE TABLE IF NOT EXISTS `{p}project_align` (
        `project_id` int(11) NOT NULL,
        `align_id` int(11) NOT NULL,
        PRIMARY KEY (`project_id`,`align_id`),
        KEY `align_id` (`align_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    // ---------- รุ่น 3 ----------
    // รายงานผลรอบ 6 / 12 เดือน (ข้อความสรุป ปัญหา ข้อเสนอแนะ)
    "CREATE TABLE IF NOT EXISTS `{p}report` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `period` tinyint(4) NOT NULL,
        `summary` text,
        `problems` text,
        `suggestions` text,
        `published` tinyint(1) NOT NULL DEFAULT 0,
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `year_period` (`year_id`,`period`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // แผน / ผลการจัดซื้อจัดจ้าง (แบบ สขร.1)
    "CREATE TABLE IF NOT EXISTS `{p}procure` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `year_id` int(11) NOT NULL,
        `project_id` int(11) DEFAULT NULL,
        `seq` int(11) NOT NULL DEFAULT 0,
        `title` varchar(500) NOT NULL,
        `method` varchar(64) NOT NULL DEFAULT '',
        `budget` decimal(15,2) NOT NULL DEFAULT 0,
        `ref_price` decimal(15,2) NOT NULL DEFAULT 0,
        `plan_month` varchar(7) NOT NULL DEFAULT '',
        `bidders` text,
        `vendor` varchar(255) NOT NULL DEFAULT '',
        `contract_amount` decimal(15,2) NOT NULL DEFAULT 0,
        `contract_no` varchar(64) NOT NULL DEFAULT '',
        `contract_date` date DEFAULT NULL,
        `reason` varchar(255) NOT NULL DEFAULT '',
        `status` varchar(16) NOT NULL DEFAULT 'plan',
        `note` varchar(500) NOT NULL DEFAULT '',
        `updated_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `year_id` (`year_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // รูป / เอกสารของโครงการ
    "CREATE TABLE IF NOT EXISTS `{p}project_file` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `project_id` int(11) NOT NULL,
        `kind` varchar(8) NOT NULL DEFAULT 'doc',
        `file` varchar(64) NOT NULL,
        `orig_name` varchar(255) NOT NULL DEFAULT '',
        `title` varchar(255) NOT NULL DEFAULT '',
        `size` int(11) NOT NULL DEFAULT 0,
        `created_at` datetime DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `project_id` (`project_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // บันทึกการแก้ไขข้อมูล
    "CREATE TABLE IF NOT EXISTS `{p}log` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) DEFAULT NULL,
        `username` varchar(64) NOT NULL DEFAULT '',
        `action` varchar(64) NOT NULL,
        `detail` varchar(500) NOT NULL DEFAULT '',
        `ip` varchar(45) NOT NULL DEFAULT '',
        `at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `at` (`at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    // ผู้กรอกข้อมูลของหน่วยงาน ↔ หน่วยงานที่ดูแล
    "CREATE TABLE IF NOT EXISTS `{p}user_unit` (
        `user_id` int(11) NOT NULL,
        `unit_id` int(11) NOT NULL,
        PRIMARY KEY (`user_id`,`unit_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
