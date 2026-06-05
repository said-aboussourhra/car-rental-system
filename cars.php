<?php
/**
 * Premium Car Rental - Cars Listing Page
 * Complete Working Version with Direct Image URLs
 */
require_once 'includes/config.php';

// ============================================
// GET FILTER VALUES
// ============================================
$search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$brand = isset($_GET['brand']) ? clean_input($_GET['brand']) : '';
$fuel_type = isset($_GET['fuel_type']) ? clean_input($_GET['fuel_type']) : '';
$transmission = isset($_GET['transmission']) ? clean_input($_GET['transmission']) : '';
$seats = isset($_GET['seats']) ? intval($_GET['seats']) : 0;
$year_from = isset($_GET['year_from']) ? intval($_GET['year_from']) : 0;
$year_to = isset($_GET['year_to']) ? intval($_GET['year_to']) : 0;
$price_min = isset($_GET['price_min']) ? floatval($_GET['price_min']) : 0;
$price_max = isset($_GET['price_max']) ? floatval($_GET['price_max']) : 0;
$sort = isset($_GET['sort']) ? clean_input($_GET['sort']) : 'popular';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// ============================================
// BUILD QUERY
// ============================================
$conditions = ["c.status = 'available'"];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.brand LIKE :s1 OR c.model LIKE :s2)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
}

if (!empty($brand)) {
    $conditions[] = "c.brand = :brand";
    $params[':brand'] = $brand;
}

if (!empty($fuel_type)) {
    $conditions[] = "c.fuel_type = :fuel";
    $params[':fuel'] = $fuel_type;
}

if (!empty($transmission)) {
    $conditions[] = "c.transmission = :trans";
    $params[':trans'] = $transmission;
}

if ($seats > 0) {
    if ($seats >= 7) {
        $conditions[] = "c.seats >= :seats";
    } else {
        $conditions[] = "c.seats = :seats";
    }
    $params[':seats'] = $seats;
}

if ($year_from > 0) {
    $conditions[] = "c.year >= :yf";
    $params[':yf'] = $year_from;
}

if ($year_to > 0) {
    $conditions[] = "c.year <= :yt";
    $params[':yt'] = $year_to;
}

if ($price_min > 0) {
    $conditions[] = "c.daily_rate >= :pmin";
    $params[':pmin'] = $price_min;
}

if ($price_max > 0) {
    $conditions[] = "c.daily_rate <= :pmax";
    $params[':pmax'] = $price_max;
}

$whereSQL = implode(' AND ', $conditions);

// ============================================
// SORTING
// ============================================
$sortOptions = [
    'popular'    => 'c.popular DESC, c.created_at DESC',
    'price_asc'  => 'c.daily_rate ASC',
    'price_desc' => 'c.daily_rate DESC',
    'year_desc'  => 'c.year DESC',
    'year_asc'   => 'c.year ASC',
    'name_asc'   => 'c.brand ASC, c.model ASC',
    'name_desc'  => 'c.brand DESC, c.model DESC',
    'newest'     => 'c.created_at DESC'
];

if (!array_key_exists($sort, $sortOptions)) {
    $sort = 'popular';
}
$orderBy = $sortOptions[$sort];

// ============================================
// PAGINATION
// ============================================
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c WHERE $whereSQL");
    $countStmt->execute($params);
    $totalCars = $countStmt->fetchColumn();
    $totalPages = ceil($totalCars / $perPage);
} catch (Exception $e) {
    $totalCars = 0;
    $totalPages = 0;
}

// ============================================
// FETCH CARS
// ============================================
try {
    $sql = "SELECT c.*, 
                   (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image,
                   (SELECT AVG(rating) FROM reviews WHERE car_id = c.id AND status = 'approved') as avg_rating,
                   (SELECT COUNT(*) FROM reviews WHERE car_id = c.id AND status = 'approved') as review_count
            FROM cars c 
            WHERE $whereSQL 
            ORDER BY $orderBy 
            LIMIT $perPage OFFSET $offset";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cars = $stmt->fetchAll();
} catch (Exception $e) {
    $cars = [];
}

// ============================================
// GET BRANDS FOR FILTER
// ============================================
try {
    $brands = $pdo->query("SELECT DISTINCT brand FROM cars WHERE status = 'available' ORDER BY brand ASC")->fetchAll();
} catch (Exception $e) {
    $brands = [];
}

// ============================================
// IMAGE URL HELPER
// ============================================
function get_car_image($imagePath) {
    if (empty($imagePath)) {
        return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=500&h=350&fit=crop';
    }
    if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
        return $imagePath;
    }
    return 'uploads/cars/' . $imagePath;
}

