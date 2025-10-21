<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';

$u   = require_role('ADMIN');
$pdo = db();

$plan_id = isset($_GET['plan_id']) ? (int)$_GET['plan_id'] : 0;
$year    = isset($_GET['year'])    ? (int)$_GET['year']    : (int)date('Y');

/* อ่านแผน */
$st = $pdo->prepare('SELECT * FROM pm_plans WHERE id=? LIMIT 1');
$st->execute([$plan_id]);
$plan = $st->fetch();
if (!$plan) { http_response_code(404); exit('plan not found'); }

/* คำนวณวันนัด PM */
$times = (int)$plan['times_per_year'];
if ($times < 1)  $times = 1;
if ($times > 12) $times = 12;

$dates = [];
$schedule_days = isset($plan['schedule_days']) ? trim($plan['schedule_days']) : '';
if ($schedule_days !== '') {
  foreach (explode(',', $schedule_days) as $p) {
    $p = trim($p);
    if (preg_match('/^(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01])$/', $p)) {
      $dates[] = sprintf('%04d-%s', $year, $p);
    }
  }
  $dates = array_values(array_unique($dates));
  if (count($dates) > $times) $dates = array_slice($dates, 0, $times);
}
if (empty($dates)) {
  $months = [];
  for ($i=0; $i<$times; $i++) {
    $m = 1 + (int)floor(($i*12)/$times);
    if ($m < 1)  $m = 1;
    if ($m > 12) $m = 12;
    $months[] = $m;
  }
  $months = array_values(array_unique($months));
  foreach ($months as $m) $dates[] = sprintf('%04d-%02d-15', $year, $m);
}

/* ตาราง pm_events + index พื้นฐาน (แก้ไวยากรณ์ INDEX) */
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  asset_id INT NULL,
  title VARCHAR(255) NOT NULL,
  scheduled_date DATE NOT NULL,
  status ENUM('PENDING','IN_PROGRESS','DONE','CANCELLED') DEFAULT 'PENDING',
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(plan_id),
  INDEX(asset_id),
  INDEX(scheduled_date),
  KEY plan_date_asset (plan_id, scheduled_date, asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* เตรียม “เป้าหมาย” */
$targets = [];
switch ($plan['target_type']) {
  case 'CATEGORY':
    $s = $pdo->prepare("SELECT id, asset_code FROM assets WHERE category=? AND status='IN_SERVICE'");
    $s->execute([$plan['target_value']]);
    foreach ($s->fetchAll() as $r) {
      $targets[] = ['asset_id'=>(int)$r['id'], 'label'=>($r['asset_code']?' • '.$r['asset_code']:'')];
    }
    break;
  case 'ASSET':
    $aid = (int)preg_replace('/[^0-9]/', '', $plan['target_value']);
    if ($aid > 0) {
      $s = $pdo->prepare("SELECT id, asset_code FROM assets WHERE id=? LIMIT 1");
      $s->execute([$aid]);
      if ($r = $s->fetch()) {
        $targets[] = ['asset_id'=>(int)$r['id'], 'label'=>($r['asset_code']?' • '.$r['asset_code']:'')];
      }
    }
    break;
  case 'GENERIC':
  default:
    $targets[] = ['asset_id'=>null, 'label'=>''];
    break;
}

/* กันซ้ำ (NULL ใน UNIQUE ไม่ชนกัน จึงเช็คด้วย SELECT ก่อน) */
$selHasAsset = $pdo->prepare("SELECT 1 FROM pm_events WHERE plan_id=? AND asset_id=?      AND scheduled_date=? LIMIT 1");
$selNoAsset  = $pdo->prepare("SELECT 1 FROM pm_events WHERE plan_id=? AND asset_id IS NULL AND scheduled_date=? LIMIT 1");
$ins         = $pdo->prepare("INSERT INTO pm_events (plan_id, asset_id, title, scheduled_date) VALUES (?,?,?,?)");

$pdo->beginTransaction();
try {
  foreach ($targets as $tg) {
    $aid = $tg['asset_id'];
    foreach ($dates as $dt) {
      $exists = false;
      if ($aid === null) {
        $selNoAsset->execute([$plan_id, $dt]);
        $exists = (bool)$selNoAsset->fetchColumn();
      } else {
        $selHasAsset->execute([$plan_id, $aid, $dt]);
        $exists = (bool)$selHasAsset->fetchColumn();
      }
      if ($exists) continue;

      $title = 'PM: '.$plan['target_value'].$tg['label'];
      $ins->execute([$plan_id, $aid, $title, $dt]);
    }
  }
  $pdo->commit();
} catch (Exception $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo 'สร้างตารางไม่สำเร็จ: '.$e->getMessage();
  exit;
}

header('Location: events.php?year='.$year);
exit;
