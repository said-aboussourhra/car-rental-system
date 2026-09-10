<?php
/**
 * API: البحث عن السيارات مع فلاتر
 * الاستدعاء: GET api/search-cars.php?search=&type=&fuel_type=&transmission=&brand=&max_price=&pickup=&return=
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $where = ["c.status = 'available'"];
    $params = [];

    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $where[] = "(c.brand LIKE :search OR c.model LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $type = $_GET['type'] ?? '';
    if ($type !== '') {
        $where[] = "c.type = :type";
        $params[':type'] = $type;
    }

    $fuel = $_GET['fuel_type'] ?? '';
    if ($fuel !== '') {
        $where[] = "c.fuel_type = :fuel";
        $params[':fuel'] = $fuel;
    }

    $trans = $_GET['transmission'] ?? '';
    if ($trans !== '') {
        $where[] = "c.transmission = :trans";
        $params[':trans'] = $trans;
    }

    $brand = $_GET['brand'] ?? '';
    if ($brand !== '') {
        $where[] = "c.brand = :brand";
        $params[':brand'] = $brand;
    }

    $maxPrice = floatval($_GET['max_price'] ?? 0);
    if ($maxPrice > 0) {
        $where[] = "c.daily_rate <= :maxp";
        $params[':maxp'] = $maxPrice;
    }

    $minSeats = intval($_GET['min_seats'] ?? 0);
    if ($minSeats > 0) {
        $where[] = "c.seats >= :seats";
        $params[':seats'] = $minSeats;
    }

    $sql = "SELECT c.*,
                   (SELECT image_path FROM car_images WHERE car_id = c.id AND is_primary = 1 LIMIT 1) as main_image,
                   (SELECT AVG(rating) FROM reviews WHERE car_id = c.id AND status = 'approved') as avg_rating
            FROM cars c
            WHERE " . implode(' AND ', $where) . "
            ORDER BY c.popular DESC, c.daily_rate ASC
            LIMIT 60";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $cars = $stmt->fetchAll();

    // استبعاد السيارات غير المتوفرة في التواريخ المطلوبة
    $pickup = $_GET['pickup'] ?? '';
    $return = $_GET['return'] ?? '';
    if ($pickup !== '' && $return !== '' && $return > $pickup) {
        $cars = array_values(array_filter($cars, function ($c) use ($pickup, $return) {
            return check_car_availability($c['id'], $pickup . ' 00:00:00', $return . ' 23:59:59');
        }));
    }

    json_response([
        'success' => true,
        'count' => count($cars),
        'cars' => $cars
    ]);
} catch (Exception $e) {
    error_log('search-cars error: ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'حدث خطأ في الخادم'], 500);
}
