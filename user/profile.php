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

// Fixed avatar
$avatarUrl = "https://upload.wikimedia.org/wikipedia/commons/9/99/Sample_User_Icon.png";

// time ago
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
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#3fffe0;
  --teal:#00d1b8;
  --cyan:#38bdf8;
  --gold:#f3d36b;
  --bg1:#031b34;
  --bg2:#020617;
  --bg3:#010b18;
  --glass: rgba(3, 23, 42, 0.62);
  --border: rgba(63,255,224,0.35);
  --border2: rgba(63,255,224,0.55);
}

*{ box-sizing:border-box; }
body{
  font-family:'Share Tech Mono', monospace;
  color:#e2e8f0;
  background:
    radial-gradient(1200px 700px at 20% 0%, rgba(56,189,248,0.18), transparent 60%),
    radial-gradient(900px 600px at 85% 10%, rgba(0,209,184,0.16), transparent 55%),
    radial-gradient(900px 700px at 50% 110%, rgba(243,211,107,0.09), transparent 60%),
    linear-gradient(180deg,var(--bg1),var(--bg2) 55%,var(--bg3));
  overflow:hidden;
}

/* ---- Caustic shimmer overlay ---- */
body::before{
  content:"";
  position:fixed;
  inset:-40%;
  pointer-events:none;
  background:
    radial-gradient(circle at 25% 35%, rgba(63,255,224,0.08), transparent 45%),
    radial-gradient(circle at 70% 30%, rgba(56,189,248,0.07), transparent 48%),
    radial-gradient(circle at 40% 75%, rgba(0,209,184,0.07), transparent 50%),
    conic-gradient(from 90deg at 50% 50%, rgba(63,255,224,0.06), transparent 25%, rgba(56,189,248,0.05), transparent 60%, rgba(0,209,184,0.05));
  filter: blur(6px);
  opacity:0.85;
  animation: caustics 14s ease-in-out infinite alternate;
}
@keyframes caustics{
  from{ transform: translate3d(-2%, -1%, 0) rotate(-1deg) scale(1.05); }
  to  { transform: translate3d( 2%,  1%, 0) rotate( 1deg) scale(1.10); }
}

/* ---- Floating particles ---- */
.particles{
  position:fixed;
  inset:0;
  pointer-events:none;
  opacity:0.20;
  background-image:
    radial-gradient(rgba(63,255,224,0.16) 1px, transparent 1px),
    radial-gradient(rgba(56,189,248,0.12) 1px, transparent 1px),
    radial-gradient(rgba(243,211,107,0.10) 1px, transparent 1px);
  background-size: 120px 120px, 180px 180px, 240px 240px;
  background-position: 0 0, 40px 80px, 90px 30px;
  animation: driftUp 22s linear infinite;
}
@keyframes driftUp{
  from{ transform: translateY(0); }
  to  { transform: translateY(-140px); }
}

/* ---- Sidebar ---- */
.sidebar{
  background: linear-gradient(180deg, rgba(2, 10, 22, 0.95), rgba(3, 18, 34, 0.88));
  border-right: 1px solid var(--border);
  position:fixed;
  height:100vh;
  width:16rem;
  box-shadow: 0 0 30px rgba(0,209,184,0.12);
  overflow:hidden;
}

/* Rune beam */
.sidebar::before{
  content:"";
  position:absolute;
  top:-20%;
  bottom:-20%;
  right:-1px;
  width:2px;
  background: linear-gradient(180deg, transparent, rgba(63,255,224,0.75), transparent);
  opacity:0.7;
  filter: blur(0.2px);
}

/* Subtle moving glow inside sidebar */
.sidebar::after{
  content:"";
  position:absolute;
  inset:-40%;
  background:
    radial-gradient(circle at 30% 30%, rgba(63,255,224,0.08), transparent 45%),
    radial-gradient(circle at 70% 50%, rgba(56,189,248,0.07), transparent 50%);
  filter: blur(10px);
  opacity:0.55;
  animation: sidebarGlow 10s ease-in-out infinite alternate;
  pointer-events:none;
}
@keyframes sidebarGlow{
  from{ transform: translate(-2%, -1%) scale(1.02); }
  to  { transform: translate( 2%,  1%) scale(1.06); }
}

.sidebar h2{
  font-family:'Cinzel', serif;
  letter-spacing:0.10em;
  text-shadow: 0 0 18px rgba(63,255,224,0.26);
}

