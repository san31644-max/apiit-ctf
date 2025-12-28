<?php
session_start();
require_once __DIR__ . "/../includes/db.php";

/*
  Atlantis Admin Dashboard (theme-matched)
  Adds: Team Registrations (4-member armies) + payment/receipt view
*/

// Only admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header("Location: ../index.php");
  exit;
}

/* ---------------- Existing CTF stats ---------------- */
$total_users = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role != 'admin'")->fetchColumn();
$total_challenges_solved = (int)$pdo->query("SELECT COUNT(*) FROM solves")->fetchColumn();

$active_users = (int)$pdo->query("
  SELECT COUNT(DISTINCT user_id)
  FROM login_logs
  WHERE login_time > (NOW() - INTERVAL 5 MINUTE)
")->fetchColumn();

/* Chart */
$submissions_per_hour = $pdo->query("
  SELECT HOUR(submission_time) as hr, COUNT(*) as cnt
  FROM challenge_logs
  GROUP BY HOUR(submission_time)
")->fetchAll(PDO::FETCH_ASSOC);

$chart_labels = [];
$chart_data = [];
foreach ($submissions_per_hour as $row) {
  $chart_labels[] = $row['hr'];
  $chart_data[] = $row['cnt'];
}

/* Recent submissions */
$logs = $pdo->query("
  SELECT cl.*, u.username, ch.title AS challenge_title
  FROM challenge_logs cl
  JOIN users u ON cl.user_id = u.id
  JOIN challenges ch ON cl.challenge_id = ch.id
  ORDER BY cl.submission_time DESC
  LIMIT 50
")->fetchAll(PDO::FETCH_ASSOC);

/* User activity */
$users = $pdo->query("
  SELECT u.id, u.username, u.score, l.last_login, l.ip_address
  FROM users u
  LEFT JOIN (
      SELECT user_id, ip_address, login_time AS last_login
      FROM login_logs
      WHERE (user_id, login_time) IN (
          SELECT user_id, MAX(login_time)
          FROM login_logs
          GROUP BY user_id
      )
  ) l ON u.id = l.user_id
  WHERE u.role != 'admin'
  ORDER BY u.score DESC
")->fetchAll(PDO::FETCH_ASSOC);


/* ---------------- NEW: Team registration stats ---------------- */
$teams_total = 0; $teams_pending = 0; $teams_verified = 0; $teams_rejected = 0;
$teamRegs = [];
$membersByTeam = [];

try {
  $teams_total = (int)$pdo->query("SELECT COUNT(*) FROM ctf_teams")->fetchColumn();
  $teams_pending = (int)$pdo->query("SELECT COUNT(*) FROM ctf_payments WHERE status='pending'")->fetchColumn();
  $teams_verified = (int)$pdo->query("SELECT COUNT(*) FROM ctf_payments WHERE status='verified'")->fetchColumn();
  $teams_rejected = (int)$pdo->query("SELECT COUNT(*) FROM ctf_payments WHERE status='rejected'")->fetchColumn();

  $teamRegs = $pdo->query("
    SELECT
      t.id AS team_id,
      t.university_name,
      t.team_name,
      t.leader_name,
      t.leader_email,
      t.leader_phone,
      t.contact_name,
      t.contact_email,
      t.contact_phone,
      t.created_at,
      p.status AS pay_status,
      p.amount AS pay_amount,
      p.currency AS pay_currency,
      p.receipt_file,
      (SELECT COUNT(*) FROM ctf_team_members m WHERE m.team_id = t.id) AS member_count
    FROM ctf_teams t
    LEFT JOIN ctf_payments p ON p.team_id = t.id
    ORDER BY t.created_at DESC
    LIMIT 25
  ")->fetchAll(PDO::FETCH_ASSOC);

  if (!empty($teamRegs)) {
    $ids = array_map(fn($r) => (int)$r['team_id'], $teamRegs);
    $in = implode(',', $ids);
    $members = $pdo->query("
      SELECT team_id, member_no, member_name, member_email, member_phone
      FROM ctf_team_members
      WHERE team_id IN ($in)
      ORDER BY team_id, member_no
    ")->fetchAll(PDO::FETCH_ASSOC);

    foreach ($members as $m) {
      $membersByTeam[(int)$m['team_id']][] = $m;
    }
  }
} catch (Exception $e) {
  // if tables not present yet, dashboard still loads
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Atlantis Admin — CTF Control Room</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --aqua2:#22d3ee;
  --gold:#f5d27b;
  --ink:#000f1d;
}

html,body{height:100%;}
body{
  font-family:'Cinzel',serif;
  background:#000;
  color:#e6faff;
  overflow-x:hidden;
}

/* ===== BACKGROUND VIDEO ===== */
.video-bg{position:fixed; inset:0; z-index:-6; overflow:hidden; background:#00101f;}
.video-bg video{width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.03);filter:saturate(1.05) contrast(1.05);}
.video-overlay{
  position:fixed; inset:0; z-index:-5; pointer-events:none;
  background:
    radial-gradient(900px 420px at 50% 10%, rgba(56,247,255,0.14), transparent 62%),
    linear-gradient(180deg, rgba(0,0,0,0.20), rgba(0,0,0,0.36));
}
.caustics{
  position:fixed; inset:0; z-index:-4; pointer-events:none;
  background:
    repeating-radial-gradient(circle at 30% 40%, rgba(56,247,255,.05) 0 2px, transparent 3px 14px),
    repeating-radial-gradient(circle at 70% 60%, rgba(255,255,255,.03) 0 1px, transparent 2px 18px);
  opacity:.28; mix-blend-mode:screen; animation: causticMove 7s linear infinite;
}
@keyframes causticMove{from{background-position:0 0,0 0;}to{background-position:0 220px,0 -180px;}}

/* ===== LAYOUT ===== */
.shell{height:100vh; display:flex;}
.sidebar{
  width:270px;
  background: rgba(0, 16, 28, 0.40);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  border-right: 1px solid rgba(56,247,255,0.16);
  box-shadow: 0 0 60px rgba(56,247,255,0.10);
}
.brand{
  padding:18px 16px;
  border-bottom: 1px solid rgba(56,247,255,0.16);
}
.brand .title{
  font-weight:900;
  letter-spacing:.18em;
  color: rgba(56,247,255,0.95);
  text-shadow: 0 0 18px rgba(56,247,255,0.30);
}
.brand .sub{
  margin-top:6px;
  font-family:'Share Tech Mono', monospace;
  font-size:12px;
  color: rgba(245,210,123,0.90);
  opacity:.9;
  letter-spacing:.10em;
}

.nav a{
  display:flex;
  align-items:center;
  gap:10px;
  padding:12px 14px;
  color: rgba(230,250,255,0.88);
  border-bottom: 1px solid rgba(255,255,255,0.04);
  transition:.22s;
  font-family:'Share Tech Mono', monospace;
  letter-spacing:.06em;
}
.nav a:hover{
  background: rgba(56,247,255,0.10);
  color: rgba(56,247,255,0.95);
}
.nav a.active{
  background: rgba(56,247,255,0.14);
  color: rgba(56,247,255,0.98);
  border-left: 3px solid rgba(245,210,123,0.9);
}

.main{
  flex:1;
  overflow:auto;
  padding:22px;
}

/* ===== GLASS PANELS ===== */
.panel{
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  background: rgba(0, 14, 24, 0.22);
  border: 1px solid rgba(56,247,255,0.18);
  box-shadow: 0 0 55px rgba(56,247,255,0.12), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 18px;
}

.h1{
  font-weight:900;
  letter-spacing:.14em;
  color: rgba(56,247,255,0.92);
  text-shadow: 0 0 18px rgba(56,247,255,0.22);
}
.mono{ font-family:'Share Tech Mono', monospace; }

/* ===== STAT CARDS ===== */
.stat{
  padding:16px;
  border-radius:16px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(255,255,255,0.04);
  box-shadow: 0 0 30px rgba(56,247,255,0.08);
}
.stat .k{color: rgba(230,250,255,0.75); font-family:'Share Tech Mono', monospace; letter-spacing:.08em;}
.stat .v{font-size:22px; font-weight:900; color: rgba(56,247,255,0.95);}
.stat .sub{font-size:12px; color: rgba(245,210,123,0.90); margin-top:6px; font-family:'Share Tech Mono', monospace;}

/* ===== TABLES ===== */
.table-wrap{overflow-x:auto;}
.table{
  width:100%;
  border-collapse: collapse;
  font-family:'Share Tech Mono', monospace;
}
.table th, .table td{
  border:1px solid rgba(56,247,255,0.18);
  padding:10px;
  vertical-align: top;
}
.table thead th{
  background: rgba(56,247,255,0.10);
  color: rgba(230,250,255,0.92);
  letter-spacing:.08em;
}
.table tbody tr:hover{
  background: rgba(56,247,255,0.06);
}

/* badges */
.badge{
  display:inline-flex;
  align-items:center;
  padding:2px 10px;
  border-radius:999px;
  font-weight:900;
  letter-spacing:.08em;
  font-size:12px;
}
.b-pending{background: rgba(245,158,11,.14); color:#fbbf24; border:1px solid rgba(245,158,11,.35);}
.b-verified{background: rgba(34,197,94,.12); color:#22c55e; border:1px solid rgba(34,197,94,.35);}
.b-rejected{background: rgba(239,68,68,.12); color:#ef4444; border:1px solid rgba(239,68,68,.35);}
.b-na{background: rgba(148,163,184,.10); color:#94a3b8; border:1px solid rgba(148,163,184,.22);}

.link{
  color: rgba(56,247,255,0.92);
  text-decoration: none;
}
.link:hover{ text-decoration: underline; }

details summary{ cursor:pointer; color: rgba(56,247,255,0.92); }
details summary:hover{ text-decoration: underline; }

.small{ font-size:12px; color: rgba(230,250,255,0.72); }

.status-correct { color:#34d399; font-weight:900; }
.status-incorrect { color:#fb7185; font-weight:900; }
</style>
</head>

<body>

<!-- Atlantis background -->
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="caustics"></div>

<div class="shell">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="brand">
      <div class="title">ATLANTIS CTF</div>
      <div class="sub">CONTROL ROOM • ADMIN</div>
    </div>

    <nav class="nav">
      <a class="active" href="dashboard.php">🏠 Dashboard</a>
      <a href="add_challenge.php">➕ Add Challenge</a>
      <a href="manage_challenges.php">📋 Manage Challenges</a>
      <a href="manage_users.php">👥 Manage Users</a>
      <a href="manage_hints.php">💡 Manage Hints</a>
      <a href="leaderboard.php">🏆 Leaderboard</a>
      <a class="text-red-200" href="../logout.php">🚪 Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main space-y-6">
    <div class="panel p-6">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <div class="h1 text-2xl md:text-3xl">ADMIN MONITORING</div>
          <div class="mono small mt-1">WATCH THE DEPTHS • TRACK USERS • VERIFY ARMIES</div>
        </div>
        <div class="mono small">
          <span class="badge b-verified">SYSTEM: ONLINE</span>
        </div>
      </div>

      <!-- STATS -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="stat">
          <div class="k">TOTAL USERS</div>
          <div class="v"><?= $total_users ?></div>
          <div class="sub">non-admin accounts</div>
        </div>
        <div class="stat">
          <div class="k">ACTIVE USERS</div>
          <div class="v"><?= $active_users ?></div>
          <div class="sub">last 5 minutes</div>
        </div>
        <div class="stat">
          <div class="k">SOLVES</div>
          <div class="v"><?= $total_challenges_solved ?></div>
          <div class="sub">total submissions solved</div>
        </div>
        <div class="stat">
          <div class="k">ARMIES REGISTERED</div>
          <div class="v"><?= $teams_total ?></div>
          <div class="sub">Pending <?= $teams_pending ?> • Verified <?= $teams_verified ?> • Rejected <?= $teams_rejected ?></div>
        </div>
      </div>
    </div>

    <!-- CHART -->
    <div class="panel p-6">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div class="h1 text-lg">SUBMISSIONS CURRENT</div>
        <div class="mono small">per hour (server time)</div>
      </div>
      <canvas id="submissionsChart" height="110"></canvas>
    </div>

    <script>
    const ctx = document.getElementById('submissionsChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: <?= json_encode($chart_labels) ?>,
        datasets: [{
          label: 'Submissions / Hour',
          data: <?= json_encode($chart_data) ?>,
          borderColor: '#38f7ff',
          backgroundColor: 'rgba(56,247,255,0.18)',
          fill: true,
          tension: 0.35
        }]
      },
      options: {
        plugins: { legend: { labels: { color: '#e6faff' } } },
        scales: {
          x: { ticks: { color:'#e6faff' }, title: { display:true, text:'Hour', color:'#e6faff' } },
          y: { ticks: { color:'#e6faff' }, beginAtZero:true, title: { display:true, text:'Submissions', color:'#e6faff' } }
        }
      }
    });
    </script>

    <!-- TEAM REGISTRATIONS (ATLANTIS THEME) -->
    <div class="panel p-6">
      <div class="flex items-center justify-between gap-3 mb-3">
        <div>
          <div class="h1 text-lg">LATEST ARMIES (4 MEMBERS)</div>
          <div class="mono small">Inter-University team registrations + receipt</div>
        </div>
        <div class="mono small">
          Fee: <span class="text-yellow-200">LKR 1000</span> / student •
          Team: <span class="text-yellow-200">LKR 4000</span>
        </div>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>UNIVERSITY</th>
              <th>TEAM</th>
              <th>LEADER / CONTACT</th>
              <th>MEMBERS</th>
              <th>PAYMENT</th>
              <th>RECEIPT</th>
              <th>TIME</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($teamRegs)): ?>
            <tr>
              <td colspan="7" class="small" style="text-align:center; padding:16px;">
                No registrations yet (or registration tables not created).
              </td>
            </tr>
          <?php else: ?>
            <?php foreach($teamRegs as $t): ?>
              <?php
                $st = $t['pay_status'] ?? null;
                $badgeClass = 'b-na'; $badgeText = 'N/A';
                if ($st === 'pending'){ $badgeClass='b-pending'; $badgeText='PENDING'; }
                if ($st === 'verified'){ $badgeClass='b-verified'; $badgeText='VERIFIED'; }
                if ($st === 'rejected'){ $badgeClass='b-rejected'; $badgeText='REJECTED'; }
                $receipt = $t['receipt_file'] ?? '';
              ?>
              <tr>
                <td><?= htmlspecialchars($t['university_name']) ?></td>
                <td>
                  <div style="font-weight:900; color:rgba(230,250,255,0.95);">
                    <?= htmlspecialchars($t['team_name']) ?>
                  </div>
                  <div class="small mono">ID: <?= (int)$t['team_id'] ?> • Members: <?= (int)$t['member_count'] ?>/4</div>
                </td>
                <td class="small">
                  <div><span style="color:rgba(56,247,255,0.92); font-weight:900;">Leader:</span>
                    <?= htmlspecialchars($t['leader_name']) ?>
                  </div>
                  <div><?= htmlspecialchars($t['leader_email']) ?> • <?= htmlspecialchars($t['leader_phone']) ?></div>
                  <div style="margin-top:8px;"><span style="color:rgba(56,247,255,0.92); font-weight:900;">Contact:</span>
                    <?= htmlspecialchars($t['contact_name']) ?>
                  </div>
                  <div><?= htmlspecialchars($t['contact_email']) ?> • <?= htmlspecialchars($t['contact_phone']) ?></div>
                </td>
                <td class="small">
                  <details>
                    <summary>View 4 members</summary>
                    <div style="margin-top:10px; display:grid; gap:8px;">
                      <?php foreach(($membersByTeam[(int)$t['team_id']] ?? []) as $m): ?>
                        <div class="panel" style="padding:10px; border-radius:14px; background:rgba(255,255,255,0.03);">
                          <div style="font-weight:900;">#<?= (int)$m['member_no'] ?> — <?= htmlspecialchars($m['member_name']) ?></div>
                          <div class="small"><?= htmlspecialchars($m['member_email']) ?> • <?= htmlspecialchars($m['member_phone']) ?></div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </details>
                </td>
                <td class="small">
                  <div class="badge <?= $badgeClass ?>"><?= $badgeText ?></div>
                  <div style="margin-top:8px;">
                    <?= htmlspecialchars($t['pay_currency'] ?? 'LKR') ?>
                    <b style="color:rgba(245,210,123,0.95);"><?= (int)($t['pay_amount'] ?? 4000) ?></b>
                  </div>
                </td>
                <td class="small">
                  <?php if ($receipt): ?>
                    <a class="link" href="../<?= htmlspecialchars($receipt) ?>" target="_blank">Download</a>
                  <?php else: ?>
                    <span class="small">No receipt</span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= htmlspecialchars($t['created_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- USER ACTIVITY -->
    <div class="panel p-6">
      <div class="h1 text-lg mb-3">USER ACTIVITY</div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>USERNAME</th>
              <th>SCORE</th>
              <th>LAST LOGIN</th>
              <th>IP</th>
              <th>LOGS</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($users as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['username']) ?></td>
              <td><?= (int)$u['score'] ?></td>
              <td><?= htmlspecialchars($u['last_login'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['ip_address'] ?? '') ?></td>
              <td>
                <a class="link" href="user_logs.php?id=<?= (int)$u['id'] ?>">View Logs</a>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- RECENT SUBMISSIONS -->
    <div class="panel p-6">
      <div class="h1 text-lg mb-3">RECENT CHALLENGE SUBMISSIONS</div>
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>USER</th>
              <th>CHALLENGE</th>
              <th>FLAG</th>
              <th>STATUS</th>
              <th>IP</th>
              <th>TIME</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($logs as $log): ?>
            <tr>
              <td><?= htmlspecialchars($log['username']) ?></td>
              <td><?= htmlspecialchars($log['challenge_title']) ?></td>
              <td><?= htmlspecialchars($log['flag_submitted']) ?></td>
              <td class="<?= $log['status']=='correct'?'status-correct':'status-incorrect' ?>">
                <?= $log['status']=='correct'?'✅ CORRECT':'❌ WRONG' ?>
              </td>
              <td><?= htmlspecialchars($log['ip_address']) ?></td>
              <td><?= htmlspecialchars($log['submission_time']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  </main>
</div>

</body>
</html>
