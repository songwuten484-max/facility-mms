<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u = require_login();
$pdo = db();

/* ดึงข้อมูลตามสิทธิ์ */
if (($u['role'] ?? '') === 'USER') {
  $stmt = $pdo->prepare("
    SELECT t.*, a.asset_code, a.name AS asset_name
    FROM maintenance_tickets t
    LEFT JOIN assets a ON a.id = t.asset_id
    WHERE t.created_by = ?
    ORDER BY t.created_at DESC
    LIMIT 500
  ");
  $stmt->execute([$u['sso_username'] ?? ('dev:' . $u['id'])]);
  $tickets = $stmt->fetchAll();
} else {
  $tickets = $pdo->query("
    SELECT t.*, a.asset_code, a.name AS asset_name
    FROM maintenance_tickets t
    LEFT JOIN assets a ON a.id = t.asset_id
    ORDER BY t.created_at DESC
    LIMIT 500
  ")->fetchAll();
}

/* ฟังก์ชันแปลงสถานะเป็นภาษาไทย + สี */
function render_status($raw) {
  $map = [
    'OPEN'         => ['label' => '⏳ รอดำเนินการ', 'class' => 'bg-warning text-dark'],
    'ASSIGNED'     => ['label' => '🧰 มอบหมายช่าง', 'class' => 'bg-info text-dark'],
    'IN_PROGRESS'  => ['label' => '🔧 กำลังซ่อม', 'class' => 'bg-primary'],
    'DONE'         => ['label' => '✅ เสร็จสิ้น', 'class' => 'bg-success'],
    'CANCELLED'    => ['label' => '❌ ยกเลิก', 'class' => 'bg-secondary'],
  ];
  $s = isset($map[$raw]) ? $map[$raw] : ['label'=>$raw, 'class'=>'bg-light text-dark'];
  return '<span class="badge '.$s['class'].'">'.$s['label'].'</span>';
}

layout_header('รายการแจ้งซ่อม');
?>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:70px;">#</th>
          <th>หัวข้อ</th>
          <th>สถานที่</th>
          <th>ครุภัณฑ์</th>
          <th style="width:160px;">สถานะ</th>
          <th style="width:180px;">เมื่อ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$tickets): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีการแจ้งซ่อม</td></tr>
        <?php else: foreach ($tickets as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td>
            <td><a href="view.php?id=<?= (int)$t['id'] ?>" class="fw-semibold text-decoration-none"><?= h($t['title']) ?></a></td>
            <td><?= h($t['location']) ?></td>
            <td><?= h(($t['asset_code'] ? $t['asset_code'].' • ' : '').($t['asset_name'] ?? '—')) ?></td>
            <td><?= render_status($t['status']) ?></td>
            <td><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php layout_footer(); ?>
