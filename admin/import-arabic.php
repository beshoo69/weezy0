<?php
// admin/import-arabic-movies.php - استيراد أحدث الأفلام العربية من 2025-2026
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

// قائمة الدول العربية
$arab_countries = [
    'EG' => 'مصر',
    'SA' => 'السعودية',
    'LB' => 'لبنان',
    'SY' => 'سوريا',
    'AE' => 'الإمارات',
    'KW' => 'الكويت',
    'MA' => 'المغرب',
    'TN' => 'تونس',
    'DZ' => 'الجزائر',
    'IQ' => 'العراق',
    'JO' => 'الأردن',
    'PS' => 'فلسطين',
    'YE' => 'اليمن',
    'OM' => 'عمان',
    'QA' => 'قطر',
    'BH' => 'البحرين'
];

// =============================================
// استيراد أحدث الأفلام العربية (حسب تاريخ الإصدار)
// =============================================
if (isset($_POST['import_arabic_movies'])) {
    $pages = (int)($_POST['pages'] ?? 5);
    $year_from = (int)($_POST['year_from'] ?? 2025);
    $year_to = (int)($_POST['year_to'] ?? 2026);
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد أحدث الأفلام العربية...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 800px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: #0e4620; }
            h2 { color: #e50914; margin: 15px 0; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #0e4620; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .movie-info { background: #252525; padding: 8px; margin: 3px 0; border-radius: 4px; font-size: 14px; }
            .year-badge { background: #0e4620; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>📽️ استيراد أحدث الأفلام العربية ({$year_from}-{$year_to})</h1>";
    
    ob_flush();
    flush();
    
    // تحديد تاريخ البداية والنهاية
    $start_date = $year_from . '-01-01';
    $end_date = $year_to . '-12-31';
    
    echo "<h2>📅 جلب أفلام من {$year_from} إلى {$year_to}</h2>";
    ob_flush();
    flush();
    
    // جلب الأفلام العربية حسب تاريخ الإصدار
    for ($page = 1; $page <= $pages; $page++) {
        echo "<div class='progress'>⏳ جاري استيراد صفحة {$page} من {$pages}...</div>";
        ob_flush();
        flush();
        
        // رابط API مع تحديد تاريخ الإصدار
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
             . "&language=ar-SA"
             . "&with_original_language=ar"
             . "&sort_by=release_date.desc"  // ترتيب حسب تاريخ الإصدار (الأحدث أولاً)
             . "&primary_release_date.gte=" . $start_date
             . "&primary_release_date.lte=" . $end_date
             . "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if (!$data || !isset($data['results'])) {
            echo "<div class='progress warning'>⚠️ لا توجد بيانات في الصفحة {$page}</div>";
            continue;
        }
        
        if (empty($data['results'])) {
            echo "<div class='progress warning'>📭 لا توجد أفلام عربية جديدة في الصفحة {$page}</div>";
            continue;
        }
        
        foreach ($data['results'] as $movie) {
            if (!isset($movie['id'])) continue;
            
            // استخراج سنة الفيلم
            $release_date = $movie['release_date'] ?? '';
            $year = !empty($release_date) ? substr($release_date, 0, 4) : 'غير معروف';
            
            // تحقق من وجود الفيلم
            $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
            $check->execute([$movie['id']]);
            
            if (!$check->fetch()) {
                // تجهيز البيانات
                $title = $movie['title'] ?? 'بدون عنوان';
                $description = $movie['overview'] ?? 'فيلم عربي';
                $poster = isset($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
                $backdrop = isset($movie['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'] : null;
                $rating = $movie['vote_average'] ?? 0;
                
                try {
                    $sql = "INSERT INTO movies (
                        tmdb_id, title, description, poster, backdrop, 
                        year, imdb_rating, language, status, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'ar', 'published', 0)";
                    
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
                    echo "<div class='movie-info'>✅ [{$year}] {$title} (⭐ {$rating})</div>";
                    ob_flush();
                    flush();
                    
                } catch (Exception $e) {
                    $errors++;
                    error_log("خطأ في استيراد فيلم عربي: " . $e->getMessage());
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
    echo "<h2 class='success'>✅ اكتمل استيراد أفلام {$year_from}-{$year_to}!</h2>";
    echo "<p>📊 أفلام جديدة: <strong style='color: #0e4620;'>{$imported}</strong></p>";
    echo "<p>⏭️ أفلام موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e50914;'>{$errors}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-arabic-movies.php' style='display: inline-block; padding: 10px 20px; background: #0e4620; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../arabic-movies.php' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>📽️ عرض الأفلام العربية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات الأفلام العربية حسب السنة
$movies_2025 = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'ar' AND year = '2025'")->fetchColumn();
$movies_2026 = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'ar' AND year = '2026'")->fetchColumn();
$arabic_movies_count = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'ar'")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد أحدث الأفلام العربية - ويزي برو</title>
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
            color: #0e4620;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
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
            font-size: 32px;
            font-weight: 800;
            color: #0e4620;
            line-height: 1;
        }
        
        .stat-label {
            color: #b3b3b3;
            margin-top: 5px;
        }
        
        .stat-small {
            font-size: 14px;
            color: #b3b3b3;
            margin-top: 5px;
        }
        
        .import-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #0e4620;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: #0e4620;
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
            border-color: #0e4620;
            outline: none;
        }
        
        .row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .col {
            flex: 1;
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
            background: #0e4620;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0a2e15;
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
            color: #0e4620;
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
            background: #0e4620;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 5px;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-right: 0; }
            .row { flex-direction: column; }
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
            <a href="import-arabic-movies.php" class="nav-item active"><i class="fas fa-film" style="color: #0e4620;"></i> أفلام عربية</a>
            <a href="import-middle-east.php" class="nav-item"><i class="fas fa-map-marker-alt"></i> محتوى عربي شامل</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-film" style="color: #0e4620;"></i> استيراد أحدث الأفلام العربية</h1>
            <div>
                <span style="color: #b3b3b3;">📊 إجمالي الأفلام العربية: <?php echo $arabic_movies_count; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $arabic_movies_count; ?></div>
                <div class="stat-label">إجمالي الأفلام العربية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $movies_2025; ?></div>
                <div class="stat-label">أفلام 2025</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $movies_2026; ?></div>
                <div class="stat-label">أفلام 2026</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                استيراد أحدث الأفلام العربية (2025-2026)
            </div>
            
            <form method="POST">
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label>من سنة</label>
                            <select name="year_from" class="form-control">
                                <option value="2024">2024</option>
                                <option value="2025" selected>2025</option>
                                <option value="2026">2026</option>
                            </select>
                        </div>
                    </div>
                    <div class="col">
                        <div class="form-group">
                            <label>إلى سنة</label>
                            <select name="year_to" class="form-control">
                                <option value="2025">2025</option>
                                <option value="2026" selected>2026</option>
                                <option value="2027">2027</option>
                            </select>
                        </div>
                    </div>
                </div>
                
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
                
                <button type="submit" name="import_arabic_movies" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد أحدث الأفلام العربية
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن الأفلام العربية الحديثة:</h4>
                <ul>
                    <li>• يتم استيراد الأفلام العربية الصادرة في <strong>2025 و 2026</strong></li>
                    <li>• الترتيب حسب <strong>تاريخ الإصدار (الأحدث أولاً)</strong></li>
                    <li>• الأفلام من جميع الدول العربية: مصر، السعودية، لبنان، سوريا، الخليج، المغرب العربي</li>
                    <li>• الأفلام المكررة يتم تخطيها تلقائياً</li>
                    <li>• 100 فيلم = أحدث 100 فيلم عربي من 2025-2026</li>
                </ul>
            </div>
        </div>
        
        <div style="background: #1a1a1a; border-radius: 15px; padding: 25px;">
            <h3 style="color: #0e4620; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-list"></i> قائمة الدول العربية المدعومة
            </h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                <?php foreach ($arab_countries as $code => $name): ?>
                <div style="background: #252525; padding: 10px; border-radius: 8px; text-align: center; border: 1px solid #333;">
                    <span style="color: #0e4620; font-weight: 700;"><?php echo $code; ?></span>
                    <span style="color: #b3b3b3; margin-right: 5px;"><?php echo $name; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>