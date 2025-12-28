<?php
// register.php — 4-member team registration (Inter-University CTF)
// Saves to MySQL tables:
//   - ctf_teams
//   - ctf_team_members
//   - ctf_payments
// Uploads receipts to: uploads/receipts/

session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => false, // set true on HTTPS
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

require_once __DIR__ . "/includes/page_guard_json.php";
guard_page_json('register');

// ----------------- DB CONFIG -----------------
// IMPORTANT: Use your real DB username/password (NOT "pma")
const DB_HOST = '127.0.0.1';
const DB_NAME = 'new_apiit';
const DB_USER = 'YOUR_DB_USER';
const DB_PASS = 'YOUR_DB_PASSWORD';

// ----------------- FLASH -----------------
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error'] ?? '';
$old     = $_SESSION['old'] ?? [];
unset($_SESSION['success'], $_SESSION['error'], $_SESSION['old']);

// ----------------- HELPERS -----------------
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function old($key, $default=''){
  $o = $_SESSION['old'] ?? [];
  $v = $o[$key] ?? $default;
  return h($v);
}
function redirect_with_error($msg){
  $_SESSION['error'] = $msg;
  header("Location: register.php");
  exit;
}
function redirect_with_success($msg){
  $_SESSION['success'] = $msg;
  header("Location: register.php");
  exit;
}
function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $dsn = "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4";
  $pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}

