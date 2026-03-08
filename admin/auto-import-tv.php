<?php
// admin/auto-import-tv.php - استيراد تلقائي للمسلسلات - نسخة مضبوطة 100%
require_once __DIR__ . '/../includes/config.php';        // ✅ اتصال قاعدة البيانات
require_once __DIR__ . '/../includes/functions.php';     // ✅ الدوال العامة
require_once __DIR__ . '/../includes/tmdb.php';   

// منع الوصول المباشر إلا من خلال Cron Job أو AJAX
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
// بدء التوقيت
$start_time = microtime(true);
$imported = 0;
$skipped = 0;
$errors = 0;
$imported_episodes = 0;

// =============================================
// 1. اختبار API أولاً
// =============================================
$test_tv = getPopularTv(1);
if (empty($test_tv)) {
    die("❌ فشل الاتصال بـ TMDB API");
}

// =============================================
// 2. استيراد المسلسلات الرائجة - نسخة مضبوطة
// =============================================
function importPopularSeries($pages = 3) {
    global $pdo, $imported, $skipped, $errors, $imported_episodes;
    
    // جلب المسلسلات من TMDB
    $all_tv = getPopularTv($pages);
    
    if (empty($all_tv)) {
        error_log("❌ فشل جلب المسلسلات من TMDB");
        return;
    }
    
    foreach ($all_tv as $tv) {
        if (!isset($tv['id']) || !isset($tv['name'])) {
            continue;
        }
        
        // تحقق من وجود المسلسل
        $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
        $check->execute([$tv['id']]);
        
        if (!$check->fetch()) {
            // تجهيز البيانات الأساسية
            $title = $tv['name'] ?? 'بدون عنوان';
            $description = $tv['overview'] ?? 'لا يوجد وصف';
            $poster = isset($tv['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $tv['poster_path'] : null;
            $backdrop = isset($tv['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $tv['backdrop_path'] : null;
            $year = isset($tv['first_air_date']) ? substr($tv['first_air_date'], 0, 4) : date('Y');
            $rating = $tv['vote_average'] ?? 0;
            
            try {
                $sql = "INSERT INTO series (
                    tmdb_id, title, description, poster, backdrop, year, imdb_rating, seasons, status, views
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'ongoing', 0)";
                
                $stmt = $pdo->prepare($sql);
                $result = $stmt->execute([
                    $tv['id'],
                    $title,
                    $description,
                    $poster,
                    $backdrop,
                    $year,
                    $rating
                ]);
                
                if ($result) {
                    $imported++;
                    $series_id = $pdo->lastInsertId();
                    
                    // استيراد الموسم الأول فقط
                    $season_data = getTvSeasons($tv['id'], 1);
                    if ($season_data && isset($season_data['episodes'])) {
                        foreach ($season_data['episodes'] as $episode) {
                            $ep_title = $episode['name'] ?? 'حلقة ' . $episode['episode_number'];
                            $ep_description = $episode['overview'] ?? '';
                            $ep_duration = $episode['runtime'] ?? 45;
                            
                            $ep_sql = "INSERT INTO episodes (
                                series_id, season_number, episode_number, title, description, duration, views
                            ) VALUES (?, ?, ?, ?, ?, ?, 0)";
                            
                            $ep_stmt = $pdo->prepare($ep_sql);
                            $ep_stmt->execute([
                                $series_id,
                                1,
                                $episode['episode_number'],
                                $ep_title,
                                $ep_description,
                                $ep_duration
                            ]);
                            
                            $imported_episodes++;
                        }
                    }
                    
                    // تأخير بسيط
                    usleep(500000);
                }
            } catch (Exception $e) {
                $errors++;
                error_log("Auto import error: " . $e->getMessage());
            }
        } else {
            $skipped++;
        }
    }
}

// =============================================
// 3. استيراد المسلسلات من الصفحات المختلفة
// =============================================
$pages_to_import = 3; // 3 صفحات = 60 مسلسل

// جلب الإعدادات من ملف إذا موجود
$settings_file = __DIR__ . '/../cache/auto-import-settings.json';
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    $pages_to_import = $settings['auto_import_pages'] ?? 3;
}

// تنفيذ الاستيراد
importPopularSeries($pages_to_import);

// =============================================
// 4. تحديث المسلسلات الموجودة (اختياري)
// =============================================
if (isset($_GET['update']) || $imported > 0) {
    // جلب المسلسلات التي ليس لها حلقات
    $stmt = $pdo->prepare("
        SELECT s.id, s.tmdb_id 
        FROM series s 
        LEFT JOIN episodes e ON s.id = e.series_id 
        WHERE s.tmdb_id IS NOT NULL AND e.id IS NULL
        LIMIT 5
    ");
    $stmt->execute();
    $series_to_update = $stmt->fetchAll();
    
    foreach ($series_to_update as $series) {
        $season_data = getTvSeasons($series['tmdb_id'], 1);
        if ($season_data && isset($season_data['episodes'])) {
            foreach ($season_data['episodes'] as $episode) {
                $check_ep = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
                $check_ep->execute([$series['id'], 1, $episode['episode_number']]);
                
                if (!$check_ep->fetch()) {
                    $ep_sql = "INSERT INTO episodes (
                        series_id, season_number, episode_number, title, description, duration, views
                    ) VALUES (?, ?, ?, ?, ?, ?, 0)";
                    
                    $ep_stmt = $pdo->prepare($ep_sql);
                    $ep_stmt->execute([
                        $series['id'],
                        1,
                        $episode['episode_number'],
                        $episode['name'] ?? 'حلقة ' . $episode['episode_number'],
                        $episode['overview'] ?? '',
                        $episode['runtime'] ?? 45
                    ]);
                    
                    $imported_episodes++;
                }
            }
        }
        usleep(500000);
    }
}

// =============================================
// 5. تسجيل النتائج
// =============================================
$end_time = microtime(true);
$execution_time = round($end_time - $start_time, 2);

// إنشاء مجلد logs إذا لم يكن موجوداً
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0777, true);
}

