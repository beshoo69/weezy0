<?php
// admin/test-tv-api.php - اختبار API المسلسلات
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/tmdb-simple.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>اختبار API المسلسلات</title>
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #e50914; }
        .success { background: #27ae60; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .error { background: #e50914; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .movie-card { background: #1a1a1a; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 اختبار TMDB API - المسلسلات</h1>";

// اختبار جلب المسلسلات
$tv_shows = getPopularTv(1);

if (!empty($tv_shows)) {
    echo "<div class='success'>✅ تم جلب " . count($tv_shows) . " مسلسل بنجاح!</div>";
    
    foreach (array_slice($tv_shows, 0, 5) as $tv) {
        echo "<div class='movie-card'>";
        echo "<h3>📺 " . ($tv['name'] ?? 'بدون اسم') . "</h3>";
        echo "<p>🆔 TMDB ID: " . ($tv['id'] ?? 'لا يوجد') . "</p>";
        echo "<p>📅 السنة: " . (isset($tv['first_air_date']) ? substr($tv['first_air_date'], 0, 4) : 'غير معروف') . "</p>";
        echo "<p>⭐ التقييم: " . ($tv['vote_average'] ?? 0) . "</p>";
        echo "</div>";
    }
} else {
    echo "<div class='error'>❌ فشل جلب المسلسلات من TMDB</div>";
}

echo "</div></body></html>";
?>