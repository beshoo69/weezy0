<?php
// redirect-download.php - صفحة وسيطة للتحميل
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'movie';
$link_id = isset($_GET['link_id']) ? (int)$_GET['link_id'] : 0;

if ($type == 'movie') {
    $stmt = $pdo->prepare("SELECT d.*, m.tmdb_id FROM download_servers d 
                           JOIN movies m ON d.item_id = m.id 
                           WHERE d.id = ? AND d.item_type = 'movie'");
    $stmt->execute([$link_id]);
    $link = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT d.*, s.tmdb_id FROM download_servers d 
                           JOIN series s ON d.item_id = s.id 
                           WHERE d.id = ? AND d.item_type = 'series'");
    $stmt->execute([$link_id]);
    $link = $stmt->fetch();
}

if (!$link) {
    header('Location: ' . ($type == 'movie' ? 'movie.php?id=' . $id : 'series.php?id=' . $id));
    exit;
}

// التحقق من صحة الرابط
$headers = @get_headers($link['download_url']);
if ($headers && strpos($headers[0], '200') !== false) {
    // الرابط صالح - إعادة توجيه مباشر
    header('Location: ' . $link['download_url']);
    exit;
} else {
    // الرابط منتهي - عرض خيارات بديلة
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <title>رابط التحميل منتهي</title>
        <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;700&display=swap" rel="stylesheet">
        <style>
            body {
                font-family: 'Tajawal', sans-serif;
                background: #0f0f0f;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                background: #1a1a1a;
                border-radius: 15px;
                padding: 30px;
                border: 1px solid #e50914;
                text-align: center;
            }
            .icon {
                font-size: 60px;
                color: #e50914;
                margin-bottom: 20px;
            }
            h1 {
                color: #e50914;
                margin-bottom: 15px;
            }
            p {
                color: #b3b3b3;
                margin-bottom: 25px;
                line-height: 1.8;
            }
            .btn {
                display: inline-block;
                padding: 12px 30px;
                background: #e50914;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                margin: 10px;
                transition: 0.3s;
                border: none;
                cursor: pointer;
            }
            .btn:hover {
                background: #b20710;
                transform: translateY(-2px);
            }
            .btn-secondary {
                background: #333;
            }
            .btn-secondary:hover {
                background: #444;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon">⚠️</div>
            <h1>رابط التحميل منتهي الصلاحية</h1>
            <p>عذراً، الرابط الذي تحاول الوصول إليه قد انتهت صلاحيته.<br>يمكنك تجربة الروابط الأخرى أو العودة للصفحة السابقة.</p>
            <a href="javascript:history.back()" class="btn btn-secondary">العودة للصفحة السابقة</a>
            <a href="<?php echo $type == 'movie' ? 'movie.php?id=' . $id : 'series.php?id=' . $id; ?>" class="btn">الذهاب لصفحة المحتوى</a>
        </div>
    </body>
    </html>
    <?php
}
?>