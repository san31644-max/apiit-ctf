<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Total stats
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$total_challenges_solved = $pdo->query("SELECT COUNT(*) FROM solves")->fetchColumn();

$active_users = $pdo->query("
    SELECT COUNT(DISTINCT user_id)
    FROM login_logs
    WHERE login_time > (NOW() - INTERVAL 5 MINUTE)
")->fetchColumn();

// Submissions per hour for chart
$submissions_per_hour = $pdo->query("
    SELECT HOUR(submission_time) as hr, COUNT(*) as cnt
    FROM challenge_logs
    GROUP BY HOUR(submission_time)
")->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_data = [];
foreach ($submissions_per_hour as $row) {
    $chart_labels[] = $row['hr'];
    $chart_data[] = $row['cnt'];
}

// Recent submissions (limit 50) with username and challenge
$logs = $pdo->query("
    SELECT cl.*, u.username, ch.title AS challenge_title
    FROM challenge_logs cl
    JOIN users u ON cl.user_id = u.id
    JOIN challenges ch ON cl.challenge_id = ch.id
    ORDER BY cl.submission_time DESC
    LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

// User activity table: latest login per user
$users = $pdo->query("
    SELECT u.id, u.username, u.score, l.last_login, l.ip_address
    FROM users u
    LEFT JOIN (
        SELECT user_id, ip_address, login_time AS last_login
        FROM login_logs
        WHERE (user_id, login_time) IN (
            SELECT user_id, MAX(login_time)
            FROM login_logs
            GROUP BY user_id
        )
    ) l ON u.id = l.user_id
    WHERE u.role != 'admin'
    ORDER BY u.score DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.table-container { overflow-x:auto; }
.table-auto th, .table-auto td { border:1px solid rgba(45,226,138,0.3); padding:8px; }
.status-correct { color:#0f0; font-weight:bold; }
.status-incorrect { color:#f00; font-weight:bold; }
</style>
</head>
<body class="h-screen flex">

<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="add_challenge.php">➕ Add Challenge</a>
  <a href="manage_challenges.php">📋 Manage Challenges</a>
  <a href="manage_users.php">👥 Manage Users</a>
  <a href="manage_hints.php">👥 Manage Hints</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl font-bold text-green-400 mb-6">Admin Monitoring Dashboard</h1>

  <!-- Stats Cards -->
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-green-900/20 p-4 rounded border border-green-500">
      <p class="text-green-300 font-bold">Total Users</p>
      <p class="text-xl font-bold text-green-400"><?= $total_users ?></p>
    </div>
    <div class="bg-green-900/20 p-4 rounded border border-green-500">
      <p class="text-green-300 font-bold">Active Users (5 min)</p>
      <p class="text-xl font-bold text-green-400"><?= $active_users ?></p>
    </div>
    <div class="bg-green-900/20 p-4 rounded border border-green-500">
      <p class="text-green-300 font-bold">Challenges Solved</p>
      <p class="text-xl font-bold text-green-400"><?= $total_challenges_solved ?></p>
    </div>
  </div>

  <!-- Submissions Chart -->
  <div class="mb-6 bg-green-900/20 p-4 rounded border border-green-500">
    <canvas id="submissionsChart" height="100"></canvas>
  </div>

  <script>
  const ctx = document.getElementById('submissionsChart').getContext('2d');
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode($chart_labels) ?>,
      datasets: [{
        label: 'Submissions per Hour',
        data: <?= json_encode($chart_data) ?>,
        borderColor: '#2de28a',
        backgroundColor: 'rgba(45,226,138,0.3)',
        fill: true,
        tension: 0.3
      }]
    },
    options: {
      scales: {
        x: { title: { display:true, text:'Hour of Day' } },
        y: { beginAtZero:true, title:{display:true,text:'Submissions'} }
      }
    }
  });
  </script>

  <!-- User Activity Table -->
  <div class="mb-6 table-container">
    <h2 class="text-xl font-bold text-green-300 mb-2">User Activity</h2>
    <table class="table-auto w-full">
      <thead>
        <tr class="bg-green-900/20">
          <th>Username</th>
          <th>Score</th>
          <th>Last Login</th>
          <th>IP Address</th>
          <th>Logs</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($users as $u): ?>
        <tr class="hover:bg-green-900/10">
          <td><?= htmlspecialchars($u['username']) ?></td>
          <td><?= $u['score'] ?></td>
          <td><?= $u['last_login'] ?></td>
          <td><?= $u['ip_address'] ?></td>
          <td>
            <a href="user_logs.php?id=<?= $u['id'] ?>" class="text-green-400 hover:underline">View Logs</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent Challenge Submissions Table -->
  <div class="mb-6 table-container">
    <h2 class="text-xl font-bold text-green-300 mb-2">Recent Challenge Submissions</h2>
    <table class="table-auto w-full">
      <thead>
        <tr class="bg-green-900/20">
          <th>Username</th>
          <th>Challenge</th>
          <th>Flag</th>
          <th>Status</th>
          <th>IP</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($logs as $log): ?>
        <tr class="hover:bg-green-900/10">
          <td><?= htmlspecialchars($log['username']) ?></td>
          <td><?= htmlspecialchars($log['challenge_title']) ?></td>
          <td><?= htmlspecialchars($log['flag_submitted']) ?></td>
          <td class="<?= $log['status']=='correct'?'status-correct':'status-incorrect' ?>">
            <?= $log['status']=='correct'?'✅':'❌' ?>
          </td>
          <td><?= $log['ip_address'] ?></td>
          <td><?= $log['submission_time'] ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
