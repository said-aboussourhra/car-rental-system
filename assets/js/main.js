/**
 * Premium Car Rental - main.js
 * دوال مشتركة لكل صفحات الموقع
 */
(function () {
    'use strict';

    // ============================================
    // زر العودة إلى الأعلى
    // ============================================
    function initBackToTop() {
        if (document.getElementById('backToTop')) return;
        var btn = document.createElement('button');
        btn.id = 'backToTop';
        btn.innerHTML = '<i class="fas fa-arrow-up"></i>';
        btn.style.cssText = 'position:fixed;bottom:25px;left:25px;width:46px;height:46px;border-radius:50%;' +
            'border:none;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;font-size:1rem;cursor:pointer;' +
            'opacity:0;visibility:hidden;transition:all .3s ease;z-index:999;box-shadow:0 8px 25px rgba(102,126,234,.4);';
        document.body.appendChild(btn);

        window.addEventListener('scroll', function () {
            if (window.scrollY > 400) {
                btn.style.opacity = '1';
                btn.style.visibility = 'visible';
            } else {
                btn.style.opacity = '0';
                btn.style.visibility = 'hidden';
            }
        });

        btn.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // ============================================
    // إخفاء تنبيهات Bootstrap تلقائياً
    // ============================================
    function autoHideAlerts() {
        document.querySelectorAll('.alert[data-autohide]').forEach(function (alert) {
            setTimeout(function () {
                alert.style.transition = 'opacity .6s ease';
                alert.style.opacity = '0';
                setTimeout(function () { alert.remove(); }, 600);
            }, 5000);
        });
    }

    // ============================================
    // إظهار إشعار Toast (يستخدمه أي صفحة)
    // message: النص | type: success | error | info
    // ============================================
    window.showToast = function (message, type) {
        type = type || 'info';
        var colors = { success: '#10b981', error: '#ef4444', info: '#667eea', warning: '#f59e0b' };

        if (typeof Toastify === 'function') {
            Toastify({
                text: message,
                duration: 4000,
                close: true,
                gravity: 'top',
                position: 'center',
                backgroundColor: colors[type] || colors.info,
                stopOnFocus: true
            }).showToast();
        } else if (window.Swal) {
            Swal.fire({
                toast: true, position: 'top', icon: type === 'error' ? 'error' : (type === 'warning' ? 'warning' : 'success'),
                title: message, timer: 3000, showConfirmButton: false
            });
        } else {
            alert(message);
        }
    };

    // ============================================
    // تنسيق أرقام الأسعار بالدرهم
    // ============================================
    window.formatMoney = function (amount) {
        return Number(amount || 0).toLocaleString('fr-MA', { maximumFractionDigits: 2 }) + ' DH';
    };

    // ============================================
    // السنة الحالية في الفوتر
    // ============================================
    function setYear() {
        document.querySelectorAll('[data-current-year]').forEach(function (el) {
            el.textContent = new Date().getFullYear();
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initBackToTop();
        autoHideAlerts();
        setYear();
    });
})();
