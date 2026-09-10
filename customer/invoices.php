<?php
/**
 * Premium Car Rental - فواتير العميل
 */
require_once '../includes/config.php';

if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'العميل';

// عرض فاتورة واحدة؟
$view_invoice = null;
if (isset($_GET['view'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, b.booking_number, b.total_days, b.pickup_date, b.return_date,
                   c.brand, c.model, c.year
            FROM invoices i
            JOIN bookings b ON b.id = i.booking_id
            JOIN cars c ON c.id = b.car_id
            WHERE i.id = :id AND b.user_id = :uid
        ");
        $stmt->execute([':id' => intval($_GET['view']), ':uid' => $user_id]);
        $view_invoice = $stmt->fetch();
    } catch (Exception $e) {}
}

// جلب كل الفواتير
$invoices = [];
try {
    $stmt = $pdo->prepare("
        SELECT i.*, b.booking_number, c.brand, c.model
        FROM invoices i
        JOIN bookings b ON b.id = i.booking_id
        JOIN cars c ON c.id = b.car_id
        WHERE b.user_id = :uid
        ORDER BY i.invoice_date DESC, i.id DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $invoices = $stmt->fetchAll();
} catch (Exception $e) {}

$statusLabel = [
    'paid' => ['مدفوعة', '#10b981', '#ecfdf5'],
    'unpaid' => ['غير مدفوعة', '#f59e0b', '#fffbeb'],
    'overdue' => ['متأخرة', '#ef4444', '#fef2f2'],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فواتيري | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root { --primary:#667eea; --gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%); --dark:#1a1a2e;
                --radius:16px; --shadow:0 5px 20px rgba(0,0,0,.06); }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Cairo',sans-serif; background:#f5f6fa; color:#333; }
        .navbar { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.05); padding:12px 0; position:sticky; top:0; z-index:1000; }
        .navbar-brand { display:flex; align-items:center; gap:8px; font-weight:900; font-size:1.2rem; color:var(--dark)!important; text-decoration:none; }
        .navbar-brand i { color:var(--primary); font-size:1.4rem; }
        .nav-link { font-weight:600; color:#333!important; padding:8px 16px!important; border-radius:8px; font-size:.9rem; }
        .nav-link:hover, .nav-link.active { color:var(--primary)!important; background:#eef0ff; }
        .btn { font-weight:600; border-radius:10px; padding:10px 20px; font-size:.88rem; }
        .btn-primary { background:var(--gradient); border:none; color:#fff; }
        .btn-primary:hover { color:#fff; transform:translateY(-2px); box-shadow:0 8px 25px rgba(102,126,234,.4); }
        .section { padding:35px 0 60px; }
        .panel { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .panel-head { background:var(--gradient); color:#fff; padding:22px 26px; }
        .panel-head h4 { font-weight:900; margin:0; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fb; font-size:.8rem; color:#6b7280; padding:13px 16px; text-align:right; font-weight:700; }
        td { padding:15px 16px; border-top:1px solid #f0f0f5; font-size:.9rem; vertical-align:middle; }
        tr:hover td { background:#fafbff; }
        .badge-st { padding:5px 14px; border-radius:20px; font-size:.75rem; font-weight:700; }
        .btn-view { padding:7px 14px; border-radius:8px; background:#eef0ff; color:var(--primary); font-size:.8rem; font-weight:700; text-decoration:none; }
        .btn-view:hover { background:var(--primary); color:#fff; }
        .empty { text-align:center; padding:60px 20px; color:#9ca3af; }
        .empty i { font-size:3.5rem; margin-bottom:15px; color:#d1d5db; }
        .invoice-modal { background:#fff; border-radius:var(--radius); box-shadow:0 30px 80px rgba(0,0,0,.25); max-width:640px; margin:40px auto; padding:0; overflow:hidden; }
        @media print {
            .navbar, .no-print { display:none!important; }
            body { background:#fff; }
            .invoice-modal { box-shadow:none; margin:0; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg no-print">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i> الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a class="nav-link" href="my-bookings.php"><i class="fas fa-calendar-alt me-1"></i> حجوزاتي</a></li>
                    <li class="nav-item"><a class="nav-link active" href="invoices.php"><i class="fas fa-file-invoice me-1"></i> فواتيري</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </div>
    </nav>

    <section class="section">
        <div class="container">
            <?php if ($view_invoice): ?>
                <!-- عرض فاتورة -->
                <div class="invoice-modal">
                    <div class="panel-head d-flex justify-content-between align-items-center">
                        <div>
                            <h4><i class="fas fa-file-invoice-dollar me-2"></i> فاتورة ضريبية</h4>
                            <small style="opacity:.85;">رقم: <?php echo e($view_invoice['invoice_number']); ?></small>
                        </div>
                        <div class="text-end">
                            <div style="font-weight:900;"><?php echo SITE_NAME; ?></div>
                            <small style="opacity:.85;"><?php echo format_date($view_invoice['invoice_date'], 'd/m/Y'); ?></small>
                        </div>
                    </div>
                    <div style="padding:28px;">
                        <div class="row mb-4">
                            <div class="col-6">
                                <small class="text-muted d-block">الفاتورة إلى</small>
                                <b><?php echo e($user_name); ?></b>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block">رقم الحجز</small>
                                <b><?php echo e($view_invoice['booking_number']); ?></b>
                            </div>
                        </div>

                        <table class="mb-4">
                            <thead>
                                <tr><th>البيان</th><th>التفاصيل</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>السيارة</td><td><b><?php echo e($view_invoice['brand'] . ' ' . $view_invoice['model'] . ' ' . $view_invoice['year']); ?></b></td></tr>
                                <tr><td>مدة الإيجار</td><td><?php echo format_date($view_invoice['pickup_date'], 'd/m/Y'); ?> ← <?php echo format_date($view_invoice['return_date'], 'd/m/Y'); ?> (<?php echo $view_invoice['total_days']; ?> يوم)</td></tr>
                                <tr><td>المبلغ قبل الضريبة</td><td><?php echo format_amount($view_invoice['amount']); ?></td></tr>
                                <tr><td>الضريبة (<?php echo TAX_RATE; ?>%)</td><td><?php echo format_amount($view_invoice['tax_amount']); ?></td></tr>
                                <tr style="background:#eef0ff;"><td><b>المجموع الكلي</b></td><td><b style="color:var(--primary);font-size:1.05rem;"><?php echo format_amount($view_invoice['total_amount']); ?></b></td></tr>
                            </tbody>
                        </table>

                        <div class="d-flex gap-2 no-print">
                            <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print me-1"></i> طباعة</button>
                            <a href="invoices.php" class="btn btn-outline-secondary">العودة للقائمة</a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- قائمة الفواتير -->
                <div class="panel">
                    <div class="panel-head">
                        <h4><i class="fas fa-file-invoice me-2"></i> فواتيري (<?php echo count($invoices); ?>)</h4>
                    </div>
                    <?php if (empty($invoices)): ?>
                        <div class="empty">
                            <i class="fas fa-file-invoice"></i>
                            <h5 style="font-weight:800;color:#6b7280;">لا توجد فواتير بعد</h5>
                            <p>ستظهر فواتيرك هنا بعد إتمام أول عملية دفع.</p>
                            <a href="../cars.php" class="btn btn-primary">تصفح السيارات</a>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>رقم الفاتورة</th>
                                    <th>التاريخ</th>
                                    <th>السيارة</th>
                                    <th>الحجز</th>
                                    <th>المبلغ</th>
                                    <th>الحالة</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $inv):
                                    $st = $statusLabel[$inv['status']] ?? $statusLabel['unpaid'];
                                ?>
                                <tr>
                                    <td><b><?php echo e($inv['invoice_number']); ?></b></td>
                                    <td><?php echo format_date($inv['invoice_date'], 'd/m/Y'); ?></td>
                                    <td><?php echo e($inv['brand'] . ' ' . $inv['model']); ?></td>
                                    <td><small><?php echo e($inv['booking_number']); ?></small></td>
                                    <td><b><?php echo format_amount($inv['total_amount']); ?></b></td>
                                    <td><span class="badge-st" style="background:<?php echo $st[2]; ?>;color:<?php echo $st[1]; ?>;"><?php echo $st[0]; ?></span></td>
                                    <td><a href="invoices.php?view=<?php echo $inv['id']; ?>" class="btn-view"><i class="fas fa-eye me-1"></i> عرض</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
