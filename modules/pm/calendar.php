<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/layout.php';

$u   = require_role('ADMIN');
$pdo = db();

/* รับพารามิเตอร์เดือน-ปี */
$year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
if ($month < 1)  $month = 1;
if ($month > 12) $month = 12;

/* วันแรก/วันสุดท้ายของเดือน */
$firstDay   = mktime(0,0,0,$month,1,$year);
$daysInMon  = (int)date('t', $firstDay);
$startDow   = (int)date('N', $firstDay); // 1=Mon ... 7=Sun
$startDate  = date('Y-m-01', $firstDay);
$endDate    = date('Y-m-t',  $firstDay);

/* ดึงเหตุการณ์ของเดือนนี้ + ข้อมูลแผนเพื่อรู้เป้าหมาย */
$sql = "SELECT e.*,
               a.asset_code, a.name AS asset_name,
               p.target_type AS plan_target_type,
               p.target_value AS plan_target_value
        FROM pm_events e
        LEFT JOIN assets a   ON a.id = e.asset_id
        LEFT JOIN pm_plans p ON p.id = e.plan_id
        WHERE e.scheduled_date BETWEEN ? AND ?
        ORDER BY e.scheduled_date, e.id";
$st  = $pdo->prepare($sql);
$st->execute(array($startDate, $endDate));
$rows = $st->fetchAll();

/* จัดข้อมูลลงแต่ละวัน */
$eventsByDay = array();
foreach ($rows as $r) {
  $d = $r['scheduled_date'];
  if (!isset($eventsByDay[$d])) $eventsByDay[$d] = array();
  $eventsByDay[$d][] = $r;
}

/* ปุ่มเปลี่ยนเดือน */
$prevMonth = $month - 1; $prevYear = $year;
$nextMonth = $month + 1; $nextYear = $year;
if ($prevMonth < 1){ $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12){ $nextMonth = 1;  $nextYear++; }

layout_header('ปฏิทิน PM');
?>
<div class="card p-3 mb-3">
  <div class="d-flex align-items-center justify-content-between">
    <a class="btn btn-outline-secondary" href="?year=<?= (int)$prevYear ?>&month=<?= (int)$prevMonth ?>">‹ เดือนก่อนหน้า</a>
    <div class="d-flex align-items-center gap-2">
      <form class="row g-2" method="get" action="">
        <div class="col-auto">
          <select name="month" class="form-select">
            <?php for($m=1;$m<=12;$m++): ?>
              <option value="<?= $m ?>" <?= $m===$month?'selected':'' ?>><?= $m ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="col-auto">
          <input type="number" class="form-control" name="year" value="<?= (int)$year ?>">
        </div>
        <div class="col-auto">
          <button class="btn btn-primary">ไป</button>
        </div>
      </form>
    </div>
    <a class="btn btn-outline-secondary" href="?year=<?= (int)$nextYear ?>&month=<?= (int)$nextMonth ?>">เดือนถัดไป ›</a>
  </div>
</div>

