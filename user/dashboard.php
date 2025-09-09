<?php
session_start();
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/logger.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit;
}

log_activity($pdo, $_SESSION['user_id'], "Visited Dashboard", $_SERVER['REQUEST_URI']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — APIIT CTF</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
body { font-family: 'Source Code Pro', monospace; background:#0b0f12; color:#c9f7e4; overflow-x:hidden; }

/* Sidebar */
.sidebar { background:#071018; border-right:1px solid rgba(45,226,138,0.2); height:100vh; }
.sidebar a { display:block; padding:12px; color:#c9f7e4; border-bottom:1px solid rgba(255,255,255,0.05); transition:0.3s; }
.sidebar a:hover { background:rgba(45,226,138,0.1); color:#2de28a; }

/* Main Content */
h1 { color:#2de28a; font-size:2.5rem; font-weight:bold; margin-bottom:2rem; }

/* Cards */
.card { background: rgba(8,11,18,0.95); border:1px solid rgba(45,226,138,0.3); border-radius:12px; padding:20px; margin-bottom:20px; box-shadow:0 0 10px rgba(0,0,0,0.3); transition: transform 0.3s, box-shadow 0.3s; }
.card:hover { transform: translateY(-5px); box-shadow:0 0 30px rgba(45,226,138,0.5); }

/* Collapsible Buttons */
.collapsible { background: rgba(8,11,18,0.9); color: #2de28a; cursor: pointer; padding: 12px 16px; width: 100%; border: 1px solid rgba(45,226,138,0.3); border-radius:10px; text-align: left; font-weight:bold; font-size:1.1rem; margin-bottom:8px; transition: 0.3s; }
.collapsible:hover { background: rgba(45,226,138,0.1); }

/* Mind Map / Tree */
.tree-root { list-style:none; padding-left:0; }
.tree-root li { margin:8px 0; cursor:pointer; position:relative; }
.tree-root li::before { content:''; position:absolute; left:-12px; top:12px; width:8px; height:2px; background:#2de28a; }
.tree { list-style:none; padding-left:20px; margin-top:6px; display:none; transition: max-height 0.4s ease, opacity 0.4s ease; opacity:0; }
.tree li:hover { color:#1ab66b; }

/* Links */
a { color:#2de28a; text-decoration:underline; }
a:hover { color:#1ab66b; }
</style>
</head>
<body class="h-screen flex">

<!-- Sidebar -->
<div class="sidebar w-64">
  <h2 class="text-green-400 text-xl font-bold p-4 border-b border-green-500">APIIT CTF</h2>
  <a href="dashboard.php">🏠 Dashboard</a>
  <a href="challenges.php">🛠 Challenges</a>
  <a href="leaderboard.php">🏆 Leaderboard</a>
  <a href="profile.php">👤 Profile</a>
  <a href="hints.php">💡 Hints</a>
  <a href="../logout.php" class="text-red-400">🚪 Logout</a>
</div>

<!-- Main Content -->
<div class="flex-1 p-6 overflow-auto">
  <h1>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>!</h1>

  <!-- OWASP Top 10 -->
  <button class="collapsible">🛡️ OWASP Top 10 Web Security Risks</button>
  <div class="content card max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
    <ul class="tree-root">
      <li>A01: Broken Access Control
        <ul class="tree">
          <li>Missing function-level access control</li>
          <li>Unauthorized URL access</li>
          <li>Privilege escalation</li>
        </ul>
      </li>
      <li>A02: Cryptographic Failures
        <ul class="tree">
          <li>Hardcoded secrets</li>
          <li>Weak encryption</li>
          <li>Improper certificate validation</li>
        </ul>
      </li>
      <li>A03: Injection
        <ul class="tree">
          <li>SQL Injection</li>
          <li>Command Injection</li>
          <li>LDAP/XML Injection</li>
        </ul>
      </li>
      <li>A04: Insecure Design
        <ul class="tree">
          <li>Missing security controls</li>
          <li>Poor threat modeling</li>
        </ul>
      </li>
      <li>A05: Security Misconfiguration
        <ul class="tree">
          <li>Default credentials</li>
          <li>Verbose error messages</li>
        </ul>
      </li>
      <li>A06: Vulnerable & Outdated Components
        <ul class="tree">
          <li>Old libraries</li>
          <li>Unpatched software</li>
        </ul>
      </li>
      <li>A07: Identification & Authentication Failures
        <ul class="tree">
          <li>Broken authentication</li>
          <li>Session hijacking</li>
        </ul>
      </li>
      <li>A08: Software & Data Integrity Failures
        <ul class="tree">
          <li>Untrusted CI/CD</li>
          <li>Deserialization attacks</li>
        </ul>
      </li>
      <li>A09: Security Logging & Monitoring Failures
        <ul class="tree">
          <li>Missing logs</li>
          <li>Alerting failures</li>
        </ul>
      </li>
      <li>A10: SSRF
        <ul class="tree">
          <li>Internal server access</li>
          <li>Sensitive data exfiltration</li>
        </ul>
      </li>
    </ul>
    <p class="mt-2 text-sm">Learn more: <a href="https://owasp.org/Top10/" target="_blank">OWASP Top 10</a></p>
  </div>

  <!-- CTF Tips -->
  <button class="collapsible">🎯 CTF Tips & Best Practices</button>
  <div class="content card max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
    <ul>
      <li>Read challenges carefully.</li>
      <li>Document commands and flags.</li>
      <li>Practice web, crypto, binary, reversing.</li>
      <li>Use safe VM environments.</li>
      <li>Analyze past write-ups.</li>
      <li>Work methodically; avoid brute-force.</li>
      <li>Collaborate with teammates.</li>
    </ul>
  </div>

  <!-- Tools -->
  <button class="collapsible">🛠️ Recommended CTF Tools</button>
  <div class="content card max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
    <ul>
      <li>Burp Suite, OWASP ZAP, Wireshark</li>
      <li>Ghidra / IDA Pro</li>
      <li>nmap, Metasploit</li>
      <li>CyberChef, Python & Bash scripting</li>
    </ul>
  </div>

  <!-- Learning Resources -->
  <button class="collapsible">📚 Learning Resources</button>
  <div class="content card max-h-0 overflow-hidden transition-all duration-500 ease-in-out">
    <ul>
      <li><a href="https://ctftime.org/" target="_blank">CTFTime.org</a></li>
      <li><a href="https://tryhackme.com/" target="_blank">TryHackMe</a></li>
      <li><a href="https://hackthebox.com/" target="_blank">Hack The Box</a></li>
      <li><a href="https://owasp.org/" target="_blank">OWASP</a></li>
      <li><a href="https://portswigger.net/web-security" target="_blank">PortSwigger Academy</a></li>
    </ul>
  </div>

</div>

<script>
// Main collapsibles
document.querySelectorAll('.collapsible').forEach(btn => {
  btn.addEventListener('click', () => {
    const content = btn.nextElementSibling;
    if(content.style.maxHeight && content.style.maxHeight !== '0px') {
      content.style.maxHeight = '0';
    } else {
      content.style.maxHeight = content.scrollHeight + 'px';
    }
  });
});

// Tree nested toggle
document.querySelectorAll('.tree-root > li').forEach(item => {
  item.addEventListener('click', e => {
    e.stopPropagation();
    const sub = item.querySelector('.tree');
    if(sub) {
      if(sub.style.display === 'block') {
        sub.style.opacity = 0;
        setTimeout(() => sub.style.display='none', 300);
      } else {
        sub.style.display = 'block';
        setTimeout(() => sub.style.opacity=1, 50);
      }
    }
  });
});
</script>

</body>
</html>
