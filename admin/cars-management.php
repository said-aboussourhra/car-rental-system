<?php
/**
 * Premium Car Rental - Admin Cars Management
 * Ultimate Cars Management Version
 */
require_once '../includes/config.php';

// Check admin access
if (!is_admin()) {
    header('Location: ../login.php');
    exit();
}

// ============================================
// HANDLE ACTIONS (Delete, Status Change)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $car_id = intval($_POST['car_id'] ?? 0);
        
        // Delete car
        if ($_POST['action'] === 'delete' && $car_id > 0) {
            try {
                $stmt = $pdo->prepare("DELETE FROM cars WHERE id = :id");
                $stmt->execute([':id' => $car_id]);
                set_message('تم حذف السيارة بنجاح', 'success');
            } catch (Exception $e) {
                set_message('خطأ في حذف السيارة: ' . $e->getMessage(), 'danger');
            }
        }
        
        // Toggle status
        if ($_POST['action'] === 'toggle_status' && $car_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cars SET status = CASE WHEN status = 'available' THEN 'maintenance' ELSE 'available' END WHERE id = :id");
                $stmt->execute([':id' => $car_id]);
                set_message('تم تحديث حالة السيارة', 'success');
            } catch (Exception $e) {
                set_message('خطأ في تحديث الحالة', 'danger');
            }
        }
        
        // Toggle popular
        if ($_POST['action'] === 'toggle_popular' && $car_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE cars SET popular = CASE WHEN popular = 1 THEN 0 ELSE 1 END WHERE id = :id");
                $stmt->execute([':id' => $car_id]);
                set_message('تم تحديث حالة السيارة', 'success');
            } catch (Exception $e) {
                set_message('خطأ في التحديث', 'danger');
            }
        }
        
        header('Location: cars-management.php');
        exit();
    }
}