<style>
  .calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:.5rem}
  .daycell{background:#fff;border:1px solid rgba(2,6,23,.06);border-radius:12px;min-height:120px;padding:.5rem}
  .daynum{font-weight:600;color:#0A3F9C}
  .badge-st{border-radius:999px}
  .event-line{font-size:.875rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
</style>

<div class="card p-3">
  <div class="calendar-grid">
    <?php
      $thaiDow = array('จันทร์','อังคาร','พุธ','พฤหัสฯ','ศุกร์','เสาร์','อาทิตย์');
      for($i=0;$i<7;$i++): ?>
        <div class="text-center fw-semibold text-muted"><?= $thaiDow[$i] ?></div>
    <?php endfor; ?>

    <?php for($i=1; $i<$startDow; $i++): ?><div></div><?php endfor; ?>

    <?php for($d=1; $d <= $daysInMon; $d++):
      $ymd = sprintf('%04d-%02d-%02d',$year,$month,$d);
      $evs = isset($eventsByDay[$ymd]) ? $eventsByDay[$ymd] : array();
      $url = 'events.php?date='.$ymd;
    ?>
      <div class="daycell">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <a href="<?= $url ?>" class="daynum text-decoration-none"><?= $d ?></a>
          <?php if (count($evs)>0): ?>
            <span class="badge bg-primary"><?= count($evs) ?></span>
          <?php endif; ?>
        </div>

        <?php
        /* ✅ ปรับเกณฑ์: ถ้าวันนี้มี ≥ 2 งาน ให้สรุปเป็นกลุ่มเป้าหมาย */
        $MANY_THRESHOLD = 2;

        if (count($evs) < $MANY_THRESHOLD):
          // แสดงทีละงาน (กรณีน้อยกว่า 2)
          foreach($evs as $e):
            $stColor = 'secondary';
            if ($e['status']==='PENDING') $stColor = 'warning';
            else if ($e['status']==='IN_PROGRESS') $stColor = 'info';
            else if ($e['status']==='DONE') $stColor = 'success';
            else if ($e['status']==='CANCELLED') $stColor = 'dark';

            $assetLabel = '';
            if (!empty($e['asset_code']) || !empty($e['asset_name'])) {
              $assetLabel = (empty($e['asset_code'])?'':$e['asset_code'].' • ')
                          . (empty($e['asset_name'])?'':$e['asset_name']).' — ';
            }
        ?>
          <div class="event-line">
            <span class="badge badge-st bg-<?= $stColor ?> me-1"><?= h($e['status']) ?></span>
            <a class="text-decoration-none" href="events.php?date=<?= $ymd ?>"><?= h($assetLabel.$e['title']) ?></a>
          </div>
        <?php
          endforeach;
        else:
          // สรุปเป็นกลุ่มตาม "เป้าหมาย"
          $groups = array();
          foreach($evs as $e){
            $ptype = isset($e['plan_target_type']) ? $e['plan_target_type'] : '';
            $pval  = isset($e['plan_target_value']) ? $e['plan_target_value'] : '';

            // label ย่อ: เน้น "เป้าหมาย" ไม่ใส่รายละเอียดชิ้นส่วนยาว ๆ
            if ($ptype === 'CATEGORY') {
              $label = $pval !== '' ? $pval : 'ประเภทไม่ระบุ';
            } elseif ($ptype === 'ASSET') {
              // สำหรับ ASSET ให้ใช้ asset_code/ชื่อย่อ
              if (!empty($e['asset_code']) || !empty($e['asset_name'])) {
                $label = ($e['asset_code'] ? $e['asset_code'].' • ' : '')
                       . ($e['asset_name'] ? $e['asset_name'] : '');
              } else {
                $label = 'ASSET #'.(int)$e['asset_id'];
              }
            } else { // GENERIC
              $label = $pval !== '' ? $pval : 'งานทั่วไป';
            }

            $key = $ptype.'||'.$label;
            if (!isset($groups[$key])) $groups[$key] = array('label'=>$label,'count'=>0);
            $groups[$key]['count']++;
          }

          // เรียงมาก→น้อย
          usort($groups, function($a,$b){
            if ($a['count']==$b['count']) return 0;
            return ($a['count'] > $b['count']) ? -1 : 1;
          });

          // แสดงกลุ่มสูงสุด 4 บรรทัด
          $maxShow = 4; $shown = 0;
          foreach($groups as $g){
            if ($shown >= $maxShow) break; $shown++;
        ?>
            <div class="event-line">
              <span class="badge badge-st bg-primary me-1"><?= (int)$g['count'] ?></span>
              <a class="text-decoration-none" href="events.php?date=<?= $ymd ?>"><?= h($g['label']) ?></a>
            </div>
        <?php
          }
          if (count($groups) > $maxShow){
            echo '<div class="text-muted small">...ดูทั้งหมด</div>';
          }
        endif;
        ?>
      </div>
    <?php endfor; ?>
  </div>
</div>

<?php layout_footer(); ?>
