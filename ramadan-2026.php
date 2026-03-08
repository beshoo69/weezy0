<?php
// ramadan-2026.php - صفحة عرض جميع مسلسلات رمضان 2026
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

// جلب مسلسلات رمضان 2026 من قاعدة البيانات
$ramadan_series = $pdo->query("
    SELECT * FROM series 
    WHERE year = '2026' 
       OR (title LIKE '%رمضان%' AND year >= '2025')
    ORDER BY 
        CASE 
            WHEN country = 'مصر' THEN 1
            WHEN country = 'سوريا' THEN 2
            WHEN country IN ('السعودية','الكويت','الإمارات') THEN 3
            ELSE 4
        END,
        id DESC
")->fetchAll();

// إحصائيات
$total_series = count($ramadan_series);
$egypt_series = $pdo->query("SELECT COUNT(*) FROM series WHERE year = '2026' AND country = 'مصر'")->fetchColumn();
$syria_series = $pdo->query("SELECT COUNT(*) FROM series WHERE year = '2026' AND country = 'سوريا'")->fetchColumn();
$gulf_series = $pdo->query("SELECT COUNT(*) FROM series WHERE year = '2026' AND country IN ('السعودية','الكويت','الإمارات')")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مسلسلات رمضان 2026 - ويزي برو</title>
    <meta name="description" content="جميع مسلسلات رمضان 2026 العربية: مصرية، سورية، خليجية. قائمة كاملة بأحدث المسلسلات الرمضانية.">
    
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            line-height: 1.6;
        }
        
        /* ===== الهيدر ===== */
        .header {
            background: #0a0a0a;
            padding: 15px 40px;
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
            font-size: 28px;
            font-weight: 800;
        }
        
        .logo span { color: #fff; }
        
        .nav-list {
            display: flex;
            gap: 25px;
            list-style: none;
        }
        
        .nav-list a {
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .nav-list a:hover,
        .nav-list a.active {
            color: #e50914;
        }
        
        /* ===== الهيرو ===== */
        .hero {
            background: linear-gradient(135deg, #0e4620, #0a2e15);
            padding: 60px 40px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '🌙';
            position: absolute;
            top: -50px;
            left: -50px;
            font-size: 200px;
            opacity: 0.1;
            color: #fff;
            transform: rotate(-15deg);
        }
        
        .hero::after {
            content: '✨';
            position: absolute;
            bottom: -50px;
            right: -50px;
            font-size: 150px;
            opacity: 0.1;
            color: #fff;
            transform: rotate(15deg);
        }
        
        .hero h2 {
            font-size: 48px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 20px;
            position: relative;
        }
        
        .hero p {
            color: #b3b3b3;
            font-size: 20px;
            max-width: 800px;
            margin: 0 auto;
            position: relative;
        }
        
        .hero-stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-top: 40px;
            position: relative;
        }
        
        .stat-item {
            background: rgba(255,255,255,0.1);
            padding: 20px 30px;
            border-radius: 15px;
            backdrop-filter: blur(5px);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: gold;
        }
        
        .stat-label {
            color: #b3b3b3;
            font-size: 16px;
        }
        
        /* ===== الفلاتر ===== */
        .filters {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .filter-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .filter-btn {
            padding: 12px 30px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 50px;
            color: #b3b3b3;
            text-decoration: none;
            transition: 0.3s;
            font-weight: 500;
        }
        
        .filter-btn:hover,
        .filter-btn.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }
        
        .filter-btn.egypt:hover { background: #0e4620; }
        .filter-btn.syria:hover { background: #9b2c2c; }
        .filter-btn.gulf:hover { background: #1a4b8c; }
        
        /* ===== شبكة المسلسلات ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .section-title {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-title i {
            color: gold;
        }
        
        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .series-card {
            background: #1a1a1a;
            border-radius: 15px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
            border: 1px solid #333;
            position: relative;
        }
        
        .series-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }
        
        .series-card.egypt:hover { border-color: #0e4620; box-shadow: 0 10px 30px rgba(14,70,32,0.3); }
        .series-card.syria:hover { border-color: #9b2c2c; box-shadow: 0 10px 30px rgba(155,44,44,0.3); }
        .series-card.gulf:hover { border-color: #1a4b8c; box-shadow: 0 10px 30px rgba(26,75,140,0.3); }
        
        .series-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
            position: relative;
        }
        /* عضوية وشارة جديدة */
        .poster-container {
            position: relative;
            width: 100%;
            overflow: hidden;
        }
        .membership-badge-on-poster {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            animation: badgePulse 2s infinite;
            backdrop-filter: blur(5px);
        }
        .membership-badge-on-poster.premium {
            background: linear-gradient(135deg, #e50914, #ff4d4d);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .membership-badge-on-poster.vip {
            background: linear-gradient(135deg, gold, #ffd700);
            color: black;
            border: 1px solid rgba(255,255,255,0.5);
        }
        .membership-badge-on-poster i {
            font-size: 11px;
        }
        @keyframes badgePulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        /* ضع شارة الدولة إلى اليسار حتى لا تتداخل مع الشارة العضوية */
        .poster-container .series-badge {
            right: auto;
            left: 10px;
        }
        
        .series-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #e50914;
            color: white;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 700;
            z-index: 2;
        }
        
        .series-badge.egypt { background: #0e4620; }
        .series-badge.syria { background: #9b2c2c; }
        .series-badge.gulf { background: #1a4b8c; }
        
        .series-info {
            padding: 20px;
        }
        
        .series-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .series-meta {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .series-stars {
            color: gold;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .series-episodes {
            display: inline-block;
            background: #252525;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: #e50914;
            margin-top: 8px;
        }
        
        /* ===== حالة عدم وجود بيانات ===== */
        .no-data {
            text-align: center;
            padding: 100px 0;
            background: #1a1a1a;
            border-radius: 20px;
        }
        
        .no-data i {
            font-size: 60px;
            color: #e50914;
            margin-bottom: 20px;
        }
        
        .no-data h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .import-btn {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: #e50914;
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 700;
            transition: 0.3s;
        }
        
        .import-btn:hover {
            background: #b20710;
            transform: scale(1.05);
        }
        
        /* ===== فوتر ===== */
        .footer {
            background: #0a0a0a;
            padding: 40px;
            text-align: center;
            color: #b3b3b3;
            margin-top: 60px;
        }
        
        /* ===== التجاوب ===== */
        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .nav-list { display: none; }
            .hero h2 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .hero-stats { flex-direction: column; gap: 15px; }
            .series-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- الهيدر -->
    <header class="header">
        <div class="logo">
            <a href="index.php" style="text-decoration: none;">
                <h1>ويزي<span>برو</span></h1>
            </a>
        </div>
        <nav>
            <ul class="nav-list">
                <li><a href="index.php">الرئيسية</a></li>
                <li><a href="movies.php">أفلام</a></li>
                <li><a href="series.php">مسلسلات</a></li>
                <li><a href="free.php">مجاني</a></li>
                <li><a href="ramadan-2026.php" class="active">رمضان 2026</a></li>
                <li><a href="live.php">بث مباشر</a></li>
            </ul>
        </nav>
    </header>

    <!-- الهيرو -->
    <section class="hero">
        <h2><i class="fas fa-moon"></i> مسلسلات رمضان 2026</h2>
        <p>أحدث المسلسلات العربية الحصرية لموسم رمضان 2026 من مصر وسوريا والخليج</p>
        
        <div class="hero-stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo $total_series; ?></div>
                <div class="stat-label">إجمالي المسلسلات</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $egypt_series; ?></div>
                <div class="stat-label">مسلسلات مصرية</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $syria_series; ?></div>
                <div class="stat-label">مسلسلات سورية</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo $gulf_series; ?></div>
                <div class="stat-label">مسلسلات خليجية</div>
            </div>
        </div>
    </section>

    <!-- فلاتر التصنيف -->
    <div class="filters">
        <div class="filter-buttons">
            <a href="?country=all" class="filter-btn <?php echo !isset($_GET['country']) || $_GET['country'] == 'all' ? 'active' : ''; ?>">الكل</a>
            <a href="?country=egypt" class="filter-btn egypt <?php echo isset($_GET['country']) && $_GET['country'] == 'egypt' ? 'active' : ''; ?>">🇪🇬 مصرية</a>
            <a href="?country=syria" class="filter-btn syria <?php echo isset($_GET['country']) && $_GET['country'] == 'syria' ? 'active' : ''; ?>">🇸🇾 سورية</a>
            <a href="?country=gulf" class="filter-btn gulf <?php echo isset($_GET['country']) && $_GET['country'] == 'gulf' ? 'active' : ''; ?>">🇸🇦 خليجية</a>
        </div>
    </div>

    <div class="container">
        <?php if (empty($ramadan_series)): ?>
            <!-- لا توجد بيانات -->
            <div class="no-data">
                <i class="fas fa-moon"></i>
                <h2>لا توجد مسلسلات رمضانية بعد</h2>
                <p style="color: #b3b3b3;">قم باستيراد مسلسلات رمضان 2026 من لوحة التحكم</p>
                <a href="admin/import-ramadan-from-elcinema.php" class="import-btn">
                    <i class="fas fa-download"></i> استيراد مسلسلات رمضان
                </a>
            </div>
        <?php else: ?>
            <!-- عرض المسلسلات -->
            <h2 class="section-title">
                <i class="fas fa-star"></i> 
                جميع مسلسلات رمضان 2026 (<?php echo count($ramadan_series); ?>)
            </h2>
            
            <div class="series-grid">
                <?php foreach ($ramadan_series as $series): 
                    // تحديد لون البطاقة حسب البلد
                    $card_class = '';
                    $badge_text = 'عربي';
                    
                    if ($series['country'] == 'مصر') {
                        $card_class = 'egypt';
                        $badge_text = 'مصري';
                    } elseif ($series['country'] == 'سوريا') {
                        $card_class = 'syria';
                        $badge_text = 'سوري';
                    } elseif (in_array($series['country'], ['السعودية','الكويت','الإمارات'])) {
                        $card_class = 'gulf';
                        $badge_text = 'خليجي';
                    }
                    
                    // تحديد عدد الحلقات (تقديري)
                    $episodes_count = isset($series['seasons']) ? $series['seasons'] * 30 : 30;
                ?>
                <a href="series.php?id=<?php echo $series['id']; ?>" class="series-card <?php echo $card_class; ?>">
                    <div class="poster-container">
                        <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($series['title']); ?>" 
                             class="series-poster" alt="<?php echo $series['title']; ?>">
                        <?php membershipBadgeOnPoster($series, 'ramadan'); ?>
                        <span class="series-badge <?php echo $card_class; ?>"><?php echo $badge_text; ?></span>
                    </div>
                    <div class="series-info">
                        <div class="series-title"><?php echo htmlspecialchars($series['title']); ?></div>
                        <div class="series-meta">
                            <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                            <?php if ($series['imdb_rating'] && $series['imdb_rating'] > 0): ?>
                            <span class="series-stars">
                                <i class="fas fa-star"></i> <?php echo number_format($series['imdb_rating'], 1); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="series-episodes">
                            <i class="fas fa-layer-group"></i> 
                            <?php echo $series['seasons'] ?? 1; ?> مواسم
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- الفوتر -->
    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>