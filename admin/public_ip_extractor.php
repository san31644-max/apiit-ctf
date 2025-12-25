<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../index.php");
    exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$logFile = realpath(__DIR__ . "/..") . DIRECTORY_SEPARATOR . "logs" . DIRECTORY_SEPARATOR . "ip_logs.jsonl";

$q = trim($_GET['q'] ?? '');
$onlyRole = trim($_GET['role'] ?? 'user'); // default show users

$latestByUser = []; // user_id => record
$totalLines = 0;

if (is_file($logFile)) {
    $fh = fopen($logFile, 'rb');
    if ($fh) {
        while (!feof($fh)) {
            $line = fgets($fh);
            if ($line === false) break;
            $line = trim($line);
            if ($line === '') continue;

            $totalLines++;
            $row = json_decode($line, true);
            if (!is_array($row)) continue;

            $uid = (int)($row['user_id'] ?? 0);
            if ($uid <= 0) continue;

            // Keep latest record by timestamp order in file (append-only => last wins)
            $latestByUser[$uid] = $row;
        }
        fclose($fh);
    }
}

// Convert to list + filters
$rows = array_values($latestByUser);

if ($onlyRole !== '') {
    $rows = array_filter($rows, fn($r) => ($r['role'] ?? '') === $onlyRole);
}

if ($q !== '') {
    $qq = mb_strtolower($q);
    $rows = array_filter($rows, function($r) use ($qq){
        $u = mb_strtolower((string)($r['username'] ?? ''));
        $ip = (string)($r['ip'] ?? '');
        return str_contains($u, $qq) || str_contains($ip, $q);
    });
}

// Sort by latest timestamp desc
usort($rows, function($a,$b){
    return strcmp((string)($b['ts'] ?? ''), (string)($a['ts'] ?? ''));
});

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Public IP Extractor — Admin</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');
body{
  font-family:'Share Tech Mono', monospace;
  background: radial-gradient(900px 500px at 30% 0%, rgba(56,247,255,0.18), transparent 55%),
              radial-gradient(900px 700px at 80% 20%, rgba(245,210,123,0.10), transparent 60%),
              linear-gradient(180deg,#020617,#010b18);
  color:#e6faff;
}
.glass{
  background: rgba(3, 23, 42, 0.45);
  border: 1px solid rgba(56,247,255,0.20);
  box-shadow: 0 0 45px rgba(56,247,255,0.12), inset 0 0 18px rgba(255,255,255,0.03);
  backdrop-filter: blur(14px);
  border-radius: 18px;
}
.badge{
  display:inline-flex;align-items:center;gap:8px;
  padding:8px 12px;border-radius:14px;
  background: rgba(56,247,255,0.10);
  border: 1px solid rgba(56,247,255,0.20);
  font-weight:900; letter-spacing:.08em;
}
</style>
</head>
<body class="min-h-screen p-6">
  <div class="max-w-6xl mx-auto space-y-5">

    <div class="glass p-6">
      <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3">
        <div>
          <h1 class="text-3xl font-bold text-cyan-300">🛰️ Public IP Extractor</h1>
          <p class="text-sm text-cyan-100/70 mt-2">
            Latest known IP per user (from login/visit logs). No DB.
          </p>
        </div>
        <div class="text-sm text-cyan-100/70 space-y-1">
          <div>Total log lines: <b class="text-cyan-200"><?= (int)$totalLines ?></b></div>
          <div>Unique users tracked: <b class="text-cyan-200"><?= count($latestByUser) ?></b></div>
        </div>
      </div>

      <form method="GET" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        <input
          name="q"
          value="<?= h($q) ?>"
          placeholder="Search username or IP..."
          class="w-full px-4 py-3 rounded-xl bg-white/5 border border-cyan-300/20 focus:outline-none focus:border-cyan-300/50"
        >
        <select name="role" class="w-full px-4 py-3 rounded-xl bg-white/5 border border-cyan-300/20 focus:outline-none focus:border-cyan-300/50">
          <option value="user" <?= $onlyRole==='user'?'selected':'' ?>>role = user</option>
          <option value="admin" <?= $onlyRole==='admin'?'selected':'' ?>>role = admin</option>
          <option value="" <?= $onlyRole===''?'selected':'' ?>>all roles</option>
        </select>
        <div class="flex gap-3">
          <button class="flex-1 px-5 py-3 rounded-xl bg-cyan-300 text-slate-900 font-bold hover:bg-cyan-200 transition">
            Filter
          </button>
          <a href="public_ip_extractor.php" class="flex-1 px-5 py-3 rounded-xl border border-cyan-300/25 hover:bg-white/5 transition text-center">
            Reset
          </a>
        </div>
      </form>
    </div>

    <div class="glass p-4 overflow-auto">
      <table class="w-full text-sm">
        <thead class="text-cyan-200">
          <tr class="border-b border-cyan-300/20">
            <th class="text-left p-3">User</th>
            <th class="text-left p-3">Role</th>
            <th class="text-left p-3">Latest IP</th>
            <th class="text-left p-3">Last Seen</th>
            <th class="text-left p-3">Event</th>
            <th class="text-left p-3">UA (short)</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr class="border-b border-white/5 hover:bg-white/5 transition">
            <td class="p-3 font-bold text-cyan-100">
              <?= h($r['username'] ?? '—') ?>
              <span class="text-white/50">(#<?= (int)($r['user_id'] ?? 0) ?>)</span>
            </td>
            <td class="p-3"><?= h($r['role'] ?? '—') ?></td>
            <td class="p-3">
              <?php if (!empty($r['ip'])): ?>
                <span class="badge"><?= h($r['ip']) ?></span>
              <?php else: ?>
                <span class="text-white/50">—</span>
              <?php endif; ?>
            </td>
            <td class="p-3 text-white/70"><?= h($r['ts'] ?? '—') ?></td>
            <td class="p-3 text-white/70"><?= h($r['event'] ?? '—') ?></td>
            <td class="p-3 text-white/60">
              <?= h(mb_strimwidth((string)($r['ua'] ?? ''), 0, 70, '…')) ?>
            </td>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($rows)): ?>
          <tr>
            <td class="p-4 text-white/60" colspan="6">
              No data found yet. Make sure you added <b>log_ip_to_file()</b> after successful login.
              Also check that <code>logs/ip_logs.jsonl</code> is writable.
            </td>
          </tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="text-xs text-white/60">
      ✅ Works without DB (uses file logging). If you are behind Nginx/Cloudflare, tell me and I’ll give the correct trusted proxy setup.
    </div>

  </div>
</body>
</html>
