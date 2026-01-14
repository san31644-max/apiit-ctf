<?php
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
  header("Location: ../index.php");
  exit;
}

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* Your branding */
$brandName = "Freelancer Payments";
$heroTitle = "Client Payment Details";
$heroSub   = "Please fill in the details below to confirm your payment information.";
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h($brandName) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@700;900&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --gold:#f5d27b;
  --glass: rgba(0, 14, 24, 0.25);
  --stroke: rgba(56,247,255,0.18);
  --shadow: rgba(56,247,255,0.12);
  --text: #e6faff;
}

body{margin:0;color:var(--text);background:#000;min-height:100vh;overflow-x:hidden;}
.video-bg{position:fixed; inset:0; z-index:-6; overflow:hidden; background:#00101f;}
.video-bg video{width:100%;height:100%;object-fit:cover;filter:saturate(1.05) contrast(1.05);}
.video-overlay{position:fixed; inset:0; z-index:-5; pointer-events:none;
  background:radial-gradient(900px 420px at 55% 12%, rgba(56,247,255,0.14), transparent 62%),
           linear-gradient(180deg, rgba(0,0,0,0.10), rgba(0,0,0,0.55));
}

.panel{
  backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
  background: var(--glass);
  border: 1px solid var(--stroke);
  box-shadow: 0 0 55px var(--shadow), inset 0 0 18px rgba(255,255,255,0.05);
  border-radius: 22px;
}
.h1{
  font-family:'Cinzel',serif;
  font-weight:900;
  letter-spacing:.14em;
  color: rgba(56,247,255,0.92);
  text-shadow: 0 0 18px rgba(56,247,255,0.22);
}
.mono{font-family:'Share Tech Mono', monospace;}
.small{font-size:12px;color: rgba(230,250,255,0.72);}

.label{font-family:'Share Tech Mono', monospace; letter-spacing:.08em; color:rgba(230,250,255,0.80); font-size:12px;}
.input{
  width:100%;
  border-radius:16px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(0,0,0,0.28);
  padding:12px 12px;
  color: rgba(230,250,255,0.92);
  outline:none;
}
.input:focus{box-shadow:0 0 0 3px rgba(56,247,255,0.14);}
.btn{
  border-radius:16px;
  padding:10px 12px;
  border:1px solid rgba(56,247,255,0.18);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.10em;
  transition:.22s;
}
.btn-primary{
  background: linear-gradient(90deg, rgba(56,247,255,0.92), rgba(34,211,238,0.72), rgba(245,210,123,0.75));
  color:#00131f;
}
.btn-primary:hover{box-shadow:0 0 26px rgba(56,247,255,0.20); transform: translateY(-1px);}
.btn-ghost{
  background: rgba(255,255,255,0.04);
  color: rgba(230,250,255,0.90);
}
.badge{
  display:inline-flex; align-items:center;
  padding:4px 10px;
  border-radius:999px;
  border:1px solid rgba(255,255,255,0.10);
  background: rgba(255,255,255,0.03);
  font-family:'Share Tech Mono', monospace;
  font-weight:900;
  letter-spacing:.10em;
  font-size:12px;
}
.badge.gold{border-color:rgba(245,210,123,0.30); color: rgba(245,210,123,0.95); background: rgba(245,210,123,0.08);}

.toast{
  position:fixed; right:18px; bottom:18px; z-index:9999;
  min-width:280px; max-width:420px;
  border-radius:18px;
  border:1px solid rgba(56,247,255,0.18);
  background: rgba(0, 14, 24, 0.55);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  box-shadow: 0 0 40px rgba(56,247,255,0.14);
  padding:12px 14px;
  display:none;
}
.toast .t{font-family:'Cinzel',serif;font-weight:900;letter-spacing:.10em;color: rgba(56,247,255,0.92);}
.toast .d{margin-top:6px;font-family:'Share Tech Mono', monospace;color: rgba(230,250,255,0.78);font-size:12px;line-height:1.4;}
</style>
</head>

<body>
<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>

<main class="max-w-5xl mx-auto p-5 md:p-8 space-y-6">
  <section class="panel p-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <div class="h1 text-2xl md:text-3xl"><?= h($heroTitle) ?></div>
        <div class="mono small mt-2"><?= h($heroSub) ?></div>
      </div>
      <div class="badge gold">SECURE FORM</div>
    </div>
  </section>

  <section class="panel p-6">
    <form id="payForm" class="grid grid-cols-1 md:grid-cols-2 gap-4" autocomplete="on">

      <div>
        <div class="label mb-1">Full Name</div>
        <input class="input" name="client_name" id="client_name" placeholder="Enter your full name" required>
      </div>

      <div>
        <div class="label mb-1">Email Address</div>
        <input class="input" name="client_email" id="client_email" type="email" placeholder="Enter your email" required>
      </div>

      <div>
        <div class="label mb-1">Amount</div>
        <input class="input mono" name="amount" id="amount" inputmode="decimal" placeholder="e.g., 250.00" required>
      </div>

      <div>
        <div class="label mb-1">Currency</div>
        <select class="input mono" name="currency" id="currency" required>
          <option>USD</option>
          <option>EUR</option>
          <option>LKR</option>
          <option>GBP</option>
          <option>AUD</option>
          <option>CAD</option>
          <option>SGD</option>
          <option>INR</option>
        </select>
      </div>

      <div>
        <div class="label mb-1">Payment Method</div>
        <select class="input mono" name="method" id="method" required>
          <option value="Binance Pay">Binance Pay</option>
          <option value="Crypto Transfer">Crypto Transfer</option>
          <option value="Bank Transfer">Bank Transfer</option>
          <option value="PayPal">PayPal</option>
        </select>
      </div>

      <div>
        <div class="label mb-1">Binance Pay ID (if applicable)</div>
        <input class="input mono" name="binance_pay_id" id="binance_pay_id" placeholder="Enter your Binance Pay ID">
      </div>

      <div class="md:col-span-2">
        <div class="label mb-1">Invoice / Reference</div>
        <input class="input mono" name="reference" id="reference" placeholder="e.g., INV-2026-001 / Project name" required>
      </div>

      <div class="md:col-span-2">
        <div class="label mb-1">Notes (optional)</div>
        <textarea class="input mono" name="notes" id="notes" rows="4" placeholder="Add any notes or instructions..."></textarea>
      </div>

      <div class="md:col-span-2 flex gap-3 flex-wrap mt-2">
        <button class="btn btn-primary" type="submit">SUBMIT DETAILS</button>
        <button class="btn btn-ghost" type="button" id="resetBtn">RESET</button>
      </div>
    </form>
  </section>
</main>

<div class="toast" id="toast">
  <div class="t" id="toastT">STATUS</div>
  <div class="d" id="toastD">—</div>
</div>

<script>
const toast = document.getElementById('toast');
const toastT = document.getElementById('toastT');
const toastD = document.getElementById('toastD');
let toastTimer=null;

function showToast(title, desc){
  toastT.textContent = title;
  toastD.textContent = desc;
  toast.style.display = 'block';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(()=> toast.style.display='none', 3200);
}

document.getElementById('resetBtn').addEventListener('click', ()=>{
  document.getElementById('payForm').reset();
  showToast('RESET', 'Form cleared.');
});

/*
  IMPORTANT:
  Since you said you can’t change databases, this demo does not save.
  Hook this submit to your existing endpoint (email sender / webhook / existing PHP handler).
*/
document.getElementById('payForm').addEventListener('submit', async (e)=>{
  e.preventDefault();

  // Basic front-end validation
  const name = document.getElementById('client_name').value.trim();
  const email = document.getElementById('client_email').value.trim();
  const amount = document.getElementById('amount').value.trim();
  const reference = document.getElementById('reference').value.trim();

  if(!name || !email || !amount || !reference){
    showToast('ERROR', 'Please fill required fields.');
    return;
  }

  // Replace this with your real submit:
  // fetch('your_existing_endpoint.php', { method:'POST', body:new FormData(payForm) })

  showToast('SUBMITTED', 'Your details have been submitted successfully.');
  document.getElementById('payForm').reset();
});
</script>
</body>
</html>
