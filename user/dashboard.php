<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

// Security: redirect if not logged in as user
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Log visit
log_activity($pdo, $_SESSION['user_id'], "Visited Dashboard", $_SERVER['REQUEST_URI']);

$message = '';

// Handle flag submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['challenge_id'], $_POST['flag'])) {
    $challenge_id = (int)$_POST['challenge_id'];
    $submitted_flag = trim($_POST['flag']);

    $stmt = $pdo->prepare("SELECT * FROM challenges WHERE id = ?");
    $stmt->execute([$challenge_id]);
    $challenge = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($challenge) {
        // Check if already solved
        $stmtCheck = $pdo->prepare("SELECT 1 FROM solves WHERE user_id = ? AND challenge_id = ?");
        $stmtCheck->execute([$_SESSION['user_id'], $challenge_id]);
        $alreadySolved = $stmtCheck->fetch();

        if ($submitted_flag === $challenge['flag'] && !$alreadySolved) {
            // Update user score
            $stmtUpdate = $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?");
            $stmtUpdate->execute([$challenge['points'], $_SESSION['user_id']]);

            // Insert solve record
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

// Fetch challenges + solved status
$stmt = $pdo->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM solves s WHERE s.challenge_id = c.id AND s.user_id = ?) AS solved
    FROM challenges c
    ORDER BY points DESC
");
$stmt->execute([$_SESSION['user_id']]);
$challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>User Dashboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.2s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.challenge-card { background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3); border-radius:12px; padding:20px; transition: transform 0.2s, box-shadow 0.2s; }
.challenge-card:hover { transform: translateY(-4px); box-shadow: 0 0 18px rgba(45,226,138,0.5); }
.tag { display:inline-block; background: rgba(45,226,138,0.15); color:#2de28a; padding:2px 8px; margin:0 4px 4px 0; border-radius:6px; font-size:0.85rem; }
input, button { border:1px solid rgba(45,226,138,0.3); background: rgba(255,255,255,0.05); color:#c9f7e4; padding:8px; border-radius:6px; width:100%; }
input:focus { outline:none; border-color:#2de28a; }
button { background:#2de28a; color:#000; font-weight:bold; transition: background 0.2s; }
button:hover { background:#1ab66b; cursor:pointer; }
.solved { border-color: rgba(45,226,138,0.7) !important; background: rgba(45,226,138,0.15) !important; }
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

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl font-bold text-green-400 mb-6">Challenges</h1>

  <!-- Flash message -->
  <?php if($message): ?>
    <div class="mb-4 p-3 bg-green-900/30 border border-green-500 rounded text-green-300 font-semibold">
      <?= htmlspecialchars($message) ?>
    </div>
  <?php endif; ?>

  <!-- Challenge grid -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($challenges as $c): ?>
    <div class="challenge-card <?= $c['solved'] ? 'solved' : '' ?>">
      <div class="flex justify-between items-center mb-2">
        <h2 class="text-xl text-green-300 font-bold"><?= htmlspecialchars($c['title']) ?></h2>
        <span class="text-green-400 font-bold"><?= $c['points'] ?> pts</span>
      </div>

      <!-- Tags -->
      <?php if(!empty($c['tags'])): ?>
        <div class="mb-2">
          <?php foreach(explode(',', $c['tags']) as $tag): ?>
            <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- File link -->
      <?php if($c['file_path']): ?>
        <p class="mb-2">
          <a href="../uploads/challenges/<?= htmlspecialchars($c['file_path']) ?>" download class="text-green-400 hover:underline">📄 Download file</a>
        </p>
      <?php endif; ?>

      <!-- External link -->
      <?php if($c['link']): ?>
        <p class="mb-4">
          <a href="<?= htmlspecialchars($c['link']) ?>" target="_blank" class="text-green-400 hover:underline">🔗 Open link</a>
        </p>
      <?php endif; ?>

      <!-- Description -->
      <?php if(!empty($c['description'])): ?>
        <p class="text-sm text-gray-300 mb-3"><?= nl2br(htmlspecialchars($c['description'])) ?></p>
      <?php endif; ?>

      <!-- Flag form -->
      <?php if(!$c['solved']): ?>
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
  </div>
</div>

</body>
</html>
