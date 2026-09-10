<?php
/**
 * Premium Car Rental - Booking Page
 * Requires login before booking
 */
require_once 'includes/config.php';

// Validate car ID
if (!isset($_GET['car_id']) || empty($_GET['car_id'])) {
    header('Location: cars.php');
    exit();
}

$car_id = intval($_GET['car_id']);

// ============================================
// CHECK LOGIN - REDIRECT IF NOT LOGGED IN
// ============================================
if (!is_logged_in()) {
    // Save car_id in session to redirect back after login
    $_SESSION['redirect_after_login'] = 'booking.php?car_id=' . $car_id;
    header('Location: login.php?redirect=booking.php%3Fcar_id%3D' . $car_id);
    exit();
}

// User is logged in, continue with booking
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? '';
$user_email = $_SESSION['user_email'] ?? '';

// Fetch car data
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image
        FROM cars c 
        WHERE c.id = :id AND c.status = 'available'
    ");
    $stmt->execute([':id' => $car_id]);
    $car = $stmt->fetch();
    
    if (!$car) {
        header('Location: cars.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: cars.php');
    exit();
}

// Fetch locations
try {
    $locations = $pdo->query("SELECT * FROM locations WHERE status = 'active' ORDER BY city ASC")->fetchAll();
} catch (Exception $e) {
    $locations = [];
}

