<?php
/**
 * Premium Car Rental - Car Details Page
 * Ultimate Luxury Design Version
 */
require_once 'includes/config.php';

// Validate car ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: cars.php');
    exit();
}

$car_id = intval($_GET['id']);

// Fetch car data
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT AVG(rating) FROM reviews WHERE car_id = c.id AND status = 'approved') as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE car_id = c.id AND status = 'approved') as review_count,
               (SELECT COUNT(*) FROM bookings WHERE car_id = c.id AND booking_status = 'completed') as rental_count
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

// حالة المفضلة للمستخدم الحالي
$is_favorite = false;
if (is_logged_in()) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :uid AND car_id = :cid");
        $stmt->execute([':uid' => $_SESSION['user_id'], ':cid' => $car_id]);
        $is_favorite = $stmt->fetchColumn() > 0;
    } catch (Exception $e) {}
}

// Fetch car images
try {
    $stmt = $pdo->prepare("SELECT * FROM car_images WHERE car_id = :car_id ORDER BY is_primary DESC, sort_order ASC");
    $stmt->execute([':car_id' => $car_id]);
    $car_images = $stmt->fetchAll();
} catch (Exception $e) {
    $car_images = [];
}

// Fetch similar cars
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image
        FROM cars c 
        WHERE c.id != :car_id AND c.status = 'available' 
        AND (c.brand = :brand OR c.type = :type)
        ORDER BY RAND() LIMIT 4
    ");
    $stmt->execute([':car_id' => $car_id, ':brand' => $car['brand'], ':type' => $car['type'] ?? '']);
    $similar_cars = $stmt->fetchAll();
} catch (Exception $e) {
    $similar_cars = [];
}

// Fetch reviews
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name, u.avatar 
        FROM reviews r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.car_id = :car_id AND r.status = 'approved' 
        ORDER BY r.created_at DESC LIMIT 6
    ");
    $stmt->execute([':car_id' => $car_id]);
    $reviews = $stmt->fetchAll();
} catch (Exception $e) {
    $reviews = [];
}

// Fetch locations
try {
    $locations = $pdo->query("SELECT * FROM locations WHERE status = 'active' ORDER BY city ASC")->fetchAll();
} catch (Exception $e) {
    $locations = [];
}

// Image URL helper
function get_image_url($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=800&h=500&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return 'uploads/cars/' . $path;
}

$main_image = !empty($car_images) ? get_image_url($car_images[0]['image_path']) : 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=800&h=500&fit=crop';

// Fuel labels
$fuel_labels = ['petrol' => 'بنزين', 'diesel' => 'ديزل', 'electric' => 'كهرباء', 'hybrid' => 'هايبرد'];
$fuel_text = $fuel_labels[$car['fuel_type']] ?? $car['fuel_type'];

// Car type labels
$type_labels = ['luxury' => 'فاخرة', 'suv' => 'SUV', 'economy' => 'اقتصادية', 'sport' => 'رياضية'];
$type_text = $type_labels[$car['type'] ?? ''] ?? ($car['type'] ?? 'قياسية');

