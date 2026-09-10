<?php
require_once 'includes/config.php';

$sql = "
CREATE TABLE IF NOT EXISTS payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(20) DEFAULT 'cash',
    transaction_id VARCHAR(100),
    payment_status VARCHAR(20) DEFAULT 'success',
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE,
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'unpaid',
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    car_id INT,
    booking_id INT,
    rating INT NOT NULL,
    title VARCHAR(200),
    comment TEXT,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT
);

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT IGNORE INTO locations (name, address, city, phone, working_hours, status) VALUES
('مراكش المدينة', 'شارع محمد الخامس، مراكش', 'مراكش', '+212524300000', '08:00 - 20:00', 'active'),
('مطار مراكش المنارة', 'مطار مراكش المنارة', 'مراكش', '+212524400000', '24/7', 'active'),
('الدار البيضاء', 'شارع الحسن الثاني، الدار البيضاء', 'الدار البيضاء', '+212522200000', '08:00 - 20:00', 'active');

INSERT IGNORE INTO extras (name, description, daily_rate, max_quantity, status) VALUES
('GPS', 'نظام ملاحة GPS', 10.00, 1, 'active'),
('مقعد طفل', 'مقعد أمان للأطفال', 15.00, 2, 'active'),
('سائق إضافي', 'إضافة سائق ثاني', 20.00, 2, 'active'),
('واي فاي', 'جهاز واي فاي متنقل', 8.00, 1, 'active');
";

try {
    // PDO ينفذ جملة واحدة فقط في كل استدعاء، لذا نقسم السكريبت
    $sql = preg_replace('/^--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(";\n", $sql)));
    $count = 0;
    foreach ($statements as $statement) {
        if ($statement !== '') {
            $pdo->exec($statement);
            $count++;
        }
    }
    echo "<h2 style='color:green;text-align:center;margin-top:50px;'>✅ تم إنشاء جميع الجداول بنجاح ($count جملة)!</h2>";
    echo "<p style='text-align:center;'><a href='admin/bookings-management.php' style='padding:15px 30px;background:#667eea;color:white;text-decoration:none;border-radius:10px;'>🔙 العودة للحجوزات</a></p>";
} catch (Exception $e) {
    echo "<h2 style='color:red;'>❌ خطأ: " . $e->getMessage() . "</h2>";
}
?>