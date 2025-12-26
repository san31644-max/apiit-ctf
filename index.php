<?php
require_once __DIR__ . "/includes/ip_logger_file.php";

// If you're behind Nginx reverse proxy / Cloudflare, add proxy IPs here.
// If not, keep empty.
$trustedProxies = [];

log_ip_to_file((int)$_SESSION['user_id'], (string)$_SESSION['username'], (string)$_SESSION['role'], 'login', $trustedProxies);

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
<title>Atlantis Gateway</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --aqua2:#22d3ee;
  --gold:#f5d27b;
  --ink:#000f1d;
}

body{
  font-family:'Cinzel',serif;
  margin:0;
  min-height:100vh;
  color:#e6faff;
  overflow:hidden;
  background:#000;
}

/* ================= VIDEO BACKGROUND ================= */
.video-bg{
  position:fixed;
  inset:0;
  z-index:-5;
  overflow:hidden;
  background:#00101f;
}
.video-bg video{
  width:100%;
  height:100%;
  object-fit:cover;
  object-position:center;
  transform:scale(1.03);
  filter:saturate(1.05) contrast(1.05);
}

/* light tint (video still visible) */
.video-overlay{
  position:fixed;
  inset:0;
  z-index:-4;
  pointer-events:none;
  background:
    radial-gradient(900px 420px at 50% 12%, rgba(56,247,255,0.14), transparent 62%),
    linear-gradient(180deg, rgba(0,0,0,0.15), rgba(0,0,0,0.30));
}

/* caustics shimmer */
.caustics{
  position:fixed;
  inset:0;
  z-index:-3;
  pointer-events:none;
  background:
    repeating-radial-gradient(circle at 30% 40%, rgba(56,247,255,.05) 0 2px, transparent 3px 14px),
    repeating-radial-gradient(circle at 70% 60%, rgba(255,255,255,.03) 0 1px, transparent 2px 18px);
  opacity:.32;
  mix-blend-mode:screen;
  animation: causticMove 7s linear infinite;
}
@keyframes causticMove{
  from{ background-position: 0 0, 0 0; }
  to{ background-position: 0 220px, 0 -180px; }
}

/* ================= LOGIN CARD ================= */
.card{
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  background: rgba(0, 14, 24, 0.22);
  border: 1px solid rgba(56,247,255,0.22);
  box-shadow:
    0 0 55px rgba(56,247,255,0.14),
    inset 0 0 18px rgba(255,255,255,0.05);
}

/* ================= CREST ================= */
.crest{
  position: relative;
  width: 100%;
  height: 190px;
  margin: 0 auto 10px;
  border-radius: 24px;
  overflow: hidden;
}
.crest::before{
  content:"";
  position:absolute; inset:-35%;
  background:
    radial-gradient(240px 240px at var(--mx, 50%) var(--my, 38%),
      rgba(56,247,255,0.18),
      rgba(56,247,255,0.06) 45%,
      transparent 72%);
  filter: blur(10px);
  opacity: .9;
  pointer-events:none;
}

.portal{
  position:absolute;
  left:50%;
  top:58%;
  transform: translate(-50%,-50%);
  width: 320px;
  height: 185px;
  border-radius: 999px 999px 44px 44px;
  background:
    radial-gradient(circle at 50% 40%, rgba(56,247,255,0.16), transparent 65%),
    linear-gradient(180deg, rgba(255,255,255,0.05), rgba(255,255,255,0.01));
  border: 1px solid rgba(56,247,255,0.18);
  box-shadow:
    0 0 42px rgba(56,247,255,0.12),
    inset 0 0 26px rgba(255,255,255,0.04);
}

.rune-ring{
  position:absolute;
  left:50%;
  top:62%;
  transform: translate(-50%,-50%);
  width: 280px;
  height: 140px;
  border-radius: 999px;
  border: 1px dashed rgba(56,247,255,0.18);
  opacity: .7;
  animation: spin 18s linear infinite;
}
@keyframes spin{ to{ transform: translate(-50%,-50%) rotate(360deg);} }

