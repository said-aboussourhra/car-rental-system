<?php
/**
 * ============================================================================
 * Premium Car Rental — إعدادات قاعدة البيانات / Database Settings Resolver
 * ============================================================================
 *
 * ملف مشترك بين:
 *   - includes/config.php   : تشغيل الموقع
 *   - install.php           : معالج التثبيت
 *
 * ترتيب الأولوية (الأعلى أولاً):
 *   1. متغيرات البيئة        DB_HOST / DB_PORT / DB_NAME / DB_USER / DB_PASS / DB_SSL / DB_SOCKET
 *   2. DATABASE_URL          mysql://user:pass@host:port/database
 *   3. متغيرات بديلة         MYSQL_* / TIDB_* (تُضبط تلقائياً على بعض المنصات)
 *   4. ملف الإعدادات المحلي  config.local.php (ينشئه معالج التثبيت — غير مُتتبع في Git)
 *   5. القيم الافتراضية      127.0.0.1:3306 (لن تعمل على الاستضافات السحابية)
 *
 * هذا الملف لا يتصل بقاعدة البيانات إطلاقاً، ولا يعتمد على أي ملف آخر.
 * ============================================================================
 */

if (!defined('CAR_RENTAL_DB_SETTINGS')) {
    define('CAR_RENTAL_DB_SETTINGS', true);
}

/* حماية إضافية في حال تجاهل الخادم لقواعد .htaccess */
if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {
    http_response_code(403);
    exit('Forbidden');
}

/* ============================================================================
 * 1) القيم الافتراضية
 * ========================================================================== */

/**
 * القيم الافتراضية لإعدادات قاعدة البيانات.
 *
 * @return array<string, mixed>
 */
function db_settings_defaults()
{
    return [
        'host'     => '127.0.0.1',
        'port'     => '3306',
        'name'     => 'car_rental_db',
        'user'     => 'root',
        'pass'     => '',
        'ssl'      => false,
        'socket'   => '',
        'charset'  => 'utf8mb4',
    ];
}

/**
 * مسارات ملفات الإعدادات المحلية (بترتيب الأولوية).
 *
 * @return string[]
 */
function db_settings_file_paths()
{
    $root = dirname(__DIR__);

    return [
        $root . DIRECTORY_SEPARATOR . 'config.local.php',
        $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db-config.php',
    ];
}

/**
 * المسار الرئيسي الذي يكتب فيه معالج التثبيت.
 *
 * @return string
 */
function db_settings_file_path()
{
    $paths = db_settings_file_paths();

    return $paths[0];
}

/* ============================================================================
 * 2) قراءة الإعدادات من البيئة والملفات
 * ========================================================================== */

/**
 * مقارنة أي قيمة بنص "مُفعَّل".
 *
 * @param  mixed $value
 * @return bool
 */
function db_settings_truthy($value)
{
    if (is_bool($value)) {
        return $value;
    }

    $value = strtolower(trim((string)$value));

    return in_array($value, ['1', 'true', 'yes', 'on', 'required', 'require', 'verify_ca', 'verify_identity'], true);
}

/**
 * تحويل قيمة إلى منطقي مع إمكانية "غير محدَّد".
 *
 * @param  mixed $value
 * @return bool|null  null = لم تُضبط
 */
function db_settings_bool_or_null($value)
{
    if ($value === null) {
        return null;
    }

    $value = strtolower(trim((string)$value));

    if ($value === '') {
        return null;
    }

    if (in_array($value, ['0', 'false', 'no', 'off', 'none', 'disabled', 'disable'], true)) {
        return false;
    }

    return true;
}

/**
 * قراءة متغير بيئة موجود فعلاً (حتى لو كانت قيمته سلسلة فارغة).
 *
 * @param  string $name
 * @return string|null
 */
function db_settings_env($name)
{
    $value = getenv($name);

    if ($value === false) {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? false;
    }

    if ($value === false || $value === null) {
        return null;
    }

    return (string)$value;
}

/**
 * قائمة مرشّحي الإعدادات مرتبة حسب الأولوية.
 *
 * كل مرشّح: ['key' => معرف, 'label' => وصف عربي, 'values' => ['host' => ...]]
 * القيمة null تعني "غير محدَّد", وسلسلة فارغة قيمة صحيحة (خاصة كلمة المرور).
 *
 * @return array<int, array<string, mixed>>
 */
