<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/db.php';
require_once __DIR__ . '/../../inc/helpers.php';

$u = require_login();
if (($u['role'] ?? '') !== 'ADMIN') {
    http_response_code(403);
    exit;
}

$pdo = db();

/* กันลืม: ตาราง log */
$pdo->exec("CREATE TABLE IF NOT EXISTS maintenance_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT NOT NULL,
  by_user VARCHAR(100) NOT NULL,
  action VARCHAR(50) NOT NULL,
  note TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX(ticket_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/* รับค่า */
$id     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$status = $_POST['status'] ?? 'OPEN';
$note   = trim($_POST['note'] ?? '');

/* อัปเดตสถานะ */
$pdo->prepare('UPDATE maintenance_tickets SET status=? WHERE id=?')->execute([$status, $id]);

/* เขียน log */
$pdo->prepare('INSERT INTO maintenance_logs (ticket_id, by_user, action, note) VALUES (?,?,?,?)')
    ->execute([$id, $u['sso_username'] ?? 'dev', 'STATUS', $note ?: ('เปลี่ยนสถานะเป็น ' . $status)]);

/* แจ้งเตือน LINE ให้ผู้แจ้ง */
if (defined('LINE_CHANNEL_TOKEN') && LINE_CHANNEL_TOKEN) {
    // ดึงข้อมูลตั๋ว + line_user_id ของผู้แจ้ง
    $q = $pdo->prepare('
      SELECT t.title, t.location, t.created_by, u.line_user_id
      FROM maintenance_tickets t
      LEFT JOIN users u ON u.sso_username = t.created_by
      WHERE t.id = ? LIMIT 1
    ');
    $q->execute([$id]);
    $row = $q->fetch();

    if ($row && !empty($row['line_user_id'])) {
        // สร้างลิงก์ประเมินแบบ absolute (กัน BASE_URL ไม่เป็น absolute)
        $origin = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http')
                . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        // ตัด path ตั้งแต่ /modules/... ออก เพื่อได้รากโปรเจ็กต์ แล้วต่อเป็น /modules/ratings/rate.php
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $projectBasePath = rtrim(preg_replace('#/modules/.*$#', '', $script), '/'); // อาจเป็น '' หรือ '/fba-facility-mms'
        if ($projectBasePath === '') $projectBasePath = '';

        $ratePath = $projectBasePath . '/modules/ratings/rate.php?ticket_id=' . $id;
        $rateUrl  = $origin . $ratePath;

        // ข้อความพื้นฐาน
        $msg = "🔧 อัปเดตงานซ่อมของคุณ"
             . "\nสถานะ: {$status}"
             . "\nเรื่อง: {$row['title']}"
             . "\nสถานที่: {$row['location']}";

        // ถ้าเสร็จสิ้น ให้ขอความร่วมมือทำแบบประเมิน
        if ($status === 'DONE') {
            $msg .= "\n\n✅ งานซ่อมเสร็จสิ้นแล้ว"
                  . "\n🙏 รบกวนเข้าทำแบบประเมินความพึงพอใจ"
                  . "\nประเมินได้ที่: {$rateUrl}";
        }

        // ส่ง LINE Push
        $payload = json_encode([
            "to"       => $row['line_user_id'],
            "messages" => [
                ["type" => "text", "text" => $msg]
            ]
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init('https://api.line.me/v2/bot/message/push');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . LINE_CHANNEL_TOKEN
            ],
            CURLOPT_POSTFIELDS     => $payload
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

/* กลับหน้ารายละเอียด */
header('Location: view.php?id=' . $id);
