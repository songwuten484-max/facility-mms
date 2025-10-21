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

layout_header('เพิ่มผู้ใช้งาน');
?>
<div class="card p-3 mb-3 d-flex justify-content-between">
    <h5 class="mb-0">เพิ่มผู้ใช้งาน</h5>
    </div>
    <div class="card p-3 mb-3 d-flex justify-content-between">
  
  <a class="btn btn-outline-secondary" href="index.php">← กลับ</a>
</div>

<div class="card p-3">
  <form method="post" action="save.php" class="row g-3">
    <div class="col-md-4">
      <label class="form-label">SSO Username</label>
      <input class="form-control" name="sso_username" required>
      <div class="form-text">เช่น sXXXXX (KMUTNB SSO)</div>
    </div>
    <div class="col-md-4">
      <label class="form-label">ชื่อ-นามสกุล</label>
      <input class="form-control" name="name" required>
    </div>
    <div class="col-md-4">
      <label class="form-label">อีเมล</label>
      <input class="form-control" type="email" name="email">
    </div>

    <div class="col-md-6">
      <label class="form-label">LINE User ID</label>
      <input class="form-control" name="line_user_id" placeholder="ถ้าผูกไลน์ไว้">
    </div>
    <div class="col-md-6">
      <label class="form-label">บทบาท</label>
      <select class="form-select" name="role">
        <option value="USER" selected>USER</option>
        <option value="ADMIN">ADMIN</option>
      </select>
    </div>

    <div class="col-12 d-flex justify-content-end">
      <button class="btn btn-primary">บันทึก</button>
    </div>
  </form>
</div>
<?php layout_footer(); ?>
