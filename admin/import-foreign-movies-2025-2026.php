<?php
// admin/import-foreign-movies-2025-2026.php - استيراد الأفلام الأجنبية 2025-2026
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

// قائمة اللغات الأجنبية (غير العربية والتركية والهندية والآسيوية)
$foreign_languages = [
    'en' => 'إنجليزي',
    'fr' => 'فرنسي',
    'es' => 'إسباني',
    'de' => 'ألماني',
    'it' => 'إيطالي',
    'pt' => 'برتغالي',
    'ru' => 'روسي',
    'nl' => 'هولندي',
    'sv' => 'سويدي',
    'da' => 'دنماركي',
    'no' => 'نرويجي',
    'fi' => 'فنلندي',
    'pl' => 'بولندي',
    'cs' => 'تشيكي',
    'hu' => 'مجري',
    'el' => 'يوناني',
    'he' => 'عبري',
    'ar' => 'عربي', // سنستثنيه
    'tr' => 'تركي', // سنستثنيه
    'hi' => 'هندي', // سنستثنيه
    'ko' => 'كوري', // سنستثنيه
    'ja' => 'ياباني', // سنستثنيه
    'zh' => 'صيني', // سنستثنيه
    'th' => 'تايلاندي' // سنستثنيه
];

// اللغات المستثناة (التي تم استيرادها في ملفات منفصلة)
$excluded_languages = ['ar', 'tr', 'hi', 'ko', 'ja', 'zh', 'th', 'vi', 'id', 'ms', 'tl'];

