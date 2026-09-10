<?php
/**
 * ============================================================================
 * Premium Car Rental - Installation Wizard
 * FINAL VERSION
 * ============================================================================
 *
 * الوظائف:
 * 1. فحص إصدار PHP
 * 2. قراءة إعدادات قاعدة البيانات من Environment Variables
 * 3. دعم DATABASE_URL
 * 4. دعم DB_HOST / DB_PORT
 * 5. دعم TiDB Cloud SSL/TLS
 * 6. إنشاء قاعدة البيانات عند توفر الصلاحية
 * 7. تنفيذ database.sql
 * 8. إنشاء حساب المدير
 * 9. إنشاء مجلدات النظام
 * 10. فحص جدول السيارات
 *
 * ⚠️ احذف install.php بعد انتهاء التثبيت في الإنتاج.
 * ============================================================================
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

date_default_timezone_set('Africa/Casablanca');


// ============================================================================
// ENVIRONMENT / DATABASE URL
// ============================================================================

$_dbUrl = getenv('DATABASE_URL');

if ($_dbUrl) {

    $_dbParts = parse_url($_dbUrl);

    if ($_dbParts && isset($_dbParts['host'])) {

        putenv(
            'DB_HOST=' .
            $_dbParts['host']
        );

        if (isset($_dbParts['port'])) {

            putenv(
                'DB_PORT=' .
                $_dbParts['port']
            );
        }

        if (isset($_dbParts['path'])) {

            putenv(
                'DB_NAME=' .
                ltrim(
                    $_dbParts['path'],
                    '/'
                )
            );
        }

        if (isset($_dbParts['user'])) {

            putenv(
                'DB_USER=' .
                urldecode(
                    $_dbParts['user']
                )
            );
        }

        if (isset($_dbParts['pass'])) {

            putenv(
                'DB_PASS=' .
                urldecode(
                    $_dbParts['pass']
                )
            );
        }
    }
}


// ============================================================================
// DATABASE VARIABLES
// ============================================================================

$DB_HOST =
    getenv('DB_HOST')
    ?: '127.0.0.1';

$DB_PORT =
    getenv('DB_PORT')
    ?: '3306';

$DB_NAME =
    getenv('DB_NAME')
    ?: 'car_rental_db';

$DB_USER =
    getenv('DB_USER')
    ?: 'root';

$DB_PASS =
    getenv('DB_PASS') !== false
        ? getenv('DB_PASS')
        : '';

$DB_SSL =
    getenv('DB_SSL') === '1';


// ============================================================================
// INSTALLATION STATE
// ============================================================================

$steps = [];

$failed = false;

$pdoRoot = null;

$pdo = null;

$adminCreated = false;

$adminPassword = 'Admin@123';

$carsCount = 0;


// ============================================================================
// STEP HELPER
// ============================================================================

function step(
    &$steps,
    $ok,
    $label,
    $detail = ''
) {
    $steps[] = [
        'ok' => (bool)$ok,
        'label' => $label,
        'detail' => $detail
    ];
}


// ============================================================================
// SAFE DATABASE IDENTIFIER
// ============================================================================

function safe_identifier($value)
{
    return preg_replace(
        '/[^a-zA-Z0-9_\-]/',
        '',
        (string)$value
    );
}


// ============================================================================
// PDO OPTIONS
// ============================================================================

$pdoOpts = [

    PDO::ATTR_ERRMODE =>
        PDO::ERRMODE_EXCEPTION,

    PDO::ATTR_DEFAULT_FETCH_MODE =>
        PDO::FETCH_ASSOC,

    PDO::ATTR_EMULATE_PREPARES =>
        false
];


// ============================================================================
// TiDB / MYSQL SSL
// ============================================================================

if ($DB_SSL) {

    if (
        defined(
            'PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'
        )
    ) {

        $pdoOpts[
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT
        ] = false;
    }


    $caPaths = [

        '/etc/ssl/certs/ca-certificates.crt',

        '/etc/ssl/cert.pem',

        '/etc/pki/tls/certs/ca-bundle.crt',

        '/etc/ssl/ca-bundle.pem'
    ];


    foreach ($caPaths as $caPath) {

        if (file_exists($caPath)) {

            if (
                defined(
                    'PDO::MYSQL_ATTR_SSL_CA'
                )
            ) {

                $pdoOpts[
                    PDO::MYSQL_ATTR_SSL_CA
                ] = $caPath;
            }

            break;
        }
    }
}


// ============================================================================
// STEP 0 — PHP VERSION
// ============================================================================

$phpOk =
    version_compare(
        PHP_VERSION,
        '7.4.0',
        '>='
    );


step(
    $steps,
    $phpOk,
    'إصدار PHP: ' . PHP_VERSION,
    $phpOk
        ? ''
        : 'المطلوب PHP 7.4 أو أحدث'
);


if (!$phpOk) {

    $failed = true;
}


// ============================================================================
// STEP 1 — REQUIRED PHP EXTENSIONS
// ============================================================================

if (!$failed) {

    $extensions = [

        'PDO' => extension_loaded('PDO'),

        'pdo_mysql' =>
            extension_loaded('pdo_mysql'),

        'mbstring' =>
            extension_loaded('mbstring'),

        'fileinfo' =>
            extension_loaded('fileinfo')
    ];


    foreach ($extensions as $name => $available) {

        step(
            $steps,
            $available,
            'PHP Extension: ' . $name,
            $available
                ? ''
                : 'هذه الإضافة مطلوبة لتشغيل النظام'
        );


        if (!$available) {

            $failed = true;
        }
    }
}


// ============================================================================
// STEP 2 — DATABASE CONNECTION WITHOUT DB
// ============================================================================

if (!$failed) {

    try {

        $rootDsn =
            'mysql:host=' .
            $DB_HOST .
            ';port=' .
            $DB_PORT .
            ';charset=utf8mb4';


        $pdoRoot =
            new PDO(
                $rootDsn,
                $DB_USER,
                $DB_PASS,
                $pdoOpts
            );


        step(
            $steps,
            true,
            'الاتصال بخادم MySQL / TiDB ناجح',
            'Host: ' .
            $DB_HOST .
            ' | Port: ' .
            $DB_PORT .
            ($DB_SSL ? ' | SSL: ON' : ' | SSL: OFF')
        );

    } catch (PDOException $e) {

        step(
            $steps,
            false,
            'فشل الاتصال بخادم MySQL / TiDB',
            $e->getMessage()
        );

        $failed = true;
    }
}


// ============================================================================
// STEP 3 — CREATE DATABASE
// ============================================================================

if (!$failed) {

    try {

        $safeDbName =
            safe_identifier(
                $DB_NAME
            );


        if ($safeDbName === '') {

            throw new RuntimeException(
                'اسم قاعدة البيانات غير صالح.'
            );
        }


        /*
         * TiDB Cloud والاستضافات المدارة قد لا تسمح
         * للمستخدم بإنشاء Database.
         *
         * لذلك نحاول إنشاءها، وإذا كانت موجودة
         * نتابع بشكل طبيعي.
         */

        try {

            $pdoRoot->exec(
                "CREATE DATABASE IF NOT EXISTS `{$safeDbName}`
                 DEFAULT CHARACTER SET utf8mb4
                 COLLATE utf8mb4_unicode_ci"
            );

        } catch (PDOException $dbCreateError) {

            /*
             * إذا كانت قاعدة البيانات موجودة أصلًا
             * أو لم تكن صلاحية CREATE DATABASE متوفرة،
             * سنحاول الاتصال بها مباشرة.
             */
        }


        $pdoRoot->exec(
            "USE `{$safeDbName}`"
        );


        step(
            $steps,
            true,
            "قاعدة البيانات `$safeDbName` جاهزة"
        );

    } catch (PDOException $e) {

        step(
            $steps,
            false,
            'تعذر اختيار قاعدة البيانات',
            $e->getMessage()
        );

        $failed = true;

    } catch (Throwable $e) {

        step(
            $steps,
            false,
            'اسم قاعدة البيانات غير صالح',
            $e->getMessage()
        );

        $failed = true;
    }
}


