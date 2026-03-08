<?php
// free.php - صفحة عرض المحتوى المجاني من يوتيوب (أفلام ومسلسلات وفيديوهات)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// جلب المحتوى من قاعدة البيانات
$movies = [];
$series = [];
$videos = [];
$featured_items = [];
$search_results = [];
$search_query = '';

try {
    // معالجة البحث
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $search_query = trim($_GET['search']);
        $search_type = $_GET['search_type'] ?? 'all';
        
        // البحث في الأفلام
        if ($search_type == 'all' || $search_type == 'movies') {
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    title, 
                    description,
                    thumbnail,
                    local_thumbnail,
                    channel_title,
                    duration,
                    view_count,
                    'movie' as content_type,
                    created_at,
                    category,
                    video_id
                FROM youtube_movies 
                WHERE status = 1 
                AND (title LIKE ? OR description LIKE ? OR channel_title LIKE ?)
                ORDER BY id DESC 
                LIMIT 30
            ");
            $search_term = "%$search_query%";
            $stmt->execute([$search_term, $search_term, $search_term]);
            $movie_results = $stmt->fetchAll();
            $search_results = array_merge($search_results, $movie_results);
        }
        
        // البحث في المسلسلات
        if ($search_type == 'all' || $search_type == 'series') {
            $stmt = $pdo->prepare("
                SELECT 
                    s.id, 
                    s.title, 
                    s.description,
                    s.thumbnail,
                    s.local_thumbnail,
                    s.channel_title,
                    s.video_count as episodes_count,
                    (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as total_episodes,
                    'series' as content_type,
                    s.created_at,
                    s.category
                FROM youtube_series s 
                WHERE s.status = 1 
                AND (s.title LIKE ? OR s.description LIKE ? OR s.channel_title LIKE ?)
                ORDER BY s.id DESC 
                LIMIT 30
            ");
            $search_term = "%$search_query%";
            $stmt->execute([$search_term, $search_term, $search_term]);
            $series_results = $stmt->fetchAll();
            $search_results = array_merge($search_results, $series_results);
        }
        
        // البحث في الفيديوهات الفردية
        if ($search_type == 'all' || $search_type == 'videos') {
            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    title, 
                    description,
                    thumbnail,
                    local_thumbnail,
                    channel_title,
                    duration,
                    view_count,
                    'video' as content_type,
                    created_at,
                    category,
                    video_id
                FROM youtube_content 
                WHERE status = 1 
                AND (title LIKE ? OR description LIKE ? OR channel_title LIKE ?)
                ORDER BY id DESC 
                LIMIT 30
            ");
            $search_term = "%$search_query%";
            $stmt->execute([$search_term, $search_term, $search_term]);
            $video_results = $stmt->fetchAll();
            $search_results = array_merge($search_results, $video_results);
        }
        
        // ترتيب نتائج البحث حسب التاريخ
        usort($search_results, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
    }
    
    // جلب الأفلام للعرض العادي
    $movies = $pdo->query("
        SELECT 
            id, 
            title, 
            description,
            thumbnail,
            local_thumbnail,
            channel_title,
            duration,
            view_count,
            'movie' as content_type,
            created_at,
            category,
            video_id
        FROM youtube_movies 
        WHERE status = 1 
        ORDER BY id DESC 
        LIMIT 50
    ")->fetchAll();
    
    // جلب المسلسلات للعرض العادي
    $series = $pdo->query("
        SELECT 
            s.id, 
            s.title, 
            s.description,
            s.thumbnail,
            s.local_thumbnail,
            s.channel_title,
            s.video_count as episodes_count,
            (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as total_episodes,
            'series' as content_type,
            s.created_at,
            s.category
        FROM youtube_series s 
        WHERE s.status = 1 
        ORDER BY s.id DESC 
        LIMIT 50
    ")->fetchAll();
    
    // جلب الفيديوهات الفردية للعرض العادي
    $videos = $pdo->query("
        SELECT 
            id, 
            title, 
            description,
            thumbnail,
            local_thumbnail,
            channel_title,
            duration,
            view_count,
            'video' as content_type,
            created_at,
            category,
            video_id
        FROM youtube_content 
        WHERE status = 1 
        ORDER BY id DESC 
        LIMIT 50
    ")->fetchAll();
    
    // مزج كل المحتوى للعرض المميز
    $all_content = array_merge($movies, $series, $videos);
    usort($all_content, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $featured_items = array_slice($all_content, 0, 60);
    
    // إحصائيات
    $stats = [
        'movies' => $pdo->query("SELECT COUNT(*) FROM youtube_movies WHERE status = 1")->fetchColumn() ?: 0,
        'series' => $pdo->query("SELECT COUNT(*) FROM youtube_series WHERE status = 1")->fetchColumn() ?: 0,
        'videos' => $pdo->query("SELECT COUNT(*) FROM youtube_content WHERE status = 1")->fetchColumn() ?: 0,
        'episodes' => $pdo->query("SELECT SUM(video_count) FROM youtube_series WHERE status = 1")->fetchColumn() ?: 0,
        'total_views' => $pdo->query("SELECT SUM(CAST(REPLACE(view_count, ',', '') AS UNSIGNED)) FROM youtube_movies WHERE status = 1")->fetchColumn() ?: 0
    ];
    $stats['total_views'] += $pdo->query("SELECT SUM(CAST(REPLACE(view_count, ',', '') AS UNSIGNED)) FROM youtube_content WHERE status = 1")->fetchColumn() ?: 0;
    
} catch (Exception $e) {
    // إذا حدث خطأ، نتعامل معه بهدوء
    $movies = [];
    $series = [];
    $videos = [];
    $featured_items = [];
    $search_results = [];
    $stats = ['movies' => 0, 'series' => 0, 'videos' => 0, 'episodes' => 0, 'total_views' => 0];
}

// معالجة نوع العرض
$view_type = $_GET['view'] ?? 'featured';
$content_type = $_GET['type'] ?? 'all';
$is_searching = isset($_GET['search']) && !empty($_GET['search']);

// دالة مساعدة لعرض الصورة
function getImageUrl($item) {
    if (!empty($item['local_thumbnail'])) {
        return $item['local_thumbnail'];
    } elseif (!empty($item['thumbnail'])) {
        return $item['thumbnail'];
    }
    return 'https://via.placeholder.com/300x169?text=No+Image';
}

// دالة لتنسيق الأرقام
function formatNumber($num) {
    if ($num >= 1000000) {
        return round($num / 1000000, 1) . 'M';
    } elseif ($num >= 1000) {
        return round($num / 1000, 1) . 'K';
    }
    return $num;
}

// دالة للتحقق من صلاحية الفيديو
function isVideoAvailable($video_id) {
    if (empty($video_id)) return false;
    
    $url = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$video_id}&format=json";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أفلام ومسلسلات مجانية - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* نفس التنسيقات السابقة مع إضافة تنسيقات للفيديو غير المتاح */
        .video-unavailable {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
            z-index: 5;
        }
        
        .video-unavailable i {
            font-size: 40px;
            color: #e50914;
            margin-bottom: 10px;
        }
        
        .video-unavailable span {
            font-size: 14px;
            text-align: center;
            padding: 0 10px;
        }
        
        /* باقي التنسيقات كما هي من الكود السابق */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0a0a0f 0%, #1a1a1f 100%);
            color: #fff;
            min-height: 100vh;
        }

        .header {
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(15px);
            padding: 15px 60px;
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

        .logo-icon {
            font-size: 32px;
            color: #e50914;
        }

        .logo h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(45deg, #fff, #e50914);
            
            -webkit-text-fill-color: transparent;
        }

        .nav-list {
            display: flex;
            gap: 40px;
            list-style: none;
        }

        .nav-list a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            padding: 8px 0;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }

        .nav-list a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: #e50914;
            transition: width 0.3s;
        }

        .nav-list a:hover::after,
        .nav-list a.active::after {
            width: 100%;
        }

        .nav-list a:hover,
        .nav-list a.active {
            color: #e50914;
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }

        .search-section {
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 30px;
            padding: 35px;
            margin-bottom: 50px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .search-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #e50914;
        }

        .search-box {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 18px 25px;
            background: rgba(0,0,0,0.3);
            border: 2px solid #333;
            border-radius: 60px;
            color: #fff;
            font-size: 16px;
        }

        .search-input:focus {
            border-color: #e50914;
            outline: none;
        }

        .search-btn {
            background: linear-gradient(45deg, #e50914, #ff4d4d);
            color: white;
            border: none;
            border-radius: 60px;
            padding: 18px 45px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-filters {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
            padding: 15px 20px;
            background: rgba(0,0,0,0.2);
            border-radius: 50px;
        }

        .search-filter {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #b3b3b3;
            cursor: pointer;
        }

        .search-filter input[type="radio"] {
            accent-color: #e50914;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
        }

        .stat-card {
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 30px;
            padding: 30px 25px;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            background: rgba(229,9,20,0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #e50914;
        }

        .stat-details {
            flex: 1;
        }

        .stat-value {
            font-size: 42px;
            font-weight: 800;
            background: linear-gradient(135deg, #fff, #e50914);
            
            -webkit-text-fill-color: transparent;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #b3b3b3;
            font-size: 16px;
        }

        .filter-section {
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 30px;
            padding: 25px;
            margin-bottom: 40px;
        }

        .view-tabs {
            display: flex;
            gap: 10px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
            padding-bottom: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .view-tab {
            padding: 12px 30px;
            border-radius: 40px;
            background: rgba(255,255,255,0.05);
            color: #b3b3b3;
            text-decoration: none;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .view-tab:hover {
            background: rgba(229,9,20,0.1);
            color: #e50914;
        }

        .view-tab.active {
            background: #e50914;
            color: white;
        }

        .filter-tabs {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 25px;
            border-radius: 40px;
            background: rgba(255,255,255,0.05);
            color: #b3b3b3;
            text-decoration: none;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab:hover {
            background: rgba(229,9,20,0.1);
            color: #e50914;
        }

        .filter-tab.active {
            background: #e50914;
            color: white;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(229,9,20,0.3);
            padding-bottom: 20px;
        }

        .section-title {
            font-size: 32px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 15px;
            color: #fff;
        }

        .section-title i {
            color: #e50914;
        }

        .section-count {
            background: linear-gradient(135deg, #e50914, #ff4d4d);
            padding: 10px 25px;
            border-radius: 40px;
            color: white;
            font-weight: 700;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 30px;
        }

        .content-card {
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 20px;
            overflow: hidden;
            transition: 0.4s;
            text-decoration: none;
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
            position: relative;
        }

        .content-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 20px 40px rgba(229,9,20,0.3);
        }

        .card-poster {
            position: relative;
            aspect-ratio: 16/9;
            overflow: hidden;
        }

        .card-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .badge-source {
            position: absolute;
            top: 15px;
            left: 15px;
            background: rgba(255,0,0,0.9);
            color: white;
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 2;
        }

        .badge-type {
            position: absolute;
            top: 15px;
            right: 15px;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            z-index: 2;
        }

        .badge-movie {
            background: linear-gradient(135deg, #e50914, #b20710);
            color: white;
        }

        .badge-series {
            background: linear-gradient(135deg, #1a4b8c, #0d2b4f);
            color: white;
        }

        .badge-video {
            background: linear-gradient(135deg, #27ae60, #1e8449);
            color: white;
        }

        .badge-info {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 2;
        }

        .badge-unavailable {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(229,9,20,0.9);
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 700;
            z-index: 3;
            white-space: nowrap;
        }

        .card-info {
            padding: 20px;
        }

        .card-title {
            font-weight: 700;
            font-size: 16px;
            margin-bottom: 12px;
            display: -webkit-box;
           
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 48px;
        }

        .card-meta {
            display: flex;
            gap: 15px;
            color: #b3b3b3;
            font-size: 13px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 4px 12px;
            border-radius: 20px;
        }

        .card-channel {
            color: #b3b3b3;
            font-size: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .card-channel i {
            color: #e50914;
        }

        .free-badge {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
            padding: 6px 18px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .search-results-info {
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-results-count {
            font-size: 20px;
            color: #e50914;
            font-weight: 600;
        }

        .search-results-count span {
            background: #e50914;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            margin-right: 10px;
        }

        .clear-search {
            background: rgba(255,255,255,0.05);
            color: #b3b3b3;
            text-decoration: none;
            padding: 10px 25px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .clear-search:hover {
            background: #e50914;
            color: white;
        }

        .no-content {
            text-align: center;
            padding: 80px 20px;
            background: linear-gradient(145deg, rgba(30,30,40,0.9), rgba(20,20,30,0.95));
            border-radius: 40px;
        }

        .no-content i {
            font-size: 100px;
            margin-bottom: 30px;
            color: #e50914;
        }

        .no-content h3 {
            font-size: 28px;
            margin-bottom: 15px;
        }

        .no-content p {
            color: #b3b3b3;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-list {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .container {
                padding: 0 20px;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-btn {
                width: 100%;
            }
            
            .content-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            }
            
            .section-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <i class="fas fa-film logo-icon"></i>
            <h1>ويزي<span>برو</span></h1>

        </div>
        <nav>
            <ul class="nav-list">
                <li><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="movies.php"><i class="fas fa-film"></i> أفلام</a></li>
                <li><a href="series.php"><i class="fas fa-tv"></i> مسلسلات</a></li>
                <li><a href="free.php" class="active"><i class="fas fa-gift"></i> مجاني</a></li>
                <li><a href="live.php"><i class="fas fa-broadcast-tower"></i> بث مباشر</a></li>
                <li><a href="anime-series.php"><i class="fas fa-dragon"></i> أنمي</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <!-- قسم البحث المتطور -->
        <div class="search-section">
            <div class="search-title">
                <i class="fas fa-search"></i>
                ابحث في المحتوى المجاني
            </div>
            
            <form method="GET" action="">
                <div class="search-box">
                    <input type="text" name="search" class="search-input" 
                           placeholder="ابحث عن فيلم، مسلسل، أو فيديو..." 
                           value="<?php echo htmlspecialchars($search_query); ?>">
                    
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </div>
                
                <div class="search-filters">
                    <label class="search-filter">
                        <input type="radio" name="search_type" value="all" <?php echo (!isset($_GET['search_type']) || $_GET['search_type'] == 'all') ? 'checked' : ''; ?>>
                        <i class="fas fa-globe"></i> الكل
                    </label>
                    <label class="search-filter">
                        <input type="radio" name="search_type" value="movies" <?php echo (isset($_GET['search_type']) && $_GET['search_type'] == 'movies') ? 'checked' : ''; ?>>
                        <i class="fas fa-film"></i> أفلام
                    </label>
                    <label class="search-filter">
                        <input type="radio" name="search_type" value="series" <?php echo (isset($_GET['search_type']) && $_GET['search_type'] == 'series') ? 'checked' : ''; ?>>
                        <i class="fas fa-list"></i> مسلسلات
                    </label>
                   
                </div>
                
                <input type="hidden" name="view" value="<?php echo $view_type; ?>">
                <input type="hidden" name="type" value="<?php echo $content_type; ?>">
            </form>
        </div>

        <?php if ($is_searching): ?>
            <!-- نتائج البحث -->
            <div class="search-results-info">
                <div class="search-results-count">
                    <i class="fas fa-search"></i> نتائج "<?php echo htmlspecialchars($search_query); ?>"
                    <span><?php echo count($search_results); ?></span>
                </div>
                <a href="free.php" class="clear-search">
                    <i class="fas fa-times"></i> مسح البحث
                </a>
            </div>

            <?php if (!empty($search_results)): ?>
            <div class="content-grid">
                <?php foreach ($search_results as $item): ?>
                    <?php
                    // تحديد الرابط حسب نوع المحتوى
                    if ($item['content_type'] == 'series') {
                        $link = 'youtube-series.php?id=' . $item['id'];
                        $target = '';
                    } else {
                        $video_id = $item['video_id'] ?? $item['id'];
                        $link = 'https://www.youtube.com/watch?v=' . $video_id;
                        $target = 'target="_blank"';
                    }
                    ?>
                    <a href="<?php echo $link; ?>" <?php echo $target; ?> class="content-card">
                        <div class="card-poster">
                            <img src="<?php echo getImageUrl($item); ?>" 
                                 class="card-image" 
                                 alt="<?php echo htmlspecialchars($item['title']); ?>"
                                 onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                            
                            <div class="badge-source">
                                <i class="fab fa-youtube"></i> YouTube
                            </div>
                            
                            <div class="badge-type <?php 
                                echo $item['content_type'] == 'movie' ? 'badge-movie' : 
                                    ($item['content_type'] == 'series' ? 'badge-series' : 'badge-video'); 
                            ?>">
                                <?php 
                                echo $item['content_type'] == 'movie' ? '🎬 فيلم' : 
                                    ($item['content_type'] == 'series' ? '📺 مسلسل' : '▶️ فيديو'); 
                                ?>
                            </div>
                            
                            <div class="badge-info">
                                <?php if ($item['content_type'] == 'series'): ?>
                                    <i class="fas fa-film"></i> <?php echo $item['total_episodes']; ?> حلقة
                                <?php else: ?>
                                    <i class="far fa-clock"></i> <?php echo $item['duration']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-info">
                            <div class="card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            
                            <div class="card-meta">
                                <?php if ($item['content_type'] != 'series'): ?>
                                <span class="meta-item">
                                    <i class="far fa-eye"></i> <?php echo formatNumber((int)str_replace(',', '', $item['view_count'] ?? '0')); ?>
                                </span>
                                <?php endif; ?>
                                <span class="meta-item">
                                    <i class="fas fa-tag"></i> <?php echo $item['category'] ?? 'عام'; ?>
                                </span>
                            </div>
                            
                            <div class="card-channel">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['channel_title']); ?>
                            </div>
                            
                            <div>
                                <span class="free-badge">
                                    <i class="fas fa-gift"></i> مجاناً
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-content">
                <i class="fas fa-search"></i>
                <h3>لا توجد نتائج للبحث</h3>
                <p>لم نجد أي محتوى مطابق لـ "<?php echo htmlspecialchars($search_query); ?>"</p>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- الإحصائيات -->
            
            
                

            <!-- أقسام الفلترة -->
            <div class="filter-section">
                <div class="view-tabs">
                    <a href="?view=featured" class="view-tab <?php echo ($view_type == 'featured') ? 'active' : ''; ?>">
                        <i class="fas fa-star"></i> المميزة
                    </a>
                    <a href="?view=movies" class="view-tab <?php echo ($view_type == 'movies') ? 'active' : ''; ?>">
                        <i class="fas fa-film"></i> أفلام
                    </a>
                    <a href="?view=series" class="view-tab <?php echo ($view_type == 'series') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> مسلسلات
                    </a>
                    
                </div>

                
            </div>

            <!-- عرض المحتوى حسب الاختيار -->
            <?php if ($view_type == 'featured'): ?>
                <!-- المميزة (أحدث الإضافات) -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-star" style="color: gold;"></i> أحدث الإضافات
                    </h2>
                    <span class="section-count"><?php echo count($featured_items); ?> عنصر</span>
                </div>

                <?php if (!empty($featured_items)): ?>
                <div class="content-grid">
                    <?php foreach ($featured_items as $item): ?>
                        <?php
                        if ($item['content_type'] == 'series') {
                            $link = 'youtube-series.php?id=' . $item['id'];
                            $target = '';
                        } else {
                            $video_id = $item['video_id'] ?? $item['id'];
                            $link = 'https://www.youtube.com/watch?v=' . $video_id;
                            $target = 'target="_blank"';
                        }
                        ?>
                        <a href="<?php echo $link; ?>" <?php echo $target; ?> class="content-card">
                            <div class="card-poster">
                                <img src="<?php echo getImageUrl($item); ?>" 
                                     class="card-image" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                                
                                <div class="badge-source">
                                    <i class="fab fa-youtube"></i> YouTube
                                </div>
                                
                                <div class="badge-type <?php 
                                    echo $item['content_type'] == 'movie' ? 'badge-movie' : 
                                        ($item['content_type'] == 'series' ? 'badge-series' : 'badge-video'); 
                                ?>">
                                    <?php 
                                    echo $item['content_type'] == 'movie' ? '🎬 فيلم' : 
                                        ($item['content_type'] == 'series' ? '📺 مسلسل' : '▶️ فيديو'); 
                                    ?>
                                </div>
                                
                                <div class="badge-info">
                                    <?php if ($item['content_type'] == 'series'): ?>
                                        <i class="fas fa-film"></i> <?php echo $item['total_episodes']; ?> حلقة
                                    <?php else: ?>
                                        <i class="far fa-clock"></i> <?php echo $item['duration']; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                                
                                <div class="card-meta">
                                    <?php if ($item['content_type'] != 'series'): ?>
                                    <span class="meta-item">
                                        <i class="far fa-eye"></i> <?php echo formatNumber((int)str_replace(',', '', $item['view_count'] ?? '0')); ?>
                                    </span>
                                    <?php endif; ?>
                                    <span class="meta-item">
                                        <i class="fas fa-tag"></i> <?php echo $item['category'] ?? 'عام'; ?>
                                    </span>
                                </div>
                                
                                <div class="card-channel">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['channel_title']); ?>
                                </div>
                                
                                <div>
                                    <span class="free-badge">
                                        <i class="fas fa-gift"></i> مجاناً
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-content">
                    <i class="fab fa-youtube"></i>
                    <h3>لا يوجد محتوى مجاني حالياً</h3>
                    <p>سيتم إضافة محتوى قريباً</p>
                </div>
                <?php endif; ?>

            <?php elseif ($view_type == 'movies'): ?>
                <!-- عرض الأفلام -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-film"></i> أفلام مجانية
                    </h2>
                    <span class="section-count"><?php echo count($movies); ?> فيلم</span>
                </div>

                <?php if (!empty($movies)): ?>
                <div class="content-grid">
                    <?php foreach ($movies as $movie): ?>
                        <?php 
                        $video_id = $movie['video_id'] ?? $movie['id'];
                        ?>
                        <a href="https://www.youtube.com/watch?v=<?php echo $video_id; ?>" target="_blank" class="content-card">
                            <div class="card-poster">
                                <img src="<?php echo getImageUrl($movie); ?>" 
                                     class="card-image" 
                                     alt="<?php echo htmlspecialchars($movie['title']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                                
                                <div class="badge-source">
                                    <i class="fab fa-youtube"></i> YouTube
                                </div>
                                
                                <div class="badge-type badge-movie">
                                    🎬 فيلم
                                </div>
                                
                                <div class="badge-info">
                                    <i class="far fa-clock"></i> <?php echo $movie['duration']; ?>
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="card-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                
                                <div class="card-meta">
                                    <span class="meta-item">
                                        <i class="far fa-eye"></i> <?php echo formatNumber((int)str_replace(',', '', $movie['view_count'] ?? '0')); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-tag"></i> <?php echo $movie['category'] ?? 'عام'; ?>
                                    </span>
                                </div>
                                
                                <div class="card-channel">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($movie['channel_title']); ?>
                                </div>
                                
                                <div>
                                    <span class="free-badge">
                                        <i class="fas fa-gift"></i> مجاناً
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-film"></i>
                    <h3>لا توجد أفلام مجانية</h3>
                    <p>سيتم إضافة أفلام قريباً</p>
                </div>
                <?php endif; ?>

            <?php elseif ($view_type == 'series'): ?>
                <!-- عرض المسلسلات -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i> مسلسلات مجانية
                    </h2>
                    <span class="section-count"><?php echo count($series); ?> مسلسل</span>
                </div>

                <?php if (!empty($series)): ?>
                <div class="content-grid">
                    <?php foreach ($series as $serie): ?>
                    <a href="youtube-series.php?id=<?php echo $serie['id']; ?>" class="content-card">
                        <div class="card-poster">
                            <img src="<?php echo getImageUrl($serie); ?>" 
                                 class="card-image" 
                                 alt="<?php echo htmlspecialchars($serie['title']); ?>"
                                 onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                            
                            <div class="badge-source">
                                <i class="fab fa-youtube"></i> YouTube
                            </div>
                            
                            <div class="badge-type badge-series">
                                📺 مسلسل
                            </div>
                            
                            <div class="badge-info">
                                <i class="fas fa-film"></i> <?php echo $serie['total_episodes']; ?> حلقة
                            </div>
                        </div>
                        
                        <div class="card-info">
                            <div class="card-title"><?php echo htmlspecialchars($serie['title']); ?></div>
                            
                            <div class="card-meta">
                                <span class="meta-item">
                                    <i class="fas fa-film"></i> <?php echo $serie['episodes_count']; ?> حلقة
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-tag"></i> <?php echo $serie['category'] ?? 'عام'; ?>
                                </span>
                            </div>
                            
                            <div class="card-channel">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($serie['channel_title']); ?>
                            </div>
                            
                            <div>
                                <span class="free-badge">
                                    <i class="fas fa-gift"></i> مجاناً
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-list"></i>
                    <h3>لا توجد مسلسلات مجانية</h3>
                    <p>سيتم إضافة مسلسلات قريباً</p>
                </div>
                <?php endif; ?>

            <?php elseif ($view_type == 'videos'): ?>
                <!-- عرض الفيديوهات الفردية -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-video"></i> فيديوهات مجانية
                    </h2>
                    <span class="section-count"><?php echo count($videos); ?> فيديو</span>
                </div>

                <?php if (!empty($videos)): ?>
                <div class="content-grid">
                    <?php foreach ($videos as $video): ?>
                        <?php 
                        $video_id = $video['video_id'] ?? $video['id'];
                        ?>
                        <a href="https://www.youtube.com/watch?v=<?php echo $video_id; ?>" target="_blank" class="content-card">
                            <div class="card-poster">
                                <img src="<?php echo getImageUrl($video); ?>" 
                                     class="card-image" 
                                     alt="<?php echo htmlspecialchars($video['title']); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                                
                                <div class="badge-source">
                                    <i class="fab fa-youtube"></i> YouTube
                                </div>
                                
                                <div class="badge-type badge-video">
                                    ▶️ فيديو
                                </div>
                                
                                <div class="badge-info">
                                    <i class="far fa-clock"></i> <?php echo $video['duration']; ?>
                                </div>
                            </div>
                            
                            <div class="card-info">
                                <div class="card-title"><?php echo htmlspecialchars($video['title']); ?></div>
                                
                                <div class="card-meta">
                                    <span class="meta-item">
                                        <i class="far fa-eye"></i> <?php echo formatNumber((int)str_replace(',', '', $video['view_count'] ?? '0')); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-tag"></i> <?php echo $video['category'] ?? 'عام'; ?>
                                    </span>
                                </div>
                                
                                <div class="card-channel">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($video['channel_title']); ?>
                                </div>
                                
                                <div>
                                    <span class="free-badge">
                                        <i class="fas fa-gift"></i> مجاناً
                                    </span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="no-content">
                    <i class="fas fa-video"></i>
                    <h3>لا توجد فيديوهات مجانية</h3>
                    <p>سيتم إضافة فيديوهات قريباً</p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>