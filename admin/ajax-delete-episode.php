<?php
// admin/ajax-delete-episode.php - حذف حلقة من قاعدة البيانات عبر AJAX
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة طلب غير صحيحة']);
    exit;
}

$episode_id = isset($_POST['episode_id']) ? (int)$_POST['episode_id'] : 0;
$season_id = isset($_POST['season_id']) ? (int)$_POST['season_id'] : 0;
$episode_number = isset($_POST['episode_number']) ? (int)$_POST['episode_number'] : 0;
$series_id = isset($_POST['series_id']) ? (int)$_POST['series_id'] : 0;

try {
    if ($episode_id > 0) {
        // حذف بواسطة ID
        // حذف الروابط المرتبطة أولاً
        $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
        $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
        $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'episode' AND content_id = ?")->execute([$episode_id]);
        
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
        $stmt->execute([$episode_id]);
        $deleted = $stmt->rowCount();
        
        if ($deleted > 0) {
            echo json_encode(['success' => true, 'message' => 'تم حذف الحلقة بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم العثور على الحلقة']);
        }
    } elseif ($series_id > 0 && $season_number > 0 && $episode_number > 0) {
        // حذف بواسطة series_id, season_number, episode_number
        // جلب معرف الحلقة أولاً
        $get_id = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
        $get_id->execute([$series_id, $season_number, $episode_number]);
        $ep = $get_id->fetch();
        
        if ($ep) {
            $episode_id = $ep['id'];
            
            // حذف الروابط المرتبطة
            $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
            $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
            $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'episode' AND content_id = ?")->execute([$episode_id]);
        }
        
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
        $stmt->execute([$series_id, $season_number, $episode_number]);
        $deleted = $stmt->rowCount();
        
        if ($deleted > 0) {
            echo json_encode(['success' => true, 'message' => 'تم حذف الحلقة بنجاح']);
        } else {
            echo json_encode(['success' => false, 'message' => 'لم يتم العثور على الحلقة']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كافية للحذف']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
}
?>