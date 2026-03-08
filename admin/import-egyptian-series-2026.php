<?php
// admin/import-egyptian-series-2026.php - استيراد جميع المسلسلات المصرية 2026
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
// استيراد جميع المسلسلات المصرية 2026
// =============================================
if (isset($_POST['import_egyptian_series_2026'])) {
    $pages = (int)($_POST['pages'] ?? 50); // 50 صفحة كحد أقصى (1000 مسلسل)
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد المسلسلات المصرية 2026...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: #ce1126; }
            h2 { color: #e50914; margin: 15px 0; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #ce1126; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .series-info { background: #252525; padding: 8px; margin: 3px 0; border-radius: 4px; font-size: 14px; border-right: 3px solid #ce1126; }
            .year-badge { background: #ce1126; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
            .egypt-flag { color: #ce1126; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🇪🇬 استيراد جميع المسلسلات المصرية 2026</h1>";
    
    ob_flush();
    flush();
    
    // تحديد تاريخ البداية والنهاية لعام 2026
    $start_date = '2026-01-01';
    $end_date = '2026-12-31';
    
    echo "<h2>📅 جلب مسلسلات مصرية من 1 يناير 2026 إلى 31 ديسمبر 2026</h2>";
    ob_flush();
    flush();
    
    // جلب المسلسلات المصرية حسب تاريخ الإصدار
    for ($page = 1; $page <= $pages; $page++) {
        echo "<div class='progress'>⏳ جاري استيراد صفحة {$page} من {$pages}...</div>";
        ob_flush();
        flush();
        
        // رابط API للمسلسلات المصرية
        $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
             . "&language=ar-SA"
             . "&with_original_language=ar"
             . "&with_origin_country=EG"  // مسلسلات أصلها مصري
             . "&sort_by=first_air_date.desc"  // الأحدث أولاً
             . "&first_air_date.gte=" . $start_date
             . "&first_air_date.lte=" . $end_date
             . "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if (!$data || !isset($data['results'])) {
            echo "<div class='progress warning'>⚠️ لا توجد بيانات في الصفحة {$page}</div>";
            continue;
        }
        
        if (empty($data['results'])) {
            echo "<div class='progress warning'>📭 لا توجد مسلسلات مصرية جديدة في الصفحة {$page}</div>";
            break; // توقف إذا لم تعد هناك نتائج
        }
        
        $total_pages = $data['total_pages'] ?? 0;
        
        foreach ($data['results'] as $series) {
            if (!isset($series['id'])) continue;
            
            // استخراج سنة المسلسل
            $release_date = $series['first_air_date'] ?? '';
            $year = !empty($release_date) ? substr($release_date, 0, 4) : '2026';
            
            // تحقق من وجود المسلسل
            $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
            $check->execute([$series['id']]);
            
            if (!$check->fetch()) {
                // تجهيز البيانات
                $title = $series['name'] ?? 'بدون عنوان';
                $description = $series['overview'] ?? 'مسلسل مصري';
                $poster = isset($series['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $series['poster_path'] : null;
                $backdrop = isset($series['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $series['backdrop_path'] : null;
                $rating = $series['vote_average'] ?? 0;
                $seasons = $series['number_of_seasons'] ?? 1;
                
                try {
                    $sql = "INSERT INTO series (
                        tmdb_id, title, description, poster, backdrop, 
                        year, imdb_rating, country, language, status, seasons, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'مصر', 'ar', 'ongoing', ?, 0)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $series['id'],
                        $title,
                        $description,
                        $poster,
                        $backdrop,
                        $year,
                        $rating,
                        $seasons
                    ]);
                    
                    $imported++;
                    
                    // عرض المسلسل المستورد
                    echo "<div class='series-info'>✅ [{$year}] <span class='egypt-flag'>🇪🇬</span> {$title} (⭐ {$rating}) - {$seasons} مواسم</div>";
                    ob_flush();
                    flush();
                    
                } catch (Exception $e) {
                    $errors++;
                    error_log("خطأ في استيراد مسلسل مصري: " . $e->getMessage());
                }
            } else {
                $skipped++;
                echo "<div class='series-info warning'>⏭️ [{$year}] {$title} (موجود مسبقاً)</div>";
                ob_flush();
                flush();
            }
        }
        
        // تأخير بين الصفحات
        usleep(500000);
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل استيراد المسلسلات المصرية 2026!</h2>";
    echo "<p>📊 مسلسلات مصرية جديدة: <strong style='color: #ce1126;'>{$imported}</strong></p>";
    echo "<p>⏭️ مسلسلات موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e50914;'>{$errors}</strong></p>";
    echo "<p>📄 إجمالي الصفحات: <strong>{$total_pages}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-egyptian-series-2026.php' style='display: inline-block; padding: 10px 20px; background: #ce1126; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../egyptian-series.php' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🇪🇬 عرض المسلسلات المصرية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات المسلسلات المصرية
$egyptian_series_total = $pdo->query("SELECT COUNT(*) FROM series WHERE country = 'مصر' OR country LIKE '%مصر%'")->fetchColumn();
$egyptian_series_2026 = $pdo->query("SELECT COUNT(*) FROM series WHERE (country = 'مصر' OR country LIKE '%مصر%') AND year = '2026'")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد المسلسلات المصرية 2026 - ويزي برو</title>
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
            <a href="import-egyptian-movies-2026.php" class="nav-item"><i class="fas fa-film" style="color: #ce1126;"></i> أفلام مصرية 2026</a>
            <a href="import-egyptian-series-2026.php" class="nav-item active"><i class="fas fa-tv" style="color: #ce1126;"></i> مسلسلات مصرية 2026</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-tv" style="color: #ce1126;"></i> استيراد المسلسلات المصرية 2026</h1>
            <div>
                <span style="color: #b3b3b3;">🇪🇬 إجمالي المسلسلات المصرية: <?php echo $egyptian_series_total; ?></span>
                <span style="color: #b3b3b3; margin-right: 15px;">📅 مسلسلات 2026: <?php echo $egyptian_series_2026; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $egyptian_series_total; ?></div>
                <div class="stat-label">إجمالي المسلسلات المصرية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $egyptian_series_2026; ?></div>
                <div class="stat-label">مسلسلات مصرية 2026</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">2026</div>
                <div class="stat-label">سنة الاستيراد</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                🇪🇬 استيراد جميع المسلسلات المصرية 2026
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 مسلسل، الحد الأقصى 1000 مسلسل)</label>
                    <select name="pages" class="form-control">
                        <option value="10">200 مسلسل</option>
                        <option value="20">400 مسلسل</option>
                        <option value="30">600 مسلسل</option>
                        <option value="40">800 مسلسل</option>
                        <option value="50" selected>1000 مسلسل (الحد الأقصى)</option>
                    </select>
                </div>
                
                <button type="submit" name="import_egyptian_series_2026" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد جميع المسلسلات المصرية 2026
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن المسلسلات المصرية 2026:</h4>
                <ul>
                    <li>• يتم استيراد جميع المسلسلات المصرية الصادرة في <strong>عام 2026</strong></li>
                    <li>• الترتيب حسب <strong>تاريخ الإصدار (الأحدث أولاً)</strong></li>
                    <li>• يشمل جميع المسلسلات المصرية من جميع القنوات والمنصات</li>
                    <li>• المسلسلات المكررة يتم تخطيها تلقائياً</li>
                    <li>• يمكن استيراد حتى <strong>1000 مسلسل</strong> مصري من 2026</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>