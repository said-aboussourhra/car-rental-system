<?php
/**
 * Premium Car Rental - Configuration File
 * النسخة المطورة الكاملة - FINAL COMPLETE VERSION
 */

// ============================================
// ENVIRONMENT
// ============================================
define('DEBUG_MODE', false); // true أثناء التطوير فقط - false في الإنتاج

date_default_timezone_set('Africa/Casablanca');
error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? 1 : 0);
ini_set('log_errors', 1);

// ============================================
// DATABASE CONFIGURATION
// تُقرأ من متغيرات البيئة عند توفرها (ضروري للنشر على Vercel وغيرها)
// يدعم أيضاً متغير DATABASE_URL بصيغة:  mysql://user:pass@host:port/dbname
// ============================================
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

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'car_rental_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_NAME', 'Premium Car Rental');
define('SITE_TAGLINE', 'خدمة تأجير السيارات الفاخرة في المغرب');
define('ADMIN_EMAIL', 'contact@carrental.ma');
define('SUPPORT_PHONE', '+212600000000');
define('CURRENCY', 'MAD');
define('CURRENCY_SYMBOL', 'DH');
define('TAX_RATE', 20);

// ============================================
// SEO / META (كانت مستعملة في الهيدر دون تعريف)
// ============================================
define('SITE_DESCRIPTION', 'أفضل موقع لتأجير السيارات الفاخرة والاقتصادية في المغرب. أسعار تنافسية، خدمة 24/7، حجز فوري عبر الإنترنت.');
define('SITE_KEYWORDS', 'تأجير سيارات المغرب, كراء السيارات, سيارة للكراء, الدار البيضاء, مراكش, كار رينتال, car rental morocco');
define('SITE_LOGO', 'assets/images/logo.svg');
define('SITE_FAVICON', 'assets/images/favicon.svg');

// ============================================
// PATHS & URLS (تُحسب تلقائياً)
// ============================================
define('BASE_PATH', dirname(__DIR__));

