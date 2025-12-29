<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function starts_with(string $s, string $prefix): bool { return strncmp($s, $prefix, strlen($prefix)) === 0; }

// ---- Inputs ----
$action   = (string)($_GET['action'] ?? 'view'); // view|csv|receipt|toggle_checked
$q        = trim((string)($_GET['q'] ?? ''));
$status   = (string)($_GET['status'] ?? 'all'); // all|pending|checked
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 15;
$offset   = ($page - 1) * $perPage;

// ---- AJAX detection ----
$isAjax = (
    (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || $action === 'toggle_checked'
);

// ---- Auth check ----
$loggedIn = !empty($_SESSION['user_id']) || !empty($_SESSION['username']) || !empty($_SESSION['logged_in']);
if (!$loggedIn) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Not logged in']);
        exit;
    }
    header("Location: /index.php");
    exit;
}

/**
 * =========================
 * IMPORTANT: CONFIGURE THIS
 * =========================
 * UPLOADS_FS_BASE must be the REAL folder on disk that CONTAINS the "uploads/" folder.
 */
define('UPLOADS_FS_BASE', realpath(__DIR__ . '/..') ?: (__DIR__ . '/..'));
define('UPLOADS_URL_BASE', '/uploads');

// ---------- Receipt helper: map DB path -> absolute FS path ----------
function receipt_fs_path(string $dbPath): string {
    $dbPath = ltrim($dbPath, '/');
    if (!starts_with($dbPath, 'uploads/')) $dbPath = 'uploads/' . $dbPath;
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

// ============================
// ACTION: toggle checked (AJAX)
// ============================
if ($action === 'toggle_checked') {
    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $teamId = (int)($_POST['team_id'] ?? 0);
    if ($teamId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid team_id']);
        exit;
    }

    // Find latest payment row for this team
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(status,'pending') AS status
        FROM ctf_payments
        WHERE team_id = :tid
        ORDER BY id DESC
        LIMIT 1
    ");
    $stmt->execute([':tid' => $teamId]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$p) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'No payment record found for this team']);
        exit;
    }

    $paymentId = (int)$p['id'];
    $current   = strtolower(trim((string)$p['status']));
    $next      = ($current === 'checked') ? 'pending' : 'checked';

    $u = $pdo->prepare("UPDATE ctf_payments SET status = :st WHERE id = :id");
    $u->execute([':st' => $next, ':id' => $paymentId]);

    echo json_encode(['ok' => true, 'status' => $next]);
    exit;
}

