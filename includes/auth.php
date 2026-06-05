<?php
/**
 * auth.php – Authentication Class
 */

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * تسجيل مستخدم جديد
     */
    public function register($data) {
        // التحقق من وجود البريد أو الهاتف
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute([':email' => $data['email'], ':phone' => $data['phone']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'البريد الإلكتروني أو رقم الهاتف مسجل بالفعل'];
        }
        
        // التحقق من تطابق كلمة المرور
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'كلمتا المرور غير متطابقتين'];
        }
        
        // تشفير كلمة المرور
        $hashed = hash_password($data['password']);
        $verification_token = bin2hex(random_bytes(32));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO users (full_name, email, password, phone, role, email_verification_token, created_at)
            VALUES (:name, :email, :password, :phone, 'customer', :token, NOW())
        ");
        
        $result = $stmt->execute([
            ':name' => $data['full_name'],
            ':email' => $data['email'],
            ':password' => $hashed,
            ':phone' => $data['phone'],
            ':token' => $verification_token
        ]);
        
        if ($result) {
            $user_id = $this->pdo->lastInsertId();
            // إرسال بريد التفعيل
            $this->sendVerificationEmail($data['email'], $verification_token, $data['full_name']);
            return ['success' => true, 'user_id' => $user_id];
        }
        
        return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الحساب'];
    }
    
    /**
     * تسجيل الدخول
     */
    public function login($email, $password, $remember = false) {
        $stmt = $this->pdo->prepare("
            SELECT id, full_name, email, password, role, status, avatar 
            FROM users 
            WHERE email = :email OR phone = :email
            LIMIT 1
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user || !verify_password($password, $user['password'])) {
            $this->logFailedAttempt($email);
            return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'];
        }
        
        if ($user['status'] !== 'active') {
            return ['success' => false, 'message' => 'الحساب غير نشط. يرجى تفعيله عبر البريد الإلكتروني'];
        }
        
        // تسجيل الدخول
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['logged_in'] = true;
        
        // تحديث آخر تسجيل دخول
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
        
        // تذكرني
        if ($remember) {
            $this->setRememberToken($user['id']);
        }
        
        log_activity($user['id'], 'login', 'تسجيل دخول ناجح', $this->pdo);
        
        return ['success' => true, 'user' => $user];
    }
    
    /**
     * تسجيل الخروج
     */
    public function logout() {
        if (is_logged_in()) {
            log_activity($_SESSION['user_id'], 'logout', 'تسجيل خروج', $this->pdo);
        }
        
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // حذف كوكيز التذكر
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }
        
        return true;
    }
    
    /**
     * التحقق من توفر السيارة في تواريخ محددة
     */
    public function checkCarAvailability($car_id, $pickup_date, $return_date) {
        return check_car_availability($this->pdo, $car_id, $pickup_date, $return_date);
    }
    
    /**
     * تغيير كلمة المرور
     */
    public function changePassword($user_id, $old_password, $new_password) {
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();
        
        if (!verify_password($old_password, $user['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
        }
        
        $hashed = hash_password($new_password);
        $stmt = $this->pdo->prepare("UPDATE users SET password = :pass WHERE id = :id");
        $result = $stmt->execute([':pass' => $hashed, ':id' => $user_id]);
        
        if ($result) {
            log_activity($user_id, 'change_password', 'تغيير كلمة المرور', $this->pdo);
            return ['success' => true];
        }
        
        return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير كلمة المرور'];
    }
    
    /**
     * إعادة تعيين كلمة المرور (نسيتها)
     */
    public function resetPassword($email) {
        $stmt = $this->pdo->prepare("SELECT id, full_name FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'البريد الإلكتروني غير مسجل'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at) 
            VALUES (:user_id, :token, :expires)
            ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires
        ");
        $stmt->execute([':user_id' => $user['id'], ':token' => $token, ':expires' => $expires]);
        
        // إرسال بريد إعادة التعيين
        $reset_link = SITE_URL . "/reset-password.php?token=" . $token;
        $subject = "إعادة تعيين كلمة المرور";
        $body = "مرحباً {$user['full_name']},<br> اضغط على الرابط التالي لإعادة تعيين كلمة المرور:<br>
                 <a href='{$reset_link}'>$reset_link</a><br> ينتهي الرابط خلال ساعة.";
        
        send_email($email, $subject, $body);
        
        return ['success' => true, 'message' => 'تم إرسال رابط إعادة التعيين إلى بريدك الإلكتروني'];
    }
    
    // دوال خاصة
    private function sendVerificationEmail($email, $token, $name) {
        $verify_link = SITE_URL . "/verify-email.php?token=" . $token;
        $subject = "تفعيل حسابك في " . SITE_NAME;
        $body = "مرحباً $name,<br> شكراً لتسجيلك. يرجى تفعيل حسابك عبر الرابط التالي:<br>
                 <a href='{$verify_link}'>$verify_link</a>";
        send_email($email, $subject, $body);
    }
    
    private function setRememberToken($user_id) {
        $token = bin2hex(random_bytes(32));
        $hashed = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO remember_tokens (user_id, token, expires_at) 
            VALUES (:user_id, :token, :expires)
            ON DUPLICATE KEY UPDATE token = :token, expires_at = :expires
        ");
        $stmt->execute([':user_id' => $user_id, ':token' => $hashed, ':expires' => $expires]);
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', true, true);
    }
    
    private function logFailedAttempt($email) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $this->pdo->prepare("
            INSERT INTO login_attempts (email, ip_address, attempt_time) 
            VALUES (:email, :ip, NOW())
        ");
        $stmt->execute([':email' => $email, ':ip' => $ip]);
    }
}