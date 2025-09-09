<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php'; // Correct path

// Only logged-in users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}


// Fetch user info
$stmt = $pdo->prepare("SELECT username, score, created_at, last_login FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch number of challenges solved
$stmt2 = $pdo->prepare("SELECT COUNT(*) AS solved_count FROM solves WHERE user_id = ?");
$stmt2->execute([$_SESSION['user_id']]);
$solvedCount = $stmt2->fetch(PDO::FETCH_ASSOC)['solved_count'];
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profile — APIIT CTF</title>
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

.profile-container {
    max-width:700px;
    margin:auto;
    padding:2rem;
}
.profile-card {
    backdrop-filter: blur(10px);
    background: rgba(0,0,0,0.5);
    border: 1px solid rgba(45,226,138,0.4);
    border-radius: 1rem;
    padding:2rem;
    box-shadow: 0 0 20px rgba(45,226,138,0.2);
}
.profile-card h2 { color:#2de28a; margin-bottom:1rem; }
.profile-item { margin-bottom:1rem; }
.profile-item span { color:#c9f7e4; font-weight:600; }
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
  <div class="profile-container">
    <div class="profile-card">
      <h2>Welcome, <?= htmlspecialchars($user['username']) ?> 👋</h2>

      <div class="profile-item">
        <span>Username:</span> <?= htmlspecialchars($user['username']) ?>
      </div>
      <div class="profile-item">
        <span>Total Score:</span> <?= $user['score'] ?>
      </div>
      <div class="profile-item">
        <span>Challenges Solved:</span> <?= $solvedCount ?>
      </div>
      <div class="profile-item">
        <span>Account Created:</span> <?= date('F j, Y, g:i a', strtotime($user['created_at'])) ?>
      </div>
      <div class="profile-item">
        <span>Last Login:</span> <?= date('F j, Y, g:i a', strtotime($user['last_login'])) ?>
      </div>

      <a href="challenges.php" class="inline-block mt-4 px-4 py-2 rounded-md border border-green-400 text-green-300 hover:bg-green-400 hover:text-black transition">View Challenges</a>
    </div>
  </div>
</div>

</body>
</html>
