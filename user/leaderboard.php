<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Top 10 competitors
$stmt = $pdo->query("SELECT username, score FROM users WHERE role = 'user' ORDER BY score DESC LIMIT 10");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current user's rank
$stmt2 = $pdo->prepare("SELECT COUNT(*) + 1 AS `rank` FROM users WHERE role = 'user' AND score > (SELECT score FROM users WHERE id = ?)");
$stmt2->execute([$_SESSION['user_id']]);
$userRank = $stmt2->fetch(PDO::FETCH_ASSOC)['rank'];
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Leaderboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Source+Code+Pro:wght@400;600;700&display=swap');

body {
    font-family: 'Source Code Pro', monospace;
    background: #0b0f12;
    color: #c9f7e4;
}
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.leaderboard-container { max-width:900px; margin:auto; padding:2rem; }
.leaderboard-card {
    backdrop-filter: blur(10px);
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(45,226,138,0.4);
    border-radius: 1rem;
    padding: 1rem;
    box-shadow: 0 0 20px rgba(45,226,138,0.2);
}
.table { width:100%; border-collapse:collapse; margin-top:1rem; }
.table th, .table td { padding:12px; text-align:center; border-bottom: 1px solid rgba(45,226,138,0.2); }
.table th { color:#2de28a; font-weight:600; }
.table tr:hover { background: rgba(45,226,138,0.1); transform: scale(1.02); transition:0.2s; }
.rank-badge {
    display:inline-block;
    padding:4px 8px;
    border-radius:6px;
    color:#fff;
    font-weight:600;
}
.rank-1 { background:#FFD700; } /* Gold */
.rank-2 { background:#C0C0C0; } /* Silver */
.rank-3 { background:#CD7F32; } /* Bronze */
.rank-me { background:#2de28a; }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="flex-1 overflow-auto p-6">
  <div class="leaderboard-container">
    <h1 class="text-3xl font-bold text-green-400 mb-4">Leaderboard</h1>
    <p class="mb-4 text-gray-400">Your current rank: <span class="rank-me px-2 py-1 rounded"><?= htmlspecialchars($userRank) ?></span></p>

    <div class="leaderboard-card">
      <table class="table">
        <thead>
          <tr>
            <th>Rank</th>
            <th>Username</th>
            <th>Score</th>
          </tr>
        </thead>
        <tbody>
          <?php 
          $rank = 1; 
          foreach($users as $u): 
            $badgeClass = $rank === 1 ? 'rank-1' : ($rank === 2 ? 'rank-2' : ($rank === 3 ? 'rank-3' : ''));
            if($u['username'] === $_SESSION['username']) $badgeClass = 'rank-me';
          ?>
          <tr>
            <td><span class="rank-badge <?= $badgeClass ?>"><?= $rank ?></span></td>
            <td><?= htmlspecialchars($u['username']) ?></td>
            <td><?= $u['score'] ?></td>
          </tr>
          <?php $rank++; endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

</body>
</html>
