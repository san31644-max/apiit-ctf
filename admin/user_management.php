<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . "/../includes/db.php";

// Only admin access
if (!isset($_SESSION['user_id']) || (($_SESSION['role'] ?? '') !== 'admin')) {
    header("Location: ../index.php");
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) c
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :t
    ");
    $stmt->execute([':t' => $table]);
    return ((int)($stmt->fetch()['c'] ?? 0)) > 0;
}

function getTableColumns(PDO $pdo, string $table): array {
    $cols = [];
    $stmt = $pdo->query("DESCRIBE `$table`");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $cols[] = $row['Field'];
    return $cols;
}

function pickFirstExisting(array $columns, array $candidates): ?string {
    foreach ($candidates as $c) if (in_array($c, $columns, true)) return $c;
    return null;
}

/* ----------------------------
   AJAX ENDPOINT: user logs
---------------------------- */
if (isset($_GET['action']) && $_GET['action'] === 'user_logs') {
    header('Content-Type: application/json; charset=utf-8');

    $userId = (int)($_GET['user_id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid user_id']);
        exit;
    }

    $hasLogs = tableExists($pdo, 'challenge_logs');
    $hasLoginLogs = tableExists($pdo, 'login_logs');
    $hasUserActivity = tableExists($pdo, 'user_activity');

    $out = [
        'ok' => true,
        'user_id' => $userId,
        'distinct_ips' => [],
        'submissions' => [],
        'login_logs' => [],
        'user_activity' => [],
    ];

    // Distinct IPs from challenge_logs
    if ($hasLogs) {
        $stmt = $pdo->prepare("
            SELECT ip_address, MAX(submission_time) AS last_seen, COUNT(*) AS cnt
            FROM challenge_logs
            WHERE user_id = :uid
            GROUP BY ip_address
            ORDER BY last_seen DESC
            LIMIT 30
        ");
        $stmt->execute([':uid' => $userId]);
        $out['distinct_ips'] = $stmt->fetchAll();
    }

    // Latest submissions
    if ($hasLogs) {
        $stmt = $pdo->prepare("
            SELECT id, challenge_id, flag_submitted, status, ip_address, submission_time, username
            FROM challenge_logs
            WHERE user_id = :uid
            ORDER BY submission_time DESC
            LIMIT 100
        ");
        $stmt->execute([':uid' => $userId]);
        $out['submissions'] = $stmt->fetchAll();
    }

    // login_logs (best-effort column detection)
    if ($hasLoginLogs) {
        $cols = getTableColumns($pdo, 'login_logs');
        $uidCol = pickFirstExisting($cols, ['user_id', 'userid', 'userId']);
        $timeCol = pickFirstExisting($cols, ['login_time', 'time', 'created_at', 'timestamp', 'date']);
        $ipCol = pickFirstExisting($cols, ['ip', 'ip_address', 'ipaddr']);
        $statusCol = pickFirstExisting($cols, ['status', 'result', 'success']);
        $userCol = pickFirstExisting($cols, ['username', 'user_name']);

        // If we can't find a user column, just show latest 50 for now (still useful)
        if ($uidCol) {
            $sql = "SELECT * FROM login_logs WHERE `$uidCol` = :uid ORDER BY " . ($timeCol ? "`$timeCol`" : "id") . " DESC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $out['login_logs'] = $stmt->fetchAll();
        } elseif ($userCol) {
            // try match by username from challenge_logs
            $uStmt = $pdo->prepare("SELECT username FROM challenge_logs WHERE user_id = :uid ORDER BY submission_time DESC LIMIT 1");
            $uStmt->execute([':uid' => $userId]);
            $uname = (string)($uStmt->fetch()['username'] ?? '');
            if ($uname !== '') {
                $sql = "SELECT * FROM login_logs WHERE `$userCol` = :un ORDER BY " . ($timeCol ? "`$timeCol`" : "id") . " DESC LIMIT 50";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':un' => $uname]);
                $out['login_logs'] = $stmt->fetchAll();
            }
        } else {
            $out['login_logs'] = [];
        }
    }

    // user_activity (best-effort column detection)
    if ($hasUserActivity) {
        $cols = getTableColumns($pdo, 'user_activity');
        $uidCol = pickFirstExisting($cols, ['user_id', 'userid', 'userId']);
        $timeCol = pickFirstExisting($cols, ['activity_time', 'time', 'created_at', 'timestamp', 'date']);

        if ($uidCol) {
            $sql = "SELECT * FROM user_activity WHERE `$uidCol` = :uid ORDER BY " . ($timeCol ? "`$timeCol`" : "id") . " DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':uid' => $userId]);
            $out['user_activity'] = $stmt->fetchAll();
        }
    }

    echo json_encode($out);
    exit;
}

