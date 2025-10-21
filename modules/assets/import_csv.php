<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/helpers.php';

$u = require_login();
if (($u['role'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  exit('forbidden');
}

$pdo = db();

/* กันลืม schema */
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

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
  exit('ไม่พบไฟล์ CSV หรืออัปโหลดผิดพลาด');
}

$fp = fopen($_FILES['csv']['tmp_name'], 'r');
if (!$fp) exit('ไม่สามารถอ่านไฟล์ CSV ได้');

$header = fgetcsv($fp);
if (!$header) exit('ไฟล์ CSV ว่างเปล่า');
$header = array_map('trim', $header);

/* mapping header → index */
$idx = array_flip($header);
/* คอลัมน์ที่ต้องมีอย่างน้อย */
$required = ['asset_code','name'];
foreach ($required as $col) {
  if (!isset($idx[$col])) exit('ขาดคอลัมน์บังคับ: '.$col);
}

$allowedStatus = ['IN_SERVICE','REPAIR','DISPOSED'];

$ins = $pdo->prepare("INSERT INTO assets
  (asset_code,name,category,building,room,serial_no,specs,status)
  VALUES (?,?,?,?,?,?,?,?)
  ON DUPLICATE KEY UPDATE
    name=VALUES(name),
    category=VALUES(category),
    building=VALUES(building),
    room=VALUES(room),
    serial_no=VALUES(serial_no),
    specs=VALUES(specs),
    status=VALUES(status)");

$rows = 0;
while (($row = fgetcsv($fp)) !== false) {
  $rows++;
  // อ่านค่าตาม header
  $asset_code = isset($idx['asset_code']) ? trim($row[$idx['asset_code']] ?? '') : '';
  $name       = isset($idx['name'])       ? trim($row[$idx['name']] ?? '')       : '';
  if ($asset_code === '' || $name === '') continue; // ข้ามแถวที่ไม่ครบ

  $category   = isset($idx['category'])   ? trim($row[$idx['category']] ?? '')   : '';
  $building   = isset($idx['building'])   ? trim($row[$idx['building']] ?? '')   : '';
  $room       = isset($idx['room'])       ? trim($row[$idx['room']] ?? '')       : '';
  $serial_no  = isset($idx['serial_no'])  ? trim($row[$idx['serial_no']] ?? '')  : '';
  $specs      = isset($idx['specs'])      ? trim($row[$idx['specs']] ?? '')      : '';
  $status     = isset($idx['status'])     ? trim($row[$idx['status']] ?? '')     : 'IN_SERVICE';
  if (!in_array($status, $allowedStatus, true)) $status = 'IN_SERVICE';

  $ins->execute([$asset_code,$name,$category,$building,$room,$serial_no,$specs,$status]);
}
fclose($fp);

header('Location: index.php');
