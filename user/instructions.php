<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, $_SESSION['user_id'], "Visited Instructions Page", $_SERVER['REQUEST_URI']);

$username = $_SESSION['username'] ?? 'Player';
$lastUpdated = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructions — APIIT CTF</title>

<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

body{
    font-family:'Share Tech Mono', monospace;
    background:#0b0f12;
    color:#c9f7e4;
    overflow-x:hidden;
}

.sidebar{
    background:#071018;
    border-right:1px solid rgba(45,226,138,0.2);
    height:100vh;
    position:fixed;
    width:16rem;
    z-index:10;
}

.sidebar a{
    display:block;
    padding:12px;
    color:#c9f7e4;
    border-bottom:1px solid rgba(255,255,255,0.05);
    transition:0.3s;
}

.sidebar a:hover{
    background:rgba(45,226,138,0.1);
    color:#2de28a;
}

.card{
    background: rgba(8,11,18,0.95);
    border:1px solid rgba(45,226,138,0.3);
    border-radius:14px;
    padding:20px;
    box-shadow:0 0 10px rgba(0,0,0,0.4);
    transition: transform 0.3s, box-shadow 0.3s;
}

.card:hover{
    transform: translateY(-4px);
    box-shadow:0 0 25px rgba(34,197,94,0.6);
    border-color: rgba(34,197,94,0.5);
}

.section-title{
    font-size:1.2rem;
    font-weight:bold;
    margin-bottom:0.75rem;
}

.green{ color:#2de28a; }
.red{ color:#f87171; }

.meta{
    font-size:0.8rem;
    color:#9becc2;
}

/* Cyber Background */
#cyber-bg{ position:fixed; inset:0; z-index:-2; }
.scanlines{
    position:fixed; inset:0; z-index:-1;
    pointer-events:none;
    background:repeating-linear-gradient(
        to bottom,
        rgba(255,255,255,0.02),
        rgba(255,255,255,0.02) 1px,
        transparent 1px,
        transparent 4px
    );
    animation: scan 6s linear infinite;
}
@keyframes scan{
    0%{background-position-y:0;}
    100%{background-position-y:100%;}
}
</style>
</head>

<body class="h-screen flex">

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

<!-- Header -->
<div class="card">
    <h1 class="text-3xl font-bold text-green-400">Welcome, <?= htmlspecialchars($username) ?> 👋</h1>
    <p class="meta mt-1">Last updated: <?= htmlspecialchars($lastUpdated) ?></p>
</div>

<!-- Critical Notice -->
<div class="card border-yellow-400">
    <p class="text-yellow-300 text-sm">
        ⚠️ Important: Viewing hints deducts points. Leaderboard and hints will be disabled during the final hour.
    </p>
</div>

<!-- General Instructions -->
<div class="card">
    <h2 class="section-title green">📖 General Instructions</h2>
    <ul class="list-disc list-inside space-y-2 text-sm">
        <li>Each challenge contains one valid flag.</li>
        <li>Submit flags in the correct format.</li>
        <li>Scores update instantly on valid submission.</li>
        <li>All user actions are logged for security.</li>
        <li>Ethical hacking principles must be followed.</li>
    </ul>
</div>

<!-- Hints -->
<div class="card">
    <h2 class="section-title green">💡 Hints & Point Penalties</h2>
    <ul class="list-disc list-inside space-y-2 text-sm">
        <li>Hints are available only during the first 4 hours.</li>
        <li>Each hint has a predefined point deduction.</li>
        <li>Points are deducted immediately upon viewing.</li>
        <li>Hints cannot be re-hidden once revealed.</li>
    </ul>
</div>

<!-- ⏱ FINAL HOUR RULES -->
<div class="card border-yellow-500">
    <h2 class="section-title text-yellow-400">⏱ Final Hour Rules (Last 1 Hour)</h2>
    <ul class="list-disc list-inside space-y-2 text-sm text-yellow-300">
        <li>The leaderboard will be hidden after the first 4 hours.</li>
        <li>Hints will be completely disabled after 4 hours.</li>
        <li>No score visibility during the final hour.</li>
        <li>Players must focus on solving remaining challenges.</li>
        <li>Use the final hour to complete documentation and reports.</li>
    </ul>

    <p class="text-yellow-400 text-sm mt-3">
        📝 Tip: A detailed report can make a difference during evaluations.
    </p>
</div>

<!-- 🚫 Prohibited Actions -->
<div class="card border-red-500">
    <h2 class="section-title red">🚫 Prohibited Actions</h2>
    <ul class="list-disc list-inside space-y-2 text-sm text-red-300">
        <li>Sharing flags, hints, or solutions with other players outside your team.</li>
        <li>Attacking the CTF infrastructure.</li>
        <li>Brute-forcing flags or abusing submissions.</li>
        <li>Using automated tools unless allowed.</li>
        <li>Exploiting vulnerabilities outside challenge scope.</li>
        <li>Collaboration between individual players or teams.</li>
    </ul>
    <p class="text-red-400 text-sm mt-3">
        ❗ Violations may lead to disqualification or score reset.
    </p>
</div>

<!-- Fair Play -->
<div class="card">
    <h2 class="section-title green">🛡 Fair Play Policy</h2>
    <ul class="list-disc list-inside space-y-2 text-sm">
        <li>Report unintended platform issues responsibly.</li>
        <li>Respect competitors and organizers.</li>
    </ul>
</div>

<!-- Final -->
<div class="card">
    <h2 class="section-title green">🚀 Final Note</h2>
    <p class="text-sm">
        Stay focused, manage your time wisely, and document your work clearly.
        Skill, ethics, and professionalism define true winners. 💚
    </p>
</div>

</div>

<!-- Background Script -->
<script>
const c=document.getElementById("cyber-bg"),x=c.getContext("2d");
function r(){c.width=innerWidth;c.height=innerHeight}
r();addEventListener("resize",r);
const n=[...Array(80)].map(()=>({x:Math.random()*c.width,y:Math.random()*c.height,vx:(Math.random()-.5)*.4,vy:(Math.random()-.5)*.4}));
(function d(){
x.clearRect(0,0,c.width,c.height);
n.forEach((a,i)=>{
a.x+=a.vx;a.y+=a.vy;
if(a.x<0||a.x>c.width)a.vx*=-1;
if(a.y<0||a.y>c.height)a.vy*=-1;
x.fillStyle="#22c55e";x.fillRect(a.x,a.y,2,2);
n.slice(i+1).forEach(b=>{
const D=Math.hypot(a.x-b.x,a.y-b.y);
if(D<120){x.strokeStyle="rgba(34,197,94,.15)";x.beginPath();x.moveTo(a.x,a.y);x.lineTo(b.x,b.y);x.stroke();}
});
});
requestAnimationFrame(d);
})();
</script>

</body>
</html>
