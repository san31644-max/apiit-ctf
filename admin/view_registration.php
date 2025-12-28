<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']);
if (!$loggedIn) { header("Location: /index.php"); exit; }

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function starts_with(string $s, string $prefix): bool { return strncmp($s, $prefix, strlen($prefix)) === 0; }

/**
 * =========================
 * IMPORTANT: CONFIGURE THIS
 * =========================
 * UPLOADS_FS_BASE must be the REAL folder on disk that CONTAINS the "uploads/" folder.
 */
define('UPLOADS_FS_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..')); // change if uploads is in /public
define('UPLOADS_URL_BASE', '/uploads'); // only used for showing public URL (optional)

// ---- Inputs ----
$action   = (string)($_GET['action'] ?? 'view'); // view|csv|receipt
$q        = trim((string)($_GET['q'] ?? ''));
$status   = (string)($_GET['status'] ?? 'all'); // all|pending|approved|rejected
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// ---------- Receipt helper: map DB path -> absolute FS path ----------
function receipt_fs_path(string $dbPath): string {
    $dbPath = ltrim($dbPath, '/');
    if (!starts_with($dbPath, 'uploads/')) {
        $dbPath = 'uploads/' . $dbPath;
    }
    $base = rtrim((string)UPLOADS_FS_BASE, DIRECTORY_SEPARATOR);
    return $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dbPath);
}

// ---------- Receipt helper: map DB path -> public URL ----------
function receipt_public_url(string $dbPath): string {
    $dbPath = ltrim($dbPath, '/');
    if (starts_with($dbPath, 'uploads/')) {
        $suffix = substr($dbPath, strlen('uploads')); // begins with "/..."
        return rtrim((string)UPLOADS_URL_BASE, '/') . $suffix;
    }
    return rtrim((string)UPLOADS_URL_BASE, '/') . '/' . $dbPath;
}

// ---- Action: stream receipt (inline or download) ----
if ($action === 'receipt') {
    $teamId = (int)($_GET['team_id'] ?? 0);
    $mode   = (string)($_GET['mode'] ?? 'download'); // download|inline
    if ($teamId <= 0) { http_response_code(400); exit("Invalid team_id"); }

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
        exit("No receipt found.");
    }

    $dbPath = (string)$row['receipt_file'];
    $orig   = (string)($row['receipt_original_name'] ?? '');
    $mime   = (string)($row['receipt_mime'] ?? '');

    $fsPath = receipt_fs_path($dbPath);

    // --- Strong security: allow only inside UPLOADS_FS_BASE/uploads/ ---
    $uploadsBaseCandidate = rtrim((string)UPLOADS_FS_BASE, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uploads';
    $uploadsBaseReal = realpath($uploadsBaseCandidate);
    if ($uploadsBaseReal === false) {
        http_response_code(500);
        exit("Uploads base folder not found on server. Checked: " . h($uploadsBaseCandidate));
    }
    $uploadsBaseReal = rtrim($uploadsBaseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    $realFs = realpath($fsPath) ?: '';
    if ($realFs === '' || !is_file($realFs)) {
        http_response_code(404);
        exit("Receipt file not found on server. Checked: " . h($fsPath));
    }

    $realFsNorm = rtrim($realFs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!starts_with($realFsNorm, $uploadsBaseReal)) {
        http_response_code(403);
        exit("Forbidden path.");
    }

    $filename = $orig !== '' ? $orig : basename($realFs);
    if ($mime === '') $mime = mime_content_type($realFs) ?: 'application/octet-stream';

    while (ob_get_level()) { ob_end_clean(); }

    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');

    $disp = ($mode === 'inline') ? 'inline' : 'attachment';
    $safeName = str_replace(["\r", "\n", '"'], '', $filename);

    header('Content-Disposition: ' . $disp . '; filename="' . $safeName . '"');
    header('Content-Length: ' . (string)filesize($realFs));

    readfile($realFs);
    exit;
}

// ---- Build WHERE ----
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(
        t.team_name LIKE :q OR
        t.university_name LIKE :q OR
        t.leader_name LIKE :q OR
        t.leader_email LIKE :q OR
        t.leader_phone LIKE :q OR
        t.contact_name LIKE :q OR
        t.contact_email LIKE :q OR
        t.contact_phone LIKE :q
    )";
    $params[':q'] = "%{$q}%";
}