/* ----------------------------
   Normal page below
---------------------------- */

$teamId   = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$userId   = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$hasTeams       = tableExists($pdo, 'ctf_teams');
$hasTeamMembers = tableExists($pdo, 'ctf_team_members');
$hasUsers       = tableExists($pdo, 'users');
$hasLogs        = tableExists($pdo, 'challenge_logs');

$teamsCols = $hasTeams ? getTableColumns($pdo, 'ctf_teams') : [];
$tmCols    = $hasTeamMembers ? getTableColumns($pdo, 'ctf_team_members') : [];
$usersCols = $hasUsers ? getTableColumns($pdo, 'users') : [];

$teamIdCol_inTeams   = pickFirstExisting($teamsCols, ['id', 'team_id']);
$teamNameCol_inTeams = pickFirstExisting($teamsCols, ['team_name', 'name', 'team']);

$tmTeamCol = pickFirstExisting($tmCols, ['team_id', 'ctf_team_id', 'teamId', 'team']);
$tmUserCol = pickFirstExisting($tmCols, ['user_id', 'userid', 'userId', 'member_id', 'memberId', 'username', 'user_name']);

$userIdCol   = pickFirstExisting($usersCols, ['id', 'user_id', 'userid']);
$usernameCol = pickFirstExisting($usersCols, ['username', 'user_name', 'name']);

$teams = [];
if ($hasTeams && $teamIdCol_inTeams && $teamNameCol_inTeams) {
    $teams = $pdo->query("SELECT `$teamIdCol_inTeams` AS id, `$teamNameCol_inTeams` AS team_name FROM ctf_teams ORDER BY `$teamNameCol_inTeams` ASC")->fetchAll();
}

