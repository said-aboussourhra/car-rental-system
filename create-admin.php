<?php
// create-admin.php - إنشاء حساب مدير عبر سطر الأوامر أو المتصفح
// ⚠️ احذف هذا الملف فور الانتهاء من استخدامه
//
// الاستخدام من سطر الأوامر (الطريقة الموصى بها):
//   php create-admin.php admin@example.com "كلمة_المرور" "الاسم الكامل"
// بدون معطيات: يُنشئ حساباً بكلمة مرور عشوائية تُعرض مرة واحدة فقط.

require_once 'includes/config.php';

$is_cli = (PHP_SAPI === 'cli');

// المعطيات
$email = $argv[1] ?? ($_GET['email'] ?? '');
$password = $argv[2] ?? '';
$full_name = $argv[3] ?? 'مدير النظام';
$generated = false;

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'admin@carrental.ma';
}
if ($password === '') {
    $password = generate_random_password(14);
    $generated = true;
}

if (!$is_cli) {
    echo '<!DOCTYPE html><html dir="rtl"><head><meta charset="UTF-8"><title>إنشاء حساب المدير</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Cairo", sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); text-align: center; }
        .success { color: #10b981; font-weight: 700; }
        .error { color: #ef4444; font-weight: 700; }
        .info { background: #f0f9ff; padding: 15px; border-radius: 10px; margin: 15px 0; }
        .warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: 12px; border-radius: 10px; font-size: .85rem; }
        .btn { display: inline-block; padding: 14px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 700; margin: 10px; }
        h2 { color: #1a1a2e; }
        code { background:#eef0ff; padding:2px 8px; border-radius:6px; }
    </style></head><body><div class="card"><h2>🔐 إنشاء حساب المدير</h2>';
}

try {
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    if ($existing) {
        // ترقية الحساب الموجود إلى مدير
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'super_admin', status = 'active', full_name = ? WHERE email = ?");
        $stmt->execute([$hashed, $full_name, $email]);
        $msg = '✅ تم تحديث الحساب وترقيته إلى مدير النظام';
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, email_verified_at, created_at, updated_at) VALUES (?, ?, ?, '0600000000', 'super_admin', 'active', NOW(), NOW(), NOW())");
        $stmt->execute([$full_name, $email, $hashed]);
        $msg = '✅ تم إنشاء حساب المدير بنجاح';
    }

    if ($is_cli) {
        echo "$msg\n";
        echo "البريد: $email\nكلمة المرور: $password" . ($generated ? " (مولّدة تلقائياً — احفظها الآن!)" : "") . "\n";
    } else {
        echo "<p class='success'>$msg</p>";
        echo '<div class="info"><h3>بيانات تسجيل الدخول:</h3>';
        echo '<p>📧 البريد: <strong>' . htmlspecialchars($email) . '</strong></p>';
        echo '<p>🔑 كلمة المرور: <code>' . htmlspecialchars($password) . '</code></p>';
        if ($generated) echo '<p style="color:#f59e0b;">⚠️ كلمة المرور مولّدة تلقائياً — انسخها الآن، لن تظهر مرة أخرى.</p>';
        echo '</div>';
        echo '<div class="warn">🔒 للأمان: احذف ملف <b>create-admin.php</b> الآن، وغيّر كلمة المرور بعد أول دخول.</div>';
        echo '<a href="login.php" class="btn">🔐 تسجيل الدخول</a>';
    }
} catch (Exception $e) {
    $err = '❌ خطأ: ' . $e->getMessage();
    if ($is_cli) {
        fwrite(STDERR, $err . "\n");
        exit(1);
    }
    echo "<p class='error'>" . htmlspecialchars($err) . "</p>";
}

if (!$is_cli) {
    echo '</div></body></html>';
}