.sidebar a{
  display:block;
  padding:12px 14px;
  color: rgba(226,232,240,0.90);
  border-bottom:1px solid rgba(255,255,255,0.06);
  transition:0.25s ease;
  position:relative;
  z-index:1;
}
.sidebar a::after{
  content:"";
  position:absolute;
  left:0; top:0; bottom:0;
  width:0px;
  background: linear-gradient(180deg, transparent, rgba(63,255,224,0.35), transparent);
  transition:0.25s ease;
}
.sidebar a:hover{
  background: rgba(63,255,224,0.08);
  color: var(--aqua);
  text-shadow: 0 0 14px rgba(63,255,224,0.18);
}
.sidebar a:hover::after{ width:4px; }

/* ---- Main spacing ---- */
.main{
  margin-left:16rem;
  padding:24px;
  height:100vh;
  overflow:auto;
}

/* ---- Profile container ---- */
.profile-container{
  max-width:900px;
  margin:auto;
  padding: 1rem;
}

/* ---- Profile card (glass + shimmer) ---- */
.profile-card{
  position:relative;
  background: linear-gradient(180deg, rgba(3,23,42,0.68), rgba(1,12,26,0.55));
  border:1px solid var(--border);
  border-radius: 22px;
  padding: 2.2rem;
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow:
    0 18px 55px rgba(0,0,0,0.35),
    0 0 25px rgba(0,209,184,0.12);
  overflow:hidden;
  transition: transform 0.35s ease, box-shadow 0.35s ease, border-color 0.35s ease;
}
.profile-card:hover{
  transform: translateY(-8px);
  border-color: var(--border2);
  box-shadow:
    0 26px 70px rgba(0,0,0,0.42),
    0 0 35px rgba(63,255,224,0.18);
}

/* Shimmer sweep */
.profile-card::before{
  content:"";
  position:absolute;
  top:-40%;
  left:-60%;
  width:55%;
  height:200%;
  background: linear-gradient(90deg, transparent, rgba(63,255,224,0.14), transparent);
  transform: rotate(18deg);
  opacity:0;
  transition:0.45s ease;
}
.profile-card:hover::before{
  opacity:1;
  left:125%;
}

/* Soft glowing corners */
.profile-card::after{
  content:"";
  position:absolute;
  inset:-1px;
  border-radius:22px;
  pointer-events:none;
  background:
    radial-gradient(500px 220px at 20% 0%, rgba(63,255,224,0.13), transparent 60%),
    radial-gradient(400px 220px at 90% 10%, rgba(56,189,248,0.10), transparent 65%),
    radial-gradient(420px 250px at 50% 110%, rgba(243,211,107,0.08), transparent 70%);
  opacity:0.9;
}

/* ---- Header row ---- */
.profile-header{
  display:flex;
  align-items:center;
  gap: 18px;
  margin-bottom: 1.6rem;
  position:relative;
  z-index:2;
}

.title-wrap{
  display:flex;
  flex-direction:column;
  gap:6px;
}

.atlantis-title{
  font-family:'Cinzel', serif;
  font-size: 2rem;
  letter-spacing:0.06em;
  color: rgba(226,232,240,0.95);
  text-shadow: 0 0 18px rgba(63,255,224,0.22);
}

.subtitle{
  color: rgba(226,232,240,0.70);
  font-size: 0.95rem;
}

/* Atlantis emblem */
.emblem{
  width:38px;
  height:38px;
  filter: drop-shadow(0 0 12px rgba(63,255,224,0.35));
  opacity:0.9;
}

/* ---- Avatar ring ---- */
.avatar-ring{
  width:104px;
  height:104px;
  border-radius:999px;
  display:grid;
  place-items:center;
  padding:3px;
  background: conic-gradient(from 0deg, rgba(63,255,224,0.9), rgba(56,189,248,0.7), rgba(0,209,184,0.9), rgba(243,211,107,0.45), rgba(63,255,224,0.9));
  animation: spin 5.5s linear infinite;
  box-shadow: 0 0 25px rgba(63,255,224,0.22);
}
@keyframes spin{
  from{ transform: rotate(0deg); }
  to  { transform: rotate(360deg); }
}
.avatar{
  width:100px;
  height:100px;
  border-radius:999px;
  background: rgba(0,0,0,0.25);
  border: 2px solid rgba(255,255,255,0.18);
  box-shadow: inset 0 0 18px rgba(63,255,224,0.12);
}

