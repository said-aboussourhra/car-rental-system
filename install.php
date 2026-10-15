<?php
/**
 * ============================================================================
 * Premium Car Rental — معالج التثبيت (Setup Wizard)
 * ============================================================================
 *
 * الوظائف:
 *  1. فحص بيئة التشغيل (PHP، الإضافات، المنصة، الجلسات)
 *  2. تحديد إعدادات قاعدة البيانات من: متغيرات البيئة / DATABASE_URL /
 *     ملف الإعدادات المحلي / القيم الافتراضية
 *  3. تشخيص دقيق لأخطاء الاتصال (Connection refused, DNS, TLS, 1045, 1049 …)
 *  4. إمكانية إدخال الإعدادات وحفظها في config.local.php
 *  5. إنشاء قاعدة البيانات + تنفيذ database.sql (بدون حذف بيانات نظام مثبَّت)
 *  6. إنشاء حساب المدير + مجلدات النظام + فحص جدول السيارات
 *
 * ⚠️ احذف هذا الملف بعد انتهاء التثبيت في الإنتاج.
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

date_default_timezone_set('Africa/Casablanca');

require_once __DIR__ . '/includes/db-settings.php';

/* ============================================================================
 * أدوات مساعدة للعرض
 * ========================================================================== */

function wiz_e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * إضافة خطوة إلى تقرير التثبيت.
 *
 * @param array  $steps
 * @param bool   $ok
 * @param string $label
 * @param string $detail
 * @param string $level  error | warning | info
 */
function wiz_step(&$steps, $ok, $label, $detail = '', $level = 'error')
{
    $steps[] = [
        'ok'     => (bool)$ok,
        'label'  => $label,
        'detail' => $detail,
        'level'  => $ok ? 'ok' : $level,
    ];
}

/**
 * أسطر متغيرات البيئة المطلوبة (جاهزة للنسخ).
 *
 * @param  array $settings
 * @param  array $platform
 * @return string[]
 */
function wiz_env_lines(array $settings, array $platform)
{
    $lines = [
        'DB_HOST=' . $settings['host'],
        'DB_PORT=' . $settings['port'],
        'DB_NAME=' . $settings['name'],
        'DB_USER=' . $settings['user'],
        'DB_PASS=' . $settings['pass'],
    ];

    if (!empty($settings['ssl'])) {
        $lines[] = 'DB_SSL=1';
    }

    if (!empty($platform['serverless'])) {
        $lines[] = 'DB_SESSIONS=1';
    }

    return $lines;
}

/* ============================================================================
 * الجلسة (لحماية CSRF في النماذج)
 * ========================================================================== */

$sessionOk = false;

if (session_status() === PHP_SESSION_NONE) {
    try {
        @session_start();
    } catch (Throwable $e) {
        /* نتجاهل: سيظهر تنبيه في الخطوات */
    }
}

$sessionOk = (session_status() === PHP_SESSION_ACTIVE);

function wiz_csrf_token()
{
    if (!isset($_SESSION['wiz_csrf'])) {
        try {
            $_SESSION['wiz_csrf'] = bin2hex(random_bytes(16));
        } catch (Throwable $e) {
            $_SESSION['wiz_csrf'] = sha1(uniqid('wiz', true));
        }
    }

    return (string)$_SESSION['wiz_csrf'];
}

function wiz_csrf_valid($token)
{
    return isset($_SESSION['wiz_csrf'])
        && is_string($token)
        && $token !== ''
        && hash_equals((string)$_SESSION['wiz_csrf'], $token);
}

/* ============================================================================
 * الحالة العامة
 * ========================================================================== */

$platform = db_platform_info();
$settings = db_settings_load();

$steps = [];
$failed = false;
$dbReady = false;
$installed = false;
$adminCreated = false;
$adminPasswordIsDefault = false;
$adminPasswordChanged = false;
$adminEmail = 'admin@carrental.ma';
$adminPassword = 'Admin@123';
$carsCount = null;
$pdo = null;
$diagnosis = null;
$probeDiagnosis = null;
$connectionError = '';
$dbTables = [];
$notices = [];
$saveResult = null;
$forceSchema = false;
$postRejected = false;
$dbFlowBlocked = false;
$settingsSourceLabel = db_settings_origin_label($settings);
$formValues = null;

$action = isset($_POST['action']) ? (string)$_POST['action'] : '';

/* ============================================================================
 * معالجة النماذج
 * ========================================================================== */

if ($sessionOk && $_SERVER['REQUEST_METHOD'] === 'POST' && $action !== '') {

    if (!wiz_csrf_valid($_POST['csrf'] ?? '')) {

        $notices[] = [
            'type' => 'error',
            'text' => 'انتهت صلاحية الجلسة (رمز الحماية غير صالح). أعد تحميل الصفحة ثم أرسل النموذج مرة أخرى.',
        ];

        $action = '';
        $postRejected = true;
    }
}

/* -------------------------------------------------------------------------- */
/* حذف ملف الإعدادات المحلي                                                    */
/* -------------------------------------------------------------------------- */

if ($action === 'remove_config') {

    $result = db_settings_delete();

    $notices[] = [
        'type' => $result['ok'] ? 'success' : 'error',
        'text' => $result['message'],
    ];

    $settings = db_settings_load();
    $settingsSourceLabel = db_settings_origin_label($settings);
    $action = '';
}

/* -------------------------------------------------------------------------- */
/* إعادة التثبيت (حذف الجداول وإعادة إنشائها)                                   */
/* -------------------------------------------------------------------------- */

if ($action === 'reinstall') {

    if (!empty($_POST['confirm_reinstall'])) {
        $forceSchema = true;
    } else {
        $notices[] = [
            'type' => 'error',
            'text' => 'لإعادة التثبيت يجب تحديد مربع التأكيد (سيتم حذف كل البيانات).',
        ];
    }
}

/* -------------------------------------------------------------------------- */
/* حفظ / اختبار إعدادات قاعدة البيانات من النموذج                               */
/* -------------------------------------------------------------------------- */

