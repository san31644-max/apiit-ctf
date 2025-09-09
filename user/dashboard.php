<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, $_SESSION['user_id'], "Visited Dashboard", $_SERVER['REQUEST_URI']);

// ---------- Dynamic stats: best-effort detection ----------
$userId = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Player';

// Defaults / fallbacks
$solvedCount = 0;
$score = null; // keep null if unknown
$rank = '—';
$statsSource = []; // capture what method we used (for debugging)

function tableExists($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl");
        $stmt->execute(['tbl' => $table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        try {
            $pdo->query("SELECT 1 FROM `$table` LIMIT 1");
            return true;
        } catch (Exception $e2) {
            return false;
        }
    }
}

function getTableColumns($pdo, $table) {
    try {
        $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl");
        $stmt->execute(['tbl' => $table]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Exception $e) {
        try {
            $res = $pdo->query("SELECT * FROM `$table` LIMIT 1");
            $cols = [];
            if ($res) {
                for ($i = 0; $i < $res->columnCount(); $i++) {
                    $meta = $res->getColumnMeta($i);
                    $cols[] = $meta['name'] ?? null;
                }
            }
            return array_filter($cols);
        } catch (Exception $e2) {
            return [];
        }
    }
}

$candidateSolveTables = ['solves','user_solved','submissions','solve','solved'];
$candidateUserCols = ['user_id','user','uid','userid'];
$candidateChallengeTables = ['challenges','challenge','ctf_challenges','problems','tasks'];
$possiblePointCols = ['points','score','value','pt'];

// 1) Try to read users.score if present
try {
    if (tableExists($pdo, 'users')) {
        $userCols = getTableColumns($pdo, 'users');
        if (in_array('score', $userCols) || in_array('points', $userCols) || in_array('total_score', $userCols)) {
            $scoreCol = in_array('score', $userCols) ? 'score' : (in_array('points', $userCols) ? 'points' : 'total_score');
            $stmt = $pdo->prepare("SELECT `$scoreCol` FROM `users` WHERE `id` = :uid LIMIT 1");
            $stmt->execute(['uid' => $userId]);
            $s = $stmt->fetchColumn();
            if ($s !== false && $s !== null) {
                $score = (int)$s;
                $statsSource[] = "users.$scoreCol";
                $rstmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE COALESCE(`$scoreCol`,0) > :myscore");
                $rstmt->execute(['myscore' => $score]);
                $higher = (int)$rstmt->fetchColumn();
                $rank = $higher + 1;
            }
        }
    }
} catch (Exception $e) {}

// 2) Compute score from solves->challenges if needed
if ($score === null) {
    $foundSolveTable = null;
    foreach ($candidateSolveTables as $tbl) { if (tableExists($pdo, $tbl)) { $foundSolveTable = $tbl; break; } }
    $foundChallengeTable = null;
    foreach ($candidateChallengeTables as $tbl) { if (tableExists($pdo, $tbl)) { $foundChallengeTable = $tbl; break; } }

    if ($foundSolveTable && $foundChallengeTable) {
        $solveCols = getTableColumns($pdo, $foundSolveTable);
        $challengeCols = getTableColumns($pdo, $foundChallengeTable);

        $userCol = null;
        foreach ($candidateUserCols as $c) { if (in_array($c, $solveCols)) { $userCol = $c; break; } }
        $chalIdColInSolve = null;
        foreach (['challenge_id','chal_id','challenge','challengeid','cid','problem_id'] as $c) { if (in_array($c, $solveCols)) { $chalIdColInSolve = $c; break; } }
        $chalIdCol = null;
        foreach (['id','challenge_id','chal_id','challengeid','cid'] as $c) { if (in_array($c, $challengeCols)) { $chalIdCol = $c; break; } }
        $pointCol = null;
        foreach ($possiblePointCols as $p) { if (in_array($p, $challengeCols)) { $pointCol = $p; break; } }

        if ($userCol && $chalIdColInSolve && $chalIdCol && $pointCol) {
            try {
                $sql = "SELECT COALESCE(SUM(ch.`$pointCol`),0) FROM `$foundSolveTable` s
                        JOIN `$foundChallengeTable` ch ON s.`$chalIdColInSolve` = ch.`$chalIdCol`
                        WHERE s.`$userCol` = :uid";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['uid' => $userId]);
                $sum = $stmt->fetchColumn();
                $score = (int)$sum;
                $statsSource[] = "sum($foundSolveTable->$foundChallengeTable.$pointCol)";
                $rankSql = "SELECT COUNT(*) FROM (
                                SELECT s.`$userCol` as uid, COALESCE(SUM(ch.`$pointCol`),0) as total
                                FROM `$foundSolveTable` s
                                JOIN `$foundChallengeTable` ch ON s.`$chalIdColInSolve` = ch.`$chalIdCol`
                                GROUP BY s.`$userCol`
                            ) t WHERE t.total > :myscore";
                $rstmt = $pdo->prepare($rankSql);
                $rstmt->execute(['myscore' => $score]);
                $higher = (int)$rstmt->fetchColumn();
                $rank = $higher + 1;
            } catch (Exception $e) {}
        }
    }
}

if ($score === null) { $score = 0; $statsSource[] = 'fallback:0'; }

// Compute solved count
try {
    $foundSolveTable = null;
    foreach ($candidateSolveTables as $tbl) { if (tableExists($pdo, $tbl)) { $foundSolveTable = $tbl; break; } }
    if ($foundSolveTable) {
        $solveCols = getTableColumns($pdo, $foundSolveTable);
        $userCol = null;
        foreach ($candidateUserCols as $c) { if (in_array($c, $solveCols)) { $userCol = $c; break; } }
        $chalIdColInSolve = null;
        foreach (['challenge_id','chal_id','challenge','challengeid','cid','problem_id'] as $c) { if (in_array($c, $solveCols)) { $chalIdColInSolve = $c; break; } }
        if ($userCol && $chalIdColInSolve) {
            $sql = "SELECT COUNT(DISTINCT `{$chalIdColInSolve}`) FROM `{$foundSolveTable}` WHERE `{$userCol}` = :uid";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['uid' => $userId]);
            $solvedCount = (int)$stmt->fetchColumn();
            $statsSource[] = "count distinct ($foundSolveTable.$chalIdColInSolve)";
        }
    }
} catch (Exception $e) {}

