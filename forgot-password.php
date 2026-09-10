<?php
/**
 * استعادة كلمة المرور - الخطوة 1: إدخال البريد الإلكتروني
 */
require_once 'includes/config.php';
require_once 'includes/auth.php';

if (is_logged_in()) {
    redirect('customer/dashboard.php');
}

$auth = new Auth($pdo);
$message = '';
$messageType = '';
$email_value = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $email_value = clean_input($_POST['email'] ?? '');

    if (!filter_var($email_value, FILTER_VALIDATE_EMAIL)) {
        $message = 'يرجى إدخال بريد إلكتروني صالح';
        $messageType = 'error';
    } else {
        $result = $auth->resetPassword($email_value);
        $message = $result['message'];
        $messageType = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" type="image/svg+xml" href="<?php echo SITE_FAVICON; ?>">
    <style>
        :root { --gold:#c9a84c; --gold-light:#f5ecd7; --dark:#121312; }
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
            box-shadow:0 30px 80px rgba(0,0,0,0.5);
            animation:fadeUp .6s ease;
        }
        @keyframes fadeUp{from{opacity:0;transform:translateY(25px);}to{opacity:1;transform:none;}}
        .brand{text-align:center;color:var(--gold);letter-spacing:4px;font-size:.75rem;font-weight:700;margin-bottom:22px;}
        .icon-circle{
            width:80px;height:80px;margin:0 auto 22px;border-radius:50%;
            background:linear-gradient(135deg,var(--gold),#a68a3e);
            display:flex;align-items:center;justify-content:center;font-size:2rem;color:#121312;
        }
        h2{color:#fff;text-align:center;font-weight:800;font-size:1.5rem;margin-bottom:8px;}
        .sub{color:rgba(255,255,255,.55);text-align:center;font-size:.9rem;margin-bottom:28px;line-height:1.8;}
        .input-wrap{position:relative;margin-bottom:20px;}
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
            font-family:'Cairo';font-weight:800;font-size:1rem;letter-spacing:.5px;transition:.3s;
        }
        .btn-submit:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(201,168,76,.35);}
        .alert{padding:13px 16px;border-radius:10px;margin-bottom:20px;font-size:.88rem;text-align:center;}
        .alert.error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.4);color:#fca5a5;}
        .alert.success{background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.4);color:#6ee7b7;}
        .back{text-align:center;margin-top:22px;font-size:.85rem;}
        .back a{color:var(--gold);text-decoration:none;font-weight:600;}
        .back a:hover{text-decoration:underline;}
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">PREMIUM CAR RENTAL</div>
        <div class="icon-circle"><i class="fas fa-key"></i></div>
        <h2>نسيت كلمة المرور؟</h2>
        <p class="sub">أدخل بريدك الإلكتروني وسنرسل لك رابطاً لإعادة تعيين كلمة المرور</p>

        <?php if ($message): ?>
            <div class="alert <?php echo $messageType; ?>"><?php echo e($message); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>
            <div class="input-wrap">
                <input type="email" name="email" placeholder="البريد الإلكتروني" value="<?php echo e($email_value); ?>" required>
                <i class="far fa-envelope"></i>
            </div>
            <button type="submit" class="btn-submit">
                <i class="fas fa-paper-plane me-2"></i> إرسال رابط الاستعادة
            </button>
        </form>

        <div class="back">
            تذكرت كلمة المرور؟ <a href="login.php">تسجيل الدخول</a>
        </div>
    </div>
</body>
</html>