// ============================
// ACTION: stream receipt
// ============================
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

    // Strong security: allow only inside UPLOADS_FS_BASE/uploads/
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
$membersJson = json_encode($membersByTeam, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

function statusBadge(string $st): array {
    $st = strtolower(trim($st));
    return $st === 'checked'
        ? ['Checked', 'badge checked']
        : ['Not Checked', 'badge pending'];
}

$base = "/admin/view_registration.php?q=" . urlencode($q) . "&status=" . urlencode($status) . "&page=";
$csvLink = "/admin/view_registration.php?action=csv&q=" . urlencode($q) . "&status=" . urlencode($status);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Atlantis Registry • CTF Admin</title>
<style>
  :root{
    --bg0:#030515; --bg1:#060a1f;
    --stroke:rgba(255,255,255,.12);
    --text:#eaf2ff; --muted:rgba(234,242,255,.72);
    --aqua:#48f1ff; --good:#22c55e; --warn:#f59e0b;
    --shadow:0 22px 80px rgba(0,0,0,.60);
  }
  *{box-sizing:border-box}
  body{
    margin:0;color:var(--text);
    font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Arial;
    background:
      radial-gradient(900px 500px at 15% -10%, rgba(72,241,255,.22), transparent 60%),
      radial-gradient(700px 420px at 90% 5%, rgba(245,184,75,.14), transparent 55%),
      radial-gradient(900px 620px at 55% 110%, rgba(0,212,255,.12), transparent 60%),
      linear-gradient(180deg, var(--bg0), var(--bg1));
    min-height:100vh;
  }
  .wrap{max-width:1320px;margin:26px auto;padding:0 16px;}
  .topbar{
    display:flex;align-items:center;justify-content:space-between;gap:16px;
    padding:16px 18px;border:1px solid var(--stroke);border-radius:22px;
    background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.20));
    box-shadow:var(--shadow);
  }
  .brand h1{margin:0;font-size:18px;letter-spacing:.14em;text-transform:uppercase}
  .brand .sub{color:var(--muted);font-size:13px;margin-top:4px}
  .panel{margin-top:14px;border:1px solid var(--stroke);border-radius:22px;background:rgba(255,255,255,.05);box-shadow:var(--shadow);overflow:hidden}
  .panel-head{
    padding:14px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between;
    border-bottom:1px solid rgba(255,255,255,.10);
    background:linear-gradient(180deg, rgba(0,0,0,.30), rgba(0,0,0,.10));
  }
  .filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
  input,select{
    background:rgba(0,0,0,.25);border:1px solid rgba(255,255,255,.14);
    color:var(--text);border-radius:16px;padding:10px 12px;outline:none;
  }
  input{min-width:320px}
  select{min-width:190px}
  .btn{
    display:inline-flex;align-items:center;justify-content:center;gap:8px;
    padding:10px 12px;border-radius:16px;border:1px solid rgba(255,255,255,.14);
    background:rgba(72,241,255,.14);color:var(--text);text-decoration:none;cursor:pointer;
  }
  .btn.secondary{background:rgba(255,255,255,.07)}
  .btn.small{padding:8px 10px;border-radius:14px;font-size:13px}
  .cards{padding:14px;display:grid;gap:12px}
  .card{
    border:1px solid rgba(255,255,255,.10);
    border-radius:20px;
    background:linear-gradient(180deg, rgba(255,255,255,.06), rgba(0,0,0,.18));
    padding:14px;
    display:grid;
    grid-template-columns: 1.2fr 1.1fr .8fr .9fr;
    gap:14px;
  }
  .title{font-weight:900;font-size:16px}
  .muted{color:var(--muted);font-size:13px}
  .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace}
  .badge{
    display:inline-flex;align-items:center;gap:8px;padding:8px 10px;border-radius:999px;
    font-size:12px;letter-spacing:.10em;text-transform:uppercase;
    border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.22);
    width:fit-content;
  }
  .badge:before{content:"";width:9px;height:9px;border-radius:50%}
  .badge.pending{color:#ffd48a}.badge.pending:before{background:var(--warn)}
  .badge.checked{color:#8bffb7}.badge.checked:before{background:var(--good)}
  .chip{
    display:inline-flex;align-items:center;gap:8px;padding:7px 10px;border-radius:999px;
    border:1px solid rgba(255,255,255,.12);background:rgba(0,0,0,.20);font-size:12px;color:rgba(234,242,255,.82)
  }
  .checkbtn{
    background:linear-gradient(135deg, rgba(72,241,255,.18), rgba(0,0,0,.10));
    border-color:rgba(72,241,255,.28);
  }
  .checkbtn.checked{
    background:linear-gradient(135deg, rgba(34,197,94,.18), rgba(0,0,0,.10));
    border-color:rgba(34,197,94,.28);
  }
  .pagination{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:space-between;padding:14px 16px;border-top:1px solid rgba(255,255,255,.10)}
  @media (max-width: 980px){ .card{grid-template-columns:1fr} input{min-width:220px} }

  /* Receipt modal */
  .modal{position:fixed;inset:0;display:none;align-items:center;justify-content:center;padding:16px;background:rgba(0,0,0,.64);z-index:60}
  .modal.on{display:flex}
  .modal-card{width:min(980px,96vw);border-radius:22px;border:1px solid rgba(255,255,255,.14);background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.26));overflow:hidden}
  .modal-head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.10)}
  .modal-body{padding:12px 14px}
  .preview{width:100%;height:72vh;border:1px solid rgba(255,255,255,.10);border-radius:16px;background:rgba(0,0,0,.25)}

  /* Members Drawer */
  .drawer{position:fixed;inset:0;display:none;z-index:70;background:rgba(0,0,0,.66)}
  .drawer.on{display:block}
  .drawer-panel{position:absolute;top:0;right:0;height:100%;width:min(520px,96vw);border-left:1px solid rgba(255,255,255,.14);background:linear-gradient(180deg, rgba(255,255,255,.08), rgba(0,0,0,.34));padding:14px;overflow:auto}
  .drawer-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;padding-bottom:12px;border-bottom:1px solid rgba(255,255,255,.10)}
  .member-list{margin-top:12px;display:grid;gap:10px}
  .member-card{border:1px solid rgba(255,255,255,.12);border-radius:18px;background:rgba(255,255,255,.05);padding:10px 12px}
  .member-name{font-weight:800}
  .member-meta{color:var(--muted);font-size:13px;margin-top:4px;word-break:break-word}
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
          <option value="pending" <?= $status==='pending'?'selected':'' ?>>Not Checked</option>
          <option value="checked" <?= $status==='checked'?'selected':'' ?>>Checked</option>
        </select>
        <button class="btn" type="submit">Filter</button>
        <a class="btn secondary" href="/admin/view_registration.php">Reset</a>
      </form>

      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="btn secondary" href="<?= h($csvLink) ?>">Export CSV</a>
      </div>
    </div>

    <div class="cards">
      <?php if (!$teams): ?>
        <div class="muted">No teams found.</div>
      <?php endif; ?>

      <?php foreach ($teams as $t):
        $tid = (int)$t['id'];
        $members = $membersByTeam[$tid] ?? [];
        $payStatus = (string)($t['payment_status'] ?? 'pending');
        [$badgeText, $badgeClass] = statusBadge($payStatus);

        $receiptFile = (string)($t['receipt_file'] ?? '');
        $receiptName = (string)($t['receipt_original_name'] ?? '');

        $inlineLink   = "?action=receipt&mode=inline&team_id={$tid}";
        $downloadLink = "?action=receipt&mode=download&team_id={$tid}";
        $publicUrl    = $receiptFile !== '' ? receipt_public_url($receiptFile) : '';

        $isChecked = (strtolower(trim($payStatus)) === 'checked');
      ?>
      <div class="card" id="teamCard<?= $tid ?>">
        <div>
          <div class="title"><?= h((string)$t['team_name']) ?></div>
          <div class="muted"><?= h((string)$t['university_name']) ?></div>
          <div class="muted" style="margin-top:8px">
            Team ID: <span class="mono"><?= $tid ?></span> • Size: <span class="mono"><?= (int)($t['team_size'] ?? 0) ?></span><br>
            Total Fee: <span class="mono"><?= h((string)($t['team_total_fee'] ?? '')) ?></span><br>
            Registered: <span class="mono"><?= h((string)($t['created_at'] ?? '')) ?></span>
          </div>

          <div style="margin-top:10px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <span class="<?= h($badgeClass) ?>" id="statusBadge<?= $tid ?>"><?= h($badgeText) ?></span>

            <button class="btn small checkbtn <?= $isChecked ? 'checked' : '' ?>"
                    type="button"
                    data-team-id="<?= $tid ?>"
                    onclick="toggleChecked(this)"
                    id="checkBtn<?= $tid ?>">
              <?= $isChecked ? '✓ Checked' : '○ Mark as Checked' ?>
            </button>
          </div>
        </div>

        <div>
          <div style="font-weight:800">Leader</div>
          <div class="muted"><?= h((string)$t['leader_name']) ?></div>
          <div class="muted"><?= h((string)$t['leader_email']) ?> • <?= h((string)$t['leader_phone']) ?></div>

          <div style="height:1px;background:rgba(255,255,255,.10);margin:10px 0"></div>

          <div style="font-weight:800">Contact</div>
          <div class="muted"><?= h((string)$t['contact_name']) ?></div>
          <div class="muted"><?= h((string)$t['contact_email']) ?> • <?= h((string)$t['contact_phone']) ?></div>
        </div>

        <div>
          <div style="font-weight:800">Members</div>
          <?php if (count($members) > 0): ?>
            <div style="margin-top:8px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
              <span class="chip"><?= (int)count($members) ?> participant(s)</span>
              <button class="btn secondary small" type="button"
                      data-team="<?= $tid ?>"
                      data-teamname="<?= h((string)$t['team_name']) ?>"
                      data-uni="<?= h((string)$t['university_name']) ?>"
                      onclick="openMembers(this)">View</button>
            </div>
            <div class="muted" style="margin-top:8px">Opens in a side panel</div>
          <?php else: ?>
            <div class="muted" style="margin-top:8px">No members found.</div>
          <?php endif; ?>
        </div>

        <div>
          <div style="font-weight:800">Payment</div>
          <div class="muted" style="margin-top:8px">
            Amount: <span class="mono"><?= h((string)($t['amount'] ?? '')) ?> <?= h((string)($t['currency'] ?? '')) ?></span><br>
            Time: <span class="mono"><?= h((string)($t['payment_created_at'] ?? '')) ?></span><br>
            Note: <span class="mono"><?= h((string)($t['admin_note'] ?? '—')) ?></span>
          </div>

          <div style="height:1px;background:rgba(255,255,255,.10);margin:10px 0"></div>

          <div style="font-weight:800">Receipt</div>
          <?php if ($receiptFile !== ''): ?>
            <div style="margin-top:8px;display:flex;gap:10px;flex-wrap:wrap">
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
            <div class="muted" style="margin-top:8px">No receipt uploaded</div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
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
      <div id="receiptTitle" style="font-weight:800">Receipt</div>
      <button class="btn secondary small" type="button" onclick="closeReceipt()">Close</button>
    </div>
    <div class="modal-body">
      <iframe class="preview" id="receiptFrame" src=""></iframe>
      <div class="muted" style="margin-top:10px">Preview uses secure inline streaming.</div>
    </div>
  </div>
