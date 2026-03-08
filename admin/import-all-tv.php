<?php
// admin/import-all-tv.php - استيراد جميع المسلسلات من TMDB
require_once __DIR__ . '/../includes/config.php';        // ✅ اتصال قاعدة البيانات
require_once __DIR__ . '/../includes/functions.php';     // ✅ الدوال العامة (فيها isLoggedIn)
require_once __DIR__ . '/../includes/tmdb.php'; 

if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';
$imported = 0;
$skipped = 0;
$errors = 0;
$imported_episodes = 0;

// =============================================
// استيراد جميع المسلسلات دفعة واحدة
// =============================================
if (isset($_GET['import_all']) && $_GET['import_all'] == '1') {
    
    // كم صفحة تريد استيرادها؟ (كل صفحة = 20 مسلسل)
    $max_pages = isset($_GET['pages']) ? (int)$_GET['pages'] : 100;
    $import_type = isset($_GET['type']) ? $_GET['type'] : 'popular';
    
    // بدء التوقيت
    $start_time = microtime(true);
    
    // جلب المسلسلات حسب النوع
    switch ($import_type) {
        case 'popular':
            $all_tv = getAllPopularTv($max_pages);
            $type_name = 'الرائجة';
            break;
        case 'top_rated':
            $all_tv = getTopRatedTv($max_pages);
            $type_name = 'الأعلى تقييماً';
            break;
        default:
            $all_tv = getAllPopularTv($max_pages);
            $type_name = 'الرائجة';
    }
    
    $total = count($all_tv);
    
    // استيراد كل مسلسل
    foreach ($all_tv as $tv) {
        if (!isset($tv['id']) || !isset($tv['name'])) continue;
        
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
                $stmt->execute([
                    $tv['id'],
                    $title,
                    $description,
                    $poster,
                    $backdrop,
                    $year,
                    $rating
                ]);
                $imported++;
                
                // استيراد الموسم الأول (اختياري)
                if (isset($_GET['import_episodes'])) {
                    $series_id = $pdo->lastInsertId();
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
                    usleep(500000);
                }
                
            } catch (Exception $e) {
                $errors++;
                error_log("Import error: " . $e->getMessage());
            }
        } else {
            $skipped++;
        }
        
        // تأخير بسيط كل 10 مسلسلات
        if ($imported % 10 == 0) {
            usleep(1000000); // 1 ثانية
        }
    }
    
    $end_time = microtime(true);
    $execution_time = round($end_time - $start_time, 2);
    
    $message = "✅ تم استيراد {$imported} مسلسل {$type_name} جديد، وتخطي {$skipped} مسلسل موجود، وقت التنفيذ: {$execution_time} ثانية";
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد جميع المسلسلات - ويزي برو</title>
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
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .message {
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .error {
            background: #e50914;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .import-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }
        
        .import-title {
            color: #e50914;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 25px;
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
            border-color: #e50914;
            outline: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .btn {
            background: #e50914;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn:hover {
            background: #b20710;
            transform: translateY(-2px);
        }
        
        .btn-large {
            font-size: 20px;
            padding: 20px 50px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #333;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
        }
        
        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
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
            <a href="import-all-tv.php" class="nav-item active"><i class="fas fa-database"></i> استيراد الكل</a>
            <a href="auto-import-settings.php" class="nav-item"><i class="fas fa-robot"></i> استيراد تلقائي</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-database"></i> استيراد جميع المسلسلات</h1>
            <div>📺 إجمالي المسلسلات: <strong style="color: #e50914;"><?php echo $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(); ?></strong></div>
        </div>
        
        <?php if ($message): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="import-card">
            <h2 class="import-title"><i class="fas fa-rocket"></i> استيراد آلاف المسلسلات دفعة واحدة</h2>
            
            <form method="GET" action="">
                <div class="form-group">
                    <label>نوع المسلسلات المراد استيرادها</label>
                    <select name="type" class="form-control">
                        <option value="popular">📺 المسلسلات الرائجة (Popular)</option>
                        <option value="top_rated">⭐ الأعلى تقييماً (Top Rated)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 مسلسل)</label>
                    <select name="pages" class="form-control">
                        <option value="10">200 مسلسل (سريع)</option>
                        <option value="25">500 مسلسل</option>
                        <option value="50" selected>1000 مسلسل (موصى به)</option>
                        <option value="100">2000 مسلسل</option>
                        <option value="250">5000 مسلسل</option>
                        <option value="500">10000 مسلسل (قد يستغرق وقتاً)</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="import_episodes" id="import_episodes" value="1">
                    <label for="import_episodes">استيراد الموسم الأول من الحلقات (قد يبطئ العملية)</label>
                </div>
                
                <button type="submit" name="import_all" value="1" class="btn btn-large" onclick="return confirm('⚠️ هل أنت متأكد؟ هذا سيستورد آلاف المسلسلات وقد يستغرق عدة دقائق')">
                    <i class="fas fa-download"></i> بدء الاستيراد الشامل
                </button>
            </form>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #333; color: #b3b3b3;">
                <p><i class="fas fa-info-circle"></i> معلومات مهمة:</p>
                <ul style="margin-right: 30px; margin-top: 10px;">
                    <li>⏱️ استيراد 1000 مسلسل يستغرق حوالي 2-3 دقائق</li>
                    <li>📦 المسلسلات المستوردة لا تحتوي على حلقات (للحلقات استخدم زر استيراد الموسم الأول)</li>
                    <li>🔄 يمكنك تشغيل الاستيراد أكثر من مرة - المسلسلات المكررة تتخطى تلقائياً</li>
                    <li>🎬 TMDB يحتوي على أكثر من 10,000 مسلسل</li>
                </ul>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(); ?></div>
                <div class="stat-label">مسلسل في قاعدة البيانات</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">10,000+</div>
                <div class="stat-label">مسلسل في TMDB</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(); ?></div>
                <div class="stat-label">حلقات مستوردة</div>
            </div>
        </div>
    </div>
</body>
</html>