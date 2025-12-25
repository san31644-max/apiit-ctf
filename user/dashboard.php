<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, $_SESSION['user_id'], "Visited Dashboard", $_SERVER['REQUEST_URI']);

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

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

// ---------- Cyber “News” (placeholder rotation) ----------
// Live cyber news needs an RSS/API call (recommended to fetch server-side). For now we show curated rotating headlines.
$cyberNews = [
  ["title"=>"New phishing kits mimic Microsoft 365 login with perfect pixel clones", "tag"=>"PHISHING", "time"=>"Today"],
  ["title"=>"Ransomware groups shifting to data theft-only extortion to avoid downtime detection", "tag"=>"RANSOMWARE", "time"=>"This week"],
  ["title"=>"Critical authentication bypass disclosed in popular open-source web panel (patch ASAP)", "tag"=>"CVE", "time"=>"This week"],
  ["title"=>"Cloud misconfigurations remain top cause of data exposure — review IAM & public buckets", "tag"=>"CLOUD", "time"=>"This month"],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard — Atlantis CTF</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --aqua2:#22d3ee;
  --teal:#00d1b8;
  --gold:#f5d27b;
  --text:#e6faff;

  --glass: rgba(0, 14, 24, 0.30);
  --stroke: rgba(56,247,255,0.18);
  --shadow: rgba(56,247,255,0.12);
}

html,body{height:100%;}
body{
  margin:0; overflow-x:hidden;
  font-family:'Share Tech Mono', monospace;
  color: var(--text);
  background:#000;
}

/* ===== VIDEO BG ===== */
.video-bg{position:fixed; inset:0; z-index:-10; overflow:hidden; background:#00101f;}
.video-bg video{width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.03);filter:saturate(1.05) contrast(1.05);}
.video-overlay{
  position:fixed; inset:0; z-index:-9; pointer-events:none;
  background:
    radial-gradient(1000px 520px at 55% 12%, rgba(56,247,255,0.14), transparent 62%),
    radial-gradient(900px 700px at 20% 90%, rgba(0,209,184,0.10), transparent 65%),
    linear-gradient(180deg, rgba(0,0,0,0.10), rgba(0,0,0,0.55));
}
.caustics{
  position:fixed; inset:0; z-index:-8; pointer-events:none;
  background:
    repeating-radial-gradient(circle at 30% 40%, rgba(56,247,255,.05) 0 2px, transparent 3px 14px),
    repeating-radial-gradient(circle at 70% 60%, rgba(255,255,255,.03) 0 1px, transparent 2px 18px);
  opacity:.26; mix-blend-mode:screen;
  animation: causticMove 7s linear infinite;
}
@keyframes causticMove{from{background-position:0 0,0 0;}to{background-position:0 220px,0 -180px;}}
.scanlines{
  position:fixed; inset:0; z-index:-7; pointer-events:none;
  background: repeating-linear-gradient(to bottom, rgba(255,255,255,0.02), rgba(255,255,255,0.02) 1px, transparent 1px, transparent 4px);
  opacity:.45;
}
.grain{
  position:fixed; inset:0; z-index:-6; pointer-events:none; opacity:.10;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");
}

/* ===== CANVAS LAYERS ===== */
#net-bg, #bubbles-bg{
  position:fixed; inset:0; z-index:-5;
  pointer-events:none;
}