if ($status !== 'all') {
    $where[] = "COALESCE(p.status, 'pending') = :st";
    $params[':st'] = $status;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// ---- CSV export ----
if ($action === 'csv') {
    $sql = "
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
      t.team_size,
      t.fee_per_student,
      t.team_total_fee,
      COALESCE(p.status, 'pending') AS payment_status,
      p.amount,
      p.currency,
      p.receipt_original_name,
      p.created_at AS payment_created_at,
      t.created_at AS registered_at
    FROM ctf_teams t
    LEFT JOIN ctf_payments p ON p.id = (
      SELECT id FROM ctf_payments
      WHERE team_id = t.id
      ORDER BY id DESC
      LIMIT 1
    )
    {$whereSql}
    ORDER BY t.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="ctf_registrations.csv"');
    $out = fopen('php://output', 'w');
    if (!$rows) {
        fputcsv($out, ['team_id']);
    } else {
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $r) fputcsv($out, $r);
    }
    fclose($out);
    exit;
}

// ---- Count for pagination ----
$sqlCount = "
SELECT COUNT(*)
FROM ctf_teams t
LEFT JOIN ctf_payments p ON p.id = (
  SELECT id FROM ctf_payments
  WHERE team_id = t.id
  ORDER BY id DESC
  LIMIT 1
)
{$whereSql}
";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

// ---- Main data ----
$sql = "
SELECT
  t.id,
  t.university_name,
  t.team_name,
  t.leader_name,
  t.leader_email,
  t.leader_phone,
  t.contact_name,
  t.contact_email,
  t.contact_phone,
  t.team_size,
  t.fee_per_student,
  t.team_total_fee,
  t.created_at,

  p.id AS payment_id,
  p.amount,
  p.currency,
  COALESCE(p.status,'pending') AS payment_status,
  p.admin_note,
  p.receipt_file,
  p.receipt_original_name,
  p.receipt_mime,
  p.created_at AS payment_created_at
FROM ctf_teams t
LEFT JOIN ctf_payments p ON p.id = (
  SELECT id
  FROM ctf_payments
  WHERE team_id = t.id
  ORDER BY id DESC
  LIMIT 1
)
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
    $m = $pdo->prepare("
        SELECT team_id, member_no, member_name, member_email, member_phone
        FROM ctf_team_members
        WHERE team_id IN ($in)
        ORDER BY team_id ASC, member_no ASC
    ");
    $m->execute($teamIds);
    while ($row = $m->fetch(PDO::FETCH_ASSOC)) {
        $membersByTeam[(int)$row['team_id']][] = $row;
    }
}

function pillClass(string $st): string {
    $st = strtolower(trim($st));
    return match ($st) {
        'approved' => 'pill approved',
        'rejected' => 'pill rejected',
        default    => 'pill pending',
    };
}
function pillText(string $st): string {
    $st = strtolower(trim($st));
    return match ($st) {
        'approved' => 'APPROVED',
        'rejected' => 'REJECTED',
        default    => 'PENDING',
    };
}

$base = "/admin/view_registration.php?q=" . urlencode($q) . "&status=" . urlencode($status) . "&page=";
$csvLink = "/admin/view_registration.php?action=csv&q=" . urlencode($q) . "&status=" . urlencode($status);

