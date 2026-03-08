<?php
// admin/movies.php - صفحة عرض جميع الأفلام للمستخدمين
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// تعريف مفتاح TMDB API
define('TMDB_API_KEY', '5dc3e335b09cbf701d8685dd9a766949');

// إعدادات الصفحة
$items_per_page = min(max(isset($_GET['per_page']) ? (int)$_GET['per_page'] : 24, 1), 100);
$page = max(isset($_GET['page']) ? (int)$_GET['page'] : 1, 1);
$offset = ($page - 1) * $items_per_page;

// بناء شروط البحث والتصفية
$where_clauses = ['1=1']; // شرط افتراضي لتسهيل إضافة AND
$params = [];

// دالة مساعدة لإضافة شروط البحث
function addWhereCondition(&$clauses, &$params, $field, $operator, $value, $isLike = false) {
    if (!empty($value) || $value === '0') {
        $clauses[] = $isLike ? "$field LIKE ?" : "$field $operator ?";
        $params[] = $isLike ? "%$value%" : $value;
    }
}

// معالجة فلاتر البحث
$filters = [
    'genre' => ['genres', 'LIKE', true],
    'year' => ['year', '=', false],
    'language' => ['language', '=', false],
    'country' => ['country', 'LIKE', true],
    'membership_level' => ['membership_level', '=', false]
];

foreach ($filters as $key => [$field, $operator, $isLike]) {
    if (isset($_GET[$key]) && $_GET[$key] !== '') {
        addWhereCondition($where_clauses, $params, $field, $operator, $_GET[$key], $isLike);
    }
}

// لا نفرض شرط الحالة هنا حتى نعرض جميع الأفلام الموجودة في القاعدة
// (كان يُستخدم لعرض المنشور فقط للمستخدم العادي)
// $where_clauses[] = "status = 'published'";

// فلاتر النطاق (range filters)
$rangeFilters = [
    'rating_min' => ['imdb_rating', '>='],
    'rating_max' => ['imdb_rating', '<='],
    'duration_min' => ['duration', '>='],
    'duration_max' => ['duration', '<=']
];

foreach ($rangeFilters as $key => [$field, $operator]) {
    if (isset($_GET[$key]) && is_numeric($_GET[$key]) && $_GET[$key] !== '') {
        $where_clauses[] = "$field $operator ?";
        $params[] = (float)$_GET[$key];
    }
}

// البحث المتقدم
if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
    $search = trim($_GET['search']);
    $searchFields = ['title', 'title_en', 'description', 'country', 'genres'];
    $searchConditions = [];
    
    foreach ($searchFields as $field) {
        $searchConditions[] = "$field LIKE ?";
        $params[] = "%$search%";
    }
    
    $where_clauses[] = '(' . implode(' OR ', $searchConditions) . ')';
}

// تحويل شروط WHERE إلى نص SQL
$where_sql = implode(' AND ', $where_clauses);

// جلب إجمالي عدد الأفلام
$total_sql = "SELECT COUNT(*) FROM movies WHERE $where_sql";
$stmt = $pdo->prepare($total_sql);
$stmt->execute($params);
$total_movies = $stmt->fetchColumn();
$total_pages = ceil($total_movies / $items_per_page);

// جلب الأفلام للصفحة الحالية
$sql = "SELECT * FROM movies WHERE $where_sql ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);

// ربط المعاملات
$paramIndex = 1;
foreach ($params as $param) {
    $stmt->bindValue($paramIndex++, $param);
}
$stmt->bindValue($paramIndex++, $items_per_page, PDO::PARAM_INT);
$stmt->bindValue($paramIndex++, $offset, PDO::PARAM_INT);

$stmt->execute();
$movies = $stmt->fetchAll();

// دوال مساعدة لجلب البيانات مع التخزين المؤقت
function getCachedData($pdo, $cacheKey, $query, $cacheTime = 3600) {
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . $cacheKey . '.php';
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return include $cacheFile;
    }
    
    try {
        $stmt = $pdo->query($query);
        $data = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($data)) {
            file_put_contents($cacheFile, '<?php return ' . var_export($data, true) . ';');
        }
        
        return $data;
    } catch (Exception $e) {
        return [];
    }
}

