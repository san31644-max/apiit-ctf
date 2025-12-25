<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/page_guard_json.php";
guard_page_json('leaderboard');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: ../index.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId   = (int)($_SESSION['user_id'] ?? 0);
$username = $_SESSION['username'] ?? 'Explorer';

$limit = 60; // top N (including podium)
$rows = [];
$useWindow = true;

/* ===== TOP N with unique places (ROW_NUMBER) ===== */
try {
  $stmt = $pdo->prepare("
    SELECT
      id,
      username,
      COALESCE(score,0) AS score,
      ROW_NUMBER() OVER (ORDER BY COALESCE(score,0) DESC, username ASC) AS place
    FROM users
    WHERE role='user'
    ORDER BY COALESCE(score,0) DESC, username ASC
    LIMIT :lim
  ");
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
  $useWindow = false;
}

/* ===== Fallback: compute unique places in PHP ===== */
if (!$useWindow) {
  $stmt = $pdo->prepare("
    SELECT id, username, COALESCE(score,0) AS score
    FROM users
    WHERE role='user'
    ORDER BY COALESCE(score,0) DESC, username ASC
    LIMIT :lim
  ");
  $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
  $stmt->execute();
  $tmp = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

  foreach ($tmp as $i => $r) {
    $r['place'] = $i + 1; // unique place always
    $rows[] = $r;
  }
}

/* ===== Current user score ===== */
$myScore = 0;
try {
  $stmt = $pdo->prepare("SELECT COALESCE(score,0) AS score, username FROM users WHERE id=:id LIMIT 1");
  $stmt->execute(['id' => $userId]);
  $meRow = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($meRow) {
    $myScore = (int)($meRow['score'] ?? 0);
    $username = $meRow['username'] ?? $username;
  }
} catch (Exception $e) {
  $myScore = 0;
}

/* ===== My global place (matches ORDER BY score DESC, username ASC) ===== */
$myPlace = null;
try {
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM users
    WHERE role='user'
      AND (
        COALESCE(score,0) > :s
        OR (COALESCE(score,0) = :s AND username < :u)
      )
  ");
  $stmt->execute(['s' => $myScore, 'u' => $username]);
  $myPlace = (int)$stmt->fetchColumn() + 1;
} catch (Exception $e) {
  $myPlace = null;
}

/* ===== Total players ===== */
$totalPlayers = 0;
try {
  $totalPlayers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
} catch (Exception $e) {
  $totalPlayers = 0;
}

/* ===== Podium (Top 3) ===== */
$top1 = $rows[0] ?? null;
$top2 = $rows[1] ?? null;
$top3 = $rows[2] ?? null;

/* ===== Grid should start from place 4 ===== */
$gridRows = array_slice($rows, 3); // remove first 3
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Leaderboard — Atlantis CTF</title>
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

  --glass: rgba(3, 23, 42, 0.44);
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
    radial-gradient(900px 700px at 50% 110%, rgba(245,210,123,0.08), transparent 60%),
    linear-gradient(180deg,var(--deep1),var(--deep2) 55%,var(--deep3));
}

/* ===== DYNAMIC BACKGROUND ===== */
.caustics{
  position:fixed; inset:-30%;
  pointer-events:none;
  z-index:-6;
  background:
    radial-gradient(circle at 20% 20%, rgba(56,247,255,0.10), transparent 45%),
    radial-gradient(circle at 70% 30%, rgba(34,211,238,0.08), transparent 48%),
    radial-gradient(circle at 40% 70%, rgba(0,209,184,0.08), transparent 50%),
    conic-gradient(from 90deg at 50% 50%, rgba(56,247,255,0.06), transparent 25%, rgba(34,211,238,0.05), transparent 60%, rgba(0,209,184,0.05));
  filter: blur(8px);
  opacity:0.85;
  animation: causticDrift 14s ease-in-out infinite alternate;
}
@keyframes causticDrift{
  from{ transform: translate3d(-2%,-1%,0) rotate(-1deg) scale(1.03); }
  to  { transform: translate3d( 2%, 1%,0) rotate( 1deg) scale(1.08); }
}

.scanlines{
  position:fixed; inset:0;
  pointer-events:none;
  z-index:-5;
  background: repeating-linear-gradient(to bottom,
    rgba(255,255,255,0.018), rgba(255,255,255,0.018) 1px,
    transparent 1px, transparent 4px
  );
  opacity:.40;
}

.grain{
  position:fixed; inset:0;
  pointer-events:none;
  z-index:-4;
  opacity:.10;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");
}

.fogGlow{
  position:fixed; inset:-20%;
  pointer-events:none;
  z-index:-3;
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

#bubbles{
  position:fixed; inset:0;
  z-index:-2;
  pointer-events:none;
  opacity:0.55;
}

/* ===== Sidebar ===== */
.sidebar{
  background: linear-gradient(180deg, rgba(2, 10, 22, 0.95), rgba(3, 18, 34, 0.88));
  border-right: 1px solid rgba(56,247,255,0.24);
  box-shadow: 0 0 30px rgba(0,209,184,0.10);
  position:fixed;
  inset:0 auto 0 0;
  width:16rem;
  overflow:hidden;
  z-index:20;
}
.sidebar a{
  display:block;
  padding:12px 14px;
  color: rgba(226,232,240,0.92);
  border-bottom:1px solid rgba(255,255,255,0.06);
  transition:0.25s ease;
}
.sidebar a:hover{
  background: rgba(56,247,255,0.08);
  color: var(--aqua);
}

.main{ margin-left:16rem; min-height:100vh; }
.wrap{ max-width:1200px; margin:0 auto; padding:26px; }

.panel{
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  background: var(--glass);
  border: 1px solid var(--stroke);
  box-shadow: 0 0 55px var(--shadow), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 22px;
}
.h1{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.14em;
  color: rgba(56,247,255,0.92);
  text-shadow: 0 0 18px rgba(56,247,255,0.22);
}
.small{font-size:12px;color: rgba(230,250,255,0.72);}

/* controls */
.controls{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; justify-content:space-between; }
.search{
  flex:1; min-width:220px;
  border-radius:14px;
  border:1px solid rgba(56,247,255,0.22);
  background: rgba(255,255,255,0.04);
  padding:10px 12px;
  outline:none;
  color: rgba(230,250,255,0.95);
}
.search:focus{ border-color: rgba(56,247,255,0.42); box-shadow: 0 0 0 3px rgba(56,247,255,0.10); }
.btn{
  border-radius:14px;
  border:1px solid rgba(56,247,255,0.24);
  background: linear-gradient(90deg, rgba(56,247,255,0.16), rgba(0,209,184,0.12));
  padding:10px 12px;
  color: rgba(230,250,255,0.95);
  font-weight:900;
  letter-spacing:.08em;
  transition:.25s ease;
}
.btn:hover{ transform: translateY(-1px); border-color: rgba(56,247,255,0.44); box-shadow: 0 0 24px rgba(56,247,255,0.14); }

/* podium */
.podium{display:grid;grid-template-columns: 1fr 1fr 1fr;gap:14px;margin-top:16px;}
.pod{
  position:relative; overflow:hidden;
  border-radius:20px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(255,255,255,0.03);
  padding:16px;
  min-height:150px;
  box-shadow: inset 0 0 18px rgba(255,255,255,0.03);
  transition:.25s ease;
}
.pod:hover{ transform: translateY(-3px); border-color:rgba(56,247,255,0.32); box-shadow:0 0 26px rgba(56,247,255,0.14), inset 0 0 18px rgba(255,255,255,0.04); }
.pod::after{
  content:"";
  position:absolute; inset:-80px -80px auto auto;
  width:170px;height:170px;
  background: radial-gradient(circle, rgba(245,210,123,0.18), transparent 60%);
  transform:rotate(18deg);
}
.pod .rank{display:inline-flex;align-items:center;gap:10px;font-weight:900;letter-spacing:.12em;color: rgba(230,250,255,0.78);}
.pod .name{margin-top:10px;font-family:'Cinzel',serif;font-weight:900;font-size:20px;color: rgba(230,250,255,0.95);}
.pod .score{margin-top:6px;font-weight:900;color: rgba(56,247,255,0.95);letter-spacing:.10em;}

/* cards */
.gridCard{
  --g:0;
  --rx:0deg;
  --ry:0deg;
  border-radius:20px;
  border:1px solid rgba(56,247,255,0.18);
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.06));
  box-shadow:
    0 0 calc(var(--g) * 26px) rgba(56,247,255,0.22),
    inset 0 0 18px rgba(255,255,255,0.03);
  padding:16px;
  transition: transform .10s linear, box-shadow .10s linear, border-color .15s ease;
  overflow:hidden;
  position:relative;
  transform: perspective(900px) rotateX(var(--rx)) rotateY(var(--ry));
}
.gridCard .top{display:flex;align-items:center;justify-content:space-between;gap:10px;}
.pill{
  display:inline-flex;align-items:center;
  padding:4px 10px;border-radius:999px;
  border:1px solid rgba(245,210,123,0.28);
  background: rgba(245,210,123,0.08);
  color: rgba(245,210,123,0.95);
  font-size:12px;
  font-weight:900;
  letter-spacing:.10em;
}
.uName{margin-top:10px;font-family:'Cinzel',serif;font-weight:900;letter-spacing:.05em;}
.uScore{margin-top:8px;font-weight:900;color: rgba(56,247,255,0.95);letter-spacing:.10em;}
.mini{font-size:12px;color: rgba(230,250,255,0.68); margin-top:6px;}

