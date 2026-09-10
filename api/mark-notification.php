<?php
/**
 * API: وضع إشعار كمقروء
 * الاستدعاء: POST api/mark-notification.php  (JSON: {id})
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    json_response(['success' => false, 'message' => 'يجب تسجيل الدخول'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$notification_id = intval($input['id'] ?? 0);

if ($notification_id <= 0) {
    json_response(['success' => false, 'message' => 'معرف غير صالح'], 400);
}

try {
    $user_id = (int)$_SESSION['user_id'];
    $is_admin_user = is_admin();

    if ($is_admin_user) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id");
        $stmt->execute([':id' => $notification_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND (user_id = :uid OR user_id IS NULL)");
        $stmt->execute([':id' => $notification_id, ':uid' => $user_id]);
    }

    json_response(['success' => true]);
} catch (Exception $e) {
    error_log('mark-notification error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
