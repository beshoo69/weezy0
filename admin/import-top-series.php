<?php
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

// دالة لجلب أفضل المسلسلات من TMDB
function getTopRatedSeries($pages = 3) {
    $all_series = [];
    
    for ($page = 1; $page <= $pages; $page++) {
        $url = "https://api.themoviedb.org/3/tv/top_rated?api_key=" . TMDB_API_KEY . "&language=en-US&page=" . $page;
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            foreach ($data['results'] as $series) {
                // جلب تفاصيل إضافية لكل مسلسل
                $details_url = "https://api.themoviedb.org/3/tv/" . $series['id'] . "?api_key=" . TMDB_API_KEY . "&language=en-US";
                $details = tmdb_request($details_url);
                
                if ($details) {
                    // تحديد بلد الإنتاج (أول دولة في القائمة)
                    $country = isset($details['origin_country'][0]) ? $details['origin_country'][0] : 'US';
                    
                    // تحويل رمز الدولة إلى اسم عربي
                    $country_name = getCountryName($country);
                    
                    $all_series[] = [
                        'id' => $series['id'],
                        'title' => $series['name'],
                        'year' => isset($series['first_air_date']) ? substr($series['first_air_date'], 0, 4) : '2000',
                        'rating' => round($series['vote_average'], 1),
                        'vote_count' => $series['vote_count'],
                        'country' => $country_name,
                        'overview' => $series['overview'] ?? '',
                        'poster' => $series['poster_path'] ?? null,
                        'backdrop' => $series['backdrop_path'] ?? null
                    ];
                }
            }
        }
        // تأخير بسيط بين الطلبات
        usleep(500000);
    }
    
    // ترتيب حسب التقييم (الأعلى أولاً)
    usort($all_series, function($a, $b) {
        return $b['rating'] <=> $a['rating'];
    });
    
    return $all_series;
}

// دالة لتحويل رمز الدولة إلى اسم عربي
function getCountryName($country_code) {
    $countries = [
        'US' => 'الولايات المتحدة',
        'GB' => 'المملكة المتحدة',
        'JP' => 'اليابان',
        'KR' => 'كوريا الجنوبية',
        'FR' => 'فرنسا',
        'DE' => 'ألمانيا',
        'IT' => 'إيطاليا',
        'ES' => 'إسبانيا',
        'CA' => 'كندا',
        'AU' => 'أستراليا',
        'IN' => 'الهند',
        'CN' => 'الصين',
        'RU' => 'روسيا',
        'BR' => 'البرازيل',
        'MX' => 'المكسيك',
        'SE' => 'السويد',
        'DK' => 'الدنمارك',
        'NO' => 'النرويج',
        'FI' => 'فنلندا',
        'NL' => 'هولندا',
        'BE' => 'بلجيكا',
        'CH' => 'سويسرا',
        'AT' => 'النمسا',
        'IE' => 'أيرلندا',
        'NZ' => 'نيوزيلندا',
        'ZA' => 'جنوب أفريقيا',
        'EG' => 'مصر',
        'SA' => 'السعودية',
        'AE' => 'الإمارات',
        'IL' => 'إسرائيل',
        'TR' => 'تركيا',
        'IR' => 'إيران'
    ];
    
    return $countries[$country_code] ?? $country_code;
}

