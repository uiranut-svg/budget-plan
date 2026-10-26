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
);