// جلب البيانات مع التخزين المؤقت
$genres = getCachedData($pdo, 'genres_cache', 
    "SELECT DISTINCT TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(genres, ',', n), ',', -1)) as genre
     FROM movies 
     CROSS JOIN (SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) numbers
     WHERE genres IS NOT NULL AND genres != ''
     ORDER BY genre");

$years = getCachedData($pdo, 'years_cache', 
    "SELECT DISTINCT year FROM movies WHERE year IS NOT NULL AND year > 1900 ORDER BY year DESC");

$countries = getCachedData($pdo, 'countries_cache', 
    "SELECT DISTINCT country FROM movies WHERE country IS NOT NULL AND country != '' ORDER BY country");

$languages = getCachedData($pdo, 'languages_cache', 
    "SELECT DISTINCT language FROM movies WHERE language IS NOT NULL AND language != '' ORDER BY language");

$membership_levels = getCachedData($pdo, 'membership_cache', 
    "SELECT DISTINCT membership_level FROM movies WHERE membership_level IS NOT NULL AND membership_level != '' ORDER BY membership_level");

// ترجمة اللغات
$lang_names = [
    'ar' => '🇸🇦 العربية',
    'en' => '🇬🇧 English',
    'tr' => '🇹🇷 Türkçe',
    'hi' => '🇮🇳 हिन्दी',
    'ko' => '🇰🇷 한국어',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch',
    'es' => '🇪🇸 Español',
    'ja' => '🇯🇵 日本語',
    'zh' => '🇨🇳 中文',
    'th' => '🇹🇭 ไทย',
    'ur' => '🇵🇰 اردو',
    'fa' => '🇮🇷 فارسی',
    'ku' => '🏳️ كوردي'
];

// ترجمة مستويات العضوية
$level_names = [
    'basic' => 'عادي',
    'premium' => 'مميز',
    'vip' => 'VIP'
];

// دالة للحصول على مسار الصورة الصحيح مع دعم TMDB
function getPosterUrl($poster_path, $tmdb_id = null) {
    $chosen = null;
    // إذا كان هناك TMDB ID نستخدم الصورة منه أولاً
    if (!empty($tmdb_id)) {
        $tmdb_poster = getTMDBPoster($tmdb_id);
        if ($tmdb_poster) {
            $chosen = $tmdb_poster;
        }
    }
    
    // إذا لم يعطنا TMDB صورة أو لم يكن هناك ID، حاول الصورة المحلية
    if (!$chosen && !empty($poster_path)) {
        // تنظيف المسار
        $poster_path = ltrim($poster_path, '/');
        
        // التحقق من وجود الملف
        $full_path = $_SERVER['DOCUMENT_ROOT'] . '/fayez-movie/' . $poster_path;
        
        if (file_exists($full_path)) {
            $chosen = 'http://localhost/fayez-movie/' . $poster_path;
        }
        
        // إذا كان المسار يبدأ بـ images/ بالفعل
        if (!$chosen && strpos($poster_path, 'images/') === 0) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/fayez-movie/' . $poster_path;
            if (file_exists($full_path)) {
                $chosen = 'http://localhost/fayez-movie/' . $poster_path;
            }
        }
        
        // محاولة مع مسارات مختلفة
        if (!$chosen) {
            $possible_paths = [
                'uploads/posters/' . basename($poster_path),
                'images/posters/' . basename($poster_path),
                'posters/' . basename($poster_path),
                'assets/images/posters/' . basename($poster_path)
            ];
            
            foreach ($possible_paths as $path) {
                $full_path = $_SERVER['DOCUMENT_ROOT'] . '/fayez-movie/' . $path;
                if (file_exists($full_path)) {
                    $chosen = 'http://localhost/fayez-movie/' . $path;
                    break;
                }
            }
        }
    }
    
    if (!$chosen) {
        $chosen = 'https://via.placeholder.com/300x450?text=' . urlencode('لا يوجد ملصق');
    }
    error_log("getPosterUrl(): tmdb_id='{$tmdb_id}' local_path='$poster_path' -> chosen='$chosen'");
    return $chosen;
}

