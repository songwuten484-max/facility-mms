<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* ensure schema */
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_by), INDEX(ticket_id), INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับตัวกรองช่วงวันที่ */
$today        = date('Y-m-d');
$defaultStart = date('Y-01-01');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $defaultStart;
$end   = isset($_GET['end'])   && $_GET['end']   !== '' ? $_GET['end']   : $today;

/* ดึงรายการตามช่วงวันที่ (แสดงตารางด้านล่าง) */
$params = [$start . ' 00:00:00', $end . ' 23:59:59'];
$sqlList = "
  SELECT r.*, t.title, t.location, t.created_at AS ticket_created
  FROM maintenance_ratings r
  LEFT JOIN maintenance_tickets t ON t.id = r.ticket_id
  WHERE r.created_at BETWEEN ? AND ?
  ORDER BY r.created_at DESC
";
$listStmt = $pdo->prepare($sqlList);
$listStmt->execute($params);
$rows = $listStmt->fetchAll();

/* การ์ดสรุป + นับคะแนน 1..5 รวมทั้งช่วง */
$sumStmt = $pdo->prepare("
  SELECT COUNT(*) cnt, AVG(score) avg_score,
         SUM(CASE WHEN score=5 THEN 1 ELSE 0 END) s5,
         SUM(CASE WHEN score=4 THEN 1 ELSE 0 END) s4,
         SUM(CASE WHEN score=3 THEN 1 ELSE 0 END) s3,
         SUM(CASE WHEN score=2 THEN 1 ELSE 0 END) s2,
         SUM(CASE WHEN score=1 THEN 1 ELSE 0 END) s1
  FROM maintenance_ratings
  WHERE created_at BETWEEN ? AND ?
");
$sumStmt->execute($params);
$sum   = $sumStmt->fetch();
$total = (int)($sum['cnt'] ?? 0);
$avg   = $total ? round((float)$sum['avg_score'], 2) : 0.0;

/* -------- กราฟ grouped bars รายเดือน แยกคะแนน 1..5 --------
   โครงสร้างผลงานจาก SQL นี้: แถวต่อเดือน พร้อมจำนวนของคะแนนแต่ละค่า */
$chartStmt = $pdo->prepare("
  SELECT ym,
         SUM(IF(score=1,1,0)) s1,
         SUM(IF(score=2,1,0)) s2,
         SUM(IF(score=3,1,0)) s3,
         SUM(IF(score=4,1,0)) s4,
         SUM(IF(score=5,1,0)) s5
  FROM (
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, score
    FROM maintenance_ratings
    WHERE created_at BETWEEN ? AND ?
  ) x
  GROUP BY ym
  ORDER BY ym
");
$chartStmt->execute($params);
$chartRows = $chartStmt->fetchAll();

/* สร้าง labels (YYYY-MM) และ datasets ต่อคะแนน */
$labels   = [];
$dataS1   = [];
$dataS2   = [];
$dataS3   = [];
$dataS4   = [];
$dataS5   = [];
foreach ($chartRows as $r) {
  $labels[] = $r['ym'];
  $dataS1[] = (int)$r['s1'];
  $dataS2[] = (int)$r['s2'];
  $dataS3[] = (int)$r['s3'];
  $dataS4[] = (int)$r['s4'];
  $dataS5[] = (int)$r['s5'];
}

/* ลิงก์ export */
$base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
$exportUrl = $base . '/../modules/ratings/export_csv.php?start=' . urlencode($start) . '&end=' . urlencode($end);

layout_header('สรุปผลประเมินความพึงพอใจ');
?>
<div class="card p-3 mb-3">
  <form class="row g-2 align-items-end" method="get" action="">
    <div class="col-md-3">
      <label class="form-label">วันที่เริ่ม</label>
      <input type="date" class="form-control" name="start" value="<?= h($start) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label">วันที่สิ้นสุด</label>
      <input type="date" class="form-control" name="end" value="<?= h($end) ?>">
    </div>
    <div class="col-md-2">
      <button class="btn btn-primary w-100">กรอง</button>
    </div>
    <div class="col-md-4 text-end">
      <a class="btn btn-outline-secondary" href="?start=<?= date('Y-01-01') ?>&end=<?= date('Y-m-d') ?>">ปีนี้</a>
      <a class="btn btn-outline-secondary" href="?start=&end=">ทั้งหมด</a>
      <a class="btn btn-success" href="<?= $exportUrl ?>">Export CSV</a>
    </div>
  </form>
</div>

<div class="row g-3 mb-3">
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted">จำนวนแบบประเมิน</div>
      <div class="fs-3 fw-bold"><?= (int)$total ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted">คะแนนเฉลี่ย</div>
      <div class="fs-3 fw-bold"><?= number_format($avg,2) ?>/5</div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted">ให้ 5 ดาว</div>
      <div class="fs-3 fw-bold"><?= (int)($sum['s5'] ?? 0) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card p-3">
      <div class="text-muted">ให้ 1 ดาว</div>
      <div class="fs-3 fw-bold"><?= (int)($sum['s1'] ?? 0) ?></div>
    </div>
  </div>
</div>

<div class="card p-3 mb-3">
  <h5 class="mb-3">กราฟคะแนนแยกรายเดือน (1–5)</h5>
  <canvas id="ratingChart" height="140"></canvas>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:110px;">#งาน</th>
          <th>หัวข้อ</th>
          <th>สถานที่</th>
          <th style="width:120px;">คะแนน</th>
          <th style="width:160px;">วันที่ประเมิน</th>
          <th>หมายเหตุ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">ไม่มีข้อมูลในช่วงวันที่ที่เลือก</td></tr>
        <?php else: foreach ($rows as $r): ?>
          <tr>
            <td><a href="../maintenance/view.php?id=<?= (int)$r['ticket_id'] ?>">#<?= (int)$r['ticket_id'] ?></a></td>
            <td><?= h($r['title'] ?? '') ?></td>
            <td><?= h($r['location'] ?? '') ?></td>
            <td><span class="badge bg-success"><?= (int)$r['score'] ?>/5</span></td>
            <td><?= h($r['created_at']) ?></td>
            <td><?= nl2br(h($r['comment'] ?? '')) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const ctx = document.getElementById('ratingChart');

  const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;   // ['2025-01','2025-02',...]
  const s1 = <?= json_encode($dataS1) ?>;
  const s2 = <?= json_encode($dataS2) ?>;
  const s3 = <?= json_encode($dataS3) ?>;
  const s4 = <?= json_encode($dataS4) ?>;
  const s5 = <?= json_encode($dataS5) ?>;

  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: 'คะแนน 1', data: s1 },
        { label: 'คะแนน 2', data: s2 },
        { label: 'คะแนน 3', data: s3 },
        { label: 'คะแนน 4', data: s4 },
        { label: 'คะแนน 5', data: s5 }
      ]
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        tooltip: { mode: 'index', intersect: false },
        legend: { position: 'top' }
      },
      scales: {
        y: {
          beginAtZero: true,
          title: { text: 'จำนวนครั้ง', display: true }
        },
        x: {
          title: { text: 'เดือน (YYYY-MM)', display: true },
          stacked: false
        }
      }
    }
  });
})();
</script>

<?php layout_footer(); ?>
