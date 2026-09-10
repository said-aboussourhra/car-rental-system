<?php
/**
 * API: التحقق من صلاحية كوبون الخصم
 * الاستدعاء: POST api/validate-coupon.php  (JSON: {code, days})
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
$code = strtoupper(trim($input['code'] ?? ''));
$days = max(1, intval($input['days'] ?? 1));

if ($code === '') {
    json_response(['success' => false, 'message' => 'أدخل كود الخصم']);
}

try {
    $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = :code AND status = 'active' LIMIT 1");
    $stmt->execute([':code' => $code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        json_response(['success' => false, 'message' => 'كود الخصم غير موجود']);
    }

    if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) {
        json_response(['success' => false, 'message' => 'انتهت صلاحية هذا الكوبون']);
    }

    if ($coupon['max_uses'] !== null && $coupon['used_count'] >= $coupon['max_uses']) {
        json_response(['success' => false, 'message' => 'تم استنفاد عدد استخدامات هذا الكوبون']);
    }

    if ($days < (int)$coupon['min_days']) {
        json_response(['success' => false, 'message' => 'هذا الكوبون يتطلب حجز ' . $coupon['min_days'] . ' أيام على الأقل']);
    }

    json_response([
        'success' => true,
        'message' => 'تم تطبيق خصم ' . ($coupon['type'] === 'percent' ? $coupon['value'] . '%' : format_amount($coupon['value'])) . ' بنجاح',
        'coupon' => [
            'code' => $coupon['code'],
            'type' => $coupon['type'],
            'value' => (float)$coupon['value'],
            'min_days' => (int)$coupon['min_days']
        ]
    ]);
} catch (Exception $e) {
    error_log('validate-coupon error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}
