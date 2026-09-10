<?php
require_once 'includes/config.php';

if (is_logged_in()) { redirect('customer/dashboard.php'); }

$error = ''; $success = ''; $form_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf();
    $form_data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? ''
    ];
    
    $errors = [];
    if (empty($form_data['full_name']) || strlen($form_data['full_name']) < 3) $errors[] = 'Nom complet requis (3 caractères min)';
    if (empty($form_data['email']) || !filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide';
    if (strlen($form_data['password']) < 8) $errors[] = 'Mot de passe (8 caractères min)';
    if ($form_data['password'] !== $form_data['confirm_password']) $errors[] = 'Mots de passe non identiques';
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([strtolower($form_data['email'])]);
            if ($stmt->fetchColumn() > 0) {
                $error = 'Cet email est déjà utilisé';
            } else {
                $hash = password_hash($form_data['password'], PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'customer', 'active', NOW(), NOW())");
                $stmt->execute([$form_data['full_name'], strtolower($form_data['email']), $hash, $form_data['phone']]);
                
                $uid = $pdo->lastInsertId();
                $_SESSION['user_id'] = $uid; $_SESSION['user_name'] = $form_data['full_name'];
                $_SESSION['user_email'] = $form_data['email']; $_SESSION['user_role'] = 'customer';
                $_SESSION['logged_in'] = true; session_regenerate_id(true);
                
                $success = 'Compte créé avec succès !';
                header("Refresh: 1.5; URL=customer/dashboard.php");
            }
        } catch (Exception $e) { $error = 'Une erreur est survenue'; }
    } else { $error = implode('<br>', $errors); }
}
?><!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription | <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --gold: #c9a84c; --gold-dark: #a68a3e; --gold-light: #f5ecd7;
            --dark: #0a0a0a; --dark2: #111; --dark3: #1a1a1a;
            --white: #fff; --gray: #888; --gray-light: #333;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{
            font-family:'Montserrat','Cairo',sans-serif;
            min-height:100vh;display:flex;background:#000;
            color:#fff;
        }
        
        /* LEFT - BMW Image */
        .auth-bg{
            width:55%;position:fixed;top:0;left:0;bottom:0;
            background:url('https://images.unsplash.com/photo-1555215695-3004980ad54e?w=1200&q=90') center/cover no-repeat;
            z-index:0;
        }
        .auth-bg::before{
            content:'';position:absolute;top:0;left:0;right:0;bottom:0;
            background:linear-gradient(to right,rgba(0,0,0,0.3),rgba(0,0,0,0.7),rgba(0,0,0,0.95));
        }
        .bg-content{
            position:absolute;bottom:60px;left:60px;z-index:1;max-width:450px;
        }
        .bg-content .bmw-logo{
            display:flex;align-items:center;gap:12px;margin-bottom:20px;
        }
        .bg-content .bmw-logo .circle{
            width:50px;height:50px;border-radius:50%;
            border:3px solid var(--gold);display:flex;align-items:center;justify-content:center;
            font-size:1.2rem;color:var(--gold);font-family:'Playfair Display',serif;font-weight:900;
        }
        .bg-content .bmw-logo span{
            font-family:'Playfair Display',serif;font-size:1.8rem;color:var(--gold);
            letter-spacing:3px;font-weight:900;
        }
        .bg-content h1{font-size:2.8rem;font-weight:300;letter-spacing:2px;margin-bottom:10px;line-height:1.1;}
        .bg-content h1 strong{font-weight:700;color:var(--gold);}
        .bg-content p{color:rgba(255,255,255,0.5);font-size:0.9rem;letter-spacing:1px;}
        
        /* RIGHT - Form */
        .auth-form{
            width:45%;margin-left:55%;min-height:100vh;
            display:flex;align-items:center;justify-content:center;
            padding:60px;background:var(--dark);position:relative;z-index:1;
        }
        .auth-form::before{
            content:'';position:absolute;top:40px;right:40px;left:40px;bottom:40px;
            border:1px solid rgba(255,255,255,0.06);border-radius:40px;pointer-events:none;
        }
        .form-container{width:100%;max-width:400px;position:relative;z-index:1;}
        
        .form-header{margin-bottom:35px;}
        .form-header .brand{
            font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--gold);
            letter-spacing:4px;text-align:center;margin-bottom:10px;font-weight:900;
        }
        .form-header h2{font-size:1.6rem;font-weight:300;letter-spacing:3px;text-align:center;}
        .form-header .line{width:40px;height:2px;background:var(--gold);margin:15px auto 0;}
        
        .input-group{margin-bottom:16px;}
        .input-group .input-wrap{position:relative;}
        .input-group .input-wrap input{
            width:100%;padding:14px 20px;background:transparent;
            border:none;border-bottom:1px solid rgba(255,255,255,0.2);
            color:#fff;font-size:0.85rem;font-family:'Montserrat',sans-serif;
            letter-spacing:1px;transition:all 0.4s;outline:none;
        }
        .input-group .input-wrap input:focus{border-bottom-color:var(--gold);}
        .input-group .input-wrap input::placeholder{color:rgba(255,255,255,0.3);letter-spacing:2px;font-size:0.75rem;}
        .input-group .input-wrap .underline{
            position:absolute;bottom:0;left:50%;transform:translateX(-50%);
            width:0;height:1px;background:var(--gold);transition:all 0.4s;
        }
        .input-group .input-wrap input:focus~.underline{width:100%;}
        .input-group .input-wrap .icon-right{
            position:absolute;right:0;top:50%;transform:translateY(-50%);
            color:rgba(255,255,255,0.2);font-size:0.85rem;transition:all 0.4s;
        }
        .input-group .input-wrap input:focus~.icon-right{color:var(--gold);}
        
        .btn-submit{
            width:100%;padding:15px;background:transparent;
            border:1px solid rgba(255,255,255,0.3);color:#fff;
            font-size:0.85rem;letter-spacing:4px;text-transform:uppercase;
            cursor:pointer;transition:all 0.4s;font-family:'Montserrat',sans-serif;
            font-weight:400;margin-top:15px;position:relative;overflow:hidden;
        }
        .btn-submit::before{
            content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
            background:var(--gold);transition:all 0.5s;z-index:-1;
        }
        .btn-submit:hover::before{left:0;}
        .btn-submit:hover{border-color:var(--gold);color:#000;letter-spacing:8px;}
        
        .alert-error{
            background:rgba(220,38,38,0.1);border:1px solid rgba(220,38,38,0.3);
            padding:12px 16px;color:#fca5a5;font-size:0.75rem;
            margin-bottom:20px;letter-spacing:1px;text-align:center;line-height:1.6;
        }
        .alert-success{
            background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);
            padding:12px 16px;color:#6ee7b7;font-size:0.75rem;
            margin-bottom:20px;letter-spacing:1px;text-align:center;
        }
        
        .form-footer{text-align:center;margin-top:35px;}
        .form-footer a{
            color:rgba(255,255,255,0.4);text-decoration:none;
            font-size:0.75rem;letter-spacing:2px;transition:all 0.3s;
        }
        .form-footer a:hover{color:var(--gold);}
        
        @media(max-width:991px){
            .auth-bg{display:none;}
            .auth-form{width:100%;margin-left:0;padding:40px 25px;}
            .auth-form::before{top:20px;right:20px;left:20px;bottom:20px;border-radius:25px;}
        }
    </style>
