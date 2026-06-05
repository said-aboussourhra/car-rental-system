<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : SITE_DESCRIPTION; ?>">
    <meta name="keywords" content="<?php echo SITE_KEYWORDS; ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="<?php echo $page_title ?? SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo SITE_DESCRIPTION; ?>">
    <meta property="og:image" content="<?php echo SITE_LOGO; ?>">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" type="image/x-icon" href="<?php echo SITE_FAVICON; ?>">
    
    <title><?php echo isset($page_title) ? $page_title . ' | ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap 5 CSS (RTL) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts (Cairo) -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Flatpickr (Datepicker) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/airbnb.css">
    <!-- Swiper JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <!-- Toastify -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <?php if (isset($page_css) && file_exists(BASE_PATH . "/assets/css/{$page_css}")): ?>
        <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/<?php echo $page_css; ?>">
    <?php endif; ?>
      
<link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
<?php if (isset($page_css) && file_exists(BASE_PATH . "/assets/css/{$page_css}")): ?>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/<?php echo $page_css; ?>">
<?php endif; ?>

    <style>
        body {
            font-family: 'Cairo', sans-serif;
            overflow-x: hidden;
        }
        .navbar-brand img {
            height: 50px;
        }
        .footer {
            background: #1a1a2e;
            color: #fff;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top shadow">
    <div class="container">
        <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
            <img src="<?php echo SITE_LOGO; ?>" alt="<?php echo SITE_NAME; ?>">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>">الرئيسية</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/cars.php">السيارات</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/about.php">من نحن</a></li>
                <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/contact.php">اتصل بنا</a></li>
            </ul>
            <ul class="navbar-nav">
                <?php if (is_logged_in()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <img src="<?php echo $_SESSION['user_avatar'] ? UPLOADS_URL . '/avatars/' . $_SESSION['user_avatar'] : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['user_name']) . '&background=random'; ?>" 
                                 class="rounded-circle" width="30" height="30">
                            <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/customer/dashboard.php"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/customer/my-bookings.php"><i class="fas fa-calendar-alt"></i> حجوزاتي</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/customer/profile.php"><i class="fas fa-user"></i> ملفي الشخصي</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?php echo SITE_URL; ?>/login.php"><i class="fas fa-sign-in-alt"></i> دخول</a></li>
                    <li class="nav-item"><a class="nav-link btn btn-primary text-white px-3 mx-1" href="<?php echo SITE_URL; ?>/register.php"><i class="fas fa-user-plus"></i> حساب جديد</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main>