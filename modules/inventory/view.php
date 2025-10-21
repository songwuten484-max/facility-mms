<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* ตารางทรานแซกชัน (ถ้ายังไม่มีให้สร้าง) */
$pdo->exec("CREATE TABLE IF NOT EXISTS inventory_txns (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  ticket_id BIGINT NULL,
  type ENUM('IN','OUT') NOT NULL,
  qty DECIMAL(12,2) NOT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(item_id), INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับพารามิเตอร์ */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ดึงข้อมูลวัสดุ */
$stmt = $pdo->prepare('SELECT * FROM inventory_items WHERE id = ? LIMIT 1');
$stmt->execute(array($id));
$it = $stmt->fetch();
if (!$it) { http_response_code(404); exit('Item not found'); }

/* ดึงรายการงานซ่อมที่ยังไม่เสร็จ (ไว้ให้เลือกผูก) */
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_tickets (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  created_by VARCHAR(100) NOT NULL,
  asset_id INT NULL,
  location VARCHAR(255) NOT NULL,
  title VARCHAR(255) NOT NULL,
  detail TEXT,
  photo_path VARCHAR(255) DEFAULT '',
  status ENUM('OPEN','ASSIGNED','IN_PROGRESS','DONE','CANCELLED') DEFAULT 'OPEN',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(asset_id), INDEX(created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stOpen = $pdo->query("
  SELECT id, title, location, status
  FROM maintenance_tickets
  WHERE status IN ('OPEN','ASSIGNED','IN_PROGRESS')
  ORDER BY created_at DESC
  LIMIT 500
");
$openTickets = $stOpen->fetchAll();

/* ดึงประวัติรับ-จ่ายของวัสดุ */
$stmt2 = $pdo->prepare('SELECT * FROM inventory_txns WHERE item_id = ? ORDER BY created_at DESC');
$stmt2->execute(array($id));
$tx = $stmt2->fetchAll();

layout_header('รายละเอียดวัสดุ');
?>
<div class="card p-3 mb-3">
  <form method="post" action="txn.php" class="row g-2">
    <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">

    <div class="col-auto">
      <select class="form-select" name="type">
        <option>IN</option>
        <option>OUT</option>
      </select>
    </div>

    <div class="col-auto">
      <input class="form-control" type="number" step="0.01" name="qty" placeholder="จำนวน" required>
    </div>

    <!-- ผูกงานซ่อม: เลือกได้เฉพาะงานที่ยังไม่เสร็จ -->
    <div class="col-md-4">
      <select class="form-select" name="ticket_id">
        <option value="">— ไม่ผูกกับงานซ่อม —</option>
        <?php foreach ($openTickets as $tk): ?>
          <?php
            $label = '#'.$tk['id'].' • '.($tk['title'] ? $tk['title'] : '').' • '.($tk['location'] ? $tk['location'] : '');
            $st_th = $tk['status']=='OPEN' ? 'รอดำเนินการ' : ($tk['status']=='ASSIGNED' ? 'มอบหมายช่าง' : 'กำลังซ่อม');
          ?>
          <option value="<?= (int)$tk['id'] ?>">[<?= h($st_th) ?>] <?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">เลือกงานซ่อมที่ยังไม่เสร็จ เพื่อผูกเบิกวัสดุเข้ากับงานนั้น</div>
    </div>

    <div class="col">
      <input class="form-control" name="note" placeholder="หมายเหตุ (ใบเบิก/ที่มา)">
    </div>

    <div class="col-auto">
      <button class="btn btn-primary">บันทึก</button>
    </div>
  </form>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th>เมื่อ</th>
          <th>ประเภท</th>
          <th>จำนวน</th>
          <th>ผูกงานซ่อม</th>
          <th>หมายเหตุ</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tx as $t): ?>
        <tr>
          <td><?= h($t['created_at']) ?></td>
          <td><?= h($t['type']) ?></td>
          <td><?= h($t['qty']) ?></td>
          <td>
            <?php if (!empty($t['ticket_id'])): ?>
              <a href="../maintenance/view.php?id=<?= (int)$t['ticket_id'] ?>" class="text-decoration-none">
                #<?= (int)$t['ticket_id'] ?>
              </a>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td><?= h(isset($t['note']) ? $t['note'] : '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer(); ?>
