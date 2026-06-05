<?php
// create-admin.php - Create admin account
require_once 'includes/config.php';

echo '<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إنشاء حساب المدير</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: "Cairo", sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f0f2f5; }
        .card { background: white; padding: 30px; border-radius: 16px; box-shadow: 0 5px 25px rgba(0,0,0,0.08); text-align: center; }
        .success { color: #10b981; font-weight: 700; }
        .error { color: #ef4444; font-weight: 700; }
        .info { background: #f0f9ff; padding: 15px; border-radius: 10px; margin: 15px 0; }
        .btn { display: inline-block; padding: 14px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 700; margin: 10px; }
        .btn:hover { background: #5a6fd6; }
        h2 { color: #1a1a2e; }
    </style>
</head>
<body>
<div class="card">
    <h2>🔐 إنشاء حساب المدير</h2>';

// Admin data
$email = 's01said@outlook.fr';
$password = 'SAID2002';
$full_name = 'سعيد';
$role = 'super_admin';

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing user to admin
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, role = ?, status = 'active', full_name = ? WHERE email = ?");
        $stmt->execute([$hashed, $role, $full_name, $email]);
        
        echo '<p class="success">✅ تم تحديث الحساب إلى مدير النظام</p>';
    } else {
        // Create new admin
        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())");
        $stmt->execute([$full_name, $email, $hashed, '0600000000', $role]);
        
        echo '<p class="success">✅ تم إنشاء حساب المدير بنجاح</p>';
    }
    
    echo '<div class="info">';
    echo '<h3>بيانات تسجيل الدخول:</h3>';
    echo '<p>📧 البريد: <strong>' . $email . '</strong></p>';
    echo '<p>🔑 كلمة المرور: <strong>' . $password . '</strong></p>';
    echo '<p>👤 الصلاحية: <strong>مدير النظام</strong></p>';
    echo '</div>';
    
    echo '<a href="logout.php" class="btn">🚪 تسجيل الخروج أولاً</a>';
    echo '<a href="login.php" class="btn">🔐 تسجيل الدخول</a>';
    
} catch (Exception $e) {
    echo '<p class="error">❌ خطأ: ' . $e->getMessage() . '</p>';
}

echo '</div></body></html>';
?>