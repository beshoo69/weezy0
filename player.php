<?php
// player.php - صفحة مشاهدة الفيديو
require_once 'includes/config.php';
require_once 'includes/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = isset($_GET['type']) ? $_GET['type'] : 'movie';

if ($type === 'movie') {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    $content = $stmt->fetch();
    $title = $content['title'] ?? '';
} else {
    $episode_id = isset($_GET['episode']) ? (int)$_GET['episode'] : 0;
    $stmt = $pdo->prepare("SELECT e.*, s.title as series_title FROM episodes e 
                           JOIN seasons se ON e.season_id = se.id 
                           JOIN series s ON se.series_id = s.id 
                           WHERE e.id = ?");
    $stmt->execute([$episode_id]);
    $content = $stmt->fetch();
    $title = ($content['series_title'] ?? '') . ' - ' . ($content['name'] ?? '');
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهدة <?php echo htmlspecialchars($title); ?> - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            margin: 0;
            padding: 0;
            background: #000;
            color: #fff;
        }
        
        .player-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #000;
        }
        
        video {
            width: 100%;
            height: 100%;
            outline: none;
        }
        
        .player-controls {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            z-index: 1000;
            opacity: 0;
            transition: 0.3s;
        }
        
        .player-container:hover .player-controls {
            opacity: 1;
        }
        
        .back-btn {
            color: white;
            text-decoration: none;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .back-btn:hover {
            color: #e50914;
        }
        
        .video-title {
            flex: 1;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="player-container">
        <video controls autoplay>
            <source src="<?php echo htmlspecialchars($content['video_url'] ?? ''); ?>" type="video/mp4">
            متصفحك لا يدعم تشغيل الفيديو
        </video>
        
        <div class="player-controls">
            <a href="<?php echo $type === 'movie' ? 'movie.php?id='.$id : 'series.php?id='.$content['series_id']; ?>" class="back-btn">
                <i class="fas fa-arrow-right"></i>
                العودة
            </a>
            <div class="video-title"><?php echo htmlspecialchars($title); ?></div>
        </div>
    </div>
</body>
</html>