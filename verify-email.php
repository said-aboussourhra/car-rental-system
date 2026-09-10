<?php
/**
 * تفعيل الحساب عبر رمز التحقق المرسل بالبريد
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth($pdo);
$token = $_GET['token'] ?? '';
$result = ['success' => false, 'message' => 'رمز التفعيل مفقود'];

if (!empty($token)) {
    try {
        $result = $auth->verifyEmail($token);
    } catch (Exception $e) {
        $result = ['success' => false, 'message' => 'حدث خطأ أثناء التفعيل'];
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفعيل الحساب | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_FAVICON; ?>">
    <style>
        :root { --gold:#c9a84c; }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Cairo',sans-serif;min-height:100vh;
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#0f0f1a 0%,#1a1a2e 50%,#16213e 100%);
            padding:20px;
        }
        .card{
            width:100%;max-width:460px;background:rgba(255,255,255,0.03);
            border:1px solid rgba(201,168,76,0.25);border-radius:24px;
            padding:50px 40px;backdrop-filter:blur(20px);
            box-shadow:0 30px 80px rgba(0,0,0,0.5);text-align:center;
            animation:fadeUp .6s ease;
        }
        @keyframes fadeUp{from{opacity:0;transform:translateY(25px);}to{opacity:1;transform:none;}}
        .icon-circle{
            width:90px;height:90px;margin:0 auto 25px;border-radius:50%;
            display:flex;align-items:center;justify-content:center;font-size:2.4rem;
        }
        .icon-ok{background:linear-gradient(135deg,#10b981,#059669);color:#fff;}
        .icon-fail{background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;}
        h2{color:#fff;font-weight:800;font-size:1.5rem;margin-bottom:12px;}
        p{color:rgba(255,255,255,.6);font-size:.95rem;line-height:1.9;margin-bottom:25px;}
        .btn{
            display:inline-block;padding:14px 35px;border-radius:12px;text-decoration:none;
            font-weight:800;font-size:.95rem;transition:.3s;
            background:linear-gradient(135deg,var(--gold),#a68a3e);color:#121312;
        }
        .btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(201,168,76,.35);}
    </style>
</head>
<body>
    <div class="card">
        <?php if ($result['success']): ?>
            <div class="icon-circle icon-ok"><i class="fas fa-check"></i></div>
            <h2>تم تفعيل حسابك بنجاح! 🎉</h2>
            <p><?php echo e($result['message']); ?></p>
            <a href="login.php" class="btn"><i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول الآن</a>
        <?php else: ?>
            <div class="icon-circle icon-fail"><i class="fas fa-times"></i></div>
            <h2>تعذر تفعيل الحساب</h2>
            <p><?php echo e($result['message']); ?></p>
            <a href="register.php" class="btn"><i class="fas fa-user-plus me-2"></i> إنشاء حساب جديد</a>
        <?php endif; ?>
    </div>
</body>
</html>
