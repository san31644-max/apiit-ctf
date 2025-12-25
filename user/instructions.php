<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, (int)$_SESSION['user_id'], "Visited Instructions Page", $_SERVER['REQUEST_URI']);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$username    = $_SESSION['username'] ?? 'Explorer';
$lastUpdated = date('Y-m-d H:i:s');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Instructions — Atlantis CTF</title>

<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --aqua2:#22d3ee;
  --teal:#00d1b8;
  --gold:#f5d27b;
  --text:#e6faff;

  --deep1:#031b34;
  --deep2:#020617;
  --deep3:#010b18;

  --glass: rgba(3, 23, 42, 0.48);
  --stroke: rgba(56,247,255,0.20);
  --shadow: rgba(56,247,255,0.12);
}

html,body{height:100%;}
body{
  margin:0;
  color:var(--text);
  font-family:'Share Tech Mono', monospace;
  overflow-x:hidden;
  background:
    radial-gradient(1200px 700px at 20% 0%, rgba(56,189,248,0.20), transparent 60%),
    radial-gradient(900px 600px at 85% 10%, rgba(0,209,184,0.16), transparent 55%),
    radial-gradient(900px 700px at 50% 110%, rgba(245,210,123,0.10), transparent 60%),
    linear-gradient(180deg,var(--deep1),var(--deep2) 55%,var(--deep3));
}

/* ===== Ambient layers ===== */
.caustics{
  position:fixed; inset:-30%;
  pointer-events:none;
  z-index:-8;
  background:
    radial-gradient(circle at 20% 20%, rgba(56,247,255,0.10), transparent 45%),
    radial-gradient(circle at 70% 30%, rgba(34,211,238,0.08), transparent 48%),
    radial-gradient(circle at 40% 70%, rgba(0,209,184,0.08), transparent 50%),
    conic-gradient(from 90deg at 50% 50%, rgba(56,247,255,0.06), transparent 25%, rgba(34,211,238,0.05), transparent 60%, rgba(0,209,184,0.05));
  filter: blur(9px);
  opacity:0.85;
  animation: causticDrift 14s ease-in-out infinite alternate;
}
@keyframes causticDrift{
  from{ transform: translate3d(-2%,-1%,0) rotate(-1deg) scale(1.03); }
  to  { transform: translate3d( 2%, 1%,0) rotate( 1deg) scale(1.08); }
}

.fogGlow{
  position:fixed; inset:-20%;
  pointer-events:none;
  z-index:-7;
  background:
    radial-gradient(800px 500px at 15% 20%, rgba(56,247,255,0.10), transparent 60%),
    radial-gradient(900px 600px at 85% 30%, rgba(245,210,123,0.08), transparent 65%),
    radial-gradient(900px 700px at 50% 90%, rgba(0,209,184,0.08), transparent 65%);
  filter: blur(18px);
  opacity:0.70;
  animation: fog 12s ease-in-out infinite alternate;
}
@keyframes fog{
  from{ transform: translate(-1%, -1%) scale(1.01); }
  to  { transform: translate( 1%,  1%) scale(1.05); }
}

.scanlines{
  position:fixed; inset:0;
  pointer-events:none;
  z-index:-6;
  background: repeating-linear-gradient(to bottom,
    rgba(255,255,255,0.018), rgba(255,255,255,0.018) 1px,
    transparent 1px, transparent 4px
  );
  opacity:.40;
}

.grain{
  position:fixed; inset:0;
  pointer-events:none;
  z-index:-5;
  opacity:.10;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");
}

/* Bubble canvas */
#bubbles{
  position:fixed; inset:0;
  z-index:-4;
  pointer-events:none;
  opacity:0.55;
}

/* ===== Layout ===== */
.shell{min-height:100vh; display:flex;}

