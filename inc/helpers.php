<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Escape HTML อย่างปลอดภัย
 * ใช้: <?= h($var) ?>
 */
function h($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** alias สั้น ๆ ของ h() */
function e($v) {
    return h($v);
}

/** ข้อมูลผู้ใช้จาก session (หรือ null ถ้าไม่ล็อกอิน) */
function user() {
    return $_SESSION['user'] ?? null;
}

/** ต้องล็อกอินเท่านั้น ไม่งั้นพาไปหน้า login */
function require_login() {
    $u = user();
    if (!$u) {
        $base = rtrim((string)(defined('BASE_URL') ? BASE_URL : ''), '/');
        header('Location: https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/modules/auth/login.php');
        exit;
    }
    return $u;
}

/**
 * ต้องมี role ที่กำหนด (ค่าเริ่มต้น ADMIN)
 * ถ้าไม่ตรง -> 403
 */
function require_role($role = 'ADMIN') {
    $u = require_login();
    if (($u['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Forbidden');
    }
    return $u;
}

/** เช็กบทบาทอย่างง่าย */
function is_admin() { return (user()['role'] ?? '') === 'ADMIN'; }
function is_user()  { return (user()['role'] ?? '') === 'USER'; }

/** redirect สั้น ๆ */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/* ---------- Flash message helpers ---------- */
function flash_set($msg, $type = 'info') {
    $_SESSION['flash'][] = ['t' => $type, 'm' => (string)$msg];
}
function flash_has() {
    return !empty($_SESSION['flash']);
}
function flash_render() {
    $html = '';
    foreach (($_SESSION['flash'] ?? []) as $f) {
        $cls = [
            'success' => 'success',
            'error'   => 'danger',
            'info'    => 'info',
            'warning' => 'warning'
        ][$f['t']] ?? 'info';
        $html .= '<div class="alert alert-' . $cls . '">' . h($f['m']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}
