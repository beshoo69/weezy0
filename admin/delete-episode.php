<?php
// admin/delete-episode.php - حذف حلقة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return = $_GET['return'] ?? 'edit-content.php';

if ($id) {
    try {
        // حذف الروابط المرتبطة أولاً
        $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'episode' AND content_id = ?")->execute([$id]);
        
        // حذف الحلقة
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: $return&deleted=1");
        exit;
    } catch (Exception $e) {
        die("خطأ: " . $e->getMessage());
    }
} else {
    header('Location: edit-content.php');
    exit;
}
?>