// تسجيل في ملف Log
$log_entry = date('Y-m-d H:i:s') . " | استيراد: {$imported} مسلسل | حلقات: {$imported_episodes} | تجاوز: {$skipped} | أخطاء: {$errors} | وقت: {$execution_time}ث\n";
file_put_contents(__DIR__ . '/../logs/auto-import.log', $log_entry, FILE_APPEND);

// =============================================
// 6. عرض النتيجة
// =============================================
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاستيراد التلقائي - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
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
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat {
            background: #252525;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
        }
        .stat-label {
            color: #b3b3b3;
            margin-top: 5px;
        }
        .success {
            background: #27ae60;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .warning {
            background: #f39c12;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .log {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 8px;
            color: #b3b3b3;
            font-family: monospace;
            max-height: 200px;
            overflow-y: auto;
        }
        .btn {
            display: inline-block;
            background: #e50914;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            margin-left: 10px;
        }
        .btn-success {
            background: #27ae60;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-robot"></i> الاستيراد التلقائي للمسلسلات</h1>
        
        <?php if ($imported > 0 || $imported_episodes > 0): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                ✅ تم استيراد <strong><?php echo $imported; ?></strong> مسلسل و <strong><?php echo $imported_episodes; ?></strong> حلقة جديدة
            </div>
        <?php else: ?>
            <div class="warning">
                <i class="fas fa-info-circle"></i>
                ⚠️ لا توجد مسلسلات جديدة للاستيراد. تم تجاوز <strong><?php echo $skipped; ?></strong> مسلسل موجود مسبقاً.
                <br>
                <small style="display: block; margin-top: 10px;">
                    هل جربت استيراد مسلسل يدوياً من صفحة <a href="import-tv.php" style="color: white; text-decoration: underline;">import-tv.php</a>؟
                </small>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            <div class="stat">
                <div class="stat-number"><?php echo $imported; ?></div>
                <div class="stat-label">مسلسلات جديدة</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $imported_episodes; ?></div>
                <div class="stat-label">حلقات جديدة</div>
            </div>
            <div class="stat">
                <div class="stat-number"><?php echo $execution_time; ?>ث</div>
                <div class="stat-label">وقت التنفيذ</div>
            </div>
        </div>
        
        <div class="log">
            <strong style="color: #e50914;">📋 آخر تشغيل:</strong><br>
            <?php echo date('Y-m-d H:i:s'); ?><br><br>
            
            <strong style="color: #e50914;">📊 إحصائيات:</strong><br>
            المسلسلات في قاعدة البيانات: <?php echo $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(); ?><br>
            الحلقات في قاعدة البيانات: <?php echo $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(); ?><br><br>
            
            <strong style="color: #e50914;">📜 السجلات السابقة:</strong><br>
            <?php
            $logs = file_exists(__DIR__ . '/../logs/auto-import.log') 
                ? file_get_contents(__DIR__ . '/../logs/auto-import.log') 
                : 'لا توجد سجلات بعد';
            echo nl2br($logs);
            ?>
        </div>
        
        <div style="margin-top: 30px;">
            <a href="auto-import-tv.php?force=1" class="btn">
                <i class="fas fa-sync"></i> تشغيل الاستيراد الآن
            </a>
            <a href="import-tv.php" class="btn" style="background: #2a2a2a;">
                <i class="fas fa-tv"></i> استيراد يدوي
            </a>
            <a href="dashboard.php" class="btn" style="background: #2a2a2a;">
                <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
            </a>
        </div>
    </div>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</body>
</html>