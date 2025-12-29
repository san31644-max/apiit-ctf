<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . "/../includes/db.php";

date_default_timezone_set('Asia/Colombo');
$pdo->exec("SET time_zone = '+05:30'");

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden']);
  exit;
}

$minutes = 60;

// submissions per minute (last 60 minutes)
$rows = $pdo->query("
  SELECT DATE_FORMAT(submission_time, '%Y-%m-%d %H:%i:00') AS bucket, COUNT(*) AS cnt
  FROM challenge_logs
  WHERE submission_time >= (NOW() - INTERVAL {$minutes} MINUTE)
  GROUP BY bucket
  ORDER BY bucket ASC
")->fetchAll(PDO::FETCH_ASSOC);

$map = [];
foreach ($rows as $r) $map[$r['bucket']] = (int)$r['cnt'];

$labels = [];
$counts = [];

$dt = new DateTime('now', new DateTimeZone('Asia/Colombo'));
$dt->modify('-' . ($minutes - 1) . ' minutes');

for ($i = 0; $i < $minutes; $i++) {
  $bucket = $dt->format('Y-m-d H:i:00');
  $labels[] = $dt->format('H:i');
  $counts[] = $map[$bucket] ?? 0;
  $dt->modify('+1 minute');
}

// top IPs (last 30 minutes)
$ipRows = $pdo->query("
  SELECT ip_address, COUNT(*) AS cnt
  FROM challenge_logs
  WHERE submission_time >= (NOW() - INTERVAL 30 MINUTE)
    AND COALESCE(ip_address,'') <> ''
  GROUP BY ip_address
  ORDER BY cnt DESC
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

$serverTime = (new DateTime('now', new DateTimeZone('Asia/Colombo')))->format('Y-m-d H:i:s');

echo json_encode([
  'ok' => true,
  'labels' => $labels,
  'counts' => $counts,
  'top_ips' => $ipRows,
  'server_time' => $serverTime,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