function db_settings_candidates()
{
    $candidates = [];

    /* ------------------------------------------------------------------ */
    /* 1. متغيرات البيئة الرسمية DB_*                                     */
    /* ------------------------------------------------------------------ */

    $env = [];

    $envMap = [
        'host'    => ['DB_HOST', 'DB_SERVER'],
        'port'    => ['DB_PORT'],
        'name'    => ['DB_NAME', 'DB_DATABASE'],
        'user'    => ['DB_USER', 'DB_USERNAME'],
        'pass'    => ['DB_PASS', 'DB_PASSWORD'],
        'socket'  => ['DB_SOCKET'],
        'charset' => ['DB_CHARSET'],
    ];

    foreach ($envMap as $field => $names) {
        foreach ($names as $name) {
            $value = db_settings_env($name);

            if ($value !== null && ($value !== '' || $field === 'pass')) {
                $env[$field] = $value;
                break;
            }
        }
    }

    $ssl = db_settings_env('DB_SSL');

    if ($ssl === null) {
        $ssl = db_settings_env('DB_TLS');
    }

    if ($ssl !== null) {
        $env['ssl'] = db_settings_bool_or_null($ssl);
    }

    if (!empty($env)) {
        $candidates[] = [
            'key'    => 'env',
            'label'  => 'متغيرات البيئة DB_*',
            'values' => $env,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 2. DATABASE_URL                                                    */
    /* ------------------------------------------------------------------ */

    $url = db_settings_env('DATABASE_URL');

    if ($url === null) {
        $url = db_settings_env('MYSQL_URL');
    }

    if ($url === null) {
        $url = db_settings_env('TIDB_DATABASE_URL');
    }

    if ($url !== null && trim($url) !== '') {
        $parsed = db_settings_parse_url($url);

        if (!empty($parsed)) {
            $candidates[] = [
                'key'    => 'database_url',
                'label'  => 'DATABASE_URL',
                'values' => $parsed,
            ];
        }
    }

    /* ------------------------------------------------------------------ */
    /* 3. متغيرات بديلة MYSQL_* / TIDB_*                                  */
    /* ------------------------------------------------------------------ */

    $alias = [];

    $aliasMap = [
        'host' => ['MYSQL_HOST', 'MYSQL_SERVER', 'TIDB_HOST'],
        'port' => ['MYSQL_PORT', 'MYSQL_TCP_PORT', 'TIDB_PORT'],
        'name' => ['MYSQL_DATABASE', 'MYSQL_DB', 'TIDB_DATABASE', 'TIDB_DB_NAME'],
        'user' => ['MYSQL_USER', 'TIDB_USER'],
        'pass' => ['MYSQL_PASSWORD', 'MYSQL_PASS', 'TIDB_PASSWORD'],
    ];

    foreach ($aliasMap as $field => $names) {
        foreach ($names as $name) {
            $value = db_settings_env($name);

            if ($value !== null && ($value !== '' || $field === 'pass')) {
                $alias[$field] = $value;
                break;
            }
        }
    }

    $aliasSsl = db_settings_env('MYSQL_SSL');

    if ($aliasSsl === null) {
        $aliasSsl = db_settings_env('TIDB_SSL');
    }

    if ($aliasSsl !== null) {
        $alias['ssl'] = db_settings_bool_or_null($aliasSsl);
    }

    if (!empty($alias)) {
        $candidates[] = [
            'key'    => 'env_alias',
            'label'  => 'متغيرات البيئة البديلة MYSQL_* / TIDB_*',
            'values' => $alias,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* 4. ملف الإعدادات المحلي                                            */
    /* ------------------------------------------------------------------ */

    foreach (db_settings_file_paths() as $path) {
        $file = db_settings_read_file($path);

        if (!empty($file['values'])) {
            $candidates[] = [
                'key'    => 'file',
                'label'  => 'ملف الإعدادات ' . basename($path),
                'path'   => $path,
                'values' => $file['values'],
            ];

            break;
        }
    }

    return $candidates;
}

/**
 * تحليل DATABASE_URL بصيغة mysql://.
 *
 * @param  string $url
 * @return array<string, mixed>
 */
function db_settings_parse_url($url)
{
    $values = [];

    $url = trim($url);

    /* بعض المنصات تعطي mysql2:// أو بدون مخطط */
    $url = preg_replace('#^(mysql2?|mysql\+pdo|pdo-mysql)://#i', 'mysql://', $url);

    $parts = @parse_url($url);

    if ($parts === false) {
        return $values;
    }

    if (isset($parts['host']) && $parts['host'] !== '') {
        $values['host'] = $parts['host'];
    }

    if (isset($parts['port']) && $parts['port'] !== '') {
        $values['port'] = (string)$parts['port'];
    }

    if (isset($parts['path'])) {
        $name = ltrim($parts['path'], '/');

        if ($name !== '') {
            $values['name'] = $name;
        }
    }

    if (isset($parts['user']) && $parts['user'] !== '') {
        $values['user'] = rawurldecode($parts['user']);
    }

    if (isset($parts['pass'])) {
        $values['pass'] = rawurldecode($parts['pass']);
    }

    /* ?ssl-mode=REQUIRED أو ?ssl=true */
    if (!empty($parts['query'])) {
        $query = [];

        parse_str($parts['query'], $query);

        $ssl = $query['ssl-mode'] ?? $query['ssl'] ?? $query['sslMode'] ?? null;

        if ($ssl !== null) {
            $values['ssl'] = db_settings_bool_or_null($ssl);

            if ($values['ssl'] === null) {
                $values['ssl'] = true;
            }
        }
    }

    /* TiDB Cloud Serverless تعمل على 4000 وتفرض TLS */
    if (!isset($values['port']) && !empty($values['host']) && db_settings_is_tidb_host($values['host'])) {
        $values['port'] = '4000';
    }

    return $values;
}

/**
 * هل الاسم يشير إلى TiDB Cloud؟
 *
 * @param  string $host
 * @return bool
 */
function db_settings_is_tidb_host($host)
{
    return stripos((string)$host, 'tidbcloud') !== false
        || stripos((string)$host, 'tidb-cloud') !== false;
}

/**
 * قراءة ملف إعدادات محلي يعيد مصفوفة.
 *
 * @param  string $path
 * @return array{values: array<string, mixed>, error: string}
 */
function db_settings_read_file($path)
{
    $result = ['values' => [], 'error' => ''];

    if (!is_file($path) || !is_readable($path)) {
        return $result;
    }

    $data = @include $path;

    if (!is_array($data)) {
        $result['error'] = 'الملف موجود لكنه لا يعيد مصفوفة إعدادات صحيحة.';

        return $result;
    }

    $map = [
        'host'    => 'host',
        'port'    => 'port',
        'name'    => 'name',
        'user'    => 'user',
        'pass'    => 'pass',
        'socket'  => 'socket',
        'charset' => 'charset',
    ];

    foreach ($map as $key => $field) {
        if (!array_key_exists($key, $data)) {
            continue;
        }

        $value = $data[$key];

        if ($value === null) {
            continue;
        }

        $value = (string)$value;

        if ($value !== '' || $field === 'pass') {
            $result['values'][$field] = $value;
        }
    }

    if (array_key_exists('ssl', $data)) {
        $ssl = db_settings_bool_or_null($data['ssl']);

        if ($ssl !== null) {
            $result['values']['ssl'] = $ssl;
        }
    }

    /* أسماء بديلة مقبولة داخل الملف */
    if (!isset($result['values']['name']) && isset($data['database'])) {
        $result['values']['name'] = (string)$data['database'];
    }

    if (!isset($result['values']['user']) && isset($data['username'])) {
        $result['values']['user'] = (string)$data['username'];
    }

    if (!isset($result['values']['pass']) && array_key_exists('password', $data)) {
        $result['values']['pass'] = (string)$data['password'];
    }

    return $result;
}

/**
 * الإعدادات النهائية بعد دمج كل المصادر.
 *
 * المفاتيح الإضافية: source / source_label / origin / has_env / local_file / notes
 *
 * @return array<string, mixed>
 */
function db_settings_load()
{
    $defaults = db_settings_defaults();
    $candidates = db_settings_candidates();

    $resolved = [];
    $origin = [];

    foreach ($candidates as $candidate) {
        foreach ($candidate['values'] as $field => $value) {
            if (array_key_exists($field, $resolved)) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '' && $field !== 'pass') {
                continue;
            }

            $resolved[$field] = is_string($value) ? trim($value) : $value;
            $origin[$field] = $candidate['key'];
        }
    }

    $settings = array_merge($defaults, $resolved);

    $settings['port'] = (string)$settings['port'];
    $settings['ssl'] = (bool)$settings['ssl'];
    $settings['charset'] = $settings['charset'] ?: 'utf8mb4';

    /* TiDB Cloud: TLS مطلوب، والمنفذ الافتراضي 4000 — نطبّقهما تلقائياً
       إن لم يضبط المستخدم قيمة صريحة، حتى لا يفشل الاتصال بلا سبب واضح. */
    $notes = [];

    if (db_settings_is_tidb_host($settings['host'])) {

        if (!array_key_exists('ssl', $origin)) {
            $settings['ssl'] = true;
            $notes[] = 'تم تفعيل TLS تلقائياً (TiDB Cloud يرفض الاتصالات غير المشفّرة) — يمكن تعطيله بـ DB_SSL=0.';
        }

        if (!array_key_exists('port', $origin) && $settings['port'] === '3306') {
            $settings['port'] = '4000';
            $notes[] = 'تم استخدام المنفذ 4000 تلقائياً (المنفذ الافتراضي في TiDB Cloud Serverless).';
        }
    }

    /* Socket محلي: لا نحتاج host/port */
    if (!empty($settings['socket'])) {
        $notes[] = 'الاتصال يستخدم Socket محلي: ' . $settings['socket'];
    }

    $settings['notes'] = $notes;

    $source = 'default';
    $sourceLabel = 'القيم الافتراضية (لم تُضبط أي إعدادات)';
    $sourcePath = '';

    foreach ($candidates as $candidate) {
        if (in_array($candidate['key'], $origin, true)) {
            $source = $candidate['key'];
            $sourceLabel = $candidate['label'];
            $sourcePath = $candidate['path'] ?? '';
            break;
        }
    }

    $settings['source'] = $source;
    $settings['source_label'] = $sourceLabel;
    $settings['source_path'] = $sourcePath;
    $settings['origin'] = $origin;
    $settings['has_env'] = $source === 'env' || $source === 'env_alias' || $source === 'database_url';
    $settings['local_file'] = '';

    foreach (db_settings_file_paths() as $path) {
        if (is_file($path)) {
            $settings['local_file'] = $path;
            break;
        }
    }

    return $settings;
}

/**
 * وصف قصير لمصدر كل حقل (للعرض في صفحة التشخيص).
 *
 * @param  array<string, mixed> $settings
 * @return string
 */
function db_settings_origin_label(array $settings)
{
    $labels = [
        'env'          => 'متغيرات البيئة DB_*',
        'database_url' => 'DATABASE_URL',
        'env_alias'    => 'متغيرات البيئة MYSQL_* / TIDB_*',
        'file'         => 'ملف الإعدادات المحلي',
    ];

    $origin = $settings['origin'] ?? [];

    /* المصدر الأقوى الذي ساهم فعلاً */
    $order = ['env', 'database_url', 'env_alias', 'file'];

    foreach ($order as $key) {
        if (in_array($key, $origin, true)) {
            $fields = [];

            foreach ($origin as $field => $source) {
                if ($source === $key) {
                    $fields[] = $field;
                }
            }

            return $labels[$key] . ' (' . implode(', ', $fields) . ')';
        }
    }

    return (string)($settings['source_label'] ?? '');
}

/**
 * إخفاء كلمة المرور عند العرض.
 *
 * @param  string $value
 * @return string
 */
function db_settings_mask($value)
{
    $value = (string)$value;

    if ($value === '') {
        return '(فارغة)';
    }

    $length = strlen($value);

    return str_repeat('•', min(8, max(3, $length))) . ' (' . $length . ' حرف)';
}

/* ============================================================================
 * 3) الكتابة في ملف الإعدادات المحلي
 * ========================================================================== */

/**
 * حفظ الإعدادات في config.local.php (كتابة ذرية + صلاحيات مقيدة).
 *
 * @param  array<string, mixed> $settings
 * @param  string|null          $path
 * @return array{ok: bool, message: string, path: string}
 */
function db_settings_write(array $settings, $path = null)
{
    $path = $path ?: db_settings_file_path();

    $data = [
        'host'     => (string)($settings['host'] ?? ''),
        'port'     => (string)($settings['port'] ?? ''),
        'name'     => (string)($settings['name'] ?? ''),
        'user'     => (string)($settings['user'] ?? ''),
        'pass'     => (string)($settings['pass'] ?? ''),
        'ssl'      => (bool)($settings['ssl'] ?? false),
        'charset'  => (string)($settings['charset'] ?? 'utf8mb4'),
    ];

    if (!empty($settings['socket'])) {
        $data['socket'] = (string)$settings['socket'];
    }

    $lines = [];

    foreach ($data as $key => $value) {
        $lines[] = "    '" . $key . "' => " . var_export($value, true) . ',';
    }

    $content = "<?php\n"
        . "/**\n"
        . " * إعدادات قاعدة البيانات — أُنشئ تلقائياً بواسطة install.php\n"
        . " * التاريخ: " . date('Y-m-d H:i:s') . "\n"
        . " *\n"
        . " * ⚠️ لا ترفع هذا الملف إلى Git (مُستثنى في .gitignore).\n"
        . " * ⚠️ متغيرات البيئة DB_* تتقدم على هذا الملف عند التشغيل.\n"
        . " */\n\n"
        . "return [\n"
        . implode("\n", $lines) . "\n"
        . "];\n";

    $dir = dirname($path);

    if (!is_dir($dir) || !is_writable($dir)) {
        return [
            'ok'      => false,
            'path'    => $path,
            'message' => 'المجلد ' . $dir . ' غير قابل للكتابة، لذلك تعذّر حفظ الملف.',
        ];
    }

    $tmp = @tempnam($dir, 'dbconf');

    if ($tmp === false) {
        return [
            'ok'      => false,
            'path'    => $path,
            'message' => 'تعذّر إنشاء ملف مؤقت لحفظ الإعدادات.',
        ];
    }

    if (@file_put_contents($tmp, $content, LOCK_EX) === false) {
        @unlink($tmp);

        return [
            'ok'      => false,
            'path'    => $path,
            'message' => 'تعذّر كتابة ملف الإعدادات.',
        ];
    }

    @chmod($tmp, 0600);

    if (!@rename($tmp, $path)) {
        @unlink($tmp);

        return [
            'ok'      => false,
            'path'    => $path,
            'message' => 'تعذّر استبدال ملف الإعدادات الحالي.',
        ];
    }

    @chmod($path, 0600);

    /* تحقق من أن الملف يُقرأ ويعيد نفس القيم */
    $check = db_settings_read_file($path);

    if (($check['values']['host'] ?? null) !== $data['host']) {
        return [
            'ok'      => false,
            'path'    => $path,
            'message' => 'تم حفظ الملف لكن لم يتم التحقق من قراءته بشكل صحيح.',
        ];
    }

    return [
        'ok'      => true,
        'path'    => $path,
        'message' => 'تم حفظ الإعدادات في ' . $path,
    ];
}

/**
 * حذف ملف الإعدادات المحلي.
 *
 * @param  string|null $path
 * @return array{ok: bool, message: string}
 */
function db_settings_delete($path = null)
{
    $path = $path ?: db_settings_file_path();

    if (!is_file($path)) {
        return ['ok' => false, 'message' => 'لا يوجد ملف إعدادات محلي لحذفه.'];
    }

    if (!@unlink($path)) {
        return ['ok' => false, 'message' => 'تعذّر حذف الملف: ' . $path];
    }

    return ['ok' => true, 'message' => 'تم حذف ملف الإعدادات المحلي.'];
}

/* ============================================================================
 * 4) الاتصال بقاعدة البيانات
 * ========================================================================== */

/**
 * بناء DSN.
 *
 * @param  array<string, mixed> $settings
 * @param  bool                 $withDatabase
 * @return string
 */
function db_dsn(array $settings, $withDatabase = true)
{
    $parts = [];

    if (!empty($settings['socket'])) {
        $parts[] = 'unix_socket=' . $settings['socket'];
    } else {
        $parts[] = 'host=' . $settings['host'];
        $parts[] = 'port=' . ($settings['port'] !== '' ? $settings['port'] : '3306');
    }

    if ($withDatabase) {
        $parts[] = 'dbname=' . $settings['name'];
    }

    $parts[] = 'charset=' . ($settings['charset'] ?: 'utf8mb4');

    return 'mysql:' . implode(';', $parts);
}

/**
 * خيارات PDO (تتضمن TLS عند الحاجة).
 *
 * @param  array<string, mixed> $settings
 * @param  int                  $timeout
 * @return array<int, mixed>
 */
function db_pdo_options(array $settings, $timeout = 5)
{
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
        PDO::ATTR_TIMEOUT            => (int)$timeout,
    ];

    if (!empty($settings['ssl'])) {
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }

        if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
            $ca = db_settings_ca_bundle();

            if ($ca !== '') {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            }
        }
    }

    return $options;
}