// ============================================
// FETCH ALL CARS
// ============================================
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(c.brand LIKE :s1 OR c.model LIKE :s2 OR c.brand LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if (!empty($status_filter)) {
    $where[] = "c.status = :status";
    $params[':status'] = $status_filter;
}

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM cars c $whereSQL");
    $countStmt->execute($params);
    $totalCars = $countStmt->fetchColumn();
    $totalPages = ceil($totalCars / $perPage);
    
    // Fetch cars
    $stmt = $pdo->prepare("
        SELECT c.*, 
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image,
               (SELECT COUNT(*) FROM bookings WHERE car_id = c.id) as booking_count
        FROM cars c 
        $whereSQL 
        ORDER BY c.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $cars = $stmt->fetchAll();
} catch (Exception $e) {
    $cars = [];
    $totalCars = 0;
    $totalPages = 0;
}

// ============================================
// HELPER FUNCTIONS
// ============================================
function get_car_image_admin($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=80&h=60&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}

function build_admin_url($page) {
    $get = $_GET;
    $get['page'] = $page;
    return 'cars-management.php?' . http_build_query($get);
}

$page_title = 'إدارة السيارات | لوحة التحكم';
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
        
        /* Sidebar */
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
        
        /* Main */
        .admin-main {
            flex: 1;
            margin-right: 250px;
        }
        
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
        }
        
        .admin-content { padding: 25px; }
        
        /* Cards */
        .content-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .content-card .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .content-card .card-header h4 {
            font-weight: 800;
            margin: 0;
            color: var(--dark);
        }
        
        .content-card .card-body { padding: 0; }
        
        .btn {
            font-weight: 600;
            border-radius: 8px;
            padding: 10px 20px;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); color: white; }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline-primary:hover { background: var(--primary); color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.8rem; }
        
        .btn-icon {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .btn-icon-info { background: #dbeafe; color: #1e40af; }
        .btn-icon-info:hover { background: #3b82f6; color: white; }
        .btn-icon-warning { background: #fef3c7; color: #92400e; }
        .btn-icon-warning:hover { background: #f59e0b; color: white; }
        .btn-icon-danger { background: #fee2e2; color: #991b1b; }
        .btn-icon-danger:hover { background: #ef4444; color: white; }
        .btn-icon-success { background: #d1fae5; color: #065f46; }
        .btn-icon-success:hover { background: #10b981; color: white; }
        
        /* Table */
        .table { margin: 0; }
        .table th {
            font-weight: 700;
            font-size: 0.82rem;
            color: var(--text-light);
            padding: 14px 16px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .table td {
            padding: 12px 16px;
            vertical-align: middle;
            font-size: 0.88rem;
        }
        .table tr:hover { background: #fafbff; }
        .table .car-thumb { width: 60px; height: 45px; object-fit: cover; border-radius: 6px; }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.75rem;
        }
        
        .badge-available { background: #d1fae5; color: #065f46; }
        .badge-rented { background: #dbeafe; color: #1e40af; }
        .badge-maintenance { background: #fef3c7; color: #92400e; }
        .badge-inactive { background: #fee2e2; color: #991b1b; }
        
        .popular-star { color: #f59e0b; font-size: 1.1rem; }
        .popular-star.inactive { color: #ddd; }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 5px;
            list-style: none;
            padding: 20px;
            margin: 0;
        }
        
        .pagination .page-link {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border);
            color: var(--text);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover { border-color: var(--primary); color: var(--primary); }
        .pagination .active .page-link { background: var(--gradient); color: white; border-color: transparent; }
        .pagination .disabled .page-link { opacity: 0.4; pointer-events: none; }
        
        /* Messages */
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }
        
        /* Responsive */
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
        <!-- Sidebar Overlay -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php" class="active"><i class="fas fa-car"></i> إدارة السيارات</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="customers-management.php"><i class="fas fa-users"></i> العملاء</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
                <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </nav>
        </aside>
        
        <!-- Main -->
        <main class="admin-main">
            <header class="admin-topbar">
                <button class="topbar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></span>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Admin'); ?>&size=35&background=667eea&color=fff&bold=true" class="rounded-circle" width="35" height="35" alt="">
                </div>
            </header>
            
            <div class="admin-content">
                <!-- Messages -->
                <?php 
                $messages = get_messages();
                foreach ($messages as $msg): 
                ?>
                <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $msg['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $msg['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endforeach; ?>
                
                <!-- Cars Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h4><i class="fas fa-car text-primary me-2"></i> إدارة السيارات</h4>
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <!-- Search -->
                            <form method="GET" class="d-flex gap-2">
                                <input type="text" class="form-control form-control-sm" name="search" 
                                       placeholder="بحث عن سيارة..." value="<?php echo htmlspecialchars($search); ?>"
                                       style="width: 200px;">
                                <select name="status" class="form-select form-select-sm" style="width: 150px;" onchange="this.form.submit()">
                                    <option value="">جميع الحالات</option>
                                    <option value="available" <?php echo $status_filter === 'available' ? 'selected' : ''; ?>>متوفرة</option>
                                    <option value="rented" <?php echo $status_filter === 'rented' ? 'selected' : ''; ?>>مؤجرة</option>
                                    <option value="maintenance" <?php echo $status_filter === 'maintenance' ? 'selected' : ''; ?>>صيانة</option>
                                </select>
                                <button type="submit" class="btn btn-primary btn-sm">بحث</button>
                                <?php if (!empty($search) || !empty($status_filter)): ?>
                                <a href="cars-management.php" class="btn btn-outline-primary btn-sm">إعادة تعيين</a>
                                <?php endif; ?>
                            </form>
                            
                            <a href="add-car.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle me-1"></i> إضافة سيارة جديدة
                            </a>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($cars)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-car fa-4x text-muted mb-3"></i>
                            <h5>لا توجد سيارات</h5>
                            <p class="text-muted">لم يتم إضافة أي سيارات بعد</p>
                            <a href="add-car.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus-circle me-1"></i> إضافة أول سيارة
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الصورة</th>
                                        <th>الماركة / الموديل</th>
                                        <th>السنة</th>
                                        <th>السعر اليومي</th>
                                        <th>النوع</th>
                                        <th>الحالة</th>
                                        <th>مميزة</th>
                                        <th>الحجوزات</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cars as $index => $car): 
                                        $car_img = get_car_image_admin($car['main_image'] ?? '');
                                        $row_num = $offset + $index + 1;
                                        
                                        $status_class = match($car['status']) {
                                            'available' => 'badge-available',
                                            'rented' => 'badge-rented',
                                            'maintenance' => 'badge-maintenance',
                                            default => 'badge-inactive'
                                        };
                                        
                                        $status_text = match($car['status']) {
                                            'available' => 'متوفرة',
                                            'rented' => 'مؤجرة',
                                            'maintenance' => 'صيانة',
                                            default => $car['status']
                                        };
                                        
                                        $fuel_icons = ['petrol' => '⛽', 'diesel' => '🛢️', 'electric' => '⚡', 'hybrid' => '🔋'];
                                        $fuel_icon = $fuel_icons[$car['fuel_type']] ?? '';
                                    ?>
                                    <tr>
                                        <td><strong><?php echo $row_num; ?></strong></td>
                                        <td>
                                            <img src="<?php echo $car_img; ?>" class="car-thumb" 
                                                 alt="<?php echo htmlspecialchars($car['brand']); ?>"
                                                 onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=80&h=60&fit=crop'">
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $fuel_icon; ?> 
                                                <?php echo $car['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي'; ?> | 
                                                <?php echo $car['seats']; ?> مقاعد
                                            </small>
                                        </td>
                                        <td><?php echo $car['year']; ?></td>
                                        <td><strong class="text-primary"><?php echo number_format($car['daily_rate'], 2); ?> DH</strong></td>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($car['type'] ?? 'قياسية'); ?></span></td>
                                        <td><span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td class="text-center">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                <input type="hidden" name="action" value="toggle_popular">
                                                <button type="submit" class="btn-icon <?php echo $car['popular'] ? 'btn-icon-warning' : 'btn-icon-info'; ?>" 
                                                        title="<?php echo $car['popular'] ? 'إلغاء التميز' : 'تعيين كمميزة'; ?>">
                                                    <i class="fas <?php echo $car['popular'] ? 'fa-star' : 'fa-star'; ?>" 
                                                       style="color: <?php echo $car['popular'] ? '#f59e0b' : '#ccc'; ?>;"></i>
                                                </button>
                                            </form>
                                        </td>
                                        <td><?php echo $car['booking_count']; ?></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="edit-car.php?id=<?php echo $car['id']; ?>" class="btn-icon btn-icon-info" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('تغيير حالة السيارة؟')">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <button type="submit" class="btn-icon <?php echo $car['status'] === 'available' ? 'btn-icon-warning' : 'btn-icon-success'; ?>" 
                                                            title="<?php echo $car['status'] === 'available' ? 'تعيين للصيانة' : 'تعيين متوفرة'; ?>">
                                                        <i class="fas <?php echo $car['status'] === 'available' ? 'fa-pause-circle' : 'fa-check-circle'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذه السيارة؟')">
                                                    <input type="hidden" name="car_id" value="<?php echo $car['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="حذف">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page > 1 ? build_admin_url($page - 1) : '#'; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_admin_url($i); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page < $totalPages ? build_admin_url($page + 1) : '#'; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <div class="p-3 border-top text-muted">
                            <small>إجمالي السيارات: <strong><?php echo number_format($totalCars); ?></strong></small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function toggleSidebar() {
            document.getElementById('adminSidebar').classList.toggle('mobile-open');
            document.getElementById('sidebarOverlay').classList.toggle('show');
        }
    </script>
</body>
</html>