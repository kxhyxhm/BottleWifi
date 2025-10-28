// Initialize dust particles animation
function createDustParticles() {
    const container = document.querySelector('.dust-container');
    for (let i = 0; i < 50; i++) {
        const dust = document.createElement('div');
        dust.className = 'dust';
        dust.style.left = Math.random() * 100 + '%';
        dust.style.animationDuration = (Math.random() * 3 + 2) + 's';
        dust.style.animationDelay = (Math.random() * 2) + 's';
        dust.style.setProperty('--tx', (Math.random() * 100 - 50) + 'px');
        container.appendChild(dust);
        
        dust.addEventListener('animationend', () => {
            dust.style.left = Math.random() * 100 + '%';
            dust.style.animationDelay = '0s';
        });
    }
}

// Initialize charts
function initializeCharts() {
    const usageCtx = document.getElementById('usageChart').getContext('2d');
    const recyclingCtx = document.getElementById('recyclingChart').getContext('2d');

    // Common chart options
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                grid: {
                    color: 'rgba(5, 150, 105, 0.1)'
                },
                ticks: {
                    color: '#65a88a'
                }
            },
            x: {
                grid: {
                    display: false
                },
                ticks: {
                    color: '#65a88a'
                }
            }
        }
    };

    // Usage Chart
    const usageChart = new Chart(usageCtx, {
        type: 'line',
        data: {
            labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
            datasets: [{
                label: 'Active Users',
                data: [4, 8, 15, 25, 30, 20],
                borderColor: '#059669',
                backgroundColor: 'rgba(5, 150, 105, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: commonOptions
    });

    // Recycling Chart
    const recyclingChart = new Chart(recyclingCtx, {
        type: 'bar',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Bottles Recycled',
                data: [12, 19, 15, 25, 22, 30, 18],
                backgroundColor: '#34d399',
                borderRadius: 8
            }]
        },
        options: commonOptions
    });
}

// Update stats with animation
function updateStats() {
    const stats = {
        'total-bottles': 245,
        'total-minutes': 1225,
        'active-users': 18,
        'avg-session': 25
    };

    for (const [id, target] of Object.entries(stats)) {
        const element = document.getElementById(id);
        const start = 0;
        const duration = 1500;
        const startTime = performance.now();

        function animate(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            
            const value = Math.floor(start + (target - start) * progress);
            element.textContent = value;

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }

        requestAnimationFrame(animate);
    }
}

// Handle period button clicks
function initializePeriodButtons() {
    const periodGroups = document.querySelectorAll('.chart-period');
    
    periodGroups.forEach(group => {
        group.addEventListener('click', (e) => {
            if (e.target.classList.contains('period-button')) {
                group.querySelectorAll('.period-button').forEach(btn => {
                    btn.classList.remove('active');
                });
                e.target.classList.add('active');
                // Here you would typically update the chart data based on the selected period
            }
        });
    });
}

// Initialize everything when the page loads
document.addEventListener('DOMContentLoaded', () => {
    createDustParticles();
    initializeCharts();
    updateStats();
    initializePeriodButtons();
});