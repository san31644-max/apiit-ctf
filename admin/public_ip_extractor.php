<?php
session_start();

/**
 * If you're not behind a proxy, REMOTE_ADDR is enough.
 * If behind a proxy, X-Forwarded-For may exist, but it can be spoofed unless you trust the proxy.
 */
function get_client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // If you are behind a reverse proxy, you MAY use X-Forwarded-For,
    // but be careful: can be spoofed unless you only trust your proxy.
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        foreach ($parts as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                $ip = $candidate;
                break;
            }
        }
    }

    if (!$ip) $ip = '0.0.0.0';
    return $ip;
}

$ip = get_client_ip();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$time = date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Public IP Extractor</title>
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
  padding:10px 14px;border-radius:14px;
  background: rgba(56,247,255,0.10);
  border: 1px solid rgba(56,247,255,0.20);
  font-weight:900; letter-spacing:.08em;
}
</style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
  <div class="glass max-w-2xl w-full p-8 space-y-5">
    <h1 class="text-3xl font-bold text-cyan-300">🛰️ Public IP Extractor</h1>
    <p class="text-sm text-cyan-100/70">
      This page shows the <b>public IP</b> of the user who opens it (no database).
    </p>

    <div class="space-y-3">
      <div class="badge">
        <span class="text-cyan-200">Your IP:</span>
        <span class="text-yellow-200"><?php echo htmlspecialchars($ip); ?></span>
      </div>
      <div class="text-xs text-white/60">Time: <?php echo htmlspecialchars($time); ?></div>
      <div class="text-xs text-white/60">User-Agent: <?php echo htmlspecialchars($ua); ?></div>
    </div>

    <div class="text-xs text-white/60">
      ⚠️ Note: Without storage (DB/file), you cannot build a full “all users IP list” later.
    </div>
  </div>
</body>
</html>
