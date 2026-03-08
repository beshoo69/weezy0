<?php
// watch.php - صفحة مشاهدة الفيلم مع دعم الروابط الديناميكية
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/membership-check.php';

function isLinkValid($url) {
    if (empty($url) || $url == '#') return false;
    
    // التحقق من أن الرابط ليس منتهي الصلاحية
    $headers = @get_headers($url);
    if ($headers && strpos($headers[0], '200') !== false) {
        return true;
    }
    return false;
}

// دالة لتحديث الرابط باستخدام TMDB ID
function getFreshLink($tmdb_id, $type = 'movie', $season = null, $episode = null) {
    // قائمة بالسيرفرات البديلة
    $alternative_servers = [
        'movie' => [
            "https://vidsrc.to/embed/movie/{TMDB_ID}",
            "https://www.2embed.cc/embed/{TMDB_ID}",
            "https://embed.su/embed/movie/{TMDB_ID}",
            "https://vidlink.pro/movie/{TMDB_ID}",
            "https://autoembed.cc/embed/movie/{TMDB_ID}"
        ],
        'tv' => [
            "https://vidsrc.to/embed/tv/{TMDB_ID}/{SEASON}/{EPISODE}",
            "https://www.2embed.cc/embed/{TMDB_ID}/{SEASON}/{EPISODE}",
            "https://embed.su/embed/tv/{TMDB_ID}/{SEASON}/{EPISODE}",
            "https://vidlink.pro/tv/{TMDB_ID}/{SEASON}/{EPISODE}"
        ]
    ];
    
    if ($type == 'movie') {
        foreach ($alternative_servers['movie'] as $base_url) {
            $url = str_replace('{TMDB_ID}', $tmdb_id, $base_url);
            if (isLinkValid($url)) {
                return $url;
            }
        }
    } else {
        foreach ($alternative_servers['tv'] as $base_url) {
            $url = str_replace(['{TMDB_ID}', '{SEASON}', '{EPISODE}'], 
                               [$tmdb_id, $season, $episode], $base_url);
            if (isLinkValid($url)) {
                return $url;
            }
        }
    }
    return null;
}

// دالة لتحديث جميع روابط المشاهدة
function refreshWatchLinks($pdo, $content_type, $content_id, $tmdb_id = null, $season = null, $episode = null) {
    // جلب جميع الروابط المخزنة
    if ($content_type == 'movie') {
        $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ?");
        $stmt->execute([$content_id]);
        $links = $stmt->fetchAll();
        
        foreach ($links as $link) {
            if (!isLinkValid($link['server_url'])) {
                // الرابط منتهي، نبحث عن بديل
                if ($tmdb_id) {
                    $new_url = getFreshLink($tmdb_id, 'movie');
                    if ($new_url) {
                        // تحديث الرابط في قاعدة البيانات
                        $update = $pdo->prepare("UPDATE watch_servers SET server_url = ? WHERE id = ?");
                        $update->execute([$new_url, $link['id']]);
                    }
                }
            }
        }
    }
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب معلومات الفيلم
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    header('Location: movies.php');
    exit;
}

// التحقق من صلاحية المشاهدة
$user_level = getUserMembershipLevel($_SESSION['user_id'] ?? 0);
$required_level = $movie['membership_level'] ?? 'basic';
$can_view = canViewContent($user_level, $required_level);

if (!$can_view) {
    $_SESSION['requested_url'] = $_SERVER['REQUEST_URI'];
    header('Location: membership-plans.php?required=' . $required_level . '&redirect=watch.php?id=' . $id);
    exit;
}

// زيادة عدد المشاهدات
try {
    $pdo->prepare("UPDATE movies SET views = IFNULL(views, 0) + 1 WHERE id = ?")->execute([$id]);
} catch (Exception $e) {
    // تجاهل الخطأ
}

// =============================================
// معالجة روابط المشاهدة والتحميل من JSON (النظام الجديد)
// =============================================
$movie_watch_servers = [];
$movie_download_servers = [];

