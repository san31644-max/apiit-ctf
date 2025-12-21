<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

/* 🔒 TOGGLE THIS */
$leaderboardLocked = true;

/* Fetch real data ONLY if unlocked */
$users = [];
if (!$leaderboardLocked) {
    $stmt = $pdo->query("SELECT username, score FROM users WHERE role='user' ORDER BY score DESC LIMIT 10");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Leaderboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>

<style>
body {
    background: radial-gradient(circle at top, #0f172a, #020617);
    font-family: 'Share Tech Mono', monospace;
    color:#e2e8f0;
}

/* Cards */
.user-card {
    backdrop-filter: blur(12px);
    background: rgba(15,23,42,0.7);
    border:1px solid rgba(34,197,94,0.4);
    border-radius:1rem;
    padding:2rem;
    text-align:center;
    box-shadow:0 0 20px rgba(34,197,94,0.2);
    position:relative;
}

/* 🔒 Locked state */
.locked .user-card {
    filter: blur(12px);
    pointer-events: none;
}

/* Overlay */
.lock-overlay {
    position:absolute;
    inset:0;
    background:rgba(2,6,23,0.85);
    display:flex;
    align-items:center;
    justify-content:center;
    text-align:center;
    z-index:50;
}

.lock-box {
    border:1px solid rgba(34,197,94,0.4);
    padding:2rem 3rem;
    border-radius:1rem;
    background:rgba(15,23,42,0.9);
    box-shadow:0 0 40px rgba(34,197,94,0.3);
}
</style>
</head>

<body class="p-8 relative">

<h1 class="text-4xl font-bold text-green-400 mb-8">🏆 Leaderboard</h1>

<div class="relative <?= $leaderboardLocked ? 'locked' : '' ?>">

    <!-- Dummy cards (always same layout) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php for ($i=0; $i<9; $i++): ?>
            <div class="user-card">
                <h3 class="text-xl">████████</h3>
                <p class="text-2xl">████ pts</p>
            </div>
        <?php endfor; ?>
    </div>

    <!-- 🔒 Overlay -->
    <?php if ($leaderboardLocked): ?>
    <div class="lock-overlay">
        <div class="lock-box">
            <h2 class="text-2xl text-green-400 mb-2">🔒 Leaderboard Locked</h2>
            <p class="text-green-300">
                Rankings will be revealed<br>
                after the competition ends.
            </p>
        </div>
    </div>
    <?php endif; ?>

</div>

</body>
</html>
