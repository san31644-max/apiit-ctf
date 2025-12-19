<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>⚡ AFTERMATH TIMER ⚡</title>
<style>
    @import url('https://fonts.googleapis.com/css2?family=Orbitron:wght@500&display=swap');

    body {
        margin: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        background: #000;
        font-family: 'Orbitron', sans-serif;
        overflow: hidden;
        color: #00ffcc;
    }

    .cyber-bg {
        position: absolute;
        width: 100%;
        height: 100%;
        background: linear-gradient(45deg, rgba(0,255,204,0.05) 1px, transparent 1px),
                    linear-gradient(-45deg, rgba(0,255,204,0.05) 1px, transparent 1px);
        background-size: 20px 20px;
        animation: bgMove 4s linear infinite;
        z-index: -1;
    }

    @keyframes bgMove {
        0% { background-position: 0 0, 0 0; }
        100% { background-position: 40px 40px, -40px -40px; }
    }

    .container {
        text-align: center;
        z-index: 1;
    }

    h1 {
        font-size: 3rem;
        text-shadow: 0 0 10px #00ffcc, 0 0 20px #00ffcc, 0 0 40px #00ffcc;
        margin-bottom: 30px;
    }

    .timer {
        font-size: 7rem;
        letter-spacing: 10px;
        border: 3px solid #00ffcc;
        padding: 30px 80px;
        border-radius: 20px;
        box-shadow: 0 0 30px #00ffcc, 0 0 60px #00ffcc inset, 0 0 90px #00ffcc;
        animation: pulse 1.5s infinite alternate;
        margin-bottom: 40px;
    }

    @keyframes pulse {
        0% { text-shadow: 0 0 20px #00ffcc, 0 0 40px #00ffcc; }
        100% { text-shadow: 0 0 40px #00ffcc, 0 0 80px #00ffcc; }
    }

    .buttons {
        display: flex;
        justify-content: center;
        gap: 25px;
    }

    button {
        font-family: 'Orbitron', monospace;
        font-size: 1.5rem;
        padding: 15px 35px;
        border: 2px solid #00ffcc;
        border-radius: 12px;
        background: transparent;
        color: #00ffcc;
        cursor: pointer;
        transition: 0.2s;
        text-transform: uppercase;
        box-shadow: 0 0 15px #00ffcc;
    }

    button:hover {
        background-color: #00ffcc;
        color: #000;
        box-shadow: 0 0 40px #00ffcc, 0 0 80px #00ffcc inset;
    }
</style>
</head>
<body>

<div class="cyber-bg"></div>

<div class="container">
    <h1>⚡ AFTERMATH ENDING IN ⚡</h1>
    <div class="timer" id="timer">05:00:00</div>
    <div class="buttons">
        <button id="startBtn">Start</button>
        <button id="pauseBtn">Pause</button>
        <button id="resetBtn">Reset</button>
    </div>
</div>

<script>
    let timeLeft = 5 * 60 * 60; // 5 hours
    let countdown = null;
    const timerEl = document.getElementById('timer');

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600).toString().padStart(2,'0');
        const m = Math.floor((seconds % 3600)/60).toString().padStart(2,'0');
        const s = (seconds % 60).toString().padStart(2,'0');
        return `${h}:${m}:${s}`;
    }

    function updateTimer() {
        timerEl.textContent = formatTime(timeLeft);
        if (timeLeft <= 60) {
            // last minute warning effect
            timerEl.style.color = '#ff0033';
            timerEl.style.textShadow = '0 0 20px #ff0033, 0 0 40px #ff0033';
        } else {
            timerEl.style.color = '#00ffcc';
            timerEl.style.textShadow = '0 0 30px #00ffcc, 0 0 60px #00ffcc inset';
        }
    }

    function startTimer() {
        if (!countdown) {
            countdown = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    updateTimer();
                } else {
                    clearInterval(countdown);
                    countdown = null;
                    timerEl.textContent = "⚠ TIME'S UP ⚠";
                }
            }, 1000);
        }
    }

    function pauseTimer() {
        clearInterval(countdown);
        countdown = null;
    }

    function resetTimer() {
        pauseTimer();
        timeLeft = 5 * 60 * 60;
        updateTimer();
    }

    // Initialize display
    updateTimer();

    document.getElementById('startBtn').addEventListener('click', startTimer);
    document.getElementById('pauseBtn').addEventListener('click', pauseTimer);
    document.getElementById('resetBtn').addEventListener('click', resetTimer);
</script>

</body>
</html>
