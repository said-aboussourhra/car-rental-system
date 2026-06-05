<?php
/**
 * Premium Car Rental - Admin Bookings Management
 * Imperial Elite Version - The Ultimate Booking Control Panel
 */
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
$user_role = $_SESSION['user_role'] ?? 'admin';

// ============================================
// HANDLE ALL ACTIONS
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $booking_id = intval($_POST['booking_id'] ?? 0);
    
    if ($booking_id > 0) {
        try {
            $pdo->beginTransaction();
            
            switch ($_POST['action']) {
                case 'confirm':
                    $pdo->prepare("UPDATE bookings SET booking_status = 'confirmed' WHERE id = :id")->execute([':id' => $booking_id]);
                    set_message('✅ تم تأكيد الحجز بنجاح', 'success');
                    break;
                    
                case 'activate':
                    $pdo->prepare("UPDATE bookings SET booking_status = 'active' WHERE id = :id")->execute([':id' => $booking_id]);
                    $pdo->prepare("UPDATE cars c JOIN bookings b ON c.id = b.car_id SET c.status = 'rented' WHERE b.id = :id")->execute([':id' => $booking_id]);
                    set_message('✅ تم تفعيل الحجز، السيارة الآن مؤجرة', 'success');
                    break;
                    
                case 'complete':
                    $pdo->prepare("UPDATE bookings SET booking_status = 'completed', payment_status = 'paid' WHERE id = :id")->execute([':id' => $booking_id]);
                    $pdo->prepare("UPDATE cars c JOIN bookings b ON c.id = b.car_id SET c.status = 'available' WHERE b.id = :id")->execute([':id' => $booking_id]);
                    set_message('✅ تم إكمال الحجز بنجاح', 'success');
                    break;
                    
                case 'cancel':
                    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :id");
                    $stmt->execute([':id' => $booking_id]);
                    $booking = $stmt->fetch();
                    if ($booking && $booking['booking_status'] !== 'active') {
                        $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = :id")->execute([':id' => $booking['car_id']]);
                    }
                    $pdo->prepare("UPDATE bookings SET booking_status = 'cancelled' WHERE id = :id")->execute([':id' => $booking_id]);
                    set_message('⚠️ تم إلغاء الحجز', 'warning');
                    break;
                    
                case 'delete':
                    $pdo->prepare("DELETE FROM bookings WHERE id = :id")->execute([':id' => $booking_id]);
                    set_message('🗑️ تم حذف الحجز نهائياً', 'danger');
                    break;
                    
                case 'mark_paid':
                    $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = :id")->execute([':id' => $booking_id]);
                    $txn = 'MANUAL-' . strtoupper(substr(md5(uniqid()), 0, 10));
                    $stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, payment_date) VALUES (:bid, (SELECT total_amount FROM bookings WHERE id = :bid2), 'manual', :txn, 'success', NOW())");
                    $stmt->execute([':bid' => $booking_id, ':bid2' => $booking_id, ':txn' => $txn]);
                    set_message('💰 تم تحديد الدفع كمكتمل', 'success');
                    break;
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            set_message('❌ خطأ: ' . $e->getMessage(), 'danger');
        }
    }
    header('Location: bookings-management.php');
    exit();
}

// ============================================
// FILTERS & PAGINATION
// ============================================
$search = clean_input($_GET['search'] ?? '');
$status = clean_input($_GET['status'] ?? '');
$payment = clean_input($_GET['payment'] ?? '');
$date_from = clean_input($_GET['date_from'] ?? '');
$date_to = clean_input($_GET['date_to'] ?? '');
$car_brand = clean_input($_GET['car_brand'] ?? '');
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(b.booking_number LIKE :s1 OR u.full_name LIKE :s2 OR u.email LIKE :s3 OR u.phone LIKE :s4 OR c.model LIKE :s5)";
    $params[':s1'] = "%$search%"; $params[':s2'] = "%$search%"; $params[':s3'] = "%$search%"; $params[':s4'] = "%$search%"; $params[':s5'] = "%$search%";
}
if (!empty($status)) { $where[] = "b.booking_status = :status"; $params[':status'] = $status; }
if (!empty($payment)) { $where[] = "b.payment_status = :payment"; $params[':payment'] = $payment; }
if (!empty($date_from)) { $where[] = "DATE(b.pickup_date) >= :date_from"; $params[':date_from'] = $date_from; }
if (!empty($date_to)) { $where[] = "DATE(b.return_date) <= :date_to"; $params[':date_to'] = $date_to; }
if (!empty($car_brand)) { $where[] = "c.brand = :car_brand"; $params[':car_brand'] = $car_brand; }

$whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b JOIN users u ON b.user_id = u.id JOIN cars c ON b.car_id = c.id $whereSQL");
    $countStmt->execute($params);
    $totalBookings = $countStmt->fetchColumn();
    $totalPages = ceil($totalBookings / $perPage);
    
    // Fetch bookings
    $stmt = $pdo->prepare("
        SELECT b.*, u.full_name, u.email, u.phone, u.avatar,
               c.brand, c.model, c.year, c.color, c.transmission, c.fuel_type,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b 
        JOIN users u ON b.user_id = u.id 
        JOIN cars c ON b.car_id = c.id 
        $whereSQL 
        ORDER BY b.created_at DESC 
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {
    $bookings = []; $totalBookings = 0; $totalPages = 0;
}

// Statistics
try {
    $stats = [
        'total' => $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn(),
        'confirmed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'confirmed'")->fetchColumn(),
        'active' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'active'")->fetchColumn(),
        'completed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'completed'")->fetchColumn(),
        'cancelled' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'cancelled'")->fetchColumn(),
        'revenue_total' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn(),
        'revenue_month' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn(),
        'revenue_today' => $pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid' AND DATE(created_at) = CURDATE()")->fetchColumn(),
        'unpaid' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending' AND booking_status NOT IN ('cancelled', 'completed')")->fetchColumn(),
    ];
} catch (Exception $e) {
    $stats = ['total' => 0,'pending' => 0,'confirmed' => 0,'active' => 0,'completed' => 0,'cancelled' => 0,'revenue_total' => 0,'revenue_month' => 0,'revenue_today' => 0,'unpaid' => 0];
}

// Car brands for filter
$car_brands = [];
try { $car_brands = $pdo->query("SELECT DISTINCT brand FROM cars ORDER BY brand")->fetchAll(); } catch (Exception $e) {}

function admin_car_img($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=60&h=45&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}
function build_admin_page_url($page) { $get = $_GET; $get['page'] = $page; return 'bookings-management.php?' . http_build_query($get); }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الحجوزات | لوحة التحكم | <?php echo SITE_NAME; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- CSS Libraries -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/material_green.css">
    
    <style>
        :root {
            --primary: #667eea; --primary-dark: #4f5fd6; --primary-light: #eef0ff;
            --gold: #c9a84c; --gold-light: #f5ecd7; --gold-dark: #a68a3e;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-gold: linear-gradient(135deg, #c9a84c 0%, #e5c76b 100%);
            --gradient-dark: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #2d2d44 100%);
            --dark: #0f0f1a; --light: #f8f9fa; --white: #ffffff;
            --text: #333333; --text-light: #6c757d; --border: #e0e0e0;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04); --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.08); --shadow-lg: 0 20px 60px rgba(0,0,0,0.12);
            --radius-xs: 8px; --radius-sm: 12px; --radius: 16px; --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f0f2f5; color: var(--text); line-height: 1.7; }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; } ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        /* ========== LAYOUT ========== */
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        /* ========== SIDEBAR ========== */
        .admin-sidebar {
            width: 265px; background: var(--gradient-dark); color: white;
            position: fixed; top: 0; right: 0; bottom: 0; z-index: 100; overflow-y: auto;
            transition: var(--transition); border-left: 1px solid rgba(255,255,255,0.03);
        }
        .sidebar-header { padding: 28px 20px; border-bottom: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .sidebar-logo { display: flex; align-items: center; justify-content: center; gap: 12px; color: white; text-decoration: none; font-weight: 900; font-size: 1.25rem; }
        .sidebar-logo .logo-icon { width: 46px; height: 46px; background: var(--gradient); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; }
        .sidebar-user { display: flex; align-items: center; gap: 12px; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.06); }
        .sidebar-user .avatar-ring { width: 44px; height: 44px; border-radius: 50%; padding: 2px; background: linear-gradient(135deg, var(--gold), #e5c76b); }
        .sidebar-user img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .sidebar-user h6 { color: white; font-weight: 700; margin: 0; font-size: 0.9rem; }
        .sidebar-user span { color: rgba(255,255,255,0.4); font-size: 0.73rem; }
        
        .sidebar-menu { padding: 15px 0; }
        .sidebar-menu .menu-label { padding: 12px 22px; font-size: 0.65rem; text-transform: uppercase; letter-spacing: 2.5px; color: rgba(255,255,255,0.25); font-weight: 700; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 12px; padding: 13px 22px; color: rgba(255,255,255,0.6);
            text-decoration: none; font-weight: 500; transition: var(--transition); margin: 2px 10px; border-radius: 10px; font-size: 0.88rem; position: relative;
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.05); color: white; padding-right: 28px; }
        .sidebar-menu a.active { background: var(--gradient); color: white; box-shadow: 0 8px 25px rgba(102,126,234,0.25); }
        .sidebar-menu a i { width: 20px; text-align: center; font-size: 0.95rem; }
        .sidebar-menu a .badge-count { margin-right: auto; background: var(--danger); color: white; padding: 2px 9px; border-radius: 20px; font-size: 0.68rem; font-weight: 700; min-width: 22px; text-align: center; }
        .sidebar-menu a.logout-item { color: #ef4444 !important; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.06); padding-top: 20px; border-radius: 0; }
        
        /* ========== MAIN ========== */
        .admin-main { flex: 1; margin-right: 265px; min-height: 100vh; display: flex; flex-direction: column; }
        
        /* Topbar */
        .admin-topbar {
            background: white; padding: 14px 28px; box-shadow: var(--shadow-xs);
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 50; gap: 20px;
        }
        .topbar-left { display: flex; align-items: center; gap: 16px; }
        .topbar-toggle { display: none; background: var(--primary-light); border: none; width: 42px; height: 42px; border-radius: 10px; font-size: 1.3rem; cursor: pointer; color: var(--primary); transition: var(--transition); }
        .topbar-toggle:hover { background: var(--primary); color: white; }
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .topbar-icon-btn { width: 40px; height: 40px; border-radius: 10px; border: none; background: var(--light); color: var(--text); cursor: pointer; transition: var(--transition); font-size: 1rem; display: flex; align-items: center; justify-content: center; position: relative; }
        .topbar-icon-btn:hover { background: var(--primary-light); color: var(--primary); }
        .topbar-icon-btn .dot { position: absolute; top: 7px; left: 7px; width: 9px; height: 9px; background: var(--danger); border-radius: 50%; border: 2px solid white; }
        .topbar-profile { display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 6px 12px; border-radius: 10px; transition: var(--transition); }
        .topbar-profile:hover { background: var(--light); }
        .topbar-profile img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
        .topbar-profile .name { font-weight: 700; font-size: 0.9rem; color: var(--dark); }
        
        /* Content */
        .admin-content { padding: 28px; flex: 1; }
        
        /* ========== STATS ROW ========== */
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 14px; margin-bottom: 28px; }
        .stat-cell {
            background: white; border-radius: var(--radius); padding: 20px 18px;
            box-shadow: var(--shadow-xs); border: 1px solid #f0f0f0; text-align: center;
            cursor: pointer; transition: var(--transition); text-decoration: none; color: inherit;
            position: relative; overflow: hidden;
        }
        .stat-cell::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; transition: var(--transition); }
        .stat-cell:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); }
        .stat-cell:hover::before { height: 4px; }
        .stat-cell.pending::before { background: #f59e0b; } .stat-cell.pending:hover { border-color: #f59e0b; }
        .stat-cell.confirmed::before { background: #3b82f6; } .stat-cell.confirmed:hover { border-color: #3b82f6; }
        .stat-cell.active::before { background: #10b981; } .stat-cell.active:hover { border-color: #10b981; }
        .stat-cell.completed::before { background: #8b5cf6; } .stat-cell.completed:hover { border-color: #8b5cf6; }
        .stat-cell.revenue::before { background: #c9a84c; } .stat-cell.revenue:hover { border-color: #c9a84c; }
        .stat-cell.active-filter { border-color: var(--primary) !important; background: var(--primary-light) !important; }
        .stat-cell .stat-icon { font-size: 1.5rem; margin-bottom: 8px; display: block; }
        .stat-cell .stat-value { font-size: 1.6rem; font-weight: 900; color: var(--dark); line-height: 1.2; }
        .stat-cell .stat-label { font-size: 0.75rem; color: var(--text-light); margin-top: 4px; font-weight: 500; }
        .stat-cell .stat-sub { font-size: 0.7rem; color: var(--success); margin-top: 3px; font-weight: 600; }
        
        /* ========== CONTENT CARD ========== */
        .content-card { background: white; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; overflow: hidden; }
        .card-header-bar { padding: 22px 28px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        .card-header-bar h4 { font-weight: 800; margin: 0; color: var(--dark); font-size: 1.15rem; display: flex; align-items: center; gap: 10px; }
        .card-header-bar h4 i { color: var(--primary); }
        .card-header-bar .total-badge { background: var(--primary-light); color: var(--primary); padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 0.85rem; }
        
        /* ========== FILTER BAR ========== */
        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .filter-bar .form-control, .filter-bar .form-select {
            border: 2px solid var(--border); border-radius: 10px; padding: 9px 14px;
            font-size: 0.84rem; font-family: 'Cairo', sans-serif; background: #fafafa;
            width: auto; min-width: 120px; transition: var(--transition); height: 42px;
        }
        .filter-bar .form-control:focus, .filter-bar .form-select:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.06); }
        .filter-bar .btn-filter { height: 42px; padding: 0 20px; border-radius: 10px; font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: var(--transition); border: none; display: flex; align-items: center; gap: 6px; }
        .btn-search { background: var(--gradient); color: white; } .btn-search:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.3); }
        .btn-reset { background: #e5e7eb; color: #333; text-decoration: none; } .btn-reset:hover { background: #d1d5db; }
        
        /* ========== TABLE ========== */
        .table-wrap { overflow-x: auto; }
        .table { margin: 0; width: 100%; border-collapse: collapse; }
        .table thead th {
            background: #f8f9fa; font-weight: 700; font-size: 0.76rem; color: var(--text-light);
            padding: 15px 16px; border-bottom: 2px solid var(--border); white-space: nowrap;
            text-transform: uppercase; letter-spacing: 0.8px;
        }
        .table tbody td { padding: 14px 16px; vertical-align: middle; font-size: 0.86rem; border-bottom: 1px solid #f5f5f5; }
        .table tbody tr { transition: var(--transition); }
        .table tbody tr:hover { background: #fafbff; }
        
        .cust-cell { display: flex; align-items: center; gap: 10px; }
        .cust-cell img { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid #f0f0f0; }
        .cust-cell h6 { font-weight: 700; margin: 0; font-size: 0.88rem; color: var(--dark); }
        .cust-cell small { color: var(--text-light); font-size: 0.76rem; }
        
        .car-cell { display: flex; align-items: center; gap: 10px; }
        .car-cell img { width: 58px; height: 42px; object-fit: cover; border-radius: 6px; }
        .car-cell h6 { font-weight: 700; margin: 0; font-size: 0.85rem; color: var(--dark); }
        .car-cell small { color: var(--text-light); font-size: 0.74rem; }
        
        /* ========== BADGES ========== */
        .badge-status {
            padding: 6px 14px; border-radius: 50px; font-weight: 700; font-size: 0.72rem;
            display: inline-flex; align-items: center; gap: 5px; letter-spacing: 0.3px; white-space: nowrap;
        }
        .badge-pending { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .badge-confirmed { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .badge-active { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .badge-completed { background: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; }
        .badge-cancelled { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        
        .badge-pay { padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 0.7rem; display: inline-flex; align-items: center; gap: 4px; }
        .badge-paid { background: #d1fae5; color: #065f46; } .badge-unpaid { background: #fef3c7; color: #92400e; }
        
        /* ========== ACTIONS ========== */
        .btn { font-weight: 600; border-radius: 8px; padding: 8px 15px; font-size: 0.8rem; cursor: pointer; transition: var(--transition); border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; white-space: nowrap; }
        .btn-xs { padding: 5px 10px; font-size: 0.7rem; border-radius: 6px; }
        .btn-gold { background: var(--gradient-gold); color: #1a1a2e; font-weight: 700; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(201,168,76,0.3); }
        .btn-success { background: #10b981; color: white; } .btn-success:hover { background: #059669; transform: translateY(-2px); }
        .btn-info { background: #3b82f6; color: white; } .btn-info:hover { background: #2563eb; transform: translateY(-2px); }
        .btn-warning { background: #f59e0b; color: #000; } .btn-warning:hover { background: #d97706; transform: translateY(-2px); }
        .btn-danger { background: #ef4444; color: white; } .btn-danger:hover { background: #dc2626; transform: translateY(-2px); }
        .btn-purple { background: #8b5cf6; color: white; } .btn-purple:hover { background: #7c3aed; transform: translateY(-2px); }
        .btn-outline { border: 2px solid var(--border); background: white; color: var(--text); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); }
        .action-group { display: flex; gap: 4px; flex-wrap: wrap; }
        
        /* ========== PAGINATION ========== */
        .pagination-wrap { display: flex; justify-content: space-between; align-items: center; padding: 18px 28px; border-top: 1px solid #f0f0f0; }
        .pagination-info { font-size: 0.85rem; color: var(--text-light); }
        .pagination { display: flex; gap: 5px; list-style: none; padding: 0; margin: 0; }
        .pagination .page-link { width: 38px; height: 38px; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid var(--border); color: var(--text); font-weight: 600; text-decoration: none; transition: var(--transition); font-size: 0.85rem; background: white; }
        .pagination .page-link:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
        .pagination .active .page-link { background: var(--gradient); color: white; border-color: transparent; box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
        .pagination .disabled .page-link { opacity: 0.4; pointer-events: none; }
        
        /* ========== ALERTS ========== */
        .alert-custom { border-radius: 12px; padding: 15px 20px; margin-bottom: 22px; border: none; font-weight: 500; display: flex; align-items: center; gap: 10px; font-size: 0.9rem; }
        
        /* ========== OVERLAY ========== */
        .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 99; backdrop-filter: blur(3px); }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 1600px) { .stats-grid { grid-template-columns: repeat(5, 1fr); } }
        @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(4, 1fr); } }
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(100%); z-index: 200; }
            .admin-sidebar.mobile-open { transform: translateX(0); }
            .admin-main { margin-right: 0; }
            .topbar-toggle { display: flex; }
            .sidebar-overlay.show { display: block; }
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
            .filter-bar { flex-direction: column; }
            .filter-bar .form-control, .filter-bar .form-select { width: 100%; }
        }
        @media (max-width: 768px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .card-header-bar { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 480px) { .stats-grid { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>
        
        <!-- ==================== SIDEBAR ==================== -->
        <aside class="admin-sidebar" id="adminSidebar">
            <div class="sidebar-header">
                <a href="index.php" class="sidebar-logo">
                    <span class="logo-icon">👑</span> <?php echo SITE_NAME; ?>
                </a>
            </div>
            <div class="sidebar-user">
                <div class="avatar-ring"><img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&size=44&background=667eea&color=fff&bold=true" alt=""></div>
                <div><h6><?php echo htmlspecialchars($user_name); ?></h6><span><?php echo $user_role==='super_admin'?'المدير العام':'مدير'; ?></span></div>
            </div>
            <nav class="sidebar-menu">
                <div class="menu-label">الرئيسية</div>
                <a href="index.php"><i class="fas fa-th-large"></i> لوحة التحكم</a>
                <a href="cars-management.php"><i class="fas fa-car"></i> السيارات</a>
                <a href="bookings-management.php" class="active"><i class="fas fa-calendar-check"></i> الحجوزات <?php if($stats['pending']>0): ?><span class="badge-count"><?php echo $stats['pending']; ?></span><?php endif; ?></a>
                <a href="customers-management.php"><i class="fas fa-users"></i> العملاء</a>
                <div class="menu-label">الإدارة</div>
                <a href="reports.php"><i class="fas fa-chart-pie"></i> التقارير</a>
                <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
                <a href="../" target="_blank"><i class="fas fa-eye"></i> معاينة الموقع</a>
                <a href="../logout.php" class="logout-item"><i class="fas fa-power-off"></i> تسجيل الخروج</a>
            </nav>
        </aside>
        
        <!-- ==================== MAIN ==================== -->
        <main class="admin-main">
            <header class="admin-topbar">
                <div class="topbar-left">
                    <button class="topbar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
                    <h5 class="mb-0 fw-bold" style="font-size:1.1rem;">📋 إدارة الحجوزات</h5>
                </div>
                <div class="topbar-right">
                    <button class="topbar-icon-btn" title="تحديث" onclick="location.reload()"><i class="fas fa-sync-alt"></i></button>
                    <button class="topbar-icon-btn" title="الإشعارات"><?php if($stats['pending']>0): ?><span class="dot"></span><?php endif; ?><i class="fas fa-bell"></i></button>
                    <div class="topbar-profile">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&size=38&background=667eea&color=fff&bold=true" alt="">
                        <span class="name"><?php echo htmlspecialchars($user_name); ?></span>
                    </div>
                </div>
            </header>
            
            <div class="admin-content">
                <!-- Messages -->
                <?php foreach(get_messages() as $msg): ?>
                <div class="alert-custom" style="background:<?php echo $msg['type']==='success'?'#d1fae5':($msg['type']==='danger'?'#fee2e2':($msg['type']==='warning'?'#fef3c7':'#dbeafe')); ?>;color:<?php echo $msg['type']==='success'?'#065f46':($msg['type']==='danger'?'#991b1b':($msg['type']==='warning'?'#92400e':'#1e40af')); ?>;">
                    <?php echo $msg['text']; ?>
                </div>
                <?php endforeach; ?>
                
                <!-- Stats -->
                <div class="stats-grid" data-aos="fade-up">
                    <a href="?status=pending" class="stat-cell pending <?php echo $status==='pending'?'active-filter':''; ?>">
                        <span class="stat-icon">🟡</span>
                        <div class="stat-value"><?php echo $stats['pending']; ?></div>
                        <div class="stat-label">قيد الانتظار</div>
                    </a>
                    <a href="?status=confirmed" class="stat-cell confirmed <?php echo $status==='confirmed'?'active-filter':''; ?>">
                        <span class="stat-icon">🔵</span>
                        <div class="stat-value"><?php echo $stats['confirmed']; ?></div>
                        <div class="stat-label">مؤكدة</div>
                    </a>
                    <a href="?status=active" class="stat-cell active <?php echo $status==='active'?'active-filter':''; ?>">
                        <span class="stat-icon">🟢</span>
                        <div class="stat-value"><?php echo $stats['active']; ?></div>
                        <div class="stat-label">نشطة</div>
                    </a>
                    <a href="?status=completed" class="stat-cell completed <?php echo $status==='completed'?'active-filter':''; ?>">
                        <span class="stat-icon">🟣</span>
                        <div class="stat-value"><?php echo $stats['completed']; ?></div>
                        <div class="stat-label">مكتملة</div>
                    </a>
                    <div class="stat-cell revenue">
                        <span class="stat-icon">👑</span>
                        <div class="stat-value"><?php echo number_format($stats['revenue_month'],0,',',' '); ?> DH</div>
                        <div class="stat-label">الإيرادات الشهرية</div>
                        <div class="stat-sub">اليوم: <?php echo number_format($stats['revenue_today'],0,',',' '); ?> DH</div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="content-card" data-aos="fade-up">
                    <div class="card-header-bar">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <h4><i class="fas fa-calendar-check"></i> قائمة الحجوزات</h4>
                            <span class="total-badge"><?php echo number_format($totalBookings); ?> حجز</span>
                        </div>
                        <form method="GET" class="filter-bar">
                            <input type="text" class="form-control" name="search" placeholder="🔍 بحث شامل..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:160px;">
                            <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>" title="من تاريخ">
                            <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>" title="إلى تاريخ">
                            <select class="form-select" name="payment" onchange="this.form.submit()">
                                <option value="">💰 كل المدفوعات</option>
                                <option value="paid" <?php echo $payment==='paid'?'selected':''; ?>>مدفوع</option>
                                <option value="pending" <?php echo $payment==='pending'?'selected':''; ?>>غير مدفوع</option>
                            </select>
                            <select class="form-select" name="car_brand" onchange="this.form.submit()">
                                <option value="">🚘 كل الماركات</option>
                                <?php foreach($car_brands as $cb): ?>
                                <option value="<?php echo htmlspecialchars($cb['brand']); ?>" <?php echo $car_brand===$cb['brand']?'selected':''; ?>><?php echo htmlspecialchars($cb['brand']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-filter btn-search"><i class="fas fa-search"></i> بحث</button>
                            <a href="bookings-management.php" class="btn-filter btn-reset"><i class="fas fa-redo"></i></a>
                        </form>
                    </div>
                    
                    <div class="table-wrap">
                        <?php if(empty($bookings)): ?>
                        <div style="text-align:center;padding:70px 20px;">
                            <i class="fas fa-calendar-xmark" style="font-size:5rem;color:#e0e0e0;display:block;margin-bottom:20px;"></i>
                            <h5 style="color:var(--dark);">لا توجد حجوزات مطابقة</h5>
                            <p class="text-muted">جرب تغيير معايير البحث</p>
                        </div>
                        <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>رقم الحجز</th>
                                    <th>العميل</th>
                                    <th>السيارة</th>
                                    <th>من ← إلى</th>
                                    <th>أيام</th>
                                    <th>المبلغ</th>
                                    <th>الدفع</th>
                                    <th>الحالة</th>
                                    <th style="min-width:200px;">الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($bookings as $b): 
                                    $car_thumb = admin_car_img($b['car_image']??'');
                                    $sClass = match($b['booking_status']){'pending'=>'badge-pending','confirmed'=>'badge-confirmed','active'=>'badge-active','completed'=>'badge-completed','cancelled'=>'badge-cancelled',default=>'badge-pending'};
                                    $sText = match($b['booking_status']){'pending'=>'قيد الانتظار','confirmed'=>'مؤكد','active'=>'نشط','completed'=>'مكتمل','cancelled'=>'ملغي',default=>$b['booking_status']};
                                    $sIcon = match($b['booking_status']){'pending'=>'fa-clock','confirmed'=>'fa-check-circle','active'=>'fa-play-circle','completed'=>'fa-flag-checkered','cancelled'=>'fa-ban',default=>'fa-circle'};
                                    $pClass = $b['payment_status']==='paid'?'badge-paid':'badge-unpaid';
                                    $pText = $b['payment_status']==='paid'?'مدفوع':'غير مدفوع';
                                ?>
                                <tr>
                                    <td><strong style="color:var(--primary);"><?php echo $b['booking_number']; ?></strong><br><small style="font-size:0.7rem;color:var(--text-light);"><?php echo format_date($b['created_at'],'d/m/Y'); ?></small></td>
                                    <td>
                                        <div class="cust-cell">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($b['full_name']); ?>&size=38&background=random&bold=true" alt="">
                                            <div><h6><?php echo htmlspecialchars($b['full_name']); ?></h6><small><?php echo htmlspecialchars($b['phone']); ?></small></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="car-cell">
                                            <img src="<?php echo $car_thumb; ?>" alt="" onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=58&h=42&fit=crop'">
                                            <div><h6><?php echo htmlspecialchars($b['brand'].' '.$b['model']); ?></h6><small><?php echo $b['year']; ?> | <?php echo htmlspecialchars($b['color']??''); ?></small></div>
                                        </div>
                                    </td>
                                    <td><span style="font-size:0.82rem;"><?php echo format_date($b['pickup_date'],'d/m/Y'); ?></span><br><span style="font-size:0.82rem;"><?php echo format_date($b['return_date'],'d/m/Y'); ?></span></td>
                                    <td><strong><?php echo $b['total_days']; ?></strong></td>
                                    <td><strong style="color:var(--primary);font-size:0.95rem;"><?php echo number_format($b['total_amount'],2); ?> DH</strong></td>
                                    <td><span class="badge-pay <?php echo $pClass; ?>"><i class="fas fa-<?php echo $b['payment_status']==='paid'?'check':'clock'; ?>"></i> <?php echo $pText; ?></span></td>
                                    <td><span class="badge-status <?php echo $sClass; ?>"><i class="fas <?php echo $sIcon; ?>"></i> <?php echo $sText; ?></span></td>
                                    <td>
                                        <div class="action-group">
                                            <?php if($b['booking_status']==='pending'): ?>
                                            <a href="send-contract.php?id=<?php echo $b['id']; ?>" class="btn btn-gold btn-xs" onclick="return confirm('تأكيد وإرسال العقد؟')" title="قبول وإرسال العقد"><i class="fas fa-file-contract"></i> عقد</a>
                                            <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="confirm"><button type="submit" class="btn btn-info btn-xs" title="تأكيد فقط"><i class="fas fa-check"></i></button></form>
                                            <?php endif; ?>
                                            <?php if($b['booking_status']==='confirmed'): ?>
                                            <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="activate"><button type="submit" class="btn btn-success btn-xs" title="تفعيل"><i class="fas fa-play"></i> تفعيل</button></form>
                                            <?php endif; ?>
                                            <?php if($b['booking_status']==='active'): ?>
                                            <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="complete"><button type="submit" class="btn btn-purple btn-xs" title="إنهاء"><i class="fas fa-flag-checkered"></i> إنهاء</button></form>
                                            <?php endif; ?>
                                            <?php if($b['payment_status']!=='paid' && !in_array($b['booking_status'],['cancelled','completed'])): ?>
                                            <form method="POST" style="display:inline;"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="mark_paid"><button type="submit" class="btn btn-success btn-xs" title="تحديد كمدفوع"><i class="fas fa-money-bill"></i></button></form>
                                            <?php endif; ?>
                                            <?php if(!in_array($b['booking_status'],['completed','cancelled'])): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('إلغاء هذا الحجز؟')"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="cancel"><button type="submit" class="btn btn-warning btn-xs" title="إلغاء"><i class="fas fa-ban"></i></button></form>
                                            <?php endif; ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('حذف نهائي؟ لا يمكن التراجع!')"><input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>"><input type="hidden" name="action" value="delete"><button type="submit" class="btn btn-danger btn-xs" title="حذف"><i class="fas fa-trash"></i></button></form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    
                    <?php if($totalPages>1): ?>
                    <div class="pagination-wrap">
                        <div class="pagination-info">عرض <?php echo min($perPage, $totalBookings); ?> من <?php echo number_format($totalBookings); ?> حجز</div>
                        <ul class="pagination">
                            <li class="page-item <?php echo $page<=1?'disabled':''; ?>"><a class="page-link" href="<?php echo $page>1?build_admin_page_url($page-1):'#'; ?>"><i class="fas fa-chevron-right"></i></a></li>
                            <?php for($i=1;$i<=$totalPages;$i++): ?>
                            <li class="page-item <?php echo $i==$page?'active':''; ?>"><a class="page-link" href="<?php echo build_admin_page_url($i); ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page>=$totalPages?'disabled':''; ?>"><a class="page-link" href="<?php echo $page<$totalPages?build_admin_page_url($page+1):'#'; ?>"><i class="fas fa-chevron-left"></i></a></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/ar.js"></script>
    <script>
        AOS.init({duration:600,once:true});
        flatpickr("input[type='date']",{locale:"ar",dateFormat:"Y-m-d"});
        function toggleSidebar(){document.getElementById('adminSidebar').classList.toggle('mobile-open');document.getElementById('sidebarOverlay').classList.toggle('show');}
    </script>
</body>
</html>