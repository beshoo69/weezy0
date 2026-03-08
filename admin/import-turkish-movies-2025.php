<?php
// admin/import-turkish-movies-2025.php - استيراد الأفلام التركية 2025
ini_set('max_execution_time', 600);
ini_set('memory_limit', '512M');
set_time_limit(600);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$imported = 0;
$skipped = 0;
$errors = 0;

// =============================================
// استيراد الأفلام التركية 2025
// =============================================
if (isset($_POST['import_turkish_movies_2025'])) {
    $pages = (int)($_POST['pages'] ?? 5);
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد الأفلام التركية 2025...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: #9b2c2c; }
            h2 { color: #e50914; margin: 15px 0; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #9b2c2c; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .movie-info { background: #252525; padding: 8px; margin: 3px 0; border-radius: 4px; font-size: 14px; border-right: 3px solid #9b2c2c; }
            .year-badge { background: #9b2c2c; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
            .turkish-flag { color: #9b2c2c; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🇹🇷 استيراد الأفلام التركية 2025</h1>";
    
    ob_flush();
    flush();
    
    // تحديد تاريخ البداية والنهاية لعام 2025
    $start_date = '2025-01-01';
    $end_date = '2025-12-31';
    
    echo "<h2>📅 جلب أفلام تركية من 1 يناير 2025 إلى 31 ديسمبر 2025</h2>";
    ob_flush();
    flush();
    
    // جلب الأفلام التركية 2025 من TMDB
    for ($page = 1; $page <= $pages; $page++) {
        echo "<div class='progress'>⏳ جاري استيراد صفحة {$page} من {$pages}...</div>";
        ob_flush();
        flush();
        
        // رابط API للأفلام التركية مع تحديد السنة
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
             . "&language=ar-SA"
             . "&with_original_language=tr"
             . "&sort_by=release_date.desc"
             . "&primary_release_date.gte=" . $start_date
             . "&primary_release_date.lte=" . $end_date
             . "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if (!$data || !isset($data['results'])) {
            echo "<div class='progress warning'>⚠️ لا توجد بيانات في الصفحة {$page}</div>";
            continue;
        }
        
        if (empty($data['results'])) {
            echo "<div class='progress warning'>📭 لا توجد أفلام تركية 2025 في الصفحة {$page}</div>";
            continue;
        }
        
        foreach ($data['results'] as $movie) {
            if (!isset($movie['id'])) continue;
            
            // استخراج سنة الفيلم
            $release_date = $movie['release_date'] ?? '';
            $year = !empty($release_date) ? substr($release_date, 0, 4) : '2025';
            
            // تحقق من وجود الفيلم
            $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
            $check->execute([$movie['id']]);
            
            if (!$check->fetch()) {
                // تجهيز البيانات
                $title = $movie['title'] ?? 'بدون عنوان';
                $original_title = $movie['original_title'] ?? '';
                $description = $movie['overview'] ?? 'فيلم تركي 2025';
                $poster = isset($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
                $backdrop = isset($movie['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'] : null;
                $rating = $movie['vote_average'] ?? 0;
                
                try {
                    $sql = "INSERT INTO movies (
                        tmdb_id, title, title_en, description, poster, backdrop, 
                        year, imdb_rating, country, language, status, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'تركيا', 'tr', 'published', 0)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $movie['id'],
                        $title,
                        $original_title,
                        $description,
                        $poster,
                        $backdrop,
                        $year,
                        $rating
                    ]);
                    
                    $imported++;
                    
                    // عرض الفيلم المستورد
                    echo "<div class='movie-info'>✅ [{$year}] <span class='turkish-flag'>🇹🇷</span> {$title} (⭐ {$rating})</div>";
                    ob_flush();
                    flush();
                    
                } catch (Exception $e) {
                    $errors++;
                    error_log("خطأ في استيراد فيلم تركي 2025: " . $e->getMessage());
                }
            } else {
                $skipped++;
                $title = $movie['title'] ?? 'فيلم تركي';
                echo "<div class='movie-info warning'>⏭️ [{$year}] {$title} (موجود مسبقاً)</div>";
                ob_flush();
                flush();
            }
        }
        
        // تأخير بين الصفحات
        usleep(500000);
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل استيراد الأفلام التركية 2025!</h2>";
    echo "<p>📊 أفلام تركية 2025 جديدة: <strong style='color: #9b2c2c;'>{$imported}</strong></p>";
    echo "<p>⏭️ أفلام موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e50914;'>{$errors}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-turkish-movies-2025.php' style='display: inline-block; padding: 10px 20px; background: #9b2c2c; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../turkish-movies.php?year=2025' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🇹🇷 عرض أفلام 2025</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات الأفلام التركية 2025
$turkish_movies_2025 = $pdo->query("SELECT COUNT(*) FROM movies WHERE country = 'تركيا' AND year = '2025'")->fetchColumn();
$turkish_movies_total = $pdo->query("SELECT COUNT(*) FROM movies WHERE country = 'تركيا'")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد الأفلام التركية 2025 - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            display: flex;
        }
        
        .sidebar {
            width: 280px;
            background: #0a0a0a;
            height: 100vh;
            position: fixed;
            right: 0;
            padding: 30px 20px;
            border-left: 1px solid #1f1f1f;
            overflow-y: auto;
        }
        
        .logo {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #b3b3b3;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            gap: 12px;
            transition: 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #e50914;
            color: white;
        }
        
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #1a1a1a;
            padding: 20px 30px;
            border-radius: 15px;
        }
        
        h1 {
            font-size: 28px;
            color: #9b2c2c;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #333;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #9b2c2c;
            line-height: 1;
        }
        
        .stat-label {
            color: #b3b3b3;
            margin-top: 5px;
        }
        
        .import-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #9b2c2c;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: #9b2c2c;
            font-size: 24px;
            font-weight: 700;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #fff;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            border-color: #9b2c2c;
            outline: none;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: #9b2c2c;
            color: white;
        }
        
        .btn-primary:hover {
            background: #7a2323;
            transform: translateY(-2px);
        }
        
        .btn-large {
            padding: 15px 40px;
            font-size: 18px;
            width: 100%;
            justify-content: center;
        }
        
        .info-box {
            background: #252525;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .info-box h4 {
            color: #9b2c2c;
            margin-bottom: 10px;
        }
        
        .info-box ul {
            list-style: none;
            padding-right: 20px;
        }
        
        .info-box li {
            color: #b3b3b3;
            margin-bottom: 5px;
        }
        
        .year-badge {
            display: inline-block;
            background: #9b2c2c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-right: 5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-right: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">🎬 فايز<span>تڨي</span></div>
        <nav>
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
            <a href="import-tmdb.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد أفلام</a>
            <a href="import-tv.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد مسلسلات</a>
            <a href="import-turkish-movies-2025.php" class="nav-item active"><i class="fas fa-film" style="color: #9b2c2c;"></i> أفلام تركية 2025</a>
            <a href="import-turkish-series-2025.php" class="nav-item"><i class="fas fa-tv" style="color: #9b2c2c;"></i> مسلسلات تركية 2025</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-film" style="color: #9b2c2c;"></i> استيراد الأفلام التركية 2025</h1>
            <div>
                <span style="color: #b3b3b3;">🇹🇷 إجمالي الأفلام التركية: <?php echo $turkish_movies_total; ?></span>
                <span style="color: #b3b3b3; margin-right: 15px;">📅 أفلام 2025: <?php echo $turkish_movies_2025; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $turkish_movies_total; ?></div>
                <div class="stat-label">إجمالي الأفلام التركية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $turkish_movies_2025; ?></div>
                <div class="stat-label">أفلام تركية 2025</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">2025</div>
                <div class="stat-label">سنة الاستيراد</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                🇹🇷 استيراد الأفلام التركية 2025
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 فيلم)</label>
                    <select name="pages" class="form-control">
                        <option value="1">20 فيلم</option>
                        <option value="2">40 فيلم</option>
                        <option value="3">60 فيلم</option>
                        <option value="5" selected>100 فيلم</option>
                        <option value="10">200 فيلم</option>
                    </select>
                </div>
                
                <button type="submit" name="import_turkish_movies_2025" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد أفلام 2025
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن الأفلام التركية 2025:</h4>
                <ul>
                    <li>• يتم استيراد الأفلام التركية الصادرة في <strong>عام 2025</strong></li>
                    <li>• الترتيب حسب <strong>تاريخ الإصدار (الأحدث أولاً)</strong></li>
                    <li>• الأفلام المكررة يتم تخطيها تلقائياً</li>
                    <li>• يمكن استيراد حتى <strong>200 فيلم</strong> تركي من 2025</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>