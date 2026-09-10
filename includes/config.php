<?php
/**
 * ============================================================================
 * Premium Car Rental - Configuration File
 * FINAL COMPLETE VERSION
 * ============================================================================
 * يدعم:
 * - MySQL / TiDB
 * - DATABASE_URL
 * - DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS
 * - SSL/TLS لقاعدة TiDB
 * - Vercel
 * - Database Sessions
 * - CSRF
 * - Security helpers
 * - Uploads
 * - SEO
 * ============================================================================
 */

// ============================================================================
// ENVIRONMENT
// ============================================================================

define('DEBUG_MODE', false);

date_default_timezone_set('Africa/Casablanca');

error_reporting(E_ALL);
ini_set('display_errors', DEBUG_MODE ? '1' : '0');
ini_set('log_errors', '1');


// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
// الأولوية:
// 1. DATABASE_URL
// 2. DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS
// ============================================================================

$_dbUrl = getenv('DATABASE_URL');

if ($_dbUrl) {
    $_dbParts = parse_url($_dbUrl);

    if ($_dbParts && isset($_dbParts['host'])) {

        // Host
        putenv('DB_HOST=' . $_dbParts['host']);

        // Port
        if (isset($_dbParts['port'])) {
            putenv('DB_PORT=' . $_dbParts['port']);
        }

        // Database
        if (isset($_dbParts['path'])) {
            putenv('DB_NAME=' . ltrim($_dbParts['path'], '/'));
        }

        // Username
        if (isset($_dbParts['user'])) {
            putenv('DB_USER=' . urldecode($_dbParts['user']));
        }

        // Password
        if (isset($_dbParts['pass'])) {
            putenv('DB_PASS=' . urldecode($_dbParts['pass']));
        }
    }
}


// ============================================================================
// DATABASE CONSTANTS
// ============================================================================

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');

define(
    'DB_PORT',
    getenv('DB_PORT') ?: '3306'
);

define(
    'DB_NAME',
    getenv('DB_NAME') ?: 'car_rental_db'
);

define(
    'DB_USER',
    getenv('DB_USER') ?: 'root'
);

define(
    'DB_PASS',
    getenv('DB_PASS') !== false
        ? getenv('DB_PASS')
        : ''
);

define('DB_CHARSET', 'utf8mb4');


// ============================================================================
// SITE CONFIGURATION
// ============================================================================

define(
    'SITE_NAME',
    'Premium Car Rental'
);

define(
    'SITE_TAGLINE',
    'خدمة تأجير السيارات الفاخرة في المغرب'
);

define(
    'ADMIN_EMAIL',
    'contact@carrental.ma'
);

define(
    'SUPPORT_PHONE',
    '+212600000000'
);

define(
    'CURRENCY',
    'MAD'
);

define(
    'CURRENCY_SYMBOL',
    'DH'
);

define(
    'TAX_RATE',
    20
);


// ============================================================================
// SEO / META
// ============================================================================

define(
    'SITE_DESCRIPTION',
    'أفضل موقع لتأجير السيارات الفاخرة والاقتصادية في المغرب. أسعار تنافسية، خدمة 24/7، حجز فوري عبر الإنترنت.'
);

define(
    'SITE_KEYWORDS',
    'تأجير سيارات المغرب, كراء السيارات, سيارة للكراء, الدار البيضاء, مراكش, كار رينتال, car rental morocco'
);

define(
    'SITE_LOGO',
    'assets/images/logo.svg'
);

define(
    'SITE_FAVICON',
    'assets/images/favicon.svg'
);


// ============================================================================
// PATHS & URLS
// ============================================================================

define(
    'BASE_PATH',
    dirname(__DIR__)
);

if (!defined('SITE_URL')) {

    // HTTPS detection
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    $scheme = $isHttps ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $baseDir = '';

    try {

        $basePathReal = realpath(BASE_PATH);

        $docRoot = realpath(
            $_SERVER['DOCUMENT_ROOT'] ?? ''
        );

        if (
            $basePathReal &&
            $docRoot &&
            strpos($basePathReal, $docRoot) === 0
        ) {
            $baseDir = str_replace(
                '\\',
                '/',
                substr(
                    $basePathReal,
                    strlen($docRoot)
                )
            );
        }

    } catch (Throwable $e) {

        // Ignore path detection errors
    }

    define(
        'SITE_URL',
        $scheme . '://' . $host . rtrim($baseDir, '/')
    );
}

