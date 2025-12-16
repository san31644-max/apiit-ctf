<?php
// ----- SESSION + SECURITY ----------------------------------------------------
session_set_cookie_params([
  'lifetime' => 0,
  'path' => '/',
  'secure' => false, // set true on HTTPS
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin'
        ? "admin/dashboard.php"
        : "user/dashboard.php"));
    exit;
}

$error = $_SESSION['error'] ?? '';
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>APIIT CTF — Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

body {
  font-family: 'Share Tech Mono', monospace;
  background: #020617;
  color: #e2e8f0;
  overflow: hidden;
}

/* ===== CYBER BACKGROUND ===== */
#cyber-bg {
  position: fixed;
  inset: 0;
  z-index: -2;
}

.scanlines {
  position: fixed;
  inset: 0;
  z-index: -1;
  pointer-events: none;
  background: repeating-linear-gradient(
    to bottom,
    rgba(255,255,255,0.02),
    rgba(255,255,255,0.02) 1px,
    transparent 1px,
    transparent 4px
  );
  animation: scan 6s linear infinite;
}

@keyframes scan {
  from { background-position-y: 0; }
  to { background-position-y: 100%; }
}

/* ===== GLASS CARD ===== */
.glass {
  backdrop-filter: blur(18px);
  background: rgba(15,23,42,0.75);
  border: 1px solid rgba(34,197,94,0.45);
  box-shadow: 0 0 40px rgba(34,197,94,0.3);
}

.glow-text {
  text-shadow: 0 0 16px #22c55e;
}

/* ===== INPUTS ===== */
.hacker-input {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(34,197,94,0.35);
  color: #e2e8f0;
}

.hacker-input:focus {
  border-color: #22c55e;
  box-shadow: 0 0 12px #22c55e;
  outline: none;
}

/* ===== BUTTON ===== */
.btn-hacker {
  background: linear-gradient(90deg, #22c55e, #16a34a);
  color: #022c22;
  font-weight: bold;
  letter-spacing: 1px;
  transition: 0.3s;
}

.btn-hacker:hover {
  box-shadow: 0 0 25px #22c55e;
  transform: translateY(-2px);
}

#authBtn.loading {
  cursor: not-allowed;
  box-shadow: 0 0 30px #22c55e;
}

#authBtn.loading #btnText {
  animation: flicker 1.2s infinite;
}

@keyframes flicker {
  0%,100% { opacity: 1; }
  50% { opacity: 0.55; }
}

/* ===== LOGOS ===== */
.logo-bar img {
  height: 52px;
  filter: grayscale(100%) brightness(0.85);
  transition: .3s;
}

.logo-bar img:hover {
  filter: grayscale(0%) brightness(1.1);
  transform: scale(1.08);
  box-shadow: 0 0 18px #22c55e;
}

.footer-powered {
  color: #22c55e;
  font-weight: bold;
  text-shadow: 0 0 10px #22c55e;
}
</style>
</head>

<body class="flex items-center justify-center min-h-screen px-4">

<!-- ===== BACKGROUND ===== -->
<canvas id="cyber-bg"></canvas>
<div class="scanlines"></div>

<!-- ===== LOGIN CARD ===== -->
<div class="w-full max-w-md p-8 rounded-2xl glass relative z-10">

  <div class="logo-bar flex justify-center gap-6 mb-6">
    <img src="https://www.staffs.ac.uk/image-library/legacy-logos/partner-colleges/apiit-logo.xe8606fb6.jpg">
    <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRQMQkUOsviqu4KXAgckYrVg1QrqbF6WaQGKw&s">
    <img src="https://ncee.org.uk/wp-content/uploads/2023/11/staffordshire.jpg">
  </div>

  <h1 class="text-3xl text-center font-bold text-green-400 glow-text mb-8">
    APIIT CTF LOGIN
  </h1>

  <?php if ($error): ?>
    <div class="mb-4 p-3 text-red-400 border border-red-500 rounded bg-red-900/40">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form id="loginForm" action="login_process.php" method="POST" class="space-y-5">

    <div>
      <label class="text-green-300">Username</label>
      <input name="username" required class="w-full mt-1 px-3 py-2 rounded hacker-input">
    </div>

    <div>
      <label class="text-green-300">Password</label>
      <input type="password" name="password" required class="w-full mt-1 px-3 py-2 rounded hacker-input">
    </div>

    <!-- ===== LOADING BUTTON ===== -->
    <button id="authBtn" type="submit"
      class="w-full py-3 rounded btn-hacker text-lg relative overflow-hidden">

      <span id="btnText" class="relative z-10">AUTHENTICATE →</span>

      <div id="progressBar"
           class="absolute inset-0 bg-green-400/30 scale-x-0 origin-left transition-transform duration-200">
      </div>
    </button>

  </form>

  <p class="text-center text-sm mt-8 text-gray-400">
    Powered by <span class="footer-powered">HACKERSPLOIT</span>
  </p>

</div>

<!-- ===== CYBER BACKGROUND SCRIPT ===== -->
<script>
const canvas = document.getElementById("cyber-bg");
const ctx = canvas.getContext("2d");

function resize() {
  canvas.width = innerWidth;
  canvas.height = innerHeight;
}
resize();
addEventListener("resize", resize);

const nodes = [];
const NODE_COUNT = 80;

for (let i = 0; i < NODE_COUNT; i++) {
  nodes.push({
    x: Math.random() * canvas.width,
    y: Math.random() * canvas.height,
    vx: (Math.random() - 0.5) * 0.4,
    vy: (Math.random() - 0.5) * 0.4
  });
}

function drawNetwork() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  for (let i = 0; i < nodes.length; i++) {
    const n = nodes[i];
    n.x += n.vx;
    n.y += n.vy;

    if (n.x < 0 || n.x > canvas.width) n.vx *= -1;
    if (n.y < 0 || n.y > canvas.height) n.vy *= -1;

    ctx.fillStyle = "#22c55e";
    ctx.fillRect(n.x, n.y, 2, 2);

    for (let j = i + 1; j < nodes.length; j++) {
      const m = nodes[j];
      const dx = n.x - m.x;
      const dy = n.y - m.y;
      const dist = Math.sqrt(dx * dx + dy * dy);

      if (dist < 120) {
        ctx.strokeStyle = "rgba(34,197,94,0.15)";
        ctx.beginPath();
        ctx.moveTo(n.x, n.y);
        ctx.lineTo(m.x, m.y);
        ctx.stroke();
      }
    }
  }
  requestAnimationFrame(drawNetwork);
}
drawNetwork();
</script>

<!-- ===== LOADING BUTTON SCRIPT ===== -->
<script>
const form = document.getElementById("loginForm");
const btn = document.getElementById("authBtn");
const bar = document.getElementById("progressBar");
const text = document.getElementById("btnText");

let loading = false;

form.addEventListener("submit", e => {
  if (loading) return;

  e.preventDefault();
  loading = true;

  btn.classList.add("loading");
  btn.disabled = true;
  text.textContent = "AUTHENTICATING…";

  let progress = 0;
  const interval = setInterval(() => {
    progress += Math.random() * 12;
    if (progress >= 100) {
      progress = 100;
      bar.style.transform = "scaleX(1)";
      clearInterval(interval);
      setTimeout(() => form.submit(), 400);
    } else {
      bar.style.transform = `scaleX(${progress / 100})`;
    }
  }, 120);
});
</script>

</body>
</html>
