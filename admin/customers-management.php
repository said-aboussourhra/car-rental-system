<?php
/**
 * Premium Car Rental - Admin Customers Management
 * Ultimate Customers Management Version
 */
require_once '../includes/config.php';

// Check admin access
if (!is_admin()) {
    header('Location: ../login.php');
    exit();
}

// ============================================
// HANDLE ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if ($user_id > 0) {
        try {
            switch ($_POST['action']) {
                case 'toggle_status':
                    $pdo->prepare("UPDATE users SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = :id")->execute([':id' => $user_id]);
                    set_message('تم تحديث حالة العميل', 'success');
                    break;
                case 'ban':
                    $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = :id")->execute([':id' => $user_id]);
                    set_message('تم حظر العميل', 'warning');
                    break;
                case 'delete':
                    $pdo->prepare("DELETE FROM users WHERE id = :id AND role = 'customer'")->execute([':id' => $user_id]);
                    set_message('تم حذف العميل', 'danger');
                    break;
            }
        } catch (Exception $e) {
            set_message('خطأ: ' . $e->getMessage(), 'danger');
        }
    }
    header('Location: customers-management.php');
    exit();
}

// ============================================
// FILTERS & PAGINATION
// ============================================
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = ["role = 'customer'"];
$params = [];

if (!empty($search)) {
    $where[] = "(full_name LIKE :s1 OR email LIKE :s2 OR phone LIKE :s3)";
    $params[':s1'] = "%$search%";
    $params[':s2'] = "%$search%";
    $params[':s3'] = "%$search%";
}

if (!empty($status)) {
    $where[] = "status = :status";
    $params[':status'] = $status;
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

try {
    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $whereSQL");
    $countStmt->execute($params);
    $totalCustomers = $countStmt->fetchColumn();
    $totalPages = ceil($totalCustomers / $perPage);
    
    // Fetch customers with stats
    $stmt = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as total_bookings,
               (SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE user_id = u.id AND payment_status = 'paid') as total_spent,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND booking_status = 'active') as active_bookings,
               (SELECT MAX(created_at) FROM bookings WHERE user_id = u.id) as last_booking_date
        FROM users u 
        $whereSQL 
        ORDER BY u.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $customers = $stmt->fetchAll();
} catch (Exception $e) {
    $customers = [];
    $totalCustomers = 0;
    $totalPages = 0;
}

// Statistics
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active'")->fetchColumn(),
        'inactive' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'inactive'")->fetchColumn(),
        'banned' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'banned'")->fetchColumn(),
        'new_today' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'new_week' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetchColumn(),
    ];
} catch (Exception $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'banned' => 0, 'new_today' => 0, 'new_week' => 0];
}

function build_cust_url($page) {
    $get = $_GET;
    $get['page'] = $page;
    return 'customers-management.php?' . http_build_query($get);
}

