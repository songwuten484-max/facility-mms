<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_login();
$pdo = db();

/* รับพารามิเตอร์แบบเข้ากับ PHP เก่า */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ดึงรายละเอียดงานซ่อม + ชื่อผู้แจ้งจากตาราง users (ถ้ามี) */
$sel = $pdo->prepare('
  SELECT t.*, a.asset_code, a.name AS asset_name, u.name AS created_name, u.email AS created_email
  FROM maintenance_tickets t
  LEFT JOIN assets a ON a.id = t.asset_id
  LEFT JOIN users  u ON u.sso_username = t.created_by
  WHERE t.id = ?
  LIMIT 1
');
$sel->execute([$id]);
$t = $sel->fetch();

if (!$t) { http_response_code(404); exit('not found'); }

/* ถ้าเป็น USER ให้ดูได้เฉพาะของตนเอง */
$role = $u['role'] ?? '';
if ($role === 'USER') {
  $u_sso = $u['sso_username'] ?? ('dev:' . ($u['id'] ?? ''));
  $owner = ($t['created_by'] === $u_sso);
  if (!$owner) { http_response_code(403); exit('Forbidden'); }
}

/* ตาราง log (กันลืม) */
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL,
  by_user VARCHAR(100) NOT NULL,
  action VARCHAR(50) NOT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ดึง log */
$logs = $pdo->prepare('SELECT * FROM maintenance_logs WHERE ticket_id=? ORDER BY created_at');
$logs->execute([$id]);

/* แปลงสถานะเป็นภาษาไทย + สี */
function render_status_th($raw) {
  $map = [
    'OPEN'        => ['label' => '⏳ รอดำเนินการ',  'class' => 'bg-warning text-dark'],
    'ASSIGNED'    => ['label' => '🧰 มอบหมายช่าง', 'class' => 'bg-info text-dark'],
    'IN_PROGRESS' => ['label' => '🔧 กำลังซ่อม',    'class' => 'bg-primary'],
    'DONE'        => ['label' => '✅ เสร็จสิ้น',    'class' => 'bg-success'],
    'CANCELLED'   => ['label' => '❌ ยกเลิก',       'class' => 'bg-secondary'],
  ];
  $s = $map[$raw] ?? ['label'=>$raw, 'class'=>'bg-light text-dark'];
  return '<span class="badge '.$s['class'].'">'.$s['label'].'</span>';
}

layout_header('รายละเอียดงานซ่อม');
?>
<div class="card p-3 mb-3">
  <div class="row">
    <div class="col-md-7">
      <p class="mb-1"><b>หัวข้อ:</b> <?= h($t['title']) ?></p>
      <p class="mb-1"><b>สถานที่:</b> <?= h($t['location']) ?></p>
      <p class="mb-1"><b>ผู้แจ้ง:</b>
        <?= h($t['created_name'] ?: $t['created_by']) ?>
        <?php if (!empty($t['created_email'])): ?>
          <span class="text-muted"> (<?= h($t['created_email']) ?>)</span>
        <?php endif; ?>
      </p>
      <p class="mb-1"><b>ครุภัณฑ์:</b>
        <?= h(
          ($t['asset_code'] ? $t['asset_code'].' • ' : '') .
          ($t['asset_name'] ?? '—')
        ) ?>
      </p>
      <p class="mb-2"><b>รายละเอียด:</b><br><?= nl2br(h($t['detail'] ?? '')) ?></p>
      <p class="mb-0"><b>สถานะ:</b> <?= render_status_th($t['status']) ?></p>
    </div>
    <div class="col-md-5">
      <?php if (!empty($t['photo_path'])): ?>
        <img class="img-fluid rounded shadow-sm" src="../../<?= h($t['photo_path']) ?>" alt="แนบรูป">
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if ($role === 'ADMIN'): ?>
<div class="card p-3 mb-3">
  <form method="post" action="update.php" class="row g-2">
    <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
    <div class="col-auto">
      <select name="status" class="form-select">
        <?php
          $map_th = [
            'OPEN'        => '⏳ รอดำเนินการ',
            'ASSIGNED'    => '🧰 มอบหมายช่าง',
            'IN_PROGRESS' => '🔧 กำลังซ่อม',
            'DONE'        => '✅ เสร็จสิ้น',
            'CANCELLED'   => '❌ ยกเลิก'
          ];
          foreach ($map_th as $val => $label):
        ?>
          <option value="<?= $val ?>" <?= ($t['status']===$val ? 'selected' : '') ?>>
            <?= $label ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col">
      <input class="form-control" name="note" placeholder="หมายเหตุ (เช่น รายการอะไหล่)">
    </div>
    <div class="col-auto">
      <button class="btn btn-primary">บันทึก</button>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="card p-3">
  <h5 class="mb-3">ประวัติการดำเนินการ</h5>
  <?php if ($logs->rowCount() === 0): ?>
    <div class="text-muted">— ยังไม่มีประวัติ —</div>
  <?php else: ?>
    <ul class="mb-0">
      <?php foreach ($logs as $l): ?>
        <li>[<?= h($l['created_at']) ?>]
          <b><?= h($l['action']) ?></b>:
          <?= nl2br(h($l['note'] ?? '')) ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>
