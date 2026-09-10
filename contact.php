<?php
require_once 'includes/config.php';
$page_title = 'اتصل بنا | ' . SITE_NAME;
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $subject = clean_input($_POST['subject'] ?? '');
    $message = clean_input($_POST['message'] ?? '');
    
    if (empty($name) || empty($email) || empty($message)) {
        $error = 'يرجى ملء جميع الحقول المطلوبة';
    } else {
        $body = "<strong>Nom:</strong> $name<br><strong>Email:</strong> $email<br><strong>Tél:</strong> $phone<br><strong>Sujet:</strong> $subject<br><strong>Message:</strong><br>$message";
        if (send_email(ADMIN_EMAIL, "Contact - $subject", $body)) {
            $success = 'تم إرسال رسالتك بنجاح! سنتواصل معك قريباً.';
        } else {
            $error = 'فشل الإرسال. يرجى المحاولة لاحقاً.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <style>
        :root {
            --primary: #667eea; --gold: #c9a84c; --gold-light: #f5ecd7;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-gold: linear-gradient(135deg, #c9a84c 0%, #e5c76b 100%);
            --gradient-dark: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%);
            --dark: #0f0f1a; --light: #f8f9fa; --white: #ffffff;
            --text: #333; --text-light: #6c757d; --border: #e0e0e0;
            --success: #10b981; --danger: #ef4444;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04); --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.08); --shadow-lg: 0 20px 60px rgba(0,0,0,0.12);
            --radius: 16px; --radius-sm: 12px; --radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); box-shadow: var(--shadow-xs); padding: 12px 0; position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none; }
        .navbar-brand .brand-icon { width: 42px; height: 42px; background: var(--gradient); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
        .nav-link { font-weight: 600; color: var(--text) !important; padding: 10px 18px !important; border-radius: 10px; transition: var(--transition); }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; background: #eef0ff; }
        .btn { font-weight: 600; border-radius: 10px; padding: 10px 22px; transition: var(--transition); font-size: 0.9rem; cursor: pointer; }
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); }
        
        .hero-contact { background: var(--gradient-dark); padding: 80px 0; text-align: center; color: white; position: relative; overflow: hidden; }
        .hero-contact h1 { font-family: 'Playfair Display', serif; font-size: 2.5rem; font-weight: 900; color: var(--gold); margin-bottom: 10px; }
        .hero-wave { position: absolute; bottom: -2px; left: 0; width: 100%; }
        
        .section { padding: 60px 0; }
        .card { background: white; border-radius: var(--radius-lg); padding: 35px; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; height: 100%; }
        .card h5 { font-weight: 800; color: var(--dark); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .card h5 i { color: var(--primary); }
        
        .info-card { background: white; border-radius: var(--radius); padding: 30px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; transition: var(--transition); height: 100%; }
        .info-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); border-color: var(--primary); }
        .info-card .info-icon { width: 65px; height: 65px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; font-size: 1.5rem; }
        .info-card h6 { font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .info-card p, .info-card a { color: var(--text-light); text-decoration: none; font-size: 0.9rem; }
        
        .form-control { border: 2px solid var(--border); border-radius: 12px; padding: 14px 18px; font-size: 0.95rem; font-family: 'Cairo', sans-serif; transition: var(--transition); }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(102,126,234,0.06); outline: none; }
        textarea.form-control { resize: vertical; min-height: 130px; }
        
        .alert { border-radius: 12px; padding: 16px 20px; border: none; font-weight: 500; }
        
        .map-container { border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow); }
        .map-container iframe { width: 100%; height: 350px; border: none; }
        
        .footer { background: var(--dark); color: white; padding: 40px 0 20px; text-align: center; }
        
        @media (max-width: 768px) { .hero-contact h1 { font-size: 1.8rem; } .card { padding: 25px; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span class="brand-icon">🚗</span> <?php echo SITE_NAME; ?></a>
            <button class="navbar-toggler border-0" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav me-auto gap-1">
                    <li class="nav-item"><a class="nav-link" href="index.php">الرئيسية</a></li>
                    <li class="nav-item"><a class="nav-link" href="cars.php">السيارات</a></li>
                    <li class="nav-item"><a class="nav-link" href="about.php">من نحن</a></li>
                    <li class="nav-item"><a class="nav-link active" href="contact.php">اتصل بنا</a></li>
                </ul>
                <div class="d-flex gap-2">
                    <?php if (is_logged_in()): ?>
                        <a href="customer/my-bookings.php" class="btn btn-outline-primary btn-sm"><i class="fas fa-list-check me-1"></i> طلباتي</a>
                        <a href="customer/dashboard.php" class="btn btn-primary btn-sm"><i class="fas fa-user me-1"></i> حسابي</a>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary btn-sm">تسجيل الدخول</a>
                        <a href="register.php" class="btn btn-primary btn-sm">إنشاء حساب</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <section class="hero-contact">
        <div class="container">
            <i class="fas fa-headset" style="font-size:3rem;color:var(--gold);margin-bottom:15px;"></i>
            <h1>Contactez-Nous</h1>
            <p style="opacity:0.8;">Notre équipe est à votre disposition 24h/24 et 7j/7</p>
        </div>
        <div class="hero-wave"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100"><path fill="#f5f6fa" d="M0,64L80,69C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,59L1440,64L1440,100L0,100Z"></path></svg></div>
    </section>
    
    <section class="section">
        <div class="container">
            <div class="row g-4 mb-5">
                <div class="col-md-4" data-aos="fade-up"><div class="info-card"><div class="info-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-phone"></i></div><h6>Téléphone</h6><a href="tel:+212600000000">+212 6 00 00 00 00</a><p class="mt-1">24h/24 - 7j/7</p></div></div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100"><div class="info-card"><div class="info-icon bg-success bg-opacity-10 text-success"><i class="fas fa-envelope"></i></div><h6>Email</h6><a href="mailto:contact@carrental.ma">contact@carrental.ma</a><p class="mt-1">Réponse sous 24h</p></div></div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200"><div class="info-card"><div class="info-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-map-marker-alt"></i></div><h6>Adresse</h6><p>Avenue Mohammed V, Immeuble Atlas, Guéliz, Marrakech</p></div></div>
            </div>
            
            <div class="row">
                <div class="col-lg-7 mb-4" data-aos="fade-up">
                    <div class="card">
                        <h5><i class="fas fa-paper-plane"></i> Envoyez-nous un message</h5>
                        <?php if($success): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i> <?php echo $success; ?></div><?php endif; ?>
                        <?php if($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo $error; ?></div><?php endif; ?>
                        <form method="POST">
                        <?php echo csrf_field(); ?>
                            <div class="row g-3">
                                <div class="col-md-6"><input type="text" class="form-control" name="name" placeholder="الاسم الكامل *" required></div>
                                <div class="col-md-6"><input type="email" class="form-control" name="email" placeholder="البريد الإلكتروني *" required></div>
                                <div class="col-md-6"><input type="tel" class="form-control" name="phone" placeholder="رقم الهاتف"></div>
                                <div class="col-md-6"><input type="text" class="form-control" name="subject" placeholder="الموضوع"></div>
                                <div class="col-12"><textarea class="form-control" name="message" placeholder="رسالتك *" required></textarea></div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg mt-4"><i class="fas fa-paper-plane me-2"></i> إرسال</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5" data-aos="fade-up">
                    <div class="map-container"><iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3397.6902415038137!2d-8.00865!3d31.62947!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xdafee8a50e4e4c1%3A0x2a71cf890507f8a1!2sMarrakech!5e0!3m2!1sen!2sma!4v1635000000000!5m2!1sen!2sma" allowfullscreen="" loading="lazy"></iframe></div>
                </div>
            </div>
        </div>
    </section>
    
    <footer class="footer"><div class="container"><p>&copy; <?php echo date('Y'); ?> Premium Car Rental. Tous droits réservés.</p></div></footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>AOS.init({duration:800,once:true});</script>
</body>
</html>