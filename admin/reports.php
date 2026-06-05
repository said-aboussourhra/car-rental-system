<?php
/**
 * Premium Car Rental - Admin Reports & Analytics
 * Ultimate Reports Dashboard
 */
require_once '../includes/config.php';

// Check admin access
if (!is_admin()) {
    header('Location: ../login.php');
    exit();
}

// ============================================
// FETCH REPORT DATA
// ============================================

// Revenue by month (current year)
try {
    $monthly_revenue = $pdo->query("
        SELECT 
            MONTH(created_at) as month,
            COUNT(*) as total_bookings,
            COALESCE(SUM(total_amount), 0) as revenue,
            COUNT(CASE WHEN booking_status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN booking_status = 'cancelled' THEN 1 END) as cancelled
        FROM bookings 
        WHERE YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY MONTH(created_at)
        ORDER BY month
    ")->fetchAll();
} catch (Exception $e) {
    $monthly_revenue = [];
}

// Revenue by car brand
try {
    $revenue_by_brand = $pdo->query("
        SELECT c.brand, COUNT(b.id) as bookings, COALESCE(SUM(b.total_amount), 0) as revenue
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        WHERE b.booking_status != 'cancelled'
        GROUP BY c.brand 
        ORDER BY revenue DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $revenue_by_brand = [];
}

// Top customers
try {
    $top_customers = $pdo->query("
        SELECT u.full_name, u.email, u.avatar,
               COUNT(b.id) as total_bookings,
               COALESCE(SUM(b.total_amount), 0) as total_spent
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        WHERE b.booking_status = 'completed'
        GROUP BY u.id 
        ORDER BY total_spent DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $top_customers = [];
}

// Top cars
try {
    $top_cars = $pdo->query("
        SELECT c.brand, c.model, c.year,
               COUNT(b.id) as booking_count,
               COALESCE(SUM(b.total_amount), 0) as revenue,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        WHERE b.booking_status = 'completed'
        GROUP BY c.id 
        ORDER BY booking_count DESC 
        LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $top_cars = [];
}

// Daily stats for last 30 days
try {
    $daily_stats = $pdo->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as bookings,
            COALESCE(SUM(total_amount), 0) as revenue
        FROM bookings 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
    ")->fetchAll();
} catch (Exception $e) {
    $daily_stats = [];
}

// Booking status distribution
try {
    $status_distribution = $pdo->query("
        SELECT booking_status, COUNT(*) as count 
        FROM bookings 
        GROUP BY booking_status
    ")->fetchAll();
} catch (Exception $e) {
    $status_distribution = [];
}

// Summary stats
try {
    $summary = [
        'total_revenue' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn(),
        'total_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'completed_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed'")->fetchColumn(),
        'cancelled_bookings' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'cancelled'")->fetchColumn(),
        'total_customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
        'total_cars' => $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn(),
        'avg_booking_value' => $pdo->query("SELECT COALESCE(AVG(total_amount), 0) FROM bookings WHERE booking_status = 'completed'")->fetchColumn(),
        'occupancy_rate' => 0,
    ];
    
    $rented = $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'rented'")->fetchColumn();
    $total = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
    $summary['occupancy_rate'] = $total > 0 ? round(($rented / $total) * 100) : 0;
    
} catch (Exception $e) {
    $summary = ['total_revenue' => 0, 'total_bookings' => 0, 'completed_bookings' => 0, 'cancelled_bookings' => 0, 'total_customers' => 0, 'total_cars' => 0, 'avg_booking_value' => 0, 'occupancy_rate' => 0];
}

// Arabic months
$arabic_months = [
    1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
    5 => 'مايو', 6 => 'يونيو', 7 => 'يوليوز', 8 => 'غشت',
    9 => 'شتنبر', 10 => 'أكتوبر', 11 => 'نونبر', 12 => 'دجنبر'
];

function get_car_thumb($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=50&h=38&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}

$page_title = 'التقارير والإحصائيات | لوحة التحكم';
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            --info: #3b82f6;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
            --radius: 14px;
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f0f2f5;
            color: var(--text);
        }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        .admin-sidebar {
            width: 250px;
            background: var(--dark);
            color: white;
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 100;
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            color: white;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.2rem;
        }
        
        .sidebar-brand i { color: var(--primary); }
        
        .sidebar-menu { padding: 10px 0; }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            margin: 2px 10px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu a.active { background: var(--gradient); color: white; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        
        .admin-main { flex: 1; margin-right: 250px; }
        
        .admin-topbar {
            background: white;
            padding: 15px 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .topbar-toggle { display: none; background: none; border: none; font-size: 1.5rem; cursor: pointer; }
        .admin-content { padding: 25px; }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }
        .stat-card .stat-value { font-size: 1.6rem; font-weight: 900; color: var(--dark); }
        .stat-card .stat-label { font-size: 0.82rem; color: var(--text-light); }
        
        .chart-card {
            background: white;
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
        }
        
        .chart-card h5 {
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-card h5 i { color: var(--primary); }
        
        .chart-container {
            position: relative;
            width: 100%;
        }
        
        .chart-container canvas {
            max-height: 350px;
        }
        
        .table-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .table-card h5 {
            font-weight: 800;
            padding: 18px 22px;
            margin: 0;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table { margin: 0; }
        .table th {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-light);
            padding: 12px 16px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        .table td { padding: 10px 16px; vertical-align: middle; font-size: 0.87rem; }
        
        .avatar-sm { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .car-thumb { width: 45px; height: 34px; object-fit: cover; border-radius: 6px; }
        
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(100%); }
            .admin-sidebar.mobile-open { transform: translateX(0); }
            .admin-main { margin-right: 0; }
            .topbar-toggle { display: block; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 99; }
            .sidebar-overlay.show { display: block; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <aside class="admin-sidebar" id="adminSidebar">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php"><i class="fas fa-car"></i> السيارات</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="customers-management.php"><i class="fas fa-users"></i> العملاء</a>
                <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> التقارير</a>
                <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-topbar">
                <button class="topbar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Admin'); ?>&size=35&background=667eea&color=fff&bold=true" class="rounded-circle" width="35" height="35" alt="">
                </div>
            </header>
            
            <div class="admin-content">
                <h3 class="fw-bold mb-4"><i class="fas fa-chart-pie text-primary me-2"></i> التقارير والإحصائيات</h3>
                
                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-money-bill-wave"></i></div>
                            <div class="stat-value"><?php echo format_amount($summary['total_revenue']); ?></div>
                            <div class="stat-label">إجمالي الإيرادات</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-info bg-opacity-10 text-info"><i class="fas fa-calendar-check"></i></div>
                            <div class="stat-value"><?php echo number_format($summary['total_bookings']); ?></div>
                            <div class="stat-label">إجمالي الحجوزات</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success"><i class="fas fa-chart-line"></i></div>
                            <div class="stat-value"><?php echo format_amount($summary['avg_booking_value']); ?></div>
                            <div class="stat-label">متوسط قيمة الحجز</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-car"></i></div>
                            <div class="stat-value"><?php echo $summary['occupancy_rate']; ?>%</div>
                            <div class="stat-label">نسبة الإشغال</div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div class="row">
                    <!-- Revenue Chart -->
                    <div class="col-lg-8">
                        <div class="chart-card">
                            <h5><i class="fas fa-chart-bar"></i> الإيرادات الشهرية - <?php echo date('Y'); ?></h5>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Distribution -->
                    <div class="col-lg-4">
                        <div class="chart-card">
                            <h5><i class="fas fa-chart-pie"></i> توزيع الحجوزات</h5>
                            <div class="chart-container">
                                <canvas id="statusChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Revenue by Brand -->
                <div class="row">
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <h5><i class="fas fa-car"></i> الإيرادات حسب الماركة</h5>
                            <div class="chart-container">
                                <canvas id="brandChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Daily Revenue -->
                    <div class="col-lg-6">
                        <div class="chart-card">
                            <h5><i class="fas fa-chart-line"></i> آخر 30 يوم</h5>
                            <div class="chart-container">
                                <canvas id="dailyChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Top Cars Table -->
                <div class="table-card">
                    <h5><i class="fas fa-trophy text-warning"></i> السيارات الأكثر طلباً</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الصورة</th>
                                    <th>السيارة</th>
                                    <th>عدد الحجوزات</th>
                                    <th>الإيرادات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_cars as $index => $car): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td>
                                        <img src="<?php echo get_car_thumb($car['car_image'] ?? ''); ?>" class="car-thumb" 
                                             onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=45&h=34&fit=crop'">
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></strong> - <?php echo $car['year']; ?></td>
                                    <td><strong><?php echo $car['booking_count']; ?></strong></td>
                                    <td><strong class="text-primary"><?php echo format_amount($car['revenue']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Top Customers Table -->
                <div class="table-card">
                    <h5><i class="fas fa-users text-success"></i> أفضل العملاء</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>العميل</th>
                                    <th>البريد</th>
                                    <th>الحجوزات</th>
                                    <th>المدفوعات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_customers as $index => $cust): ?>
                                <tr>
                                    <td><strong><?php echo $index + 1; ?></strong></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($cust['full_name']); ?>&size=35&background=random&bold=true" 
                                                 class="avatar-sm" alt="">
                                            <strong><?php echo htmlspecialchars($cust['full_name']); ?></strong>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                    <td><strong><?php echo $cust['total_bookings']; ?></strong></td>
                                    <td><strong class="text-primary"><?php echo format_amount($cust['total_spent']); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('mobile-open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
        
        // ==================== CHARTS ====================
        
        // Chart defaults
        Chart.defaults.font.family = 'Cairo, sans-serif';
        Chart.defaults.color = '#6c757d';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        Chart.defaults.plugins.legend.labels.padding = 20;
        
        // Colors
        const gradientColors = ['#667eea', '#764ba2', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16'];
        
        // 1. Revenue Chart
        const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
        if (revenueCtx) {
            const months = [<?php echo implode(',', array_map(function($m) use ($arabic_months) { return "'" . $arabic_months[$m['month']] . "'"; }, $monthly_revenue)); ?>];
            const revenues = [<?php echo implode(',', array_map(function($m) { return $m['revenue']; }, $monthly_revenue)); ?>];
            const bookings = [<?php echo implode(',', array_map(function($m) { return $m['total_bookings']; }, $monthly_revenue)); ?>];
            
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: months.length ? months : ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليوز','غشت','شتنبر','أكتوبر','نونبر','دجنبر'],
                    datasets: [{
                        label: 'الإيرادات (DH)',
                        data: revenues.length ? revenues : [0,0,0,0,0,0,0,0,0,0,0,0],
                        backgroundColor: 'rgba(102,126,234,0.7)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        borderRadius: 8,
                        order: 2
                    }, {
                        label: 'عدد الحجوزات',
                        data: bookings.length ? bookings : [0,0,0,0,0,0,0,0,0,0,0,0],
                        type: 'line',
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,0.1)',
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#f59e0b',
                        pointRadius: 5,
                        pointHoverRadius: 8,
                        order: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => value.toLocaleString() + ' DH' }
                        }
                    }
                }
            });
        }
        
        // 2. Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            const statusData = <?php echo json_encode($status_distribution); ?>;
            const labels = { 'pending': 'قيد الانتظار', 'confirmed': 'مؤكد', 'active': 'نشط', 'completed': 'مكتمل', 'cancelled': 'ملغي' };
            const colors = { 'pending': '#f59e0b', 'confirmed': '#3b82f6', 'active': '#10b981', 'completed': '#667eea', 'cancelled': '#ef4444' };
            
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: statusData.map(d => labels[d.booking_status] || d.booking_status),
                    datasets: [{
                        data: statusData.map(d => d.count),
                        backgroundColor: statusData.map(d => colors[d.booking_status] || '#ccc'),
                        borderWidth: 3,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
        
        // 3. Brand Chart
        const brandCtx = document.getElementById('brandChart')?.getContext('2d');
        if (brandCtx) {
            const brandData = <?php echo json_encode($revenue_by_brand); ?>;
            
            new Chart(brandCtx, {
                type: 'bar',
                data: {
                    labels: brandData.map(d => d.brand),
                    datasets: [{
                        label: 'الإيرادات (DH)',
                        data: brandData.map(d => d.revenue),
                        backgroundColor: gradientColors.slice(0, brandData.length),
                        borderRadius: 8,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: {
                        x: {
                            ticks: { callback: value => value.toLocaleString() + ' DH' }
                        }
                    }
                }
            });
        }
        
        // 4. Daily Chart
        const dailyCtx = document.getElementById('dailyChart')?.getContext('2d');
        if (dailyCtx) {
            const dailyData = <?php echo json_encode(array_reverse($daily_stats)); ?>;
            
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: dailyData.map(d => d.date),
                    datasets: [{
                        label: 'الإيرادات اليومية (DH)',
                        data: dailyData.map(d => d.revenue),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: '#667eea',
                        pointRadius: 3,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            ticks: { callback: value => value.toLocaleString() + ' DH' }
                        }
                    }
                }
            });
        }
        
        // Set chart container heights
        document.querySelectorAll('.chart-container').forEach(el => {
            el.style.height = '350px';
        });
    </script>
</body>
</html>