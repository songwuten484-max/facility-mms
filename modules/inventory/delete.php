<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u = require_role('ADMIN');
$pdo = db();

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  exit('Invalid ID');
}

$stmt = $pdo->prepare("DELETE FROM inventory_items WHERE id = ? LIMIT 1");
$stmt->execute([$id]);

if (function_exists('flash_set')) {
  flash_set('ลบวัสดุเรียบร้อยแล้ว', 'success');
}

header('Location: index.php');
exit;
