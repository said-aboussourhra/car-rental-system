<?php
/**
 * Premium Car Rental - Ultimate Homepage
 * Version: 4.0 - Luxury Design
 */
require_once 'includes/config.php';

// Fetch featured cars
$featured_cars = [];
try {
    $stmt = $pdo->query("
        SELECT c.*, 
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image,
               (SELECT AVG(rating) FROM reviews WHERE car_id = c.id AND status = 'approved') as avg_rating,
               (SELECT COUNT(*) FROM reviews WHERE car_id = c.id AND status = 'approved') as review_count
        FROM cars c WHERE c.status = 'available' 
        ORDER BY c.popular DESC, c.created_at DESC LIMIT 8
    ");
    $featured_cars = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch locations
$locations = [];
try {
    $locations = $pdo->query("SELECT * FROM locations WHERE status = 'active' LIMIT 10")->fetchAll();
} catch (Exception $e) {}

// Fetch reviews
$reviews = [];
try {
    $reviews = $pdo->query("
        SELECT r.*, u.full_name 
        FROM reviews r JOIN users u ON r.user_id = u.id 
        WHERE r.status = 'approved' ORDER BY r.created_at DESC LIMIT 6
    ")->fetchAll();
} catch (Exception $e) {}

// Image helper
function car_image($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=600&h=400&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return 'uploads/cars/' . $path;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="أفضل موقع لتأجير السيارات الفاخرة في المغرب. أسعار تنافسية، خدمة 24/7">
    <title><?php echo SITE_NAME; ?> - استأجر سيارتك الفاخرة</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
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
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --white: #ffffff;
            --text: #333333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.04);
            --shadow: 0 10px 40px rgba(0,0,0,0.08);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.12);
            --shadow-xl: 0 30px 80px rgba(0,0,0,0.2);
            --radius-xs: 8px;
            --radius-sm: 12px;
            --radius: 16px;
            --radius-lg: 20px;
            --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; overflow-x: hidden; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        /* ========== NAVBAR ========== */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }
        .navbar.scrolled { box-shadow: var(--shadow); }
        .navbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none;
        }
        .navbar-brand .brand-icon {
            width: 42px; height: 42px; background: var(--gradient); border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white;
        }
        .nav-link {
            font-weight: 600; color: var(--text) !important; padding: 10px 18px !important;
            border-radius: 10px; transition: var(--transition); font-size: 0.95rem;
        }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; background: var(--primary-light); }
        
        .btn {
            font-weight: 600; border-radius: 10px; padding: 10px 22px; transition: var(--transition);
            font-size: 0.9rem; position: relative; overflow: hidden;
        }
        .btn::after {
            content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0;
            border-radius: 50%; background: rgba(255,255,255,0.3);
            transform: translate(-50%, -50%); transition: width 0.6s, height 0.6s;
        }
        .btn:active::after { width: 300px; height: 300px; }
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(102,126,234,0.4); color: white; }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .btn-outline-primary:hover { background: var(--primary); color: white; transform: translateY(-2px); }
        .btn-light { background: white; color: var(--primary); font-weight: 700; }
        .btn-light:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .btn-outline-light { border: 2px solid white; color: white; }
        .btn-outline-light:hover { background: white; color: var(--primary); }
        .btn-lg { padding: 16px 35px; font-size: 1.1rem; border-radius: 14px; }
        .btn-sm { padding: 6px 14px; font-size: 0.85rem; }
        .btn-rounded { border-radius: 50px; }
        
        /* ========== HERO SECTION ========== */
        .hero-section {
            position: relative;
            min-height: 90vh;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, rgba(26,26,46,0.92) 0%, rgba(22,33,62,0.85) 100%),
                        url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?w=1920&q=80') center/cover no-repeat;
            color: white;
            padding: 100px 0 80px;
            overflow: hidden;
        }
        .hero-section::before {
            content: ''; position: absolute; top: -50%; left: -30%;
            width: 800px; height: 800px;
            background: radial-gradient(circle, rgba(102,126,234,0.3) 0%, transparent 70%);
            pointer-events: none;
        }
        .hero-section::after {
            content: ''; position: absolute; bottom: -20%; right: -10%;
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(118,75,162,0.2) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .hero-badge {
            display: inline-block; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px);
            padding: 8px 20px; border-radius: 50px; font-weight: 600; font-size: 0.9rem;
            margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.2);
        }
        .hero-title { font-size: 3.5rem; font-weight: 900; line-height: 1.3; margin-bottom: 20px; }
        .hero-title .highlight { color: var(--gold); }
        .hero-description { font-size: 1.2rem; opacity: 0.9; margin-bottom: 30px; }
        
        .hero-stats { display: flex; gap: 15px; margin: 25px 0; flex-wrap: wrap; }
        .hero-stat {
            display: flex; align-items: center; gap: 12px;
            background: rgba(255,255,255,0.1); backdrop-filter: blur(10px);
            padding: 15px 20px; border-radius: var(--radius-sm); border: 1px solid rgba(255,255,255,0.15);
        }
        .hero-stat-icon {
            width: 48px; height: 48px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
        }
        .hero-stat-value { font-size: 1.4rem; font-weight: 900; line-height: 1; }
        .hero-stat-label { font-size: 0.78rem; opacity: 0.8; }
        
        /* Booking Widget */
        .booking-widget {
            background: white; border-radius: var(--radius-lg); overflow: hidden;
            box-shadow: var(--shadow-xl);
        }
        .booking-widget-header {
            background: var(--gradient); padding: 22px; text-align: center;
        }
        .booking-widget-header h4 { font-weight: 800; margin: 0; font-size: 1.2rem; }
        .booking-widget-body { padding: 25px; color: var(--text); }
        .booking-widget .form-label { font-weight: 700; margin-bottom: 6px; color: var(--dark); font-size: 0.88rem; }
        .booking-widget .form-control, .booking-widget .form-select {
            border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 12px 15px;
            font-size: 0.95rem; transition: var(--transition); background: #fafafa; font-family: 'Cairo', sans-serif;
        }
        .booking-widget .form-control:focus, .booking-widget .form-select:focus {
            border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(102,126,234,0.08); background: white;
        }
        
        /* Wave */
        .wave-separator { position: absolute; bottom: -2px; left: 0; width: 100%; z-index: 2; }
        .wave-separator svg { display: block; }
        
        /* ========== SECTIONS ========== */
        .section { padding: 80px 0; }
        .section-light { background: #f8f9fa; }
        
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-badge {
            display: inline-block; background: var(--primary-light); color: var(--primary);
            padding: 7px 18px; border-radius: 50px; font-weight: 700; font-size: 0.85rem; margin-bottom: 12px;
        }
        .section-title { font-size: 2.2rem; font-weight: 900; color: var(--dark); margin-bottom: 12px; }
        .section-description { color: var(--text-light); font-size: 1.05rem; max-width: 600px; margin: 0 auto; }
        
        /* Features */
        .feature-card {
            background: white; padding: 35px 25px; border-radius: var(--radius); text-align: center;
            box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; transition: var(--transition); height: 100%;
        }
        .feature-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .feature-icon {
            width: 70px; height: 70px; border-radius: 18px; display: flex;
            align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 1.8rem;
        }
        .feature-card h5 { font-weight: 800; margin-bottom: 8px; color: var(--dark); }
        .feature-card p { color: var(--text-light); font-size: 0.92rem; margin: 0; }
        
        /* Car Cards */
        .car-card {
            background: white; border-radius: var(--radius); overflow: hidden;
            box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; transition: var(--transition); height: 100%;
        }
        .car-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }
        .car-card-image { position: relative; height: 210px; overflow: hidden; }
        .car-card-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .car-card:hover .car-card-image img { transform: scale(1.08); }
        .car-card-badge {
            position: absolute; top: 12px; right: 12px; background: var(--danger);
            color: white; padding: 5px 14px; border-radius: 50px; font-size: 0.75rem; font-weight: 700;
        }
        .car-card-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
            gap: 10px; opacity: 0; transition: var(--transition);
        }
        .car-card:hover .car-card-overlay { opacity: 1; }
        .car-card-body { padding: 18px; }
        .car-card-body h5 { font-weight: 800; margin-bottom: 5px; color: var(--dark); }
        .car-card-body .year { color: var(--text-light); font-size: 0.85rem; margin-bottom: 12px; }
        .car-specs { display: flex; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; }
        .car-spec {
            display: flex; align-items: center; gap: 4px; background: #f8f9fa;
            padding: 4px 10px; border-radius: 6px; font-size: 0.78rem; color: var(--text-light);
        }
        .car-spec i { color: var(--primary); }
        .car-price-row { display: flex; justify-content: space-between; align-items: flex-end; padding-top: 14px; border-top: 1px solid #f0f0f0; }
        .car-price { font-size: 1.3rem; font-weight: 900; color: var(--primary); }
        .car-price-unit { font-size: 0.78rem; color: var(--text-light); }
        
        /* Testimonials */
        .testimonial-card {
            background: white; padding: 25px; border-radius: var(--radius);
            box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; border-right: 4px solid var(--primary);
        }
        .testimonial-stars { color: var(--gold); margin-bottom: 12px; }
        .testimonial-text { font-style: italic; margin-bottom: 15px; color: var(--text); }
        .testimonial-author { display: flex; align-items: center; gap: 10px; }
        .testimonial-author img { width: 45px; height: 45px; border-radius: 50%; }
        .testimonial-author h6 { font-weight: 700; margin: 0; color: var(--dark); }
        
        /* CTA */
        .cta-section {
            background: var(--gradient); padding: 80px 0; text-align: center; color: white;
            position: relative; overflow: hidden;
        }
        .cta-section::before {
            content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%);
            animation: rotate 20s linear infinite;
        }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .cta-section .container { position: relative; z-index: 1; }
        
        /* Footer */
        .footer {
            background: var(--dark); color: white; padding: 60px 0 20px;
        }
        .footer h5 { font-weight: 800; margin-bottom: 18px; color: white; }
        .footer p, .footer li { color: rgba(255,255,255,0.7); font-size: 0.9rem; }
        .footer a { color: rgba(255,255,255,0.7); text-decoration: none; transition: var(--transition); }
        .footer a:hover { color: white; padding-right: 5px; }
        .footer-bottom { border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px; margin-top: 40px; text-align: center; font-size: 0.88rem; }
        .footer-social a {
            width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.1);
            display: inline-flex; align-items: center; justify-content: center; margin: 0 3px; transition: var(--transition);
        }
        .footer-social a:hover { background: var(--primary); transform: translateY(-3px); }
        
        /* WhatsApp Float */
        .whatsapp-float {
            position: fixed; bottom: 25px; right: 25px; width: 60px; height: 60px;
            background: #25d366; color: white; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-size: 30px;
            box-shadow: 0 10px 30px rgba(37,211,102,0.4); z-index: 999; text-decoration: none;
            animation: whatsappPulse 2s infinite; transition: var(--transition);
        }
        .whatsapp-float:hover { transform: scale(1.1); color: white; }
        @keyframes whatsappPulse {
            0% { box-shadow: 0 0 0 0 rgba(37,211,102,0.4); }
            70% { box-shadow: 0 0 0 20px rgba(37,211,102,0); }
            100% { box-shadow: 0 0 0 0 rgba(37,211,102,0); }
        }
        
        /* Back to Top */
        .back-to-top {
            position: fixed; bottom: 95px; right: 25px; width: 50px; height: 50px;
            background: var(--primary); color: white; border: none; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
            opacity: 0; visibility: hidden; transition: var(--transition); z-index: 998;
            box-shadow: 0 10px 30px rgba(102,126,234,0.4); font-size: 1.2rem;
        }
        .back-to-top.show { opacity: 1; visibility: visible; }
        .back-to-top:hover { background: var(--secondary); transform: translateY(-5px); }
        
        /* Responsive */
        @media (max-width: 991px) {
            .hero-title { font-size: 2.3rem; }
            .section-title { font-size: 1.8rem; }
            .hero-stats { flex-direction: column; }
        }
        @media (max-width: 576px) {
            .hero-title { font-size: 1.8rem; }
            .hero-section { padding: 80px 0 60px; min-height: auto; }
            .section { padding: 50px 0; }
            .whatsapp-float { width: 50px; height: 50px; font-size: 25px; bottom: 15px; right: 15px; }
            .back-to-top { width: 40px; height: 40px; bottom: 75px; right: 15px; }
        }
    </style>
