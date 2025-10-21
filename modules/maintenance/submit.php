<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_login();
$pdo = db();

/* กันลืมตารางหลัก ๆ */
$pdo->exec("CREATE TABLE IF NOT EXISTS assets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  asset_code VARCHAR(100) UNIQUE,
  name VARCHAR(255) NOT NULL,
  category VARCHAR(100) DEFAULT '',
  building VARCHAR(100) DEFAULT '',
  room VARCHAR(100) DEFAULT '',
  serial_no VARCHAR(200) DEFAULT '',
  specs TEXT,
  status ENUM('IN_SERVICE','REPAIR','DISPOSED') DEFAULT 'IN_SERVICE',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

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

$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_ratings (
  id BIGINT NOT NULL AUTO_INCREMENT,
  ticket_id BIGINT NOT NULL UNIQUE,
  created_by VARCHAR(100) NOT NULL,
  score TINYINT NOT NULL,
  comment TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_created_by (created_by),
  INDEX idx_ticket_id (ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* บล็อกการแจ้งซ่อม ถ้ามีงาน DONE ของตัวเองที่ยังไม่ได้ประเมิน */
$me = $u['sso_username'] ?? ('dev:'.$u['id']);
$needRate = $pdo->prepare("
  SELECT t.id, t.title, t.location, t.created_at
  FROM maintenance_tickets t
  WHERE t.created_by = ?
    AND t.status = 'DONE'
    AND NOT EXISTS (
      SELECT 1 FROM maintenance_ratings r
      WHERE r.ticket_id = t.id AND r.created_by = t.created_by
    )
  ORDER BY t.created_at DESC
  LIMIT 20
");
$needRate->execute([$me]);
$pendingRatings = $needRate->fetchAll();

/* ข้อมูลตัวกรอง อาคาร/ห้อง/ครุภัณฑ์ */
$buildings = $pdo->query("SELECT DISTINCT building FROM assets WHERE building<>'' ORDER BY building")->fetchAll(PDO::FETCH_COLUMN);
$roomsByBuilding = [];
$assetsMap = [];

/* โหลดห้องและครุภัณฑ์ทั้งหมดแบบ cache ในเพจ (scale ได้ถึงหลักพันรายการ) */
$rows = $pdo->query("SELECT id,asset_code,name,building,room FROM assets ORDER BY building,room,name")->fetchAll();
foreach ($rows as $r) {
  $b = (string)$r['building'];
  $m = (string)$r['room'];

  if (!isset($roomsByBuilding[$b])) $roomsByBuilding[$b] = [];
  if ($m !== '' && !in_array($m, $roomsByBuilding[$b], true)) $roomsByBuilding[$b][] = $m;

  $key = $b.'|'.$m;
  if (!isset($assetsMap[$key])) $assetsMap[$key] = [];
  $assetsMap[$key][] = [
    'id'   => (int)$r['id'],
    'text' => trim(($r['asset_code'] ? $r['asset_code'].' • ' : '').$r['name'].' • '.$b.' '.$m)
  ];
}

layout_header('แจ้งซ่อมอาคาร/ครุภัณฑ์');
?>

<?php if ($pendingRatings): ?>
  <!-- การ์ดบล็อกการแจ้งซ่อม -->
  <div class="alert alert-warning border-0 shadow-sm">
    <div class="d-flex align-items-start">
      <div class="me-2 fs-5">⚠️</div>
      <div>
        <div class="fw-semibold mb-1">คุณมีงานซ่อมที่ปิดงานแล้ว แต่ยังไม่ได้ประเมินความพึงพอใจ</div>
        <div class="mb-2">โปรดประเมินก่อนจึงจะสามารถแจ้งซ่อมครั้งใหม่ได้</div>
        <ul class="mb-2">
          <?php foreach ($pendingRatings as $t): ?>
            <li>
              #<?= (int)$t['id'] ?> — <?= h($t['title']) ?> (<?= h($t['location']) ?>)
              <a class="ms-2" href="../ratings/rate.php?ticket_id=<?= (int)$t['id'] ?>">ประเมินเลย</a>
            </li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn-primary btn-sm" href="../ratings/index.php">ไปหน้าประเมินทั้งหมด</a>
      </div>
    </div>
  </div>
<?php else: ?>
  <!-- ฟอร์มแจ้งซ่อม -->
  <div class="card p-3">
    <form method="post" action="submit_save.php" enctype="multipart/form-data" class="row g-3">

      <!-- ตัวกรองอาคาร/ห้อง/ครุภัณฑ์ -->
      <div class="col-md-4">
        <label class="form-label">อาคาร</label>
        <select id="building" class="form-select">
          <option value="">— เลือกอาคาร —</option>
          <?php foreach ($buildings as $b): ?>
            <option value="<?= h($b) ?>"><?= h($b) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">ห้อง</label>
        <select id="room" class="form-select" disabled>
          <option value="">— เลือกห้อง —</option>
        </select>
      </div>

      <div class="col-md-4">
        <label class="form-label">เลือกครุภัณฑ์ (ถ้ามี)</label>
        <select id="asset_id" name="asset_id" class="form-select" disabled>
          <option value="">— ไม่ระบุครุภัณฑ์ —</option>
        </select>
      </div>

      <!-- สถานที่ (อาคาร/ห้อง) -->
      <div class="col-md-6">
        <label class="form-label">สถานที่ (อาคาร/ห้อง)</label>
        <input class="form-control" name="location" id="location" placeholder="เช่น อาคาร A ห้อง 204" required>
        <div class="form-text">ถ้าเลือกอาคาร/ห้องด้านบน ระบบจะเติมให้อัตโนมัติ</div>
      </div>

      <div class="col-md-6">
        <label class="form-label">หัวข้อปัญหา</label>
        <input class="form-control" name="title" required>
      </div>

      <div class="col-12">
        <label class="form-label">รายละเอียด</label>
        <textarea class="form-control" name="detail" rows="4" placeholder="อธิบายอาการ/สิ่งที่พบ"></textarea>
      </div>

      <div class="col-md-6">
        <label class="form-label">แนบรูป (ไม่บังคับ)</label>
        <input type="file" class="form-control" name="photo" accept="image/*">
      </div>

      <div class="col-12">
        <button class="btn btn-primary">ส่งแจ้งซ่อม</button>
      </div>
    </form>
  </div>
<?php endif; ?>

<script>
/* ข้อมูลห้องต่ออาคาร และครุภัณฑ์ต่อ (อาคาร|ห้อง) ที่ embed จาก PHP */
const roomsByBuilding = <?= json_encode($roomsByBuilding, JSON_UNESCAPED_UNICODE) ?>;
const assetsMap       = <?= json_encode($assetsMap, JSON_UNESCAPED_UNICODE) ?>;

const elB = document.getElementById('building');
const elR = document.getElementById('room');
const elA = document.getElementById('asset_id');
const elL = document.getElementById('location');

function syncRooms(){
  const b = elB.value || '';
  elR.innerHTML = '<option value="">— เลือกห้อง —</option>';
  elR.disabled = true;

  if (b && roomsByBuilding[b] && roomsByBuilding[b].length){
    roomsByBuilding[b].forEach(r => {
      const opt = document.createElement('option');
      opt.value = r; opt.textContent = r;
      elR.appendChild(opt);
    });
    elR.disabled = false;
  }
  syncAssets(); // เมื่อเปลี่ยนตึก ให้ล้าง/รีเฟรชรายการครุภัณฑ์
  syncLocation();
}

function syncAssets(){
  const b = elB.value || '';
  const r = elR.value || '';
  const key = b + '|' + r;

  elA.innerHTML = '<option value="">— ไม่ระบุครุภัณฑ์ —</option>';
  elA.disabled = true;

  if (assetsMap[key] && assetsMap[key].length){
    assetsMap[key].forEach(a => {
      const opt = document.createElement('option');
      opt.value = a.id;
      opt.textContent = a.text;
      elA.appendChild(opt);
    });
    elA.disabled = false;
  }
}

function syncLocation(){
  const b = elB.value || '';
  const r = elR.value || '';
  if (b && r){
    elL.value = b + ' ' + r;
  } else if (b){
    elL.value = b;
  }
}

elB && elB.addEventListener('change', () => { syncRooms(); });
elR && elR.addEventListener('change', () => { syncAssets(); syncLocation(); });
</script>

<?php layout_footer(); ?>
