<?php
// download-images.php - تحميل صور السيارات من الإنترنت
$uploadDir = _DIR_ . '/cars/';

// إنشاء المجلد إذا لم يكن موجوداً
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// صور Mercedes C-Class
$images = [
    'mercedes-c-class-1.jpg' => 'https://images.unsplash.com/photo-1618843479313-40f8afb4b4d8?w=800&q=80',
    'mercedes-c-class-2.jpg' => 'https://images.unsplash.com/photo-1617531653332-bd46c24f2068?w=800&q=80',
    'mercedes-c-class-3.jpg' => 'https://images.unsplash.com/photo-1617814076367-b759c7d7e738?w=800&q=80',
    'mercedes-c-class-4.jpg' => 'https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=800&q=80',
    
    // صور Toyota RAV4
    'toyota-rav4-1.jpg' => 'https://images.unsplash.com/photo-1629897048514-3dd7414fe72a?w=800&q=80',
    'toyota-rav4-2.jpg' => 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?w=800&q=80',
    'toyota-rav4-3.jpg' => 'https://images.unsplash.com/photo-1559416523-140ddc3d238c?w=800&q=80',
    
    // صور Volkswagen Golf
    'volkswagen-golf-1.jpg' => 'https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=800&q=80',
    'volkswagen-golf-2.jpg' => 'https://images.unsplash.com/photo-1541899481282-d53bffe3c35d?w=800&q=80',
    'volkswagen-golf-3.jpg' => 'https://images.unsplash.com/photo-1546614042-7df3c24c9e5d?w=800&q=80',
];

echo "<h2>تحميل صور السيارات</h2>";
echo "<ul>";

foreach ($images as $filename => $url) {
    $filepath = $uploadDir . $filename;
    
    if (file_exists($filepath)) {
        echo "<li style='color:orange;'>⏭️ $filename - موجودة مسبقاً</li>";
        continue;
    }
    
    $imageData = @file_get_contents($url);
    
    if ($imageData !== false) {
        file_put_contents($filepath, $imageData);
        echo "<li style='color:green;'>✅ $filename - تم التحميل بنجاح</li>";
    } else {
        echo "<li style='color:red;'>❌ $filename - فشل التحميل</li>";
    }
}

echo "</ul>";
echo "<p><strong>تم الانتهاء!</strong></p>";
echo "<a href='cars.php' style='padding:10px 20px; background:#667eea; color:white; text-decoration:none; border-radius:8px;'>عرض السيارات</a>";
?>