<?php
/**
 * API: معالجة الدفع (تحويل بنكي / نقداً / بطاقة)
 * الاستدعاء: POST api/process-payment.php  (JSON: {booking_id, payment_method, bank, transaction_ref})
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
}

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'يجب تسجيل الدخول أولاً', 'requires_login' => true], 401);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$booking_id = intval($input['booking_id'] ?? 0);
$payment_method = strtolower(trim($input['payment_method'] ?? 'cash'));
$bank = trim($input['bank'] ?? '');
$transaction_ref = trim($input['transaction_ref'] ?? '');

$allowed_methods = ['cash', 'bank_transfer', 'card', 'paypal'];
if (!in_array($payment_method, $allowed_methods)) {
    json_response(['success' => false, 'message' => 'طريقة الدفع غير مدعومة'], 400);
}

try {
    $user_id = (int)$_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => $booking_id, ':uid' => $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(['success' => false, 'message' => 'الحجز غير موجود'], 404);
    }

    if ($booking['payment_status'] === 'paid') {
        json_response(['success' => true, 'already_paid' => true, 'message' => 'الحجز مدفوع مسبقاً']);
    }

    if (in_array($booking['booking_status'], ['cancelled', 'completed'])) {
        json_response(['success' => false, 'message' => 'لا يمكن دفع حجز ملغى أو مكتمل'], 400);
    }

    $pdo->beginTransaction();

    // تحديث حالة الدفع
    $txn = ($transaction_ref !== '' ? $transaction_ref : 'TXN-' . strtoupper(bin2hex(random_bytes(6))));
    $pdo->prepare("UPDATE bookings SET payment_status = 'paid', booking_status = IF(booking_status = 'pending', 'confirmed', booking_status) WHERE id = :id")
        ->execute([':id' => $booking_id]);

    // تسجيل عملية الدفع
    $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_method, transaction_id, payment_status, payment_date)
                   VALUES (:bid, :amt, :method, :txn, 'success', NOW())")
        ->execute([
            ':bid' => $booking_id,
            ':amt' => $booking['total_amount'],
            ':method' => $payment_method . ($bank !== '' ? ':' . $bank : ''),
            ':txn' => $txn
        ]);

    // إنشاء الفاتورة إن لم تكن موجودة
    $invStmt = $pdo->prepare("SELECT invoice_number FROM invoices WHERE booking_id = :bid LIMIT 1");
    $invStmt->execute([':bid' => $booking_id]);
    $invoice = $invStmt->fetch();

    if (!$invoice) {
        $invoice_number = generate_invoice_number();
        $pdo->prepare("INSERT INTO invoices (booking_id, invoice_number, invoice_date, due_date, amount, tax_amount, total_amount, status)
                       VALUES (:bid, :inv, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), :amt, :tax, :total, 'paid')")
            ->execute([
                ':bid' => $booking_id,
                ':inv' => $invoice_number,
                ':amt' => $booking['subtotal'] + ($booking['extras_charges'] ?? 0) - ($booking['discount_amount'] ?? 0),
                ':tax' => $booking['tax_amount'],
                ':total' => $booking['total_amount']
            ]);
    } else {
        $invoice_number = $invoice['invoice_number'];
        $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE booking_id = :bid")->execute([':bid' => $booking_id]);
    }

    $pdo->commit();

    log_activity($user_id, 'payment', "دفع الحجز رقم {$booking['booking_number']} عبر $payment_method");

    json_response([
        'success' => true,
        'message' => 'تمت عملية الدفع بنجاح! تم تأكيد حجزك.',
        'booking_number' => $booking['booking_number'],
        'invoice_number' => $invoice_number,
        'amount_paid' => (float)$booking['total_amount'],
        'transaction_id' => $txn
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('process-payment error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ أثناء معالجة الدفع'], 500);
}
