<?php
require_once __DIR__.'/../inc/config.php';
require_once __DIR__.'/../inc/helpers.php';
require_once __DIR__.'/../inc/layout.php';
require_once __DIR__.'/../inc/db.php';

$u   = require_login();
$pdo = db();
$role = isset($u['role']) ? $u['role'] : 'USER';

/* กันลืม: ตารางผลประเมินความพึงพอใจ */
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_by),
  INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* คำนวณจำนวน “รอประเมิน” ของผู้ใช้ปัจจุบัน */
$u_sso = $u['sso_username'] ?? ('dev:'.($u['id'] ?? ''));
$stmtPending = $pdo->prepare("
  SELECT COUNT(*) c
  FROM maintenance_tickets t
  LEFT JOIN maintenance_ratings r ON r.ticket_id = t.id
  WHERE t.created_by = ? AND t.status = 'DONE' AND r.id IS NULL
");
$stmtPending->execute([$u_sso]);
$pendingRate = (int)($stmtPending->fetch()['c'] ?? 0);

layout_header('แดชบอร์ด • FBA Facilities');

/* =========================
   ถ้าเป็น USER: แสดงเฉพาะ 3 เมนู
   ========================= */
if ($role === 'USER'): ?>
  <div class="row g-3 mb-3">
    <div class="col-12">
      <div class="alert alert-info mb-0">
        คุณกำลังใช้งานในสิทธิ์ <b>User</b> — สามารถแจ้งซ่อม ติดตาม “งานซ่อมของฉัน” และทำ “แบบประเมินความพึงพอใจของฉัน”
      </div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <a class="text-decoration-none text-reset" href="../modules/maintenance/submit.php">
        <div class="card p-3 card-hover h-100">
          <h5>🛠️ แจ้งซ่อมออนไลน์</h5>
          <div class="text-muted">บันทึกปัญหา เลือกครุภัณฑ์/สถานที่ แนบรูป กดส่ง</div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a class="text-decoration-none text-reset" href="../modules/maintenance/list.php">
        <div class="card p-3 card-hover h-100">
          <h5>📋 งานซ่อมของฉัน</h5>
          <div class="text-muted">ดูสถานะงานซ่อมและประวัติที่คุณแจ้งไว้</div>
        </div>
      </a>
    </div>

    <div class="col-md-4">
      <a class="text-decoration-none text-reset" href="../modules/ratings/index.php">
        <div class="card p-3 card-hover h-100 d-flex justify-content-between">
          <div>
            <h5>⭐ แบบประเมินความพึงพอใจของฉัน</h5>
            <div class="text-muted">ประเมินงานซ่อมที่เสร็จแล้ว (ต้องประเมินก่อนแจ้งครั้งถัดไป)</div>
          </div>
          <?php if ($pendingRate > 0): ?>
            <span class="badge bg-warning text-dark align-self-start">รอประเมิน <?= $pendingRate ?></span>
          <?php else: ?>
            <span class="badge bg-success align-self-start">ไม่มีรายการค้าง</span>
          <?php endif; ?>
        </div>
      </a>
    </div>
  </div>

<?php
/* จบโหมด USER */
layout_footer();
exit;
endif;

/* =========================
   โหมด ADMIN: คำนวณสรุปตัวเลข + เมนู + ปฏิทิน
   ========================= */

/* ตัวเลขสรุป */
$row  = $pdo->query("SELECT COUNT(*) c FROM maintenance_tickets")->fetch();
$tot  = isset($row['c']) ? (int)$row['c'] : 0;

$row  = $pdo->query("SELECT COUNT(*) c FROM maintenance_tickets WHERE status IN ('OPEN','ASSIGNED','IN_PROGRESS')")->fetch();
$open = isset($row['c']) ? (int)$row['c'] : 0;

$row  = $pdo->query("SELECT COUNT(*) c FROM maintenance_tickets WHERE status='DONE'")->fetch();
$done = isset($row['c']) ? (int)$row['c'] : 0;

$row   = $pdo->query("SELECT COUNT(*) c FROM assets")->fetch();
$assets= isset($row['c']) ? (int)$row['c'] : 0;
?>

<!-- การ์ดสรุป (ADMIN) -->
<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3 card-hover">
      <div class="text-muted">งานซ่อมทั้งหมด</div>
      <div class="fs-3 fw-bold"><?= $tot ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3 card-hover">
      <div class="text-muted">กำลังดำเนินการ</div>
      <div class="fs-3 fw-bold"><?= $open ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3 card-hover">
      <div class="text-muted">ปิดงานแล้ว</div>
      <div class="fs-3 fw-bold"><?= $done ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3 card-hover">
      <div class="text-muted">จำนวนครุภัณฑ์</div>
      <div class="fs-3 fw-bold"><?= $assets ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/maintenance/submit.php">
      <div class="card p-3 card-hover h-100">
        <h5>🛠️ แจ้งซ่อมออนไลน์</h5>
        <div class="text-muted">บันทึกปัญหา เลือกครุภัณฑ์/สถานที่ แนบรูป กดส่ง</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/maintenance/list.php">
      <div class="card p-3 card-hover h-100">
        <h5>📋 งานซ่อมทั้งหมด</h5>
        <div class="text-muted">ติดตามสถานะ จ่ายงาน ช่างบันทึกผล ปิดงาน</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/ratings/index.php">
      <div class="card p-3 card-hover h-100 d-flex justify-content-between">
        <div>
          <h5>⭐ แบบประเมินความพึงพอใจของฉัน</h5>
          <div class="text-muted">ดู/ทำแบบประเมินของตนเอง</div>
        </div>
        <?php if ($pendingRate > 0): ?>
          <span class="badge bg-warning text-dark align-self-start">รอประเมิน <?= $pendingRate ?></span>
        <?php else: ?>
          <span class="badge bg-success align-self-start">ไม่มีรายการค้าง</span>
        <?php endif; ?>
      </div>
    </a>
  </div>

  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/assets/index.php">
      <div class="card p-3 card-hover h-100">
        <h5>📦 ฐานข้อมูลครุภัณฑ์</h5>
        <div class="text-muted">รายการครุภัณฑ์ รหัส ที่ตั้ง ประวัติซ่อม</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/pm/plan.php">
      <div class="card p-3 card-hover h-100">
        <h5>📅 วางแผนบำรุงรักษา</h5>
        <div class="text-muted">ตั้งครั้ง/ปี สร้างตาราง PM อัตโนมัติ</div>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a class="text-decoration-none text-reset" href="../modules/inventory/index.php">
      <div class="card p-3 card-hover h-100">
        <h5>🧰 คลังวัสดุ</h5>
        <div class="text-muted">รับ-จ่ายวัสดุ ออกใบเบิก ผูกกับงานซ่อม/PM</div>
      </div>
    </a>
  </div>
</div>

<?php
/* ---------- ปฏิทิน PM (เดือนนี้) — ADMIN เท่านั้น ---------- */
$year  = (int)date('Y');
$month = (int)date('n');
$first = mktime(0,0,0,$month,1,$year);
$daysInMonth = (int)date('t', $first);
$startDow = (int)date('N', $first); // 1=Mon..7=Sun

$startDate = date('Y-m-01', $first);
$endDate   = date('Y-m-t',  $first);

$sql = "SELECT e.id, e.scheduled_date, e.status, e.title, a.asset_code, a.name AS asset_name
        FROM pm_events e
        LEFT JOIN assets a ON a.id=e.asset_id
        WHERE e.scheduled_date BETWEEN ? AND ?
        ORDER BY e.scheduled_date, e.id";
$st = $pdo->prepare($sql);
$st->execute([$startDate, $endDate]);
$rows = $st->fetchAll();

/* group by day */
$byDay = [];
foreach ($rows as $r){
  $d = $r['scheduled_date'];
  if (!isset($byDay[$d])) $byDay[$d] = [];
  $byDay[$d][] = $r;
}

/* link ปฏิทินเต็ม */
$calendarLink = '../modules/pm/calendar.php?year='.$year.'&month='.$month;
?>

<style>
  .cal-mini{display:grid;grid-template-columns:repeat(7,1fr);gap:.5rem}
  .cal-mini .head{font-weight:600;color:#64748b;text-align:center}
  .cal-cell{background:#fff;border:1px solid rgba(2,6,23,.06);border-radius:12px;min-height:110px;padding:.5rem}
  .cal-num{font-weight:700;color:#0A3F9C}
  .cal-ev{font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

<div class="card p-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">ปฏิทิน PM (<?= $month ?>/<?= $year ?>)</h5>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-primary" href="<?= $calendarLink ?>">เปิดปฏิทินเต็ม</a>
    </div>
  </div>

  <div class="cal-mini">
    <?php
      $thaiDow = ['จันทร์','อังคาร','พุธ','พฤหัสฯ','ศุกร์','เสาร์','อาทิตย์'];
      for ($i=0;$i<7;$i++) echo '<div class="head">'.$thaiDow[$i].'</div>';

      // ช่องว่างก่อนวันแรก (ให้วันจันทร์เป็นคอลัมน์แรก)
      for ($i=1; $i<$startDow; $i++) echo '<div></div>';

      for ($d=1; $d <= $daysInMonth; $d++):
        $ymd = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $evs = isset($byDay[$ymd]) ? $byDay[$ymd] : [];
        $dayLink = '../modules/pm/events.php?date='.$ymd;
    ?>
      <div class="cal-cell">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <a class="cal-num text-decoration-none" href="<?= $dayLink ?>"><?= $d ?></a>
          <?php if (count($evs)>0): ?>
            <span class="badge bg-primary"><?= count($evs) ?></span>
          <?php endif; ?>
        </div>
        <?php
          $shown = 0;
          foreach ($evs as $e){
            if ($shown>=3){ echo '<div class="text-muted small"><a href="'.$dayLink.'">…ดูทั้งหมด</a></div>'; break; }
            $shown++;
            // สีจากสถานะ
            $clr = 'secondary';
            if ($e['status']==='PENDING') $clr='warning';
            else if ($e['status']==='IN_PROGRESS') $clr='info';
            else if ($e['status']==='DONE') $clr='success';
            else if ($e['status']==='CANCELLED') $clr='dark';

            $label = '';
            if (!empty($e['asset_code']) || !empty($e['asset_name'])) {
              $label = (empty($e['asset_code'])?'':$e['asset_code'].' • ')
                     . (empty($e['asset_name'])?'':$e['asset_name']).' — ';
            }
            echo '<div class="cal-ev"><span class="badge bg-'.$clr.' me-1">'.$e['status'].'</span>'
               . '<a class="text-decoration-none" href="'.$dayLink.'">'.h($label.$e['title']).'</a></div>';
          }
        ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php layout_footer();
