<?php
// admin/import-100-anime.php - استيراد 100 مسلسل أنمي فقط
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';  // ✅ المسار الصحيح

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// =============================================
// استيراد 100 مسلسل أنمي (5 صفحات × 20 = 100)
// =============================================
$imported = 0;
$skipped = 0;
$pages = 5; // 5 صفحات = 100 مسلسل
$anime_genre_id = 16; // تصنيف الأنمي في TMDB

// بدء التوقيت
$start_time = microtime(true);

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>جاري استيراد 100 مسلسل أنمي...</title>
    <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #333;
        }
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .progress {
            background: #252525;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-right: 4px solid #e50914;
        }
        .success {
            color: #27ae60;
        }
        .warning {
            color: #f39c12;
        }
        .stats {
            background: #0a0a0a;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            background: #e50914;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1><i class='fas fa-dragon'></i> استيراد 100 مسلسل أنمي</h1>";

// جلب مسلسلات الأنمي من TMDB
for ($page = 1; $page <= $pages; $page++) {
    echo "<div class='progress'>⏳ جاري استيراد الصفحة {$page} من {$pages}...</div>";
    ob_flush();
    flush();
    
    // بناء رابط API للأنمي
    $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
         . "&language=" . TMDB_LANGUAGE
         . "&with_genres=" . $anime_genre_id
         . "&sort_by=popularity.desc"
         . "&page=" . $page;
    
    $data = tmdb_request($url);
    
    if ($data && isset($data['results'])) {
        foreach ($data['results'] as $anime) {
            if (!isset($anime['id'])) continue;
            
            // تحقق من وجود المسلسل
            $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
            $check->execute([$anime['id']]);
            
            if (!$check->fetch()) {
                // تجهيز البيانات
                $title = $anime['name'] ?? 'بدون عنوان';
                $original_title = $anime['original_name'] ?? '';
                $description = $anime['overview'] ?? 'مسلسل أنمي';
                $poster = isset($anime['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $anime['poster_path'] : null;
                $backdrop = isset($anime['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $anime['backdrop_path'] : null;
                $year = isset($anime['first_air_date']) ? substr($anime['first_air_date'], 0, 4) : date('Y');
                $rating = $anime['vote_average'] ?? 0;
                $seasons = $anime['number_of_seasons'] ?? 1;
                
                try {
                    $sql = "INSERT INTO series (
                        tmdb_id, title, title_en, description, poster, backdrop, 
                        year, seasons, imdb_rating, genre, language, status, views
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'أنمي', 'ja', 'ongoing', 0)";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $anime['id'],
                        $title,
                        $original_title,
                        $description,
                        $poster,
                        $backdrop,
                        $year,
                        $seasons,
                        $rating
                    ]);
                    
                    $imported++;
                    echo "<div class='progress success'>✅ تم استيراد: {$title}</div>";
                    
                } catch (Exception $e) {
                    echo "<div class='progress warning'>❌ خطأ في استيراد: {$title}</div>";
                }
            } else {
                $skipped++;
                echo "<div class='progress warning'>⏭️ موجود مسبقاً: {$anime['name']}</div>";
            }
        }
    }
    
    // تأخير بسيط بين الصفحات
    usleep(500000); // 0.5 ثانية
}

$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

echo "<div class='stats'>";
echo "<h2 style='color: #e50914;'>✅ اكتمل الاستيراد!</h2>";
echo "<p>📊 إجمالي المسلسلات الجديدة: <strong style='color: #27ae60;'>{$imported}</strong></p>";
echo "<p>⏭️ المسلسلات الموجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
echo "<p>⏱️ الوقت المستغرق: <strong>{$execution_time}</strong> ثانية</p>";
echo "</div>";

echo "<a href='../index.php' class='btn'><i class='fas fa-home'></i> العودة للصفحة الرئيسية</a>";
echo "<a href='import-anime.php' class='btn' style='background: #2a2a2a; margin-right: 10px;'><i class='fas fa-arrow-left'></i> استيراد المزيد</a>";
echo "</div></body></html>";

?>