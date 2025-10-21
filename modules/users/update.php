<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/helpers.php';

$u = require_role('ADMIN');
$pdo = db();

/* ensure schema */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sso_username VARCHAR(100) UNIQUE,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) DEFAULT '',
  line_user_id VARCHAR(64) DEFAULT '',
  role ENUM('ADMIN','USER') NOT NULL DEFAULT 'USER',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$id   = (int)($_POST['id'] ?? 0);
$sso  = trim($_POST['sso_username'] ?? '');
$name = trim($_POST['name'] ?? '');
$email= trim($_POST['email'] ?? '');
$line = trim($_POST['line_user_id'] ?? '');
$role = ($_POST['role'] ?? 'USER') === 'ADMIN' ? 'ADMIN' : 'USER';

if ($id <= 0 || $sso === '' || $name === '') {
  header('Location: index.php'); exit;
}

// ตรวจ SSO ซ้ำกับ id อื่น
$chk = $pdo->prepare("SELECT id FROM users WHERE sso_username=? AND id<>? LIMIT 1");
$chk->execute([$sso,$id]);
if ($chk->fetch()) {
  // duplicate
  header('Location: edit.php?id='.$id);
  exit;
}

$upd = $pdo->prepare("UPDATE users SET sso_username=?, name=?, email=?, line_user_id=?, role=? WHERE id=?");
$upd->execute([$sso,$name,$email,$line,$role,$id]);

header('Location: index.php');
