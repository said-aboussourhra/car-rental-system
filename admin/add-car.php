<?php
require_once '../includes/config.php';

if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

// Create upload folder
$upload_dir = __DIR__ . '/../uploads/cars/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = intval($_POST['year'] ?? 2024);
    $type = trim($_POST['type'] ?? 'economy');
    $fuel_type = trim($_POST['fuel_type'] ?? 'petrol');
    $transmission = trim($_POST['transmission'] ?? 'automatic');
    $seats = intval($_POST['seats'] ?? 5);
    $doors = intval($_POST['doors'] ?? 4);
    $engine_size = trim($_POST['engine_size'] ?? '');
    $color = trim($_POST['color'] ?? '');
    $daily_rate = floatval($_POST['daily_rate'] ?? 0);
    $weekly_rate = floatval($_POST['weekly_rate'] ?? 0) ?: null;
    $monthly_rate = floatval($_POST['monthly_rate'] ?? 0) ?: null;
    $deposit = floatval($_POST['deposit'] ?? 2000);
    $mileage_limit = intval($_POST['mileage_limit'] ?? 200);
    $description = trim($_POST['description'] ?? '');
    $features = trim($_POST['features'] ?? '');
    $popular = isset($_POST['popular']) ? 1 : 0;
    
    $has_image = isset($_FILES['image1']) && $_FILES['image1']['error'] === UPLOAD_ERR_OK;
    
    if (empty($brand) || empty($model) || $daily_rate <= 0) {
        $error = 'يرجى ملء الحقول المطلوبة (الماركة، الموديل، السعر)';
    } elseif (!$has_image) {
        $error = 'يرجى رفع الصورة الأولى على الأقل';
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("INSERT INTO cars (brand, model, year, type, fuel_type, transmission, seats, doors, engine_size, color, daily_rate, weekly_rate, monthly_rate, deposit, mileage_limit, description, features, popular, status, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'available',NOW(),NOW())");
            $stmt->execute([$brand, $model, $year, $type, $fuel_type, $transmission, $seats, $doors, $engine_size, $color, $daily_rate, $weekly_rate, $monthly_rate, $deposit, $mileage_limit, $description, $features, $popular]);
            
            $car_id = $pdo->lastInsertId();
            
            // Upload images (max 5)
            for ($i = 1; $i <= 5; $i++) {
                $key = 'image' . $i;
                if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES[$key];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) continue;
                    if ($file['size'] > 5 * 1024 * 1024) continue;
                    
                    $fname = 'car_' . $car_id . '' . $i . '' . time() . '.' . $ext;
                    $fpath = $upload_dir . $fname;
                    
                    if (move_uploaded_file($file['tmp_name'], $fpath)) {
                        $is_primary = ($i === 1) ? 1 : 0;
                        $pdo->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?,?,?,?)")->execute([$car_id, $fname, $is_primary, $i-1]);
                    }
                }
            }
            
            $pdo->commit();
            $success = '✅ تمت إضافة السيارة بنجاح! <a href="cars-management.php">عرض السيارات</a>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'خطأ: ' . $e->getMessage();
        }
    }
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة سيارة | لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --radius: 14px;
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f0f2f5; color: var(--text); line-height: 1.7; }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .admin-sidebar {
            width: 250px; background: var(--dark); color: white;
            position: fixed; top: 0; right: 0; bottom: 0; z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px; padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1); color: white;
            text-decoration: none; font-weight: 800; font-size: 1.1rem;
        }
        .sidebar-brand i { color: var(--primary); font-size: 1.3rem; }
        .sidebar-menu { padding: 10px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px; padding: 12px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 500;
            margin: 2px 10px; border-radius: 10px; font-size: 0.9rem; transition: var(--transition);
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu a.active { background: var(--gradient); color: white; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        
        /* Main */
        .admin-main { flex: 1; margin-right: 250px; }
        .admin-topbar {
            background: white; padding: 14px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50;
        }
        .admin-content { padding: 25px; max-width: 900px; }
        
        /* Card */
        .content-card {
            background: white; border-radius: var(--radius); padding: 30px; box-shadow: var(--shadow); border: 1px solid #f0f0f0;
        }
        .content-card h4 {
            font-weight: 800; color: var(--dark); margin-bottom: 25px;
            padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 10px;
        }
        .content-card h4 i { color: var(--primary); }
        
        /* Form */
        .form-label { font-weight: 700; font-size: 0.85rem; margin-bottom: 5px; color: var(--dark); display: block; }
        .form-control, .form-select {
            border: 2px solid var(--border); border-radius: 10px; padding: 10px 14px;
            font-size: 0.9rem; transition: var(--transition); font-family: 'Cairo', sans-serif;
            width: 100%; background: #fafafa;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); outline: none; background: white; box-shadow: 0 0 0 3px rgba(102,126,234,0.08); }
        textarea.form-control { resize: vertical; min-height: 70px; }
        
        .btn {
            font-weight: 600; border-radius: 10px; padding: 12px 25px; font-size: 0.95rem;
            cursor: pointer; transition: var(--transition); display: inline-block; border: none; text-decoration: none;
        }
        .btn-primary { background: var(--gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        .btn-secondary { background: #e5e7eb; color: #333; }
        .btn-secondary:hover { background: #d1d5db; }
        
        .alert { border-radius: 10px; padding: 14px 18px; margin-bottom: 20px; border: none; font-weight: 500; font-size: 0.9rem; }
        
        .upload-box {
            border: 2px dashed #ccc; border-radius: 10px; padding: 20px; text-align: center;
            cursor: pointer; transition: var(--transition); background: #fafafa; height: 100%;
        }
        .upload-box:hover { border-color: var(--primary); background: #eef0ff; }
        .upload-box i { font-size: 2rem; color: #999; margin-bottom: 8px; }
        .upload-box .preview-img { max-width: 100%; max-height: 80px; border-radius: 8px; margin-top: 10px; }
        
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(100%); }
            .admin-sidebar.mobile-open { transform: translateX(0); }
            .admin-main { margin-right: 0; }
            .admin-content { padding: 15px; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="sidebar">
            <a href="index.php" class="sidebar-brand"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php"><i class="fas fa-car"></i> السيارات</a>
                <a href="add-car.php" class="active"><i class="fas fa-plus-circle"></i> إضافة سيارة</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="../logout.php" style="color: #ef4444; margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 15px;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </nav>
        </aside>
        
        <!-- Main -->
        <main class="admin-main">
            <header class="admin-topbar">
                <button style="background:#eef0ff;color:#667eea;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;" 
                        onclick="document.getElementById('sidebar').classList.toggle('mobile-open')">
                    <i class="fas fa-bars"></i>
                </button>
                <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            </header>
            
            <div class="admin-content">
                <?php if ($error): ?>
                <div class="alert alert-danger">❌ <?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <div class="content-card">
                    <h4><i class="fas fa-plus-circle"></i> إضافة سيارة جديدة</h4>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <!-- Row 1: Brand & Model -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">الماركة *</label>
                                <input type="text" class="form-control" name="brand" placeholder="مثال: Toyota" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">الموديل *</label>
                                <input type="text" class="form-control" name="model" placeholder="مثال: Corolla" required>
                            </div>
                        </div>
                        
                        <!-- Row 2: Year, Type, Color -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">السنة</label>
                                <input type="number" class="form-control" name="year" value="2024">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">النوع</label>
                                <select class="form-select" name="type">
                                    <option value="economy">اقتصادية</option>
                                    <option value="luxury">فاخرة</option>
                                    <option value="suv">SUV</option>
                                    <option value="sport">رياضية</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">اللون</label>
                                <input type="text" class="form-control" name="color" placeholder="مثال: أبيض">
                            </div>
                        </div>
                        
                        <!-- Row 3: Fuel, Transmission, Seats, Doors, Engine -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">نوع الوقود</label>
                                <select class="form-select" name="fuel_type">
                                    <option value="petrol">بنزين</option>
                                    <option value="diesel">ديزل</option>
                                    <option value="electric">كهرباء</option>
                                    <option value="hybrid">هايبرد</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">ناقل الحركة</label>
                                <select class="form-select" name="transmission">
                                    <option value="automatic">أوتوماتيك</option>
                                    <option value="manual">يدوي</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">سعة المحرك</label>
                                <input type="text" class="form-control" name="engine_size" placeholder="مثال: 2.0L">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">عدد المقاعد</label>
                                <input type="number" class="form-control" name="seats" value="5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">عدد الأبواب</label>
                                <input type="number" class="form-control" name="doors" value="4">
                            </div>
                        </div>
                        
                        <!-- Row 4: Prices -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">السعر اليومي (DH) *</label>
                                <input type="number" class="form-control" name="daily_rate" step="0.01" placeholder="500" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السعر الأسبوعي (DH)</label>
                                <input type="number" class="form-control" name="weekly_rate" step="0.01" placeholder="3000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">السعر الشهري (DH)</label>
                                <input type="number" class="form-control" name="monthly_rate" step="0.01" placeholder="10000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">مبلغ التأمين (DH)</label>
                                <input type="number" class="form-control" name="deposit" value="2000">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">الحد اليومي (كم)</label>
                                <input type="number" class="form-control" name="mileage_limit" value="200">
                            </div>
                        </div>
                        
                        <!-- Row 5: Description & Features -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">الوصف</label>
                                <textarea class="form-control" name="description" rows="2" placeholder="وصف مختصر للسيارة..."></textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">المميزات (مفصولة بفواصل)</label>
                                <textarea class="form-control" name="features" rows="2" placeholder="مكيف، بلوتوث، كاميرا خلفية..."></textarea>
                            </div>
                        </div>
                        
                        <!-- Row 6: Images -->
                        <div class="mb-3">
                            <label class="form-label">📸 صور السيارة * (الصورة الأولى مطلوبة)</label>
                            <div class="row g-3">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <div class="col-md-4 col-6">
                                    <label class="upload-box" for="img<?php echo $i; ?>" style="display:block;">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div><strong>صورة <?php echo $i; ?></strong></div>
                                        <small><?php echo $i === 1 ? '(مطلوبة)' : '(اختياري)'; ?></small>
                                        <img id="prev<?php echo $i; ?>" class="preview-img" src="" style="display:none;">
                                    </label>
                                    <input type="file" id="img<?php echo $i; ?>" name="image<?php echo $i; ?>" 
                                           accept="image/*" <?php echo $i === 1 ? 'required' : ''; ?>
                                           onchange="showPreview(this, <?php echo $i; ?>)" style="display:none;">
                                </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                        
                        <!-- Row 7: Checkbox -->
                        <div class="mb-3">
                            <label class="form-check-label" style="cursor:pointer;">
                                <input type="checkbox" name="popular" value="1" style="width:18px;height:18px;margin-left:8px;">
                                <strong>⭐ تعيين كسيارة مميزة (الأكثر طلباً)</strong>
                            </label>
                        </div>
                        
                        <!-- Buttons -->
                        <div style="display:flex;gap:10px;margin-top:20px;">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> حفظ السيارة</button>
                            <a href="cars-management.php" class="btn btn-secondary"><i class="fas fa-times"></i> إلغاء</a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Click on upload box triggers file input
        document.querySelectorAll('.upload-box').forEach(function(box) {
            box.addEventListener('click', function() {
                var inputId = this.getAttribute('for');
                document.getElementById(inputId).click();
            });
        });
        
        // Show image preview
        function showPreview(input, num) {
            var img = document.getElementById('prev' + num);
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>