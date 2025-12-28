<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Uses the SAME login session as your site.
 * If your project uses a specific session key, update this line.
 */
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']);
if (!$loggedIn) {
    header("Location: /index.php"); // your main login page
    exit;
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function tableColumns(PDO $pdo, string $table): array {
    $stmt = $pdo->prepare("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $stmt->execute([':t' => $table]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
}

function pickFirst(array $cols, array $candidates): ?string {
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

function starts_with(string $haystack, string $prefix): bool {
    return strncmp($haystack, $prefix, strlen($prefix)) === 0;
}

// ---- Detect columns to avoid schema mismatch ----
$teamCols = tableColumns($pdo, 'ctf_teams');
$memCols  = tableColumns($pdo, 'ctf_team_members');
$payCols  = tableColumns($pdo, 'ctf_payments');

$colTeamId     = pickFirst($teamCols, ['id', 'team_id']);
$colTeamName   = pickFirst($teamCols, ['team_name', 'name', 'team']);
$colCaptain    = pickFirst($teamCols, ['captain_name', 'captain', 'leader_name', 'leader']);
$colEmail      = pickFirst($teamCols, ['email', 'captain_email', 'contact_email']);
$colPhone      = pickFirst($teamCols, ['phone', 'mobile', 'contact', 'contact_phone']);
$colCreatedAt  = pickFirst($teamCols, ['created_at', 'registered_at', 'created', 'reg_date']);

$colMemTeamId  = pickFirst($memCols, ['team_id']);
$colMemName    = pickFirst($memCols, ['member_name', 'name', 'full_name']);
$colMemEmail   = pickFirst($memCols, ['member_email', 'email']);

$colPayTeamId  = pickFirst($payCols, ['team_id']);
$colPayAmount  = pickFirst($payCols, ['amount', 'payment_amount', 'total']);
$colPayStatus  = pickFirst($payCols, ['status', 'payment_status']);
$colPayPaidAt  = pickFirst($payCols, ['paid_at', 'payment_date', 'created_at', 'date']);
$colPayProof   = pickFirst($payCols, ['proof_file', 'proof', 'receipt', 'slip', 'payment_proof', 'file_path']);

if (!$colTeamId || !$colTeamName) {
    http_response_code(500);
    exit("DB schema error: ctf_teams must have an id/team_id and a team_name/name column.");
}

// ---- Inputs ----
$action  = (string)($_GET['action'] ?? 'view'); // view|csv|download_proof
$q       = trim((string)($_GET['q'] ?? ''));
$payment = (string)($_GET['payment'] ?? 'all'); // all|paid|unpaid
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

// ---- Build WHERE ----
$where = [];
$params = [];

if ($q !== '') {
    $likeParts = [];
    $likeParts[] = "t.`{$colTeamName}` LIKE :q";
    if ($colCaptain) $likeParts[] = "t.`{$colCaptain}` LIKE :q";
    if ($colEmail)   $likeParts[] = "t.`{$colEmail}` LIKE :q";
    if ($colPhone)   $likeParts[] = "t.`{$colPhone}` LIKE :q";
    $where[] = "(" . implode(" OR ", $likeParts) . ")";
    $params[':q'] = "%{$q}%";
}

$hasPaymentsJoin = ($colPayTeamId !== null);
$statusExpr = ($hasPaymentsJoin && $colPayStatus) ? "COALESCE(p.`{$colPayStatus}`,'')" : "''";

if ($payment === 'paid') {
    $where[] = "{$statusExpr} IN ('paid','PAID','success','SUCCESS','completed','COMPLETED')";
} elseif ($payment === 'unpaid') {
    if ($hasPaymentsJoin) {
        $where[] = "({$statusExpr} NOT IN ('paid','PAID','success','SUCCESS','completed','COMPLETED') OR p.`{$colPayTeamId}` IS NULL)";
    } else {
        $where[] = "1=1";
    }
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ---- Query builder for teams (used by view and csv) ----
function fetchTeams(PDO $pdo, array $select, bool $hasPaymentsJoin, string $whereSql, array $params, string $colPayTeamId, string $colTeamId, ?int $limit=null, ?int $offset=null): array {
    $sql = "
        SELECT " . implode(", ", $select) . "
        FROM ctf_teams t
        " . ($hasPaymentsJoin ? "LEFT JOIN ctf_payments p ON p.`{$colPayTeamId}` = t.`{$colTeamId}`" : "") . "
        {$whereSql}
        ORDER BY t.`{$colTeamId}` DESC
    ";
    if ($limit !== null && $offset !== null) {
        $sql .= " LIMIT :limit OFFSET :offset";
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_STR);
    if ($limit !== null && $offset !== null) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ---- Action: Download payment proof (same page) ----
if ($action === 'download_proof') {
    $teamId = (int)($_GET['team_id'] ?? 0);
    if ($teamId <= 0) { http_response_code(400); exit("Invalid team_id"); }
    if (!$hasPaymentsJoin || !$colPayProof || !$colPayTeamId) { http_response_code(500); exit("Payments table/proof column not available."); }

    $stmt = $pdo->prepare("SELECT p.`{$colPayProof}` FROM ctf_payments p WHERE p.`{$colPayTeamId}` = :tid LIMIT 1");
    $stmt->execute([':tid' => $teamId]);
    $proof = (string)($stmt->fetchColumn() ?: '');
    if ($proof === '') { http_response_code(404); exit("No proof file found."); }

    // Restrict files to /uploads or /logs within project
    $baseUploads = realpath(__DIR__ . '/../uploads');
    $baseLogs    = realpath(__DIR__ . '/../logs');

    $path = $proof;
    if (!starts_with($path, '/')) {
        $path = realpath(__DIR__ . '/../' . ltrim($proof, '/')) ?: '';
    } else {
        $path = realpath($path) ?: '';
    }

    if ($path === '' || !is_file($path)) { http_response_code(404); exit("File not found."); }

    $ok = false;
    foreach ([$baseUploads, $baseLogs] as $base) {
        if ($base && starts_with($path, $base . DIRECTORY_SEPARATOR)) { $ok = true; break; }
    }
    if (!$ok) { http_response_code(403); exit("Forbidden path."); }

    $filename = basename($path);
    $mime = mime_content_type($path) ?: 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string)filesize($path));
    readfile($path);
    exit;
}

// ---- Action: Export CSV (same page) ----
if ($action === 'csv') {
    $select = [
        "t.`{$colTeamId}` AS team_id",
        "t.`{$colTeamName}` AS team_name",
    ];
    if ($colCaptain)   $select[] = "t.`{$colCaptain}` AS captain_name";
    if ($colEmail)     $select[] = "t.`{$colEmail}` AS email";
    if ($colPhone)     $select[] = "t.`{$colPhone}` AS phone";
    if ($colCreatedAt) $select[] = "t.`{$colCreatedAt}` AS registered_at";

    if ($hasPaymentsJoin) {
        if ($colPayAmount) $select[] = "p.`{$colPayAmount}` AS payment_amount";
        if ($colPayStatus) $select[] = "p.`{$colPayStatus}` AS payment_status";
        if ($colPayPaidAt) $select[] = "p.`{$colPayPaidAt}` AS paid_at";
        if ($colPayProof)  $select[] = "p.`{$colPayProof}` AS proof_file";
    }

    $rows = fetchTeams($pdo, $select, $hasPaymentsJoin, $whereSql, $params, (string)$colPayTeamId, (string)$colTeamId);

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

// ---- View: Count + paginated list ----
$sqlCount = "
SELECT COUNT(*) AS cnt
FROM ctf_teams t
" . ($hasPaymentsJoin ? "LEFT JOIN ctf_payments p ON p.`{$colPayTeamId}` = t.`{$colTeamId}`" : "") . "
{$whereSql}
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$select = [
    "t.`{$colTeamId}` AS id",
    "t.`{$colTeamName}` AS team_name",
];
if ($colCaptain)   $select[] = "t.`{$colCaptain}` AS captain_name";
if ($colEmail)     $select[] = "t.`{$colEmail}` AS email";
if ($colPhone)     $select[] = "t.`{$colPhone}` AS phone";
if ($colCreatedAt) $select[] = "t.`{$colCreatedAt}` AS created_at";

if ($hasPaymentsJoin) {
    if ($colPayAmount) $select[] = "p.`{$colPayAmount}` AS amount";
    if ($colPayStatus) $select[] = "p.`{$colPayStatus}` AS payment_status";
    if ($colPayPaidAt) $select[] = "p.`{$colPayPaidAt}` AS paid_at";
    if ($colPayProof)  $select[] = "p.`{$colPayProof}` AS proof_file";
}

$teams = fetchTeams($pdo, $select, $hasPaymentsJoin, $whereSql, $params, (string)$colPayTeamId, (string)$colTeamId, $perPage, $offset);

// Members for current page
$membersByTeam = [];
if ($teams && $colMemTeamId && $colMemName) {
    $teamIds = array_map(fn($r) => (int)$r['id'], $teams);
    $in = implode(',', array_fill(0, count($teamIds), '?'));

    $fields = ["`{$colMemTeamId}` AS team_id", "`{$colMemName}` AS member_name"];
    if ($colMemEmail) $fields[] = "`{$colMemEmail}` AS member_email";

    $m = $pdo->prepare("SELECT " . implode(',', $fields) . " FROM ctf_team_members WHERE `{$colMemTeamId}` IN ($in) ORDER BY 1 ASC");
    $m->execute($teamIds);

    while ($row = $m->fetch(PDO::FETCH_ASSOC)) {
        $tid = (int)$row['team_id'];
        $membersByTeam[$tid][] = $row;
    }
}

// Build base URL for pagination
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
          $status = (string)($t['payment_status'] ?? '');
          $isPaid = in_array($status, ['paid','PAID','success','SUCCESS','completed','COMPLETED'], true);
          $proof = (string)($t['proof_file'] ?? '');
          $proofLink = "/admin/view_registration.php?action=download_proof&team_id={$tid}";
        ?>
          <tr>
            <td><strong><?= h((string)($t['team_name'] ?? '')) ?></strong><div class="muted">ID: <?= $tid ?></div></td>
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

              <?php if ($proof !== ''): ?>
                <div style="margin-top:8px;">
                  <a class="btn secondary" href="<?= h($proofLink) ?>">Download proof</a>
                </div>
              <?php endif; ?>
            </td>
            <td><?= h((string)($t['created_at'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <div class="pagination">
        <a class="btn secondary" href="<?= h($base . $prev) ?>">&larr; Prev</a>
        <div class="muted" style="padding:10px 0;">Page <?= (int)$page ?> / <?= (int)$totalPages ?></div>
        <a class="btn secondary" href="<?= h($base . $next) ?>">Next &rarr;</a>
      </div>
    </div>
  </div>
</body>
</html>
