<?php
/**
 * Premium Car Rental - Moroccan Payment System
 * All Moroccan Banks - Luxury Design
 */
require_once 'includes/config.php';

if (!is_logged_in()) {
    $_SESSION['redirect_after_login'] = 'payment.php?booking_id=' . ($_GET['booking_id'] ?? 0);
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : ($_SESSION['last_booking_id'] ?? 0);
if ($booking_id <= 0) { header('Location: customer/my-bookings.php'); exit(); }

// Fetch booking
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.brand, c.model, c.year, c.color,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b JOIN cars c ON b.car_id = c.id 
        WHERE b.id = :bid AND b.user_id = :uid
    ");
    $stmt->execute([':bid' => $booking_id, ':uid' => $user_id]);
    $booking = $stmt->fetch();
    if (!$booking) { header('Location: customer/my-bookings.php'); exit(); }
} catch (Exception $e) { header('Location: customer/my-bookings.php'); exit(); }

$already_paid = ($booking['payment_status'] === 'paid');

// ============================================
// ALL MOROCCAN BANKS WITH LOGOS
// ============================================
$moroccan_banks = [
    'cih' => [
        'name' => 'CIH BANK',
        'logo' => 'https://www.cihbank.ma/fr/sites/default/files/logo-cih-bank.png',
        'color' => '#003d8f',
        'bg' => '#e8f0fe',
        'account' => '230 123 4567890123456789 01',
        'iban' => 'MA64 230 123 4567890123456789 01',
        'swift' => 'CIHMMAMC'
    ],
    'awb' => [
        'name' => 'Attijariwafa Bank',
        'logo' => 'https://www.attijariwafabank.com/sites/default/files/logo-awb.png',
        'color' => '#8b0000',
        'bg' => '#fce4e4',
        'account' => '007 987 6543210987654321 01',
        'iban' => 'MA64 007 987 6543210987654321 01',
        'swift' => 'BCMAMAMC'
    ],
    'bcp' => [
        'name' => 'Banque Populaire',
        'logo' => 'https://www.gbp.ma/PublishingImages/logo-bcp.png',
        'color' => '#0066cc',
        'bg' => '#dbeafe',
        'account' => '120 456 7890123456789012 01',
        'iban' => 'MA64 120 456 7890123456789012 01',
        'swift' => 'BCPOMAMC'
    ],
    'bMCE' => [
        'name' => 'Bank of Africa',
        'logo' => 'https://www.bankofafrica.ma/sites/default/files/logo-boa.png',
        'color' => '#1a5276',
        'bg' => '#d6eaf8',
        'account' => '011 789 0123456789012345 01',
        'iban' => 'MA64 011 789 0123456789012345 01',
        'swift' => 'BMCEMAMC'
    ],
    'sg' => [
        'name' => 'Société Générale Maroc',
        'logo' => 'https://www.sgmaroc.com/sites/default/files/logo-sg.png',
        'color' => '#cc0000',
        'bg' => '#fce4e4',
        'account' => '022 321 6549870123456789 01',
        'iban' => 'MA64 022 321 6549870123456789 01',
        'swift' => 'SGMBMAMC'
    ],
    'cdm' => [
        'name' => 'Crédit du Maroc',
        'logo' => 'https://www.creditdumaroc.ma/sites/default/files/logo-cdm.png',
        'color' => '#e67e22',
        'bg' => '#fef3c7',
        'account' => '021 654 3210987654321098 01',
        'iban' => 'MA64 021 654 3210987654321098 01',
        'swift' => 'CDMAMAMC'
    ],
    'cam' => [
        'name' => 'Crédit Agricole du Maroc',
        'logo' => 'https://www.credit-agricole.ma/sites/default/files/logo-cam.png',
        'color' => '#27ae60',
        'bg' => '#d1fae5',
        'account' => '140 147 2583690123456789 01',
        'iban' => 'MA64 140 147 2583690123456789 01',
        'swift' => 'CNCAMAMC'
    ],
    'albarid' => [
        'name' => 'Al Barid Bank',
        'logo' => 'https://www.albaridbank.ma/sites/default/files/logo-abb.png',
        'color' => '#e74c3c',
        'bg' => '#fadbd8',
        'account' => '350 123 4567890123456789 01',
        'iban' => 'MA64 350 123 4567890123456789 01',
        'swift' => 'ABBMMAMC'
    ],
    'bmci' => [
        'name' => 'BMCI',
        'logo' => 'https://www.bmci.ma/sites/default/files/logo-bmci.png',
        'color' => '#2c3e50',
        'bg' => '#d5dbdb',
        'account' => '013 123 4567890123456789 01',
        'iban' => 'MA64 013 123 4567890123456789 01',
        'swift' => 'BMCIMAMC'
    ],
    'cfG' => [
        'name' => 'CFG Bank',
        'logo' => 'https://www.cfgbank.com/sites/default/files/logo-cfg.png',
        'color' => '#8e44ad',
        'bg' => '#f5eef8',
        'account' => '045 123 4567890123456789 01',
        'iban' => 'MA64 045 123 4567890123456789 01',
        'swift' => 'CFGBMAMC'
    ]
];

