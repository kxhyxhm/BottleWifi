<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bottle WiFi</title>
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
.info-pill { display: inline-block; background-color: #f0fdf4; border-radius: 9999px; padding: 0.5rem 1rem; color: #059669; font-size: 0.875rem; }
.timer-section { text-align: center; margin-bottom: 2rem; display: none; }
.timer-display { font-size: 3rem; font-weight: 600; color: #059669; margin-bottom: 1rem; }
.progress-bar-container { width: 100%; background-color: #f0fdf4; border-radius: 9999px; padding: 2px; margin-top: 1rem; }
.progress-bar { height: 6px; border-radius: 9999px; background: linear-gradient(90deg, #34d399, #059669); box-shadow: 0 0 10px rgba(52,211,153,0.3); transition: width 1s linear; }
.start-button { width: 100%; background: linear-gradient(to right, #34d399, #059669); color: white; border: none; padding: 1rem 1.5rem; border-radius: 9999px; font-size: 1rem; font-weight: 500; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.start-button:hover { transform: translateY(-1px); box-shadow: 0 6px 8px rgba(0,0,0,0.15); }
.success-message { display: none; margin-top: 1rem; padding: 1.5rem; background: linear-gradient(135deg, #f0fdf4, #dcfce7); border: 1px solid #86efac; border-radius: 12px; text-align: center; }
.status-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1.5rem; }
.status-card { background: white; padding: 0.75rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.status-label { font-size: 0.75rem; color: #059669; font-weight: 500; }
.status-value { color: #059669; }
.network-info { margin-top: 1rem; font-size: 0.75rem; color: #059669; text-align: center; }
.dust-container { position: absolute; top:0; left:0; right:0; bottom:0; overflow:hidden; pointer-events:none; }
.dust { position: absolute; width:3px; height:3px; background: rgba(250,204,21,0.2); border-radius:50%; }
@keyframes float-up { 0%{transform:translateY(100%) translateX(0) scale(0);opacity:0;} 50%{opacity:0.5;} 100%{transform:translateY(-100%) translateX(var(--tx)) scale(1);opacity:0;} }
@keyframes pulse {0%{transform:scale(1);}50%{transform:scale(1.02);}100%{transform:scale(1);} }
.pulse { animation: pulse 2s infinite; }
@media (max-width:640px){.container{padding:1.5rem;margin:0.75rem;border-radius:16px;}.title{font-size:1.25rem;}.emoji{font-size:1.25rem;}.timer-display{font-size:2.5rem;}.start-button{padding:0.875rem 1.25rem;font-size:0.9375rem;}.status-grid{gap:0.75rem;}.status-card{padding:0.625rem;} }
</style>

</head>
<body>
<body>
    <div class="container">
        <div class="dust-container"></div>
        
        <div class="header">
            <div class="emoji-row">
                <span class="emoji">🌿</span>
                <span class="emoji">♻️</span>
                <span class="emoji">🌱</span>
            </div>
            <h1 class="title">Bottle WiFi</h1>
            <p class="subtitle">Insert a bottle to connect</p>
            <div class="info-pill">
                <span>1 Bottle = 5 minutes</span>
            </div>
        </div>

        <div id="timerSection" class="timer-section">
            <div class="timer-display" id="timer">30</div>
            <p class="subtitle">Please insert your bottle</p>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar" style="width: 100%"></div>
            </div>
        </div>

        <div id="startSection">
            <button id="startButton" class="start-button pulse">
                <span>Start Recycling</span>
                <span class="emoji">♻️</span>
            </button>

            <div class="admin-link">
                <a href="admin-login.php" class="admin-button">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    Admin Settings
                </a>
            </div>
        </div>

        <div id="successMessage" class="success-message">
            <div class="title">Connected! 🌿</div>
            <p class="subtitle">Thank you for recycling. Your 5 minutes of WiFi access starts now.</p>
            
            <div class="status-grid">
                <div class="status-card">
                    <div class="status-label">Status</div>
                    <div class="status-value">Active</div>
                </div>
                <div class="status-card">
                    <div class="status-label">Time Left</div>
                    <div class="status-value" id="timeRemaining">5:00</div>
                </div>
            </div>
            
            <div class="network-info">Network: Bottle_WiFi</div>
        </div>

        <div id="errorMessage" class="error-message" style="display: none; background: #fee; border: 1px solid #fcc; padding: 1rem; border-radius: 8px; margin-top: 1rem;">
            <div style="color: #c33; font-weight: 600; margin-bottom: 0.5rem;">⚠️ Error</div>
            <div id="errorText" style="color: #c33; font-size: 0.9rem;"></div>
            <div id="errorDebug" style="color: #999; font-size: 0.8rem; margin-top: 0.5rem; font-family: monospace;"></div>
        </div>
    </div>

    <script>
        function createDust() {
            const container = document.querySelector('.dust-container');
            for (let i = 0; i < 30; i++) {
                const dust = document.createElement('div');
                dust.className = 'dust';
                const size = Math.random() * 3 + 1;
                const startX = Math.random() * 100;
                const tx = (Math.random() - 0.5) * 50;
                const duration = Math.random() * 3 + 2;
                const delay = Math.random() * 2;
                
                dust.style.cssText = `
                    left: ${startX}%;
                    width: ${size}px;
                    height: ${size}px;
                    --tx: ${tx}px;
                    animation: float-up ${duration}s ease-in infinite ${delay}s;
                `;
                
                container.appendChild(dust);
            }
        }

        function showError(errorMsg, errorType, debug) {
            const errorDiv = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const errorDebug = document.getElementById('errorDebug');
            
            const errorDescriptions = {
                FILE_NOT_FOUND: "Sensor script not found. Check installation.",
                PYTHON_NOT_FOUND: "Python3 is not installed or not in PATH.",
                NO_OUTPUT: "Sensor did not respond. Check GPIO wiring and permissions.",
                INVALID_JSON: "Sensor returned invalid data.",
                PYTHON_ERROR: "Sensor script encountered an error.",
                MISSING_FIELD: "Sensor response missing expected data.",
                TIMEOUT: "No bottle detected within timeout period."
            };
            
            errorText.textContent = errorDescriptions[errorType] || errorMsg;
            
            if (debug) {
                let debugText = `Type: ${errorType || 'unknown'}`;
                
                // Show raw output if available
                if (debug.raw_output) {
                    debugText += `\nRaw Output: ${debug.raw_output}`;
                }
                
                if (debug.possible_causes && Array.isArray(debug.possible_causes)) {
                    debugText += `\nPossible: ${debug.possible_causes.join(', ')}`;
                }
                if (debug.python_version) {
                    debugText += `\nPython: ${debug.python_version}`;
                }
                if (debug.json_error) {
                    debugText += `\nJSON Error: ${debug.json_error}`;
                }
                
                errorDebug.textContent = debugText;
                console.log('Full debug object:', debug);
            }
            
            errorDiv.style.display = 'block';
            document.getElementById('startSection').style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', function () {
            createDust();

            const startButton = document.getElementById('startButton');
            const timerSection = document.getElementById('timerSection');
            const startSection = document.getElementById('startSection');
            const timer = document.getElementById('timer');
            const progressBar = document.getElementById('progressBar');
            const successMessage = document.getElementById('successMessage');
            
            let timeLeft = 30;
            let countdownInterval;

            const adminButton = document.querySelector('.admin-button');
            adminButton.addEventListener('click', function(e) {
                e.preventDefault();
                if (countdownInterval) { clearInterval(countdownInterval); }
                window.location.href = 'admin-login.php';
            });

            // Start button
            startButton.addEventListener('click', function () {
                startSection.style.display = 'none';
                timerSection.style.display = 'block';

                // IR CHECK LOOP
                // The IR sensor's built-in red LED will blink when bottle is near
                let checkIR = setInterval(async function () {
                    try {
                        const res = await fetch("ir.php");
                        const data = await res.json();
                        
                        console.log('IR Response:', data);

                        if (data.error) {
                            clearInterval(checkIR);
                            clearInterval(countdownInterval);
                            timerSection.style.display = 'none';
                            console.log('Showing error:', data.error_type);
                            showError(data.error, data.error_type, data.debug);
                            return;
                        }

                        if (data.detected) {
                            clearInterval(checkIR);
                            clearInterval(countdownInterval);
                            
                            timerSection.style.display = 'none';
                            successMessage.style.display = 'block';
                            
                            startWiFiTimer();
                        }
                    } catch (e) {
                        console.error('Fetch error:', e);
                    }
                }, 500);

                // Countdown
                countdownInterval = setInterval(function () {
                    timeLeft--;
                    timer.textContent = timeLeft;
                    progressBar.style.width = (timeLeft / 30 * 100) + "%";

                    if (timeLeft <= 0) {
                        clearInterval(countdownInterval);
                        clearInterval(checkIR);
                        timerSection.style.display = 'none';
                        showError("No bottle detected within 30 seconds", "TIMEOUT", {
                            'possible_causes': [
                                'Bottle not positioned in front of sensor',
                                'Sensor may not be wired correctly',
                                'GPIO pin permissions not configured',
                                'Check the sensor\'s red LED is blinking'
                            ]
                        });
                    }
                }, 1000);
            });

            function startWiFiTimer() {
                let timeLeft = 5 * 60;
                const display = document.getElementById('timeRemaining');

                // Get device MAC address and grant WiFi access
                fetch('hardware_control.php?action=wifi&subaction=grant&duration=5')
                    .then(res => res.json())
                    .then(data => {
                        console.log('WiFi granted:', data);
                        if (data.error) {
                            console.error('WiFi grant failed:', data.error);
                        }
                    })
                    .catch(err => console.error('WiFi control error:', err));

                // Log the bottle detection
                fetch('log_bottle.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'log_bottle' })
                }).catch(err => console.error('Failed to log bottle:', err));

                const wifiTimer = setInterval(function () {
                    timeLeft--;
                    const m = Math.floor(timeLeft / 60);
                    const s = timeLeft % 60;
                    display.textContent = `${m}:${s.toString().padStart(2, '0')}`;

                    if (timeLeft <= 0) {
                        clearInterval(wifiTimer);
                        successMessage.querySelector('.subtitle').textContent =
                            'Session ended. Insert another bottle.';
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>