if ($solvedCount === 0 && $score > 0) { $solvedCount = '≈' . ceil($score / max(1,100)); $statsSource[] = 'estimated-from-score'; }

$lastUpdated = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; overflow-x:hidden; }
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); height:100vh; }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.3s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }
.card { background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3); border-radius:14px; padding:20px; box-shadow:0 0 10px rgba(0,0,0,0.4); transition: transform 0.3s, box-shadow 0.3s; }
.card:hover { transform: translateY(-4px); box-shadow:0 0 25px rgba(45,226,138,0.4); }
.section-title { color:#2de28a; font-size:1.2rem; font-weight:bold; margin-bottom:0.75rem; }
.meta { font-size:0.8rem; color:#9becc2; opacity:0.9; }
</style>
</head>
<body class="h-screen flex">

<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<div class="flex-1 p-6 overflow-auto space-y-6">
  
  <div class="card">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-green-400">Welcome, <?= htmlspecialchars($username) ?> 🎉</h1>
        <p class="mt-1 meta">Last updated: <?= htmlspecialchars($lastUpdated) ?></p>
      </div>
      <div class="text-right">
       <p class="meta">Data source: Score & solved challenges</p>

      </div>
    </div>
  </div>

  <div class="card">
    <h2 class="section-title">📊 Quick Stats</h2>
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="p-4 text-center">
        <div class="text-3xl font-bold text-green-300"><?= htmlspecialchars((string)$solvedCount) ?></div>
        <div class="meta">Challenges Solved</div>
      </div>
      <div class="p-4 text-center">
        <div class="text-3xl font-bold text-green-300"><?= htmlspecialchars((string)$rank) ?></div>
        <div class="meta">Current Rank</div>
      </div>
      <div class="p-4 text-center">
        <div class="text-3xl font-bold text-green-300"><?= htmlspecialchars((string)$score) ?> pts</div>
        <div class="meta">Score</div>
      </div>
    </div>
  </div>

  <!-- Challenge Categories -->
  <div>
    <h2 class="section-title">🕹️ Challenge Categories</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <div class="card"><h3 class="font-bold text-green-400">🌐 Web</h3><p class="mt-2 text-sm">XSS, SQLi, SSRF…</p></div>
      <div class="card"><h3 class="font-bold text-green-400">🔐 Crypto</h3><p class="mt-2 text-sm">Ciphers & crypto puzzles.</p></div>
      <div class="card"><h3 class="font-bold text-green-400">🕵️ Forensics</h3><p class="mt-2 text-sm">PCAP, stego, images.</p></div>
      <div class="card"><h3 class="font-bold text-green-400">⚙️ Reversing</h3><p class="mt-2 text-sm">Binaries & obstacles.</p></div>
      <div class="card"><h3 class="font-bold text-green-400">💣 Pwn</h3><p class="mt-2 text-sm">Exploits & memory bugs.</p></div>
      <div class="card"><h3 class="font-bold text-green-400">🧩 Misc</h3><p class="mt-2 text-sm">Fun or mixed challenges.</p></div>
    </div>
  </div>

  <!-- Educational Materials Cards -->
  <div>
    <h2 class="section-title">📚 Educational Resources & Tips</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
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
  </div>

</div>

</body>
</html>
