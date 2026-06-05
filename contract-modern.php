<?php
/**
 * Premium Car Rental - Rental Contract
 * Modern Edition - Clean Design
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
               c.brand, c.model, c.year, c.color, c.engine_size, c.transmission, c.fuel_type, c.seats, c.doors,
               c.daily_rate, c.deposit, c.mileage_limit, c.extra_mileage_rate,
               u.full_name as customer_name, u.email as customer_email, u.phone as customer_phone, u.address as customer_address
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
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>عقد الإيجار | <?php echo $contract_number; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary: #667eea; --primary-dark: #4f5fd6; --primary-light: #eef0ff;
            --dark: #1a1a2e; --text: #333; --text-light: #6c757d; --border: #e0e0e0;
            --white: #ffffff; --light: #f8f9fa;
            --radius: 12px; --radius-lg: 16px; --shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', 'Cairo', sans-serif; background: #f0f2f5;
            display: flex; justify-content: center; padding: 20px;
        }
        
        .no-print { position: fixed; top: 20px; left: 20px; z-index: 1000; display: flex; gap: 10px; }
        .no-print .btn {
            padding: 12px 24px; border-radius: 50px; font-weight: 600; font-size: 0.9rem;
            cursor: pointer; border: none; text-decoration: none; display: flex; align-items: center; gap: 8px;
            transition: all 0.3s; font-family: 'Cairo', sans-serif;
        }
        .btn-print { background: var(--dark); color: white; }
        .btn-print:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
        .btn-back { background: white; color: var(--dark); border: 2px solid var(--border); }
        .btn-back:hover { transform: translateY(-3px); }
        
        .contract-wrapper {
            width: 210mm; background: white; box-shadow: var(--shadow);
            border-radius: var(--radius-lg); overflow: hidden;
        }
        
        .header {
            background: var(--dark); padding: 30px 40px; color: white;
            display: flex; justify-content: space-between; align-items: center;
        }
        .header-left h1 { font-size: 1.3rem; font-weight: 800; color: var(--primary-light); }
        .header-left p { font-size: 0.8rem; opacity: 0.6; }
        .header-right { text-align: right; }
        .header-right .contract-num { font-size: 1rem; font-weight: 700; color: white; }
        .header-right .contract-label { font-size: 0.7rem; opacity: 0.5; text-transform: uppercase; letter-spacing: 2px; }
        
        .body { padding: 35px 40px; }
        
        .section { margin-bottom: 25px; }
        .section-title {
            font-size: 0.85rem; font-weight: 700; color: var(--primary); text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px;
            padding-bottom: 10px; border-bottom: 2px solid #f0f0f0;
        }
        .section-title i { font-size: 0.9rem; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .info-card { background: var(--light); border-radius: var(--radius); padding: 18px 20px; }
        .info-card h5 { font-size: 0.85rem; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .info-card p { font-size: 0.8rem; color: var(--text-light); margin-bottom: 4px; }
        .info-card p strong { color: var(--dark); }
        
        .specs-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .spec-cell {
            background: var(--light); border-radius: 8px; padding: 14px; text-align: center;
        }
        .spec-cell .spec-icon { font-size: 1.1rem; color: var(--primary); margin-bottom: 5px; }
        .spec-cell .spec-label { font-size: 0.65rem; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; }
        .spec-cell .spec-value { font-weight: 700; color: var(--dark); font-size: 0.85rem; margin-top: 3px; }
        
        .table { width: 100%; border-collapse: collapse; }
        .table th {
            background: var(--dark); color: white; padding: 12px 16px; font-size: 0.75rem;
            font-weight: 600; text-align: center; text-transform: uppercase; letter-spacing: 0.5px;
        }
        .table td { padding: 12px 16px; text-align: center; border-bottom: 1px solid var(--border); font-size: 0.85rem; }
        .table .total-row td { background: var(--primary-light); font-weight: 700; color: var(--primary); font-size: 0.95rem; }
        
        .clauses { margin-top: 20px; }
        .clause-item {
            display: flex; gap: 10px; margin-bottom: 6px; font-size: 0.78rem; color: var(--text-light);
            padding: 8px 14px; background: var(--light); border-radius: 6px; border-left: 3px solid var(--primary);
        }
        .clause-item .num {
            width: 24px; height: 24px; border-radius: 50%; background: var(--primary); color: white;
            display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.7rem; flex-shrink: 0;
        }
        
        .sign-row { display: grid; grid-template-columns: 1fr auto 1fr; gap: 20px; margin-top: 30px; padding-top: 20px; border-top: 2px solid var(--border); align-items: end; }
        .sign-box { text-align: center; }
        .sign-line { width: 150px; border-bottom: 1px dashed #ccc; margin: 25px auto 8px; }
        .sign-box h6 { font-size: 0.85rem; color: var(--dark); font-weight: 600; }
        .sign-box p { font-size: 0.7rem; color: var(--text-light); }
        
        .barcode-box { text-align: center; }
        .barcode-box img { max-width: 130px; }
        .barcode-box .code { font-size: 0.7rem; color: var(--dark); font-weight: 600; letter-spacing: 1px; }
        
        .footer {
            background: var(--dark); padding: 15px 40px; text-align: center;
            color: rgba(255,255,255,0.5); font-size: 0.7rem;
        }
        .footer strong { color: white; }
        
        @media print { body { background: white; padding: 0; } .no-print { display: none !important; } .contract-wrapper { box-shadow: none; border-radius: 0; } @page { size: A4; margin: 0; } }
        @media (max-width: 768px) { .contract-wrapper { width: 100%; } .header { flex-direction: column; text-align: center; } .info-grid { grid-template-columns: 1fr; } .specs-row { grid-template-columns: repeat(2, 1fr); } .sign-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print"><i class="fas fa-print"></i> طباعة</button>
        <button onclick="window.print()" class="btn btn-print" style="background:#dc2626;"><i class="fas fa-file-pdf"></i> PDF</button>
        <a href="customer/my-bookings.php" class="btn btn-back"><i class="fas fa-arrow-right"></i> عودة</a>
    </div>
    
    <div class="contract-wrapper">
        <div class="header">
            <div class="header-left">
                <h1>🚗 Premium Car Rental</h1>
                <p>Location de Voitures de Luxe</p>
            </div>
            <div class="header-right">
                <div class="contract-label">CONTRAT DE LOCATION</div>
                <div class="contract-num"><?php echo $contract_number; ?></div>
            </div>
        </div>
        
        <div class="body">
            <div class="section">
                <div class="section-title"><i class="fas fa-info-circle"></i> معلومات العقد</div>
                <div class="info-grid">
                    <div class="info-card">
                        <h5><i class="fas fa-building" style="color:var(--primary);margin-left:6px;"></i> المؤجر</h5>
                        <p><strong>Premium Car Rental SARL</strong></p>
                        <p>Avenue Mohammed V, Guéliz, Marrakech</p>
                        <p>Tél: +212 5 24 30 00 00</p>
                    </div>
                    <div class="info-card">
                        <h5><i class="fas fa-user" style="color:var(--primary);margin-left:6px;"></i> المستأجر</h5>
                        <p><strong><?php echo htmlspecialchars($contract['customer_name']); ?></strong></p>
                        <p>Tél: <?php echo htmlspecialchars($contract['customer_phone']); ?></p>
                        <p>Email: <?php echo htmlspecialchars($contract['customer_email']); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title"><i class="fas fa-car-side"></i> السيارة</div>
                <div class="specs-row">
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-car"></i></div><div class="spec-label">Marque</div><div class="spec-value"><?php echo htmlspecialchars($contract['brand'].' '.$contract['model']); ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-calendar"></i></div><div class="spec-label">Année</div><div class="spec-value"><?php echo $contract['year']; ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-palette"></i></div><div class="spec-label">Couleur</div><div class="spec-value"><?php echo htmlspecialchars($contract['color']??'N/C'); ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-cog"></i></div><div class="spec-label">Transmission</div><div class="spec-value"><?php echo $contract['transmission']=='automatic'?'Auto':'Manuelle'; ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-gas-pump"></i></div><div class="spec-label">Carburant</div><div class="spec-value"><?php echo $contract['fuel_type']; ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-chair"></i></div><div class="spec-label">Places</div><div class="spec-value"><?php echo $contract['seats']; ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-tachometer-alt"></i></div><div class="spec-label">Moteur</div><div class="spec-value"><?php echo htmlspecialchars($contract['engine_size']??'N/C'); ?></div></div>
                    <div class="spec-cell"><div class="spec-icon"><i class="fas fa-road"></i></div><div class="spec-label">Km/Jour</div><div class="spec-value"><?php echo $contract['mileage_limit']; ?> km</div></div>
                </div>
            </div>
            
            <div class="section">
                <div class="section-title"><i class="fas fa-calculator"></i> التفاصيل المالية</div>
                <table class="table">
                    <thead><tr><th>Début</th><th>Fin</th><th>Jours</th><th>Tarif/J</th><th>Caution</th><th>Total TTC</th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?php echo $pickup->format('d/m/Y H:i'); ?></td>
                            <td><?php echo $return->format('d/m/Y H:i'); ?></td>
                            <td><strong><?php echo $contract['total_days']; ?></strong></td>
                            <td><?php echo number_format($contract['daily_rate'],2); ?> MAD</td>
                            <td><?php echo number_format($deposit,2); ?> MAD</td>
                            <td><strong><?php echo number_format($contract['total_amount'],2); ?> MAD</strong></td>
                        </tr>
                        <tr class="total-row"><td colspan="2">Sous-total: <?php echo number_format($contract['subtotal'],2); ?> MAD</td><td colspan="2">Extras: <?php echo number_format($contract['extras_charges']??0,2); ?> MAD</td><td>TVA: <?php echo number_format($contract['tax_amount'],2); ?> MAD</td><td>NET: <?php echo number_format($contract['total_amount'],2); ?> MAD</td></tr>
                    </tbody>
                </table>
            </div>
            
            <div class="section">
                <div class="section-title"><i class="fas fa-gavel"></i> الشروط</div>
                <div class="clauses">
                    <div class="clause-item"><span class="num">1</span> Présenter pièce d'identité et permis de conduire valides.</div>
                    <div class="clause-item"><span class="num">2</span> Véhicule assuré tous risques avec franchise à charge du locataire.</div>
                    <div class="clause-item"><span class="num">3</span> Interdiction de fumer - Amende 500 MAD.</div>
                    <div class="clause-item"><span class="num">4</span> Restituer avec même niveau de carburant.</div>
                    <div class="clause-item"><span class="num">5</span> Km supplémentaire: <?php echo number_format($contract['extra_mileage_rate']??0.50,2); ?> MAD/km.</div>
                    <div class="clause-item"><span class="num">6</span> Interdiction de sortir du Maroc sans autorisation écrite.</div>
                </div>
            </div>
            
            <div class="sign-row">
                <div class="sign-box">
                    <h6>Le Loueur</h6>
                    <div class="sign-line"></div>
                    <p>Premium Car Rental</p>
                </div>
                <div class="barcode-box">
                    <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($contract_number); ?>&code=Code128&imagetype=png" alt="" onerror="this.style.display='none'">
                    <div class="code"><?php echo $contract_number; ?></div>
                </div>
                <div class="sign-box">
                    <h6>Le Locataire</h6>
                    <div class="sign-line"></div>
                    <p><?php echo htmlspecialchars($contract['customer_name']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <strong>Premium Car Rental SARL</strong> | RC: 45678 | IF: 87654321 | Généré le <?php echo $contract_date->format('d/m/Y à H:i'); ?>
        </div>
    </div>
</body>
</html>