.sidebar{
  width:16.5rem;
  position:fixed;
  inset:0 auto 0 0;
  z-index:20;
  background: linear-gradient(180deg, rgba(2, 10, 22, 0.95), rgba(3, 18, 34, 0.88));
  border-right: 1px solid rgba(56,247,255,0.24);
  box-shadow: 0 0 30px rgba(0,209,184,0.10);
  overflow:hidden;
}
.sidebar::after{
  content:"";
  position:absolute; inset:-40%;
  background:
    radial-gradient(circle at 30% 30%, rgba(56,247,255,0.08), transparent 45%),
    radial-gradient(circle at 70% 55%, rgba(0,209,184,0.07), transparent 55%);
  filter: blur(12px);
  opacity:0.55;
  animation: sidebarGlow 10s ease-in-out infinite alternate;
  pointer-events:none;
}
@keyframes sidebarGlow{
  from{ transform: translate(-2%,-1%) scale(1.02); }
  to  { transform: translate( 2%, 1%) scale(1.06); }
}

.brand{
  padding:18px 16px;
  border-bottom:1px solid rgba(56,247,255,0.16);
}
.brand .t{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.16em;
  color:rgba(56,247,255,0.95);
  text-shadow:0 0 18px rgba(56,247,255,0.30);
}
.brand .s{
  margin-top:6px;
  font-size:12px;
  color:rgba(245,210,123,0.92);
  letter-spacing:.12em;
}

.nav a{
  display:flex; gap:10px; align-items:center;
  padding:12px 14px;
  color: rgba(226,232,240,0.92);
  border-bottom:1px solid rgba(255,255,255,0.06);
  transition:0.25s ease;
  position:relative;
  z-index:1;
}
.nav a::after{
  content:"";
  position:absolute; left:0; top:0; bottom:0;
  width:0px;
  background: linear-gradient(180deg, transparent, rgba(56,247,255,0.35), transparent);
  transition:0.25s ease;
}
.nav a:hover{
  background: rgba(56,247,255,0.08);
  color: var(--aqua);
  text-shadow: 0 0 14px rgba(56,247,255,0.16);
}
.nav a:hover::after{ width:4px; }
.nav a.active{
  background: rgba(56,247,255,0.12);
  border-left:3px solid rgba(245,210,123,0.92);
}
.nav a.danger{ color: rgba(251,113,133,0.95); }
.nav a.danger:hover{ background: rgba(251,113,133,0.10); }

.main{
  margin-left:16.5rem;
  width:calc(100% - 16.5rem);
  padding:22px;
  overflow:auto;
}

.panel{
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  background: var(--glass);
  border: 1px solid rgba(56,247,255,0.18);
  box-shadow: 0 0 55px var(--shadow), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 22px;
}

/* ===== Cards with proximity glow + tilt ===== */
.card{
  --g:0;
  --rx:0deg;
  --ry:0deg;
  border-radius:22px;
  border:1px solid rgba(56,247,255,0.18);
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.06));
  box-shadow:
    0 0 calc(var(--g) * 28px) rgba(56,247,255,0.22),
    inset 0 0 18px rgba(255,255,255,0.03);
  padding:18px;
  transition: transform .12s linear, box-shadow .12s linear, border-color .2s ease;
  transform: perspective(900px) rotateX(var(--rx)) rotateY(var(--ry));
  position:relative;
  overflow:hidden;
}
.card::before{
  content:"";
  position:absolute; inset:auto -60px -70px auto;
  width:200px;height:200px;
  background: radial-gradient(circle, rgba(56,247,255,0.14), transparent 62%);
}
.card:hover{
  border-color: rgba(56,247,255,0.34);
}

/* Header shimmer */
.hTitle{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.14em;
  color: rgba(56,247,255,0.92);
  text-shadow: 0 0 18px rgba(56,247,255,0.22);
  position:relative;
}
.hTitle::after{
  content:"";
  position:absolute; inset:-40% -30%;
  background: linear-gradient(120deg, transparent, rgba(245,210,123,0.14), transparent);
  transform: translateX(-40%);
  animation: shimmer 4.6s ease-in-out infinite;
  pointer-events:none;
}
@keyframes shimmer{
  0%{ transform: translateX(-40%) rotate(6deg); }
  50%{ transform: translateX(40%) rotate(6deg); }
  100%{ transform: translateX(-40%) rotate(6deg); }
}

