<?php
/**
 * Premium Car Rental - Admin Settings
 * Complete Settings Page
 */
require_once '../includes/config.php';

// Check admin access
if (!is_logged_in() || !is_admin()) {
    header('Location: ../login.php');
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Admin';

// ============================================
// HANDLE SETTINGS SAVE
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    require_valid_csrf();
    $settings = [
        'site_name' => clean_input($_POST['site_name'] ?? SITE_NAME),
        'site_email' => clean_input($_POST['site_email'] ?? ADMIN_EMAIL),
        'site_phone' => clean_input($_POST['site_phone'] ?? ''),
        'currency' => clean_input($_POST['currency'] ?? 'MAD'),
        'currency_symbol' => clean_input($_POST['currency_symbol'] ?? 'DH'),
        'tax_rate' => floatval($_POST['tax_rate'] ?? 20),
        'min_rental_days' => intval($_POST['min_rental_days'] ?? 1),
        'max_rental_days' => intval($_POST['max_rental_days'] ?? 90),
        'deposit_amount' => floatval($_POST['deposit_amount'] ?? 2000),
        'mileage_limit' => intval($_POST['mileage_limit'] ?? 200),
        'working_hours_start' => clean_input($_POST['working_hours_start'] ?? '08:00'),
        'working_hours_end' => clean_input($_POST['working_hours_end'] ?? '20:00'),
        'address' => clean_input($_POST['address'] ?? ''),
        'city' => clean_input($_POST['city'] ?? ''),
        'facebook_url' => clean_input($_POST['facebook_url'] ?? ''),
        'instagram_url' => clean_input($_POST['instagram_url'] ?? ''),
        'whatsapp_number' => clean_input($_POST['whatsapp_number'] ?? ''),
        'youtube_url' => clean_input($_POST['youtube_url'] ?? ''),
        'tiktok_url' => clean_input($_POST['tiktok_url'] ?? ''),
        'about_text' => $_POST['about_text'] ?? '',
        'terms_text' => $_POST['terms_text'] ?? '',
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
    ];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        foreach ($settings as $key => $value) {
            $stmt->execute([$key, $value]);
        }
        $success = '✅ تم حفظ الإعدادات بنجاح!';
    } catch (Exception $e) {
        $error = '❌ خطأ في الحفظ: ' . $e->getMessage();
    }
}

// Handle add location
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    require_valid_csrf();
    $loc_name = clean_input($_POST['loc_name'] ?? '');
    $loc_city = clean_input($_POST['loc_city'] ?? '');
    $loc_address = clean_input($_POST['loc_address'] ?? '');
    $loc_phone = clean_input($_POST['loc_phone'] ?? '');
    $loc_hours = clean_input($_POST['loc_hours'] ?? '');
    
    if (!empty($loc_name) && !empty($loc_city)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO locations (name, city, address, phone, working_hours, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$loc_name, $loc_city, $loc_address, $loc_phone, $loc_hours]);
            $success = '✅ تم إضافة الموقع بنجاح!';
        } catch (Exception $e) {
            $error = '❌ خطأ: ' . $e->getMessage();
        }
    }
}

// Handle delete location
if (isset($_GET['delete_loc'])) {
    $loc_id = intval($_GET['delete_loc']);
    try {
        $pdo->prepare("DELETE FROM locations WHERE id = ?")->execute([$loc_id]);
        $success = '✅ تم حذف الموقع!';
    } catch (Exception $e) {
        $error = '❌ خطأ في الحذف';
    }
    header('Location: settings.php');
    exit();
}

// Handle add extra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_extra'])) {
    require_valid_csrf();
    $ext_name = clean_input($_POST['ext_name'] ?? '');
    $ext_desc = clean_input($_POST['ext_desc'] ?? '');
    $ext_rate = floatval($_POST['ext_rate'] ?? 0);
    $ext_max = intval($_POST['ext_max'] ?? 1);
    
    if (!empty($ext_name) && $ext_rate > 0) {
        try {
            $stmt = $pdo->prepare("INSERT INTO extras (name, description, daily_rate, max_quantity, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$ext_name, $ext_desc, $ext_rate, $ext_max]);
            $success = '✅ تم إضافة الإضافة بنجاح!';
        } catch (Exception $e) {
            $error = '❌ خطأ: ' . $e->getMessage();
        }
    }
}

