<?php
/**
 * Premium Car Rental - Customer Dashboard
 * Fully Working Version
 */
require_once '../includes/config.php';

// Check login
if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'المستخدم';
$user_email = $_SESSION['user_email'] ?? '';

// Get stats
$total_bookings = 0;
$active_bookings_count = 0;
$completed_bookings = 0;
$total_spent = 0;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_bookings = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_status IN ('pending', 'confirmed', 'active')");
    $stmt->execute([$user_id]);
    $active_bookings_count = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND booking_status = 'completed'");
    $stmt->execute([$user_id]);
    $completed_bookings = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE user_id = ? AND payment_status = 'paid'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetchColumn();
} catch (Exception $e) {}

// Get current bookings
$current_bookings = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.brand, c.model, c.year,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        WHERE b.user_id = ? AND b.booking_status IN ('pending', 'confirmed', 'active')
        ORDER BY b.pickup_date ASC LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $current_bookings = $stmt->fetchAll();
} catch (Exception $e) {}

// Get past bookings
$past_bookings = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.brand, c.model, c.year,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        WHERE b.user_id = ? AND b.booking_status = 'completed'
        ORDER BY b.return_date DESC LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $past_bookings = $stmt->fetchAll();
} catch (Exception $e) {}

// Image helper
function getCarImage($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=200&h=140&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --text: #333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --radius: 16px;
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f5f6fa;
            color: var(--text);
        }
        
        /* Navbar */
        .navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 900;
            font-size: 1.2rem;
            color: var(--dark) !important;
            text-decoration: none;
        }
        
        .navbar-brand i { color: var(--primary); font-size: 1.4rem; }
        
        .nav-link {
            font-weight: 600;
            color: var(--text) !important;
            padding: 8px 16px !important;
            border-radius: 8px;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: #eef0ff;
        }
        
        .btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 20px;
            transition: var(--transition);
            font-size: 0.88rem;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }
        
        .btn-primary {
            background: var(--gradient);
            border: none;
            color: white;
        }
        
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); color: white; }
        .btn-outline-danger {
            border: 2px solid var(--danger);
            color: var(--danger);
            background: transparent;
        }
        
        .btn-outline-danger:hover { background: var(--danger); color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.82rem; }
        
        /* Dashboard */
        .dashboard-section { padding: 35px 0 60px; }
        
        .welcome-card {
            background: var(--gradient);
            color: white;
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 25px;
        }
        
        .welcome-card h2 { font-weight: 900; margin-bottom: 8px; }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover { transform: translateY(-5px); }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 900; color: var(--dark); }
        .stat-card .stat-label { font-size: 0.82rem; color: var(--text-light); margin-top: 5px; }
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin: 0 auto 12px;
        }
        
        .section-card {
            background: white;
            border-radius: var(--radius);
            padding: 25px;
            box-shadow: var(--shadow);
            margin-bottom: 25px;
        }
        
        .section-card h5 {
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .booking-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border: 1px solid #f0f0f0;
            border-radius: 12px;
            margin-bottom: 10px;
            transition: var(--transition);
        }
        
        .booking-item:hover { border-color: var(--primary); background: #fafbff; }
        .booking-item img { width: 100px; height: 70px; object-fit: cover; border-radius: 8px; }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.73rem;
        }
        
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #e0e7ff; color: #3730a3; }
        
        .empty-state {
            text-align: center;
            padding: 40px;
        }
        
        .empty-state i { font-size: 3rem; color: #ddd; margin-bottom: 15px; }
        
        @media (max-width: 768px) {
            .booking-item { flex-direction: column; text-align: center; }
            .booking-item img { width: 100%; height: 140px; }
        }
    </style>
</head>
<body>
    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#dashNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="dashNav">
                <ul class="navbar-nav me-auto gap-1">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i> الرئيسية</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../cars.php"><i class="fas fa-car me-1"></i> السيارات</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../contact.php"><i class="fas fa-envelope me-1"></i> اتصل بنا</a>
                    </li>
                </ul>
                
                <div class="d-flex gap-2">
    <a href="../cars.php" class="btn btn-primary btn-sm">
        <i class="fas fa-plus-circle me-1"></i> حجز جديد
    </a>
    <a href="my-bookings.php" class="btn btn-outline-primary btn-sm">
        <i class="fas fa-list-check me-1"></i> طلباتي
    </a>
    <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;">
        <i class="fas fa-sign-out-alt"></i>
    </a>
</div>
            </div>
        </div>
    </nav>
    
    <!-- ==================== DASHBOARD ==================== -->
    <section class="dashboard-section">
        <div class="container">
            <!-- Welcome -->
            <div class="welcome-card">
                <h2><i class="fas fa-hand-wave me-2"></i> مرحباً بك، <?php echo htmlspecialchars($user_name); ?>!</h2>
                <p class="mb-0 opacity-90">نحن سعداء بخدمتك. استعرض حجوزاتك أو احجز سيارة جديدة الآن.</p>
            </div>
            
            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-calendar-check"></i></div>
                        <div class="stat-value"><?php echo number_format($total_bookings); ?></div>
                        <div class="stat-label">إجمالي الحجوزات</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-car"></i></div>
                        <div class="stat-value"><?php echo number_format($active_bookings_count); ?></div>
                        <div class="stat-label">حجوزات نشطة</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-value"><?php echo number_format($completed_bookings); ?></div>
                        <div class="stat-label">حجوزات مكتملة</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-value"><?php echo format_amount($total_spent); ?></div>
                        <div class="stat-label">إجمالي المدفوعات</div>
                    </div>
                </div>
            </div>
            
            <!-- Current Bookings -->
            <div class="section-card">
                <h5><i class="fas fa-clock text-primary"></i> الحجوزات الحالية</h5>
                
                <?php if (empty($current_bookings)): ?>
                <div class="empty-state">
                    <i class="fas fa-car-side"></i>
                    <h5>لا توجد حجوزات حالية</h5>
                    <p class="text-muted">احجز سيارتك الأولى الآن!</p>
                    <a href="../cars.php" class="btn btn-primary mt-2"><i class="fas fa-plus-circle me-1"></i> احجز سيارة</a>
                </div>
                <?php else: ?>
                <?php foreach ($current_bookings as $b): 
                    $img = getCarImage($b['car_image'] ?? '');
                    $status_class = match($b['booking_status']) {
                        'pending' => 'badge-pending', 'confirmed' => 'badge-confirmed',
                        'active' => 'badge-active', default => 'badge-pending'
                    };
                    $status_text = match($b['booking_status']) {
                        'pending' => 'قيد الانتظار', 'confirmed' => 'مؤكد',
                        'active' => 'نشط', default => $b['booking_status']
                    };
                ?>
                <div class="booking-item">
                    <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($b['brand']); ?>" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=200&h=140&fit=crop'">
                    <div class="flex-grow-1">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($b['brand'] . ' ' . $b['model'] . ' ' . $b['year']); ?></h6>
                        <p class="mb-1 text-muted small">رقم الحجز: <strong><?php echo $b['booking_number']; ?></strong></p>
                        <p class="mb-1 small"><i class="fas fa-calendar text-primary"></i> <?php echo format_date($b['pickup_date']); ?> - <?php echo format_date($b['return_date']); ?></p>
                    </div>
                    <div class="text-start">
                        <span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                        <p class="mt-2 mb-0 fw-bold text-primary"><?php echo format_amount($b['total_amount']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Past Bookings -->
            <?php if (!empty($past_bookings)): ?>
            <div class="section-card">
                <h5><i class="fas fa-history text-success"></i> آخر الحجوزات المكتملة</h5>
                
                <?php foreach ($past_bookings as $b): 
                    $img = getCarImage($b['car_image'] ?? '');
                ?>
                <div class="booking-item">
                    <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($b['brand']); ?>" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=200&h=140&fit=crop'">
                    <div class="flex-grow-1">
                        <h6 class="fw-bold"><?php echo htmlspecialchars($b['brand'] . ' ' . $b['model'] . ' ' . $b['year']); ?></h6>
                        <p class="mb-1 text-muted small">رقم الحجز: <strong><?php echo $b['booking_number']; ?></strong></p>
                        <p class="mb-1 small"><i class="fas fa-calendar-check text-success"></i> تم التسليم: <?php echo format_date($b['return_date']); ?></p>
                    </div>
                    <div class="text-start">
                        <span class="badge-status badge-completed">مكتمل</span>
                        <p class="mt-2 mb-0 fw-bold text-success"><?php echo format_amount($b['total_amount']); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>