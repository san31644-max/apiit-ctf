<?php
// ----- SESSION + SECURITY ----------------------------------------------------
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => false,   // set true if using HTTPS
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

// Already logged in? → Redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: user/dashboard.php");
    }
    exit;
}

// Capture error message if set
$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>APIIT CTF — Login</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

    body {
      font-family: 'Share Tech Mono', monospace;
      background: radial-gradient(circle at top, #0f172a, #020617);
      color: #e2e8f0;
    }
    .glass {
      backdrop-filter: blur(14px);
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(34,197,94,0.5);
      box-shadow: 0 0 30px rgba(34,197,94,0.2);
    }
    .btn-hacker {
      background: linear-gradient(90deg, #22c55e, #16a34a);
      color: #0f172a;
      font-weight: bold;
      letter-spacing: 1px;
      transition: 0.25s ease-in-out;
    }
    .btn-hacker:hover {
      box-shadow: 0 0 18px #22c55e;
      transform: scale(1.03);
    }
    .hacker-input {
      background: rgba(255,255,255,0.03);
      border: 1px solid rgba(34,197,94,0.3);
      color: #e2e8f0;
      transition: 0.25s;
    }
    .hacker-input:focus {
      border-color: #22c55e;
      box-shadow: 0 0 10px #22c55e;
      outline: none;
    }
    .glow-text {
      text-shadow: 0 0 12px #22c55e;
    }
  </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-md p-8 glass rounded-2xl">
    <h1 class="text-3xl font-bold text-center text-green-400 glow-text mb-8 tracking-wider">
      APIIT CTF Login
    </h1>

    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3 text-red-400 border border-red-500 rounded bg-red-900/40 animate-pulse">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <form action="login_process.php" method="POST" class="space-y-5">
      <div>
        <label class="block mb-1 text-green-300">Username</label>
        <input type="text" name="username" required placeholder="> enter username"
          class="w-full px-3 py-2 rounded-md hacker-input" />
      </div>
      <div>
        <label class="block mb-1 text-green-300">Password</label>
        <input type="password" name="password" required placeholder="> enter password"
          class="w-full px-3 py-2 rounded-md hacker-input" />
      </div>
      <button type="submit"
        class="w-full py-3 rounded-md btn-hacker text-lg tracking-wide">
        LOGIN →
      </button>
    </form>

    <p class="text-center text-xs text-gray-400 mt-6">
      Powered by <span class="text-green-400">APIIT_CTF</span>
    </p>
  </div>
</body>
</html>
