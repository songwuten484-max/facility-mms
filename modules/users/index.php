<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u = require_role('ADMIN');
$pdo = db();

/* ensure schema */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sso_username VARCHAR(100) UNIQUE,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) DEFAULT '',
  line_user_id VARCHAR(64) DEFAULT '',
  role ENUM('ADMIN','USER') NOT NULL DEFAULT 'USER',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$rows = $pdo->query("SELECT * FROM users ORDER BY created_at DESC, id DESC LIMIT 1000")->fetchAll();

layout_header('จัดการผู้ใช้งาน');
?>
<div class="card p-3 mb-3 d-flex gap-2 align-items-center">
  <a class="btn btn-primary btn-sm" href="new.php">+ เพิ่มผู้ใช้งาน</a>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0 align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:70px">#</th>
          <th>SSO Username</th>
          <th>ชื่อ</th>
          <th>อีเมล</th>
          <th>LINE User ID</th>
          <th style="width:120px">บทบาท</th>
          <th style="width:160px">คำสั่ง</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h($r['sso_username']) ?></td>
          <td><?= h($r['name']) ?></td>
          <td><?= h($r['email']) ?></td>
          <td><code><?= h($r['line_user_id']) ?></code></td>
          <td>
            <?php
              $badge = ($r['role']==='ADMIN') ? 'danger' : 'secondary';
            ?>
            <span class="badge bg-<?= $badge ?>"><?= h($r['role']) ?></span>
          </td>
          <td class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-primary" href="edit.php?id=<?= (int)$r['id'] ?>">แก้ไข</a>
            <form method="post" action="delete.php" onsubmit="return confirm('แน่ใจว่าต้องการลบผู้ใช้นี้?');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="btn btn-sm btn-outline-danger">ลบ</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php layout_footer(); ?>
