<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/helpers.php';

if (!isset($_GET['code'], $_GET['state']) || ($_GET['state'] ?? '') !== ($_SESSION['oauth_state'] ?? '')) {
    http_response_code(400);
    exit('Invalid state');
}

$code = $_GET['code'];

/* 1) ขอ access_token */
$ch = curl_init(SSO_TOKEN_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => SSO_REDIRECT_URI,
        'client_id'     => SSO_CLIENT_ID,
        'client_secret' => SSO_CLIENT_SECRET,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
]);
$tokenResp = curl_exec($ch);
if ($tokenResp === false) exit('Token request failed: ' . curl_error($ch));
curl_close($ch);

$token = json_decode($tokenResp, true);
$access_token = $token['access_token'] ?? null;
if (!$access_token) {
    exit('No access_token found: ' . $tokenResp);
}

/* 2) ขอข้อมูลผู้ใช้จาก SSO */
$ch = curl_init(SSO_USER_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $access_token],
]);
$userJson = curl_exec($ch);
curl_close($ch);
$me = json_decode($userJson, true);

if (!isset($me['profile'])) {
    exit('<pre>Unexpected response structure: ' . htmlspecialchars($userJson) . '</pre>');
}
$profile = $me['profile'];

/* 3) ดึงข้อมูลหลัก */
$sso_username = $profile['username'] ?? null;
$display_name = $profile['display_name'] ?? '';
$email = $profile['email'] ?? '';
$person_key = $profile['person_key'] ?? '';
$account_type = $profile['account_type'] ?? '';

if (!$sso_username) {
    exit('Cannot resolve username from SSO (profile.username not found)');
}

/* 4) สร้าง/อัปเดตผู้ใช้ในฐานข้อมูล */
$pdo = db();

/* --- ensure users schema (create if missing) --- */
$pdo->exec("CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sso_username VARCHAR(100) UNIQUE,
  name VARCHAR(200) NOT NULL,
  email VARCHAR(200) DEFAULT '',
  line_user_id VARCHAR(64) DEFAULT '',
  role ENUM('ADMIN','USER') NOT NULL DEFAULT 'USER',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* --- migrate columns if this table existed before --- */
$existingCols = [];
$stmtCols = $pdo->query("SHOW COLUMNS FROM users");
foreach ($stmtCols as $c) { $existingCols[] = $c['Field']; }

if (!in_array('person_key', $existingCols, true)) {
  $pdo->exec("ALTER TABLE users ADD COLUMN person_key VARCHAR(32) DEFAULT '' AFTER email");
}
if (!in_array('account_type', $existingCols, true)) {
  $pdo->exec("ALTER TABLE users ADD COLUMN account_type VARCHAR(32) DEFAULT '' AFTER person_key");
}

/* --- upsert user --- */
$sel = $pdo->prepare("SELECT * FROM users WHERE sso_username=? LIMIT 1");
$sel->execute([$sso_username]);
$user = $sel->fetch();

if (!$user) {
  $ins = $pdo->prepare("
    INSERT INTO users (sso_username, name, email, person_key, account_type, role)
    VALUES (?, ?, ?, ?, ?, 'USER')
  ");
  $ins->execute([$sso_username, $display_name, $email, $person_key, $account_type]);

  $sel->execute([$sso_username]);
  $user = $sel->fetch();
} else {
  $upd = $pdo->prepare("
    UPDATE users SET name=?, email=?, person_key=?, account_type=? WHERE id=?
  ");
  $upd->execute([$display_name, $email, $person_key, $account_type, $user['id']]);
}


/* 5) ตั้ง session แล้วเข้าสู่ระบบ */
$_SESSION['user'] = [
    'id'           => $user['id'],
    'sso_username' => $user['sso_username'],
    'name'         => $user['name'],
    'email'        => $user['email'],
    'role'         => $user['role'],
];
unset($_SESSION['oauth_state']);

header('Location: https://roombooking.fba.kmutnb.ac.th/fba-facility-mms/public/index.php');
exit;

