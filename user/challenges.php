<?php
// user/challenges.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";
require_once __DIR__ . "/../includes/page_guard_json.php";
guard_page_json('challenges');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: ../index.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$userId   = (int)($_SESSION['user_id'] ?? 0);
$username = (string)($_SESSION['username'] ?? '');
log_activity($pdo, $userId, "Visited Challenges", $_SERVER['REQUEST_URI']);

/* ✅ CTF SWITCH */
$ctfEnded = false;

/* =========================
   HELPERS
========================= */
function client_ip(): string {
  $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_CLIENT_IP','REMOTE_ADDR'];
  foreach ($keys as $k) {
    if (!empty($_SERVER[$k])) {
      $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
      if ($ip !== '') return $ip;
    }
  }
  return '';
}

/* =========================
   FLAG SUBMISSION (logs to challenge_logs)
========================= */
$message = '';
$messageType = 'info'; // success | warn | error | info

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_id'], $_POST['flag'])) {
  if ($ctfEnded) {
    $message = "🛑 CTF is closed. Flag submissions are disabled.";
    $messageType = "warn";
  } else {
    $challenge_id = (int)$_POST['challenge_id'];
    $submitted_flag = trim((string)$_POST['flag']);
    $ip = client_ip();

    if ($challenge_id <= 0 || $submitted_flag === '') {
      $message = "⚠️ Invalid submission.";
      $messageType = "warn";
    } else {
      try {
        $pdo->beginTransaction();

        // lock user row (prevents race score updates)
        $pdo->prepare("SELECT id FROM users WHERE id=? FOR UPDATE")->execute([$userId]);

        $stmt = $pdo->prepare("SELECT id, title, flag, points FROM challenges WHERE id=? LIMIT 1");
        $stmt->execute([$challenge_id]);
        $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$challenge) {
          $pdo->rollBack();
          $message = "⚠️ Challenge not found.";
          $messageType = "warn";
        } else {
          $stmtCheck = $pdo->prepare("SELECT 1 FROM solves WHERE user_id=? AND challenge_id=? LIMIT 1");
          $stmtCheck->execute([$userId, $challenge_id]);
          $alreadySolved = (bool)$stmtCheck->fetchColumn();

          // ✅ ALWAYS log attempt (even if already solved)
          if ($alreadySolved) {
            $pdo->prepare("
              INSERT INTO challenge_logs (user_id, username, challenge_id, flag_submitted, status, ip_address, submission_time)
              VALUES (:uid, :uname, :cid, :flag, :status, :ip, NOW())
            ")->execute([
              ':uid' => $userId,
              ':uname' => $username,
              ':cid' => $challenge_id,
              ':flag' => $submitted_flag,
              ':status' => 'already_solved',
              ':ip' => $ip
            ]);

            $pdo->commit();
            $message = "⚠️ You already solved this challenge.";
            $messageType = "warn";
          } else {
            $isCorrect = hash_equals((string)$challenge['flag'], $submitted_flag);

            // ✅ LOG THIS SUBMISSION
            $pdo->prepare("
              INSERT INTO challenge_logs (user_id, username, challenge_id, flag_submitted, status, ip_address, submission_time)
              VALUES (:uid, :uname, :cid, :flag, :status, :ip, NOW())
            ")->execute([
              ':uid' => $userId,
              ':uname' => $username,
              ':cid' => $challenge_id,
              ':flag' => $submitted_flag,
              ':status' => $isCorrect ? 'correct' : 'wrong',
              ':ip' => $ip
            ]);

            if ($isCorrect) {
              $pdo->prepare("UPDATE users SET score = COALESCE(score,0) + ? WHERE id=?")
                  ->execute([(int)$challenge['points'], $userId]);

              $pdo->prepare("INSERT INTO solves (user_id, challenge_id, solved_at) VALUES (?,?,NOW())")
                  ->execute([$userId, $challenge_id]);

              $pdo->commit();

              $message = "✅ Correct! You earned " . (int)$challenge['points'] . " points.";
              $messageType = "success";
              log_activity($pdo, $userId, "Solved challenge: " . $challenge['title']);
            } else {
              $pdo->commit();
              $message = "❌ Incorrect flag. Try again!";
              $messageType = "error";
              log_activity($pdo, $userId, "Failed attempt on challenge: " . $challenge['title']);
            }
          }
        }
      } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = "❌ Server error. Please try again.";
        $messageType = "error";
      }
    }
  }
}

