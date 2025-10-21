<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';

$BASE = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');

// ถ้าเข้าสู่ระบบแล้ว ให้กลับหน้าแรกเลย
if (function_exists('user') && user()) {
  header('Location: '.$BASE.'/index.php');
  exit;
}

// อ่านค่าสำหรับแสดงปุ่ม/ลิงก์เพิ่มเติม
$hasDev = defined('ALLOW_DEV_LOGIN') && (string)ALLOW_DEV_LOGIN === '1';
$lineAddUrl = defined('LINE_ADD_FRIEND_URL') ? LINE_ADD_FRIEND_URL : '';
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>เข้าสู่ระบบ • FBA Facilities</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{--fba-blue:#0B5ED7;--fba-navy:#0A3F9C;--bg:#f6f8fb}
    body{background:var(--bg)}
    .card{border-radius:16px;border:1px solid rgba(2,6,23,.06);box-shadow:0 14px 30px rgba(2,6,23,.08)}
    .brand{color:var(--fba-navy)}
    .logo{height:56px;width:auto}
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="row justify-content-center">
      <div class="col-md-6 col-lg-4">

       

        <div class="card p-3">
             <div class="text-center mb-4">
          <img class="logo mb-2" src="https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/public/img/logofba.png" alt="FBA">
          <div class="text-muted"></div>
          <h1 class="h5 fw-bold brand mb-1">ระบบจัดการงานอาคารสถานที่</h1>
          <div class="text-muted"></div>
          <img src="https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/public/img/sso-icit-account.png" alt="SSO Logo" style="height:120px; margin:8px;">
        </div>
          <!-- ปุ่ม SSO -->
          <a class="btn btn-primary w-100 mb-3" href="sso.php">
            เข้าสู่ระบบผ่าน KMUTNB SSO
          </a>

          <?php if (!empty($lineAddUrl)): ?>
            <a class="btn btn-outline-success w-100 mb-3" href="<?php echo htmlspecialchars($lineAddUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
              เพิ่มเพื่อน LINE OA (รับการแจ้งเตือน)
            </a>
          <?php endif; ?>

          
        </div>

        <div class="text-center text-muted mt-3 small">
          แจ้งปัญหาการใช้งานระบบ : คุณทรงวุฒิ พิกุลรตน์ วิศวกรไฟฟ้า โทร. 5526
        </div>

      </div>
    </div>
  </div>
</body>
</html>
