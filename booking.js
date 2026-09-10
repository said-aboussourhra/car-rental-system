/**
 * booking.js — نسخة مساعدة لصفحة الحجز
 * ملاحظة: صفحة الحجز تعتمد أساساً على السكربت المضمّن داخلها،
 * وهذا الملف يوفر الدوال المشتركة القابلة لإعادة الاستخدام.
 * يقرأ معرف السيارة من عنصر مخفي: <input type="hidden" id="car-id-field" value="...">
 */
(function () {
    'use strict';

    function getCarId() {
        var el = document.getElementById('car-id-field');
        return el ? parseInt(el.value, 10) : 0;
    }

    function getDates() {
        var pickup = document.querySelector('[name="pickup_date"]');
        var ret = document.querySelector('[name="return_date"]');
        return {
            pickup: pickup ? pickup.value : '',
            ret: ret ? ret.value : ''
        };
    }

    // فحص توفر السيارة في التواريخ المختارة
    function checkAvailability() {
        var dates = getDates();
        var carId = getCarId();

        if (!carId || !dates.pickup || !dates.ret) return;

        var url = 'api/check-availability.php?car_id=' + encodeURIComponent(carId) +
                  '&pickup=' + encodeURIComponent(dates.pickup) +
                  '&return=' + encodeURIComponent(dates.ret);

        fetch(url)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var availabilityDiv = document.getElementById('availabilityMessage');
                if (!data.available) {
                    var html = '<div class="alert alert-danger mt-2" id="availabilityMessage">' +
                               '<i class="fas fa-exclamation-circle"></i> ' +
                               (data.message || 'السيارة غير متوفرة في هذه التواريخ. يرجى اختيار تواريخ أخرى.') +
                               '</div>';
                    if (availabilityDiv) {
                        availabilityDiv.outerHTML = html;
                    } else {
                        var holder = document.querySelector('[name="pickup_date"]');
                        if (holder && holder.parentElement) {
                            holder.parentElement.insertAdjacentHTML('beforeend', html);
                        }
                    }
                } else if (availabilityDiv) {
                    availabilityDiv.remove();
                }
            })
            .catch(function () { /* تجاهل أخطاء الشبكة */ });
    }

    // ربط تلقائي بحقول التاريخ إن وُجدت
    document.addEventListener('DOMContentLoaded', function () {
        ['pickup_date', 'return_date'].forEach(function (name) {
            var input = document.querySelector('[name="' + name + '"]');
            if (input) {
                input.addEventListener('change', checkAvailability);
            }
        });
    });
})();