/* =========================
   FETCH DATA
========================= */

// Solved lookup
$solvedStmt = $pdo->prepare("SELECT challenge_id FROM solves WHERE user_id=?");
$solvedStmt->execute([$userId]);
$solvedIds = array_map('intval', array_column($solvedStmt->fetchAll(PDO::FETCH_ASSOC), 'challenge_id'));
$solvedLookup = array_fill_keys($solvedIds, true);

// Categories ONLY WITH challenges
$categories = $pdo->query("
  SELECT c.id, c.name, c.description, COUNT(ch.id) AS ch_count
  FROM categories c
  INNER JOIN challenges ch ON ch.category_id = c.id
  GROUP BY c.id, c.name, c.description
  HAVING ch_count > 0
  ORDER BY c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// All challenges
$allChallenges = $pdo->query("
  SELECT id, title, description, points, tags, file_path, link, category_id
  FROM challenges
  ORDER BY category_id ASC, points DESC, id DESC
")->fetchAll(PDO::FETCH_ASSOC);

$totalChallenges = count($allChallenges);
$solvedCount = count($solvedIds);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Challenges — Atlantis CTF</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff; --aqua2:#22d3ee; --gold:#f5d27b;
  --glass: rgba(0, 14, 24, 0.22);
  --stroke: rgba(56,247,255,0.18);
  --shadow: rgba(56,247,255,0.12);
  --text: #e6faff;
}

html,body{height:100%;}
body{margin:0;color:var(--text);background:#000;overflow-x:hidden;}

/* Video BG */
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
  background-image:url("data:image/svg+xml,%3Csvg xmlns='https://www.w3.org/2000/svg' width='140' height='140'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.8' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='140' height='140' filter='url(%23n)' opacity='.35'/%3E%3C/svg%3E");
}

/* Layout */
.shell{min-height:100vh; display:flex;}
.sidebar{
  width:16.5rem; position:fixed; inset:0 auto 0 0; z-index:20;
  background: rgba(0,16,28,0.40);
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  border-right:1px solid rgba(56,247,255,0.16);
  box-shadow: 0 0 60px rgba(56,247,255,0.10);
  font-family:'Share Tech Mono', monospace;
}
.brand{padding:18px 16px;border-bottom:1px solid rgba(56,247,255,0.16);}
.brand .t{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.16em;color:rgba(56,247,255,0.95);text-shadow:0 0 18px rgba(56,247,255,0.30);}
.brand .s{margin-top:6px;font-size:12px;color:rgba(245,210,123,0.92);letter-spacing:.12em;}
.nav a{display:flex;gap:10px;align-items:center;padding:12px 14px;color:rgba(230,250,255,0.88);
  border-bottom:1px solid rgba(255,255,255,0.04);transition:.22s;letter-spacing:.06em;}
.nav a:hover{background:rgba(56,247,255,0.10);color:rgba(56,247,255,0.98);}
.nav a.active{background:rgba(56,247,255,0.14);border-left:3px solid rgba(245,210,123,0.92);color:rgba(56,247,255,0.99);}
.nav a.danger{color:rgba(251,113,133,0.95);}
.nav a.danger:hover{background:rgba(251,113,133,0.10);}

.main{margin-left:16.5rem;width:calc(100% - 16.5rem);padding:22px;overflow:auto;}
.panel{
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  background: var(--glass);
  border: 1px solid rgba(56,247,255,0.18);
  box-shadow: 0 0 55px rgba(56,247,255,0.12), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 22px;
}
.h1{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.14em;color:rgba(56,247,255,0.92);text-shadow:0 0 18px rgba(56,247,255,0.22);}
.mono{font-family:'Share Tech Mono', monospace;}
.small{font-size:12px;color:rgba(230,250,255,0.72);}

.pill{display:inline-flex;gap:10px;align-items:center;padding:8px 12px;border-radius:999px;
  border:1px solid rgba(56,247,255,0.18);background:rgba(255,255,255,0.04);
  font-family:'Share Tech Mono', monospace;font-weight:900;letter-spacing:.10em;}
.pill b{color:rgba(245,210,123,0.95);}

.alert{border-radius:18px;border:1px solid rgba(56,247,255,0.18);background:rgba(0,14,24,0.40);padding:12px 14px;
  font-family:'Share Tech Mono', monospace;letter-spacing:.06em;}
.alert.success{border-color: rgba(34,197,94,0.35); background: rgba(34,197,94,0.08); color: rgba(187,255,220,0.95);}
.alert.warn{border-color: rgba(245,158,11,0.35); background: rgba(245,158,11,0.08); color: rgba(255,240,205,0.95);}
.alert.error{border-color: rgba(244,63,94,0.35); background: rgba(244,63,94,0.08); color: rgba(255,210,220,0.95);}

/* Two column */
.grid2{display:grid;grid-template-columns: 320px 1fr; gap:16px;}
@media (max-width: 1100px){ .grid2{grid-template-columns: 1fr;} }

/* Category list */
.catBtn{
  width:100%; text-align:left;
  display:flex; justify-content:space-between; align-items:center;
  padding:12px 12px;
  border-radius:18px;
  border:1px solid rgba(56,247,255,0.16);
  background:rgba(255,255,255,0.03);
  font-family:'Share Tech Mono', monospace;
  font-weight:900; letter-spacing:.08em;
  transition:.22s;
  position:relative;
  overflow:hidden;
}
.catBtn::after{
  content:"";
  position:absolute; inset:0;
  background: radial-gradient(240px 120px at var(--mx,50%) var(--my,50%), rgba(56,247,255,0.12), transparent 60%);
  opacity:0;
  transition:opacity .18s;
  pointer-events:none;
}
.catBtn:hover::after{opacity:1;}
.catBtn:hover{transform: translateY(-1px); box-shadow:0 0 18px rgba(56,247,255,0.12);}
.catBtn.active{border-color: rgba(245,210,123,0.35); background: rgba(245,210,123,0.08); color: rgba(245,210,123,0.95);}
.catBtn .count{font-size:12px;color: rgba(230,250,255,0.72);}

.hiddenArea{
  display:grid;
  place-items:center;
  min-height:220px;
  border-radius:22px;
  border:1px dashed rgba(56,247,255,0.20);
  background: rgba(0,0,0,0.16);
  color: rgba(230,250,255,0.74);
  font-family:'Share Tech Mono', monospace;
  letter-spacing:.08em;
  padding: 22px;
  text-align:center;
}

/* === FIX: neat aligned cards, long description not pushing page === */
.chWrap{display:none;}
.chHeader{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
.chHeader .t{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.12em;color:rgba(56,247,255,0.92);text-shadow:0 0 18px rgba(56,247,255,0.22);}
.chHeader .d{font-size:12px;color:rgba(230,250,255,0.70);}

/* Cards grid becomes consistent-height */
.cardsGrid{
  display:grid;
  grid-template-columns: repeat(12, 1fr);
  gap:14px;
}
@media (max-width: 1280px){ .cardsGrid{grid-template-columns: repeat(8, 1fr);} }
@media (max-width: 980px){ .cardsGrid{grid-template-columns: repeat(6, 1fr);} }
@media (max-width: 740px){ .cardsGrid{grid-template-columns: repeat(4, 1fr);} }
@media (max-width: 520px){ .cardsGrid{grid-template-columns: repeat(1, 1fr);} }

.chCard{
  grid-column: span 4; /* 3 per row on large (12 cols) */
  border-radius:22px;border:1px solid rgba(56,247,255,0.18);
  background: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.06), rgba(255,255,255,0.02) 55%, rgba(0,0,0,0.06));
  box-shadow: 0 0 0 rgba(56,247,255,0.00), inset 0 0 18px rgba(255,255,255,0.03);
  padding:16px;
  transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  overflow:hidden;
  position:relative;

  /* key: fixed height and internal layout */
  height: 420px;
  display:flex;
  flex-direction:column;
}
@media (max-width: 980px){ .chCard{grid-column: span 3; height: 430px;} }
@media (max-width: 740px){ .chCard{grid-column: span 4; height: 440px;} }
@media (max-width: 520px){ .chCard{grid-column: span 1; height:auto;} }

