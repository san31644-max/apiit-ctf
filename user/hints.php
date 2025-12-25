<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";
require_once __DIR__ . "/../includes/page_guard_json.php";
guard_page_json('hints');


/* Security */
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: ../index.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$user_id = (int)$_SESSION['user_id'];
log_activity($pdo, $user_id, "Visited Hints Page", $_SERVER['REQUEST_URI']);

/* ======================
   Ensure hint_views table exists (your DB already has it)
   Expected columns (typical):
   - id (PK)
   - user_id
   - hint_id
   - viewed_at
====================== */

/* Get user score */
$stmt = $pdo->prepare("SELECT COALESCE(score,0) FROM users WHERE id=? LIMIT 1");
$stmt->execute([$user_id]);
$user_score = (int)$stmt->fetchColumn();

/* Fetch hints with challenge title */
$stmt = $pdo->query("
  SELECT h.id, h.title, h.content, h.point_cost, h.created_at, c.title AS challenge_title
  FROM hints h
  LEFT JOIN challenges c ON h.challenge_id = c.id
  ORDER BY h.created_at DESC
");
$hints = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Fetch which hints this user already opened */
$opened = [];
try{
  $st = $pdo->prepare("SELECT hint_id FROM hint_views WHERE user_id=?");
  $st->execute([$user_id]);
  foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $hid) $opened[(int)$hid] = true;
}catch(Exception $e){
  // if hint_views schema differs, you'll see errors in server log
}

/* ======================
   AJAX endpoint (same file)
   POST action=open_hint, hint_id=#
   - if already opened: return content without charging again
   - else: if score >= cost -> deduct cost, insert hint_views, return content
====================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'open_hint') {
  header('Content-Type: application/json; charset=utf-8');

  $hintId = (int)($_POST['hint_id'] ?? 0);
  if ($hintId <= 0) {
    echo json_encode(['ok'=>false,'msg'=>'Invalid hint.']);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // Lock user row (prevents double-spend)
    $u = $pdo->prepare("SELECT COALESCE(score,0) AS score FROM users WHERE id=? FOR UPDATE");
    $u->execute([$user_id]);
    $scoreNow = (int)$u->fetchColumn();

    // Load hint cost/content
    $hs = $pdo->prepare("SELECT id, title, content, COALESCE(point_cost,0) AS point_cost FROM hints WHERE id=? LIMIT 1");
    $hs->execute([$hintId]);
    $hint = $hs->fetch(PDO::FETCH_ASSOC);
    if (!$hint) {
      $pdo->rollBack();
      echo json_encode(['ok'=>false,'msg'=>'Hint not found.']);
      exit;
    }

    // Already opened?
    $v = $pdo->prepare("SELECT 1 FROM hint_views WHERE user_id=? AND hint_id=? LIMIT 1");
    $v->execute([$user_id, $hintId]);
    $already = (bool)$v->fetchColumn();

    if ($already) {
      $pdo->commit();
      echo json_encode([
        'ok'=>true,
        'already'=>true,
        'score'=>$scoreNow,
        'title'=>$hint['title'],
        'content'=>$hint['content'],
        'cost'=>(int)$hint['point_cost'],
      ]);
      exit;
    }

    $cost = (int)$hint['point_cost'];
    if ($scoreNow < $cost) {
      $pdo->rollBack();
      echo json_encode(['ok'=>false,'msg'=>"Not enough points. Need {$cost} pts, you have {$scoreNow} pts."]);
      exit;
    }

    // Deduct points
    $upd = $pdo->prepare("UPDATE users SET score = GREATEST(COALESCE(score,0) - ?, 0) WHERE id=?");
    $upd->execute([$cost, $user_id]);

    // Insert view record
    $ins = $pdo->prepare("INSERT INTO hint_views (user_id, hint_id, viewed_at) VALUES (?,?,NOW())");
    $ins->execute([$user_id, $hintId]);

    $pdo->commit();

    // Read new score
    $ns = $pdo->prepare("SELECT COALESCE(score,0) FROM users WHERE id=? LIMIT 1");
    $ns->execute([$user_id]);
    $newScore = (int)$ns->fetchColumn();

    echo json_encode([
      'ok'=>true,
      'already'=>false,
      'score'=>$newScore,
      'title'=>$hint['title'],
      'content'=>$hint['content'],
      'cost'=>$cost,
    ]);
    exit;

  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok'=>false,'msg'=>'Server error opening hint. Check DB schema / logs.']);
    exit;
  }
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hints — Atlantis CTF</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --aqua2:#22d3ee;
  --gold:#f5d27b;
  --glass: rgba(0, 14, 24, 0.22);
  --stroke: rgba(56,247,255,0.18);
  --shadow: rgba(56,247,255,0.12);
  --text: #e6faff;
}

html,body{height:100%;}
body{margin:0;color:var(--text);background:#000;overflow-x:hidden;}

/* ===== VIDEO BG ===== */
.video-bg{position:fixed; inset:0; z-index:-6; overflow:hidden; background:#00101f;}
.video-bg video{width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.03);filter:saturate(1.05) contrast(1.05);}
.video-overlay{position:fixed; inset:0; z-index:-5; pointer-events:none;
  background:radial-gradient(900px 420px at 55% 12%, rgba(56,247,255,0.14), transparent 62%),
           linear-gradient(180deg, rgba(0,0,0,0.14), rgba(0,0,0,0.46));
}
.caustics{position:fixed; inset:0; z-index:-4; pointer-events:none;
  background:
    repeating-radial-gradient(circle at 30% 40%, rgba(56,247,255,.05) 0 2px, transparent 3px 14px),
    repeating-radial-gradient(circle at 70% 60%, rgba(255,255,255,.03) 0 1px, transparent 2px 18px);
  opacity:.26; mix-blend-mode:screen; animation: causticMove 7s linear infinite;
}
@keyframes causticMove{from{background-position:0 0,0 0;}to{background-position:0 220px,0 -180px;}}
.scanlines{position:fixed; inset:0; z-index:-3; pointer-events:none;
  background: repeating-linear-gradient(to bottom, rgba(255,255,255,0.02), rgba(255,255,255,0.02) 1px, transparent 1px, transparent 4px);
  opacity:.52;
}
.grain{position:fixed; inset:0; z-index:-2; pointer-events:none; opacity:.10;
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");
}

