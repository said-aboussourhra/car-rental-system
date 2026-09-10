<?php
/**
 * Premium Car Rental - السيارات المفضلة
 */
require_once '../includes/config.php';

if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// إزالة من المفضلة
if (isset($_GET['remove'])) {
    try {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = :uid AND car_id = :cid")
            ->execute([':uid' => $user_id, ':cid' => intval($_GET['remove'])]);
    } catch (Exception $e) {}
    header('Location: my-favorites.php');
    exit();
}

// جلب المفضلة
$favorites = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.*,
               (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image,
               (SELECT AVG(rating) FROM reviews WHERE car_id = c.id AND status = 'approved') as avg_rating,
               f.created_at as favorited_at
        FROM favorites f
        JOIN cars c ON c.id = f.car_id
        WHERE f.user_id = :uid
        ORDER BY f.id DESC
    ");
    $stmt->execute([':uid' => $user_id]);
    $favorites = $stmt->fetchAll();
} catch (Exception $e) {}

function fav_img($path) {
    if (empty($path)) return 'https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=500&h=350&fit=crop';
    if (filter_var($path, FILTER_VALIDATE_URL)) return $path;
    return '../uploads/cars/' . $path;
}

$type_labels = [
    'economy' => 'اقتصادية', 'suv' => 'دفع رباعي', 'luxury' => 'فاخرة',
    'compact' => 'مدمجة', 'sedan' => 'سيدان', 'van' => 'عائلية', 'sport' => 'رياضية'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مفضلتي | <?php echo SITE_NAME; ?></title>
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
        .page-head { background:var(--gradient); color:#fff; border-radius:var(--radius); padding:26px 30px; margin-bottom:25px; }
        .page-head h3 { font-weight:900; margin:0; }
        .fav-card { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; transition:.3s; height:100%; }
        .fav-card:hover { transform:translateY(-6px); box-shadow:0 15px 35px rgba(0,0,0,.1); }
        .fav-img { height:190px; position:relative; overflow:hidden; }
        .fav-img img { width:100%; height:100%; object-fit:cover; transition:.4s; }
        .fav-card:hover .fav-img img { transform:scale(1.06); }
        .price-tag { position:absolute; bottom:10px; right:10px; background:rgba(26,26,46,.85); color:#fff; padding:5px 14px; border-radius:10px; font-size:.82rem; font-weight:700; }
        .fav-body { padding:18px; }
        .fav-body h6 { font-weight:800; color:var(--dark); }
        .specs { display:flex; gap:12px; flex-wrap:wrap; color:#6b7280; font-size:.78rem; margin:10px 0; }
        .fav-actions { display:flex; gap:8px; margin-top:14px; }
        .fav-actions a { flex:1; text-align:center; padding:9px; border-radius:10px; font-size:.82rem; font-weight:700; text-decoration:none; }
        .a-book { background:var(--gradient); color:#fff; }
        .a-view { background:#eef0ff; color:var(--primary); }
        .a-del { background:#fef2f2; color:#ef4444; flex:0 0 44px!important; }
        .a-del:hover { background:#ef4444; color:#fff; }
        .empty { text-align:center; padding:70px 20px; color:#9ca3af; background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); }
        .empty i { font-size:4rem; color:#e5e7eb; margin-bottom:15px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
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
                    <li class="nav-item"><a class="nav-link active" href="my-favorites.php"><i class="fas fa-heart me-1"></i> مفضلتي</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </div>
    </nav>

    <section class="section">
        <div class="container">
            <div class="page-head d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h3><i class="fas fa-heart me-2"></i> سياراتي المفضلة</h3>
                <span class="badge bg-white text-primary" style="font-size:.9rem;"><?php echo count($favorites); ?> سيارة</span>
            </div>

            <?php if (empty($favorites)): ?>
                <div class="empty">
                    <i class="far fa-heart"></i>
                    <h5 style="font-weight:800;color:#6b7280;">قائمة المفضلة فارغة</h5>
                    <p>اضغط على أيقونة القلب ♥ في أي سيارة لإضافتها هنا.</p>
                    <a href="../cars.php" class="btn btn-primary mt-2"><i class="fas fa-car me-1"></i> تصفح السيارات</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($favorites as $car): ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="fav-card">
                            <div class="fav-img">
                                <img src="<?php echo fav_img($car['main_image']); ?>" alt="<?php echo e($car['brand']); ?>"
                                     onerror="this.src='https://images.unsplash.com/photo-1543465077-db45d34b88a5?w=500&h=350&fit=crop'">
                                <span class="price-tag"><?php echo format_amount($car['daily_rate']); ?> / يوم</span>
                            </div>
                            <div class="fav-body">
                                <h6><?php echo e($car['brand'] . ' ' . $car['model']); ?> <small class="text-muted">(<?php echo $car['year']; ?>)</small></h6>
                                <div class="specs">
                                    <span><i class="fas fa-tag"></i> <?php echo $type_labels[$car['type']] ?? e($car['type']); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo $car['seats']; ?> مقاعد</span>
                                    <span><i class="fas fa-cogs"></i> <?php echo $car['transmission'] === 'automatic' ? 'أوتوماتيك' : 'عادي'; ?></span>
                                    <?php if ($car['avg_rating']): ?>
                                    <span style="color:#f59e0b;"><i class="fas fa-star"></i> <?php echo number_format($car['avg_rating'], 1); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="fav-actions">
                                    <a href="../booking.php?car_id=<?php echo $car['id']; ?>" class="a-book"><i class="fas fa-calendar-check me-1"></i> احجز الآن</a>
                                    <a href="../car-details.php?id=<?php echo $car['id']; ?>" class="a-view"><i class="fas fa-eye me-1"></i> تفاصيل</a>
                                    <a href="my-favorites.php?remove=<?php echo $car['id']; ?>" class="a-del"
                                       onclick="return confirm('إزالة هذه السيارة من المفضلة؟')"><i class="fas fa-trash"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
