<?php
require_once 'includes/config.php';
$page_title = 'من نحن | ' . SITE_NAME;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    
    <style>
        :root {
            --primary: #667eea; --primary-light: #eef0ff;
            --gold: #c9a84c; --gold-light: #f5ecd7; --gold-dark: #a68a3e;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-gold: linear-gradient(135deg, #c9a84c 0%, #e5c76b 100%);
            --gradient-dark: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 50%, #2d2d44 100%);
            --dark: #0f0f1a; --light: #f8f9fa; --white: #ffffff;
            --text: #333; --text-light: #6c757d; --border: #e0e0e0;
            --shadow-xs: 0 2px 8px rgba(0,0,0,0.04); --shadow-sm: 0 5px 20px rgba(0,0,0,0.06);
            --shadow: 0 10px 40px rgba(0,0,0,0.08); --shadow-lg: 0 20px 60px rgba(0,0,0,0.12); --shadow-xl: 0 30px 80px rgba(0,0,0,0.2);
            --radius: 16px; --radius-sm: 12px; --radius-lg: 20px; --radius-xl: 24px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Cairo', sans-serif; background: #f5f6fa; color: var(--text); line-height: 1.8; overflow-x: hidden; }
        ::-webkit-scrollbar { width: 6px; } ::-webkit-scrollbar-track { background: #f1f1f1; } ::-webkit-scrollbar-thumb { background: var(--primary); border-radius: 3px; }
        
        .navbar { background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); box-shadow: var(--shadow-xs); padding: 12px 0; position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 1.3rem; color: var(--dark) !important; text-decoration: none; }
        .navbar-brand .brand-icon { width: 42px; height: 42px; background: var(--gradient); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.3rem; color: white; }
        .nav-link { font-weight: 600; color: var(--text) !important; padding: 10px 18px !important; border-radius: 10px; transition: var(--transition); }
        .nav-link:hover, .nav-link.active { color: var(--primary) !important; background: var(--primary-light); }
        .btn { font-weight: 600; border-radius: 10px; padding: 10px 22px; transition: var(--transition); font-size: 0.9rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: var(--gradient); border: none; color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,0.4); color: white; }
        .btn-outline-primary { border: 2px solid var(--primary); color: var(--primary); background: transparent; }
        .btn-outline-primary:hover { background: var(--primary); color: white; }
        .btn-sm { padding: 6px 14px; font-size: 0.82rem; }
        
        /* Hero */
        .hero-about {
            background: var(--gradient-dark); padding: 120px 0 100px; text-align: center;
            color: white; position: relative; overflow: hidden;
        }
        .hero-about::before { content: ''; position: absolute; top: -30%; left: -20%; width: 700px; height: 700px; background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%); pointer-events: none; }
        .hero-about::after { content: ''; position: absolute; bottom: -10%; right: -10%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(102,126,234,0.1) 0%, transparent 70%); pointer-events: none; }
        .hero-about .hero-icon { font-size: 5rem; color: var(--gold); margin-bottom: 25px; position: relative; animation: floatIcon 4s ease-in-out infinite; }
        @keyframes floatIcon { 0%,100% { transform: translateY(0) rotate(0deg); } 25% { transform: translateY(-20px) rotate(5deg); } 75% { transform: translateY(-10px) rotate(-5deg); } }
        .hero-about h1 { font-family: 'Playfair Display', serif; font-size: 3.5rem; font-weight: 900; color: var(--gold); margin-bottom: 18px; position: relative; letter-spacing: 3px; }
        .hero-about .subtitle { font-size: 1.25rem; opacity: 0.85; position: relative; max-width: 650px; margin: 0 auto; font-weight: 300; }
        .hero-wave { position: absolute; bottom: -2px; left: 0; width: 100%; z-index: 2; }
        
        /* Sections */
        .section { padding: 100px 0; }
        .section-dark { background: var(--dark); color: white; position: relative; overflow: hidden; }
        .section-dark::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.02)" d="M0,160L48,176C96,192,192,224,288,213.3C384,203,480,149,576,138.7C672,128,768,160,864,181.3C960,203,1056,213,1152,208C1248,203,1344,181,1392,170.7L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') no-repeat bottom; background-size: cover; opacity: 0.5; }
        .section-light { background: #f8f9fa; }
        .section-header { text-align: center; margin-bottom: 70px; }
        .section-badge { display: inline-block; background: var(--primary-light); color: var(--primary); padding: 10px 24px; border-radius: 50px; font-weight: 700; font-size: 0.88rem; margin-bottom: 18px; letter-spacing: 1.5px; text-transform: uppercase; }
        .section-dark .section-badge { background: rgba(201,168,76,0.2); color: var(--gold); }
        .section-title { font-size: 2.5rem; font-weight: 900; color: var(--dark); margin-bottom: 18px; }
        .section-dark .section-title { color: white; }
        .section-description { color: var(--text-light); max-width: 650px; margin: 0 auto; font-size: 1.1rem; }
        .section-dark .section-description { color: rgba(255,255,255,0.7); }
        
        /* Stats */
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; }
        .stat-item { text-align: center; padding: 40px 25px; background: rgba(255,255,255,0.04); border-radius: var(--radius-lg); border: 1px solid rgba(255,255,255,0.08); backdrop-filter: blur(10px); transition: var(--transition); }
        .stat-item:hover { transform: translateY(-10px); border-color: var(--gold); background: rgba(201,168,76,0.08); }
        .stat-item .stat-number { font-size: 3.5rem; font-weight: 900; color: var(--gold); line-height: 1; font-family: 'Playfair Display', serif; }
        .stat-item .stat-plus { color: var(--gold); }
        .stat-item .stat-label { font-size: 0.95rem; color: rgba(255,255,255,0.7); margin-top: 12px; font-weight: 500; letter-spacing: 1px; }
        
        /* Values */
        .values-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 25px; }
        .value-card { background: white; border-radius: var(--radius-lg); padding: 40px 30px; text-align: center; box-shadow: var(--shadow-sm); border: 1px solid #f0f0f0; transition: var(--transition); position: relative; overflow: hidden; }
        .value-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 5px; background: var(--gradient-gold); transform: scaleX(0); transition: transform 0.5s ease; border-radius: 0 0 50% 50%; }
        .value-card:hover::before { transform: scaleX(1); }
        .value-card:hover { transform: translateY(-12px); box-shadow: var(--shadow-xl); }
        .value-icon { width: 80px; height: 80px; border-radius: 24px; display: flex; align-items: center; justify-content: center; margin: 0 auto 22px; font-size: 2rem; transition: var(--transition); }
        .value-card:hover .value-icon { transform: rotateY(360deg); scale: 1.1; }
        .value-card h5 { font-weight: 800; color: var(--dark); margin-bottom: 12px; font-size: 1.1rem; }
        .value-card p { color: var(--text-light); font-size: 0.92rem; margin: 0; line-height: 1.8; }
        
        /* Team Section - SAID */
        .team-section { position: relative; }
        .team-spotlight { text-align: center; }
        .team-card-founder {
            background: white; border-radius: var(--radius-xl); padding: 50px 40px;
            box-shadow: var(--shadow-lg); border: 1px solid #f0f0f0; max-width: 450px; margin: 0 auto;
            position: relative; overflow: hidden; transition: var(--transition);
        }
        .team-card-founder::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 200px; background: var(--gradient-dark); border-radius: 0 0 50% 50%; }
        .team-card-founder:hover { transform: translateY(-10px); box-shadow: var(--shadow-xl); }
        .founder-image-wrapper { position: relative; z-index: 2; margin-bottom: 20px; }
        .founder-image-wrapper img {
            width: 240px; height: 290px; border-radius: 50%; object-fit: cover;
            border: 5px solid var(--gold); box-shadow: 0 15px 40px rgba(201,168,76,0.3);
            transition: var(--transition);
        }
        .team-card-founder:hover .founder-image-wrapper img { transform: scale(1.05); border-color: white; }
        .founder-name {
            font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 900;
            background: var(--gradient-gold); -webkit-background-clip: text; -webkit-text-fill-color: transparent;
            background-clip: text; margin-bottom: 5px; letter-spacing: 2px;
        }
        .founder-title { color: var(--text-light); font-weight: 600; font-size: 1rem; letter-spacing: 1px; }
        .founder-social { margin-top: 20px; display: flex; justify-content: center; gap: 10px; }
        .founder-social a { width: 44px; height: 44px; border-radius: 50%; background: var(--light); display: flex; align-items: center; justify-content: center; color: var(--text-light); transition: var(--transition); text-decoration: none; font-size: 1.1rem; }
        .founder-social a:hover { background: var(--gradient); color: white; transform: translateY(-3px); }
        
        /* Timeline */
        .timeline { position: relative; padding: 30px 0; }
        .timeline::before { content: ''; position: absolute; top: 0; right: 50%; width: 2px; height: 100%; background: var(--border); }
        .timeline-item { display: flex; align-items: center; margin-bottom: 50px; position: relative; }
        .timeline-item:nth-child(odd) { flex-direction: row-reverse; }
        .timeline-content { width: 42%; background: white; padding: 30px; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); transition: var(--transition); }
        .timeline-content:hover { box-shadow: var(--shadow-lg); transform: translateY(-3px); }
        .timeline-dot { width: 20px; height: 20px; background: var(--gold); border-radius: 50%; position: absolute; right: 50%; transform: translateX(50%); border: 4px solid white; box-shadow: 0 0 0 6px var(--gold-light); z-index: 2; }
        .timeline-year { font-size: 1.8rem; font-weight: 900; color: var(--gold); font-family: 'Playfair Display', serif; margin-bottom: 8px; }
        .timeline-content h5 { font-weight: 800; color: var(--dark); margin-bottom: 8px; }
        .timeline-content p { color: var(--text-light); font-size: 0.9rem; margin: 0; }
        
        /* CTA */
        .cta-section { background: var(--gradient); padding: 100px 0; text-align: center; color: white; position: relative; overflow: hidden; }
        .cta-section::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 50%); animation: rotate 20s linear infinite; }
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        .cta-section .container { position: relative; z-index: 1; }
        .btn-light { background: white; color: var(--primary); font-weight: 700; padding: 18px 40px; border-radius: 50px; text-decoration: none; display: inline-block; transition: var(--transition); font-size: 1.05rem; }
        .btn-light:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(0,0,0,0.2); }
        .btn-outline-light { border: 2px solid white; color: white; padding: 18px 40px; border-radius: 50px; text-decoration: none; display: inline-block; transition: var(--transition); font-weight: 700; font-size: 1.05rem; }
        .btn-outline-light:hover { background: white; color: var(--primary); transform: translateY(-3px); }
        
        .footer { background: var(--dark); color: white; padding: 60px 0 20px; text-align: center; }
        .footer a { color: rgba(255,255,255,0.6); text-decoration: none; }
        .footer a:hover { color: white; }
        
        @media (max-width: 1200px) { .values-grid { grid-template-columns: repeat(2, 1fr); } .stats-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 991px) { .timeline::before { right: 20px; } .timeline-item, .timeline-item:nth-child(odd) { flex-direction: column; } .timeline-content { width: 85%; margin-right: 40px; } .timeline-dot { right: 20px; } }
        @media (max-width: 768px) { .hero-about h1 { font-size: 2.2rem; } .values-grid { grid-template-columns: 1fr; } .stats-row { grid-template-columns: 1fr 1fr; } .timeline-content { width: 90%; } }
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
                    <li class="nav-item"><a class="nav-link active" href="about.php">من نحن</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">اتصل بنا</a></li>
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
    
    <!-- Hero -->
    <section class="hero-about" data-aos="fade-up">
        <div class="container">
            <div class="hero-icon">👑</div>
            <h1>À Propos de Nous</h1>
            <p class="subtitle">L'excellence automobile depuis 2010 - Votre partenaire de confiance pour la location de voitures de luxe au Maroc</p>
        </div>
        <div class="hero-wave"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 100"><path fill="#f5f6fa" d="M0,64L80,69C160,75,320,85,480,80C640,75,800,53,960,48C1120,43,1280,53,1360,59L1440,64L1440,100L0,100Z"></path></svg></div>
    </section>
    
    <!-- Stats -->
    <section class="section-dark" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Chiffres Clés</span>
                <h2 class="section-title">Nos Réalisations</h2>
                <p class="section-description">Des chiffres qui témoignent de notre engagement envers l'excellence</p>
            </div>
            <div class="stats-row">
                <div class="stat-item"><div class="stat-number">500<span class="stat-plus">+</span></div><div class="stat-label">Véhicules Premium</div></div>
                <div class="stat-item"><div class="stat-number">15K<span class="stat-plus">+</span></div><div class="stat-label">Clients Satisfaits</div></div>
                <div class="stat-item"><div class="stat-number">14</div><div class="stat-label">Ans d'Excellence</div></div>
                <div class="stat-item"><div class="stat-number">24/7</div><div class="stat-label">Support Client</div></div>
            </div>
        </div>
    </section>
    
    <!-- Notre Histoire - Timeline -->
    <section class="section" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Notre Histoire</span>
                <h2 class="section-title">رحلتنا منذ البداية</h2>
                <p class="section-description">قصة نجاح بدأت بسيارة واحدة وشغف كبير</p>
            </div>
            <div class="timeline">
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-year">2010</div><h5>✨ البداية</h5><p>تأسيس الشركة بـ 5 سيارات فقط في مدينة مراكش. رؤيتنا كانت واضحة: تقديم أفضل خدمة تأجير سيارات فاخرة في المغرب.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-year">2015</div><h5>📈 التوسع</h5><p>افتتاح فروع جديدة في الدار البيضاء وأكادير. توسيع الأسطول إلى 150 سيارة متنوعة.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-year">2020</div><h5>💻 التحول الرقمي</h5><p>إطلاق منصة الحجز الإلكتروني وتطبيق الموبايل. أول شركة تأجير سيارات تتيح الحجز الفوري عبر الإنترنت في المغرب.</p></div></div>
                <div class="timeline-item"><div class="timeline-dot"></div><div class="timeline-content"><div class="timeline-year">2024</div><h5>👑 الريادة</h5><p>أكثر من 500 سيارة فاخرة. شراكات مع أكبر الفنادق والمنتجعات. الوجهة الأولى لتأجير السيارات الفاخرة في المغرب.</p></div></div>
            </div>
        </div>
    </section>
    
    
                             
                   <!-- Team - SAID -->