// Fetch extras
try {
    $extras = $pdo->query("SELECT * FROM extras WHERE status = 'active' ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {
    $extras = [];
}

// Image helper
function get_car_img($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=400&h=250&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return 'uploads/cars/' . $path;
}

$car_image = get_car_img($car['main_image'] ?? '');

// Pre-fill dates from URL
$pickup_date = $_GET['pickup_date'] ?? '';
$return_date = $_GET['return_date'] ?? '';
$pickup_location = $_GET['pickup_location'] ?? '';

// Handle form submission
$booking_complete = false;
$booking_error = '';
$booking_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // حماية من تزوير الطلبات عبر المواقع
    require_valid_csrf();

    $pickup_date = $_POST['pickup_date'] ?? '';
    $return_date = $_POST['return_date'] ?? '';
    $pickup_location = $_POST['pickup_location'] ?? '';
    $pickup_time = $_POST['pickup_time'] ?? '10:00';
    $return_time = $_POST['return_time'] ?? '10:00';
    $notes = clean_input($_POST['notes'] ?? '');
    $selected_extras = $_POST['extras'] ?? [];
    $coupon_code = strtoupper(trim($_POST['coupon_code'] ?? ''));
    
    $errors = [];
    
    if (empty($pickup_date)) $errors[] = 'تاريخ الاستلام مطلوب';
    if (empty($return_date)) $errors[] = 'تاريخ التسليم مطلوب';
    if (empty($pickup_location)) $errors[] = 'مكان الاستلام مطلوب';
    
    if ($pickup_date && $return_date) {
        $pickup = new DateTime($pickup_date);
        $return = new DateTime($return_date);
        $today = new DateTime('today');
        
        if ($pickup < $today) $errors[] = 'تاريخ الاستلام يجب أن يكون من اليوم فصاعداً';
        if ($return <= $pickup) $errors[] = 'تاريخ التسليم يجب أن يكون بعد تاريخ الاستلام';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $pickup_dt = new DateTime($pickup_date . ' ' . $pickup_time . ':00');
            $return_dt = new DateTime($return_date . ' ' . $return_time . ':00');
            $days = $pickup_dt->diff($return_dt)->days;
            $days = max($days, 1);
            
            $subtotal = $car['daily_rate'] * $days;
            
            // Calculate extras
            $extras_total = 0;
            $extras_details = [];
            
            if (!empty($selected_extras)) {
                foreach ($selected_extras as $extra_id => $qty) {
                    if ($qty > 0) {
                        $stmt = $pdo->prepare("SELECT * FROM extras WHERE id = :id");
                        $stmt->execute([':id' => $extra_id]);
                        $extra = $stmt->fetch();
                        if ($extra) {
                            $cost = $extra['daily_rate'] * $qty * $days;
                            $extras_total += $cost;
                            $extras_details[] = [
                                'name' => $extra['name'],
                                'qty' => $qty,
                                'rate' => $extra['daily_rate'],
                                'total' => $cost
                            ];
                        }
                    }
                }
            }
            
            // Discount (المدة)
            $discount = 0;
            $subtotal_extras = $subtotal + $extras_total;
            if ($days >= 30) $discount = $subtotal_extras * 0.20;
            elseif ($days >= 7) $discount = $subtotal_extras * 0.10;

            // الكوبون: يُطبق الخصم الأفضل فقط (لا يُجمع مع خصم المدة)
            $applied_coupon = null;
            if ($coupon_code !== '') {
                try {
                    $cStmt = $pdo->prepare("SELECT * FROM coupons WHERE code = :code AND status = 'active' LIMIT 1");
                    $cStmt->execute([':code' => $coupon_code]);
                    $coupon = $cStmt->fetch();
                    if ($coupon
                        && ($coupon['expires_at'] === null || strtotime($coupon['expires_at']) > time())
                        && ($coupon['max_uses'] === null || $coupon['used_count'] < $coupon['max_uses'])
                        && $days >= (int)$coupon['min_days']) {
                        $coupon_disc = ($coupon['type'] === 'percent')
                            ? $subtotal_extras * ($coupon['value'] / 100)
                            : min($coupon['value'], $subtotal_extras);
                        if ($coupon_disc > $discount) {
                            $discount = $coupon_disc;
                            $applied_coupon = $coupon['code'];
                        }
                    }
                } catch (Exception $e) { /* جدول الكوبونات غير موجود - تجاهل */ }
            }

            $after_discount = $subtotal_extras - $discount;
            $tax = $after_discount * (TAX_RATE / 100);
            $total_amount = $after_discount + $tax;
            
            $booking_number = generate_booking_number();
            
            // Insert booking
            $stmt = $pdo->prepare("
                INSERT INTO bookings (
                    booking_number, user_id, car_id,
                    pickup_date, return_date,
                    pickup_location, return_location,
                    total_days, daily_rate, subtotal,
                    extras_charges, discount_amount, tax_amount, total_amount,
                    deposit_amount, extras, notes, coupon_code,
                    payment_status, booking_status, created_at
                ) VALUES (
                    :bn, :uid, :cid,
                    :pd, :rd,
                    :pl, :rl,
                    :days, :dr, :sub,
                    :ext, :disc, :tax, :tot,
                    :dep, :extras, :notes, :coupon,
                    'pending', 'pending', NOW()
                )
            ");
            
            $stmt->execute([
                ':bn' => $booking_number, ':uid' => $user_id, ':cid' => $car_id,
                ':pd' => $pickup_date . ' ' . $pickup_time . ':00',
                ':rd' => $return_date . ' ' . $return_time . ':00',
                ':pl' => $pickup_location, ':rl' => $pickup_location,
                ':days' => $days, ':dr' => $car['daily_rate'],
                ':sub' => $subtotal, ':ext' => $extras_total,
                ':disc' => $discount,
                ':tax' => $tax, ':tot' => $total_amount,
                ':dep' => ($car['deposit'] ?? 2000),
                ':extras' => json_encode($extras_details),
                ':notes' => $notes,
                ':coupon' => $applied_coupon
            ]);
            
            $booking_id = $pdo->lastInsertId();

            // زيادة عداد استخدام الكوبون
            if ($applied_coupon) {
                try {
                    $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE code = :code")
                        ->execute([':code' => $applied_coupon]);
                } catch (Exception $e) {}
            }
            
            $pdo->commit();
            
            $booking_complete = true;
            $booking_data = [
                'booking_number' => $booking_number,
                'booking_id' => $booking_id,
                'days' => $days,
                'subtotal' => $subtotal,
                'extras_total' => $extras_total,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total_amount,
                'pickup_date' => $pickup_date,
                'return_date' => $return_date,
                'pickup_time' => $pickup_time,
                'return_time' => $return_time,
            ];
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $booking_error = 'حدث خطأ: ' . $e->getMessage();
        }
    } else {
        $booking_error = implode('<br>', $errors);
    }
}

