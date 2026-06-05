// admin.js - JavaScript for Admin Dashboard

document.addEventListener('DOMContentLoaded', function() {
    
    // ==================== SIDEBAR TOGGLE ====================
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.admin-sidebar');
    
    // Create overlay element
    const overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
            overlay.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        });
    }
    
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    });
    
    // Close sidebar on window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            sidebar?.classList.remove('show');
            overlay?.classList.remove('show');
            document.body.style.overflow = '';
        }
    });
    
    // ==================== ACTIVE NAV LINK ====================
    const currentPage = window.location.pathname.split('/').pop();
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPage || (currentPage === '' && href === 'index.php')) {
            link.classList.add('active');
        } else {
            link.classList.remove('active');
        }
    });
    
    // ==================== STATS COUNTER ANIMATION ====================
    const counters = document.querySelectorAll('.stat-card h3');
    
    function animateCounter(counter) {
        const target = parseFloat(counter.textContent.replace(/[^0-9.]/g, ''));
        const isAmount = counter.textContent.includes('DH') || counter.textContent.includes('درهم');
        let current = 0;
        const duration = 1500;
        const steps = 50;
        const increment = target / steps;
        const stepTime = duration / steps;
        let step = 0;
        
        const timer = setInterval(() => {
            step++;
            current += increment;
            
            if (step >= steps) {
                current = target;
                clearInterval(timer);
            }
            
            if (isAmount) {
                counter.textContent = Math.floor(current).toLocaleString() + ' DH';
            } else {
                counter.textContent = Math.floor(current);
            }
        }, stepTime);
    }
    
    // Observe stat cards
    if (window.IntersectionObserver) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target.querySelector('h3');
                    if (counter && counter.textContent !== '0') {
                        animateCounter(counter);
                    }
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });
    }
    
    // ==================== NOTIFICATION DROPDOWN ====================
    // Mark notifications as read (if implemented)
    document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const notificationId = this.getAttribute('data-id');
            if (notificationId) {
                fetch('api/mark-notification.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ id: notificationId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        this.closest('.dropdown-item').remove();
                        updateNotificationCount();
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        });
    });
    
    function updateNotificationCount() {
        const count = document.querySelectorAll('.dropdown-menu .dropdown-item').length;
        const badge = document.querySelector('.admin-topbar .badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
            } else {
                badge.remove();
            }
        }
    }
    
    // ==================== QUICK ACTIONS ====================
    // Confirm delete actions
    document.querySelectorAll('.delete-confirm').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const href = this.getAttribute('href');
            
            Swal.fire({
                title: 'هل أنت متأكد؟',
                text: 'لا يمكن التراجع عن هذا الإجراء!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'نعم، احذف',
                cancelButtonText: 'إلغاء'
            }).then((result) => {
                if (result.isConfirmed && href) {
                    window.location.href = href;
                }
            });
        });
    });
    
    // ==================== AJAX STATUS UPDATES ====================
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const bookingId = this.getAttribute('data-booking-id');
            const newStatus = this.checked ? 'confirmed' : 'pending';
            
            fetch('api/update-booking-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    this.checked = !this.checked;
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ',
                        text: data.message || 'فشل تحديث الحالة'
                    });
                }
            })
            .catch(error => {
                this.checked = !this.checked;
                console.error('Error:', error);
            });
        });
    });
    
    // ==================== DATE RANGE PICKER ====================
    if (typeof flatpickr !== 'undefined') {
        flatpickr(".date-range", {
            mode: "range",
            dateFormat: "Y-m-d",
            locale: "ar"
        });
    }
    
    // ==================== PRINT FUNCTION ====================
    document.querySelector('.print-btn')?.addEventListener('click', function() {
        window.print();
    });
    
    // ==================== EXPORT FUNCTIONS ====================
    document.querySelector('.export-csv')?.addEventListener('click', function() {
        const table = document.querySelector('.table');
        if (!table) return;
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        rows.forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(col => {
                rowData.push('"' + col.innerText.replace(/"/g, '""') + '"');
            });
            csv.push(rowData.join(','));
        });
        
        const csvContent = csv.join('\n');
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const url = URL.createObjectURL(blob);
        
        link.setAttribute('href', url);
        link.setAttribute('download', 'export_' + new Date().toISOString().split('T')[0] + '.csv');
        link.style.visibility = 'hidden';
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // ==================== AUTO REFRESH (every 5 minutes) ====================
    setInterval(() => {
        fetch('api/dashboard-stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDashboardStats(data.stats);
                }
            })
            .catch(error => console.error('Auto-refresh error:', error));
    }, 300000); // 5 minutes
});

/**
 * Update dashboard stats dynamically
 */
function updateDashboardStats(stats) {
    // Update stat cards if data is available
    const statCards = document.querySelectorAll('.stat-card h3');
    if (stats && statCards.length >= 4) {
        statCards[0].textContent = stats.total_cars || '0';
        statCards[1].textContent = stats.total_bookings || '0';
        statCards[2].textContent = stats.total_customers || '0';
        statCards[3].textContent = stats.monthly_revenue || '0 DH';
    }
}