<section class="section-light team-section" data-aos="fade-up">
    <div class="container">
        <div class="section-header">
            <span class="section-badge">Notre Fondateur</span>
            <h2 class="section-title">المؤسس والمدير العام</h2>
            <p class="section-description">العقل المدبر وراء نجاح Premium Car Rental</p>
        </div>
        <div class="team-spotlight">
            <div class="team-card-founder">
                <div class="founder-image-wrapper">
                    <img src="uploads/cars/S.JPG.jpeg" 
                         alt="SAID"
                         onerror="this.src='https://ui-avatars.com/api/?name=SAID&size=200&background=c9a84c&color=1a1a2e&bold=true&format=png'">
                </div>
                <div class="founder-name">SAID</div>
                <div class="founder-title">Fondateur & Directeur Général</div>
                <p style="color:var(--text-light);margin-top:12px;font-size:0.9rem;">
                    رؤيته وإصراره حوّلا شركة صغيرة إلى أكبر أسطول للسيارات الفاخرة في المغرب
                </p>
                <div class="founder-social">
                    <a href="mailto:s01said@outlook.fr" title="Email"><i class="fas fa-envelope"></i></a>
                    <a href="tel:+212600000000" title="Téléphone"><i class="fas fa-phone"></i></a>
                    <a href="#" title="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" title="Instagram"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
        </div>
    </div>