</div>

<!-- Members Drawer -->
<div class="drawer" id="membersDrawer" aria-hidden="true">
  <div class="drawer-panel" role="dialog" aria-modal="true">
    <div class="drawer-top">
      <div>
        <div style="font-weight:900" id="membersTitle">Members</div>
        <div class="muted" id="membersSub">—</div>
      </div>
      <button class="btn secondary small" type="button" onclick="closeMembers()">Close</button>
    </div>

    <div style="height:1px;background:rgba(255,255,255,.10);margin:10px 0"></div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <span class="chip" id="membersCountChip">0 participant(s)</span>
      <input id="memberSearch" type="text" placeholder="Search member name / email / phone"
             style="flex:1;min-width:220px" oninput="renderMembers()">
    </div>

    <div class="member-list" id="membersList" style="margin-top:12px"></div>
  </div>
</div>

<script>
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
    document.body.style.overflow = 'hidden';
  }
  function closeReceipt(){
    receiptFrame.src = '';
    receiptModal.classList.remove('on');
    receiptModal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }
  receiptModal.addEventListener('click', (e) => { if (e.target === receiptModal) closeReceipt(); });

  // Members drawer
  const drawer = document.getElementById('membersDrawer');
  const membersTitle = document.getElementById('membersTitle');
  const membersSub = document.getElementById('membersSub');
  const membersList = document.getElementById('membersList');
  const membersCountChip = document.getElementById('membersCountChip');
  const memberSearch = document.getElementById('memberSearch');

  let activeMembers = [];

  function openMembers(btn){
    const teamId = btn.getAttribute('data-team');
    const teamName = btn.getAttribute('data-teamname') || 'Team';
    const uni = btn.getAttribute('data-uni') || '';

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
          <div class="member-meta"><span class="chip">#${no}</span></div>
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

  // Toggle checked (AJAX) — uses relative URL to avoid wrong-host issues
  async function toggleChecked(btn){
    const teamId = btn.getAttribute('data-team-id');
    if (!teamId) return;

    btn.disabled = true;
    const oldText = btn.textContent;
    btn.textContent = '… Updating';

    try{
      const form = new FormData();
      form.append('team_id', teamId);

      const res = await fetch('?action=toggle_checked', {
        method: 'POST',
        body: form,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const text = await res.text();
      let data;
      try { data = JSON.parse(text); }
      catch(e){
        console.error('Non-JSON response:', text);
        alert('Server returned non-JSON. Check console (F12).');
        throw e;
      }

      if (!data.ok) throw new Error(data.error || 'Update failed');

      const st = String(data.status || 'pending').toLowerCase();
      const badge = document.getElementById('statusBadge' + teamId);

      if (st === 'checked') {
        badge.className = 'badge checked';
        badge.textContent = 'Checked';
        btn.classList.add('checked');
        btn.textContent = '✓ Checked';
      } else {
        badge.className = 'badge pending';
        badge.textContent = 'Not Checked';
        btn.classList.remove('checked');
        btn.textContent = '○ Mark as Checked';
      }

    } catch(err){
      alert(err.message || 'Something went wrong');
      btn.textContent = oldText;
    } finally {
      btn.disabled = false;
    }
  }
</script>
</body>
</html>