// For JS: provide members data for only teams on this page
$membersJson = json_encode($membersByTeam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Atlantis Registry • CTF Admin</title>
<style>
  :root{
    --bg0:#050814; --bg1:#070c1c;
    --stroke:rgba(255,255,255,.10);
    --text:#e8f0ff; --muted:rgba(232,240,255,.68);
    --aqua:#48f1ff; --good:#22c55e; --bad:#ef4444; --warn:#f59e0b;
    --shadow:0 18px 55px rgba(0,0,0,.55);
  }
  *{box-sizing:border-box}
  body{
    margin:0; color:var(--text);
    font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:
      radial-gradient(1200px 600px at 20% -10%, rgba(72,241,255,.18), transparent 60%),
      radial-gradient(900px 480px at 90% 10%, rgba(245,158,11,.10), transparent 55%),
      radial-gradient(800px 520px at 60% 110%, rgba(72,241,255,.12), transparent 60%),
      linear-gradient(180deg, var(--bg0), var(--bg1));
    min-height:100vh;
  }
  .wrap{max-width:1320px;margin:26px auto;padding:0 16px;}
  .topbar{
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    padding:16px 18px;border:1px solid var(--stroke);border-radius:18px;
    background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(255,255,255,.03));
    box-shadow:var(--shadow);
  }
  .brand h1{margin:0;font-size:18px;letter-spacing:.08em;text-transform:uppercase}
  .brand .sub{color:var(--muted);font-size:13px;margin-top:2px}
  .panel{
    margin-top:14px;border:1px solid var(--stroke);border-radius:18px;
    background:rgba(255,255,255,.04);box-shadow:var(--shadow);overflow:hidden;
  }
  .panel-head{
    padding:14px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between;
    border-bottom:1px solid rgba(255,255,255,.08);
    background:linear-gradient(180deg, rgba(0,0,0,.20), rgba(0,0,0,.06));
  }
  .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  input,select,textarea{
    background:rgba(0,0,0,.24);
    border:1px solid rgba(255,255,255,.14);
    color:var(--text);
    border-radius:14px;padding:10px 12px;outline:none;
  }
  input{min-width:320px}
  select{min-width:180px}
  textarea{width:100%;min-height:64px;resize:vertical}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 12px;border-radius:14px;border:1px solid rgba(255,255,255,.14);
    background:rgba(72,241,255,.14);color:var(--text);
    text-decoration:none;cursor:pointer;
  }
  .btn.secondary{background:rgba(255,255,255,.06)}
  .btn.good{background:rgba(34,197,94,.14)}
  .btn.bad{background:rgba(239,68,68,.14)}
  .btn.small{padding:8px 10px;border-radius:12px;font-size:13px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.08);vertical-align:top}
  th{font-size:12px;text-transform:uppercase;letter-spacing:.08em;color:rgba(232,240,255,.70);text-align:left;background:rgba(0,0,0,.12);}
  .muted{color:var(--muted);font-size:13px}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  .pill{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;font-size:12px;border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.22);letter-spacing:.06em;}
  .pill:before{content:"";width:8px;height:8px;border-radius:50%}
  .pill.pending{color:#ffd48a}.pill.pending:before{background:var(--warn)}
  .pill.approved{color:#8bffb7}.pill.approved:before{background:var(--good)}
  .pill.rejected{color:#ff9aa8}.pill.rejected:before{background:var(--bad)}
  .pagination{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;padding:14px 16px}

  /* Receipt modal */
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.6);z-index:50}
  .modal.on{display:flex}
  .modal-card{width:min(980px, 96vw);border-radius:18px;border:1px solid rgba(255,255,255,.14);background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(0,0,0,.22));box-shadow:var(--shadow);overflow:hidden;}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.10)}
  .modal-body{padding:12px 14px}
  .preview{width:100%;height:70vh;border:1px solid rgba(255,255,255,.10);border-radius:14px;background:rgba(0,0,0,.25);}

  /* Members drawer modal (fixes overlay issue) */
  .drawer{position:fixed;inset:0;display:none;z-index:60;background:rgba(0,0,0,.62)}
  .drawer.on{display:block}
  .drawer-panel{
    position:absolute;top:0;right:0;height:100%;
    width:min(520px, 96vw);
    border-left:1px solid rgba(255,255,255,.14);
    background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(0,0,0,.35));
    box-shadow:var(--shadow);
    padding:14px;
    overflow:auto;
    transform:translateX(0);
  }
  .drawer-top{
    display:flex;align-items:flex-start;justify-content:space-between;gap:10px;
    padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.10);
  }
  .drawer-title{font-weight:800;letter-spacing:.02em}
  .drawer-sub{color:var(--muted);font-size:13px;margin-top:4px}
  .chip{
    display:inline-flex;align-items:center;gap:8px;
    padding:6px 10px;border-radius:999px;
    border:1px solid rgba(255,255,255,.12);
    background:rgba(0,0,0,.20);
    font-size:12px;color:rgba(232,240,255,.78);
  }
  .member-list{margin-top:12px;display:grid;gap:10px}
  .member-card{
    border:1px solid rgba(255,255,255,.12);
    border-radius:16px;
    background:rgba(255,255,255,.04);
    padding:10px 12px;
  }
  .member-name{font-weight:750}
  .member-meta{color:var(--muted);font-size:13px;margin-top:4px;word-break:break-word}
  .member-no{margin-top:8px}
  .divider{height:1px;background:rgba(255,255,255,.08);margin:10px 0}

  @media (max-width: 720px){
    input{min-width:220px}
    th:nth-child(2), td:nth-child(2){min-width:240px}
  }
