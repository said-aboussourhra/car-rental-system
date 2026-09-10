<?php
/**
 * API: تحديث حالة حجز (للمدير فقط)
 * الاستدعاء: POST api/update-booking-status.php  (JSON: {booking_id, status})
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$booking_id = intval($input['booking_id'] ?? 0);
$status = $input['status'] ?? '';

$allowed = ['pending', 'confirmed', 'active', 'completed', 'cancelled'];
if ($booking_id <= 0 || !in_array($status, $allowed)) {
    json_response(['success' => false, 'message' => 'معطيات غير صالحة'], 400);
}

try {
    $stmt = $pdo->prepare("SELECT id, booking_number, car_id, booking_status FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        json_response(['success' => false, 'message' => 'الحجز غير موجود'], 404);
    }

    $pdo->beginTransaction();

    $pdo->prepare("UPDATE bookings SET booking_status = :st, updated_at = NOW() WHERE id = :id")
        ->execute([':st' => $status, ':id' => $booking_id]);

    // عند التفعيل: السيارة تصبح مشغولة، وعند الإلغاء/الإكمال تعود متاحة
    if ($status === 'active') {
        $pdo->prepare("UPDATE cars SET status = 'rented' WHERE id = :id")->execute([':id' => $booking['car_id']]);
    } elseif (in_array($status, ['cancelled', 'completed'])) {
        $pdo->prepare("UPDATE cars SET status = 'available' WHERE id = :id")->execute([':id' => $booking['car_id']]);
    }

    // إشعار للعميل
    $stmt = $pdo->prepare("SELECT user_id FROM bookings WHERE id = :id");
    $stmt->execute([':id' => $booking_id]);
    $uid = $stmt->fetchColumn();
    $labels = ['pending' => 'قيد الانتظار', 'confirmed' => 'مؤكد', 'active' => 'نشط', 'completed' => 'مكتمل', 'cancelled' => 'ملغى'];
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:uid, :t, :m, 'booking')")
        ->execute([
            ':uid' => $uid,
            ':t' => 'تحديث حالة الحجز ' . $booking['booking_number'],
            ':m' => 'أصبح حجزك الآن: ' . $labels[$status]
        ]);

    $pdo->commit();

    log_activity($_SESSION['user_id'], 'booking_status', "تحديث الحجز {$booking['booking_number']} إلى $status");

    json_response(['success' => true, 'message' => 'تم تحديث حالة الحجز']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('update-booking-status error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