if ($action === 'save_settings' || $action === 'test_settings') {

    $form = [
        'host'    => trim((string)($_POST['db_host'] ?? '')),
        'port'    => trim((string)($_POST['db_port'] ?? '')),
        'name'    => trim((string)($_POST['db_name'] ?? '')),
        'user'    => trim((string)($_POST['db_user'] ?? '')),
        'pass'    => (string)($_POST['db_pass'] ?? ''),
        'ssl'     => !empty($_POST['db_ssl']),
        'socket'  => trim((string)($_POST['db_socket'] ?? '')),
        'charset' => (string)($settings['charset'] ?? 'utf8mb4'),
    ];

    $formErrors = [];

    if ($form['socket'] === '') {

        if ($form['host'] === '') {
            $formErrors[] = 'عنوان الخادم (DB_HOST) مطلوب.';
        }

        if ($form['port'] === '' || !ctype_digit($form['port']) || (int)$form['port'] < 1 || (int)$form['port'] > 65535) {
            $formErrors[] = 'المنفذ (DB_PORT) يجب أن يكون رقماً بين 1 و 65535.';
        }
    }

    if ($form['name'] === '') {
        $formErrors[] = 'اسم قاعدة البيانات (DB_NAME) مطلوب.';
    } elseif (!preg_match('/^[A-Za-z0-9_\-]+$/', $form['name'])) {
        $formErrors[] = 'اسم قاعدة البيانات يقبل الأحرف اللاتينية والأرقام و _ و - فقط.';
    }

    if ($form['user'] === '') {
        $formErrors[] = 'اسم المستخدم (DB_USER) مطلوب.';
    }

    if ($form['port'] === '') {
        $form['port'] = '3306';
    }

    $formValues = $form;

    if (!empty($formErrors)) {

        $notices[] = [
            'type' => 'error',
            'text' => 'راجع الإعدادات المدخلة: ' . implode(' ', $formErrors),
        ];

        $action = '';
        $postRejected = true;
    } else {
        $settings['host'] = $form['host'];
        $settings['port'] = $form['port'];
        $settings['name'] = $form['name'];
        $settings['user'] = $form['user'];
        $settings['pass'] = $form['pass'];
        $settings['ssl'] = $form['ssl'];
        $settings['socket'] = $form['socket'];

        $settings['source'] = 'form';
        $settings['source_label'] = 'الإعدادات المُدخلة في هذه الصفحة';
        $settingsSourceLabel = 'الإعدادات المُدخلة في هذه الصفحة';
    }
}

/* قيم النموذج الافتراضية عند العرض */
if ($formValues === null) {
    $formValues = [
        'host'    => (string)$settings['host'],
        'port'    => (string)$settings['port'],
        'name'    => (string)$settings['name'],
        'user'    => (string)$settings['user'],
        'pass'    => '',
        'ssl'     => (bool)$settings['ssl'],
        'socket'  => (string)($settings['socket'] ?? ''),
        'charset' => (string)$settings['charset'],
    ];
}

/* نموذج مرفوض: نعرض الأخطاء فقط ولا نُنفّذ أي عملية على قاعدة البيانات */
if ($postRejected) {
    $dbFlowBlocked = true;
}

/* ============================================================================
 * 1) فحص إصدار PHP
 * ========================================================================== */

$phpOk = version_compare(PHP_VERSION, '7.4.0', '>=');

wiz_step(
    $steps,
    $phpOk,
    'إصدار PHP: ' . PHP_VERSION,
    $phpOk ? '' : 'المطلوب PHP 7.4 أو أحدث.'
);

if (!$phpOk) {
    $failed = true;
}

/* ============================================================================
 * 2) الإضافات المطلوبة
 * ========================================================================== */

if (!$failed) {

    foreach (db_required_extensions() as $name => $extension) {

        if (!$extension['ok'] && !$extension['required']) {

            wiz_step(
                $steps,
                false,
                'PHP Extension: ' . $name . ' (اختيارية)',
                $extension['note'],
                'warning'
            );

            continue;
        }

        wiz_step(
            $steps,
            $extension['ok'],
            'PHP Extension: ' . $name,
            $extension['ok'] ? $extension['note'] : 'هذه الإضافة مطلوبة: ' . $extension['note']
        );

        if (!$extension['ok']) {
            $failed = true;
        }
    }
}

/* ============================================================================
 * 3) الجلسات
 * ========================================================================== */

if (!$failed) {
    wiz_step(
        $steps,
        $sessionOk,
        'الجلسات (Sessions)',
        $sessionOk
            ? 'مُفعَّلة، ومجلد الجلسات قابل للكتابة.'
            : 'تعذّر بدء الجلسة. على الاستضافة السحابية اضبط DB_SESSIONS=1 وتأكد من صلاحيات مجلد الجلسات.',
        'warning'
    );
}

/* ============================================================================
 * 4) مصدر إعدادات قاعدة البيانات
 * ========================================================================== */

$usingDefaults = ($settings['source'] === 'default');

wiz_step(
    $steps,
    !$usingDefaults,
    'إعدادات قاعدة البيانات: ' . $settingsSourceLabel,
    $usingDefaults
        ? 'لم يتم العثور على أي إعدادات (لا متغيرات بيئة ولا ملف config.local.php)، لذلك يستخدم النظام القيم الافتراضية '
            . $settings['host'] . ':' . $settings['port'] . ' وهي غير متاحة إلا على جهازك المحلي.'
        : 'الخادم: ' . $settings['host'] . ':' . $settings['port']
            . ' | القاعدة: ' . $settings['name']
            . ' | المستخدم: ' . $settings['user']
            . ' | كلمة المرور: ' . db_settings_mask($settings['pass'])
            . ' | SSL: ' . ($settings['ssl'] ? 'مُفعَّل' : 'مُعطَّل')
            . (!empty($settings['notes']) ? ' — ' . implode(' ', $settings['notes']) : ''),
    'warning'
);