// ============================================================================
// STEP 4 — CONNECT DIRECTLY TO DATABASE
// ============================================================================

if (!$failed) {

    try {

        $dbDsn =
            'mysql:host=' .
            $DB_HOST .
            ';port=' .
            $DB_PORT .
            ';dbname=' .
            $DB_NAME .
            ';charset=utf8mb4';


        $pdo =
            new PDO(
                $dbDsn,
                $DB_USER,
                $DB_PASS,
                $pdoOpts
            );


        step(
            $steps,
            true,
            'الاتصال بقاعدة البيانات ناجح'
        );

    } catch (PDOException $e) {

        step(
            $steps,
            false,
            'فشل الاتصال بقاعدة البيانات',
            $e->getMessage()
        );

        $failed = true;
    }
}


// ============================================================================
// STEP 5 — EXECUTE DATABASE.SQL
// ============================================================================

if (!$failed) {

    try {

        $sqlFile =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            'database.sql';


        if (!file_exists($sqlFile)) {

            throw new RuntimeException(
                'ملف database.sql غير موجود.'
            );
        }


        $sql =
            file_get_contents(
                $sqlFile
            );


        if ($sql === false) {

            throw new RuntimeException(
                'تعذر قراءة database.sql.'
            );
        }


        /*
         * إزالة BOM في بداية الملف.
         */

        $sql =
            preg_replace(
                '/^\xEF\xBB\xBF/',
                '',
                $sql
            );


        /*
         * إزالة تعليقات SQL ذات السطر الواحد.
         */

        $sql =
            preg_replace(
                '/^\s*--.*$/m',
                '',
                $sql
            );


        /*
         * إزالة التعليقات #.
         */

        $sql =
            preg_replace(
                '/^\s*#.*$/m',
                '',
                $sql
            );


        /*
         * تقسيم الجمل.
         *
         * هذا مناسب لملفات database.sql المعتادة.
         */

        $statements =
            array_filter(
                array_map(
                    'trim',
                    preg_split(
                        '/;\s*(?:\r?\n|$)/',
                        $sql
                    )
                )
            );


        $count = 0;


        foreach ($statements as $stmt) {

            $stmt =
                trim($stmt);


            if ($stmt === '') {

                continue;
            }


            /*
             * لا نحتاج CREATE DATABASE / USE
             * لأن الاتصال بقاعدة البيانات تم مسبقًا.
             */

            if (
                preg_match(
                    '/^\s*(CREATE\s+DATABASE|USE\s+)/i',
                    $stmt
                )
            ) {

                continue;
            }


            $pdo->exec($stmt);

            $count++;
        }


        step(
            $steps,
            true,
            "تم تنفيذ مخطط قاعدة البيانات ($count جملة)"
        );

    } catch (PDOException $e) {

        step(
            $steps,
            false,
            'خطأ أثناء تنفيذ database.sql',
            $e->getMessage()
        );

        $failed = true;

    } catch (Throwable $e) {

        step(
            $steps,
            false,
            'خطأ أثناء قراءة مخطط قاعدة البيانات',
            $e->getMessage()
        );

        $failed = true;
    }
}