</style>
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <div class="brand">
      <h1>Atlantis Registry</h1>
      <div class="sub">CTF Admin Console • Payments • Receipts • Members</div>
      <div class="sub">Uploads FS base: <span class="mono"><?= h((string)UPLOADS_FS_BASE) ?></span></div>
    </div>
    <div class="muted">Total teams: <span class="mono"><?= (int)$total ?></span> • Page <span class="mono"><?= (int)$page ?>/<?= (int)$totalPages ?></span></div>
  </div>

  <div class="panel">
    <div class="panel-head">
      <form class="filters" method="get">
        <input type="text" name="q" value="<?= h($q) ?>" placeholder="Search team / university / leader / contact / email / phone">
        <select name="status">
          <option value="all" <?= $status==='all'?'selected':'' ?>>Status: All</option>
          <option value="pending" <?= $status==='pending'?'selected':'' ?>>Pending</option>
          <option value="approved" <?= $status==='approved'?'selected':'' ?>>Approved</option>
          <option value="rejected" <?= $status==='rejected'?'selected':'' ?>>Rejected</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <a class="btn secondary" href="/admin/view_registration.php">Reset</a>
      </form>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn secondary" href="<?= h($csvLink) ?>">Export CSV</a>
      </div>
    </div>

    <div style="overflow:auto">
      <table>
        <thead>
        <tr>
          <th>Team</th>
          <th>Leader + Contact</th>
          <th>Members</th>
          <th>Payment</th>
          <th>Receipt</th>
          <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$teams): ?>
          <tr><td colspan="6" class="muted">No teams found.</td></tr>
        <?php endif; ?>

        <?php foreach ($teams as $t):
          $tid = (int)$t['id'];
          $members = $membersByTeam[$tid] ?? [];
          $payStatus = (string)($t['payment_status'] ?? 'pending');
          $receiptFile = (string)($t['receipt_file'] ?? '');
          $receiptName = (string)($t['receipt_original_name'] ?? '');

          $inlineLink = "/admin/view_registration.php?action=receipt&mode=inline&team_id={$tid}";
          $downloadLink = "/admin/view_registration.php?action=receipt&mode=download&team_id={$tid}";
          $publicUrl = $receiptFile !== '' ? receipt_public_url($receiptFile) : '';
        ?>
        <tr>
          <td>
            <div style="font-weight:700; font-size:15px"><?= h((string)$t['team_name']) ?></div>
            <div class="muted"><?= h((string)$t['university_name']) ?></div>
            <div class="muted">Team ID: <span class="mono"><?= $tid ?></span> • Size: <span class="mono"><?= (int)($t['team_size'] ?? 0) ?></span></div>
            <div class="muted">Total Fee: <span class="mono"><?= h((string)($t['team_total_fee'] ?? '')) ?></span></div>
            <div class="muted">Registered: <span class="mono"><?= h((string)($t['created_at'] ?? '')) ?></span></div>
          </td>

          <td>
            <div><strong><?= h((string)$t['leader_name']) ?></strong> <span class="muted">(Leader)</span></div>
            <div class="muted"><?= h((string)$t['leader_email']) ?> • <?= h((string)$t['leader_phone']) ?></div>

            <div style="margin-top:8px"><strong><?= h((string)$t['contact_name']) ?></strong> <span class="muted">(Contact)</span></div>
            <div class="muted"><?= h((string)$t['contact_email']) ?> • <?= h((string)$t['contact_phone']) ?></div>
          </td>

          <td>
            <?php if (count($members) > 0): ?>
              <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                <span class="chip"><?= (int)count($members) ?> participant(s)</span>
                <button class="btn secondary small" type="button"
                        data-team="<?= $tid ?>"
                        data-teamname="<?= h((string)$t['team_name']) ?>"
                        data-uni="<?= h((string)$t['university_name']) ?>"
                        onclick="openMembers(this)">
                  View
                </button>
              </div>
              <div class="muted" style="margin-top:8px">Opens in a side panel (no table overlap)</div>
            <?php else: ?>
              <div class="muted">No members found.</div>
            <?php endif; ?>
          </td>

          <td>
            <span class="<?= h(pillClass($payStatus)) ?>"><?= h(pillText($payStatus)) ?></span>
            <div class="muted" style="margin-top:10px">
              Amount: <span class="mono"><?= h((string)($t['amount'] ?? '')) ?> <?= h((string)($t['currency'] ?? '')) ?></span><br>
              Time: <span class="mono"><?= h((string)($t['payment_created_at'] ?? '')) ?></span><br>
              Note: <span class="mono"><?= h((string)($t['admin_note'] ?? '—')) ?></span>
            </div>
          </td>

          <td>
            <?php if ($receiptFile !== ''): ?>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn secondary small" type="button"
                        data-inline="<?= h($inlineLink) ?>"
                        data-name="<?= h($receiptName !== '' ? $receiptName : 'Receipt') ?>"
                        onclick="openReceipt(this)">Preview</button>
                <a class="btn secondary small" href="<?= h($downloadLink) ?>">Download</a>
              </div>
              <div class="muted" style="margin-top:8px"><?= h($receiptName) ?></div>
              <div class="muted" style="margin-top:6px">DB path: <span class="mono"><?= h($receiptFile) ?></span></div>
              <div class="muted" style="margin-top:6px">Public url: <span class="mono"><?= h($publicUrl) ?></span></div>
            <?php else: ?>
              <div class="muted">No receipt uploaded</div>
            <?php endif; ?>
          </td>

          <td>
            <form method="post" action="/admin/payment_action.php" style="display:grid;gap:10px">
              <input type="hidden" name="team_id" value="<?= $tid ?>">
              <textarea name="admin_note" placeholder="Admin note (optional)"><?= h((string)($t['admin_note'] ?? '')) ?></textarea>
              <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button class="btn good" name="do" value="approve" type="submit">Approve</button>
                <button class="btn bad" name="do" value="reject" type="submit">Reject</button>
              </div>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="pagination">
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn secondary" href="<?= h($base . max(1, $page-1)) ?>">&larr; Prev</a>
        <a class="btn secondary" href="<?= h($base . min($totalPages, $page+1)) ?>">Next &rarr;</a>
      </div>
      <div class="muted">Showing <?= count($teams) ?> of <?= (int)$total ?> teams</div>
    </div>
  </div>
