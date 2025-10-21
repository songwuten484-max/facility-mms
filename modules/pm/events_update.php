<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u   = require_role('ADMIN');
$pdo = db();

/* ตารางไฟล์แนบ (ครั้งแรกจะถูกสร้างอัตโนมัติ) */
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_event_files (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) DEFAULT '',
  mime VARCHAR(100) DEFAULT '',
  size BIGINT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$action = $_POST['action'] ?? 'update';
$id     = (int)($_POST['id'] ?? 0);

if ($id <= 0) { http_response_code(400); exit('invalid id'); }

/* อัปเดตรายการ */
$scheduled_date = trim($_POST['scheduled_date'] ?? '');
$status         = trim($_POST['status'] ?? 'PENDING');
$note           = trim($_POST['note'] ?? '');

$upd = $pdo->prepare("UPDATE pm_events SET scheduled_date=?, status=?, note=IF(?='', note, ?) WHERE id=?");
$upd->execute([$scheduled_date ?: null, $status, $note, $note, $id]);

/* แนบไฟล์ (images/PDF) */
if (!empty($_FILES['files']) && is_array($_FILES['files']['name'])) {
  $uploadBase = __DIR__ . '/../../storage/pm';
  if (!is_dir($uploadBase)) {
    @mkdir($uploadBase, 0777, true);
  }

  $count = count($_FILES['files']['name']);
  for ($i=0; $i<$count; $i++) {
    $err = $_FILES['files']['error'][$i];
    if ($err !== UPLOAD_ERR_OK) continue;

    $tmp  = $_FILES['files']['tmp_name'][$i];
    $size = (int)$_FILES['files']['size'][$i];
    $name = $_FILES['files']['name'][$i];
    $mime = mime_content_type($tmp);

    // อนุญาตเฉพาะภาพหรือ PDF
    $allow = false;
    if (strpos($mime, 'image/') === 0) $allow = true;
    if ($mime === 'application/pdf')   $allow = true;
    if (!$allow) continue;

    // จำกัดขนาด (ตัวอย่าง: 20MB)
    if ($size > 20 * 1024 * 1024) continue;

    $ext  = pathinfo($name, PATHINFO_EXTENSION);
    $ext  = $ext ? ('.'.strtolower($ext)) : '';
    $fn   = 'pm_' . $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . $ext;

    $dest = $uploadBase . '/' . $fn;
    if (@move_uploaded_file($tmp, $dest)) {
      // เก็บ path แบบ relative เพื่อใช้แสดงผล
      $rel = 'storage/pm/' . $fn;
      $ins = $pdo->prepare("INSERT INTO pm_event_files (event_id, file_path, original_name, mime, size) VALUES (?,?,?,?,?)");
      $ins->execute([$id, $rel, $name, $mime, $size]);
    }
  }
}

/* กลับไปหน้ารายการ: ถ้าส่งมาจากกรองวัน ให้เด้งกลับวันเดิมเพื่อสะดวก */
$back = 'events.php';
if (!empty($_POST['scheduled_date'])) {
  $back .= '?date=' . urlencode($_POST['scheduled_date']);
}
header('Location: ' . $back);
exit;