/* ============================================================================
 * 5) فحص المنفذ (TCP) — يميّز مشاكل الشبكة عن مشاكل البيانات
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked) {

    if (!empty($settings['socket'])) {

        $socketProbe = db_socket_probe($settings['socket']);

        wiz_step(
            $steps,
            $socketProbe['ok'],
            'فحص Socket المحلي: ' . $settings['socket'],
            $socketProbe['ok'] ? 'الملف موجود وقابل للاتصال.' : $socketProbe['error'],
            'warning'
        );

        if (!$socketProbe['ok']) {
            $probeDiagnosis = db_error_diagnosis(
                'SQLSTATE[HY000] [2002] No such file or directory (unix_socket)',
                $settings
            );
        }
    } elseif (function_exists('fsockopen')) {

        $probe = db_tcp_probe($settings['host'], $settings['port'], 4);

        $probeDetail = '';

        if ($probe['ok']) {
            $probeDetail = 'المنفذ مفتوح ومتاح (' . $settings['host'] . ':' . $settings['port'] . ').';
        } elseif ($probe['dns'] === false) {
            $probeDetail = 'تعذّر تحليل اسم الخادم (DNS): ' . $settings['host'];
        } else {
            $probeDetail = 'المنفذ ' . $settings['port'] . ' لا يستجيب على ' . $settings['host']
                . ($probe['error'] !== '' ? ' — ' . $probe['error'] : '');
        }

        wiz_step(
            $steps,
            $probe['ok'],
            'فحص المنفذ ' . $settings['host'] . ':' . $settings['port'],
            $probeDetail,
            'warning'
        );

        if (!$probe['ok']) {
            $probeDiagnosis = db_error_diagnosis(
                'SQLSTATE[HY000] [2002] ' . $probe['error'],
                $settings
            );
        }
    } else {
        wiz_step(
            $steps,
            false,
            'فحص المنفذ (fsockopen)',
            'الدالة fsockopen معطّلة على هذا الخادم، لذلك تم تخطي فحص المنفذ والاعتماد على محاولة الاتصال الفعلية.',
            'warning'
        );
    }
}

/* ============================================================================
 * 6) الاتصال بقاعدة البيانات
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked) {

    try {

        $pdo = new PDO(
            db_dsn($settings, false),
            $settings['user'],
            $settings['pass'],
            db_pdo_options($settings)
        );

        wiz_step(
            $steps,
            true,
            'الاتصال بخادم ' . ($settings['socket'] !== '' ? 'MySQL (Socket)' : 'MySQL / TiDB'),
            'تم الاتصال بـ '
                . ($settings['socket'] !== '' ? 'Socket: ' . $settings['socket'] : $settings['host'] . ':' . $settings['port'])
                . ' | SSL: ' . ($settings['ssl'] ? 'مُفعَّل' : 'مُعطَّل')
        );

    } catch (PDOException $e) {

        $connectionError = $e->getMessage();
        $diagnosis = db_error_diagnosis($connectionError, $settings);

        wiz_step($steps, false, 'فشل الاتصال بخادم MySQL / TiDB', $connectionError);
        $failed = true;

    } catch (Throwable $e) {

        $connectionError = $e->getMessage();
        $diagnosis = db_error_diagnosis($connectionError, $settings);

        wiz_step($steps, false, 'فشل الاتصال بخادم MySQL / TiDB', $connectionError);
        $failed = true;
    }
}

/* ============================================================================
 * 7) إنشاء قاعدة البيانات (إن سمحت الصلاحيات)
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked && $pdo instanceof PDO) {

    try {

        $safeDbName = preg_replace('/[^A-Za-z0-9_\-]/', '', (string)$settings['name']);

        if ($safeDbName === '') {
            throw new RuntimeException('اسم قاعدة البيانات غير صالح.');
        }

        $createdNow = false;

        try {

            $pdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$safeDbName}`"
                . ' DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
            );

        } catch (PDOException $createError) {

            /* قد لا يملك المستخدم صلاحية CREATE DATABASE — نتابع */
            $createdNow = false;
        }

        $pdo->exec("USE `{$safeDbName}`");

        wiz_step(
            $steps,
            true,
            'قاعدة البيانات `' . $safeDbName . '` جاهزة',
            $createdNow ? 'تم إنشاء القاعدة الآن.' : ''
        );

        $dbReady = true;

    } catch (PDOException $e) {

        $diagnosis = db_error_diagnosis($e->getMessage(), $settings);

        wiz_step($steps, false, 'تعذّر تحديد قاعدة البيانات `' . $settings['name'] . '`', $e->getMessage());
        $failed = true;

    } catch (Throwable $e) {

        wiz_step($steps, false, 'تعذّر تحديد قاعدة البيانات', $e->getMessage());
        $failed = true;
    }
}

/* ============================================================================
 * 8) الاتصال المباشر بقاعدة البيانات المحددة
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked && $dbReady) {

    try {

        $pdo = new PDO(
            db_dsn($settings, true),
            $settings['user'],
            $settings['pass'],
            db_pdo_options($settings)
        );

        wiz_step($steps, true, 'الاتصال بقاعدة البيانات ناجح', 'القاعدة: ' . $settings['name']);

    } catch (PDOException $e) {

        $diagnosis = db_error_diagnosis($e->getMessage(), $settings);

        wiz_step($steps, false, 'فشل الاتصال بقاعدة البيانات', $e->getMessage());
        $failed = true;

    } catch (Throwable $e) {

        wiz_step($steps, false, 'فشل الاتصال بقاعدة البيانات', $e->getMessage());
        $failed = true;
    }
}

/* ============================================================================
 * 9) حفظ الإعدادات التي نجحت (من النموذج)
 * ========================================================================== */

if (
    $action === 'save_settings'
    && $dbReady
    && !empty($_POST['persist'])
) {
    $saveResult = db_settings_write($settings);

    $notices[] = [
        'type' => $saveResult['ok'] ? 'success' : 'error',
        'text' => $saveResult['ok']
            ? 'تم حفظ الإعدادات في ' . $saveResult['path'] . ' — سيستخدمها الموقع تلقائياً من الآن.'
            : $saveResult['message']
                . ($platform['serverless']
                    ? ' على المنصات السحابية يجب ضبط متغيرات البيئة بدلاً من الملف.'
                    : ''),
    ];

    if ($saveResult['ok']) {
        $settings = db_settings_load();
        $settingsSourceLabel = db_settings_origin_label($settings);
    }
}

/* ============================================================================
 * 10) حالة التثبيت الحالية + تنفيذ database.sql
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked && $dbReady && $pdo instanceof PDO) {

    $dbTables = db_existing_tables($pdo);

    $expectedTables = db_expected_tables();
    $missingTables = array_values(array_diff($expectedTables, $dbTables));

    $installed = in_array('users', $dbTables, true)
        && in_array('cars', $dbTables, true)
        && in_array('bookings', $dbTables, true);

    if ($installed && !$forceSchema) {

        wiz_step(
            $steps,
            true,
            'النظام مثبَّت مسبقاً على هذه القاعدة',
            'تم تخطي تنفيذ database.sql حفاظاً على بياناتك (الجداول الموجودة: ' . count($dbTables) . ').'
        );

    } else {

        $sqlFile = __DIR__ . DIRECTORY_SEPARATOR . 'database.sql';

        if (!is_file($sqlFile)) {

            wiz_step($steps, false, 'ملف database.sql غير موجود', 'تأكد من رفع الملف كاملاً إلى مجلد المشروع.');
            $failed = true;

        } else {

            $sql = file_get_contents($sqlFile);

            if ($sql === false || trim($sql) === '') {

                wiz_step($steps, false, 'تعذّر قراءة ملف database.sql', 'الملف فارغ أو غير قابل للقراءة.');
                $failed = true;

            } else {

                $statements = db_split_sql($sql);
                $report = db_execute_statements($pdo, $statements);

                $dbTables = db_existing_tables($pdo);
                $missingTables = array_values(array_diff($expectedTables, $dbTables));

                if (!empty($report['errors'])) {

                    wiz_step(
                        $steps,
                        false,
                        'أخطاء أثناء تنفيذ database.sql',
                        implode(' | ', array_slice($report['errors'], 0, 3))
                    );

                    $failed = true;

                } else {

                    wiz_step(
                        $steps,
                        true,
                        'تم تنفيذ مخطط قاعدة البيانات',
                        'عدد الجمل: ' . count($statements)
                            . ' | نُفِّذت: ' . $report['done']
                            . ' | تم تخطيها: ' . $report['skipped']
                            . (!empty($report['ignored']) ? ' | موجودة مسبقاً: ' . count($report['ignored']) : '')
                    );
                }
            }
        }
    }

    /* التحقق من الجداول المتوقعة */
    if (!$failed && !empty($missingTables)) {

        wiz_step(
            $steps,
            false,
            'جداول ناقصة في قاعدة البيانات',
            'الجداول التالية لم تُنشأ: ' . implode(', ', $missingTables),
            'warning'
        );
    }
}

