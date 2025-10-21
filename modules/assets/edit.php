<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u = require_login();
if (($u['role'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  exit('forbidden');
}

$pdo = db();

/* กันลืม schema */
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { http_response_code(400); exit('invalid id'); }

/* โหลดข้อมูลเดิม */
$sel = $pdo->prepare('SELECT * FROM assets WHERE id=? LIMIT 1');
$sel->execute([$id]);
$asset = $sel->fetch();
if (!$asset) { http_response_code(404); exit('not found'); }

/* ผลการบันทึก */
$err = '';
$ok  = '';

/* บันทึกเมื่อ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $asset_code = trim($_POST['asset_code'] ?? '');
  $name       = trim($_POST['name'] ?? '');
  $category   = trim($_POST['category'] ?? '');
  $building   = trim($_POST['building'] ?? '');
  $room       = trim($_POST['room'] ?? '');
  $serial_no  = trim($_POST['serial_no'] ?? '');
  $specs      = trim($_POST['specs'] ?? '');
  $status     = trim($_POST['status'] ?? 'IN_SERVICE');

  if ($asset_code === '' || $name === '') {
    $err = 'กรุณากรอก รหัสครุภัณฑ์ และ ชื่อครุภัณฑ์ ให้ครบถ้วน';
  } else {
    try {
      // ตรวจ asset_code ซ้ำกับตัวอื่น
      $chk = $pdo->prepare('SELECT id FROM assets WHERE asset_code=? AND id<>? LIMIT 1');
      $chk->execute([$asset_code, $id]);
      if ($chk->fetch()) {
        $err = 'รหัสครุภัณฑ์นี้ถูกใช้แล้ว กรุณาใช้รหัสอื่น';
      } else {
        $upd = $pdo->prepare('UPDATE assets
          SET asset_code=?, name=?, category=?, building=?, room=?, serial_no=?, specs=?, status=?
          WHERE id=?');
        $upd->execute([$asset_code, $name, $category, $building, $room, $serial_no, $specs, $status, $id]);

        // อัปเดตตัวแปรแสดงผลหน้าฟอร์ม
        $asset['asset_code'] = $asset_code;
        $asset['name']       = $name;
        $asset['category']   = $category;
        $asset['building']   = $building;
        $asset['room']       = $room;
        $asset['serial_no']  = $serial_no;
        $asset['specs']      = $specs;
        $asset['status']     = $status;

        $ok = 'บันทึกสำเร็จ';
      }
    } catch (PDOException $e) {
      // ป้องกัน error message หลุดออกยาวเกินไป
      $err = 'บันทึกไม่สำเร็จ: ' . (stripos($e->getMessage(), 'Duplicate') !== false ? 'รหัสครุภัณฑ์ซ้ำ' : 'ข้อผิดพลาดระบบ');
    }
  }
}

layout_header('แก้ไขครุภัณฑ์');
?>
<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center">
    <h5 class="mb-0">แก้ไขครุภัณฑ์ #<?= (int)$asset['id'] ?></h5>
    <div>
      <a class="btn btn-outline-secondary" href="index.php">← กลับรายการ</a>
    </div>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php elseif ($ok): ?>
  <div class="alert alert-success"><?= h($ok) ?></div>
<?php endif; ?>

<div class="card p-3">
  <form method="post" action="" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">รหัสครุภัณฑ์ (Unique)</label>
      <input class="form-control" name="asset_code" value="<?= h($asset['asset_code']) ?>" required>
    </div>

    <div class="col-md-8">
      <label class="form-label">ชื่อครุภัณฑ์</label>
      <input class="form-control" name="name" value="<?= h($asset['name']) ?>" required>
    </div>

    <div class="col-md-4">
      <label class="form-label">ประเภท</label>
      <input class="form-control" name="category" value="<?= h($asset['category']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">อาคาร</label>
      <input class="form-control" name="building" value="<?= h($asset['building']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">ห้อง</label>
      <input class="form-control" name="room" value="<?= h($asset['room']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">Serial No.</label>
      <input class="form-control" name="serial_no" value="<?= h($asset['serial_no']) ?>">
    </div>

    <div class="col-md-6">
      <label class="form-label">สถานะ</label>
      <?php
        $st = $asset['status'];
        $opts = ['IN_SERVICE'=>'พร้อมใช้งาน', 'REPAIR'=>'กำลังซ่อม', 'DISPOSED'=>'จำหน่ายทิ้ง'];
      ?>
      <select class="form-select" name="status">
        <?php foreach($opts as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $st===$val?'selected':'' ?>><?= $val ?> — <?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12">
      <label class="form-label">สเปก / รายละเอียด</label>
      <textarea class="form-control" name="specs" rows="5"><?= h($asset['specs']) ?></textarea>
    </div>

    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary">บันทึกการแก้ไข</button>
    </div>
  </form>
</div>

<?php layout_footer(); ?>