// ============================================================================
// STEP 6 — ENSURE ADMIN ACCOUNT
// ============================================================================

if (!$failed) {

    try {

        /*
         * نتأكد أولاً من وجود جدول users.
         */

        $tableExists =
            $pdo->query(
                "SHOW TABLES LIKE 'users'"
            )->fetchColumn();


        if (!$tableExists) {

            throw new RuntimeException(
                'جدول users غير موجود. تحقق من database.sql.'
            );
        }


        $adminEmail =
            'admin@carrental.ma';


        $stmt =
            $pdo->prepare(
                "
                SELECT COUNT(*)
                FROM users
                WHERE email = :email
                "
            );


        $stmt->execute(
            [
                ':email' =>
                    $adminEmail
            ]
        );


        $exists =
            (int)$stmt->fetchColumn();


        if ($exists === 0) {

            $hash =
                password_hash(
                    $adminPassword,
                    PASSWORD_BCRYPT,
                    [
                        'cost' => 12
                    ]
                );


            /*
             * استخدام الأعمدة الأساسية الموجودة
             * في النظام.
             */

            $insert =
                $pdo->prepare(
                    "
                    INSERT INTO users
                    (
                        full_name,
                        email,
                        password,
                        phone,
                        role,
                        status,
                        email_verified_at
                    )
                    VALUES
                    (
                        :full_name,
                        :email,
                        :password,
                        :phone,
                        :role,
                        :status,
                        NOW()
                    )
                    "
                );


            $insert->execute(
                [
                    ':full_name' =>
                        'مدير النظام',

                    ':email' =>
                        $adminEmail,

                    ':password' =>
                        $hash,

                    ':phone' =>
                        '0600000000',

                    ':role' =>
                        'super_admin',

                    ':status' =>
                        'active'
                ]
            );


            $adminCreated = true;


            step(
                $steps,
                true,
                'تم إنشاء حساب المدير',
                $adminEmail
            );

        } else {

            step(
                $steps,
                true,
                'حساب المدير موجود مسبقاً',
                $adminEmail
            );
        }

    } catch (PDOException $e) {

        step(
            $steps,
            false,
            'تعذر إنشاء / التحقق من حساب المدير',
            $e->getMessage()
        );

        $failed = true;

    } catch (Throwable $e) {

        step(
            $steps,
            false,
            'تعذر إنشاء / التحقق من حساب المدير',
            $e->getMessage()
        );

        $failed = true;
    }
}


