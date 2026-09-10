/**
 * Premium Car Rental - validation.js
 * أدوات التحقق من صحة النماذج في جهة العميل
 *
 * الاستخدام: أعطِ النموذج data-validate="form"
 * وحقول الإدخال تحمل أنماط التحقق المطلوبة (مطابقة تلقائية).
 */
(function () {
    'use strict';

    var rules = {
        email: {
            test: function (v) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v); },
            msg: 'البريد الإلكتروني غير صالح'
        },
        phone: {
            test: function (v) { return /^[0-9+\s\-]{9,15}$/.test(v); },
            msg: 'رقم الهاتف غير صالح'
        },
        password: {
            test: function (v) { return v.length >= 8; },
            msg: 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'
        },
        name: {
            test: function (v) { return v.trim().length >= 3; },
            msg: 'الاسم مطلوب (3 أحرف على الأقل)'
        }
    };

    function fieldType(input) {
        if (input.dataset.rule) return input.dataset.rule;
        if (input.type === 'email') return 'email';
        if (input.type === 'tel') return 'phone';
        if (input.name === 'password' || input.name === 'new_password') return 'password';
        if (input.name === 'full_name') return 'name';
        return null;
    }

    function showError(input, message) {
        clearError(input);
        input.classList.add('is-invalid');
        var div = document.createElement('div');
        div.className = 'invalid-feedback d-block js-validation-error';
        div.textContent = message;
        input.parentNode.appendChild(div);
    }

    function clearError(input) {
        input.classList.remove('is-invalid');
        var next = input.parentNode.querySelector('.js-validation-error');
        if (next) next.remove();
    }

    function validateInput(input) {
        var type = fieldType(input);
        if (!type || !rules[type]) return true;
        if (!input.value && !input.required) { clearError(input); return true; }
        if (!rules[type].test(input.value)) {
            showError(input, rules[type].msg);
            return false;
        }
        clearError(input);
        return true;
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-validate="form"]').forEach(function (form) {
            // تحقق فوري أثناء الكتابة
            form.querySelectorAll('input, select, textarea').forEach(function (input) {
                input.addEventListener('blur', function () { validateInput(input); });
                input.addEventListener('input', function () {
                    if (input.classList.contains('is-invalid')) validateInput(input);
                });
            });

            // التحقق عند الإرسال
            form.addEventListener('submit', function (e) {
                var valid = true;
                form.querySelectorAll('input[required], input[data-rule]').forEach(function (input) {
                    if (!validateInput(input)) valid = false;
                });

                // تطابق كلمتي المرور
                var pass = form.querySelector('input[name="password"]');
                var confirm = form.querySelector('input[name="confirm_password"]');
                if (pass && confirm && confirm.value && pass.value !== confirm.value) {
                    showError(confirm, 'كلمتا المرور غير متطابقتين');
                    valid = false;
                }

                if (!valid) {
                    e.preventDefault();
                    var firstInvalid = form.querySelector('.is-invalid');
                    if (firstInvalid) firstInvalid.focus();
                }
            });
        });
    });
})();
