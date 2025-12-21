<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

/**
 * CTF END SWITCH
 * Set to true to close all challenges on this page.
 * Later you can replace this with a DB setting or env var.
 */
$ctfEnded = true;

if ($ctfEnded) {
    // Optional: log that user tried to access challenges after end
    log_activity($pdo, $_SESSION['user_id'], "Visited Challenges (CTF ended)", $_SERVER['REQUEST_URI']);
    ?>
    <!doctype html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>CTF Ended — APIIT CTF</title>
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
        <div class="flex-1 p-6 overflow-auto flex items-center justify-center">
            <div class="max-w-2xl w-full bg-green-900/20 border border-green-500 rounded-2xl p-8 shadow-xl">
                <h1 class="text-3xl md:text-4xl font-bold text-green-400 mb-4">
                    🛑 CTF Closed
                </h1>
                <p class="text-lg text-green-200 mb-4">
                    Good luck, hacker — well played.
                </p>
                <p class="text-green-200/90 mb-6">
                    But the CTF is over now. Challenges are locked and flag submissions are disabled.
                </p>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="leaderboard.php" class="bg-green-500 text-black font-bold px-4 py-2 rounded text-center">
                        View Leaderboard
                    </a>
                    <a href="dashboard.php" class="border border-green-500 text-green-300 font-bold px-4 py-2 rounded text-center">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

    </body>
    </html>
    <?php
    exit;
}

// (your existing code continues below ONLY if $ctfEnded is false)