$page_title = 'إدارة العملاء | لوحة التحكم';
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
            --info: #3b82f6;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --shadow-sm: 0 2px 8px rgba(0,0,0,0.04);
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
        
        /* Stat Mini */
        .stat-mini {
            background: white;
            border-radius: var(--radius-sm);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
        }
        
        .stat-mini:hover { transform: translateY(-3px); box-shadow: var(--shadow); }
        .stat-mini.active { border-color: var(--primary); }
        .stat-mini .value { font-size: 1.5rem; font-weight: 900; color: var(--dark); }
        .stat-mini .label { font-size: 0.8rem; color: var(--text-light); font-weight: 500; }
        
        /* Content Card */
        .content-card {
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-top: 20px;
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
        
        .content-card .card-header h4 { font-weight: 800; margin: 0; color: var(--dark); }
        
        .btn {
            font-weight: 600;
            border-radius: 8px;
            padding: 8px 16px;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); color: white; }
        .btn-sm { padding: 5px 12px; font-size: 0.78rem; }
        .btn-xs { padding: 3px 8px; font-size: 0.7rem; border-radius: 6px; }
        
        .btn-success { background: #10b981; border: none; color: white; }
        .btn-warning { background: #f59e0b; border: none; color: #000; }
        .btn-danger { background: #ef4444; border: none; color: white; }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            font-size: 0.85rem;
        }
        
        .btn-icon-info { background: #dbeafe; color: #1e40af; }
        .btn-icon-info:hover { background: #3b82f6; color: white; }
        .btn-icon-warning { background: #fef3c7; color: #92400e; }
        .btn-icon-warning:hover { background: #f59e0b; color: white; }
        .btn-icon-danger { background: #fee2e2; color: #991b1b; }
        .btn-icon-danger:hover { background: #ef4444; color: white; }
        
        .table { margin: 0; }
        .table th {
            font-weight: 700;
            font-size: 0.8rem;
            color: var(--text-light);
            padding: 12px 14px;
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .table td { padding: 12px 14px; vertical-align: middle; font-size: 0.88rem; }
        .table tr:hover { background: #fafbff; }
        
        .avatar-thumb { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.73rem;
        }
        
        .badge-active { background: #d1fae5; color: #065f46; }
        .badge-inactive { background: #fef3c7; color: #92400e; }
        .badge-banned { background: #fee2e2; color: #991b1b; }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 4px;
            list-style: none;
            padding: 18px;
            margin: 0;
        }
        
        .pagination .page-link {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border);
            color: var(--text);
            font-weight: 600;
            font-size: 0.85rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .pagination .page-link:hover { border-color: var(--primary); color: var(--primary); }
        .pagination .active .page-link { background: var(--gradient); color: white; border-color: transparent; }
        .pagination .disabled .page-link { opacity: 0.4; pointer-events: none; }
        
        .alert { border-radius: 10px; padding: 15px 20px; margin-bottom: 20px; border: none; }
        
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
        
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="adminSidebar">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php"><i class="fas fa-car"></i> إدارة السيارات</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="customers-management.php" class="active"><i class="fas fa-users"></i> العملاء</a>
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
                <?php foreach (get_messages() as $msg): ?>
                <div class="alert alert-<?php echo $msg['type']; ?> alert-dismissible fade show">
                    <i class="fas fa-<?php echo $msg['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                    <?php echo $msg['text']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endforeach; ?>
                
                <!-- Stats -->
                <div class="row g-3">
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value"><?php echo number_format($stats['total']); ?></div>
                            <div class="label">إجمالي العملاء</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value text-success"><?php echo number_format($stats['active']); ?></div>
                            <div class="label">نشط</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value text-warning"><?php echo $stats['inactive']; ?></div>
                            <div class="label">غير نشط</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value text-danger"><?php echo $stats['banned']; ?></div>
                            <div class="label">محظور</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value text-info"><?php echo $stats['new_today']; ?></div>
                            <div class="label">جديد اليوم</div>
                        </div>
                    </div>
                    <div class="col-4 col-md-2">
                        <div class="stat-mini">
                            <div class="value text-primary"><?php echo $stats['new_week']; ?></div>
                            <div class="label">جديد الأسبوع</div>
                        </div>
                    </div>
                </div>
                
                <!-- Customers Table -->
                <div class="content-card">
                    <div class="card-header">
                        <h4><i class="fas fa-users text-primary me-2"></i> قائمة العملاء</h4>
                        <form method="GET" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control form-control-sm" 
                                   placeholder="بحث عن عميل..." value="<?php echo htmlspecialchars($search); ?>" style="width: 200px;">
                            <select name="status" class="form-select form-select-sm" style="width: 130px;" onchange="this.form.submit()">
                                <option value="">جميع الحالات</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                <option value="banned" <?php echo $status === 'banned' ? 'selected' : ''; ?>>محظور</option>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">بحث</button>
                            <?php if (!empty($search) || !empty($status)): ?>
                            <a href="customers-management.php" class="btn btn-outline-primary btn-sm">إعادة تعيين</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (empty($customers)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-user-slash fa-4x text-muted mb-3"></i>
                            <h5>لا يوجد عملاء</h5>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>العميل</th>
                                        <th>البريد</th>
                                        <th>الهاتف</th>
                                        <th>الحجوزات</th>
                                        <th>المدفوعات</th>
                                        <th>نشط</th>
                                        <th>آخر حجز</th>
                                        <th>تاريخ التسجيل</th>
                                        <th>الحالة</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customers as $cust): 
                                        $status_class = match($cust['status']) {
                                            'active' => 'badge-active',
                                            'inactive' => 'badge-inactive',
                                            'banned' => 'badge-banned',
                                            default => 'badge-inactive'
                                        };
                                        $status_text = match($cust['status']) {
                                            'active' => 'نشط',
                                            'inactive' => 'غير نشط',
                                            'banned' => 'محظور',
                                            default => $cust['status']
                                        };
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($cust['full_name']); ?>&size=42&background=random&bold=true" 
                                                     class="avatar-thumb" alt="">
                                                <strong><?php echo htmlspecialchars($cust['full_name']); ?></strong>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cust['email']); ?></td>
                                        <td><?php echo htmlspecialchars($cust['phone']); ?></td>
                                        <td><strong><?php echo $cust['total_bookings']; ?></strong></td>
                                        <td><strong class="text-primary"><?php echo format_amount($cust['total_spent']); ?></strong></td>
                                        <td><strong class="text-success"><?php echo $cust['active_bookings']; ?></strong></td>
                                        <td><?php echo $cust['last_booking_date'] ? format_date($cust['last_booking_date']) : '-'; ?></td>
                                        <td><small><?php echo format_date($cust['created_at']); ?></small></td>
                                        <td><span class="badge-status <?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                        <td>
                                            <div class="d-flex gap-1">
                                                <a href="customer-details.php?id=<?php echo $cust['id']; ?>" class="btn-icon btn-icon-info" title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                
                                                <?php if ($cust['status'] !== 'banned'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $cust['id']; ?>">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <button type="submit" class="btn-icon <?php echo $cust['status'] === 'active' ? 'btn-icon-warning' : 'btn-icon-info'; ?>" 
                                                            title="<?php echo $cust['status'] === 'active' ? 'تعطيل' : 'تفعيل'; ?>">
                                                        <i class="fas <?php echo $cust['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?>"></i>
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('حظر هذا العميل؟')">
                                                    <input type="hidden" name="user_id" value="<?php echo $cust['id']; ?>">
                                                    <input type="hidden" name="action" value="ban">
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="حظر">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('حذف نهائي؟')">
                                                    <input type="hidden" name="user_id" value="<?php echo $cust['id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="حذف" style="background:#fee2e2; color:#991b1b;">
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
                        
                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page > 1 ? build_cust_url($page - 1) : '#'; ?>"><i class="fas fa-chevron-right"></i></a>
                                </li>
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="<?php echo build_cust_url($i); ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="<?php echo $page < $totalPages ? build_cust_url($page + 1) : '#'; ?>"><i class="fas fa-chevron-left"></i></a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        
                        <div class="p-3 border-top text-muted small">
                            إجمالي العملاء: <strong><?php echo number_format($totalCustomers); ?></strong>
                        </div>
                        <?php endif; ?>
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
    </script>
</body>
</html>