/* ============================================================================
 * 11) حساب المدير
 * ========================================================================== */

if (!$failed && !$dbFlowBlocked && $dbReady && $pdo instanceof PDO && in_array('users', $dbTables, true)) {

    try {

        $defaultAdminEmail = 'admin@carrental.ma';
        $defaultAdminPassword = 'Admin@123';

        $requestedEmail = trim((string)($_POST['admin_email'] ?? ''));
        $requestedPassword = (string)($_POST['admin_password'] ?? '');

        if ($requestedEmail === '' || !filter_var($requestedEmail, FILTER_VALIDATE_EMAIL)) {
            $requestedEmail = '';
        }

        if (strlen($requestedPassword) < 8) {
            $requestedPassword = '';
        }

        $targetEmail = $requestedEmail !== '' ? $requestedEmail : $defaultAdminEmail;

        /* هل يوجد حساب بهذا البريد؟ */
        $lookup = $pdo->prepare('SELECT id, password FROM users WHERE email = :email LIMIT 1');
        $lookup->execute([':email' => $targetEmail]);
        $existing = $lookup->fetch();

        $adminEmail = $targetEmail;

        if ($existing) {

            /* ------------------------------------------------ حساب موجود */
            if ($requestedPassword !== '') {

                $update = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
                $update->execute([
                    ':password' => password_hash($requestedPassword, PASSWORD_BCRYPT, ['cost' => 12]),
                    ':id'       => $existing['id'],
                ]);

                $adminPassword = $requestedPassword;
                $adminPasswordChanged = true;

                wiz_step($steps, true, 'تم تحديث كلمة مرور حساب المدير', $adminEmail);

            } elseif (
                !empty($existing['password'])
                && password_verify($defaultAdminPassword, (string)$existing['password'])
            ) {

                /* حساب database.sql الافتراضي ولم تُغيّر كلمته بعد */
                $adminPassword = $defaultAdminPassword;
                $adminPasswordIsDefault = true;

                wiz_step(
                    $steps,
                    false,
                    'حساب المدير ما زال يستخدم كلمة المرور الافتراضية',
                    'البريد: ' . $adminEmail . ' — غيّرها فور أول تسجيل دخول، أو عيّن كلمة مرور جديدة '
                        . 'من «خيارات متقدمة» في النموذج ثم أعد الفحص.',
                    'warning'
                );

            } else {

                $adminPassword = '';

                wiz_step($steps, true, 'حساب المدير موجود مسبقاً', $adminEmail . ' — استخدم كلمة المرور الخاصة به.');
            }

        } else {

            /* ------------------------------------------------ لا يوجد حساب */
            /* إن طُلب بريد مختلف ووُجد الحساب الافتراضي، نُحدّثه بدل ترك
               حساب بكلمة مرور معروفة أو إنشاء حساب ثانٍ. */
            $reuseDefault = false;

            if ($targetEmail !== $defaultAdminEmail) {

                $defaultLookup = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
                $defaultLookup->execute([':email' => $defaultAdminEmail]);
                $defaultRow = $defaultLookup->fetch();

                if ($defaultRow) {
                    $reuseDefault = true;
                }
            }

            $newPassword = $requestedPassword !== '' ? $requestedPassword : $defaultAdminPassword;

            $adminPassword = $newPassword;
            $adminPasswordIsDefault = ($requestedPassword === '');

            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

            if ($reuseDefault) {

                $update = $pdo->prepare(
                    'UPDATE users SET email = :email, password = :password, full_name = :name WHERE id = :id'
                );

                $update->execute([
                    ':email'    => $adminEmail,
                    ':password' => $hash,
                    ':name'     => 'مدير النظام',
                    ':id'       => $defaultRow['id'],
                ]);

                $adminCreated = true;

                wiz_step($steps, true, 'تم تحديث حساب المدير الافتراضي (البريد وكلمة المرور)', $adminEmail);

            } else {

                $insert = $pdo->prepare(
                    'INSERT INTO users
                        (full_name, email, password, phone, role, status, email_verified_at)
                     VALUES
                        (:full_name, :email, :password, :phone, :role, :status, NOW())'
                );

                $insert->execute([
                    ':full_name' => 'مدير النظام',
                    ':email'     => $adminEmail,
                    ':password'  => $hash,
                    ':phone'     => '0600000000',
                    ':role'      => 'super_admin',
                    ':status'    => 'active',
                ]);

                $adminCreated = true;

                wiz_step($steps, true, 'تم إنشاء حساب المدير', $adminEmail);
            }
        }

    } catch (PDOException $e) {

        wiz_step($steps, false, 'تعذّر إنشاء / التحقق من حساب المدير', $e->getMessage());
        $failed = true;

    } catch (Throwable $e) {

        wiz_step($steps, false, 'تعذّر إنشاء / التحقق من حساب المدير', $e->getMessage());
        $failed = true;
    }
}

/* ============================================================================
 * 12) مجلدات النظام
 * ========================================================================== */

if (!$failed) {

    $dirs = ['uploads', 'uploads/cars', 'uploads/avatars', 'uploads/invoices', 'assets/images'];

    $okAll = true;
    $details = [];

    foreach ($dirs as $dir) {

        $path = __DIR__ . DIRECTORY_SEPARATOR . $dir;

        if (is_dir($path)) {
            continue;
        }

        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            $okAll = false;
            $details[] = $dir . ': تعذر الإنشاء';
        }
    }

    wiz_step(
        $steps,
        $okAll,
        'إنشاء مجلدات النظام',
        $okAll
            ? 'uploads/cars, uploads/avatars, uploads/invoices, assets/images'
            : implode(' | ', $details) . ' — على المنصات السحابية نظام الملفات مؤقت، استخدم تخزيناً خارجياً للصور.',
        'warning'
    );
}

