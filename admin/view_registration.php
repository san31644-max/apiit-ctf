<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Uses SAME login session as your site.
 * If your login uses a different session key, change this.
 */
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']);
if (!$loggedIn) {
    header("Location: /index.php");
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function starts_with(string $s, string $prefix): bool { return strncmp($s, $prefix, strlen($prefix)) === 0; }

// ---- Inputs ----
$action  = (string)($_GET['action'] ?? 'view'); // view|csv|download_receipt
$q       = trim((string)($_GET['q'] ?? ''));
$payment = (string)($_GET['payment'] ?? 'all'); // all|paid|unpaid
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ---- Action: Download receipt (same page) ----
if ($action === 'download_receipt') {
    $teamId = (int)($_GET['team_id'] ?? 0);
    if ($teamId <= 0) { http_response_code(400); exit("Invalid team_id"); }

    // Always download the latest receipt for this team
    $stmt = $pdo->prepare("
        SELECT receipt_file, receipt_original_name, receipt_mime
        FROM ctf_payments
        WHERE team_id = :tid
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':tid' => $teamId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['receipt_file'])) {
        http_response_code(404);
        exit("No receipt found for this team.");
    }

    $proof = (string)$row['receipt_file'];
    $orig  = (string)($row['receipt_original_name'] ?? '');
    $mime  = (string)($row['receipt_mime'] ?? '');

    // DB stores: uploads/receipts/....
    $projectRoot = realpath(__DIR__ . '/..');
    $uploadsDir  = realpath(__DIR__ . '/../uploads');
    $receiptsDir = realpath(__DIR__ . '/../uploads/receipts');

    // Build absolute path
    if (starts_with($proof, '/')) {
        $target = realpath($proof) ?: '';
    } else {
        $target = realpath($projectRoot . '/' . ltrim($proof, '/')) ?: '';
    }

    if ($target === '' || !is_file($target)) {
        http_response_code(404);
        exit("Receipt file not found on server.");
    }

    // Security: allow only inside /uploads (and receipts subfolder)
    $ok = false;
    foreach (array_filter([$uploadsDir, $receiptsDir]) as $base) {
        if (starts_with($target, $base . DIRECTORY_SEPARATOR) || $target === $base) { $ok = true; break; }
    }
    if (!$ok) {
        http_response_code(403);
        exit("Forbidden file path.");
    }

    $filename = $orig !== '' ? $orig : basename($target);
    if ($mime === '') $mime = mime_content_type($target) ?: 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
    header('Content-Length: ' . (string)filesize($target));
    readfile($target);
    exit;
}

// ---- Build WHERE (for view & csv) ----
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(t.team_name LIKE :q OR t.captain_name LIKE :q OR t.email LIKE :q OR t.phone LIKE :q)";
    $params[':q'] = "%{$q}%";
}

if ($payment === 'paid') {
    $where[] = "COALESCE(p.status,'') IN ('paid','PAID','success','SUCCESS','completed','COMPLETED')";
} elseif ($payment === 'unpaid') {
    $where[] = "(p.id IS NULL OR COALESCE(p.status,'') NOT IN ('paid','PAID','success','SUCCESS','completed','COMPLETED'))";
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ---- Action: Export CSV (same page) ----
if ($action === 'csv') {
    $sql = "
    SELECT
      t.id AS team_id,
      t.team_name,
      t.captain_name,
      t.email,
      t.phone,
      t.created_at AS registered_at,
      p.amount,
      p.currency,
      p.status AS payment_status,
      p.receipt_file,
      p.created_at AS payment_created_at
    FROM ctf_teams t
    LEFT JOIN ctf_payments p ON p.team_id = t.id
    {$whereSql}
    ORDER BY t.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="registrations.csv"');

    $out = fopen('php://output', 'w');
    if (!$rows) {
        fputcsv($out, ['team_id','team_name']);
    } else {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ---- Count for pagination ----
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

// ---- Main page data (include receipt columns) ----
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
  p.currency,
  p.status AS payment_status,
  p.receipt_file,
  p.receipt_original_name,
  p.receipt_mime,
  p.created_at AS payment_created_at

FROM ctf_teams t
LEFT JOIN ctf_payments p ON p.team_id = t.id
{$whereSql}
ORDER BY t.id DESC
LIMIT :limit OFFSET :offset
";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Members for teams on this page ----
$teamIds = array_map(fn($r) => (int)$r['id'], $teams);
$membersByTeam = [];
if ($teamIds) {
    $in = implode(',', array_fill(0, count($teamIds), '?'));
    $m = $pdo->prepare("SELECT team_id, member_name, member_email FROM ctf_team_members WHERE team_id IN ($in) ORDER BY id ASC");
    $m->execute($teamIds);
    while ($row = $m->fetch(PDO::FETCH_ASSOC)) {
        $membersByTeam[(int)$row['team_id']][] = $row;
    }
}

// Links
$base = "/admin/view_registration.php?q=" . urlencode($q) . "&payment=" . urlencode($payment) . "&page=";
$prev = max(1, $page - 1);
$next = min($totalPages, $page + 1);
$csvLink = "/admin/view_registration.php?action=csv&q=" . urlencode($q) . "&payment=" . urlencode($payment);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Team Registrations</title>
  <style>
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
          <a class="btn secondary" href="<?= h($csvLink) ?>">Download CSV</a>
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
            <th>Payment + Receipt</th>
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
          $status = (string)($t['payment_status'] ?? '');
          $isPaid = in_array($status, ['paid','PAID','success','SUCCESS','completed','COMPLETED'], true);

          $receiptFile = (string)($t['receipt_file'] ?? '');
          $receiptLink = "/admin/view_registration.php?action=download_receipt&team_id={$tid}";
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
                Amount: <?= h((string)($t['amount'] ?? '')) ?> <?= h((string)($t['currency'] ?? '')) ?><br>
                Status: <?= h($status) ?><br>
                Payment time: <?= h((string)($t['payment_created_at'] ?? '')) ?>
              </div>

              <?php if ($receiptFile !== ''): ?>
                <div style="margin-top:8px;">
                  <a class="btn secondary" href="<?= h($receiptLink) ?>">Download receipt</a>
                </div>
                <div class="muted" style="margin-top:6px;">
                  <?= h((string)($t['receipt_original_name'] ?? '')) ?>
                </div>
              <?php else: ?>
                <div class="muted" style="margin-top:8px;">No receipt uploaded</div>
              <?php endif; ?>
            </td>
            <td><?= h((string)($t['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="pagination">
        <a class="btn secondary" href="<?= h($base . max(1, $page-1)) ?>">&larr; Prev</a>
        <div class="muted" style="padding:10px 0;">Page <?= (int)$page ?> / <?= (int)$totalPages ?></div>
        <a class="btn secondary" href="<?= h($base . min($totalPages, $page+1)) ?>">Next &rarr;</a>
      </div>
    </div>
  </div>
</body>
</html>
