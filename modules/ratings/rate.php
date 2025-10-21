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

$ticket_id = (int)($_GET['ticket_id'] ?? 0);

// โหลด ticket + ตรวจว่าเป็นของผู้ใช้
$me = $u['sso_username'] ?? ('dev:'.$u['id']);
$st = $pdo->prepare("SELECT * FROM maintenance_tickets WHERE id = ? LIMIT 1");
$st->execute([$ticket_id]);
$t = $st->fetch();

if (!$t || $t['created_by'] !== $me) { http_response_code(404); exit('not found'); }

// ต้อง DONE เท่านั้น
if ($t['status'] !== 'DONE') {
  layout_header('ไม่สามารถประเมินได้');
  echo '<div class="alert alert-warning">งานยังไม่ปิดงาน ไม่สามารถประเมินได้</div>';
  layout_footer();
  exit;
}

// ห้ามประเมินซ้ำ
$chk = $pdo->prepare("SELECT 1 FROM maintenance_ratings WHERE ticket_id=? AND created_by=?");
$chk->execute([$ticket_id, $me]);
if ($chk->fetch()) {
  layout_header('ประเมินแล้ว');
  echo '<div class="alert alert-success">คุณได้ประเมินงานนี้แล้ว</div>';
  layout_footer();
  exit;
}

layout_header('ประเมินความพึงพอใจ');
?>
<div class="card p-3">
  <h5 class="mb-3">ประเมินงานซ่อม #<?= (int)$t['id'] ?> — <?= h($t['title']) ?></h5>
  <p class="text-muted">สถานที่: <?= h($t['location']) ?></p>

  <form method="post" action="save.php" class="row g-3">
    <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">

    <div class="col-12">
      <label class="form-label">คะแนนความพึงพอใจ (1–5)</label>
      <div class="d-flex gap-2">
        <?php for ($i=1; $i<=5; $i++): ?>
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="score" id="sc<?= $i ?>" value="<?= $i ?>" <?= $i==5?'checked':'' ?>>
            <label class="form-check-label" for="sc<?= $i ?>"><?= $i ?></label>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="col-12">
      <label class="form-label">อื่นๆ (ข้อเสนอแนะ/คำติชม)</label>
      <textarea class="form-control" name="comment" rows="3" placeholder="พิมพ์ข้อความเพิ่มเติม (ถ้ามี)"></textarea>
    </div>

    <div class="col-12 d-flex justify-content-end">
      <a class="btn btn-outline-secondary me-2" href="index.php">ยกเลิก</a>
      <button class="btn btn-primary">บันทึกการประเมิน</button>
    </div>
  </form>
</div>
<?php layout_footer(); ?>
