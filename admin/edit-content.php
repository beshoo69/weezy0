<?php
// admin/edit-content.php - نظام متكامل لتعديل المحتوى الموجود (نسخة مصححة)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// منع التشغيل من سطر الأوامر
if (php_sapi_name() === 'cli') {
    echo "⚠️ هذا الملف يجب تشغيله من المتصفح وليس من سطر الأوامر!\n";
    echo "استخدم الرابط: http://localhost/fayez-movie/admin/edit-content.php\n";
    exit;
}

// قائمة اللغات المدعومة للترجمة
$subtitle_languages = [
    'ar' => '🇸🇦 العربية',
    'en' => '🇬🇧 English',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch',
    'es' => '🇪🇸 Español',
    'it' => '🇮🇹 Italiano',
    'tr' => '🇹🇷 Türkçe',
    'hi' => '🇮🇳 हिन्दी',
    'ur' => '🇵🇰 اردو',
    'ku' => '🏳️ كوردي',
    'fa' => '🇮🇷 فارسی',
    'ps' => '🇦🇫 پښتو',
    'bn' => '🇧🇩 বাংলা'
];

// تحديد العنصر المطلوب تعديله
$content_type = $_GET['type'] ?? $_POST['content_type'] ?? 'movie';
$content_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0);

// جلب قائمة الأفلام والمسلسلات للاختيار
$all_movies = $pdo->query("SELECT id, title, year, poster FROM movies ORDER BY id DESC LIMIT 50")->fetchAll();
$all_series = $pdo->query("SELECT id, title, year, poster FROM series ORDER BY id DESC LIMIT 50")->fetchAll();

// جلب بيانات العنصر إذا كان معرف موجود
$item = null;
$seasons_data = [];
$watch_servers = [];
$download_servers = [];
$subtitles = [];

if ($content_id > 0) {
    if ($content_type == 'movie') {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$content_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            // جلب روابط المشاهدة للفيلم
            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $watch_servers = $stmt->fetchAll();
            
            // جلب روابط التحميل للفيلم
            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $download_servers = $stmt->fetchAll();
        }
        
    } elseif ($content_type == 'series') {
        $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
        $stmt->execute([$content_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            // جلب المواسم
            $stmt = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
            $stmt->execute([$content_id]);
            $db_seasons = $stmt->fetchAll();
            
            // تجهيز بيانات المواسم للتعديل
            foreach ($db_seasons as $season) {
                // جلب حلقات هذا الموسم
                $stmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? AND season_number = ? ORDER BY episode_number");
                $stmt->execute([$content_id, $season['season_number']]);
                $episodes = $stmt->fetchAll();
                
                $season_episodes = [];
                foreach ($episodes as $ep) {
                    // جلب روابط المشاهدة للحلقة
                    $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'episode' AND item_id = ?");
                    $stmt->execute([$ep['id']]);
                    $ep_watch = $stmt->fetchAll();
                    
                    // جلب روابط التحميل للحلقة
                    $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'episode' AND item_id = ?");
                    $stmt->execute([$ep['id']]);
                    $ep_download = $stmt->fetchAll();
                    
                    $season_episodes[] = [
                        'number' => $ep['episode_number'],
                        'title' => $ep['title'],
                        'description' => $ep['description'] ?? '',
                        'duration' => $ep['duration'] ?? 45,
                        'still_path' => $ep['still_path'] ?? '',
                        'air_date' => $ep['air_date'] ?? null,
                        'watch_servers' => $ep_watch,
                        'download_servers' => $ep_download,
                        'id' => $ep['id']
                    ];
                }
                
                $seasons_data[] = [
                    'number' => $season['season_number'],
                    'name' => $season['name'] ?? "الموسم {$season['season_number']}",
                    'overview' => $season['overview'] ?? '',
                    'poster' => $season['poster'] ?? '',
                    'air_date' => $season['air_date'] ?? null,
                    'episodes' => $season_episodes,
                    'id' => $season['id']
                ];
            }
            
            // جلب روابط المشاهدة للمسلسل
            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $watch_servers = $stmt->fetchAll();
            
            // جلب روابط التحميل للمسلسل
            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $download_servers = $stmt->fetchAll();
        }
        
    } elseif ($content_type == 'episode') {
        $stmt = $pdo->prepare("
            SELECT e.*, s.title as series_title, s.id as series_id 
            FROM episodes e 
            LEFT JOIN series s ON e.series_id = s.id 
            WHERE e.id = ?
        ");
        $stmt->execute([$content_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            // جلب روابط المشاهدة للحلقة
            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'episode' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $watch_servers = $stmt->fetchAll();
            
            // جلب روابط التحميل للحلقة
            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'episode' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$content_id]);
            $download_servers = $stmt->fetchAll();
        }
    }
    
    // جلب الترجمات للعنصر
    if ($content_id > 0 && in_array($content_type, ['movie', 'series', 'episode'])) {
        $stmt = $pdo->prepare("SELECT * FROM subtitles WHERE content_type = ? AND content_id = ? ORDER BY is_default DESC, language");
        $stmt->execute([$content_type, $content_id]);
        $subtitles = $stmt->fetchAll();
    }
}

