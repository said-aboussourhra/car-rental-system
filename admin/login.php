<?php
/**
 * Premium Car Rental - Login Page
 * Ultimate Version with Admin Redirect
 */

require_once '../includes/config.php';

// If already logged in, redirect
if (is_logged_in()) {
    if (is_admin()) {
        redirect('admin/index.php');
    }
    redirect('customer/dashboard.php');
}

$error = '';
$email_value = '';

// Process login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $email_value = $email;
    
    if (empty($email) || empty($password)) {
        $error = 'يرجى إدخال البريد الإلكتروني وكلمة المرور';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => strtolower($email)]);
            $user = $stmt->fetch();
            
            if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                session_regenerate_id(true);
                
                // Redirect based on role
                if ($user['role'] === 'admin' || $user['role'] === 'super_admin') {
                    redirect('admin/index.php');
                } else {
                    redirect('customer/dashboard.php');
                }
            } else {
                $error = 'البريد الإلكتروني أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ. يرجى المحاولة مرة أخرى.';
        }
    }
}

$page_title = 'تسجيل الدخول | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
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
            --shadow: 0 10px 40px rgba(0,0,0,0.1);
            --shadow-lg: 0 20px 60px rgba(0,0,0,0.15);
            --radius: 20px;
            --radius-sm: 12px;
            --transition: all 0.3s ease;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Background Animation */
        .bg-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
        }
        
        .bg-circle {
            position: absolute;
            border-radius: 50%;
            background: var(--gradient);
            opacity: 0.08;
            animation: float 15s infinite ease-in-out;
        }
        
        .bg-circle:nth-child(1) { width: 500px; height: 500px; top: -150px; right: -100px; }
        .bg-circle:nth-child(2) { width: 400px; height: 400px; bottom: -120px; left: -80px; animation-delay: -5s; }
        .bg-circle:nth-child(3) { width: 300px; height: 300px; top: 40%; left: 30%; animation-delay: -10s; }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        /* Auth Container */
        .auth-container {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 950px;
        }
        
        .auth-card {
            background: rgba(255,255,255,0.98);
            backdrop-filter: blur(20px);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .auth-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 550px;
        }
        
        /* Form Side */
        .auth-form-side {
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .auth-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 30px;
            justify-content: center;
        }
        
        .auth-logo .logo-icon {
            width: 50px;
            height: 50px;
            background: var(--gradient);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }
        
        .auth-logo .logo-text {
            font-weight: 900;
            font-size: 1.4rem;
            color: var(--dark);
        }
        
        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .form-group .form-control {
            height: 55px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 10px 45px 10px 15px;
            font-size: 1rem;
            transition: var(--transition);
            background: #f8f9fa;
            font-family: 'Cairo', sans-serif;
        }
        
        .form-group .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
            background: white;
            outline: none;
        }
        
        .form-group .form-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
            font-size: 1.1rem;
            pointer-events: none;
            transition: var(--transition);
        }
        
        .form-group .form-control:focus ~ .form-icon { color: var(--primary); }
        
        .password-toggle {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 5px;
            z-index: 5;
            transition: var(--transition);
        }
        
        .password-toggle:hover { color: var(--primary); }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            border-radius: var(--radius-sm);
            background: var(--gradient);
            border: none;
            color: white;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.4);
        }
        
        .alert {
            border-radius: var(--radius-sm);
            padding: 15px;
            margin-bottom: 20px;
            border: none;
            font-weight: 500;
        }
        
        /* Info Side */
        .auth-info-side {
            background: var(--gradient);
            padding: 50px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .auth-info-side::before {
            content: '';
            position: absolute;
            top: -30%;
            left: -30%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        
        .auth-info-side i { font-size: 4rem; margin-bottom: 20px; position: relative; }
        .auth-info-side h2 { font-weight: 900; margin-bottom: 10px; position: relative; }
        .auth-info-side p { opacity: 0.9; position: relative; }
        
        @media (max-width: 768px) {
            .auth-grid { grid-template-columns: 1fr; }
            .auth-info-side { display: none; }
            .auth-form-side { padding: 35px 25px; }
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="bg-particles">
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
        <div class="bg-circle"></div>
    </div>
    
    <!-- Auth Container -->
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-grid">
                <!-- Form Side -->
                <div class="auth-form-side">
                    <div class="auth-logo">
                        <div class="logo-icon">🚗</div>
                        <span class="logo-text"><?php echo SITE_NAME; ?></span>
                    </div>
                    
                    <h4 class="fw-bold text-center mb-1">تسجيل الدخول</h4>
                    <p class="text-muted text-center mb-4">مرحباً بعودتك! سجل دخولك للمتابعة</p>
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="loginForm">
                    <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <input type="email" class="form-control" name="email" 
                                   placeholder="البريد الإلكتروني" 
                                   value="<?php echo htmlspecialchars($email_value); ?>" required>
                            <i class="fas fa-envelope form-icon"></i>
                        </div>
                        
                        <div class="form-group">
                            <input type="password" class="form-control" name="password" 
                                   id="loginPassword" placeholder="كلمة المرور" required>
                            <i class="fas fa-lock form-icon"></i>
                            <button type="button" class="password-toggle" onclick="togglePass()">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">تذكرني</label>
                            </div>
                            <a href="forgot-password.php" class="text-primary fw-bold">نسيت كلمة المرور؟</a>
                        </div>
                        
                        <button type="submit" class="btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i> تسجيل الدخول
                        </button>
                    </form>
                    
                    <p class="text-center mt-4 mb-0">
                        ليس لديك حساب؟ 
                        <a href="register.php" class="text-primary fw-bold">إنشاء حساب جديد</a>
                    </p>
                    
                    <!-- Quick Links -->
                    <div class="text-center mt-3">
                        <small class="text-muted">حساب تجريبي للمدير:</small><br>
                        <small><strong>admin@carrental.ma</strong> / <strong>Admin@123</strong></small>
                    </div>
                </div>
                
                <!-- Info Side -->
                <div class="auth-info-side">
                    <i class="fas fa-car-side"></i>
                    <h2>أهلاً بك في عالم السيارات الفاخرة</h2>
                    <p>سجل دخولك للوصول إلى لوحة التحكم وإدارة أعمالك</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePass() {
            const input = document.getElementById('loginPassword');
            const icon = document.getElementById('toggleIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