/* ---- Profile rows ---- */
.profile-item{
  margin-bottom: 0.95rem;
  font-size: 1.02rem;
  position:relative;
  z-index:2;
  padding: 10px 12px;
  border-radius: 14px;
  border: 1px solid rgba(255,255,255,0.06);
  background: rgba(255,255,255,0.03);
  transition: 0.25s ease;
}
.profile-item:hover{
  border-color: rgba(63,255,224,0.25);
  background: rgba(63,255,224,0.05);
  box-shadow: 0 0 18px rgba(63,255,224,0.10);
}
.profile-item span{
  color: var(--aqua);
  font-weight: 800;
  letter-spacing:0.02em;
}

/* ---- Button ---- */
.view-challenges-btn{
  display:inline-block;
  margin-top: 1.2rem;
  padding: 0.75rem 1.1rem;
  border-radius: 14px;
  border: 1px solid rgba(63,255,224,0.35);
  color: rgba(226,232,240,0.95);
  background: linear-gradient(90deg, rgba(63,255,224,0.20), rgba(0,209,184,0.16));
  box-shadow: 0 14px 28px rgba(0,0,0,0.28);
  transition: 0.25s ease;
  position:relative;
  overflow:hidden;
  z-index:2;
}
.view-challenges-btn::before{
  content:"";
  position:absolute;
  top:0; left:-60%;
  width:55%;
  height:100%;
  background: linear-gradient(90deg, transparent, rgba(63,255,224,0.22), transparent);
  transform: skewX(-18deg);
  transition: 0.35s ease;
  opacity:0.8;
}
.view-challenges-btn:hover::before{
  left:120%;
}
.view-challenges-btn:hover{
  transform: translateY(-2px);
  border-color: rgba(63,255,224,0.60);
  box-shadow: 0 18px 40px rgba(0,0,0,0.35), 0 0 26px rgba(63,255,224,0.16);
}

/* Make small text consistent */
small{ color: rgba(226,232,240,0.55) !important; }
</style>
</head>

<body>
<div class="particles"></div>

<!-- Sidebar -->
<div class="sidebar">
  <h2 class="text-xl font-bold p-4 border-b border-[rgba(63,255,224,0.25)] relative z-10">
    APIIT CTF
  </h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main -->
<div class="main">
  <div class="profile-container">
    <div class="profile-card">

      <div class="profile-header">
        <div class="avatar-ring">
          <img class="avatar" src="<?= htmlspecialchars($avatarUrl) ?>" alt="Avatar">
        </div>

        <div class="title-wrap">
          <div class="flex items-center gap-3">
            <!-- Atlantis emblem -->
            <svg class="emblem" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M32 5C45 13 53 26 53 39C53 52 44 59 32 59C20 59 11 52 11 39C11 26 19 13 32 5Z" stroke="rgba(63,255,224,0.9)" stroke-width="2"/>
              <path d="M22 40C26 30 38 30 42 40" stroke="rgba(56,189,248,0.8)" stroke-width="2" stroke-linecap="round"/>
              <path d="M18 46C25 42 39 42 46 46" stroke="rgba(0,209,184,0.85)" stroke-width="2" stroke-linecap="round"/>
              <path d="M26 21L32 15L38 21" stroke="rgba(243,211,107,0.75)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M32 15V30" stroke="rgba(243,211,107,0.55)" stroke-width="2" stroke-linecap="round"/>
            </svg>

            <h2 class="atlantis-title"><?= htmlspecialchars($user['username']) ?> 🔱</h2>
          </div>
          <div class="subtitle">Citizen of the Lost City • Profile Console</div>
        </div>
      </div>

      <div class="profile-item"><span>Username:</span> <?= htmlspecialchars($user['username']) ?></div>
      <div class="profile-item"><span>Total Score:</span> <?= (int)$user['score'] ?></div>
      <div class="profile-item"><span>Challenges Solved:</span> <?= (int)$solvedCount ?></div>
      <div class="profile-item"><span>Account Created:</span> <?= date('F j, Y, g:i a', strtotime($user['created_at'])) ?></div>
      <div class="profile-item">
        <span>Last Login:</span>
        <?= htmlspecialchars(timeAgo($user['last_login'])) ?>
        <small>(<?= date('F j, Y, g:i a', strtotime($user['last_login'])) ?>)</small>
      </div>

      <a href="challenges.php" class="view-challenges-btn">Explore the Ruins (Challenges)</a>

    </div>
  </div>
</div>

</body>
</html>
