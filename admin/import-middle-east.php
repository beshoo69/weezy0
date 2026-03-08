<?php
// admin/import-middle-east.php - استيراد الأفلام والمسلسلات من الدول العربية
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';  // ✅ تم تعديل المسار (إذا كان الملف في includes/)

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$imported_movies = 0;
$imported_tv = 0;

// قائمة الدول العربية
$middle_east_countries = [
    'EG' => 'مصر',
    'SA' => 'السعودية',
    'LB' => 'لبنان',
    'SY' => 'سوريا',
    'AE' => 'الإمارات',
    'KW' => 'الكويت',
    'MA' => 'المغرب',
    'TN' => 'تونس',
    'DZ' => 'الجزائر',
    'IQ' => 'العراق',
    'JO' => 'الأردن',
    'PS' => 'فلسطين',
    'YE' => 'اليمن',
    'OM' => 'عمان',
    'QA' => 'قطر',
    'BH' => 'البحرين'
];

// استيراد أفلام من الدول العربية
if (isset($_POST['import_middle_east_movies'])) {
    $pages = (int)($_POST['pages'] ?? 3);
    $country = $_POST['country'] ?? null;
    
    if ($country && $country != 'all') {
        $result = getMoviesByArabCountry($country, 1);
        $movies = $result['movies'];
        $country_name = $result['country_name'];
    } else {
        $movies = getArabicMovies($pages);
        $country_name = 'جميع الدول العربية';
    }
    
    foreach ($movies as $movie) {
        if (!isset($movie['id'])) continue;
        
        $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
        $check->execute([$movie['id']]);
        
        if (!$check->fetch()) {
            $title = $movie['title'] ?? 'بدون عنوان';
            $description = $movie['overview'] ?? 'فيلم عربي';
            $poster = isset($movie['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'] : null;
            $backdrop = isset($movie['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $movie['backdrop_path'] : null;
            $year = isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : date('Y');
            $rating = $movie['vote_average'] ?? 0;
            $country_code = $movie['country'] ?? $country ?? 'AR';
            $country_name_db = $middle_east_countries[$country_code] ?? $country_code;
            
            $sql = "INSERT INTO movies (tmdb_id, title, description, poster, backdrop, year, imdb_rating, country, language, status, views) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ar', 'published', 0)";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $movie['id'],
                    $title,
                    $description,
                    $poster,
                    $backdrop,
                    $year,
                    $rating,
                    $country_name_db
                ]);
                $imported_movies++;
            } catch (Exception $e) {
                error_log("Error importing movie: " . $e->getMessage());
            }
        }
    }
    
    $message = "✅ تم استيراد {$imported_movies} فيلم من {$country_name}";
}

