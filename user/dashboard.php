<?php
session_start();

// Redirect if not logged in as 'user'
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'user') {
    header("Location: ../index.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Player';
$lastUpdated = date('Y-m-d H:i:s');
$pdfLink = "https://drive.google.com/file/d/1jdQbG6JSJDOE6MWOUofHe4B80nHKRQt-/view?usp=drive_link"; // direct download link
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Data Entry Sheet</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;800&family=Share+Tech+Mono&display=swap');

:root{
  --aqua:#38f7ff;
  --gold:#f5d27b;
  --text:#e6faff;
  --glass: rgba(0, 14, 24, 0.30);
  --stroke: rgba(56,247,255,0.18);
  --shadow: rgba(56,247,255,0.12);
}

html,body{height:100%; margin:0; font-family:'Share Tech Mono', monospace; color: var(--text); background:#000;}
.video-bg{position:fixed; inset:0; z-index:-10; overflow:hidden; background:#00101f;}
.video-bg video{width:100%;height:100%;object-fit:cover;object-position:center;transform:scale(1.03);filter:saturate(1.05) contrast(1.05);}
.video-overlay{position:fixed; inset:0; z-index:-9; pointer-events:none; background: linear-gradient(180deg, rgba(0,0,0,0.1), rgba(0,0,0,0.55));}
.shell{min-height:100vh; display:flex; flex-direction:column; padding:22px;}
.panel{backdrop-filter: blur(14px); background: var(--glass); border:1px solid var(--stroke); box-shadow:0 0 55px var(--shadow), inset 0 0 18px rgba(255,255,255,0.05); border-radius:22px; margin-bottom:20px; padding:20px;}
.h1{font-family:'Cinzel',serif; font-weight:900; color: var(--aqua);}
.small{font-size:12px;color:rgba(230,250,255,0.72);}
.table-container{overflow:auto; max-height:500px;}
table{width:100%; border-collapse:collapse;}
th,td{border:1px solid rgba(56,247,255,0.3); padding:8px; text-align:center;}
th{background: rgba(56,247,255,0.1);}
input{width:100%; background:transparent; border:none; color: var(--text); padding:4px;}
button{cursor:pointer;}
button:disabled{opacity:0.5; cursor:not-allowed;}
.locked td{background: rgba(255,255,255,0.05);}
</style>
</head>
<body>

<div class="video-bg">
  <video autoplay muted loop playsinline preload="auto">
    <source src="../assets/atlantis.mp4" type="video/mp4">
  </video>
</div>
<div class="video-overlay"></div>

<div class="shell">
  <div class="panel flex justify-between items-center">
    <div>
      <div class="h1 text-2xl">WELCOME, <?= htmlspecialchars($username) ?> 🔱</div>
      <div class="small mt-1">Last updated: <?= htmlspecialchars($lastUpdated) ?></div>
    </div>
    <div class="flex gap-2">
      <div class="small">Countdown Timer: <span id="countdown">06:00:00</span></div>
      <button onclick="logout()" class="px-3 py-1 bg-red-600 rounded hover:bg-red-500">🚪 Logout</button>
    </div>
  </div>

  <div class="panel">
    <div class="h1 text-xl mb-3">📝 Data Entry Sheet</div>
    <div class="flex justify-between mb-3 gap-2 flex-wrap">
      <button id="addBtn" onclick="addRow()" class="px-4 py-2 bg-teal-500 rounded hover:bg-teal-400">➕ Add Row</button>
      <button id="exportBtn" onclick="exportCSV()" class="px-4 py-2 bg-gold-500 rounded hover:bg-yellow-400">💾 Export CSV</button>
      <button id="pdfBtn" onclick="downloadPDF()" class="px-4 py-2 bg-blue-500 rounded hover:bg-blue-400">📄 Download PDF</button>
      <button id="lockBtn" onclick="lockSheet()" class="px-4 py-2 bg-green-600 rounded hover:bg-green-500">✅ COMPLETED</button>
    </div>

    <div class="table-container">
      <table id="dataSheet">
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>Age</th>
            <th>Email</th>
            <th>Department</th>
            <th>Task Completed</th>
            <th>Remarks</th>
            <th>Delete</th>
          </tr>
        </thead>
        <tbody>
          <!-- Initial row -->
          <tr>
            <td>1</td>
            <td contenteditable="true"></td>
            <td contenteditable="true"></td>
            <td contenteditable="true"></td>
            <td contenteditable="true"></td>
            <td contenteditable="true"></td>
            <td contenteditable="true"></td>
            <td><button onclick="deleteRow(this)">❌</button></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// ===== ADD / DELETE ROWS =====
function addRow(){
  const table = document.getElementById('dataSheet').getElementsByTagName('tbody')[0];
  const rowCount = table.rows.length + 1;
  const row = table.insertRow();
  row.innerHTML = `
    <td>${rowCount}</td>
    <td contenteditable="true"></td>
    <td contenteditable="true"></td>
    <td contenteditable="true"></td>
    <td contenteditable="true"></td>
    <td contenteditable="true"></td>
    <td contenteditable="true"></td>
    <td><button onclick="deleteRow(this)">❌</button></td>
  `;
}

function deleteRow(btn){
  const row = btn.parentNode.parentNode;
  row.parentNode.removeChild(row);
  const table = document.getElementById('dataSheet').getElementsByTagName('tbody')[0];
  for(let i=0;i<table.rows.length;i++){
    table.rows[i].cells[0].innerText = i+1;
  }
}

// ===== EXPORT CSV =====
function exportCSV(){
  const table = document.getElementById('dataSheet');
  let csv = [];
  for(let i=0;i<table.rows.length;i++){
    let row = [], cols = table.rows[i].cells;
    for(let j=0;j<cols.length-1;j++){ // exclude delete button
      row.push(cols[j].innerText.replace(/,/g,";"));
    }
    csv.push(row.join(","));
  }
  const csvStr = csv.join("\n");
  const blob = new Blob([csvStr], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'data_sheet.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// ===== DOWNLOAD PDF =====
function downloadPDF(){
  window.open("<?= $pdfLink ?>", "_blank");
}

// ===== COUNTDOWN 6 HOURS =====
let countdownSeconds = 6*60*60;
function updateCountdown(){
  const h = String(Math.floor(countdownSeconds/3600)).padStart(2,'0');
  const m = String(Math.floor((countdownSeconds%3600)/60)).padStart(2,'0');
  const s = String(countdownSeconds%60).padStart(2,'0');
  document.getElementById('countdown').innerText = `${h}:${m}:${s}`;
  if(countdownSeconds>0) countdownSeconds--;
}
setInterval(updateCountdown,1000);

// ===== LOCK / COMPLETED =====
function lockSheet(){
  const table = document.getElementById('dataSheet');

  // disable contenteditable
  const tds = table.querySelectorAll('td[contenteditable="true"]');
  tds.forEach(td => td.setAttribute('contenteditable','false'));

  // disable add row
  document.getElementById('addBtn').disabled = true;

  // disable delete buttons
  const delBtns = table.querySelectorAll('button');
  delBtns.forEach(btn => {
    if(btn.id !== 'exportBtn' && btn.id !== 'lockBtn' && btn.id !== 'pdfBtn'){
      btn.disabled = true;
    }
  });

  table.classList.add('locked');
  alert("✅ Data sheet is now LOCKED. You can still export CSV and download PDF.");
}

// ===== LOGOUT =====
function logout(){
  window.location.href = '../logout.php';
}
</script>

</body>
</html>