/* ===== LAYOUT ===== */
.shell{min-height:100vh; display:flex;}
.sidebar{
  width: 16.5rem;
  position:fixed; inset:0 auto 0 0; z-index:20;
  background: rgba(0, 16, 28, 0.40);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-right: 1px solid rgba(56,247,255,0.16);
  box-shadow: 0 0 60px rgba(56,247,255,0.10);
  font-family:'Share Tech Mono', monospace;
}
.brand{padding:18px 16px;border-bottom: 1px solid rgba(56,247,255,0.16);}
.brand .t{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.16em;color: rgba(56,247,255,0.95);text-shadow: 0 0 18px rgba(56,247,255,0.30);}
.brand .s{margin-top:6px;font-size:12px;color: rgba(245,210,123,0.92);letter-spacing:.12em;}
.nav a{display:flex;align-items:center;gap:10px;padding:12px 14px;color: rgba(230,250,255,0.88);
  border-bottom: 1px solid rgba(255,255,255,0.04);transition:.22s;letter-spacing:.06em;}
.nav a:hover{background: rgba(56,247,255,0.10); color: rgba(56,247,255,0.98);}
.nav a.active{background: rgba(56,247,255,0.14); border-left: 3px solid rgba(245,210,123,0.92); color: rgba(56,247,255,0.99);}
.nav a.danger{color: rgba(251,113,133,0.95);}
.nav a.danger:hover{background: rgba(251,113,133,0.10);}

.main{margin-left:16.5rem;width:calc(100% - 16.5rem);padding:22px;overflow:auto;}

/* ===== PANELS ===== */
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
.mono{font-family:'Share Tech Mono', monospace;}
.small{font-size:12px;color: rgba(230,250,255,0.72);}

/* ===== SCORE PILL ===== */
.scorePill{
  display:inline-flex; align-items:center; gap:10px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(255,255,255,0.04);
  box-shadow: inset 0 0 18px rgba(255,255,255,0.03);
  font-family:'Share Tech Mono', monospace;
  letter-spacing:.10em;
}
.scorePill b{color: rgba(245,210,123,0.95);}

