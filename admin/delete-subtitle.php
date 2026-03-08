<?php
// admin/delete-subtitle.php - حذف ترجمة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? 'movie';
$content_id = isset($_GET['content_id']) ? (int)$_GET['content_id'] : 0;

if ($id) {
    try {
        // جلب مسار الملف لحذفه
        $stmt = $pdo->prepare("SELECT subtitle_file FROM subtitles WHERE id = ?");
        $stmt->execute([$id]);
        $sub = $stmt->fetch();
        
        if ($sub && !empty($sub['subtitle_file'])) {
            $file_path = __DIR__ . '/../' . $sub['subtitle_file'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // حذف الترجمة
        $stmt = $pdo->prepare("DELETE FROM subtitles WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: edit-content.php?type=$type&id=$content_id&deleted=1");
        exit;
    } catch (Exception $e) {
        die("خطأ: " . $e->getMessage());
    }
} else {
    header('Location: edit-content.php');
    exit;
}
?>