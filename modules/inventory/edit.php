<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* กันลืม: ตารางหลัก */
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50) DEFAULT '',
  stock DECIMAL(12,2) DEFAULT 0,
  min_stock DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับ id */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  exit('Invalid ID');
}

/* ดึงข้อมูลเดิม */
$sel = $pdo->prepare("SELECT * FROM inventory_items WHERE id = ? LIMIT 1");
$sel->execute([$id]);
$item = $sel->fetch();
if (!$item) {
  http_response_code(404);
  exit('Not found');
}

/* แจ้งผล */
$err = '';
$ok  = '';

/* บันทึกเมื่อ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $sku       = trim($_POST['sku'] ?? '');
  $name      = trim($_POST['name'] ?? '');
  $unit      = trim($_POST['unit'] ?? '');
  $stock     = isset($_POST['stock']) ? (float)$_POST['stock'] : 0;
  $min_stock = isset($_POST['min_stock']) ? (float)$_POST['min_stock'] : 0;

  if ($name === '') {
    $err = 'กรุณากรอกชื่อวัสดุ';
  } else {
    try {
      $upd = $pdo->prepare("UPDATE inventory_items
        SET sku = ?, name = ?, unit = ?, stock = ?, min_stock = ?
        WHERE id = ? LIMIT 1");
      $upd->execute([$sku, $name, $unit, $stock, $min_stock, $id]);

      // โหลดข้อมูลใหม่อีกรอบ
      $sel->execute([$id]);
      $item = $sel->fetch();

      if (function_exists('flash_set')) {
        flash_set('บันทึกการแก้ไขเรียบร้อย', 'success');
        header('Location: index.php');
        exit;
      } else {
        $ok = 'บันทึกการแก้ไขเรียบร้อย';
      }
    } catch (PDOException $e) {
      // จัดการกรณี sku ซ้ำ (Duplicate entry)
      if ((int)$e->getCode() === 23000) {
        $err = 'SKU นี้ถูกใช้แล้ว กรุณาเปลี่ยนค่าใหม่';
      } else {
        $err = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
      }
    }
  }
}

layout_header('แก้ไขวัสดุ');
?>
<div class="card p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="mb-0">แก้ไขวัสดุ</h5>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" href="index.php">← กลับรายการ</a>
    <a class="btn btn-outline-primary btn-sm" href="view.php?id=<?= (int)$item['id'] ?>">ดูรายละเอียด</a>
  </div>
</div>

<?php if ($err): ?>
  <div class="alert alert-danger"><?= h($err) ?></div>
<?php endif; ?>
<?php if ($ok): ?>
  <div class="alert alert-success"><?= h($ok) ?></div>
<?php endif; ?>

<div class="card p-3">
  <form method="post" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">SKU</label>
      <input class="form-control" name="sku" value="<?= h($item['sku']) ?>" placeholder="ระบุรหัส (ถ้ามี)">
      <div class="form-text">ต้องไม่ซ้ำกันในระบบ</div>
    </div>

    <div class="col-md-8">
      <label class="form-label">ชื่อวัสดุ <span class="text-danger">*</span></label>
      <input class="form-control" name="name" value="<?= h($item['name']) ?>" required>
    </div>

    <div class="col-md-4">
      <label class="form-label">หน่วยนับ</label>
      <input class="form-control" name="unit" value="<?= h($item['unit']) ?>" placeholder="เช่น ชิ้น, กล่อง, เมตร">
    </div>

    <div class="col-md-4">
      <label class="form-label">คงเหลือ (Stock)</label>
      <input class="form-control" type="number" step="0.01" name="stock" value="<?= h($item['stock']) ?>">
    </div>

    <div class="col-md-4">
      <label class="form-label">จุดสั่งซื้อ (Min Stock)</label>
      <input class="form-control" type="number" step="0.01" name="min_stock" value="<?= h($item['min_stock']) ?>">
    </div>

    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary">บันทึกการแก้ไข</button>
    </div>
  </form>
</div>

<?php layout_footer(); ?>
