<?php
// admin/import-egyptian-movies-2026.php - استيراد جميع الأفلام المصرية 2026
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
$total_pages = 0;

// =============================================
// استيراد جميع الأفلام المصرية 2026
// =============================================
if (isset($_POST['import_egyptian_movies_2026'])) {
    $pages = (int)($_POST['pages'] ?? 50); // 50 صفحة كحد أقصى (1000 فيلم)
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد الأفلام المصرية 2026...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: #ce1126; } /* لون العلم المصري */
            h2 { color: #e50914; margin: 15px 0; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #ce1126; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .movie-info { background: #252525; padding: 8px; margin: 3px 0; border-radius: 4px; font-size: 14px; border-right: 3px solid #ce1126; }
            .year-badge { background: #ce1126; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
            .egypt-flag { color: #ce1126; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🇪🇬 استيراد جميع الأفلام المصرية 2026</h1>";
    
    ob_flush();
    flush();
    
    // تحديد تاريخ البداية والنهاية لعام 2026
    $start_date = '2026-01-01';
    $end_date = '2026-12-31';
    
    echo "<h2>📅 جلب أفلام مصرية من 1 يناير 2026 إلى 31 ديسمبر 2026</h2>";
    ob_flush();
    flush();
    
    // جلب الأفلام المصرية حسب تاريخ الإصدار
    for ($page = 1; $page <= $pages; $page++) {
        echo "<div class='progress'>⏳ جاري استيراد صفحة {$page} من {$pages}...</div>";
        ob_flush();
        flush();
        
        // رابط API للأفلام المصرية (مصر = region=EG)
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
             . "&language=ar-SA"
             . "&with_original_language=ar"
             . "&region=EG"  // تركيز على مصر
             . "&sort_by=release_date.desc"  // الأحدث أولاً
             . "&primary_release_date.gte=" . $start_date
             . "&primary_release_date.lte=" . $end_date
             . "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if (!$data || !isset($data['results'])) {
            echo "<div class='progress warning'>⚠️ لا توجد بيانات في الصفحة {$page}</div>";
            continue;
        }
        
        if (empty($data['results'])) {
            echo "<div class='progress warning'>📭 لا توجد أفلام مصرية جديدة في الصفحة {$page}</div>";
            break; // توقف إذا لم تعد هناك نتائج
        }
        
        $total_pages = $data['total_pages'] ?? 0;
        
        foreach ($data['results'] as $movie) {
            if (!isset($movie['id'])) continue;
            
            // استخراج سنة الفيلم
            $release_date = $movie['release_date'] ?? '';
            $year = !empty($release_date) ? substr($release_date, 0, 4) : '2026';
            
            // تحقق من وجود الفيلم
            $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
            $check->execute([$movie['id']]);
            
            if (!$check->fetch()) {
                // تجهيز البيانات
                $title = $movie['title'] ?? 'بدون عنوان';
                $description = $movie['overview'] ?? 'فيلم مصري';
                $poster = isset($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
                $backdrop = isset($movie['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'] : null;
                $rating = $movie['vote_average'] ?? 0;
                
                try {
                    $sql = "INSERT INTO movies (
                        tmdb_id, title, description, poster, backdrop, 
                        year, imdb_rating, country, language, status, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'مصر', 'ar', 'published', 0)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $movie['id'],
                        $title,
                        $description,
                        $poster,
                        $backdrop,
                        $year,
                        $rating
                    ]);
                    
                    $imported++;
                    
                    // عرض الفيلم المستورد
                    echo "<div class='movie-info'>✅ [{$year}] <span class='egypt-flag'>🇪🇬</span> {$title} (⭐ {$rating})</div>";
                    ob_flush();
                    flush();
                    
                } catch (Exception $e) {
                    $errors++;
                    error_log("خطأ في استيراد فيلم مصري: " . $e->getMessage());
                }
            } else {
                $skipped++;
                echo "<div class='movie-info warning'>⏭️ [{$year}] {$title} (موجود مسبقاً)</div>";
                ob_flush();
                flush();
            }
        }
        
        // تأخير بين الصفحات
        usleep(500000);
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل استيراد الأفلام المصرية 2026!</h2>";
    echo "<p>📊 أفلام مصرية جديدة: <strong style='color: #ce1126;'>{$imported}</strong></p>";
    echo "<p>⏭️ أفلام موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e50914;'>{$errors}</strong></p>";
    echo "<p>📄 إجمالي الصفحات: <strong>{$total_pages}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-egyptian-movies-2026.php' style='display: inline-block; padding: 10px 20px; background: #ce1126; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../egyptian-movies.php' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🇪🇬 عرض الأفلام المصرية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات الأفلام المصرية
$egyptian_movies_total = $pdo->query("SELECT COUNT(*) FROM movies WHERE country = 'مصر' OR country LIKE '%مصر%'")->fetchColumn();
$egyptian_movies_2026 = $pdo->query("SELECT COUNT(*) FROM movies WHERE (country = 'مصر' OR country LIKE '%مصر%') AND year = '2026'")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد الأفلام المصرية 2026 - ويزي برو</title>
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
            color: #ce1126;
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
            color: #ce1126;
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
            border: 1px solid #ce1126;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: #ce1126;
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
            border-color: #ce1126;
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
            background: #ce1126;
            color: white;
        }
        
        .btn-primary:hover {
            background: #a50e1f;
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
            color: #ce1126;
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
            <a href="import-arabic-movies.php" class="nav-item"><i class="fas fa-film" style="color: #0e4620;"></i> أفلام عربية</a>
            <a href="import-egyptian-movies-2026.php" class="nav-item active"><i class="fas fa-film" style="color: #ce1126;"></i> أفلام مصرية 2026</a>
            <a href="import-egyptian-series-2026.php" class="nav-item"><i class="fas fa-tv" style="color: #ce1126;"></i> مسلسلات مصرية 2026</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-film" style="color: #ce1126;"></i> استيراد الأفلام المصرية 2026</h1>
            <div>
                <span style="color: #b3b3b3;">🇪🇬 إجمالي الأفلام المصرية: <?php echo $egyptian_movies_total; ?></span>
                <span style="color: #b3b3b3; margin-right: 15px;">📅 أفلام 2026: <?php echo $egyptian_movies_2026; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $egyptian_movies_total; ?></div>
                <div class="stat-label">إجمالي الأفلام المصرية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $egyptian_movies_2026; ?></div>
                <div class="stat-label">أفلام مصرية 2026</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">2026</div>
                <div class="stat-label">سنة الاستيراد</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                🇪🇬 استيراد جميع الأفلام المصرية 2026
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 فيلم، الحد الأقصى 1000 فيلم)</label>
                    <select name="pages" class="form-control">
                        <option value="10">200 فيلم</option>
                        <option value="20">400 فيلم</option>
                        <option value="30">600 فيلم</option>
                        <option value="40">800 فيلم</option>
                        <option value="50" selected>1000 فيلم (الحد الأقصى)</option>
                    </select>
                </div>
                
                <button type="submit" name="import_egyptian_movies_2026" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد جميع الأفلام المصرية 2026
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن الأفلام المصرية 2026:</h4>
                <ul>
                    <li>• يتم استيراد جميع الأفلام المصرية الصادرة في <strong>عام 2026</strong></li>
                    <li>• الترتيب حسب <strong>تاريخ الإصدار (الأحدث أولاً)</strong></li>
                    <li>• يشمل جميع الأفلام المصرية من جميع الشركات والموزعين</li>
                    <li>• الأفلام المكررة يتم تخطيها تلقائياً</li>
                    <li>• يمكن استيراد حتى <strong>1000 فيلم</strong> مصري من 2026</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>