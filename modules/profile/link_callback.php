<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';

header('Content-Type: application/json; charset=utf-8');

$u = require_login();
$pdo = db();

$raw = file_get_contents('php://input');
$js  = json_decode($raw, true);
$lineUserId = trim($js['line_user_id'] ?? '');

if ($lineUserId === '') {
  echo json_encode(['ok'=>false,'error'=>'missing line_user_id']); exit;
}

// กันผู้ใช้สองคนใช้ line_user_id ซ้ำ
$chk = $pdo->prepare("SELECT id, sso_username FROM users WHERE line_user_id=? AND sso_username<>?");
$chk->execute([$lineUserId, $u['sso_username'] ?? '']);
$dup = $chk->fetch();
if ($dup) {
  echo json_encode(['ok'=>false,'error'=>'LINE นี้ถูกเชื่อมกับผู้ใช้อื่นแล้ว']); exit;
}

$upd = $pdo->prepare("UPDATE users SET line_user_id=? WHERE id=?");
$upd->execute([$lineUserId, $u['id']]);

echo json_encode(['ok'=>true]);
