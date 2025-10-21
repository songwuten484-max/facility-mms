<?php
require_once __DIR__.'/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php'; 
$u = require_login();
if (($u['role'] ?? '') !== 'ADMIN') {
  http_response_code(403);
  exit('forbidden');
}

$filename = 'assets_sample.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');

$out = fopen('php://output', 'w');

/* หัวคอลัมน์ */
fputcsv($out, [
  'asset_code','name','category','building','room','serial_no','specs','status'
]);

/* ตัวอย่างข้อมูล 3 แถว */
$rows = [
  ['412000010009-31201-00001','เครื่องปรับอากาศ 24000 BTU','AIRCON','อาคาร A','201','SN-A1','ยี่ห้อ X รุ่น Y','IN_SERVICE'],
  ['412000010009-31201-00002','ลิฟต์โดยสาร ฝั่งทิศเหนือ','LIFT','อาคาร B','','SN-LF1','บำรุงรักษารายเดือน','REPAIR'],
  ['412000010009-31201-00003','คอมพิวเตอร์ห้องปฏิบัติการ','IT','อาคาร C','Lab-3','SN-PC23','Core i5, RAM 16GB','IN_SERVICE'],
];
foreach ($rows as $r) {
  fputcsv($out, $r);
}

fclose($out);
exit;