</section>
    
    <!-- Values -->
    <section class="section" data-aos="fade-up">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Nos Valeurs</span>
                <h2 class="section-title">قيمنا الأساسية</h2>
                <p class="section-description">المبادئ التي توجه كل قرار وكل خدمة نقدمها</p>
            </div>
            <div class="values-grid">
                <div class="value-card">
                    <div class="value-icon bg-primary bg-opacity-10 text-primary"><i class="fas fa-gem"></i></div>
                    <h5>Excellence</h5>
                    <p>نسعى للكمال في كل تفاصيل خدمتنا، من اختيار السيارات إلى استقبال العملاء.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon bg-success bg-opacity-10 text-success"><i class="fas fa-handshake"></i></div>
                    <h5>Confiance</h5>
                    <p>الشفافية المطلقة في الأسعار والعقود. لا رسوم مخفية، لا مفاجآت.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon bg-info bg-opacity-10 text-info"><i class="fas fa-rocket"></i></div>
                    <h5>Innovation</h5>
                    <p>نستثمر في أحدث التقنيات لنجعل تجربة التأجير سلسة وسريعة وآمنة.</p>
                </div>
                <div class="value-card">
                    <div class="value-icon bg-warning bg-opacity-10 text-warning"><i class="fas fa-heart"></i></div>
                    <h5>Passion</h5>
                    <p>حبنا للسيارات ينعكس في كل مركبة نقدمها. نتعامل مع كل سيارة كأنها سيارتنا الخاصة.</p>
                </div>
            </div>
        </div>
    </section>
    
    <!-- CTA -->
    <section class="cta-section">
        <div class="container">
            <h2 class="fw-bold mb-3" data-aos="fade-up" style="font-size:2rem;">Prêt à vivre l'expérience Premium ?</h2>
            <p class="mb-4" data-aos="fade-up" style="opacity:0.9;">انضم إلى أكثر من 15,000 عميل سعيد يثقون بنا</p>
            <div data-aos="fade-up">
                <a href="cars.php" class="btn-light me-3"><i class="fas fa-car me-2"></i> Découvrir nos voitures</a>
                <a href="contact.php" class="btn-outline-light"><i class="fas fa-headset me-2"></i> Nous contacter</a>
            </div>
        </div>
    </section>
    
    <footer class="footer">
        <div class="container">
            <h5 style="color:var(--gold);font-family:'Playfair Display',serif;font-size:1.3rem;">Premium Car Rental</h5>
            <p style="opacity:0.7;">L'Excellence en Location Automobile depuis 2010</p>
            <p class="mt-3" style="opacity:0.5;">&copy; <?php echo date('Y'); ?> Tous droits réservés.</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>AOS.init({duration:800,once:true,offset:30});</script>
</body>
</html>