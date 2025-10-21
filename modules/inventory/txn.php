<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u   = require_role('ADMIN');
$pdo = db();

/* ตารางพื้นฐาน */
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory_txns (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  ticket_id BIGINT NULL,
  type ENUM('IN','OUT') NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(item_id), INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_tickets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_by VARCHAR(100) NOT NULL,
  asset_id INT NULL,
  location VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  detail TEXT,
  photo_path VARCHAR(255) DEFAULT '',
  status ENUM('OPEN','ASSIGNED','IN_PROGRESS','DONE','CANCELLED') DEFAULT 'OPEN',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(asset_id), INDEX(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับค่า */
$item_id   = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
$type      = isset($_POST['type']) ? $_POST['type'] : 'IN';
$qty       = isset($_POST['qty']) ? (float)$_POST['qty'] : 0;
$ticket_id = isset($_POST['ticket_id']) && $_POST['ticket_id']!=='' ? (int)$_POST['ticket_id'] : null;
$note      = isset($_POST['note']) ? trim($_POST['note']) : '';

if ($item_id <= 0 || $qty <= 0 || ($type!=='IN' && $type!=='OUT')) {
  http_response_code(422);
  exit('ข้อมูลไม่ถูกต้อง');
}

/* ถ้ามีการผูก ticket_id ต้องเป็นงานที่ยังไม่เสร็จ */
if (!is_null($ticket_id)) {
  $q = $pdo->prepare("SELECT id FROM maintenance_tickets WHERE id=? AND status IN ('OPEN','ASSIGNED','IN_PROGRESS') LIMIT 1");
  $q->execute(array($ticket_id));
  $ok = $q->fetch();
  if (!$ok) {
    http_response_code(422);
    exit('ไม่สามารถผูกกับงานซ่อมที่ปิดแล้ว หรือไม่พบงานซ่อมนี้');
  }
}

/* บันทึกทรานแซกชัน */
$ins = $pdo->prepare("INSERT INTO inventory_txns (item_id, ticket_id, type, qty, note) VALUES (?,?,?,?,?)");
$ins->execute(array($item_id, $ticket_id, $type, $qty, $note));

/* อัปเดตสต็อก */
if ($type === 'IN') {
  $pdo->prepare("UPDATE inventory_items SET stock = stock + ? WHERE id=?")->execute(array($qty, $item_id));
} else {
  $pdo->prepare("UPDATE inventory_items SET stock = GREATEST(stock - ?, 0) WHERE id=?")->execute(array($qty, $item_id));
}

header('Location: view.php?id='.$item_id);
exit;
