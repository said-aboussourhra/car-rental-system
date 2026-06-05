<?php
require_once 'includes/config.php';

echo '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>إضافة السيارات</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
<style>body{font-family:Cairo,sans-serif;max-width:850px;margin:30px auto;padding:20px;background:#f0f2f5;}
.card{background:white;padding:20px;border-radius:14px;margin:10px 0;box-shadow:0 5px 20px rgba(0,0,0,0.06);}
.ok{color:#10b981;font-weight:700;} .err{color:#ef4444;} .info{color:#3b82f6;}
.btn{display:inline-block;padding:14px 30px;background:#667eea;color:white;text-decoration:none;border-radius:10px;font-weight:700;margin:5px;}
.btn:hover{background:#5a6fd6;}
img{width:120px;height:85px;object-fit:cover;border-radius:8px;margin:3px;border:2px solid #e0e0e0;transition:transform 0.2s;}
img:hover{transform:scale(1.5);z-index:10;position:relative;box-shadow:0 10px 30px rgba(0,0,0,0.3);}
h1{text-align:center;color:#1a1a2e;}
.progress{width:100%;height:6px;background:#e0e0e0;border-radius:10px;margin:15px 0;overflow:hidden;}
.progress-bar{height:100%;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:10px;transition:width 0.3s;width:0%;}
</style></head><body>
<h1>🚗 إضافة 15 سيارة بصور حقيقية</h1>
<div class="progress"><div class="progress-bar" id="progressBar"></div></div>';

// حذف السيارات القديمة
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    $pdo->exec("TRUNCATE TABLE car_images");
    $pdo->exec("TRUNCATE TABLE bookings");
    $pdo->exec("TRUNCATE TABLE cars");
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo '<div class="card"><span class="ok">✅ تم مسح جميع السيارات القديمة</span></div>';
} catch (Exception $e) {
    echo '<div class="card"><span class="err">❌ ' . $e->getMessage() . '</span></div>';
}

// 15 سيارة مع صور حقيقية من Unsplash
$cars = [
    // ==================== DACIA ====================
    [
        'brand' => 'Dacia', 'model' => 'Logan', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'manual', 'seats' => 5, 'doors' => 4, 'engine_size' => '1.0L SCe', 'color' => 'أبيض',
        'daily_rate' => 250, 'weekly_rate' => 1500, 'monthly_rate' => 5500, 'deposit' => 2000,
        'mileage_limit' => 400, 'extra_mileage_rate' => 0.50,
        'description' => 'داسيا لوغان - السيارة الاقتصادية الأكثر مبيعاً في المغرب. مثالية للاستخدام اليومي والمسافات الطويلة.',
        'features' => 'مكيف هواء,راديو MP3,وسائد هوائية,ABS,نوافذ كهربائية,مساحة داخلية واسعة',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Dacia', 'model' => 'Sandero', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'manual', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.0L TCe', 'color' => 'أحمر',
        'daily_rate' => 280, 'weekly_rate' => 1700, 'monthly_rate' => 6000, 'deposit' => 2000,
        'mileage_limit' => 400, 'extra_mileage_rate' => 0.50,
        'description' => 'داسيا سانديرو - هاتشباك عصرية بتصميم جذاب وأداء اقتصادي ممتاز.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,وسائد هوائية,ABS,تصميم شبابي',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Dacia', 'model' => 'Duster', 'year' => 2024, 'type' => 'suv', 'fuel_type' => 'diesel',
        'transmission' => 'manual', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.5L Blue dCi', 'color' => 'رمادي',
        'daily_rate' => 400, 'weekly_rate' => 2500, 'monthly_rate' => 9000, 'deposit' => 3000,
        'mileage_limit' => 350, 'extra_mileage_rate' => 1.00,
        'description' => 'داسيا دستر - SUV قوية وعملية. مثالية للطرق الوعرة والمدينة معاً.',
        'features' => 'شاشة لمس,كاميرا خلفية,حساسات ركن,مكيف,بلوتوث,نظام تثبيت السرعة',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
        ]
    ],
    
    // ==================== RENAULT ====================
    [
        'brand' => 'Renault', 'model' => 'Clio 5', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.0L TCe', 'color' => 'أزرق',
        'daily_rate' => 350, 'weekly_rate' => 2200, 'monthly_rate' => 8000, 'deposit' => 2500,
        'mileage_limit' => 350, 'extra_mileage_rate' => 0.80,
        'description' => 'رونو كليو 5 - هاتشباك فرنسية أنيقة بتصميم عصري وتقنيات متطورة.',
        'features' => 'شاشة لمس 7 بوصة,Apple CarPlay,Android Auto,بلوتوث,مكيف أوتوماتيك,كاميرا خلفية',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Renault', 'model' => 'Mégane', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'diesel',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.5L Blue dCi', 'color' => 'رمادي',
        'daily_rate' => 450, 'weekly_rate' => 2800, 'monthly_rate' => 10000, 'deposit' => 3000,
        'mileage_limit' => 300, 'extra_mileage_rate' => 1.00,
        'description' => 'رونو ميغان - سيارة عائلية مريحة بتصميم أنيق وأداء قوي.',
        'features' => 'شاشة لمس,نظام صوت Bose,مقاعد جلدية,سقف بانورامي,حساسات ركن,كاميرا 360',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
        ]
    ],
    
    // ==================== OPEL ====================
    [
        'brand' => 'Opel', 'model' => 'Astra', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.2L Turbo', 'color' => 'أسود',
        'daily_rate' => 400, 'weekly_rate' => 2500, 'monthly_rate' => 9000, 'deposit' => 3000,
        'mileage_limit' => 300, 'extra_mileage_rate' => 1.00,
        'description' => 'أوبل أسترا - هاتشباك ألمانية بتصميم رياضي وتقنيات حديثة.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,حساسات,كاميرا خلفية,مقاعد رياضية',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Opel', 'model' => 'Corsa', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'manual', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.2L', 'color' => 'أبيض',
        'daily_rate' => 300, 'weekly_rate' => 1800, 'monthly_rate' => 6500, 'deposit' => 2000,
        'mileage_limit' => 400, 'extra_mileage_rate' => 0.50,
        'description' => 'أوبل كورسا - سيارة صغيرة مثالية للمدينة. اقتصادية وسهلة القيادة.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,ABS,وسائد هوائية',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
        ]
    ],
    
    // ==================== FIAT ====================
    [
        'brand' => 'Fiat', 'model' => '500', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 4, 'doors' => 3, 'engine_size' => '1.0L', 'color' => 'أبيض',
        'daily_rate' => 350, 'weekly_rate' => 2200, 'monthly_rate' => 8000, 'deposit' => 2500,
        'mileage_limit' => 350, 'extra_mileage_rate' => 0.80,
        'description' => 'فيات 500 - سيارة إيطالية أيقونية بتصميم كلاسيكي وعصري. مثالية للمدينة.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,ABS,وسائد هوائية,تصميم أيقوني',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Fiat', 'model' => 'Tipo', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'diesel',
        'transmission' => 'manual', 'seats' => 5, 'doors' => 4, 'engine_size' => '1.6L MultiJet', 'color' => 'رمادي',
        'daily_rate' => 320, 'weekly_rate' => 2000, 'monthly_rate' => 7000, 'deposit' => 2500,
        'mileage_limit' => 350, 'extra_mileage_rate' => 0.80,
        'description' => 'فيات تيبو - سيارة إيطالية عملية واقتصادية. مثالية للعائلات الصغيرة.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,ABS,وسائد هوائية,مساحة داخلية واسعة',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
        ]
    ],
    
    // ==================== PEUGEOT ====================
    [
        'brand' => 'Peugeot', 'model' => '208', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.2L PureTech', 'color' => 'أصفر',
        'daily_rate' => 380, 'weekly_rate' => 2400, 'monthly_rate' => 8500, 'deposit' => 2500,
        'mileage_limit' => 350, 'extra_mileage_rate' => 0.80,
        'description' => 'بيجو 208 - هاتشباك فرنسية بتصميم جريء وتقنيات متطورة.',
        'features' => 'شاشة لمس 10 بوصة,i-Cockpit,بلوتوث,مكيف,كاميرا خلفية,حساسات',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Peugeot', 'model' => '3008', 'year' => 2024, 'type' => 'suv', 'fuel_type' => 'diesel',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.5L BlueHDi', 'color' => 'أزرق',
        'daily_rate' => 600, 'weekly_rate' => 3800, 'monthly_rate' => 14000, 'deposit' => 4000,
        'mileage_limit' => 300, 'extra_mileage_rate' => 1.50,
        'description' => 'بيجو 3008 - SUV فرنسية فاخرة بتصميم داخلي ثوري وأداء ممتاز.',
        'features' => 'i-Cockpit,شاشة لمس,نظام صوت Focal,سقف بانورامي,كاميرا 360,حساسات,مقاعد جلدية',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
        ]
    ],
    
    // ==================== CITROËN ====================
    [
        'brand' => 'Citroën', 'model' => 'C3', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.2L PureTech', 'color' => 'برتقالي',
        'daily_rate' => 350, 'weekly_rate' => 2200, 'monthly_rate' => 8000, 'deposit' => 2500,
        'mileage_limit' => 350, 'extra_mileage_rate' => 0.80,
        'description' => 'سيتروين C3 - سيارة مريحة بتصميم فريد ونظام تعليق هيدروليكي ناعم.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,ABS,وسائد هوائية,تعليق مريح',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    
    // ==================== HYUNDAI ====================
    [
        'brand' => 'Hyundai', 'model' => 'Tucson', 'year' => 2024, 'type' => 'suv', 'fuel_type' => 'diesel',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.6L CRDi', 'color' => 'أسود',
        'daily_rate' => 550, 'weekly_rate' => 3500, 'monthly_rate' => 13000, 'deposit' => 4000,
        'mileage_limit' => 300, 'extra_mileage_rate' => 1.50,
        'description' => 'هيونداي توسان - SUV كورية بجودة عالية وتصميم عصري وتقنيات متطورة.',
        'features' => 'شاشة لمس,كاميرا خلفية,حساسات,مقاعد جلدية,سقف بانورامي,بلوتوث',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
        ]
    ],
    
    // ==================== KIA ====================
    [
        'brand' => 'Kia', 'model' => 'Sportage', 'year' => 2024, 'type' => 'suv', 'fuel_type' => 'diesel',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.6L CRDi', 'color' => 'أبيض',
        'daily_rate' => 550, 'weekly_rate' => 3500, 'monthly_rate' => 13000, 'deposit' => 4000,
        'mileage_limit' => 300, 'extra_mileage_rate' => 1.50,
        'description' => 'كيا سبورتاج - SUV كورية أنيقة بضمان 7 سنوات وتقنيات أمان متطورة.',
        'features' => 'شاشة لمس 12.3 بوصة,كاميرا 360,حساسات,مقاعد جلدية,سقف بانورامي,نظام صوت Harman Kardon',
        'popular' => 1,
        'images' => [
            'https://images.unsplash.com/photo-1552519507-da3b142c6e3d?w=800&q=80',
            'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
        ]
    ],
    [
        'brand' => 'Kia', 'model' => 'Picanto', 'year' => 2024, 'type' => 'economy', 'fuel_type' => 'petrol',
        'transmission' => 'automatic', 'seats' => 5, 'doors' => 5, 'engine_size' => '1.0L', 'color' => 'أخضر',
        'daily_rate' => 280, 'weekly_rate' => 1700, 'monthly_rate' => 6000, 'deposit' => 2000,
        'mileage_limit' => 400, 'extra_mileage_rate' => 0.50,
        'description' => 'كيا بيكانتو - سيارة صغيرة مثالية للمدينة. اقتصادية وسهلة الركن.',
        'features' => 'شاشة لمس,بلوتوث,مكيف,ABS,وسائد هوائية,سهلة الركن',
        'popular' => 0,
        'images' => [
            'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
            'https://images.unsplash.com/photo-1549317661-bd32c8ce0db2?w=800&q=80',
        ]
    ],
];

$totalCars = count($cars);
$carCount = 0;
$imgCount = 0;

foreach ($cars as $index => $carData) {
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
        
        echo '<div class="card">';
        echo '<h3>✅ ' . $carData['brand'] . ' ' . $carData['model'] . ' - ' . $carData['year'] . '</h3>';
        echo '<p>💰 <strong>' . number_format($carData['daily_rate'], 2) . ' DH/يوم</strong> | ⚡ ' . ($carData['transmission'] == 'automatic' ? 'أوتوماتيك' : 'يدوي') . ' | ⛽ ' . $carData['fuel_type'] . ' | 🪑 ' . $carData['seats'] . ' مقاعد</p>';
        echo '<div>';
        
        foreach ($images as $imgIndex => $url) {
            $isPrimary = ($imgIndex === 0) ? 1 : 0;
            $stmt = $pdo->prepare("INSERT INTO car_images (car_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
            $stmt->execute([$carId, $url, $isPrimary, $imgIndex]);
            $imgCount++;
            echo '<img src="' . $url . '" alt="' . $carData['brand'] . ' ' . $carData['model'] . '" loading="lazy" onerror="this.style.display=\'none\'">';
        }
        
        echo '</div></div>';
        
    } catch (Exception $e) {
        echo '<div class="card"><span class="err">❌ خطأ: ' . $e->getMessage() . '</span></div>';
    }
    
    // Progress
    $progress = round((($index + 1) / $totalCars) * 100);
    echo '<script>document.getElementById("progressBar").style.width="' . $progress . '%";</script>';
    
    ob_flush();
    flush();
}

echo '<script>document.getElementById("progressBar").style.width="100%";</script>';
echo '<div style="text-align:center;margin-top:30px;">';
echo '<h2 style="color:#10b981;">✅ تم بنجاح! ' . $carCount . ' سيارة | ' . $imgCount . ' صورة</h2>';
echo '<a href="cars.php" class="btn">🚗 عرض جميع السيارات</a>';
echo '<a href="admin/index.php" class="btn">👑 لوحة التحكم</a>';
echo '</div>';
echo '</body></html>';
?>