/* ===== LAYOUT ===== */
.shell{min-height:100vh; display:flex;}
.sidebar{
  width:16.5rem;
  position:fixed; inset:0 auto 0 0;
  z-index:30;
  background: rgba(0,16,28,0.40);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-right:1px solid rgba(56,247,255,0.18);
  box-shadow: 0 0 60px rgba(56,247,255,0.10);
}
.brand{padding:18px 16px;border-bottom:1px solid rgba(56,247,255,0.16);}
.brand .t{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.16em;
  color:rgba(56,247,255,0.95);
  text-shadow:0 0 18px rgba(56,247,255,0.30);
}
.brand .s{margin-top:6px;font-size:12px;color:rgba(245,210,123,0.92);letter-spacing:.12em;}
.nav a{
  display:flex;gap:10px;align-items:center;
  padding:12px 14px;
  color:rgba(230,250,255,0.88);
  border-bottom:1px solid rgba(255,255,255,0.05);
  transition:0.22s;
  letter-spacing:.06em;
}
.nav a:hover{background:rgba(56,247,255,0.10);color:rgba(56,247,255,0.98);}
.nav a.active{background:rgba(56,247,255,0.14);border-left:3px solid rgba(245,210,123,0.92);color:rgba(56,247,255,0.99);}
.nav a.danger{color:rgba(251,113,133,0.95);}
.nav a.danger:hover{background:rgba(251,113,133,0.10);}

.main{
  margin-left:16.5rem;
  width:calc(100% - 16.5rem);
  padding:22px;
  overflow:auto;
}

/* ===== “SUPER” PANELS ===== */
.panel{
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
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
.small{font-size:12px;color:rgba(230,250,255,0.72);}

/* ===== CARDS dynamic ===== */
.card{
  --g:0;
  --rx:0deg;
  --ry:0deg;
  border-radius:22px;
  border:1px solid rgba(56,247,255,0.18);
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.06));
  box-shadow:
    0 0 calc(var(--g) * 26px) rgba(56,247,255,0.20),
    inset 0 0 18px rgba(255,255,255,0.03);
  padding:18px;
  transition: transform .10s linear, box-shadow .10s linear, border-color .15s ease;
  transform: perspective(900px) rotateX(var(--rx)) rotateY(var(--ry));
}
.card:hover{border-color: rgba(56,247,255,0.34);}

