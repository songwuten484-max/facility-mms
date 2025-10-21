<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

/**
 * Header + Navbar + Page Header
 * - แสดงเมนูตามสิทธิ์ (ADMIN / USER)
 * - ธีมฟ้า-น้ำเงิน-ขาว
 * - โลโก้ FBA (/public/img/logofba.png)
 * - ปุ่มโปรไฟล์แบบ Dropdown (โปรไฟล์ / ออกจากระบบ)
 */
function layout_header($title = '')
{
    // Base URL สำหรับลิงก์
    $BASE = BASE_URL;

    // ผู้ใช้ปัจจุบัน (จาก helpers.php)
    $u    = user();
    $role = $u['role'] ?? '';

    // ฟังก์ชันไฮไลท์เมนูปัจจุบัน
    $curr = $_SERVER['REQUEST_URI'] ?? '';
    $isActive = function ($needle) use ($curr) {
        return (strpos($curr, $needle) !== false) ? ' active' : '';
    };

    echo '<!doctype html><html lang="th"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>' . h($title) . '</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="' . $BASE . '/css/fleet.css" rel="stylesheet">
<style>
  .brand-logos img{height:28px;width:auto;}
  @media (min-width:992px){ .brand-logos img{height:32px;} }
  .navbar-brand { color:#0A3F9C!important; }
  .nav-link.active { font-weight: 600; }
  header.bg-gradient-primary{
    background: linear-gradient(45deg, #0A3F9C, #2B6CB0);
  }
</style>
</head><body>';

    // Navbar
    echo '
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top border-bottom">
  <div class="container">
    <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="' . $BASE . '/public/index.php">
      <span class="brand-logos d-flex align-items-center gap-2">
        <img src="' . $BASE . '/public/img/logofba.png" alt="FBA">
      </span>
      <span>FBA • Facilities & Vehicle Services</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-controls="nav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">';

    if ($u) {
        // เมนูสำหรับผู้ที่ล็อกอินแล้ว
        if ($role === 'ADMIN') {
            echo '
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/maintenance/submit.php') . '" href="' . $BASE . '/modules/maintenance/submit.php">แจ้งซ่อม</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/maintenance/list.php') . '" href="' . $BASE . '/modules/maintenance/list.php">งานซ่อม</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/assets/') . '" href="' . $BASE . '/modules/assets/index.php">ครุภัณฑ์</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/pm/plan.php') . '" href="' . $BASE . '/modules/pm/plan.php">วางแผน PM</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/pm/calendar.php') . '" href="' . $BASE . '/modules/pm/calendar.php">ปฏิทิน PM</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/inventory/') . '" href="' . $BASE . '/modules/inventory/index.php">คลังวัสดุ</a></li>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle' . ($isActive('/modules/ratings/') ? ' active' : '') . '" href="#" id="ratingMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
            ประเมินความพึงพอใจ
          </a>
          <ul class="dropdown-menu" aria-labelledby="ratingMenu">
            <li><a class="dropdown-item" href="' . $BASE . '/modules/ratings/index.php">แบบประเมินของฉัน</a></li>
            <li><a class="dropdown-item" href="' . $BASE . '/modules/ratings/admin.php">สรุป/รายงาน (ผู้ดูแล)</a></li>
          </ul>
        </li>

        <li class="nav-item"><a class="nav-link' . $isActive('/modules/users/') . '" href="' . $BASE . '/modules/users/index.php">ผู้ใช้งาน</a></li>';
        } else {
            // USER เห็นเมนูจำกัด
            echo '
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/maintenance/submit.php') . '" href="' . $BASE . '/modules/maintenance/submit.php">แจ้งซ่อม</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/maintenance/list.php') . '" href="' . $BASE . '/modules/maintenance/list.php">งานซ่อมของฉัน</a></li>
        <li class="nav-item"><a class="nav-link' . $isActive('/modules/ratings/') . '" href="' . $BASE . '/modules/ratings/index.php">ประเมินความพึงพอใจ</a></li>';
        }

        // โปรไฟล์ Dropdown (ซ่อนเมนูย่อย)
        $displayName = $u['name'] ?? ($u['sso_username'] ?? 'ผู้ใช้');
        echo '
      </ul>

      <div class="ms-lg-3 mt-3 mt-lg-0">
        <div class="dropdown">
          <button class="btn btn-outline-primary btn-sm dropdown-toggle d-flex align-items-center gap-2"
                  type="button" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i><span>' . h($displayName) . '</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="' . $BASE . '/modules/profile/index.php">โปรไฟล์</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="' . $BASE . '/modules/admin/logout.php">ออกจากระบบ</a></li>
          </ul>
        </div>
      </div>';
    } else {
        // ยังไม่ล็อกอิน → ปุ่มเข้าสู่ระบบ
        echo '</ul>
      <div class="d-flex align-items-center small ms-lg-3 mt-3 mt-lg-0">
        <a class="btn btn-primary btn-sm" href="' . $BASE . '/auth/login.php">เข้าสู่ระบบ</a>
      </div>';
    }

    echo '
    </div>
  </div>
</nav>

<header class="bg-gradient-primary text-white py-4">
  <div class="container">
    <h1 class="h4 mb-1">งานอาคารสถานที่และยานพาหนะ</h1>
    <p class="mb-0 opacity-75">คณะบริหารธุรกิจ มจพ.ระยอง</p>
  </div>
</header>

<main class="container my-4">';

    // Flash message (ถ้าใช้ใน helpers.php)
    if (function_exists('flash_has') && flash_has()) {
        echo '<div class="mb-3">' . flash_render() . '</div>';
    }
}

/** Footer + Scripts */
function layout_footer()
{
    echo '</main>
<footer class="bg-white border-top py-3 mt-4">
  <div class="container d-flex justify-content-between small text-muted">
    <span>© ' . date('Y') . ' FBA KMUTNB Rayong</span>
    <span>Fleet theme</span>
  </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body></html>';
}
