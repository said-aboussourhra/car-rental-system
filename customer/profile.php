<?php
/**
 * Premium Car Rental - الملف الشخصي للعميل
 */
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    header('Location: ../login.php');
    exit();
}

$auth = new Auth($pdo);
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// ============================================
// معالجة تحديث البيانات
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    require_valid_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $cin = trim($_POST['cin'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $birth_date = $_POST['birth_date'] ?? null;

    if (mb_strlen($full_name) < 3) {
        $error = 'الاسم الكامل مطلوب (3 أحرف على الأقل)';
    } elseif (!preg_match('/^[0-9+\s\-]{9,15}$/', $phone)) {
        $error = 'رقم الهاتف غير صالح';
    } else {
        try {
            if ($birth_date && !DateTime::createFromFormat('Y-m-d', $birth_date)) {
                $birth_date = null;
            }

            $avatarField = '';
            $avatarParam = [];

            // رفع صورة شخصية جديدة إن وجدت
            if (!empty($_FILES['avatar']['name'])) {
                $upload = upload_file($_FILES['avatar'], 'avatars');
                if ($upload['success']) {
                    $avatarField = ', avatar = :avatar';
                    $avatarParam = [':avatar' => $upload['filename']];
                } else {
                    $error = $upload['message'];
                }
            }

            if (!$error) {
                $stmt = $pdo->prepare("UPDATE users SET full_name = :name, phone = :phone, address = :address,
                                       city = :city, cin = :cin, license_number = :lic, birth_date = :bd $avatarField,
                                       updated_at = NOW() WHERE id = :id");
                $stmt->execute(array_merge([
                    ':name' => $full_name, ':phone' => $phone, ':address' => $address,
                    ':city' => $city, ':cin' => $cin, ':lic' => $license_number,
                    ':bd' => $birth_date, ':id' => $user_id
                ], $avatarParam));

                $_SESSION['user_name'] = $full_name;
                if ($avatarField) {
                    $_SESSION['user_avatar'] = $avatarParam[':avatar'];
                }
                log_activity($user_id, 'profile_update', 'تحديث الملف الشخصي');
                $success = 'تم تحديث بياناتك بنجاح ✅';
            }
        } catch (Exception $e) {
            $error = 'حدث خطأ أثناء الحفظ';
        }
    }
}

// ============================================
// معالجة تغيير كلمة المرور
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    require_valid_csrf();

    $old = $_POST['old_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_new_password'] ?? '';

    if ($new !== $confirm) {
        $error = 'كلمتا المرور الجديدتان غير متطابقتين';
    } else {
        $result = $auth->changePassword($user_id, $old, $new);
        if ($result['success']) {
            $success = 'تم تغيير كلمة المرور بنجاح 🔒';
        } else {
            $error = $result['message'];
        }
    }
}

// جلب بيانات المستخدم
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch();
    if (!$user) { header('Location: ../logout.php'); exit(); }
} catch (Exception $e) {
    die('حدث خطأ في تحميل البيانات');
}