.section-title{
  color:rgba(56,247,255,0.92);
  font-weight:900;
  letter-spacing:.10em;
  font-family:'Cinzel',serif;
}
.meta{font-size:0.8rem;color:#b6f7ea;opacity:0.9;}
.counter{font-family:'Share Tech Mono', monospace;letter-spacing:.10em;text-shadow: 0 0 18px rgba(56,247,255,0.12);}

/* pills */
.pill{
  display:inline-flex;gap:10px;align-items:center;
  padding:8px 12px;border-radius:999px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(255,255,255,0.04);
  font-family:'Share Tech Mono', monospace;
  font-weight:900; letter-spacing:.10em;
}
.pill b{color:rgba(245,210,123,0.95);}

/* sonar pulse behind header */
.sonar{
  position:absolute; inset:-40px -40px auto auto;
  width:220px; height:220px; border-radius:50%;
  background: radial-gradient(circle, rgba(56,247,255,0.18), transparent 60%);
  filter: blur(0.2px);
  animation: sonar 3.6s ease-in-out infinite;
  pointer-events:none;
}
@keyframes sonar{
  0%{transform:scale(0.85); opacity:0.40;}
  50%{transform:scale(1.05); opacity:0.72;}
  100%{transform:scale(0.85); opacity:0.40;}
}

/* news ticker */
.newsItem{
  border-radius:18px;
  border:1px solid rgba(56,247,255,0.16);
  background: rgba(0,0,0,0.18);
  padding:14px 14px;
}
.tag{
  display:inline-flex;align-items:center;
  padding:3px 10px;border-radius:999px;
  border:1px solid rgba(245,210,123,0.28);
  background: rgba(245,210,123,0.08);
  color: rgba(245,210,123,0.95);
  font-weight:900;
  letter-spacing:.10em;
  font-size:12px;
}

/* responsive */
@media (max-width: 860px){
  .sidebar{position:static;width:100%;height:auto;}
  .main{margin-left:0;width:100%;}
}
</style>
</head>

<body class="h-screen flex">

<!-- Atlantis Video Background -->
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="caustics"></div>
<div class="scanlines"></div>
<div class="grain"></div>

<!-- Dual Cyber Canvases -->
<canvas id="net-bg"></canvas>
<canvas id="bubbles-bg"></canvas>

<div class="shell">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="brand">
      <div class="t">ATLANTIS CTF</div>
      <div class="s">EXPLORER CONSOLE</div>
    </div>
    <nav class="nav">
      <a class="active" href="dashboard.php">🏠 Dashboard</a>
      <a href="challenges.php">🛠 Challenges</a>
      <a href="leaderboard.php">🏆 Leaderboard</a>
      <a href="instructions.php">📖 Instructions</a>
      <a href="hints.php">💡 Hints</a>
      <a href="profile.php">👤 Profile</a>
      <a class="danger" href="../logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main Content -->
  <main class="main space-y-6">

    <!-- Header -->
    <section class="panel p-6 relative overflow-hidden">
      <div class="sonar"></div>
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <div class="h1 text-2xl md:text-3xl">WELCOME, <?= h($username) ?> 🔱</div>
          <div class="small mt-2">Last updated: <?= h($lastUpdated) ?></div>
        </div>
        <div class="small text-right">
          <span class="pill">DATA: <b><?= h(implode(', ', $statsSource)) ?></b></span>
        </div>
      </div>
    </section>

    <!-- Quick Stats + News -->
    <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
      <!-- Stats -->
      <div class="panel p-6 xl:col-span-2">
        <div class="section-title text-xl mb-4">📊 QUICK STATS</div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div class="card text-center">
            <div class="text-3xl font-extrabold text-[rgba(56,247,255,0.92)] counter" data-target="<?= h((string)$solvedCount) ?>">0</div>
            <div class="small mt-2">Challenges Solved</div>
          </div>
          <div class="card text-center">
            <div class="text-3xl font-extrabold text-[rgba(245,210,123,0.95)] counter" data-target="<?= h((string)$rank) ?>">—</div>
            <div class="small mt-2">Current Rank</div>
          </div>
          <div class="card text-center">
            <div class="text-3xl font-extrabold text-[rgba(56,247,255,0.92)] counter" data-target="<?= h((string)$score) ?>">0</div>
            <div class="small mt-2">Score</div>
          </div>
        </div>
      </div>

      <!-- Cyber News -->
      <div class="panel p-6">
        <div class="section-title text-xl mb-4">🛰️ CYBER INTEL FEED</div>
        <div class="small mb-3">Auto-rotates. (To fetch real-time news, connect an RSS/API server-side.)</div>

        <div id="newsBox" class="newsItem">
          <div class="flex items-center justify-between gap-3">
            <span class="tag" id="newsTag">NEWS</span>
            <span class="small" id="newsTime">—</span>
          </div>
          <div class="mt-3 text-[rgba(230,250,255,0.92)] font-bold" id="newsTitle">Loading intel…</div>
          <div class="mt-3 small">Tip: Patch fast. Review logs. Rotate secrets.</div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div class="card">
            <div class="section-title text-sm">✅ TODAY’S GOAL</div>
            <div class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Solve 2 challenges & write notes.</div>
          </div>
          <div class="card">
            <div class="section-title text-sm">🧠 TOOL KIT</div>
            <div class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Burp • Wireshark • Nmap • Python</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Prize Pool Card -->
    <section class="panel p-6">
      <div class="section-title text-xl mb-4">🏆 PRIZE POOL</div>
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
        <div class="card" style="background:linear-gradient(145deg, rgba(255,215,0,0.24), rgba(255,236,139,0.10)); border-color:rgba(245,210,123,0.35);">
          1️⃣ FIRST PLACE<br><span class="text-3xl font-extrabold text-[rgba(245,210,123,0.95)]">20,000 LKR</span>
        </div>
        <div class="card" style="background:linear-gradient(145deg, rgba(192,192,192,0.18), rgba(224,224,224,0.08)); border-color:rgba(230,250,255,0.22);">
          2️⃣ SECOND PLACE<br><span class="text-3xl font-extrabold text-[rgba(230,250,255,0.95)]">15,000 LKR</span>
        </div>
        <div class="card" style="background:linear-gradient(145deg, rgba(205,127,50,0.20), rgba(217,160,102,0.08)); border-color:rgba(245,210,123,0.25);">
          3️⃣ THIRD PLACE<br><span class="text-3xl font-extrabold text-[rgba(245,210,123,0.90)]">10,000 LKR</span>
        </div>
      </div>
      <p class="mt-3 text-sm text-[rgba(230,250,255,0.75)]">Prizes awarded to top 3 competitors on the leaderboard.</p>
    </section>

    <!-- ================== CTF WALKTHROUGH & RULES ================== -->
    <section class="panel p-6">
      <div class="section-title text-2xl mb-2">🚀 CTF WALKTHROUGH & GAME RULES</div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-5">
        <div class="card">
          <div class="section-title text-lg mb-3">🧭 HOW THE CTF WORKS</div>
          <ol class="list-decimal list-inside space-y-2 text-sm text-[rgba(230,250,255,0.78)]">
            <li>Login to your dashboard and explore available challenges.</li>
            <li>Solve challenges and submit flags in the correct format.</li>
            <li>Each correct flag increases your score instantly.</li>
            <li>Hints are optional but will deduct points.</li>
            <li>Document every step for your final report.</li>
          </ol>
        </div>

        <div class="card">
          <div class="section-title text-lg mb-3">⏱️ COMPETITION TIMELINE</div>
          <ul class="list-disc list-inside space-y-2 text-sm text-[rgba(230,250,255,0.78)]">
            <li>🟢 CTF starts officially when the timer begins.</li>
            <li>⏳ <strong>After 4 hours:</strong></li>
            <ul class="ml-6 list-disc text-yellow-200">
              <li>❌ Leaderboard will be hidden</li>
              <li>❌ Hints section will be disabled</li>
              <li>📄 Focus shifts to report preparation</li>
            </ul>
            <li>🏁 Final scores are locked after game end.</li>
          </ul>
        </div>
      </div>

      <div class="card mt-6">
        <div class="section-title text-lg mb-3">🎯 SCORING BREAKDOWN</div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-center mt-3">
          <div class="card" style="background:linear-gradient(145deg, rgba(14,165,233,0.22), rgba(56,189,248,0.08));">
            🧠 CTF CHALLENGES<br>
            <span class="text-3xl font-extrabold text-[rgba(56,247,255,0.92)]">1500 POINTS</span>
          </div>
          <div class="card" style="background:linear-gradient(145deg, rgba(139,92,246,0.22), rgba(167,139,250,0.08));">
            📑 TECHNICAL REPORT<br>
            <span class="text-3xl font-extrabold text-[rgba(230,250,255,0.92)]">500 POINTS</span>
          </div>
        </div>
        <p class="mt-4 text-sm text-[rgba(230,250,255,0.75)] text-center">
          Maximum achievable score: <strong class="text-[rgba(56,247,255,0.92)]">2000 points</strong>
        </p>
      </div>

      <div class="card mt-6" style="border-color:rgba(245,158,11,0.35); background: rgba(245,158,11,0.08);">
        <div class="section-title text-lg mb-3" style="color:rgba(255,240,205,0.95);">💡 HINT SYSTEM NOTICE</div>
        <ul class="list-disc list-inside space-y-2 text-sm text-[rgba(255,240,205,0.92)]">
          <li>Hints are available per challenge.</li>
          <li>Each hint deducts a specific number of points.</li>
          <li>Points are deducted only once per hint.</li>
          <li>⚠️ Hints will be <strong>disabled after 4 hours</strong>.</li>
        </ul>
      </div>

      <div class="card mt-6" style="border-color:rgba(244,63,94,0.35); background: rgba(244,63,94,0.08);">
        <div class="section-title text-lg mb-3" style="color:rgba(255,210,220,0.95);">⛔ PROHIBITED ACTIONS (ZERO TOLERANCE)</div>
        <ul class="list-disc list-inside space-y-2 text-sm text-[rgba(255,210,220,0.92)]">
          <li>Sharing flags, hints, or solutions with others.</li>
          <li>Attacking the CTF infrastructure or platform.</li>
          <li>Brute-forcing flags or automated flag submission.</li>
          <li>Collaboration between teams or players.</li>
          <li>Plagiarizing write-ups or reports.</li>
          <li>Using leaked or pre-solved challenge material.</li>
        </ul>
        <p class="mt-3 text-sm font-semibold text-[rgba(255,210,220,0.95)]">
          🚨 Violation may result in immediate disqualification.
        </p>
      </div>

      <div class="card mt-6">
        <p class="text-[rgba(56,247,255,0.92)] text-sm text-center">
          🧠 Focus on skill, ethics, and documentation. The final hour is for analysis & reporting — not racing the leaderboard.
        </p>
      </div>
    </section>
    <!-- ================== END WALKTHROUGH ================== -->

    <!-- Challenge Categories -->
    <section class="panel p-6">
      <div class="section-title text-xl mb-4">🕹️ CHALLENGE CATEGORIES</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="card"><div class="section-title">🌐 WEB</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">XSS, SQLi, SSRF…</p></div>
        <div class="card"><div class="section-title">🔐 CRYPTO</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Ciphers & crypto puzzles.</p></div>
        <div class="card"><div class="section-title">🕵️ FORENSICS</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">PCAP, stego, images.</p></div>
        <div class="card"><div class="section-title">⚙️ REVERSING</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Binaries & obstacles.</p></div>
        <div class="card"><div class="section-title">💣 PWN</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Exploits & memory bugs.</p></div>
        <div class="card"><div class="section-title">🧩 MISC</div><p class="mt-2 text-sm text-[rgba(230,250,255,0.78)]">Fun or mixed challenges.</p></div>
      </div>
    </section>

    <!-- Educational Resources -->
    <section class="panel p-6">
      <div class="section-title text-xl mb-4">📚 EDUCATIONAL RESOURCES & TIPS</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-[rgba(230,250,255,0.80)] text-sm">
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
    </section>

  </main>
</div>

<script>
/* ========= CYBER NEWS ROTATION ========= */
const newsData = <?php echo json_encode($cyberNews, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
let ni = 0;
function renderNews(){
  const item = newsData[ni % newsData.length];
  document.getElementById('newsTag').textContent = item.tag;
  document.getElementById('newsTime').textContent = item.time;
  document.getElementById('newsTitle').textContent = item.title;
  ni++;
}
renderNews();
setInterval(renderNews, 4500);

/* ========= COUNTER (supports numbers, "—", "≈123") ========= */
function parseTarget(t){
  t = (t ?? '').toString().trim();
  if (!t || t === '—') return { kind:'dash', value:'—' };
  if (t.startsWith('≈')) {
    const n = parseInt(t.slice(1), 10);
    if (Number.isFinite(n)) return { kind:'approx', value:n };
    return { kind:'text', value:t };
  }
  const n = parseInt(t, 10);
  if (Number.isFinite(n)) return { kind:'num', value:n };
  return { kind:'text', value:t };
}

document.querySelectorAll('.counter').forEach(el=>{
  const raw = el.dataset.target;
  const info = parseTarget(raw);

  if (info.kind === 'dash' || info.kind === 'text'){
    el.textContent = info.value;
    return;
  }

  const target = info.value;
  let current = 0;
  const steps = 60;
  const step = Math.max(1, Math.floor(target / steps));

  function tick(){
    current += step;
    if (current >= target) current = target;
    el.textContent = (info.kind === 'approx') ? ('≈' + current) : String(current);
    if (current < target) requestAnimationFrame(tick);
  }
  requestAnimationFrame(tick);
});

/* ========= PROXIMITY GLOW + TILT ========= */
const cards = document.querySelectorAll('.card');
document.addEventListener('mousemove', (e)=>{
  cards.forEach(c=>{
    const r = c.getBoundingClientRect();
    const cx = r.left + r.width/2;
    const cy = r.top + r.height/2;
    const dx = e.clientX - cx;
    const dy = e.clientY - cy;
    const dist = Math.sqrt(dx*dx + dy*dy);
    const g = Math.max(0, 1 - dist/360);
    c.style.setProperty('--g', g.toFixed(2));

    const clamp = (v, m)=> Math.max(-m, Math.min(m, v));
    const ry = clamp((dx / (r.width/2)) * 6, 9);
    const rx = clamp((-dy / (r.height/2)) * 6, 9);

    if (dist < 420){
      c.style.setProperty('--rx', rx.toFixed(2)+'deg');
      c.style.setProperty('--ry', ry.toFixed(2)+'deg');
    } else {
      c.style.setProperty('--rx','0deg');
      c.style.setProperty('--ry','0deg');
    }
  });
});

/* ========= CANVAS: NEON NETWORK ========= */
const net = document.getElementById('net-bg');
const nctx = net.getContext('2d', { alpha:true });
let NW=0,NH=0;
let nodes = [];

function resizeNet(){
  NW = net.width = window.innerWidth;
  NH = net.height = window.innerHeight;
  const count = Math.max(70, Math.floor((NW*NH)/26000));
  nodes = [];
  for(let i=0;i<count;i++){
    nodes.push({
      x: Math.random()*NW,
      y: Math.random()*NH,
      vx: (Math.random()-0.5)*0.35,
      vy: (Math.random()-0.5)*0.35
    });
  }
}
resizeNet();
window.addEventListener('resize', resizeNet);

function drawNetwork(){
  nctx.clearRect(0,0,NW,NH);
  for(let i=0;i<nodes.length;i++){
    const p = nodes[i];
    p.x += p.vx; p.y += p.vy;
    if(p.x<0||p.x>NW) p.vx*=-1;
    if(p.y<0||p.y>NH) p.vy*=-1;

    nctx.fillStyle = "rgba(56,247,255,0.55)";
    nctx.fillRect(p.x, p.y, 2, 2);

    for(let j=i+1;j<nodes.length;j++){
      const q = nodes[j];
      const dx = p.x-q.x, dy=p.y-q.y;
      const d = Math.sqrt(dx*dx+dy*dy);
      if(d < 140){
        const a = (1 - d/140) * 0.18;
        nctx.strokeStyle = `rgba(56,247,255,${a})`;
        nctx.beginPath();
        nctx.moveTo(p.x,p.y);
        nctx.lineTo(q.x,q.y);
        nctx.stroke();
      }
    }
  }
  requestAnimationFrame(drawNetwork);
}
drawNetwork();

/* ========= CANVAS: BUBBLES ========= */
const bub = document.getElementById('bubbles-bg');
const bctx = bub.getContext('2d', { alpha:true });
let BW=0,BH=0, bubbles=[];

function resizeB(){
  BW = bub.width = window.innerWidth;
  BH = bub.height = window.innerHeight;
  const count = Math.max(40, Math.floor((BW*BH)/42000));
  bubbles = [];
  for(let i=0;i<count;i++){
    bubbles.push({
      x: Math.random()*BW,
      y: Math.random()*BH,
      r: 1.2 + Math.random()*4.5,
      s: 0.15 + Math.random()*0.55,
      drift: (Math.random()-0.5)*0.35,
      a: 0.04 + Math.random()*0.14,
      tint: Math.random()<0.15 ? '245,210,123' : (Math.random()<0.5 ? '56,247,255' : '0,209,184')
    });
  }
}
resizeB();
window.addEventListener('resize', resizeB);

function drawBubbles(){
  bctx.clearRect(0,0,BW,BH);
  for(const b of bubbles){
    b.y -= b.s*2.1;
    b.x += b.drift;
    if(b.y < -10){ b.y = BH + 10; b.x = Math.random()*BW; }
    if(b.x < -10) b.x = BW+10;
    if(b.x > BW+10) b.x = -10;

    bctx.beginPath();
    bctx.arc(b.x,b.y,b.r,0,Math.PI*2);
    bctx.fillStyle = `rgba(${b.tint},${b.a})`;
    bctx.fill();
  }
  requestAnimationFrame(drawBubbles);
}
drawBubbles();
</script>

</body>
</html>