/* ===== HINT CARDS ===== */
.hintCard{
  --g:0;
  position:relative;
  border-radius:22px;
  border:1px solid rgba(56,247,255,0.18);
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.06));
  box-shadow: 0 0 calc(var(--g) * 22px) rgba(56,247,255,0.22), inset 0 0 18px rgba(255,255,255,0.03);
  padding:16px;
  transition:.12s linear;
  overflow:hidden;
}
.hintCard::before{
  content:"";
  position:absolute; inset:auto -70px -80px auto;
  width:190px;height:190px;
  background: radial-gradient(circle, rgba(56,247,255,0.16), transparent 62%);
}
.hTitle{font-family:'Cinzel',serif;font-weight:900;color: rgba(230,250,255,0.96);letter-spacing:.06em;}
.badges{display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;}
.badge{
  display:inline-flex; align-items:center;
  padding:4px 10px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,0.10);
  background: rgba(255,255,255,0.03);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.10em;
  font-size:12px;
  color: rgba(230,250,255,0.82);
}
.badge.points{border-color:rgba(245,210,123,0.30); color: rgba(245,210,123,0.95); background: rgba(245,210,123,0.08);}
.badge.chal{border-color:rgba(56,247,255,0.22); color: rgba(56,247,255,0.95); background: rgba(56,247,255,0.07);}

.btn{
  width:100%;
  margin-top:14px;
  border-radius:16px;
  padding:10px 12px;
  border:1px solid rgba(56,247,255,0.18);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.10em;
  transition:.22s;
}
.btn-open{
  background: linear-gradient(90deg, rgba(56,247,255,0.92), rgba(34,211,238,0.72), rgba(245,210,123,0.75));
  color:#00131f;
}
.btn-open:hover{box-shadow:0 0 26px rgba(56,247,255,0.20); transform: translateY(-1px);}
.btn-open:disabled{opacity:.55; cursor:not-allowed; transform:none; box-shadow:none;}

.btn-seen{
  background: rgba(34,197,94,0.10);
  color: rgba(34,197,94,0.95);
  border-color: rgba(34,197,94,0.30);
}
.btn-seen:hover{box-shadow:0 0 18px rgba(34,197,94,0.14); transform: translateY(-1px);}

.hContent{
  display:none;
  margin-top:12px;
  border-radius:16px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(0,0,0,0.20);
  padding:12px;
  color: rgba(230,250,255,0.86);
  font-family:'Share Tech Mono', monospace;
  white-space: pre-wrap;
}
.metaTime{margin-top:12px;font-family:'Share Tech Mono', monospace;font-size:12px;color: rgba(230,250,255,0.60);}

.toast{
  position:fixed; right:18px; bottom:18px; z-index:9999;
  min-width:280px; max-width:420px;
  border-radius:18px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(0, 14, 24, 0.55);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 0 40px rgba(56,247,255,0.14);
  padding:12px 14px;
  display:none;
}
.toast .t{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.10em;color: rgba(56,247,255,0.92);}
.toast .d{margin-top:6px;font-family:'Share Tech Mono', monospace;color: rgba(230,250,255,0.78);font-size:12px;line-height:1.4;}

@media (max-width: 860px){
  .sidebar{position:static;width:100%;height:auto;}
  .main{margin-left:0;width:100%;}
}
</style>
</head>

