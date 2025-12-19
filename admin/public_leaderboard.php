<?php
require_once __DIR__ . "/../includes/db.php";

// Fetch leaderboard
$stmt = $pdo->query("
    SELECT u.id, u.username, u.score, COUNT(s.challenge_id) AS solved_count
    FROM users u
    LEFT JOIN solves s ON u.id = s.user_id
    WHERE u.role != 'admin'
    GROUP BY u.id
    ORDER BY u.score DESC, solved_count DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Online users
$onlineStmt = $pdo->prepare("
    SELECT DISTINCT user_id 
    FROM user_activity 
    WHERE created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
");
$onlineStmt->execute();
$onlineUsers = $onlineStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>APIIT CTF | Cyber Arena</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>

<style>
/* ===== ARENA BACKGROUND ===== */
body {
    margin: 0;
    background: radial-gradient(circle at top, #0a1220, #020409 60%);
    font-family: 'Inter', 'Source Code Pro', monospace;
    color: #eafaf3;
    overflow: hidden;
}

/* ===== 3D STAGE ===== */
.stage {
    height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    perspective: 1200px;
}

/* ===== HOLOGRAM PANEL ===== */
.holo-panel {
    width: 92%;
    height: 85%;
    background: linear-gradient(
        180deg,
        rgba(15,30,55,0.85),
        rgba(6,14,28,0.92)
    );
    border-radius: 26px;
    transform-style: preserve-3d;
    transform: rotateX(8deg);
    box-shadow:
        0 40px 120px rgba(0,0,0,0.9),
        inset 0 0 0 1px rgba(45,226,138,0.25);
    backdrop-filter: blur(14px);
    display: flex;
    flex-direction: column;
}

/* ===== HEADER ===== */
.header {
    padding: 30px;
    text-align: center;
    transform: translateZ(40px);
}

.header h1 {
    font-size: 3.5rem;
    font-weight: 800;
    color: #2de28a;
    letter-spacing: 3px;
    text-shadow: 0 0 20px rgba(45,226,138,0.4);
}

/* ===== TABLE ===== */
.table-wrap {
    flex: 1;
    padding: 20px 60px 40px;
    transform: translateZ(20px);
}

table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0 14px;
    font-size: 1.35rem;
}

thead th {
    color: #9fe7c6;
    font-size: 1rem;
    letter-spacing: 1px;
    padding-bottom: 10px;
}

/* ===== ROW CARDS ===== */
tbody tr {
    background: linear-gradient(
        180deg,
        rgba(18,40,70,0.9),
        rgba(10,22,40,0.9)
    );
    border-radius: 14px;
    transform-style: preserve-3d;
    box-shadow:
        0 10px 30px rgba(0,0,0,0.6),
        inset 0 0 0 1px rgba(255,255,255,0.05);
    transition: transform 0.4s ease;
}

tbody tr:hover {
    transform: translateZ(20px) scale(1.01);
}

/* ===== CELLS ===== */
td {
    padding: 18px 22px;
}

/* ===== RANK DEPTH ===== */
.rank-1 { color: gold; transform: translateZ(35px); font-size: 1.6rem; }
.rank-2 { color: silver; transform: translateZ(25px); }
.rank-3 { color: #cd7f32; transform: translateZ(18px); }

/* ===== SCORE ENERGY ===== */
.score {
    color: #2de28a;
    font-weight: 800;
    position: relative;
}

.score::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: -6px;
    width: 100%;
    height: 2px;
    background: linear-gradient(90deg, transparent, #2de28a, transparent);
    animation: pulse 2.5s ease-in-out infinite;
}

@keyframes pulse {
    0%,100% { opacity: 0.3; }
    50% { opacity: 1; }
}

/* ===== ONLINE DOT ===== */
.online {
    color: #2de28a;
}

.online::before {
    content: '';
    width: 9px;
    height: 9px;
    background: #2de28a;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
    box-shadow: 0 0 12px #2de28a;
    animation: breathe 2.5s infinite;
}

@keyframes breathe {
    0%,100% { opacity: 0.5; }
    50% { opacity: 1; }
}

/* ===== FOOTER ===== */
.footer {
    text-align: center;
    padding: 14px;
    font-size: 0.95rem;
    color: #7ccfb0;
    transform: translateZ(10px);
}
</style>
</head>

<body>

<div class="stage">
    <div class="holo-panel" id="panel">

        <div class="header">
            <h1>APIIT CTF — CYBER ARENA</h1>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Operator</th>
                        <th>Score</th>
                        <th>Solved</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php $rank = 1; foreach ($users as $u):
                    $online = in_array($u['id'], $onlineUsers);
                    $rClass = $rank <= 3 ? "rank-$rank" : "";
                ?>
                    <tr>
                        <td class="<?= $rClass ?>">#<?= $rank ?></td>
                        <td class="font-semibold"><?= htmlspecialchars($u['username']) ?></td>
                        <td class="score"><?= $u['score'] ?></td>
                        <td><?= $u['solved_count'] ?></td>
                        <td class="<?= $online ? 'online' : 'text-gray-500' ?>">
                            <?= $online ? 'Online' : 'Offline' ?>
                        </td>
                    </tr>
                <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            Live Cyber Arena • Auto Refresh
        </div>

    </div>
</div>

<script>
// Soft parallax motion (calm)
const panel = document.getElementById('panel');
let angle = 0;

setInterval(() => {
    angle += 0.02;
    panel.style.transform =
        `rotateX(${8 + Math.sin(angle)*1.5}deg) rotateY(${Math.cos(angle)*1.5}deg)`;
}, 50);

// Refresh data calmly
setTimeout(() => location.reload(), 15000);
</script>

</body>
</html>
