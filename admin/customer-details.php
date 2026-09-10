<?php
/**
 * لوحة الإدارة - تفاصيل العميل
 */
require_once '../includes/config.php';

if (!is_admin()) {
    header('Location: ../login.php');
    exit();
}

$customer_id = intval($_GET['id'] ?? 0);
if ($customer_id <= 0) {
    header('Location: customers-management.php');
    exit();
}

// ============================================
// تغيير حالة العميل
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    require_valid_csrf();
    $new_status = $_POST['new_status'] ?? '';
    if (in_array($new_status, ['active', 'inactive', 'banned'])) {
        try {
            // لا يمكن تعديل حساب مدير من هنا
            $stmt = $pdo->prepare("UPDATE users SET status = :st, updated_at = NOW() WHERE id = :id AND role = 'customer'");
            $stmt->execute([':st' => $new_status, ':id' => $customer_id]);
            log_activity($_SESSION['user_id'], 'admin_customer_status', "تغيير حالة العميل #$customer_id إلى $new_status");
            set_message('تم تحديث حالة العميل بنجاح', 'success');
        } catch (Exception $e) {
            set_message('حدث خطأ أثناء التحديث', 'danger');
        }
        redirect("customer-details.php?id=$customer_id");
    }
}

// ============================================
// بيانات العميل
// ============================================
try {
    $stmt = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id) as total_bookings,
               (SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE user_id = u.id AND payment_status = 'paid') as total_spent,
               (SELECT COUNT(*) FROM bookings WHERE user_id = u.id AND booking_status = 'active') as active_bookings,
               (SELECT COUNT(*) FROM favorites WHERE user_id = u.id) as favorites_count,
               (SELECT MAX(created_at) FROM bookings WHERE user_id = u.id) as last_booking_date
        FROM users u WHERE u.id = :id
    ");
    $stmt->execute([':id' => $customer_id]);
    $cust = $stmt->fetch();
} catch (Exception $e) {
    $cust = null;
}

if (!$cust) {
    set_message('العميل غير موجود', 'danger');
    redirect('customers-management.php');
}

// حجوزات العميل
$bookings = [];
try {
    $stmt = $pdo->prepare("
        SELECT b.*, c.brand, c.model,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as car_image
        FROM bookings b JOIN cars c ON c.id = b.car_id
        WHERE b.user_id = :uid ORDER BY b.created_at DESC LIMIT 50
    ");
    $stmt->execute([':uid' => $customer_id]);
    $bookings = $stmt->fetchAll();
} catch (Exception $e) {}

// آخر نشاطات العميل
$activities = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM activity_logs WHERE user_id = :uid ORDER BY id DESC LIMIT 10");
    $stmt->execute([':uid' => $customer_id]);
    $activities = $stmt->fetchAll();
} catch (Exception $e) {}