// ============================================================================
// STEP 7 — DIRECTORIES
// ============================================================================

if (!$failed) {

    $dirs = [

        'uploads',

        'uploads/cars',

        'uploads/avatars',

        'uploads/invoices',

        'assets/images'
    ];


    $okAll = true;

    $details = [];


    foreach ($dirs as $dir) {

        $path =
            __DIR__ .
            DIRECTORY_SEPARATOR .
            $dir;


        if (!is_dir($path)) {

            if (
                !@mkdir(
                    $path,
                    0755,
                    true
                )
            ) {

                /*
                 * على Vercel نظام الملفات ليس مكانًا
                 * مناسبًا للتخزين الدائم.
                 *
                 * لا نوقف التثبيت هنا.
                 */

                if (!is_dir($path)) {

                    $okAll = false;

                    $details[] =
                        $dir .
                        ': تعذر الإنشاء';
                }
            }
        }
    }


    step(
        $steps,
        $okAll,
        'إنشاء مجلدات النظام',
        $okAll
            ? ''
            : implode(
                ' | ',
                $details
            )
    );
}


// ============================================================================
// STEP 8 — COUNT CARS
// ============================================================================

if (!$failed) {

    try {

        $carsCount =
            (int)$pdo
                ->query(
                    "SELECT COUNT(*) FROM cars"
                )
                ->fetchColumn();


        step(
            $steps,
            true,
            "عدد السيارات في قاعدة البيانات: $carsCount",
            $carsCount > 0
                ? ''
                : 'يمكنك إضافة السيارات التجريبية من add-all-cars.php'
        );

    } catch (Exception $e) {

        step(
            $steps,
            false,
            'تعذر فحص جدول السيارات',
            $e->getMessage()
        );
    }
}


