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

$sso  = trim($_POST['sso_username'] ?? '');
$name = trim($_POST['name'] ?? '');
$email= trim($_POST['email'] ?? '');
$line = trim($_POST['line_user_id'] ?? '');
$role = ($_POST['role'] ?? 'USER') === 'ADMIN' ? 'ADMIN' : 'USER';

if ($sso === '' || $name === '') {
  header('Location: new.php'); exit;
}

try {
  $ins = $pdo->prepare("INSERT INTO users (sso_username,name,email,line_user_id,role) VALUES (?,?,?,?,?)");
  $ins->execute([$sso,$name,$email,$line,$role]);
} catch (PDOException $e) {
  // duplicate sso or other error
  // คุณอาจทำ flash message เก็บใน session เพื่อแจ้งเตือนก็ได้
}
header('Location: index.php');
