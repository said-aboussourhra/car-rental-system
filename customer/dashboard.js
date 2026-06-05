// dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    
    // Count up animation for statistics
    const counters = document.querySelectorAll('.stat-card h3');
    
    function animateCounter(counter) {
        const target = parseFloat(counter.textContent.replace(/[^0-9.]/g, ''));
        const isAmount = counter.textContent.includes('DH') || counter.textContent.includes('درهم');
        let current = 0;
        const increment = target / 50;
        
        const updateCounter = () => {
            if (current < target) {
                current += increment;
                if (current > target) current = target;
                
                if (isAmount) {
                    counter.textContent = Math.floor(current).toLocaleString() + ' DH';
                } else {
                    counter.textContent = Math.floor(current);
                }
                
                requestAnimationFrame(updateCounter);
            } else {
                if (isAmount) {
                    counter.textContent = target.toLocaleString() + ' DH';
                } else {
                    counter.textContent = target;
                }
            }
        };
        
        updateCounter();
    }
    
    // Observe stat cards for animation
    if (window.IntersectionObserver) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target.querySelector('h3');
                    if (counter) animateCounter(counter);
                    observer.unobserve(entry.target);
                }
            });
        });
        
        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });
    }
    
    // Quick actions
    document.querySelector('.quick-book-btn')?.addEventListener('click', function() {
        window.location.href = '../cars.php';
    });
    
    // Smooth scroll
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
});