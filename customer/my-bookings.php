<?php
/**
 * Premium Car Rental - My Bookings
 * Royal Elite Version - Ultimate Luxury Design
 */
require_once '../includes/config.php';

if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'المستخدم';
$user_email = $_SESSION['user_email'] ?? '';

// Handle cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    if ($booking_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :bid AND user_id = :uid AND booking_status IN ('pending', 'confirmed')");
            $stmt->execute([':bid' => $booking_id, ':uid' => $user_id]);
            $booking = $stmt->fetch();
            if ($booking) {
                $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = :id")->execute([':id' => $booking_id]);
                $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = :id")->execute([':id' => $booking['car_id']]);
                $success = 'تم إلغاء الحجز بنجاح';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ';
        }
    }
}

// Pagination & Filters
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = ["b.user_id = :uid"];
$params = [':uid' => $user_id];
if (!empty($status_filter)) {
    $where[] = "b.booking_status = :status";
    $params[':status'] = $status_filter;
}
$whereSQL = implode(' AND ', $where);

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b WHERE $whereSQL");
    $countStmt->execute($params);
    $totalBookings = $countStmt->fetchColumn();
    $totalPages = ceil($totalBookings / $perPage);
    
    $stmt = $pdo->prepare("
        SELECT b.*, c.brand, c.model, c.year, c.color, c.transmission, c.fuel_type, c.seats, c.doors, c.engine_size,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b JOIN cars c ON b.car_id = c.id 
        WHERE $whereSQL ORDER BY b.created_at DESC LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $bookings = []; $totalBookings = 0; $totalPages = 0;
}

// Counts
try {
    $counts = [
        'all' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'pending'")->fetchColumn(),
        'confirmed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'confirmed'")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'active'")->fetchColumn(),
        'completed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'completed'")->fetchColumn(),
        'cancelled' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE user_id = $user_id AND booking_status = 'cancelled'")->fetchColumn(),
    ];
} catch (Exception $e) {
    $counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'active' => 0, 'completed' => 0, 'cancelled' => 0];
}

function my_img($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=300&h=200&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}
function build_url($page) { $get = $_GET; $get['page'] = $page; return 'my-bookings.php?' . http_build_query($get); }