.me{
  border-color: rgba(245,210,123,0.46) !important;
  box-shadow: 0 0 44px rgba(245,210,123,0.16), inset 0 0 18px rgba(255,255,255,0.04) !important;
}
.me .pill{
  border-color: rgba(56,247,255,0.30);
  background: rgba(56,247,255,0.08);
  color: rgba(56,247,255,0.95);
}

@media (max-width: 760px){
  .sidebar{position:relative; width:100%; height:auto;}
  .main{margin-left:0;}
  .wrap{padding:16px;}
  .podium{grid-template-columns:1fr;}
}
</style>
</head>

<body>
<div class="caustics"></div>
<div class="fogGlow"></div>
<div class="scanlines"></div>
<div class="grain"></div>
<canvas id="bubbles"></canvas>

<!-- Sidebar -->
<div class="sidebar">
  <h2 class="text-xl font-bold p-4 border-b border-[rgba(56,247,255,0.20)]">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="main">
  <div class="wrap space-y-6">

    <!-- Header -->
    <div class="panel p-6">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <div class="h1 text-2xl md:text-3xl">🏆 ATLANTIS LEADERBOARD</div>
          <div class="small mt-2">PODIUM = TOP 3 • GRID STARTS FROM #4 • TOTAL PLAYERS: <?= (int)$totalPlayers ?></div>
        </div>
        <div class="small text-right">
          YOU: <b style="color:rgba(245,210,123,0.95);"><?= h($username) ?></b><br>
          PLACE: <b style="color:rgba(56,247,255,0.95);"><?= $myPlace !== null ? (int)$myPlace : '—' ?></b>
          • SCORE: <b style="color:rgba(56,247,255,0.95);"><?= (int)$myScore ?></b>
        </div>
      </div>

      <div class="controls mt-5">
        <input id="search" class="search" placeholder="Search explorer name..." autocomplete="off">
        <button class="btn" id="jumpMe">JUMP TO ME</button>
        <button class="btn" id="reset">RESET</button>
      </div>
    </div>

    <!-- Podium (ONLY 1/2/3 shown here) -->
    <div class="panel p-6">
      <div class="h1 text-xl">THE TRIDENT PODIUM</div>
      <div class="small mt-2">Top 3 explorers in the depths</div>

      <div class="podium">
        <?php
          $pod = [
            ['#1','👑', $top1],
            ['#2','🥈', $top2],
            ['#3','🥉', $top3],
          ];
          foreach($pod as $p):
            $r = $p[2] ?: ['username'=>'—','score'=>0];
        ?>
          <div class="pod">
            <div class="rank"><?= h($p[1]) ?> <span><?= h($p[0]) ?></span></div>
            <div class="name"><?= h($r['username']) ?></div>
            <div class="score"><?= (int)$r['score'] ?> PTS</div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Grid (STARTS FROM PLACE 4) -->
    <div class="panel p-6">
      <div class="h1 text-xl">RANKS #4 → #<?= (int)$limit ?></div>
      <div class="small mt-2">Hover to glow + tilt • Search filters instantly • Your card is highlighted (if not in podium)</div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mt-6" id="gridCards">
        <?php foreach ($gridRows as $r): ?>
          <?php $isMe = ((int)$r['id'] === $userId); ?>
          <div class="gridCard <?= $isMe ? 'me' : '' ?>"
               data-name="<?= h(strtolower($r['username'])) ?>"
               <?= $isMe ? 'id="meCard"' : '' ?>>
            <div class="top">
              <span class="pill">PLACE #<?= (int)$r['place'] ?></span>
              <span class="small">🔱</span>
            </div>
            <div class="uName"><?= h($r['username']) ?> <?= $isMe ? ' (YOU)' : '' ?></div>
            <div class="uScore"><?= (int)$r['score'] ?> PTS</div>
            <div class="mini">ATLANTIS SECTOR: <?= (int)$r['place'] <= 10 ? 'ROYAL DEPTHS' : ((int)$r['place'] <= 30 ? 'INNER RUINS' : 'OUTER TRENCH') ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (empty($gridRows)): ?>
        <div class="small mt-6">Not enough players to show ranks beyond #3.</div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
