<?php
// admin/fix-all-posters.php - إصلاح جميع صور البوستر دفعة واحدة
ini_set('max_execution_time', 1200);
ini_set('memory_limit', '512M');
set_time_limit(1200);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

// جلب جميع المسلسلات
$series_list = $pdo->query("SELECT id, title, tmdb_id, poster FROM series ORDER BY id")->fetchAll();
$total = count($series_list);
$fixed = 0;
$skipped = 0;

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>إصلاح جميع البوسترات - ويزي برو</title>
    <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
        .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
        h1 { color: #e50914; }
        .progress { background: #252525; padding: 5px; margin: 2px 0; border-radius: 3px; }
        .success { color: #27ae60; }
        .warning { color: #f39c12; }
        .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
        .progress-bar { height: 20px; background: #333; border-radius: 10px; margin: 20px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: #e50914; width: 0%; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔄 إصلاح جميع صور البوستر</h1>
        <p>إجمالي المسلسلات: {$total}</p>
        
        <div class='progress-bar'>
            <div class='progress-fill' id='progressFill'></div>
        </div>
        
        <div id='log'></div>";

ob_flush();
flush();

$processed = 0;
foreach ($series_list as $series) {
    $processed++;
    $percent = round(($processed / $total) * 100);
    
    echo "<script>
        document.getElementById('progressFill').style.width = '{$percent}%';
    </script>";
    
    // التحقق من وجود صورة صحيحة
    $needs_fix = false;
    
    if (empty($series['poster']) || 
        strpos($series['poster'], 'placeholder') !== false ||
        !strpos($series['poster'], 'image.tmdb.org')) {
        $needs_fix = true;
    }
    
    if ($needs_fix) {
        echo "<div class='progress'>⏳ معالجة: {$series['title']}...</div>";
        ob_flush();
        flush();
        
        // محاولة جلب صورة جديدة
        $poster_url = null;
        
        if ($series['tmdb_id']) {
            $details = tmdb_request("https://api.themoviedb.org/3/tv/" . $series['tmdb_id'] . "?api_key=" . TMDB_API_KEY);
            if ($details && isset($details['poster_path'])) {
                $poster_url = 'https://image.tmdb.org/t/p/w500' . $details['poster_path'];
            }
        }
        
        if (!$poster_url) {
            $search = tmdb_request("https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY . "&query=" . urlencode($series['title']));
            if ($search && isset($search['results'][0]['poster_path'])) {
                $poster_url = 'https://image.tmdb.org/t/p/w500' . $search['results'][0]['poster_path'];
            }
        }
        
        if ($poster_url) {
            $pdo->prepare("UPDATE series SET poster = ? WHERE id = ?")->execute([$poster_url, $series['id']]);
            $fixed++;
            echo "<div class='progress success'>✅ تم إصلاح: {$series['title']}</div>";
        } else {
            $skipped++;
            echo "<div class='progress warning'>⚠️ لا توجد صورة لـ: {$series['title']}</div>";
        }
    } else {
        $skipped++;
    }
    
    usleep(250000);
}

echo "<div class='stats'>";
echo "<h2 style='color: #e50914;'>📊 النتائج</h2>";
echo "<p>✅ تم إصلاح: <strong style='color: #27ae60;'>{$fixed}</strong> بوستر</p>";
echo "<p>⏭️ لم تحتاج إصلاح: <strong style='color: #f39c12;'>{$skipped}</strong> مسلسل</p>";
echo "</div>";

echo "<div style='margin-top:20px;'>";
echo "<a href='../index.php' style='display:inline-block; padding:10px 20px; background:#e50914; color:white; text-decoration:none; border-radius:5px;'>🏠 العودة للرئيسية</a>";
echo "</div>";

echo "</div></body></html>";
?>