// Handle Payment
$payment_success = false;
$payment_error = '';
$selected_bank = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = clean_input($_POST['payment_method'] ?? 'cash');
    $selected_bank = clean_input($_POST['bank'] ?? '');
    $transaction_ref = clean_input($_POST['transaction_ref'] ?? '');
    
    try {
        // Add column if missing
        try { $pdo->exec("ALTER TABLE bookings ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NULL AFTER payment_status"); } catch (Exception $e) {}
        
        $pdo->beginTransaction();
        
        $txn = ($payment_method === 'cash') ? 'CASH-' . strtoupper(substr(md5(uniqid()), 0, 10)) : 'BNK-' . strtoupper(substr(md5(uniqid()), 0, 10));
        if (!empty($transaction_ref)) $txn = $transaction_ref;
        
        $pdo->prepare("UPDATE bookings SET payment_status = 'paid', payment_method = :method WHERE id = :id")->execute([':method' => $payment_method, ':id' => $booking_id]);
        $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, payment_date) VALUES (:bid, :amt, :method, :txn, 'success', NOW())")->execute([':bid' => $booking_id, ':amt' => $booking['total_amount'], ':method' => $payment_method, ':txn' => $txn]);
        
        $inv = generate_invoice_number();
        $pdo->prepare("INSERT INTO invoices (booking_id, invoice_number, invoice_date, due_date, amount, tax_amount, total_amount, status) VALUES (:bid, :inv, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :amt, :tax, :total, 'paid')")->execute([':bid' => $booking_id, ':inv' => $inv, ':amt' => $booking['subtotal'] + ($booking['extras_charges'] ?? 0), ':tax' => $booking['tax_amount'], ':total' => $booking['total_amount']]);
        
        $pdo->commit();
        $payment_success = true;
    } catch (Exception $e) {
        $pdo->rollBack();
        $payment_error = 'خطأ: ' . $e->getMessage();
    }
}

