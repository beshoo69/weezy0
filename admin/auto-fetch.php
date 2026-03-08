<?php
// admin/auto-fetch.php - الجلب التلقائي للأفلام والمسلسلات الحديثة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/TMDBClient.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'auto-fetch-config.php';

$tmdb = new TMDBClient(TMDB_API_KEY);
$message = '';
$messageType = '';

// معالجة طلب الجلب
if (isset($_GET['fetch']) && isset($_GET['type'])) {
    $type = $_GET['type'];
    $category = $_GET['category'] ?? 'popular';
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = $fetch_settings[$type]['max_per_category'] ?? 20;
    
    $results = fetchContent($tmdb, $pdo, $type, $category, $page, $limit);
    $message = $results['message'];
    $messageType = $results['type'];
}

/**
 * جلب المحتوى وحفظه في قاعدة البيانات
 */
function fetchContent($tmdb, $pdo, $type, $category, $page, $limit) {
    $added = 0;
    $skipped = 0;
    $failed = 0;
    
    // تحديد الدالة المناسبة حسب النوع والتصنيف
    switch ($type) {
        case 'movies':
            switch ($category) {
                case 'popular':
                    $data = $tmdb->getPopularMovies($page);
                    break;
                case 'now_playing':
                    $data = $tmdb->getNowPlayingMovies($page);
                    break;
                case 'upcoming':
                    $data = $tmdb->getUpcomingMovies($page);
                    break;
                case 'top_rated':
                    $data = $tmdb->getTopRatedMovies($page);
                    break;
                default:
                    return ['message' => '❌ تصنيف غير معروف', 'type' => 'error'];
            }
            break;
            
        case 'series':
            switch ($category) {
                case 'popular':
                    $data = $tmdb->getPopularTVShows($page);
                    break;
                case 'airing_today':
                    $data = $tmdb->getTVAiringToday($page);
                    break;
                case 'on_the_air':
                    $data = $tmdb->getTVOnTheAir($page);
                    break;
                case 'top_rated':
                    $data = $tmdb->getTopRatedTVShows($page);
                    break;
                default:
                    return ['message' => '❌ تصنيف غير معروف', 'type' => 'error'];
            }
            break;
            
        default:
            return ['message' => '❌ نوع غير معروف', 'type' => 'error'];
    }
    
    if (!$data || empty($data['results'])) {
        return ['message' => '❌ لا توجد نتائج', 'type' => 'error'];
    }
    
    // معالجة النتائج
    $items = array_slice($data['results'], 0, $limit);
    
    foreach ($items as $item) {
        try {
            // التحقق من وجود المحتوى مسبقاً
            $check = $pdo->prepare("SELECT id FROM " . ($type == 'movies' ? 'movies' : 'series') . " WHERE tmdb_id = ?");
            $check->execute([$item['id']]);
            
            if ($check->fetch()) {
                $skipped++;
                continue;
            }
            
            // جلب تفاصيل إضافية
            if ($type == 'movies') {
                $details = $tmdb->getMovieDetails($item['id']);
                saveMovie($pdo, $tmdb, $details);
            } else {
                $details = $tmdb->getTVShowDetails($item['id']);
                saveTVShow($pdo, $tmdb, $details);
            }
            
            $added++;
            
        } catch (Exception $e) {
            $failed++;
        }
    }
    
    $total_pages = $data['total_pages'] ?? 1;
    $next_page = $page + 1;
    $has_next = $next_page <= $total_pages && ($page * $limit) < ($data['total_results'] ?? 0);
    
    $message = "✅ تمت العملية بنجاح:\n";
    $message .= "• تمت إضافة: $added عنصر\n";
    $message .= "• موجود مسبقاً: $skipped عنصر\n";
    $message .= "• فشل: $failed عنصر\n";
    
    if ($has_next) {
        $message .= "\n📌 توجد نتائج إضافية. اضغط على التالي لجلب المزيد.";
    }
    
    return [
        'message' => $message,
        'type' => 'success',
        'has_next' => $has_next,
        'next_page' => $next_page
    ];
}

