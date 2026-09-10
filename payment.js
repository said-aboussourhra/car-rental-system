// payment.js - JavaScript for Payment Page

document.addEventListener('DOMContentLoaded', function() {
    
    // Stripe initialization
    let stripe, card;
    // يُقرأ المفتاح من وسم: <meta name="stripe-key" content="..."> في صفحة الدفع
    const stripeKey = (document.querySelector('meta[name="stripe-key"]') || {}).content || '';
    
    if (stripeKey && stripeKey !== 'pk_test_YOUR_KEY') {
        stripe = Stripe(stripeKey);
        const elements = stripe.elements({
            locale: 'ar',
            appearance: {
                theme: 'stripe',
                variables: {
                    colorPrimary: '#667eea',
                }
            }
        });
        card = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    fontFamily: '"Cairo", sans-serif',
                    color: '#32325d',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                }
            }
        });
    }
    
    // Payment Method Toggle
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            // Hide all details
            document.querySelectorAll('.card-details, .paypal-details, .bank-details').forEach(el => {
                el.style.display = 'none';
            });
            
            // Show selected method details
            switch (this.value) {
                case 'credit_card':
                    document.getElementById('cardDetails').style.display = 'block';
                    if (card && !card._element) {
                        card.mount('#card-element');
                    }
                    break;
                case 'paypal':
                    document.getElementById('paypalDetails').style.display = 'block';
                    initializePayPal();
                    break;
                case 'bank_transfer':
                    document.getElementById('bankDetails').style.display = 'block';
                    break;
            }
        });
    });
    
    // Payment Form Submission
    const paymentForm = document.getElementById('paymentForm');
    
    paymentForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
        
        if (paymentMethod === 'credit_card' && stripe) {
            handleStripePayment();
        } else if (paymentMethod === 'paypal') {
            // PayPal handled separately
            return;
        } else {
            // Cash or bank transfer - show confirmation
            Swal.fire({
                title: 'تأكيد الحجز',
                text: paymentMethod === 'cash' ? 'سيتم الدفع نقداً عند استلام السيارة' : 'سيتم تأكيد الحجز بعد استلام التحويل البنكي',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'تأكيد',
                cancelButtonText: 'إلغاء',
                confirmButtonColor: '#667eea'
            }).then((result) => {
                if (result.isConfirmed) {
                    showProcessingOverlay();
                    paymentForm.submit();
                }
            });
        }
    });
    
    // Initialize PayPal if needed
    function initializePayPal() {
        const paypalContainer = document.getElementById('paypal-button-container');
        if (paypalContainer && typeof paypal !== 'undefined') {
            const totalAmount =
            
            paypal.Buttons({
                createOrder: function(data, actions) {
                    return actions.order.create({
                        purchase_units: [{
                            amount: {
                                value: totalAmount.toFixed(2)
                            },
                          
                        }]
                    });
                },
                onApprove: function(data, actions) {
                    return actions.order.capture().then(function(details) {
                        // Add PayPal transaction ID to form
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'paypal_transaction_id';
                        input.value = details.id;
                        paymentForm.appendChild(input);
                        
                        // Submit form
                        showProcessingOverlay();
                        paymentForm.submit();
                    });
                },
                onCancel: function(data) {
                    Swal.fire({
                        icon: 'info',
                        title: 'تم الإلغاء',
                        text: 'تم إلغاء عملية الدفع عبر PayPal',
                    });
                },
                onError: function(err) {
                    Swal.fire({
                        icon: 'error',
                        title: 'خطأ في الدفع',
                        text: 'حدث خطأ أثناء معالجة الدفع عبر PayPal',
                    });
                }
            }).render('#paypal-button-container');
        }
    }
    
    // Handle Stripe Payment
    async function handleStripePayment() {
        if (!stripe || !card) return;
        
        showProcessingOverlay();
        
        const {token, error} = await stripe.createToken(card);
        
        if (error) {
            hideProcessingOverlay();
            document.getElementById('card-errors').textContent = error.message;
        } else {
            // Add token to form
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'stripeToken';
            input.value = token.id;
            paymentForm.appendChild(input);
            
            // Submit form
            paymentForm.submit();
        }
    }
    
    // Processing Overlay
    function showProcessingOverlay() {
        const overlay = document.createElement('div');
        overlay.className = 'payment-processing';
        overlay.innerHTML = `
            <div class="spinner"></div>
            <h3 class="mt-3">جاري معالجة الدفع...</h3>
            <p class="text-muted">يرجى الانتظار</p>
        `;
        document.body.appendChild(overlay);
    }
    
    function hideProcessingOverlay() {
        const overlay = document.querySelector('.payment-processing');
        if (overlay) overlay.remove();
    }
    
    // Auto-submit for cash payment (simplified flow)
    if (document.querySelector('input[value="cash"]')?.checked) {
        document.getElementById('cardDetails')?.style.display = 'none';
        document.getElementById('paypalDetails')?.style.display = 'none';
        document.getElementById('bankDetails')?.style.display = 'none';
    }
});