define(
    'ASSETS_URL',
    SITE_URL . '/assets'
);

define(
    'UPLOADS_URL',
    SITE_URL . '/uploads'
);


// ============================================================================
// PAYMENT CONFIGURATION
// ============================================================================
// ضع المفاتيح الحقيقية في Environment Variables عند تفعيل الدفع.
// ============================================================================

define(
    'STRIPE_PUBLISHABLE_KEY',
    getenv('STRIPE_PUBLISHABLE_KEY') ?: 'pk_test_YOUR_KEY'
);

define(
    'STRIPE_SECRET_KEY',
    getenv('STRIPE_SECRET_KEY') ?: 'sk_test_YOUR_KEY'
);

define(
    'PAYPAL_CLIENT_ID',
    getenv('PAYPAL_CLIENT_ID') ?: 'YOUR_PAYPAL_CLIENT_ID'
);

define(
    'PAYPAL_SECRET',
    getenv('PAYPAL_SECRET') ?: 'YOUR_PAYPAL_SECRET'
);


// ============================================================================
// SECURITY SETTINGS
// ============================================================================

define(
    'MAX_LOGIN_ATTEMPTS',
    5
);

define(
    'LOGIN_LOCKOUT_MINUTES',
    15
);

define(
    'MAX_UPLOAD_SIZE',
    5 * 1024 * 1024
);


// ============================================================================
// DATABASE CONNECTION
// ============================================================================

try {

    // ------------------------------------------------------------------------
    // PDO DSN
    // ------------------------------------------------------------------------

    $dsn =
        'mysql:host=' . DB_HOST .
        ';port=' . DB_PORT .
        ';dbname=' . DB_NAME .
        ';charset=' . DB_CHARSET;


    // ------------------------------------------------------------------------
    // PDO OPTIONS
    // ------------------------------------------------------------------------

    $options = [

        PDO::ATTR_ERRMODE =>
            PDO::ERRMODE_EXCEPTION,

        PDO::ATTR_DEFAULT_FETCH_MODE =>
            PDO::FETCH_ASSOC,

        PDO::ATTR_EMULATE_PREPARES =>
            false,

        PDO::ATTR_STRINGIFY_FETCHES =>
            false,
    ];


    // ------------------------------------------------------------------------
    // SSL / TLS FOR TiDB
    // ------------------------------------------------------------------------
    // عند وضع DB_SSL=1 في Vercel سيتم تفعيل SSL.
    //
    // لا نضع شهادة ثابتة داخل المشروع.
    // نبحث عن CA bundle الموجودة في بيئة Linux.
    // ------------------------------------------------------------------------

    if (getenv('DB_SSL') === '1') {

        // Disable certificate verification only when explicitly requested.
        // TiDB Cloud commonly requires TLS.
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
            ] = false;
        }


        // Search common CA bundle locations.

        $caPaths = [

            '/etc/ssl/certs/ca-certificates.crt',

            '/etc/ssl/cert.pem',

            '/etc/pki/tls/certs/ca-bundle.crt',

            '/etc/ssl/ca-bundle.pem',

        ];


        foreach ($caPaths as $caPath) {

            if (file_exists($caPath)) {

                if (defined('PDO::MYSQL_ATTR_SSL_CA')) {

                    $options[
                        PDO::MYSQL_ATTR_SSL_CA
                    ] = $caPath;
                }

                break;
            }
        }
    }


    // ------------------------------------------------------------------------
    // CREATE PDO CONNECTION
    // ------------------------------------------------------------------------

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $options
    );


} catch (PDOException $e) {

    // Log detailed error server-side.
    error_log(
        'Database Error: ' . $e->getMessage()
    );


    // Never expose database credentials/details to visitors.

    $message =
        DEBUG_MODE
            ? e($e->getMessage())
            : 'تأكد من إعداد متغيرات قاعدة البيانات واتصال TiDB.';


    http_response_code(500);


    die(
        '<div style="
            font-family:Cairo,Tahoma,Arial,sans-serif;
            direction:rtl;
            text-align:center;
            padding:60px 20px;
            background:#f8fafc;
            min-height:100vh;
        ">
            <div style="
                max-width:650px;
                margin:auto;
                background:#fff;
                padding:40px;
                border-radius:20px;
                box-shadow:0 10px 40px rgba(0,0,0,.08);
            ">
                <h2 style="margin-bottom:15px;">
                    ⚠️ تعذر الاتصال بقاعدة البيانات
                </h2>

                <p style="color:#64748b;">
                    ' . $message . '
                </p>

                <a
                    href="install.php"
                    style="
                        display:inline-block;
                        margin-top:20px;
                        padding:12px 30px;
                        background:#667eea;
                        color:#fff;
                        border-radius:10px;
                        text-decoration:none;
                    "
                >
                    فتح صفحة التثبيت
                </a>
            </div>
        </div>'
    );
}


// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {

    // Security
    ini_set(
        'session.cookie_httponly',
        '1'
    );

    ini_set(
        'session.use_only_cookies',
        '1'
    );

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.cookie_samesite',
        'Lax'
    );


    // HTTPS cookie
    $isHttps =
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');


    if ($isHttps) {

        ini_set(
            'session.cookie_secure',
            '1'
        );
    }


    // ------------------------------------------------------------------------
    // DATABASE SESSIONS
    // ------------------------------------------------------------------------
    // Vercel filesystem is ephemeral.
    // Therefore DB sessions are recommended.
    // ------------------------------------------------------------------------

    $useDbSessions =
        (
            getenv('DB_SESSIONS') === '1'
            ||
            getenv('VERCEL') === '1'
        )
        &&
        interface_exists('SessionHandlerInterface');


    if ($useDbSessions) {

        try {

            $sessionHandlerFile =
                __DIR__ . '/db-session-handler.php';


            if (file_exists($sessionHandlerFile)) {

                require_once $sessionHandlerFile;


                if (class_exists('DbSessionHandler')) {

                    $handler =
                        new DbSessionHandler($pdo);


                    session_set_save_handler(
                        $handler,
                        true
                    );
                }
            }

        } catch (Throwable $e) {

            error_log(
                'DB sessions unavailable: ' .
                $e->getMessage()
            );
        }
    }


    // ------------------------------------------------------------------------
    // LOCAL SESSION FALLBACK
    // ------------------------------------------------------------------------

    if (!$useDbSessions) {

        $savePath =
            ini_get('session.save_path');


        if (
            $savePath === ''
            ||
            $savePath === false
            ||
            !is_dir($savePath)
            ||
            !is_writable($savePath)
        ) {

            $fallback =
                sys_get_temp_dir()
                .
                DIRECTORY_SEPARATOR
                .
                'php_sessions_'
                .
                substr(
                    md5(BASE_PATH),
                    0,
                    8
                );


            if (!is_dir($fallback)) {

                @mkdir(
                    $fallback,
                    0700,
                    true
                );
            }


            if (
                is_dir($fallback)
                &&
                is_writable($fallback)
            ) {

                ini_set(
                    'session.save_path',
                    $fallback
                );
            }
        }
    }


    // ------------------------------------------------------------------------
    // START SESSION
    // ------------------------------------------------------------------------

    session_start();
}


// ============================================================================
// CORE FUNCTIONS
// ============================================================================


/**
 * Clean and sanitize input data.
 */
function clean_input($data)
{
    if (is_array($data)) {

        return array_map(
            'clean_input',
            $data
        );
    }


    $data = trim(
        (string)($data ?? '')
    );

    $data = stripslashes($data);

    $data = htmlspecialchars(
        $data,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );


    return $data;
}


/**
 * Escape HTML output.
 */
function e($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}


/**
 * Generate CSRF token.
 */
function generate_csrf_token()
{
    if (
        !isset($_SESSION['csrf_token'])
        ||
        !isset($_SESSION['csrf_token_time'])
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );

        $_SESSION['csrf_token_time'] =
            time();
    }


    // Regenerate every 30 minutes.

    if (
        time()
        -
        ($_SESSION['csrf_token_time'] ?? 0)
        >
        1800
    ) {

        $_SESSION['csrf_token'] =
            bin2hex(
                random_bytes(32)
            );

        $_SESSION['csrf_token_time'] =
            time();
    }


    return $_SESSION['csrf_token'];
}


/**
 * Verify CSRF token.
 */
function verify_csrf_token($token)
{
    if (
        !isset($_SESSION['csrf_token'])
        ||
        empty($token)
    ) {

        return false;
    }


    return hash_equals(
        $_SESSION['csrf_token'],
        (string)$token
    );
}


/**
 * CSRF hidden field.
 */
