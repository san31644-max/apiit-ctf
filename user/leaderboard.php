<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Fetch top 10 users
$stmt = $pdo->query("SELECT username, score FROM users WHERE role = 'user' ORDER BY score DESC LIMIT 10");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Current user's rank
$stmt2 = $pdo->prepare("SELECT COUNT(*) + 1 AS `rank` FROM users WHERE role = 'user' AND score > (SELECT score FROM users WHERE id = ?)");
$stmt2->execute([$_SESSION['user_id']]);
$userRank = $stmt2->fetch(PDO::FETCH_ASSOC)['rank'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Leaderboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

body {
    font-family: 'Share Tech Mono', monospace;
    background: radial-gradient(circle at top, #0f172a, #020617);
    color: #e2e8f0;
    overflow-x: hidden;
    position: relative;
}

/* Animated grid background */
body::after {
    content:"";
    position: fixed; top:0; left:0; width:100%; height:100%;
    background-image: linear-gradient(rgba(34,197,94,0.05) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(34,197,94,0.05) 1px, transparent 1px);
    background-size: 50px 50px;
    animation: moveGrid 15s linear infinite;
    pointer-events: none; z-index:0;
}
@keyframes moveGrid {
    0% {background-position: 0 0, 0 0;}
    100% {background-position: 100px 100px, -100px -100px;}
}

/* Sidebar */
.sidebar { background:#071018; border-right:1px solid rgba(34,197,94,0.3); position:fixed; height:100vh; width:16rem; z-index:10; }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.sidebar a:hover { background:rgba(34,197,94,0.1); color:#22c55e; }

/* Leaderboard container */
.leaderboard-container { max-width:1100px; margin:auto; padding:2rem; display:grid; grid-template-columns: repeat(auto-fill,minmax(250px,1fr)); gap:1.5rem; z-index:5; position:relative; }

/* User card */
.user-card {
    backdrop-filter: blur(12px);
    background: rgba(15,23,42,0.7);
    border:1px solid rgba(34,197,94,0.4);
    border-radius:1rem;
    padding:2rem 1.5rem;
    text-align:center;
    box-shadow:0 0 20px rgba(34,197,94,0.2);
    position:relative;
    overflow:hidden;
    opacity:0;
    transform: translateY(20px);
    animation: fadeInUp 0.7s forwards;
}
.user-card:hover { transform: translateY(-4px); box-shadow:0 0 35px #22c55e; transition:0.3s; }

.user-card h3 { font-size:1.5rem; color:#22c55e; text-shadow:0 0 10px #22c55e; margin-bottom:0.5rem; }
.user-card p { font-size:1.4rem; font-weight:bold; color:#22c55e; text-shadow:0 0 6px #22c55e; animation: pulseScore 1.2s infinite alternate; }

/* Rank badge as cup icon */
.rank-badge {
    position:absolute; top:-12px; left:50%; transform:translateX(-50%);
    font-size:2rem;
}
.rank-1::before { content:"🏆"; }
.rank-2::before { content:"🥈"; }
.rank-3::before { content:"🥉"; }
.rank-me { content:"✨"; }

/* Animations */
@keyframes fadeInUp {
    0% { opacity:0; transform: translateY(20px); }
    100% { opacity:1; transform: translateY(0); }
}

@keyframes pulseScore {
    0% { text-shadow:0 0 6px #22c55e; }
    100% { text-shadow:0 0 18px #22c55e; }
}
</style>
</head>
<body class="flex">

<!-- Sidebar -->
<div class="sidebar">
    <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="challenges.php">🛠 Challenges</a>
    <a href="leaderboard.php">🏆 Leaderboard</a>
    <a href="instructions.php" class="bg-green-900">📖 Instructions</a>
    <a href="hints.php">💡 Hints</a>
    <a href="profile.php">👤 Profile</a>
    <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="flex-1 p-6 ml-64">
  <h1 class="text-4xl font-bold text-green-400 mb-4">Leaderboard</h1>
  
  <div class="leaderboard-container">
      <?php
      $rank = 1;
      foreach($users as $u):
          if($rank==1) $badgeClass='rank-1';
          elseif($rank==2) $badgeClass='rank-2';
          elseif($rank==3) $badgeClass='rank-3';
          elseif($u['username']==$_SESSION['username']) $badgeClass='rank-me';
          else $badgeClass='';
      ?>
      <div class="user-card" style="animation-delay: <?= ($rank*0.1) ?>s">
          <div class="rank-badge <?= $badgeClass ?>"></div>
          <h3><?= htmlspecialchars($u['username']) ?></h3>
          <p><?= $u['score'] ?> pts</p>
      </div>
      <?php
          $rank++;
      endforeach;
      ?>
  </div>
</div>
</body>
</html>
