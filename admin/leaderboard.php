<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// Fetch leaderboard data excluding admins
$stmt = $pdo->query("
    SELECT u.id, u.username, u.score, COUNT(s.challenge_id) AS solved_count
    FROM users u
    LEFT JOIN solves s ON u.id = s.user_id
    WHERE u.role != 'admin'
    GROUP BY u.id
    ORDER BY u.score DESC, solved_count DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch currently online users (active in last 5 mins)
$onlineStmt = $pdo->prepare("
    SELECT DISTINCT user_id 
    FROM user_activity 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$onlineStmt->execute();
$onlineUsers = $onlineStmt->fetchAll(PDO::FETCH_COLUMN, 0);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Leaderboard — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.table-header { background: rgba(8,11,18,0.9); border-bottom:1px solid rgba(45,226,138,0.3); }
.table-row { border-bottom:1px solid rgba(45,226,138,0.1); transition: background 0.2s; }
.table-row:hover { background: rgba(45,226,138,0.1); }
.online { color:#00ff00; font-weight:bold; }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="categories.php">➕ Add Categories</a>
  <a href="add_challenge.php">➕ Add Challenge</a>
  <a href="manage_users.php">👥 Manage Users</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="../index.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl font-bold text-green-400 mb-6">Leaderboard</h1>

  <div class="overflow-x-auto">
    <table class="w-full text-left">
      <thead class="table-header text-green-300">
        <tr>
          <th class="px-4 py-2">Rank</th>
          <th class="px-4 py-2">Username</th>
          <th class="px-4 py-2">Score</th>
          <th class="px-4 py-2">Solved Challenges</th>
          <th class="px-4 py-2">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php $rank = 1; foreach($users as $user): ?>
        <tr class="table-row <?= in_array($user['id'], $onlineUsers) ? 'online' : '' ?>">
          <td class="px-4 py-2"><?= $rank ?></td>
          <td class="px-4 py-2"><?= htmlspecialchars($user['username']) ?></td>
          <td class="px-4 py-2"><?= $user['score'] ?></td>
          <td class="px-4 py-2"><?= $user['solved_count'] ?></td>
          <td class="px-4 py-2"><?= in_array($user['id'], $onlineUsers) ? 'Online' : 'Offline' ?></td>
        </tr>
        <?php $rank++; endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
