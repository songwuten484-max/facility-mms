<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u  = require_login();
$pdo= db();

/* รับพารามิเตอร์ */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ดึงข้อมูลครุภัณฑ์ */
$sel = $pdo->prepare('SELECT * FROM assets WHERE id=? LIMIT 1');
$sel->execute([$id]);
$a = $sel->fetch();
if (!$a) { http_response_code(404); exit('not found'); }

/* ประวัติแจ้งซ่อมของครุภัณฑ์นี้ */
$rep = $pdo->prepare("
  SELECT t.*
  FROM maintenance_tickets t
  WHERE t.asset_id=?
  ORDER BY t.created_at DESC
");
$rep->execute([$id]);
$repRows = $rep->fetchAll();

/* ประวัติ PM ของครุภัณฑ์นี้ (ผูกกับ pm_events.asset_id) */
$pm = $pdo->prepare("
  SELECT e.*, p.target_type, p.target_value
  FROM pm_events e
  LEFT JOIN pm_plans p ON p.id = e.plan_id
  WHERE e.asset_id = ?
  ORDER BY e.scheduled_date DESC, e.id DESC
");
$pm->execute([$id]);
$pmRows = $pm->fetchAll();

/* ฟังก์ชันแปลงสถานะซ่อม → ป้ายสีไทย */
function ticket_status_label($st) {
  $map = [
    'OPEN'        => ['label'=>'⏳ รอดำเนินการ','class'=>'bg-warning text-dark'],
    'ASSIGNED'    => ['label'=>'🧰 มอบหมายช่าง','class'=>'bg-info text-dark'],
    'IN_PROGRESS' => ['label'=>'🔧 กำลังซ่อม','class'=>'bg-primary'],
    'DONE'        => ['label'=>'✅ เสร็จสิ้น','class'=>'bg-success'],
    'CANCELLED'   => ['label'=>'❌ ยกเลิก','class'=>'bg-secondary'],
  ];
  $m = isset($map[$st]) ? $map[$st] : ['label'=>$st,'class'=>'bg-light text-dark'];
  return '<span class="badge '.$m['class'].'">'.$m['label'].'</span>';
}

/* ฟังก์ชันแปลงสถานะ PM → ป้ายสีไทย */
function pm_status_label($st) {
  $map = [
    'PENDING'     => ['label'=>'⏳ รอทำ PM','class'=>'bg-warning text-dark'],
    'IN_PROGRESS' => ['label'=>'🔧 กำลังทำ PM','class'=>'bg-info text-dark'],
    'DONE'        => ['label'=>'✅ เสร็จสิ้น','class'=>'bg-success'],
    'CANCELLED'   => ['label'=>'❌ ยกเลิก','class'=>'bg-secondary'],
  ];
  $m = isset($map[$st]) ? $map[$st] : ['label'=>$st,'class'=>'bg-light text-dark'];
  return '<span class="badge '.$m['class'].'">'.$m['label'].'</span>';
}

layout_header('รายละเอียดครุภัณฑ์');
?>

<div class="card p-3 mb-3">
  <h5 class="mb-2"><?= h($a['asset_code'].' • '.$a['name']) ?></h5>
  <p class="mb-1"><b>ประเภท:</b> <?= h($a['category']) ?></p>
  <p class="mb-1"><b>ที่ตั้ง:</b> <?= h(trim($a['building'].' '.$a['room'])) ?></p>
  <p class="mb-1"><b>Serial:</b> <?= h($a['serial_no']) ?></p>
  <p class="mb-1"><b>สถานะ:</b> <span class="badge bg-secondary"><?= h($a['status']) ?></span></p>
  <p class="mb-0"><b>สเปก:</b><br><?= nl2br(h($a['specs'])) ?></p>
</div>

<div class="row g-3">
  <!-- ประวัติแจ้งซ่อม -->
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h6 class="mb-3">ประวัติแจ้งซ่อม</h6>
      <?php if (!$repRows): ?>
        <div class="text-muted">— ไม่มีประวัติแจ้งซ่อม —</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:90px;">เลขที่</th>
                <th>หัวข้อ</th>
                <th style="width:140px;">สถานะ</th>
                <th style="width:160px;">เมื่อ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($repRows as $t): ?>
                <tr>
                  <td>#<?= (int)$t['id'] ?></td>
                  <td><a href="../maintenance/view.php?id=<?= (int)$t['id'] ?>"><?= h($t['title']) ?></a></td>
                  <td><?= ticket_status_label($t['status']) ?></td>
                  <td><?= h($t['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ประวัติ PM/บำรุงรักษา -->
  <div class="col-md-6">
    <div class="card p-3 h-100">
      <h6 class="mb-3">ประวัติบำรุงรักษา (PM)</h6>
      <?php if (!$pmRows): ?>
        <div class="text-muted">— ไม่มีประวัติ PM สำหรับครุภัณฑ์นี้ —</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">กำหนด</th>
                <th>หัวข้อ/แผน</th>
                <th style="width:140px;">สถานะ</th>
                <th style="width:1%;white-space:nowrap;">ไปยังตาราง</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pmRows as $p): ?>
                <?php
                  // สรุปชื่อแผน (target_type: CATEGORY/ASSET/GENERIC)
                  $planInfo = '';
                  if ($p['target_type']==='CATEGORY') {
                    $planInfo = 'ประเภท: '.($p['target_value'] ?? '');
                  } elseif ($p['target_type']==='ASSET') {
                    $planInfo = 'เฉพาะครุภัณฑ์ (ID: '.(int)$id.')';
                  } elseif ($p['target_type']==='GENERIC') {
                    $planInfo = 'ทั่วไป';
                  }
                  $dayLink = '../pm/events.php?date=' . h($p['scheduled_date']);
                ?>
                <tr>
                  <td><?= h($p['scheduled_date']) ?></td>
                  <td>
                    <div class="fw-semibold"><?= h($p['title']) ?></div>
                    <div class="text-muted small"><?= h($planInfo) ?></div>
                  </td>
                  <td><?= pm_status_label($p['status']) ?></td>
                  <td><a class="btn btn-outline-primary btn-sm" href="<?= $dayLink ?>">ดู</a></td>
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
