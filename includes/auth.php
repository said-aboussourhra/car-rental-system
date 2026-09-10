<?php
/**
 * auth.php – Authentication Class (النسخة المطورة)
 * إصلاحات: استدعاءات الدوال، تأمين الجلسة، تحديد محاولات الدخول، تذكرني
 */

require_once __DIR__ . '/config.php';

class Auth {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * تسجيل مستخدم جديد
     */
    public function register($data) {
        $data['email'] = strtolower(trim($data['email'] ?? ''));
        $data['phone'] = trim($data['phone'] ?? '');

        // التحقق من صحة المدخلات
        if (mb_strlen($data['full_name'] ?? '') < 3) {
            return ['success' => false, 'message' => 'الاسم الكامل مطلوب (3 أحرف على الأقل)'];
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'البريد الإلكتروني غير صالح'];
        }
        if (!preg_match('/^[0-9+\s\-]{9,15}$/', $data['phone'])) {
            return ['success' => false, 'message' => 'رقم الهاتف غير صالح'];
        }

        // التحقق من وجود البريد أو الهاتف
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email OR phone = :phone");
        $stmt->execute([':email' => $data['email'], ':phone' => $data['phone']]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'البريد الإلكتروني أو رقم الهاتف مسجل بالفعل'];
        }

        // التحقق من قوة كلمة المرور
        if (strlen($data['password'] ?? '') < 8) {
            return ['success' => false, 'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'];
        }

        // التحقق من تطابق كلمة المرور
        if ($data['password'] !== $data['confirm_password']) {
            return ['success' => false, 'message' => 'كلمتا المرور غير متطابقتين'];
        }

        // تشفير كلمة المرور
        $hashed = hash_password($data['password']);
        $verification_token = bin2hex(random_bytes(32));

        $stmt = $this->pdo->prepare("
            INSERT INTO users (full_name, email, password, phone, role, status, email_verification_token, created_at, updated_at)
            VALUES (:name, :email, :password, :phone, 'customer', 'active', :token, NOW(), NOW())
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
            // إرسال بريد التفعيل (الحساب نشط مباشرة، والرمز للتحقق الاختياري)
            $this->sendVerificationEmail($data['email'], $verification_token, $data['full_name']);
            log_activity($user_id, 'register', 'تسجيل حساب جديد');
            return ['success' => true, 'user_id' => $user_id];
        }

        return ['success' => false, 'message' => 'حدث خطأ أثناء إنشاء الحساب'];
    }

    /**
     * تسجيل الدخول (مع حماية من هجمات التخمين)
     */
    public function login($email, $password, $remember = false) {
        $email = strtolower(trim($email));
        $ip = get_client_ip();

        // فحص المحاولات الفاشلة (قفل مؤقت)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE (email = :email OR ip_address = :ip)
            AND attempt_time > DATE_SUB(NOW(), INTERVAL " . (int)LOGIN_LOCKOUT_MINUTES . " MINUTE)
        ");
        $stmt->execute([':email' => $email, ':ip' => $ip]);
        if ($stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS) {
            return ['success' => false, 'message' => 'تم تجاوز عدد المحاولات المسموح. حاول مجدداً بعد ' . LOGIN_LOCKOUT_MINUTES . ' دقيقة.'];
        }

        $stmt = $this->pdo->prepare("
            SELECT id, full_name, email, password, role, status, avatar
            FROM users
            WHERE email = :email1 OR phone = :email2
            LIMIT 1
        ");
        $stmt->execute([':email1' => $email, ':email2' => $email]);
        $user = $stmt->fetch();

        if (!$user || !verify_password($password, $user['password'])) {
            $this->logFailedAttempt($email);
            return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'];
        }

        if ($user['status'] === 'banned') {
            return ['success' => false, 'message' => 'هذا الحساب محظور. تواصل مع الإدارة.'];
        }
        if ($user['status'] === 'inactive') {
            return ['success' => false, 'message' => 'الحساب غير نشط. يرجى تفعيله عبر البريد الإلكتروني'];
        }

        // تجديد معرف الجلسة لمنع Session Fixation
        session_regenerate_id(true);

        // تسجيل الدخول
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_avatar'] = $user['avatar'];
        $_SESSION['logged_in'] = true;

        // تحديث آخر تسجيل دخول + إعادة ترقية الهاش إن لزم
        $stmt = $this->pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
        if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $stmt = $this->pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $stmt->execute([':p' => hash_password($password), ':id' => $user['id']]);
        }

        // تذكرني
        if ($remember) {
            $this->setRememberToken($user['id']);
        }

        log_activity($user['id'], 'login', 'تسجيل دخول ناجح');

        return ['success' => true, 'user' => $user];
    }

    /**
     * تسجيل الدخول عبر كوكي "تذكرني" (تُستدعى في أعلى الصفحات إن لم يكن مسجلاً)
     */
    public function loginFromRememberToken() {
        if (is_logged_in() || empty($_COOKIE['remember_token'])) {
            return false;
        }
        try {
            $hashed = hash('sha256', $_COOKIE['remember_token']);
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.full_name, u.email, u.role, u.avatar, u.status
                FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
                WHERE rt.token = :token AND rt.expires_at > NOW() LIMIT 1
            ");
            $stmt->execute([':token' => $hashed]);
            $user = $stmt->fetch();

            if ($user && $user['status'] === 'active') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_avatar'] = $user['avatar'];
                $_SESSION['logged_in'] = true;
                return true;
            }
            // رمز غير صالح - حذفه
            $this->pdo->prepare("DELETE FROM remember_tokens WHERE token = :token")->execute([':token' => $hashed]);
        } catch (Exception $e) {
            error_log("Remember token error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * تسجيل الخروج
     */
    public function logout() {
        if (is_logged_in()) {
            log_activity($_SESSION['user_id'], 'logout', 'تسجيل خروج');
            // حذف رمز التذكر من القاعدة
            try {
                if (!empty($_COOKIE['remember_token'])) {
                    $hashed = hash('sha256', $_COOKIE['remember_token']);
                    $this->pdo->prepare("DELETE FROM remember_tokens WHERE token = :token AND user_id = :uid")
                              ->execute([':token' => $hashed, ':uid' => $_SESSION['user_id']]);
                }
            } catch (Exception $e) { /* ignore */ }
        }

        $_SESSION = [];
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
        session_destroy();

        // حذف كوكيز التذكر
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        }

        return true;
    }

    /**
     * التحقق من توفر سيارة في تواريخ محددة
     */
    public function checkCarAvailability($car_id, $pickup_date, $return_date) {
        return check_car_availability($car_id, $pickup_date, $return_date);
    }

    /**
     * تغيير كلمة المرور
     */
    public function changePassword($user_id, $old_password, $new_password) {
        $stmt = $this->pdo->prepare("SELECT password FROM users WHERE id = :id");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user || !verify_password($old_password, $user['password'])) {
            return ['success' => false, 'message' => 'كلمة المرور الحالية غير صحيحة'];
        }

        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل'];
        }

        $hashed = hash_password($new_password);
        $stmt = $this->pdo->prepare("UPDATE users SET password = :pass, updated_at = NOW() WHERE id = :id");
        $result = $stmt->execute([':pass' => $hashed, ':id' => $user_id]);

        if ($result) {
            log_activity($user_id, 'change_password', 'تغيير كلمة المرور');
            return ['success' => true];
        }

        return ['success' => false, 'message' => 'حدث خطأ أثناء تغيير كلمة المرور'];
    }

    /**
     * إعادة تعيين كلمة المرور (نسيتها)
     */
    public function resetPassword($email) {
        $email = strtolower(trim($email));
        $stmt = $this->pdo->prepare("SELECT id, full_name FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        // رسالة موحدة دائماً لمنع كشف وجود البريد من عدمه
        $generic = ['success' => true, 'message' => 'إذا كان البريد مسجلاً لدينا فستصلك رسالة إعادة التعيين'];

        if (!$user) {
            return $generic;
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $stmt = $this->pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at)
            VALUES (:user_id, :token, :expires, NOW())
        ");
        $stmt->execute([':user_id' => $user['id'], ':token' => $token, ':expires' => $expires]);

        // إرسال بريد إعادة التعيين
        $reset_link = SITE_URL . "/reset-password.php?token=" . $token;
        $subject = "إعادة تعيين كلمة المرور - " . SITE_NAME;
        $body = "<div dir='rtl' style='font-family:Cairo,Tahoma'>
                 <h3>مرحباً {$user['full_name']},</h3>
                 <p>توصلنا بطلب لإعادة تعيين كلمة المرور الخاصة بك.</p>
                 <p>اضغط على الرابط التالي لإعادة تعيين كلمة المرور:</p>
                 <p><a href='{$reset_link}' style='background:#667eea;color:#fff;padding:12px 25px;border-radius:8px;text-decoration:none;'>إعادة تعيين كلمة المرور</a></p>
                 <p>أو انسخ الرابط: <br>{$reset_link}</p>
                 <p>⏰ ينتهي الرابط خلال ساعة واحدة.</p>
                 <p>إذا لم تطلب ذلك، تجاهل هذه الرسالة.</p>
                 </div>";

        send_email($email, $subject, $body);
        log_activity($user['id'], 'password_reset_requested', 'طلب إعادة تعيين كلمة المرور');

        return $generic;
    }

    /**
     * التحقق من رمز إعادة التعيين
     */
    public function validateResetToken($token) {
        if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $stmt = $this->pdo->prepare("
            SELECT pr.*, u.email, u.full_name FROM password_resets pr
            JOIN users u ON u.id = pr.user_id
            WHERE pr.token = :token AND pr.expires_at > NOW() AND pr.used = 0
            ORDER BY pr.id DESC LIMIT 1
        ");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch() ?: null;
    }

    /**
     * تنفيذ تغيير كلمة المرور عبر رمز إعادة التعيين
     */
    public function completePasswordReset($token, $new_password) {
        $reset = $this->validateResetToken($token);
        if (!$reset) {
            return ['success' => false, 'message' => 'الرابط غير صالح أو منتهي الصلاحية'];
        }
        if (strlen($new_password) < 8) {
            return ['success' => false, 'message' => 'كلمة المرور يجب أن تكون 8 أحرف على الأقل'];
        }

        $stmt = $this->pdo->prepare("UPDATE users SET password = :p, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':p' => hash_password($new_password), ':id' => $reset['user_id']]);

        $this->pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = :id")->execute([':id' => $reset['id']]);
        log_activity($reset['user_id'], 'password_reset', 'إعادة تعيين كلمة المرور بنجاح');

        return ['success' => true, 'message' => 'تم تغيير كلمة المرور بنجاح، يمكنك الآن تسجيل الدخول'];
    }

    /**
     * تفعيل الحساب عبر رمز التحقق
     */
    public function verifyEmail($token) {
        if (empty($token)) return ['success' => false, 'message' => 'رمز غير صالح'];
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email_verification_token = :token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        if (!$user) {
            return ['success' => false, 'message' => 'رمز التفعيل غير صالح'];
        }
        $stmt = $this->pdo->prepare("UPDATE users SET status = 'active', email_verified_at = NOW(), email_verification_token = NULL, updated_at = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);
        log_activity($user['id'], 'email_verified', 'تفعيل البريد الإلكتروني');
        return ['success' => true, 'message' => 'تم تفعيل حسابك بنجاح! يمكنك الآن تسجيل الدخول'];
    }

    // ============================================
    // دوال خاصة
    // ============================================
    private function sendVerificationEmail($email, $token, $name) {
        $verify_link = SITE_URL . "/verify-email.php?token=" . $token;
        $subject = "تفعيل حسابك في " . SITE_NAME;
        $body = "<div dir='rtl' style='font-family:Cairo,Tahoma'>
                 <h3>مرحباً $name,</h3>
                 <p>شكراً لتسجيلك في " . SITE_NAME . ".</p>
                 <p>لتفعيل حسابك اضغط على الزر التالي:</p>
                 <p><a href='{$verify_link}' style='background:#10b981;color:#fff;padding:12px 25px;border-radius:8px;text-decoration:none;'>تفعيل الحساب</a></p>
                 <p>أو انسخ الرابط: <br>{$verify_link}</p>
                 </div>";
        send_email($email, $subject, $body);
    }

    private function setRememberToken($user_id) {
        try {
            $token = bin2hex(random_bytes(32));
            $hashed = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

            $stmt = $this->pdo->prepare("
                INSERT INTO remember_tokens (user_id, token, expires_at, created_at)
                VALUES (:user_id, :token, :expires, NOW())
            ");
            $stmt->execute([':user_id' => $user_id, ':token' => $hashed, ':expires' => $expires]);

            setcookie('remember_token', $token, [
                'expires' => time() + (30 * 24 * 60 * 60),
                'path' => '/',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        } catch (Exception $e) {
            error_log("Remember token error: " . $e->getMessage());
        }
    }

    private function logFailedAttempt($email) {
        try {
            $ip = get_client_ip();
            $stmt = $this->pdo->prepare("
                INSERT INTO login_attempts (email, ip_address, attempt_time)
                VALUES (:email, :ip, NOW())
            ");
            $stmt->execute([':email' => $email, ':ip' => $ip]);
        } catch (Exception $e) {
            error_log("Login attempt log error: " . $e->getMessage());
        }
    }
}
