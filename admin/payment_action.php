<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']);
if (!$loggedIn) { http_response_code(403); exit("Forbidden"); }

$teamId = (int)($_POST['team_id'] ?? 0);
$do     = (string)($_POST['do'] ?? '');
$note   = trim((string)($_POST['admin_note'] ?? ''));

if ($teamId <= 0 || !in_array($do, ['approve','reject'], true)) {
  http_response_code(400);
  exit("Bad request");
}

$newStatus = ($do === 'approve') ? 'approved' : 'rejected';

// update latest payment row for this team
$stmt = $pdo->prepare("
  UPDATE ctf_payments
  SET status = :st,
      admin_note = :note
  WHERE id = (
    SELECT id
    FROM ctf_payments
    WHERE team_id = :tid
    ORDER BY id DESC
    LIMIT 1
  )
");
$stmt->execute([
  ':st'   => $newStatus,
  ':note' => ($note === '' ? null : $note),
  ':tid'  => $teamId
]);

header("Location: /admin/view_registration.php");
exit;