.meta{font-size:12px;color: rgba(230,250,255,0.70);}
.green{ color: rgba(56,247,255,0.95); }
.gold{ color: rgba(245,210,123,0.95); }
.warnBox{ border-color: rgba(245,158,11,0.35) !important; }
.dangerBox{ border-color: rgba(244,63,94,0.35) !important; }

@media (max-width: 860px){
  .sidebar{position:static;width:100%;height:auto;}
  .main{margin-left:0;width:100%;}
}
</style>
</head>

<body>
<div class="caustics"></div>
<div class="fogGlow"></div>
<div class="scanlines"></div>
<div class="grain"></div>
<canvas id="bubbles"></canvas>

<div class="shell">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="t">ATLANTIS CTF</div>
      <div class="s">EXPLORER CONSOLE</div>
    </div>
    <nav class="nav">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a href="challenges.php">🛠 Challenges</a>
      <a href="leaderboard.php">🏆 Leaderboard</a>
      <a class="active" href="instructions.php">📖 Instructions</a>
      <a href="hints.php">💡 Hints</a>
      <a href="profile.php">👤 Profile</a>
      <a class="danger" href="../logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main -->
  <main class="main space-y-6">

    <!-- Header -->
    <section class="card">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <div class="hTitle text-2xl md:text-3xl">📖 ATLANTIS INSTRUCTIONS</div>
          <div class="meta mt-2">Welcome, <span class="gold font-bold"><?= h($username) ?></span> • Last updated: <?= h($lastUpdated) ?></div>
        </div>
        <div class="meta">
          “Stay ethical. Stay sharp. Explore the depths.” 🔱
        </div>
      </div>
    </section>

    <!-- Critical Notice -->
    <section class="card warnBox">
      <div class="text-sm" style="color: rgba(255,240,205,0.95);">
        ⚠️ Important: Viewing hints deducts points. Leaderboard and hints will be disabled during the final hour.
      </div>
    </section>

    <!-- General Instructions -->
    <section class="card">
      <div class="hTitle text-lg">📖 General Instructions</div>
      <ul class="list-disc list-inside space-y-2 text-sm mt-3" style="color: rgba(230,250,255,0.80);">
        <li>Each challenge contains one valid flag.</li>
        <li>Submit flags in the correct format.</li>
        <li>Scores update instantly on valid submission.</li>
        <li>All user actions are logged for security.</li>
        <li>Ethical hacking principles must be followed.</li>
      </ul>
    </section>

    <!-- Hints -->
    <section class="card">
      <div class="hTitle text-lg">💡 Hints & Point Penalties</div>
      <ul class="list-disc list-inside space-y-2 text-sm mt-3" style="color: rgba(230,250,255,0.80);">
        <li>Hints are available only during the first 4 hours.</li>
        <li>Each hint has a predefined point deduction.</li>
        <li>Points are deducted immediately upon viewing.</li>
        <li>Hints cannot be re-hidden once revealed.</li>
      </ul>
    </section>

    <!-- Final hour -->
    <section class="card warnBox">
      <div class="hTitle text-lg" style="color: rgba(255,240,205,0.95);">⏱ Final Hour Rules (Last 1 Hour)</div>
      <ul class="list-disc list-inside space-y-2 text-sm mt-3" style="color: rgba(255,240,205,0.92);">
        <li>The leaderboard will be hidden after the first 4 hours.</li>
        <li>Hints will be completely disabled after 4 hours.</li>
        <li>No score visibility during the final hour.</li>
        <li>Players must focus on solving remaining challenges.</li>
        <li>Use the final hour to complete documentation and reports.</li>
      </ul>
      <p class="text-sm mt-3" style="color: rgba(255,240,205,0.95);">
        📝 Tip: A detailed report can make a difference during evaluations.
      </p>
    </section>

    <!-- Prohibited -->
    <section class="card dangerBox">
      <div class="hTitle text-lg" style="color: rgba(255,210,220,0.95);">🚫 Prohibited Actions</div>
      <ul class="list-disc list-inside space-y-2 text-sm mt-3" style="color: rgba(255,210,220,0.92);">
        <li>Sharing flags, hints, or solutions with other players outside your team.</li>
        <li>Attacking the CTF infrastructure.</li>
        <li>Brute-forcing flags or abusing submissions.</li>
        <li>Using automated tools unless allowed.</li>
        <li>Exploiting vulnerabilities outside challenge scope.</li>
        <li>Collaboration between individual players or teams.</li>
      </ul>
      <p class="text-sm mt-3" style="color: rgba(255,210,220,0.95);">
        ❗ Violations may lead to disqualification or score reset.
      </p>
    </section>

    <!-- Fair Play -->
    <section class="card">
      <div class="hTitle text-lg">🛡 Fair Play Policy</div>
      <ul class="list-disc list-inside space-y-2 text-sm mt-3" style="color: rgba(230,250,255,0.80);">
        <li>Report unintended platform issues responsibly.</li>
        <li>Respect competitors and organizers.</li>
      </ul>
    </section>

    <!-- Final -->
    <section class="card">
      <div class="hTitle text-lg">🚀 Final Note</div>
      <p class="text-sm mt-3" style="color: rgba(230,250,255,0.80);">
        Stay focused, manage your time wisely, and document your work clearly.
        Skill, ethics, and professionalism define true winners. 💚
      </p>
    </section>

  </main>
