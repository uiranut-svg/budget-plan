<?php
// คอลัมน์ที่เพิ่มภายหลังในตารางเดิม — bp_migrate() เพิ่มให้ถ้ายังไม่มี (ตาราง => คอลัมน์ => นิยาม)
return array(
    'user' => array(
        'role' => "varchar(16) NOT NULL DEFAULT 'admin'",
    ),
    'project' => array(
        'spent_q1' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'spent_q2' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'spent_q3' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'spent_q4' => 'decimal(15,2) NOT NULL DEFAULT 0',
        'result' => 'text',
        'result_pct' => 'int(11) DEFAULT NULL',
        'problems' => 'text',
    ),
);