/**
 * أول ملف CA متوفر في النظام (لا نضمّن شهادات داخل المشروع).
 *
 * @return string
 */
function db_settings_ca_bundle()
{
    $paths = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/ssl/cert.pem',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
        '/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem',
        '/usr/local/share/certs/ca-root-nss.crt',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return '';
}

/* ============================================================================
 * 5) فحوصات الشبكة والتشخيص
 * ========================================================================== */

/**
 * هل العنوان محلي (يعمل فقط على جهازك)؟
 *
 * @param  string $host
 * @return bool
 */
function db_settings_is_local_host($host)
{
    $host = strtolower(trim((string)$host, " \t\n\r\0\x0B[]"));

    if ($host === '' || $host === 'localhost' || $host === '::1') {
        return true;
    }

    if (strpos($host, '127.') === 0) {
        return true;
    }

    if (strpos($host, '192.168.') === 0 || strpos($host, '10.') === 0) {
        return true;
    }

    if (preg_match('#^172\.(1[6-9]|2[0-9]|3[01])\.#', $host)) {
        return true;
    }

    return in_array($host, ['mysql', 'db', 'database', 'mariadb'], true);
}

/**
 * فحص الاتصال بالمنفذ (TCP) قبل محاولة PDO، لتمييز مشاكل الشبكة عن مشاكل البيانات.
 *
 * @param  string $host
 * @param  string $port
 * @param  int    $timeout
 * @return array<string, mixed>
 */
