<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* สร้างตาราง inventory_items ถ้ายังไม่มี */
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  unit VARCHAR(50) DEFAULT '',
  stock DECIMAL(12,2) DEFAULT 0,
  min_stock DECIMAL(12,2) DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ดึงข้อมูลวัสดุทั้งหมด */
$items = $pdo->query('SELECT * FROM inventory_items ORDER BY name')->fetchAll();

layout_header('คลังวัสดุ');
?>
<div class="card p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="mb-0">รายการวัสดุ</h5>
  <a class="btn btn-primary" href="new.php">+ เพิ่มวัสดุ</a>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>SKU</th>
          <th>ชื่อ</th>
          <th>คงเหลือ</th>
          <th>จุดสั่งซื้อ</th>
          <th style="width:160px;">คำสั่ง</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$items): ?>
          <tr><td colspan="5" class="text-center text-muted py-4">ยังไม่มีข้อมูลวัสดุ</td></tr>
        <?php else: foreach ($items as $it): ?>
          <tr>
            <td><?= h($it['sku']) ?></td>
            <td><a href="view.php?id=<?= (int)$it['id'] ?>"><?= h($it['name']) ?></a></td>
            <td><?= h($it['stock'] . ' ' . $it['unit']) ?></td>
            <td><?= h($it['min_stock'] . ' ' . $it['unit']) ?></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int)$it['id'] ?>">แก้ไข</a>
              <form method="post" action="delete.php" onsubmit="return confirm('ยืนยันลบวัสดุนี้หรือไม่?');">
                <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_footer(); ?>
