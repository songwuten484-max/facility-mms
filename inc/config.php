<?php
/**
 * inc/config.php — safe defaults + absolute URLs
 * - ควบคุม error ตาม APP_ENV
 * - คำนวน APP_URL/PUBLIC_URL อัตโนมัติ
 * - สร้าง SSO_REDIRECT_URI แบบ absolute เสมอ
 * - ดึง secrets จาก ENV เป็นหลัก (ยังอนุญาต fallback เพื่อ dev)
 */

/* -------------------- ENV / MODE -------------------- */
if (!defined('APP_ENV')) {
  define('APP_ENV', getenv('APP_ENV') ?: 'dev'); // dev | prod
}

/* -------------------- ERROR HANDLING -------------------- */
if (APP_ENV === 'dev') {
  ini_set('display_errors', 1);
  ini_set('display_startup_errors', 1);
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', 0);
  error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
}

/* -------------------- SESSION -------------------- */
session_name('FBAMMSSESSID');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  'httponly' => true,
  'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

/* -------------------- URL HELPERS -------------------- */
/**
 * คำนวนฐาน URL ของโปรเจกต์แบบอัตโนมัติ
 * โครงสร้างแนะนำ:
 *   /fba-facility-mms/
 *     ├─ public/         (document root ชี้มาที่นี่)
 *     └─ modules/, inc/, ...
 */
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
/*
 * ถ้าไฟล์นี้ถูกเรียกผ่านหน้าในโฟลเดอร์ public ให้ถอยฐาน path ขึ้น 1 ระดับ
 * เช่น   /fba-facility-mms/public  => base path = /fba-facility-mms
 */
$basePath = $scriptDir;
if (substr($basePath, -7) === '/public') {
  $basePath = substr($basePath, 0, -7);
}
$basePath = rtrim($basePath, '/');

/** URL หลักของโปรเจกต์ (ไม่มี / ต่อท้าย) */
$APP_URL    = $scheme . '://' . $host . ($basePath ?: '');
/** URL โฟลเดอร์ public (ไม่มี / ต่อท้าย) */
$PUBLIC_URL = $APP_URL . '/public';

/* ให้ override ได้ด้วย ENV/define เดิมถ้าจำเป็น */
if (!defined('BASE_URL')) {
  // **คงไว้เพื่อเข้ากันย้อนหลัง**: BASE_URL = URL ของ public
  define('BASE_URL', '/fba-facility-mms'); 
}

/* -------------------- BRAND -------------------- */
if (!defined('BRAND_NAME_TH')) define('BRAND_NAME_TH', 'งานอาคารสถานที่และยานพาหนะ • FBA มจพ.ระยอง');
if (!defined('BRAND_NAME_EN')) define('BRAND_NAME_EN', 'FBA Facilities & Vehicle Services • KMUTNB Rayong');

/* -------------------- DATABASE -------------------- */
if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')    ?: '127.0.0.1');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')    ?: 'fba-facility-mms');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')    ?: 'fba-facility-mms');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASS')    ?: 'gnH!#987*'); // <- เปลี่ยน!
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/* -------------------- LINE (ดึงจาก ENV เป็นหลัก) -------------------- */
// Messaging API token
if (!defined('LINE_CHANNEL_TOKEN')) {
  define('LINE_CHANNEL_TOKEN', getenv('LINE_CHANNEL_TOKEN') ?: 'P1zDxMiy2JUi9k1bIfoTLYSSQWOGNjPsgNWyFr1wl4RrsQKJpJEP/UcgdrQQrliGj35RgrL2cVbBMrza5UsGZbaV6W9mP1yNzOqmg3XIlcM40jemFyDRyCI6S3MY5mLJuHTVBMjfCvd7QKEyGRgHPAdB04t89/1O/w1cDnyilFU=');
}
// LINE Login / LIFF
if (!defined('LINE_LOGIN_CHANNEL_ID'))     define('LINE_LOGIN_CHANNEL_ID',     getenv('LINE_LOGIN_CHANNEL_ID')     ?: '2008299648');
if (!defined('LINE_LOGIN_CHANNEL_SECRET')) define('LINE_LOGIN_CHANNEL_SECRET', getenv('LINE_LOGIN_CHANNEL_SECRET') ?: '4c0ac4d122bee04a9fadb0667a3cb2b2');
if (!defined('LINE_LIFF_ID'))              define('LINE_LIFF_ID',              getenv('LINE_LIFF_ID')              ?: '2008299648-M3eLqdyq');
if (!defined('LINE_ADD_FRIEND_URL'))       define('LINE_ADD_FRIEND_URL',       getenv('LINE_ADD_FRIEND_URL')       ?: 'https://lin.ee/9cUpMts'); // OA add friend (optional)





/* -------------------- KMUTNB SSO -------------------- */


define('SSO_AUTH_URL',  'https://sso.kmutnb.ac.th/auth/authorize');
define('SSO_TOKEN_URL', 'https://sso.kmutnb.ac.th/auth/token');
define('SSO_USER_URL',  'https://sso.kmutnb.ac.th/resources/userinfo');

define('SSO_CLIENT_ID',     'XIUzkghdE5ojn7uKiYEm78GDFWK8kwGZ');  // ต้องตรงกับใน Developer Console
define('SSO_CLIENT_SECRET', 'YCnFTBdtaCYjLgk8A0t9lm7so0JH6RvqReJTX60BLA0K4yEAgb2w1uq6MRE9rc5a');
define('SSO_REDIRECT_URI',  'https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/modules/auth/callback.php');






/**
 * ที่สำคัญ: ทำให้ Redirect URI เป็น **absolute URL** เสมอ
 * ตัวอย่างผลลัพธ์:
 *   https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/modules/auth/callback.php
 */
$defaultRedirect = $APP_URL . '/modules/auth/callback.php';
if (!defined('SSO_REDIRECT_URI')) {
  $envRedirect = getenv('SSO_REDIRECT_URI');
  define('SSO_REDIRECT_URI', $envRedirect ?: $defaultRedirect);
}

/* อนุญาต dev login (เช่นปุ่ม “เข้าสู่ระบบทดสอบ”) */
if (!defined('ALLOW_DEV_LOGIN')) define('ALLOW_DEV_LOGIN', getenv('ALLOW_DEV_LOGIN') ?: '1');
