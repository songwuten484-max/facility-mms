<?php
require_once __DIR__.'/../../inc/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// ป้องกัน 500 จาก server ที่ปิด random_bytes()
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;

$params = [
  'response_type' => 'code',
  'client_id'     => SSO_CLIENT_ID,
  'redirect_uri'  => SSO_REDIRECT_URI,
  'scope'         => 'profile email',
  'state'         => $state,
];

header('Location: '.SSO_AUTH_URL.'?'.http_build_query($params));
exit;
