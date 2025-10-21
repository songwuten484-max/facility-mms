<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

$u   = require_login();
$pdo = db();

/* กันลืม schema */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sso_username VARCHAR(100) UNIQUE,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) DEFAULT '',
  line_user_id VARCHAR(64) DEFAULT '',
  role ENUM('ADMIN','USER') NOT NULL DEFAULT 'USER',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ตรวจ CSRF */
$csrf = $_POST['csrf'] ?? '';
if (empty($_SESSION['csrf']) || $csrf !== $_SESSION['csrf']) {
  echo json_encode(['ok'=>false, 'error'=>'invalid_csrf']); exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'link') {
  $lineId = trim($_POST['line_user_id'] ?? '');
  if ($lineId === '') {
    echo json_encode(['ok'=>false, 'error'=>'missing_line_user_id']); exit;
  }
  $upd = $pdo->prepare("UPDATE users SET line_user_id=? WHERE id=?");
  $upd->execute([$lineId, (int)$u['id']]);
  echo json_encode(['ok'=>true]); exit;

} elseif ($action === 'unlink') {
  $upd = $pdo->prepare("UPDATE users SET line_user_id='' WHERE id=?");
  $upd->execute([(int)$u['id']]);
  echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false, 'error'=>'unknown_action']);
