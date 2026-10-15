<?php
/**
 * ============================================================================
 * فحص صحة النشر — Deployment health check
 * ============================================================================
 * افتح هذا الملف في المتصفح للتحقق من أن الاستضافة تشغّل PHP فعلاً:
 *
 *   https://your-site.com/ping.php
 *
 * النتيجة الصحيحة: JSON فيه "php_running": true
 * إذا ظهرت أكواد PHP كنص عادي في الصفحة، فالاستضافة تخدم الملفات كملفات
 * ثابتة ولا تشغّل PHP (راجع قسم النشر على Vercel في README.md).
 *
 * لا يعرض هذا الملف أي كلمة مرور أو بيانات اتصال — فقط نعم/لا.
 * ليس جزءاً من عمل الموقع، ويمكنك حذفه بعد التحقق.
 * ============================================================================
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

$result = [
    'php_running' => true,
    'php_version' => PHP_VERSION,
    'sapi'        => PHP_SAPI,
    'server_time' => date('c'),
    'extensions'  => [
        'pdo'       => extension_loaded('PDO'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mbstring'  => extension_loaded('mbstring'),
        'fileinfo'  => extension_loaded('fileinfo'),
        'openssl'   => extension_loaded('openssl'),
    ],
    'config_source' => 'default',
    'platform'      => 'unknown',
    'db'            => 'not_checked',
];

/* إعدادات قاعدة البيانات (بدون كشف أي بيانات حساسة) */
$settingsFile = __DIR__ . '/includes/db-settings.php';

if (is_file($settingsFile)) {

    require_once $settingsFile;

    $settings = db_settings_load();

    $result['config_source'] = $settings['source'];
    $result['db_target'] = $settings['host'] . ':' . $settings['port'];
    $result['db_ssl'] = (bool)$settings['ssl'];

    $platform = db_platform_info();
    $result['platform'] = $platform['serverless'] ? 'serverless' : 'php-host';

    try {

        $pdo = new PDO(
            db_dsn($settings, true),
            $settings['user'],
            $settings['pass'],
            db_pdo_options($settings, 4)
        );

        $tables = db_existing_tables($pdo);

        $result['db'] = 'connected';
        $result['db_tables'] = count($tables);
        $result['installed'] = in_array('users', $tables, true)
            && in_array('cars', $tables, true)
            && in_array('bookings', $tables, true);

    } catch (PDOException $e) {

        $diagnosis = db_error_diagnosis($e->getMessage(), $settings);

        $result['db'] = 'error';
        $result['db_error_code'] = $diagnosis['code'];
        $result['db_error_title'] = $diagnosis['title'];

    } catch (Throwable $e) {

        $result['db'] = 'error';
        $result['db_error_title'] = 'خطأ غير متوقع أثناء الاتصال بقاعدة البيانات';
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
