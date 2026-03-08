<?php
// youtube-series.php - صفحة عرض المسلسل مع حلقاته
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$series_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$series_id) {
    header('Location: free.php');
    exit;
}

// جلب معلومات المسلسل
$series = $pdo->prepare("
    SELECT * FROM youtube_series WHERE id = ? AND status = 1
");
$series->execute([$series_id]);
$series_data = $series->fetch();

if (!$series_data) {
    header('Location: free.php');
    exit;
}

// جلب الحلقات مرتبة
$episodes = $pdo->prepare("
    SELECT * FROM youtube_episodes 
    WHERE series_id = ? AND status = 1
    ORDER BY episode_number ASC
");
$episodes->execute([$series_id]);
$episodes_data = $episodes->fetchAll();

// حساب إجمالي المشاهدات
$total_views = 0;
foreach ($episodes_data as $ep) {
    $views = (int)str_replace(',', '', $ep['view_count']);
    $total_views += $views;
}

// جلب المواسم
$seasons = $pdo->prepare("
    SELECT DISTINCT season_number, COUNT(*) as episode_count 
    FROM youtube_episodes 
    WHERE series_id = ? 
    GROUP BY season_number
    ORDER BY season_number ASC
");
$seasons->execute([$series_id]);
$seasons_data = $seasons->fetchAll();

$current_season = isset($_GET['season']) ? (int)$_GET['season'] : 1;

// فلترة حسب الموسم
if (!empty($seasons_data) && $current_season > 0) {
    $filtered_episodes = array_filter($episodes_data, function($ep) use ($current_season) {
        return ($ep['season_number'] ?? 1) == $current_season;
    });
} else {
    $filtered_episodes = $episodes_data;
}

// استخراج طاقم التمثيل (هذا مثال، ideally يكون في جدول منفصل)
$cast = [
    ['name' => 'علاء الزعبي', 'role' => 'فارس حسن جابر'],
    ['name' => 'من واصف', 'role' => 'نائبني خوري زهرة'],
    ['name' => 'نور علي', 'role' => 'شاكر محمد'],
    ['name' => 'سامر المصري', 'role' => 'أبو جابر'],
    ['name' => 'كاريس بشار', 'role' => 'أمينة'],
];

$genres = ['دراما', 'جريمة', 'تشويق'];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($series_data['title']); ?> - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
        }

        .header {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo h1 {
            color: #e50914;
            font-size: 32px;
            font-weight: 800;
        }

        .logo span {
            color: #fff;
        }

        .nav-list {
            display: flex;
            gap: 40px;
            list-style: none;
        }

        .nav-list a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav-list a:hover,
        .nav-list a.active {
            color: #e50914;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* شريط التنقل */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: #b3b3b3;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #b3b3b3;
            text-decoration: none;
            transition: 0.3s;
        }

        .breadcrumb a:hover {
            color: #e50914;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #252525;
            color: #fff;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            margin-bottom: 20px;
            border: 1px solid #333;
            transition: 0.3s;
        }

        .back-button:hover {
            border-color: #e50914;
            color: #e50914;
        }

        /* قسم معلومات المسلسل */
        .series-header {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #333;
            position: relative;
            overflow: hidden;
        }

        .series-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e50914, #ff6b6b);
        }

        .series-info {
            display: flex;
            gap: 30px;
        }

        .series-poster {
            width: 250px;
            height: 150px;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid #e50914;
        }

        .series-details {
            flex: 1;
        }

        .series-title {
            font-size: 42px;
            font-weight: 800;
            color: #e50914;
            margin-bottom: 20px;
        }

        /* بطاقات المعلومات */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-card {
            background: #252525;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #333;
        }

        .info-label {
            color: #b3b3b3;
            font-size: 13px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 18px;
            font-weight: 700;
        }

        .rating {
            color: gold;
            font-size: 18px;
        }

        /* التصنيفات */
        .genres {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .genre-tag {
            background: rgba(229,9,20,0.1);
            color: #e50914;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
        }

        /* طاقم التمثيل */
        .cast-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e50914;
        }

        .cast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }

        .cast-card {
            background: #252525;
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #333;
            transition: 0.3s;
        }

        .cast-card:hover {
            border-color: #e50914;
        }

        .cast-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .cast-role {
            color: #b3b3b3;
            font-size: 13px;
        }

        /* المواسم */
        .seasons-tabs {
            display: flex;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .season-tab {
            padding: 10px 25px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 30px;
            color: #b3b3b3;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .season-tab:hover,
        .season-tab.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }

        /* الحلقات */
        .episodes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .episodes-title {
            font-size: 24px;
            font-weight: 700;
            color: #e50914;
        }

        .episodes-count {
            background: rgba(229,9,20,0.1);
            padding: 8px 15px;
            border-radius: 30px;
            color: #e50914;
        }

        .episodes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .episode-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: all 0.3s;
            text-decoration: none;
            color: #fff;
        }

        .episode-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }

        .episode-thumb {
            position: relative;
            width: 100%;
            height: 160px;
            object-fit: cover;
        }

        .episode-number-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(229,9,20,0.9);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .episode-duration-badge {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 5px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .episode-info {
            padding: 15px;
        }

        .episode-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .episode-meta {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            font-size: 13px;
        }

        .watch-button {
            background: #e50914;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            transition: 0.3s;
            width: 100%;
            justify-content: center;
        }

        .watch-button:hover {
            background: #b20710;
        }

        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-list {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .series-info {
                flex-direction: column;
            }
            
            .series-poster {
                width: 100%;
            }
            
            .series-title {
                font-size: 32px;
            }
            
            .episodes-grid {
                grid-template-columns: 1fr;
            }
            
            .cast-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <nav>
            <ul class="nav-list">
                <li><a href="index.php"><i class="fas fa-home"></i> الرئيسية</a></li>
                <li><a href="movies.php"><i class="fas fa-film"></i> أفلام</a></li>
                <li><a href="series.php"><i class="fas fa-tv"></i> مسلسلات</a></li>
                <li><a href="free.php" class="active"><i class="fas fa-gift"></i> مجاني</a></li>
                <li><a href="live.php"><i class="fas fa-broadcast-tower"></i> بث مباشر</a></li>
                <li><a href="anime-series.php"><i class="fas fa-dragon"></i> أنمي</a></li>
            </ul>
        </nav>
    </header>

    <div class="container">
        <!-- شريط التنقل -->
        <div class="breadcrumb">
            <a href="index.php">الرئيسية</a>
            <i class="fas fa-chevron-left"></i>
            <a href="free.php">المحتوى المجاني</a>
            <i class="fas fa-chevron-left"></i>
            <span><?php echo htmlspecialchars($series_data['title']); ?></span>
        </div>

        <!-- معلومات المسلسل -->
        <div class="series-header">
            <div class="series-info">
                <img src="<?php echo !empty($series_data['local_thumbnail']) ? $series_data['local_thumbnail'] : $series_data['thumbnail']; ?>" 
                     class="series-poster" 
                     alt="<?php echo htmlspecialchars($series_data['title']); ?>"
                     onerror="this.src='https://via.placeholder.com/300x150?text=No+Image'">
                
                <div class="series-details">
                    <h1 class="series-title"># <?php echo htmlspecialchars($series_data['title']); ?></h1>
                    
                    <!-- بطاقات المعلومات -->
                    <div class="info-cards">
                        <div class="info-card">
                            <div class="info-label">الملفات</div>
                            <div class="info-value"><?php echo count($episodes_data); ?> حلقة</div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">المشاهدات</div>
                            <div class="info-value"><?php echo number_format($total_views); ?></div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">التقييم</div>
                            <div class="info-value rating">
                                <i class="fas fa-star"></i> 
                                <?php 
                                // تقييم عشوائي كمثال (يمكنك حسابه من التقييمات الحقيقية)
                                $rating = rand(75, 95) / 10;
                                echo number_format($rating, 1); 
                                ?>/10
                            </div>
                        </div>
                        
                        <div class="info-card">
                            <div class="info-label">الصور</div>
                            <div class="info-value">1</div>
                        </div>
                    </div>
                    
                    <!-- التصنيفات -->
                    <div class="genres">
                        <?php foreach ($genres as $genre): ?>
                        <span class="genre-tag"><?php echo $genre; ?></span>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- وصف المسلسل -->
                    <?php if (!empty($series_data['description'])): ?>
                    <p style="color: #b3b3b3; line-height: 1.8; margin-bottom: 20px;">
                        <?php echo nl2br(htmlspecialchars($series_data['description'])); ?>
                    </p>
                    <?php endif; ?>
                    
                    <!-- معلومات القناة -->
                    <div style="background: #252525; padding: 15px; border-radius: 10px; display: inline-block;">
                        <i class="fab fa-youtube" style="color: #ff0000;"></i>
                        <span style="margin-right: 10px;">القناة: <?php echo htmlspecialchars($series_data['channel_title']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- طاقم التمثيل -->
        <div class="cast-section">
            <h2 class="section-title">
                <i class="fas fa-users"></i> طاقم التمثيل
            </h2>
            
            <div class="cast-grid">
                <?php foreach ($cast as $actor): ?>
                <div class="cast-card">
                    <div class="cast-name"><?php echo $actor['name']; ?></div>
                    <div class="cast-role"><?php echo $actor['role']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- المواسم -->
        <?php if (count($seasons_data) > 1): ?>
        <div class="seasons-tabs">
            <?php foreach ($seasons_data as $season): ?>
            <a href="?id=<?php echo $series_id; ?>&season=<?php echo $season['season_number']; ?>" 
               class="season-tab <?php echo ($current_season == $season['season_number']) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> الموسم <?php echo $season['season_number']; ?>
                <span style="background: rgba(255,255,255,0.2); padding: 2px 6px; border-radius: 10px;">
                    <?php echo $season['episode_count']; ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- الحلقات -->
        <div class="episodes-header">
            <h2 class="episodes-title">
                <i class="fas fa-play-circle"></i> 
                <?php if (count($seasons_data) > 1): ?>
                    الموسم <?php echo $current_season; ?>
                <?php else: ?>
                    جميع الحلقات
                <?php endif; ?>
            </h2>
            <span class="episodes-count"><?php echo count($filtered_episodes); ?> حلقة</span>
        </div>

        <?php if (!empty($filtered_episodes)): ?>
        <div class="episodes-grid">
            <?php foreach ($filtered_episodes as $episode): ?>
            <a href="https://www.youtube.com/watch?v=<?php echo $episode['video_id']; ?>" target="_blank" class="episode-card">
                <div style="position: relative;">
                    <img src="<?php echo !empty($episode['local_thumbnail']) ? $episode['local_thumbnail'] : $episode['thumbnail']; ?>" 
                         class="episode-thumb" 
                         alt="<?php echo htmlspecialchars($episode['title']); ?>"
                         onerror="this.src='https://via.placeholder.com/300x160?text=No+Image'">
                    
                    <span class="episode-number-badge">الحلقة <?php echo $episode['episode_number']; ?></span>
                    
                    <span class="episode-duration-badge">
                        <i class="far fa-clock"></i> <?php echo $episode['duration']; ?>
                    </span>
                </div>
                
                <div class="episode-info">
                    <h3 class="episode-title"><?php echo htmlspecialchars($episode['title']); ?></h3>
                    
                    <div class="episode-meta">
                        <span><i class="far fa-eye"></i> <?php echo $episode['view_count']; ?> مشاهدة</span>
                        <span><i class="far fa-calendar"></i> <?php echo date('Y-m-d', strtotime($episode['published_at'])); ?></span>
                    </div>
                    
                    <div class="watch-button">
                        <i class="fas fa-play"></i> مشاهدة الحلقة
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="text-align: center; color: #b3b3b3; padding: 40px;">
            لا توجد حلقات في هذا الموسم
        </p>
        <?php endif; ?>
    </div>
</body>
</html>