$page_title = 'حجز ' . htmlspecialchars($car['brand'] . ' ' . $car['model']) . ' | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_green.css">
    <style>
        :root {
            --primary: #667eea;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --text: #333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --danger: #ef4444;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --radius: 14px;
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; }
        
        .navbar {
            background: white; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 12px 0; position: sticky; top: 0; z-index: 1000;
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 8px;
            font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none;
        }
        .navbar-brand i { color: var(--primary); font-size: 1.6rem; }
        
        .booking-section { padding: 40px 0; }
        
        .card {
            background: white; border-radius: var(--radius); padding: 25px;
            box-shadow: var(--shadow); margin-bottom: 20px; border: 1px solid #f0f0f0;
        }
        .card h5 {
            font-weight: 800; color: var(--dark); margin-bottom: 20px;
            padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 8px;
        }
        .card h5 i { color: var(--primary); }
        
        .form-label { font-weight: 700; font-size: 0.88rem; color: var(--dark); margin-bottom: 6px; display: block; }
        .form-control, .form-select {
            border: 2px solid var(--border); border-radius: 10px; padding: 12px 15px;
            font-size: 0.95rem; transition: var(--transition); font-family: 'Cairo', sans-serif;
            width: 100%; background: #fafafa;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); outline: none; background: white; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .btn {
            font-weight: 600; border-radius: 10px; padding: 12px 25px;
            font-size: 0.95rem; cursor: pointer; transition: var(--transition); border: none;
        }
        .btn-primary { background: var(--gradient); color: white; width: 100%; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        .btn-lg { padding: 16px 30px; font-size: 1.1rem; border-radius: 14px; }
        
        .car-summary {
            display: flex; align-items: center; gap: 15px; padding: 15px;
            background: #f8f9fa; border-radius: 10px; margin-bottom: 15px;
        }
        .car-summary img { width: 120px; height: 85px; object-fit: cover; border-radius: 8px; }
        
        .alert { border-radius: 10px; padding: 15px; margin-bottom: 20px; border: none; }
        
        .summary-row {
            display: flex; justify-content: space-between; padding: 10px 0;
            border-bottom: 1px solid #f0f0f0; font-size: 0.95rem;
        }
        .summary-row.total {
            border-bottom: none; font-size: 1.3rem; font-weight: 900;
            color: var(--primary); border-top: 2px solid var(--border);
            padding-top: 15px; margin-top: 10px;
        }
        
        .success-icon { font-size: 5rem; color: var(--success); }
        
        .login-alert {
            background: #eef0ff; border: 2px solid var(--primary); border-radius: var(--radius);
            padding: 30px; text-align: center;
        }
        .login-alert i { font-size: 3rem; color: var(--primary); margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .car-summary { flex-direction: column; text-align: center; }
            .car-summary img { width: 100%; height: 150px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
            <div>
                <span class="me-3"><i class="fas fa-user-check text-primary"></i> <?php echo htmlspecialchars($user_name); ?></span>
                <a href="logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;">خروج</a>
            </div>
        </div>
    </nav>
    
    <section class="booking-section">
        <div class="container">
            <?php if ($booking_complete): ?>
            <!-- ==================== SUCCESS ==================== -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card text-center" style="padding: 50px;">
                        <i class="fas fa-check-circle success-icon mb-4"></i>
                        <h2 class="fw-bold mb-3">تم الحجز بنجاح! 🎉</h2>
                        <p class="mb-2">رقم الحجز الخاص بك:</p>
                        <h3 style="color:var(--primary);font-weight:900;letter-spacing:2px;"><?php echo $booking_data['booking_number']; ?></h3>
                        
                        <div class="alert alert-info mt-3 text-start">
                            <p><strong>السيارة:</strong> <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']); ?></p>
                            <p><strong>تاريخ الاستلام:</strong> <?php echo format_date($booking_data['pickup_date']); ?> - <?php echo $booking_data['pickup_time']; ?></p>
                            <p><strong>تاريخ التسليم:</strong> <?php echo format_date($booking_data['return_date']); ?> - <?php echo $booking_data['return_time']; ?></p>
                            <p><strong>المدة:</strong> <?php echo $booking_data['days']; ?> أيام</p>
                            <p><strong>المبلغ الإجمالي:</strong> <strong style="color:var(--primary);font-size:1.2rem;"><?php echo format_amount($booking_data['total']); ?></strong></p>
                        </div>
                        
                        <div class="d-flex gap-3 justify-content-center mt-4">
                            <a href="customer/dashboard.php" class="btn btn-primary" style="width:auto;">
                                <i class="fas fa-tachometer-alt me-2"></i> لوحة التحكم
                            </a>
                            <a href="cars.php" class="btn" style="width:auto;background:#e0e0e0;">
                                <i class="fas fa-car me-2"></i> تصفح المزيد
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php else: ?>
            <!-- ==================== BOOKING FORM ==================== -->
            <h4 class="fw-bold mb-4 text-center">
                <i class="fas fa-calendar-check text-primary me-2"></i> إتمام الحجز
            </h4>
            
            <?php if ($booking_error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $booking_error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="bookingForm">
            <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-lg-8">
                        <!-- Car Summary -->
                        <div class="card">
                            <div class="car-summary">
                                <img src="<?php echo $car_image; ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" 
                                     onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=400&h=250&fit=crop'">
                                <div>
                                    <h5 class="fw-bold"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']); ?></h5>
                                    <p class="mb-1 text-muted">
                                        <i class="fas fa-cog"></i> <?php echo $car['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي'; ?> |
                                        <i class="fas fa-gas-pump"></i> <?php echo $car['fuel_type']; ?> |
                                        <i class="fas fa-user"></i> <?php echo $car['seats']; ?> مقاعد
                                    </p>
                                    <strong class="text-primary"><?php echo format_amount($car['daily_rate']); ?> / يوم</strong>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Dates & Location -->
                        <div class="card">
                            <h5><i class="fas fa-calendar-alt"></i> تواريخ الحجز</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">تاريخ الاستلام *</label>
                                    <input type="text" class="form-control datepicker" name="pickup_date" 
                                           value="<?php echo $pickup_date; ?>" placeholder="اختر التاريخ" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">وقت الاستلام</label>
                                    <select class="form-select" name="pickup_time">
                                        <?php for ($h = 8; $h <= 20; $h++): $hr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $hr; ?>:00"><?php echo $hr; ?>:00</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تاريخ التسليم *</label>
                                    <input type="text" class="form-control datepicker" name="return_date" 
                                           value="<?php echo $return_date; ?>" placeholder="اختر التاريخ" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">وقت التسليم</label>
                                    <select class="form-select" name="return_time">
                                        <?php for ($h = 8; $h <= 20; $h++): $hr = str_pad($h, 2, '0', STR_PAD_LEFT); ?>
                                        <option value="<?php echo $hr; ?>:00"><?php echo $hr; ?>:00</option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">مكان الاستلام *</label>
                                    <select class="form-select" name="pickup_location" required>
                                        <option value="">اختر الموقع...</option>
                                        <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>" <?php echo $pickup_location == $loc['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($loc['city'] . ' - ' . $loc['name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Info -->
                        <div class="card">
                            <h5><i class="fas fa-user-check"></i> معلومات العميل</h5>
                            <div class="alert" style="background:#eef0ff;color:#667eea;">
                                <i class="fas fa-user me-2"></i>
                                مرحباً <strong><?php echo htmlspecialchars($user_name); ?></strong>، 
                                سيتم استخدام بيانات حسابك للحجز. البريد: <strong><?php echo htmlspecialchars($user_email); ?></strong>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ملاحظات إضافية</label>
                                <textarea class="form-control" name="notes" placeholder="أي طلبات خاصة أو ملاحظات..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Extras -->
                        <?php if (!empty($extras)): ?>
                        <div class="card">
                            <h5><i class="fas fa-plus-circle"></i> إضافات اختيارية</h5>
                            <?php foreach ($extras as $extra): ?>
                            <div class="d-flex justify-content-between align-items-center p-3 border rounded-3 mb-2" id="extra-<?php echo $extra['id']; ?>">
                                <div>
                                    <strong><?php echo htmlspecialchars($extra['name']); ?></strong>
                                    <br><small class="text-muted"><?php echo format_amount($extra['daily_rate']); ?> / يوم</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeQty(<?php echo $extra['id']; ?>, -1, <?php echo $extra['max_quantity']; ?>)">-</button>
                                    <input type="text" class="form-control form-control-sm text-center fw-bold" 
                                           name="extras[<?php echo $extra['id']; ?>]" id="qty-<?php echo $extra['id']; ?>" value="0" readonly style="width:50px;">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="changeQty(<?php echo $extra['id']; ?>, 1, <?php echo $extra['max_quantity']; ?>)">+</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Summary Sidebar -->
                    <div class="col-lg-4">
                        <div class="card" style="position:sticky;top:100px;">
                            <h5 class="text-center"><i class="fas fa-receipt me-2"></i> ملخص الحجز</h5>
                            <div class="summary-row"><span>السعر اليومي</span><span><?php echo format_amount($car['daily_rate']); ?></span></div>
                            <div class="summary-row"><span>المدة</span><span id="summaryDays">- أيام</span></div>
                            <div class="summary-row"><span>الإيجار الأساسي</span><span id="summarySubtotal">-</span></div>
                            <div class="summary-row"><span>الإضافات</span><span id="summaryExtras">0.00 DH</span></div>
                            <div class="summary-row" style="color:#10b981;"><span>الخصم</span><span id="summaryDiscount">-0.00 DH</span></div>
                            <div class="summary-row"><span>الضريبة (<?php echo TAX_RATE; ?>%)</span><span id="summaryTax">-</span></div>
                            <div class="summary-row total"><span>المجموع الكلي</span><span id="summaryTotal">-</span></div>
                            
                            <!-- كوبون الخصم -->
                            <div class="input-group mt-3">
                                <input type="text" class="form-control" name="coupon_code" id="couponInput" placeholder="كود الخصم (اختياري)" style="text-transform:uppercase;">
                                <button type="button" class="btn btn-outline-primary" id="applyCouponBtn" onclick="applyCoupon()">تطبيق</button>
                            </div>
                            <small id="couponMessage" class="d-block mt-1"></small>

                            <button type="submit" class="btn btn-primary btn-lg mt-3">
                                <i class="fas fa-check-circle me-2"></i> تأكيد الحجز
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    <script>
        // Datepickers
        const pickupPicker = flatpickr("input[name='pickup_date']", {
            locale: "ar", dateFormat: "Y-m-d", minDate: "today",
            onChange: function(selectedDates, dateStr) {
                returnPicker.set('minDate', dateStr);
                updateSummary();
            }
        });
        const returnPicker = flatpickr("input[name='return_date']", {
            locale: "ar", dateFormat: "Y-m-d", minDate: "today",
            onChange: function() { updateSummary(); }
        });
        
        // Extras quantity
        function changeQty(id, delta, max) {
            const input = document.getElementById('qty-' + id);
            const item = document.getElementById('extra-' + id);
            let val = parseInt(input.value) + delta;
            if (val < 0) val = 0;
            if (val > max) val = max;
            input.value = val;
            item.style.borderColor = val > 0 ? '#667eea' : '#e0e0e0';
            item.style.background = val > 0 ? '#eef0ff' : 'white';
            updateSummary();
        }
        
        // Update summary
        function updateSummary() {
            const pickupDate = document.querySelector('[name="pickup_date"]').value;
            const returnDate = document.querySelector('[name="return_date"]').value;
            const dailyRate = <?php echo $car['daily_rate']; ?>;
            const taxRate = <?php echo TAX_RATE; ?>;
            
            if (pickupDate && returnDate) {
                const start = new Date(pickupDate);
                const end = new Date(returnDate);
                const days = Math.max(1, Math.ceil((end - start) / (1000 * 60 * 60 * 24)));
                
                document.getElementById('summaryDays').textContent = days + ' أيام';
                const subtotal = days * dailyRate;
                document.getElementById('summarySubtotal').textContent = subtotal.toLocaleString() + ' DH';
                
                let extrasTotal = 0;
                document.querySelectorAll('[id^="qty-"]').forEach(function(input) {
                    const qty = parseInt(input.value);
                    if (qty > 0) {
                        const extraId = input.id.replace('qty-', '');
                        const extraRate = <?php echo json_encode(array_column($extras, 'daily_rate', 'id')); ?>[extraId] || 0;
                        extrasTotal += extraRate * qty * days;
                    }
                });
                document.getElementById('summaryExtras').textContent = extrasTotal.toLocaleString() + ' DH';
                
                let discount = 0;
                const subtotalExtras = subtotal + extrasTotal;
                if (days >= 30) discount = subtotalExtras * 0.2;
                else if (days >= 7) discount = subtotalExtras * 0.1;
                
                // خصم الكوبون: يُعتمد الخصم الأفضل فقط
                if (window.appliedCoupon) {
                    let couponDisc = 0;
                    if (days >= (window.appliedCoupon.min_days || 0)) {
                        couponDisc = (window.appliedCoupon.type === 'percent')
                            ? subtotalExtras * (window.appliedCoupon.value / 100)
                            : Math.min(window.appliedCoupon.value, subtotalExtras);
                    }
                    if (couponDisc > discount) discount = couponDisc;
                }

                document.getElementById('summaryDiscount').textContent = '-' + discount.toLocaleString() + ' DH';
                
                const afterDiscount = subtotalExtras - discount;
                const tax = afterDiscount * (taxRate / 100);
                const total = afterDiscount + tax;
                
                document.getElementById('summaryTax').textContent = tax.toLocaleString() + ' DH';
                document.getElementById('summaryTotal').textContent = total.toLocaleString() + ' DH';
            }
        }
        
        // تطبيق كوبون الخصم عبر الـ API
        window.appliedCoupon = null;
        function applyCoupon() {
            const code = document.getElementById('couponInput').value.trim().toUpperCase();
            const msg = document.getElementById('couponMessage');
            if (!code) { msg.textContent = ''; return; }

            const pickupDate = document.querySelector('[name="pickup_date"]').value;
            const returnDate = document.querySelector('[name="return_date"]').value;
            const days = (pickupDate && returnDate)
                ? Math.max(1, Math.ceil((new Date(returnDate) - new Date(pickupDate)) / (1000*60*60*24)))
                : 1;

            msg.style.color = '#6b7280';
            msg.textContent = 'جارٍ التحقق...';

            fetch('api/validate-coupon.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({code: code, days: days})
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.appliedCoupon = data.coupon;
                    msg.style.color = '#10b981';
                    msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                } else {
                    window.appliedCoupon = null;
                    msg.style.color = '#ef4444';
                    msg.innerHTML = '<i class="fas fa-times-circle"></i> ' + (data.message || 'كوبون غير صالح');
                }
                updateSummary();
            })
            .catch(function() {
                msg.style.color = '#ef4444';
                msg.textContent = 'تعذر التحقق من الكوبون';
            });
        }

        updateSummary();
    </script>
</body>
</html>