<?php
/**
 * Premium Car Rental - Edit Car
 */
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

$car_id = intval($_GET['id'] ?? 0);
if ($car_id <= 0) {
    header('Location: cars-management.php');
    exit();
}

// Fetch car
try {
    $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
    $stmt->execute([$car_id]);
    $car = $stmt->fetch();
    if (!$car) {
        header('Location: cars-management.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: cars-management.php');
    exit();
}

// Fetch images
try {
    $stmt = $pdo->prepare("SELECT * FROM car_images WHERE car_id = ? ORDER BY sort_order ASC");
    $stmt->execute([$car_id]);
    $car_images = $stmt->fetchAll();
} catch (Exception $e) {
    $car_images = [];
}

$error = '';
$success = '';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $brand = clean_input($_POST['brand'] ?? $car['brand']);
    $model = clean_input($_POST['model'] ?? $car['model']);
    $year = intval($_POST['year'] ?? $car['year']);
    $type = clean_input($_POST['type'] ?? $car['type']);
    $fuel_type = clean_input($_POST['fuel_type'] ?? $car['fuel_type']);
    $transmission = clean_input($_POST['transmission'] ?? $car['transmission']);
    $seats = intval($_POST['seats'] ?? $car['seats']);
    $doors = intval($_POST['doors'] ?? $car['doors']);
    $engine_size = clean_input($_POST['engine_size'] ?? $car['engine_size']);
    $color = clean_input($_POST['color'] ?? $car['color']);
    $daily_rate = floatval($_POST['daily_rate'] ?? $car['daily_rate']);
    $weekly_rate = !empty($_POST['weekly_rate']) ? floatval($_POST['weekly_rate']) : null;
    $monthly_rate = !empty($_POST['monthly_rate']) ? floatval($_POST['monthly_rate']) : null;
    $deposit = floatval($_POST['deposit'] ?? $car['deposit']);
    $mileage_limit = intval($_POST['mileage_limit'] ?? $car['mileage_limit']);
    $extra_mileage_rate = floatval($_POST['extra_mileage_rate'] ?? $car['extra_mileage_rate']);
    $description = clean_input($_POST['description'] ?? $car['description']);
    $features = clean_input($_POST['features'] ?? $car['features']);
    $popular = isset($_POST['popular']) ? 1 : 0;
    $status = clean_input($_POST['status'] ?? $car['status']);
    
    // New image URLs
    $new_images = [];
    for ($i = 1; $i <= 5; $i++) {
        $url = clean_input($POST['image_url' . $i] ?? '');
        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $new_images[] = $url;
        }
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update car
        $stmt = $pdo->prepare("
            UPDATE cars SET 
                brand = ?, model = ?, year = ?, type = ?, fuel_type = ?, transmission = ?,
                seats = ?, doors = ?, engine_size = ?, color = ?,
                daily_rate = ?, weekly_rate = ?, monthly_rate = ?, deposit = ?,
                mileage_limit = ?, extra_mileage_rate = ?, description = ?, features = ?,
                popular = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            $brand, $model, $year, $type, $fuel_type, $transmission,
            $seats, $doors, $engine_size, $color,
            $daily_rate, $weekly_rate, $monthly_rate, $deposit,
            $mileage_limit, $extra_mileage_rate, $description, $features,
            $popular, $status, $car_id
        ]);
        
        // Update images only if new ones provided
        if (!empty($new_images)) {
            // Delete old images
            $pdo->prepare("DELETE FROM car_images WHERE car_id = ?")->execute([$car_id]);
            
            // Insert new images
            foreach ($new_images as $index => $url) {
                $is_primary = ($index === 0) ? 1 : 0;
                $stmt = $pdo->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                $stmt->execute([$car_id, $url, $is_primary, $index]);
            }
        }
        
        $pdo->commit();
        $success = 'تم تحديث السيارة بنجاح!';
        
        // Refresh car data
        $stmt = $pdo->prepare("SELECT * FROM cars WHERE id = ?");
        $stmt->execute([$car_id]);
        $car = $stmt->fetch();
        
        // Refresh images
        $stmt = $pdo->prepare("SELECT * FROM car_images WHERE car_id = ? ORDER BY sort_order ASC");
        $stmt->execute([$car_id]);
        $car_images = $stmt->fetchAll();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'خطأ في التحديث: ' . $e->getMessage();
    }
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل سيارة | لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --text: #333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
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
            font-size: 1.1rem;
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
        }
        
        .admin-content { padding: 25px; }
        
        .content-card {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        .content-card h4 {
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label {
            font-weight: 700;
            font-size: 0.88rem;
            color: var(--dark);
            margin-bottom: 6px;
        }
        
        .form-control, .form-select {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 11px 15px;
            font-size: 0.9rem;
            transition: var(--transition);
            font-family: 'Cairo', sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102,126,234,0.08);
            outline: none;
        }
        
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .btn {
            font-weight: 600;
            border-radius: 10px;
            padding: 12px 25px;
            transition: var(--transition);
            font-size: 0.9rem;
            cursor: pointer;
        }
        
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); color: white; }
        .btn-secondary { background: #e0e0e0; border: none; color: var(--text); text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #ccc; }
        
        .alert {
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }
        
        .current-images { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .current-images img { width: 100px; height: 70px; object-fit: cover; border-radius: 8px; border: 2px solid var(--border); }
        
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(100%); }
            .admin-sidebar.mobile-open { transform: translateX(0); }
            .admin-main { margin-right: 0; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="sidebar">
            <a href="index.php" class="sidebar-brand">
                <i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?>
            </a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php" class="active"><i class="fas fa-car"></i> إدارة السيارات</a>
                <a href="add-car.php"><i class="fas fa-plus-circle"></i> إضافة سيارة</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="customers-management.php"><i class="fas fa-users"></i> العملاء</a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </nav>
        </aside>
        
        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <button class="btn btn-sm" style="background:#eef0ff;color:#667eea;border:none;margin-left:10px;" 
                            onclick="document.getElementById('sidebar').classList.toggle('mobile-open')">
                        <i class="fas fa-bars"></i>
                    </button>
                    <strong>تعديل سيارة #<?php echo $car_id; ?></strong>
                </div>
                <div>
                    <span class="me-3"><?php echo htmlspecialchars($user_name); ?></span>
                    <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;">خروج</a>
                </div>
            </header>
            
            <div class="admin-content">
                <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="content-card">
                    <h4><i class="fas fa-edit text-primary"></i> تعديل: <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model'] . ' ' . $car['year']); ?></h4>
                    
                    <!-- Current Images -->
                    <?php if (!empty($car_images)): ?>
                    <div class="mb-3">
                        <label class="form-label">الصور الحالية:</label>
                        <div class="current-images">
                            <?php foreach ($car_images as $img): 
                                $img_url = filter_var($img['image_path'], FILTER_VALIDATE_URL) ? $img['image_path'] : '../uploads/cars/' . $img['image_path'];
                            ?>
                            <img src="<?php echo $img_url; ?>" alt="صورة">
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">الماركة *</label>
                                <input type="text" class="form-control" name="brand" value="<?php echo htmlspecialchars($car['brand']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الموديل *</label>
                                <input type="text" class="form-control" name="model" value="<?php echo htmlspecialchars($car['model']); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السنة</label>
                                <input type="number" class="form-control" name="year" value="<?php echo $car['year']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">النوع</label>
                                <select class="form-select" name="type">
                                    <option value="">اختر...</option>
                                    <option value="luxury" <?php echo $car['type'] === 'luxury' ? 'selected' : ''; ?>>فاخرة</option>
                                    <option value="suv" <?php echo $car['type'] === 'suv' ? 'selected' : ''; ?>>SUV</option>
                                    <option value="economy" <?php echo $car['type'] === 'economy' ? 'selected' : ''; ?>>اقتصادية</option>
                                    <option value="sport" <?php echo $car['type'] === 'sport' ? 'selected' : ''; ?>>رياضية</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اللون</label>
                                <input type="text" class="form-control" name="color" value="<?php echo htmlspecialchars($car['color'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الوقود</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="petrol" <?php echo $car['fuel_type'] === 'petrol' ? 'selected' : ''; ?>>بنزين</option>
                                    <option value="diesel" <?php echo $car['fuel_type'] === 'diesel' ? 'selected' : ''; ?>>ديزل</option>
                                    <option value="electric" <?php echo $car['fuel_type'] === 'electric' ? 'selected' : ''; ?>>كهرباء</option>
                                    <option value="hybrid" <?php echo $car['fuel_type'] === 'hybrid' ? 'selected' : ''; ?>>هايبرد</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ناقل الحركة</label>
                                <select class="form-select" name="transmission">
                                    <option value="automatic" <?php echo $car['transmission'] === 'automatic' ? 'selected' : ''; ?>>أوتوماتيك</option>
                                    <option value="manual" <?php echo $car['transmission'] === 'manual' ? 'selected' : ''; ?>>يدوي</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">المقاعد</label>
                                <input type="number" class="form-control" name="seats" value="<?php echo $car['seats']; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السعر اليومي (DH) *</label>
                                <input type="number" class="form-control" name="daily_rate" step="0.01" value="<?php echo $car['daily_rate']; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السعر الأسبوعي (DH)</label>
                                <input type="number" class="form-control" name="weekly_rate" step="0.01" value="<?php echo $car['weekly_rate'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السعر الشهري (DH)</label>
                                <input type="number" class="form-control" name="monthly_rate" step="0.01" value="<?php echo $car['monthly_rate'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">التأمين (DH)</label>
                                <input type="number" class="form-control" name="deposit" step="0.01" value="<?php echo $car['deposit'] ?? 2000; ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الحد اليومي (كم)</label>
                                <input type="number" class="form-control" name="mileage_limit" value="<?php echo $car['mileage_limit'] ?? 200; ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">الوصف</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($car['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">المميزات</label>
                                <textarea class="form-control" name="features" rows="2"><?php echo htmlspecialchars($car['features'] ?? ''); ?></textarea>
                            </div>
                            
                            <!-- New Images -->
                            <div class="col-12">
                                <label class="form-label">روابط صور جديدة (اتركها فارغة للإبقاء على الحالية)</label>
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <input type="url" class="form-control mb-2" name="image_url_<?php echo $i; ?>" placeholder="رابط الصورة <?php echo $i; ?>">
                                <?php endfor; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">الحالة</label>
                                <select class="form-select" name="status">
                                    <option value="available" <?php echo $car['status'] === 'available' ? 'selected' : ''; ?>>متوفرة</option>
                                    <option value="rented" <?php echo $car['status'] === 'rented' ? 'selected' : ''; ?>>مؤجرة</option>
                                    <option value="maintenance" <?php echo $car['status'] === 'maintenance' ? 'selected' : ''; ?>>صيانة</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input type="checkbox" class="form-check-input" name="popular" value="1" <?php echo $car['popular'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold">سيارة مميزة</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ التعديلات</button>
                            <a href="cars-management.php" class="btn btn-secondary"><i class="fas fa-arrow-right me-1"></i> العودة</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>