.glyph{
  position:absolute;
  left:50%;
  top:62%;
  transform: translate(-50%,-50%);
  width: 60px;
  height: 60px;
  border-radius: 999px;
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(245,210,123,0.20);
  box-shadow: 0 0 18px rgba(56,247,255,0.12), inset 0 0 16px rgba(255,255,255,0.04);
  display:flex;
  align-items:center;
  justify-content:center;
  font-family:'Share Tech Mono', monospace;
  color: rgba(245,210,123,0.95);
  text-shadow: 0 0 10px rgba(245,210,123,0.22), 0 0 14px rgba(56,247,255,0.12);
}
.glyph span{ font-size: 22px; }

/* ================= LOGO MEDALLIONS ================= */
.med{
  --glow: 0;
  position:absolute;
  width: 74px;
  height: 74px;
  border-radius: 999px;
  background: rgba(0, 20, 35, 0.28);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  border: 1px solid rgba(56,247,255,0.32);
  box-shadow:
    0 0 calc(var(--glow)*28px) rgba(56,247,255,0.50),
    inset 0 0 14px rgba(255,255,255,0.06);
  display:flex;
  align-items:center;
  justify-content:center;
  transition: box-shadow .12s linear, transform .2s ease, border-color .2s ease;
}
.med:hover{
  border-color: rgba(56,247,255,0.85);
  transform: translateY(-2px);
}
.med .stage{
  width: 88%;
  height: 88%;
  border-radius: 999px;
  overflow:hidden;
  position: relative;
  box-shadow: inset 0 0 18px rgba(0,0,0,0.18);
}
.med img{
  width: 100%;
  height: 100%;
  object-fit: contain;
  object-position:center;
  padding: 10%;
  transform: scale(1.18);
  filter: drop-shadow(0 0 10px rgba(56,247,255,0.22));
}

/* Arc positions */
.med.p1{ left: 8%;  top: 22%; }
.med.p2{ left: 30%; top: 3%;  }
.med.p3{ right: 30%; top: 3%; }
.med.p4{ right: 8%; top: 22%; }

@media (max-width: 420px){
  .crest{ height: 180px; }
  .portal{ width: 300px; height: 170px; }
  .med{ width: 70px; height: 70px; }
  .med.p1{ left: 4%;  top: 22%; }
  .med.p4{ right: 4%; top: 22%; }
}

/* ================= INPUTS ================= */
.input{
  background: rgba(0,0,0,0.30);
  border: 1px solid rgba(56,247,255,0.30);
  color:#e6faff;
}
.input::placeholder{ color: rgba(230,250,255,0.75); }
.input:focus{
  outline:none;
  border-color: var(--aqua);
  box-shadow: 0 0 12px rgba(56,247,255,0.55);
}

