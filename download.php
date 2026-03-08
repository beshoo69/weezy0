<?php
// download.php - صفحة تحميل الفيلم أو المسلسل
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$type = $_GET['type'] ?? 'movie';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات الفيلم أو المسلسل
if ($type == 'movie') {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? AND status = 'published'");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    $title = $item['title'] ?? '';
    $poster = $item['poster'] ?? '';
} else {
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    $title = $item['title'] ?? '';
    $poster = $item['poster'] ?? '';
}

if (!$item) {
    header("Location: 404.php");
    exit;
}

// جلب سيرفرات التحميل
$stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = ? AND item_id = ? AND is_valid = 1");
$stmt->execute([$type, $id]);
$servers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحميل <?php echo $title; ?> - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
        }
        
        .header {
            background: #0a0a0a;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
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
        }
        
        .nav-list a:hover { color: #e50914; }
        
        .download-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .download-header {
            display: flex;
            gap: 30px;
            margin-bottom: 40px;
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
            border: 1px solid #333;
        }
        
        .download-poster {
            width: 150px;
            border-radius: 10px;
        }
        
        .download-info { flex: 1; }
        
        .download-title {
            font-size: 36px;
            font-weight: 800;
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2a2a2a;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            margin-top: 15px;
            transition: 0.3s;
        }
        
        .back-btn:hover {
            background: #27ae60;
        }
        
        .servers-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
        }
        
        .section-title {
            color: #27ae60;
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .server-card {
            background: #252525;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            border: 1px solid #333;
            transition: 0.3s;
        }
        
        .server-card:hover {
            border-color: #27ae60;
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(39,174,96,0.2);
        }
        
        .server-icon {
            font-size: 48px;
            color: #27ae60;
            margin-bottom: 15px;
        }
        
        .server-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .server-quality {
            color: #27ae60;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .server-size {
            color: #b3b3b3;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .download-link {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .download-link:hover {
            background: #219a52;
            transform: scale(1.02);
        }
        
        .footer {
            background: #0a0a0a;
            padding: 30px;
            text-align: center;
            color: #b3b3b3;
            margin-top: 60px;
        }
        
        @media (max-width: 768px) {
            .download-header { flex-direction: column; align-items: center; text-align: center; }
            .download-poster { width: 120px; }
            .download-title { font-size: 28px; }
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
                <li><a href="live.php">بث مباشر</a></li>
            </ul>
        </nav>
    </header>

    <div class="download-container">
        <!-- رأس صفحة التحميل -->
        <div class="download-header">
            <img src="<?php echo $poster ?: 'https://via.placeholder.com/150x225?text=No+Image'; ?>" 
                 class="download-poster" alt="<?php echo $title; ?>">
            <div class="download-info">
                <h1 class="download-title">تحميل <?php echo $title; ?></h1>
                <p style="color: #b3b3b3;">اختر أحد السيرفرات أدناه لبدء التحميل</p>
                <a href="<?php echo $type == 'movie' ? 'movie-pro.php?id=' . $id : 'series.php?id=' . $id; ?>" class="back-btn">
                    <i class="fas fa-arrow-right"></i> العودة للصفحة الرئيسية
                </a>
            </div>
        </div>
        
        <!-- سيرفرات التحميل -->
        <div class="servers-section">
            <h2 class="section-title"><i class="fas fa-download"></i> سيرفرات التحميل المتاحة</h2>
            
            <?php if (empty($servers)): ?>
                <div style="text-align: center; padding: 60px; background: #252525; border-radius: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 50px; color: #e50914; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">لا توجد سيرفرات تحميل متاحة</h3>
                    <p style="color: #b3b3b3;">سيتم إضافة سيرفرات التحميل قريباً</p>
                </div>
            <?php else: ?>
                <div class="servers-grid">
                    <?php foreach ($servers as $server): ?>
                    <div class="server-card">
                        <div class="server-icon">
                            <i class="fas fa-cloud-download-alt"></i>
                        </div>
                        <div class="server-name"><?php echo htmlspecialchars($server['server_name']); ?></div>
                        <div class="server-quality"><?php echo $server['quality']; ?></div>
                        <?php if (!empty($server['size'])): ?>
                        <div class="server-size">الحجم: <?php echo $server['size']; ?></div>
                        <?php endif; ?>
                        <a href="download-proxy.php?id=<?php echo $server['id']; ?>" class="download-link" target="_blank">
                            <i class="fas fa-download"></i> تحميل
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>