// =============================================
// استيراد الأفلام الأجنبية 2025-2026
// =============================================
if (isset($_POST['import_foreign_movies_2025_2026'])) {
    $pages = (int)($_POST['pages'] ?? 10);
    $year_from = (int)($_POST['year_from'] ?? 2025);
    $year_to = (int)($_POST['year_to'] ?? 2026);
    
    echo "<!DOCTYPE html>
    <html dir='rtl' lang='ar'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>جاري استيراد الأفلام الأجنبية {$year_from}-{$year_to}...</title>
        <link href='https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap' rel='stylesheet'>
        <style>
            body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; padding: 30px; }
            .container { max-width: 900px; margin: 0 auto; background: #1a1a1a; padding: 30px; border-radius: 15px; }
            h1 { color: #1a4b8c; }
            h2 { color: #e50914; margin: 15px 0; }
            .progress { background: #252525; padding: 10px; margin: 5px 0; border-radius: 5px; border-right: 4px solid #1a4b8c; }
            .success { color: #27ae60; }
            .warning { color: #f39c12; }
            .stats { background: #0a0a0a; padding: 20px; border-radius: 10px; margin-top: 20px; }
            .movie-info { background: #252525; padding: 8px; margin: 3px 0; border-radius: 4px; font-size: 14px; border-right: 3px solid #1a4b8c; }
            .year-badge { background: #1a4b8c; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 10px; }
            .foreign-flag { color: #1a4b8c; font-weight: bold; }
            .lang-tag { background: #1a4b8c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; margin-right: 5px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1>🌍 استيراد الأفلام الأجنبية {$year_from}-{$year_to}</h1>";
    
    ob_flush();
    flush();
    
    // تحديد تاريخ البداية والنهاية
    $start_date = $year_from . '-01-01';
    $end_date = $year_to . '-12-31';
    
    echo "<h2>📅 جلب أفلام أجنبية من 1 يناير {$year_from} إلى 31 ديسمبر {$year_to}</h2>";
    ob_flush();
    flush();
    
    // قائمة اللغات الأجنبية للاستعلام (باستثناء المحتوى العربي والآسيوي)
    $foreign_language_codes = ['en', 'fr', 'es', 'de', 'it', 'pt', 'ru', 'nl', 'sv', 'da', 'no', 'fi', 'pl', 'cs', 'hu', 'el'];
    
    // جلب الأفلام الأجنبية من TMDB (إنجليزي + لغات عالمية)
    foreach ($foreign_language_codes as $lang_code) {
        $language_name = $foreign_languages[$lang_code] ?? $lang_code;
        echo "<h3 style='color: #1a4b8c; margin-top: 20px;'>🔍 جلب أفلام {$language_name}...</h3>";
        ob_flush();
        flush();
        
        for ($page = 1; $page <= $pages; $page++) {
            echo "<div class='progress'>⏳ صفحة {$page} من {$pages} - {$language_name}...</div>";
            ob_flush();
            flush();
            
            // رابط API للأفلام الأجنبية حسب اللغة
            $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
                 . "&language=ar-SA"
                 . "&with_original_language=" . $lang_code
                 . "&sort_by=release_date.desc"
                 . "&primary_release_date.gte=" . $start_date
                 . "&primary_release_date.lte=" . $end_date
                 . "&page=" . $page;
            
            $data = tmdb_request($url);
            
            if (!$data || !isset($data['results'])) {
                echo "<div class='progress warning'>⚠️ لا توجد بيانات في الصفحة {$page}</div>";
                continue;
            }
            
            if (empty($data['results'])) {
                break; // لا مزيد من النتائج لهذه اللغة
            }
            
            foreach ($data['results'] as $movie) {
                if (!isset($movie['id'])) continue;
                
                // استخراج سنة الفيلم
                $release_date = $movie['release_date'] ?? '';
                $year = !empty($release_date) ? substr($release_date, 0, 4) : $year_from;
                
                // تحقق من وجود الفيلم
                $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
                $check->execute([$movie['id']]);
                
                if (!$check->fetch()) {
                    // تجهيز البيانات
                    $title = $movie['title'] ?? 'بدون عنوان';
                    $original_title = $movie['original_title'] ?? '';
                    $description = $movie['overview'] ?? "فيلم {$language_name}";
                    $poster = isset($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
                    $backdrop = isset($movie['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'] : null;
                    $rating = $movie['vote_average'] ?? 0;
                    
                    try {
                        $sql = "INSERT INTO movies (
                            tmdb_id, title, title_en, description, poster, backdrop, 
                            year, imdb_rating, country, language, status, views
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'أجنبي', ?, 'published', 0)";
                        
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            $movie['id'],
                            $title,
                            $original_title,
                            $description,
                            $poster,
                            $backdrop,
                            $year,
                            $rating,
                            $lang_code
                        ]);
                        
                        $imported++;
                        
                        // عرض الفيلم المستورد
                        echo "<div class='movie-info'>✅ [{$year}] <span class='foreign-flag'>🌍</span> {$title} <span class='lang-tag'>{$language_name}</span> (⭐ {$rating})</div>";
                        ob_flush();
                        flush();
                        
                    } catch (Exception $e) {
                        $errors++;
                        error_log("خطأ في استيراد فيلم أجنبي: " . $e->getMessage());
                    }
                } else {
                    $skipped++;
                    $title = $movie['title'] ?? 'فيلم أجنبي';
                    echo "<div class='movie-info warning'>⏭️ [{$year}] {$title} (موجود مسبقاً)</div>";
                    ob_flush();
                    flush();
                }
            }
            
            // تأخير بين الصفحات
            usleep(500000);
        }
    }
    
    echo "<div class='stats'>";
    echo "<h2 class='success'>✅ اكتمل استيراد الأفلام الأجنبية {$year_from}-{$year_to}!</h2>";
    echo "<p>📊 أفلام أجنبية جديدة: <strong style='color: #1a4b8c;'>{$imported}</strong></p>";
    echo "<p>⏭️ أفلام موجودة مسبقاً: <strong style='color: #f39c12;'>{$skipped}</strong></p>";
    echo "<p>❌ أخطاء: <strong style='color: #e50914;'>{$errors}</strong></p>";
    echo "</div>";
    
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='import-foreign-movies-2025-2026.php' style='display: inline-block; padding: 10px 20px; background: #1a4b8c; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;'>⬅️ العودة للاستيراد</a>";
    echo "<a href='../foreign-movies.php?year={$year_from}-{$year_to}' style='display: inline-block; padding: 10px 20px; background: #e50914; color: white; text-decoration: none; border-radius: 5px;'>🌍 عرض الأفلام الأجنبية</a>";
    echo "</div>";
    
    echo "</div></body></html>";
    exit;
}

// إحصائيات الأفلام الأجنبية
$foreign_movies_2025 = $pdo->query("SELECT COUNT(*) FROM movies WHERE language NOT IN ('ar','tr','hi','ko','ja','zh','th','vi','id','ms','tl') AND year = '2025'")->fetchColumn();
$foreign_movies_2026 = $pdo->query("SELECT COUNT(*) FROM movies WHERE language NOT IN ('ar','tr','hi','ko','ja','zh','th','vi','id','ms','tl') AND year = '2026'")->fetchColumn();
$foreign_movies_total = $pdo->query("SELECT COUNT(*) FROM movies WHERE language NOT IN ('ar','tr','hi','ko','ja','zh','th','vi','id','ms','tl')")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد الأفلام الأجنبية 2025-2026 - ويزي برو</title>
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
            color: #1a4b8c;
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
            color: #1a4b8c;
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
            border: 1px solid #1a4b8c;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: #1a4b8c;
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
            border-color: #1a4b8c;
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
            background: #1a4b8c;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0e2e5c;
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
            color: #1a4b8c;
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
        
        .foreign-languages {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 20px;
        }
        
        .lang-item {
            background: #252525;
            padding: 8px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #333;
            font-size: 13px;
        }
        
        .lang-code {
            color: #1a4b8c;
            font-weight: 700;
            margin-left: 5px;
        }
        
        .year-badge {
            display: inline-block;
            background: #1a4b8c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-right: 5px;
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
            <a href="import-foreign-movies-2025-2026.php" class="nav-item active"><i class="fas fa-film" style="color: #1a4b8c;"></i> أفلام أجنبية 2025-26</a>
            <a href="import-foreign-series-2025-2026.php" class="nav-item"><i class="fas fa-tv" style="color: #1a4b8c;"></i> مسلسلات أجنبية 2025-26</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-film" style="color: #1a4b8c;"></i> استيراد الأفلام الأجنبية 2025-2026</h1>
            <div>
                <span style="color: #b3b3b3;">🌍 إجمالي الأفلام الأجنبية: <?php echo $foreign_movies_total; ?></span>
                <span style="color: #b3b3b3; margin-right: 15px;">📅 2025: <?php echo $foreign_movies_2025; ?></span>
                <span style="color: #b3b3b3; margin-right: 15px;">📅 2026: <?php echo $foreign_movies_2026; ?></span>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $foreign_movies_total; ?></div>
                <div class="stat-label">إجمالي الأفلام الأجنبية</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $foreign_movies_2025; ?></div>
                <div class="stat-label">أفلام 2025</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $foreign_movies_2026; ?></div>
                <div class="stat-label">أفلام 2026</div>
            </div>
        </div>
        
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-download"></i>
                🌍 استيراد الأفلام الأجنبية 2025-2026
            </div>
            
            <form method="POST">
                <div class="row">
                    <div class="col">
                        <div class="form-group">
                            <label>من سنة</label>
                            <select name="year_from" class="form-control">
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
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>عدد الصفحات لكل لغة (كل صفحة = 20 فيلم)</label>
                    <select name="pages" class="form-control">
                        <option value="3">60 فيلم لكل لغة</option>
                        <option value="5" selected>100 فيلم لكل لغة</option>
                        <option value="10">200 فيلم لكل لغة</option>
                        <option value="15">300 فيلم لكل لغة</option>
                    </select>
                </div>
                
                <button type="submit" name="import_foreign_movies_2025_2026" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> بدء استيراد الأفلام الأجنبية
                </button>
            </form>
            
            <div class="info-box">
                <h4>📋 معلومات عن الأفلام الأجنبية 2025-2026:</h4>
                <ul>
                    <li>• يتم استيراد الأفلام من: <strong>إنجليزي، فرنسي، إسباني، ألماني، إيطالي، برتغالي، روسي، ولغات عالمية أخرى</strong></li>
                    <li>• الأفلام الصادرة في <strong>2025 و 2026</strong> فقط</li>
                    <li>• الترتيب حسب <strong>تاريخ الإصدار (الأحدث أولاً)</strong></li>
                    <li>• الأفلام المكررة يتم تخطيها تلقائياً</li>
                    <li>• يمكن استيراد حتى <strong>300 فيلم</strong> لكل لغة</li>
                </ul>
            </div>
        </div>
        
        <div style="background: #1a1a1a; border-radius: 15px; padding: 25px; margin-top: 30px;">
            <h3 style="color: #1a4b8c; margin-bottom: 20px;">🌍 اللغات الأجنبية المدعومة</h3>
            <div class="foreign-languages">
                <div class="lang-item"><span class="lang-code">en</span> إنجليزي</div>
                <div class="lang-item"><span class="lang-code">fr</span> فرنسي</div>
                <div class="lang-item"><span class="lang-code">es</span> إسباني</div>
                <div class="lang-item"><span class="lang-code">de</span> ألماني</div>
                <div class="lang-item"><span class="lang-code">it</span> إيطالي</div>
                <div class="lang-item"><span class="lang-code">pt</span> برتغالي</div>
                <div class="lang-item"><span class="lang-code">ru</span> روسي</div>
                <div class="lang-item"><span class="lang-code">nl</span> هولندي</div>
                <div class="lang-item"><span class="lang-code">sv</span> سويدي</div>
                <div class="lang-item"><span class="lang-code">da</span> دنماركي</div>
                <div class="lang-item"><span class="lang-code">no</span> نرويجي</div>
                <div class="lang-item"><span class="lang-code">fi</span> فنلندي</div>
                <div class="lang-item"><span class="lang-code">pl</span> بولندي</div>
                <div class="lang-item"><span class="lang-code">cs</span> تشيكي</div>
                <div class="lang-item"><span class="lang-code">hu</span> مجري</div>
                <div class="lang-item"><span class="lang-code">el</span> يوناني</div>
            </div>
        </div>
    </div>
</body>
</html>