<?php
/**
 * API: فحص توفر سيارة في تواريخ محددة
 * الاستدعاء: GET api/check-availability.php?car_id=1&pickup=2026-09-15&return=2026-09-18
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

$car_id = intval($_GET['car_id'] ?? 0);
$pickup = trim($_GET['pickup'] ?? '');
$return = trim($_GET['return'] ?? '');

if ($car_id <= 0 || empty($pickup) || empty($return)) {
    json_response(['success' => false, 'available' => false, 'message' => 'معطيات ناقصة'], 400);
}

// التحقق من صحة صيغة التواريخ
$pickupDate = DateTime::createFromFormat('Y-m-d', $pickup);
$returnDate = DateTime::createFromFormat('Y-m-d', $return);
if (!$pickupDate || !$returnDate) {
    json_response(['success' => false, 'available' => false, 'message' => 'صيغة التاريخ غير صحيحة'], 400);
}

if ($return <= $pickup) {
    json_response(['success' => false, 'available' => false, 'message' => 'تاريخ التسليم يجب أن يكون بعد تاريخ الاستلام']);
}

$today = date('Y-m-d');
if ($pickup < $today) {
    json_response(['success' => false, 'available' => false, 'message' => 'تاريخ الاستلام يجب أن يكون من اليوم فصاعداً']);
}

try {
    // هل السيارة موجودة ونشطة؟
    $stmt = $pdo->prepare("SELECT status FROM cars WHERE id = :id");
    $stmt->execute([':id' => $car_id]);
    $car = $stmt->fetch();

    if (!$car) {
        json_response(['success' => false, 'available' => false, 'message' => 'السيارة غير موجودة'], 404);
    }
    if ($car['status'] === 'maintenance') {
        json_response(['success' => true, 'available' => false, 'message' => 'السيارة في الصيانة حالياً']);
    }

    $available = check_car_availability($car_id, $pickup . ' 00:00:00', $return . ' 23:59:59');

    json_response([
        'success' => true,
        'available' => $available,
        'message' => $available ? 'السيارة متوفرة في هذه التواريخ' : 'السيارة محجوزة في هذه التواريخ، جرب تواريخ أخرى'
    ]);
} catch (Exception $e) {
    error_log('check-availability error: ' . $e->getMessage());
    json_response(['success' => false, 'available' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}