/* ============================================================================
 * 13) عدد السيارات
 * ========================================================================== */

if (!$failed && $dbReady && $pdo instanceof PDO && in_array('cars', $dbTables, true)) {

    try {

        $carsCount = (int)$pdo->query('SELECT COUNT(*) FROM cars')->fetchColumn();

        wiz_step(
            $steps,
            true,
            'عدد السيارات في قاعدة البيانات: ' . $carsCount,
            $carsCount > 0 ? '' : 'يمكنك إضافة السيارات التجريبية من add-all-cars.php'
        );

    } catch (Throwable $e) {

        wiz_step($steps, false, 'تعذر فحص جدول السيارات', $e->getMessage(), 'warning');
    }
}

/* ============================================================================
 * النتيجة النهائية
 * ========================================================================== */

if ($diagnosis === null && !$dbReady) {
    $diagnosis = $probeDiagnosis;
}

$success = (!$failed && $dbReady && !$dbFlowBlocked);
$showForm = (!$success) || $usingDefaults || $settings['source'] === 'form';

$envLines = wiz_env_lines($settings, $platform);
$envInventory = db_env_inventory();

$maskedPassword = db_settings_mask($settings['pass']);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">

<title>معالج تثبيت نظام تأجير السيارات</title>

<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
* { margin: 0; padding: 0; box-sizing: border-box; }

body {
    font-family: 'Cairo', Tahoma, Arial, sans-serif;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 36px 14px;
    color: #111827;
}

.card {
    background: #ffffff;
    max-width: 900px;
    margin: 0 auto;
    border-radius: 22px;
    box-shadow: 0 25px 70px rgba(0, 0, 0, .30);
    overflow: hidden;
}