// Handle delete extra
if (isset($_GET['delete_ext'])) {
    $ext_id = intval($_GET['delete_ext']);
    try {
        $pdo->prepare("DELETE FROM extras WHERE id = ?")->execute([$ext_id]);
        $success = '✅ تم حذف الإضافة!';
    } catch (Exception $e) {
        $error = '❌ خطأ في الحذف';
    }
    header('Location: settings.php');
    exit();
}

// ============================================
// FETCH CURRENT DATA
// ============================================
function getSetting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

$settings_data = [
    'site_name' => getSetting('site_name', SITE_NAME),
    'site_email' => getSetting('site_email', ADMIN_EMAIL),
    'site_phone' => getSetting('site_phone', ''),
    'currency' => getSetting('currency', 'MAD'),
    'currency_symbol' => getSetting('currency_symbol', 'DH'),
    'tax_rate' => getSetting('tax_rate', '20'),
    'min_rental_days' => getSetting('min_rental_days', '1'),
    'max_rental_days' => getSetting('max_rental_days', '90'),
    'deposit_amount' => getSetting('deposit_amount', '2000'),
    'mileage_limit' => getSetting('mileage_limit', '200'),
    'working_hours_start' => getSetting('working_hours_start', '08:00'),
    'working_hours_end' => getSetting('working_hours_end', '20:00'),
    'address' => getSetting('address', ''),
    'city' => getSetting('city', ''),
    'facebook_url' => getSetting('facebook_url', ''),
    'instagram_url' => getSetting('instagram_url', ''),
    'whatsapp_number' => getSetting('whatsapp_number', ''),
    'youtube_url' => getSetting('youtube_url', ''),
    'tiktok_url' => getSetting('tiktok_url', ''),
    'about_text' => getSetting('about_text', ''),
    'terms_text' => getSetting('terms_text', ''),
    'maintenance_mode' => getSetting('maintenance_mode', '0'),
];