if (!defined('SITE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseDir = '';
    try {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        if ($docRoot && strpos(realpath(BASE_PATH) ?: '', $docRoot) === 0) {
            $baseDir = str_replace('\\', '/', substr(realpath(BASE_PATH), strlen($docRoot)));
        }
    } catch (Throwable $e) { /* ignore */ }
    define('SITE_URL', $scheme . '://' . $host . rtrim($baseDir, '/'));
}
define('ASSETS_URL', SITE_URL . '/assets');
define('UPLOADS_URL', SITE_URL . '/uploads');

// ============================================
// PAYMENT CONFIGURATION (Optional)
// ============================================
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY');
define('PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_CLIENT_ID');
define('PAYPAL_SECRET', 'YOUR_PAYPAL_SECRET');

// ============================================
// SECURITY SETTINGS
// ============================================
define('MAX_LOGIN_ATTEMPTS', 5);          // أقصى محاولات تسجيل دخول فاشلة
define('LOGIN_LOCKOUT_MINUTES', 15);      // مدة القفل بعد تجاوز المحاولات
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("<div style='font-family:Cairo,Tahoma;direction:rtl;text-align:center;padding:60px 20px;'>
        <h2>⚠️ تعذر الاتصال بقاعدة البيانات</h2>
        <p>تأكد من تشغيل MySQL وإنشاء قاعدة البيانات عبر <b>install.php</b></p>
        <a href='install.php' style='display:inline-block;padding:12px 30px;background:#667eea;color:#fff;border-radius:10px;text-decoration:none;'>فتح صفحة التثبيت</a>
        </div>");
}

// ============================================
// الجلسات: على المنصات السحابية (Vercel) تُحفظ في قاعدة البيانات
// لأن نظام الملفات مؤقت ولا يُشارك بين الخوادم
// ============================================
class DbSessionHandler extends SessionHandlerInterface {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    #[\ReturnTypeWillChange]
    public function open($path, $name) { return true; }

    #[\ReturnTypeWillChange]
    public function close() { return true; }

    #[\ReturnTypeWillChange]
    public function read($id) {
        try {
            $stmt = $this->pdo->prepare("SELECT data FROM sessions WHERE id = :id AND expires > UNIX_TIMESTAMP() LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            return $row ? (string)$row['data'] : '';
        } catch (Exception $e) {
            return '';
        }
    }

    #[\ReturnTypeWillChange]
    public function write($id, $data) {
        try {
            $ttl = (int)ini_get('session.gc_maxlifetime') ?: 1440;
            $stmt = $this->pdo->prepare("REPLACE INTO sessions (id, data, expires) VALUES (:id, :data, :exp)");
            return $stmt->execute([':id' => $id, ':data' => $data, ':exp' => time() + $ttl]);
        } catch (Exception $e) {
            return false;
        }
    }

    #[\ReturnTypeWillChange]
    public function destroy($id) {
        try {
            $this->pdo->prepare("DELETE FROM sessions WHERE id = :id")->execute([':id' => $id]);
        } catch (Exception $e) { /* ignore */ }
        return true;
    }

    #[\ReturnTypeWillChange]
    public function gc($max_lifetime) {
        try {
            $this->pdo->prepare("DELETE FROM sessions WHERE expires < UNIX_TIMESTAMP()")->execute();
            return 0;
        } catch (Exception $e) {
            return false;
        }
    }
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }

    $useDbSessions = getenv('DB_SESSIONS') === '1' || getenv('VERCEL') === '1';

    if ($useDbSessions) {
        try {
            session_set_save_handler(new DbSessionHandler($pdo), true);
        } catch (Throwable $e) {
            error_log("DB sessions unavailable: " . $e->getMessage());
        }
    } else {
        // التأكد من أن مجلد حفظ الجلسات موجود وقابل للكتابة
        $savePath = ini_get('session.save_path');
        if ($savePath === '' || $savePath === false || !is_dir($savePath) || !is_writable($savePath)) {
            $fallback = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php_sessions_' . substr(md5(BASE_PATH), 0, 8);
            if (!is_dir($fallback)) {
                @mkdir($fallback, 0700, true);
            }
            if (is_dir($fallback) && is_writable($fallback)) {
                ini_set('session.save_path', $fallback);
            }
        }
    }

    session_start();
}

// ============================================
// ALL CORE FUNCTIONS
// ============================================

/**
 * Clean and sanitize input data
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data ?? '');
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

/**
 * Escape for HTML output (اختصار سريع)
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    // Regenerate every 30 minutes
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * حقل CSRF جاهز للنماذج
 */
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * وسم meta الخاص بـ CSRF (لمواقف AJAX)
 */
function csrf_meta() {
    return '<meta name="csrf-token" content="' . generate_csrf_token() . '">';
}

/**
 * التحقق من توكن النموذج الحالي أو إيقاف التنفيذ
 */
function require_valid_csrf() {
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf_token($token)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(419);
            echo json_encode(['success' => false, 'message' => 'انتهت صلاحية الجلسة، أعد تحميل الصفحة وحاول مجدداً']);
            exit();
        }
        http_response_code(419);
        die('❌ انتهت صلاحية الجلسة (رمز الأمان غير صالح). <a href="javascript:history.back()">العودة</a>');
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) &&
           isset($_SESSION['logged_in']) &&
           $_SESSION['logged_in'] === true &&
           $_SESSION['user_id'] > 0;
}

/**
 * معرف المستخدم الحالي أو 0
 */
function current_user_id() {
    return is_logged_in() ? (int)$_SESSION['user_id'] : 0;
}

/**
 * Check if user is admin
 */
function is_admin() {
    if (!is_logged_in()) return false;
    return isset($_SESSION['user_role']) &&
           in_array($_SESSION['user_role'], ['admin', 'super_admin']);
}

/**
 * Redirect to URL and exit
 */
function redirect($url) {
    if (!headers_sent()) {
        header("Location: " . $url);
    } else {
        echo '<script>window.location.href="' . addslashes($url) . '";</script>';
    }
    exit();
}

/**
 * Format date
 */