.card-head { background: #111827; color: #ffffff; padding: 32px 26px; text-align: center; }
.card-head h1 { font-size: 1.55rem; font-weight: 900; }
.card-head p { color: #aeb6c5; margin-top: 7px; font-size: .9rem; }

.card-body { padding: 28px; }

.status { padding: 16px 18px; border-radius: 14px; margin-bottom: 20px; font-weight: 700; text-align: center; }
.status.success { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
.status.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

h2.section {
    font-size: 1.05rem;
    font-weight: 800;
    margin: 26px 0 12px;
    padding-bottom: 8px;
    border-bottom: 2px solid #eef2f7;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 15px;
    border-radius: 13px;
    margin-bottom: 9px;
    background: #f8fafc;
    border: 1px solid #eef2f7;
}
.step.ok { background: #ecfdf5; border-color: #d1fae5; }
.step.fail { background: #fef2f2; border-color: #fee2e2; }
.step.warning { background: #fffbeb; border-color: #fde68a; }
.step.info { background: #eff6ff; border-color: #bfdbfe; }

.step .icon {
    width: 34px; height: 34px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: .95rem; flex-shrink: 0; color: #ffffff; font-weight: 900;
}
.step.ok .icon { background: #10b981; }
.step.fail .icon { background: #ef4444; }
.step.warning .icon { background: #f59e0b; }
.step.info .icon { background: #3b82f6; }

.step b { display: block; font-size: .93rem; color: #111827; }
.step small { display: block; color: #6b7280; font-size: .78rem; margin-top: 4px; word-break: break-word; line-height: 1.7; }

.panel { border-radius: 14px; padding: 16px 18px; margin-top: 18px; font-size: .86rem; line-height: 1.9; }
.panel.warn { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; }
.panel.danger { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; }
.panel.info { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.panel.ok { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; }
.panel h3 { font-size: .95rem; margin-bottom: 8px; }
.panel ul, .panel ol { margin: 6px 20px 0; }
.panel li { margin-bottom: 5px; }

.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px; margin-top: 12px; }
.fact { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 12px; padding: 12px 14px; }
.fact span { display: block; color: #6b7280; font-size: .74rem; margin-bottom: 3px; }
.fact b { font-size: .86rem; font-weight: 700; word-break: break-word; }

code, pre {
    direction: ltr;
    text-align: left;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    display: block;
}
code { display: inline-block; background: rgba(0,0,0,.06); padding: 2px 6px; border-radius: 5px; font-size: .8rem; }
pre {
    background: #111827; color: #e5e7eb; padding: 14px 16px; border-radius: 12px;
    font-size: .78rem; line-height: 1.8; overflow-x: auto; margin: 10px 0;
}
pre.light { background: #f3f4f6; color: #111827; border: 1px solid #e5e7eb; }

table.env { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: .8rem; }
table.env th, table.env td { text-align: right; padding: 7px 10px; border-bottom: 1px solid #eef2f7; }
table.env td:first-child { direction: ltr; text-align: left; font-family: monospace; color: #4b5563; }

form.settings { margin-top: 14px; }
.fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
.field label { display: block; font-size: .78rem; font-weight: 700; margin-bottom: 5px; color: #374151; }
.field input[type="text"], .field input[type="password"], .field input[type="email"] {
    width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 10px;
    font-family: inherit; font-size: .85rem; background: #ffffff; direction: ltr; text-align: left;
}
.field input:focus { outline: 2px solid #a5b4fc; border-color: #6366f1; }
.check { display: flex; align-items: center; gap: 8px; font-size: .82rem; margin-top: 10px; }
.check input { width: 17px; height: 17px; accent-color: #667eea; }

.btns { text-align: center; margin-top: 22px; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.btn {
    display: inline-block; padding: 12px 24px; border-radius: 12px; text-decoration: none;
    font-weight: 800; font-size: .88rem; border: none; cursor: pointer; font-family: inherit;
    transition: .2s ease;
}
.btn:hover { transform: translateY(-2px); }
.btn-p { background: linear-gradient(135deg, #667eea, #764ba2); color: #ffffff; }
.btn-g { background: #10b981; color: #ffffff; }
.btn-d { background: #ef4444; color: #ffffff; }
.btn-o { background: #ffffff; color: #667eea; border: 2px solid #667eea; }

details.more { margin-top: 16px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 13px; padding: 6px 16px; }
details.more > summary { cursor: pointer; font-weight: 700; font-size: .86rem; padding: 9px 0; color: #374151; }
details.more[open] > summary { border-bottom: 1px solid #eef2f7; margin-bottom: 12px; }

@media (max-width: 600px) {
    body { padding: 18px 10px; }
    .card-body { padding: 20px 16px; }
    .card-head h1 { font-size: 1.25rem; }
    .btn { width: 100%; }
}
</style>

</head>

<body>

<div class="card">

    <div class="card-head">
        <h1>🚗 معالج تثبيت نظام تأجير السيارات</h1>
        <p>Premium Car Rental — Setup Wizard</p>
    </div>

    <div class="card-body">

        <?php if ($success): ?>

            <div class="status success">
                ✅ تم تجهيز النظام بنجاح — قاعدة البيانات والإعدادات جاهزة
            </div>

        <?php else: ?>

            <div class="status error">
                ❌ التثبيت غير مكتمل — راجع التشخيص والخطوات أدناه
            </div>

        <?php endif; ?>

        <?php foreach ($notices as $notice): ?>

            <div class="panel <?php echo $notice['type'] === 'success' ? 'ok' : 'danger'; ?>">
                <?php echo wiz_e($notice['text']); ?>
            </div>

        <?php endforeach; ?>

        <?php if (!$success && $diagnosis): ?>

            <div class="panel danger">
                <h3>🔎 التشخيص: <?php echo wiz_e($diagnosis['title']); ?></h3>

                <?php if (!empty($diagnosis['cause'])): ?>
                    <div><?php echo wiz_e($diagnosis['cause']); ?></div>
                <?php endif; ?>

                <?php if (!empty($diagnosis['hints'])): ?>
                    <ol>
                        <?php foreach ($diagnosis['hints'] as $hint): ?>
                            <li><?php echo $hint; ?></li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <?php if (!$success && $usingDefaults && !$dbReady): ?>

            <div class="panel warn">
                <h3>⚠️ السبب الأكثر شيوعاً لهذا الخطأ</h3>
                لم يتم ضبط أي إعدادات لقاعدة البيانات، لذلك يبحث النظام عن خادم MySQL على
                <code>127.0.0.1:3306</code> وهو عنوان <strong>جهازك المحلي فقط</strong>.
                على الاستضافات السحابية (Vercel) والاستضافة المشتركة لا يوجد خادم MySQL على هذا العنوان،
                لذلك يرفض الاتصال فوراً برسالة <code>Connection refused</code>.
                <br>
                <strong>الحل:</strong> أنشئ قاعدة بيانات MySQL خارجية (TiDB Cloud Serverless مجاناً)
                ثم أدخل بياناتها في النموذج أدناه أو اضبط متغيرات البيئة.
            </div>

        <?php endif; ?>

        <h2 class="section">📋 فحص بيئة التشغيل والخطوات</h2>

        <?php foreach ($steps as $step): ?>

            <div class="step <?php echo $step['ok'] ? 'ok' : $step['level']; ?>">
                <div class="icon"><?php echo $step['ok'] ? '✓' : ($step['level'] === 'warning' ? '!' : '✗'); ?></div>
                <div>
                    <b><?php echo wiz_e($step['label']); ?></b>
                    <?php if (!empty($step['detail'])): ?>
                        <small><?php echo wiz_e($step['detail']); ?></small>
                    <?php endif; ?>
                </div>
            </div>

        <?php endforeach; ?>

        <details class="more">
            <summary>🧾 تفاصيل بيئة التشغيل والإعدادات الفعلية</summary>

            <div class="grid">
                <div class="fact"><span>المنصة</span><b><?php echo wiz_e($platform['name']); ?></b></div>
                <div class="fact"><span>PHP</span><b><?php echo wiz_e($platform['php'] . ' (' . $platform['sapi'] . ')'); ?></b></div>
                <div class="fact"><span>مصدر الإعدادات</span><b><?php echo wiz_e($settingsSourceLabel); ?></b></div>
                <div class="fact"><span>الخادم</span><b><?php echo wiz_e(
                    !empty($settings['socket'])
                        ? 'Socket: ' . $settings['socket']
                        : ((string)$settings['host'] . ':' . (string)$settings['port'])
                ); ?></b></div>
                <div class="fact"><span>قاعدة البيانات</span><b><?php echo wiz_e($settings['name']); ?></b></div>
                <div class="fact"><span>المستخدم</span><b><?php echo wiz_e($settings['user']); ?></b></div>
                <div class="fact"><span>كلمة المرور</span><b><?php echo wiz_e($maskedPassword); ?></b></div>
                <div class="fact"><span>SSL / TLS</span><b><?php echo $settings['ssl'] ? 'مُفعَّل' : 'مُعطَّل'; ?></b></div>
                <div class="fact"><span>مجلد المشروع قابل للكتابة</span><b><?php echo $platform['root_writable'] ? 'نعم (يمكن حفظ config.local.php)' : 'لا (استخدم متغيرات البيئة)'; ?></b></div>
                <div class="fact"><span>ملف الإعدادات المحلي</span><b><?php echo $settings['local_file'] !== '' ? wiz_e($settings['local_file']) : 'غير موجود'; ?></b></div>
            </div>

            <?php foreach ((array)($settings['notes'] ?? []) as $autoNote): ?>
                <div class="panel info" style="margin-top:10px">ℹ️ <?php echo wiz_e($autoNote); ?></div>
            <?php endforeach; ?>

            <?php foreach (db_platform_notes($platform) as $note): ?>
                <div class="panel info" style="margin-top:10px"><?php echo wiz_e($note); ?></div>
            <?php endforeach; ?>

            <?php if (!empty($envInventory)): ?>
                <h3 style="margin-top:16px;font-size:.86rem">متغيرات البيئة المكتشفة</h3>
                <table class="env">
                    <tr><th>المتغير</th><th>القيمة</th></tr>
                    <?php foreach ($envInventory as $name => $value): ?>
                        <tr>
                            <td><?php echo wiz_e($name); ?></td>
                            <td><?php echo wiz_e($value); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <div class="panel warn" style="margin-top:16px">
                    لا يوجد أي متغير بيئة لقاعدة البيانات في هذا التشغيل (لا <code>DB_*</code> ولا <code>DATABASE_URL</code> ولا <code>MYSQL_*</code>).
                </div>
            <?php endif; ?>
        </details>

        <?php if ($showForm && !$success): ?>

            <h2 class="section">🔌 إعدادات قاعدة البيانات</h2>

            <div class="panel info">
                أدخل بيانات الاتصال أدناه لاختبارها فوراً. عند نجاح الاختبار واختيار «اختبار وحفظ» سيتم إنشاء ملف
                <code>config.local.php</code> داخل المشروع (غير مُتتبع في Git) وسيستخدمه الموقع تلقائياً.
                إن كانت المنصة سحابية (الملفات غير دائمة) استخدم متغيرات البيئة الموضّحة في المربع أسفل النموذج.
            </div>

            <form method="post" class="settings" autocomplete="off">

                <input type="hidden" name="csrf" value="<?php echo wiz_e($sessionOk ? wiz_csrf_token() : ''); ?>">
                <input type="hidden" name="action" value="save_settings">
                <input type="hidden" name="persist" value="1">

                <div class="fields">
                    <div class="field">
                        <label for="db_host">عنوان الخادم DB_HOST</label>
                        <input type="text" id="db_host" name="db_host" value="<?php echo wiz_e($formValues['host']); ?>" placeholder="gateway01.eu-central-1.prod.aws.tidbcloud.com">
                    </div>
                    <div class="field">
                        <label for="db_port">المنفذ DB_PORT</label>
                        <input type="text" id="db_port" name="db_port" value="<?php echo wiz_e($formValues['port']); ?>" placeholder="3306">
                    </div>
                    <div class="field">
                        <label for="db_name">اسم قاعدة البيانات DB_NAME</label>
                        <input type="text" id="db_name" name="db_name" value="<?php echo wiz_e($formValues['name']); ?>" placeholder="car_rental_db">
                    </div>
                    <div class="field">
                        <label for="db_user">اسم المستخدم DB_USER</label>
                        <input type="text" id="db_user" name="db_user" value="<?php echo wiz_e($formValues['user']); ?>" placeholder="root">
                    </div>
                    <div class="field">
                        <label for="db_pass">كلمة المرور DB_PASS</label>
                        <input type="password" id="db_pass" name="db_pass" value="" placeholder="<?php echo $settings['pass'] !== '' ? 'اتركها فارغة للإبقاء على الحالية' : 'كلمة المرور'; ?>">
                    </div>
                    <div class="field">
                        <label for="db_socket">Socket محلي (اختياري) DB_SOCKET</label>
                        <input type="text" id="db_socket" name="db_socket" value="<?php echo wiz_e($formValues['socket']); ?>" placeholder="/var/run/mysqld/mysqld.sock">
                    </div>
                </div>

                <label class="check">
                    <input type="checkbox" name="db_ssl" value="1" <?php echo $settings['ssl'] ? 'checked' : ''; ?>>
                    تفعيل TLS/SSL (مطلوب في TiDB Cloud Serverless)
                </label>

                <details class="more">
                    <summary>خيارات متقدمة: حساب المدير</summary>
                    <div class="fields">
                        <div class="field">
                            <label for="admin_email">بريد المدير</label>
                            <input type="email" id="admin_email" name="admin_email" value="" placeholder="admin@carrental.ma">
                        </div>
                        <div class="field">
                            <label for="admin_password">كلمة مرور المدير (8 أحرف على الأقل)</label>
                            <input type="password" id="admin_password" name="admin_password" value="" placeholder="اتركه فارغاً للقيمة الافتراضية Admin@123">
                        </div>
                    </div>
                </details>

                <div class="btns">
                    <button type="submit" class="btn btn-p">🔌 اختبار وحفظ الإعدادات وإكمال التثبيت</button>
                </div>
            </form>

        <?php endif; ?>

        <?php if (!$success): ?>

            <details class="more">
                <summary>🧩 ضبط الإعدادات عبر متغيرات البيئة (Vercel، الدوكر، الاستضافة المشتركة)</summary>

                <div class="panel warn">
                    <h3>الطريقة الأضمن على المنصات السحابية</h3>
                    متغيرات البيئة تتقدم على ملف <code>config.local.php</code> وتعمل حتى لو كان نظام الملفات غير قابل للكتابة.
                </div>

                <?php if ((string)$settings['pass'] !== ''): ?>
                    <div class="panel warn">
                        🔐 المربع التالي يحتوي كلمة مرور قاعدة البيانات — لا تشاركه ولا تصوّره.
                    </div>
                <?php endif; ?>

                <pre><?php echo wiz_e(implode("\n", $envLines)); ?></pre>

                <?php if ($platform['vercel']): ?>
                    <p>على Vercel: <strong>Project → Settings → Environment Variables</strong> ثم أضف المتغيرات أعلاه لكل بيئة (Production + Preview) ثم أعد النشر Redeploy. أو من الطرفية:</p>
                    <pre><?php
                        $cli = [];
                        foreach ($envLines as $line) {
                            $cli[] = 'vercel env add ' . strtok($line, '=') . ' production';
                        }
                        $cli[] = 'vercel --prod';
                        echo wiz_e(implode("\n", $cli));
                    ?></pre>
                    <p>
                        ملاحظة: على Vercel لا يوجد خادم MySQL محلي إطلاقاً، ولا يمكن حفظ الملفات بشكل دائم —
                        يجب إنشاء قاعدة بيانات خارجية (TiDB Cloud Serverless / PlanetScale / Aiven …) وضبط المتغيرات.
                    </p>
                <?php endif; ?>

                <details class="more">
                    <summary>☁️ خطوات إنشاء قاعدة بيانات مجانية على TiDB Cloud Serverless</summary>
                    <ol>
                        <li>أنشئ حساباً على <code>tidbcloud.com</code> ثم <strong>Create Cluster → Serverless</strong>.</li>
                        <li>بعد الإنشاء اضغط <strong>Connect</strong> واختر <code>Generate Password</code>.</li>
                        <li>انسخ قيم: <code>Host</code> و<code>Port = 4000</code> و<code>User</code> (بصيغة <code>xxxxxxxx.root</code>).</li>
                        <li>اضبط المتغيرات: <code>DB_HOST</code>, <code>DB_PORT=4000</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>, <code>DB_SSL=1</code> و<code>DB_SESSIONS=1</code>.</li>
                        <li>أعد نشر الموقع ثم أعد تحميل هذه الصفحة — المعالج سينشئ قاعدة البيانات والجداول تلقائياً.</li>
                    </ol>
                </details>
            </details>

        <?php endif; ?>

        <?php if ($settings['local_file'] !== ''): ?>

            <details class="more">
                <summary>🗑️ إدارة ملف الإعدادات المحلي</summary>

                <p style="font-size:.84rem;line-height:1.9">
                    الملف الحالي: <code><?php echo wiz_e($settings['local_file']); ?></code><br>
                    متغيرات البيئة (إن وُجدت) تتقدم على هذا الملف. إذا حذفته سيعود النظام إلى متغيرات البيئة أو القيم الافتراضية.
                </p>

                <form method="post" onsubmit="return confirm('حذف ملف الإعدادات المحلي؟');">
                    <input type="hidden" name="csrf" value="<?php echo wiz_e($sessionOk ? wiz_csrf_token() : ''); ?>">
                    <input type="hidden" name="action" value="remove_config">
                    <div class="btns">
                        <button type="submit" class="btn btn-o">حذف ملف الإعدادات</button>
                    </div>
                </form>
            </details>

        <?php endif; ?>

        <?php if ($success): ?>

            <h2 class="section">✅ الخطوات التالية</h2>

            <?php if ($adminCreated || $adminPasswordChanged || $adminPasswordIsDefault): ?>

                <div class="panel warn">
                    🔐 <strong>حساب المدير</strong><br>
                    البريد: <code><?php echo wiz_e($adminEmail); ?></code><br>
                    كلمة المرور: <code><?php echo wiz_e($adminPassword); ?></code>
                    <br><br>
                    <?php if ($adminPasswordIsDefault): ?>
                        ⚠️ هذه كلمة المرور الافتراضية المعروفة <code>Admin@123</code> — غيّرها فور أول تسجيل دخول.
                    <?php elseif ($adminCreated): ?>
                        ⚠️ احفظ كلمة المرور الآن — لن تظهر مرة أخرى — ثم غيّرها من لوحة التحكم بعد أول دخول.
                    <?php else: ?>
                        ⚠️ تم تحديث كلمة المرور — احفظها الآن.
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="panel info">
                    🔐 حساب المدير على هذه القاعدة: <code><?php echo wiz_e($adminEmail); ?></code>
                    — استخدم كلمة المرور الخاصة به (لم يتم تغييرها).
                </div>

            <?php endif; ?>

            <?php if ($carsCount === 0): ?>

                <div class="panel info">
                    🚙 لا توجد سيارات في القاعدة بعد. أضف السيارات التجريبية من الأزرار أدناه.
                </div>

            <?php endif; ?>

            <div class="btns">
                <?php if ($carsCount === 0): ?>
                    <a class="btn btn-g" href="add-all-cars.php">🚙 إضافة 15 سيارة تجريبية</a>
                    <a class="btn btn-o" href="seed-cars.php">إضافة 3 سيارات فقط</a>
                <?php endif; ?>
                <a class="btn btn-p" href="index.php">🏠 الانتقال إلى الموقع</a>
                <a class="btn btn-o" href="admin/index.php">⚙️ لوحة الإدارة</a>
                <a class="btn btn-o" href="install.php">🔄 إعادة الفحص</a>
            </div>

            <div class="panel danger">
                <h3>⚠️ مهم جداً للإنتاج</h3>
                بعد نجاح التثبيت احذف الملفات التالية من الخادم حتى لا يستطيع أي زائر إعادة التثبيت أو رؤية بياناتك:
                <code>install.php</code>
                <code>create-tables.php</code>
                <code>create-admin.php</code>
                <code>create_structure.php</code>
                <code>seed-cars.php</code>
                <code>add-all-cars.php</code>
                <code>download-images.php</code>
            </div>

            <details class="more">
                <summary>🛠️ تعديل إعدادات قاعدة البيانات بعد التثبيت</summary>

                <div class="panel info">
                    متغيرات البيئة تتقدم دائماً على الملف <code>config.local.php</code>.
                </div>

                <pre><?php echo wiz_e(implode("\n", $envLines)); ?></pre>

                <?php if ($settings['local_file'] !== ''): ?>
                    <div class="panel ok">
                        الإعدادات الحالية محفوظة في: <code><?php echo wiz_e($settings['local_file']); ?></code>
                    </div>
                <?php endif; ?>

                <form method="post" class="settings" autocomplete="off">
                    <input type="hidden" name="csrf" value="<?php echo wiz_e($sessionOk ? wiz_csrf_token() : ''); ?>">
                    <input type="hidden" name="action" value="save_settings">
                    <input type="hidden" name="persist" value="1">

                    <div class="fields">
                        <div class="field">
                            <label for="s_host">DB_HOST</label>
                            <input type="text" id="s_host" name="db_host" value="<?php echo wiz_e($settings['host']); ?>">
                        </div>
                        <div class="field">
                            <label for="s_port">DB_PORT</label>
                            <input type="text" id="s_port" name="db_port" value="<?php echo wiz_e($settings['port']); ?>">
                        </div>
                        <div class="field">
                            <label for="s_name">DB_NAME</label>
                            <input type="text" id="s_name" name="db_name" value="<?php echo wiz_e($settings['name']); ?>">
                        </div>
                        <div class="field">
                            <label for="s_user">DB_USER</label>
                            <input type="text" id="s_user" name="db_user" value="<?php echo wiz_e($settings['user']); ?>">
                        </div>
                        <div class="field">
                            <label for="s_pass">DB_PASS</label>
                            <input type="password" id="s_pass" name="db_pass" value="" placeholder="اتركها فارغة للإبقاء على الحالية">
                        </div>
                        <div class="field">
                            <label for="s_socket">DB_SOCKET (اختياري)</label>
                            <input type="text" id="s_socket" name="db_socket" value="<?php echo wiz_e((string)($settings['socket'] ?? '')); ?>">
                        </div>
                    </div>

                    <label class="check">
                        <input type="checkbox" name="db_ssl" value="1" <?php echo $settings['ssl'] ? 'checked' : ''; ?>>
                        تفعيل TLS/SSL
                    </label>

                    <div class="btns">
                        <button type="submit" class="btn btn-p">حفظ الإعدادات واختبارها</button>
                    </div>
                </form>

                <?php if ($installed): ?>
                    <details class="more">
                        <summary>♻️ إعادة تثبيت كاملة (حذف جميع الجداول والبيانات)</summary>
                        <div class="panel danger">
                            سيتم تنفيذ <code>database.sql</code> بالكامل، وهذا يعني حذف كل الحجوزات والسيارات والعملاء نهائياً.
                            استخدم هذا الخيار فقط إذا كنت تريد البدء من الصفر.
                        </div>
                        <form method="post" onsubmit="return confirm('تأكيد: سيتم حذف جميع البيانات. متابعة؟');">
                            <input type="hidden" name="csrf" value="<?php echo wiz_e($sessionOk ? wiz_csrf_token() : ''); ?>">
                            <input type="hidden" name="action" value="reinstall">
                            <label class="check">
                                <input type="checkbox" name="confirm_reinstall" value="1">
                                أُقر بأنني أريد حذف جميع البيانات وإعادة التثبيت.
                            </label>
                            <div class="btns">
                                <button type="submit" class="btn btn-d">إعادة التثبيت وحذف البيانات</button>
                            </div>
                        </form>
                    </details>
                <?php endif; ?>
            </details>

        <?php endif; ?>

    </div>
</div>

</body>
</html>
