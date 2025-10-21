<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/helpers.php';

$u   = require_login();
$pdo = db();
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_by), INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$ticket_id = (int)($_POST['ticket_id'] ?? 0);
$score     = (int)($_POST['score'] ?? 0);
$comment   = trim($_POST['comment'] ?? '');

$me = $u['sso_username'] ?? ('dev:'.$u['id']);

// ตรวจ ticket เป็นของผู้ใช้และ DONE
$st = $pdo->prepare("SELECT * FROM maintenance_tickets WHERE id=? LIMIT 1");
$st->execute([$ticket_id]);
$t = $st->fetch();
if (!$t || $t['created_by'] !== $me || $t['status'] !== 'DONE') {
  http_response_code(403); exit('forbidden');
}

// กันประเมินซ้ำ
$chk = $pdo->prepare("SELECT 1 FROM maintenance_ratings WHERE ticket_id=? AND created_by=?");
$chk->execute([$ticket_id, $me]);
if ($chk->fetch()) {
  header('Location: index.php');
  exit;
}

// validate score
if ($score < 1 || $score > 5) $score = 5;

// insert
$ins = $pdo->prepare("INSERT INTO maintenance_ratings (ticket_id, created_by, score, comment) VALUES (?,?,?,?)");
$ins->execute([$ticket_id, $me, $score, $comment]);

// เสร็จ -> กลับหน้า index
header('Location: index.php');