function db_tcp_probe($host, $port, $timeout = 3)
{
    $result = [
        'ok'       => false,
        'dns'      => null,
        'ip'       => '',
        'error'    => '',
        'errno'    => 0,
        'latency'  => null,
        'skipped'  => false,
    ];

    $host = trim((string)$host);
    $port = (int)$port;

    if ($host === '' || $port <= 0) {
        $result['skipped'] = true;
        $result['error'] = 'عنوان أو منفذ غير صالح.';

        return $result;
    }

    /* فحص تحليل الاسم (DNS) */
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $result['dns'] = true;
        $result['ip'] = $host;
    } else {
        $ip = @gethostbyname($host);

        $resolved = ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP));

        $result['dns'] = $resolved;
        $result['ip'] = $resolved ? $ip : '';

        if (!$resolved) {
            $result['error'] = 'تعذّر تحليل اسم الخادم (DNS): ' . $host;

            return $result;
        }
    }

    /* IPv6 يحتاج أقواس في fsockopen */
    $target = $host;

    if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $target = '[' . $host . ']';
    }

    $errno = 0;
    $errstr = '';
    $start = microtime(true);

    $handle = @fsockopen($target, $port, $errno, $errstr, $timeout);

    $result['latency'] = round((microtime(true) - $start) * 1000);

    if (is_resource($handle)) {
        $result['ok'] = true;
        @fclose($handle);

        return $result;
    }

    $result['errno'] = $errno;
    $result['error'] = trim($errstr) !== '' ? trim($errstr) : 'فشل الاتصال بالمنفذ ' . $port;

    return $result;
}