</head>
<body>
    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar navbar-expand-lg" id="navbar">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <span class="brand-icon">🚗</span> <?php echo SITE_NAME; ?>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
                    <li class="nav-item"><a class="nav-link active" href="index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="cars.php">السيارات</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">من نحن</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">اتصل بنا</a></li>
                </ul>
                <div class="d-flex gap-2">
    <?php if (is_logged_in()): ?>
        <a href="customer/my-bookings.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-list-check me-1"></i> طلباتي
        </a>
        <?php if (is_admin()): ?>
            <a href="admin/index.php" class="btn btn-primary btn-sm">
                <i class="fas fa-tachometer-alt me-1"></i> لوحة التحكم
            </a>
        <?php else: ?>
            <a href="customer/dashboard.php" class="btn btn-primary btn-sm">
                <i class="fas fa-user me-1"></i> حسابي
            </a>
        <?php endif; ?>
        <a href="logout.php" class="btn btn-sm text-danger" style="background:#fee2e2;">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    <?php else: ?>
        <a href="login.php" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sign-in-alt me-1"></i> تسجيل الدخول
        </a>
        <a href="register.php" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus me-1"></i> إنشاء حساب
        </a>
    <?php endif; ?>
</div>
            </div>
        </div>
    </nav>
    
    <!-- ==================== HERO SECTION ==================== -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-5 mb-lg-0" data-aos="fade-up">
                    <span class="hero-badge"><i class="fas fa-star text-warning me-1"></i> أسرع خدمة تأجير سيارات في المغرب</span>
                    <h1 class="hero-title">استأجر سيارتك <span class="highlight">الفاخرة</span> بكل سهولة</h1>
                    <p class="hero-description">أفضل الأسعار، أحدث السيارات، خدمة 24/7. احجز سيارتك الآن واستمتع بتجربة لا تُنسى</p>
                    
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-icon bg-primary bg-opacity-50"><i class="fas fa-car"></i></div>
                            <div><div class="hero-stat-value">+50</div><div class="hero-stat-label">سيارة متوفرة</div></div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon bg-success bg-opacity-50"><i class="fas fa-smile"></i></div>
                            <div><div class="hero-stat-value">+2000</div><div class="hero-stat-label">عميل سعيد</div></div>
                        </div>
                    </div>
                    
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        <a href="cars.php" class="btn btn-light btn-lg btn-rounded"><i class="fas fa-car me-2"></i> تصفح السيارات</a>
                        <a href="https://wa.me/212600000000" class="btn btn-outline-light btn-lg btn-rounded" target="_blank"><i class="fab fa-whatsapp me-2"></i> واتساب</a>
                    </div>
                </div>
                
                <div class="col-lg-5" data-aos="fade-left" data-aos-delay="200">
                    <div class="booking-widget">
                        <div class="booking-widget-header text-white">
                            <h4><i class="fas fa-calendar-check me-2"></i> احجز سيارتك الآن</h4>
                        </div>
                        <div class="booking-widget-body">
                            <form action="cars.php" method="GET">
                                <div class="mb-3">
                                    <label class="form-label">مكان الاستلام</label>
                                    <select class="form-select" name="pickup_location" required>
                                        <option value="">اختر الموقع...</option>
                                        <?php foreach ($locations as $loc): ?>
                                        <option value="<?php echo $loc['id']; ?>"><?php echo htmlspecialchars($loc['city'] . ' - ' . $loc['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <label class="form-label">تاريخ الاستلام</label>
                                        <input type="text" class="form-control datepicker" name="pickup_date" placeholder="اختر التاريخ" required readonly>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">تاريخ التسليم</label>
                                        <input type="text" class="form-control datepicker" name="return_date" placeholder="اختر التاريخ" required readonly>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold"><i class="fas fa-search me-2"></i> ابحث عن سيارتك</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="wave-separator">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100" preserveAspectRatio="none"><path fill="#f5f6fa" d="M0,64L80,69C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,59L1440,64L1440,100L0,100Z"></path></svg>
        </div>
    </section>
    
    <!-- ==================== FEATURES ==================== -->
    <section class="section" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">لماذا تختارنا</span>
                <h2 class="section-title">خدماتنا المميزة</h2>
                <p class="section-description">نقدم أفضل الخدمات لتجربة تأجير سيارات لا تُنسى</p>
            </div>
            <div class="row g-4">
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-bolt"></i></div>
                        <h5>حجز سريع وفوري</h5><p>احجز سيارتك في أقل من دقيقتين مع تأكيد فوري</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon bg-success bg-opacity-10 text-success"><i class="fas fa-tag"></i></div>
                        <h5>أفضل الأسعار</h5><p>أسعار تنافسية مضمونة مع خصومات للحجوزات الطويلة</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-headset"></i></div>
                        <h5>دعم 24/7</h5><p>فريق دعم متاح على مدار الساعة لمساعدتك</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <div class="feature-card">
                        <div class="feature-icon bg-info bg-opacity-10 text-info"><i class="fas fa-shield-alt"></i></div>
                        <h5>تأمين شامل</h5><p>جميع سياراتنا مؤمنة تأميناً شاملاً لراحتك</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ==================== FEATURED CARS ==================== -->
    <section class="section section-light" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">سياراتنا المميزة</span>
                <h2 class="section-title">أحدث السيارات المتوفرة</h2>
                <p class="section-description">اختر من بين مجموعتنا الواسعة من السيارات الفاخرة والاقتصادية</p>
            </div>
            
            <?php if (!empty($featured_cars)): ?>
            <div class="row g-4">
                <?php foreach ($featured_cars as $car): $img = car_image($car['main_image'] ?? ''); ?>
                <div class="col-lg-3 col-md-6">
                    <div class="car-card">
                        <div class="car-card-image">
                            <img src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($car['brand']); ?>" loading="lazy" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=600&h=400&fit=crop'">
                            <?php if ($car['popular']): ?><span class="car-card-badge"><i class="fas fa-fire"></i> الأكثر طلباً</span><?php endif; ?>
                            <div class="car-card-overlay">
                                <a href="car-details.php?id=<?php echo $car['id']; ?>" class="btn btn-sm btn-light">تفاصيل</a>
                                <a href="booking.php?car_id=<?php echo $car['id']; ?>" class="btn btn-sm btn-primary">احجز</a>
                            </div>
                        </div>
                        <div class="car-card-body">
                            <h5><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h5>
                            <p class="year"><?php echo $car['year']; ?></p>
                            <div class="car-specs">
                                <span class="car-spec"><i class="fas fa-cog"></i> <?php echo $car['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي'; ?></span>
                                <span class="car-spec"><i class="fas fa-gas-pump"></i> <?php echo $car['fuel_type'] == 'diesel' ? 'ديزل' : 'بنزين'; ?></span>
                                <span class="car-spec"><i class="fas fa-user"></i> <?php echo $car['seats']; ?> مقاعد</span>
                            </div>
                            <div class="car-price-row">
                                <div><span class="car-price"><?php echo number_format($car['daily_rate'], 2); ?> DH</span><span class="car-price-unit"> / يوم</span></div>
                                <a href="booking.php?car_id=<?php echo $car['id']; ?>" class="btn btn-primary btn-sm">احجز</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5">
                <a href="cars.php" class="btn btn-outline-primary btn-lg">عرض جميع السيارات <i class="fas fa-arrow-left ms-2"></i></a>
            </div>
            <?php else: ?>
            <div class="text-center py-5"><i class="fas fa-car fa-4x text-muted mb-3"></i><h4>لا توجد سيارات متوفرة</h4></div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- ==================== TESTIMONIALS ==================== -->
    <?php if (!empty($reviews)): ?>
    <section class="section" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">آراء العملاء</span>
                <h2 class="section-title">ماذا يقول عملاؤنا</h2>
            </div>
            <div class="swiper reviewSlider">
                <div class="swiper-wrapper">
                    <?php foreach ($reviews as $r): ?>
                    <div class="swiper-slide">
                        <div class="testimonial-card">
                            <div class="testimonial-stars"><?php for($i=1;$i<=5;$i++): ?><i class="fas fa-star <?php echo $i<=$r['rating']?'':'text-muted'; ?>"></i><?php endfor; ?></div>
                            <p class="testimonial-text">"<?php echo htmlspecialchars($r['comment']); ?>"</p>
                            <div class="testimonial-author">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($r['full_name']); ?>&size=45&background=random&bold=true" alt="">
                                <h6><?php echo htmlspecialchars($r['full_name']); ?></h6>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination mt-4"></div>
            </div>
        </div>
    </section>
    <?php endif; ?>
    
    <!-- ==================== CTA ==================== -->
    <section class="cta-section" data-aos="fade-up">
        <div class="container">
            <h2 class="fw-bold mb-3">هل أنت مستعد لاستئجار سيارتك المثالية؟</h2>
            <p class="lead mb-4 opacity-90">احجز الآن واستمتع بخدمة استثنائية وأسعار لا تقبل المنافسة</p>
            <a href="cars.php" class="btn btn-light btn-lg"><i class="fas fa-car me-2"></i> تصفح السيارات</a>
            <a href="tel:+212600000000" class="btn btn-outline-light btn-lg ms-3"><i class="fas fa-phone me-2"></i> اتصل بنا</a>
        </div>
    </section>
    
    <!-- ==================== FOOTER ==================== -->
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="brand-icon" style="width:42px;height:42px;background:var(--gradient);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;">🚗</div>
                        <h5 class="mb-0"><?php echo SITE_NAME; ?></h5>
                    </div>
                    <p>شركة رائدة في مجال تأجير السيارات الفاخرة في المغرب. نقدم خدمة متميزة وأسعار تنافسية.</p>
                    <div class="footer-social"><a href="#"><i class="fab fa-facebook-f"></i></a><a href="#"><i class="fab fa-instagram"></i></a><a href="#"><i class="fab fa-whatsapp"></i></a><a href="#"><i class="fab fa-youtube"></i></a></div>
                </div>
                <div class="col-lg-2 col-md-4">
                    <h5>روابط سريعة</h5>
                    <ul class="list-unstyled"><li><a href="index.php">الرئيسية</a></li><li><a href="cars.php">السيارات</a></li><li><a href="about.php">من نحن</a></li><li><a href="contact.php">اتصل بنا</a></li></ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>اتصل بنا</h5>
                    <ul class="list-unstyled"><li><i class="fas fa-map-marker-alt text-primary ms-2"></i> شارع محمد الخامس، مراكش</li><li><i class="fas fa-phone text-primary ms-2"></i> +212 600-000000</li><li><i class="fas fa-envelope text-primary ms-2"></i> contact@carrental.ma</li></ul>
                </div>
                <div class="col-lg-3 col-md-4">
                    <h5>النشرة البريدية</h5>
                    <form onsubmit="event.preventDefault();alert('تم الاشتراك!');"><div class="input-group"><input type="email" class="form-control" placeholder="بريدك الإلكتروني"><button class="btn btn-primary"><i class="fas fa-paper-plane"></i></button></div></form>
                </div>
            </div>
            <div class="footer-bottom"><p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة.</p></div>
        </div>
    </footer>
    
    <!-- Floating Buttons -->
    <a href="https://wa.me/212600000000" class="whatsapp-float" target="_blank" title="واتساب"><i class="fab fa-whatsapp"></i></a>
    <button class="back-to-top" id="backToTop" title="العودة للأعلى"><i class="fas fa-chevron-up"></i></button>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    
    <script>
        // AOS
        AOS.init({ duration: 800, easing: 'ease-in-out', once: true, offset: 30 });
        
        // Swiper
        const swiper = document.querySelector('.reviewSlider');
        if (swiper) {
            new Swiper('.reviewSlider', {
                slidesPerView: 1, spaceBetween: 24, loop: true,
                autoplay: { delay: 4000, disableOnInteraction: false },
                pagination: { el: '.swiper-pagination', clickable: true },
                breakpoints: { 768: { slidesPerView: 2 }, 992: { slidesPerView: 3 } }
            });
        }
        
        // Datepickers
        flatpickr(".datepicker", { locale: "ar", dateFormat: "Y-m-d", minDate: "today", disableMobile: true });
        
        // Back to top
        const btn = document.getElementById('backToTop');
        window.addEventListener('scroll', () => { btn.classList.toggle('show', window.scrollY > 500); });
        btn.addEventListener('click', () => { window.scrollTo({ top: 0, behavior: 'smooth' }); });
        
        // Navbar scroll
        window.addEventListener('scroll', () => { document.getElementById('navbar').classList.toggle('scrolled', window.scrollY > 50); });
    </script>
</body>
</html>