$page_title = $car['brand'] . ' ' . $car['model'] . ' ' . $car['year'] . ' - للإيجار | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- Swiper -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <!-- AOS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_green.css">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #4f5fd6;
            --primary-light: #eef0ff;
            --secondary: #764ba2;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-dark: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            --gold: #f59e0b;
            --gold-light: #fffbeb;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --white: #ffffff;
            --text: #333333;
            --text-light: #6c757d;
            --text-muted: #98a6ad;
            --border: #e0e0e0;
            --border-light: #f0f0f0;
            --success: #10b981;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.15);
            --shadow-xl: 0 30px 80px rgba(0,0,0,0.2);
            --radius-xs: 8px;
            --radius-sm: 12px;
            --radius: 16px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        html { scroll-behavior: smooth; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f5f6fa;
            color: var(--text);
            line-height: 1.8;
            overflow-x: hidden;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 3px; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        /* ========== NAVBAR ========== */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: var(--shadow-xs);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .navbar.scrolled { box-shadow: var(--shadow-sm); }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
            font-size: 1.3rem;
            color: var(--dark) !important;
            text-decoration: none;
        }
        
        .navbar-brand .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            color: white;
        }
        
        .nav-link {
            font-weight: 600;
            color: var(--text) !important;
            padding: 10px 18px !important;
            border-radius: 10px;
            transition: var(--transition);
            font-size: 0.95rem;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary) !important;
            background: var(--primary-light);
        }
        
        .btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 10px 22px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        
        .btn:active::after { width: 300px; height: 300px; }
        
        .btn-primary {
            background: var(--gradient);
            border: none;
            color: white;
        }
        
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); color: white; }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: transparent;
        }
        
        .btn-outline-primary:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
            border: none;
            color: #000;
            font-weight: 700;
        }
        
        .btn-warning:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(245,158,11,0.4); }
        
        .btn-success {
            background: #25d366;
            border: none;
            color: white;
            font-weight: 700;
        }
        
        .btn-success:hover { background: #1ebe57; color: white; transform: translateY(-2px); box-shadow: 0 10px 30px rgba(37,211,102,0.4); }
        
        .btn-lg { padding: 16px 35px; font-size: 1.1rem; border-radius: 14px; }
        .btn-sm { padding: 8px 16px; font-size: 0.85rem; }
        
        /* ========== BREADCRUMB BAR ========== */
        .breadcrumb-bar {
            background: white;
            border-bottom: 1px solid var(--border-light);
            padding: 14px 0;
        }
        
        .breadcrumb {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }
        
        .breadcrumb-item { color: var(--text-light); }
        .breadcrumb-item a { color: var(--text-light); text-decoration: none; transition: var(--transition); }
        .breadcrumb-item a:hover { color: var(--primary); }
        .breadcrumb-item.active { color: var(--primary); font-weight: 700; }
        .breadcrumb-separator { color: #ccc; font-size: 0.7rem; }
        
        /* ========== MAIN SECTION ========== */
        .detail-section { padding: 40px 0 60px; }
        
        /* ========== GALLERY ========== */
        .gallery-wrapper {
            position: sticky;
            top: 100px;
        }
        
        .main-image-container {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            background: #000;
            cursor: zoom-in;
            margin-bottom: 15px;
        }
        
        .main-image-container img {
            width: 100%;
            height: 450px;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .main-image-container:hover img { transform: scale(1.03); }
        
        .gallery-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: rgba(255,255,255,0.9);
            border: none;
            cursor: pointer;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: var(--transition);
            z-index: 5;
            color: var(--dark);
        }
        
        .gallery-nav-btn:hover { background: white; box-shadow: 0 6px 25px rgba(0,0,0,0.3); transform: translateY(-50%) scale(1.05); }
        .gallery-nav-btn.prev { right: 15px; }
        .gallery-nav-btn.next { left: 15px; }
        
        .image-badge {
            position: absolute;
            bottom: 20px;
            right: 50%;
            transform: translateX(50%);
            background: rgba(0,0,0,0.75);
            backdrop-filter: blur(10px);
            color: white;
            padding: 6px 18px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .car-badges {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            z-index: 3;
        }
        
        .car-badge {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            gap: 5px;
            backdrop-filter: blur(10px);
        }
        
        .car-badge.popular { background: rgba(239,68,68,0.9); }
        .car-badge.type { background: rgba(102,126,234,0.9); }
        
        .thumbnails-scroll {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 5px 0;
            scrollbar-width: thin;
        }
        
        .thumbnails-scroll::-webkit-scrollbar { height: 4px; }
        .thumbnails-scroll::-webkit-scrollbar-track { background: transparent; }
        .thumbnails-scroll::-webkit-scrollbar-thumb { background: #ddd; border-radius: 2px; }
        
        .thumb-item {
            width: 95px;
            height: 70px;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            border: 3px solid transparent;
            transition: var(--transition);
            flex-shrink: 0;
            opacity: 0.65;
        }
        
        .thumb-item:hover { opacity: 0.9; border-color: #ccc; }
        .thumb-item.active { border-color: var(--primary); opacity: 1; box-shadow: 0 0 0 4px rgba(102,126,234,0.2); }
        .thumb-item img { width: 100%; height: 100%; object-fit: cover; }
        
        /* ========== INFO CARD ========== */
        .info-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border-light);
        }
        
        .car-title {
            font-weight: 900;
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 8px;
            line-height: 1.3;
        }
        
        .car-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .car-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .car-meta-item i { color: var(--primary); font-size: 0.85rem; }
        
        .rating-display {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--gold-light);
            padding: 6px 14px;
            border-radius: 50px;
            font-weight: 700;
            color: #92400e;
        }
        
        .rating-display i { color: var(--gold); }
        
        /* Price Display */
        .price-showcase {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: var(--radius);
            padding: 24px;
            margin: 24px 0;
            border: 2px solid var(--border-light);
        }
        
        .price-main {
            display: flex;
            align-items: baseline;
            gap: 6px;
            margin-bottom: 18px;
        }
        
        .price-amount {
            font-size: 2.8rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
        }
        
        .price-unit { color: var(--text-light); font-weight: 500; font-size: 1rem; }
        
        .price-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .price-option-card {
            text-align: center;
            padding: 14px 8px;
            background: white;
            border-radius: 10px;
            border: 2px solid var(--border);
            transition: var(--transition);
        }
        
        .price-option-card:hover { border-color: var(--primary); box-shadow: 0 5px 15px rgba(102,126,234,0.1); }
        
        .price-option-card .opt-label { font-size: 0.78rem; color: var(--text-light); margin-bottom: 4px; }
        .price-option-card .opt-value { font-size: 1.1rem; font-weight: 800; color: var(--dark); }
        .price-option-card .opt-save { font-size: 0.75rem; color: var(--success); font-weight: 700; }
        
        /* Specs Grid */
        .specs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        
        .spec-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            background: var(--light);
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .spec-item:hover { background: #eef0ff; }
        
        .spec-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: var(--primary);
            box-shadow: var(--shadow-xs);
        }
        
        .spec-content strong { display: block; font-size: 0.9rem; color: var(--dark); }
        .spec-content span { font-size: 0.8rem; color: var(--text-light); }
        
        /* Booking Form */
        .booking-form-section {
            background: white;
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-sm);
            border: 2px solid var(--primary-light);
            margin-top: 24px;
        }
        
        .booking-form-section .form-label {
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 6px;
            color: var(--dark);
        }
        
        .booking-form-section .form-control,
        .booking-form-section .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.95rem;
            transition: var(--transition);
            font-family: 'Cairo', sans-serif;
        }
        
        .booking-form-section .form-control:focus,
        .booking-form-section .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102,126,234,0.08);
            outline: none;
        }
        
        /* ========== SIMILAR CARS ========== */
        .section-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .section-badge {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            padding: 6px 18px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .section-title {
            font-weight: 900;
            font-size: 1.8rem;
            color: var(--dark);
        }
        
        .similar-car-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-xs);
            transition: var(--transition);
            border: 1px solid var(--border-light);
            text-decoration: none;
            display: block;
        }
        
        .similar-car-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }
        
        .similar-car-card img {
            width: 100%;
            height: 160px;
            object-fit: cover;
        }
        
        .similar-car-card .card-info {
            padding: 15px;
            text-align: center;
        }
        
        .similar-car-card .card-info h6 {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .similar-car-card .card-info .price {
            font-weight: 800;
            color: var(--primary);
        }
        
        /* ========== LIGHTBOX ========== */
        .lightbox-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }
        
        .lightbox-overlay.active { display: flex; }
        
        .lightbox-overlay img {
            max-width: 90%;
            max-height: 90vh;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .lightbox-close {
            position: absolute;
            top: 25px;
            left: 30px;
            color: white;
            font-size: 45px;
            cursor: pointer;
            z-index: 10;
            transition: var(--transition);
        }
        
        .lightbox-close:hover { color: var(--danger); transform: scale(1.1); }
        
        .lightbox-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: white;
            font-size: 35px;
            cursor: pointer;
            padding: 20px;
            transition: var(--transition);
            user-select: none;
        }
        
        .lightbox-nav:hover { color: var(--primary); }
        .lightbox-nav.prev { right: 20px; }
        .lightbox-nav.next { left: 20px; }
        
        /* ========== FOOTER ========== */
        .footer {
            background: var(--dark);
            color: white;
            padding: 50px 0 20px;
            text-align: center;
        }
        
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; margin: 0 12px; font-weight: 500; transition: var(--transition); }
        .footer a:hover { color: white; }
        .footer .brand { font-weight: 900; font-size: 1.2rem; color: white; margin-bottom: 15px; display: block; }
        .footer .brand i { color: var(--primary); }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 30px; font-size: 0.9rem; opacity: 0.7; }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 991px) {
            .gallery-wrapper { position: static; margin-bottom: 25px; }
            .main-image-container img { height: 320px; }
            .car-title { font-size: 1.5rem; }
            .price-amount { font-size: 2rem; }
            .price-options { grid-template-columns: repeat(3, 1fr); }
            .specs-grid { grid-template-columns: 1fr 1fr; }
        }
        
        @media (max-width: 576px) {
            .main-image-container img { height: 250px; }
            .car-title { font-size: 1.3rem; }
            .price-amount { font-size: 1.8rem; }
            .price-options { grid-template-columns: 1fr 1fr; }
            .specs-grid { grid-template-columns: 1fr; }
            .car-meta { gap: 10px; }
            .info-card { padding: 20px; }
        }
    </style>