/**
 * فحص وجود Socket محلي لخادم MySQL (XAMPP / MAMP / حزم Linux).
 *
 * @return string[] المسارات الموجودة فعلاً
 */
function db_settings_socket_candidates()
{
    $paths = [
        '/var/run/mysqld/mysqld.sock',
        '/var/lib/mysql/mysql.sock',
        '/run/mysqld/mysqld.sock',
        '/tmp/mysql.sock',
        '/Applications/MAMP/tmp/mysql/mysql.sock',
        '/opt/lampp/var/mysql/mysql.sock',
        '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock',
        '/usr/local/var/mysql/mysql.sock',
    ];

    $extra = db_settings_env('DB_SOCKET');

    if ($extra !== null && trim($extra) !== '') {
        array_unshift($paths, trim($extra));
    }

    $found = [];

    foreach ($paths as $path) {
        if (!in_array($path, $found, true) && file_exists($path)) {
            $found[] = $path;
        }
    }

    return $found;
}

/**
 * فحص إمكانية الاتصال بـ Socket.
 *
 * @param  string $path
 * @param  int    $timeout
 * @return array{ok: bool, error: string}
 */
function db_socket_probe($path, $timeout = 3)
{
    $result = ['ok' => false, 'error' => ''];

    if (!file_exists($path)) {
        $result['error'] = 'الملف غير موجود: ' . $path;

        return $result;
    }

    $errno = 0;
    $errstr = '';

    $handle = @stream_socket_client(
        'unix://' . $path,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );

    if (is_resource($handle)) {
        $result['ok'] = true;
        @fclose($handle);

        return $result;
    }

    $result['error'] = trim($errstr) !== '' ? trim($errstr) : 'تعذّر الاتصال بالـ Socket.';

    return $result;
}

/**
 * تشخيص رسالة خطأ PDO وتحويلها إلى خطوات عملية.
 *
 * @param  string               $message
 * @param  array<string, mixed> $settings
 * @return array<string, mixed>
 */