// معالجة الاستيراد
if (isset($_POST['import_top_series']) || isset($_GET['action']) && $_GET['action'] == 'import') {
    
    // عدد الصفحات المطلوب جلبها (كل صفحة 20 مسلسل)
    $pages = isset($_POST['pages']) ? intval($_POST['pages']) : 3;
    $min_votes = isset($_POST['min_votes']) ? intval($_POST['min_votes']) : 1000;
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد أفضل المسلسلات...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: gold; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid gold; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .imdb-badge { background: gold; color: black; padding: 2px 8px; border-radius: 4px; margin-left: 10px; font-weight: 700; }
            .details { color: #b3b3b3; font-size: 13px; margin-top: 3px; }
            .votes { color: #7f8c8d; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>⭐ استيراد أفضل المسلسلات من TMDB</h1>";
    
    ob_flush();
    flush();
    
    echo "<div class='progress'>🔍 جاري جلب قائمة أفضل المسلسلات من TMDB...</div>";
    ob_flush();
    flush();
    
    // جلب أفضل المسلسلات
    $top_series = getTopRatedSeries($pages);
    
    // تصفية المسلسلات التي لها عدد تصويت كافٍ
    $top_series = array_filter($top_series, function($series) use ($min_votes) {
        return $series['vote_count'] >= $min_votes;
    });
    
    echo "<div class='progress'>✅ تم العثور على " . count($top_series) . " مسلسل عالي التقييم</div>";
    ob_flush();
    flush();
    
    $imported = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($top_series as $series) {
        echo "<div class='progress'>⏳ جاري استيراد {$series['title']} ({$series['year']})...</div>";
        ob_flush();
        flush();
        
        // تحقق من وجود المسلسل
        $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ? OR title LIKE ? OR title_en LIKE ?");
        $search = "%{$series['title']}%";
        $check->execute([$series['id'], $search, $search]);
        
        if (!$check->fetch()) {
            try {
                // جلب تفاصيل كاملة من TMDB
                $details_url = "https://api.themoviedb.org/3/tv/" . $series['id'] . "?api_key=" . TMDB_API_KEY . "&language=ar-SA";
                $details = tmdb_request($details_url);
                
                $poster = $series['poster'] ? 'https://image.tmdb.org/t/p/w500' . $series['poster'] : null;
                $backdrop = $series['backdrop'] ? 'https://image.tmdb.org/t/p/original' . $series['backdrop'] : null;
                $description = $series['overview'] ?: "مسلسل {$series['country']} حاصل على تقييم {$series['rating']} في IMDb.";
                $seasons = $details['number_of_seasons'] ?? 1;
                
                // حالة المسلسل
                $status = 'ongoing';
                if (isset($details['status'])) {
                    if ($details['status'] == 'Ended') $status = 'completed';
                    if ($details['status'] == 'Canceled') $status = 'cancelled';
                }
                
                $sql = "INSERT INTO series (
                    tmdb_id, title, title_en, description, poster, backdrop, 
                    year, seasons, imdb_rating, country, language, status, views
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'en', ?, 0)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $series['id'],
                    $series['title'],
                    $series['title'],
                    $description,
                    $poster,
                    $backdrop,
                    $series['year'],
                    $seasons,
                    $series['rating'],
                    $series['country'],
                    $status
                ]);
                
                $series_id = $pdo->lastInsertId();
                $imported++;
                
                echo "<div class='progress success'>✅ تم استيراد: {$series['title']} <span class='imdb-badge'>⭐ {$series['rating']}</span> <span class='votes'>({$series['vote_count']} صوت)</span> - {$seasons} مواسم</div>";
                
                // استيراد الحلقات (كل المواسم) إذا طُلب ذلك
                if (isset($_POST['import_episodes']) && $seasons > 0) {
                    echo "<div class='details'>⏳ جاري استيراد حلقات كل المواسم...</div>";
                    ob_flush();
                    flush();

                    $total_eps = 0;
                    for ($s = 1; $s <= $seasons; $s++) {
                        $season_url = "https://api.themoviedb.org/3/tv/" . $series['id'] . "/season/{$s}?api_key=" . TMDB_API_KEY . "&language=ar-SA";
                        $season_data = tmdb_request($season_url);
                        if ($season_data && isset($season_data['episodes'])) {
                            foreach ($season_data['episodes'] as $episode) {
                                $ep_sql = "INSERT INTO episodes (
                                    series_id, season_number, episode_number, title, description, duration, views
                                ) VALUES (?, ?, ?, ?, ?, ?, 0)";

                                $ep_stmt = $pdo->prepare($ep_sql);
                                $ep_stmt->execute([
                                    $series_id,
                                    $s,
                                    $episode['episode_number'],
                                    $episode['name'] ?? 'حلقة ' . $episode['episode_number'],
                                    $episode['overview'] ?? '',
                                    $episode['runtime'] ?? 45
                                ]);
                            }
                            $count = count($season_data['episodes']);
                            $total_eps += $count;
                            echo "<div class='details'>✅ الموسم {$s}: تم استيراد {$count} حلقة</div>";
                        }
                        usleep(500000);
                    }
                    echo "<div class='details'>✅ تم استيراد إجمالي {$total_eps} حلقة من {$seasons} مواسم.</div>";
                }
                
            } catch (Exception $e) {
                $errors++;
                echo "<div class='progress warning'>❌ خطأ في استيراد: {$series['title']} - " . $e->getMessage() . "</div>";
            }
        } else {
            $skipped++;
            echo "<div class='progress warning'>⏭️ موجود مسبقاً: {$series['title']}</div>";
        }
        
        usleep(300000); // تأخير بين كل عملية استيراد
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل الاستيراد!</h2>";
    echo "<p>📊 مسلسلات جديدة: <strong style='color: gold;'>{$imported}</strong></p>";
    echo "<p>⏭️ مسلسلات موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e74c3c;'>{$errors}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px; display: flex; gap: 10px; flex-wrap: wrap;'>";
    echo "<a href='import-top-series.php' style='display: inline-block; padding: 10px 20px; background: gold; color: black; text-decoration: none; border-radius: 5px; font-weight: 700;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../index.php#recommended' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🏠 عرض الصفحة الرئيسية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات المسلسلات عالية التقييم