// =============================================
// معالجة تحديث البيانات
// =============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_content'])) {
    $type = $_POST['content_type'] ?? 'movie';
    $content_id = (int)($_POST['content_id'] ?? 0);
    
    if (!$content_id) {
        $message = "❌ معرف العنصر مطلوب";
        $messageType = "error";
    } else {
        $title = $_POST['title'] ?? '';
        $title_en = $_POST['title_en'] ?? '';
        $overview = $_POST['overview'] ?? '';
        $year = $_POST['year'] ?? date('Y');
        $country = $_POST['country'] ?? '';
        $language = $_POST['language'] ?? 'ar';
        $genre = $_POST['genre'] ?? '';
        $duration = (int)($_POST['duration'] ?? 0);
        $imdb_rating = (float)($_POST['imdb_rating'] ?? 0);
        $membership_level = $_POST['membership_level'] ?? 'basic';
        $status = $_POST['status'] ?? 'published';
        $quality = $_POST['quality'] ?? 'HD';
        
        // معالجة الصور
        $poster_url = $_POST['poster_url'] ?? '';
        $backdrop_url = $_POST['backdrop_url'] ?? '';
        
        $local_poster = null;
        $local_backdrop = null;
        
        // إذا تم رفع صور جديدة
        if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_poster.' . $ext;
            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $upload_dir . $filename)) {
                $local_poster = 'uploads/posters/' . $filename;
            }
        }
        
        if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_backdrop.' . $ext;
            if (move_uploaded_file($_FILES['backdrop_file']['tmp_name'], $upload_dir . $filename)) {
                $local_backdrop = 'uploads/posters/' . $filename;
            }
        }
        
        try {
            if ($type == 'movie') {
                $sql = "UPDATE movies SET 
                        title = ?, 
                        title_en = ?, 
                        description = ?, 
                        year = ?, 
                        country = ?, 
                        language = ?, 
                        genre = ?, 
                        duration = ?, 
                        imdb_rating = ?, 
                        membership_level = ?,
                        status = ?,
                        quality = ?";
                
                $params = [$title, $title_en, $overview, $year, $country, $language, $genre, $duration, $imdb_rating, $membership_level, $status, $quality];
                
                if ($local_poster) {
                    $sql .= ", poster = ?";
                    $params[] = $local_poster;
                }
                
                if ($local_backdrop) {
                    $sql .= ", backdrop = ?";
                    $params[] = $local_backdrop;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $content_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = "✅ تم تحديث الفيلم بنجاح!";
                
            } elseif ($type == 'series') {
                $sql = "UPDATE series SET 
                        title = ?, 
                        title_en = ?, 
                        description = ?, 
                        year = ?, 
                        country = ?, 
                        language = ?, 
                        genre = ?, 
                        imdb_rating = ?, 
                        membership_level = ?,
                        status = ?,
                        quality = ?";
                
                $params = [$title, $title_en, $overview, $year, $country, $language, $genre, $imdb_rating, $membership_level, $status, $quality];
                
                if ($local_poster) {
                    $sql .= ", poster = ?";
                    $params[] = $local_poster;
                }
                
                if ($local_backdrop) {
                    $sql .= ", backdrop = ?";
                    $params[] = $local_backdrop;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $content_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = "✅ تم تحديث المسلسل بنجاح!";
                
            } elseif ($type == 'episode') {
                $sql = "UPDATE episodes SET 
                        title = ?, 
                        description = ?, 
                        duration = ?, 
                        still_path = ?, 
                        air_date = ?
                        WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title, $overview, $duration, $poster_url, $year, $content_id]);
                
                $message = "✅ تم تحديث الحلقة بنجاح!";
            }
            
            $messageType = "success";
            
            // إعادة تحميل الصفحة بعد التحديث
            header("Location: edit-content.php?type=$type&id=$content_id&updated=1");
            exit;
            
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// =============================================
// معالجة إضافة رابط مشاهدة
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_watch_link'])) {
    $item_type = $_POST['item_type'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر مشاهدة';
    $server_url = $_POST['server_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $language = $_POST['language'] ?? 'arabic';
    
    if ($item_id && !empty($server_url)) {
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
            
            header("Location: edit-content.php?type=$item_type&id=$item_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// =============================================
// معالجة حذف رابط مشاهدة
// =============================================
if (isset($_GET['delete_watch_link'])) {
    $link_id = (int)$_GET['delete_watch_link'];
    $type = $_GET['type'] ?? 'movie';
    $id = (int)$_GET['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM watch_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        header("Location: edit-content.php?type=$type&id=$id&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}

// =============================================
// معالجة إضافة رابط تحميل
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_download_link'])) {
    $item_type = $_POST['item_type'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر تحميل';
    $download_url = $_POST['download_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $size = $_POST['size'] ?? '';
    
    if ($item_id && !empty($download_url)) {
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
            
            header("Location: edit-content.php?type=$item_type&id=$item_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// =============================================
// معالجة حذف رابط تحميل
// =============================================
if (isset($_GET['delete_download_link'])) {
    $link_id = (int)$_GET['delete_download_link'];
    $type = $_GET['type'] ?? 'movie';
    $id = (int)$_GET['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM download_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        header("Location: edit-content.php?type=$type&id=$id&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المحتوى - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
        }
        
        .header {
            background: #0a0a0a;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 28px;
        }
        
        .logo span { color: #fff; }
        
        .back-link {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { color: #e50914; }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .alert-error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }
        
        .selector-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .selector-title {
            color: #e50914;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selector-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .selector-box {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
        }
        
        .selector-box h3 {
            color: #e50914;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selector-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #333;
            border-radius: 5px;
        }
        
        .selector-item {
            padding: 10px 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .selector-item:hover {
            background: #333;
        }
        
        .selector-item.selected {
            background: #e50914;
            color: white;
        }
        
        .selector-item-info {
            flex: 1;
            margin: 0 10px;
        }
        
        .selector-item-title {
            font-weight: bold;
        }
        
        .selector-item-year {
            font-size: 11px;
            color: #b3b3b3;
        }
        
        .edit-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        
        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .form-tab {
            padding: 10px 25px;
            background: transparent;
            border: none;
            color: #b3b3b3;
            font-weight: bold;
            cursor: pointer;
            border-radius: 30px;
            transition: 0.3s;
        }
        
        .form-tab.active {
            background: #e50914;
            color: #fff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .preview-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .preview-box {
            background: #252525;
            border-radius: 10px;
            padding: 10px;
        }
        
        .preview-box img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e50914;
        }
        
        .upload-section {
            background: #1a2a1a;
            border: 2px dashed #27ae60;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .upload-title {
            color: #27ae60;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-box {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .upload-icon {
            font-size: 40px;
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .upload-label {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .upload-hint {
            color: #b3b3b3;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .file-input {
            width: 100%;
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #27ae60;
            border-radius: 5px;
            color: #fff;
            margin-top: 10px;
        }
        
        .membership-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #333;
        }
        
        .membership-title {
            color: #e50914;
            font-size: 18px;
            margin-bottom: 20px;
        }
        
        .membership-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .membership-option {
            flex: 1;
            min-width: 150px;
            background: #252525;
            border: 2px solid;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .membership-option:hover {
            transform: translateY(-3px);
        }
        
        .membership-option input[type="radio"] {
            margin-left: 10px;
        }
        
        .membership-name {
            font-size: 16px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .membership-desc {
            color: #b3b3b3;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .links-section {
            margin: 20px 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-header h3 {
            color: #e50914;
        }
        
        .add-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .add-btn:hover {
            background: #219a52;
        }
        
        .links-table {
            width: 100%;
            border-collapse: collapse;
            background: #252525;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .links-table th {
            background: #333;
            color: #e50914;
            padding: 10px;
        }
        
        .links-table td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        
        .links-table tr:hover {
            background: #2a2a2a;
        }
        
        .delete-btn {
            background: #e50914;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background: #b20710;
        }
        
        .subtitles-section {
            margin: 20px 0;
        }
        
        .subtitle-item {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto;
            gap: 10px;
            align-items: center;
        }
        
        .subtitle-item select,
        .subtitle-item input {
            padding: 8px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
        }
        
        .remove-btn {
            background: #e50914;
            color: #fff;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background: #219a52;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #b3b3b3;
        }
        
        @media (max-width: 768px) {
            .selector-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .preview-images {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <a href="dashboard-pro.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة
        </a>
    </div>
    
    <div class="container">
        <h1>
            <i class="fas fa-edit"></i>
            تعديل المحتوى الموجود
        </h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            ✅ تم تحديث المحتوى بنجاح
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">
            ✅ تمت الإضافة بنجاح
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            ✅ تم الحذف بنجاح
        </div>
        <?php endif; ?>
        
        <!-- قسم اختيار المحتوى -->
        <div class="selector-section">
            <div class="selector-title">
                <i class="fas fa-search"></i>
                اختر المحتوى الذي تريد تعديله
            </div>
            
            <div class="selector-grid">
                <!-- اختيار فيلم -->
                <div class="selector-box">
                    <h3><i class="fas fa-film"></i> أفلام</h3>
                    <div class="selector-list">
                        <?php if (empty($all_movies)): ?>
                            <div class="selector-item">لا توجد أفلام</div>
                        <?php else: ?>
                            <?php foreach ($all_movies as $movie): ?>
                            <div class="selector-item <?php echo ($content_type == 'movie' && $content_id == $movie['id']) ? 'selected' : ''; ?>"
                                 onclick="location.href='?type=movie&id=<?php echo $movie['id']; ?>'">
                                <div class="selector-item-info">
                                    <div class="selector-item-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                    <div class="selector-item-year"><?php echo $movie['year']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- اختيار مسلسل -->
                <div class="selector-box">
                    <h3><i class="fas fa-tv"></i> مسلسلات</h3>
                    <div class="selector-list">
                        <?php if (empty($all_series)): ?>
                            <div class="selector-item">لا توجد مسلسلات</div>
                        <?php else: ?>
                            <?php foreach ($all_series as $series): ?>
                            <div class="selector-item <?php echo ($content_type == 'series' && $content_id == $series['id']) ? 'selected' : ''; ?>"
                                 onclick="location.href='?type=series&id=<?php echo $series['id']; ?>'">
                                <div class="selector-item-info">
                                    <div class="selector-item-title"><?php echo htmlspecialchars($series['title']); ?></div>
                                    <div class="selector-item-year"><?php echo $series['year']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($item): ?>
        <!-- نموذج التعديل -->
        <div class="edit-section">
            <div class="section-title">
                <i class="fas fa-edit" style="color: #e50914;"></i>
                تعديل: <?php echo htmlspecialchars($item['title'] ?? $item['name'] ?? ''); ?>
            </div>
            
            <div class="form-tabs">
                <button class="form-tab active" onclick="showTab('basic')">📋 معلومات أساسية</button>
                <button class="form-tab" onclick="showTab('membership')">👑 مستوى العضوية</button>
                <button class="form-tab" onclick="showTab('links')">🔗 روابط</button>
                <button class="form-tab" onclick="showTab('upload')">📁 رفع يدوي</button>
                <button class="form-tab" onclick="showTab('subtitles')">📝 ترجمة</button>
            </div>
            
            <form method="POST" id="contentForm" enctype="multipart/form-data">
                <input type="hidden" name="content_type" value="<?php echo $content_type; ?>">
                <input type="hidden" name="content_id" value="<?php echo $content_id; ?>">
                <input type="hidden" name="update_content" value="1">
                
                <!-- تبويب المعلومات الأساسية -->
                <div id="basicTab" class="tab-content active">
                    <?php if ($content_type == 'movie' || $content_type == 'series'): ?>
                    <div class="preview-images">
                        <div class="preview-box">
                            <label>صورة البوستر الحالية</label>
                            <img src="<?php echo $item['poster'] ?? 'https://via.placeholder.com/300x450?text=No+Poster'; ?>" id="poster_preview" alt="poster">
                            <input type="file" name="poster_file" accept="image/*" style="margin-top: 10px; width: 100%;">
                        </div>
                        <div class="preview-box">
                            <label>صورة الخلفية الحالية</label>
                            <img src="<?php echo $item['backdrop'] ?? 'https://via.placeholder.com/1280x720?text=No+Backdrop'; ?>" id="backdrop_preview" alt="backdrop">
                            <input type="file" name="backdrop_file" accept="image/*" style="margin-top: 10px; width: 100%;">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>العنوان (عربي)</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($item['title'] ?? $item['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>العنوان (إنجليزي)</label>
                            <input type="text" name="title_en" value="<?php echo htmlspecialchars($item['title_en'] ?? $item['original_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>سنة الإنتاج</label>
                            <input type="number" name="year" value="<?php echo $item['year'] ?? date('Y'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>البلد</label>
                            <input type="text" name="country" value="<?php echo htmlspecialchars($item['country'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>اللغة</label>
                            <select name="language">
                                <option value="ar" <?php echo ($item['language'] == 'ar') ? 'selected' : ''; ?>>العربية</option>
                                <option value="en" <?php echo ($item['language'] == 'en') ? 'selected' : ''; ?>>الإنجليزية</option>
                                <option value="tr" <?php echo ($item['language'] == 'tr') ? 'selected' : ''; ?>>التركية</option>
                                <option value="hi" <?php echo ($item['language'] == 'hi') ? 'selected' : ''; ?>>الهندية</option>
                                <option value="ko" <?php echo ($item['language'] == 'ko') ? 'selected' : ''; ?>>الكورية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>التصنيف</label>
                            <input type="text" name="genre" value="<?php echo htmlspecialchars($item['genre'] ?? ''); ?>">
                        </div>
                        
                        <?php if ($content_type == 'movie'): ?>
                        <div class="form-group">
                            <label>المدة (دقائق)</label>
                            <input type="number" name="duration" value="<?php echo $item['duration'] ?? 0; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>تقييم IMDB</label>
                            <input type="number" step="0.1" name="imdb_rating" value="<?php echo $item['imdb_rating'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الوصف</label>
                            <textarea name="overview" rows="5"><?php echo htmlspecialchars($item['description'] ?? $item['overview'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>الحالة</label>
                            <select name="status">
                                <option value="published" <?php echo ($item['status'] == 'published') ? 'selected' : ''; ?>>منشور</option>
                                <option value="draft" <?php echo ($item['status'] == 'draft') ? 'selected' : ''; ?>>مسودة</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K" <?php echo ($item['quality'] == '4K') ? 'selected' : ''; ?>>4K UHD</option>
                                <option value="1080p" <?php echo ($item['quality'] == '1080p') ? 'selected' : ''; ?>>1080p HD</option>
                                <option value="720p" <?php echo ($item['quality'] == '720p') ? 'selected' : ''; ?>>720p HD</option>
                                <option value="480p" <?php echo ($item['quality'] == '480p') ? 'selected' : ''; ?>>480p</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب مستوى العضوية -->
                <div id="membershipTab" class="tab-content">
                    <div class="membership-section">
                        <div class="membership-title">
                            <i class="fas fa-crown" style="color: gold;"></i>
                            مستوى العضوية المطلوب للمشاهدة
                        </div>
                        
                        <div class="membership-options">
                            <label class="membership-option" style="border-color: #6c757d;">
                                <input type="radio" name="membership_level" value="basic" <?php echo ($item['membership_level'] == 'basic') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: #6c757d;">عادي</div>
                                    <div class="membership-desc">متاح للجميع مجاناً</div>
                                </div>
                            </label>
                            
                            <label class="membership-option" style="border-color: #e50914;">
                                <input type="radio" name="membership_level" value="premium" <?php echo ($item['membership_level'] == 'premium') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: #e50914;">مميز ⭐</div>
                                    <div class="membership-desc">للمشتركين المميزين فقط</div>
                                </div>
                            </label>
                            
                            <label class="membership-option" style="border-color: gold;">
                                <input type="radio" name="membership_level" value="vip" <?php echo ($item['membership_level'] == 'vip') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: gold;">VIP 👑</div>
                                    <div class="membership-desc">حصري لمشتركي VIP</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الروابط -->
                <div id="linksTab" class="tab-content">
                    <div class="links-section">
                        <div class="section-header">
                            <h3>روابط المشاهدة</h3>
                            <button type="button" class="add-btn" onclick="showAddWatchLinkForm()">
                                <i class="fas fa-plus"></i> إضافة رابط
                            </button>
                        </div>
                        
                        <?php if (empty($watch_servers)): ?>
                        <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد روابط مشاهدة</p>
                        <?php else: ?>
                        <table class="links-table">
                            <thead>
                                <tr>
                                    <th>اسم السيرفر</th>
                                    <th>الجودة</th>
                                    <th>اللغة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($watch_servers as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['server_name']); ?></td>
                                    <td><?php echo $link['quality']; ?></td>
                                    <td><?php echo $link['language'] == 'arabic' ? 'عربي' : 'English'; ?></td>
                                    <td>
                                        <a href="?delete_watch_link=<?php echo $link['id']; ?>&type=<?php echo $content_type; ?>&id=<?php echo $content_id; ?>" 
                                           class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا الرابط؟')">
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    
                    <div class="links-section" style="margin-top: 30px;">
                        <div class="section-header">
                            <h3>روابط التحميل</h3>
                            <button type="button" class="add-btn" onclick="showAddDownloadLinkForm()">
                                <i class="fas fa-plus"></i> إضافة رابط
                            </button>
                        </div>
                        
                        <?php if (empty($download_servers)): ?>
                        <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد روابط تحميل</p>
                        <?php else: ?>
                        <table class="links-table">
                            <thead>
                                <tr>
                                    <th>اسم السيرفر</th>
                                    <th>الجودة</th>
                                    <th>الحجم</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($download_servers as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['server_name']); ?></td>
                                    <td><?php echo $link['quality']; ?></td>
                                    <td><?php echo $link['size'] ?? '-'; ?></td>
                                    <td>
                                        <a href="?delete_download_link=<?php echo $link['id']; ?>&type=<?php echo $content_type; ?>&id=<?php echo $content_id; ?>" 
                                           class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا الرابط؟')">
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- تبويب الرفع اليدوي -->
                <div id="uploadTab" class="tab-content">
                    <div class="upload-section">
                        <div class="upload-title">
                            <i class="fas fa-cloud-upload-alt"></i>
                            رفع ملفات من جهازك
                        </div>
                        
                        <div class="form-grid">
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="upload-label">رفع ملف الفيديو</div>
                                <input type="file" name="video_file" class="file-input" accept="video/*">
                                <div class="upload-hint">MP4, MKV, AVI, MOV</div>
                            </div>
                            
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="upload-label">رفع ملف التحميل</div>
                                <input type="file" name="download_file" class="file-input">
                                <div class="upload-hint">أي صيغة ملف</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الترجمة -->
                <div id="subtitlesTab" class="tab-content">
                    <div class="subtitles-section">
                        <div class="section-header">
                            <h3>الترجمات المتاحة</h3>
                            <button type="button" class="add-btn" onclick="addSubtitleField()">
                                <i class="fas fa-plus"></i> إضافة ترجمة
                            </button>
                        </div>
                        
                        <div id="subtitlesContainer">
                            <?php if (!empty($subtitles)): ?>
                                <?php foreach ($subtitles as $index => $sub): ?>
                                <div class="subtitle-item" id="subtitle-<?php echo $index; ?>">
                                    <select name="subtitles[<?php echo $index; ?>][language_code]">
                                        <?php foreach ($subtitle_languages as $code => $name): ?>
                                        <option value="<?php echo $code; ?>" <?php echo ($sub['language_code'] == $code) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="url" name="subtitles[<?php echo $index; ?>][url]" placeholder="رابط الترجمة" value="<?php echo htmlspecialchars($sub['subtitle_url'] ?? ''); ?>">
                                    <label>
                                        <input type="checkbox" name="subtitles[<?php echo $index; ?>][is_default]" value="1" <?php echo $sub['is_default'] ? 'checked' : ''; ?>> افتراضي
                                    </label>
                                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">×</button>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
            
            <!-- نماذج إضافة الروابط -->
            <div id="addWatchLinkForm" style="display: none; margin-top: 20px; padding: 20px; background: #252525; border-radius: 8px;">
                <h4 style="color: #e50914; margin-bottom: 15px;">إضافة رابط مشاهدة جديد</h4>
                <form method="POST">
                    <input type="hidden" name="item_type" value="<?php echo $content_type; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $content_id; ?>">
                    <input type="hidden" name="add_watch_link" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اللغة</label>
                            <select name="language">
                                <option value="arabic">عربي</option>
                                <option value="english">English</option>
                                <option value="turkish">Türkçe</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>اسم السيرفر</label>
                            <input type="text" name="server_name" placeholder="مثال: سيرفر 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K">4K</option>
                                <option value="1080p">1080p</option>
                                <option value="720p">720p</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الرابط</label>
                            <input type="url" name="server_url" placeholder="https://..." required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="add-btn">إضافة</button>
                        <button type="button" class="add-btn" style="background: #e50914;" onclick="hideAddWatchLinkForm()">إلغاء</button>
                    </div>
                </form>
            </div>
            
            <div id="addDownloadLinkForm" style="display: none; margin-top: 20px; padding: 20px; background: #252525; border-radius: 8px;">
                <h4 style="color: #e50914; margin-bottom: 15px;">إضافة رابط تحميل جديد</h4>
                <form method="POST">
                    <input type="hidden" name="item_type" value="<?php echo $content_type; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $content_id; ?>">
                    <input type="hidden" name="add_download_link" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم السيرفر</label>
                            <input type="text" name="server_name" placeholder="مثال: ميديا فاير" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K">4K</option>
                                <option value="1080p">1080p</option>
                                <option value="720p">720p</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>الحجم</label>
                            <input type="text" name="size" placeholder="مثال: 1.5 GB">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الرابط</label>
                            <input type="url" name="download_url" placeholder="https://..." required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="add-btn">إضافة</button>
                        <button type="button" class="add-btn" style="background: #e50914;" onclick="hideAddDownloadLinkForm()">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($content_id > 0 && !$item): ?>
        <div class="alert alert-error">
            ❌ لم يتم العثور على المحتوى المطلوب
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // دوال التبويبات
        function showTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'basic') {
                document.querySelectorAll('.form-tab')[0].classList.add('active');
                document.getElementById('basicTab').classList.add('active');
            } else if (tab === 'membership') {
                document.querySelectorAll('.form-tab')[1].classList.add('active');
                document.getElementById('membershipTab').classList.add('active');
            } else if (tab === 'links') {
                document.querySelectorAll('.form-tab')[2].classList.add('active');
                document.getElementById('linksTab').classList.add('active');
            } else if (tab === 'upload') {
                document.querySelectorAll('.form-tab')[3].classList.add('active');
                document.getElementById('uploadTab').classList.add('active');
            } else if (tab === 'subtitles') {
                document.querySelectorAll('.form-tab')[4].classList.add('active');
                document.getElementById('subtitlesTab').classList.add('active');
            }
        }
        
        // دوال إضافة الروابط
        function showAddWatchLinkForm() {
            document.getElementById('addWatchLinkForm').style.display = 'block';
        }
        
        function hideAddWatchLinkForm() {
            document.getElementById('addWatchLinkForm').style.display = 'none';
        }
        
        function showAddDownloadLinkForm() {
            document.getElementById('addDownloadLinkForm').style.display = 'block';
        }
        
        function hideAddDownloadLinkForm() {
            document.getElementById('addDownloadLinkForm').style.display = 'none';
        }
        
        // دالة إضافة ترجمة
        let subtitleCount = <?php echo count($subtitles); ?>;
        
        function addSubtitleField() {
            const html = `
                <div class="subtitle-item" id="subtitle-${subtitleCount}">
                    <select name="subtitles[${subtitleCount}][language_code]">
                        <?php foreach ($subtitle_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="subtitles[${subtitleCount}][url]" placeholder="رابط الترجمة">
                    <label>
                        <input type="checkbox" name="subtitles[${subtitleCount}][is_default]" value="1"> افتراضي
                    </label>
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">×</button>
                </div>
            `;
            document.getElementById('subtitlesContainer').insertAdjacentHTML('beforeend', html);
            subtitleCount++;
        }
    </script>
</body>
</html>