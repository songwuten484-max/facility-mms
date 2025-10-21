<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/db.php';

$u = require_role('ADMIN');
$pdo = db();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

/* กันลบตัวเอง */
if (!empty($u['id']) && (int)$u['id'] === $id) {
  header('Location: index.php'); exit;
}

$del = $pdo->prepare("DELETE FROM users WHERE id=? LIMIT 1");
$del->execute([$id]);

header('Location: index.php');
