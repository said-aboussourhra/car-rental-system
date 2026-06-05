// booking.js - JavaScript for Booking Page

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize Datepickers
    const pickupDatePicker = flatpickr(".datepicker[name='pickup_date']", {
        locale: "ar",
        dateFormat: "Y-m-d",
        minDate: "today",
        disableMobile: "true",
        onChange: function(selectedDates, dateStr, instance) {
            // Update return date min date
            if (returnDatePicker) {
                returnDatePicker.set('minDate', dateStr);
            }
            updateBookingSummary();
            checkAvailability();
        }
    });
    
    const returnDatePicker = flatpickr(".datepicker[name='return_date']", {
        locale: "ar",
        dateFormat: "Y-m-d",
        minDate: "today",
        disableMobile: "true",
        onChange: function(selectedDates, dateStr, instance) {
            updateBookingSummary();
        }
    });
    
    // Extras Quantity Buttons
    document.querySelectorAll('.minus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.extra-quantity');
            if (input.value > 0) {
                input.value = parseInt(input.value) - 1;
                updateBookingSummary();
                animateQuantity(input);
            }
        });
    });
    
    document.querySelectorAll('.plus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const input = this.parentElement.querySelector('.extra-quantity');
            const max = parseInt(input.max);
            if (parseInt(input.value) < max) {
                input.value = parseInt(input.value) + 1;
                updateBookingSummary();
                animateQuantity(input);
            }
        });
    });
    
    document.querySelectorAll('.extra-quantity').forEach(input => {
        input.addEventListener('change', function() {
            const value = parseInt(this.value);
            const max = parseInt(this.max);
            if (value < 0) this.value = 0;
            if (value > max) this.value = max;
            updateBookingSummary();
        });
    });
    
    // Toggle Password Fields
    const createAccountCheckbox = document.getElementById('createAccount');
    const passwordFields = document.getElementById('passwordFields');
    
    if (createAccountCheckbox && passwordFields) {
        createAccountCheckbox.addEventListener('change', function() {
            passwordFields.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) {
                passwordFields.querySelectorAll('input').forEach(input => {
                    input.value = '';
                    input.removeAttribute('required');
                });
            } else {
                passwordFields.querySelectorAll('input').forEach(input => {
                    input.setAttribute('required', 'required');
                });
            }
        });
    }
    
    // Form Validation and Submission
    const bookingForm = document.getElementById('bookingForm');
    
    bookingForm?.addEventListener('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.add('was-validated');
            
            // Scroll to first error
            const firstError = this.querySelector(':invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        } else {
            // Show loading state
            const submitBtn = this.querySelector('[type="submit"]');
            submitBtn.classList.add('btn-loading');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري المعالجة...';
        }
    });
    
    // Initial booking summary update
    updateBookingSummary();
    
    // Animate quantity change
    function animateQuantity(input) {
        input.style.transform = 'scale(1.2)';
        setTimeout(() => {
            input.style.transform = 'scale(1)';
        }, 200);
    }
    
    // Check car availability via AJAX
    function checkAvailability() {
        const pickupDate = document.querySelector('[name="pickup_date"]')?.value;
        const returnDate = document.querySelector('[name="return_date"]')?.value;
        const carId = <?php echo $car_id; ?>;
        
        if (pickupDate && returnDate) {
            const availabilityDiv = document.getElementById('availabilityMessage');
            
            fetch(api/check-availability.php?car_id=${carId}&pickup=${pickupDate}&return=${returnDate})
                .then(response => response.json())
                .then(data => {
                    if (!data.available) {
                        if (!availabilityDiv) {
                            const div = document.createElement('div');
                            div.id = 'availabilityMessage';
                            div.className = 'alert alert-danger mt-2';
                            div.innerHTML = '<i class="fas fa-exclamation-circle"></i> السيارة غير متوفرة في هذه التواريخ. يرجى اختيار تواريخ أخرى.';
                            document.querySelector('[name="pickup_date"]').parentElement.appendChild(div);
                        }
                    } else {
                        if (availabilityDiv) {
                            availabilityDiv.remove();
                        }
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    }
});

// Update Booking Summary
function updateBookingSummary() {
    const pickupDate = document.querySelector('[name="pickup_date"]')?.value;
    const returnDate = document.querySelector('[name="return_date"]')?.value;
    const dailyRate = <?php echo $car['daily_rate']; ?>;
    
    if (pickupDate && returnDate) {
        const start = new Date(pickupDate);
        const end = new Date(returnDate);
        const days = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
        
        if (days > 0) {
            // Update days
            document.getElementById('summaryDays').textContent = days + ' أيام';
            
            // Calculate subtotal
            const subtotal = days * dailyRate;
            document.getElementById('summarySubtotal').textContent = subtotal.toLocaleString() + ' درهم';
            
            // Calculate extras
            let extrasTotal = 0;
            document.querySelectorAll('.extra-quantity').forEach(input => {
                const quantity = parseInt(input.value);
                if (quantity > 0) {
                    const extraId = input.name.match(/\[(\d+)\]/)[1];
                    const extraRate = <?php echo json_encode(array_column($extras, 'daily_rate', 'id')); ?>[extraId] || 0;
                    extrasTotal += extraRate * quantity * days;
                }
            });
            document.getElementById('summaryExtras').textContent = extrasTotal.toLocaleString() + ' درهم';
            
            // Calculate discount
            let subtotalWithExtras = subtotal + extrasTotal;
            let discount = 0;
            
            if (days >= 30) {
                discount = subtotalWithExtras * 0.2; // 20% monthly discount
            } else if (days >= 7) {
                discount = subtotalWithExtras * 0.1; // 10% weekly discount
            }
            
            document.getElementById('summaryDiscount').textContent = '-' + discount.toLocaleString() + ' درهم';
            
            // Calculate tax
            const amountAfterDiscount = subtotalWithExtras - discount;
            const taxRate = <?php echo TAX_RATE; ?>;
            const tax = amountAfterDiscount * (taxRate / 100);
            document.getElementById('summaryTax').textContent = tax.toLocaleString() + ' درهم';
            
            // Calculate total
            const total = amountAfterDiscount + tax;
            document.getElementById('summaryTotal').textContent = total.toLocaleString() + ' درهم';
            
        } else {
            resetSummary();
        }
    } else {
        resetSummary();
    }
}

// Reset Summary
function resetSummary() {
    document.getElementById('summaryDays').textContent = '- أيام';
    document.getElementById('summarySubtotal').textContent = '-';
    document.getElementById('summaryExtras').textContent = '0.00 درهم';
    document.getElementById('summaryDiscount').textContent = '-0.00 درهم';
    document.getElementById('summaryTax').textContent = '-';
    document.getElementById('summaryTotal').textContent = '-';
}

// Apply Coupon
function applyCoupon() {
    const couponCode = document.querySelector('[name="coupon_code"]').value.trim();
    const messageDiv = document.querySelector('.coupon-message');
    
    if (!couponCode) {
        messageDiv.innerHTML = '<span class="text-danger">يرجى إدخال كود الخصم</span>';
        return;
    }
    
    // Show loading
    messageDiv.innerHTML = '<span class="text-info"><i class="fas fa-spinner fa-spin"></i> جاري التحقق...</span>';
    
    fetch('api/validate-coupon.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            coupon_code: couponCode.toUpperCase(),
            amount: calculateCurrentTotal()
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = `
                <span class="text-success">
                    <i class="fas fa-check-circle"></i> ${data.message}
                </span>
            `;
            updateBookingSummaryWithCoupon(data.discount);
        } else {
            messageDiv.innerHTML = `
                <span class="text-danger">
                    <i class="fas fa-times-circle"></i> ${data.message}
                </span>
            `;
        }
    })
    .catch(error => {
        messageDiv.innerHTML = '<span class="text-danger">حدث خطأ. يرجى المحاولة مرة أخرى.</span>';
        console.error('Error:', error);
    });
}