// دالة لجلب ملصق الفيلم من TMDB
function getTMDBPoster($tmdb_id) {
    if (empty($tmdb_id)) return null;
    
    $url = "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // إضافة logging للتحقق
    error_log("TMDB Poster Request: ID=$tmdb_id, HTTP_CODE=$http_code");
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['poster_path']) && !empty($data['poster_path'])) {
            $poster_url = "https://image.tmdb.org/t/p/w500" . $data['poster_path'];
            error_log("TMDB Poster Found: $poster_url");
            return $poster_url;
        }
    }
    
    error_log("TMDB Poster Not Found for ID: $tmdb_id");
    return null;
}

// دالة لعرض شارة الجودة
function getQualityBadge($movie) {
    if (!empty($movie['quality'])) {
        $quality = strtolower($movie['quality']);
        $colors = [
            '4k' => '#FFD700',
            '1080p' => '#4CAF50',
            '720p' => '#2196F3',
            '480p' => '#FF9800'
        ];
        $color = $colors[$quality] ?? '#9C27B0';
        return "<span style='background: $color; position: absolute; top: 10px; left: 10px; color: white; padding: 3px 8px; border-radius: 5px; font-size: 11px; font-weight: bold; z-index: 5;'>$quality</span>";
    }
    return '';
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>أفلام - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* جميع التنسيقات السابقة مع بعض التعديلات */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
        }
        
        .header {
            background: linear-gradient(to bottom, #000000, #0f0f0f);
            padding: 20px 0;
            border-bottom: 3px solid #e50914;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 4px 20px rgba(0,0,0,0.5);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }
        
        .nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 32px;
            font-weight: 800;
        }
        
        .logo span {
            color: #fff;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-links a:hover,
        .nav-links a.active {
            background: #e50914;
            transform: translateY(-2px);
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background: #252525;
            border-radius: 50px;
            padding: 5px;
            flex: 1;
            max-width: 400px;
        }
        
        .search-box input {
            background: transparent;
            border: none;
            color: white;
            padding: 10px 15px;
            width: 100%;
            outline: none;
            font-family: 'Tajawal', sans-serif;
        }
        
        .search-box input::placeholder {
            color: #666;
        }
        
        .search-box button {
            background: #e50914;
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-box button:hover {
            background: #b20710;
            transform: scale(1.05);
        }
        
        .filter-section {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 25px;
            margin: 30px 0;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .filter-title {
            color: #e50914;
            margin-bottom: 20px;
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .filter-group label {
            color: #b3b3b3;
            font-size: 13px;
            font-weight: 500;
        }
        
        .filter-select, .filter-input {
            background: #252525;
            color: white;
            border: 1px solid #333;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.3s;
            font-family: 'Tajawal', sans-serif;
            width: 100%;
        }
        
        .filter-select:hover, .filter-input:hover {
            border-color: #e50914;
        }
        
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #e50914;
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.2);
        }
        
        .range-inputs {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .range-inputs input {
            flex: 1;
            min-width: 0;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        
        .btn {
            background: #e50914;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 30px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
        }
        
        .btn:hover {
            background: #b20710;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }
        
        .btn-secondary {
            background: #252525;
        }
        
        .btn-secondary:hover {
            background: #333;
        }
        
        .stats-bar {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-info {
            color: #b3b3b3;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-info i {
            color: #e50914;
        }
        
        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }
        
        .movie-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid #333;
            cursor: pointer;
            position: relative;
        }
        
        .movie-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 15px 40px rgba(229, 9, 20, 0.4);
        }
        
        .poster-container {
            position: relative;
            width: 100%;
            aspect-ratio: 2/3;
            overflow: hidden;
            background: #000;
        }
        
        .movie-poster {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .movie-card:hover .movie-poster {
            transform: scale(1.05);
        }
        
        .membership-badge-on-poster {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            backdrop-filter: blur(5px);
        }
        
        .membership-badge-on-poster.premium {
            background: linear-gradient(135deg, #e50914, #ff4d4d);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .membership-badge-on-poster.vip {
            background: linear-gradient(135deg, gold, #ffd700);
            color: black;
            border: 1px solid rgba(255,255,255,0.5);
        }
        
        .streaming-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            z-index: 10;
            backdrop-filter: blur(5px);
            border: 1px solid #e50914;
        }
        
        .movie-info {
            padding: 15px;
        }
        
        .movie-title {
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .movie-meta {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            font-size: 13px;
        }
        
        .movie-rating {
            color: gold;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .movie-year {
            background: #252525;
            padding: 2px 8px;
            border-radius: 20px;
        }
        
        .movie-duration {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 50px 0;
            flex-wrap: wrap;
        }
        
        .page-btn {
            background: #1a1a1a;
            color: white;
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 10px;
            border: 1px solid #333;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-btn:hover,
        .page-btn.active {
            background: #e50914;
            border-color: #e50914;
            transform: translateY(-2px);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 100px 20px;
            background: #1a1a1a;
            border-radius: 20px;
            border: 2px dashed #333;
        }
        
        .empty-state i {
            font-size: 80px;
            color: #333;
            margin-bottom: 20px;
        }
        
        .empty-state h2 {
            color: #b3b3b3;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            color: #666;
            margin-bottom: 25px;
        }
        
        .footer {
            background: #0a0a0a;
            padding: 40px 0;
            margin-top: 50px;
            border-top: 1px solid #333;
            text-align: center;
            color: #b3b3b3;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 0 15px;
            }
            
            .nav {
                flex-direction: column;
                align-items: stretch;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .movies-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .stats-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* تحسين عرض الصور */
        .poster-container img {
            background: linear-gradient(45deg, #1a1a1a 25%, #2a2a2a 25%, #2a2a2a 50%, #1a1a1a 50%, #1a1a1a 75%, #2a2a2a 75%);
            background-size: 20px 20px;
        }

        /* تلميح عند فشل تحميل الصورة */
        .poster-container img[src*="placeholder"] {
            object-fit: contain;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="nav">
                <div class="logo">
                    <i class="fas fa-film" style="color: #e50914; font-size: 36px;"></i>
                    <h1>ويزي<span>برو</span></h1>
                </div>
                
                <div class="nav-links">
                    <a href="../index.php"><i class="fas fa-home"></i> الرئيسية</a>
                    <a href="movies.php" class="active"><i class="fas fa-film"></i> أفلام</a>
                    <a href="series.php"><i class="fas fa-tv"></i> مسلسلات</a>
                </div>
                
                <form method="GET" class="search-box">
                    <input type="text" name="search" placeholder="بحث في الأفلام..." 
                           value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button type="submit"><i class="fas fa-search"></i> بحث</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="container">
        <div class="filter-section">
            <div class="filter-title">
                <i class="fas fa-sliders-h"></i>
                تصفية الأفلام
            </div>
            
            <form method="GET" id="filterForm">
                <!-- الحفاظ على معامل البحث -->
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                
                <div class="filter-grid">
                    <div class="filter-group">
                        <label><i class="fas fa-tags"></i> التصنيف</label>
                        <select name="genre" class="filter-select">
                            <option value="">كل التصنيفات</option>
                            <?php foreach ($genres as $genre): ?>
                                <?php if (!empty($genre)): ?>
                                <option value="<?php echo htmlspecialchars($genre); ?>" 
                                    <?php echo (isset($_GET['genre']) && $_GET['genre'] == $genre) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($genre); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> السنة</label>
                        <select name="year" class="filter-select">
                            <option value="">كل السنوات</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" 
                                    <?php echo (isset($_GET['year']) && $_GET['year'] == $year) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-language"></i> اللغة</label>
                        <select name="language" class="filter-select">
                            <option value="">كل اللغات</option>
                            <?php foreach ($languages as $lang): ?>
                                <?php if (!empty($lang)): ?>
                                <option value="<?php echo htmlspecialchars($lang); ?>" 
                                    <?php echo (isset($_GET['language']) && $_GET['language'] == $lang) ? 'selected' : ''; ?>>
                                    <?php echo $lang_names[$lang] ?? htmlspecialchars($lang); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-globe"></i> البلد</label>
                        <select name="country" class="filter-select">
                            <option value="">كل البلدان</option>
                            <?php foreach ($countries as $country): ?>
                                <?php if (!empty($country)): ?>
                                <option value="<?php echo htmlspecialchars($country); ?>" 
                                    <?php echo (isset($_GET['country']) && $_GET['country'] == $country) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($country); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-crown"></i> مستوى العضوية</label>
                        <select name="membership_level" class="filter-select">
                            <option value="">كل المستويات</option>
                            <?php foreach ($membership_levels as $level): ?>
                                <?php if (!empty($level)): ?>
                                <option value="<?php echo htmlspecialchars($level); ?>" 
                                    <?php echo (isset($_GET['membership_level']) && $_GET['membership_level'] == $level) ? 'selected' : ''; ?>>
                                    <?php echo $level_names[$level] ?? htmlspecialchars($level); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-star"></i> التقييم (IMDb)</label>
                        <div class="range-inputs">
                            <input type="number" name="rating_min" class="filter-input" placeholder="من" 
                                   step="0.1" min="0" max="10" 
                                   value="<?php echo htmlspecialchars($_GET['rating_min'] ?? ''); ?>">
                            <input type="number" name="rating_max" class="filter-input" placeholder="إلى" 
                                   step="0.1" min="0" max="10" 
                                   value="<?php echo htmlspecialchars($_GET['rating_max'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-clock"></i> المدة (دقيقة)</label>
                        <div class="range-inputs">
                            <input type="number" name="duration_min" class="filter-input" placeholder="من" 
                                   min="0" 
                                   value="<?php echo htmlspecialchars($_GET['duration_min'] ?? ''); ?>">
                            <input type="number" name="duration_max" class="filter-input" placeholder="إلى" 
                                   min="0" 
                                   value="<?php echo htmlspecialchars($_GET['duration_max'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn">
                        <i class="fas fa-check"></i>
                        تطبيق الفلاتر
                    </button>
                    
                    <a href="movies.php" class="btn btn-secondary">
                        <i class="fas fa-undo"></i>
                        إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
        
        <div class="stats-bar">
            <div class="stats-info">
                <i class="fas fa-film"></i>
                <span>إجمالي الأفلام: <strong><?php echo number_format($total_movies); ?></strong></span>
                <?php if (!empty($_GET)): ?>
                <span style="margin-left:10px; color:#ccc;">
                    (حالة ضمن الأعمدة)
                </span>
                <?php endif; ?>
                <span style="color: #e50914; margin-right: 10px;">
                    (مصفاة)
                </span>
                
            </div>
        </div>
        
        <?php if (empty($movies)): ?>
        <div class="empty-state">
            <i class="fas fa-film"></i>
            <h2>لا توجد أفلام متاحة</h2>
            <p>لم يتم العثور على أفلام تطابق معايير البحث الخاصة بك.</p>
        </div>
        <?php else: ?>
        <div class="movies-grid">
            <?php foreach ($movies as $movie): ?>
            <div class="movie-card" onclick="location.href='../movie.php?id=<?php echo $movie['id']; ?>'">
                <?php if (!empty($movie['status'])): ?>
                
                <?php endif; ?>
                <?php if (!empty($movie['streaming_service'])): ?>
                <span class="streaming-badge">
                    <i class="fas fa-play"></i>
                    <?php echo htmlspecialchars($movie['streaming_service']); ?>
                </span>
                <?php endif; ?>
                
                <?php echo getQualityBadge($movie); ?>
                
                <div class="poster-container">
                    <?php 
                    $poster_url = getPosterUrl($movie['poster'] ?? '', $movie['tmdb_id'] ?? null);
                    ?>
                    <a href="<?php echo $poster_url; ?>" target="_blank" title="فتح الصورة في تاب جديد">
                        <img src="<?php echo $poster_url; ?>" 
                             class="movie-poster" 
                             alt="<?php echo htmlspecialchars($movie['title']); ?>"
                             loading="lazy"
                             onerror="this.src='https://via.placeholder.com/300x450?text=<?php echo urlencode('لا يوجد ملصق'); ?>'; this.onerror=null;">
                    </a>
                    <div style="font-size:10px; color:#999; word-break:break-all; margin-top:5px;">
                        URL: <?php echo $poster_url; ?>
                    </div>
                    
                    <?php 
                    if (function_exists('membershipBadgeOnPoster')) {
                        membershipBadgeOnPoster($movie); 
                    } else {
                        // عرض شارة العضوية بشكل يدوي إذا الدالة غير موجودة
                        if (!empty($movie['membership_level']) && $movie['membership_level'] !== 'basic'):
                            $level = $movie['membership_level'];
                            $class = $level === 'vip' ? 'vip' : 'premium';
                            $icon = $level === 'vip' ? 'fa-crown' : 'fa-star';
                            $text = $level === 'vip' ? 'VIP' : 'مميز';
                            echo "<span class='membership-badge-on-poster $class'><i class='fas $icon'></i> $text</span>";
                        endif;
                    }
                    ?>
                </div>
                
                <div class="movie-info">
                    <div class="movie-title" title="<?php echo htmlspecialchars($movie['title']); ?>">
                        <?php echo htmlspecialchars($movie['title']); ?>
                    </div>
                    <?php if (!empty($movie['tmdb_id'])): ?>
                    <div style="font-size: 11px; color: #666; margin-bottom: 5px;">
                        TMDB ID: <?php echo htmlspecialchars($movie['tmdb_id']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="movie-meta">
                        <span class="movie-year">
                            <i class="far fa-calendar-alt"></i>
                            <?php echo htmlspecialchars($movie['year'] ?? 'N/A'); ?>
                        </span>
                        <span class="movie-rating">
                            <i class="fas fa-star"></i>
                            <?php echo isset($movie['imdb_rating']) ? number_format($movie['imdb_rating'], 1) : '0.0'; ?>
                        </span>
                    </div>
                    <?php if (!empty($movie['duration'])): ?>
                    <div class="movie-duration">
                        <i class="far fa-clock"></i>
                        <?php echo htmlspecialchars($movie['duration']); ?> دقيقة
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
            <a href="?page=1&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn" title="الصفحة الأولى">
                <i class="fas fa-angle-double-right"></i>
            </a>
            <a href="?page=<?php echo $page-1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn">
                <i class="fas fa-chevron-right"></i>
                السابق
            </a>
            <?php endif; ?>
            
            <?php 
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            
            if ($start > 1) {
                echo '<span class="page-btn disabled">...</span>';
            }
            
            for ($i = $start; $i <= $end; $i++): 
            ?>
            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" 
               class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; 
            
            if ($end < $total_pages) {
                echo '<span class="page-btn disabled">...</span>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn">
                التالي
                <i class="fas fa-chevron-left"></i>
            </a>
            <a href="?page=<?php echo $total_pages; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>" class="page-btn" title="الصفحة الأخيرة">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <div class="footer">
        <div class="container">
            <p>جميع الحقوق محفوظة &copy; ويزي برو <?php echo date('Y'); ?></p>
            <p style="margin-top: 10px; font-size: 14px;">
                <i class="fas fa-film"></i> 
                <?php echo number_format($total_movies); ?> فيلم متاح للمشاهدة
            </p>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // تحسين تجربة البحث
            const filterForm = document.getElementById('filterForm');
            if (filterForm) {
                filterForm.addEventListener('submit', function() {
                    const btn = this.querySelector('button[type="submit"]');
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التطبيق...';
                    btn.disabled = true;
                });
            }
            
            // إضافة lazy loading للصور
            const images = document.querySelectorAll('img[loading="lazy"]');
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const image = entry.target;
                            image.src = image.dataset.src || image.src;
                            imageObserver.unobserve(image);
                        }
                    });
                });
                
                images.forEach(img => imageObserver.observe(img));
            }
        });
    </script>
</body>
</html>