$locations = [];
$extras = [];
try {
    $locations = $pdo->query("SELECT * FROM locations ORDER BY city ASC, name ASC")->fetchAll();
    $extras = $pdo->query("SELECT * FROM extras ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

$error = $error ?? '';
$success = $success ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات | لوحة التحكم</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #667eea;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --dark: #1a1a2e;
            --text: #333;
            --text-light: #6c757d;
            --border: #e0e0e0;
            --success: #10b981;
            --danger: #ef4444;
            --shadow: 0 5px 20px rgba(0,0,0,0.06);
            --radius: 14px;
            --radius-sm: 10px;
            --transition: all 0.3s ease;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f0f2f5; color: var(--text); }
        
        .admin-wrapper { display: flex; min-height: 100vh; }
        
        .admin-sidebar {
            width: 250px; background: var(--dark); color: white;
            position: fixed; top: 0; right: 0; bottom: 0; z-index: 100; overflow-y: auto;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px; padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1); color: white;
            text-decoration: none; font-weight: 800; font-size: 1.1rem;
        }
        .sidebar-brand i { color: var(--primary); }
        .sidebar-menu { padding: 10px 0; }
        .sidebar-menu a {
            display: flex; align-items: center; gap: 10px; padding: 12px 20px;
            color: rgba(255,255,255,0.7); text-decoration: none; font-weight: 500;
            margin: 2px 10px; border-radius: 10px; font-size: 0.9rem; transition: var(--transition);
        }
        .sidebar-menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu a.active { background: var(--gradient); color: white; }
        .sidebar-menu a i { width: 20px; text-align: center; }
        
        .admin-main { flex: 1; margin-right: 250px; }
        .admin-topbar {
            background: white; padding: 15px 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 50;
        }
        .admin-content { padding: 25px; max-width: 1000px; }
        
        .content-card {
            background: white; border-radius: var(--radius); padding: 25px;
            box-shadow: var(--shadow); border: 1px solid #f0f0f0; margin-bottom: 25px;
        }
        .content-card h5 {
            font-weight: 800; color: var(--dark); margin-bottom: 20px;
            padding-bottom: 15px; border-bottom: 2px solid #f0f0f0;
            display: flex; align-items: center; gap: 8px;
        }
        .content-card h5 i { color: var(--primary); }
        
        .form-label { font-weight: 700; font-size: 0.85rem; margin-bottom: 5px; color: var(--dark); display: block; }
        .form-control, .form-select {
            border: 2px solid var(--border); border-radius: var(--radius-sm); padding: 10px 14px;
            font-size: 0.9rem; font-family: 'Cairo', sans-serif; width: 100%; transition: var(--transition); background: #fafafa;
        }
        .form-control:focus, .form-select:focus { border-color: var(--primary); outline: none; background: white; }
        textarea.form-control { resize: vertical; min-height: 80px; }
        
        .btn {
            font-weight: 600; border-radius: var(--radius-sm); padding: 10px 20px; font-size: 0.9rem;
            cursor: pointer; transition: var(--transition); border: none; text-decoration: none; display: inline-block;
        }
        .btn-primary { background: var(--gradient); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        .btn-xs { padding: 3px 8px; font-size: 0.7rem; border-radius: 6px; }
        
        .alert { border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px; border: none; font-weight: 500; }
        
        .nav-pills .nav-link {
            font-weight: 600; color: var(--text); padding: 10px 20px; border-radius: 10px; margin: 0 3px; transition: var(--transition);
        }
        .nav-pills .nav-link.active { background: var(--gradient); color: white; }
        
        .table { margin: 0; }
        .table th { font-weight: 700; font-size: 0.82rem; color: var(--text-light); padding: 12px 14px; border-bottom: 2px solid var(--border); }
        .table td { padding: 10px 14px; vertical-align: middle; font-size: 0.88rem; }
        
        @media (max-width: 991px) {
            .admin-sidebar { transform: translateX(100%); }
            .admin-sidebar.mobile-open { transform: translateX(0); }
            .admin-main { margin-right: 0; }
        }
    </style>
</head>
<body>
    <div class="admin-wrapper">
        <!-- Sidebar -->
        <aside class="admin-sidebar" id="sidebar">
            <a href="index.php" class="sidebar-brand"><i class="fas fa-car-side"></i> <?php echo SITE_NAME; ?></a>
            <nav class="sidebar-menu">
                <a href="index.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
                <a href="cars-management.php"><i class="fas fa-car"></i> السيارات</a>
                <a href="bookings-management.php"><i class="fas fa-calendar-check"></i> الحجوزات</a>
                <a href="customers-management.php"><i class="fas fa-users"></i> العملاء</a>
                <a href="reports.php"><i class="fas fa-chart-bar"></i> التقارير</a>
                <a href="settings.php" class="active"><i class="fas fa-cog"></i> الإعدادات</a>
                <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
                <a href="../logout.php" style="color: #ef4444;"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </nav>
        </aside>
        
        <!-- Main -->
        <main class="admin-main">
            <header class="admin-topbar">
                <button style="background:#eef0ff;color:#667eea;border:none;padding:8px 14px;border-radius:8px;cursor:pointer;" 
                        onclick="document.getElementById('sidebar').classList.toggle('mobile-open')">
                    <i class="fas fa-bars"></i>
                </button>
                <span><strong><?php echo htmlspecialchars($user_name); ?></strong></span>
            </header>
            
            <div class="admin-content">
                <h3 style="font-weight:800;margin-bottom:25px;"><i class="fas fa-cog" style="color:var(--primary);margin-left:10px;"></i> الإعدادات</h3>
                
                <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Tabs -->
                <ul class="nav nav-pills mb-4" id="tabs">
                    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general"><i class="fas fa-sliders-h me-1"></i> عام</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#social"><i class="fas fa-share-alt me-1"></i> تواصل</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#locations"><i class="fas fa-map-marker-alt me-1"></i> مواقع</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#extras"><i class="fas fa-plus-circle me-1"></i> إضافات</button></li>
                    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#texts"><i class="fas fa-file-alt me-1"></i> نصوص</button></li>
                </ul>
                
                <div class="tab-content">
                    <!-- GENERAL TAB -->
                    <div class="tab-pane fade show active" id="general">
                        <form method="POST" class="content-card">
                        <?php echo csrf_field(); ?>
                            <h5><i class="fas fa-sliders-h"></i> الإعدادات العامة</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">اسم الموقع</label>
                                    <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($settings_data['site_name']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">البريد الإلكتروني</label>
                                    <input type="email" class="form-control" name="site_email" value="<?php echo htmlspecialchars($settings_data['site_email']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">رقم الهاتف</label>
                                    <input type="text" class="form-control" name="site_phone" value="<?php echo htmlspecialchars($settings_data['site_phone']); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">العملة</label>
                                    <input type="text" class="form-control" name="currency" value="<?php echo $settings_data['currency']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">رمز العملة</label>
                                    <input type="text" class="form-control" name="currency_symbol" value="<?php echo $settings_data['currency_symbol']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">نسبة الضريبة (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" value="<?php echo $settings_data['tax_rate']; ?>" step="0.01">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الحد الأدنى للأيام</label>
                                    <input type="number" class="form-control" name="min_rental_days" value="<?php echo $settings_data['min_rental_days']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الحد الأقصى للأيام</label>
                                    <input type="number" class="form-control" name="max_rental_days" value="<?php echo $settings_data['max_rental_days']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">التأمين الافتراضي (DH)</label>
                                    <input type="number" class="form-control" name="deposit_amount" value="<?php echo $settings_data['deposit_amount']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">الحد اليومي (كم)</label>
                                    <input type="number" class="form-control" name="mileage_limit" value="<?php echo $settings_data['mileage_limit']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ساعة البدء</label>
                                    <input type="time" class="form-control" name="working_hours_start" value="<?php echo $settings_data['working_hours_start']; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">ساعة الانتهاء</label>
                                    <input type="time" class="form-control" name="working_hours_end" value="<?php echo $settings_data['working_hours_end']; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">العنوان</label>
                                    <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($settings_data['address']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">المدينة</label>
                                    <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($settings_data['city']); ?>">
                                </div>
                                <div class="col-12">
                                    <label style="cursor:pointer;">
                                        <input type="checkbox" name="maintenance_mode" value="1" <?php echo $settings_data['maintenance_mode'] == '1' ? 'checked' : ''; ?> style="width:18px;height:18px;margin-left:8px;">
                                        <strong>🚧 وضع الصيانة</strong>
                                    </label>
                                </div>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary mt-4"><i class="fas fa-save me-1"></i> حفظ</button>
                        </form>
                    </div>
                    
                    <!-- SOCIAL TAB -->
                    <div class="tab-pane fade" id="social">
                        <form method="POST" class="content-card">
                        <?php echo csrf_field(); ?>
                            <h5><i class="fas fa-share-alt"></i> وسائل التواصل</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Facebook</label>
                                    <input type="url" class="form-control" name="facebook_url" value="<?php echo htmlspecialchars($settings_data['facebook_url']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Instagram</label>
                                    <input type="url" class="form-control" name="instagram_url" value="<?php echo htmlspecialchars($settings_data['instagram_url']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="text" class="form-control" name="whatsapp_number" value="<?php echo htmlspecialchars($settings_data['whatsapp_number']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">YouTube</label>
                                    <input type="url" class="form-control" name="youtube_url" value="<?php echo htmlspecialchars($settings_data['youtube_url']); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">TikTok</label>
                                    <input type="url" class="form-control" name="tiktok_url" value="<?php echo htmlspecialchars($settings_data['tiktok_url']); ?>">
                                </div>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary mt-4"><i class="fas fa-save me-1"></i> حفظ</button>
                        </form>
                    </div>
                    
                    <!-- LOCATIONS TAB -->
                    <div class="tab-pane fade" id="locations">
                        <div class="content-card">
                            <h5><i class="fas fa-map-marker-alt"></i> مواقع الاستلام والتسليم</h5>
                            
                            <!-- Add Form -->
                            <form method="POST" class="row g-2 mb-4 p-3" style="background:#f8f9fa;border-radius:10px;">
                            <?php echo csrf_field(); ?>
                                <input type="hidden" name="add_location" value="1">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="loc_name" placeholder="اسم الموقع *" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="loc_city" placeholder="المدينة *" required>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="loc_phone" placeholder="الهاتف">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" name="loc_address" placeholder="العنوان">
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="loc_hours" placeholder="ساعات العمل">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                                </div>
                            </form>
                            
                            <!-- Locations Table -->
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>الاسم</th><th>المدينة</th><th>العنوان</th><th>الهاتف</th><th>ساعات العمل</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($locations as $loc): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($loc['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($loc['city']); ?></td>
                                            <td><?php echo htmlspecialchars($loc['address']); ?></td>
                                            <td><?php echo htmlspecialchars($loc['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($loc['working_hours']); ?></td>
                                            <td>
                                                <a href="?delete_loc=<?php echo $loc['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($locations)): ?>
                                        <tr><td colspan="6" class="text-center text-muted py-3">لا توجد مواقع</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- EXTRAS TAB -->
                    <div class="tab-pane fade" id="extras">
                        <div class="content-card">
                            <h5><i class="fas fa-plus-circle"></i> الإضافات الاختيارية</h5>
                            
                            <form method="POST" class="row g-2 mb-4 p-3" style="background:#f8f9fa;border-radius:10px;">
                            <?php echo csrf_field(); ?>
                                <input type="hidden" name="add_extra" value="1">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="ext_name" placeholder="الاسم *" required>
                                </div>
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="ext_desc" placeholder="وصف">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="ext_rate" placeholder="السعر/يوم *" step="0.01" required>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control" name="ext_max" placeholder="الحد الأقصى" value="1">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-plus"></i></button>
                                </div>
                            </form>
                            
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr><th>الاسم</th><th>الوصف</th><th>السعر/يوم</th><th>الحد</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($extras as $ext): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($ext['name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($ext['description']); ?></td>
                                            <td><strong class="text-primary"><?php echo number_format($ext['daily_rate'], 2); ?> DH</strong></td>
                                            <td><?php echo $ext['max_quantity']; ?></td>
                                            <td>
                                                <a href="?delete_ext=<?php echo $ext['id']; ?>" class="btn btn-danger btn-xs" onclick="return confirm('حذف؟')"><i class="fas fa-trash"></i></a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($extras)): ?>
                                        <tr><td colspan="5" class="text-center text-muted py-3">لا توجد إضافات</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- TEXTS TAB -->
                    <div class="tab-pane fade" id="texts">
                        <form method="POST" class="content-card">
                        <?php echo csrf_field(); ?>
                            <h5><i class="fas fa-file-alt"></i> النصوص والصفحات</h5>
                            <div class="mb-3">
                                <label class="form-label">نص من نحن</label>
                                <textarea class="form-control" name="about_text" rows="6"><?php echo htmlspecialchars($settings_data['about_text']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">الشروط والأحكام</label>
                                <textarea class="form-control" name="terms_text" rows="6"><?php echo htmlspecialchars($settings_data['terms_text']); ?></textarea>
                            </div>
                            <button type="submit" name="save_settings" class="btn btn-primary"><i class="fas fa-save me-1"></i> حفظ</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>