/**
 * حفظ فيلم في قاعدة البيانات
 */
function saveMovie($pdo, $tmdb, $data) {
    $title = $data['title'] ?? '';
    $title_en = $data['original_title'] ?? '';
    $overview = $data['overview'] ?? '';
    $year = isset($data['release_date']) ? substr($data['release_date'], 0, 4) : date('Y');
    $poster = $tmdb->getImageUrl($data['poster_path'] ?? '');
    $backdrop = $tmdb->getImageUrl($data['backdrop_path'] ?? '', 'original');
    $imdb_rating = $data['vote_average'] ?? 0;
    $tmdb_id = $data['id'];
    
    // معالجة البلدان
    $countries = [];
    if (isset($data['production_countries'])) {
        foreach ($data['production_countries'] as $c) {
            $countries[] = $c['name'];
        }
    }
    $country = implode('، ', $countries);
    
    // معالجة اللغات
    $language = $data['original_language'] ?? 'en';
    
    // معالجة التصنيفات
    $genres = [];
    if (isset($data['genres'])) {
        foreach ($data['genres'] as $g) {
            $genres[] = $g['name'];
        }
    }
    $genre = implode('، ', $genres);
    
    // المدة
    $duration = $data['runtime'] ?? 0;
    
    $sql = "INSERT INTO movies (title, title_en, description, poster, backdrop, year, country, language, genre, duration, imdb_rating, tmdb_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $title_en, $overview, $poster, $backdrop, $year, $country, $language, $genre, $duration, $imdb_rating, $tmdb_id]);
    
    return $pdo->lastInsertId();
}

/**
 * حفظ مسلسل في قاعدة البيانات
 */
