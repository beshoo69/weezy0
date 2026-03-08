<?php
// admin/api-content.php - API موحد لجميع عمليات المحتوى
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_episode':
        addEpisode($pdo);
        break;
    case 'update_episode':
        updateEpisode($pdo);
        break;
    case 'delete_episode':
        deleteEpisode($pdo);
        break;
    case 'get_episode':
        getEpisode($pdo);
        break;
    case 'add_watch_link':
        addWatchLink($pdo);
        break;
    case 'delete_watch_link':
        deleteWatchLink($pdo);
        break;
    case 'add_download_link':
        addDownloadLink($pdo);
        break;
    case 'delete_download_link':
        deleteDownloadLink($pdo);
        break;
    case 'add_season':
        addSeason($pdo);
        break;
    case 'update_season':
        updateSeason($pdo);
        break;
    case 'delete_season':
        deleteSeason($pdo);
        break;
    case 'get_season':
        getSeason($pdo);
        break;
    case 'upload_video':
        uploadVideo($pdo);
        break;
    case 'upload_subtitle':
        uploadSubtitle($pdo);
        break;
    case 'add_subtitle':
        addSubtitle($pdo);
        break;
    case 'delete_subtitle':
        deleteSubtitle($pdo);
        break;
    case 'fetch_series_seasons':
        fetchSeriesSeasons($pdo);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
}

// =============================================
// دوال الحلقات
// =============================================

/**
 * إضافة حلقة جديدة
 */
