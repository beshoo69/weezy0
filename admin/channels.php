<?php
// admin/live/channels.php - إدارة قنوات البث المباشر
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// جلب جميع القنوات
$channels = $pdo->query("SELECT * FROM live_channels ORDER BY featured DESC, views DESC")->fetchAll();
?>

<!-- تصميم مشابه للوحة التحكم - يمكنني إضافته بالكامل إذا أردت -->