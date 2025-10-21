<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';  // ✅ สำคัญ — มี require_role()
require_once __DIR__ . '/../../inc/db.php';

$u = require_role('ADMIN'); // ✅ ตรวจสิทธิ์เฉพาะแอดมิน
$pdo = db();

/* สร้างตาราง inventory_items (ถ้ายังไม่มี) */
$pdo->exec("
CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50) DEFAULT '',
  stock DECIMAL(12,2) DEFAULT 0,
  min_stock DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

/* ตรวจค่าที่ส่งมาจากฟอร์ม */
$sku        = trim($_POST['sku'] ?? '');
$name       = trim($_POST['name'] ?? '');
$unit       = trim($_POST['unit'] ?? '');
$stock      = (float)($_POST['stock'] ?? 0);
$min_stock  = (float)($_POST['min_stock'] ?? 0);

if ($name === '' || $sku === '') {
  http_response_code(400);
  exit('กรุณากรอกข้อมูลให้ครบถ้วน');
}

/* เพิ่มข้อมูลลงฐานข้อมูล */
$ins = $pdo->prepare("
  INSERT INTO inventory_items (sku, name, unit, stock, min_stock)
  VALUES (?, ?, ?, ?, ?)
");
$ins->execute([$sku, $name, $unit, $stock, $min_stock]);

header('Location: index.php');
exit;
