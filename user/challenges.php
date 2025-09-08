<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Log dashboard visit
log_activity($pdo, $_SESSION['user_id'], "Visited Challenges", $_SERVER['REQUEST_URI']);

// Handle flag submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_id'], $_POST['flag'])) {
    $challenge_id = (int)$_POST['challenge_id'];
    $submitted_flag = trim($_POST['flag']);

    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
    $stmt->execute([$challenge_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($challenge) {
        $stmtCheck = $pdo->prepare("SELECT * FROM solves WHERE user_id = ? AND challenge_id = ?");
        $stmtCheck->execute([$_SESSION['user_id'], $challenge_id]);
        $alreadySolved = $stmtCheck->fetch();

        if ($submitted_flag === $challenge['flag'] && !$alreadySolved) {
            $stmtUpdate = $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?");
            $stmtUpdate->execute([$challenge['points'], $_SESSION['user_id']]);

            $stmtSolved = $pdo->prepare("INSERT INTO solves (user_id, challenge_id, solved_at) VALUES (?, ?, NOW())");
            $stmtSolved->execute([$_SESSION['user_id'], $challenge_id]);

            $message = "✅ Correct! You earned {$challenge['points']} points.";
            log_activity($pdo, $_SESSION['user_id'], "Solved challenge: {$challenge['title']}");
        } elseif ($alreadySolved) {
            $message = "⚠️ You already solved this challenge.";
        } else {
            $message = "❌ Incorrect flag. Try again!";
            log_activity($pdo, $_SESSION['user_id'], "Failed attempt on challenge: {$challenge['title']}");
        }
    } else {
        $message = "⚠️ Challenge not found.";
    }
}

// Fetch all categories
$categoriesStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch solved challenges
$solvedStmt = $pdo->prepare("SELECT challenge_id FROM solves WHERE user_id = ?");
$solvedStmt->execute([$_SESSION['user_id']]);
$solvedIds = array_column($solvedStmt->fetchAll(PDO::FETCH_ASSOC), 'challenge_id');

// Fetch challenges per category
$challengesByCategory = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE category_id = ? ORDER BY points DESC");
    $stmt->execute([$cat['id']]);
    $challengesByCategory[$cat['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Challenges — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');
  body {
    font-family: 'Share Tech Mono', monospace;
    background: radial-gradient(circle at top, #0f172a, #020617);
    color: #e2e8f0;
  }
  .sidebar {
    background: rgba(15,23,42,0.9);
    border-right: 1px solid rgba(34,197,94,0.3);
    box-shadow: 0 0 20px rgba(34,197,94,0.1);
  }
  .sidebar a {
    display:block; padding:12px; 
    color:#cbd5e1; 
    border-bottom:1px solid rgba(255,255,255,0.05);
    transition:0.2s;
  }
  .sidebar a:hover { background:rgba(34,197,94,0.2); color:#22c55e; }
  .challenge-card {
    background: rgba(15,23,42,0.7);
    border:1px solid rgba(34,197,94,0.4);
    border-radius:14px;
    padding:20px;
    transition:0.3s;
  }
  .challenge-card:hover {
    transform: translateY(-5px);
    box-shadow:0 0 25px rgba(34,197,94,0.4);
  }
  .tag {
    display:inline-block;
    background: rgba(34,197,94,0.2);
    color:#22c55e;
    padding:2px 8px;
    margin:0 4px 4px 0;
    border-radius:6px;
    font-size:0.8rem;
  }
  input, button {
    border:1px solid rgba(34,197,94,0.3);
    background: rgba(255,255,255,0.05);
    color:#e2e8f0;
    padding:8px;
    border-radius:6px;
    width:100%;
    transition:0.2s;
  }
  input:focus { outline:none; border-color:#22c55e; box-shadow:0 0 8px #22c55e; }
  button {
    background: linear-gradient(90deg, #22c55e, #16a34a);
    color:#0f172a;
    font-weight:bold;
  }
  button:hover { background:#15803d; cursor:pointer; box-shadow:0 0 12px #22c55e; }
  .solved {
    background: rgba(34,197,94,0.3) !important;
    border-color: rgba(34,197,94,0.7) !important;
  }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl font-bold text-green-400 mb-6 glow-text">Challenges</h1>

  <?php if($message): ?>
    <div class="mb-4 p-3 bg-green-900/40 border border-green-500 rounded text-green-300 font-semibold animate-pulse">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <?php foreach($categories as $cat): ?>
    <h2 class="text-2xl font-bold text-green-300 mb-2 mt-6"><?= htmlspecialchars($cat['name']) ?></h2>
    <?php if (!empty($cat['description'])): ?>
      <p class="mb-4 text-green-200 italic"><?= htmlspecialchars($cat['description']) ?></p>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php if(!empty($challengesByCategory[$cat['id']])): ?>
        <?php foreach($challengesByCategory[$cat['id']] as $c): ?>
          <div class="challenge-card <?= in_array($c['id'], $solvedIds) ? 'solved' : '' ?>">
            <div class="flex justify-between items-center mb-2">
              <h3 class="text-xl text-green-300 font-bold"><?= htmlspecialchars($c['title']) ?></h3>
              <span class="text-green-400 font-bold"><?= $c['points'] ?> pts</span>
            </div>

            <?php if(!empty($c['tags'])): ?>
              <div class="mb-2">
                <?php foreach(explode(',', $c['tags']) as $tag) echo '<span class="tag">'.htmlspecialchars(trim($tag)).'</span>'; ?>
              </div>
            <?php endif; ?>

            <!-- File -->
            <?php 
            if (!empty($c['file_path'])) {
                $fullPath = __DIR__ . '/../' . $c['file_path']; 
                if (file_exists($fullPath)) { ?>
                    <p class="mb-2">
                        <a href="../<?= htmlspecialchars($c['file_path']) ?>" download class="text-green-400 hover:underline">📄 Download file</a>
                    </p>
            <?php   } else { ?>
                    <p class="mb-2 text-red-400">File not available</p>
            <?php   }
            } ?>

      <!-- Description -->
      <?php if(!empty($c['description'])): ?>
        <p class="text-sm text-gray-300 mb-3"><?= nl2br(htmlspecialchars($c['description'])) ?></p>
      <?php endif; ?>

            <!-- Link -->
            <?php if (!empty($c['link'])): ?>
                <p class="mb-2">
                    <a href="<?= htmlspecialchars($c['link']) ?>" target="_blank" class="text-green-400 hover:underline">🔗 Open challenge link</a>
                </p>
            <?php endif; ?>

            <?php if(!in_array($c['id'], $solvedIds)): ?>
              <form method="POST">
                <input type="hidden" name="challenge_id" value="<?= $c['id'] ?>">
                <input type="text" name="flag" placeholder="Enter flag here" required class="mb-2">
                <button type="submit">Submit Flag</button>
              </form>
            <?php else: ?>
              <div class="text-green-400 font-bold">✅ Completed</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p class="text-green-300 mb-4">No challenges in this category yet.</p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

</div>
</body>
</html>
