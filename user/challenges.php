<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

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
            $pdo->prepare("UPDATE users SET score = score + ? WHERE id = ?")
                ->execute([$challenge['points'], $_SESSION['user_id']]);

            $pdo->prepare("INSERT INTO solves (user_id, challenge_id, solved_at) VALUES (?, ?, NOW())")
                ->execute([$_SESSION['user_id'], $challenge_id]);

            $message = "✅ Correct! You earned {$challenge['points']} points.";
            log_activity($pdo, $_SESSION['user_id'], "Solved challenge: {$challenge['title']}");
        } elseif ($alreadySolved) {
            $message = "⚠️ You already solved this challenge.";
        } else {
            $message = "❌ Incorrect flag.";
        }
    }
}

// Data
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$solvedStmt = $pdo->prepare("SELECT challenge_id FROM solves WHERE user_id = ?");
$solvedStmt->execute([$_SESSION['user_id']]);
$solvedIds = array_column($solvedStmt->fetchAll(PDO::FETCH_ASSOC), 'challenge_id');

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
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600&family=Share+Tech+Mono&display=swap');

body{
  font-family:'Share Tech Mono', monospace;
  background:
    radial-gradient(900px 500px at 20% 0%, rgba(56,189,248,.18), transparent 60%),
    radial-gradient(900px 500px at 80% 10%, rgba(0,209,184,.16), transparent 60%),
    linear-gradient(180deg,#031b34,#020617 60%,#010b18);
  color:#e2e8f0;
}

.sidebar{
  background:rgba(2,10,22,.95);
  border-right:1px solid rgba(63,255,224,.35);
}

.sidebar h2{
  font-family:'Cinzel', serif;
  letter-spacing:.1em;
  text-shadow:0 0 15px rgba(63,255,224,.3);
}

.sidebar a{
  display:block;
  padding:12px;
  border-bottom:1px solid rgba(255,255,255,.05);
  color:#cbd5e1;
}
.sidebar a:hover{
  background:rgba(63,255,224,.08);
  color:#3fffe0;
}

.glow-text{
  font-family:'Cinzel', serif;
  text-shadow:0 0 20px rgba(63,255,224,.3);
}

.challenge-card{
  background:rgba(3,23,42,.75);
  border:1px solid rgba(63,255,224,.35);
  border-radius:16px;
  padding:20px;
  transition:.3s;
}
.challenge-card:hover{
  transform:translateY(-6px);
  box-shadow:0 0 30px rgba(63,255,224,.25);
}

.tag{
  background:rgba(63,255,224,.15);
  color:#3fffe0;
  padding:3px 10px;
  border-radius:999px;
  font-size:.75rem;
  margin-right:6px;
}

input,button{
  background:rgba(255,255,255,.06);
  border:1px solid rgba(63,255,224,.3);
  padding:10px;
  border-radius:10px;
  width:100%;
}

button{
  background:linear-gradient(90deg,#3fffe0,#00d1b8);
  color:#00131f;
  font-weight:800;
}

.solved{
  background:rgba(0,209,184,.18)!important;
  border-color:rgba(243,211,107,.45)!important;
}
</style>
</head>

<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-xl font-bold p-4 border-b">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main -->
<div class="flex-1 p-6 overflow-auto">
  <h1 class="text-3xl mb-6 glow-text">Lost City Challenges</h1>

  <?php if($message): ?>
    <div class="mb-4 p-3 border rounded"><?= htmlspecialchars($message) ?></div>
  <?php endif; ?>

  <?php foreach($categories as $cat): ?>
    <h2 class="text-2xl mt-6 mb-2"><?= htmlspecialchars($cat['name']) ?></h2>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach($challengesByCategory[$cat['id']] as $c): ?>
      <div class="challenge-card <?= in_array($c['id'],$solvedIds)?'solved':'' ?>">
        <div class="flex justify-between mb-2">
          <h3><?= htmlspecialchars($c['title']) ?></h3>
          <span><?= $c['points'] ?> pts</span>
        </div>

        <?php if($c['tags']): foreach(explode(',',$c['tags']) as $t): ?>
          <span class="tag"><?= htmlspecialchars(trim($t)) ?></span>
        <?php endforeach; endif; ?>

        <p class="text-sm mt-3"><?= nl2br(htmlspecialchars($c['description'])) ?></p>

        <?php if(!in_array($c['id'],$solvedIds)): ?>
        <form method="POST" class="mt-3">
          <input type="hidden" name="challenge_id" value="<?= $c['id'] ?>">
          <input name="flag" placeholder="Enter flag" required class="mb-2">
          <button type="submit">Submit Flag</button>
        </form>
        <?php else: ?>
          <div class="mt-3 font-bold text-green-300">✅ Completed</div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>

</body>
</html>
