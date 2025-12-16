<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

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

// Use a fixed profile image for all users
$avatarUrl = "https://upload.wikimedia.org/wikipedia/commons/9/99/Sample_User_Icon.png";

// Calculate "time ago" for last login
function timeAgo($datetime){
    $time = strtotime($datetime);
    $diff = time() - $time;
    if($diff < 60) return "$diff seconds ago";
    $diff = floor($diff/60);
    if($diff < 60) return "$diff minutes ago";
    $diff = floor($diff/60);
    if($diff < 24) return "$diff hours ago";
    $diff = floor($diff/24);
    if($diff < 7) return "$diff days ago";
    return date('F j, Y, g:i a', $time);
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profile — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

body {
    font-family: 'Share Tech Mono', monospace;
    background: #0b0f12;
    color: #c9f7e4;
}
.sidebar { 
    background:#071018; 
    border-right:1px solid rgba(45,226,138,0.2); 
    position:fixed; height:100vh; width:16rem; 
}
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }

.profile-container {
    max-width:800px;
    margin:auto;
    padding:2rem;
}
.profile-card {
    backdrop-filter: blur(10px);
    background: rgba(0,0,0,0.55);
    border: 1px solid rgba(34,197,94,0.5);
    border-radius: 1rem;
    padding:2rem;
    box-shadow: 0 0 20px rgba(34,197,94,0.3);
    transition: transform 0.3s, box-shadow 0.3s;
}
.profile-card:hover { transform: translateY(-5px); box-shadow:0 0 30px #22c55e; }

.profile-header {
    display:flex; align-items:center; margin-bottom:1.5rem;
}
.profile-header img {
    width:100px; height:100px; border-radius:50%; border:2px solid #22c55e; margin-right:1.5rem;
    transition: transform 0.3s;
}
.profile-header img:hover { transform: scale(1.1); }

.profile-card h2 { color:#22c55e; font-size:1.8rem; }
.profile-item { margin-bottom:1rem; font-size:1rem; }
.profile-item span { color:#c9f7e4; font-weight:600; }
.view-challenges-btn {
    display:inline-block; margin-top:1rem; padding:0.5rem 1rem; 
    border:1px solid #22c55e; color:#22c55e; border-radius:0.5rem;
    transition: all 0.3s;
}
.view-challenges-btn:hover { background:#22c55e; color:#000; transform:scale(1.05);}
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="flex-1 overflow-auto p-6 ml-64">
  <div class="profile-container">
    <div class="profile-card">
      
      <div class="profile-header">
        <img src="<?= $avatarUrl ?>" alt="Avatar">
        <h2><?= htmlspecialchars($user['username']) ?> 👋</h2>
      </div>

      <div class="profile-item"><span>Username:</span> <?= htmlspecialchars($user['username']) ?></div>
      <div class="profile-item"><span>Total Score:</span> <?= $user['score'] ?></div>
      <div class="profile-item"><span>Challenges Solved:</span> <?= $solvedCount ?></div>
      <div class="profile-item"><span>Account Created:</span> <?= date('F j, Y, g:i a', strtotime($user['created_at'])) ?></div>
      <div class="profile-item"><span>Last Login:</span> <?= timeAgo($user['last_login']) ?> <small class="text-gray-400">(<?= date('F j, Y, g:i a', strtotime($user['last_login'])) ?>)</small></div>

      <a href="challenges.php" class="view-challenges-btn">View Challenges</a>

    </div>
  </div>
</div>

</body>
</html>
