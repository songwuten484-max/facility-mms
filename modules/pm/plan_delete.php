<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/helpers.php';

$u   = require_role('ADMIN');
$pdo = db();

$plan_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($plan_id <= 0) {
  http_response_code(400);
  echo 'Invalid plan id';
  exit;
}

/* กันลืม: มีตารางแน่ ๆ */
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('CATEGORY','ASSET') NOT NULL,
  target_value VARCHAR(200) NOT NULL,
  times_per_year INT NOT NULL DEFAULT 2,
  description TEXT,
  schedule_days VARCHAR(255) DEFAULT '',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS pm_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  asset_id INT NULL,
  title VARCHAR(255) NOT NULL,
  scheduled_date DATE NOT NULL,
  status ENUM('PENDING','IN_PROGRESS','DONE','CANCELLED') DEFAULT 'PENDING',
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(plan_id), INDEX(asset_id), INDEX(scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

try {
  $pdo->beginTransaction();

  // ลบเหตุการณ์ทั้งหมดของแผนนี้ก่อน
  $delEvents = $pdo->prepare('DELETE FROM pm_events WHERE plan_id=?');
  $delEvents->execute(array($plan_id));

  // ลบตัวแผน
  $delPlan = $pdo->prepare('DELETE FROM pm_plans WHERE id=?');
  $delPlan->execute(array($plan_id));

  $pdo->commit();
} catch (Exception $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo 'ลบไม่สำเร็จ: '.$e->getMessage();
  exit;
}

header('Location: plan.php');
exit;
