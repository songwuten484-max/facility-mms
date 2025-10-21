<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u   = require_role('ADMIN');
$pdo = db();

/* ensure table/columns/enums */
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('CATEGORY','ASSET','GENERIC') NOT NULL,
  target_value VARCHAR(200) NOT NULL,
  times_per_year INT NOT NULL DEFAULT 2,
  description TEXT,
  schedule_days VARCHAR(255) DEFAULT '',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$colDays = $pdo->query("SHOW COLUMNS FROM pm_plans LIKE 'schedule_days'")->fetch();
if (!$colDays) {
  $pdo->exec("ALTER TABLE pm_plans ADD COLUMN schedule_days VARCHAR(255) DEFAULT '' AFTER description");
}
$colTarget = $pdo->query("SHOW COLUMNS FROM pm_plans LIKE 'target_type'")->fetch();
if ($colTarget && strpos($colTarget['Type'], 'GENERIC') === false) {
  $pdo->exec("ALTER TABLE pm_plans MODIFY target_type ENUM('CATEGORY','ASSET','GENERIC') NOT NULL");
}

/* read & validate inputs */
$target_type    = isset($_POST['target_type'])    ? trim($_POST['target_type'])    : '';
$target_value   = isset($_POST['target_value'])   ? trim($_POST['target_value'])   : '';
$times_per_year = isset($_POST['times_per_year']) ? (int)$_POST['times_per_year']  : 0;
$description    = isset($_POST['description'])    ? trim($_POST['description'])    : '';
$schedule_days  = isset($_POST['schedule_days'])  ? trim($_POST['schedule_days'])  : '';

if ($target_type === '' || $target_value === '' || $times_per_year < 1) {
  http_response_code(422);
  echo 'กรุณากรอกให้ครบ: รูปแบบเป้าหมาย / ค่าเป้าหมาย / ครั้งต่อปี (>0)';
  exit;
}
if (!in_array($target_type, array('CATEGORY','ASSET','GENERIC'))) {
  http_response_code(422);
  echo 'target_type ไม่ถูกต้อง';
  exit;
}

/* normalize schedule_days => 'MM-DD,MM-DD' */
$days_clean = array();
if ($schedule_days !== '') {
  $parts = explode(',', $schedule_days);
  foreach ($parts as $p) {
    $p = trim($p);
    if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $p)) {
      $days_clean[] = $p;
    }
  }
  $days_clean = array_values(array_unique($days_clean));
}
$schedule_days_str = implode(',', $days_clean);

/* insert */
$ins = $pdo->prepare("
  INSERT INTO pm_plans (target_type, target_value, times_per_year, description, schedule_days)
  VALUES (?,?,?,?,?)
");
$ins->execute(array($target_type, $target_value, $times_per_year, $description, $schedule_days_str));

header('Location: plan.php');
exit;