function format_date($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format amount with currency
 */
function format_amount($amount) {
    $amount = floatval($amount);
    return number_format($amount, 2, ',', ' ') . ' ' . CURRENCY_SYMBOL;
}

/**
 * Set flash message
 */
function set_message($message, $type = 'info') {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'text' => $message,
        'type' => $type,
        'time' => time()
    ];
}

/**
 * Get and clear flash messages
 */
function get_messages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Generate unique booking number
 */
function generate_booking_number() {
    return 'BK-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 8));
}

/**
 * Generate unique invoice number
 */
function generate_invoice_number() {
    return 'INV-' . date('Y') . '-' . str_pad((string)mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Calculate days between two dates
 */
function calculate_days($start, $end) {
    try {
        $startDate = new DateTime($start);
        $endDate = new DateTime($end);
        $diff = $startDate->diff($endDate);
        return (int)$diff->days;
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Hash password using BCRYPT
 */
function hash_password($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 */
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Send email
 */
function send_email($to, $subject, $body) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <" . ADMIN_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . ADMIN_EMAIL . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    $sent = @mail($to, $subject, $body, $headers);
    if (!$sent) {
        error_log("Mail failed to [$to]: $subject");
    }
    return $sent;
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $description = '') {
    global $pdo;
    try {
        $ip = get_client_ip();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (:uid, :action, :desc, :ip, NOW())");
        $stmt->execute([':uid' => $user_id, ':action' => $action, ':desc' => $description, ':ip' => $ip]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * Upload file - نسخة آمنة ومصححة (كانت تحتوي خطأ _DIR_)
 */
function upload_file($file, $subdir = 'cars') {
    $uploadDir = BASE_PATH . '/uploads/' . trim($subdir, '/') . '/';

    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'message' => 'تعذر إنشاء مجلد الرفع'];
        }
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'message' => 'ملف غير صالح'];
    }

    // فحص نوع الملف الحقيقي وليس فقط الامتداد
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $realType = $finfo->file($file['tmp_name']);

    if (!in_array($realType, $allowedTypes)) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح به (صور فقط: JPG, PNG, WEBP, GIF)'];
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 5 ميجابايت)'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
        $extension = str_replace('jpeg', 'jpg', str_replace('image/', '', $realType));
    }
    $filename = preg_replace('/[^a-z0-9_\-\.]/i', '', uniqid() . '_' . time() . '.' . $extension);
    $filepath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => trim($subdir, '/') . '/' . $filename, 'filename' => $filename];
    }

    return ['success' => false, 'message' => 'فشل في حفظ الملف'];
}

/**
 * Get client IP
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}

/**
 * Check car availability for given dates
 */
function check_car_availability($car_id, $pickup, $return) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
            WHERE car_id = :cid
            AND booking_status NOT IN ('cancelled', 'completed')
            AND payment_status != 'refunded'
            AND (
                (:pickup BETWEEN pickup_date AND return_date)
                OR (:return BETWEEN pickup_date AND return_date)
                OR (pickup_date BETWEEN :pickup2 AND :return2)
            )
        ");
        $stmt->execute([
            ':cid' => $car_id,
            ':pickup' => $pickup,
            ':return' => $return,
            ':pickup2' => $pickup,
            ':return2' => $return
        ]);
        return $stmt->fetchColumn() == 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * إعدادات الموقع المخزنة في جدول settings
 */
function get_setting($key, $default = '') {
    global $pdo;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $rows = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            foreach ($rows as $r) $cache[$r['setting_key']] = $r['setting_value'];
        } catch (Exception $e) { /* الجدول قد لا يكون موجوداً بعد */ }
    }
    return $cache[$key] ?? $default;
}

function save_setting($key, $value) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)
                               ON DUPLICATE KEY UPDATE setting_value = :v");
        return $stmt->execute([':k' => $key, ':v' => $value]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * إجابة JSON موحدة لكل واجهات الـ API
 */
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * توليد كلمة مرور عشوائية قوية
 */
function generate_random_password($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#$%';
    $pass = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}
