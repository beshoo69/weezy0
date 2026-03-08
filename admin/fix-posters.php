<?php
// admin/import-hd-posters.php - استيراد صور بوستر عالية الجودة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$series_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($series_id == 0) {
    // عرض قائمة المسلسلات
    $series_list = $pdo->query("SELECT id, title, poster FROM series ORDER BY id DESC LIMIT 50")->fetchAll();
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>استيراد صور عالية الجودة - ويزي برو</title>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
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
                background: #1a1a1a;
                padding: 30px;
                border-radius: 15px;
            }
            h1 {
                color: #e50914;
                margin-bottom: 30px;
            }
            .series-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 20px;
            }
            .series-card {
                background: #252525;
                border-radius: 10px;
                overflow: hidden;
                border: 1px solid #333;
            }
            .series-poster {
                width: 100%;
                height: 250px;
                object-fit: cover;
            }
            .series-info {
                padding: 15px;
            }
            .series-title {
                font-size: 16px;
                font-weight: 700;
                margin-bottom: 10px;
            }
            .btn {
                display: inline-block;
                background: #e50914;
                color: white;
                padding: 8px 15px;
                border-radius: 5px;
                text-decoration: none;
                width: 100%;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🖼️ استيراد صور عالية الجودة</h1>
            <div class="series-grid">
                <?php foreach ($series_list as $series): ?>
                <div class="series-card">
                    <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($series['title']); ?>" 
                         class="series-poster">
                    <div class="series-info">
                        <div class="series-title"><?php echo $series['title']; ?></div>
                        <a href="?id=<?php echo $series['id']; ?>" class="btn">استيراد صورة HD</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// استيراد صورة عالية الجودة لمسلسل محدد
$stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
$stmt->execute([$series_id]);
$series = $stmt->fetch();

if (!$series) {
    die("❌ المسلسل غير موجود");
}

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>استيراد صورة HD - {$series['title']}</title>
    <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
        .container { max-width: 800px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
        h1 { color: #e50914; }
        .poster-box { display: flex; gap: 20px; margin: 30px 0; }
        .old-poster, .new-poster { flex: 1; text-align: center; }
        img { max-width: 100%; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .btn { display: inline-block; padding: 12px 25px; background: #e50914; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🖼️ استيراد صورة عالية الجودة لـ: {$series['title']}</h1>";

// البحث عن صور عالية الجودة
$search_url = "https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY 
            . "&query=" . urlencode($series['title']) . "&language=ar-SA";
$search_data = tmdb_request($search_url);

if ($search_data && isset($search_data['results'][0])) {
    $tmdb_id = $search_data['results'][0]['id'];
    
    // جلب صور عالية الجودة
    $images_url = "https://api.themoviedb.org/3/tv/" . $tmdb_id . "/images?api_key=" . TMDB_API_KEY;
    $images_data = tmdb_request($images_url);
    
    echo "<div class='poster-box'>";
    echo "<div class='old-poster'>";
    echo "<h3>البوستر الحالي</h3>";
    echo "<img src='" . ($series['poster'] ?? 'https://via.placeholder.com/300x450') . "' style='max-width:300px;'>";
    echo "</div>";
    
    if ($images_data && isset($images_data['posters'][0])) {
        $hd_poster = 'https://image.tmdb.org/t/p/original' . $images_data['posters'][0]['file_path'];
        
        echo "<div class='new-poster'>";
        echo "<h3>البوستر الجديد (HD)</h3>";
        echo "<img src='{$hd_poster}' style='max-width:300px;'>";
        echo "</div>";
        echo "</div>";
        
        // تحديث قاعدة البيانات
        $update = $pdo->prepare("UPDATE series SET poster = ? WHERE id = ?");
        $update->execute([$hd_poster, $series_id]);
        
        echo "<div style='text-align:center; margin-top:30px;'>";
        echo "<p style='color:#27ae60;'>✅ تم تحديث الصورة بنجاح!</p>";
        echo "<a href='../series.php?id={$series_id}' class='btn'>عرض المسلسل</a>";
        echo "</div>";
        
    } else {
        echo "<div class='new-poster'>";
        echo "<h3>لا توجد صور عالية الجودة</h3>";
        echo "</div></div>";
    }
}

echo "</div></body></html>";
?>