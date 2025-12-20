<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, $_SESSION['user_id'], "Visited Dashboard", $_SERVER['REQUEST_URI']);

// ---------- Dynamic stats ----------
$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Player';

$solvedCount = 0;
$score = null;
$rank = '—';
$statsSource = [];

// -- helper functions --
function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl");
        $stmt->execute(['tbl'=>$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch(Exception $e) { return false; }
}

function getTableColumns($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl");
        $stmt->execute(['tbl'=>$table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch(Exception $e) { return []; }
}

// Candidate tables & columns
$candidateSolveTables = ['solves','user_solved','submissions','solve','solved'];
$candidateUserCols = ['user_id','user','uid','userid'];
$candidateChallengeTables = ['challenges','challenge','ctf_challenges','problems','tasks'];
$possiblePointCols = ['points','score','value','pt'];

// ---------- SCORE & RANK COMPUTATION ----------
try {
    if(tableExists($pdo,'users')){
        $userCols = getTableColumns($pdo,'users');
        $scoreCol = null;
        foreach(['score','points','total_score'] as $c){ if(in_array($c,$userCols)){$scoreCol=$c; break;} }
        if($scoreCol){
            $stmt = $pdo->prepare("SELECT `$scoreCol` FROM `users` WHERE `id`=:uid LIMIT 1");
            $stmt->execute(['uid'=>$userId]);
            $s = $stmt->fetchColumn();
            if($s!==false && $s!==null){
                $score = (int)$s;
                $statsSource[]="users.$scoreCol";
                $rstmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE COALESCE(`$scoreCol`,0) > :myscore");
                $rstmt->execute(['myscore'=>$score]);
                $rank = (int)$rstmt->fetchColumn()+1;
            }
        }
    }
} catch(Exception $e){}

// Fallback if score table not found
if($score===null){ $score=0; $statsSource[]='fallback:0'; $rank='—'; }

// ---------- SOLVED COUNT ----------
try{
    $foundSolveTable=null;
    foreach($candidateSolveTables as $tbl){ if(tableExists($pdo,$tbl)){$foundSolveTable=$tbl; break;} }
    if($foundSolveTable){
        $solveCols=getTableColumns($pdo,$foundSolveTable);
        $userCol=null; foreach($candidateUserCols as $c){if(in_array($c,$solveCols)){$userCol=$c; break;}}
        $chalIdColInSolve=null; foreach(['challenge_id','chal_id','challenge','challengeid','cid','problem_id'] as $c){ if(in_array($c,$solveCols)){$chalIdColInSolve=$c; break;}}
        if($userCol && $chalIdColInSolve){
            $stmt=$pdo->prepare("SELECT COUNT(DISTINCT `$chalIdColInSolve`) FROM `$foundSolveTable` WHERE `$userCol`=:uid");
            $stmt->execute(['uid'=>$userId]);
            $solvedCount=(int)$stmt->fetchColumn();
            $statsSource[]="count distinct ($foundSolveTable.$chalIdColInSolve)";
        }
    }
}catch(Exception $e){}
if($solvedCount===0 && $score>0){ $solvedCount='≈'.ceil($score/100); $statsSource[]='estimated-from-score'; }

$lastUpdated=date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

/* ----- GLOBAL STYLES ----- */
body{font-family:'Share Tech Mono', monospace;background:#0b0f12;color:#c9f7e4;overflow-x:hidden;}
.sidebar{background:#071018;border-right:1px solid rgba(45,226,138,0.2);height:100vh;position:fixed;width:16rem;z-index:10;}
.sidebar a{display:block;padding:12px;color:#c9f7e4;border-bottom:1px solid rgba(255,255,255,0.05);transition:0.3s;}
.sidebar a:hover{background:rgba(45,226,138,0.1);color:#2de28a;}
.card{background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3); border-radius:14px; padding:20px; box-shadow:0 0 10px rgba(0,0,0,0.4); transition: transform 0.3s, box-shadow 0.3s; }
.card:hover{transform: translateY(-4px); box-shadow:0 0 25px rgba(34,197,94,0.6); border-color: rgba(34,197,94,0.5);}
.section-title{color:#2de28a;font-size:1.2rem;font-weight:bold;margin-bottom:0.75rem;}
.meta{font-size:0.8rem;color:#9becc2;opacity:0.9;}

/* ----- CYBER BACKGROUND ----- */
#cyber-bg{position:fixed; inset:0; z-index:-2;}
.scanlines{position:fixed; inset:0; z-index:-1; pointer-events:none; background:repeating-linear-gradient(to bottom, rgba(255,255,255,0.02), rgba(255,255,255,0.02) 1px, transparent 1px, transparent 4px); animation: scan 6s linear infinite;}
@keyframes scan{0%{background-position-y:0;}100%{background-position-y:100%;}}

/* ----- NEON CARD HOVER ----- */
.card h2, .card h3 { transition: text-shadow 0.3s; }
.card:hover h2, .card:hover h3 { text-shadow: 0 0 12px #22c55e; }

/* ----- PRIZE / CATEGORY GRADIENTS ----- */
.prize-card{border-radius:12px; padding:20px; font-weight:bold; text-shadow:0 0 5px #000; transition: transform 0.3s, box-shadow 0.3s;}
.prize-card:hover{transform: translateY(-4px); box-shadow:0 0 25px #22c55e;}

/* ----- COUNTER ANIMATION ----- */
.counter{transition: all 0.3s ease;}
</style>
</head>
<body class="h-screen flex">

<!-- Cyber Background -->
<canvas id="cyber-bg"></canvas>
<div class="scanlines"></div>

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

<!-- Main Content -->
<div class="flex-1 p-6 ml-64 overflow-auto space-y-6">

<!-- Welcome Card -->
<div class="card">
<div class="flex items-center justify-between">
  <div>
    <h1 class="text-3xl font-bold text-green-400">Welcome, <?= htmlspecialchars($username) ?> 🎉</h1>
    <p class="mt-1 meta">Last updated: <?= htmlspecialchars($lastUpdated) ?></p>
  </div>
  <div class="text-right">
    <p class="meta">Data source: <?= implode(', ', $statsSource) ?></p>
  </div>
</div>
</div>

<!-- Quick Stats -->
<div class="card">
<h2 class="section-title">📊 Quick Stats</h2>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
  <div class="p-4 text-center">
    <div class="text-3xl font-bold text-green-300 counter" data-target="<?= htmlspecialchars((string)$solvedCount) ?>">0</div>
    <div class="meta">Challenges Solved</div>
  </div>
  <div class="p-4 text-center">
    <div class="text-3xl font-bold text-green-300 counter" data-target="<?= htmlspecialchars((string)$rank) ?>">0</div>
    <div class="meta">Current Rank</div>
  </div>
  <div class="p-4 text-center">
    <div class="text-3xl font-bold text-green-300 counter" data-target="<?= htmlspecialchars((string)$score) ?>">0</div>
    <div class="meta">Score</div>
  </div>
</div>
</div>

<!-- Prize Pool Card -->
<div class="card">
<h2 class="section-title">🏆 Prize Pool</h2>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
  <div class="prize-card" style="background:linear-gradient(145deg,#FFD700,#FFEC8B); color:#000;">1️⃣ First Place<br><span class="text-3xl font-extrabold">20,000 LKR</span></div>
  <div class="prize-card" style="background:linear-gradient(145deg,#C0C0C0,#E0E0E0); color:#000;">2️⃣ Second Place<br><span class="text-3xl font-extrabold">15,000 LKR</span></div>
  <div class="prize-card" style="background:linear-gradient(145deg,#CD7F32,#D9A066); color:#000;">3️⃣ Third Place<br><span class="text-3xl font-extrabold">10,000 LKR</span></div>
</div>
<p class="mt-2 text-sm text-gray-300">Prizes awarded to top 3 competitors on the leaderboard.</p>
</div>

<!-- Challenge Categories -->
<div>
<h2 class="section-title">🕹️ Challenge Categories</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <div class="card"><h3 class="font-bold text-green-400">🌐 Web</h3><p class="mt-2 text-sm">XSS, SQLi, SSRF…</p></div>
  <div class="card"><h3 class="font-bold text-green-400">🔐 Crypto</h3><p class="mt-2 text-sm">Ciphers & crypto puzzles.</p></div>
  <div class="card"><h3 class="font-bold text-green-400">🕵️ Forensics</h3><p class="mt-2 text-sm">PCAP, stego, images.</p></div>
  <div class="card"><h3 class="font-bold text-green-400">⚙️ Reversing</h3><p class="mt-2 text-sm">Binaries & obstacles.</p></div>
  <div class="card"><h3 class="font-bold text-green-400">💣 Pwn</h3><p class="mt-2 text-sm">Exploits & memory bugs.</p></div>
  <div class="card"><h3 class="font-bold text-green-400">🧩 Misc</h3><p class="mt-2 text-sm">Fun or mixed challenges.</p></div>
</div>
</div>

<!-- Educational Resources -->
<div>
<h2 class="section-title">📚 Educational Resources & Tips</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <div class="card">🔎 TryHackMe — guided CTF labs</div>
  <div class="card">💻 Hack The Box — practice hacking</div>
  <div class="card">📖 CTFTime.org — track competitions</div>
  <div class="card">🛡️ OWASP Top 10 — web vulnerabilities</div>
  <div class="card">🧩 Cryptopals — crypto challenges</div>
  <div class="card">📝 Document all steps during CTFs</div>
  <div class="card">⚡ Learn Burp Suite, Wireshark, Nmap</div>
  <div class="card">🐍 Python & Bash scripting for automation</div>
  <div class="card">💡 Read past write-ups responsibly</div>
</div>
</div>

</div>

<!-- ----- SCRIPTS ----- -->
<script>
// CYBER BACKGROUND
const canvas=document.getElementById("cyber-bg");
const ctx=canvas.getContext("2d");
function resize(){canvas.width=innerWidth; canvas.height=innerHeight;}
resize(); addEventListener("resize",resize);
const nodes=[];
for(let i=0;i<80;i++){nodes.push({x:Math.random()*canvas.width,y:Math.random()*canvas.height,vx:(Math.random()-0.5)*0.4,vy:(Math.random()-0.5)*0.4});}
function drawNetwork(){
  ctx.clearRect(0,0,canvas.width,canvas.height);
  for(let i=0;i<nodes.length;i++){
    const n=nodes[i]; n.x+=n.vx; n.y+=n.vy;
    if(n.x<0||n.x>canvas.width) n.vx*=-1;
    if(n.y<0||n.y>canvas.height) n.vy*=-1;
    ctx.fillStyle="#22c55e"; ctx.fillRect(n.x,n.y,2,2);
    for(let j=i+1;j<nodes.length;j++){
      const m=nodes[j],dx=n.x-m.x,dy=n.y-m.y,dist=Math.sqrt(dx*dx+dy*dy);
      if(dist<120){ctx.strokeStyle="rgba(34,197,94,0.15)"; ctx.beginPath(); ctx.moveTo(n.x,n.y); ctx.lineTo(m.x,m.y); ctx.stroke();}
    }
  }
  requestAnimationFrame(drawNetwork);
}
drawNetwork();

// COUNTER ANIMATION
document.querySelectorAll('.counter').forEach(el=>{
  const target=parseInt(el.dataset.target);
  let current=0;
  const step=Math.max(1, Math.floor(target/50));
  function update(){ current+=step; if(current>=target){el.textContent=target;} else {el.textContent=current; requestAnimationFrame(update);} }
  requestAnimationFrame(update);
});
</script>
</body>
</html>
