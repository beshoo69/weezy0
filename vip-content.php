<?php
// vip-content.php - صفحة المحتوى الحصري
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/membership.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: admin/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// التحقق من صلاحية VIP
if (!checkMembershipAccess($_SESSION['user_id'], 'vip')) {
    header('Location: membership-plans.php?required=vip');
    exit;
}

// باقي محتوى الصفحة...
?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>المحتوى الحصري - ويزي برو</title>
</head>
<body>
    <h1>مرحباً بك في المحتوى الحصري!</h1>
    <p>هذه الصفحة مخصصة لمشتركي VIP فقط.</p>
</body>
</html>