/* ===== BUBBLES CANVAS ===== */
const canvas = document.getElementById('bubbles');
const ctx = canvas.getContext('2d', { alpha: true });
let W=0,H=0, bubbles=[];

function resize(){
  W = canvas.width = window.innerWidth;
  H = canvas.height = window.innerHeight;
}
window.addEventListener('resize', resize);
resize();

function rand(min,max){ return Math.random()*(max-min)+min; }

function spawn(n=36){
  bubbles = [];
  for(let i=0;i<n;i++){
    bubbles.push({
      x: rand(0,W),
      y: rand(0,H),
      r: rand(1.2, 5.0),
      s: rand(0.18, 0.60),
      a: rand(0.05, 0.18),
      drift: rand(-0.28,0.28),
      hue: Math.random()<0.12 ? 'rgba(245,210,123,' : (Math.random()<0.5 ? 'rgba(56,247,255,' : 'rgba(0,209,184,')
    });
  }
}
spawn();

function tick(){
  ctx.clearRect(0,0,W,H);
  for(const b of bubbles){
    b.y -= b.s*2.2;
    b.x += b.drift;
    if(b.y < -10){ b.y = H + 10; b.x = rand(0,W); }
    if(b.x < -10) b.x = W+10;
    if(b.x > W+10) b.x = -10;

    ctx.beginPath();
    ctx.arc(b.x,b.y,b.r,0,Math.PI*2);
    ctx.fillStyle = b.hue + b.a + ')';
    ctx.fill();
  }
  requestAnimationFrame(tick);
}
tick();

