<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/helpers.php';

$u = require_role('ADMIN');
$pdo = db();

/* สร้างตารางถ้ายังไม่มี */
$pdo->exec("CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT '',
  building VARCHAR(100) DEFAULT '',
  room VARCHAR(100) DEFAULT '',
  serial_no VARCHAR(200) DEFAULT '',
  specs TEXT,
  status ENUM('IN_SERVICE','REPAIR','DISPOSED') DEFAULT 'IN_SERVICE',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับค่าจากฟอร์ม */
$asset_code = isset($_POST['asset_code']) ? trim($_POST['asset_code']) : '';
$name       = isset($_POST['name'])       ? trim($_POST['name'])       : '';
$category   = isset($_POST['category'])   ? trim($_POST['category'])   : '';
$building   = isset($_POST['building'])   ? trim($_POST['building'])   : '';
$room       = isset($_POST['room'])       ? trim($_POST['room'])       : '';
$serial_no  = isset($_POST['serial_no'])  ? trim($_POST['serial_no'])  : '';
$specs      = isset($_POST['specs'])      ? trim($_POST['specs'])      : '';

if ($asset_code === '' || $name === '') {
  http_response_code(422);
  echo 'กรุณากรอก "รหัสครุภัณฑ์" และ "ชื่อรายการ"';
  exit;
}

/* บันทึก */
$ins = $pdo->prepare('INSERT INTO assets (asset_code,name,category,building,room,serial_no,specs)
                      VALUES (?,?,?,?,?,?,?)');
$ins->execute(array($asset_code, $name, $category, $building, $room, $serial_no, $specs));

header('Location: index.php');
exit;
