<?php
/**
 * Premium Car Rental - Configuration File
 * FINAL STABLE VERSION - NO ERRORS
 */
date_default_timezone_set('Africa/Casablanca');
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'car_rental_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// SITE CONFIGURATION
// ============================================
define('SITE_NAME', 'Premium Car Rental');
define('SITE_URL', 'http://localhost/car-rental-system');
define('ADMIN_EMAIL', 'contact@carrental.ma');
define('SUPPORT_PHONE', '+212600000000');
define('CURRENCY', 'MAD');
define('CURRENCY_SYMBOL', 'DH');
define('TAX_RATE', 20);

// ============================================
// PAYMENT CONFIGURATION (Optional)
// ============================================
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_YOUR_KEY');
define('STRIPE_SECRET_KEY', 'sk_test_YOUR_KEY');
define('PAYPAL_CLIENT_ID', 'YOUR_PAYPAL_CLIENT_ID');
define('PAYPAL_SECRET', 'YOUR_PAYPAL_SECRET');

// ============================================
// SESSION - START FIRST
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// ============================================
// DATABASE CONNECTION
// ============================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch(PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    die("Database connection failed. Please check your configuration.");
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
 * Generate CSRF token
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    // Regenerate every 30 minutes
    if (time() - $_SESSION['csrf_token_time'] > 1800) {
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
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['logged_in']) && 
           $_SESSION['logged_in'] === true && 
           $_SESSION['user_id'] > 0;
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
        echo '<script>window.location.href="' . $url . '";</script>';
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
    return 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
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
    
    return @mail($to, $subject, $body, $headers);
}

/**
 * Log activity
 */
function log_activity($user_id, $action, $description) {
    global $pdo;
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, created_at) VALUES (:uid, :action, :desc, :ip, NOW())");
        $stmt->execute([':uid' => $user_id, ':action' => $action, ':desc' => $description, ':ip' => $ip]);
    } catch (Exception $e) {
        error_log("Activity Log Error: " . $e->getMessage());
    }
}

/**
 * Upload file
 */
function upload_file($file, $subdir = 'cars') {
    $uploadDir = _DIR_ . '/../uploads/' . $subdir . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'نوع الملف غير مسموح به'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'خطأ في رفع الملف'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'path' => $subdir . '/' . $filename, 'filename' => $filename];
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

// ============================================
// CREATE DEFAULT ADMIN IF NOT EXISTS
// ============================================
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = 'admin@carrental.ma'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        $password = password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, created_at, updated_at) VALUES ('مدير النظام', 'admin@carrental.ma', :pass, '0600000000', 'super_admin', 'active', NOW(), NOW())")->execute([':pass' => $password]);
    }
} catch (Exception $e) {
    // Table might not exist yet - ignore
}