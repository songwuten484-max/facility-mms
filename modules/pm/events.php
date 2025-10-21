<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u = require_role('ADMIN');
$pdo = db();

/* พารามิเตอร์กรอง */
$year       = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$filterDate = isset($_GET['date']) ? trim($_GET['date']) : '';

/* ดึงข้อมูลตามเงื่อนไข */
if ($filterDate !== '') {
  $rows = $pdo->prepare("
    SELECT e.*, a.asset_code, a.name AS asset_name
    FROM pm_events e
    LEFT JOIN assets a ON a.id = e.asset_id
    WHERE e.scheduled_date = ?
    ORDER BY e.scheduled_date, e.id
  ");
  $rows->execute([$filterDate]);
  $events = $rows->fetchAll();
  $pageTitle = 'ตาราง PM วันที่ ' . $filterDate;
} else {
  $rows = $pdo->prepare("
    SELECT e.*, a.asset_code, a.name AS asset_name
    FROM pm_events e
    LEFT JOIN assets a ON a.id = e.asset_id
    WHERE YEAR(e.scheduled_date) = ?
    ORDER BY e.scheduled_date, e.id
  ");
  $rows->execute([$year]);
  $events = $rows->fetchAll();
  $pageTitle = 'ตาราง PM ปี ' . $year;
}

layout_header($pageTitle);
?>

<div class="card p-3 mb-3">
  <div class="d-flex flex-wrap align-items-center gap-2">
    <a class="btn btn-outline-secondary" href="calendar.php">← ปฏิทิน PM</a>

    <form class="row g-2 ms-auto" method="get" action="">
      <div class="col-auto">
        <input type="date" class="form-control" name="date" value="<?= h($filterDate) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-outline-primary">กรองตามวัน</button>
      </div>
    </form>

    <form class="row g-2" method="get" action="">
      <div class="col-auto">
        <input type="number" class="form-control" name="year" value="<?= (int)$year ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary">ดูตามปี</button>
      </div>
    </form>
  </div>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width: 150px;">วันที่</th>
          <th>ครุภัณฑ์</th>
          <th>หัวข้อ</th>
          <th style="width: 140px;">สถานะ</th>
          <th style="width: 540px;">อัปเดต / แนบไฟล์</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$events): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">ไม่พบรายการ</td></tr>
        <?php else: foreach ($events as $e): ?>

        <?php
          // ✅ โหลดไฟล์แนบของ event นี้
          $files = $pdo->prepare("SELECT * FROM pm_event_files WHERE event_id=? ORDER BY id");
          $files->execute([$e['id']]);
          $attachments = $files->fetchAll();

          // ✅ สีสถานะ
          $stColor = 'secondary';
          $stLabel = $e['status'];
          if ($e['status'] === 'PENDING')     { $stColor = 'warning text-dark'; $stLabel = '⏳ รอดำเนินการ'; }
          if ($e['status'] === 'IN_PROGRESS') { $stColor = 'info text-dark';    $stLabel = '🔧 กำลังดำเนินการ'; }
          if ($e['status'] === 'DONE')        { $stColor = 'success';           $stLabel = '✅ เสร็จสิ้น'; }
          if ($e['status'] === 'CANCELLED')   { $stColor = 'dark';              $stLabel = '❌ ยกเลิก'; }
        ?>

        <tr>
          <td><?= h($e['scheduled_date']) ?></td>
          <td><?= h(
                (isset($e['asset_code']) && $e['asset_code'] ? $e['asset_code'].' • ' : '') .
                (isset($e['asset_name']) && $e['asset_name'] ? $e['asset_name'] : '')
              ) ?></td>
          <td><?= h($e['title']) ?></td>
          <td><span class="badge bg-<?= $stColor ?>" style="font-size:0.9rem;"><?= h($stLabel) ?></span></td>
          <td>
            <!-- ฟอร์มอัปเดต -->
            <form method="post" action="events_update.php" class="row g-2 align-items-center mb-2" enctype="multipart/form-data">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">

              <div class="col-auto">
                <input type="date" class="form-control" name="scheduled_date" value="<?= h($e['scheduled_date']) ?>">
              </div>

              <div class="col-auto" style="min-width: 170px;">
                <select class="form-select" name="status">
                  <?php foreach (['PENDING','IN_PROGRESS','DONE','CANCELLED'] as $st): ?>
                    <option value="<?= $st ?>" <?= ($e['status'] === $st ? 'selected' : '') ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col">
                <input class="form-control" name="note" placeholder="หมายเหตุ">
              </div>

              <div class="col-auto">
                <input class="form-control" type="file" name="files[]" accept="image/*,application/pdf" multiple>
              </div>

              <div class="col-auto">
                <button class="btn btn-primary">บันทึก</button>
              </div>
            </form>

            <!-- ✅ แสดงไฟล์แนบ -->
            <?php if ($attachments): ?>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($attachments as $f): 
                  $isImg = strpos($f['mime'], 'image/') === 0;
                ?>
                  <?php if ($isImg): ?>
                    <a href="../../<?= h($f['file_path']) ?>" target="_blank">
                      <img src="../../<?= h($f['file_path']) ?>" alt="" style="height:60px;border-radius:8px;border:1px solid #ccc;">
                    </a>
                  <?php else: ?>
                    <a href="../../<?= h($f['file_path']) ?>" target="_blank" class="btn btn-outline-dark btn-sm">
                      📄 <?= h($f['original_name']) ?>
                    </a>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="text-muted small">ไม่มีไฟล์แนบ</div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_footer(); ?>