function addEpisode($pdo) {
    $series_id = (int)($_POST['series_id'] ?? 0);
    $season_number = (int)($_POST['season_number'] ?? 0);
    $episode_number = (int)($_POST['episode_number'] ?? 0);
    $title = $_POST['title'] ?? "الحلقة {$episode_number}";
    $description = $_POST['description'] ?? '';
    $duration = (int)($_POST['duration'] ?? 45);
    $still_path = $_POST['still_path'] ?? '';
    $air_date = $_POST['air_date'] ?? null;
    
    if (!$series_id || !$season_number || !$episode_number) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // التحقق من وجود الحلقة مسبقاً
        $check = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
        $check->execute([$series_id, $season_number, $episode_number]);
        $existing = $check->fetch();
        
        if ($existing) {
            // تحديث الحلقة الموجودة
            $sql = "UPDATE episodes SET title = ?, description = ?, duration = ?, still_path = ?, air_date = ? 
                    WHERE series_id = ? AND season_number = ? AND episode_number = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$title, $description, $duration, $still_path, $air_date, $series_id, $season_number, $episode_number]);
            $episode_id = $existing['id'];
        } else {
            // إضافة حلقة جديدة
            $sql = "INSERT INTO episodes (series_id, season_number, episode_number, title, description, duration, still_path, air_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$series_id, $season_number, $episode_number, $title, $description, $duration, $still_path, $air_date]);
            $episode_id = $pdo->lastInsertId();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم حفظ الحلقة بنجاح',
            'episode_id' => $episode_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * تحديث حلقة موجودة
 */
function updateEpisode($pdo) {
    $episode_id = (int)($_POST['episode_id'] ?? 0);
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $duration = (int)($_POST['duration'] ?? 45);
    $still_path = $_POST['still_path'] ?? '';
    $air_date = $_POST['air_date'] ?? null;
    
    if (!$episode_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الحلقة مطلوب']);
        return;
    }
    
    try {
        $sql = "UPDATE episodes SET title = ?, description = ?, duration = ?, still_path = ?, air_date = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $description, $duration, $still_path, $air_date, $episode_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم تحديث الحلقة بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * حذف حلقة
 */
function deleteEpisode($pdo) {
    $episode_id = (int)($_POST['episode_id'] ?? 0);
    
    if (!$episode_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الحلقة مطلوب']);
        return;
    }
    
    try {
        // حذف روابط المشاهدة المرتبطة أولاً
        $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
        
        // حذف روابط التحميل المرتبطة
        $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'episode' AND item_id = ?")->execute([$episode_id]);
        
        // حذف الترجمات المرتبطة
        $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'episode' AND content_id = ?")->execute([$episode_id]);
        
        // حذف الحلقة
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
        $stmt->execute([$episode_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف الحلقة بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * جلب معلومات حلقة
 */
function getEpisode($pdo) {
    $episode_id = (int)($_GET['episode_id'] ?? 0);
    
    if (!$episode_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الحلقة مطلوب']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM episodes WHERE id = ?");
        $stmt->execute([$episode_id]);
        $episode = $stmt->fetch();
        
        if ($episode) {
            // جلب روابط المشاهدة
            $watch_servers = [];
            if (!empty($episode['watch_servers'])) {
                $watch_servers = json_decode($episode['watch_servers'], true) ?: [];
            }
            
            // جلب روابط التحميل
            $download_servers = [];
            if (!empty($episode['download_servers'])) {
                $download_servers = json_decode($episode['download_servers'], true) ?: [];
            }
            
            $episode['watch_servers_array'] = $watch_servers;
            $episode['download_servers_array'] = $download_servers;
            
            echo json_encode(['success' => true, 'data' => $episode]);
        } else {
            echo json_encode(['success' => false, 'message' => 'الحلقة غير موجودة']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

// =============================================
// دوال روابط المشاهدة
// =============================================

/**
 * إضافة رابط مشاهدة
 */
function addWatchLink($pdo) {
    $item_type = $_POST['item_type'] ?? 'episode';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر مشاهدة';
    $server_url = $_POST['server_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $language = $_POST['language'] ?? 'arabic';
    
    if (!$item_id || empty($server_url)) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // إنشاء جدول watch_servers إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS watch_servers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_type VARCHAR(20) NOT NULL,
                item_id INT NOT NULL,
                server_name VARCHAR(255),
                server_url TEXT,
                quality VARCHAR(50),
                language VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item (item_type, item_id)
            )
        ");
        
        $sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_type, $item_id, $server_name, $server_url, $quality, $language]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إضافة رابط المشاهدة بنجاح',
            'link_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * حذف رابط مشاهدة
 */
function deleteWatchLink($pdo) {
    $link_id = (int)($_POST['link_id'] ?? 0);
    
    if (!$link_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الرابط مطلوب']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM watch_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف رابط المشاهدة بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

// =============================================
// دوال روابط التحميل
// =============================================

/**
 * إضافة رابط تحميل
 */
function addDownloadLink($pdo) {
    $item_type = $_POST['item_type'] ?? 'episode';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر تحميل';
    $download_url = $_POST['download_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $size = $_POST['size'] ?? '';
    
    if (!$item_id || empty($download_url)) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // إنشاء جدول download_servers إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS download_servers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_type VARCHAR(20) NOT NULL,
                item_id INT NOT NULL,
                server_name VARCHAR(255),
                download_url TEXT,
                quality VARCHAR(50),
                size VARCHAR(50),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_item (item_type, item_id)
            )
        ");
        
        $sql = "INSERT INTO download_servers (item_type, item_id, server_name, download_url, quality, size) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_type, $item_id, $server_name, $download_url, $quality, $size]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إضافة رابط التحميل بنجاح',
            'link_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * حذف رابط تحميل
 */
function deleteDownloadLink($pdo) {
    $link_id = (int)($_POST['link_id'] ?? 0);
    
    if (!$link_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الرابط مطلوب']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM download_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف رابط التحميل بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

// =============================================
// دوال المواسم
// =============================================

/**
 * إضافة موسم جديد
 */
function addSeason($pdo) {
    $series_id = (int)($_POST['series_id'] ?? 0);
    $season_number = (int)($_POST['season_number'] ?? 0);
    $name = $_POST['name'] ?? "الموسم {$season_number}";
    $overview = $_POST['overview'] ?? '';
    $poster = $_POST['poster'] ?? '';
    $air_date = $_POST['air_date'] ?? null;
    
    if (!$series_id || !$season_number) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // إنشاء جدول seasons إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS seasons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                series_id INT NOT NULL,
                season_number INT NOT NULL,
                name VARCHAR(255),
                overview TEXT,
                poster VARCHAR(500),
                air_date DATE,
                watch_servers TEXT,
                download_servers TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_series (series_id),
                UNIQUE KEY unique_season (series_id, season_number)
            )
        ");
        
        // التحقق من وجود الموسم مسبقاً
        $check = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? AND season_number = ?");
        $check->execute([$series_id, $season_number]);
        $existing = $check->fetch();
        
        if ($existing) {
            // تحديث الموسم الموجود
            $sql = "UPDATE seasons SET name = ?, overview = ?, poster = ?, air_date = ? WHERE series_id = ? AND season_number = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $overview, $poster, $air_date, $series_id, $season_number]);
            $season_id = $existing['id'];
        } else {
            // إضافة موسم جديد
            $sql = "INSERT INTO seasons (series_id, season_number, name, overview, poster, air_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$series_id, $season_number, $name, $overview, $poster, $air_date]);
            $season_id = $pdo->lastInsertId();
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم حفظ الموسم بنجاح',
            'season_id' => $season_id
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * تحديث موسم موجود
 */
function updateSeason($pdo) {
    $season_id = (int)($_POST['season_id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $poster = $_POST['poster'] ?? '';
    $air_date = $_POST['air_date'] ?? null;
    
    if (!$season_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الموسم مطلوب']);
        return;
    }
    
    try {
        $sql = "UPDATE seasons SET name = ?, overview = ?, poster = ?, air_date = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$name, $overview, $poster, $air_date, $season_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم تحديث الموسم بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * حذف موسم
 */
function deleteSeason($pdo) {
    $season_id = (int)($_POST['season_id'] ?? 0);
    
    if (!$season_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الموسم مطلوب']);
        return;
    }
    
    try {
        // حذف جميع حلقات الموسم أولاً
        $pdo->prepare("DELETE FROM episodes WHERE season_id = ?")->execute([$season_id]);
        
        // حذف الموسم
        $stmt = $pdo->prepare("DELETE FROM seasons WHERE id = ?");
        $stmt->execute([$season_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف الموسم وجميع حلقاته بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * جلب معلومات موسم
 */
function getSeason($pdo) {
    $season_id = (int)($_GET['season_id'] ?? 0);
    
    if (!$season_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الموسم مطلوب']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM seasons WHERE id = ?");
        $stmt->execute([$season_id]);
        $season = $stmt->fetch();
        
        if ($season) {
            // جلب روابط المشاهدة
            $watch_servers = [];
            if (!empty($season['watch_servers'])) {
                $watch_servers = json_decode($season['watch_servers'], true) ?: [];
            }
            
            // جلب روابط التحميل
            $download_servers = [];
            if (!empty($season['download_servers'])) {
                $download_servers = json_decode($season['download_servers'], true) ?: [];
            }
            
            // جلب حلقات الموسم
            $episodes_stmt = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
            $episodes_stmt->execute([$season_id]);
            $episodes = $episodes_stmt->fetchAll();
            
            $season['watch_servers_array'] = $watch_servers;
            $season['download_servers_array'] = $download_servers;
            $season['episodes'] = $episodes;
            
            echo json_encode(['success' => true, 'data' => $season]);
        } else {
            echo json_encode(['success' => false, 'message' => 'الموسم غير موجود']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

// =============================================
// دوال رفع الملفات
// =============================================

/**
 * رفع ملف فيديو
 */
function uploadVideo($pdo) {
    $item_type = $_POST['item_type'] ?? 'movie';
    $item_id = (int)($_POST['item_id'] ?? 0);
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'message' => 'معرف العنصر مطلوب']);
        return;
    }
    
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'لم يتم رفع أي ملف']);
        return;
    }
    
    $upload_dir = __DIR__ . '/../uploads/videos/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $video_ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
    $allowed_video_ext = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'm4v', 'mpg', 'mpeg'];
    
    if (!in_array(strtolower($video_ext), $allowed_video_ext)) {
        echo json_encode(['success' => false, 'message' => 'صيغة الفيديو غير مدعومة']);
        return;
    }
    
    $video_name = time() . '_' . uniqid() . '_video.' . $video_ext;
    $video_path = $upload_dir . $video_name;
    
    if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_path)) {
        $video_url = 'uploads/videos/' . $video_name;
        
        if ($item_type == 'movie') {
            $stmt = $pdo->prepare("UPDATE movies SET video_url = ? WHERE id = ?");
            $stmt->execute([$video_url, $item_id]);
        } elseif ($item_type == 'episode') {
            $stmt = $pdo->prepare("UPDATE episodes SET video_url = ? WHERE id = ?");
            $stmt->execute([$video_url, $item_id]);
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم رفع الفيديو بنجاح', 
            'video_url' => $video_url
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل رفع الملف']);
    }
}

/**
 * رفع ملف ترجمة
 */
function uploadSubtitle($pdo) {
    $item_type = $_POST['item_type'] ?? 'movie';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $language_code = $_POST['language_code'] ?? 'ar';
    $language_name = $_POST['language_name'] ?? 'العربية';
    
    if (!$item_id) {
        echo json_encode(['success' => false, 'message' => 'معرف العنصر مطلوب']);
        return;
    }
    
    if (!isset($_FILES['subtitle_file']) || $_FILES['subtitle_file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'لم يتم رفع أي ملف']);
        return;
    }
    
    $upload_dir = __DIR__ . '/../uploads/subtitles/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_ext = pathinfo($_FILES['subtitle_file']['name'], PATHINFO_EXTENSION);
    $allowed_ext = ['srt', 'vtt', 'ass', 'ssa', 'sub'];
    
    if (!in_array(strtolower($file_ext), $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'صيغة الترجمة غير مدعومة']);
        return;
    }
    
    $file_name = time() . '_' . uniqid() . '_subtitle.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($_FILES['subtitle_file']['tmp_name'], $file_path)) {
        $subtitle_file = 'uploads/subtitles/' . $file_name;
        
        // إنشاء جدول subtitles إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subtitles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_type ENUM('movie', 'series', 'episode') NOT NULL,
                content_id INT NOT NULL,
                language VARCHAR(50) NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                subtitle_url TEXT,
                subtitle_file VARCHAR(500),
                is_default BOOLEAN DEFAULT FALSE,
                downloads INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_content (content_type, content_id),
                INDEX idx_language (language_code)
            )
        ");
        
        $sql = "INSERT INTO subtitles (content_type, content_id, language, language_code, subtitle_file) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_type, $item_id, $language_name, $language_code, $subtitle_file]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم رفع الترجمة بنجاح',
            'subtitle_id' => $pdo->lastInsertId(),
            'subtitle_file' => $subtitle_file
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل رفع الملف']);
    }
}

/**
 * إضافة ترجمة برابط خارجي
 */
function addSubtitle($pdo) {
    $item_type = $_POST['item_type'] ?? 'movie';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $language_code = $_POST['language_code'] ?? 'ar';
    $language_name = $_POST['language_name'] ?? 'العربية';
    $subtitle_url = $_POST['subtitle_url'] ?? '';
    $is_default = isset($_POST['is_default']) ? 1 : 0;
    
    if (!$item_id || empty($subtitle_url)) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // إنشاء جدول subtitles إذا لم يكن موجوداً
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subtitles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                content_type ENUM('movie', 'series', 'episode') NOT NULL,
                content_id INT NOT NULL,
                language VARCHAR(50) NOT NULL,
                language_code VARCHAR(10) NOT NULL,
                subtitle_url TEXT,
                subtitle_file VARCHAR(500),
                is_default BOOLEAN DEFAULT FALSE,
                downloads INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_content (content_type, content_id),
                INDEX idx_language (language_code)
            )
        ");
        
        if ($is_default) {
            $pdo->prepare("UPDATE subtitles SET is_default = FALSE WHERE content_type = ? AND content_id = ?")
               ->execute([$item_type, $item_id]);
        }
        
        $sql = "INSERT INTO subtitles (content_type, content_id, language, language_code, subtitle_url, is_default) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$item_type, $item_id, $language_name, $language_code, $subtitle_url, $is_default]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'تم إضافة الترجمة بنجاح',
            'subtitle_id' => $pdo->lastInsertId()
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * حذف ترجمة
 */
function deleteSubtitle($pdo) {
    $subtitle_id = (int)($_POST['subtitle_id'] ?? 0);
    
    if (!$subtitle_id) {
        echo json_encode(['success' => false, 'message' => 'معرف الترجمة مطلوب']);
        return;
    }
    
    try {
        // جلب مسار الملف لحذفه
        $stmt = $pdo->prepare("SELECT subtitle_file FROM subtitles WHERE id = ?");
        $stmt->execute([$subtitle_id]);
        $subtitle = $stmt->fetch();
        
        if ($subtitle && !empty($subtitle['subtitle_file'])) {
            $file_path = __DIR__ . '/../' . $subtitle['subtitle_file'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM subtitles WHERE id = ?");
        $stmt->execute([$subtitle_id]);
        
        echo json_encode(['success' => true, 'message' => 'تم حذف الترجمة بنجاح']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}

/**
 * جلب المواسم والحلقات من TMDB وإضافتها إلى قاعدة البيانات
 */
function fetchSeriesSeasons($pdo) {
    $series_id = (int)($_POST['series_id'] ?? 0);
    $tmdb_id = (int)($_POST['tmdb_id'] ?? 0);
    $api_key = $_POST['api_key'] ?? '';
    
    if (!$series_id || !$tmdb_id || !$api_key) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
        return;
    }
    
    try {
        // جلب بيانات المسلسل الأساسية من TMDB
        $url = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language=ar-SA";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            echo json_encode(['success' => false, 'message' => 'فشل جلب البيانات من TMDB']);
            return;
        }
        
        $series_data = json_decode($response, true);
        $total_seasons = (int)($series_data['number_of_seasons'] ?? 0);
        
        if ($total_seasons === 0) {
            echo json_encode(['success' => false, 'message' => 'لا توجد مواسم لهذا المسلسل']);
            return;
        }
        
        $seasons_added = 0;
        $episodes_added = 0;
        
        // جلب كل موسم وحلقاته
        for ($season_num = 1; $season_num <= $total_seasons; $season_num++) {
            $season_url = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_num}?api_key={$api_key}&language=ar-SA";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $season_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $season_response = curl_exec($ch);
            $season_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($season_http_code !== 200) continue;
            
            $season_data = json_decode($season_response, true);
            
            // إضافة الموسم
            $season_sql = "INSERT INTO seasons (series_id, season_number, name, overview, poster, air_date) 
                          VALUES (?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          name = VALUES(name),
                          overview = VALUES(overview),
                          poster = VALUES(poster),
                          air_date = VALUES(air_date)";
            
            $season_stmt = $pdo->prepare($season_sql);
            $season_poster = isset($season_data['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $season_data['poster_path'] : '';
            $season_air_date = $season_data['air_date'] ?? null;
            
            $season_stmt->execute([
                $series_id,
                $season_num,
                $season_data['name'] ?? "الموسم {$season_num}",
                $season_data['overview'] ?? '',
                $season_poster,
                $season_air_date
            ]);
            
            $seasons_added++;
            
            // إضافة الحلقات
            if (isset($season_data['episodes']) && is_array($season_data['episodes'])) {
                foreach ($season_data['episodes'] as $episode) {
                    $episode_sql = "INSERT INTO episodes 
                                   (series_id, season_number, episode_number, title, description, duration, still_path, air_date)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE
                                   title = VALUES(title),
                                   description = VALUES(description),
                                   duration = VALUES(duration),
                                   still_path = VALUES(still_path),
                                   air_date = VALUES(air_date)";
                    
                    $episode_stmt = $pdo->prepare($episode_sql);
                    $episode_still = isset($episode['still_path']) ? 'https://image.tmdb.org/t/p/w500' . $episode['still_path'] : '';
                    $episode_number = $episode['episode_number'] ?? '';
                    $episode_title = $episode['name'] ?? "الحلقة {$episode_number}";
                    
                    $episode_stmt->execute([
                        $series_id,
                        $season_num,
                        $episode_number,
                        $episode_title,
                        $episode['overview'] ?? '',
                        $episode['runtime'] ?? 45,
                        $episode_still,
                        $episode['air_date'] ?? null
                    ]);
                    
                    $episodes_added++;
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "تم إضافة {$seasons_added} موسم و {$episodes_added} حلقة بنجاح",
            'seasons_added' => $seasons_added,
            'episodes_added' => $episodes_added
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'خطأ: ' . $e->getMessage()]);
    }
}
?>