<?php
// admin/view_registration.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

// ---- Admin auth (CHANGE this to match your existing dashboard auth) ----
// Example: if you use $_SESSION['is_admin'] or $_SESSION['admin_logged_in'].
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['admin_logged_in']) && empty($_SESSION['is_admin'])) {
    header("Location: /admin/login.php");
    exit;
}

// ---- Helpers ----
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// ---- Inputs ----
$q = trim((string)($_GET['q'] ?? ''));
$payment = (string)($_GET['payment'] ?? 'all'); // all|paid|unpaid
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// ---- Build query ----
// NOTE: Column names may differ. We'll adjust after you share schema.
// Assumptions:
// ctf_teams: id, team_name, captain_name, email, phone, created_at
// ctf_payments: id, team_id, amount, status, paid_at, proof_file
// ctf_team_members: id, team_id, member_name, member_email
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(t.team_name LIKE :q OR t.captain_name LIKE :q OR t.email LIKE :q OR t.phone LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($payment === 'paid') {
    $where[] = "COALESCE(p.status,'') IN ('paid','PAID','success','SUCCESS')";
} elseif ($payment === 'unpaid') {
    $where[] = "(p.id IS NULL OR COALESCE(p.status,'') NOT IN ('paid','PAID','success','SUCCESS'))";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// Count for pagination
$sqlCount = "
SELECT COUNT(*) AS cnt
FROM ctf_teams t
LEFT JOIN ctf_payments p ON p.team_id = t.id
{$whereSql}
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// Main page data
$sql = "
SELECT
  t.id,
  t.team_name,
  t.captain_name,
  t.email,
  t.phone,
  t.created_at,
  p.id AS payment_id,
  p.amount,
  p.status AS payment_status,
  p.paid_at,
  p.proof_file
FROM ctf_teams t
LEFT JOIN ctf_payments p ON p.team_id = t.id
{$whereSql}
ORDER BY t.id DESC
LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch members for displayed team ids
$teamIds = array_map(fn($r) => (int)$r['id'], $teams);
$membersByTeam = [];
if ($teamIds) {
    $in = implode(',', array_fill(0, count($teamIds), '?'));
    $m = $pdo->prepare("SELECT team_id, member_name, member_email FROM ctf_team_members WHERE team_id IN ($in) ORDER BY id ASC");
    $m->execute($teamIds);
    while ($row = $m->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int)$row['team_id'];
        $membersByTeam[$tid][] = $row;
    }
}

// ---- Page layout ----
// If your admin theme uses includes like header/sidebar, replace the HTML below
// with require_once __DIR__.'/partials/header.php'; etc.
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>View Registrations</title>
  <style>
    /* Minimal styling. Replace with your dashboard CSS classes once you paste it. */
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial; background:#0b1220; color:#e5e7eb; margin:0;}
    .wrap{max-width:1200px; margin:24px auto; padding:0 16px;}
    .card{background:#0f1b33; border:1px solid rgba(255,255,255,.08); border-radius:14px; padding:16px;}
    .row{display:flex; gap:12px; flex-wrap:wrap; align-items:center;}
    input,select{background:#0b1220; color:#e5e7eb; border:1px solid rgba(255,255,255,.12); border-radius:10px; padding:10px 12px;}
    a.btn,button.btn{display:inline-block; background:#2563eb; color:white; padding:10px 12px; border-radius:10px; border:0; text-decoration:none; cursor:pointer;}
    a.btn.secondary{background:#334155;}
    table{width:100%; border-collapse:collapse; margin-top:14px;}
    th,td{padding:10px; border-bottom:1px solid rgba(255,255,255,.08); vertical-align:top;}
    th{font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#9ca3af; text-align:left;}
    .pill{display:inline-block; padding:4px 8px; border-radius:999px; font-size:12px;}
    .paid{background:rgba(34,197,94,.15); color:#22c55e;}
    .unpaid{background:rgba(239,68,68,.15); color:#ef4444;}
    details{background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); border-radius:10px; padding:10px;}
    .muted{color:#9ca3af; font-size:13px;}
    .pagination{display:flex; gap:8px; flex-wrap:wrap; margin-top:14px;}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="row" style="justify-content:space-between;">
        <div>
          <h2 style="margin:0 0 4px;">Team Registrations</h2>
          <div class="muted">Total: <?= (int)$total ?></div>
        </div>
        <div class="row">
          <a class="btn secondary" href="/admin/export_registrations_csv.php">Download CSV</a>
        </div>
      </div>

      <form class="row" method="get" style="margin-top:14px;">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search team/captain/email/phone">
        <select name="payment">
          <option value="all" <?= $payment==='all'?'selected':'' ?>>Payment: All</option>
          <option value="paid" <?= $payment==='paid'?'selected':'' ?>>Paid</option>
          <option value="unpaid" <?= $payment==='unpaid'?'selected':'' ?>>Unpaid</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <a class="btn secondary" href="/admin/view_registration.php">Reset</a>
      </form>

      <table>
        <thead>
          <tr>
            <th>Team</th>
            <th>Captain</th>
            <th>Contact</th>
            <th>Members</th>
            <th>Payment</th>
            <th>Registered</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$teams): ?>
          <tr><td colspan="6" class="muted">No teams found.</td></tr>
        <?php endif; ?>

        <?php foreach ($teams as $t): 
          $tid = (int)$t['id'];
          $members = $membersByTeam[$tid] ?? [];
          $isPaid = in_array((string)($t['payment_status'] ?? ''), ['paid','PAID','success','SUCCESS'], true);
        ?>
          <tr>
            <td><strong><?= h((string)$t['team_name']) ?></strong><div class="muted">ID: <?= $tid ?></div></td>
            <td><?= h((string)($t['captain_name'] ?? '')) ?></td>
            <td>
              <div><?= h((string)($t['email'] ?? '')) ?></div>
              <div class="muted"><?= h((string)($t['phone'] ?? '')) ?></div>
            </td>
            <td>
              <details>
                <summary><?= count($members) ?> member(s)</summary>
                <div style="margin-top:8px;">
                  <?php if (!$members): ?>
                    <div class="muted">No members found.</div>
                  <?php else: ?>
                    <?php foreach ($members as $m): ?>
                      <div style="padding:6px 0; border-bottom:1px dashed rgba(255,255,255,.08);">
                        <div><?= h((string)($m['member_name'] ?? '')) ?></div>
                        <div class="muted"><?= h((string)($m['member_email'] ?? '')) ?></div>
                      </div>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </div>
              </details>
            </td>
            <td>
              <div class="pill <?= $isPaid ? 'paid':'unpaid' ?>">
                <?= $isPaid ? 'Paid' : 'Unpaid' ?>
              </div>
              <div class="muted" style="margin-top:6px;">
                Amount: <?= h((string)($t['amount'] ?? '')) ?><br>
                Paid at: <?= h((string)($t['paid_at'] ?? '')) ?>
              </div>

              <?php if (!empty($t['proof_file'])): ?>
                <div style="margin-top:8px;">
                  <a class="btn secondary" href="/admin/download_payment.php?team_id=<?= $tid ?>">Download proof</a>
                </div>
              <?php endif; ?>
            </td>
            <td><?= h((string)($t['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="pagination">
        <?php
          $base = "/admin/view_registration.php?q=" . urlencode($q) . "&payment=" . urlencode($payment) . "&page=";
          $prev = max(1, $page - 1);
          $next = min($totalPages, $page + 1);
        ?>
        <a class="btn secondary" href="<?= h($base . $prev) ?>">&larr; Prev</a>
        <div class="muted" style="padding:10px 0;">Page <?= (int)$page ?> / <?= (int)$totalPages ?></div>
        <a class="btn secondary" href="<?= h($base . $next) ?>">Next &rarr;</a>
      </div>
    </div>
  </div>
</body>
</html>
