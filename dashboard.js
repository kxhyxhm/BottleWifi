// Common dashboard functionality
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

// Update current date
function updateDate() {
    const dateElement = document.getElementById('current-date');
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    dateElement.textContent = new Date().toLocaleDateString('en-US', options);
}

// Handle navigation
function initNavigation() {
    const navLinks = document.querySelectorAll('.nav-link');
    const currentPage = window.location.pathname.split('/').pop();

    navLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || 
            (currentPage === '' && href === 'dashboard.html') ||
            (currentPage === 'index.html' && href === 'dashboard.html')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    createDust();
    updateDate();
    setInterval(updateDate, 60000); // Update time every minute
    initNavigation();
});