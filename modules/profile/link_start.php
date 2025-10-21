<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__.'/../../inc/helpers.php';
require_once __DIR__.'/../../inc/layout.php';

$u = require_login();
layout_header('เชื่อมต่อ LINE');
?>
<div class="card p-3">
  <h5 class="mb-2">กำลังเชื่อมต่อ LINE…</h5>
  <div class="text-muted">ถ้าไม่ได้รันในแอป LINE ให้ลองเปิดด้วย LINE (มือถือ) หรือ LINE Desktop</div>
</div>

<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
(async () => {
  const LIFF_ID = "<?= h(LINE_LIFF_ID) ?>";
  try {
    await liff.init({ liffId: LIFF_ID });
    if (!liff.isLoggedIn()) {
      liff.login(); // หลัง login จะ reload กลับมา
      return;
    }
    const prof = await liff.getProfile();           // { userId, displayName, ... }
    const userId = prof.userId;

    // ส่งไปเก็บฝั่งเซิร์ฟเวอร์
    const resp = await fetch('link_callback.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ line_user_id: userId })
    });
    const data = await resp.json();

    if (data.ok) {
      alert('เชื่อมต่อ LINE สำเร็จ');
      // ปิด LIFF ถ้าอยู่ใน in-app
      if (liff.isInClient()) { liff.closeWindow(); }
      else { location.href = '../profile/index.php'; }
    } else {
      alert('เชื่อมต่อไม่สำเร็จ: ' + (data.error || 'unknown'));
    }
  } catch (e) {
    console.error(e);
    alert('เกิดข้อผิดพลาดในการเชื่อมต่อ LINE');
  }
})();
</script>
<?php layout_footer(); ?>
