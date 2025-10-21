<?php
// modules/profile/unlink.php

require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u   = require_login();
$pdo = db();

// อนุญาตเฉพาะ POST เพื่อป้องกันการคลิก link ผิดพลาด
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    flash_set('ไม่สามารถดำเนินการได้: รูปแบบคำขอไม่ถูกต้อง', 'warning');
    header('Location: index.php');
    exit;
}

// ดึง username ปัจจุบันจาก session
$sso = $u['sso_username'] ?? null;
if (!$sso) {
    // กันเคส dev login หรือไม่มีข้อมูล
    $sso = isset($u['id']) ? ('dev:' . $u['id']) : '';
}

try {
    // ล้าง line_user_id ออกจากฐานข้อมูล
    $stmt = $pdo->prepare("UPDATE users SET line_user_id = NULL WHERE sso_username = ? LIMIT 1");
    $stmt->execute([$sso]);

    // อัปเดตใน session ด้วย
    $_SESSION['user']['line_user_id'] = null;

    // แจ้งผล
    if ($stmt->rowCount() > 0 || !empty($u['line_user_id'])) {
        flash_set('ยกเลิกการเชื่อมต่อ LINE แล้ว', 'success');
    } else {
        // กรณีไม่ได้เชื่อมต่ออยู่แล้ว
        flash_set('บัญชีนี้ยังไม่ได้เชื่อมต่อ LINE อยู่ก่อนแล้ว', 'info');
    }
} catch (Throwable $e) {
    // กรณีผิดพลาด
    if (APP_ENV === 'dev') {
        flash_set('ยกเลิกการเชื่อมต่อล้มเหลว: ' . $e->getMessage(), 'error');
    } else {
        flash_set('เกิดข้อผิดพลาดระหว่างยกเลิกการเชื่อมต่อ LINE', 'error');
    }
}

// กลับไปหน้าโปรไฟล์
header('Location: index.php');
exit;