$members = [];
$membersJoinNote = "";
if ($hasTeamMembers && $hasTeams && $hasUsers && $tmTeamCol && $tmUserCol && $teamIdCol_inTeams && $teamNameCol_inTeams && $userIdCol && $usernameCol) {
    $joinUsersOn = ($tmUserCol === 'username' || $tmUserCol === 'user_name')
        ? "u.`$usernameCol` = tm.`$tmUserCol`"
        : "u.`$userIdCol` = tm.`$tmUserCol`";

    $membersJoinNote = ($tmUserCol === 'username' || $tmUserCol === 'user_name')
        ? "Team members link by username (`$tmUserCol`)."
        : "Team members link by user id (`$tmUserCol`).";

    $membersSql = "
        SELECT
            tm.`$tmTeamCol` AS team_id,
            t.`$teamNameCol_inTeams` AS team_name,
            u.`$userIdCol` AS user_id,
            u.`$usernameCol` AS username
        FROM ctf_team_members tm
        JOIN ctf_teams t ON t.`$teamIdCol_inTeams` = tm.`$tmTeamCol`
        JOIN users u ON $joinUsersOn
        WHERE 1=1
    ";
    $params = [];
    if ($teamId > 0) { $membersSql .= " AND tm.`$tmTeamCol` = :team_id"; $params[':team_id'] = $teamId; }
    if ($userId > 0) { $membersSql .= " AND u.`$userIdCol` = :user_id"; $params[':user_id'] = $userId; }
    $membersSql .= " ORDER BY t.`$teamNameCol_inTeams` ASC, u.`$usernameCol` ASC";

    $stmt = $pdo->prepare($membersSql);
    $stmt->execute($params);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Logs list for main table (latest)
$logs = [];
$totalRows = 0;
$totalPages = 1;

if ($hasLogs) {
    $where = " WHERE 1=1 ";
    $params = [];

    if ($teamId > 0 && $hasTeamMembers && $tmTeamCol && $tmUserCol) {
        if ($tmUserCol === 'username' || $tmUserCol === 'user_name') {
            $where .= " AND cl.username IN (SELECT `$tmUserCol` FROM ctf_team_members WHERE `$tmTeamCol` = :team_id2) ";
        } else {
            $where .= " AND cl.user_id IN (SELECT `$tmUserCol` FROM ctf_team_members WHERE `$tmTeamCol` = :team_id2) ";
        }
        $params[':team_id2'] = $teamId;
    }
    if ($userId > 0) {
        $where .= " AND cl.user_id = :user_id2 ";
        $params[':user_id2'] = $userId;
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) c FROM challenge_logs cl {$where}");
    $countStmt->execute($params);
    $totalRows = (int)($countStmt->fetch()['c'] ?? 0);
    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    $teamJoin = "";
    $selectTeamName = "'' AS team_name";
    if ($hasTeams && $hasTeamMembers && $tmTeamCol && $tmUserCol && $teamIdCol_inTeams && $teamNameCol_inTeams) {
        $teamJoin = "LEFT JOIN ctf_team_members tm ON " .
            (($tmUserCol === 'username' || $tmUserCol === 'user_name') ? "tm.`$tmUserCol` = cl.username" : "tm.`$tmUserCol` = cl.user_id") .
            " LEFT JOIN ctf_teams t ON t.`$teamIdCol_inTeams` = tm.`$tmTeamCol`";
        $selectTeamName = "t.`$teamNameCol_inTeams` AS team_name";
    }

    $sql = "
        SELECT cl.id, cl.user_id, cl.username, cl.challenge_id, cl.flag_submitted, cl.status, cl.ip_address, cl.submission_time,
               {$selectTeamName}
        FROM challenge_logs cl
        {$teamJoin}
        {$where}
        ORDER BY cl.submission_time DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>CTF User Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:20px;background:#0b0f14;color:#e8eef6}
    a{color:#7cc4ff;text-decoration:none}
    .card{background:#121a24;border:1px solid #223043;border-radius:10px;padding:14px;margin:14px 0}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #223043;padding:10px;text-align:left;vertical-align:top}
    th{color:#b9c7da;font-weight:600}
    input,select,button{background:#0b0f14;color:#e8eef6;border:1px solid #223043;border-radius:8px;padding:8px}
    .row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
    .muted{color:#9fb0c7}
    .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #223043}
    .ok{border-color:#1f7a3a;color:#8ef0b0}
    .bad{border-color:#7a1f2a;color:#ff9aa6}
    .clickRow{cursor:pointer}
    .clickRow:hover{background:#0f1620}
    code{color:#bfe3ff}
    /* MODAL */
    .modalBack{position:fixed;inset:0;background:rgba(0,0,0,.6);display:none;align-items:center;justify-content:center;padding:16px;z-index:9999}
    .modal{width:min(1100px, 98vw);max-height:90vh;overflow:auto;background:#121a24;border:1px solid #223043;border-radius:12px;padding:14px}
    .modalTop{display:flex;justify-content:space-between;align-items:center;gap:12px}
    .closeBtn{cursor:pointer;font-size:20px;border:1px solid #223043;border-radius:10px;padding:6px 10px}
    .grid{display:grid;grid-template-columns:1fr;gap:12px}
    @media (min-width: 900px){ .grid{grid-template-columns: 1fr 2fr;} }
    .small{font-size:12px}
  </style>
</head>
<body>

<h1>CTF User Management</h1>
<p class="muted">Click a user row to open popup logs.</p>

<div class="card">
  <form method="get">
    <div class="row">
      <div>
        <label>Team</label><br>
        <select name="team_id">
          <option value="0">All teams</option>
          <?php foreach ($teams as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $teamId === (int)$t['id'] ? 'selected' : '' ?>>
              <?= h((string)$t['team_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label>User ID</label><br>
        <input type="number" name="user_id" value="<?= (int)$userId ?>" min="0" placeholder="0 = all">
      </div>

      <div><button type="submit">Apply</button></div>
    </div>
  </form>
</div>

<div class="card">
  <h2>Team Members</h2>
  <?php if ($membersJoinNote): ?><div class="muted"><?= h($membersJoinNote) ?></div><?php endif; ?>

  <table>
    <thead><tr><th>Team</th><th>User</th><th>Action</th></tr></thead>
    <tbody>
      <?php if (!$members): ?>
        <tr><td colspan="3" class="muted">No members found.</td></tr>
      <?php else: ?>
        <?php foreach ($members as $m): ?>
          <tr class="clickRow" data-user-id="<?= (int)$m['user_id'] ?>" data-username="<?= h((string)$m['username']) ?>">
            <td><?= h((string)$m['team_name']) ?></td>
            <td><?= h((string)$m['username']) ?> <span class="muted small">(user_id: <?= (int)$m['user_id'] ?>)</span></td>
            <td class="muted">Click to view logs</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div class="card">
  <h2>Recent Submissions</h2>
  <div class="muted">Total: <?= (int)$totalRows ?> | Page <?= (int)$page ?> / <?= (int)$totalPages ?></div>

  <table>
    <thead>
      <tr>
        <th>Time</th><th>Team</th><th>User</th><th>Challenge</th><th>Flag</th><th>Status</th><th>IP</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$logs): ?>
        <tr><td colspan="7" class="muted">No logs.</td></tr>
      <?php else: ?>
        <?php foreach ($logs as $r): ?>
          <tr class="clickRow" data-user-id="<?= (int)$r['user_id'] ?>" data-username="<?= h((string)$r['username']) ?>">
            <td><?= h((string)$r['submission_time']) ?></td>
            <td><?= h((string)($r['team_name'] ?? '')) ?></td>
            <td><?= h((string)$r['username']) ?> <span class="muted small">(user_id: <?= (int)$r['user_id'] ?>)</span></td>
            <td>#<?= (int)$r['challenge_id'] ?></td>
            <td><code><?= h((string)$r['flag_submitted']) ?></code></td>
            <td><?= ((string)$r['status'] === 'correct') ? '<span class="pill ok">correct</span>' : '<span class="pill bad">'.h((string)$r['status']).'</span>' ?></td>
            <td><?= h((string)$r['ip_address']) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="pagination" style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
    <?php
      $base = $_GET;
      for ($p = max(1, $page-3); $p <= min($totalPages, $page+3); $p++) {
          $base['page'] = $p;
          $qs = http_build_query($base);
          echo ($p === $page) ? "<strong>$p</strong>" : "<a href=\"?{$qs}\">$p</a>";
      }
    ?>
  </div>
</div>

<!-- MODAL -->
<div class="modalBack" id="modalBack">
  <div class="modal">
    <div class="modalTop">
      <div>
        <div style="font-size:18px;font-weight:700" id="modalTitle">User Logs</div>
        <div class="muted small" id="modalSub"></div>
      </div>
      <div class="closeBtn" id="closeBtn">✕</div>
    </div>

    <div class="grid" style="margin-top:12px">
      <div class="card" style="margin:0">
        <h3 style="margin-top:0">Distinct IPs</h3>
        <div id="ipsBox" class="muted">Loading...</div>
      </div>

      <div class="card" style="margin:0">
        <h3 style="margin-top:0">Submissions (latest 100)</h3>
        <div id="subsBox" class="muted">Loading...</div>
      </div>
    </div>

    <div class="card">
      <h3 style="margin-top:0">Login Logs (latest 50)</h3>
      <div id="loginBox" class="muted">Loading...</div>
    </div>

    <div class="card">
      <h3 style="margin-top:0">User Activity (latest 100)</h3>
      <div id="activityBox" class="muted">Loading...</div>
    </div>
  </div>
</div>

<script>
const modalBack = document.getElementById('modalBack');
const closeBtn = document.getElementById('closeBtn');

function esc(s){ return (s ?? '').toString()
  .replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;')
  .replaceAll('"','&quot;').replaceAll("'","&#039;"); }

function openModal(userId, username){
  document.getElementById('modalTitle').textContent = `Logs for ${username}`;
  document.getElementById('modalSub').textContent = `user_id: ${userId}`;
  document.getElementById('ipsBox').innerHTML = 'Loading...';
  document.getElementById('subsBox').innerHTML = 'Loading...';
  document.getElementById('loginBox').innerHTML = 'Loading...';
  document.getElementById('activityBox').innerHTML = 'Loading...';

  modalBack.style.display = 'flex';

  fetch(`user_management.php?action=user_logs&user_id=${encodeURIComponent(userId)}`)
    .then(r => r.json())
    .then(data => {
      if (!data.ok){
        document.getElementById('ipsBox').innerHTML = esc(data.error || 'Error');
        return;
      }

      // IPs
      if (!data.distinct_ips || data.distinct_ips.length === 0){
        document.getElementById('ipsBox').innerHTML = '<div class="muted">No IPs found.</div>';
      } else {
        let html = '<table><thead><tr><th>IP</th><th>Last Seen</th><th>Count</th></tr></thead><tbody>';
        data.distinct_ips.forEach(row=>{
          html += `<tr><td>${esc(row.ip_address)}</td><td>${esc(row.last_seen)}</td><td>${esc(row.cnt)}</td></tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('ipsBox').innerHTML = html;
      }

      // Submissions
      if (!data.submissions || data.submissions.length === 0){
        document.getElementById('subsBox').innerHTML = '<div class="muted">No submissions.</div>';
      } else {
        let html = '<table><thead><tr><th>Time</th><th>Challenge</th><th>Status</th><th>IP</th><th>Flag</th></tr></thead><tbody>';
        data.submissions.forEach(row=>{
          const st = (row.status === 'correct')
            ? '<span class="pill ok">correct</span>'
            : `<span class="pill bad">${esc(row.status)}</span>`;
          html += `<tr>
            <td>${esc(row.submission_time)}</td>
            <td>#${esc(row.challenge_id)}</td>
            <td>${st}</td>
            <td>${esc(row.ip_address)}</td>
            <td><code>${esc(row.flag_submitted)}</code></td>
          </tr>`;
        });
        html += '</tbody></table>';
        document.getElementById('subsBox').innerHTML = html;
      }

      // login logs
      if (!data.login_logs || data.login_logs.length === 0){
        document.getElementById('loginBox').innerHTML = '<div class="muted">No login logs (or table missing).</div>';
      } else {
        const cols = Object.keys(data.login_logs[0]);
        let html = '<table><thead><tr>' + cols.map(c=>`<th>${esc(c)}</th>`).join('') + '</tr></thead><tbody>';
        data.login_logs.forEach(row=>{
          html += '<tr>' + cols.map(c=>`<td>${esc(row[c])}</td>`).join('') + '</tr>';
        });
        html += '</tbody></table>';
        document.getElementById('loginBox').innerHTML = html;
      }

      // activity logs
      if (!data.user_activity || data.user_activity.length === 0){
        document.getElementById('activityBox').innerHTML = '<div class="muted">No user activity (or table missing).</div>';
      } else {
        const cols = Object.keys(data.user_activity[0]);
        let html = '<table><thead><tr>' + cols.map(c=>`<th>${esc(c)}</th>`).join('') + '</tr></thead><tbody>';
        data.user_activity.forEach(row=>{
          html += '<tr>' + cols.map(c=>`<td>${esc(row[c])}</td>`).join('') + '</tr>';
        });
        html += '</tbody></table>';
        document.getElementById('activityBox').innerHTML = html;
      }
    })
    .catch(err => {
      document.getElementById('ipsBox').innerHTML = esc(err.toString());
      document.getElementById('subsBox').innerHTML = '';
      document.getElementById('loginBox').innerHTML = '';
      document.getElementById('activityBox').innerHTML = '';
    });
}

// click any row with data-user-id
document.querySelectorAll('.clickRow').forEach(tr=>{
  tr.addEventListener('click', ()=>{
    const uid = tr.getAttribute('data-user-id');
    const un = tr.getAttribute('data-username') || ('User ' + uid);
    if (uid) openModal(uid, un);
  });
});

closeBtn.addEventListener('click', ()=> modalBack.style.display = 'none');
modalBack.addEventListener('click', (e)=> { if (e.target === modalBack) modalBack.style.display = 'none'; });
document.addEventListener('keydown', (e)=> { if (e.key === 'Escape') modalBack.style.display = 'none'; });
</script>

</body>
</html>