.chCard::before{
  content:"";
  position:absolute; inset:-2px;
  background: radial-gradient(320px 140px at var(--mx,50%) var(--my,50%), rgba(56,247,255,0.16), transparent 60%);
  opacity:0;
  transition: opacity .18s ease;
  pointer-events:none;
}
.chCard:hover{
  transform: translateY(-2px);
  border-color: rgba(56,247,255,0.34);
  box-shadow: 0 0 28px rgba(56,247,255,0.16), inset 0 0 18px rgba(255,255,255,0.03);
}
.chCard:hover::before{opacity:1;}

.chTop{display:flex;flex-direction:column;gap:10px;}
.chTitle{font-family:'Cinzel',serif;font-weight:900;color:rgba(230,250,255,0.96);letter-spacing:.06em;line-height:1.1;}
.badges{display:flex;flex-wrap:wrap;gap:8px;}
.badge{display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,0.10);
  background: rgba(255,255,255,0.03);font-family:'Share Tech Mono', monospace;font-weight:900;letter-spacing:.10em;font-size:12px;color:rgba(230,250,255,0.82);}
.badge.points{border-color:rgba(245,210,123,0.30); color:rgba(245,210,123,0.95); background:rgba(245,210,123,0.08);}
.badge.done{border-color:rgba(34,197,94,0.30); color:rgba(34,197,94,0.95); background:rgba(34,197,94,0.10);}