function saveTVShow($pdo, $tmdb, $data) {
    $title = $data['name'] ?? '';
    $title_en = $data['original_name'] ?? '';
    $overview = $data['overview'] ?? '';
    $year = isset($data['first_air_date']) ? substr($data['first_air_date'], 0, 4) : date('Y');
    $poster = $tmdb->getImageUrl($data['poster_path'] ?? '');
    $backdrop = $tmdb->getImageUrl($data['backdrop_path'] ?? '', 'original');
    $imdb_rating = $data['vote_average'] ?? 0;
    $tmdb_id = $data['id'];
    
    // معالجة البلدان
    $countries = [];
    if (isset($data['production_countries'])) {
        foreach ($data['production_countries'] as $c) {
            $countries[] = $c['name'];
        }
    }
    $country = implode('، ', $countries);
    
    // معالجة اللغات
    $language = $data['original_language'] ?? 'en';
    
    // معالجة التصنيفات
    $genres = [];
    if (isset($data['genres'])) {
        foreach ($data['genres'] as $g) {
            $genres[] = $g['name'];
        }
    }
    $genre = implode('، ', $genres);
    
    $sql = "INSERT INTO series (title, title_en, description, poster, backdrop, year, country, language, genre, imdb_rating, tmdb_id, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$title, $title_en, $overview, $poster, $backdrop, $year, $country, $language, $genre, $imdb_rating, $tmdb_id]);
    
    return $pdo->lastInsertId();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الجلب التلقائي - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
            color: #fff;
            min-height: 100vh;
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
            max-width: 1200px;
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
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            white-space: pre-line;
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
        
        .section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .section-title {
            color: #e50914;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .category-card {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            border: 1px solid #333;
            transition: 0.3s;
        }
        
        .category-card:hover {
            border-color: #e50914;
            transform: translateY(-3px);
        }
        
        .category-name {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #e50914;
        }
        
        .category-desc {
            color: #b3b3b3;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .fetch-btn {
            display: inline-block;
            padding: 8px 20px;
            background: #e50914;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 14px;
            transition: 0.3s;
        }
        
        .fetch-btn:hover {
            background: #b20710;
            transform: scale(1.05);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .stat-item {
            background: #252525;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 800;
            color: #e50914;
        }
        
        .stat-label {
            color: #b3b3b3;
            font-size: 13px;
        }
        
        .pagination {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .page-btn {
            padding: 10px 20px;
            background: #252525;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            border: 1px solid #333;
        }
        
        .page-btn:hover {
            border-color: #e50914;
        }
        
        .info-box {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            border-right: 3px solid #e50914;
        }
        
        .info-box i {
            color: #e50914;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .category-grid {
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
            <i class="fas fa-sync-alt"></i>
            الجلب التلقائي للأفلام والمسلسلات الحديثة
        </h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo nl2br($message); ?>
            
            <?php if (isset($results['has_next']) && $results['has_next']): ?>
            <div class="pagination">
                <a href="?fetch=1&type=<?php echo $_GET['type']; ?>&category=<?php echo $_GET['category']; ?>&page=<?php echo $results['next_page']; ?>" class="page-btn">
                    <i class="fas fa-arrow-left"></i> التالي
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- إحصائيات سريعة -->
        <div class="section">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                إحصائيات المحتوى
            </div>
            
            <?php
            $total_movies = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
            $total_series = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
            $recent_movies = $pdo->query("SELECT COUNT(*) FROM movies WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            $recent_series = $pdo->query("SELECT COUNT(*) FROM series WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
            ?>
            
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_movies; ?></div>
                    <div class="stat-label">إجمالي الأفلام</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_series; ?></div>
                    <div class="stat-label">إجمالي المسلسلات</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $recent_movies; ?></div>
                    <div class="stat-label">أفلام جديدة (7 أيام)</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $recent_series; ?></div>
                    <div class="stat-label">مسلسلات جديدة (7 أيام)</div>
                </div>
            </div>
        </div>
        
        <!-- قسم الأفلام -->
        <?php if ($fetch_settings['movies']['enabled']): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-film"></i>
                أفلام حديثة
            </div>
            
            <div class="category-grid">
                <?php foreach ($fetch_settings['movies']['categories'] as $key => $name): ?>
                <div class="category-card">
                    <div class="category-name"><?php echo $name; ?></div>
                    <div class="category-desc">جلب أحدث <?php echo $name; ?></div>
                    <a href="?fetch=1&type=movies&category=<?php echo $key; ?>" class="fetch-btn">
                        <i class="fas fa-download"></i> جلب الآن
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- قسم المسلسلات -->
        <?php if ($fetch_settings['series']['enabled']): ?>
        <div class="section">
            <div class="section-title">
                <i class="fas fa-tv"></i>
                مسلسلات حديثة
            </div>
            
            <div class="category-grid">
                <?php foreach ($fetch_settings['series']['categories'] as $key => $name): ?>
                <div class="category-card">
                    <div class="category-name"><?php echo $name; ?></div>
                    <div class="category-desc">جلب أحدث <?php echo $name; ?></div>
                    <a href="?fetch=1&type=series&category=<?php echo $key; ?>" class="fetch-btn">
                        <i class="fas fa-download"></i> جلب الآن
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- معلومات إضافية -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <strong>معلومات مهمة:</strong>
            <ul style="margin-top: 10px; padding-right: 20px; color: #b3b3b3;">
                <li>سيتم جلب المحتوى من TMDB API بشكل آلي</li>
                <li>المحتوى الجديد يضاف كـ "مسودة" للمراجعة قبل النشر</li>
                <li>يمكنك التحكم في الإعدادات من ملف auto-fetch-config.php</li>
                <li>الحد الأقصى لكل فئة: <?php echo $fetch_settings['movies']['max_per_category']; ?> عنصر</li>
            </ul>
        </div>
    </div>
</body>
</html>