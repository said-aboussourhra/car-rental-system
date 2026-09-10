<?php
/**
 * Premium Car Rental - معالج التثبيت
 * يفتح في المتصفح مرة واحدة بعد رفع الملفات:
 *   1) يتحقق من إعدادات قاعدة البيانات
 *   2) ينشئ قاعدة البيانات والجداول والبيانات الأولية
 *   3) يتأكد من وجود حساب المدير
 *   4) يعرض روابط إضافة السيارات التجريبية
 * ⚠️ احذف هذا الملف بعد الانتهاء من التثبيت في بيئة الإنتاج
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Africa/Casablanca');

// إعدادات مطابقة لـ includes/config.php (تدعم متغيرات البيئة للسحابة)
$_dbUrl = getenv('DATABASE_URL');
if ($_dbUrl) {
    $_dbParts = parse_url($_dbUrl);
    if ($_dbParts && isset($_dbParts['host'])) {
        putenv('DB_HOST=' . $_dbParts['host'] . (isset($_dbParts['port']) ? ':' . $_dbParts['port'] : ''));
        putenv('DB_NAME=' . ltrim($_dbParts['path'] ?? '', '/'));
        if (isset($_dbParts['user'])) putenv('DB_USER=' . $_dbParts['user']);
        if (isset($_dbParts['pass'])) putenv('DB_PASS=' . $_dbParts['pass']);
    }
}
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME') ?: 'car_rental_db';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$steps = [];
$failed = false;

function step(&$steps, $ok, $label, $detail = '') {
    $steps[] = ['ok' => $ok, 'label' => $label, 'detail' => $detail];
}

// ---------- Step 0: PHP version ----------
step($steps, version_compare(PHP_VERSION, '7.4.0', '>='), 'إصدار PHP: ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4.0', '>=') ? '' : 'المطلوب 7.4 أو أحدث');

// ---------- Step 1: connect without db ----------
try {
    $pdoRoot = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    step($steps, true, 'الاتصال بخادم MySQL ناجح');
} catch (PDOException $e) {
    step($steps, false, 'فشل الاتصال بـ MySQL', $e->getMessage());
    $failed = true;
}

// ---------- Step 2: create database ----------
if (!$failed) {
    try {
        $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        step($steps, true, "قاعدة البيانات `$DB_NAME` جاهزة");
        $pdoRoot->exec("USE `$DB_NAME`");
    } catch (PDOException $e) {
        step($steps, false, 'تعذر إنشاء قاعدة البيانات', $e->getMessage());
        $failed = true;
    }
}

// ---------- Step 3: run database.sql ----------
if (!$failed) {
    try {
        $sqlFile = __DIR__ . '/database.sql';
        $sql = file_get_contents($sqlFile);
        // إزالة التعليقات ثم تقسيم الجُمل على الفاصلة المنقوطة في نهاية السطر
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        $count = 0;
        foreach ($statements as $stmt) {
            if ($stmt === '' || $stmt === ';') continue;
            // على الاستضافات المُدارة (Vercel + TiDB وغيرها) قد لا يُسمح بإنشاء/اختيار قاعدة بيانات
            if (preg_match('/^(CREATE DATABASE|USE )/i', $stmt)) {
                try { $pdoRoot->exec($stmt); $count++; } catch (PDOException $e) { /* تجاهل */ }
                continue;
            }
            $pdoRoot->exec($stmt);
            $count++;
        }
        step($steps, true, "تم تنفيذ مخطط قاعدة البيانات ($count جملة)");
    } catch (PDOException $e) {
        step($steps, false, 'خطأ أثناء تنفيذ المخطط', $e->getMessage());
        $failed = true;
    }
}

// ---------- Step 4: ensure admin exists ----------
$adminCreated = false;
$adminPassword = 'Admin@123';
if (!$failed) {
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $exists = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@carrental.ma'")->fetchColumn();
        if (!$exists) {
            $hash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);
            $st = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, email_verified_at) VALUES ('مدير النظام', 'admin@carrental.ma', :p, '0600000000', 'super_admin', 'active', NOW())");
            $st->execute([':p' => $hash]);
            $adminCreated = true;
        }
        step($steps, true, 'حساب المدير جاهز: admin@carrental.ma' . ($adminCreated ? ' (تم إنشاؤه الآن)' : ' (موجود مسبقاً)'));
    } catch (PDOException $e) {
        step($steps, false, 'تعذر التحقق من حساب المدير', $e->getMessage());
        $failed = true;
    }
}

