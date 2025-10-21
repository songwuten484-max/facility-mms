<?php
require_once __DIR__ . '/../../inc/config.php';
require_once __DIR__ . '/../../inc/helpers.php';
require_once __DIR__ . '/../../inc/db.php';

$u   = require_role('ADMIN');
$pdo = db();

$today   = date('Y-m-d');
$defaultStart = date('Y-01-01');
$start = isset($_GET['start']) && $_GET['start'] !== '' ? $_GET['start'] : $defaultStart;
$end   = isset($_GET['end'])   && $_GET['end']   !== '' ? $_GET['end']   : $today;

$params = [$start . ' 00:00:00', $end . ' 23:59:59'];
$sql = "
  SELECT r.id, r.ticket_id, r.created_by, r.score, r.comment, r.created_at,
         t.title, t.location, t.created_at AS ticket_created
  FROM maintenance_ratings r
  LEFT JOIN maintenance_tickets t ON t.id=r.ticket_id
  WHERE r.created_at BETWEEN ? AND ?
  ORDER BY r.created_at DESC
";
$st = $pdo->prepare($sql);
$st->execute($params);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=ratings_'.$start.'_'.$end.'.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['rating_id','ticket_id','created_by','score','comment','rated_at','ticket_title','ticket_location','ticket_created']);

while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
  fputcsv($out, [
    $row['id'],
    $row['ticket_id'],
    $row['created_by'],
    $row['score'],
    $row['comment'],
    $row['created_at'],
    $row['title'],
    $row['location'],
    $row['ticket_created'],
  ]);
}
fclose($out);
exit;
