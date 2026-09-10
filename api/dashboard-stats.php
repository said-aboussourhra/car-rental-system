<?php
/**
 * API: إحصائيات لوحة الإدارة (تحديث تلقائي)
 * يتطلب صلاحيات مدير
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_admin()) {
    json_response(['success' => false, 'message' => 'غير مصرح'], 403);
}

try {
    $stats = [];
    $stats['total_cars'] = (int)$pdo->query("SELECT COUNT(*) FROM cars")->fetchColumn();
    $stats['available_cars'] = (int)$pdo->query("SELECT COUNT(*) FROM cars WHERE status = 'available'")->fetchColumn();
    $stats['total_customers'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $stats['total_bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $stats['pending_bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending'")->fetchColumn();
    $stats['active_bookings'] = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'active'")->fetchColumn();
    $stats['total_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $stats['monthly_revenue'] = (float)$pdo->query("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid' AND MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetchColumn();
    $stats['monthly_revenue_formatted'] = format_amount($stats['monthly_revenue']);

    json_response(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    error_log('dashboard-stats error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
