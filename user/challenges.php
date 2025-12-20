<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

// Log visit
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

            $pdo->prepare(
                "INSERT INTO solves (user_id, challenge_id, solved_at) VALUES (?, ?, NOW())"
            )->execute([$_SESSION['user_id'], $challenge_id]);

            $message = "✅ Correct! You earned {$challenge['points']} points.";
            log_activity($pdo, $_SESSION['user_id'], "Solved challenge: {$challenge['title']}");
        } elseif ($alreadySolved) {
            $message = "⚠️ You already solved this challenge.";
        } else {
            $message = "❌ Incorrect flag. Try again!";
            log_activity($pdo, $_SESSION['user_id'], "Failed attempt: {$challenge['title']}");
        }
    }
}

// Fetch categories
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

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

// Base project path
$basePath = realpath(__DIR__ . '/../');
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
}
.sidebar a {
    display:block; padding:12px;
    color:#cbd5e1;
    border-bottom:1px solid rgba(255,255,255,0.05);
}
.sidebar a:hover { background:rgba(34,197,94,0.2); color:#22c55e; }
.challenge-card {
    background: rgba(15,23,42,0.7);
    border:1px solid rgba(34,197,94,0.4);
    border-radius:14px;
    padding:20px;
    transition:.3s;
}
.challenge-card:hover {
    transform: translateY(-5px);
    box-shadow:0 0 25px rgba(34,197,94,0.4);
}
.tag {
    background: rgba(34,197,94,0.2);
    color:#22c55e;
    padding:2px 8px;
    border-radius:6px;
    font-size:.8rem;
    margin-right:4px;
}
.solved {
    background: rgba(34,197,94,0.3) !important;
}
</style>
</head>

<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar">
    <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
    <a href="dashboard.php">🏠 Dashboard</a>
    <a href="challenges.php">🛠 Challenges</a>
    <a href="leaderboard.php">🏆 Leaderboard</a>
    <a href="instructions.php">📖 Instructions</a>
    <a href="hints.php">💡 Hints</a>
    <a href="profile.php">👤 Profile</a>
    <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main -->
<div class="flex-1 p-6 overflow-auto">

<h1 class="text-3xl font-bold text-green-400 mb-6">Challenges</h1>

<?php if ($message): ?>
<div class="mb-4 p-3 bg-green-900/40 border border-green-500 rounded">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<?php foreach ($categories as $cat): ?>
<h2 class="text-2xl font-bold text-green-300 mt-6"><?= htmlspecialchars($cat['name']) ?></h2>
<?php if (!empty($cat['description'])): ?>
<p class="text-green-200 italic mb-4"><?= htmlspecialchars($cat['description']) ?></p>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

<?php foreach ($challengesByCategory[$cat['id']] ?? [] as $c): ?>
<div class="challenge-card <?= in_array($c['id'], $solvedIds) ? 'solved' : '' ?>">

<div class="flex justify-between mb-2">
    <h3 class="text-xl text-green-300 font-bold"><?= htmlspecialchars($c['title']) ?></h3>
    <span class="text-green-400 font-bold"><?= $c['points'] ?> pts</span>
</div>

<!-- TAGS / HASHTAGS -->
<?php if (!empty($c['tags'])): ?>
<div class="mb-2">
<?php foreach (explode(',', $c['tags']) as $tag): ?>
<span class="tag">#<?= htmlspecialchars(trim($tag)) ?></span>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FILE DOWNLOAD (FIXED ONLY) -->
<?php
if (!empty($c['file_path'])) {
    $fileReal = realpath($basePath . '/' . $c['file_path']);
    if ($fileReal && str_starts_with($fileReal, $basePath) && file_exists($fileReal)) {
        echo '<a href="../' . htmlspecialchars($c['file_path']) . '" download
              class="text-green-400 hover:underline mb-2 block">📄 Download file</a>';
    } else {
        echo '<p class="text-red-400 mb-2">File not available</p>';
    }
}
?>

<!-- DESCRIPTION -->
<?php if (!empty($c['description'])): ?>
<p class="text-sm text-gray-300 mb-3"><?= nl2br(htmlspecialchars($c['description'])) ?></p>
<?php endif; ?>

<!-- LINK -->
<?php if (!empty($c['link'])): ?>
<a href="<?= htmlspecialchars($c['link']) ?>" target="_blank"
   class="text-green-400 hover:underline mb-2 block">🔗 Open challenge link</a>
<?php endif; ?>

<?php if (!in_array($c['id'], $solvedIds)): ?>
<form method="POST">
    <input type="hidden" name="challenge_id" value="<?= $c['id'] ?>">
    <input type="text" name="flag" placeholder="Enter flag here" required class="mb-2 w-full p-2 bg-black border border-green-500">
    <button class="w-full bg-green-500 text-black font-bold p-2">Submit Flag</button>
</form>
<?php else: ?>
<div class="text-green-400 font-bold">✅ Completed</div>
<?php endif; ?>

</div>
<?php endforeach; ?>

</div>
<?php endforeach; ?>

</div>
</body>
</html>