// ---------- Step 5: directories ----------
if (!$failed) {
    $dirs = ['uploads', 'uploads/cars', 'uploads/avatars', 'uploads/invoices', 'assets/images'];
    $okAll = true;
    foreach ($dirs as $d) {
        $path = __DIR__ . '/' . $d;
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true)) $okAll = false;
        }
    }
    step($steps, $okAll, 'إنشاء مجلدات الرفع (uploads)', $okAll ? '' : 'تحقق من صلاحيات الكتابة');
}

// ---------- Step 6: count cars ----------
$carsCount = 0;
if (!$failed) {
    try {
        $carsCount = (int)$pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
        step($steps, true, "عدد السيارات في القاعدة: $carsCount", $carsCount > 0 ? '' : 'يمكنك إضافة سيارات تجريبية من الروابط أدناه');
    } catch (Exception $e) {}
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تثبيت نظام تأجير السيارات</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 15px; }
    .card { background: #fff; max-width: 720px; margin: 0 auto; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,.3); overflow: hidden; }
    .card-head { background: #1a1a2e; color: #fff; padding: 30px; text-align: center; }
    .card-head h1 { font-size: 1.6rem; font-weight: 900; }
    .card-head p { color: #aab; margin-top: 6px; font-size: .95rem; }
    .card-body { padding: 30px; }
    .step { display: flex; align-items: flex-start; gap: 12px; padding: 14px; border-radius: 12px; margin-bottom: 10px; background: #f8f9fb; }
    .step.ok { background: #ecfdf5; }
    .step.fail { background: #fef2f2; }
    .step .icon { width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; color: #fff; }
    .step.ok .icon { background: #10b981; }
    .step.fail .icon { background: #ef4444; }
    .step b { display: block; font-size: .95rem; color: #1a1a2e; }
    .step small { color: #6b7280; font-size: .8rem; word-break: break-word; }
    .btns { text-align: center; margin-top: 25px; }
    .btn { display: inline-block; padding: 13px 28px; border-radius: 12px; text-decoration: none; font-weight: 700; margin: 6px; font-size: .92rem; }
    .btn-p { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
    .btn-g { background: #10b981; color: #fff; }
    .btn-o { background: #fff; color: #667eea; border: 2px solid #667eea; }
    .warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 14px 18px; border-radius: 12px; margin-top: 20px; font-size: .88rem; }
</style>
</head>
<body>
<div class="card">
    <div class="card-head">
        <h1>🚗 معالج تثبيت نظام تأجير السيارات</h1>
        <p>Premium Car Rental — Setup Wizard</p>
    </div>
    <div class="card-body">
        <?php foreach ($steps as $s): ?>
            <div class="step <?php echo $s['ok'] ? 'ok' : 'fail'; ?>">
                <div class="icon"><?php echo $s['ok'] ? '✓' : '✗'; ?></div>
                <div>
                    <b><?php echo htmlspecialchars($s['label']); ?></b>
                    <?php if (!empty($s['detail'])): ?><small><?php echo htmlspecialchars($s['detail']); ?></small><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if ($failed): ?>
            <div class="warn">
                ❌ فشل التثبيت. صحّح الأخطاء أعلاه (غالباً: خدمة MySQL غير مشغلة أو بيانات الدخول في أعلى هذا الملف غير صحيحة)، ثم أعد فتح الصفحة.
            </div>
        <?php else: ?>
            <div class="warn">
                🔐 بيانات دخول لوحة الإدارة: <b>admin@carrental.ma</b> / <b>Admin@123</b> — غيّرها فور أول تسجيل دخول!
            </div>
            <div class="btns">
                <?php if ($carsCount == 0): ?>
                    <a class="btn btn-g" href="add-all-cars.php">🚙 إضافة 15 سيارة تجريبية</a>
                    <a class="btn btn-o" href="seed-cars.php">إضافة 3 سيارات فقط</a>
                <?php endif; ?>
                <a class="btn btn-p" href="index.php">🏠 الانتقال إلى الموقع</a>
                <a class="btn btn-o" href="admin/index.php">⚙️ لوحة الإدارة</a>
            </div>
            <div class="warn" style="background:#fef2f2;border-color:#fca5a5;color:#991b1b;">
                ⚠️ للأمان: احذف الملفات <b>install.php</b> و<b>create-tables.php</b> و<b>create-admin.php</b> بعد اكتمال التثبيت.
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