$avatarUrl = !empty($user['avatar'])
    ? '../uploads/avatars/' . $user['avatar']
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=667eea&color=fff&size=128';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملفي الشخصي | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        :root { --primary:#667eea; --gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
                --dark:#1a1a2e; --radius:16px; --shadow:0 5px 20px rgba(0,0,0,.06); }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Cairo',sans-serif; background:#f5f6fa; color:#333; }
        .navbar { background:#fff; box-shadow:0 2px 10px rgba(0,0,0,.05); padding:12px 0; position:sticky; top:0; z-index:1000; }
        .navbar-brand { display:flex; align-items:center; gap:8px; font-weight:900; font-size:1.2rem; color:var(--dark)!important; text-decoration:none; }
        .navbar-brand i { color:var(--primary); font-size:1.4rem; }
        .nav-link { font-weight:600; color:#333!important; padding:8px 16px!important; border-radius:8px; font-size:.9rem; }
        .nav-link:hover, .nav-link.active { color:var(--primary)!important; background:#eef0ff; }
        .btn { font-weight:600; border-radius:10px; padding:10px 20px; font-size:.88rem; }
        .btn-primary { background:var(--gradient); border:none; color:#fff; }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(102,126,234,.4); color:#fff; }

        .profile-section { padding:35px 0 60px; }
        .card-panel { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:28px; }
        .card-panel h5 { font-weight:800; color:var(--dark); margin-bottom:20px; }
        .avatar-wrap { position:relative; width:130px; height:130px; margin:0 auto; }
        .avatar-wrap img { width:130px; height:130px; border-radius:50%; object-fit:cover; border:4px solid #eef0ff; }
        .avatar-wrap label {
            position:absolute; bottom:4px; left:4px; width:38px; height:38px; border-radius:50%;
            background:var(--gradient); color:#fff; display:flex; align-items:center; justify-content:center;
            cursor:pointer; font-size:.9rem; box-shadow:0 4px 12px rgba(102,126,234,.4);
        }
        .form-label { font-weight:700; font-size:.85rem; color:#4b5563; }
        .form-control, .form-select { border-radius:10px; border:1.5px solid #e5e7eb; padding:10px 14px; font-size:.9rem; }
        .form-control:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(102,126,234,.12); }
        .alert { border-radius:12px; font-size:.9rem; }
        .side-stat { display:flex; align-items:center; gap:12px; padding:14px; border-radius:12px; background:#f8f9fb; margin-bottom:10px; }
        .side-stat i { width:42px; height:42px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.1rem; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../index.php"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="nav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="../index.php"><i class="fas fa-home me-1"></i> الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i> لوحة التحكم</a></li>
                    <li class="nav-item"><a class="nav-link" href="my-bookings.php"><i class="fas fa-calendar-alt me-1"></i> حجوزاتي</a></li>
                    <li class="nav-item"><a class="nav-link active" href="profile.php"><i class="fas fa-user me-1"></i> ملفي</a></li>
                </ul>
                <a href="../logout.php" class="btn btn-sm" style="background:#fee2e2;color:#991b1b;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </div>
    </nav>

    <section class="profile-section">
        <div class="container">
            <?php if ($success): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle me-1"></i> <?php echo e($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-1"></i> <?php echo e($error); ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- البيانات الشخصية -->
                <div class="col-lg-7">
                    <div class="card-panel">
                        <h5><i class="fas fa-user-edit me-2 text-primary"></i> البيانات الشخصية</h5>
                        <form method="POST" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="update_profile" value="1">

                            <div class="avatar-wrap mb-4">
                                <img src="<?php echo e($avatarUrl); ?>" alt="الصورة الشخصية" id="avatarPreview">
                                <label for="avatarInput" title="تغيير الصورة"><i class="fas fa-camera"></i></label>
                                <input type="file" name="avatar" id="avatarInput" accept="image/*" hidden onchange="previewAvatar(this)">
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">الاسم الكامل *</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo e($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">رقم الهاتف *</label>
                                    <input type="tel" name="phone" class="form-control" value="<?php echo e($user['phone']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" value="<?php echo e($user['email']); ?>" disabled>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">تاريخ الميلاد</label>
                                    <input type="date" name="birth_date" class="form-control" value="<?php echo e($user['birth_date'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">رقم البطاقة الوطنية (CIN)</label>
                                    <input type="text" name="cin" class="form-control" value="<?php echo e($user['cin'] ?? ''); ?>" placeholder="مثال: AB123456">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">رقم رخصة السياقة</label>
                                    <input type="text" name="license_number" class="form-control" value="<?php echo e($user['license_number'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">المدينة</label>
                                    <input type="text" name="city" class="form-control" value="<?php echo e($user['city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">العنوان</label>
                                    <input type="text" name="address" class="form-control" value="<?php echo e($user['address'] ?? ''); ?>">
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-4"><i class="fas fa-save me-1"></i> حفظ التغييرات</button>
                        </form>
                    </div>
                </div>

                <!-- كلمة المرور + معلومات الحساب -->
                <div class="col-lg-5">
                    <div class="card-panel mb-4">
                        <h5><i class="fas fa-key me-2 text-warning"></i> تغيير كلمة المرور</h5>
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="change_password" value="1">
                            <div class="mb-3">
                                <label class="form-label">كلمة المرور الحالية</label>
                                <input type="password" name="old_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">كلمة المرور الجديدة (8+ أحرف)</label>
                                <input type="password" name="new_password" class="form-control" minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                                <input type="password" name="confirm_new_password" class="form-control" minlength="8" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-lock me-1"></i> تحديث كلمة المرور</button>
                        </form>
                    </div>

                    <div class="card-panel">
                        <h5><i class="fas fa-info-circle me-2 text-info"></i> معلومات الحساب</h5>
                        <div class="side-stat">
                            <i class="fas fa-envelope" style="background:#eef0ff;color:#667eea;"></i>
                            <div><small class="text-muted d-block">البريد</small><b><?php echo e($user['email']); ?></b></div>
                        </div>
                        <div class="side-stat">
                            <i class="fas fa-calendar" style="background:#ecfdf5;color:#10b981;"></i>
                            <div><small class="text-muted d-block">عضو منذ</small><b><?php echo format_date($user['created_at'], 'd M Y'); ?></b></div>
                        </div>
                        <div class="side-stat">
                            <i class="fas fa-clock" style="background:#fffbeb;color:#f59e0b;"></i>
                            <div><small class="text-muted d-block">آخر دخول</small><b><?php echo $user['last_login'] ? format_date($user['last_login'], 'd/m/Y H:i') : '—'; ?></b></div>
                        </div>
                        <div class="side-stat">
                            <i class="fas fa-shield-alt" style="background:<?php echo $user['email_verified_at'] ? '#ecfdf5' : '#fef2f2'; ?>;color:<?php echo $user['email_verified_at'] ? '#10b981' : '#ef4444'; ?>;"></i>
                            <div><small class="text-muted d-block">حالة البريد</small>
                                <b><?php echo $user['email_verified_at'] ? 'موثّق ✓' : 'غير موثّق'; ?></b>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) { document.getElementById('avatarPreview').src = e.target.result; };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