function csrf_field()
{
    return
        '<input type="hidden" name="csrf_token" value="'
        .
        e(generate_csrf_token())
        .
        '">';
}


/**
 * CSRF meta tag.
 */
function csrf_meta()
{
    return
        '<meta name="csrf-token" content="'
        .
        e(generate_csrf_token())
        .
        '">';
}


/**
 * Require valid CSRF token.
 */
function require_valid_csrf()
{
    $token =
        $_POST['csrf_token']
        ??
        ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');


    if (!verify_csrf_token($token)) {

        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            &&
            $_SERVER['HTTP_X_REQUESTED_WITH']
            ===
            'XMLHttpRequest'
        ) {

            header(
                'Content-Type: application/json; charset=utf-8'
            );

            http_response_code(419);

            echo json_encode(
                [
                    'success' => false,
                    'message' =>
                        'انتهت صلاحية الجلسة، أعد تحميل الصفحة وحاول مجدداً'
                ],
                JSON_UNESCAPED_UNICODE
            );

            exit();
        }


        http_response_code(419);


        die(
            '❌ انتهت صلاحية الجلسة (رمز الأمان غير صالح). '
            .
            '<a href="javascript:history.back()">العودة</a>'
        );
    }
}


/**
 * Check if user is logged in.
 */
function is_logged_in()
{
    return
        isset($_SESSION['user_id'])
        &&
        isset($_SESSION['logged_in'])
        &&
        $_SESSION['logged_in'] === true
        &&
        (int)$_SESSION['user_id'] > 0;
}


/**
 * Current user ID.
 */
function current_user_id()
{
    return is_logged_in()
        ? (int)$_SESSION['user_id']
        : 0;
}


/**
 * Check if user is admin.
 */
function is_admin()
{
    if (!is_logged_in()) {

        return false;
    }


    return
        isset($_SESSION['user_role'])
        &&
        in_array(
            $_SESSION['user_role'],
            ['admin', 'super_admin'],
            true
        );
}


/**
 * Redirect.
 */
function redirect($url)
{
    if (!headers_sent()) {

        header(
            'Location: ' . $url
        );

    } else {

        echo
            '<script>'
            .
            'window.location.href='
            .
            json_encode($url)
            .
            ';</script>';
    }


    exit();
}


/**
 * Format date.
 */
function format_date(
    $date,
    $format = 'd/m/Y'
) {
    if (empty($date)) {

        return '';
    }


    try {

        $dt =
            new DateTime($date);


        return $dt->format($format);

    } catch (Exception $e) {

        return $date;
    }
}


/**
 * Format amount.
 */
function format_amount($amount)
{
    $amount =
        (float)$amount;


    return
        number_format(
            $amount,
            2,
            ',',
            ' '
        )
        .
        ' '
        .
        CURRENCY_SYMBOL;
}


/**
 * Set flash message.
 */
function set_message(
    $message,
    $type = 'info'
) {
    if (
        !isset(
            $_SESSION['flash_messages']
        )
    ) {

        $_SESSION['flash_messages'] = [];
    }


    $_SESSION['flash_messages'][] = [

        'text' =>
            $message,

        'type' =>
            $type,

        'time' =>
            time()
    ];
}


/**
 * Get and clear flash messages.
 */
function get_messages()
{
    $messages =
        $_SESSION['flash_messages']
        ??
        [];


    unset(
        $_SESSION['flash_messages']
    );


    return $messages;
}


/**
 * Generate unique booking number.
 */
function generate_booking_number()
{
    return
        'BK-'
        .
        date('Ymd')
        .
        '-'
        .
        strtoupper(
            bin2hex(
                random_bytes(4)
            )
        );
}


/**
 * Generate unique invoice number.
 */
function generate_invoice_number()
{
    return
        'INV-'
        .
        date('Y')
        .
        '-'
        .
        str_pad(
            (string)mt_rand(1, 99999),
            5,
            '0',
            STR_PAD_LEFT
        );
}


/**
 * Calculate days between dates.
 */
function calculate_days(
    $start,
    $end
) {
    try {

        $startDate =
            new DateTime($start);

        $endDate =
            new DateTime($end);


        $diff =
            $startDate->diff(
                $endDate
            );


        return
            (int)$diff->days;

    } catch (Exception $e) {

        return 0;
    }
}


/**
 * Hash password using BCRYPT.
 */