// ============================================================================
// SECURITY: INSTALLATION STATUS
// ============================================================================

$success =
    !$failed;


// ============================================================================
// HTML
// ============================================================================

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    تثبيت Premium Car Rental
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap"
    rel="stylesheet"
>

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {

    font-family: 'Cairo', Tahoma, Arial, sans-serif;

    background:
        linear-gradient(
            135deg,
            #667eea 0%,
            #764ba2 100%
        );

    min-height: 100vh;

    padding: 40px 15px;
}

.card {

    background: #ffffff;

    max-width: 760px;

    margin: 0 auto;

    border-radius: 22px;

    box-shadow:
        0 25px 70px rgba(0, 0, 0, .30);

    overflow: hidden;
}

.card-head {

    background: #111827;

    color: #ffffff;

    padding: 34px 25px;

    text-align: center;
}

.card-head h1 {

    font-size: 1.6rem;

    font-weight: 900;
}

.card-head p {

    color: #aeb6c5;

    margin-top: 7px;

    font-size: .92rem;
}

.card-body {

    padding: 30px;
}

.status {

    padding: 16px 18px;

    border-radius: 14px;

    margin-bottom: 22px;

    font-weight: 700;

    text-align: center;
}

.status.success {

    background: #ecfdf5;

    color: #047857;

    border: 1px solid #a7f3d0;
}

.status.error {

    background: #fef2f2;

    color: #b91c1c;

    border: 1px solid #fecaca;
}

.step {

    display: flex;

    align-items: flex-start;

    gap: 13px;

    padding: 15px;

    border-radius: 13px;

    margin-bottom: 10px;

    background: #f8fafc;

    border: 1px solid #eef2f7;
}

.step.ok {

    background: #ecfdf5;

    border-color: #d1fae5;
}

.step.fail {

    background: #fef2f2;

    border-color: #fee2e2;
}

.step .icon {

    width: 36px;

    height: 36px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 1rem;

    flex-shrink: 0;

    color: #ffffff;

    font-weight: 900;
}

.step.ok .icon {

    background: #10b981;
}

.step.fail .icon {

    background: #ef4444;
}

.step b {

    display: block;

    font-size: .94rem;

    color: #111827;
}

.step small {

    display: block;

    color: #6b7280;

    font-size: .78rem;

    margin-top: 4px;

    word-break: break-word;

    line-height: 1.6;
}

.btns {

    text-align: center;

    margin-top: 25px;
}

.btn {

    display: inline-block;

    padding: 13px 25px;

    border-radius: 12px;

    text-decoration: none;

    font-weight: 800;

    margin: 5px;

    font-size: .9rem;

    transition: .2s ease;
}

.btn:hover {

    transform: translateY(-2px);
}

.btn-p {

    background:
        linear-gradient(
            135deg,
            #667eea,
            #764ba2
        );

    color: #ffffff;
}

.btn-g {

    background: #10b981;

    color: #ffffff;
}

.btn-o {

    background: #ffffff;

    color: #667eea;

    border: 2px solid #667eea;
}

.warn {

    background: #fffbeb;

    border: 1px solid #fcd34d;

    color: #92400e;

    padding: 15px 18px;

    border-radius: 13px;

    margin-top: 20px;

    font-size: .86rem;

    line-height: 1.8;
}

.danger {

    background: #fef2f2;

    border: 1px solid #fca5a5;

    color: #991b1b;

    padding: 15px 18px;

    border-radius: 13px;

    margin-top: 20px;

    font-size: .86rem;

    line-height: 1.8;
}

.info {

    background: #eff6ff;

    border: 1px solid #bfdbfe;

    color: #1e40af;

    padding: 15px 18px;

    border-radius: 13px;

    margin-top: 20px;

    font-size: .84rem;

    line-height: 1.8;
}

code {

    direction: ltr;

    display: inline-block;

    background: rgba(0,0,0,.06);

    padding: 2px 6px;

    border-radius: 5px;

    font-family: monospace;
}

