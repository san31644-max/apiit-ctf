<?php
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
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>DataEntry Pro – Freelancer Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&family=Share+Tech+Mono&display=swap');

body{
  font-family:'Inter',sans-serif;
  margin:0;
  min-height:100vh;
  background:linear-gradient(135deg,#020617,#020b16,#001b2e);
  color:#e6faff;
}

/* Card */
.card{
  backdrop-filter: blur(14px);
  background: rgba(0, 20, 40, 0.4);
  border: 1px solid rgba(56,247,255,0.3);
  box-shadow: 0 0 40px rgba(56,247,255,.25);
}

/* Inputs */
.input{
  background: rgba(0,0,0,0.4);
  border: 1px solid rgba(56,247,255,.4);
  color:#e6faff;
}
.input:focus{
  outline:none;
  border-color:#38f7ff;
  box-shadow:0 0 15px rgba(56,247,255,.7);
}

/* Button */
.btn{
  background:linear-gradient(90deg,#38f7ff,#22d3ee,#f5d27b);
  color:#022c33;
  font-weight:900;
  letter-spacing:2px;
  transition:.25s;
}
.btn:hover{
  box-shadow:0 0 30px rgba(56,247,255,.5);
  transform:translateY(-2px);
}

/* Loading overlay */
.overlay{
  position:fixed;
  inset:0;
  background:rgba(0,0,0,.85);
  display:none;
  align-items:center;
  justify-content:center;
  z-index:999;
}
.overlay.on{ display:flex; }

.loader{
  width:120px;
  height:120px;
  border-radius:50%;
  border:6px solid rgba(56,247,255,.2);
  border-top-color:#38f7ff;
  animation:spin 1s linear infinite;
}
@keyframes spin{ to{transform:rotate(360deg);} }
</style>
</head>

<body class="flex items-center justify-center min-h-screen px-4">

<div class="overlay" id="overlay">
  <div class="loader"></div>
</div>

<div class="w-full max-w-md p-8 rounded-2xl card">

<h1 class="text-3xl text-center font-extrabold text-cyan-300 mb-1">
DATAENTRY PRO
</h1>
<p class="text-center text-cyan-200/70 mb-6 text-sm">
Freelancer Work Portal
</p>

<div id="errBox" class="hidden mb-4 p-3 text-red-300 border border-red-500 rounded bg-red-950/30"></div>

<form id="form" class="space-y-5">

  <div>
    <label class="text-cyan-200">Freelancer ID</label>
    <input name="username" required placeholder="Enter your Freelancer ID"
           class="w-full mt-1 px-3 py-2 rounded input">
  </div>

  <div>
    <label class="text-cyan-200">Password</label>
    <input type="password" name="password" required placeholder="Enter your password"
           class="w-full mt-1 px-3 py-2 rounded input">
  </div>

  <button type="submit" class="w-full py-3 rounded btn text-lg">
    LOGIN TO DASHBOARD →
  </button>

</form>

<p class="text-center text-sm mt-8 text-cyan-100/70">
Powered by <span class="text-yellow-300 font-bold">DataEntry Pro</span>
</p>

<p class="text-center mt-4">
<a href="register.php" class="text-cyan-300 hover:underline">
Apply as a Freelancer
</a>
</p>

</div>

<script>
const form = document.getElementById("form");
const overlay = document.getElementById("overlay");
const errBox = document.getElementById("errBox");

form.addEventListener("submit", async (e)=>{
  e.preventDefault();
  errBox.classList.add("hidden");
  overlay.classList.add("on");

  try{
    const res = await fetch("login_process.php",{
      method:"POST",
      body:new FormData(form),
      headers:{ "X-Requested-With":"fetch" }
    });

    const data = await res.json();

    if(!data.ok){
      overlay.classList.remove("on");
      errBox.textContent = data.message || "Login failed";
      errBox.classList.remove("hidden");
      return;
    }

    window.location.href = data.redirect;
  }
  catch(e){
    overlay.classList.remove("on");
    errBox.textContent = "Network error. Try again.";
    errBox.classList.remove("hidden");
  }
});
</script>

</body>
</html>
