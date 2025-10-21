<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* สร้าง/อัปเกรดสคีมา (รองรับ GENERIC + schedule_days) */
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target_type ENUM('CATEGORY','ASSET','GENERIC') NOT NULL,
  target_value VARCHAR(200) NOT NULL,
  times_per_year INT NOT NULL DEFAULT 2,
  description TEXT,
  schedule_days VARCHAR(255) DEFAULT '',
  active TINYINT(1) DEFAULT 1,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$colDays = $pdo->query("SHOW COLUMNS FROM pm_plans LIKE 'schedule_days'")->fetch();
if (!$colDays) {
  $pdo->exec("ALTER TABLE pm_plans ADD COLUMN schedule_days VARCHAR(255) DEFAULT '' AFTER description");
}
$colTarget = $pdo->query("SHOW COLUMNS FROM pm_plans LIKE 'target_type'")->fetch();
if ($colTarget && strpos($colTarget['Type'], 'GENERIC') === false) {
  $pdo->exec("ALTER TABLE pm_plans MODIFY target_type ENUM('CATEGORY','ASSET','GENERIC') NOT NULL");
}

$pdo->exec("CREATE TABLE IF NOT EXISTS pm_events (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  plan_id INT NOT NULL,
  asset_id INT NULL,
  title VARCHAR(255) NOT NULL,
  scheduled_date DATE NOT NULL,
  status ENUM('PENDING','IN_PROGRESS','DONE','CANCELLED') DEFAULT 'PENDING',
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(plan_id), INDEX(asset_id), INDEX(scheduled_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* ดึงข้อมูลเพื่อใช้กรอก */
$cats = $pdo->query("SELECT DISTINCT category FROM assets WHERE category<>'' ORDER BY category")->fetchAll();
$assetsStmt = $pdo->query("SELECT id, asset_code, name, building, room FROM assets ORDER BY asset_code, name LIMIT 2000");
$assets = $assetsStmt->fetchAll();

/* แผนทั้งหมด */
$plans = $pdo->query("SELECT * FROM pm_plans WHERE active = 1 ORDER BY id DESC")->fetchAll();

layout_header('วางแผนบำรุงรักษา');
?>

<div class="card p-3 mb-3">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0">สร้างแผน</h5>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="calendar.php">ปฏิทิน PM</a>
      <a class="btn btn-outline-secondary btn-sm" href="events.php?year=<?= date('Y') ?>">ตาราง PM ปีนี้</a>
    </div>
  </div>

  <form method="post" action="plan_save.php" class="row g-2">
    <div class="col-md-3">
      <label class="form-label mb-1">รูปแบบเป้าหมาย</label>
      <select class="form-select" name="target_type" id="target_type" required>
        <option value="CATEGORY">ตามประเภท (เช่น AIRCON, LIFT)</option>
        <option value="ASSET">ตามครุภัณฑ์ (ระบุ asset_id)</option>
        <option value="GENERIC">ทั่วไป/ไม่ผูกครุภัณฑ์</option>
      </select>
      <div class="form-text">
        - CATEGORY: สร้าง PM ให้ “ทุกชิ้น” ในประเภทนั้น<br>
        - ASSET: สร้าง PM ให้ครุภัณฑ์ “ชิ้นเดียว” ที่ระบุ<br>
        - GENERIC: กิจกรรมทั่วไป ไม่ผูกครุภัณฑ์ (เช่น ทำความสะอาดโถงชั้น 2)
      </div>
    </div>

    <!-- ค่าเป้าหมาย: ใช้ input ซ่อนจริง + ตัวเลือกแสดงผลตาม type -->
    <input type="hidden" name="target_value" id="target_value">

    <div class="col-md-3" id="wrap_category" style="display:none;">
      <label class="form-label mb-1">ค่าเป้าหมาย (ประเภท)</label>
      <select class="form-select" id="category_select">
        <option value="">— เลือกประเภท —</option>
        <?php foreach ($cats as $c): ?>
          <?php $cv = $c['category']; if ($cv==='') continue; ?>
          <option value="<?= h($cv) ?>"><?= h($cv) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">เลือกประเภทครุภัณฑ์ เช่น AIRCON / LIFT</div>
    </div>

    <div class="col-md-3" id="wrap_asset" style="display:none;">
      <label class="form-label mb-1">ค่าเป้าหมาย (ครุภัณฑ์)</label>
      <select class="form-select" id="asset_select">
        <option value="">— เลือกครุภัณฑ์ —</option>
        <?php foreach ($assets as $a): ?>
          <?php
            $label = '#'.$a['id'].' • '
                   . ($a['asset_code'] ? $a['asset_code'].' • ' : '')
                   . ($a['name'] ? $a['name'] : '')
                   . (($a['building'] || $a['room']) ? ' • '.$a['building'].' '.$a['room'] : '');
          ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($label) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">เลือกครุภัณฑ์ 1 ชิ้น (ระบบจะส่งค่าเป็น <code>asset_id=&lt;id&gt;</code>)</div>
    </div>

    <div class="col-md-3" id="wrap_generic" style="display:none;">
      <label class="form-label mb-1">ค่าเป้าหมาย (ทั่วไป)</label>
      <input class="form-control" id="generic_input" placeholder="เช่น ทำความสะอาดโถงชั้น 2">
      <div class="form-text">ใส่ชื่อกิจกรรม/พื้นที่สำหรับงานทั่วไปที่ไม่ผูกครุภัณฑ์</div>
    </div>

    <div class="col-md-2">
      <label class="form-label mb-1">ครั้งต่อปี</label>
      <input class="form-control" id="times_per_year" type="number" name="times_per_year" value="2" min="1" max="12" required oninput="toggleScheduleHelp()">
      <div class="form-text">จำนวนรอบ PM ใน 1 ปี</div>
    </div>

    <div class="col-md-4">
      <label class="form-label mb-1">วัน PM (กำหนดเองเป็น MM-DD)</label>
      <input class="form-control" id="schedule_days" name="schedule_days" placeholder="เช่น 03-15,09-15">
      <div class="form-text" id="schedule_hint">
        ถ้ากำหนด ระบบจะใช้วันที่นี้ทุกปี (รูปแบบ <b>MM-DD</b> คั่นด้วยจุลภาค) ตัวอย่าง: 03-15,09-15
      </div>
    </div>

    <div class="col-md-12">
      <label class="form-label mb-1">คำอธิบาย (ถ้ามี)</label>
      <input class="form-control" name="description" placeholder="เช่น ล้างกรอง/ตรวจแรงดัน">
    </div>

    <div class="col-md-12 d-flex justify-content-end">
      <button class="btn btn-primary">บันทึก</button>
    </div>
  </form>
</div>

<div class="card p-0">
  <div class="table-responsive">
    <table class="table mb-0">
      <thead class="table-light">
        <tr>
          <th style="width:80px">#</th>
          <th>เป้าหมาย</th>
          <th style="width:120px">ครั้ง/ปี</th>
          <th style="width:160px">วัน PM (ถ้ากำหนด)</th>
          <th style="width:100px">สถานะ</th>
          <th style="width:320px">คำสั่ง</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$plans): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">ยังไม่มีแผน</td></tr>
        <?php else: foreach ($plans as $p): ?>
          <tr>
            <td><?= (int)$p['id'] ?></td>
            <td><?= h($p['target_type'] . ': ' . $p['target_value']) ?></td>
            <td><?= (int)$p['times_per_year'] ?></td>
            <td><?= h($p['schedule_days']) ?></td>
            <td><span class="badge bg-secondary"><?= $p['active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="d-flex gap-2">
              <a class="btn btn-sm btn-outline-primary"
                 href="schedule.php?plan_id=<?= (int)$p['id'] ?>">🎯 สร้างตารางปีนี้</a>
              <a class="btn btn-sm btn-outline-secondary"
                 href="events.php?year=<?= date('Y') ?>">ดูตาราง</a>
              <form method="post" action="plan_delete.php" class="d-inline"
                    onsubmit="return confirm('ต้องการลบแผนนี้และเหตุการณ์ PM ที่เกี่ยวข้องทั้งหมดใช่หรือไม่?');">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">ลบ</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function toggleScheduleHelp(){
  var t = document.getElementById('times_per_year');
  var hint = document.getElementById('schedule_hint');
  if (!t || !hint) return;
  var n = parseInt(t.value || '0', 10);
  if (n >= 2) {
    hint.innerHTML = 'คุณตั้ง '+n+' ครั้ง/ปี — แนะนำให้กำหนด <b>'+n+'</b> วันที่ในรูปแบบ MM-DD คั่นด้วยจุลภาค เช่น 03-15,09-15';
  } else {
    hint.innerHTML = 'ถ้ากำหนด ระบบจะใช้วันที่นี้ทุกปี (รูปแบบ <b>MM-DD</b> คั่นด้วยจุลภาค) ตัวอย่าง: 03-15,09-15';
  }
}

function updateTargetUI(){
  var typeSel = document.getElementById('target_type');
  var targetVal = document.getElementById('target_value');

  var wCat = document.getElementById('wrap_category');
  var wAsset = document.getElementById('wrap_asset');
  var wGen = document.getElementById('wrap_generic');

  var selCat = document.getElementById('category_select');
  var selAsset = document.getElementById('asset_select');
  var inpGen = document.getElementById('generic_input');

  var t = (typeSel && typeSel.value) ? typeSel.value : 'CATEGORY';
  // ซ่อนทุกอันก่อน
  if (wCat) wCat.style.display = 'none';
  if (wAsset) wAsset.style.display = 'none';
  if (wGen) wGen.style.display = 'none';

  if (t === 'CATEGORY') {
    if (wCat) wCat.style.display = '';
    // sync ค่า
    if (targetVal && selCat) {
      targetVal.value = selCat.value || '';
    }
  } else if (t === 'ASSET') {
    if (wAsset) wAsset.style.display = '';
    if (targetVal && selAsset) {
      var idv = selAsset.value || '';
      targetVal.value = idv ? ('asset_id=' + idv) : '';
    }
  } else { // GENERIC
    if (wGen) wGen.style.display = '';
    if (targetVal && inpGen) {
      targetVal.value = inpGen.value || '';
    }
  }
}

/* sync เมื่อมีการเปลี่ยนค่าคอนโทรล */
document.getElementById('target_type').addEventListener('change', updateTargetUI);
var cs = document.getElementById('category_select');
if (cs) cs.addEventListener('change', updateTargetUI);
var as = document.getElementById('asset_select');
if (as) as.addEventListener('change', updateTargetUI);
var gi = document.getElementById('generic_input');
if (gi) gi.addEventListener('input', updateTargetUI);

/* เริ่มต้น: ตั้งเป็น CATEGORY และ sync ค่าครั้งแรก */
(function initDefault(){
  var typeSel = document.getElementById('target_type');
  if (typeSel) {
    // ค่าเริ่มตอนโหลด: CATEGORY
    typeSel.value = 'CATEGORY';
  }
  updateTargetUI();
  toggleScheduleHelp();
})();
</script>

<?php layout_footer(); ?>
