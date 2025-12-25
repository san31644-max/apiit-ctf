<?php
// admin/page_manager.php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header("Location: ../index.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$path = __DIR__ . "/../config/page_locks.json";
$defaults = [
  "register"    => "open",
  "challenges"  => "open",
  "hints"       => "open",
  "leaderboard" => "open",
];

if (!file_exists($path)) {
  @file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT));
}

$locks = $defaults;
$raw = @file_get_contents($path);
$tmp = json_decode((string)$raw, true);
if (is_array($tmp)) $locks = array_merge($defaults, $tmp);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$success = $error = "";

/* Save */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
    $error = "Invalid token. Refresh and try again.";
  } else {
    $new = [];
    foreach ($defaults as $k => $v) {
      $val = strtolower(trim((string)($_POST[$k] ?? "open")));
      $new[$k] = ($val === "closed") ? "closed" : "open";
    }

    $ok = @file_put_contents($path, json_encode($new, JSON_PRETTY_PRINT));
    if ($ok === false) {
      $error = "Cannot write to config/page_locks.json (check folder permissions).";
    } else {
      $locks = $new;
      $success = "Page settings saved successfully!";
    }
  }
}

$pages = [
  "register"    => ["Register Page",     "register.php",        "Controls team registration access"],
  "challenges"  => ["Challenges Page",   "user/challenges.php", "Controls access to challenges + flag submits"],
  "hints"       => ["Hints Page",        "user/hints.php",      "Controls access to hints (point based)"],
  "leaderboard" => ["Leaderboard Page",  "user/leaderboard.php","Controls ranks visibility"],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Atlantis Admin — Page Manager</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');
:root{
  --aqua:#38f7ff; --gold:#f5d27b;
  --glass: rgba(0, 14, 24, 0.24);
  --stroke: rgba(56,247,255,0.18);
  --text: #e6faff;
}
body{
  margin:0; min-height:100vh; color:var(--text); font-family:'Share Tech Mono', monospace;
  background:
    radial-gradient(900px 420px at 55% 12%, rgba(56,247,255,0.14), transparent 62%),
    linear-gradient(180deg, rgba(0,0,0,0.18), rgba(0,0,0,0.70)),
    #00101f;
}
.panel{
  backdrop-filter: blur(14px);
  background: var(--glass);
  border: 1px solid var(--stroke);
  box-shadow: 0 0 55px rgba(56,247,255,0.12), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 22px;
}
.h1{
  font-family:'Cinzel',serif; font-weight:900; letter-spacing:.14em;
  color:rgba(56,247,255,0.92); text-shadow:0 0 18px rgba(56,247,255,0.22);
}
.small{font-size:12px;color:rgba(230,250,255,0.70);}
.pill{border:1px solid rgba(56,247,255,.2); background:rgba(255,255,255,.05); padding:6px 10px; border-radius:999px;}
.btn{
  border-radius:14px; padding:10px 12px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(0,0,0,0.18);
  font-weight:900; letter-spacing:.08em;
  transition:.22s;
}
.btn:hover{box-shadow:0 0 18px rgba(56,247,255,0.14); transform: translateY(-1px);}
.btn-save{background: linear-gradient(90deg, rgba(56,247,255,0.92), rgba(34,211,238,0.72), rgba(245,210,123,0.75)); color:#00131f; border:0;}
select{
  width:100%;
  border-radius:14px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(255,255,255,0.05);
  padding:10px 12px;
  color: rgba(230,250,255,0.92);
  outline:none;
}
select:focus{border-color: rgba(56,247,255,0.45); box-shadow: 0 0 14px rgba(56,247,255,0.18);}
.card{border:1px solid rgba(56,247,255,0.16); border-radius:18px; background:rgba(255,255,255,0.03);}
</style>
</head>

<body class="p-4 md:p-8">
<div class="max-w-6xl mx-auto space-y-6">

  <div class="panel p-6">
    <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
      <div>
        <div class="small tracking-[0.35em]">ATLANTIS ADMIN</div>
        <div class="h1 text-2xl md:text-3xl mt-2">🔱 PAGE MANAGER</div>
        <div class="small mt-2">Open / Close user pages without database changes.</div>
      </div>
      <div class="flex gap-2 flex-wrap">
        <a href="dashboard.php" class="btn">← Back</a>
        <a href="../logout.php" class="btn" style="border:0;background:linear-gradient(90deg, rgba(251,113,133,0.95), rgba(251,113,133,0.55));color:#1a0505;">Logout</a>
      </div>
    </div>
  </div>

  <?php if($success): ?>
    <div class="panel p-4" style="border-color:rgba(34,197,94,0.30); background: rgba(34,197,94,0.08);">
      <div class="font-extrabold text-green-300">✅ <?= h($success) ?></div>
      <div class="small mt-1">Saved to <span class="text-cyan-200">config/page_locks.json</span></div>
    </div>
  <?php endif; ?>

  <?php if($error): ?>
    <div class="panel p-4" style="border-color:rgba(251,113,133,0.40); background: rgba(251,113,133,0.08);">
      <div class="font-extrabold" style="color:rgba(251,113,133,0.95)">⚠️ <?= h($error) ?></div>
    </div>
  <?php endif; ?>

  <form method="POST" class="panel p-6 space-y-4">
    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <?php foreach($pages as $key => [$title,$pathHint,$desc]): ?>
        <?php $state = strtolower((string)($locks[$key] ?? 'open')); ?>
        <div class="card p-5">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="text-lg font-extrabold" style="color:rgba(245,210,123,0.95)"><?= h($title) ?></div>
              <div class="small mt-1">File: <span class="text-cyan-200"><?= h($pathHint) ?></span></div>
              <div class="small mt-2"><?= h($desc) ?></div>
              <div class="mt-3 flex flex-wrap gap-2 text-xs text-cyan-100/70">
                <span class="pill">Key: <?= h($key) ?></span>
                <span class="pill">Now: <?= strtoupper(h($state)) ?></span>
              </div>
            </div>
          </div>

          <div class="mt-4">
            <label class="small">Set status</label>
            <select name="<?= h($key) ?>" class="mt-2">
              <option value="open" <?= $state==='open'?'selected':'' ?>>OPEN</option>
              <option value="closed" <?= $state==='closed'?'selected':'' ?>>CLOSED</option>
            </select>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="flex flex-col sm:flex-row gap-3 justify-end pt-2">
      <button type="submit" class="btn btn-save px-6">SAVE SETTINGS →</button>
    </div>

    <div class="small mt-2">
      ✅ After saving, the pages will block automatically (if you added the guard lines).
    </div>
  </form>

  <div class="panel p-6">
    <div class="h1 text-lg">⚙️ How to activate on each page</div>
    <div class="small mt-2">Add these lines at the TOP of the target page:</div>
    <pre class="mt-3 p-4 rounded-xl bg-black/40 border border-cyan-300/20 overflow-auto text-cyan-100 text-sm"><code><?php echo h("require_once __DIR__ . '/../includes/page_guard_json.php';\nguard_page_json('challenges');"); ?></code></pre>
    <div class="small mt-2">Use keys: <span class="text-cyan-200">register, challenges, hints, leaderboard</span></div>
  </div>

</div>
</body>
</html>