// استيراد مسلسلات من الدول العربية
if (isset($_POST['import_middle_east_tv'])) {
    $pages = (int)($_POST['pages'] ?? 3);
    $country = $_POST['country'] ?? null;
    
    if ($country && $country != 'all') {
        $result = getTvShowsByArabCountry($country, 1);
        $tv_shows = $result['tv_shows'];
        $country_name = $result['country_name'];
    } else {
        $tv_shows = getArabicTvShows($pages);
        $country_name = 'جميع الدول العربية';
    }
    
    foreach ($tv_shows as $tv) {
        if (!isset($tv['id'])) continue;
        
        $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
        $check->execute([$tv['id']]);
        
        if (!$check->fetch()) {
            $title = $tv['name'] ?? 'بدون عنوان';
            $description = $tv['overview'] ?? 'مسلسل عربي';
            $poster = isset($tv['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $tv['poster_path'] : null;
            $backdrop = isset($tv['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $tv['backdrop_path'] : null;
            $year = isset($tv['first_air_date']) ? substr($tv['first_air_date'], 0, 4) : date('Y');
            $rating = $tv['vote_average'] ?? 0;
            $country_code = $tv['country'] ?? $country ?? 'AR';
            $country_name_db = $middle_east_countries[$country_code] ?? $country_code;
            
            $sql = "INSERT INTO series (tmdb_id, title, description, poster, backdrop, year, imdb_rating, country, language, status, seasons, views) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ar', 'ongoing', 1, 0)";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $tv['id'],
                    $title,
                    $description,
                    $poster,
                    $backdrop,
                    $year,
                    $rating,
                    $country_name_db
                ]);
                $imported_tv++;
            } catch (Exception $e) {
                error_log("Error importing TV show: " . $e->getMessage());
            }
        }
    }
    
    $message = "✅ تم استيراد {$imported_tv} مسلسل من {$country_name}";
}

// إحصائيات
$middle_east_movies = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'ar' OR country IN ('مصر','السعودية','لبنان','سوريا','الإمارات','الكويت','المغرب','تونس','الجزائر','العراق','الأردن','فلسطين','اليمن','عمان','قطر','البحرين')")->fetchColumn();
$middle_east_series = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'ar' OR country IN ('مصر','السعودية','لبنان','سوريا','الإمارات','الكويت','المغرب','تونس','الجزائر','العراق','الأردن','فلسطين','اليمن','عمان','قطر','البحرين')")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استيراد أفلام ومسلسلات الدول العربية - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* التنسيقات كما هي */
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
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(229,9,20,0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: #e50914;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #e50914;
            line-height: 1;
        }
        
        .stat-label {
            color: #b3b3b3;
            margin-top: 5px;
        }
        
        .message {
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .import-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            color: #e50914;
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
            border-color: #e50914;
            outline: none;
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
            background: #e50914;
            color: white;
        }
        
        .btn-primary:hover {
            background: #b20710;
            transform: translateY(-2px);
        }
        
        .btn-large {
            padding: 15px 40px;
            font-size: 18px;
        }
        
        .countries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .country-item {
            background: #252525;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .country-flag {
            color: #e50914;
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
            <a href="movies/import-tmdb.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد أفلام</a>
            <a href="series/import-tv.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد مسلسلات</a>
            <a href="import-middle-east.php" class="nav-item active"><i class="fas fa-map-marker-alt"></i> أفلام ومسلسلات عربية</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-map-marker-alt"></i> أفلام ومسلسلات عربية</h1>
            <div>
                <span style="color: #b3b3b3;"><i class="fas fa-film"></i> أفلام: <?php echo $middle_east_movies; ?></span>
                <span style="color: #b3b3b3; margin-right: 20px;"><i class="fas fa-tv"></i> مسلسلات: <?php echo $middle_east_series; ?></span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-film"></i></div>
                <div>
                    <div class="stat-number"><?php echo $middle_east_movies; ?></div>
                    <div class="stat-label">فيلم عربي</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-tv"></i></div>
                <div>
                    <div class="stat-number"><?php echo $middle_east_series; ?></div>
                    <div class="stat-label">مسلسل عربي</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-flag"></i></div>
                <div>
                    <div class="stat-number">16</div>
                    <div class="stat-label">دولة عربية</div>
                </div>
            </div>
        </div>
        
        <!-- استيراد أفلام عربية -->
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-film"></i>
                استيراد أفلام من الدول العربية
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>اختر الدولة</label>
                    <select name="country" class="form-control">
                        <option value="all">🌍 جميع الدول العربية</option>
                        <?php foreach ($middle_east_countries as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?> (<?php echo $code; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 فيلم)</label>
                    <select name="pages" class="form-control">
                        <option value="1">20 فيلم</option>
                        <option value="2">40 فيلم</option>
                        <option value="3" selected>60 فيلم</option>
                        <option value="5">100 فيلم</option>
                        <option value="10">200 فيلم</option>
                    </select>
                </div>
                
                <button type="submit" name="import_middle_east_movies" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> استيراد أفلام
                </button>
            </form>
        </div>
        
        <!-- استيراد مسلسلات عربية -->
        <div class="import-card">
            <div class="card-header">
                <i class="fas fa-tv"></i>
                استيراد مسلسلات من الدول العربية
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label>اختر الدولة</label>
                    <select name="country" class="form-control">
                        <option value="all">🌍 جميع الدول العربية</option>
                        <?php foreach ($middle_east_countries as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?> (<?php echo $code; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>عدد الصفحات (كل صفحة = 20 مسلسل)</label>
                    <select name="pages" class="form-control">
                        <option value="1">20 مسلسل</option>
                        <option value="2">40 مسلسل</option>
                        <option value="3" selected>60 مسلسل</option>
                        <option value="5">100 مسلسل</option>
                        <option value="10">200 مسلسل</option>
                    </select>
                </div>
                
                <button type="submit" name="import_middle_east_tv" class="btn btn-primary btn-large">
                    <i class="fas fa-download"></i> استيراد مسلسلات
                </button>
            </form>
        </div>
        
        <!-- قائمة الدول العربية -->
        <div style="background: #1a1a1a; border-radius: 15px; padding: 25px; margin-top: 30px;">
            <h3 style="color: #e50914; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-map-marker-alt"></i> الدول العربية المدعومة
            </h3>
            <div class="countries-grid">
                <?php foreach ($middle_east_countries as $code => $name): ?>
                <div class="country-item">
                    <span class="country-flag"><?php echo $code; ?></span>
                    <span><?php echo $name; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>