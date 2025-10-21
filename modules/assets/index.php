<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_login();
$pdo = db();

/* ตาราง assets (กันลืม) */
$pdo->exec("CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT '',
  building VARCHAR(100) DEFAULT '',
  room VARCHAR(100) DEFAULT '',
  serial_no VARCHAR(200) DEFAULT '',
  specs TEXT,
  status ENUM('IN_SERVICE','REPAIR','DISPOSED') DEFAULT 'IN_SERVICE',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ==== รับค่าค้นหา/กรอง ==== */
$building = trim($_GET['building'] ?? '');
$room     = trim($_GET['room'] ?? '');
$code     = trim($_GET['code'] ?? ''); // ค้นหารหัสครุภัณฑ์ (บางส่วน)
$q        = trim($_GET['q'] ?? '');    // คีย์เวิร์ด: ชื่อ/ประเภท/สเปก

/* ==== โหลดรายการอาคาร/ห้องสำหรับตัวกรอง ==== */
$buildings = $pdo->query("SELECT DISTINCT building FROM assets WHERE building <> '' ORDER BY building")->fetchAll();

if ($building !== '') {
  $stRooms = $pdo->prepare("SELECT DISTINCT room FROM assets WHERE building = ? AND room <> '' ORDER BY room");
  $stRooms->execute([$building]);
  $rooms = $stRooms->fetchAll();
} else {
  $rooms = $pdo->query("SELECT DISTINCT room FROM assets WHERE room <> '' ORDER BY room")->fetchAll();
}

/* ==== สร้างเงื่อนไขค้นหาแบบยืดหยุ่น ==== */
$where  = [];
$params = [];

if ($building !== '') {
  $where[]  = "building = ?";
  $params[] = $building;
}
if ($room !== '') {
  $where[]  = "room = ?";
  $params[] = $room;
}
if ($code !== '') {
  $where[]  = "asset_code LIKE ?";
  $params[] = "%$code%";
}
if ($q !== '') {
  $where[]  = "(name LIKE ? OR category LIKE ? OR specs LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $params[] = "%$q%";
}

$sql = "SELECT * FROM assets";
if ($where) {
  $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY building, room, name LIMIT 1000";

$st = $pdo->prepare($sql);
$st->execute($params);
$assets = $st->fetchAll();

layout_header('ฐานข้อมูลครุภัณฑ์');
?>

<div class="card p-0">
  <!-- แถบเครื่องมือ: เพิ่ม/นำเข้า/ตัวอย่าง + ฟอร์มค้นหา -->
  <div class="p-3 d-flex flex-wrap align-items-center gap-3">
    <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
      <a class="btn btn-primary btn-sm" href="new.php">+ เพิ่มครุภัณฑ์</a>

      <!-- นำเข้า CSV (ขนาดเล็ก) -->
      <form class="d-flex align-items-center gap-2" method="post" action="import_csv.php" enctype="multipart/form-data">
        <div class="input-group input-group-sm" style="max-width: 360px;">
          <input type="file" name="csv" class="form-control" accept=".csv" required>
          <button class="btn btn-outline-primary">นำเข้า CSV</button>
        </div>
      </form>

      <!-- ปุ่มดาวน์โหลดตัวอย่าง CSV -->
      <a class="btn btn-outline-secondary btn-sm" href="sample_csv.php">ตัวอย่าง CSV</a>
    <?php endif; ?>

    <!-- ฟอร์มค้นหา/กรอง -->
    <form class="ms-auto w-100 w-lg-auto" method="get" action="">
      <div class="row g-2">
        <div class="col-6 col-md-3">
          <select name="building" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">— ทุกอาคาร —</option>
            <?php foreach ($buildings as $b): $bv = (string)$b['building']; ?>
              <option value="<?= h($bv) ?>" <?= ($bv === $building ? 'selected' : '') ?>><?= h($bv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <select name="room" class="form-select form-select-sm">
            <option value="">— ทุกห้อง —</option>
            <?php foreach ($rooms as $r): $rv = (string)$r['room']; ?>
              <option value="<?= h($rv) ?>" <?= ($rv === $room ? 'selected' : '') ?>><?= h($rv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <input type="text" name="code" class="form-control form-control-sm" placeholder="รหัสครุภัณฑ์ (เช่น AC-01)" value="<?= h($code) ?>">
        </div>
        <div class="col-6 col-md-3">
          <div class="input-group input-group-sm">
            <input type="text" name="q" class="form-control" placeholder="คีย์เวิร์ด: ชื่อ/ประเภท/สเปก" value="<?= h($q) ?>">
            <button class="btn btn-primary">ค้นหา</button>
          </div>
        </div>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>รหัส</th>
          <th>ชื่อ</th>
          <th>ประเภท</th>
          <th>ที่ตั้ง</th>
          <th>สถานะ</th>
          <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
            <th style="width: 160px;">คำสั่ง</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php if (!$assets): ?>
          <tr><td colspan="<?= (($u['role'] ?? '') === 'ADMIN') ? 6 : 5 ?>" class="text-center text-muted py-4">ไม่พบรายการ</td></tr>
        <?php else: foreach ($assets as $a): ?>
          <tr>
            <td><a href="view.php?id=<?= (int)$a['id'] ?>"><?= h($a['asset_code']) ?></a></td>
            <td><?= h($a['name']) ?></td>
            <td><?= h($a['category']) ?></td>
            <td><?= h(trim($a['building'].' '.$a['room'])) ?></td>
            <td>
              <?php
                $st = $a['status'];
                $badge = 'secondary';
                if ($st === 'IN_SERVICE') $badge = 'success';
                if ($st === 'REPAIR')     $badge = 'warning text-dark';
                if ($st === 'DISPOSED')   $badge = 'dark';
              ?>
              <span class="badge bg-<?= $badge ?>"><?= h($st) ?></span>
            </td>
            <?php if (($u['role'] ?? '') === 'ADMIN'): ?>
              <td class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int)$a['id'] ?>">แก้ไข</a>
                <form method="post" action="delete.php" onsubmit="return confirm('ยืนยันลบครุภัณฑ์นี้?');">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">ลบ</button>
                </form>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_footer(); ?>