function car_img($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=200&h=140&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return 'uploads/cars/' . $path;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الدفع | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --primary: #667eea; --gold: #c9a84c; --gold-light: #f5ecd7;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-gold: linear-gradient(135deg, #c9a84c 0%, #e5c76b 100%);
            --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%);
            --dark: #1a1a2e; --light: #f8f9fa; --white: #ffffff;
            --text: #333; --text-light: #6c757d; --border: #e0e0e0;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04); --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.08); --shadow-lg: 0 20px 60px rgba(0,0,0,0.12);
            --radius-xs: 8px; --radius-sm: 12px; --radius: 16px; --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); box-shadow: var(--shadow-xs); padding: 12px 0; position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none; }
        .navbar-brand .brand-icon { width: 42px; height: 42px; background: var(--gradient); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
        
        .payment-section { padding: 40px 0 80px; }
        
        .progress-steps { display: flex; justify-content: center; align-items: center; margin-bottom: 45px; }
        .step { display: flex; flex-direction: column; align-items: center; }
        .step-circle { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.2rem; background: #e5e7eb; color: #9ca3af; transition: var(--transition); }
        .step-circle.completed { background: var(--success); color: white; box-shadow: 0 5px 20px rgba(16,185,129,0.3); }
        .step-circle.active { background: var(--gradient); color: white; box-shadow: 0 5px 20px rgba(102,126,234,0.3); animation: pulse 2s infinite; }
        @keyframes pulse { 0%{box-shadow:0 0 0 0 rgba(102,126,234,0.4)} 70%{box-shadow:0 0 0 15px rgba(102,126,234,0)} 100%{box-shadow:0 0 0 0 rgba(102,126,234,0)} }
        .step-line { width: 80px; height: 3px; background: #e5e7eb; } .step-line.completed { background: var(--success); }
        .step-label { font-size: 0.8rem; font-weight: 600; color: #9ca3af; margin-top: 8px; }
        
        .card { background: white; border-radius: var(--radius-lg); padding: 28px; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; margin-bottom: 22px; }
        .card-header-custom { display: flex; align-items: center; gap: 12px; margin-bottom: 22px; padding-bottom: 16px; border-bottom: 2px solid #f0f0f0; }
        .card-header-custom h5 { font-weight: 800; color: var(--dark); margin: 0; font-size: 1.1rem; }
        .card-header-custom .icon-circle { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
        
        .pay-method-card { border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 20px; cursor: pointer; transition: var(--transition); margin-bottom: 12px; position: relative; }
        .pay-method-card:hover { border-color: var(--primary); background: #fafbff; }
        .pay-method-card.selected { border-color: var(--primary); background: var(--primary-light); box-shadow: 0 0 0 4px rgba(102,126,234,0.08); }
        .pay-method-card input[type="radio"] { position: absolute; opacity: 0; }
        
        .bank-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; }
        .bank-card { border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 16px 10px; text-align: center; cursor: pointer; transition: var(--transition); background: white; }
        .bank-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: var(--shadow-sm); }
        .bank-card.selected { border-color: var(--primary); background: var(--primary-light); box-shadow: 0 0 0 4px rgba(102,126,234,0.08); }
        .bank-card .bank-logo { height: 40px; display: flex; align-items: center; justify-content: center; margin-bottom: 8px; }
        .bank-card .bank-logo img { max-height: 35px; max-width: 100%; object-fit: contain; }
        .bank-card .bank-logo .bank-icon { font-size: 2rem; }
        .bank-card h6 { font-size: 0.78rem; font-weight: 700; color: var(--dark); margin: 0; }
        
        .bank-details { margin-top: 20px; padding: 20px; background: #fafafa; border-radius: var(--radius-sm); display: none; border: 2px solid var(--border); }
        .bank-details.show { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; background: white; border-radius: 8px; margin-bottom: 6px; font-size: 0.88rem; }
        .detail-row .copy-btn { background: var(--primary-light); border: none; color: var(--primary); padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 0.75rem; font-weight: 600; transition: var(--transition); }
        .detail-row .copy-btn:hover { background: var(--primary); color: white; }
        .detail-row .copy-btn.copied { background: var(--success); color: white; }
        
        .summary-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.95rem; }
        .summary-row.total { border-bottom: none; font-size: 1.4rem; font-weight: 900; color: var(--primary); border-top: 2px solid var(--border); padding-top: 16px; margin-top: 12px; }
        
        .btn { font-weight: 600; border-radius: 12px; padding: 14px 28px; font-size: 0.95rem; cursor: pointer; transition: var(--transition); border: none; display: inline-flex; align-items: center; gap: 8px; }
        .btn-primary { background: var(--gradient); color: white; width: 100%; justify-content: center; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); }
        .btn-lg { padding: 17px 35px; font-size: 1.1rem; border-radius: 14px; }
        .btn-success { background: #25d366; color: white; width: 100%; justify-content: center; }
        .btn-success:hover { background: #1ebe57; transform: translateY(-2px); }
        
        .alert { border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; border: none; font-weight: 500; }
        .success-icon { font-size: 5rem; color: var(--success); animation: pop 0.6s cubic-bezier(0.68,-0.55,0.265,1.55); }
        @keyframes pop { 0%{transform:scale(0)} 50%{transform:scale(1.2)} 100%{transform:scale(1)} }
        
        .form-control { border: 2px solid var(--border); border-radius: 10px; padding: 12px 15px; font-size: 0.95rem; font-family: 'Cairo', sans-serif; width: 100%; transition: var(--transition); }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(102,126,234,0.06); }
        
        .whatsapp-float { position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px; background: #25d366; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 28px; box-shadow: 0 10px 30px rgba(37,211,102,0.4); z-index: 999; text-decoration: none; animation: waPulse 2s infinite; transition: var(--transition); }
        .whatsapp-float:hover { transform: scale(1.1); color: white; }
        @keyframes waPulse { 0%{box-shadow:0 0 0 0 rgba(37,211,102,0.4)} 70%{box-shadow:0 0 0 20px rgba(37,211,102,0)} 100%{box-shadow:0 0 0 0 rgba(37,211,102,0)} }
        
        @media (max-width: 1200px) { .bank-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 991px) { .bank-grid { grid-template-columns: repeat(3, 1fr); } .step-line { width: 40px; } }
        @media (max-width: 768px) { .bank-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 480px) { .bank-grid { grid-template-columns: 1fr 1fr; } .step-line { width: 20px; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span class="brand-icon">🚗</span> <?php echo SITE_NAME; ?></a>
            <span class="fw-bold"><?php echo htmlspecialchars($user_name); ?></span>
        </div>
    </nav>
    
    <section class="payment-section">
        <div class="container">
            <?php if ($payment_success || $already_paid): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card text-center" style="padding:60px 30px;">
                        <i class="fas fa-check-circle success-icon mb-4"></i>
                        <h2 class="fw-bold mb-3">تم الدفع بنجاح! 🎉</h2>
                        <div class="alert alert-info text-start">
                            <p><strong>رقم الحجز:</strong> <?php echo $booking['booking_number']; ?></p>
                            <p><strong>السيارة:</strong> <?php echo htmlspecialchars($booking['brand'].' '.$booking['model'].' '.$booking['year']); ?></p>
                            <p><strong>المبلغ:</strong> <strong style="font-size:1.3rem;color:var(--primary);"><?php echo format_amount($booking['total_amount']); ?></strong></p>
                        </div>
                        <div class="d-flex gap-3 justify-content-center mt-4">
                            <a href="customer/dashboard.php" class="btn btn-primary" style="width:auto;">لوحة التحكم</a>
                            <a href="customer/my-bookings.php" class="btn" style="width:auto;background:#e0e0e0;">طلباتي</a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            
            <div class="progress-steps">
                <div class="step"><div class="step-circle completed">✓</div><div class="step-label">تفاصيل الحجز</div></div>
                <div class="step-line completed"></div>
                <div class="step"><div class="step-circle active">2</div><div class="step-label">الدفع</div></div>
                <div class="step-line"></div>
                <div class="step"><div class="step-circle">3</div><div class="step-label">تأكيد</div></div>
            </div>
            
            <h4 class="fw-bold mb-4 text-center">💳 إتمام الدفع</h4>
            <?php if ($payment_error): ?><div class="alert alert-danger"><?php echo $payment_error; ?></div><?php endif; ?>
            
            <form method="POST" id="paymentForm">
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header-custom">
                                <div class="icon-circle bg-primary bg-opacity-10 text-primary"><i class="fas fa-car"></i></div>
                                <h5>ملخص الحجز</h5>
                            </div>
                            <div class="d-flex align-items-center gap-3 p-3 bg-light rounded-3">
                                <img src="<?php echo car_img($booking['car_image']??''); ?>" style="width:110px;height:80px;object-fit:cover;border-radius:10px;" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=150&h=100&fit=crop'">
                                <div>
                                    <h6 class="fw-bold"><?php echo htmlspecialchars($booking['brand'].' '.$booking['model'].' '.$booking['year']); ?></h6>
                                    <p class="mb-0 small text-muted">رقم: <strong><?php echo $booking['booking_number']; ?></strong></p>
                                    <p class="mb-0 small"><?php echo format_date($booking['pickup_date']); ?> - <?php echo format_date($booking['return_date']); ?> | <strong><?php echo $booking['total_days']; ?> أيام</strong></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header-custom">
                                <div class="icon-circle bg-info bg-opacity-10 text-info"><i class="fas fa-credit-card"></i></div>
                                <h5>اختر طريقة الدفع</h5>
                            </div>
                            
                            <!-- Cash -->
                            <label class="pay-method-card selected" onclick="selectMethod('cash')">
                                <input type="radio" name="payment_method" value="cash" checked>
                                <div class="d-flex align-items-center gap-3">
                                    <div style="font-size:3rem;">💵</div>
                                    <div><h6 class="fw-bold mb-1">نقداً عند الاستلام</h6><small class="text-muted">ادفع نقداً عند استلام السيارة</small></div>
                                </div>
                            </label>
                            
                            <!-- Bank Transfer -->
                            <label class="pay-method-card" onclick="selectMethod('bank')">
                                <input type="radio" name="payment_method" value="bank">
                                <div class="d-flex align-items-center gap-3">
                                    <div style="font-size:3rem;">🏦</div>
                                    <div><h6 class="fw-bold mb-1">تحويل بنكي</h6><small class="text-muted">حول المبلغ إلى أحد حساباتنا البنكية</small></div>
                                </div>
                            </label>
                            
                            <!-- Bank Selection -->
                            <div class="bank-details" id="bankSection">
                                <h6 class="fw-bold mb-3">اختر البنك الذي تريد التحويل إليه:</h6>
                                <div class="bank-grid">
                                    <?php foreach ($moroccan_banks as $key => $bank): ?>
                                    <div class="bank-card" id="bank-<?php echo $key; ?>" onclick="selectBank('<?php echo $key; ?>')">
                                        <input type="radio" name="bank" value="<?php echo $key; ?>" style="display:none;">
                                        <div class="bank-logo">
                                            <span class="bank-icon" style="color:<?php echo $bank['color']; ?>;">🏛️</span>
                                        </div>
                                        <h6><?php echo $bank['name']; ?></h6>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div id="bankInfo" style="margin-top:18px;"></div>
                                
                                <div class="mt-3">
                                    <label class="form-label fw-bold">رقم مرجع العملية (اختياري)</label>
                                    <input type="text" class="form-control" name="transaction_ref" placeholder="أدخل رقم مرجع التحويل إن وجد">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <div class="card" style="position:sticky;top:100px;">
                            <div class="card-header-custom">
                                <div class="icon-circle bg-warning bg-opacity-10 text-warning"><i class="fas fa-receipt"></i></div>
                                <h5>فاتورة</h5>
                            </div>
                            <div class="summary-row"><span>الإيجار الأساسي</span><span><?php echo format_amount($booking['subtotal']); ?></span></div>
                            <div class="summary-row"><span>الإضافات</span><span><?php echo format_amount($booking['extras_charges']??0); ?></span></div>
                            <div class="summary-row"><span>الضريبة (20%)</span><span><?php echo format_amount($booking['tax_amount']); ?></span></div>
                            <div class="summary-row"><span>التأمين (مسترد)</span><span><?php echo format_amount($booking['deposit_amount']??2000); ?></span></div>
                            <div class="summary-row total"><span>المجموع</span><span><?php echo format_amount($booking['total_amount']); ?></span></div>
                            
                            <button type="submit" class="btn btn-primary btn-lg mt-4">
                                <i class="fas fa-check-circle me-2"></i> تأكيد الدفع
                            </button>
                            <a href="https://wa.me/212600000000" class="btn btn-success mt-2" target="_blank">
                                <i class="fab fa-whatsapp me-2"></i> مساعدة عبر واتساب
                            </a>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>
    
    <a href="https://wa.me/212600000000" class="whatsapp-float" target="_blank"><i class="fab fa-whatsapp"></i></a>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const banks = <?php echo json_encode($moroccan_banks); ?>;
        
        function selectMethod(method) {
            document.querySelectorAll('.pay-method-card').forEach(el => el.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input').checked = true;
            
            const bankSection = document.getElementById('bankSection');
            bankSection.classList.toggle('show', method === 'bank');
        }
        
        function selectBank(key) {
            document.querySelectorAll('.bank-card').forEach(el => el.classList.remove('selected'));
            document.getElementById('bank-' + key).classList.add('selected');
            document.getElementById('bank-' + key).querySelector('input').checked = true;
            
            const bank = banks[key];
            const infoDiv = document.getElementById('bankInfo');
            infoDiv.innerHTML = `
                <div class="detail-row">
                    <span><strong>${bank.name}</strong></span>
                    <span style="color:${bank.color};font-weight:700;">${bank.account}</span>
                </div>
                <div class="detail-row">
                    <span>رقم الحساب (RIB)</span>
                    <span>${bank.account} <button type="button" class="copy-btn" onclick="copyText('${bank.account}', this)">نسخ</button></span>
                </div>
                <div class="detail-row">
                    <span>IBAN</span>
                    <span>${bank.iban} <button type="button" class="copy-btn" onclick="copyText('${bank.iban}', this)">نسخ</button></span>
                </div>
                <div class="detail-row">
                    <span>SWIFT</span>
                    <span>${bank.swift} <button type="button" class="copy-btn" onclick="copyText('${bank.swift}', this)">نسخ</button></span>
                </div>
            `;
        }
        
        function copyText(text, btn) {
            navigator.clipboard.writeText(text).then(() => {
                btn.textContent = '✓ تم';
                btn.classList.add('copied');
                setTimeout(() => { btn.textContent = 'نسخ'; btn.classList.remove('copied'); }, 2000);
            });
        }
    </script>
</body>
</html>