</div>

<!-- Receipt Preview Modal -->
<div class="modal" id="receiptModal" aria-hidden="true">
  <div class="modal-card" role="dialog" aria-modal="true">
    <div class="modal-head">
      <div id="receiptTitle" style="font-weight:700">Receipt</div>
      <button class="btn secondary small" type="button" onclick="closeReceipt()">Close</button>
    </div>
    <div class="modal-body">
      <iframe class="preview" id="receiptFrame" src=""></iframe>
      <div class="muted" style="margin-top:10px">
        Preview uses secure inline streaming (no need for /uploads to be public).
      </div>
    </div>
  </div>
</div>

<!-- Members Drawer -->
<div class="drawer" id="membersDrawer" aria-hidden="true">
  <div class="drawer-panel" role="dialog" aria-modal="true">
    <div class="drawer-top">
      <div>
        <div class="drawer-title" id="membersTitle">Members</div>
        <div class="drawer-sub" id="membersSub">—</div>
      </div>
      <button class="btn secondary small" type="button" onclick="closeMembers()">Close</button>
    </div>

    <div class="divider"></div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <span class="chip" id="membersCountChip">0 participant(s)</span>
      <input id="memberSearch" type="text" placeholder="Search member name / email / phone" style="flex:1;min-width:220px" oninput="renderMembers()">
    </div>

    <div class="member-list" id="membersList" style="margin-top:12px"></div>
  </div>
