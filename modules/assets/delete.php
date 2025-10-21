<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__ . '/../../inc/helpers.php';

$u = require_login();
if (($u['role'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  exit('forbidden');
}

$pdo = db();
$id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) { header('Location: index.php'); exit; }

/* ถ้าตารางอื่นมี FK มาผูกกับ assets.id คุณอาจต้องเช็คความสัมพันธ์ก่อนลบ */
$del = $pdo->prepare('DELETE FROM assets WHERE id=? LIMIT 1');
$del->execute([$id]);

header('Location: index.php');
