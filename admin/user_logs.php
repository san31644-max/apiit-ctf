<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Get user ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid user ID.");
}
$user_id = (int) $_GET['id'];

// Fetch user info
$stmt = $pdo->prepare("SELECT username, score FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) die("User not found.");

// Fetch login history
$logins = $pdo->prepare("SELECT login_time, ip_address FROM login_logs WHERE user_id = ? ORDER BY login_time DESC");
$logins->execute([$user_id]);
$login_logs = $logins->fetchAll(PDO::FETCH_ASSOC);

// Fetch challenge submissions
$challenges = $pdo->prepare("
    SELECT cl.*, c.title AS challenge
    FROM challenge_logs cl
    JOIN challenges c ON cl.challenge_id = c.id
    WHERE cl.user_id = ?
    ORDER BY cl.submission_time DESC
");
$challenges->execute([$user_id]);
$challenge_logs = $challenges->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Logs for <?= htmlspecialchars($user['username']) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-green-300 font-mono p-6">
<a href="dashboard.php" class="text-green-400 hover:underline mb-4 inline-block">← Back to Dashboard</a>
<h1 class="text-2xl font-bold mb-2">Logs for <?= htmlspecialchars($user['username']) ?></h1>
<p>Score: <?= $user['score'] ?></p>

<h2 class="text-xl mt-4 mb-2">Login History</h2>
<table class="table-auto border border-green-500 w-full mb-4">
<thead>
<tr class="bg-green-900/20">
<th>Login Time</th>
<th>IP Address</th>
</tr>
</thead>
<tbody>
<?php foreach($login_logs as $log): ?>
<tr>
<td><?= $log['login_time'] ?></td>
<td><?= $log['ip_address'] ?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($login_logs)): ?>
<tr><td colspan="2" class="text-red-400">No login history</td></tr>
<?php endif; ?>
</tbody>
</table>

<h2 class="text-xl mt-4 mb-2">Challenge Submissions</h2>
<table class="table-auto border border-green-500 w-full">
<thead>
<tr class="bg-green-900/20">
<th>Challenge</th>
<th>Flag Submitted</th>
<th>Status</th>
<th>IP Address</th>
<th>Submission Time</th>
</tr>
</thead>
<tbody>
<?php foreach($challenge_logs as $cl): ?>
<tr>
<td><?= htmlspecialchars($cl['challenge']) ?></td>
<td><?= htmlspecialchars($cl['flag_submitted']) ?></td>
<td class="<?= $cl['status']=='correct'?'text-green-400':'text-red-400' ?>">
<?= $cl['status']=='correct'?'✅':'❌' ?></td>
<td><?= $cl['ip_address'] ?></td>
<td><?= $cl['submission_time'] ?></td>
</tr>
<?php endforeach; ?>
<?php if(empty($challenge_logs)): ?>
<tr><td colspan="5" class="text-red-400">No challenge submissions</td></tr>
<?php endif; ?>
</tbody>
</table>
</body>
</html>
