<?php
// admin/youtube-series-manager.php - إدارة المسلسلات من يوتيوب
define('ALLOW_ACCESS', true);

$base_path = 'C:/xampp/htdocs/fayez-movie';
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$youtube_api_key = 'AIzaSyApqaqvZDto7tpEQEWRYw3QVzguTxfnKcU';

// إنشاء مجلد للصور
$upload_dir = $base_path . '/uploads/youtube/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// دوال مساعدة
function extractPlaylistId($url) {
    $patterns = [
        '/youtube\.com\/playlist\?list=([^&]+)/',
        '/youtube\.com\/watch\?.*list=([^&]+)/',
        '/youtu\.be\/.*\?list=([^&]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return false;
}

function getPlaylistDetails($playlist_id, $api_key) {
    $url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&id=" . $playlist_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['items'][0])) {
        $item = $data['items'][0];
        return [
            'title' => $item['snippet']['title'],
            'description' => $item['snippet']['description'],
            'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
            'channelTitle' => $item['snippet']['channelTitle'],
            'channelId' => $item['snippet']['channelId'],
            'videoCount' => $item['contentDetails']['itemCount'] ?? 0
        ];
    }
    
    return false;
}

function getPlaylistVideos($playlist_id, $api_key) {
    $videos = [];
    $next_page_token = '';
    
    do {
        $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=" . $playlist_id . "&key=" . $api_key;
        if (!empty($next_page_token)) {
            $url .= "&pageToken=" . $next_page_token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!isset($data['items'])) break;
        
        // تجميع IDs الفيديوهات
        $video_ids = [];
        foreach ($data['items'] as $item) {
            if (isset($item['snippet']['resourceId']['videoId'])) {
                $video_ids[] = $item['snippet']['resourceId']['videoId'];
            }
        }
        
        // جلب تفاصيل الفيديوهات
        if (!empty($video_ids)) {
            $ids_string = implode(',', $video_ids);
            $details_url = "https://www.googleapis.com/youtube/v3/videos?part=contentDetails,statistics&id=" . $ids_string . "&key=" . $api_key;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $details_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $details_response = curl_exec($ch);
            curl_close($ch);
            
            $details_data = json_decode($details_response, true);
            $details_map = [];
            
            if (isset($details_data['items'])) {
                foreach ($details_data['items'] as $detail) {
                    $duration = $detail['contentDetails']['duration'];
                    $viewCount = $detail['statistics']['viewCount'] ?? 0;
                    
                    // تحويل المدة
                    $duration = str_replace(['PT', 'H', 'M', 'S'], ['', ':', ':', ''], $duration);
                    $parts = explode(':', $duration);
                    
                    if (count($parts) == 3) {
                        $duration = sprintf("%02d:%02d:%02d", (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                    } elseif (count($parts) == 2) {
                        $duration = sprintf("00:%02d:%02d", (int)$parts[0], (int)$parts[1]);
                    } else {
                        $duration = sprintf("00:00:%02d", (int)$parts[0]);
                    }
                    
                    $details_map[$detail['id']] = [
                        'duration' => $duration,
                        'viewCount' => number_format($viewCount)
                    ];
                }
            }
            
            // تجميع النتائج مع ترتيب الحلقات
            foreach ($data['items'] as $index => $item) {
                $video_id = $item['snippet']['resourceId']['videoId'];
                $snippet = $item['snippet'];
                
                $videos[] = [
                    'videoId' => $video_id,
                    'title' => $snippet['title'],
                    'description' => $snippet['description'],
                    'thumbnail' => $snippet['thumbnails']['high']['url'],
                    'publishedAt' => $snippet['publishedAt'],
                    'position' => $index + 1,
                    'duration' => $details_map[$video_id]['duration'] ?? '00:00:00',
                    'viewCount' => $details_map[$video_id]['viewCount'] ?? '0'
                ];
            }
        }
        
        $next_page_token = $data['nextPageToken'] ?? '';
        
    } while (!empty($next_page_token));
    
    return $videos;
}

function downloadThumbnail($url, $name, $upload_dir) {
    if (empty($url)) return '';
    
    $filename = 'yt_' . $name . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if (!empty($data)) {
        file_put_contents($filepath, $data);
        return 'uploads/youtube/' . $filename;
    }
    
    return $url;
}

// معالجة حذف المسلسل
if (isset($_GET['delete_series'])) {
    $series_id = (int)$_GET['delete_series'];
    
    try {
        $pdo->beginTransaction();
        
        // حذف الصور المحلية للمسلسل
        $series = $pdo->prepare("SELECT local_thumbnail FROM youtube_series WHERE id = ?");
        $series->execute([$series_id]);
        $series_data = $series->fetch();
        
        if ($series_data && !empty($series_data['local_thumbnail'])) {
            $file_path = $base_path . '/' . $series_data['local_thumbnail'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // حذف صور الحلقات
        $episodes = $pdo->prepare("SELECT local_thumbnail FROM youtube_episodes WHERE series_id = ?");
        $episodes->execute([$series_id]);
        while ($ep = $episodes->fetch()) {
            if (!empty($ep['local_thumbnail'])) {
                $file_path = $base_path . '/' . $ep['local_thumbnail'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        
        // حذف الحلقات أولاً
        $delete_episodes = $pdo->prepare("DELETE FROM youtube_episodes WHERE series_id = ?");
        $delete_episodes->execute([$series_id]);
        
        // حذف المسلسل
        $delete_series = $pdo->prepare("DELETE FROM youtube_series WHERE id = ?");
        $delete_series->execute([$series_id]);
        
        $pdo->commit();
        
        $success = "✅ تم حذف المسلسل وجميع حلقاته بنجاح!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "❌ خطأ في الحذف: " . $e->getMessage();
    }
}

// معالجة تحديث المسلسل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_series'])) {
    $series_id = (int)$_POST['series_id'];
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'series';
    
    try {
        $update = $pdo->prepare("
            UPDATE youtube_series SET 
                title = ?,
                description = ?,
                category = ?
            WHERE id = ?
        ");
        $update->execute([$title, $description, $category, $series_id]);
        
        $success = "✅ تم تحديث بيانات المسلسل بنجاح!";
    } catch (Exception $e) {
        $error = "❌ خطأ في التحديث: " . $e->getMessage();
    }
}

// معالجة جلب المسلسل
$playlist_data = null;
$playlist_videos = [];
$existing_series = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_series'])) {
    $playlist_url = $_POST['playlist_url'] ?? '';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($playlist_url)) {
        $playlist_id = extractPlaylistId($playlist_url);
        
        if (!$playlist_id) {
            $error = "رابط قائمة تشغيل غير صحيح";
        } else {
            // التحقق من وجود المسلسل مسبقاً
            $check = $pdo->prepare("SELECT * FROM youtube_series WHERE playlist_id = ?");
            $check->execute([$playlist_id]);
            $existing_series = $check->fetch();
            
            // جلب تفاصيل القائمة
            $playlist_details = getPlaylistDetails($playlist_id, $youtube_api_key);
            
            if ($playlist_details) {
                $playlist_data = [
                    'playlist_id' => $playlist_id,
                    'title' => $playlist_details['title'],
                    'description' => $playlist_details['description'],
                    'thumbnail' => $playlist_details['thumbnail'],
                    'channelTitle' => $playlist_details['channelTitle'],
                    'channelId' => $playlist_details['channelId'],
                    'videoCount' => $playlist_details['videoCount']
                ];
                
                // جلب جميع الحلقات
                $playlist_videos = getPlaylistVideos($playlist_id, $youtube_api_key);
                
                // تخزين في الجلسة
                $_SESSION['playlist_data'] = $playlist_data;
                $_SESSION['playlist_videos'] = $playlist_videos;
            } else {
                $error = "لم يتم العثور على القائمة";
            }
        }
    }
}

// حفظ المسلسل كاملاً
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_series'])) {
    $playlist_id = $_POST['playlist_id'] ?? '';
    $series_title = $_POST['series_title'] ?? '';
    $series_description = $_POST['series_description'] ?? '';
    $series_category = $_POST['series_category'] ?? 'series';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($playlist_id) && isset($_SESSION['playlist_data']) && isset($_SESSION['playlist_videos'])) {
        $playlist_data = $_SESSION['playlist_data'];
        $playlist_videos = $_SESSION['playlist_videos'];
        
        try {
            $pdo->beginTransaction();
            
            // تحميل صورة المسلسل
            $local_thumbnail = '';
            if ($download_images) {
                $local_thumbnail = downloadThumbnail($playlist_data['thumbnail'], 'series_' . $playlist_id, $upload_dir);
            }
            
            // إدراج المسلسل
            $insert_series = $pdo->prepare("
                INSERT INTO youtube_series (
                    playlist_id, title, description, thumbnail, channel_title, channel_id,
                    video_count, category, local_thumbnail, added_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    description = VALUES(description),
                    thumbnail = VALUES(thumbnail),
                    video_count = VALUES(video_count),
                    local_thumbnail = VALUES(local_thumbnail)
            ");
            
            $insert_series->execute([
                $playlist_id,
                $series_title ?: $playlist_data['title'],
                $series_description ?: $playlist_data['description'],
                $playlist_data['thumbnail'],
                $playlist_data['channelTitle'],
                $playlist_data['channelId'],
                count($playlist_videos),
                $series_category,
                $local_thumbnail,
                $_SESSION['username'] ?? 'admin'
            ]);
            
            $series_id = $pdo->lastInsertId();
            if (!$series_id) {
                // إذا كان موجوداً، نجيب الـ ID
                $get_id = $pdo->prepare("SELECT id FROM youtube_series WHERE playlist_id = ?");
                $get_id->execute([$playlist_id]);
                $series_id = $get_id->fetchColumn();
            }
            
            // حذف الحلقات القديمة
            $delete_episodes = $pdo->prepare("DELETE FROM youtube_episodes WHERE series_id = ?");
            $delete_episodes->execute([$series_id]);
            
            // إدراج الحلقات الجديدة
            $insert_episode = $pdo->prepare("
                INSERT INTO youtube_episodes (
                    series_id, video_id, title, description, thumbnail,
                    episode_number, duration, view_count, published_at, local_thumbnail
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $episode_count = 0;
            foreach ($playlist_videos as $index => $video) {
                // تحميل صورة الحلقة
                $episode_thumbnail = '';
                if ($download_images) {
                    $episode_thumbnail = downloadThumbnail($video['thumbnail'], 'ep_' . $video['videoId'], $upload_dir);
                }
                
                $insert_episode->execute([
                    $series_id,
                    $video['videoId'],
                    $video['title'],
                    $video['description'] ?? '',
                    $video['thumbnail'],
                    $index + 1,
                    $video['duration'],
                    $video['viewCount'],
                    date('Y-m-d H:i:s', strtotime($video['publishedAt'])),
                    $episode_thumbnail
                ]);
                $episode_count++;
            }
            
            $pdo->commit();
            
            $success = "✅ تم حفظ المسلسل بنجاح!";
            $success .= "<br>عدد الحلقات: " . $episode_count;
            
            // مسح الجلسة
            unset($_SESSION['playlist_data']);
            unset($_SESSION['playlist_videos']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

// جلب المسلسلات المحفوظة مع إحصائيات - تم إصلاح مشكلة SQL
$saved_series = [];
try {
    $saved_series = $pdo->query("
        SELECT s.*, 
               (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as episodes_count,
               (SELECT SUM(CAST(REPLACE(view_count, ',', '') AS UNSIGNED)) FROM youtube_episodes WHERE series_id = s.id) as total_views
        FROM youtube_series s 
        ORDER BY s.id DESC 
        LIMIT 50
    ")->fetchAll();
} catch (Exception $e) {
    $saved_series = [];
    $error = "خطأ في جلب البيانات: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المسلسلات - يوتيوب</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* ضع هنا نفس التنسيقات السابقة */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(229, 9, 20, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo h1 {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
        }

        .logo span {
            color: #fff;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1a1a1a;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #333;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: #e50914;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .dashboard-container {
            display: flex;
            padding: 30px;
            gap: 30px;
            min-height: calc(100vh - 80px);
        }

        .sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #333;
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            flex-shrink: 0;
        }

        .nav-section {
            color: #b3b3b3;
            font-size: 12px;
            margin: 25px 0 15px 10px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #b3b3b3;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            gap: 12px;
            transition: all 0.3s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .content-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }

        .section-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e50914;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            background: rgba(0,0,0,0.3);
            border: 2px solid #333;
            border-radius: 10px;
            color: #fff;
        }

        .search-input:focus {
            border-color: #e50914;
            outline: none;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
        }

        .btn-primary {
            background: #e50914;
        }

        .btn-primary:hover {
            background: #b20710;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-info {
            background: #3498db;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #252525;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #333;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #e50914;
        }

        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
        }

        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .series-card {
            background: #252525;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: 0.3s;
        }

        .series-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .series-thumb {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .series-info {
            padding: 15px;
        }

        .series-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .series-meta {
            display: flex;
            gap: 15px;
            color: #b3b3b3;
            font-size: 13px;
        }

        .notification {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 50px;
            background: #27ae60;
            color: white;
            z-index: 9999;
            animation: slideDown 0.5s ease;
        }

        .notification.error {
            background: #e50914;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-film" style="color: #e50914; font-size: 32px;"></i>
            <h1>ويزي<span>برو</span></h1>
        </div>
        <div class="user-menu">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if (isset($error)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
            <div class="notification">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- إحصائيات سريعة -->
            <?php if (!empty($saved_series)): ?>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo count($saved_series); ?></div>
                    <div class="stat-label">إجمالي المسلسلات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">
                        <?php 
                        $total_eps = 0;
                        foreach ($saved_series as $s) $total_eps += $s['episodes_count'];
                        echo $total_eps;
                        ?>
                    </div>
                    <div class="stat-label">إجمالي الحلقات</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- جلب مسلسل جديد -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-plus-circle"></i> إضافة مسلسل من يوتيوب</h2>
                </div>

                <form method="POST">
                    <div class="search-box">
                        <input type="url" name="playlist_url" class="search-input" 
                               placeholder="أدخل رابط قائمة تشغيل المسلسل (playlist)" required>
                        
                        <label style="display: flex; align-items: center; gap: 5px; color: #b3b3b3;">
                            <input type="checkbox" name="download_images" value="1" checked>
                            تحميل الصور محلياً
                        </label>
                        
                        <button type="submit" name="fetch_series" class="btn btn-primary">
                            <i class="fas fa-search"></i> جلب المسلسل
                        </button>
                    </div>
                    <p style="color: #b3b3b3; font-size: 13px;">
                        <i class="fas fa-info-circle"></i>
                        مثال: https://www.youtube.com/playlist?list=PLXXXXX
                    </p>
                </form>

                <?php if ($playlist_data && !empty($playlist_videos)): ?>
                <div class="content-section" style="margin-top: 20px; border-color: #e50914;">
                    <form method="POST">
                        <input type="hidden" name="playlist_id" value="<?php echo $playlist_data['playlist_id']; ?>">
                        
                        <h3 style="color: #e50914; margin-bottom: 15px;"><?php echo htmlspecialchars($playlist_data['title']); ?></h3>
                        
                        <div style="margin-bottom: 15px;">
                            <label>عنوان المسلسل:</label>
                            <input type="text" name="series_title" class="search-input" 
                                   value="<?php echo htmlspecialchars($playlist_data['title']); ?>">
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>الوصف:</label>
                            <textarea name="series_description" class="search-input" rows="3"><?php echo htmlspecialchars($playlist_data['description']); ?></textarea>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label>التصنيف:</label>
                            <select name="series_category" class="search-input" style="width: 200px;">
                                <option value="series">📺 مسلسل</option>
                                <option value="movies">🎬 أفلام</option>
                                <option value="general">🎥 عام</option>
                            </select>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: flex; align-items: center; gap: 5px;">
                                <input type="checkbox" name="download_images" value="1" checked>
                                تحميل جميع الصور محلياً
                            </label>
                        </div>
                        
                        <p style="color: #b3b3b3; margin: 15px 0;">
                            عدد الحلقات: <?php echo count($playlist_videos); ?>
                        </p>
                        
                        <button type="submit" name="save_series" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ المسلسل (<?php echo count($playlist_videos); ?> حلقة)
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- المسلسلات المحفوظة -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-database"></i> المسلسلات المحفوظة</h2>
                    <a href="../free.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> عرض الصفحة العامة
                    </a>
                </div>

                <?php if (!empty($saved_series)): ?>
                <div class="series-grid">
                    <?php foreach ($saved_series as $series): ?>
                    <div class="series-card">
                        <img src="<?php echo !empty($series['local_thumbnail']) ? '../' . $series['local_thumbnail'] : $series['thumbnail']; ?>" 
                             class="series-thumb" 
                             alt=""
                             onerror="this.src='https://via.placeholder.com/300x150?text=No+Image'">
                        <div class="series-info">
                            <div class="series-title"><?php echo htmlspecialchars($series['title']); ?></div>
                            <div class="series-meta">
                                <span><i class="fas fa-list"></i> <?php echo $series['episodes_count']; ?> حلقة</span>
                                <span><i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($series['created_at'])); ?></span>
                            </div>
                            <div style="margin-top: 10px; display: flex; gap: 10px;">
                                <a href="youtube-series-episodes.php?id=<?php echo $series['id']; ?>" class="btn btn-info btn-sm">
                                    <i class="fas fa-list"></i> الحلقات
                                </a>
                                <a href="?delete_series=<?php echo $series['id']; ?>" class="btn btn-primary btn-sm" 
                                   onclick="return confirm('هل أنت متأكد من حذف هذا المسلسل؟')">
                                    <i class="fas fa-trash"></i> حذف
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #b3b3b3; padding: 40px;">
                    لا يوجد مسلسلات محفوظة بعد
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    setTimeout(() => {
        let notification = document.querySelector('.notification');
        if (notification) {
            notification.style.animation = 'slideDown 0.5s reverse';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);
    </script>
</body>
</html>