<body>
<!-- Video background -->
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="caustics"></div>
<div class="scanlines"></div>
<div class="grain"></div>

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
      <a href="profile.php">👤 Profile</a>
      <a class="active" href="hints.php">💡 Hints</a>
      <a class="danger" href="../logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <!-- Main -->
  <main class="main space-y-6">

    <!-- Header -->
    <section class="panel p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
          <div class="h1 text-2xl md:text-3xl">💡 ATLANTIS HINT VAULT</div>
          <div class="mono small mt-2">Open a hint by spending points (charged once per hint)</div>
        </div>
        <div class="scorePill">
          <span class="mono small">YOUR SCORE</span>
          <b><span id="user-score"><?= (int)$user_score ?></span> PTS</b>
        </div>
      </div>
    </section>

    <!-- Hint Grid -->
    <section class="panel p-6">
      <div class="h1 text-xl">HINTS</div>
      <div class="mono small mt-2">Hover to glow • Click “OPEN” to spend points and reveal content</div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6" id="hintGrid">
        <?php foreach ($hints as $h): ?>
          <?php
            $hid = (int)$h['id'];
            $isOpened = isset($opened[$hid]);
            $cost = (int)$h['point_cost'];
          ?>
          <div class="hintCard" id="card-<?= $hid ?>">
            <div class="hTitle"><?= h($h['title']) ?></div>

            <div class="badges">
              <span class="badge points"><?= $cost ?> PTS</span>
              <?php if (!empty($h['challenge_title'])): ?>
                <span class="badge chal">CHAL: <?= h($h['challenge_title']) ?></span>
              <?php else: ?>
                <span class="badge chal">GENERAL</span>
              <?php endif; ?>
            </div>

            <div class="hContent" id="content-<?= $hid ?>">
              <?php if ($isOpened): ?>
                <?= h($h['content']) ?>
              <?php endif; ?>
            </div>

            <?php if ($isOpened): ?>
              <button class="btn btn-seen toggleBtn" type="button"
                      data-hid="<?= $hid ?>" data-cost="<?= $cost ?>" data-opened="1">
                ✅ VIEW AGAIN (NO COST)
              </button>
            <?php else: ?>
              <button class="btn btn-open openBtn" type="button"
                      data-hid="<?= $hid ?>" data-cost="<?= $cost ?>" data-opened="0">
                🔓 OPEN HINT (COST <?= $cost ?> PTS)
              </button>
            <?php endif; ?>

            <div class="metaTime">ADDED: <?= h($h['created_at']) ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (empty($hints)): ?>
          <div class="mono small">No hints found in database.</div>
        <?php endif; ?>
      </div>
    </section>

  </main>
</div>

<!-- Toast -->
<div class="toast" id="toast">
  <div class="t" id="toastT">ATLANTIS</div>
  <div class="d" id="toastD">—</div>
</div>

<script>
/* Toast */
const toast = document.getElementById('toast');
const toastT = document.getElementById('toastT');
const toastD = document.getElementById('toastD');
let toastTimer = null;
function showToast(title, desc){
  toastT.textContent = title;
  toastD.textContent = desc;
  toast.style.display = 'block';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=> toast.style.display='none', 3200);
}

/* Proximity glow */
const cards = document.querySelectorAll('.hintCard');
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
  });
});

/* Toggle opened content (no cost) */
document.querySelectorAll('.toggleBtn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const hid = btn.dataset.hid;
    const box = document.getElementById('content-'+hid);
    const isOpen = box.style.display === 'block';
    box.style.display = isOpen ? 'none' : 'block';
    btn.textContent = isOpen ? '✅ VIEW AGAIN (NO COST)' : '✅ HIDE HINT';
  });
});

/* Open hint (spend points, ajax) */
document.querySelectorAll('.openBtn').forEach(btn=>{
  btn.addEventListener('click', async ()=>{
    const hid = btn.dataset.hid;
    const cost = parseInt(btn.dataset.cost || '0', 10);

    // optimistic UI lock
    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = 'OPENING...';

    try{
      const form = new FormData();
      form.append('action','open_hint');
      form.append('hint_id', hid);

      const res = await fetch(location.href, { method:'POST', body: form, credentials:'same-origin' });
      const data = await res.json();

      if(!data.ok){
        btn.disabled = false;
        btn.textContent = oldText;
        showToast('ACCESS DENIED', data.msg || 'Could not open hint');
        return;
      }

      // Update score
      const scoreEl = document.getElementById('user-score');
      if(scoreEl) scoreEl.textContent = data.score;

      // Reveal content
      const box = document.getElementById('content-'+hid);
      box.textContent = data.content || '';
      box.style.display = 'block';

      // Convert button into toggle (no cost)
      btn.classList.remove('btn-open','openBtn');
      btn.classList.add('btn-seen','toggleBtn');
      btn.disabled = false;
      btn.textContent = '✅ HIDE HINT';
      btn.dataset.opened = '1';

      // attach toggle handler
      btn.addEventListener('click', ()=>{
        const isOpen = box.style.display === 'block';
        box.style.display = isOpen ? 'none' : 'block';
        btn.textContent = isOpen ? '✅ VIEW AGAIN (NO COST)' : '✅ HIDE HINT';
      }, { once:false });

      showToast(
        data.already ? 'ALREADY UNLOCKED' : 'HINT UNLOCKED',
        data.already ? 'No points deducted.' : `-${data.cost} points deducted.`
      );

    } catch(err){
      btn.disabled = false;
      btn.textContent = oldText;
      showToast('ERROR', 'Network/server error while opening hint.');
    }
  });
});
</script>

</body>
</html>