// ----------------- HANDLE SUBMIT -----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Store old values for repopulation
  $_SESSION['old'] = [
    'university'   => trim($_POST['university'] ?? ''),
    'team_name'    => trim($_POST['team_name'] ?? ''),
    'leader_name'  => trim($_POST['leader_name'] ?? ''),
    'leader_email' => trim($_POST['leader_email'] ?? ''),
    'leader_phone' => trim($_POST['leader_phone'] ?? ''),
    'contact_name' => trim($_POST['contact_name'] ?? ''),
    'contact_email'=> trim($_POST['contact_email'] ?? ''),
    'contact_phone'=> trim($_POST['contact_phone'] ?? ''),
    'm1_name'      => trim($_POST['m1_name'] ?? ''),
    'm1_email'     => trim($_POST['m1_email'] ?? ''),
    'm1_phone'     => trim($_POST['m1_phone'] ?? ''),
    'm2_name'      => trim($_POST['m2_name'] ?? ''),
    'm2_email'     => trim($_POST['m2_email'] ?? ''),
    'm2_phone'     => trim($_POST['m2_phone'] ?? ''),
    'm3_name'      => trim($_POST['m3_name'] ?? ''),
    'm3_email'     => trim($_POST['m3_email'] ?? ''),
    'm3_phone'     => trim($_POST['m3_phone'] ?? ''),
    'm4_name'      => trim($_POST['m4_name'] ?? ''),
    'm4_email'     => trim($_POST['m4_email'] ?? ''),
    'm4_phone'     => trim($_POST['m4_phone'] ?? ''),
    'notes'        => trim($_POST['notes'] ?? ''),
  ];

  $errors = [];

  $req = function($key, $label) {
    $v = trim($_POST[$key] ?? '');
    return $v === '' ? "$label is required." : '';
  };
  $email = function($key, $label) {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') return '';
    return filter_var($v, FILTER_VALIDATE_EMAIL) ? '' : "$label must be a valid email.";
  };
  $phone = function($key, $label) {
    $v = trim($_POST[$key] ?? '');
    if ($v === '') return '';
    $digits = preg_replace('/\D+/', '', $v);
    return strlen($digits) >= 9 ? '' : "$label must be a valid phone number.";
  };

  // Core
  if ($m=$req('university','University name')) $errors[]=$m;
  if ($m=$req('team_name','Team name')) $errors[]=$m;

  // Leader
  if ($m=$req('leader_name','Team leader name')) $errors[]=$m;
  if ($m=$req('leader_email','Team leader email')) $errors[]=$m;
  if ($m=$email('leader_email','Team leader email')) $errors[]=$m;
  if ($m=$req('leader_phone','Team leader phone')) $errors[]=$m;
  if ($m=$phone('leader_phone','Team leader phone')) $errors[]=$m;

  // Contact
  if ($m=$req('contact_name','Contact person name')) $errors[]=$m;
  if ($m=$req('contact_email','Contact person email')) $errors[]=$m;
  if ($m=$email('contact_email','Contact person email')) $errors[]=$m;
  if ($m=$req('contact_phone','Contact person phone')) $errors[]=$m;
  if ($m=$phone('contact_phone','Contact person phone')) $errors[]=$m;

  // Members 1..4
  for ($i=1;$i<=4;$i++){
    if ($m=$req("m{$i}_name","Member {$i} name")) $errors[]=$m;
    if ($m=$req("m{$i}_email","Member {$i} email")) $errors[]=$m;
    if ($m=$email("m{$i}_email","Member {$i} email")) $errors[]=$m;
    if ($m=$req("m{$i}_phone","Member {$i} phone")) $errors[]=$m;
    if ($m=$phone("m{$i}_phone","Member {$i} phone")) $errors[]=$m;
  }

  // Unique emails
  $emailKeys = ['leader_email','contact_email','m1_email','m2_email','m3_email','m4_email'];
  $emails = [];
  foreach ($emailKeys as $k){
    $v = strtolower(trim($_POST[$k] ?? ''));
    if ($v !== '') $emails[] = $v;
  }
  if (count($emails) !== count(array_unique($emails))) {
    $errors[] = "Emails must be unique (duplicate email detected).";
  }

  // Receipt validation
  if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = "Payment receipt upload is required.";
  } else {
    $f = $_FILES['receipt'];
    $maxBytes = 5 * 1024 * 1024;
    if ($f['size'] > $maxBytes) $errors[] = "Receipt is too large (max 5MB).";

    $allowedExt = ['jpg','jpeg','png','pdf'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) $errors[] = "Receipt must be JPG, PNG, or PDF.";

    if (!class_exists('finfo')) {
      $errors[] = "Server error: PHP fileinfo extension is not enabled.";
    } else {
      $finfo = new finfo(FILEINFO_MIME_TYPE);
      $mime = $finfo->file($f['tmp_name']);
      $allowedMime = ['image/jpeg','image/png','application/pdf'];
      if ($mime && !in_array($mime, $allowedMime, true)) $errors[] = "Receipt file type is not allowed.";
    }
  }

  if (!empty($errors)) {
    $_SESSION['error'] = implode("<br>", array_map('h', $errors));
    header("Location: register.php");
    exit;
  }

  // ---------- Ensure upload directory exists & is writable ----------
  $uploadDir = __DIR__ . '/uploads/receipts';
  if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
      redirect_with_error("Server error: cannot create uploads/receipts directory.");
    }
  }
  if (!is_writable($uploadDir)) {
    redirect_with_error("Server error: uploads/receipts directory is not writable.");
  }

  // ---------- Save receipt file ----------
  $safeTeam = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $_POST['team_name'] ?? 'team');
  $safeUni  = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $_POST['university'] ?? 'uni');
  $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
  $filename = date('Ymd_His') . "_{$safeUni}_{$safeTeam}_" . bin2hex(random_bytes(4)) . "." . $ext;

  $dest = $uploadDir . '/' . $filename;
  if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $dest)) {
    redirect_with_error("Failed to upload receipt. Please try again.");
  }

  // ---------- SAVE TO MYSQL (ctf_teams, ctf_team_members, ctf_payments) ----------
  $receiptPath = "uploads/receipts/" . $filename;
  $receiptOrig = $_FILES['receipt']['name'];
  $receiptMime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['receipt']['tmp_name']) ?: '';

  $pdo = null;
  try {
    $pdo = db();
    $pdo->beginTransaction();

    // 1) Team
    $teamStmt = $pdo->prepare("
      INSERT INTO ctf_teams (
        university_name, team_name,
        leader_name, leader_email, leader_phone,
        contact_name, contact_email, contact_phone,
        team_size, fee_per_student, team_total_fee,
        notes, created_at, updated_at
      ) VALUES (
        :university_name, :team_name,
        :leader_name, :leader_email, :leader_phone,
        :contact_name, :contact_email, :contact_phone,
        :team_size, :fee_per_student, :team_total_fee,
        :notes, NOW(), NOW()
      )
    ");

    $teamStmt->execute([
      ':university_name'  => trim($_POST['university']),
      ':team_name'        => trim($_POST['team_name']),
      ':leader_name'      => trim($_POST['leader_name']),
      ':leader_email'     => trim($_POST['leader_email']),
      ':leader_phone'     => trim($_POST['leader_phone']),
      ':contact_name'     => trim($_POST['contact_name']),
      ':contact_email'    => trim($_POST['contact_email']),
      ':contact_phone'    => trim($_POST['contact_phone']),
      ':team_size'        => 4,
      ':fee_per_student'  => 1000,
      ':team_total_fee'   => 4000,
      ':notes'            => trim($_POST['notes'] ?? ''),
    ]);

    $teamId = (int)$pdo->lastInsertId();

    // 2) Members
    $memStmt = $pdo->prepare("
      INSERT INTO ctf_team_members (
        team_id, member_no, member_name, member_email, member_phone, created_at
      ) VALUES (
        :team_id, :member_no, :member_name, :member_email, :member_phone, NOW()
      )
    ");

    for ($i=1; $i<=4; $i++) {
      $memStmt->execute([
        ':team_id'       => $teamId,
        ':member_no'     => $i,
        ':member_name'   => trim($_POST["m{$i}_name"]),
        ':member_email'  => trim($_POST["m{$i}_email"]),
        ':member_phone'  => trim($_POST["m{$i}_phone"]),
      ]);
    }

    // 3) Payment
    $payStmt = $pdo->prepare("
      INSERT INTO ctf_payments (
        team_id, amount, currency,
        receipt_file, receipt_original_name, receipt_mime,
        status, admin_note, created_at
      ) VALUES (
        :team_id, :amount, :currency,
        :receipt_file, :receipt_original_name, :receipt_mime,
        :status, :admin_note, NOW()
      )
    ");

    $payStmt->execute([
      ':team_id'                => $teamId,
      ':amount'                 => 4000,
      ':currency'               => 'LKR',
      ':receipt_file'           => $receiptPath,
      ':receipt_original_name'  => $receiptOrig,
      ':receipt_mime'           => $receiptMime,
      ':status'                 => 'pending',
      ':admin_note'             => null,
    ]);

    $pdo->commit();

    unset($_SESSION['old']);
    redirect_with_success("Registration submitted! Your 4-member army is registered.");

  } catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    // Remove uploaded receipt so you don't get orphan files
    @unlink($dest);
    redirect_with_error("DB error: " . $e->getMessage());
  }
}

// restore old for form
$_SESSION['old'] = $old;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Register Your Army — Inter-University CTF</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{ --aqua:#38f7ff; --gold:#f5d27b; }

html, body{ height:100%; }
body{
  font-family:'Cinzel',serif;
  margin:0;
  min-height:100vh;
  color:#e6faff;
  background:#000;
  overflow-x:hidden;
  overflow-y:auto;
}

/* Background video stays fixed while page scrolls */
.video-bg{ position:fixed; inset:0; z-index:-5; overflow:hidden; background:#00101f; }
.video-bg video{ width:100%; height:100%; object-fit:cover; object-position:center; transform:scale(1.03); filter:saturate(1.05) contrast(1.05); }
.video-overlay{
  position:fixed; inset:0; z-index:-4; pointer-events:none;
  background:
    radial-gradient(900px 420px at 50% 12%, rgba(56,247,255,0.14), transparent 62%),
    linear-gradient(180deg, rgba(0,0,0,0.15), rgba(0,0,0,0.30));
}
.caustics{
  position:fixed; inset:0; z-index:-3; pointer-events:none;
  background:
    repeating-radial-gradient(circle at 30% 40%, rgba(56,247,255,.05) 0 2px, transparent 3px 14px),
    repeating-radial-gradient(circle at 70% 60%, rgba(255,255,255,.03) 0 1px, transparent 2px 18px);
  opacity:.32; mix-blend-mode:screen; animation: causticMove 7s linear infinite;
}
@keyframes causticMove{ from{background-position:0 0,0 0;} to{background-position:0 220px,0 -180px;} }

/* Glass panel */
.card{
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  background: rgba(0, 14, 24, 0.22);
  border: 1px solid rgba(56,247,255,0.22);
  box-shadow: 0 0 55px rgba(56,247,255,0.14), inset 0 0 18px rgba(255,255,255,0.05);
}

/* Inputs */
.input{
  background: rgba(0,0,0,0.30);
  border: 1px solid rgba(56,247,255,0.30);
  color:#e6faff;
}
.input::placeholder{ color: rgba(230,250,255,0.75); }
.input:focus{ outline:none; border-color: var(--aqua); box-shadow: 0 0 12px rgba(56,247,255,0.55); }

/* Buttons */
.btn{
  background: linear-gradient(90deg,#38f7ff,#22d3ee,#f5d27b);
  color:#022c33;
  font-weight:900;
  letter-spacing:2px;
  transition:.25s;
}
.btn:hover{ box-shadow:0 0 28px rgba(56,247,255,0.45); transform: translateY(-2px); }

.btn-outline{
  background: rgba(0, 20, 35, 0.18);
  border: 1px solid rgba(56,247,255,0.45);
  color: #e6faff;
  font-weight: 800;
  letter-spacing: 1.2px;
  transition: .25s;
}
.btn-outline:hover{ background: rgba(56,247,255,0.12); box-shadow: 0 0 18px rgba(56,247,255,0.35); transform: translateY(-1px); }

.mono{ font-family:'Share Tech Mono', monospace; }

.badge{
  display:inline-flex; align-items:center; justify-content:center;
  width:42px; height:42px; border-radius:999px;
  background: rgba(56,247,255,0.14);
  border:1px solid rgba(56,247,255,0.30);
  box-shadow: 0 0 18px rgba(56,247,255,0.10);
}

.section{
  border: 1px solid rgba(56,247,255,0.22);
  background: rgba(255,255,255,0.04);
}
small{ color: rgba(230,250,255,0.75); }
.hint{ color: rgba(56,247,255,0.85); letter-spacing: 0.08em; }
</style>
</head>

<body class="px-4 py-10">

<!-- Background Video -->
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="caustics"></div>

<div class="max-w-3xl mx-auto p-7 md:p-10 rounded-2xl card">

  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
    <div>
      <h1 class="text-3xl md:text-4xl font-extrabold tracking-widest text-cyan-200">
        REGISTER YOUR ARMY
      </h1>
      <div class="mt-2 hint mono text-sm">
        4-MEMBER TEAM • INTER-UNIVERSITY CTF
      </div>
      <div class="mt-2 text-cyan-100/75 text-sm">
        Fee: <b>Rs. 1000 per student</b> • Team Total: <b>Rs. 4000</b>
      </div>
    </div>

    <div class="flex gap-3">
      <a href="index.php" class="px-4 py-2 rounded btn-outline text-sm md:text-base text-center">← LOGIN</a>
    </div>
  </div>

  <?php if ($success): ?>
    <div class="mb-5 p-4 rounded border border-emerald-400/60 bg-emerald-950/25 text-emerald-100">
      <?= $success ?>
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div class="mb-5 p-4 rounded border border-red-400/60 bg-red-950/25 text-red-100">
      <?= $error ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="register.php" enctype="multipart/form-data" class="space-y-6">

    <!-- University + Team -->
    <div class="p-4 rounded section">
      <div class="flex items-center gap-3 mb-3">
        <div class="badge mono">U</div>
        <div class="font-bold text-cyan-100 tracking-wide">University & Team</div>
      </div>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <label class="text-cyan-200">University Name *</label>
          <input name="university" required value="<?= old('university') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="e.g., APIIT">
        </div>
        <div>
          <label class="text-cyan-200">Team Name *</label>
          <input name="team_name" required value="<?= old('team_name') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="e.g., Trident Hackers">
        </div>
      </div>
    </div>

    <!-- Leader -->
    <div class="p-4 rounded section">
      <div class="flex items-center gap-3 mb-3">
        <div class="badge mono">L</div>
        <div class="font-bold text-cyan-100 tracking-wide">Team Leader</div>
      </div>
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="text-cyan-200">Leader Name *</label>
          <input name="leader_name" required value="<?= old('leader_name') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="Full name">
        </div>
        <div>
          <label class="text-cyan-200">Leader Email *</label>
          <input type="email" name="leader_email" required value="<?= old('leader_email') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="name@uni.edu">
        </div>
        <div>
          <label class="text-cyan-200">Leader Phone *</label>
          <input name="leader_phone" required value="<?= old('leader_phone') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="+94 7X XXX XXXX">
        </div>
      </div>
    </div>

    <!-- Contact -->
    <div class="p-4 rounded section">
      <div class="flex items-center gap-3 mb-2">
        <div class="badge mono">C</div>
        <div class="font-bold text-cyan-100 tracking-wide">Contact Person</div>
      </div>
      <small class="block mb-3">For coordination (can be the leader, but fill in clearly).</small>
      <div class="grid md:grid-cols-3 gap-4">
        <div>
          <label class="text-cyan-200">Contact Name *</label>
          <input name="contact_name" required value="<?= old('contact_name') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="Full name">
        </div>
        <div>
          <label class="text-cyan-200">Contact Email *</label>
          <input type="email" name="contact_email" required value="<?= old('contact_email') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="name@domain.com">
        </div>
        <div>
          <label class="text-cyan-200">Contact Phone *</label>
          <input name="contact_phone" required value="<?= old('contact_phone') ?>"
                 class="w-full mt-1 px-3 py-2 rounded input" placeholder="+94 7X XXX XXXX">
        </div>
      </div>
    </div>

    <!-- 4 Members -->
    <div class="p-4 rounded section">
      <div class="flex items-center gap-3 mb-4">
        <div class="badge mono">4</div>
        <div class="font-bold text-cyan-100 tracking-wide">Team Members (All 4 Required)</div>
      </div>

      <?php
        $members = [
          ['i'=>1,'k'=>'m1'],
          ['i'=>2,'k'=>'m2'],
          ['i'=>3,'k'=>'m3'],
          ['i'=>4,'k'=>'m4'],
        ];
      ?>

      <div class="space-y-4">
        <?php foreach ($members as $m): ?>
          <div class="p-4 rounded border border-cyan-300/20 bg-black/10">
            <div class="flex items-center gap-2 mb-3">
              <span class="badge mono"><?= $m['i'] ?></span>
              <span class="text-cyan-100 font-bold tracking-wide">Member <?= $m['i'] ?></span>
            </div>
            <div class="grid md:grid-cols-3 gap-4">
              <div>
                <label class="text-cyan-200">Name *</label>
                <input name="<?= $m['k'] ?>_name" required value="<?= old($m['k'].'_name') ?>"
                       class="w-full mt-1 px-3 py-2 rounded input" placeholder="Full name">
              </div>
              <div>
                <label class="text-cyan-200">Email *</label>
                <input type="email" name="<?= $m['k'] ?>_email" required value="<?= old($m['k'].'_email') ?>"
                       class="w-full mt-1 px-3 py-2 rounded input" placeholder="name@domain.com">
              </div>
              <div>
                <label class="text-cyan-200">Phone *</label>
                <input name="<?= $m['k'] ?>_phone" required value="<?= old($m['k'].'_phone') ?>"
                       class="w-full mt-1 px-3 py-2 rounded input" placeholder="+94 7X XXX XXXX">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Receipt -->
    <div class="p-4 rounded section">
      <div class="flex items-center gap-3 mb-2">
        <div class="badge mono">₹</div>
        <div class="font-bold text-cyan-100 tracking-wide">Upload Payment Receipt *</div>
      </div>
      <small class="block mb-3">
        ACCOUNT DETAILS : <br>00000000000000 <br>FCS APIIT <br>HNB BANK <br>
        Accepted: JPG / PNG / PDF (max 5MB)
      </small>
      <input type="file" name="receipt" required
             accept=".jpg,.jpeg,.png,.pdf,application/pdf,image/png,image/jpeg"
             class="w-full px-3 py-2 rounded input">
    </div>

    <!-- Notes -->
    <div class="p-4 rounded section">
      <label class="text-cyan-200">Notes (optional)</label>
      <textarea name="notes" rows="3"
                class="w-full mt-1 px-3 py-2 rounded input"
                placeholder="Anything we should know (special requests, etc.)"><?= old('notes') ?></textarea>
    </div>

    <div class="flex flex-col md:flex-row gap-3 md:gap-4 items-stretch md:items-center justify-between">
      <div class="text-sm text-cyan-100/70">
        By submitting, you confirm your team has exactly <b>4 members</b> and the payment total is <b>Rs. 4000</b>.
      </div>
      <button type="submit" class="px-6 py-3 rounded btn text-lg">
        SUBMIT REGISTRATION →
      </button>
    </div>

  </form>
</div>

</body>
</html>