// Calculate current total for coupon validation
function calculateCurrentTotal() {
    const subtotalText = document.getElementById('summarySubtotal').textContent;
    const extrasText = document.getElementById('summaryExtras').textContent;
    
    const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;
    const extras = parseFloat(extrasText.replace(/[^0-9.]/g, '')) || 0;
    
    return subtotal + extras;
}

// Update summary with coupon discount
function updateBookingSummaryWithCoupon(couponDiscount) {
    const currentDiscount = parseFloat(document.getElementById('summaryDiscount').textContent.replace(/[^0-9.]/g, '')) || 0;
    const totalDiscount = currentDiscount + couponDiscount;
    
    document.getElementById('summaryDiscount').textContent = '-' + totalDiscount.toLocaleString() + ' درهم';
    
    // Recalculate total
    const subtotal = parseFloat(document.getElementById('summarySubtotal').textContent.replace(/[^0-9.]/g, '')) || 0;
    const extras = parseFloat(document.getElementById('summaryExtras').textContent.replace(/[^0-9.]/g, '')) || 0;
    const taxRate = <?php echo TAX_RATE; ?>;
    const amountAfterDiscount = subtotal + extras - totalDiscount;
    const tax = amountAfterDiscount * (taxRate / 100);
    const total = amountAfterDiscount + tax;
    
    document.getElementById('summaryTax').textContent = tax.toLocaleString() + ' درهم';
    document.getElementById('summaryTotal').textContent = total.toLocaleString() + ' درهم';
}