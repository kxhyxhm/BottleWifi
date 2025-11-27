<?php
session_start();
// Reset bottle count when starting fresh
$_SESSION['bottle_count'] = 0;
$_SESSION['verification_tokens'] = [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Collect Bottles - Bottle WiFi</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #f0fdf4; background-image: radial-gradient(circle at 10px 10px, rgba(147, 197, 153, 0.1) 2px, transparent 0); background-size: 24px 24px; }
.container { background: white; padding: 2rem; border-radius: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 100%; max-width: 28rem; margin: 1rem; border: 4px solid rgba(5,150,105,0.1); position: relative; }
.header { text-align: center; margin-bottom: 2rem; }
.emoji-row { display: flex; justify-content: center; gap: 0.5rem; margin-bottom: 1rem; }
.emoji { font-size: 1.5rem; }
.title { font-size: 1.5rem; font-weight: 600; color: #065f46; margin-bottom: 0.5rem; }
.subtitle { color: #059669; margin-bottom: 0.5rem; font-size: 0.9rem; }
.bottle-count-display { text-align: center; margin-bottom: 2rem; }
.count-number { font-size: 5rem; font-weight: 700; color: #059669; line-height: 1; margin-bottom: 1rem; }
.pulse-animation { animation: pulse 0.5s ease-out; }
@keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
.total-time { color: #059669; font-size: 1.25rem; font-weight: 500; margin-top: 0.5rem; }
.timer-section { text-align: center; margin-bottom: 2rem; display: none; }
.timer-display { font-size: 3rem; font-weight: 600; color: #059669; margin-bottom: 1rem; }
.progress-bar-container { width: 100%; background-color: #f0fdf4; border-radius: 9999px; padding: 2px; margin-top: 1rem; }
.progress-bar { height: 6px; border-radius: 9999px; background: linear-gradient(90deg, #34d399, #059669); box-shadow: 0 0 10px rgba(52,211,153,0.3); transition: width 1s linear; }
.button { width: 100%; background: linear-gradient(to right, #34d399, #059669); color: white; border: none; padding: 1rem 1.5rem; border-radius: 9999px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 1rem; }
.button:hover { transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0,0,0,0.15); }
.button:disabled { background: #9ca3af; cursor: not-allowed; opacity: 0.6; transform: none; }
.button-done { background: linear-gradient(to right, #059669, #047857); }
.status-message { padding: 1rem; background: #d1fae5; border: 2px solid #86efac; border-radius: 12px; text-align: center; color: #065f46; font-size: 0.9rem; font-weight: 500; margin-bottom: 1rem; display: none; }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="emoji-row">
                <span class="emoji">🌿</span>
                <span class="emoji">♻️</span>
                <span class="emoji">🌱</span>
            </div>
            <h1 class="title">Bottle Wifi</h1>
            <p class="subtitle" id="headerSubtitle">Drop bottles to earn WiFi time</p>
        </div>

        <div class="bottle-count-display">
            <div class="count-number" id="bottleCount">0</div>
            <p class="subtitle" id="bottlesCollectedText">No Bottles Yet</p>
            <p class="total-time" id="totalTime">0 minutes WiFi</p>
        </div>

        <div id="timerSection" class="timer-section">
            <div class="timer-display" id="timer">30</div>
            <p class="subtitle">Waiting for bottle...</p>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar" style="width: 100%"></div>
            </div>
        </div>

        <div id="statusMessage" class="status-message">
            ✓ Bottle detected!
        </div>

        <button id="addBottleButton" class="button">
            <span>Insert Bottle</span>
            <span class="emoji">♻️</span>
        </button>

        <button id="doneButton" class="button button-done" disabled>
            <span>Done - Get WiFi</span>
            <span class="emoji">✓</span>
        </button>

        <div id="errorMessage" style="display: none; background: #fee2e2; border: 2px solid #fca5a5; color: #991b1b; padding: 1rem; border-radius: 12px; margin-top: 1rem; font-size: 0.9rem;">
            <div style="font-weight: 600; margin-bottom: 0.5rem;">⚠️ Error</div>
            <div id="errorText"></div>
        </div>
    </div>

    <script>
        let bottleCount = 0;
        let verificationTokens = [];
        let isDetecting = false;
        let checkIR = null;
        let countdownInterval = null;

        const bottleCountDisplay = document.getElementById('bottleCount');
        const totalTimeDisplay = document.getElementById('totalTime');
        const addBottleButton = document.getElementById('addBottleButton');
        const doneButton = document.getElementById('doneButton');
        const timerSection = document.getElementById('timerSection');
        const timer = document.getElementById('timer');
        const progressBar = document.getElementById('progressBar');
        const statusMessage = document.getElementById('statusMessage');

        addBottleButton.addEventListener('click', function() {
            if (!isDetecting) {
                startBottleDetection();
            }
        });

        doneButton.addEventListener('click', function() {
            if (bottleCount === 0) {
                alert('Please insert at least one bottle!');
                return;
            }

            // Redirect to index.php with bottle count and tokens
            const params = new URLSearchParams({
                bottles: bottleCount,
                tokens: verificationTokens.join(',')
            });
            window.location.href = 'index.php?' + params.toString();
        });

        function startBottleDetection() {
            if (isDetecting) return;
            
            isDetecting = true;
            addBottleButton.disabled = true;
            timerSection.style.display = 'block';
            statusMessage.style.display = 'none';
            
            let timeLeft = 30;
            timer.textContent = timeLeft;
            progressBar.style.width = '100%';

            // Start countdown
            countdownInterval = setInterval(function() {
                timeLeft--;
                timer.textContent = timeLeft;
                progressBar.style.width = (timeLeft / 30 * 100) + '%';

                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    clearInterval(checkIR);
                    stopDetection();
                    alert('Timeout - no bottle detected. Try again!');
                }
            }, 1000);

            // Check for bottle every 500ms
            checkIR = setInterval(async function() {
                try {
                    const res = await fetch('ir.php');
                    const data = await res.json();

                    console.log('IR Response:', data);

                    // Check for errors from sensor
                    if (data.error) {
                        clearInterval(checkIR);
                        clearInterval(countdownInterval);
                        stopDetection();
                        showError('Sensor error: ' + data.error);
                        return;
                    }

                    if (data.detected) {
                        clearInterval(checkIR);
                        clearInterval(countdownInterval);

                        // Bottle detected!
                        bottleCount++;
                        verificationTokens.push(data.verification_token);

                        // Animate count
                        bottleCountDisplay.classList.add('pulse-animation');
                        bottleCountDisplay.textContent = bottleCount;
                        setTimeout(() => {
                            bottleCountDisplay.classList.remove('pulse-animation');
                        }, 500);

                       
                        // Fetch duration and update total time
                        fetch('settings_handler.php')
                            .then(res => res.json())
                            .catch(() => ({ wifi_time: 300 }))
                            .then(settings => {
                                const minutesPerBottle = Math.floor(settings.wifi_time / 60);
                                const totalMinutes = bottleCount * minutesPerBottle;
                                totalTimeDisplay.textContent = `${totalMinutes} minutes WiFi`;
                            });

                        // Show success message
                        statusMessage.style.display = 'block';
                        setTimeout(() => {
                            statusMessage.style.display = 'none';
                        }, 2000);

                        // Log bottle
                        fetch('log_bottle.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action: 'log_bottle' })
                        }).catch(err => console.error('Log error:', err));

                        stopDetection();

                        // Enable done button
                        doneButton.disabled = false;
                    }
                } catch (e) {
                    console.error('Fetch error:', e);
                }
            }, 500);
        }

        function stopDetection() {
            isDetecting = false;
            addBottleButton.disabled = false;
            timerSection.style.display = 'none';
        }

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            errorText.textContent = message;
            errorDiv.style.display = 'block';
        }
    </script>
</body>
</html>