</head>
<body>
    <!-- LEFT - BMW Image -->
    <div class="auth-bg">
        <div class="bg-content">
            <div class="bmw-logo">
                <div class="circle">B</div>
                <span>BMW</span>
            </div>
            <h1>Join the<br><strong>Premium</strong><br>Experience</h1>
            <p>THE ULTIMATE DRIVING MACHINE</p>
        </div>
    </div>
    
    <!-- RIGHT - Form -->
    <div class="auth-form">
        <div class="form-container">
            <div class="form-header">
                <div class="brand">PREMIUM CAR RENTAL</div>
                <h2>INSCRIPTION</h2>
                <div class="line"></div>
            </div>
            
            <?php if($error):?>
            <div class="alert-error"><?php echo $error;?></div>
            <?php endif;?>
            <?php if($success):?>
            <div class="alert-success"><?php echo $success;?></div>
            <?php else:?>
            
            <form method="POST" autocomplete="off">
            <?php echo csrf_field(); ?>
                <div class="input-group">
                    <div class="input-wrap">
                        <input type="text" name="full_name" placeholder="NOM COMPLET" value="<?php echo htmlspecialchars($form_data['full_name']??'');?>" required>
                        <div class="underline"></div>
                        <i class="far fa-user icon-right"></i>
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-wrap">
                        <input type="tel" name="phone" placeholder="TÉLÉPHONE" value="<?php echo htmlspecialchars($form_data['phone']??'');?>" required>
                        <div class="underline"></div>
                        <i class="fas fa-phone icon-right"></i>
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-wrap">
                        <input type="email" name="email" placeholder="EMAIL" value="<?php echo htmlspecialchars($form_data['email']??'');?>" required>
                        <div class="underline"></div>
                        <i class="far fa-envelope icon-right"></i>
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-wrap">
                        <input type="password" name="password" placeholder="MOT DE PASSE (8+ CARACTÈRES)" required>
                        <div class="underline"></div>
                        <i class="fas fa-lock icon-right"></i>
                    </div>
                </div>
                <div class="input-group">
                    <div class="input-wrap">
                        <input type="password" name="confirm_password" placeholder="CONFIRMER LE MOT DE PASSE" required>
                        <div class="underline"></div>
                        <i class="fas fa-check-circle icon-right"></i>
                    </div>
                </div>
                <button type="submit" class="btn-submit">Créer mon compte</button>
            </form>
            <?php endif;?>
            
            <div class="form-footer">
                <a href="login.php">DÉJÀ UN COMPTE ? SE CONNECTER</a>
            </div>
        </div>
    </div>
</body>
</html>