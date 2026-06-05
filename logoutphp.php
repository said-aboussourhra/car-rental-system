<?php
// logout.php
require_once 'includes/config.php';

// حذف جميع جلسات المستخدم
$_SESSION = [];

// حذف كوكيز الجلسة
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// حذف كوكيز التذكر
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// تدمير الجلسة
session_destroy();

// التوجيه للصفحة الرئيسية
header("Location: " . SITE_URL);
exit();
?>