$error = $error ?? '';
$success = $success ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>طلباتي | <?php echo SITE_NAME; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        :root {
            --primary: #667eea; --primary-dark: #4f5fd6; --primary-light: #eef0ff;
            --gold: #c9a84c; --gold-light: #f5ecd7; --gold-dark: #a68a3e;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-gold: linear-gradient(135deg, #c9a84c 0%, #e5c76b 100%);
            --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #2d2d44 100%);
            --dark: #1a1a2e; --light: #f8f9fa; --white: #ffffff;
            --text: #333333; --text-light: #6c757d; --border: #e0e0e0;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04); --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.08); --shadow-lg: 0 20px 60px rgba(0,0,0,0.12);
            --radius-xs: 8px; --radius-sm: 12px; --radius: 16px; --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; overflow-x: hidden; }
        
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        /* ========== NAVBAR ========== */
        .navbar {
            background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            box-shadow: var(--shadow-xs); padding: 12px 0; position: sticky; top: 0; z-index: 1000; transition: var(--transition);
        }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none; }
        .navbar-brand .brand-icon { width: 42px; height: 42px; background: var(--gradient); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
        .nav-link { font-weight: 600; color: var(--text) !important; padding: 10px 18px !important; border-radius: 10px; transition: var(--transition); font-size: 0.9rem; }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; background: var(--primary-light); }
        
        .btn { font-weight: 600; border-radius: 10px; padding: 10px 20px; transition: var(--transition); font-size: 0.88rem; cursor: pointer; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; position: relative; overflow: hidden; }
        .btn::after { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; border-radius: 50%; background: rgba(255,255,255,0.3); transform: translate(-50%, -50%); transition: width 0.6s, height 0.6s; }
        .btn:active::after { width: 300px; height: 300px; }
        .btn-primary { background: var(--gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.82rem; }
        .btn-xs { padding: 4px 10px; font-size: 0.72rem; border-radius: 6px; }
        .btn-gold { background: var(--gradient-gold); color: #1a1a2e; font-weight: 700; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(201,168,76,0.4); }
        .btn-contract { background: linear-gradient(135deg, #1a1a2e, #2d2d44); color: var(--gold); border: 1px solid var(--gold); }
        .btn-contract:hover { background: var(--gold); color: #1a1a2e; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(201,168,76,0.3); }
        .btn-pay { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .btn-pay:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(16,185,129,0.4); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-info { background: #3b82f6; color: white; }
        .btn-info:hover { background: #2563eb; transform: translateY(-2px); }
        
        /* ========== PAGE HEADER ========== */
        .page-header {
            background: var(--gradient-dark); padding: 50px 0; text-align: center; color: white;
            position: relative; overflow: hidden;
        }
        .page-header::before { content: ''; position: absolute; top: -50%; left: -30%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(201,168,76,0.15) 0%, transparent 70%); pointer-events: none; }
        .page-header h1 { font-weight: 900; font-size: 2.2rem; margin-bottom: 8px; position: relative; }
        .page-header p { opacity: 0.8; position: relative; font-size: 1rem; }
        .page-header .header-icon { font-size: 3rem; color: var(--gold); margin-bottom: 15px; position: relative; }
        
        /* ========== DASHBOARD ========== */
        .dashboard-section { padding: 40px 0 80px; }
        
        /* Sidebar */
        .dash-sidebar { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow: hidden; position: sticky; top: 100px; border: 1px solid #f0f0f0; }
        .sidebar-profile { text-align: center; padding: 30px 20px; background: var(--gradient); color: white; position: relative; }
        .sidebar-profile .avatar-ring { width: 80px; height: 80px; border-radius: 50%; padding: 3px; background: linear-gradient(135deg, var(--gold), #e5c76b); margin: 0 auto 12px; }
        .sidebar-profile img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid white; }
        .sidebar-profile h6 { font-weight: 800; font-size: 1.05rem; margin: 0; }
        .sidebar-profile .badge-role { display: inline-block; background: rgba(255,255,255,0.2); padding: 3px 12px; border-radius: 20px; font-size: 0.7rem; margin-top: 5px; }
        .sidebar-menu { padding: 8px 0; }
        .sidebar-menu a { display: flex; align-items: center; gap: 12px; padding: 14px 22px; color: var(--text); text-decoration: none; font-weight: 600; font-size: 0.9rem; transition: var(--transition); border-right: 3px solid transparent; position: relative; }
        .sidebar-menu a:hover { background: var(--primary-light); color: var(--primary); border-right-color: var(--primary); padding-right: 28px; }
        .sidebar-menu a.active { background: var(--primary-light); color: var(--primary); border-right-color: var(--primary); font-weight: 700; }
        .sidebar-menu a i { width: 22px; text-align: center; font-size: 1rem; }
        .sidebar-menu a .badge-count { margin-right: auto; background: var(--danger); color: white; padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; font-weight: 700; }
        .sidebar-menu a.logout { color: var(--danger); border-top: 1px solid #f0f0f0; margin-top: 8px; padding-top: 16px; }
        .sidebar-menu a.logout:hover { background: #fef2f2; border-right-color: var(--danger); }
        
        /* Content */
        .content-card { background: white; border-radius: var(--radius-lg); padding: 30px; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; margin-bottom: 25px; }
        .card-header-custom { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 18px; border-bottom: 2px solid #f0f0f0; }
        .card-header-custom h5 { font-weight: 800; color: var(--dark); margin: 0; font-size: 1.1rem; display: flex; align-items: center; gap: 8px; }
        .card-header-custom h5 i { color: var(--primary); }
        
        /* Status Tabs */
        .status-tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 28px; padding: 5px; background: #f8f9fa; border-radius: 14px; }
        .status-tab { padding: 10px 18px; border-radius: 10px; font-weight: 600; font-size: 0.84rem; text-decoration: none; transition: var(--transition); color: var(--text-light); background: transparent; display: flex; align-items: center; gap: 6px; white-space: nowrap; }
        .status-tab:hover { color: var(--primary); background: white; }
        .status-tab.active { background: var(--gradient); color: white; box-shadow: 0 5px 15px rgba(102,126,234,0.2); }
        .status-tab .count { padding: 2px 8px; border-radius: 20px; font-size: 0.7rem; background: rgba(0,0,0,0.08); font-weight: 700; }
        .status-tab.active .count { background: rgba(255,255,255,0.3); }
        
        /* Booking Card */
        .booking-card { background: white; border-radius: var(--radius); margin-bottom: 16px; transition: var(--transition); overflow: hidden; border: 1px solid #f0f0f0; }
        .booking-card:hover { border-color: var(--primary); box-shadow: var(--shadow); transform: translateY(-2px); }
        .booking-inner { display: flex; }
        .booking-img { width: 220px; min-height: 180px; position: relative; overflow: hidden; flex-shrink: 0; background: #e0e0e0; }
        .booking-img img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .booking-card:hover .booking-img img { transform: scale(1.05); }
        .booking-img .status-badge { position: absolute; top: 12px; right: 12px; z-index: 2; }
        
        .booking-body { flex: 1; padding: 20px; display: flex; flex-direction: column; }
        .booking-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
        .booking-info h6 { font-weight: 800; font-size: 1.05rem; color: var(--dark); margin-bottom: 3px; }
        .booking-info .book-num { font-size: 0.78rem; color: var(--primary); font-weight: 700; letter-spacing: 1px; }
        .booking-info .specs { display: flex; gap: 8px; margin-top: 6px; flex-wrap: wrap; }
        .booking-info .specs span { font-size: 0.75rem; color: var(--text-light); background: #f8f9fa; padding: 3px 10px; border-radius: 6px; display: flex; align-items: center; gap: 4px; }
        .booking-info .specs span i { color: var(--primary); font-size: 0.7rem; }
        .booking-dates { display: flex; gap: 20px; margin: 10px 0; flex-wrap: wrap; }
        .booking-dates .date-item { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: var(--text-light); }
        .booking-dates .date-item i { color: var(--primary); }
        .booking-dates .date-item strong { color: var(--dark); }
        .booking-bottom { display: flex; justify-content: space-between; align-items: flex-end; margin-top: auto; padding-top: 14px; border-top: 1px solid #f0f0f0; }
        .price .amount { font-size: 1.3rem; font-weight: 900; color: var(--primary); }
        .price .period { font-size: 0.75rem; color: var(--text-light); }
        .actions { display: flex; gap: 6px; flex-wrap: wrap; }
        
        /* Badges */
        .badge-status { padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 0.73rem; display: inline-flex; align-items: center; gap: 5px; letter-spacing: 0.5px; }
        .badge-pending { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-active { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-completed { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .badge-payment { padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 0.7rem; }
        .badge-paid { background: #d1fae5; color: #065f46; }
        .badge-unpaid { background: #fef3c7; color: #92400e; }
        
        /* Empty */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state .empty-icon { font-size: 5rem; color: #e0e0e0; margin-bottom: 20px; }
        .empty-state h5 { font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        
        /* Pagination */
        .pagination-wrap { display: flex; justify-content: center; margin-top: 30px; }
        .pagination { display: flex; gap: 6px; list-style: none; padding: 0; margin: 0; }
        .pagination .page-link { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border); color: var(--text); font-weight: 600; text-decoration: none; transition: var(--transition); font-size: 0.9rem; background: white; }
        .pagination .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .pagination .active .page-link { background: var(--gradient); color: white; border-color: transparent; box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
        .pagination .disabled .page-link { opacity: 0.4; pointer-events: none; }
        
        .alert-custom { border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; border: none; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        
        @media (max-width: 991px) {
            .dash-sidebar { position: static; margin-bottom: 25px; }
            .booking-inner { flex-direction: column; }
            .booking-img { width: 100%; height: 180px; }
            .page-header h1 { font-size: 1.6rem; }
        }
        @media (max-width: 576px) {
            .status-tabs { gap: 4px; }
            .status-tab { padding: 8px 12px; font-size: 0.75rem; }
            .booking-body { padding: 15px; }
            .booking-dates { flex-direction: column; gap: 4px; }
            .booking-bottom { flex-direction: column; gap: 10px; align-items: flex-start; }
            .actions { width: 100%; justify-content: flex-start; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><span class="brand-icon">🚗</span> <?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#dashNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="dashNav">
                <ul class="navbar-nav me-auto gap-1">
                    <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i> الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="../cars.php"><i class="fas fa-car me-1"></i> السيارات</a></li>
                    <li class="nav-item"><a class="nav-link" href="../contact.php"><i class="fas fa-envelope me-1"></i> اتصل بنا</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <a href="../cars.php" class="btn btn-primary btn-sm"><i class="fas fa-plus-circle me-1"></i> حجز جديد</a>
                    <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-sign-out-alt me-1"></i> خروج</a>
                </div>
            </div>
        </div>
    </nav>
    
    <section class="page-header" data-aos="fade-up">
        <div class="container">
            <div class="header-icon"><i class="fas fa-calendar-check"></i></div>
            <h1>طلباتي</h1>
            <p>جميع حجوزاتك في مكان واحد - تتبع، ادفع، حمّل العقود</p>
        </div>
    </section>
    
    <section class="dashboard-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 mb-4" data-aos="fade-right">
                    <div class="dash-sidebar">
                        <div class="sidebar-profile">
                            <div class="avatar-ring"><img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&size=80&background=667eea&color=fff&bold=true" alt=""></div>
                            <h6><?php echo htmlspecialchars($user_name); ?></h6>
                            <span class="badge-role">عميل مميز</span>
                        </div>
                        <div class="sidebar-menu">
                            <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                            <a href="my-bookings.php" class="active"><i class="fas fa-calendar-check"></i> طلباتي <span class="badge-count"><?php echo $counts['all']; ?></span></a>
                            <a href="my-favorites.php"><i class="fas fa-heart"></i> المفضلة</a>
                            <a href="profile.php"><i class="fas fa-user-cog"></i> الملف الشخصي</a>
                            <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-9" data-aos="fade-left">
                    <?php if ($error): ?>
                    <div class="alert-custom" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                    <div class="alert-custom" style="background:#d1fae5;color:#065f46;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <div class="content-card">
                        <div class="card-header-custom">
                            <h5><i class="fas fa-list-check"></i> قائمة الحجوزات</h5>
                            <span class="text-muted" style="font-size:0.85rem;">إجمالي: <strong><?php echo $counts['all']; ?></strong> حجز</span>
                        </div>
                        
                        <div class="status-tabs">
                            <a href="my-bookings.php" class="status-tab <?php echo empty($status_filter) ? 'active' : ''; ?>"><i class="fas fa-list"></i> الكل <span class="count"><?php echo $counts['all']; ?></span></a>
                            <a href="my-bookings.php?status=pending" class="status-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> قيد الانتظار <span class="count"><?php echo $counts['pending']; ?></span></a>
                            <a href="my-bookings.php?status=confirmed" class="status-tab <?php echo $status_filter === 'confirmed' ? 'active' : ''; ?>"><i class="fas fa-check-circle"></i> مؤكدة <span class="count"><?php echo $counts['confirmed']; ?></span></a>
                            <a href="my-bookings.php?status=active" class="status-tab <?php echo $status_filter === 'active' ? 'active' : ''; ?>"><i class="fas fa-play-circle"></i> نشطة <span class="count"><?php echo $counts['active']; ?></span></a>
                            <a href="my-bookings.php?status=completed" class="status-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>"><i class="fas fa-flag-checkered"></i> مكتملة <span class="count"><?php echo $counts['completed']; ?></span></a>
                            <a href="my-bookings.php?status=cancelled" class="status-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>"><i class="fas fa-ban"></i> ملغية <span class="count"><?php echo $counts['cancelled']; ?></span></a>
                        </div>
                        
                        <?php if (empty($bookings)): ?>
                        <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar-xmark"></i></div><h5>لا توجد حجوزات</h5><p class="text-muted"><?php echo empty($status_filter) ? 'لم تقم بأي حجز بعد. ابدأ رحلتك الآن!' : 'لا توجد حجوزات بهذه الحالة'; ?></p><a href="../cars.php" class="btn btn-primary mt-3"><i class="fas fa-plus-circle me-1"></i> احجز سيارة الآن</a></div>
                        <?php else: ?>
                        <?php foreach ($bookings as $b): 
                            $img = my_img($b['car_image'] ?? '');
                            $sClass = match($b['booking_status']) {'pending'=>'badge-pending','confirmed'=>'badge-confirmed','active'=>'badge-active','completed'=>'badge-completed','cancelled'=>'badge-cancelled',default=>'badge-pending'};
                            $sText = match($b['booking_status']) {'pending'=>'قيد الانتظار','confirmed'=>'مؤكد','active'=>'نشط','completed'=>'مكتمل','cancelled'=>'ملغي',default=>$b['booking_status']};
                            $sIcon = match($b['booking_status']) {'pending'=>'fa-clock','confirmed'=>'fa-check-circle','active'=>'fa-play-circle','completed'=>'fa-flag-checkered','cancelled'=>'fa-ban',default=>'fa-circle'};
                            $pClass = $b['payment_status']==='paid'?'badge-paid':'badge-unpaid';
                            $pText = $b['payment_status']==='paid'?'مدفوع':'غير مدفوع';
                        ?>
                        <div class="booking-card">
                            <div class="booking-inner">
                                <div class="booking-img">
                                    <img src="<?php echo $img; ?>" alt="" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=300&h=200&fit=crop'">
                                    <div class="status-badge"><span class="badge-status <?php echo $sClass; ?>"><i class="fas <?php echo $sIcon; ?>"></i> <?php echo $sText; ?></span></div>
                                </div>
                                <div class="booking-body">
                                    <div class="booking-top">
                                        <div class="booking-info">
                                            <h6><?php echo htmlspecialchars($b['brand'].' '.$b['model'].' '.$b['year']); ?></h6>
                                            <div class="book-num">#<?php echo $b['booking_number']; ?></div>
                                            <div class="specs">
                                                <span><i class="fas fa-cog"></i> <?php echo $b['transmission']=='automatic'?'أوتوماتيك':'يدوي'; ?></span>
                                                <span><i class="fas fa-gas-pump"></i> <?php echo $b['fuel_type']; ?></span>
                                                <span><i class="fas fa-user"></i> <?php echo $b['seats']; ?> مقاعد</span>
                                                <span><i class="fas fa-palette"></i> <?php echo htmlspecialchars($b['color']??''); ?></span>
                                            </div>
                                        </div>
                                        <span class="badge-payment <?php echo $pClass; ?>"><?php echo $pText; ?></span>
                                    </div>
                                    <div class="booking-dates">
                                        <div class="date-item"><i class="far fa-calendar-alt"></i> <strong>الاستلام:</strong> <?php echo format_date($b['pickup_date'],'d/m/Y H:i'); ?></div>
                                        <div class="date-item"><i class="far fa-calendar-check"></i> <strong>التسليم:</strong> <?php echo format_date($b['return_date'],'d/m/Y H:i'); ?></div>
                                        <div class="date-item"><i class="fas fa-clock"></i> <strong><?php echo $b['total_days']; ?> أيام</strong></div>
                                    </div>
                                    <div class="booking-bottom">
                                        <div class="price"><span class="amount"><?php echo number_format($b['total_amount'],2); ?> DH</span><span class="period"> المجموع</span></div>
                                        <div class="actions">
                                            <?php if(in_array($b['booking_status'],['confirmed','active','completed'])): ?>
                                            <a href="../contract.php?booking_id=<?php echo $b['id']; ?>" class="btn btn-contract btn-xs" target="_blank"><i class="fas fa-file-contract"></i> العقد</a>
                                            <?php endif; ?>
                                            <?php if($b['payment_status']!=='paid' && !in_array($b['booking_status'],['cancelled','completed'])): ?>
                                            <a href="../payment.php?booking_id=<?php echo $b['id']; ?>" class="btn btn-pay btn-xs"><i class="fas fa-credit-card"></i> دفع</a>
                                            <?php endif; ?>
                                            <?php if(in_array($b['booking_status'],['pending','confirmed'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('إلغاء هذا الحجز؟')">
                                                <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="cancel_booking" value="1">
                                                <button type="submit" class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination-wrap">
                            <ul class="pagination">
                                <li class="page-item <?php echo $page<=1?'disabled':''; ?>"><a class="page-link" href="<?php echo $page>1?build_url($page-1):'#'; ?>"><i class="fas fa-chevron-right"></i></a></li>
                                <?php for($i=1;$i<=$totalPages;$i++): ?>
                                <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo build_url($i); ?>"><?php echo $i; ?></a></li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>"><a class="page-link" href="<?php echo $page<$totalPages?build_url($page+1):'#'; ?>"><i class="fas fa-chevron-left"></i></a></li>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>AOS.init({duration:600,once:true});</script>
</body>
</html>