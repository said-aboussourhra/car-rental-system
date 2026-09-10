-- ============================================================
-- Premium Car Rental - Complete Database Schema
-- نظام تأجير السيارات - مخطط قاعدة البيانات الكامل
-- الاستيراد: من phpMyAdmin أو:
--   mysql -u root -p < database.sql
-- أو افتح install.php في المتصفح (الأسهل)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `car_rental_db`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `car_rental_db`;

-- ------------------------------------------------------------
-- المستخدمون (عملاء + مدراء)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `role` ENUM('customer','admin','super_admin') NOT NULL DEFAULT 'customer',
  `status` ENUM('active','inactive','banned') NOT NULL DEFAULT 'active',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `cin` VARCHAR(30) DEFAULT NULL,
  `license_number` VARCHAR(50) DEFAULT NULL,
  `birth_date` DATE DEFAULT NULL,
  `email_verification_token` VARCHAR(100) DEFAULT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `last_login` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- حساب المدير الافتراضي
-- البريد: admin@carrental.ma | كلمة المرور: Admin@123
-- ⚠️ غيّر كلمة المرور فور أول تسجيل دخول!
INSERT INTO `users` (`full_name`, `email`, `password`, `phone`, `role`, `status`, `email_verified_at`) VALUES
('مدير النظام', 'admin@carrental.ma', '$2y$10$FYtfLH76DRMzfYQndz9NjO0LL9HCduSN1g0pmBFN5TFbrSbZECqFO', '0600000000', 'super_admin', 'active', NOW());

