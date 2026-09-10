<?php
// logout.php - تسجيل خروج آمن (مع تنظيف رموز "تذكرني")
require_once 'includes/config.php';
require_once 'includes/auth.php';

$auth = new Auth($pdo);
$auth->logout();

// منع التخزين المؤقت
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header('Location: index.php?logout=' . time());
exit();
