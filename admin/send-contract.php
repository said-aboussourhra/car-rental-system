<?php
/**
 * Premium Car Rental - Send Contract to Customer
 * Auto-sends when admin confirms booking
 */
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

$booking_id = intval($_GET['id'] ?? $_POST['booking_id'] ?? 0);

// Fetch booking with all details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               c.brand, c.model, c.year, c.color, c.engine_size, c.transmission, c.fuel_type, 
               c.seats, c.doors, c.daily_rate, c.deposit, c.mileage_limit, c.extra_mileage_rate,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               u.address as customer_address
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        JOIN users u ON b.user_id = u.id 
        WHERE b.id = :bid
    ");
    $stmt->execute([':bid' => $booking_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        set_message('الحجز غير موجود', 'danger');
        header('Location: bookings-management.php');
        exit();
    }
} catch (Exception $e) {
    set_message('خطأ: ' . $e->getMessage(), 'danger');
    header('Location: bookings-management.php');
    exit();
}

// Generate Contract Number
$contract_number = 'CONT-' . date('Ymd') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
$contract_date = new DateTime();
$pickup_date = new DateTime($booking['pickup_date']);
$return_date = new DateTime($booking['return_date']);
$total_deposit = $booking['deposit_amount'] ?? ($booking['daily_rate'] * 3);

// Generate invoice if not exists
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    if ($stmt->fetchColumn() == 0) {
        $inv_number = generate_invoice_number();
        $stmt = $pdo->prepare("INSERT INTO invoices (booking_id, invoice_number, invoice_date, due_date, amount, tax_amount, total_amount, status) VALUES (?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, 'unpaid')");
        $stmt->execute([$booking_id, $inv_number, $booking['subtotal'] + ($booking['extras_charges'] ?? 0), $booking['tax_amount'], $booking['total_amount']]);
    }
} catch (Exception $e) {}

// ============================================
// CREATE BEAUTIFUL CONTRACT EMAIL
// ============================================
$contract_link = SITE_URL . '/contract.php?booking_id=' . $booking_id;
$payment_link = SITE_URL . '/payment.php?booking_id=' . $booking_id;
$dashboard_link = SITE_URL . '/customer/my-bookings.php';

$subject = "🎉 تم تأكيد حجزك | عقد الإيجار | " . SITE_NAME;

