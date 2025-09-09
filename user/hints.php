<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

// Security: logged-in user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
log_activity($pdo, $user_id, "Visited Hints Page", $_SERVER['REQUEST_URI']);

// Fetch user's current score (note: your project uses 'score' column)
$stmt = $pdo->prepare("SELECT score FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_score = $stmt->fetchColumn();
if ($user_score === false) $user_score = 0;

// Fetch hints with challenge title
$stmt = $pdo->query("
    SELECT h.id, h.title, h.content, h.point_cost, h.created_at, c.title AS challenge_title
    FROM hints h
    LEFT JOIN challenges c ON h.challenge_id = c.id
    ORDER BY h.created_at DESC
");
$hints = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch which hints this user already viewed
$stmt = $pdo->prepare("SELECT hint_id FROM hint_views WHERE user_id = ?");
$stmt->execute([$user_id]);
$viewedRows = $stmt->fetchAll(PDO::FETCH_COLUMN);
$viewedMap = array_flip($viewedRows); // quick lookup
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Hints — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.card { background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3); border-radius:12px; padding:20px; transition: transform 0.3s, box-shadow 0.3s; }
.card:hover { transform: translateY(-4px); box-shadow: 0 0 18px rgba(45,226,138,0.5); }
.badge { display:inline-block; padding:4px 8px; border-radius:8px; font-size:0.85rem; margin-right:8px; }
.badge-points { background:#2de28a; color:#000; font-weight:bold; }
.badge-challenge { background:rgba(45,226,138,0.12); color:#2de28a; border:1px solid rgba(45,226,138,0.25); }
button { background:#2de28a; color:#000; font-weight:bold; padding:8px 12px; border:none; border-radius:6px; cursor:pointer; transition:0.2s; }
button:hover { background:#1ab66b; }
.hint-content { display:none; margin-top:12px; background: rgba(0,255,0,0.03); padding:12px; border-left:3px solid #2de28a; border-radius:6px; font-size:0.95rem; color:#b6f7d3; }
.viewed { display:block !important; }
.header-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; gap:12px; }
.score-pill { background: rgba(45,226,138,0.12); padding:6px 10px; border-radius:8px; font-weight:700; color:#2de28a; border:1px solid rgba(45,226,138,0.18); }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64 p-4">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main -->
<div class="flex-1 p-6 overflow-auto">
  <div class="header-row">
    <h1 class="text-3xl font-bold text-green-400">💡 Hints</h1>
    <div class="score-pill">Your Score: <span id="user-score"><?= (int)$user_score ?></span> pts</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($hints as $h): 
        $already = isset($viewedMap[$h['id']]);
    ?>
    <div class="card" id="card-<?= $h['id'] ?>">
      <h2 class="text-xl text-green-300 font-bold"><?= htmlspecialchars($h['title']) ?></h2>
      <div class="mb-3">
        <span class="badge badge-points"><?= (int)$h['point_cost'] ?> pts</span>
        <?php if (!empty($h['challenge_title'])): ?>
          <span class="badge badge-challenge">Related: <?= htmlspecialchars($h['challenge_title']) ?></span>
        <?php else: ?>
          <span class="badge badge-challenge">General Hint</span>
        <?php endif; ?>
      </div>

      <?php if ($already): ?>
        <div class="hint-content viewed" id="hint-<?= $h['id'] ?>"><?= nl2br(htmlspecialchars($h['content'])) ?></div>
        <div class="mt-3"><button disabled>Viewed</button></div>
      <?php else: ?>
        <div class="mt-2">
          <button class="view-hint" data-id="<?= $h['id'] ?>" data-cost="<?= (int)$h['point_cost'] ?>">View Hint (Cost: <?= (int)$h['point_cost'] ?> pts)</button>
        </div>
        <div class="hint-content" id="hint-<?= $h['id'] ?>"></div>
      <?php endif; ?>
      <small class="block mt-3 text-gray-500">Added on <?= htmlspecialchars($h['created_at']) ?></small>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
// helper to escape HTML
function escapeHtml(unsafe) {
    return unsafe
         .replace(/&/g, "&amp;")
         .replace(/</g, "&lt;")
         .replace(/>/g, "&gt;")
         .replace(/"/g, "&quot;")
         .replace(/'/g, "&#039;");
}

document.querySelectorAll('.view-hint').forEach(btn => {
    btn.addEventListener('click', function() {
        const hintId = this.dataset.id;
        const cost = parseInt(this.dataset.cost, 10);
        const hintDiv = document.getElementById('hint-' + hintId);
        const card = document.getElementById('card-' + hintId);
        const scoreEl = document.getElementById('user-score');

        // disable button while processing
        btn.disabled = true;
        btn.innerText = 'Processing...';

        fetch('view_hint.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ hint_id: hintId })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                // set new score
                scoreEl.innerText = data.new_score;

                // show content safely (escape + newlines -> <br>)
                const safe = escapeHtml(data.content || '');
                hintDiv.innerHTML = safe.replace(/\n/g, '<br>');
                hintDiv.classList.add('viewed');

                // update button
                btn.innerText = 'Viewed';
                btn.disabled = true;
            } else {
                alert(data.message || 'Could not show hint');
                btn.disabled = false;
                btn.innerText = `View Hint (Cost: ${cost} pts)`;
            }
        })
        .catch(err => {
            console.error(err);
            alert('Request failed — check console');
            btn.disabled = false;
            btn.innerText = `View Hint (Cost: ${cost} pts)`;
        });
    });
});
</script>

</body>
</html>
