<?php
/**
 * استعادة كلمة المرور - الخطوة 2: كلمة المرور الجديدة
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect('customer/dashboard.php');
}

$auth = new Auth($pdo);
$token = $_GET['token'] ?? ($_POST['token'] ?? '');
$error = '';
$success = '';
$token_valid = false;

if (!empty($token)) {
    try {
        $token_valid = ($auth->validateResetToken($token) !== null);
    } catch (Exception $e) {
        $token_valid = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$token_valid) {
        $error = 'الرابط غير صالح أو منتهي الصلاحية. اطلب رابطاً جديداً.';
    } elseif ($password !== $confirm) {
        $error = 'كلمتا المرور غير متطابقتين';
    } else {
        $result = $auth->completePasswordReset($token, $password);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة المرور | <?php echo SITE_NAME; ?></title>
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
            padding:45px 40px;backdrop-filter:blur(20px);
            box-shadow:0 30px 80px rgba(0,0,0,0.5);animation:fadeUp .6s ease;
        }
        @keyframes fadeUp{from{opacity:0;transform:translateY(25px);}to{opacity:1;transform:none;}}
        .brand{text-align:center;color:var(--gold);letter-spacing:4px;font-size:.75rem;font-weight:700;margin-bottom:22px;}
        .icon-circle{
            width:80px;height:80px;margin:0 auto 22px;border-radius:50%;
            background:linear-gradient(135deg,var(--gold),#a68a3e);
            display:flex;align-items:center;justify-content:center;font-size:2rem;color:#121312;
        }
        h2{color:#fff;text-align:center;font-weight:800;font-size:1.5rem;margin-bottom:8px;}
        .sub{color:rgba(255,255,255,.55);text-align:center;font-size:.9rem;margin-bottom:28px;}
        .input-wrap{position:relative;margin-bottom:18px;}
        input{
            width:100%;padding:15px 50px 15px 18px;background:rgba(255,255,255,.05);
            border:1px solid rgba(255,255,255,.15);border-radius:12px;color:#fff;
            font-family:'Cairo';font-size:.95rem;outline:none;transition:.3s;
        }
        input:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,168,76,.15);}
        input::placeholder{color:rgba(255,255,255,.35);}
        .input-wrap i{position:absolute;right:18px;top:50%;transform:translateY(-50%);color:var(--gold);}
        .btn-submit{
            width:100%;padding:15px;border:none;border-radius:12px;cursor:pointer;
            background:linear-gradient(135deg,var(--gold),#a68a3e);color:#121312;
            font-family:'Cairo';font-weight:800;font-size:1rem;transition:.3s;
        }
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(201,168,76,.35);}
        .alert{padding:13px 16px;border-radius:10px;margin-bottom:20px;font-size:.88rem;text-align:center;}
        .alert.error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);color:#fca5a5;}
        .alert.success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.4);color:#6ee7b7;line-height:1.8;}
        .back{text-align:center;margin-top:22px;font-size:.85rem;}
        .back a{color:var(--gold);text-decoration:none;font-weight:600;}
        .strength{height:5px;border-radius:3px;background:rgba(255,255,255,.1);margin:-8px 0 16px;overflow:hidden;}
        .strength span{display:block;height:100%;width:0;background:#ef4444;transition:.4s;}
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">PREMIUM CAR RENTAL</div>
        <div class="icon-circle"><i class="fas fa-lock"></i></div>
        <h2>كلمة مرور جديدة</h2>
        <p class="sub">اختر كلمة مرور قوية لم تستخدمها من قبل</p>

        <?php if ($error): ?>
            <div class="alert error"><?php echo e($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success">
                ✅ <?php echo e($success); ?><br>
                <a href="login.php" style="color:#6ee7b1;font-weight:700;">الانتقال إلى تسجيل الدخول ←</a>
            </div>
        <?php elseif (!$token_valid): ?>
            <div class="alert error">
                ⚠️ هذا الرابط غير صالح أو انتهت صلاحيته.<br>
                <a href="forgot-password.php" style="color:#fca5a5;font-weight:700;">اطلب رابطاً جديداً</a>
            </div>
        <?php else: ?>
            <form method="POST" autocomplete="off" id="resetForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="token" value="<?php echo e($token); ?>">
                <div class="input-wrap">
                    <input type="password" name="password" id="password" placeholder="كلمة المرور الجديدة (8+ أحرف)" minlength="8" required>
                    <i class="fas fa-lock"></i>
                </div>
                <div class="strength"><span id="strengthBar"></span></div>
                <div class="input-wrap">
                    <input type="password" name="confirm_password" placeholder="تأكيد كلمة المرور" minlength="8" required>
                    <i class="fas fa-lock"></i>
                </div>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check me-2"></i> حفظ كلمة المرور
                </button>
            </form>
        <?php endif; ?>

        <div class="back">
            <a href="login.php"><i class="fas fa-arrow-right me-1"></i> العودة لتسجيل الدخول</a>
        </div>
    </div>

    <script>
        // مؤشر قوة كلمة المرور
        var pw = document.getElementById('password');
        if (pw) {
            pw.addEventListener('input', function () {
                var v = pw.value, s = 0;
                if (v.length >= 8) s++;
                if (/[A-Z]/.test(v) && /[a-z]/.test(v)) s++;
                if (/[0-9]/.test(v)) s++;
                if (/[^A-Za-z0-9]/.test(v)) s++;
                var bar = document.getElementById('strengthBar');
                var colors = ['#ef4444', '#f59e0b', '#eab308', '#10b981'];
                bar.style.width = (s * 25) + '%';
                bar.style.background = colors[Math.max(0, s - 1)] || '#ef4444';
            });
        }
    </script>
</body>
</html>
