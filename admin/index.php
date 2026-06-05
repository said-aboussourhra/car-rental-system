<?php
/**
 * Premium Car Rental - Admin Dashboard
 * Ultimate Admin Panel Version
 */
require_once '../includes/config.php';

// Check admin access
if (!is_admin()) {
    header('Location: ../login.php');
    exit();
}

// ============================================
// FETCH STATISTICS
// ============================================
try {
    $total_cars = $pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
    $available_cars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'available'")->fetchColumn();
    $rented_cars = $pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'rented'")->fetchColumn();
    $total_customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $pending_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn();
    $active_bookings = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'active'")->fetchColumn();
    $total_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $monthly_revenue = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();
} catch (Exception $e) {
    $total_cars = $available_cars = $rented_cars = $total_customers = 0;
    $total_bookings = $pending_bookings = $active_bookings = 0;
    $total_revenue = $monthly_revenue = 0;
}

// Recent bookings
try {
    $recent_bookings = $pdo->query("
        SELECT b.*, u.full_name, u.email, c.brand, c.model, c.year
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN cars c ON b.car_id = c.id 
        ORDER BY b.created_at DESC LIMIT 10
    ")->fetchAll();
} catch (Exception $e) {
    $recent_bookings = [];
}

// Recent customers
try {
    $recent_customers = $pdo->query("SELECT * FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 5")->fetchAll();
} catch (Exception $e) {
    $recent_customers = [];
}

// Monthly chart data
try {
    $monthly_data = $pdo->query("
        SELECT MONTH(created_at) as month, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as revenue
        FROM bookings WHERE YEAR(created_at) = YEAR(CURRENT_DATE()) AND booking_status != 'cancelled'
        GROUP BY MONTH(created_at) ORDER BY month
    ")->fetchAll();
} catch (Exception $e) {
    $monthly_data = [];
}

$page_title = 'لوحة التحكم | ' . SITE_NAME;
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    
    <style>
        :root {
            --primary: #667eea;
            --primary-dark: #4f5fd6;
            --primary-light: #eef0ff;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --dark-light: #2d2d44;
            --light: #f8f9fa;
            --white: #ffffff;
            --text: #333333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 40px rgba(0,0,0,0.1);
            --radius: 14px;
            --radius-sm: 10px;
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #f0f2f5;
            color: var(--text);
        }
        
        /* Admin Layout */
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .admin-sidebar {
            width: 260px;
            background: var(--dark);
            color: white;
            position: fixed;
            top: 0;
            right: 0;
            bottom: 0;
            z-index: 100;
            transition: var(--transition);
            overflow-y: auto;
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
        
        .sidebar-brand i { color: var(--primary); font-size: 1.5rem; }
        
        .sidebar-menu { padding: 15px 0; }
        
        .sidebar-menu .menu-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            margin: 3px 10px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        
        .sidebar-menu .menu-item:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu .menu-item.active {
            background: var(--gradient);
            color: white;
        }
        
        .sidebar-menu .menu-item i { width: 20px; text-align: center; }
        
        .sidebar-menu .badge-count {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
        }
        
        /* Main Content */
        .admin-main {
            flex: 1;
            margin-right: 260px;
            padding: 0;
        }
        
        /* Topbar */
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
        
        .topbar-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text);
        }
        
        .admin-content { padding: 25px; }
        
        /* Welcome */
        .welcome-card {
            background: var(--gradient);
            color: white;
            padding: 30px;
            border-radius: var(--radius);
            margin-bottom: 25px;
        }
        
        .welcome-card h2 { font-weight: 800; margin-bottom: 8px; }
        .welcome-card p { opacity: 0.9; margin: 0; }
        
        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 22px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border);
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }
        
        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 15px;
        }
        
        .stat-card .stat-value {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.85rem;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .stat-card .stat-sub {
            font-size: 0.78rem;
            color: var(--success);
            margin-top: 5px;
        }
        
        /* Tables */
        .table-card {
            background: white;
            border-radius: var(--radius);
            padding: 0;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-card .card-header {
            padding: 18px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-card .card-header h5 {
            font-weight: 800;
            margin: 0;
            color: var(--dark);
        }
        
        .table { margin: 0; }
        
        .table th {
            font-weight: 700;
            font-size: 0.85rem;
            color: var(--text-light);
            padding: 14px 20px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
        }
        
        .table td {
            padding: 12px 20px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        
        .table tr:hover { background: #fafbff; }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; }
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-completed { background: #e0e7ff; color: #3730a3; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; }
        
        /* Recent Customers */
        .customer-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            border-bottom: 1px solid var(--border);
            transition: var(--transition);
        }
        
        .customer-item:hover { background: #fafbff; }
        .customer-item:last-child { border-bottom: none; }
        
        .customer-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        /* Buttons */
        .btn-primary {
            background: var(--gradient);
            border: none;
            color: white;
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        
        /* Responsive */
        @media (max-width: 991px) {
            .admin-sidebar {
                transform: translateX(100%);
            }
            
            .admin-sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .admin-main {
                margin-right: 0;
            }
            
            .topbar-toggle {
                display: block;
            }
            
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 99;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
        
        @media (max-width: 576px) {
            .admin-content { padding: 15px; }
            .welcome-card { padding: 20px; }
            .stat-card { padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <nav class="sidebar-menu">
                <a href="index.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i> لوحة التحكم
                </a>
                <a href="cars-management.php" class="menu-item">
                    <i class="fas fa-car"></i> إدارة السيارات
                </a>
                <a href="bookings-management.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> إدارة الحجوزات
                    <?php if ($pending_bookings > 0): ?>
                    <span class="badge-count"><?php echo $pending_bookings; ?></span>
                    <?php endif; ?>
                </a>
                <a href="customers-management.php" class="menu-item">
                    <i class="fas fa-users"></i> العملاء
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-chart-bar"></i> التقارير
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i> الإعدادات
                </a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <a href="../" class="menu-item" target="_blank">
                    <i class="fas fa-external-link-alt"></i> معاينة الموقع
                </a>
                <a href="../logout.php" class="menu-item text-danger">
                    <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                </a>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <!-- Topbar -->
            <header class="admin-topbar">
                <button class="topbar-toggle" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Admin'); ?>&size=35&background=667eea&color=fff&bold=true" 
                         class="rounded-circle" width="35" height="35" alt="">
                </div>
            </header>
            
            <!-- Content -->
            <div class="admin-content">
                <!-- Welcome -->
                <div class="welcome-card" data-aos="fade-up">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-hand-wave me-2"></i> مرحباً بك، <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?>!</h2>
                            <p>هذه لوحة التحكم الخاصة بك. يمكنك إدارة جميع جوانب الموقع من هنا.</p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <a href="cars-management.php?action=add" class="btn btn-light fw-bold">
                                <i class="fas fa-plus-circle me-1"></i> إضافة سيارة جديدة
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Row -->
                <div class="row g-3 mb-4">
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="0">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($total_cars); ?></div>
                            <div class="stat-label">إجمالي السيارات</div>
                            <div class="stat-sub"><?php echo $available_cars; ?> متوفرة</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-card">
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($total_bookings); ?></div>
                            <div class="stat-label">إجمالي الحجوزات</div>
                            <div class="stat-sub"><?php echo $active_bookings; ?> نشطة</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-value"><?php echo number_format($total_customers); ?></div>
                            <div class="stat-label">العملاء</div>
                            <div class="stat-sub">عملاء مسجلين</div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-card">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="fas fa-money-bill-wave"></i>
                            </div>
                            <div class="stat-value"><?php echo format_amount($monthly_revenue); ?></div>
                            <div class="stat-label">إيرادات الشهر</div>
                            <div class="stat-sub">الإجمالي: <?php echo format_amount($total_revenue); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Bookings & Customers -->
                <div class="row g-3">
                    <div class="col-lg-8" data-aos="fade-up">
                        <div class="table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-clock text-primary me-2"></i> آخر الحجوزات</h5>
                                <a href="bookings-management.php" class="btn btn-primary btn-sm">عرض الكل</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>رقم الحجز</th>
                                            <th>العميل</th>
                                            <th>السيارة</th>
                                            <th>التاريخ</th>
                                            <th>المبلغ</th>
                                            <th>الحالة</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($recent_bookings)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">لا توجد حجوزات بعد</td>
                                        </tr>
                                        <?php else: ?>
                                        <?php foreach ($recent_bookings as $b): 
                                            $status_class = match($b['booking_status']) {
                                                'pending' => 'badge-pending',
                                                'confirmed' => 'badge-confirmed',
                                                'active' => 'badge-active',
                                                'completed' => 'badge-completed',
                                                'cancelled' => 'badge-cancelled',
                                                default => 'badge-pending'
                                            };
                                            $status_text = match($b['booking_status']) {
                                                'pending' => 'قيد الانتظار',
                                                'confirmed' => 'مؤكد',
                                                'active' => 'نشط',
                                                'completed' => 'مكتمل',
                                                'cancelled' => 'ملغي',
                                                default => 'قيد الانتظار'
                                            };
                                        ?>
                                        <tr>
                                            <td><strong><?php echo $b['booking_number']; ?></strong></td>
                                            <td><?php echo htmlspecialchars($b['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($b['brand'] . ' ' . $b['model']); ?></td>
                                            <td><?php echo format_date($b['pickup_date']); ?></td>
                                            <td><strong><?php echo format_amount($b['total_amount']); ?></strong></td>
                                            <td><span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4" data-aos="fade-up" data-aos-delay="100">
                        <div class="table-card">
                            <div class="card-header">
                                <h5><i class="fas fa-user-plus text-success me-2"></i> آخر العملاء</h5>
                            </div>
                            <?php if (empty($recent_customers)): ?>
                            <div class="p-4 text-center text-muted">لا يوجد عملاء بعد</div>
                            <?php else: ?>
                            <?php foreach ($recent_customers as $cust): ?>
                            <div class="customer-item">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($cust['full_name']); ?>&size=40&background=random&bold=true" 
                                     class="customer-avatar" alt="">
                                <div>
                                    <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($cust['full_name']); ?></h6>
                                    <small class="text-muted"><?php echo htmlspecialchars($cust['email']); ?></small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Quick Actions -->
                        <div class="table-card mt-3 p-4">
                            <h5 class="fw-bold mb-3"><i class="fas fa-bolt text-warning me-2"></i> إجراءات سريعة</h5>
                            <a href="cars-management.php?action=add" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-plus me-1"></i> إضافة سيارة
                            </a>
                            <a href="bookings-management.php?action=add" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-calendar-plus me-1"></i> حجز جديد
                            </a>
                            <a href="reports.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-chart-line me-1"></i> التقارير
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <script>
        AOS.init({ duration: 800, once: true });
        
        // Sidebar toggle
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('mobile-open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
</body>
</html>