$message = '
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Cairo", "Arial", sans-serif; background: #f0f2f5; margin: 0; padding: 0; }
        .email-container { max-width: 650px; margin: 20px auto; background: #ffffff; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
        
        /* Header */
        .email-header { background: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%); padding: 35px 25px; text-align: center; color: white; position: relative; }
        .email-header .logo { font-size: 3rem; margin-bottom: 10px; }
        .email-header h1 { font-family: "Cairo", sans-serif; font-size: 1.6rem; font-weight: 900; margin: 0; color: #c9a84c; letter-spacing: 2px; }
        .email-header .subtitle { font-size: 0.9rem; opacity: 0.7; margin-top: 5px; }
        .email-header::after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 4px; background: linear-gradient(90deg, #c9a84c, #e5c76b, #c9a84c); }
        
        /* Body */
        .email-body { padding: 30px; }
        
        /* Success Box */
        .success-box { background: linear-gradient(135deg, #d1fae5, #a7f3d0); border-radius: 14px; padding: 22px; text-align: center; margin-bottom: 25px; border: 2px solid #10b981; }
        .success-box .icon { font-size: 3rem; margin-bottom: 10px; }
        .success-box h2 { color: #065f46; font-size: 1.3rem; margin: 0 0 5px 0; font-weight: 900; }
        .success-box p { color: #047857; margin: 0; font-weight: 500; }
        
        /* Info Cards */
        .info-card { background: #f8f9fa; border-radius: 12px; padding: 20px; margin-bottom: 15px; border: 1px solid #e0e0e0; }
        .info-card h3 { font-size: 1.05rem; color: #1a1a2e; margin: 0 0 15px 0; display: flex; align-items: center; gap: 8px; font-weight: 800; }
        .info-card h3 .icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e8e8e8; font-size: 0.88rem; }
        .info-row:last-child { border-bottom: none; }
        .info-row .label { color: #6c757d; font-weight: 500; }
        .info-row .value { font-weight: 700; color: #1a1a2e; }
        .info-row .value.highlight { color: #667eea; font-size: 1.1rem; }
        
        /* Buttons */
        .btn-group { text-align: center; margin: 30px 0 15px; }
        .btn {
            display: inline-block; padding: 15px 32px; border-radius: 50px; font-weight: 700; font-size: 1rem;
            text-decoration: none; margin: 6px; transition: all 0.3s; cursor: pointer; font-family: "Cairo", sans-serif;
        }
        .btn-contract { background: linear-gradient(135deg, #1a1a2e, #2d2d44); color: #c9a84c; border: 2px solid #c9a84c; }
        .btn-contract:hover { background: #c9a84c; color: #1a1a2e; transform: translateY(-3px); box-shadow: 0 12px 30px rgba(201,168,76,0.3); }
        .btn-payment { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-payment:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(16,185,129,0.3); }
        .btn-dashboard { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-dashboard:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(102,126,234,0.3); }
        .btn-whatsapp { background: #25d366; color: white; }
        .btn-whatsapp:hover { transform: translateY(-3px); box-shadow: 0 12px 30px rgba(37,211,102,0.3); }
        
        /* Footer */
        .email-footer { background: #1a1a2e; padding: 22px; text-align: center; color: rgba(255,255,255,0.6); font-size: 0.8rem; }
        .email-footer strong { color: #c9a84c; }
        .email-footer .company-name { font-size: 1rem; font-weight: 700; color: white; margin-bottom: 8px; }
        
        /* Responsive */
        @media (max-width: 600px) {
            .email-body { padding: 20px; }
            .btn { display: block; margin: 8px 0; }
            .info-row { flex-direction: column; gap: 4px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            <div class="logo">🚗</div>
            <h1>PREMIUM CAR RENTAL</h1>
            <div class="subtitle">Location de Voitures de Luxe</div>
        </div>
        
        <!-- Body -->
        <div class="email-body">
            <div class="success-box">
                <div class="icon">✅</div>
                <h2>تم تأكيد حجزك بنجاح!</h2>
                <p>مرحباً ' . htmlspecialchars($booking['customer_name']) . '، نود إعلامك بأنه تم تأكيد حجزك.</p>
            </div>
            
            <!-- Vehicle Info -->
            <div class="info-card">
                <h3><span class="icon" style="background:#e8f0fe;color:#1a1a2e;">🚘</span> معلومات السيارة</h3>
                <div class="info-row"><span class="label">السيارة</span><span class="value">' . htmlspecialchars($booking['brand'] . ' ' . $booking['model'] . ' ' . $booking['year']) . '</span></div>
                <div class="info-row"><span class="label">اللون</span><span class="value">' . htmlspecialchars($booking['color'] ?? 'غير محدد') . '</span></div>
                <div class="info-row"><span class="label">ناقل الحركة</span><span class="value">' . ($booking['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي') . '</span></div>
                <div class="info-row"><span class="label">الوقود</span><span class="value">' . $booking['fuel_type'] . '</span></div>
                <div class="info-row"><span class="label">المقاعد</span><span class="value">' . $booking['seats'] . ' مقاعد</span></div>
            </div>
            
            <!-- Booking Info -->
            <div class="info-card">
                <h3><span class="icon" style="background:#fef3c7;color:#92400e;">📋</span> تفاصيل الحجز</h3>
                <div class="info-row"><span class="label">رقم الحجز</span><span class="value">' . $booking['booking_number'] . '</span></div>
                <div class="info-row"><span class="label">رقم العقد</span><span class="value" style="color:#c9a84c;">' . $contract_number . '</span></div>
                <div class="info-row"><span class="label">تاريخ الاستلام</span><span class="value">' . $pickup_date->format('d/m/Y H:i') . '</span></div>
                <div class="info-row"><span class="label">تاريخ التسليم</span><span class="value">' . $return_date->format('d/m/Y H:i') . '</span></div>
                <div class="info-row"><span class="label">المدة</span><span class="value">' . $booking['total_days'] . ' أيام</span></div>
            </div>
            
            <!-- Payment Info -->
            <div class="info-card">
                <h3><span class="icon" style="background:#d1fae5;color:#065f46;">💰</span> تفاصيل الدفع</h3>
                <div class="info-row"><span class="label">الإيجار الأساسي</span><span class="value">' . number_format($booking['subtotal'], 2) . ' MAD</span></div>
                <div class="info-row"><span class="label">الإضافات</span><span class="value">' . number_format($booking['extras_charges'] ?? 0, 2) . ' MAD</span></div>
                <div class="info-row"><span class="label">الضريبة (20%)</span><span class="value">' . number_format($booking['tax_amount'], 2) . ' MAD</span></div>
                <div class="info-row" style="font-weight:900;font-size:1.05rem;"><span class="label">المجموع الكلي</span><span class="value highlight">' . number_format($booking['total_amount'], 2) . ' MAD</span></div>
                <div class="info-row"><span class="label">التأمين (مسترد)</span><span class="value">' . number_format($total_deposit, 2) . ' MAD</span></div>
            </div>
            
            <!-- Buttons -->
            <div class="btn-group">
                <a href="' . $contract_link . '" class="btn btn-contract" target="_blank">
                    📄 عرض وتحميل العقد
                </a>
                <a href="' . $payment_link . '" class="btn btn-payment" target="_blank">
                    💳 الدفع الآن
                </a>
                <a href="' . $dashboard_link . '" class="btn btn-dashboard" target="_blank">
                    📊 لوحة التحكم
                </a>
                <a href="https://wa.me/212600000000?text=مرحباً، لدي استفسار بخصوص الحجز ' . $booking['booking_number'] . '" class="btn btn-whatsapp" target="_blank">
                    💬 واتساب
                </a>
            </div>
            
            <p style="text-align:center;color:#6c757d;font-size:0.85rem;margin-top:20px;">
                للمساعدة، اتصل بنا على: <strong style="color:#1a1a2e;">+212 6 00 00 00 00</strong>
            </p>
        </div>
        
        <!-- Footer -->
        <div class="email-footer">
            <div class="company-name">Premium Car Rental SARL</div>
            Avenue Mohammed V, Immeuble Atlas, 3ème Étage, Guéliz, Marrakech<br>
            Tél: +212 5 24 30 00 00 | Email: contact@premiumcarrental.ma<br>
            RC: 45678 | IF: 87654321 | ICE: 009876543210<br>
            <small>© ' . date('Y') . ' Premium Car Rental. Tous droits réservés.</small>
        </div>
    </div>
</body>
</html>';

// ============================================
// SEND EMAIL
// ============================================
$email_sent = send_email($booking['customer_email'], $subject, $message);

// Confirm booking
try {
    if ($booking['booking_status'] === 'pending') {
        $pdo->prepare("UPDATE bookings SET booking_status = 'confirmed' WHERE id = ?")->execute([$booking_id]);
    }
} catch (Exception $e) {}

// Result message
if ($email_sent) {
    set_message('✅ تم تأكيد الحجز وإرسال العقد إلى ' . htmlspecialchars($booking['customer_email']), 'success');
} else {
    set_message('⚠️ تم تأكيد الحجز ولكن تعذر إرسال البريد. يمكن للعميل تحميل العقد من حسابه.', 'warning');
}

header('Location: bookings-management.php');
exit();
?>