@media (max-width: 600px) {

    body {
        padding: 20px 10px;
    }

    .card-body {
        padding: 20px;
    }

    .card-head h1 {
        font-size: 1.3rem;
    }

    .btn {
        width: 100%;
        margin: 5px 0;
    }
}

</style>

</head>

<body>

<div class="card">

    <div class="card-head">

        <h1>
            🚗 معالج تثبيت نظام تأجير السيارات
        </h1>

        <p>
            Premium Car Rental — Setup Wizard
        </p>

    </div>


    <div class="card-body">

        <?php if ($success): ?>

            <div class="status success">

                ✅ تم تجهيز النظام بنجاح

            </div>

        <?php else: ?>

            <div class="status error">

                ❌ فشل التثبيت — راجع الخطأ المحدد أدناه

            </div>

        <?php endif; ?>


        <?php foreach ($steps as $s): ?>

            <div
                class="step <?php echo $s['ok'] ? 'ok' : 'fail'; ?>"
            >

                <div class="icon">

                    <?php
                    echo $s['ok']
                        ? '✓'
                        : '✗';
                    ?>

                </div>

                <div>

                    <b>
                        <?php
                        echo htmlspecialchars(
                            $s['label'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </b>


                    <?php if (!empty($s['detail'])): ?>

                        <small>

                            <?php
                            echo htmlspecialchars(
                                $s['detail'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>

                        </small>

                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>


        <?php if ($success): ?>

            <div class="warn">

                🔐 <strong>حساب المدير</strong><br>

                البريد:
                <code>admin@carrental.ma</code>
                <br>

                كلمة المرور:
                <code>Admin@123</code>

                <br><br>

                ⚠️ غيّر كلمة المرور بعد أول تسجيل دخول.

            </div>


            <?php if ($carsCount == 0): ?>

                <div class="info">

                    🚙 لا توجد سيارات حاليًا.

                    يمكنك إضافة السيارات التجريبية
                    من الزر أدناه إذا كان الملف موجودًا.

                </div>

            <?php endif; ?>


            <div class="btns">

                <?php if ($carsCount == 0): ?>

                    <a
                        class="btn btn-g"
                        href="add-all-cars.php"
                    >
                        🚙 إضافة 15 سيارة تجريبية
                    </a>


                    <a
                        class="btn btn-o"
                        href="seed-cars.php"
                    >
                        إضافة 3 سيارات فقط
                    </a>

                <?php endif; ?>


                <a
                    class="btn btn-p"
                    href="index.php"
                >
                    🏠 الانتقال إلى الموقع
                </a>


                <a
                    class="btn btn-o"
                    href="admin/index.php"
                >
                    ⚙️ لوحة الإدارة
                </a>

            </div>


            <div class="danger">

                ⚠️ <strong>مهم جدًا للإنتاج:</strong>

                بعد نجاح التثبيت، احذف:

                <br>

                <code>install.php</code>

                <br>

                وأي ملفات تثبيت أو إنشاء جداول غير ضرورية مثل:

                <br>

                <code>create-tables.php</code>

                <br>

                <code>create-admin.php</code>

                <br><br>

                لا تترك معالج التثبيت متاحًا للعامة.

            </div>


        <?php else: ?>

            <div class="warn">

                صحّح الخطأ الظاهر في الخطوات أعلاه ثم أعد تحميل الصفحة.

                <br><br>

                إذا كنت تستخدم TiDB Cloud، تأكد من:

                <br>

                <code>DB_HOST</code>

                <br>

                <code>DB_PORT=4000</code>

                <br>

                <code>DB_NAME</code>

                <br>

                <code>DB_USER</code>

                <br>

                <code>DB_PASS</code>

                <br>

                <code>DB_SSL=1</code>

            </div>

        <?php endif; ?>

    </div>

</div>

</body>

</html>