</div>

<script>
  // Members data for this page only (prevents extra queries + avoids table overlay)
  const MEMBERS = <?= $membersJson ?>;

  // Receipt modal
  const receiptModal = document.getElementById('receiptModal');
  const receiptFrame = document.getElementById('receiptFrame');
  const receiptTitle = document.getElementById('receiptTitle');

  function openReceipt(btn){
    const url = btn.getAttribute('data-inline');
    const name = btn.getAttribute('data-name') || 'Receipt';
    receiptTitle.textContent = name;
    receiptFrame.src = url;
    receiptModal.classList.add('on');
    receiptModal.setAttribute('aria-hidden', 'false');
  }
  function closeReceipt(){
    receiptFrame.src = '';
    receiptModal.classList.remove('on');
    receiptModal.setAttribute('aria-hidden', 'true');
  }
  receiptModal.addEventListener('click', (e) => { if (e.target === receiptModal) closeReceipt(); });

  // Members drawer
  const drawer = document.getElementById('membersDrawer');
  const membersTitle = document.getElementById('membersTitle');
  const membersSub = document.getElementById('membersSub');
  const membersList = document.getElementById('membersList');
  const membersCountChip = document.getElementById('membersCountChip');
  const memberSearch = document.getElementById('memberSearch');

  let activeTeamId = null;
  let activeMembers = [];

  function openMembers(btn){
    const teamId = btn.getAttribute('data-team');
    const teamName = btn.getAttribute('data-teamname') || 'Team';
    const uni = btn.getAttribute('data-uni') || '';

    activeTeamId = teamId;
    activeMembers = (MEMBERS[teamId] || []);
    membersTitle.textContent = `Members • ${teamName}`;
    membersSub.textContent = uni ? uni : `Team ID: ${teamId}`;
    membersCountChip.textContent = `${activeMembers.length} participant(s)`;

    memberSearch.value = '';
    renderMembers();

    drawer.classList.add('on');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }

  function closeMembers(){
    drawer.classList.remove('on');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    activeTeamId = null;
    activeMembers = [];
    membersList.innerHTML = '';
  }

  function renderMembers(){
    const q = (memberSearch.value || '').trim().toLowerCase();
    let list = activeMembers;

    if (q !== '') {
      list = activeMembers.filter(m => {
        const name = (m.member_name || '').toLowerCase();
        const email = (m.member_email || '').toLowerCase();
        const phone = (m.member_phone || '').toLowerCase();
        const no = String(m.member_no || '');
        return name.includes(q) || email.includes(q) || phone.includes(q) || no.includes(q);
      });
    }

    if (!list.length) {
      membersList.innerHTML = `<div class="muted">No matching members.</div>`;
      return;
    }

    membersList.innerHTML = list.map(m => {
      const name = escapeHtml(m.member_name || '—');
      const email = escapeHtml(m.member_email || '—');
      const phone = escapeHtml(m.member_phone || '—');
      const no = escapeHtml(String(m.member_no ?? '—'));
      return `
        <div class="member-card">
          <div class="member-name">${name}</div>
          <div class="member-meta">${email}</div>
          <div class="member-meta">${phone}</div>
          <div class="member-no"><span class="chip">#${no}</span></div>
        </div>
      `;
    }).join('');
  }

  drawer.addEventListener('click', (e) => { if (e.target === drawer) closeMembers(); });

  // ESC closes whichever is open
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (drawer.classList.contains('on')) closeMembers();
      if (receiptModal.classList.contains('on')) closeReceipt();
    }
  });

  function escapeHtml(str){
    return String(str).replace(/[&<>"']/g, (c) => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));
  }
</script>
</body>
</html>