/* Description area: clamp + internal scroll for long text */
.chDesc{
  margin-top:10px;
  color:rgba(230,250,255,0.78);
  font-family:'Share Tech Mono', monospace;
  font-size:12px;
  line-height:1.45;

  /* takes remaining space but never grows the card */
  flex:1;
  min-height: 0; /* important for flex scroll */
  overflow:auto;
  padding-right:6px;
}

/* Pretty scrollbars (webkit) */
.chDesc::-webkit-scrollbar{width:8px;}
.chDesc::-webkit-scrollbar-track{background:rgba(0,0,0,0.20); border-radius:99px;}
.chDesc::-webkit-scrollbar-thumb{background:rgba(56,247,255,0.20); border-radius:99px; border:1px solid rgba(56,247,255,0.18);}
.chDesc::-webkit-scrollbar-thumb:hover{background:rgba(56,247,255,0.32);}

.cardFooter{
  margin-top:12px;
  display:flex;
  flex-direction:column;
  gap:10px;
}

/* Link row + "View more" */
.linkRow{display:flex;flex-wrap:wrap;gap:10px;}
.linkA{
  display:inline-flex;gap:8px;align-items:center;
  padding:8px 10px;border-radius:14px;border:1px solid rgba(56,247,255,0.18);
  background: rgba(0,0,0,0.18);color:rgba(56,247,255,0.95);
  font-family:'Share Tech Mono', monospace;font-weight:900;letter-spacing:.08em;transition:.22s;
}
.linkA:hover{box-shadow:0 0 18px rgba(56,247,255,0.14); transform: translateY(-1px);}

.btnLite{
  display:inline-flex;justify-content:center;align-items:center;
  padding:8px 10px;border-radius:14px;
  border:1px solid rgba(245,210,123,0.28);
  background: rgba(245,210,123,0.08);
  color: rgba(245,210,123,0.95);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.10em;
  transition:.22s;
}
.btnLite:hover{box-shadow:0 0 18px rgba(245,210,123,0.14); transform: translateY(-1px);}

.formBox{border-radius:18px;border:1px solid rgba(56,247,255,0.16);background:rgba(0,0,0,0.18);padding:12px;}
.inp{width:100%;border-radius:14px;border:1px solid rgba(56,247,255,0.18);background:rgba(255,255,255,0.05);
  padding:10px 12px;color:rgba(230,250,255,0.92);font-family:'Share Tech Mono', monospace;letter-spacing:.06em;}