/* ================= PRIMARY LOGIN BUTTON ================= */
.btn{
  background: linear-gradient(90deg,#38f7ff,#22d3ee,#f5d27b);
  color:#022c33;
  font-weight:900;
  letter-spacing:2px;
  transition:.25s;
}
.btn:hover{
  box-shadow:0 0 28px rgba(56,247,255,0.45);
  transform: translateY(-2px);
}

.footer-powered{
  color: rgba(245,210,123,0.95);
  text-shadow: 0 0 12px rgba(245,210,123,0.28), 0 0 16px rgba(56,247,255,0.12);
  font-weight: 800;
}

/* ================= OUTSIDE CTA: "REGISTER YOUR ARMY" ================= */
/* This sits OUTSIDE the login card and looks like a highlighted callout */
.army-cta{
  position: fixed;
  left: 50%;
  bottom: 22px;
  transform: translateX(-50%);
  z-index: 50;
  width: min(680px, calc(100vw - 28px));
  border-radius: 999px;
  padding: 10px;
  background: rgba(0, 18, 28, 0.28);
  border: 1px solid rgba(56,247,255,0.22);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
  box-shadow:
    0 0 70px rgba(56,247,255,0.18),
    0 0 30px rgba(245,210,123,0.10);
  overflow: hidden;
}

/* animated glow sweep */
.army-cta::before{
  content:"";
  position:absolute;
  inset:-60%;
  background:
    radial-gradient(280px 180px at 30% 45%, rgba(56,247,255,0.22), transparent 70%),
    radial-gradient(240px 160px at 70% 55%, rgba(245,210,123,0.14), transparent 70%);
  filter: blur(14px);
  opacity: .75;
  animation: ctaSweep 5.5s ease-in-out infinite alternate;
  pointer-events:none;
}
@keyframes ctaSweep{
  from { transform: translate3d(-4%, -2%, 0) rotate(-6deg); }
  to   { transform: translate3d( 4%,  2%, 0) rotate( 6deg); }
}

.army-cta-inner{
  position: relative;
  display: flex;
  gap: 10px;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  border-radius: 999px;
  background: rgba(255,255,255,0.03);
  border: 1px solid rgba(56,247,255,0.18);
}

.army-copy{
  display:flex;
  align-items:center;
  gap: 12px;
  min-width: 0;
}

.badge-4{
  flex: 0 0 auto;
  width: 44px;
  height: 44px;
  border-radius: 999px;
  display:grid;
  place-items:center;
  font-family: 'Share Tech Mono', monospace;
  font-weight: 800;
  color: rgba(0, 20, 30, 0.95);
  background: linear-gradient(135deg, rgba(56,247,255,0.95), rgba(34,211,238,0.85), rgba(245,210,123,0.70));
  box-shadow: 0 0 22px rgba(56,247,255,0.30);
}

.army-text{
  min-width: 0;
  line-height: 1.05;
}
.army-text .top{
  font-size: 0.92rem;
  letter-spacing: 0.08em;
  color: rgba(230,250,255,0.92);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.army-text .sub{
  margin-top: 2px;
  font-size: 0.78rem;
  letter-spacing: 0.06em;
  color: rgba(56,247,255,0.85);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* CTA button */
.army-btn{
  flex: 0 0 auto;
  display:inline-flex;
  align-items:center;
  gap: 10px;
  padding: 12px 16px;
  border-radius: 999px;
  font-weight: 900;
  letter-spacing: 0.10em;
  color: rgba(2, 24, 30, 0.95);
  background: linear-gradient(90deg, rgba(56,247,255,0.95), rgba(34,211,238,0.85), rgba(245,210,123,0.65));
  box-shadow: 0 0 26px rgba(56,247,255,0.30);
  transition: .25s;
  text-decoration:none;
}
.army-btn:hover{
  transform: translateY(-2px);
  box-shadow: 0 0 34px rgba(56,247,255,0.45), 0 0 26px rgba(245,210,123,0.18);
}
.army-btn:active{ transform: translateY(0px); }

.arrow{
  font-family: 'Share Tech Mono', monospace;
  font-size: 1.05rem;
  opacity: .95;
}

/* on very small screens, stack nicely */
@media (max-width: 420px){
  .army-cta-inner{ padding: 10px; }
  .army-btn{ padding: 10px 12px; letter-spacing: 0.06em; }
  .army-text .top{ font-size: 0.86rem; }
}
</style>
</head>

<body class="flex items-center justify-center min-h-screen px-4">

<!-- ===== VIDEO BACKGROUND ===== -->
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>
<div class="caustics"></div>

<!-- ===== LOGIN CARD ===== -->
<div class="w-full max-w-md p-8 rounded-2xl card z-10">

  <div id="crest" class="crest">
    <div class="portal"></div>
    <div class="rune-ring"></div>
    <div class="glyph"><span>Ψ</span></div>

    <div class="med p1">
      <div class="stage">
        <img src="https://yt3.googleusercontent.com/ytc/AIdro_lowz4PsfRFrMy7KjUjzsglykKd0kgOANY6pjP0WkPuwU8=s900-c-k-c0x00ffffff-no-rj" alt="Logo 1">
      </div>
    </div>

    <div class="med p2">
      <div class="stage">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRQMQkUOsviqu4KXAgckYrVg1QrqbF6WaQGKw&s" alt="Logo 2">
      </div>
    </div>

    <div class="med p3">
      <div class="stage">
        <img src="https://i.postimg.cc/Xvv3gtGg/Chat-GPT-Image-Dec-24-2025-10-10-02-PM.png" alt="Logo 3">
      </div>
    </div>

    <div class="med p4">
      <div class="stage">
        <img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRGbRrjY2PCuwNRPLchl5JG7_fRm1R3lhEDxw&s" alt="Logo 4">
      </div>
    </div>
  </div>

  <h1 class="text-3xl text-center font-bold text-cyan-200 mb-6 tracking-widest">
    ATLANTIS GATEWAY
  </h1>

  <?php if ($error): ?>
    <div class="mb-4 p-3 text-red-300 border border-red-500 rounded bg-red-950/30">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form id="form" method="POST" action="login_process.php" class="space-y-5">

    <div>
      <label class="text-cyan-200">Explorer ID</label>
      <input name="username" required placeholder="Enter username"
             class="w-full mt-1 px-3 py-2 rounded input">
    </div>

    <div>
      <label class="text-cyan-200">Relic Key</label>
      <input type="password" name="password" required placeholder="Enter password"
             class="w-full mt-1 px-3 py-2 rounded input">
    </div>

    <button id="authBtn" type="submit"
            class="w-full py-3 rounded btn text-lg relative overflow-hidden">
      <span id="btnText" class="relative z-10">ENTER THE DEPTHS →</span>
      <div id="progressBar"
           class="absolute inset-0 bg-white/25 scale-x-0 origin-left transition-transform duration-200"></div>
    </button>
  </form>

  <p class="text-center text-sm mt-8 text-cyan-100/70">
    Powered by <span class="footer-powered">MODA MALITHI</span>
  </p>
</div>

<!-- ===== OUTSIDE HIGHLIGHT CTA (REGISTER YOUR 4-MEMBER ARMY) ===== -->
<div class="army-cta" aria-label="Register your team">
  <div class="army-cta-inner">
    <div class="army-copy">
      <div class="badge-4">4</div>
      <div class="army-text">
        <div class="top">Bring your 4-member army.</div>
        <div class="sub">Register your army for the CTF.</div>
      </div>
    </div>

    <a class="army-btn" href="register.php">
      REGISTER ARMY <span class="arrow">→</span>
    </a>
  </div>
</div>

<!-- ===== CURSOR SPOTLIGHT + LOGO PROXIMITY GLOW ===== -->
<script>
const crest = document.getElementById('crest');
const meds  = document.querySelectorAll('.med');

document.addEventListener('mousemove', (e) => {
  const r = crest.getBoundingClientRect();
  const px = ((e.clientX - r.left) / r.width) * 100;
  const py = ((e.clientY - r.top) / r.height) * 100;
  crest.style.setProperty('--mx', px + '%');
  crest.style.setProperty('--my', py + '%');

  meds.forEach(m => {
    const mr = m.getBoundingClientRect();
    const cx = mr.left + mr.width/2;
    const cy = mr.top  + mr.height/2;
    const d  = Math.hypot(e.clientX - cx, e.clientY - cy);
    const max = 190;
    const g = Math.max(0, 1 - d/max);
    m.style.setProperty('--glow', g.toFixed(2));
  });
});
</script>

<!-- ===== LOADING BUTTON ===== -->
<script>
const form = document.getElementById("form");
const btn  = document.getElementById("authBtn");
const bar  = document.getElementById("progressBar");
const text = document.getElementById("btnText");
let loading = false;

form.addEventListener("submit", e => {
  if (loading) return;
  e.preventDefault();
  loading = true;

  btn.disabled = true;
  text.textContent = "DESCENDING…";

  let p = 0;
  const i = setInterval(() => {
    p += Math.random() * 12;
    if (p >= 100) {
      p = 100;
      bar.style.transform = "scaleX(1)";
      clearInterval(i);
      setTimeout(() => form.submit(), 300);
    } else {
      bar.style.transform = `scaleX(${p/100})`;
    }
  }, 120);
});
</script>

</body>
</html>
