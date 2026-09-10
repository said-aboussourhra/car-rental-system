/**
 * Premium Car Rental - booking.js (نسخة الأصول المشتركة)
 * تهيئة منتقيات التواريخ العربية + فحص التوفر عبر الـ API
 * تعمل مع أي صفحة تعرض حقول: pickup_date / return_date / car_id
 */
(function () {
    'use strict';

    function initDatepickers() {
        if (typeof flatpickr === 'undefined') return;

        var pickup = document.querySelector('input[name="pickup_date"]');
        var ret = document.querySelector('input[name="return_date"]');
        if (!pickup || !ret) return;

        var pickupPicker = flatpickr(pickup, {
            locale: typeof flatpickr.l10ns.ar !== 'undefined' ? 'ar' : 'default',
            dateFormat: 'Y-m-d',
            minDate: 'today',
            onChange: function (selectedDates, dateStr) {
                retPicker.set('minDate', dateStr);
                window.__bookingDatesChanged && window.__bookingDatesChanged();
            }
        });

        var retPicker = flatpickr(ret, {
            locale: typeof flatpickr.l10ns.ar !== 'undefined' ? 'ar' : 'default',
            dateFormat: 'Y-m-d',
            minDate: pickup.value || 'today',
            onChange: function () {
                window.__bookingDatesChanged && window.__bookingDatesChanged();
            }
        });
    }

    /**
     * فحص توفر السيارة عبر الـ API
     * carId, pickupDate, returnDate, targetElementId
     */
    window.checkCarAvailability = function (carId, pickupDate, returnDate, targetElementId) {
        var box = document.getElementById(targetElementId || 'availabilityMessage');
        if (!carId || !pickupDate || !returnDate) return;

        fetch('api/check-availability.php?car_id=' + encodeURIComponent(carId) +
              '&pickup=' + encodeURIComponent(pickupDate) +
              '&return=' + encodeURIComponent(returnDate))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!box) return;
                if (data.available) {
                    box.innerHTML = '<span style="color:#10b981;font-weight:700;"><i class="fas fa-check-circle"></i> ' + data.message + '</span>';
                } else {
                    box.innerHTML = '<span style="color:#ef4444;font-weight:700;"><i class="fas fa-exclamation-circle"></i> ' + (data.message || 'السيارة غير متوفرة') + '</span>';
                }
            })
            .catch(function () { /* تجاهل أخطاء الشبكة */ });
    };

    document.addEventListener('DOMContentLoaded', initDatepickers);
})();