.inp:focus{outline:none;border-color: rgba(56,247,255,0.45); box-shadow: 0 0 14px rgba(56,247,255,0.18);}
.btn{width:100%;border-radius:14px;padding:10px 12px;font-family:'Share Tech Mono', monospace;font-weight:900;letter-spacing:.10em;border:1px solid rgba(56,247,255,0.18);transition:.22s;}
.btn-submit{background: linear-gradient(90deg, rgba(56,247,255,0.92), rgba(34,211,238,0.72), rgba(245,210,123,0.75)); color:#00131f;}
.btn-submit:hover{box-shadow:0 0 26px rgba(56,247,255,0.20); transform: translateY(-1px);}
.solvedBox{border-radius:18px;border:1px solid rgba(34,197,94,0.30);background: rgba(34,197,94,0.08);
  padding:12px;font-family:'Share Tech Mono', monospace;font-weight:900;letter-spacing:.10em;color: rgba(34,197,94,0.95);}

/* Modal (View More) */
.modalBackdrop{
  position:fixed; inset:0; z-index:60;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(10px);
  display:none;
  align-items:center;
  justify-content:center;
  padding:18px;
}
.modal{
  width:min(920px, 100%);
  border-radius:22px;
  border:1px solid rgba(56,247,255,0.22);
  background: rgba(0, 14, 24, 0.55);
  box-shadow: 0 0 60px rgba(56,247,255,0.18), inset 0 0 18px rgba(255,255,255,0.04);
  overflow:hidden;
}
.modalHead{
  padding:14px 16px;
  border-bottom:1px solid rgba(56,247,255,0.16);
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:12px;
}
.modalTitle{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.10em;
  color: rgba(230,250,255,0.96);
}
.modalClose{
  padding:8px 10px;
  border-radius:14px;
  border:1px solid rgba(251,113,133,0.35);
  background: rgba(251,113,133,0.08);
  color: rgba(255,210,220,0.95);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.08em;
  transition:.22s;
}
.modalClose:hover{transform: translateY(-1px); box-shadow:0 0 18px rgba(251,113,133,0.14);}
.modalBody{
  padding:16px;
  max-height: 70vh;
  overflow:auto;
  font-family:'Share Tech Mono', monospace;
  font-size:13px;
  line-height:1.55;
  color: rgba(230,250,255,0.86);
}
.modalBody::-webkit-scrollbar{width:10px;}
.modalBody::-webkit-scrollbar-track{background:rgba(0,0,0,0.22); border-radius:99px;}
.modalBody::-webkit-scrollbar-thumb{background:rgba(56,247,255,0.22); border-radius:99px; border:1px solid rgba(56,247,255,0.18);}

@media (max-width: 860px){
  .sidebar{position:static;width:100%;height:auto;}
  .main{margin-left:0;width:100%;}
}
</style>
</head>

<body>
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
  <aside class="sidebar">
    <div class="brand">
      <div class="t">ATLANTIS CTF</div>
      <div class="s">EXPLORER CONSOLE</div>
    </div>
    <nav class="nav">
      <a href="dashboard.php">🏠 Dashboard</a>
      <a class="active" href="challenges.php">🛠 Challenges</a>
      <a href="leaderboard.php">🏆 Leaderboard</a>
      <a href="profile.php">👤 Profile</a>
      <a href="hints.php">💡 Hints</a>
      <a class="danger" href="../logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <main class="main space-y-6">
    <section class="panel p-6">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <div class="h1 text-2xl md:text-3xl">🛠 ATLANTIS CHALLENGE TEMPLE</div>
          <div class="mono small mt-2">Select a category → challenges appear</div>
        </div>
        <div class="flex flex-wrap gap-2">
          <span class="pill">TOTAL: <b><?= (int)$totalChallenges ?></b></span>
          <span class="pill">SOLVED: <b><?= (int)$solvedCount ?></b></span>
          <span class="pill">STATUS: <b><?= $ctfEnded ? 'SEALED' : 'OPEN' ?></b></span>
        </div>
      </div>
    </section>

    <?php if ($message): ?>
      <div class="alert <?= h($messageType) ?>"><?= h($message) ?></div>
    <?php endif; ?>

    <section class="grid2">
      <!-- LEFT: categories -->
      <div class="panel p-5">
        <div class="h1 text-lg">🔱 CATEGORIES</div>
        <div class="mono small mt-2">Choose one to reveal challenges</div>

        <div class="mt-4 space-y-3" id="catList">
          <?php foreach($categories as $cat): ?>
            <button
              type="button"
              class="catBtn"
              data-cat="<?= (int)$cat['id'] ?>"
              data-name="<?= h($cat['name']) ?>"
              data-desc="<?= h((string)($cat['description'] ?? '')) ?>"
            >
              <span><?= h($cat['name']) ?></span>
              <span class="count"><?= (int)$cat['ch_count'] ?></span>
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- RIGHT: challenges -->
      <div class="panel p-5">
        <div id="emptyState" class="hiddenArea">
          <div>
            <div style="font-family:'Cinzel',serif;font-weight:900;letter-spacing:.14em;color:rgba(56,247,255,0.92);">
              🔒 CHAMBER SEALED
            </div>
            <div style="margin-top:10px;">
              Select a category on the left to open a chamber.
            </div>
          </div>
        </div>

        <div id="challengeArea" class="chWrap">
          <div class="chHeader">
            <div class="t" id="catTitle">CATEGORY</div>
            <div class="d" id="catDesc"></div>
          </div>

          <div class="cardsGrid" id="cards">
            <?php foreach ($allChallenges as $c): ?>
              <?php
                $cid = (int)$c['id'];
                $isSolved = isset($solvedLookup[$cid]);
                $catId = (int)$c['category_id'];

                $descRaw = (string)($c['description'] ?? '');
                // keep original for modal, safe-escaped in data-full
                $descFull = h($descRaw);
              ?>
              <div
                class="chCard"
                data-cat="<?= $catId ?>"
                data-title="<?= h($c['title']) ?>"
                data-desc="<?= $descFull ?>"
                style="display:none;"
              >
                <div class="chTop">
                  <div class="chTitle"><?= h($c['title']) ?></div>

                  <?php if (!empty($c['tags'])): ?>
                    <div class="badges">
                      <?php foreach (explode(',', (string)$c['tags']) as $tag): ?>
                        <?php $t = trim($tag); if($t==='') continue; ?>
                        <span class="badge" style="border-color:rgba(56,247,255,0.22);color:rgba(56,247,255,0.92);background:rgba(56,247,255,0.07);">
                          <?= h($t) ?>
                        </span>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </div>

                <?php if($descRaw !== ''): ?>
                  <!-- FIX: scroll inside card, no mega page height -->
                  <div class="chDesc"><?= nl2br(h($descRaw)) ?></div>
                <?php else: ?>
                  <div class="chDesc" style="opacity:.7;">No description.</div>
                <?php endif; ?>

                <div class="cardFooter">
                  <div class="badges">
                    <span class="badge points"><?= (int)$c['points'] ?> PTS</span>
                    <?php if ($isSolved): ?>
                      <span class="badge done">✅ SOLVED</span>
                    <?php endif; ?>
                  </div>

                  <div class="linkRow">
                    <?php
                      if (!empty($c['file_path'])) {
                        $fullPath = __DIR__ . '/../' . $c['file_path'];
                        if (file_exists($fullPath)) {
                          echo '<a class="linkA" href="../'.h($c['file_path']).'" download>📄 FILE</a>';
                        } else {
                          echo '<span class="mono small" style="color:rgba(244,63,94,0.9)">File missing</span>';
                        }
                      }
                      if (!empty($c['link'])) {
                        echo '<a class="linkA" href="'.h($c['link']).'" target="_blank" rel="noopener">🔗 LINK</a>';
                      }
                    ?>
                    <button type="button" class="btnLite js-viewMore">👁 VIEW MORE</button>
                  </div>

                  <?php if ($ctfEnded): ?>
                    <div class="solvedBox" style="border-color:rgba(245,158,11,0.35);background:rgba(245,158,11,0.08);color:rgba(255,240,205,0.95);">
                      🔒 SEALED (CTF ENDED)
                    </div>
                  <?php else: ?>
                    <?php if (!$isSolved): ?>
                      <form method="POST" class="formBox">
                        <input type="hidden" name="challenge_id" value="<?= $cid ?>">
                        <input class="inp" type="text" name="flag" placeholder="ENTER FLAG (EX: ATL{...})" required>
                        <button class="btn btn-submit" type="submit">SUBMIT FLAG →</button>
                      </form>
                    <?php else: ?>
                      <div class="solvedBox">✅ COMPLETED</div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>
  </main>
</div>

<!-- Modal -->
<div class="modalBackdrop" id="modalBackdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
    <div class="modalHead">
      <div>
        <div class="modalTitle" id="modalTitle">CHALLENGE</div>
        <div class="mono small" id="modalSub" style="margin-top:6px; opacity:.85;"></div>
      </div>
      <button class="modalClose" type="button" id="modalClose">✖ CLOSE</button>
    </div>
    <div class="modalBody" id="modalBody"></div>
  </div>
</div>

<script>
/* ===== Mouse glow tracking for category buttons & cards ===== */
function setMouseVars(el, evt){
  const r = el.getBoundingClientRect();
  const x = ((evt.clientX - r.left) / r.width) * 100;
  const y = ((evt.clientY - r.top) / r.height) * 100;
  el.style.setProperty('--mx', x.toFixed(2) + '%');
  el.style.setProperty('--my', y.toFixed(2) + '%');
}

document.querySelectorAll('.catBtn').forEach(btn=>{
  btn.addEventListener('mousemove', e => setMouseVars(btn, e));
});
document.querySelectorAll('.chCard').forEach(card=>{
  card.addEventListener('mousemove', e => setMouseVars(card, e));
});

/* ===== Category click → show only that category challenges (smooth + aligned) ===== */
const chCards = document.querySelectorAll('.chCard');
const catBtns = document.querySelectorAll('.catBtn');
const emptyState = document.getElementById('emptyState');
const challengeArea = document.getElementById('challengeArea');
const catTitle = document.getElementById('catTitle');
const catDesc = document.getElementById('catDesc');

function showCategory(catId, name, desc){
  emptyState.style.display = 'none';
  challengeArea.style.display = 'block';

  catTitle.textContent = name || 'CATEGORY';
  catDesc.textContent = desc || '';

  // show/hide
  chCards.forEach(card=>{
    card.style.display = (card.dataset.cat === String(catId)) ? 'flex' : 'none';
  });

  // active btn
  catBtns.forEach(b=>b.classList.remove('active'));
  const activeBtn = [...catBtns].find(b => b.dataset.cat === String(catId));
  if (activeBtn) activeBtn.classList.add('active');

  // smooth scroll to top of cards
  document.getElementById('cards').scrollIntoView({behavior:'smooth', block:'start'});
}

catBtns.forEach(btn=>{
  btn.addEventListener('click', ()=> showCategory(btn.dataset.cat, btn.dataset.name, btn.dataset.desc));
});

/* ===== Modal "View More" (full description without breaking layout) ===== */
const modalBackdrop = document.getElementById('modalBackdrop');
const modalTitle = document.getElementById('modalTitle');
const modalSub = document.getElementById('modalSub');
const modalBody = document.getElementById('modalBody');
const modalClose = document.getElementById('modalClose');

function openModal(title, bodyHtml, sub=''){
  modalTitle.textContent = title || 'CHALLENGE';
  modalSub.textContent = sub || '';
  modalBody.innerHTML = bodyHtml || '';
  modalBackdrop.style.display = 'flex';
  modalBackdrop.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

function closeModal(){
  modalBackdrop.style.display = 'none';
  modalBackdrop.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.js-viewMore');
  if (!btn) return;

  const card = btn.closest('.chCard');
  if (!card) return;

  const title = card.getAttribute('data-title') || 'CHALLENGE';
  const desc  = card.getAttribute('data-desc') || '';
  // desc is already escaped by PHP (h()), so safe to insert as text with <br>
  const pretty = desc.replace(/\n/g, '<br>');
  openModal(title, pretty, 'FULL BRIEFING');
});

modalClose.addEventListener('click', closeModal);
modalBackdrop.addEventListener('click', (e)=>{
  if (e.target === modalBackdrop) closeModal();
});
document.addEventListener('keydown', (e)=>{
  if (e.key === 'Escape' && modalBackdrop.style.display === 'flex') closeModal();
});
</script>

</body>
</html>