function hash_password($password)
{
    return password_hash(
        $password,
        PASSWORD_BCRYPT,
        [
            'cost' => 12
        ]
    );
}


/**
 * Verify password.
 */
function verify_password(
    $password,
    $hash
) {
    return password_verify(
        $password,
        $hash
    );
}


/**
 * Send email.
 */
function send_email(
    $to,
    $subject,
    $body
) {
    $headers =
        "MIME-Version: 1.0\r\n";

    $headers .=
        "Content-Type: text/html; charset=UTF-8\r\n";

    $headers .=
        "From: "
        .
        SITE_NAME
        .
        " <"
        .
        ADMIN_EMAIL
        .
        ">\r\n";

    $headers .=
        "Reply-To: "
        .
        ADMIN_EMAIL
        .
        "\r\n";

    $headers .=
        "X-Mailer: PHP/"
        .
        phpversion();


    $sent =
        @mail(
            $to,
            $subject,
            $body,
            $headers
        );


    if (!$sent) {

        error_log(
            "Mail failed to [$to]: $subject"
        );
    }


    return $sent;
}


/**
 * Get client IP.
 */
function get_client_ip()
{
    if (
        !empty(
            $_SERVER['HTTP_CLIENT_IP']
        )
    ) {

        return
            $_SERVER['HTTP_CLIENT_IP'];
    }


    if (
        !empty(
            $_SERVER['HTTP_X_FORWARDED_FOR']
        )
    ) {

        $ips =
            explode(
                ',',
                $_SERVER['HTTP_X_FORWARDED_FOR']
            );


        return trim($ips[0]);
    }


    return
        $_SERVER['REMOTE_ADDR']
        ??
        '127.0.0.1';
}


/**
 * Log activity.
 */
function log_activity(
    $user_id,
    $action,
    $description = ''
) {
    global $pdo;


    try {

        $ip =
            get_client_ip();


        $stmt =
            $pdo->prepare(
                "
                INSERT INTO activity_logs
                (
                    user_id,
                    action,
                    description,
                    ip_address,
                    created_at
                )
                VALUES
                (
                    :uid,
                    :action,
                    :desc,
                    :ip,
                    NOW()
                )
                "
            );


        $stmt->execute(
            [
                ':uid' =>
                    $user_id,

                ':action' =>
                    $action,

                ':desc' =>
                    $description,

                ':ip' =>
                    $ip
            ]
        );

    } catch (Exception $e) {

        error_log(
            'Activity Log Error: '
            .
            $e->getMessage()
        );
    }
}


/**
 * Upload file.
 */
function upload_file(
    $file,
    $subdir = 'cars'
) {
    $subdir =
        trim(
            $subdir,
            '/'
        );


    $uploadDir =
        BASE_PATH
        .
        '/uploads/'
        .
        $subdir
        .
        '/';


    if (!is_dir($uploadDir)) {

        if (
            !mkdir(
                $uploadDir,
                0755,
                true
            )
        ) {

            return [
                'success' => false,
                'message' =>
                    'تعذر إنشاء مجلد الرفع'
            ];
        }
    }


    if (
        !isset($file['tmp_name'])
        ||
        !is_uploaded_file(
            $file['tmp_name']
        )
    ) {

        return [
            'success' => false,
            'message' =>
                'ملف غير صالح'
        ];
    }


    // Check upload error first.

    if (
        ($file['error'] ?? UPLOAD_ERR_NO_FILE)
        !==
        UPLOAD_ERR_OK
    ) {

        return [
            'success' => false,
            'message' =>
                'خطأ في رفع الملف'
        ];
    }


    // File size.

    if (
        ($file['size'] ?? 0)
        >
        MAX_UPLOAD_SIZE
    ) {

        return [
            'success' => false,
            'message' =>
                'حجم الملف كبير جداً (الحد الأقصى 5 ميجابايت)'
        ];
    }


    // Real MIME type.

    $allowedTypes = [

        'image/jpeg',

        'image/jpg',

        'image/png',

        'image/webp',

        'image/gif'
    ];


    $finfo =
        new finfo(
            FILEINFO_MIME_TYPE
        );


    $realType =
        $finfo->file(
            $file['tmp_name']
        );


    if (
        !in_array(
            $realType,
            $allowedTypes,
            true
        )
    ) {

        return [
            'success' => false,
            'message' =>
                'نوع الملف غير مسموح به (JPG, PNG, WEBP, GIF فقط)'
        ];
    }


    // Map MIME to safe extension.

    $extensions = [

        'image/jpeg' =>
            'jpg',

        'image/jpg' =>
            'jpg',

        'image/png' =>
            'png',

        'image/webp' =>
            'webp',

        'image/gif' =>
            'gif'
    ];


    $extension =
        $extensions[$realType]
        ??
        'jpg';


    // Generate secure unique filename.

    try {

        $random =
            bin2hex(
                random_bytes(16)
            );

    } catch (Throwable $e) {

        $random =
            uniqid(
                '',
                true
            );
    }


    $filename =
        $random
        .
        '_'
        .
        time()
        .
        '.'
        .
        $extension;


    $filepath =
        $uploadDir
        .
        $filename;


    if (
        move_uploaded_file(
            $file['tmp_name'],
            $filepath
        )
    ) {

        return [

            'success' =>
                true,

            'path' =>
                $subdir
                .
                '/'
                .
                $filename,

            'filename' =>
                $filename
        ];
    }


    return [
        'success' => false,
        'message' =>
            'فشل في حفظ الملف'
    ];
}


