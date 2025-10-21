<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_login();
$pdo = db();

/* โหลดสถานะล่าสุดของผู้ใช้จากฐานข้อมูล เพื่อให้รู้ว่าเชื่อม LINE แล้ว/ยัง */
$refKey = $u['sso_username'] ?? null;
if ($refKey) {
    $st = $pdo->prepare("SELECT id, sso_username, name, email, role, line_user_id, created_at FROM users WHERE sso_username=? LIMIT 1");
    $st->execute([$refKey]);
    $fresh = $st->fetch();
    if ($fresh) {
        // รวมข้อมูลใหม่เข้ากับ session ปัจจุบัน เพื่อให้เมนู/ส่วนอื่น ๆ มองเห็นการเปลี่ยนแปลงทันที
        $_SESSION['user'] = array_merge($u, $fresh);
        $u = $_SESSION['user'];
    }
}

$lineId = $u['line_user_id'] ?? '';

layout_header('โปรไฟล์ของฉัน');
?>

<div class="card p-3 mb-3">
  <h5 class="mb-3">เชื่อมต่อ LINE</h5>

  <?php if (!empty($lineId)): ?>
    <div class="alert alert-success">
      เชื่อมต่อแล้ว (LINE userId: <code><?= h($lineId) ?></code>)
    </div>
    <form method="post" action="unlink.php" onsubmit="return confirm('ยกเลิกการเชื่อมต่อ LINE?');">
      <button class="btn btn-outline-danger">ยกเลิกการเชื่อมต่อ</button>
    </form>
  <?php else: ?>
    <div class="alert alert-warning">ยังไม่ได้เชื่อมต่อ LINE</div>

    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-primary" href="link_start.php">เชื่อมต่อ LINE</a>
      <?php if (defined('LINE_ADD_FRIEND_URL') && LINE_ADD_FRIEND_URL): ?>
        <a class="btn btn-outline-success" target="_blank" href="<?= h(LINE_ADD_FRIEND_URL) ?>">เพิ่มเพื่อน OA</a>
      <?php endif; ?>
    </div>

    <div class="small text-muted mt-2">
      * ต้อง “เพิ่มเพื่อน OA” ก่อน จึงจะรับการแจ้งเตือนได้
    </div>
  <?php endif; ?>
</div>

<?php layout_footer(); ?>
