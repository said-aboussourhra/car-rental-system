<?php
/**
 * Premium Car Rental - Luxury Rental Contract
 * Final Version - Colors Preserved on PDF Print
 */
require_once 'includes/config.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit();
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

try {
    $stmt = $pdo->prepare("
        SELECT b.*, 
               c.brand, c.model, c.year, c.color, c.engine_size, c.transmission, c.fuel_type, 
               c.seats, c.doors, c.daily_rate, c.deposit, c.mileage_limit, c.extra_mileage_rate,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone,
               u.address as customer_address
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id
        JOIN users u ON b.user_id = u.id
        WHERE b.id = :bid AND b.user_id = :uid
    ");
    $stmt->execute([':bid' => $booking_id, ':uid' => $_SESSION['user_id']]);
    $contract = $stmt->fetch();
    if (!$contract) { header('Location: customer/my-bookings.php'); exit(); }
} catch (Exception $e) { die('Error'); }

$contract_number = 'CONT-' . date('Ymd') . '-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
$contract_date = new DateTime();
$pickup = new DateTime($contract['pickup_date']);
$return = new DateTime($contract['return_date']);
$deposit = $contract['deposit_amount'] ?? ($contract['daily_rate'] * 3);
$barcode_url = 'https://barcode.tec-it.com/barcode.ashx?data=' . urlencode($contract_number . '|' . $contract['booking_number']) . '&code=Code128&translate-esc=true&dpi=150&imagetype=png';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عقد الإيجار | <?php echo $contract_number; ?> | Premium Car Rental</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,700;0,900;1,400&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --gold: #c9a84c; --gold-dark: #a68a3e; --gold-light: #f5ecd7;
            --black: #1a1a2e; --dark: #2d2d44; --cream: #fdfaf3;
            --text: #3d3d3d; --text-light: #6c757d; --border: #d4c5a9;
            --white: #ffffff; --red: #c0392b;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Cairo', sans-serif;
            background: #e8e0d5;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            min-height: 100vh;
        }
        
        /* ========== ACTION BUTTONS ========== */
        .action-bar {
            position: fixed; top: 20px; left: 20px; z-index: 1000;
            display: flex; gap: 10px; flex-wrap: wrap;
        }
        .action-btn {
            padding: 14px 28px; border-radius: 50px; font-weight: 700; font-size: 0.9rem;
            cursor: pointer; border: none; text-decoration: none;
            display: flex; align-items: center; gap: 8px;
            transition: all 0.3s cubic-bezier(0.4,0,0.2,1);
            font-family: 'Cairo', sans-serif; letter-spacing: 0.5px;
        }
        .btn-print { background: linear-gradient(135deg, #1a1a2e, #2d2d44); color: #c9a84c; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(0,0,0,0.4); }
        .btn-pdf { background: linear-gradient(135deg, #dc2626, #991b1b); color: white; box-shadow: 0 10px 30px rgba(220,38,38,0.3); }
        .btn-pdf:hover { transform: translateY(-3px); box-shadow: 0 15px 40px rgba(220,38,38,0.4); }
        .btn-back { background: white; color: #333; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .btn-back:hover { transform: translateY(-3px); }
        
        /* ========== CONTRACT WRAPPER ========== */
        .contract-wrapper {
            width: 210mm;
            background: white;
            box-shadow: 0 30px 80px rgba(0,0,0,0.25);
            position: relative;
            overflow: hidden;
        }
        
        /* Gold Top Line */
        .gold-line {
            height: 5px;
            background: linear-gradient(90deg, #a68a3e, #c9a84c, #e5c76b, #c9a84c, #a68a3e);
        }
        
        /* Watermark */
        .watermark {
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%) rotate(-15deg);
            font-family: 'Playfair Display', serif;
            font-size: 9rem; color: rgba(201,168,76,0.03);
            font-weight: 900; pointer-events: none;
            letter-spacing: 20px; white-space: nowrap; z-index: 1;
        }
        
        /* ========== PAGE 1 ========== */
        .page-1 {
            min-height: 297mm;
            position: relative;
            padding-bottom: 5mm;
            z-index: 2;
        }
        
        /* Header */
        .header {
            background: #1a1a2e;
            padding: 28px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
            position: relative;
        }
        .header::after {
            content: '';
            position: absolute; bottom: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #c9a84c, transparent);
        }
        
        .header-left { display: flex; align-items: center; gap: 15px; }
        .logo-crown {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, #c9a84c, #e5c76b);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6rem; color: #1a1a2e;
            box-shadow: 0 5px 20px rgba(201,168,76,0.3);
        }
        .company-info h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; font-weight: 900; color: #c9a84c;
            letter-spacing: 2px; margin-bottom: 2px;
        }
        .company-info .slogan {
            font-family: 'Great Vibes', cursive;
            font-size: 1rem; color: rgba(255,255,255,0.5); letter-spacing: 1px;
        }
        
        .header-right { text-align: center; }
        .header-right .doc-type {
            font-family: 'Playfair Display', serif;
            font-size: 1.3rem; font-weight: 900; color: #c9a84c;
            letter-spacing: 4px; margin-bottom: 5px;
        }
        .header-right .doc-type-ar {
            font-size: 0.85rem; color: rgba(255,255,255,0.6); letter-spacing: 2px;
        }
        .header-right .doc-number {
            font-size: 0.78rem; color: rgba(255,255,255,0.4);
            margin-top: 8px; letter-spacing: 1px;
        }
        
        /* Body */
        .contract-body { padding: 30px 35px; position: relative; }
        
        /* Info Row */
        .info-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 15px 20px; background: #fdfaf3;
            border-radius: 10px; margin-bottom: 25px;
            border: 1px solid #d4c5a9; font-size: 0.82rem;
        }
        .info-row .item { display: flex; align-items: center; gap: 6px; }
        .info-row .item i { color: #c9a84c; }
        .info-row .item strong { color: #1a1a2e; }
        
        /* Section Title */
        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.1rem; font-weight: 900; color: #1a1a2e;
            margin-bottom: 18px; padding-bottom: 10px;
            border-bottom: 2px solid #d4c5a9;
            display: flex; align-items: center; gap: 10px;
            letter-spacing: 1px;
        }
        .section-title .section-icon {
            width: 35px; height: 35px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem; color: white;
        }
        
        /* Parties */
        .parties-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .party-card {
            background: white; border-radius: 12px; padding: 20px;
            border: 1px solid #d4c5a9; position: relative;
            border-top: 3px solid #c9a84c;
        }
        .party-card .party-badge {
            position: absolute; top: -12px; right: 15px;
            background: linear-gradient(135deg, #c9a84c, #a68a3e);
            color: white; padding: 4px 15px; border-radius: 20px;
            font-size: 0.65rem; font-weight: 700; letter-spacing: 1.5px;
        }
        .party-card h4 { font-size: 0.95rem; color: #1a1a2e; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; font-weight: 800; }
        .party-card h4 i { color: #c9a84c; }
        .party-card p { font-size: 0.8rem; color: #555; margin-bottom: 3px; line-height: 1.9; }
        .party-card p strong { color: #1a1a2e; }
        
        /* Vehicle Specs */
        .vehicle-specs { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 25px; }
        .spec-item {
            text-align: center; padding: 16px 10px; background: #fdfaf3;
            border-radius: 10px; border: 1px solid #d4c5a9;
        }
        .spec-item .spec-icon { font-size: 1.3rem; color: #c9a84c; margin-bottom: 6px; }
        .spec-item .spec-label { font-size: 0.62rem; color: #999; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 2px; }
        .spec-item .spec-value { font-weight: 700; color: #1a1a2e; font-size: 0.82rem; }
        
        /* Pricing Table */
        .pricing-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; border-radius: 10px; overflow: hidden; }
        .pricing-table thead th {
            background: #1a1a2e; color: #c9a84c; padding: 13px 15px;
            font-size: 0.75rem; font-weight: 700; text-align: center;
            letter-spacing: 1px; text-transform: uppercase;
        }
        .pricing-table tbody td {
            padding: 13px 15px; text-align: center; border-bottom: 1px solid #d4c5a9;
            font-size: 0.85rem; background: white;
        }
        .pricing-table tbody tr:nth-child(even) td { background: #fdfcf9; }
        .pricing-table .total-row td {
            background: #f5ecd7 !important; font-weight: 900;
            font-size: 0.95rem; color: #1a1a2e;
        }
        
        /* Clauses */
        .clauses { margin-bottom: 25px; }
        .clause-item {
            display: flex; gap: 10px; margin-bottom: 5px; font-size: 0.76rem;
            color: #555; padding: 8px 14px; background: #fdfaf3;
            border-radius: 6px; border-right: 3px solid #c9a84c;
        }
        .clause-num {
            width: 22px; height: 22px; border-radius: 50%;
            background: #c9a84c; color: white;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 0.65rem; flex-shrink: 0;
        }
        
        /* Signatures */
        .signatures { display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; margin-top: 25px; padding-top: 20px; border-top: 2px solid #d4c5a9; align-items: end; }
        .sig-box { text-align: center; }
        .sig-line { width: 160px; border-bottom: 2px solid #e0e0e0; margin: 30px auto 8px; }
        .sig-box h6 { font-size: 0.85rem; color: #1a1a2e; font-weight: 700; }
        .sig-box p { font-size: 0.7rem; color: #999; }
        .barcode-col { text-align: center; }
        .barcode-col img { max-width: 140px; }
        .barcode-num { font-size: 0.7rem; color: #1a1a2e; font-weight: 600; letter-spacing: 1px; margin-top: 4px; }
        
        /* Page 1 Footer */
        .page-1-footer {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: #1a1a2e; padding: 14px 35px;
            display: flex; justify-content: space-between; align-items: center;
            color: rgba(255,255,255,0.5); font-size: 0.7rem;
        }
        .page-1-footer strong { color: #c9a84c; }
        .page-1-footer .seal { font-family: 'Great Vibes', cursive; font-size: 1.1rem; color: #c9a84c; }
        .page-number { font-size: 0.7rem; color: rgba(255,255,255,0.35); }
        
        /* ========== PAGE 2 ========== */
        .page-2 {
            min-height: 297mm;
            page-break-before: always;
            background: white;
            padding: 30px 35px;
            position: relative;
        }
        
        .page-2-header {
            text-align: center; margin-bottom: 30px;
            padding-bottom: 15px; border-bottom: 2px solid #d4c5a9;
        }
        .page-2-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem; font-weight: 900; color: #1a1a2e;
            letter-spacing: 2px;
        }
        .page-2-header p { font-size: 0.9rem; color: #6c757d; }
        
        .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .detail-card {
            background: #fdfaf3; border-radius: 10px; padding: 20px;
            border: 1px solid #d4c5a9;
        }
        .detail-card h5 { font-size: 0.95rem; color: #1a1a2e; margin-bottom: 12px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
        .detail-card h5 i { color: #c9a84c; }
        .detail-card .detail-row { display: flex; justify-content: space-between; padding: 7px 0; font-size: 0.82rem; border-bottom: 1px dotted #e0e0e0; }
        .detail-card .detail-row:last-child { border-bottom: none; }
        .detail-card .detail-row .dlabel { color: #888; }
        .detail-card .detail-row .dvalue { font-weight: 600; color: #1a1a2e; }
        
        .insurance-box {
            background: #fef3c7; border: 2px solid #c9a84c; border-radius: 10px;
            padding: 20px; margin-bottom: 25px; text-align: center;
        }
        .insurance-box h5 { font-weight: 800; color: #1a1a2e; margin-bottom: 8px; }
        .insurance-box p { font-size: 0.82rem; color: #555; margin: 0; line-height: 1.8; }
        
        .checklist { margin-bottom: 25px; }
        .checklist h5 { font-size: 0.95rem; color: #1a1a2e; margin-bottom: 12px; font-weight: 800; }
        .checklist-item {
            display: flex; align-items: center; gap: 10px; padding: 8px 0;
            font-size: 0.82rem; color: #555; border-bottom: 1px solid #f0f0f0;
        }
        .checklist-item i { color: #c9a84c; font-size: 0.9rem; }
        .check-box {
            width: 22px; height: 22px; border: 2px solid #ccc; border-radius: 4px;
            flex-shrink: 0;
        }
        
        .page-2-footer {
            position: absolute; bottom: 0; left: 0; right: 0;
            background: #1a1a2e; padding: 14px 35px;
            display: flex; justify-content: space-between; align-items: center;
            color: rgba(255,255,255,0.5); font-size: 0.7rem;
        }
        .page-2-footer strong { color: #c9a84c; }
        
        /* ========== PRINT STYLES - SAVE PDF COLORS ========== */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            body { background: white; padding: 0; margin: 0; }
            .action-bar { display: none !important; }
            .contract-wrapper { box-shadow: none; width: 100%; margin: 0; }
            .page-1 { min-height: auto; }
            .page-2 { page-break-before: always; min-height: auto; }
            
            /* Force all colors to print */
            .header, .page-1-footer, .page-2-footer, .pricing-table thead th { background: #1a1a2e !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            .gold-line { background: linear-gradient(90deg, #a68a3e, #c9a84c, #e5c76b, #c9a84c, #a68a3e) !important; -webkit-print-color-adjust: exact !important; }
            .company-info h1, .header-right .doc-type, .page-1-footer strong, .page-2-footer strong, .header-right .doc-type, .pricing-table thead th { color: #c9a84c !important; }
            .logo-crown { background: linear-gradient(135deg, #c9a84c, #e5c76b) !important; -webkit-print-color-adjust: exact !important; }
            .party-card { border-top: 3px solid #c9a84c !important; }
            .section-title .section-icon, .clause-num { background: #c9a84c !important; -webkit-print-color-adjust: exact !important; }
            .pricing-table .total-row td { background: #f5ecd7 !important; -webkit-print-color-adjust: exact !important; }
            .insurance-box { background: #fef3c7 !important; border: 2px solid #c9a84c !important; -webkit-print-color-adjust: exact !important; }
            
            @page { size: A4; margin: 0; }
            .no-page-break { page-break-inside: avoid; }
        }
        
        /* ========== RESPONSIVE ========== */
        @media screen and (max-width: 768px) {
            .contract-wrapper { width: 100%; }
            .header { flex-direction: column; text-align: center; gap: 12px; padding: 20px; }
            .parties-grid { grid-template-columns: 1fr; }
            .vehicle-specs { grid-template-columns: repeat(2, 1fr); }
            .signatures { grid-template-columns: 1fr; gap: 15px; }
            .contract-body { padding: 20px; }
            .info-row { flex-direction: column; gap: 8px; }
            .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Action Buttons -->
    <div class="action-bar">
        <button onclick="window.print()" class="action-btn btn-print">
            <i class="fas fa-print"></i> طباعة
        </button>
        <button onclick="window.print()" class="action-btn btn-pdf">
            <i class="fas fa-file-pdf"></i> حفظ PDF
        </button>
        <a href="customer/my-bookings.php" class="action-btn btn-back">
            <i class="fas fa-arrow-right"></i> العودة
        </a>
    </div>
    
    <!-- Contract -->
    <div class="contract-wrapper">
        
        <!-- ==================== PAGE 1 ==================== -->
        <div class="page-1">
            <div class="gold-line"></div>
            <div class="watermark">PREMIUM</div>
            
            <div class="header">
                <div class="header-left">
                    <div class="logo-crown"><i class="fas fa-crown"></i></div>
                    <div class="company-info">
                        <h1>Premium Car Rental</h1>
                        <div class="slogan">L'Excellence en Location Automobile</div>
                    </div>
                </div>
                <div class="header-right">
                    <div class="doc-type">CONTRAT DE LOCATION</div>
                    <div class="doc-type-ar">عقد إيجار سيارة</div>
                    <div class="doc-number">N° <?php echo $contract_number; ?></div>
                </div>
            </div>
            
            <div class="contract-body">
                <div class="info-row">
                    <span class="item"><i class="far fa-calendar-alt"></i> <strong>تاريخ العقد:</strong> <?php echo $contract_date->format('d/m/Y'); ?></span>
                    <span class="item"><i class="fas fa-hashtag"></i> <strong>رقم الحجز:</strong> <?php echo $contract['booking_number']; ?></span>
                    <span class="item"><i class="fas fa-clock"></i> <strong>المدة:</strong> <?php echo $contract['total_days']; ?> أيام</span>
                </div>
                
                <div class="section-title">
                    <div class="section-icon" style="background:#c9a84c;"><i class="fas fa-users"></i></div>
                    Parties Contractantes - الأطراف المتعاقدة
                </div>
                <div class="parties-grid no-page-break">
                    <div class="party-card">
                        <div class="party-badge">BAILLEUR</div>
                        <h4><i class="fas fa-building"></i> المؤجر (Loueur)</h4>
                        <p><strong>Premium Car Rental SARL</strong></p>
                        <p>📍 Avenue Mohammed V, Immeuble Atlas, 3ème Étage, Guéliz, 40000 Marrakech</p>
                        <p>📞 +212 5 24 30 00 00 | +212 6 00 00 00 00</p>
                        <p>✉️ contact@premiumcarrental.ma</p>
                        <p style="margin-top:8px;font-size:0.68rem;color:#999;">RC: 45678 | PAT: 98765 | IF: 87654321 | ICE: 009876543210</p>
                    </div>
                    <div class="party-card">
                        <div class="party-badge">PRENEUR</div>
                        <h4><i class="fas fa-user"></i> المستأجر (Locataire)</h4>
                        <p><strong><?php echo htmlspecialchars($contract['customer_name']); ?></strong></p>
                        <p>📞 <?php echo htmlspecialchars($contract['customer_phone']); ?></p>
                        <p>✉️ <?php echo htmlspecialchars($contract['customer_email']); ?></p>
                        <?php if($contract['customer_address']): ?><p>📍 <?php echo htmlspecialchars($contract['customer_address']); ?></p><?php endif; ?>
                    </div>
                </div>
                
                <div class="section-title">
                    <div class="section-icon" style="background:#1a1a2e;"><i class="fas fa-car-side"></i></div>
                    Véhicule - معلومات السيارة
                </div>
                <div class="vehicle-specs no-page-break">
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-car"></i></div><div class="spec-label">Marque/Modèle</div><div class="spec-value"><?php echo htmlspecialchars($contract['brand'].' '.$contract['model']); ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-calendar"></i></div><div class="spec-label">Année</div><div class="spec-value"><?php echo $contract['year']; ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-palette"></i></div><div class="spec-label">Couleur</div><div class="spec-value"><?php echo htmlspecialchars($contract['color']??'N/C'); ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-chair"></i></div><div class="spec-label">Places</div><div class="spec-value"><?php echo $contract['seats']; ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-cog"></i></div><div class="spec-label">Transmission</div><div class="spec-value"><?php echo $contract['transmission']=='automatic'?'Auto':'Manuelle'; ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-gas-pump"></i></div><div class="spec-label">Carburant</div><div class="spec-value"><?php echo $contract['fuel_type']; ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-tachometer-alt"></i></div><div class="spec-label">Moteur</div><div class="spec-value"><?php echo htmlspecialchars($contract['engine_size']??'N/C'); ?></div></div>
                    <div class="spec-item"><div class="spec-icon"><i class="fas fa-road"></i></div><div class="spec-label">Km/Jour</div><div class="spec-value"><?php echo $contract['mileage_limit']; ?> km</div></div>
                </div>
                
                <div class="section-title">
                    <div class="section-icon" style="background:#a68a3e;"><i class="fas fa-calculator"></i></div>
                    Conditions Financières - الشروط المالية
                </div>
                <table class="pricing-table no-page-break">
                    <thead><tr><th>Date Début</th><th>Date Fin</th><th>Durée</th><th>Tarif/Jour</th><th>Caution</th><th>Total TTC</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?php echo $pickup->format('d/m/Y H:i'); ?></td>
                            <td><?php echo $return->format('d/m/Y H:i'); ?></td>
                            <td><strong><?php echo $contract['total_days']; ?> jours</strong></td>
                            <td><?php echo number_format($contract['daily_rate'],2); ?> MAD</td>
                            <td style="color:#c0392b;"><?php echo number_format($deposit,2); ?> MAD</td>
                            <td><strong style="font-size:1rem;"><?php echo number_format($contract['total_amount'],2); ?> MAD</strong></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="2">Sous-total: <?php echo number_format($contract['subtotal'],2); ?> MAD</td>
                            <td colspan="2">Extras: <?php echo number_format($contract['extras_charges']??0,2); ?> MAD</td>
                            <td>TVA: <?php echo number_format($contract['tax_amount'],2); ?> MAD</td>
                            <td><strong>NET À PAYER</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="section-title">
                    <div class="section-icon" style="background:#c0392b;"><i class="fas fa-gavel"></i></div>
                    Clauses du Contrat - شروط العقد
                </div>
                <div class="clauses no-page-break">
                    <div class="clause-item"><span class="clause-num">1</span> Présenter pièce d'identité nationale et permis de conduire valide. يجب تقديم بطاقة هوية وطنية ورخصة سياقة سارية المفعول.</div>
                    <div class="clause-item"><span class="clause-num">2</span> Véhicule assuré tous risques avec franchise à charge du locataire. السيارة مؤمنة تأميناً شاملاً مع تحمل المستأجر للفرنشايز.</div>
                    <div class="clause-item"><span class="clause-num">3</span> Interdiction de fumer - Amende 500 MAD. يمنع التدخين - غرامة 500 درهم.</div>
                    <div class="clause-item"><span class="clause-num">4</span> Restituer avec même niveau de carburant. إرجاع السيارة بنفس مستوى الوقود.</div>
                    <div class="clause-item"><span class="clause-num">5</span> Km limite: <?php echo $contract['mileage_limit']; ?> km/jour. Supplément: <?php echo number_format($contract['extra_mileage_rate']??0.50,2); ?> MAD/km.</div>
                    <div class="clause-item"><span class="clause-num">6</span> Interdiction de sortir du Maroc sans autorisation écrite. يمنع الخروج خارج المغرب بدون إذن.</div>
                    <div class="clause-item"><span class="clause-num">7</span> Tout retard entraîne facturation d'une journée supplémentaire. أي تأخير يحتسب يوم إضافي.</div>
                </div>
                
                <div class="signatures">
                    <div class="sig-box">
                        <h6>المؤجر (Le Loueur)</h6>
                        <div class="sig-line"></div>
                        <p>Premium Car Rental SARL</p>
                        <p>Cachet & Signature</p>
                    </div>
                    <div class="barcode-col">
                        <img src="<?php echo $barcode_url; ?>" alt="Barcode" onerror="this.style.display='none'">
                        <div class="barcode-num"><?php echo $contract_number; ?></div>
                        <p style="font-size:0.6rem;color:#999;">Scan to verify</p>
                    </div>
                    <div class="sig-box">
                        <h6>المستأجر (Le Locataire)</h6>
                        <div class="sig-line"></div>
                        <p><?php echo htmlspecialchars($contract['customer_name']); ?></p>
                        <p>Lu et approuvé</p>
                    </div>
                </div>
            </div>
            
            <div class="page-1-footer">
                <span><strong>Premium Car Rental SARL</strong> | Avenue Mohammed V, Guéliz, Marrakech</span>
                <span class="seal">Premium</span>
                <span class="page-number">Page 1/2</span>
            </div>
        </div>
        
        <!-- ==================== PAGE 2 ==================== -->
        <div class="page-2">
            <div class="gold-line" style="margin:-30px -35px 30px;"></div>
            
            <div class="page-2-header">
                <h2>ANNEXE - ملحق العقد</h2>
                <p>Informations Complémentaires - معلومات إضافية</p>
            </div>
            
            <div class="details-grid">
                <div class="detail-card">
                    <h5><i class="fas fa-shield-alt"></i> Assurance - التأمين</h5>
                    <div class="detail-row"><span class="dlabel">Type</span><span class="dvalue">Tous Risques</span></div>
                    <div class="detail-row"><span class="dlabel">Franchise</span><span class="dvalue"><?php echo number_format($deposit,2); ?> MAD</span></div>
                    <div class="detail-row"><span class="dlabel">Assistance 24/7</span><span class="dvalue">Incluse ✅</span></div>
                    <div class="detail-row"><span class="dlabel">Vol</span><span class="dvalue">Couvert ✅</span></div>
                    <div class="detail-row"><span class="dlabel">Incendie</span><span class="dvalue">Couvert ✅</span></div>
                </div>
                <div class="detail-card">
                    <h5><i class="fas fa-info-circle"></i> Informations Pratiques</h5>
                    <div class="detail-row"><span class="dlabel">Contact Urgence</span><span class="dvalue">+212 6 00 00 00 00</span></div>
                    <div class="detail-row"><span class="dlabel">Heures Service</span><span class="dvalue">24h/24 - 7j/7</span></div>
                    <div class="detail-row"><span class="dlabel">Site Web</span><span class="dvalue">www.premiumcarrental.ma</span></div>
                    <div class="detail-row"><span class="dlabel">Email</span><span class="dvalue">contact@premiumcarrental.ma</span></div>
                    <div class="detail-row"><span class="dlabel">Adresse</span><span class="dvalue">Avenue Mohammed V, Marrakech</span></div>
                </div>
            </div>
            
            <div class="insurance-box">
                <h5><i class="fas fa-exclamation-triangle" style="color:#c9a84c;margin-left:8px;"></i> Note Importante - ملاحظة هامة</h5>
                <p>La caution de <strong><?php echo number_format($deposit,2); ?> MAD</strong> sera restituée intégralement à la fin de la location si le véhicule est retourné dans l'état initial. En cas de dommage, les frais de réparation seront déduits de la caution. Le locataire est responsable des contraventions et amendes durant la période de location.</p>
                <p style="margin-top:10px;">سيتم إرجاع مبلغ التأمين <strong><?php echo number_format($deposit,2); ?> درهم</strong> بالكامل عند إرجاع السيارة بحالتها الأصلية. في حالة حدوث أضرار، سيتم خصم تكاليف الإصلاح من التأمين. المستأجر مسؤول عن المخالفات والغرامات خلال فترة الإيجار.</p>
            </div>
            
            <div class="checklist">
                <h5><i class="fas fa-clipboard-check"></i> Checklist de Départ - قائمة التحقق</h5>
                <div class="checklist-item"><span class="check-box"></span> Contrôle extérieur du véhicule - فحص خارجي للسيارة</div>
                <div class="checklist-item"><span class="check-box"></span> Contrôle intérieur - فحص داخلي</div>
                <div class="checklist-item"><span class="check-box"></span> Niveau de carburant noté: __</div>
                <div class="checklist-item"><span class="check-box"></span> Kilométrage départ: __ km</div>
                <div class="checklist-item"><span class="check-box"></span> Documents remis: Carte Grise, Assurance, Contrat</div>
                <div class="checklist-item"><span class="check-box"></span> État des lieux signé par les deux parties</div>
            </div>
            
            <div class="signatures" style="margin-top:30px;">
                <div class="sig-box">
                    <h6>المؤجر (Le Loueur)</h6>
                    <div class="sig-line"></div>
                    <p>Premium Car Rental SARL</p>
                </div>
                <div class="barcode-col">
                    <img src="<?php echo $barcode_url; ?>" alt="Barcode" onerror="this.style.display='none'">
                    <div class="barcode-num"><?php echo $contract_number; ?></div>
                </div>
                <div class="sig-box">
                    <h6>المستأجر (Le Locataire)</h6>
                    <div class="sig-line"></div>
                    <p><?php echo htmlspecialchars($contract['customer_name']); ?></p>
                </div>
            </div>
            
            <div class="page-2-footer">
                <span><strong>Premium Car Rental SARL</strong> | Avenue Mohammed V, Guéliz, Marrakech</span>
                <span class="seal">Premium</span>
                <span class="page-number">Page 2/2</span>
            </div>
        </div>
        
    </div>
</body>
</html>