// فك تشفير روابط المشاهدة من JSON (إذا كان الحقل موجوداً)
if (!empty($movie['watch_servers']) && $movie['watch_servers'] != 'null' && $movie['watch_servers'] != '[]') {
    $decoded = json_decode($movie['watch_servers'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $movie_watch_servers = $decoded;
    }
}

// فك تشفير روابط التحميل من JSON (إذا كان الحقل موجوداً)
if (!empty($movie['download_servers']) && $movie['download_servers'] != 'null' && $movie['download_servers'] != '[]') {
    $decoded = json_decode($movie['download_servers'], true);
    if (is_array($decoded) && !empty($decoded)) {
        $movie_download_servers = $decoded;
    }
}

// =============================================
// معالجة روابط المشاهدة والتحميل من الجداول المنفصلة (النظام القديم)
// =============================================

// إذا لم توجد روابط في JSON، نبحث في الجدول القديم
if (empty($movie_watch_servers)) {
    $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY 
                           CASE quality 
                               WHEN '4K' THEN 1
                               WHEN '1080p' THEN 2
                               WHEN '720p' THEN 3
                               ELSE 4
                           END");
    $stmt->execute([$id]);
    $old_servers = $stmt->fetchAll();
    
    foreach ($old_servers as $server) {
        $movie_watch_servers[] = [
            'name' => $server['server_name'],
            'url' => $server['server_url'] ?: $server['embed_code'],
            'lang' => $server['language'] ?? 'arabic',
            'quality' => $server['quality'] ?? 'HD'
        ];
    }
}

// إذا لم توجد روابط تحميل في JSON، نبحث في الجدول القديم
if (empty($movie_download_servers)) {
    $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ? AND is_valid = 1 ORDER BY 
                           CASE quality 
                               WHEN '4K' THEN 1
                               WHEN '1080p' THEN 2
                               WHEN '720p' THEN 3
                               ELSE 4
                           END");
    $stmt->execute([$id]);
    $old_downloads = $stmt->fetchAll();
    
    foreach ($old_downloads as $server) {
        $movie_download_servers[] = [
            'name' => $server['server_name'],
            'url' => $server['download_url'],
            'quality' => $server['quality'] ?? 'HD',
            'size' => $server['size'] ?? ''
        ];
    }
}

// =============================================
// ترتيب السيرفرات حسب الجودة
// =============================================
usort($movie_watch_servers, function($a, $b) {
    $quality_order = ['4K' => 1, '1080p' => 2, '720p' => 3, 'HD' => 4, '480p' => 5];
    $a_quality = $a['quality'] ?? 'HD';
    $b_quality = $b['quality'] ?? 'HD';
    return ($quality_order[$a_quality] ?? 99) - ($quality_order[$b_quality] ?? 99);
});

usort($movie_download_servers, function($a, $b) {
    $quality_order = ['4K' => 1, '1080p' => 2, '720p' => 3, 'HD' => 4, '480p' => 5];
    $a_quality = $a['quality'] ?? 'HD';
    $b_quality = $b['quality'] ?? 'HD';
    return ($quality_order[$a_quality] ?? 99) - ($quality_order[$b_quality] ?? 99);
});

// =============================================
// إنشاء روابط افتراضية إذا لم توجد أي روابط (كملاذ أخير)
// =============================================
$tmdb_id = $movie['tmdb_id'] ?? null;

if (empty($movie_watch_servers) && !empty($tmdb_id)) {
    $movie_watch_servers = [
        [
            'name' => '🎬 Vidsrc.to - 4K UHD',
            'url' => "https://vidsrc.to/embed/movie/{$tmdb_id}",
            'lang' => 'arabic',
            'quality' => '4K'
        ],
        [
            'name' => '🎥 2Embed - 1080p HD',
            'url' => "https://www.2embed.cc/embed/{$tmdb_id}",
            'lang' => 'arabic',
            'quality' => '1080p'
        ],
        [
            'name' => '📺 Embed.su - 1080p HD',
            'url' => "https://embed.su/embed/movie/{$tmdb_id}",
            'lang' => 'english',
            'quality' => '1080p'
        ],
        [
            'name' => '⚡ VidLink.pro - 4K UHD',
            'url' => "https://vidlink.pro/movie/{$tmdb_id}",
            'lang' => 'arabic',
            'quality' => '4K'
        ]
    ];
}

// =============================================
// جلب أفلام مشابهة
// =============================================
$similar_movies = $pdo->prepare("
    SELECT id, title, poster, year, imdb_rating 
    FROM movies 
    WHERE category = ? AND id != ? AND status = 'published'
    ORDER BY imdb_rating DESC, views DESC
    LIMIT 6
");
$similar_movies->execute([$movie['category'] ?? '', $id]);
$similar_movies_data = $similar_movies->fetchAll();

// جلب الحلقة السابقة والتالية (للمسلسلات فقط - نتركها فارغة للأفلام)
$prev_episode = null;
$next_episode = null;
$other_episodes = [];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مشاهدة <?php echo htmlspecialchars($movie['title']); ?> - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --success: #27ae60;
            --text-gray: #b3b3b3;
            --border: #333;
            --dark: #0f0f0f;
            --dark-light: #1a1a1a;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--dark);
            color: #fff;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--primary);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 800;
        }

        .logo span {
            color: #fff;
        }

        .nav-links {
            display: flex;
            gap: 15px;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 30px;
            transition: 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            background: var(--primary);
        }

        .back-btn {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: rgba(255,255,255,0.05);
            border-radius: 30px;
            transition: 0.3s;
        }

        .back-btn:hover {
            background: var(--primary);
            transform: translateX(-5px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* ===== شارة العضوية ===== */
        .membership-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .membership-badge.premium {
            background: linear-gradient(135deg, var(--primary), #ff4d4d);
            color: white;
        }

        .membership-badge.vip {
            background: linear-gradient(135deg, gold, #ffd700);
            color: black;
        }

        /* ===== هيدر الفيلم ===== */
        .movie-header {
            background: linear-gradient(145deg, var(--dark-light), var(--dark));
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .movie-poster {
            width: 200px;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            border: 3px solid var(--primary);
        }

        .movie-info {
            flex: 1;
        }

        .movie-title {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .series-link {
            color: var(--text-gray);
            text-decoration: none;
            font-size: 20px;
            margin-bottom: 15px;
            display: inline-block;
            transition: 0.3s;
        }

        .series-link:hover {
            color: var(--primary);
        }

        .movie-meta {
            display: flex;
            gap: 20px;
            color: var(--text-gray);
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .movie-meta i {
            color: var(--primary);
        }

        .movie-description {
            color: var(--text-gray);
            line-height: 1.8;
            max-width: 800px;
        }

        /* ===== أزرار الإجراءات ===== */
        .action-bar {
            display: flex;
            gap: 15px;
            margin: 25px 0;
            flex-wrap: wrap;
        }

        .action-btn {
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }

        .watch-btn {
            background: var(--primary);
            color: white;
            flex: 1;
        }

        .watch-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .download-main-btn {
            background: var(--success);
            color: white;
            flex: 1;
        }

        .download-main-btn:hover {
            background: #219a52;
            transform: translateY(-2px);
        }

        .share-menu {
            position: relative;
        }

        .share-btn {
            background: #252525;
            color: white;
            border: 1px solid var(--border);
            height: 100%;
        }

        .share-btn:hover {
            background: var(--primary);
        }

        .share-options {
            position: absolute;
            bottom: 100%;
            left: 0;
            background: #1a1a1a;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 8px;
            display: none;
            gap: 5px;
            margin-bottom: 10px;
            z-index: 100;
        }

        .share-options.active {
            display: flex;
        }

        .share-option {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }

        .share-option.facebook { background: #3b5998; }
        .share-option.twitter { background: #1da1f2; }
        .share-option.whatsapp { background: #25d366; }
        .share-option.telegram { background: #0088cc; }

        .share-option:hover {
            transform: translateY(-3px);
            filter: brightness(1.2);
        }

        /* ===== مشغل الفيديو ===== */
        .video-player-section {
            background: #000;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 30px;
        }

        .video-placeholder {
            background: linear-gradient(45deg, #1a1a1a, #0a0a0a);
            padding: 100px 20px;
            text-align: center;
            border-radius: 15px;
        }

        .video-placeholder i {
            font-size: 80px;
            color: var(--primary);
            margin-bottom: 20px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        .player-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            display: none;
        }

        .player-container.active {
            display: block;
        }

        .player-container iframe,
        .player-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* ===== أقسام السيرفرات ===== */
        .section {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 22px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-count {
            background: rgba(229,9,20,0.1);
            padding: 5px 15px;
            border-radius: 30px;
            color: var(--primary);
            font-size: 14px;
        }

        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .server-card {
            background: var(--dark);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .server-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(229,9,20,0.2);
        }

        .server-card.best {
            border: 2px solid gold;
        }

        .server-card.best::before {
            content: "⭐ أفضل جودة";
            position: absolute;
            top: -12px;
            left: 20px;
            background: gold;
            color: black;
            padding: 3px 15px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: bold;
        }

        .server-icon {
            font-size: 40px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .server-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .server-tags {
            display: flex;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .server-tag {
            background: rgba(255,255,255,0.05);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            color: var(--text-gray);
        }

        .server-tag i {
            color: var(--primary);
            margin-left: 3px;
        }

        .server-status {
            color: var(--success);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .server-btn {
            width: 100%;
            padding: 10px;
            background: transparent;
            border: 2px solid var(--primary);
            color: white;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .server-btn:hover {
            background: var(--primary);
            transform: scale(1.02);
        }

        .download-section {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid var(--success);
        }

        .download-section .section-title {
            color: var(--success);
        }

        .download-card {
            background: var(--dark);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: var(--transition);
        }

        .download-card:hover {
            transform: translateY(-3px);
            border-color: var(--success);
        }

        .download-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .download-meta {
            display: flex;
            justify-content: space-between;
            color: var(--text-gray);
            font-size: 13px;
            margin-bottom: 15px;
        }

        .download-link {
            display: inline-block;
            width: 100%;
            padding: 10px;
            background: var(--success);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            text-align: center;
            transition: 0.3s;
        }

        .download-link:hover {
            background: #219a52;
            transform: scale(1.02);
        }

        /* ===== أفلام مشابهة ===== */
        .similar-section {
            margin-top: 40px;
        }

        .similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .similar-card {
            background: #1a1a1a;
            border-radius: 8px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
            color: white;
        }

        .similar-card:hover {
            transform: translateY(-5px);
            border: 1px solid var(--primary);
        }

        .similar-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }

        .similar-info {
            padding: 10px;
        }

        .similar-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .similar-year {
            color: var(--text-gray);
            font-size: 12px;
        }

        .footer {
            background: #0a0a0a;
            padding: 30px;
            text-align: center;
            color: var(--text-gray);
            margin-top: 60px;
            border-top: 1px solid var(--border);
        }

        /* ===== مودال خيارات التحميل ===== */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }

        .modal-content {
            background: #1a1a1a;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--primary);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: var(--primary);
            margin: 0;
        }

        .close-modal {
            font-size: 24px;
            cursor: pointer;
            color: var(--text-gray);
            transition: 0.3s;
        }

        .close-modal:hover {
            color: var(--primary);
        }

        .modal-body {
            padding: 20px;
            max-height: 400px;
            overflow-y: auto;
        }

        .download-links-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .download-link-item {
            background: #0f0f0f;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px;
            text-decoration: none;
            color: white;
            transition: 0.3s;
        }

        .download-link-item:hover {
            border-color: var(--success);
            transform: translateY(-2px);
        }

        .download-link-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .download-link-meta {
            display: flex;
            gap: 10px;
            color: var(--text-gray);
            font-size: 13px;
        }

        .download-link-meta .quality {
            background: var(--success);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
        }

        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .nav-links { display: none; }
            .movie-header { flex-direction: column; align-items: center; text-align: center; }
            .movie-poster { width: 150px; height: 225px; }
            .movie-meta { justify-content: center; }
            .action-bar { flex-direction: column; }
            .share-menu { width: 100%; }
            .share-btn { width: 100%; }
            .share-options { bottom: auto; top: 100%; left: 0; right: 0; justify-content: center; }
            .servers-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-link">الرئيسية</a>
            <a href="movies.php" class="nav-link">أفلام</a>
            <a href="series.php" class="nav-link">مسلسلات</a>
        </div>
        <a href="movie.php?id=<?php echo $id; ?>" class="back-btn">
            <i class="fas fa-arrow-right"></i> العودة للفيلم
        </a>
    </div>

    <div class="container">
        <!-- شارة العضوية إذا كانت مطلوبة -->
        <?php if ($required_level != 'basic'): ?>
        <div class="membership-badge <?php echo $required_level; ?>" style="margin-bottom: 15px;">
            <i class="fas fa-<?php echo $required_level == 'vip' ? 'crown' : 'star'; ?>"></i>
            هذا الفيلم متاح لمشتركي <?php echo $required_level == 'vip' ? 'VIP' : 'المميزين'; ?>
        </div>
        <?php endif; ?>

        <!-- هيدر الفيلم -->
        <div class="movie-header">
            <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/200x300?text=No+Image'; ?>" class="movie-poster" alt="<?php echo htmlspecialchars($movie['title']); ?>">
            <div class="movie-info">
                <h1 class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></h1>
                <div class="movie-meta">
                    <span><i class="fas fa-calendar-alt"></i> <?php echo $movie['year'] ?? date('Y'); ?></span>
                    <span><i class="fas fa-clock"></i> <?php echo $movie['duration'] ?? '?'; ?> دقيقة</span>
                    <?php if ($movie['imdb_rating']): ?>
                    <span><i class="fas fa-star" style="color: gold;"></i> <?php echo number_format($movie['imdb_rating'], 1); ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-eye"></i> <?php echo number_format($movie['views'] ?? 0); ?> مشاهدة</span>
                    <?php if ($movie['country']): ?>
                    <span><i class="fas fa-globe"></i> <?php echo htmlspecialchars($movie['country']); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($movie['quality'])): ?>
                    <span><i class="fas fa-hd"></i> <?php echo htmlspecialchars($movie['quality']); ?></span>
                    <?php endif; ?>
                </div>
                <p class="movie-description">
                    <?php echo nl2br(htmlspecialchars($movie['description'] ?: 'لا يوجد وصف لهذا الفيلم')); ?>
                </p>
            </div>
        </div>

        <!-- أزرار الإجراءات -->
        <?php if ($can_view): ?>
        <div class="action-bar">
            <a href="#video-player" class="action-btn watch-btn" onclick="document.querySelector('.video-player-section').scrollIntoView({behavior: 'smooth'}); return false;">
                <i class="fas fa-play"></i> مشاهدة الفيلم
            </a>
            
            <?php if (!empty($movie_download_servers)): ?>
            <a href="javascript:void(0);" class="action-btn download-main-btn" onclick="showDownloadOptions()">
                <i class="fas fa-download"></i> تحميل (<?php echo count($movie_download_servers); ?>)
            </a>
            <?php endif; ?>
            
            <div class="share-menu">
                <button class="action-btn share-btn" onclick="toggleShareMenu()">
                    <i class="fas fa-share-alt"></i> مشاركة
                </button>
                
                <div class="share-options" id="shareOptions">
                    <?php
                    $share_url = urlencode((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/watch.php?id=' . $id);
                    ?>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" class="share-option facebook"><i class="fab fa-facebook-f"></i></a>
                    <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>" target="_blank" class="share-option twitter"><i class="fab fa-twitter"></i></a>
                    <a href="https://wa.me/?text=<?php echo $share_url; ?>" target="_blank" class="share-option whatsapp"><i class="fab fa-whatsapp"></i></a>
                    <a href="https://t.me/share/url?url=<?php echo $share_url; ?>" target="_blank" class="share-option telegram"><i class="fab fa-telegram-plane"></i></a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- مشغل الفيديو -->
        <div class="video-player-section" id="video-player">
            <div id="videoPlaceholder" class="video-placeholder">
                <i class="fas fa-play-circle"></i>
                <h2 style="margin-bottom: 10px;">اختر سيرفر للمشاهدة</h2>
                <p style="color: var(--text-gray);">اضغط على أي سيرفر من الأسفل لبدء التشغيل</p>
            </div>
            <div id="playerContainer" class="player-container">
                <iframe id="videoIframe" src="" allowfullscreen></iframe>
            </div>
        </div>

        <!-- سيرفرات المشاهدة -->
        <?php if (!empty($movie_watch_servers) && $can_view): ?>
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-play-circle"></i> سيرفرات المشاهدة
                </h2>
                <span class="section-count"><?php echo count($movie_watch_servers); ?> سيرفر</span>
            </div>
            
            <div class="servers-grid" id="watch-servers-container">
                <?php 
                $best_quality = '';
                foreach ($movie_watch_servers as $index => $server):
                    // تحديد أفضل سيرفر (أول 4K)
                    $quality = $server['quality'] ?? $server['server_quality'] ?? 'HD';
                    if (($quality == '4K' || $quality == '4K UHD') && !$best_quality) {
                        $best_quality = true;
                        $is_best = true;
                    } else {
                        $is_best = false;
                    }
                    
                    $server_name = $server['name'] ?? $server['server_name'] ?? 'سيرفر مشاهدة';
                    $server_url = $server['url'] ?? $server['server_url'] ?? $server['embed_code'] ?? '#';
                    $server_quality = $quality;
                    $server_lang = $server['lang'] ?? $server['language'] ?? 'arabic';
                    
                    // تخطي الروابط الفارغة
                    if (empty($server_url) || $server_url == '#') continue;
                ?>
                <div class="server-card <?php echo $is_best ? 'best' : ''; ?>">
                    <div class="server-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="server-name"><?php echo htmlspecialchars($server_name); ?></div>
                    <div class="server-tags">
                        <span class="server-tag"><i class="fas fa-hd"></i> <?php echo htmlspecialchars($server_quality); ?></span>
                        <span class="server-tag"><i class="fas fa-language"></i> <?php echo $server_lang == 'arabic' ? 'عربي' : 'English'; ?></span>
                    </div>
                    <div class="server-status">
                        <i class="fas fa-check-circle"></i> يعمل بكفاءة
                    </div>
                    <button class="server-btn" onclick="playVideo('<?php echo htmlspecialchars(addslashes($server_url)); ?>')">
                        <i class="fas fa-play"></i> مشاهدة الآن
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- سيرفرات التحميل -->
        <?php if (!empty($movie_download_servers) && $can_view): ?>
        <div class="download-section" id="download-section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-download"></i> روابط التحميل
                </h2>
                <span class="section-count"><?php echo count($movie_download_servers); ?> رابط</span>
            </div>
            
            <div class="servers-grid" id="download-servers-container">
                <?php foreach ($movie_download_servers as $server):
                    $server_name = $server['name'] ?? $server['server_name'] ?? 'سيرفر تحميل';
                    $server_url = $server['url'] ?? $server['download_url'] ?? '#';
                    $server_quality = $server['quality'] ?? 'HD';
                    $server_size = $server['size'] ?? '';
                    
                    if (empty($server_url) || $server_url == '#') continue;
                ?>
                <div class="download-card">
                    <div class="server-icon" style="color: var(--success);">
                        <i class="fas fa-cloud-download-alt"></i>
                    </div>
                    <div class="download-name"><?php echo htmlspecialchars($server_name); ?></div>
                    <div class="download-meta">
                        <span><i class="fas fa-hd"></i> <?php echo htmlspecialchars($server_quality); ?></span>
                        <?php if ($server_size): ?>
                        <span><i class="fas fa-database"></i> <?php echo htmlspecialchars($server_size); ?></span>
                        <?php endif; ?>
                    </div>
                    <a href="<?php echo htmlspecialchars($server_url); ?>" class="download-link" target="_blank" rel="nofollow noopener">
                        <i class="fas fa-download"></i> تحميل
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- أفلام مشابهة -->
        <?php if (!empty($similar_movies_data)): ?>
        <div class="similar-section">
            <h2 class="section-title" style="margin-bottom: 15px;">
                <i class="fas fa-film"></i> أفلام مشابهة
            </h2>
            <div class="similar-grid">
                <?php foreach ($similar_movies_data as $similar): ?>
                <a href="movie.php?id=<?php echo $similar['id']; ?>" class="similar-card">
                    <img src="<?php echo $similar['poster'] ?? 'https://via.placeholder.com/300x450?text=No+Image'; ?>" class="similar-poster">
                    <div class="similar-info">
                        <div class="similar-title"><?php echo htmlspecialchars($similar['title']); ?></div>
                        <div class="similar-year"><?php echo $similar['year']; ?> • ⭐ <?php echo number_format($similar['imdb_rating'], 1); ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>

    <!-- مودال خيارات التحميل -->
    <div id="downloadModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-download"></i> خيارات التحميل</h3>
                <span class="close-modal" onclick="closeDownloadModal()">&times;</span>
            </div>
            <div class="modal-body" id="download-options-container">
                <div style="text-align: center; padding: 20px;">
                    <i class="fas fa-spinner fa-spin"></i> جاري تحميل خيارات التحميل...
                </div>
            </div>
        </div>
    </div>

    <script>
        function playVideo(url) {
            console.log('🔍 تشغيل الفيديو:', url);
            
            if (!url || url === '' || url === '#') {
                alert('❌ رابط المشاهدة غير متاح');
                return;
            }
            
            const placeholder = document.getElementById('videoPlaceholder');
            const player = document.getElementById('playerContainer');
            const iframe = document.getElementById('videoIframe');
            
            if (!player || !placeholder) return;
            
            // تنظيف الرابط
            url = url.replace(/^['"]|['"]$/g, '');
            
            // تنظيف المحتوى
            player.innerHTML = '';
            
            // تحديد نوع الرابط
            if (url.match(/\.(mp4|mkv|avi|mov|wmv|flv|webm|m4v|3gp|ogv|ogg)(\?.*)?$/i)) {
                // روابط فيديو مباشرة
                const videoElement = document.createElement('video');
                videoElement.controls = true;
                videoElement.autoplay = true;
                videoElement.style.width = '100%';
                videoElement.style.height = '100%';
                videoElement.style.backgroundColor = '#000';
                
                const source = document.createElement('source');
                source.src = url;
                
                if (url.match(/\.mp4/i)) source.type = 'video/mp4';
                else if (url.match(/\.mkv/i)) source.type = 'video/x-matroska';
                else if (url.match(/\.webm/i)) source.type = 'video/webm';
                else source.type = 'video/mp4';
                
                videoElement.appendChild(source);
                player.appendChild(videoElement);
            } else if (url.includes('youtube.com') || url.includes('youtu.be')) {
                let videoId = '';
                if (url.includes('youtube.com/watch?v=')) {
                    videoId = url.split('v=')[1].split('&')[0];
                } else if (url.includes('youtu.be/')) {
                    videoId = url.split('youtu.be/')[1].split('?')[0];
                } else if (url.includes('/embed/')) {
                    videoId = url.split('/embed/')[1].split('?')[0];
                }
                
                if (videoId) {
                    iframe.src = 'https://www.youtube.com/embed/' + videoId;
                } else {
                    iframe.src = url;
                }
                player.appendChild(iframe);
            } else if (url.includes('vimeo.com')) {
                let videoId = url.split('vimeo.com/')[1].split('?')[0];
                iframe.src = 'https://player.vimeo.com/video/' + videoId;
                player.appendChild(iframe);
            } else {
                iframe.src = url;
                player.appendChild(iframe);
            }
            
            placeholder.style.display = 'none';
            player.classList.add('active');
            
            // تمرير للمشغل
            setTimeout(() => {
                window.scrollTo({
                    top: player.offsetTop - 20,
                    behavior: 'smooth'
                });
            }, 500);
        }
        
        function toggleShareMenu() {
            const menu = document.getElementById('shareOptions');
            menu.classList.toggle('active');
        }
        
        // إغلاق قائمة المشاركة عند النقر خارجها
        document.addEventListener('click', function(event) {
            const menu = document.getElementById('shareOptions');
            const shareBtn = document.querySelector('.share-btn');
            
            if (menu && shareBtn && !shareBtn.contains(event.target) && !menu.contains(event.target)) {
                menu.classList.remove('active');
            }
        });
        
        function showDownloadOptions() {
            const modal = document.getElementById('downloadModal');
            const container = document.getElementById('download-options-container');
            
            <?php if (!empty($movie_download_servers)): ?>
            let html = '<div class="download-links-grid">';
            <?php foreach ($movie_download_servers as $server): 
                $server_name = addslashes($server['name'] ?? $server['server_name'] ?? 'سيرفر تحميل');
                $server_url = addslashes($server['url'] ?? $server['download_url'] ?? '#');
                $server_quality = addslashes($server['quality'] ?? 'HD');
                $server_size = addslashes($server['size'] ?? '');
            ?>
                <?php if ($server_url != '#'): ?>
                html += `
                    <a href="<?php echo $server_url; ?>" class="download-link-item" target="_blank" rel="nofollow noopener">
                        <div class="download-link-name"><?php echo $server_name; ?></div>
                        <div class="download-link-meta">
                            <span class="quality"><?php echo $server_quality; ?></span>
                            <?php if (!empty($server['size'])): ?>
                            <span class="size"><?php echo $server_size; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                `;
                <?php endif; ?>
            <?php endforeach; ?>
            html += '</div>';
            container.innerHTML = html;
            <?php else: ?>
            container.innerHTML = '<p style="text-align: center; color: #b3b3b3;">لا توجد روابط تحميل متاحة</p>';
            <?php endif; ?>
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeDownloadModal() {
            document.getElementById('downloadModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // إغلاق المودال بالنقر خارج المحتوى
        document.getElementById('downloadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDownloadModal();
            }
        });
        
        // إغلاق المودال بالضغط على ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDownloadModal();
                document.getElementById('shareOptions')?.classList.remove('active');
            }
        });
    </script>
</body>
</html>