</div>

<script>
/* ===== Bubble canvas (Atlantis particles) ===== */
const bub = document.getElementById('bubbles');
const btx = bub.getContext('2d', { alpha:true });
let W=0,H=0, bubbles=[];
function resize(){
  W = bub.width = window.innerWidth;
  H = bub.height = window.innerHeight;
}
window.addEventListener('resize', resize);
resize();

function rand(min,max){ return Math.random()*(max-min)+min; }
function spawn(n=42){
  bubbles=[];
  for(let i=0;i<n;i++){
    bubbles.push({
      x: rand(0,W),
      y: rand(0,H),
      r: rand(1.2, 5.5),
      s: rand(0.20, 0.70),
      a: rand(0.05, 0.18),
      drift: rand(-0.30,0.30),
      hue: Math.random()<0.12 ? 'rgba(245,210,123,' : (Math.random()<0.5 ? 'rgba(56,247,255,' : 'rgba(0,209,184,')
    });
  }
}
spawn();

function tick(){
  btx.clearRect(0,0,W,H);
  for(const b of bubbles){
    b.y -= b.s*2.0;
    b.x += b.drift;
    if(b.y < -12){ b.y = H + 12; b.x = rand(0,W); }
    if(b.x < -12) b.x = W+12;
    if(b.x > W+12) b.x = -12;

    btx.beginPath();
    btx.arc(b.x,b.y,b.r,0,Math.PI*2);
    btx.fillStyle = b.hue + b.a + ')';
    btx.fill();
  }
  requestAnimationFrame(tick);
}
tick();

/* ===== Proximity glow + tilt for cards ===== */
const cards = document.querySelectorAll('.card');
document.addEventListener('mousemove', (e)=>{
  cards.forEach(c=>{
    const r = c.getBoundingClientRect();
    const cx = r.left + r.width/2;
    const cy = r.top + r.height/2;
    const dx = e.clientX - cx;
    const dy = e.clientY - cy;
    const dist = Math.sqrt(dx*dx + dy*dy);

    const g = Math.max(0, 1 - dist/320);
    c.style.setProperty('--g', g.toFixed(2));

    const clamp = (v, m)=> Math.max(-m, Math.min(m, v));
    const ry = clamp((dx / (r.width/2)) * 6, 8);
    const rx = clamp((-dy / (r.height/2)) * 6, 8);

    if(dist < 420){
      c.style.setProperty('--rx', rx.toFixed(2)+'deg');
      c.style.setProperty('--ry', ry.toFixed(2)+'deg');
    } else {
      c.style.setProperty('--rx','0deg');
      c.style.setProperty('--ry','0deg');
    }
  });
});
</script>

</body>
</html>