/* ===== Proximity glow + tilt ===== */
const cards = document.querySelectorAll('.gridCard');
document.addEventListener('mousemove', (e)=>{
  cards.forEach(c=>{
    const r = c.getBoundingClientRect();
    const cx = r.left + r.width/2;
    const cy = r.top + r.height/2;
    const dx = e.clientX - cx;
    const dy = e.clientY - cy;
    const dist = Math.sqrt(dx*dx + dy*dy);
    const g = Math.max(0, 1 - dist/280);
    c.style.setProperty('--g', g.toFixed(2));

    const clamp = (v, m)=> Math.max(-m, Math.min(m, v));
    const ry = clamp((dx / (r.width/2)) * 6, 8);
    const rx = clamp((-dy / (r.height/2)) * 6, 8);
    if(dist < 360){
      c.style.setProperty('--rx', rx.toFixed(2)+'deg');
      c.style.setProperty('--ry', ry.toFixed(2)+'deg');
    } else {
      c.style.setProperty('--rx','0deg');
      c.style.setProperty('--ry','0deg');
    }
  });
});

/* ===== Search filter ===== */
const search = document.getElementById('search');
search.addEventListener('input', ()=>{
  const q = (search.value || '').trim().toLowerCase();
  cards.forEach(c=>{
    const name = c.dataset.name || '';
    c.style.display = name.includes(q) ? '' : 'none';
  });
});

/* ===== Jump to me ===== */
document.getElementById('jumpMe').addEventListener('click', ()=>{
  const me = document.getElementById('meCard');
  if(me){
    me.scrollIntoView({behavior:'smooth', block:'center'});
    me.style.transition = '0.2s ease';
    me.style.borderColor = 'rgba(245,210,123,0.70)';
    me.style.boxShadow = '0 0 60px rgba(245,210,123,0.20), inset 0 0 18px rgba(255,255,255,0.05)';
    setTimeout(()=>{
      me.style.borderColor = '';
      me.style.boxShadow = '';
    }, 900);
  } else {
    // if user is in podium, scroll to top instead
    window.scrollTo({top:0, behavior:'smooth'});
  }
});

/* ===== Reset ===== */
document.getElementById('reset').addEventListener('click', ()=>{
  search.value = '';
  cards.forEach(c=> c.style.display = '');
});
</script>
</body>
</html>
