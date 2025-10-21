<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_login();
$pdo = db();

$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(created_by), INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$me = $u['sso_username'] ?? ('dev:'.$u['id']);

// งานที่ต้องประเมิน (DONE แต่ยังไม่มี rating)
$needStmt = $pdo->prepare("
  SELECT t.id, t.title, t.location, t.created_at
  FROM maintenance_tickets t
  WHERE t.created_by = ?
    AND t.status = 'DONE'
    AND NOT EXISTS (
      SELECT 1 FROM maintenance_ratings r WHERE r.ticket_id = t.id AND r.created_by = t.created_by
    )
  ORDER BY t.created_at DESC
  LIMIT 500
");
$needStmt->execute([$me]);
$need = $needStmt->fetchAll();

// ประวัติที่เคยประเมินแล้ว
$hisStmt = $pdo->prepare("
  SELECT r.*, t.title, t.location, t.created_at AS ticket_created
  FROM maintenance_ratings r
  LEFT JOIN maintenance_tickets t ON t.id = r.ticket_id
  WHERE r.created_by = ?
  ORDER BY r.created_at DESC
  LIMIT 500
");
$hisStmt->execute([$me]);
$history = $hisStmt->fetchAll();

layout_header('ประเมินความพึงพอใจ');
?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3">
      <h5 class="mb-3">รายการที่ต้องประเมิน</h5>
      <?php if (!$need): ?>
        <div class="text-muted">ไม่มีรายการค้างประเมิน</div>
      <?php else: ?>
        <div class="list-group">
          <?php foreach ($need as $t): ?>
            <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
               href="rate.php?ticket_id=<?= (int)$t['id'] ?>">
              <span>
                #<?= (int)$t['id'] ?> • <?= h($t['title']) ?> — <?= h($t['location']) ?>
                <div class="small text-muted"><?= h($t['created_at']) ?></div>
              </span>
              <span class="btn btn-sm btn-outline-primary">ประเมิน</span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3">
      <h5 class="mb-3">ประวัติการประเมินของฉัน</h5>
      <?php if (!$history): ?>
        <div class="text-muted">ยังไม่มีประวัติ</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>#งาน</th>
                <th>หัวข้อ</th>
                <th>คะแนน</th>
                <th>เมื่อ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($history as $r): ?>
                <tr>
                  <td><?= (int)$r['ticket_id'] ?></td>
                  <td><?= h($r['title'] ?? '') ?></td>
                  <td><span class="badge bg-success"><?= (int)$r['score'] ?>/5</span></td>
                  <td><?= h($r['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php layout_footer(); ?>