-- ------------------------------------------------------------
-- السيارات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `cars`;
CREATE TABLE `cars` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `brand` VARCHAR(80) NOT NULL,
  `model` VARCHAR(80) NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL DEFAULT 2024,
  `type` VARCHAR(40) NOT NULL DEFAULT 'economy',
  `fuel_type` VARCHAR(20) NOT NULL DEFAULT 'petrol',
  `transmission` VARCHAR(20) NOT NULL DEFAULT 'manual',
  `seats` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `doors` TINYINT UNSIGNED NOT NULL DEFAULT 4,
  `engine_size` VARCHAR(40) DEFAULT NULL,
  `color` VARCHAR(50) DEFAULT NULL,
  `license_plate` VARCHAR(30) DEFAULT NULL,
  `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `weekly_rate` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `monthly_rate` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `deposit` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `mileage_limit` INT UNSIGNED NOT NULL DEFAULT 300,
  `extra_mileage_rate` DECIMAL(6,2) NOT NULL DEFAULT 1.00,
  `description` TEXT,
  `features` TEXT,
  `popular` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('available','rented','maintenance','reserved') NOT NULL DEFAULT 'available',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cars_status` (`status`),
  KEY `idx_cars_type` (`type`),
  KEY `idx_cars_popular` (`popular`),
  KEY `idx_cars_brand` (`brand`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- صور السيارات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `car_images`;
CREATE TABLE `car_images` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `car_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(255) NOT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_car_images_car` (`car_id`, `is_primary`),
  CONSTRAINT `fk_images_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- مواقع الاستلام والتسليم
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `working_hours` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_locations_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `locations` (`name`, `address`, `city`, `phone`, `working_hours`, `status`) VALUES
('مراكش المدينة', 'شارع محمد الخامس، مراكش', 'مراكش', '+212524300000', '08:00 - 20:00', 'active'),
('مطار مراكش المنارة', 'مطار مراكش المنارة', 'مراكش', '+212524400000', '24/7', 'active'),
('الدار البيضاء', 'شارع الحسن الثاني، الدار البيضاء', 'الدار البيضاء', '+212522200000', '08:00 - 20:00', 'active'),
('مطار محمد الخامس', 'مطار محمد الخامس الدولي', 'الدار البيضاء', '+212522300000', '24/7', 'active'),
('أكادير', 'شارع الحسن الأول، أكادير', 'أكادير', '+212528200000', '08:00 - 20:00', 'active');

-- ------------------------------------------------------------
-- الخدمات الإضافية
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `extras`;
CREATE TABLE `extras` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `daily_rate` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `max_quantity` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `extras` (`name`, `description`, `daily_rate`, `max_quantity`, `status`) VALUES
('GPS', 'نظام ملاحة GPS', 10.00, 1, 'active'),
('مقعد طفل', 'مقعد أمان للأطفال', 15.00, 2, 'active'),
('سائق إضافي', 'إضافة سائق ثاني', 20.00, 2, 'active'),
('واي فاي', 'جهاز واي فاي متنقل', 8.00, 1, 'active'),
('تأمين شامل', 'تغطية تأمينية شاملة بدون تحمل', 50.00, 1, 'active');

-- ------------------------------------------------------------
-- الحجوزات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `bookings`;
CREATE TABLE `bookings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_number` VARCHAR(40) NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `car_id` INT UNSIGNED NOT NULL,
  `pickup_date` DATETIME NOT NULL,
  `return_date` DATETIME NOT NULL,
  `pickup_location` VARCHAR(150) DEFAULT NULL,
  `return_location` VARCHAR(150) DEFAULT NULL,
  `total_days` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `extras_charges` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `deposit_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `extras` TEXT,
  `notes` TEXT,
  `coupon_code` VARCHAR(30) DEFAULT NULL,
  `payment_status` ENUM('pending','paid','refunded') NOT NULL DEFAULT 'pending',
  `booking_status` ENUM('pending','confirmed','active','completed','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_number` (`booking_number`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_car` (`car_id`),
  KEY `idx_bookings_status` (`booking_status`),
  KEY `idx_bookings_dates` (`pickup_date`, `return_date`),
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المدفوعات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(30) DEFAULT 'cash',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `payment_status` VARCHAR(20) DEFAULT 'success',
  `payment_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_booking` (`booking_id`),
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- الفواتير
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `booking_id` INT UNSIGNED NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE DEFAULT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) DEFAULT 0,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('unpaid','paid','overdue') NOT NULL DEFAULT 'unpaid',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invoice_number` (`invoice_number`),
  KEY `idx_invoices_booking` (`booking_id`),
  CONSTRAINT `fk_invoices_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- التقييمات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `car_id` INT UNSIGNED DEFAULT NULL,
  `booking_id` INT UNSIGNED DEFAULT NULL,
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `title` VARCHAR(200) DEFAULT NULL,
  `comment` TEXT,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reviews_car` (`car_id`, `status`),
  CONSTRAINT `fk_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reviews_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- المفضلة
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `favorites`;
CREATE TABLE `favorites` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `car_id` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_favorites` (`user_id`, `car_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_car` FOREIGN KEY (`car_id`) REFERENCES `cars` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- كوبونات الخصم
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(30) NOT NULL,
  `type` ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  `value` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `max_uses` INT UNSIGNED DEFAULT NULL,
  `used_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `min_days` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME DEFAULT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_coupon_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- كوبون ترحيبي: خصم 10% (جربه عند الحجز)
INSERT INTO `coupons` (`code`, `type`, `value`, `max_uses`, `min_days`, `status`) VALUES
('WELCOME10', 'percent', 10.00, NULL, 0, 'active'),
('SUMMER20', 'percent', 20.00, 100, 3, 'active');

-- ------------------------------------------------------------
-- الإعدادات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(50) NOT NULL,
  `setting_value` TEXT,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Premium Car Rental'),
('whatsapp_number', '+212600000000'),
('maintenance_mode', '0');

-- ------------------------------------------------------------
-- الجلسات (ضرورية للعمل على المنصات السحابية مثل Vercel)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` VARCHAR(128) NOT NULL,
  `data` MEDIUMTEXT,
  `expires` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_expires` (`expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- الإشعارات (للإدارة)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT,
  `type` VARCHAR(30) NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_read` (`is_read`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- سجل النشاطات
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) DEFAULT NULL,
  `description` TEXT,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_user` (`user_id`),
  KEY `idx_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- محاولات تسجيل الدخول (لحماية من التخمين)
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attempts_email` (`email`),
  KEY `idx_attempts_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- طلبات إعادة تعيين كلمة المرور
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` VARCHAR(100) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resets_token` (`token`),
  CONSTRAINT `fk_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- رموز "تذكرني"
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `remember_tokens`;
CREATE TABLE `remember_tokens` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `token` CHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_remember_token` (`token`),
  CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- انتهى المخطط. لإضافة سيارات تجريبية افتح:
--   add-all-cars.php   (15 سيارة مع صور)
-- أو   seed-cars.php   (3 سيارات + حساب المدير)
-- ============================================================