$badge = ['active' => ['badge-active', 'نشط'], 'inactive' => ['badge-inactive', 'غير نشط'], 'banned' => ['badge-banned', 'محظور']];
$st = $badge[$cust['status']] ?? $badge['inactive'];
$booking_badge = [
    'pending' => ['background:#fef3c7;color:#92400e;', 'قيد الانتظار'],
    'confirmed' => ['background:#dbeafe;color:#1e40af;', 'مؤكد'],
    'active' => ['background:#d1fae5;color:#065f46;', 'نشط'],
    'completed' => ['background:#e0e7ff;color:#3730a3;', 'مكتمل'],
    'cancelled' => ['background:#fee2e2;color:#991b1b;', 'ملغى'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل العميل | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root { --primary:#667eea; --gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
                --dark:#1a1a2e; --text:#333; --text-light:#6c757d; --border:#e0e0e0;
                --shadow:0 5px 20px rgba(0,0,0,.06); --radius:16px; --transition:all .3s ease; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Cairo',sans-serif; background:#f5f6fa; color:var(--text); }
        .admin-wrapper { display:flex; min-height:100vh; }
        .admin-sidebar { width:250px; background:var(--dark); position:fixed; top:0; right:0; bottom:0; z-index:1000; overflow-y:auto; }
        .sidebar-brand { display:flex; align-items:center; gap:10px; color:#fff; text-decoration:none; font-weight:800; padding:20px; font-size:1.05rem; }
        .sidebar-brand i { color:var(--primary); }
        .sidebar-menu { padding:10px 0; }
        .sidebar-menu a { display:flex; align-items:center; gap:10px; padding:12px 20px; color:rgba(255,255,255,.7);
                          text-decoration:none; font-weight:500; margin:2px 10px; border-radius:10px; font-size:.9rem; }
        .sidebar-menu a:hover { background:rgba(255,255,255,.1); color:#fff; }
        .sidebar-menu a.active { background:var(--gradient); color:#fff; }
        .sidebar-menu a i { width:20px; text-align:center; }
        .admin-main { flex:1; margin-right:250px; }
        .admin-topbar { background:#fff; padding:15px 25px; box-shadow:0 2px 10px rgba(0,0,0,.05);
                        display:flex; justify-content:space-between; align-items:center; position:sticky; top:0; z-index:100; }
        .admin-content { padding:25px; }
        .card-panel { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:24px; height:100%; }
        .card-panel h5 { font-weight:800; color:var(--dark); }
        .stat-mini { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:18px; text-align:center; }
        .stat-mini .value { font-size:1.5rem; font-weight:900; color:var(--dark); }
        .stat-mini .label { font-size:.78rem; color:var(--text-light); }
        .profile-hero { background:var(--gradient); border-radius:var(--radius); color:#fff; padding:30px; }
        .profile-hero img { width:90px; height:90px; border-radius:50%; object-fit:cover; border:4px solid rgba(255,255,255,.35); }
        .info-row { display:flex; justify-content:space-between; padding:11px 0; border-bottom:1px dashed #eee; font-size:.88rem; }
        .info-row:last-child { border-bottom:none; }
        .info-row b { color:var(--dark); }
        .info-row span { color:var(--text-light); }
        .badge-status { padding:5px 14px; border-radius:50px; font-weight:700; font-size:.75rem; }
        .badge-active { background:#d1fae5; color:#065f46; }
        .badge-inactive { background:#fef3c7; color:#92400e; }
        .badge-banned { background:#fee2e2; color:#991b1b; }
        .table { margin:0; }
        .table th { font-weight:700; font-size:.78rem; color:var(--text-light); padding:12px; border-bottom:2px solid var(--border); white-space:nowrap; }
        .table td { padding:12px; vertical-align:middle; font-size:.86rem; }
        .table tr:hover { background:#fafbff; }
        .btn { font-weight:600; border-radius:8px; padding:8px 16px; font-size:.85rem; }
        .btn-primary { background:var(--gradient); border:none; color:#fff; }
        .btn-xs { padding:4px 10px; font-size:.72rem; border-radius:6px; }
        .activity-item { display:flex; gap:12px; padding:10px 0; border-bottom:1px dashed #eee; font-size:.85rem; }
        .activity-item:last-child { border-bottom:none; }
        .activity-item i { color:var(--primary); margin-top:4px; }
        @media (max-width: 991px) {
            .admin-sidebar { transform:translateX(100%); transition:.3s; }
            .admin-sidebar.mobile-open { transform:translateX(0); }
            .admin-main { margin-right:0; }
        }
    </style>
</head>
<body>
<div class="admin-wrapper">
    <aside class="admin-sidebar" id="adminSidebar">
        <a href="index.php" class="sidebar-brand"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
            <a href="cars-management.php"><i class="fas fa-car"></i> إدارة السيارات</a>
            <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
            <a href="customers-management.php" class="active"><i class="fas fa-users"></i> العملاء</a>
            <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
            <a href="settings.php"><i class="fas fa-cog"></i> الإعدادات</a>
            <hr style="border-color:rgba(255,255,255,.1);margin:15px 0;">
            <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
        </nav>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <button class="btn btn-light d-lg-none" onclick="document.getElementById('adminSidebar').classList.toggle('mobile-open')"><i class="fas fa-bars"></i></button>
            <h5 class="mb-0 fw-bold"><a href="customers-management.php" class="text-decoration-none text-muted"><i class="fas fa-arrow-right me-1"></i></a> تفاصيل العميل</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold"><?php echo e($_SESSION['user_name'] ?? 'Admin'); ?></span>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'Admin'); ?>&size=35&background=667eea&color=fff&bold=true" class="rounded-circle" width="35" height="35" alt="">
            </div>
        </header>

        <div class="admin-content">
            <?php foreach (get_messages() as $msg): ?>
            <div class="alert alert-<?php echo e($msg['type']); ?> alert-dismissible fade show">
                <?php echo e($msg['text']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endforeach; ?>

            <!-- بطاقة العميل -->
            <div class="profile-hero mb-4 d-flex flex-wrap align-items-center gap-4">
                <img src="<?php echo !empty($cust['avatar']) ? '../uploads/avatars/' . e($cust['avatar']) : 'https://ui-avatars.com/api/?name=' . urlencode($cust['full_name']) . '&size=128&background=fff&color=667eea&bold=true'; ?>" alt="">
                <div class="flex-grow-1">
                    <h3 class="fw-bold mb-1"><?php echo e($cust['full_name']); ?>
                        <span class="badge-status <?php echo $st[0]; ?>"><?php echo $st[1]; ?></span>
                    </h3>
                    <p class="mb-1 opacity-75"><i class="fas fa-envelope me-1"></i> <?php echo e($cust['email']); ?></p>
                    <p class="mb-0 opacity-75"><i class="fas fa-phone me-1"></i> <?php echo e($cust['phone']); ?>
                        <?php if ($cust['city']): ?> · <i class="fas fa-map-marker-alt me-1"></i> <?php echo e($cust['city']); ?><?php endif; ?>
                    </p>
                </div>
                <div>
                    <form method="POST" class="d-flex gap-2 flex-wrap">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="change_status" value="1">
                        <?php if ($cust['status'] !== 'active'): ?>
                            <button name="new_status" value="active" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i> تفعيل</button>
                        <?php endif; ?>
                        <?php if ($cust['status'] !== 'inactive'): ?>
                            <button name="new_status" value="inactive" class="btn btn-warning btn-sm"><i class="fas fa-pause me-1"></i> تعطيل</button>
                        <?php endif; ?>
                        <?php if ($cust['status'] !== 'banned'): ?>
                            <button name="new_status" value="banned" class="btn btn-danger btn-sm" onclick="return confirm('حظر هذا العميل؟')"><i class="fas fa-ban me-1"></i> حظر</button>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- إحصائيات -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3"><div class="stat-mini"><div class="value"><?php echo number_format($cust['total_bookings']); ?></div><div class="label">إجمالي الحجوزات</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-mini"><div class="value"><?php echo number_format($cust['active_bookings']); ?></div><div class="label">حجوزات نشطة</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-mini"><div class="value"><?php echo format_amount($cust['total_spent']); ?></div><div class="label">إجمالي المدفوعات</div></div></div>
                <div class="col-6 col-md-3"><div class="stat-mini"><div class="value"><?php echo number_format($cust['favorites_count']); ?></div><div class="label">في المفضلة</div></div></div>
            </div>

            <div class="row g-4">
                <!-- معلومات -->
                <div class="col-lg-4">
                    <div class="card-panel">
                        <h5 class="mb-3"><i class="fas fa-id-card me-2 text-primary"></i> معلومات الحساب</h5>
                        <div class="info-row"><span>رقم العميل</span><b>#<?php echo $cust['id']; ?></b></div>
                        <div class="info-row"><span>البطاقة الوطنية</span><b><?php echo e($cust['cin'] ?: '—'); ?></b></div>
                        <div class="info-row"><span>رخصة السياقة</span><b><?php echo e($cust['license_number'] ?: '—'); ?></b></div>
                        <div class="info-row"><span>تاريخ الميلاد</span><b><?php echo $cust['birth_date'] ? format_date($cust['birth_date']) : '—'; ?></b></div>
                        <div class="info-row"><span>العنوان</span><b><?php echo e($cust['address'] ?: '—'); ?></b></div>
                        <div class="info-row"><span>تاريخ التسجيل</span><b><?php echo format_date($cust['created_at'], 'd/m/Y H:i'); ?></b></div>
                        <div class="info-row"><span>آخر دخول</span><b><?php echo $cust['last_login'] ? format_date($cust['last_login'], 'd/m/Y H:i') : '—'; ?></b></div>
                        <div class="info-row"><span>آخر حجز</span><b><?php echo $cust['last_booking_date'] ? format_date($cust['last_booking_date'], 'd/m/Y') : '—'; ?></b></div>
                    </div>
                </div>

                <!-- الحجوزات -->
                <div class="col-lg-8">
                    <div class="card-panel">
                        <h5 class="mb-3"><i class="fas fa-calendar-check me-2 text-primary"></i> سجل الحجوزات (<?php echo count($bookings); ?>)</h5>
                        <?php if (empty($bookings)): ?>
                            <p class="text-muted text-center py-4">لا توجد حجوزات لهذا العميل</p>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr><th>الرقم</th><th>السيارة</th><th>التواريخ</th><th>المبلغ</th><th>الدفع</th><th>الحالة</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $b):
                                        $bb = $booking_badge[$b['booking_status']] ?? $booking_badge['pending'];
                                    ?>
                                    <tr>
                                        <td><b><?php echo e($b['booking_number']); ?></b></td>
                                        <td><?php echo e($b['brand'] . ' ' . $b['model']); ?></td>
                                        <td><small><?php echo format_date($b['pickup_date'], 'd/m/Y'); ?> ← <?php echo format_date($b['return_date'], 'd/m/Y'); ?></small></td>
                                        <td><b><?php echo format_amount($b['total_amount']); ?></b></td>
                                        <td><?php echo $b['payment_status'] === 'paid' ? '<i class="fas fa-check-circle text-success"></i> مدفوع' : '<i class="fas fa-clock text-warning"></i> معلق'; ?></td>
                                        <td><span class="badge-status" style="<?php echo $bb[0]; ?>"><?php echo $bb[1]; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- آخر النشاطات -->
            <?php if (!empty($activities)): ?>
            <div class="card-panel mt-4">
                <h5 class="mb-3"><i class="fas fa-history me-2 text-primary"></i> آخر نشاطات العميل</h5>
                <?php foreach ($activities as $act): ?>
                <div class="activity-item">
                    <i class="fas fa-circle-notch"></i>
                    <div class="flex-grow-1">
                        <b><?php echo e($act['action']); ?></b> — <?php echo e($act['description']); ?>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo format_date($act['created_at'], 'd/m/Y H:i'); ?> · IP: <?php echo e($act['ip_address']); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
