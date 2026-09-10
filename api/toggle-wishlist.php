<?php
/**
 * API: إضافة/إزالة سيارة من المفضلة
 * الاستدعاء: POST api/toggle-wishlist.php  (JSON: {car_id})
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
$car_id = intval($input['car_id'] ?? 0);

if ($car_id <= 0) {
    json_response(['success' => false, 'message' => 'معرف السيارة غير صالح'], 400);
}

try {
    // التأكد من وجود السيارة
    $stmt = $pdo->prepare("SELECT id FROM cars WHERE id = :id");
    $stmt->execute([':id' => $car_id]);
    if (!$stmt->fetch()) {
        json_response(['success' => false, 'message' => 'السيارة غير موجودة'], 404);
    }

    $user_id = (int)$_SESSION['user_id'];

    // هل هي مفضلة حالياً؟
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = :uid AND car_id = :cid");
    $stmt->execute([':uid' => $user_id, ':cid' => $car_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        $pdo->prepare("DELETE FROM favorites WHERE id = :id")->execute([':id' => $existing['id']]);
        $in_wishlist = false;
        $message = 'تمت إزالة السيارة من المفضلة';
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, car_id) VALUES (:uid, :cid)")
            ->execute([':uid' => $user_id, ':cid' => $car_id]);
        $in_wishlist = true;
        $message = 'تمت إضافة السيارة إلى المفضلة';
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = :uid");
    $stmt->execute([':uid' => $user_id]);

    json_response([
        'success' => true,
        'in_wishlist' => $in_wishlist,
        'message' => $message,
        'count' => (int)$stmt->fetchColumn()
    ]);
} catch (Exception $e) {
    error_log('toggle-wishlist error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}