</head>
<body>
    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar navbar-expand-lg" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span class="brand-icon">🚗</span>
                <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
                    <li class="nav-item"><a class="nav-link" href="index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link active" href="cars.php">السيارات</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">من نحن</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">اتصل بنا</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if (is_logged_in()): ?>
                        <a href="customer/dashboard.php" class="btn btn-outline-primary btn-sm">لوحة التحكم</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary btn-sm">تسجيل الدخول</a>
                        <a href="register.php" class="btn btn-primary btn-sm">إنشاء حساب</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- ==================== BREADCRUMB ==================== -->
    <div class="breadcrumb-bar">
        <div class="container">
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
                    <li class="breadcrumb-separator"><i class="fas fa-chevron-left"></i></li>
                    <li class="breadcrumb-item"><a href="cars.php">السيارات</a></li>
                    <li class="breadcrumb-separator"><i class="fas fa-chevron-left"></i></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></li>
                </ol>
            </nav>
        </div>
    </div>
    
    <!-- ==================== MAIN CONTENT ==================== -->
    <section class="detail-section">
        <div class="container">
            <div class="row g-4">
                <!-- ==================== GALLERY COLUMN ==================== -->
                <div class="col-lg-7">
                    <div class="gallery-wrapper" data-aos="fade-up">
                        <div class="main-image-container" onclick="openLightbox()">
                            <img src="<?php echo $main_image; ?>" id="mainImage" alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                            
                            <?php if (count($car_images) > 1): ?>
                            <button class="gallery-nav-btn prev" onclick="event.stopPropagation(); navigateGallery(-1)">
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <button class="gallery-nav-btn next" onclick="event.stopPropagation(); navigateGallery(1)">
                                <i class="fas fa-chevron-left"></i>
                            </button>
                            <div class="image-badge" id="imageCounter">1 / <?php echo count($car_images); ?></div>
                            <?php endif; ?>
                            
                            <div class="car-badges">
                                <?php if ($car['popular']): ?>
                                <span class="car-badge popular"><i class="fas fa-fire"></i> الأكثر طلباً</span>
                                <?php endif; ?>
                                <span class="car-badge type"><i class="fas fa-tag"></i> <?php echo $type_text; ?></span>
                            </div>
                        </div>
                        
                        <?php if (count($car_images) > 1): ?>
                        <div class="thumbnails-scroll">
                            <?php foreach ($car_images as $index => $img): 
                                $thumb = get_image_url($img['image_path']);
                            ?>
                            <div class="thumb-item <?php echo $index === 0 ? 'active' : ''; ?>" 
                                 onclick="setMainImage(<?php echo $index; ?>, '<?php echo $thumb; ?>')">
                                <img src="<?php echo $thumb; ?>" alt="صورة <?php echo $index + 1; ?>" loading="lazy">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ==================== INFO COLUMN ==================== -->
                <div class="col-lg-5">
                    <div class="info-card" data-aos="fade-up" data-aos-delay="100">
                        <h1 class="car-title"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h1>
                        
                        <div class="car-meta">
                            <span class="car-meta-item"><i class="fas fa-calendar"></i> <?php echo $car['year']; ?></span>
                            <span class="car-meta-item"><i class="fas fa-door-open"></i> <?php echo $car['doors']; ?> أبواب</span>
                            <span class="car-meta-item"><i class="fas fa-palette"></i> <?php echo htmlspecialchars($car['color'] ?? 'غير محدد'); ?></span>
                            
                            <?php if ($car['avg_rating']): ?>
                            <span class="rating-display">
                                <i class="fas fa-star"></i> <?php echo number_format($car['avg_rating'], 1); ?>
                                <small>(<?php echo $car['review_count']; ?>)</small>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Price Showcase -->
                        <div class="price-showcase">
                            <div class="price-main">
                                <span class="price-amount"><?php echo number_format($car['daily_rate'], 2); ?></span>
                                <span class="price-unit">DH / لليوم</span>
                            </div>
                            
                            <div class="price-options">
                                <div class="price-option-card">
                                    <div class="opt-label">يوم واحد</div>
                                    <div class="opt-value"><?php echo number_format($car['daily_rate'], 2); ?> DH</div>
                                </div>
                                <?php if (!empty($car['weekly_rate'])): ?>
                                <div class="price-option-card" style="border-color: var(--success);">
                                    <div class="opt-label">أسبوع</div>
                                    <div class="opt-value"><?php echo number_format($car['weekly_rate'], 2); ?> DH</div>
                                    <div class="opt-save">وفر <?php echo number_format(($car['daily_rate'] * 7) - $car['weekly_rate'], 2); ?> DH</div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($car['monthly_rate'])): ?>
                                <div class="price-option-card" style="border-color: var(--primary);">
                                    <div class="opt-label">شهر</div>
                                    <div class="opt-value"><?php echo number_format($car['monthly_rate'], 2); ?> DH</div>
                                    <div class="opt-save">وفر <?php echo number_format(($car['daily_rate'] * 30) - $car['monthly_rate'], 2); ?> DH</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Specs -->
                        <div class="specs-grid">
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-cog"></i></div>
                                <div class="spec-content">
                                    <strong>ناقل الحركة</strong>
                                    <span><?php echo $car['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي'; ?></span>
                                </div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-gas-pump"></i></div>
                                <div class="spec-content">
                                    <strong>نوع الوقود</strong>
                                    <span><?php echo $fuel_text; ?></span>
                                </div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-chair"></i></div>
                                <div class="spec-content">
                                    <strong>عدد المقاعد</strong>
                                    <span><?php echo $car['seats']; ?> مقاعد</span>
                                </div>
                            </div>
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-road"></i></div>
                                <div class="spec-content">
                                    <strong>الحد اليومي</strong>
                                    <span><?php echo $car['mileage_limit']; ?> كم</span>
                                </div>
                            </div>
                            <?php if (!empty($car['engine_size'])): ?>
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-tachometer-alt"></i></div>
                                <div class="spec-content">
                                    <strong>سعة المحرك</strong>
                                    <span><?php echo htmlspecialchars($car['engine_size']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="spec-item">
                                <div class="spec-icon"><i class="fas fa-shield-alt"></i></div>
                                <div class="spec-content">
                                    <strong>التأمين</strong>
                                    <span><?php echo number_format($car['deposit'], 2); ?> DH (مسترد)</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($car['description'])): ?>
                        <h5 class="fw-bold mt-4 mb-2"><i class="fas fa-info-circle text-primary me-2"></i>الوصف</h5>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($car['description'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if (!empty($car['features'])): ?>
                        <h5 class="fw-bold mt-3 mb-2"><i class="fas fa-check-circle text-success me-2"></i>المميزات</h5>
                        <p class="text-muted"><?php echo htmlspecialchars($car['features']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Booking Form -->
                    <div class="booking-form-section" data-aos="fade-up" data-aos-delay="200">
                        <h5 class="fw-bold mb-3 text-center">
                            <i class="fas fa-calendar-check text-primary me-2"></i>احجز هذه السيارة الآن
                        </h5>
                        
                        <form action="booking.php" method="GET">
                            <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                            
                            <div class="row g-3 mb-3">
                                <div class="col-6">
                                    <label class="form-label">تاريخ الاستلام</label>
                                    <input type="text" class="form-control datepicker" name="pickup_date" 
                                           placeholder="اختر التاريخ" required readonly>
                                </div>
                                <div class="col-6">
                                    <label class="form-label">تاريخ التسليم</label>
                                    <input type="text" class="form-control datepicker" name="return_date" 
                                           placeholder="اختر التاريخ" required readonly>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">مكان الاستلام</label>
                                <select class="form-select" name="pickup_location" required>
                                    <option value="">اختر الموقع...</option>
                                    <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo $loc['id']; ?>">
                                        <?php echo htmlspecialchars($loc['city'] . ' - ' . $loc['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-calendar-check me-2"></i> احجز الآن
                                </button>
                                <button type="button" id="favoriteBtn" onclick="toggleFavorite(<?php echo $car_id; ?>)"
                                        class="btn btn-lg <?php echo $is_favorite ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                    <i class="<?php echo $is_favorite ? 'fas' : 'far'; ?> fa-heart me-2"></i>
                                    <?php echo $is_favorite ? 'في المفضلة' : 'أضف إلى المفضلة'; ?>
                                </button>
                                <a href="https://wa.me/212600000000?text=مرحباً، أريد حجز <?php echo urlencode($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']); ?>" 
                                   class="btn btn-success btn-lg" target="_blank">
                                    <i class="fab fa-whatsapp me-2"></i> احجز عبر واتساب
                                </a>
                            </div>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">تحتاج مساعدة؟</small>
                            <a href="tel:+212600000000" class="d-block fw-bold text-primary">
                                <i class="fas fa-phone"></i> +212 600-000000
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- ==================== SIMILAR CARS ==================== -->
            <?php if (!empty($similar_cars)): ?>
            <div class="mt-5 pt-4" data-aos="fade-up">
                <div class="section-header">
                    <span class="section-badge">قد يعجبك أيضاً</span>
                    <h3 class="section-title">سيارات مشابهة</h3>
                </div>
                
                <div class="row g-3">
                    <?php foreach ($similar_cars as $sim): 
                        $sim_img = get_image_url($sim['main_image'] ?? '');
                    ?>
                    <div class="col-lg-3 col-md-6">
                        <a href="car-details.php?id=<?php echo $sim['id']; ?>" class="similar-car-card">
                            <img src="<?php echo $sim_img; ?>" alt="<?php echo htmlspecialchars($sim['brand']); ?>" 
                                 loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=400&h=250&fit=crop'">
                            <div class="card-info">
                                <h6><?php echo htmlspecialchars($sim['brand'] . ' ' . $sim['model']); ?></h6>
                                <span class="price"><?php echo number_format($sim['daily_rate'], 2); ?> DH / يوم</span>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- ==================== REVIEWS ==================== -->
            <?php if (!empty($reviews)): ?>
            <div class="mt-5 pt-4" data-aos="fade-up">
                <div class="section-header">
                    <span class="section-badge">آراء العملاء</span>
                    <h3 class="section-title">تقييمات السيارة</h3>
                </div>
                
                <div class="row g-3">
                    <?php foreach ($reviews as $review): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100" style="border-radius: var(--radius);">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center gap-3 mb-3">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($review['full_name']); ?>&size=45&background=random&bold=true" 
                                         class="rounded-circle" width="45" height="45" alt="">
                                    <div>
                                        <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($review['full_name']); ?></h6>
                                        <div class="text-warning small">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= $review['rating'] ? '' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                                <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- ==================== LIGHTBOX ==================== -->
    <div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
        <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
        <span class="lightbox-nav prev" onclick="event.stopPropagation(); lightboxNavigate(-1)"><i class="fas fa-chevron-right"></i></span>
        <img src="" id="lightboxImage" alt="" onclick="event.stopPropagation()">
        <span class="lightbox-nav next" onclick="event.stopPropagation(); lightboxNavigate(1)"><i class="fas fa-chevron-left"></i></span>
    </div>
    
    <!-- ==================== FOOTER ==================== -->
    <footer class="footer">
        <div class="container">
            <span class="brand"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></span>
            <div class="mb-3">
                <a href="index.php">الرئيسية</a>
                <a href="cars.php">السيارات</a>
                <a href="about.php">من نحن</a>
                <a href="contact.php">اتصل بنا</a>
                <a href="terms.php">الشروط</a>
            </div>
            <div class="footer-bottom">
                <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة.</p>
            </div>
        </div>
    </footer>
    
    <!-- ==================== SCRIPTS ==================== -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    
    <script>
        // AOS
        AOS.init({ duration: 800, easing: 'ease-in-out', once: true });
        
        // Datepickers
        flatpickr(".datepicker", {
            locale: "ar",
            dateFormat: "Y-m-d",
            minDate: "today",
            disableMobile: true
        });
        
        // Navbar scroll
        window.addEventListener('scroll', () => {
            document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50);
        });
        
        // ==================== GALLERY ====================
        const imageUrls = [
            <?php foreach ($car_images as $img) { echo "'" . get_image_url($img['image_path']) . "',"; } ?>
        ];
        let currentIndex = 0;
        
        function setMainImage(index, url) {
            currentIndex = index;
            const img = document.getElementById('mainImage');
            img.style.opacity = '0';
            setTimeout(() => { img.src = url; img.style.opacity = '1'; }, 200);
            
            document.querySelectorAll('.thumb-item').forEach((t, i) => t.classList.toggle('active', i === index));
            document.getElementById('imageCounter').textContent = (index + 1) + ' / ' + imageUrls.length;
        }
        
        function navigateGallery(dir) {
            let idx = currentIndex + dir;
            if (idx < 0) idx = imageUrls.length - 1;
            if (idx >= imageUrls.length) idx = 0;
            setMainImage(idx, imageUrls[idx]);
        }
        
        // Keyboard navigation for gallery
        document.addEventListener('keydown', (e) => {
            if (document.getElementById('lightbox').classList.contains('active')) return;
            if (e.key === 'ArrowRight') navigateGallery(-1);
            if (e.key === 'ArrowLeft') navigateGallery(1);
        });
        
        // ==================== LIGHTBOX ====================
        let lightboxIdx = 0;
        
        function openLightbox() {
            lightboxIdx = currentIndex;
            document.getElementById('lightboxImage').src = imageUrls[lightboxIdx];
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        function lightboxNavigate(dir) {
            lightboxIdx += dir;
            if (lightboxIdx < 0) lightboxIdx = imageUrls.length - 1;
            if (lightboxIdx >= imageUrls.length) lightboxIdx = 0;
            
            const img = document.getElementById('lightboxImage');
            img.style.opacity = '0';
            setTimeout(() => { img.src = imageUrls[lightboxIdx]; img.style.opacity = '1'; }, 200);
        }
        
        // Lightbox keyboard
        document.addEventListener('keydown', (e) => {
            if (!document.getElementById('lightbox').classList.contains('active')) return;
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight') lightboxNavigate(-1);
            if (e.key === 'ArrowLeft') lightboxNavigate(1);
        });

        // المفضلة
        function toggleFavorite(carId) {
            var btn = document.getElementById('favoriteBtn');
            var isLoggedIn = <?php echo is_logged_in() ? 'true' : 'false'; ?>;

            if (!isLoggedIn) {
                if (confirm('يجب تسجيل الدخول لإضافة سيارة إلى المفضلة. الانتقال لصفحة الدخول؟')) {
                    window.location.href = 'login.php?redirect=' + encodeURIComponent('car-details.php?id=' + carId);
                }
                return;
            }

            btn.disabled = true;
            fetch('api/toggle-wishlist.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ car_id: carId })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btn.disabled = false;
                if (!data.success) { alert(data.message || 'حدث خطأ'); return; }
                var icon = btn.querySelector('i');
                if (data.in_wishlist) {
                    btn.classList.remove('btn-outline-danger'); btn.classList.add('btn-danger');
                    icon.className = 'fas fa-heart me-2';
                    btn.innerHTML = '<i class="fas fa-heart me-2"></i> في المفضلة';
                } else {
                    btn.classList.remove('btn-danger'); btn.classList.add('btn-outline-danger');
                    btn.innerHTML = '<i class="far fa-heart me-2"></i> أضف إلى المفضلة';
                }
            })
            .catch(function () { btn.disabled = false; alert('خطأ في الاتصال'); });
        }
    </script>
</body>
</html>