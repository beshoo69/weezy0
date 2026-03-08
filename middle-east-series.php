<?php
// middle-east-series.php - صفحة عرض المسلسلات العربية
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$country = isset($_GET['country']) ? $_GET['country'] : null;
$offset = ($page - 1) * 30;

// جلب المسلسلات العربية
$sql = "SELECT * FROM series WHERE language = 'ar' OR country IN ('مصر','السعودية','لبنان','سوريا','الإمارات','الكويت','المغرب','تونس','الجزائر','العراق','الأردن','فلسطين','اليمن','عمان','قطر','البحرين')";

if ($country) {
    $sql .= " AND country LIKE '%$country%'";
}

$sql .= " ORDER BY id DESC LIMIT 30 OFFSET $offset";
$series = $pdo->query($sql)->fetchAll();

$total_series = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'ar' OR country IN ('مصر','السعودية','لبنان','سوريا','الإمارات','الكويت','المغرب','تونس','الجزائر','العراق','الأردن','فلسطين','اليمن','عمان','قطر','البحرين')")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مسلسلات عربية - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* نفس التنسيقات */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
        }
        
        .header {
            background: #0a0a0a;
            padding: 20px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 32px;
            font-weight: 800;
        }
        
        .logo span { color: #fff; }
        
        .nav-list {
            display: flex;
            gap: 40px;
            list-style: none;
        }
        
        .nav-list a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-list a:hover,
        .nav-list a.active {
            color: #e50914;
        }
        
        .hero {
            background: linear-gradient(135deg, #0e4620, #0a2e15);
            padding: 60px 40px;
            text-align: center;
        }
        
        .hero h2 {
            font-size: 48px;
            font-weight: 800;
            color: #fff;
            margin-bottom: 20px;
        }
        
        .hero p {
            color: #b3b3b3;
            font-size: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 60px auto;
            padding: 0 40px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .section-title {
            font-size: 28px;
            font-weight: 800;
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .series-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 25px;
        }
        
        .series-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
            color: white;
            border: 1px solid #333;
        }
        
        .series-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229,9,20,0.2);
        }
        
        .series-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        /* شارة العضوية على البوستر */
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
        
        .series-info {
            padding: 15px;
        }
        
        .series-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .series-meta {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            font-size: 14px;
        }
        
        .country-badge {
            background: rgba(229,9,20,0.2);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            color: #e50914;
        }
        
        .footer {
            background: #0a0a0a;
            padding: 60px 40px 30px;
            margin-top: 80px;
            text-align: center;
            color: #b3b3b3;
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
                <li><a href="index.php">الرئيسية</a></li>
                <li><a href="movies.php">أفلام</a></li>
                <li><a href="series.php">مسلسلات</a></li>
                <li><a href="middle-east-movies.php">أفلام عربية</a></li>
                <li><a href="middle-east-series.php" class="active">مسلسلات عربية</a></li>
                <li><a href="live.php">بث مباشر</a></li>
            </ul>
        </nav>
    </header>
    
    <div class="hero">
        <h2><i class="fas fa-map-marker-alt"></i> مسلسلات عربية</h2>
        <p>أحدث المسلسلات من مصر، السعودية، لبنان، سوريا، ودول الخليج</p>
    </div>
    
    <div class="container">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-tv"></i> أحدث المسلسلات العربية</h2>
            <span style="color: #b3b3b3;">إجمالي: <?php echo $total_series; ?> مسلسل</span>
        </div>
        
        <?php if (empty($series)): ?>
            <div style="text-align: center; padding: 100px 0; background: #1a1a1a; border-radius: 15px;">
                <i class="fas fa-tv" style="font-size: 60px; color: #e50914; margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 10px;">لا توجد مسلسلات عربية بعد</h3>
                <p style="color: #b3b3b3;">قم باستيراد المسلسلات العربية من لوحة التحكم</p>
            </div>
        <?php else: ?>
            <div class="series-grid">
                <?php foreach ($series as $serie): ?>
                <a href="series.php?id=<?php echo $serie['id']; ?>" class="series-card">
                    <div class="poster-container">
                        <img src="<?php echo $serie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($serie['title']); ?>" 
                             class="series-poster" alt="<?php echo $serie['title']; ?>">
                        <?php membershipBadgeOnPoster($serie); ?>
                    </div>
                    <div class="series-info">
                        <div class="series-title"><?php echo $serie['title']; ?></div>
                        <div class="series-meta">
                            <span><?php echo $serie['year']; ?></span>
                            <?php if ($serie['country']): ?>
                            <span class="country-badge"><?php echo $serie['country']; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>