$high_rated_count = $pdo->query("SELECT COUNT(*) FROM series WHERE imdb_rating >= 9.0")->fetchColumn();
if (!$high_rated_count) $high_rated_count = 0;
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد أفضل المسلسلات - ويزي برو</title>
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
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #2ecc71;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            background: #333;
            border: 1px solid #444;
            border-radius: 6px;
            color: white;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: gold;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            width: 20px;
            height: 20px;
        }
        
        .info-text {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 5px;
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
            <a href="import-top-series.php" class="nav-item active"><i class="fas fa-star" style="color: gold;"></i> أفضل المسلسلات</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-star" style="color: gold;"></i> أفضل المسلسلات عالية التقييم</h1>
            <div>
                <span style="color: #b3b3b3;">⭐ مسلسلات 9.0+: <?php echo $high_rated_count; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $high_rated_count; ?></div>
                <div class="stat-label">مسلسل بتقييم 9.0+</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">TMDB</div>
                <div class="stat-label">مصدر البيانات</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">تلقائي</div>
                <div class="stat-label">استيراد آلي</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                ⭐ استيراد أفضل المسلسلات من TMDB
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label><i class="fas fa-file"></i> عدد الصفحات (كل صفحة = 20 مسلسل)</label>
                    <select name="pages" class="form-control">
                        <option value="1">صفحة واحدة (أفضل 20 مسلسل)</option>
                        <option value="2" selected>صفحتين (أفضل 40 مسلسل) - موصى به</option>
                        <option value="3">3 صفحات (أفضل 60 مسلسل)</option>
                        <option value="4">4 صفحات (أفضل 80 مسلسل)</option>
                        <option value="5">5 صفحات (أفضل 100 مسلسل)</option>
                    </select>
                    <div class="info-text">كلما زاد عدد الصفحات، زاد وقت الاستيراد</div>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-vote-yea"></i> الحد  الأدنى لعدد الأصوات</label>
                    <select name="min_votes" class="form-control">
                        <option value="500">500 صوت - يشمل مسلسلات أقل شهرة</option>
                        <option value="1000" selected>1000 صوت - موصى به</option>
                        <option value="2000">2000 صوت - مسلسلات مشهورة فقط</option>
                        <option value="5000">5000 صوت - كلاسيكيات فقط</option>
                    </select>
                    <div class="info-text">عدد الأصوات الأعلى يعني مسلسلات أكثر شهرة وموثوقية</div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="import_episodes" id="import_episodes" checked>
                    <label for="import_episodes">استيراد حلقات الموسم الأول</label>
                </div>
                
                <button type="submit" name="import_top_series" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> استيراد أفضل المسلسلات تلقائياً
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن الاستيراد التلقائي:</h4>
                <ul style="list-style: none; padding-right: 0;">
                    <li><i class="fas fa-check-circle" style="color: gold;"></i> يتم جلب المسلسلات تلقائياً من TMDB (نفس بيانات IMDB)</li>
                    <li><i class="fas fa-check-circle" style="color: gold;"></i> يتم ترتيبها حسب التقييم العالمي</li>
                    <li><i class="fas fa-check-circle" style="color: gold;"></i> يتم تصفية المسلسلات بعدد أصوات كافٍ للدقة</li>
                    <li><i class="fas fa-check-circle" style="color: gold;"></i> يتم استيراد الصور والتفاصيل كاملة</li>
                    <li><i class="fas fa-check-circle" style="color: gold;"></i> يمكن استيراد الحلقات أيضاً</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>