/**
 * Check car availability.
 */
function check_car_availability(
    $car_id,
    $pickup,
    $return
) {
    global $pdo;


    try {

        $stmt =
            $pdo->prepare(
                "
                SELECT COUNT(*)
                FROM bookings
                WHERE car_id = :cid
                AND booking_status
                    NOT IN ('cancelled', 'completed')
                AND payment_status
                    != 'refunded'
                AND (
                    (:pickup BETWEEN pickup_date AND return_date)
                    OR
                    (:return BETWEEN pickup_date AND return_date)
                    OR
                    (pickup_date BETWEEN :pickup2 AND :return2)
                )
                "
            );


        $stmt->execute(
            [
                ':cid' =>
                    $car_id,

                ':pickup' =>
                    $pickup,

                ':return' =>
                    $return,

                ':pickup2' =>
                    $pickup,

                ':return2' =>
                    $return
            ]
        );


        return
            (int)$stmt->fetchColumn()
            ===
            0;

    } catch (Exception $e) {

        error_log(
            'Car Availability Error: '
            .
            $e->getMessage()
        );


        return false;
    }
}


/**
 * Get site setting.
 */
function get_setting(
    $key,
    $default = ''
) {
    global $pdo;


    static $cache = null;


    if ($cache === null) {

        $cache = [];


        try {

            $rows =
                $pdo
                    ->query(
                        "
                        SELECT
                            setting_key,
                            setting_value
                        FROM settings
                        "
                    )
                    ->fetchAll();


            foreach ($rows as $row) {

                $cache[
                    $row['setting_key']
                ] =
                    $row['setting_value'];
            }

        } catch (Exception $e) {

            // Settings table may not exist during installation.
        }
    }


    return
        $cache[$key]
        ??
        $default;
}


/**
 * Save site setting.
 */
function save_setting(
    $key,
    $value
) {
    global $pdo;


    try {

        $stmt =
            $pdo->prepare(
                "
                INSERT INTO settings
                (
                    setting_key,
                    setting_value
                )
                VALUES
                (
                    :k,
                    :v
                )
                ON DUPLICATE KEY UPDATE
                    setting_value = :v
                "
            );


        return $stmt->execute(
            [
                ':k' =>
                    $key,

                ':v' =>
                    $value
            ]
        );

    } catch (Exception $e) {

        error_log(
            'Save Setting Error: '
            .
            $e->getMessage()
        );


        return false;
    }
}


/**
 * Standard JSON response.
 */
function json_response(
    $data,
    $code = 200
) {
    http_response_code($code);


    header(
        'Content-Type: application/json; charset=utf-8'
    );


    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        |
        JSON_UNESCAPED_SLASHES
    );


    exit();
}


/**
 * Generate strong random password.
 */
function generate_random_password(
    $length = 12
) {
    $chars =
        'ABCDEFGHJKLMNPQRSTUVWXYZ'
        .
        'abcdefghijkmnpqrstuvwxyz'
        .
        '23456789'
        .
        '!@#$%';


    $pass = '';


    for (
        $i = 0;
        $i < $length;
        $i++
    ) {

        $pass .=
            $chars[
                random_int(
                    0,
                    strlen($chars) - 1
                )
            ];
    }


    return $pass;
}