// ============================================
// BUILD URL HELPERS
// ============================================
function buildPageUrl($pageNum) {
    $get = $_GET;
    $get['page'] = $pageNum;
    return 'cars.php?' . http_build_query($get);
}

function buildSortUrl($sortValue) {
    $get = $_GET;
    $get['sort'] = $sortValue;
    unset($get['page']);
    return 'cars.php?' . http_build_query($get);
}

// ============================================
// PAGE TITLE
// ============================================
$page_title = 'السيارات المتوفرة للإيجار | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Bootstrap RTL -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #5a6fd6;
            --primary-light: #e8eaff;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --light: #f5f6fa;
            --white: #ffffff;
            --text: #333333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --shadow-sm: 0 2px 10px rgba(0,0,0,0.05);
            --shadow: 0 10px 30px rgba(0,0,0,0.08);
            --shadow-lg: 0 15px 40px rgba(0,0,0,0.12);
            --radius: 12px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--light);
            color: var(--text);
            line-height: 1.7;
        }
        
        /* ========== NAVBAR ========== */
        .navbar {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            box-shadow: var(--shadow-sm);
            padding: 12px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--dark) !important;
            text-decoration: none;
        }
        
        .navbar-brand i { color: var(--primary); font-size: 1.6rem; }
        
        .nav-link {
            font-weight: 600;
            color: var(--text) !important;
            padding: 8px 16px !important;
            border-radius: 8px;
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
            padding: 10px 20px;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--gradient);
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary);
            color: var(--primary);
            background: white;
        }
        
        .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-sm { padding: 6px 14px; font-size: 0.85rem; }
        
        /* ========== PAGE HEADER ========== */
        .page-header {
            background: linear-gradient(135deg, rgba(26,26,46,0.92) 0%, rgba(26,26,46,0.75) 100%),
                        url('https://images.unsplash.com/photo-1580273916550-e323be2ae537?w=1920&q=80') center/cover no-repeat;
            color: white;
            padding: 50px 0 40px;
            text-align: center;
        }
        
        .page-header h1 {
            font-weight: 900;
            font-size: 2.2rem;
            margin-bottom: 8px;
        }
        
        .breadcrumb {
            justify-content: center;
            margin: 10px 0 0 0;
            padding: 0;
            list-style: none;
            display: flex;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .breadcrumb-item a { color: rgba(255,255,255,0.7); text-decoration: none; }
        .breadcrumb-item a:hover { color: white; }
        .breadcrumb-item.active { color: white; }
        
        /* ========== MAIN SECTION ========== */
        .cars-section { padding: 35px 0 60px; }
        
        /* ========== FILTER SIDEBAR ========== */
        .filter-sidebar {
            background: white;
            border-radius: var(--radius-lg);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 90px;
        }
        
        .filter-title {
            font-weight: 800;
            font-size: 1.1rem;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--dark);
        }
        
        .filter-group {
            margin-bottom: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .filter-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .filter-group label {
            font-weight: 700;
            font-size: 0.88rem;
            margin-bottom: 8px;
            display: block;
            color: var(--dark);
        }
        
        .form-select, .form-control {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 10px 12px;
            font-size: 0.9rem;
            transition: var(--transition);
            font-family: 'Cairo', sans-serif;
            background: var(--light);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            outline: none;
            background: white;
        }
        
        /* ========== RESULTS HEADER ========== */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .results-count {
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .results-count strong {
            color: var(--primary);
            font-size: 1.1rem;
        }
        
        /* ========== CAR CARD ========== */
        .car-card {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            height: 100%;
            display: flex;
            flex-direction: column;
            border: 1px solid #f0f0f0;
        }
        
        .car-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--shadow-lg);
            border-color: transparent;
        }
        
        .car-card-image {
            position: relative;
            height: 210px;
            overflow: hidden;
            background: #e0e0e0;
        }
        
        .car-card-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .car-card:hover .car-card-image img { transform: scale(1.08); }
        
        .car-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 0.73rem;
            font-weight: 700;
            color: white;
            z-index: 2;
        }
        
        .car-badge.popular { background: #ef4444; }
        .car-badge.eco { background: #10b981; top: 44px; }
        .car-badge.new { background: #3b82f6; }
        
        .car-card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            opacity: 0;
            transition: var(--transition);
        }
        
        .car-card:hover .car-card-overlay { opacity: 1; }
        
        .car-card-body {
            padding: 18px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .car-rating {
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 8px;
        }
        
        .car-rating .stars { color: #f59e0b; font-size: 0.75rem; }
        .car-rating .rating-val { font-weight: 700; font-size: 0.85rem; color: var(--dark); }
        .car-rating .rating-count { font-size: 0.75rem; color: var(--text-light); }
        
        .car-title {
            font-weight: 800;
            font-size: 1rem;
            margin-bottom: 3px;
            line-height: 1.3;
        }
        
        .car-title a {
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .car-title a:hover { color: var(--primary); }
        
        .car-year {
            color: var(--text-light);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .car-specs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 14px;
        }
        
        .car-spec {
            display: flex;
            align-items: center;
            gap: 4px;
            background: var(--light);
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 0.76rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .car-spec i { color: var(--primary); font-size: 0.7rem; }
        
        .car-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-top: 14px;
            border-top: 1px solid #f0f0f0;
        }
        
        .car-price {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--primary);
            line-height: 1;
        }
        
        .car-price-period {
            font-size: 0.73rem;
            color: var(--text-light);
            display: block;
        }
        
        /* ========== PAGINATION ========== */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 40px;
            list-style: none;
            padding: 0;
            flex-wrap: wrap;
        }
        
        .page-item .page-link {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border);
            background: white;
            color: var(--text);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .page-item .page-link:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--primary-light);
        }
        
        .page-item.active .page-link {
            background: var(--gradient);
            color: white;
            border-color: transparent;
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        .page-item.disabled .page-link {
            opacity: 0.4;
            pointer-events: none;
            cursor: not-allowed;
        }
        
        /* ========== NO RESULTS ========== */
        .no-results {
            text-align: center;
            padding: 70px 20px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .no-results i {
            font-size: 5rem;
            color: #ddd;
            margin-bottom: 20px;
            display: block;
        }
        
        /* ========== FOOTER ========== */
        .footer {
            background: var(--dark);
            color: white;
            padding: 35px 0;
            text-align: center;
        }
        
        .footer a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            margin: 0 10px;
        }
        
        .footer a:hover { color: white; }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 991px) {
            .filter-sidebar {
                position: static;
                margin-bottom: 25px;
            }
            .page-header h1 { font-size: 1.8rem; }
        }
        
        @media (max-width: 768px) {
            .page-header h1 { font-size: 1.5rem; }
            .car-card-image { height: 180px; }
            .results-header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <!-- ==================== NAVBAR ==================== -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
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
    </nav>
    
    <!-- ==================== PAGE HEADER ==================== -->
    <section class="page-header">
        <div class="container">
            <h1><i class="fas fa-car me-2"></i> سياراتنا المتوفرة</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">الرئيسية</a></li>
                    <li class="breadcrumb-item active">السيارات</li>
                </ol>
            </nav>
        </div>
    </section>
    
    <!-- ==================== MAIN CONTENT ==================== -->
    <section class="cars-section">
        <div class="container">
            <div class="row">
                <!-- ==================== FILTER SIDEBAR ==================== -->
                <div class="col-lg-3 mb-4">
                    <form method="GET" action="cars.php" id="filterForm">
                        <div class="filter-sidebar">
                            <div class="filter-title">
                                <i class="fas fa-sliders-h text-primary"></i> تصفية النتائج
                            </div>
                            
                            <!-- Search -->
                            <div class="filter-group">
                                <label><i class="fas fa-search text-primary"></i> بحث سريع</label>
                                <input type="text" class="form-control" name="search" 
                                       placeholder="ابحث عن سيارة..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <!-- Brand -->
                            <div class="filter-group">
                                <label><i class="fas fa-car text-primary"></i> الماركة</label>
                                <select class="form-select" name="brand" onchange="this.form.submit()">
                                    <option value="">جميع الماركات</option>
                                    <?php foreach ($brands as $b): ?>
                                    <option value="<?php echo htmlspecialchars($b['brand']); ?>"
                                            <?php echo $brand === $b['brand'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['brand']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Fuel Type -->
                            <div class="filter-group">
                                <label><i class="fas fa-gas-pump text-primary"></i> نوع الوقود</label>
                                <select class="form-select" name="fuel_type" onchange="this.form.submit()">
                                    <option value="">الكل</option>
                                    <option value="petrol" <?php echo $fuel_type === 'petrol' ? 'selected' : ''; ?>>⛽ بنزين</option>
                                    <option value="diesel" <?php echo $fuel_type === 'diesel' ? 'selected' : ''; ?>>🛢️ ديزل</option>
                                    <option value="electric" <?php echo $fuel_type === 'electric' ? 'selected' : ''; ?>>⚡ كهرباء</option>
                                    <option value="hybrid" <?php echo $fuel_type === 'hybrid' ? 'selected' : ''; ?>>🔋 هايبرد</option>
                                </select>
                            </div>
                            
                            <!-- Transmission -->
                            <div class="filter-group">
                                <label><i class="fas fa-cog text-primary"></i> ناقل الحركة</label>
                                <select class="form-select" name="transmission" onchange="this.form.submit()">
                                    <option value="">الكل</option>
                                    <option value="automatic" <?php echo $transmission === 'automatic' ? 'selected' : ''; ?>>أوتوماتيك</option>
                                    <option value="manual" <?php echo $transmission === 'manual' ? 'selected' : ''; ?>>يدوي</option>
                                </select>
                            </div>
                            
                            <!-- Seats -->
                            <div class="filter-group">
                                <label><i class="fas fa-chair text-primary"></i> عدد المقاعد</label>
                                <select class="form-select" name="seats" onchange="this.form.submit()">
                                    <option value="">الكل</option>
                                    <option value="2" <?php echo $seats === 2 ? 'selected' : ''; ?>>2 مقاعد</option>
                                    <option value="4" <?php echo $seats === 4 ? 'selected' : ''; ?>>4 مقاعد</option>
                                    <option value="5" <?php echo $seats === 5 ? 'selected' : ''; ?>>5 مقاعد</option>
                                    <option value="7" <?php echo $seats === 7 ? 'selected' : ''; ?>>7 مقاعد فأكثر</option>
                                </select>
                            </div>
                            
                            <!-- Year -->
                            <div class="filter-group">
                                <label><i class="fas fa-calendar text-primary"></i> سنة الصنع</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <select class="form-select form-select-sm" name="year_from" onchange="this.form.submit()">
                                            <option value="">من</option>
                                            <?php for ($y = date('Y'); $y >= 2015; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $year_from === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <select class="form-select form-select-sm" name="year_to" onchange="this.form.submit()">
                                            <option value="">إلى</option>
                                            <?php for ($y = date('Y'); $y >= 2015; $y--): ?>
                                            <option value="<?php echo $y; ?>" <?php echo $year_to === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Price Range -->
                            <div class="filter-group">
                                <label><i class="fas fa-tag text-primary"></i> السعر لليوم (DH)</label>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm" name="price_min" 
                                               placeholder="من" value="<?php echo $price_min > 0 ? $price_min : ''; ?>">
                                    </div>
                                    <div class="col-6">
                                        <input type="number" class="form-control form-control-sm" name="price_max" 
                                               placeholder="إلى" value="<?php echo $price_max > 0 ? $price_max : ''; ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-sm btn-outline-primary w-100 mt-2">تطبيق</button>
                            </div>
                            
                            <!-- Reset -->
                            <a href="cars.php" class="btn btn-outline-danger btn-sm w-100">
                                <i class="fas fa-redo me-1"></i> إعادة تعيين
                            </a>
                            
                            <!-- Search Button -->
                            <button type="submit" class="btn btn-primary btn-sm w-100 mt-2">
                                <i class="fas fa-search me-1"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- ==================== CARS GRID ==================== -->
                <div class="col-lg-9">
                    <!-- Results Header -->
                    <div class="results-header">
                        <div class="results-count">
                            تم العثور على <strong><?php echo number_format($totalCars); ?></strong> سيارة
                        </div>
                        
                        <select class="form-select" style="width: auto; min-width: 200px;" onchange="window.location.href=this.value">
                            <option value="<?php echo buildSortUrl('popular'); ?>" <?php echo $sort === 'popular' ? 'selected' : ''; ?>>🔥 الأكثر طلباً</option>
                            <option value="<?php echo buildSortUrl('price_asc'); ?>" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>💰 السعر: من الأقل إلى الأعلى</option>
                            <option value="<?php echo buildSortUrl('price_desc'); ?>" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>💰 السعر: من الأعلى إلى الأقل</option>
                            <option value="<?php echo buildSortUrl('year_desc'); ?>" <?php echo $sort === 'year_desc' ? 'selected' : ''; ?>>📅 الأحدث</option>
                            <option value="<?php echo buildSortUrl('newest'); ?>" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>🆕 المضافة حديثاً</option>
                            <option value="<?php echo buildSortUrl('name_asc'); ?>" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>🔤 الاسم: أ-ي</option>
                        </select>
                    </div>
                    
                    <!-- Cars Grid -->
                    <?php if (empty($cars)): ?>
                    <div class="no-results">
                        <i class="fas fa-search"></i>
                        <h4>لا توجد سيارات متطابقة</h4>
                        <p class="text-muted mb-3">جرب تغيير معايير البحث أو الفلترة</p>
                        <a href="cars.php" class="btn btn-primary">إعادة تعيين الفلترة</a>
                    </div>
                    <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($cars as $car): 
                            $carImage = get_car_image($car['main_image'] ?? '');
                        ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="car-card">
                                <div class="car-card-image">
                                    <img src="<?php echo $carImage; ?>" 
                                         alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>"
                                         loading="lazy"
                                         onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=500&h=350&fit=crop'">
                                    
                                    <?php if ($car['popular']): ?>
                                    <span class="car-badge popular"><i class="fas fa-fire"></i> الأكثر طلباً</span>
                                    <?php endif; ?>
                                    
                                    <?php if ($car['fuel_type'] === 'electric' || $car['fuel_type'] === 'hybrid'): ?>
                                    <span class="car-badge eco"><i class="fas fa-leaf"></i> صديق للبيئة</span>
                                    <?php endif; ?>
                                    
                                    <div class="car-card-overlay">
                                        <a href="car-details.php?id=<?php echo $car['id']; ?>" class="btn btn-light btn-sm">
                                            <i class="fas fa-eye"></i> تفاصيل
                                        </a>
                                        <a href="booking.php?car_id=<?php echo $car['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-calendar-check"></i> احجز
                                        </a>
                                    </div>
                                </div>
                                
                                <div class="car-card-body">
                                    <?php if ($car['avg_rating']): ?>
                                    <div class="car-rating">
                                        <div class="stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= round($car['avg_rating']) ? '' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="rating-val"><?php echo number_format($car['avg_rating'], 1); ?></span>
                                        <span class="rating-count">(<?php echo $car['review_count']; ?>)</span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <h3 class="car-title">
                                        <a href="car-details.php?id=<?php echo $car['id']; ?>">
                                            <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                        </a>
                                    </h3>
                                    <p class="car-year"><?php echo $car['year']; ?></p>
                                    
                                    <div class="car-specs">
                                        <span class="car-spec">
                                            <i class="fas fa-cog"></i> <?php echo $car['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي'; ?>
                                        </span>
                                        <span class="car-spec">
                                            <i class="fas fa-gas-pump"></i> 
                                            <?php 
                                                $fuelLabels = ['petrol' => 'بنزين', 'diesel' => 'ديزل', 'electric' => 'كهرباء', 'hybrid' => 'هايبرد'];
                                                echo $fuelLabels[$car['fuel_type']] ?? $car['fuel_type']; 
                                            ?>
                                        </span>
                                        <span class="car-spec">
                                            <i class="fas fa-user"></i> <?php echo $car['seats']; ?> مقاعد
                                        </span>
                                    </div>
                                    
                                    <div class="car-footer">
                                        <div>
                                            <span class="car-price"><?php echo number_format($car['daily_rate'], 2); ?> DH</span>
                                            <span class="car-price-period">لليوم الواحد</span>
                                        </div>
                                        <a href="booking.php?car_id=<?php echo $car['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-calendar-check me-1"></i> احجز
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <!-- Previous -->
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page > 1 ? buildPageUrl($page - 1) : '#'; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                            
                            <!-- First Page -->
                            <?php if ($page > 2): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPageUrl(1); ?>">1</a>
                            </li>
                            <?php if ($page > 3): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <!-- Page Numbers -->
                            <?php 
                            $start = max(1, $page - 1);
                            $end = min($totalPages, $page + 1);
                            for ($i = $start; $i <= $end; $i++): 
                            ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo buildPageUrl($i); ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <!-- Last Page -->
                            <?php if ($page < $totalPages - 1): ?>
                            <?php if ($page < $totalPages - 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo buildPageUrl($totalPages); ?>"><?php echo $totalPages; ?></a>
                            </li>
                            <?php endif; ?>
                            
                            <!-- Next -->
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="<?php echo $page < $totalPages ? buildPageUrl($page + 1) : '#'; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    
    <!-- ==================== FOOTER ==================== -->
    <footer class="footer">
        <div class="container">
            <a href="index.php">الرئيسية</a>
            <a href="cars.php">السيارات</a>
            <a href="about.php">من نحن</a>
            <a href="contact.php">اتصل بنا</a>
            <a href="terms.php">الشروط والأحكام</a>
            <p class="mb-0 mt-3" style="opacity:0.7;">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. جميع الحقوق محفوظة.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>