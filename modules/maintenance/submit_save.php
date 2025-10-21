<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';

$u   = require_login();
$pdo = db();

/* กันลืมตารางหลัก */
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

$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL,
  by_user VARCHAR(100) NOT NULL,
  action VARCHAR(50) NOT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_created_by (created_by),
  INDEX idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* บล็อกการแจ้งซ่อม ถ้ามีงาน DONE ที่ยังไม่ได้ประเมิน */
$me = $u['sso_username'] ?? ('dev:'.$u['id']);
$needRate = $pdo->prepare("
  SELECT 1
  FROM maintenance_tickets t
  WHERE t.created_by = ?
    AND t.status = 'DONE'
    AND NOT EXISTS (
      SELECT 1 FROM maintenance_ratings r
      WHERE r.ticket_id = t.id AND r.created_by = t.created_by
    )
  LIMIT 1
");
$needRate->execute([$me]);
if ($needRate->fetch()) {
  if (function_exists('flash_set')) {
    flash_set('คุณมีงานซ่อมที่ปิดงานแล้ว แต่ยังไม่ได้ประเมินความพึงพอใจ โปรดประเมินก่อนแจ้งซ่อมใหม่','warning');
  }
  header('Location: ../ratings/index.php');
  exit;
}

/* รับค่า */
$asset_id = isset($_POST['asset_id']) && $_POST['asset_id'] !== '' ? (int)$_POST['asset_id'] : null;
$location = trim($_POST['location'] ?? '');
$title    = trim($_POST['title'] ?? '');
$detail   = trim($_POST['detail'] ?? '');

/* ตรวจ */
if ($location === '' || $title === '') {
  if (function_exists('flash_set')) {
    flash_set('กรอกข้อมูลไม่ครบ','error');
  }
  header('Location: submit.php');
  exit;
}

/* อัปโหลดรูป (ถ้ามี) -> storage/uploads/ */
$photo_path = '';
if (!empty($_FILES['photo']['name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
  $root    = realpath(__DIR__ . '/../../');
  $upDir   = $root . '/storage/uploads';
  if (!is_dir($upDir)) {
    @mkdir($upDir, 0777, true);
  }
  $ext   = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
  $ext   = $ext ? ('.'.strtolower($ext)) : '';
  $fname = 'ticket_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).$ext;
  $dest  = $upDir . '/' . $fname;

  if (@move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {
    // เก็บเป็น path relative จากรากเว็บ (สำหรับแสดงผล)
    $photo_path = 'storage/uploads/' . $fname;
  }
}

/* บันทึกงาน */
$ins = $pdo->prepare("
  INSERT INTO maintenance_tickets (created_by, asset_id, location, title, detail, photo_path)
  VALUES (?, ?, ?, ?, ?, ?)
");
$ins->execute([$me, $asset_id, $location, $title, $detail, $photo_path]);
$ticketId = (int)$pdo->lastInsertId();

/* log แรก */
$log = $pdo->prepare("INSERT INTO maintenance_logs (ticket_id, by_user, action, note) VALUES (?,?,?,?)");
$log->execute([$ticketId, $me, 'CREATE', 'สร้างแจ้งซ่อมใหม่']);

/* -----------------------------
   แจ้งเตือนผ่าน LINE OA
   ----------------------------- */
function line_push($to, $text) {
  if (!defined('LINE_CHANNEL_TOKEN') || !LINE_CHANNEL_TOKEN) return;
  $token = LINE_CHANNEL_TOKEN;

  $payload = [
    'to' => $to,
    'messages' => [[
      'type' => 'text',
      'text' => $text
    ]]
  ];

  $ch = curl_init('https://api.line.me/v2/bot/message/push');
  curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer '.$token
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
  ]);
  curl_exec($ch);
  curl_close($ch);
}

/* ดึง LINE User ID ของผู้แจ้ง และ ADMIN ทั้งหมด */
$uStmt = $pdo->prepare("SELECT line_user_id FROM users WHERE sso_username = ? AND line_user_id <> '' LIMIT 1");
$uStmt->execute([$me]);
$uLine = $uStmt->fetchColumn();

$adminStmt = $pdo->query("SELECT line_user_id FROM users WHERE role='ADMIN' AND line_user_id <> ''");
$adminIds = $adminStmt->fetchAll(PDO::FETCH_COLUMN);

/* ข้อความแจ้งเตือน */
$BASE = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
$viewUrl = 'https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/modules/maintenance/view.php?id=' . $ticketId;

$msg = "🛠️ แจ้งซ่อมใหม่ #{$ticketId}\n"
     . "เรื่อง: {$title}\n"
     . "สถานที่: {$location}\n"
     . (!empty($asset_id) ? "ครุภัณฑ์: #{$asset_id}\n" : "")
     . "ผู้แจ้ง: ".($u['name'] ?? $me)."\n"
     . "ดูรายละเอียด: {$viewUrl}";

/* ส่งให้ผู้แจ้ง (ถ้ามีการเชื่อมต่อ LINE) */
if ($uLine) {
  line_push($uLine, "เราได้รับแจ้งซ่อมของคุณแล้ว ✅\nหมายเลข: #{$ticketId}\nเรื่อง: {$title}\nติดตามงาน: {$viewUrl}");
}

/* ส่งให้แอดมินทุกคน */
if ($adminIds) {
  foreach ($adminIds as $lid) {
    line_push($lid, $msg);
  }
}

/* เสร็จ */
if (function_exists('flash_set')) {
  flash_set('ส่งแจ้งซ่อมเรียบร้อยแล้ว','success');
}
header('Location: view.php?id='.$ticketId);
exit;