function db_error_diagnosis($message, array $settings)
{
    $message = (string)$message;
    $lower = strtolower($message);

    $host = (string)($settings['host'] ?? '');
    $port = (string)($settings['port'] ?? '');
    $target = $host . ':' . $port;

    $result = [
        'code'     => '',
        'title'    => 'تعذّر الاتصال بقاعدة البيانات',
        'cause'    => '',
        'hints'    => [],
        'severity' => 'error',
    ];

    if (preg_match('/SQLSTATE\[([A-Za-z0-9]+)\]\s*\[?([0-9]{3,5})?\]?/', $message, $m)) {
        $result['code'] = $m[2] !== '' ? $m[2] : $m[1];
    }

    $code = $result['code'];

    /* ---------------------------------------------------------------- */
    /* 2002 — تعذّر الاتصال بالخادم                                       */
    /* ---------------------------------------------------------------- */

    if ($code === '2002' || strpos($lower, 'connection refused') !== false) {

        if (strpos($lower, 'no such file or directory') !== false || strpos($lower, 'unix_socket') !== false) {
            $result['title'] = 'خادم MySQL غير متاح عبر Socket المحلي';
            $result['cause'] = 'الاتصال يستخدم Socket محلي غير موجود — غالباً خادم MySQL غير مُشغَّل.';
            $result['hints'] = [
                'شغّل خادم MySQL (XAMPP: زر Start بجانب MySQL — WAMP: أيقونة MySQL).',
                'إذا كنت تستخدم استضافة سحابية، لا تستخدم Socket محلي: استعمل DB_HOST و DB_PORT.',
                'حدّد المسار الصحيح للملف عبر المتغير DB_SOCKET إن كان خادمك يستخدم مساراً مختلفاً.',
            ];

            return $result;
        }

        if (strpos($lower, 'getaddrinfo') !== false || strpos($lower, 'php_network_getaddresses') !== false) {
            $result['title'] = 'تعذّر تحليل اسم خادم قاعدة البيانات (DNS)';
            $result['cause'] = 'اسم الخادم «' . $host . '» غير قابل للتحليل من هذا الخادم.';
            $result['hints'] = [
                'تحقق من كتابة DB_HOST بدون مسافات وبدون http:// أو / في النهاية.',
                'استخدم العنوان الكامل الذي تنسخه من لوحة مزوّد قاعدة البيانات.',
                'إذا كان الخادم على شبكة خاصة (Private Endpoint)، تأكد من وجود اتصال شبكي بين الخدمتين.',
            ];

            return $result;
        }

        if (strpos($lower, 'timed out') !== false || strpos($lower, 'timeout') !== false) {
            $result['title'] = 'انتهت مهلة الاتصال بـ ' . $target;
            $result['cause'] = 'المنفذ لا يستجيب: إما الجدار الناري يحجب الاتصال أو الخادم غير متاح من هذه الشبكة.';
            $result['hints'] = [
                'تحقق من أن المنفذ صحيح: MySQL/MariaDB عادة 3306، TiDB Cloud Serverless على 4000.',
                'في استضافات MySQL المدارة، أضف عنوان IP الخاص بالخادم إلى قائمة السماح (IP Allowlist / Remote MySQL).',
                'لا تحاول الاتصال بعناوين 127.0.0.1 أو 192.168.x من استضافة سحابية، فهي تشير إلى الخادم نفسه لا إلى قاعدة بياناتك.',
            ];

            return $result;
        }

        $result['title'] = 'لا يوجد خادم قاعدة بيانات يستمع على ' . $target;
        $result['cause'] = 'تم رفض الاتصال (Connection refused) — لا شيء يعمل على هذا العنوان والمنفذ.';

        if (db_settings_is_local_host($host)) {
            $result['hints'][] = 'العنوان ' . $host . ' يشير إلى نفس الجهاز/الشبكة المحلية: هذا يعمل فقط إذا كان MySQL مُثبَّتاً على نفس الخادم الذي يشغّل الموقع.';
        }

        $result['hints'] = array_merge($result['hints'], [
            'تأكد من تشغيل خادم MySQL/MariaDB على نفس الجهاز (أو استخدم قاعدة بيانات خارجية مثل TiDB Cloud).',
            'إذا كانت القيمة الافتراضية 127.0.0.1:3306 هي المستخدمة، فهذا يعني أن متغيرات البيئة لم تُضبط بعد — اضبط DB_HOST و DB_PORT.',
            'على Vercel أو أي استضافة Serverless: لا يمكن الاتصال بـ localhost إطلاقاً، يجب إنشاء قاعدة بيانات خارجية وضبط متغيرات البيئة.',
            'تحقق من المنفذ: 3306 لـ MySQL/MariaDB، و4000 لـ TiDB Cloud Serverless.',
        ]);

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* 1045 / 1044 — مشكلة صلاحيات                                        */
    /* ---------------------------------------------------------------- */

    if ($code === '1045' || strpos($lower, 'access denied for user') !== false) {
        $result['title'] = 'بيانات الدخول غير صحيحة (Access denied)';
        $result['cause'] = 'اسم المستخدم أو كلمة المرور مرفوضان من خادم قاعدة البيانات.';
        $result['hints'] = [
            'راجع DB_USER و DB_PASS. على TiDB Cloud Serverless يكون اسم المستخدم بالشكل: <code>3Kx3xk2fFqY6LX7.root</code> — لا تحذف الجزء قبل النقطة.',
            'إذا كانت كلمة المرور تحتوي رموزاً خاصة (@ # % : /)، استخدمها مباشرة في DB_PASS، أو رمّزها (URL-encode) إن استعملت DATABASE_URL.',
            'تأكد أن المستخدم يملك صلاحية على قاعدة البيانات المحددة في DB_NAME.',
            'على الاستضافة المشتركة: أنشئ المستخدم من cPanel واربطه بقاعدة البيانات.',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* 1049 — قاعدة بيانات غير موجودة                                      */
    /* ---------------------------------------------------------------- */

    if ($code === '1049' || strpos($lower, 'unknown database') !== false) {
        $result['title'] = 'قاعدة البيانات غير موجودة';
        $result['cause'] = 'قاعدة البيانات «' . ($settings['name'] ?? '') . '» غير موجودة على الخادم.';
        $result['hints'] = [
            'اترك اسم قاعدة البيانات صحيحاً حرفياً (حساس لحالة الأحرف على Linux).',
            'سيحاول المعالج إنشاء القاعدة تلقائياً إن كانت الصلاحيات تسمح بذلك.',
            'على الاستضافة المشتركة: أنشئ قاعدة البيانات من cPanel ثم استخدم اسمها الكامل مثل <code>user_car_rental</code>.',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* TLS / SSL                                                          */
    /* ---------------------------------------------------------------- */

    if (
        strpos($lower, 'ssl') !== false
        || strpos($lower, 'tls') !== false
        || strpos($lower, 'insecure transport') !== false
        || strpos($lower, 'secure transport') !== false
        || strpos($lower, 'require_secure_transport') !== false
        || in_array($code, ['2026', '3159'], true)
    ) {
        $result['title'] = 'مشكلة في تشفير الاتصال (TLS/SSL)';
        $result['cause'] = 'الخادم يفرض اتصالاً مشفراً أو أن إعدادات TLS غير مقبولة.';
        $result['hints'] = [
            'أضف المتغير <code>DB_SSL=1</code> لتفعيل TLS (مطلوب في TiDB Cloud Serverless).',
            'إذا كنت تستخدم TiDB Cloud، تأكد من المنفذ 4000 مع DB_SSL=1.',
            'إن ظهر خطأ شهادة، تأكد من وجود ملف CA bundle في النظام (/etc/ssl/certs/ca-certificates.crt).',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* 1105 — أخطاء عامة من TiDB                                          */
    /* ---------------------------------------------------------------- */

    if ($code === '1105') {
        $result['title'] = 'رفض الخادم الطلب (TiDB / MySQL)';
        $result['cause'] = 'رسالة عامة من الخادم، وغالبها سببها TLS أو حدود الحصة.';
        $result['hints'] = [
            'أضف <code>DB_SSL=1</code> — TiDB Cloud Serverless يرفض الاتصالات غير المشفّرة.',
            'تأكد من عدم تجاوز حصة الخادم (الطلبات/التخزين) من لوحة TiDB Cloud.',
            'تحقق من المنفذ 4000 واسم المستخدم الكامل (مثال: <code>3Kx3xk2fFqY6LX7.root</code>).',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* 1130 — الخادم غير مسموح له بالاتصال                                 */
    /* ---------------------------------------------------------------- */

    if ($code === '1130' || strpos($lower, 'host is not allowed') !== false) {
        $result['title'] = 'الخادم الحالي غير مسموح له بالاتصال بقاعدة البيانات';
        $result['cause'] = 'خادم الويب ليس ضمن قائمة العناوين المسموح لها.';
        $result['hints'] = [
            'في cPanel: Remote MySQL → أضف عنوان IP الخاص بخادم الموقع.',
            'في TiDB Cloud: تحقق من Network Access / Private Endpoint.',
            'في MySQL المحلي: تأكد أن المستخدم يملك صلاحية الاتصال من % وليس من localhost فقط.',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* driver مفقود                                                      */
    /* ---------------------------------------------------------------- */

    if (strpos($lower, 'could not find driver') !== false) {
        $result['title'] = 'إضافة pdo_mysql غير مُفعَّلة';
        $result['cause'] = 'PHP لا يملك تعريف PDO الخاص بـ MySQL.';
        $result['hints'] = [
            'في cPanel: MultiPHP INI Editor أو Select PHP Version → فعّل pdo_mysql و mysqli.',
            'محلياً: أزل الفاصلة المنقوطة قبل extension=pdo_mysql في php.ini.',
            'على Vercel: التأكد من أن runtime يدعم pdo_mysql (vercel-php@0.9.0 يدعمه).',
        ];

        return $result;
    }

    /* ---------------------------------------------------------------- */
    /* احتياطي                                                            */
    /* ---------------------------------------------------------------- */

    $result['cause'] = 'رسالة الخادم: ' . $message;
    $result['hints'] = [
        'راجع عنوان الخادم والمنفذ واسم قاعدة البيانات وبيانات الدخول.',
        'راجع قسم «استكشاف الأخطاء» في README.md للحصول على قائمة الحلول.',
    ];

    return $result;
}

/* ============================================================================
 * 6) بيئة التشغيل
 * ========================================================================== */

/**
 * معلومات عن بيئة التشغيل الحالية (تدعم نصائح مخصصة).
 *
 * @return array<string, mixed>
 */
function db_platform_info()
{
    $root = dirname(__DIR__);

    $vercelEnv = db_settings_env('VERCEL_ENV');
    $vercel = db_settings_env('VERCEL') !== null
        || $vercelEnv !== null
        || db_settings_env('NOW_REGION') !== null
        || db_settings_env('VERCEL_URL') !== null;

    $lambda = db_settings_env('AWS_LAMBDA_FUNCTION_NAME') !== null;
    $azure = db_settings_env('FUNCTIONS_WORKER_RUNTIME') !== null;

    $serverless = $vercel || $lambda || $azure;

    $name = 'خادم PHP تقليدي (Apache/Nginx)';

    if ($vercel) {
        $name = 'Vercel (استضافة Serverless)';
    } elseif ($lambda) {
        $name = 'AWS Lambda (Serverless)';
    } elseif ($azure) {
        $name = 'Azure Functions (Serverless)';
    }

    return [
        'name'        => $name,
        'serverless'  => $serverless,
        'vercel'      => $vercel,
        'region'      => (string)(db_settings_env('VERCEL_REGION') ?? db_settings_env('AWS_REGION') ?? ''),
        'sapi'        => PHP_SAPI,
        'php'         => PHP_VERSION,
        'root'        => $root,
        'root_writable' => is_writable($root),
        'tmp'         => sys_get_temp_dir(),
        'tmp_writable' => is_writable(sys_get_temp_dir()),
        'os'          => PHP_OS . ' ' . php_uname('m'),
    ];
}

/**
 * إضافات PHP المطلوبة وحالتها.
 *
 * @return array<string, array{ok: bool, required: bool, note: string}>
 */
function db_required_extensions()
{
    return [
        'PDO' => [
            'ok'       => extension_loaded('PDO'),
            'required' => true,
            'note'     => 'الطبقة الأساسية للاتصال بقواعد البيانات',
        ],
        'pdo_mysql' => [
            'ok'       => extension_loaded('pdo_mysql'),
            'required' => true,
            'note'     => 'تعريف MySQL — بدونه لا يمكن الاتصال إطلاقاً',
        ],
        'mbstring' => [
            'ok'       => extension_loaded('mbstring'),
            'required' => true,
            'note'     => 'معالجة النصوص العربية',
        ],
        'fileinfo' => [
            'ok'       => extension_loaded('fileinfo'),
            'required' => true,
            'note'     => 'التحقق من أنواع ملفات الرفع',
        ],
        'openssl' => [
            'ok'       => extension_loaded('openssl'),
            'required' => false,
            'note'     => 'مطلوب لاتصالات TLS مع TiDB Cloud',
        ],
        'json' => [
            'ok'       => extension_loaded('json'),
            'required' => false,
            'note'     => 'واجهات JSON في api/',
        ],
    ];
}

/**
 * عنوان المنصة وملاحظة سريعة عنها.
 *
 * @param  array<string, mixed> $platform
 * @return string[]
 */
function db_platform_notes(array $platform)
{
    if ($platform['serverless']) {
        return [
            'هذه بيئة Serverless: الملفات المؤقتة لا تُحفظ بين الطلبات، لذلك الإعداد الدائم يجب أن يكون عبر متغيرات البيئة.',
            'لا يمكن الاتصال بـ 127.0.0.1 أو localhost هنا — استخدم قاعدة بيانات خارجية (مثل TiDB Cloud Serverless).',
            'بعد إضافة/تعديل متغيرات البيئة أعد النشر (Redeploy) ليعمل التغيير.',
        ];
    }

    return [
        'هذه استضافة PHP تقليدية: يمكن حفظ الإعدادات في ملف config.local.php داخل المشروع.',
        'إذا كان MySQL على نفس الخادم فاستخدم 127.0.0.1 (أسرع من localhost لأنه يتجنب Socket غير مُهيَّأ).',
    ];
}

/**
 * أسماء متغيرات البيئة ذات الصلة الموجودة فعلاً (لعرضها في التشخيص).
 *
 * @return array<string, string> اسم المتغير => القيمة مخفية جزئياً
 */
function db_env_inventory()
{
    $names = [
        'DATABASE_URL', 'MYSQL_URL', 'TIDB_DATABASE_URL',
        'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_SSL', 'DB_SOCKET', 'DB_SESSIONS',
        'MYSQL_HOST', 'MYSQL_PORT', 'MYSQL_DATABASE', 'MYSQL_USER',
        'TIDB_HOST', 'TIDB_PORT', 'TIDB_USER', 'TIDB_DATABASE',
        'VERCEL', 'VERCEL_ENV', 'VERCEL_REGION', 'VERCEL_URL',
    ];

    $found = [];

    foreach ($names as $name) {
        $value = db_settings_env($name);

        if ($value === null) {
            continue;
        }

        $secret = (stripos($name, 'PASS') !== false || stripos($name, 'URL') !== false);

        if ($value === '') {
            $found[$name] = '(فارغ)';
        } elseif ($secret) {
            $found[$name] = '(مضبوط — ' . strlen($value) . ' حرف)';
        } else {
            $found[$name] = strlen($value) > 60 ? substr($value, 0, 57) . '…' : $value;
        }
    }

    return $found;
}

/* ============================================================================
 * 7) تنفيذ ملف SQL
 * ========================================================================== */

/**
 * تقسيم ملف SQL إلى جمل صحيحة (يتعامل مع النصوص والتعليقات والفاصلة المنقوطة داخلها).
 *
 * @param  string $sql
 * @return string[]
 */
function db_split_sql($sql)
{
    $sql = (string)$sql;

    /* إزالة BOM */
    if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
        $sql = substr($sql, 3);
    }

    $statements = [];
    $buffer = '';
    $length = strlen($sql);

    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inLineComment = false;
    $inBlockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        /* داخل تعليق سطر */
        if ($inLineComment) {
            if ($char === "\n") {
                $inLineComment = false;
                $buffer .= "\n";
            }

            continue;
        }

        /* داخل تعليق كتلة */
        if ($inBlockComment) {
            if ($char === '*' && $next === '/') {
                $inBlockComment = false;
                $i++;
            }

            continue;
        }

        /* داخل نص */
        if ($inSingle || $inDouble || $inBacktick) {
            $buffer .= $char;

            $quote = $inSingle ? "'" : ($inDouble ? '"' : '`');

            /* الشرطة المائلة العكسية تهرّب الحرف التالي (ليس في الأسماء المُقتبسة) */
            if ($char === '\\' && !$inBacktick) {
                if ($next !== '') {
                    $buffer .= $next;
                    $i++;
                }

                continue;
            }

            if ($char === $quote) {
                /* '' داخل نص تعني علامة مهرّبة */
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;

                    continue;
                }

                if ($inSingle) {
                    $inSingle = false;
                } elseif ($inDouble) {
                    $inDouble = false;
                } else {
                    $inBacktick = false;
                }
            }

            continue;
        }

        /* بداية تعليق سطر: -- متبوعة بمسافة أو نهاية سطر */
        if ($char === '-' && $next === '-') {
            $after = ($i + 2 < $length) ? $sql[$i + 2] : "\n";

            if ($after === ' ' || $after === "\t" || $after === "\n" || $after === "\r" || $after === '') {
                $inLineComment = true;
                $i++;

                continue;
            }
        }

        if ($char === '#') {
            $inLineComment = true;

            continue;
        }

        if ($char === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;

            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $inSingle = ($char === "'");
            $inDouble = ($char === '"');
            $inBacktick = ($char === '`');
            $buffer .= $char;

            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            $buffer = '';

            if ($statement !== '') {
                $statements[] = $statement;
            }

            continue;
        }

        $buffer .= $char;
    }

    $tail = trim($buffer);

    if ($tail !== '') {
        $statements[] = $tail;
    }

    return $statements;
}

/**
 * أخطاء MySQL "غير خطيرة" عند إعادة التنفيذ (المُخطط موجود مسبقاً).
 *
 * @param  string $message
 * @return bool
 */
function db_error_is_benign($message)
{
    $benign = [1007, 1050, 1060, 1061, 1091, 1826, 1062, 1216, 1217, 1396];

    if (preg_match('/\[(\d{3,4})\]/', (string)$message, $m)) {
        if (in_array((int)$m[1], $benign, true)) {
            return true;
        }
    }

    $phrases = [
        'already exists',
        'duplicate column',
        'duplicate key name',
        'duplicate entry',
        'check that column/key exists',
        "can't drop",
        'cannot drop',
        'operation not allowed',
    ];

    $lower = strtolower((string)$message);

    foreach ($phrases as $phrase) {
        if (strpos($lower, $phrase) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * تنفيذ قائمة جمل SQL على اتصال قائم.
 *
 * @param  PDO      $pdo
 * @param  string[] $statements
 * @return array{done: int, skipped: int, ignored: string[], errors: string[]}
 */
function db_execute_statements($pdo, array $statements)
{
    $report = [
        'done'    => 0,
        'skipped' => 0,
        'ignored' => [],
        'errors'  => [],
    ];

    foreach ($statements as $statement) {

        /* لا نعيد إنشاء القاعدة ولا نبدّل بينها عبر USE */
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE\s+|DROP\s+DATABASE)/i', $statement)) {
            $report['skipped']++;

            continue;
        }

        try {
            $pdo->exec($statement);
            $report['done']++;
        } catch (PDOException $e) {
            if (db_error_is_benign($e->getMessage())) {
                $report['ignored'][] = $e->getMessage();

                continue;
            }

            $report['errors'][] = db_statement_label($statement) . ' → ' . $e->getMessage();
        } catch (Throwable $e) {
            $report['errors'][] = db_statement_label($statement) . ' → ' . $e->getMessage();
        }
    }

    return $report;
}

/**
 * وصف مختصر لجملة SQL (لاستخدامه في التقارير).
 *
 * @param  string $statement
 * @return string
 */
function db_statement_label($statement)
{
    $flat = preg_replace('/\s+/', ' ', trim($statement));

    return mb_substr($flat, 0, 90, 'UTF-8') . (mb_strlen($flat, 'UTF-8') > 90 ? '…' : '');
}

/**
 * الجداول المتوقعة في النظام.
 *
 * @return string[]
 */
function db_expected_tables()
{
    return [
        'users', 'cars', 'car_images', 'locations', 'extras', 'bookings',
        'payments', 'invoices', 'reviews', 'favorites', 'coupons', 'settings',
        'sessions', 'notifications', 'activity_logs', 'login_attempts',
        'password_resets', 'remember_tokens',
    ];
}

/**
 * الجداول الموجودة فعلاً على القاعدة.
 *
 * @param  PDO $pdo
 * @return string[]
 */
function db_existing_tables($pdo)
{
    $tables = [];

    try {
        $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);

        foreach ($rows as $row) {
            if (isset($row[0])) {
                $tables[] = (string)$row[0];
            }
        }
    } catch (Throwable $e) {
        /* نتجاهل: سيظهر الخطأ في خطوة أخرى */
    }

    return $tables;
}
