<?php
// includes/page_guard_json.php
if (session_status() === PHP_SESSION_NONE) session_start();

function pg_load_locks(): array {
  $path = __DIR__ . "/../config/page_locks.json";

  $defaults = [
    "register"    => "open",
    "challenges"  => "open",
    "hints"       => "open",
    "leaderboard" => "open",
  ];

  if (!file_exists($path)) {
    @file_put_contents($path, json_encode($defaults, JSON_PRETTY_PRINT));
    return $defaults;
  }

  $raw = @file_get_contents($path);
  $data = json_decode((string)$raw, true);
  if (!is_array($data)) return $defaults;

  return array_merge($defaults, $data);
}

function guard_page_json(string $pageKey): void {
  $locks = pg_load_locks();
  $state = strtolower(trim((string)($locks[$pageKey] ?? "open")));
  if ($state !== "closed") return;

  // Decide back link depending on location
  $isUserArea = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/user/') !== false);
  $back = $isUserArea ? "dashboard.php" : "index.php";

  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Section Closed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
      @import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');
      body{
        margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
        padding:24px;
        background:
          radial-gradient(900px 420px at 55% 12%, rgba(56,247,255,0.14), transparent 62%),
          linear-gradient(180deg, rgba(0,0,0,0.18), rgba(0,0,0,0.72)),
          #00101f;
        color:#e6faff;
        font-family:'Share Tech Mono', monospace;
      }
      .card{
        max-width:760px; width:100%;
        border-radius:22px;
        backdrop-filter: blur(14px);
        background: rgba(0, 14, 24, 0.30);
        border: 1px solid rgba(56,247,255,0.18);
        box-shadow: 0 0 55px rgba(56,247,255,0.12), inset 0 0 18px rgba(255,255,255,0.05);
        padding:22px;
      }
      .title{
        font-family:'Cinzel',serif;
        font-weight:900;
        letter-spacing:.14em;
        color:rgba(56,247,255,0.92);
        text-shadow:0 0 18px rgba(56,247,255,0.22);
      }
      .pill{
        border:1px solid rgba(56,247,255,.2);
        background:rgba(255,255,255,.05);
        padding:6px 10px;
        border-radius:999px;
      }
      .btn{
        border-radius:14px;
        padding:10px 12px;
        font-weight:900;
        letter-spacing:.08em;
        transition:.2s;
      }
      .btnA{background:linear-gradient(90deg, rgba(56,247,255,0.92), rgba(34,211,238,0.72), rgba(245,210,123,0.75)); color:#00131f;}
      .btnB{border:1px solid rgba(56,247,255,0.35); color:rgba(230,250,255,0.92); background:rgba(0,0,0,0.18);}
      .btnB:hover{box-shadow:0 0 18px rgba(56,247,255,0.14); transform: translateY(-1px);}
    </style>
  </head>
  <body>
    <div class="card">
      <div class="title text-2xl md:text-3xl">🔒 ATLANTIS SECTION SEALED</div>

      <p class="mt-3 text-cyan-100/85 text-lg">
        <b>Admin has closed this section for viewing.</b>
      </p>
      <p class="mt-2 text-cyan-100/70">
        Please return to the dashboard and continue other available sections.
      </p>

      <div class="mt-4 flex flex-wrap gap-2 text-xs text-cyan-100/70">
        <span class="pill">Page Key: <?= htmlspecialchars($pageKey) ?></span>
        <span class="pill">Status: CLOSED</span>
      </div>

      <div class="mt-6 flex flex-col sm:flex-row gap-3">
        <a href="<?= htmlspecialchars($back) ?>" class="btn btnA text-center">RETURN →</a>
        <a href="<?= $isUserArea ? "../logout.php" : "logout.php" ?>" class="btn btnB text-center">LOGOUT</a>
      </div>

      <p class="mt-6 text-xs text-cyan-100/60">Atlantis Control Seal • Admin Managed</p>
    </div>
  </body>
  </html>
  <?php
  exit;
}
