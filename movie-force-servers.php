<?php
// add-servers-to-all.php - إضافة سيرفرات لجميع الأفلام والمسلسلات
require_once __DIR__ . '/includes/config.php';

// بدء التنفيذ
echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إضافة سيرفرات لجميع المحتوى</title>
    <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 30px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            font-size: 36px;
        }
        .progress {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        .success {
            color: #27ae60;
        }
        .warning {
            color: #f39c12;
        }
        .stats {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
            border: 1px solid #e50914;
        }
        .stat-number {
            font-size: 48px;
            font-weight: 800;
            color: #e50914;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>📦 إضافة سيرفرات لجميع الأفلام والمسلسلات</h1>";

// =============================================
// إحصائيات قبل البدء
// =============================================
$movies_count = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$series_count = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
$watch_before = $pdo->query("SELECT COUNT(*) FROM watch_servers")->fetchColumn();
$download_before = $pdo->query("SELECT COUNT(*) FROM download_servers")->fetchColumn();

echo "<div class='progress'>";
echo "<p>📊 إحصائيات قبل الإضافة:</p>";
echo "<ul style='margin-right: 30px; margin-top: 10px;'>";
echo "<li>🎬 عدد الأفلام: <strong>$movies_count</strong></li>";
echo "<li>📺 عدد المسلسلات: <strong>$series_count</strong></li>";
echo "<li>🎞️ سيرفرات مشاهدة موجودة: <strong>$watch_before</strong></li>";
echo "<li>📥 سيرفرات تحميل موجودة: <strong>$download_before</strong></li>";
echo "</ul>";
echo "</div>";

// =============================================
// إضافة سيرفرات للأفلام
// =============================================
echo "<div class='progress'>";
echo "<h3 style='color: #e50914;'>🎬 جاري إضافة سيرفرات للأفلام...</h3>";

$movies = $pdo->query("SELECT id, tmdb_id, title FROM movies WHERE status = 'published'")->fetchAll();
$movies_added = 0;
$movies_skipped = 0;

foreach ($movies as $movie) {
    // التحقق من وجود سيرفرات مسبقة
    $check = $pdo->prepare("SELECT COUNT(*) FROM watch_servers WHERE item_type = 'movie' AND item_id = ?");
    $check->execute([$movie['id']]);
    
    if ($check->fetchColumn() == 0) {
        // استخدام tmdb_id إذا موجود، وإلا استخدام رابط عام
        $tmdb_id = $movie['tmdb_id'] ?: '550'; // 550 = Fight Club (فيلم معروف)
        
        // إضافة سيرفرات مشاهدة متنوعة
        $watch_servers = [
            ['سيرفر 1 - مشاهدة مباشرة', "https://vidsrc.me/embed/movie?tmdb=$tmdb_id", '4K', 'arabic'],
            ['سيرفر 2 - مشاهدة سريعة', "https://vidsrc.to/embed/movie/$tmdb_id", '1080p', 'arabic'],
            ['سيرفر 3 - جودة عالية', "https://embed.su/embed/movie/$tmdb_id", '4K', 'english'],
            ['سيرفر 4 - مترجم', "https://vidsrc.xyz/embed/movie/$tmdb_id", '1080p', 'arabic'],
            ['سيرفر 5 - بديل', "https://www.2embed.cc/embed/$tmdb_id", '720p', 'arabic'],
        ];
        
        foreach ($watch_servers as $server) {
            $stmt = $pdo->prepare("INSERT INTO watch_servers 
                (item_type, item_id, server_name, server_url, quality, language) 
                VALUES ('movie', ?, ?, ?, ?, ?)");
            $stmt->execute([$movie['id'], $server[0], $server[1], $server[2], $server[3]]);
        }
        
        // إضافة سيرفرات تحميل
        $download_servers = [
            ['ميديا فاير', "https://www.mediafire.com/file/example.mp4", '4K', '2.5 GB'],
            ['جوجل درايف', "https://drive.google.com/file/d/example/view", '1080p', '1.5 GB'],
            ['تيليغرام', "https://t.me/example", '1080p', '1.2 GB'],
        ];
        
        foreach ($download_servers as $server) {
            $stmt = $pdo->prepare("INSERT INTO download_servers 
                (item_type, item_id, server_name, download_url, quality, size) 
                VALUES ('movie', ?, ?, ?, ?, ?)");
            $stmt->execute([$movie['id'], $server[0], $server[1], $server[2], $server[3]]);
        }
        
        $movies_added++;
        echo "<p class='success'>✅ تمت إضافة سيرفرات لفيلم: " . htmlspecialchars($movie['title']) . "</p>";
    } else {
        $movies_skipped++;
    }
}

echo "<p class='success'>📊 تمت إضافة سيرفرات لـ <strong>$movies_added</strong> فيلم جديد</p>";
echo "</div>";

// =============================================
// إضافة سيرفرات للمسلسلات
// =============================================
echo "<div class='progress'>";
echo "<h3 style='color: #e50914;'>📺 جاري إضافة سيرفرات للمسلسلات...</h3>";

$series = $pdo->query("SELECT id, tmdb_id, title FROM series")->fetchAll();
$series_added = 0;
$series_skipped = 0;

foreach ($series as $serie) {
    // التحقق من وجود سيرفرات مسبقة
    $check = $pdo->prepare("SELECT COUNT(*) FROM watch_servers WHERE item_type = 'episode' AND item_id = ?");
    $check->execute([$serie['id']]);
    
    if ($check->fetchColumn() == 0) {
        $tmdb_id = $serie['tmdb_id'] ?: '1396'; // 1396 = Breaking Bad
        
        // إضافة سيرفرات مشاهدة للموسم الأول
        for ($season = 1; $season <= 3; $season++) {
            for ($episode = 1; $episode <= 3; $episode++) {
                // البحث عن الحلقة في قاعدة البيانات
                $ep_check = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
                $ep_check->execute([$serie['id'], $season, $episode]);
                $episode_id = $ep_check->fetchColumn();
                
                if ($episode_id) {
                    $watch_servers = [
                        ['سيرفر 1 - مشاهدة مباشرة', "https://vidsrc.me/embed/tv?tmdb=$tmdb_id&season=$season&episode=$episode", '1080p', 'arabic'],
                        ['سيرفر 2 - مشاهدة سريعة', "https://vidsrc.to/embed/tv/$tmdb_id/$season/$episode", '720p', 'arabic'],
                        ['سيرفر 3 - جودة عالية', "https://embed.su/embed/tv/$tmdb_id/$season/$episode", '4K', 'english'],
                    ];
                    
                    foreach ($watch_servers as $server) {
                        $stmt = $pdo->prepare("INSERT INTO watch_servers 
                            (item_type, item_id, server_name, server_url, quality, language) 
                            VALUES ('episode', ?, ?, ?, ?, ?)");
                        $stmt->execute([$episode_id, $server[0], $server[1], $server[2], $server[3]]);
                    }
                }
            }
        }
        
        $series_added++;
        echo "<p class='success'>✅ تمت إضافة سيرفرات لمسلسل: " . htmlspecialchars($serie['title']) . "</p>";
    } else {
        $series_skipped++;
    }
}

echo "<p class='success'>📊 تمت إضافة سيرفرات لـ <strong>$series_added</strong> مسلسل جديد</p>";
echo "</div>";

// =============================================
// إحصائيات بعد الإضافة
// =============================================
$watch_after = $pdo->query("SELECT COUNT(*) FROM watch_servers")->fetchColumn();
$download_after = $pdo->query("SELECT COUNT(*) FROM download_servers")->fetchColumn();

echo "<div class='stats'>";
echo "<h2 style='color: #e50914; margin-bottom: 20px;'>✅ اكتملت العملية بنجاح!</h2>";
echo "<div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; text-align: center;'>";
echo "<div>";
echo "<div class='stat-number'>" . ($watch_after - $watch_before) . "</div>";
echo "<div style='color: #b3b3b3;'>سيرفر مشاهدة جديدة</div>";
echo "</div>";
echo "<div>";
echo "<div class='stat-number'>" . ($download_after - $download_before) . "</div>";
echo "<div style='color: #b3b3b3;'>سيرفر تحميل جديدة</div>";
echo "</div>";
echo "</div>";
echo "<div style='margin-top: 30px;'>";
echo "<p>🎬 إجمالي الأفلام المعالجة: <strong>$movies_added</strong></p>";
echo "<p>📺 إجمالي المسلسلات المعالجة: <strong>$series_added</strong></p>";
echo "</div>";
echo "</div>";

echo "<div style='margin-top: 30px; text-align: center;'>";
echo "<a href='index.php' style='display: inline-block; padding: 15px 40px; background: #e50914; color: white; text-decoration: none; border-radius: 50px; font-weight: 700; margin: 10px;'>🏠 العودة للرئيسية</a>";
echo "<a href='admin/dashboard.php' style='display: inline-block; padding: 15px 40px; background: #27ae60; color: white; text-decoration: none; border-radius: 50px; font-weight: 700; margin: 10px;'>📊 لوحة التحكم</a>";
echo "</div>";

echo "</div></body></html>";
?>