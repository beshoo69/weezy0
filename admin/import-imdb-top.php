<?php
// admin/import-imdb-top.php - استيراد أفضل 20 مسلسل من IMDb 2025
ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');
set_time_limit(300);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$imported = 0;
$skipped = 0;

// قائمة أفضل 20 مسلسل في IMDb لعام 2025 (وفقاً للقوائم الرسمية)
$imdb_top_2025 = [
    ['title' => 'When Life Gives You Tangerines', 'year' => 2025, 'rating' => 9.2, 'country' => 'كوريا الجنوبية', 'language' => 'ko'],
    ['title' => 'The Pitt', 'year' => 2025, 'rating' => 8.9, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Invincible', 'year' => 2025, 'rating' => 8.7, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Severance', 'year' => 2025, 'rating' => 8.7, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Black Mirror', 'year' => 2025, 'rating' => 8.7, 'country' => 'المملكة المتحدة', 'language' => 'en'],
    ['title' => 'Andor', 'year' => 2025, 'rating' => 8.5, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'MobLand', 'year' => 2025, 'rating' => 8.4, 'country' => 'المملكة المتحدة', 'language' => 'en'],
    ['title' => 'Solo Leveling', 'year' => 2025, 'rating' => 8.6, 'country' => 'اليابان', 'language' => 'ja'],
    ['title' => '1923', 'year' => 2025, 'rating' => 8.3, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Dept. Q', 'year' => 2025, 'rating' => 8.2, 'country' => 'الدنمارك', 'language' => 'da'],
    ['title' => 'Daredevil: Born Again', 'year' => 2025, 'rating' => 8.0, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'American Primeval', 'year' => 2025, 'rating' => 8.0, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Adolescence', 'year' => 2025, 'rating' => 8.1, 'country' => 'المملكة المتحدة', 'language' => 'en'],
    ['title' => 'Reacher', 'year' => 2025, 'rating' => 8.0, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Paradise', 'year' => 2025, 'rating' => 7.8, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'The White Lotus', 'year' => 2025, 'rating' => 8.0, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'The Studio', 'year' => 2025, 'rating' => 8.1, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'Your Friends & Neighbors', 'year' => 2025, 'rating' => 7.9, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'The Residence', 'year' => 2025, 'rating' => 7.8, 'country' => 'الولايات المتحدة', 'language' => 'en'],
    ['title' => 'The Eternaut', 'year' => 2025, 'rating' => 7.7, 'country' => 'الأرجنتين', 'language' => 'es']
];

if (isset($_POST['import_imdb_top'])) {
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد أفضل مسلسلات IMDb...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 800px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: gold; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid gold; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .imdb-badge { background: gold; color: black; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>⭐ استيراد أفضل 20 مسلسل في IMDb 2025</h1>";
    
    ob_flush();
    flush();
    
    foreach ($imdb_top_2025 as $index => $series) {
        echo "<div class='progress'>⏳ جاري استيراد {$series['title']}...</div>";
        ob_flush();
        flush();
        
        // تحقق من وجود المسلسل
        $check = $pdo->prepare("SELECT id FROM series WHERE title LIKE ? OR title_en LIKE ?");
        $search = "%{$series['title']}%";
        $check->execute([$search, $search]);
        
        if (!$check->fetch()) {
            // البحث عن المسلسل في TMDB لجلب الصور
            $search_url = "https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY 
                        . "&query=" . urlencode($series['title']) . "&language=ar-SA";
            $search_data = tmdb_request($search_url);
            
            $poster = null;
            $backdrop = null;
            $description = "مسلسل {$series['country']} حاصل على تقييم {$series['rating']} في IMDb ضمن أفضل مسلسلات 2025";
            $seasons = 1;
            
            if ($search_data && isset($search_data['results'][0])) {
                $tv_data = $search_data['results'][0];
                $poster = isset($tv_data['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $tv_data['poster_path'] : null;
                $backdrop = isset($tv_data['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $tv_data['backdrop_path'] : null;
                $description = $tv_data['overview'] ?? $description;
            }
            
            try {
                $sql = "INSERT INTO series (
                    title, title_en, description, poster, backdrop, year, seasons, imdb_rating, country, language, status, views
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ongoing', 0)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $series['title'],
                    $series['title'],
                    $description,
                    $poster,
                    $backdrop,
                    $series['year'],
                    $seasons,
                    $series['rating'],
                    $series['country'],
                    $series['language']
                ]);
                
                $imported++;
                echo "<div class='progress success'>✅ تم استيراد: {$series['title']} <span class='imdb-badge'>⭐ {$series['rating']}</span></div>";
                
            } catch (Exception $e) {
                echo "<div class='progress warning'>❌ خطأ في استيراد: {$series['title']}</div>";
            }
        } else {
            $skipped++;
            echo "<div class='progress warning'>⏭️ موجود مسبقاً: {$series['title']}</div>";
        }
        
        usleep(500000);
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل استيراد أفضل مسلسلات IMDb 2025!</h2>";
    echo "<p>📊 مسلسلات جديدة: <strong style='color: gold;'>{$imported}</strong></p>";
    echo "<p>⏭️ مسلسلات موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-imdb-top.php' style='display: inline-block; padding: 10px 20px; background: gold; color: black; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../index.php#recommended' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🏠 عرض الصفحة الرئيسية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات
$top_series_count = $pdo->query("SELECT COUNT(*) FROM series WHERE imdb_rating >= 7.5")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد أفضل مسلسلات IMDb - ويزي برو</title>
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
            color: gold;
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
            color: gold;
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
            border: 1px solid gold;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: gold;
            font-size: 24px;
            font-weight: 700;
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
            background: gold;
            color: black;
        }
        
        .btn-primary:hover {
            background: #e6c200;
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
            color: gold;
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
        
        .top-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .top-item {
            background: #252525;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .top-rank {
            background: gold;
            color: black;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }
        
        .top-rating {
            margin-right: auto;
            color: gold;
            font-weight: 700;
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
            <a href="import-imdb-top.php" class="nav-item active"><i class="fas fa-star" style="color: gold;"></i> أفضل IMDb 2025</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-star" style="color: gold;"></i> أفضل 20 مسلسل في IMDb 2025</h1>
            <div>
                <span style="color: #b3b3b3;">⭐ مسلسلات مميزة: <?php echo $top_series_count; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $top_series_count; ?></div>
                <div class="stat-label">مسلسل بتقييم عالي</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">20</div>
                <div class="stat-label">أفضل مسلسل 2025</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">9.2</div>
                <div class="stat-label">أعلى تقييم</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                ⭐ استيراد أفضل 20 مسلسل في IMDb 2025
            </div>
            
            <form method="POST">
                <button type="submit" name="import_imdb_top" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد أفضل مسلسلات IMDb
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 قائمة أفضل 20 مسلسل في IMDb 2025:</h4>
                <div class="top-list">
                    <?php foreach ($imdb_top_2025 as $index => $series): ?>
                    <div class="top-item">
                        <span class="top-rank"><?php echo $index + 1; ?></span>
                        <span><?php echo $series['title']; ?></span>
                        <span class="top-rating">⭐ <?php echo $series['rating']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>