<?php

$dirs = [
    "assets/css",
    "assets/js",
    "assets/images/cars",
    "assets/images/logos",
    "assets/images/backgrounds",
    "assets/fonts",
    "includes",
    "admin",
    "customer",
    "api"
];

$files = [
    "assets/css/style.css",
    "assets/css/admin.css",
    "assets/css/responsive.css",
    "assets/js/main.js",
    "assets/js/booking.js",
    "assets/js/admin.js",
    "assets/js/validation.js",
    "includes/config.php",
    "includes/header.php",
    "includes/footer.php",
    "includes/functions.php",
    "includes/auth.php",
    "admin/index.php",
    "admin/cars-management.php",
    "admin/bookings-management.php",
    "admin/customers-management.php",
    "admin/reports.php",
    "admin/settings.php",
    "customer/dashboard.php",
    "customer/my-bookings.php",
    "customer/invoices.php",
    "customer/profile.php",
    "api/search-cars.php",
    "api/check-availability.php",
    "api/process-payment.php",
    "database.sql",
    "index.php",
    "cars.php",
    "car-details.php",
    "booking.php",
    "payment.php",
    "login.php",
    "register.php",
    "about.php",
    "contact.php",
    "terms.php",
    ".htaccess"
];

// Create folders
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Create files
foreach ($files as $file) {
    if (!file_exists($file)) {
        file_put_contents($file, "");
    }
}

echo "Project structure created successfully!";