<?php
// seed-cars.php - CREATE ADMIN + 3 CARS WITH IMAGES
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'includes/config.php';

echo '<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعداد النظام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Cairo", sans-serif; max-width: 750px; margin: 30px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; padding: 25px; border-radius: 16px; margin: 15px 0; box-shadow: 0 5px 20px rgba(0,0,0,0.06); }
        h1 { text-align: center; color: #1a1a2e; }
        h3 { color: #1a1a2e; margin: 0 0 10px 0; }
        .ok { color: #10b981; font-weight: 600; }
        .err { color: #ef4444; font-weight: 600; }
        .info { color: #3b82f6; font-weight: 600; }
        .btn { display: inline-block; padding: 14px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 700; margin: 8px; }
        .btn:hover { background: #5a6fd6; }
        .btn-green { background: #10b981; }
        .btn-green:hover { background: #059669; }
        img { width: 100px; height: 70px; object-fit: cover; border-radius: 8px; margin: 4px; border: 2px solid #e0e0e0; }
    </style>
</head>
<body>
<h1>🚗 إعداد النظام</h1>';

// ============================================
// STEP 1: CREATE ADMIN USER
// ============================================
echo '<div class="card"><h3>👤 إنشاء حساب المدير</h3>';

try {
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@carrental.ma'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if ($adminExists) {
        // Update admin password
        $hashed = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = :pass, role = 'super_admin', status = 'active' WHERE email = 'SAID@gmail.ma'");
        $stmt->execute([':pass' => $hashed]);
        echo '<p class="ok">✅ تم تحديث كلمة مرور المدير</p>';
    } else {
        // Create new admin
        $hashed = password_hash('SAID2002', PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, created_at, updated_at) VALUES ('مدير النظام', 'admin@carrental.ma', :pass, '0600000000', 'super_admin', 'active', NOW(), NOW())");
        $stmt->execute([':pass' => $hashed]);
        echo '<p class="ok">✅ تم إنشاء حساب المدير بنجاح</p>';
    }
    
    echo '<div style="background:#f0f9ff;padding:15px;border-radius:10px;margin-top:10px;">';
    echo '<strong>بيانات تسجيل الدخول:</strong><br>';
    echo '📧 البريد: <strong>admin@carrental.ma</strong><br>';
    echo '🔑 كلمة المرور: <strong>Admin@123</strong><br>';
    echo '<a href="login.php" class="btn" style="margin-top:10px;">🔐 تسجيل الدخول</a>';
    echo '</div>';
    
} catch (Exception $e) {
    echo '<p class="err">❌ خطأ: ' . $e->getMessage() . '</p>';
}

echo '</div>';

// ============================================
// STEP 2: CREATE CARS
// ============================================
echo '<div class="card"><h3>🚗 إدخال السيارات</h3>';

// Clear old cars
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE car_images");
    $pdo->exec("TRUNCATE TABLE bookings");
    $pdo->exec("TRUNCATE TABLE cars");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo '<p class="info">🗑️ تم حذف البيانات القديمة</p>';
} catch (Exception $e) {
    echo '<p class="err">❌ خطأ: ' . $e->getMessage() . '</p>';
}

// Cars data
$cars = [
    [
        'brand' => 'Mercedes-Benz',
        'model' => 'C-Class C300',
        'year' => 2024,
        'type' => 'luxury',
        'fuel_type' => 'petrol',
        'transmission' => 'automatic',
        'seats' => 5,
        'doors' => 4,
        'engine_size' => '2.0L Turbo',
        'color' => 'أسود',
        'daily_rate' => 1200.00,
        'weekly_rate' => 7500.00,
        'monthly_rate' => 28000.00,
        'deposit' => 5000.00,
        'mileage_limit' => 250,
        'extra_mileage_rate' => 2.50,
        'description' => 'مرسيدس بنز C-Class الفاخرة، تجربة قيادة استثنائية مع محرك توربو قوي ونظام تعليق متطور. مثالية لرجال الأعمال والمناسبات الخاصة.',
        'features' => 'شاشة لمس 12.3 بوصة,نظام صوت Burmester,مقاعد جلدية,سقف بانورامي,كاميرا 360 درجة,حساسات ركن,CarPlay,شاحن لاسلكي,إضاءة LED',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80',
            'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?w=800&q=80',
            'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=800&q=80',
            'https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=800&q=80'
        ]
    ],
    [
        'brand' => 'Toyota',
        'model' => 'RAV4 Hybrid',
        'year' => 2024,
        'type' => 'suv',
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'seats' => 5,
        'doors' => 5,
        'engine_size' => '2.5L Hybrid',
        'color' => 'أبيض',
        'daily_rate' => 800.00,
        'weekly_rate' => 5000.00,
        'monthly_rate' => 18000.00,
        'deposit' => 3000.00,
        'mileage_limit' => 300,
        'extra_mileage_rate' => 1.50,
        'description' => 'تويوتا راف 4 هايبرد، السيارة العائلية المثالية. تجمع بين الأداء القوي والاقتصاد في استهلاك الوقود.',
        'features' => 'شاشة لمس 10.5 بوصة,Toyota Safety Sense,فتحة سقف,مقاعد قماش فاخرة,بلوتوث,كاميرا خلفية,حساسات,نظام الثبات,VSC',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1629897048514-3dd7414fe72a?w=800&q=80',
            'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=800&q=80',
            'https://images.unsplash.com/photo-1559416523-140ddc3d238c?w=800&q=80'
        ]
    ],
    [
        'brand' => 'Volkswagen',
        'model' => 'Golf 8 TSI',
        'year' => 2024,
        'type' => 'economy',
        'fuel_type' => 'diesel',
        'transmission' => 'manual',
        'seats' => 5,
        'doors' => 5,
        'engine_size' => '1.6L TDI',
        'color' => 'رمادي',
        'daily_rate' => 450.00,
        'weekly_rate' => 2800.00,
        'monthly_rate' => 10000.00,
        'deposit' => 2000.00,
        'mileage_limit' => 350,
        'extra_mileage_rate' => 1.00,
        'description' => 'فولكس واجن جولف 8، السيارة الاقتصادية المثالية للاستخدام اليومي. محرك ديزل قوي واقتصادي.',
        'features' => 'شاشة لمس 8 بوصة,App-Connect,بلوتوث,مكيف أوتوماتيك,زر تشغيل,حساسات ركن,فرامل ABS,EBD',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
            'https://images.unsplash.com/photo-1546614042-7df3c24c9e5d?w=800&q=80'
        ]
    ]
];

$carCount = 0;
$imgCount = 0;

foreach ($cars as $carData) {
    $images = $carData['images'];
    unset($carData['images']);
    
    try {
        $cols = array_keys($carData);
        $vals = array_values($carData);
        $ph = array_fill(0, count($cols), '?');
        
        $sql = "INSERT INTO cars (" . implode(', ', $cols) . ", created_at, updated_at) VALUES (" . implode(', ', $ph) . ", NOW(), NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
        $carId = $pdo->lastInsertId();
        $carCount++;
        
        echo '<p><strong>✅ ' . htmlspecialchars($carData['brand'] . ' ' . $carData['model']) . ' - ' . $carData['year'] . '</strong></p>';
        echo '<p>💰 ' . number_format($carData['daily_rate'], 2) . ' DH/يوم | ⚡ ' . ($carData['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي') . ' | 🪑 ' . $carData['seats'] . ' مقاعد</p>';
        echo '<div>';
        
        // Save image URLs directly
        foreach ($images as $index => $url) {
            $isPrimary = ($index === 0) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$carId, $url, $isPrimary, $index]);
            $imgCount++;
            
            echo '<img src="' . $url . '" alt="صورة" loading="lazy">';
        }
        
        echo '</div><br>';
        
    } catch (Exception $e) {
        echo '<p class="err">❌ خطأ: ' . $e->getMessage() . '</p>';
    }
    
    ob_flush();
    flush();
}

echo '<p class="ok">✅ تم إدخال ' . $carCount . ' سيارات و ' . $imgCount . ' صورة بنجاح!</p>';
echo '</div>';

// ============================================
// FINAL LINKS
// ============================================
echo '<div style="text-align:center;margin-top:20px;">';
echo '<a href="login.php" class="btn">🔐 تسجيل الدخول (مدير)</a>';
echo '<a href="cars.php" class="btn btn-green">🚗 عرض السيارات</a>';
echo '<a href="index.php" class="btn">🏠 الصفحة الرئيسية</a>';
echo '</div>';

echo '</body></html>';
?>