<?php
// movie-pro.php - صفحة فيلم مع سيرفرات مشاهدة وتحميل ونظام عضوية
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/membership-check.php'; // إضافة نظام العضوية

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات الفيلم
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    header('Location: 404.php');
    exit;
}

// التحقق من صلاحية المشاهدة
$user_level = getUserMembershipLevel($_SESSION['user_id'] ?? 0);
$required_level = $movie['membership_level'] ?? 'basic';
$can_view = canViewContent($user_level, $required_level);

if (!$can_view) {
    // تخزين الرابط المطلوب في الجلسة
    $_SESSION['requested_url'] = $_SERVER['REQUEST_URI'];
}

// زيادة عدد المشاهدات (فقط إذا كان مسموح بالمشاهدة)
if ($can_view) {
    $pdo->prepare("UPDATE movies SET views = views + 1 WHERE id = ?")->execute([$id]);
}

// =============================================
// جلب بيانات إضافية من TMDB API
// =============================================
$tmdb_id = $movie['tmdb_id'] ?? null;
$actors = [];
$crew = [];
$similar_movies = [];
$videos = [];
$movie_details = [];

if ($tmdb_id) {
    // جلب تفاصيل إضافية من TMDB
    $details_url = "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,similar,videos,reviews,recommendations";
    $details_data = tmdb_request($details_url);
    
    if ($details_data) {
        $movie_details = $details_data;
        
        // جلب طاقم العمل (الممثلين)
        if (isset($details_data['credits']['cast'])) {
            $actors = array_slice($details_data['credits']['cast'], 0, 8);
        }
        
        // جلب طاقم العمل (Crew)
        if (isset($details_data['credits']['crew'])) {
            $crew = array_slice($details_data['credits']['crew'], 0, 5);
        }
        
        // جلب أفلام مشابهة
        if (isset($details_data['similar']['results'])) {
            $similar_movies = array_slice($details_data['similar']['results'], 0, 8);
        }
        
        // جلب توصيات
        if (isset($details_data['recommendations']['results'])) {
            $recommendations = array_slice($details_data['recommendations']['results'], 0, 8);
        }
        
        // جلب الفيديوهات (إعلان)
        if (isset($details_data['videos']['results'])) {
            $videos = array_filter($details_data['videos']['results'], function($video) {
                return $video['site'] == 'YouTube' && ($video['type'] == 'Trailer' || $video['type'] == 'Teaser');
            });
        }
    }
}

// =============================================
// جلب سيرفرات المشاهدة من قاعدة البيانات
// =============================================
$stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
$stmt->execute([$id]);
$watch_servers = $stmt->fetchAll();

// الحصول على TMDB ID أو IMDB ID
$tmdb_id = $movie['tmdb_id'] ?? $movie['tmbd_id'] ?? null;
$imdb_id = $movie['imdb_id'] ?? null;

// إذا لم يكن TMDB ID موجوداً، نحاول الحصول عليه من API
if (!$tmdb_id && !empty($movie['title'])) {
    $search_url = "https://api.themoviedb.org/3/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($movie['title']);
    if (!empty($movie['year'])) {
        $search_url .= "&year=" . $movie['year'];
    }
    
    $search_result = tmdb_request($search_url);
    if ($search_result && !empty($search_result['results'][0]['id'])) {
        $tmdb_id = $search_result['results'][0]['id'];
    }
}

// سيرفرات مشاهدة حقيقية
$watch_servers = [];

if ($tmdb_id) {
    $watch_servers = [
        [
            'id' => 1,
            'server_name' => '🎬 Vidsrc.to - 4K UHD',
            'server_url' => 'https://vidsrc.to/embed/movie/' . $tmdb_id,
            'embed_code' => '<iframe src="https://vidsrc.to/embed/movie/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '4K UHD',
            'language' => 'العربية',
            'icon' => 'fas fa-crown'
        ],
        [
            'id' => 2,
            'server_name' => '🎥 2Embed - 1080p HD',
            'server_url' => 'https://www.2embed.cc/embed/' . $tmdb_id,
            'embed_code' => '<iframe src="https://www.2embed.cc/embed/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '1080p HD',
            'language' => 'العربية',
            'icon' => 'fas fa-film'
        ],
        [
            'id' => 3,
            'server_name' => '📺 Embed.su - 1080p HD',
            'server_url' => 'https://embed.su/embed/movie/' . $tmdb_id,
            'embed_code' => '<iframe src="https://embed.su/embed/movie/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '1080p HD',
            'language' => 'English',
            'icon' => 'fas fa-play'
        ],
        [
            'id' => 4,
            'server_name' => '⚡ VidLink.pro - 4K UHD',
            'server_url' => 'https://vidlink.pro/movie/' . $tmdb_id,
            'embed_code' => '<iframe src="https://vidlink.pro/movie/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '4K UHD',
            'language' => 'العربية',
            'icon' => 'fas fa-bolt'
        ]
    ];
} else {
    $watch_servers = [
        [
            'id' => 1,
            'server_name' => '🔍 بحث في Vidsrc.to',
            'server_url' => 'https://vidsrc.to/embed/movie/tt' . rand(1000000, 9999999),
            'embed_code' => '<iframe src="https://vidsrc.to/embed/movie/tt' . rand(1000000, 9999999) . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => 'مباشر',
            'language' => 'العربية',
            'icon' => 'fas fa-search'
        ],
        [
            'id' => 2,
            'server_name' => '🔍 بحث في Embed.su',
            'server_url' => 'https://embed.su/embed/movie/tt' . rand(1000000, 9999999),
            'embed_code' => '<iframe src="https://embed.su/embed/movie/tt' . rand(1000000, 9999999) . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => 'مباشر',
            'language' => 'English',
            'icon' => 'fas fa-search'
        ]
    ];
}

$servers_count = count($watch_servers);

// =============================================
// جلب سيرفرات التحميل
// =============================================
$stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ? AND is_valid = 1 ORDER BY quality DESC");
$stmt->execute([$id]);
$download_servers = $stmt->fetchAll();

if (empty($download_servers)) {
    $download_servers = [
        [
            'server_name' => 'ميديا فاير',
            'download_url' => '#',
            'quality' => '1080p',
            'size' => '1.8 GB'
        ],
        [
            'server_name' => 'جوجل درايف',
            'download_url' => '#',
            'quality' => '720p',
            'size' => '950 MB'
        ]
    ];
}

// جلب أفلام ذات صلة
$related_movies_db = [];
if (!empty($movie['genre'])) {
    $genre_parts = explode('،', $movie['genre']);
    $first_genre = trim($genre_parts[0]);
    
    $stmt = $pdo->prepare("
        SELECT * FROM movies 
        WHERE (genre LIKE ? OR genre LIKE ?) 
        AND id != ? 
        AND status = 'published' 
        LIMIT 8
    ");
    $stmt->execute(["%{$first_genre}%", "%{$first_genre}%", $id]);
    $related_movies_db = $stmt->fetchAll();
}

$all_similar = [];
if (!empty($similar_movies)) {
    $all_similar = $similar_movies;
} elseif (!empty($recommendations)) {
    $all_similar = $recommendations;
} elseif (!empty($related_movies_db)) {
    $all_similar = $related_movies_db;
}

// جلب المخرج
$director = '';
if (!empty($crew)) {
    foreach ($crew as $member) {
        if ($member['job'] == 'Director') {
            $director = $member['name'];
            break;
        }
    }
}
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_servers') {
    header('Content-Type: application/json');
    
    $movie_id = (int)($_GET['id'] ?? 0);
    
    // جلب روابط المشاهدة
    $watch_servers = [];
    $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
    $stmt->execute([$movie_id]);
    $watch_servers = $stmt->fetchAll();
    
    // جلب روابط التحميل
    $download_servers = [];
    $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ? AND is_valid = 1 ORDER BY quality DESC");
    $stmt->execute([$movie_id]);
    $download_servers = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'watch_servers' => $watch_servers,
        'download_servers' => $download_servers
    ]);
    exit;}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($movie['title']); ?> - ويزي برو</title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_substr($movie['description'] ?: ($movie_details['overview'] ?? ''), 0, 160)); ?>">
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --primary-glow: rgba(229, 9, 20, 0.3);
            --secondary: #0f0f0f;
            --dark: #0a0a0a;
            --light: #1a1a1a;
            --lighter: #2a2a2a;
            --text: #fff;
            --text-gray: #b3b3b3;
            --border: #333;
            --gold: gold;
            --gold-glow: rgba(255, 215, 0, 0.2);
            --success: #27ae60;
            --success-glow: rgba(39, 174, 96, 0.2);
            --server-1: #ff6b6b;
            --server-2: #4ecdc4;
            --server-3: #45b7d1;
            --server-4: #96ceb4;
            --server-5: #ffeaa7;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-aos] {
            pointer-events: none;
        }
        
        [data-aos].aos-animate {
            pointer-events: auto;
        }
        
        /* ===== الهيدر المتحرك ===== */
        .header {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(229, 9, 20, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
            transition: var(--transition);
        }
        
        .header.scrolled {
            padding: 10px 40px;
            background: rgba(10, 10, 10, 0.98);
            border-bottom-color: var(--primary);
        }
        
        .logo {
            position: relative;
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -1px;
            position: relative;
            z-index: 2;
        }
        
        .logo span {
            color: #fff;
        }
        
        .logo::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 40px;
            height: 40px;
            background: rgba(229, 9, 20, 0.1);
            border-radius: 50%;
            filter: blur(10px);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
            50% { transform: translate(-50%, -50%) scale(1.5); opacity: 0.2; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 0.5; }
        }
        
        .nav-list {
            display: flex;
            gap: 30px;
            list-style: none;
        }
        
        .nav-list a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            position: relative;
            padding: 5px 0;
            transition: var(--transition);
        }
        
        .nav-list a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition);
        }
        
        .nav-list a:hover::after {
            width: 100%;
        }
        
        .nav-list a:hover {
            color: var(--primary);
            text-shadow: 0 0 10px var(--primary-glow);
        }
        
        /* ===== حاوية الفيلم ===== */
        .movie-wrapper {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* ===== الهيرو المتحرك ===== */
        .movie-hero {
            position: relative;
            height: 70vh;
            min-height: 600px;
            border-radius: 30px;
            overflow: hidden;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            animation: heroGlow 4s infinite alternate;
        }
        
        @keyframes heroGlow {
            0% { box-shadow: 0 20px 40px rgba(229,9,20,0.2); }
            100% { box-shadow: 0 20px 60px rgba(229,9,20,0.5); }
        }
        
        .hero-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scale(1);
            transition: transform 10s ease;
            animation: zoomIn 20s infinite alternate;
        }
        
        @keyframes zoomIn {
            0% { transform: scale(1); }
            100% { transform: scale(1.1); }
        }
        
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to right,
                rgba(10,10,10,0.95) 20%,
                rgba(10,10,10,0.7) 50%,
                rgba(10,10,10,0.3) 80%
            );
            display: flex;
            align-items: center;
            padding: 0 80px;
        }
        
        .hero-content {
            max-width: 700px;
            animation: slideInRight 1s ease;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .hero-title {
            font-size: 64px;
            font-weight: 900;
            color: #fff;
            margin-bottom: 20px;
            line-height: 1.2;
            text-shadow: 0 0 30px rgba(0,0,0,0.5);
            position: relative;
            display: inline-block;
        }
        
        .hero-title::before {
            content: '';
            position: absolute;
            bottom: 10px;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, var(--primary), transparent);
            border-radius: 4px;
            animation: titleLine 2s infinite;
        }
        
        @keyframes titleLine {
            0% { width: 100%; opacity: 1; }
            50% { width: 80%; opacity: 0.7; }
            100% { width: 100%; opacity: 1; }
        }
        
        .hero-badges {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }
        
        .badge {
            padding: 10px 20px;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .badge:hover {
            transform: translateY(-2px);
            border-color: var(--primary);
            box-shadow: 0 5px 15px var(--primary-glow);
        }
        
        .badge i {
            color: var(--primary);
        }
        
        .badge.hd {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .badge.hd i {
            color: white;
        }
        
        .badge.age {
            background: #f39c12;
            border-color: #f39c12;
            color: #000;
        }
        
        .badge.age i {
            color: #000;
        }
        
        /* ===== قسم الوصف أسفل الهيرو ===== */
        .description-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 40px;
            border: 1px solid var(--primary);
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .description-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at top right, var(--primary-glow), transparent 70%);
            opacity: 0.5;
        }
        
        .description-section::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 150px;
            height: 150px;
            background: var(--primary);
            filter: blur(80px);
            opacity: 0.2;
        }
        
        .description-content {
            position: relative;
            z-index: 2;
            max-width: 900px;
            margin: 0 auto;
            text-align: center;
        }
        
        .description-quote {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 20px;
            font-weight: 700;
            opacity: 0.8;
        }
        
        .description-text {
            color: var(--text);
            line-height: 1.8;
            font-size: 18px;
            margin-bottom: 30px;
        }
        
        .description-text::first-letter {
            font-size: 48px;
            color: var(--primary);
            font-weight: 800;
            margin-left: 5px;
        }
        
        .description-meta {
            display: flex;
            justify-content: center;
            gap: 30px;
            flex-wrap: wrap;
            color: var(--text-gray);
            font-size: 16px;
        }
        
        .description-meta span {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .description-meta i {
            color: var(--primary);
        }
        
        /* ===== قسم سيرفرات المشاهدة ===== */
        .servers-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid var(--primary);
            position: relative;
            overflow: hidden;
        }
        
        .servers-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .section-title {
            font-size: 28px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .section-title i {
            font-size: 32px;
        }
        
        .section-badge {
            background: #252525;
            padding: 8px 20px;
            border-radius: 30px;
            color: var(--text-gray);
            font-size: 16px;
            border: 1px solid var(--border);
        }
        
        .servers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        
        .server-card {
            background: #252525;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .server-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, transparent, rgba(229,9,20,0.1), transparent);
            transform: translateX(-100%);
            transition: 0.5s;
        }
        
        .server-card:hover::before {
            transform: translateX(100%);
        }
        
        .server-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px var(--primary-glow);
        }
        
        .server-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }
        
        .server-card:nth-child(1) .server-icon { color: var(--server-1); }
        .server-card:nth-child(2) .server-icon { color: var(--server-2); }
        .server-card:nth-child(3) .server-icon { color: var(--server-3); }
        .server-card:nth-child(4) .server-icon { color: var(--server-4); }
        .server-card:nth-child(5) .server-icon { color: var(--server-5); }
        
        .server-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .server-meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            color: var(--text-gray);
            font-size: 14px;
        }
        
        .server-quality {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .server-language {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .server-language i {
            color: var(--primary);
        }
        
        .server-btn {
            width: 100%;
            padding: 12px;
            background: transparent;
            border: 2px solid var(--primary);
            color: white;
            border-radius: 30px;
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
        
        /* ===== قسم التحميل ===== */
        .download-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 50px;
            border: 1px solid var(--success);
            position: relative;
            overflow: hidden;
        }
        
        .download-section::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, var(--success-glow) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }
        
        .download-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        
        .download-card {
            background: #252525;
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            border: 1px solid #333;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .download-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--success);
            box-shadow: 0 20px 40px var(--success-glow);
        }
        
        .download-icon {
            font-size: 48px;
            color: var(--success);
            margin-bottom: 15px;
            animation: bounce 2s infinite;
        }
        
        .download-card:nth-child(1) .download-icon { color: #ff6b6b; }
        .download-card:nth-child(2) .download-icon { color: #4ecdc4; }
        .download-card:nth-child(3) .download-icon { color: #45b7d1; }
        .download-card:nth-child(4) .download-icon { color: #96ceb4; }
        
        .download-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .download-quality {
            color: var(--success);
            margin-bottom: 5px;
        }
        
        .download-size {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .download-link {
            display: inline-block;
            padding: 12px 30px;
            background: var(--success);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: var(--transition);
            border: none;
            cursor: pointer;
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .download-link:hover {
            background: #219a52;
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(39, 174, 96, 0.6);
        }
        
        .download-link i {
            margin-left: 8px;
            transition: transform 0.3s ease;
        }
        
        .download-link:hover i {
            transform: translateY(-3px);
        }
        
        /* ===== باقي التنسيقات ===== */
        .movie-main {
            display: flex;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .movie-poster-section {
            flex: 0 0 300px;
            position: relative;
            perspective: 1000px;
        }
        
        .movie-poster {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            border: 2px solid transparent;
            transition: var(--transition);
            transform-style: preserve-3d;
            cursor: pointer;
        }
        
        .movie-poster:hover {
            transform: rotateY(5deg) scale(1.05);
            border-color: var(--primary);
            box-shadow: 0 30px 60px var(--primary-glow);
        }
        
        .poster-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, var(--primary-glow) 0%, transparent 70%);
            filter: blur(20px);
            z-index: -1;
            opacity: 0;
            transition: var(--transition);
        }
        
        .movie-poster:hover + .poster-glow {
            opacity: 1;
        }
        
        .movie-info-section {
            flex: 1;
        }
        
        .movie-title {
            font-size: 42px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 15px;
            animation: glow 3s infinite;
        }
        
        @keyframes glow {
            0% { text-shadow: 0 0 10px rgba(229,9,20,0.3); }
            50% { text-shadow: 0 0 20px rgba(229,9,20,0.6); }
            100% { text-shadow: 0 0 10px rgba(229,9,20,0.3); }
        }
        
        .movie-title-en {
            font-size: 24px;
            color: var(--text-gray);
            margin-bottom: 25px;
        }
        
        /* ===== شارات العضوية ===== */
        .membership-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
            margin-right: 8px;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }

        .membership-badge.premium {
            background: linear-gradient(135deg, #e50914, #ff4d4d);
            color: white;
            box-shadow: 0 2px 8px rgba(229, 9, 20, 0.3);
        }

        .membership-badge.vip {
            background: linear-gradient(135deg, gold, #ffd700);
            color: black;
            box-shadow: 0 2px 8px rgba(255, 215, 0, 0.4);
        }

        .membership-badge i {
            margin-left: 3px;
            font-size: 10px;
        }

        .membership-badge {
            animation: badgeGlow 2s infinite;
        }

        @keyframes badgeGlow {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .membership-required-bar {
            background: linear-gradient(135deg, #2a1a1a, #1a1a1a);
            border: 2px solid;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .membership-required-bar.vip {
            border-color: gold;
        }

        .membership-required-bar.premium {
            border-color: #e50914;
        }
        
        /* تحسين مشغل الفيديو */
        .video-player {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.95);
            backdrop-filter: blur(10px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
        }

        .video-player.active {
            display: flex;
        }

        .player-container {
            width: 90%;
            max-width: 1200px;
            position: relative;
        }

        .player-frame {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            border-radius: 10px;
            overflow: hidden;
            background: #000;
            box-shadow: 0 0 30px rgba(229,9,20,0.5);
        }

        .player-frame iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        .close-player {
            position: absolute;
            top: -40px;
            right: 0;
            background: #e50914;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            z-index: 10000;
        }

        .close-player:hover {
            background: #b20710;
        }
        
        .rating-bar {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            padding: 20px;
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            border: 1px solid #333;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }
        
        .rating-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), #ff6b6b, var(--primary));
            animation: slide 2s infinite;
        }
        
        @keyframes slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .rating-item {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }
        
        .rating-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: conic-gradient(gold <?php echo ($movie['imdb_rating'] ?? 0) * 10; ?>deg, #333 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            animation: rotate 10s linear infinite;
        }
        
        .rating-circle::before {
            content: '';
            position: absolute;
            width: 50px;
            height: 50px;
            background: #1a1a1a;
            border-radius: 50%;
        }
        
        .rating-circle span {
            position: relative;
            z-index: 2;
            font-weight: 800;
            font-size: 18px;
            color: gold;
        }
        
        .rating-info {
            display: flex;
            flex-direction: column;
        }
        
        .rating-label {
            color: var(--text-gray);
            font-size: 14px;
        }
        
        .rating-value {
            font-size: 24px;
            font-weight: 800;
            color: gold;
        }
        
        .rating-stars {
            display: flex;
            gap: 5px;
            margin-top: 5px;
        }
        
        .rating-stars i {
            color: gold;
            font-size: 16px;
            animation: starPulse 1s infinite;
        }
        
        @keyframes starPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .info-card {
            background: #252525;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #333;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px var(--primary-glow);
        }
        
        .info-icon {
            font-size: 24px;
            color: var(--primary);
            margin-bottom: 10px;
        }
        
        .info-label {
            color: var(--text-gray);
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 18px;
            font-weight: 700;
        }
        
        .actors-section {
            margin-bottom: 30px;
        }
        
        .actors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .actor-card {
            background: #252525;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: var(--transition);
        }
        
        .actor-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .actor-image {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
        }
        
        .actor-info {
            padding: 15px;
        }
        
        .actor-name {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .actor-character {
            color: var(--primary);
            font-size: 12px;
        }
        
        .action-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            position: relative;
            padding: 15px 30px;
            border: none;
            border-radius: 60px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            min-width: 160px;
        }
        
        .action-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        
        .action-btn:hover::before {
            left: 100%;
        }
        
        .watch-btn {
            background: linear-gradient(135deg, #e50914, #ff4d4d);
            color: white;
            box-shadow: 0 5px 20px rgba(229, 9, 20, 0.5);
        }
        
        .download-main-btn {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            color: white;
        }
        
        .share-btn {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .share-menu {
            position: relative;
        }
        
        .share-options {
            position: absolute;
            top: 100%;
            left: 0;
            transform: translateY(10px);
            display: flex;
            gap: 10px;
            padding: 15px;
            background: rgba(20, 20, 20, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 50px;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            z-index: 100;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .share-options.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .share-option {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .share-option:hover {
            transform: scale(1.2);
        }
        
        .share-option.facebook { background: #1877f2; }
        .share-option.twitter { background: #1da1f2; }
        .share-option.whatsapp { background: #25d366; }
        .share-option.telegram { background: #0088cc; }
        
        .similar-section {
            margin-top: 50px;
        }
        
        .similar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }
        
        .similar-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            text-decoration: none;
            color: white;
            border: 1px solid #333;
            transition: var(--transition);
        }
        
        .similar-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .similar-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        
        .similar-info {
            padding: 15px;
        }
        
        .similar-title {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .similar-meta {
            display: flex;
            justify-content: space-between;
            color: var(--text-gray);
            font-size: 13px;
        }
        
        .footer {
            background: linear-gradient(to top, #0a0a0a, #0f0f0f);
            padding: 60px 40px 30px;
            text-align: center;
            color: var(--text-gray);
            border-top: 1px solid rgba(229,9,20,0.3);
            margin-top: 50px;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .nav-list { display: none; }
            .hero-title { font-size: 36px; }
            .movie-main { flex-direction: column; }
            .movie-poster-section { width: 200px; margin: 0 auto; }
            .servers-grid { grid-template-columns: 1fr; }
            .download-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <header class="header" id="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <nav>
            <ul class="nav-list">
                <li><a href="index.php">الرئيسية</a></li>
                <li><a href="movies.php">أفلام</a></li>
                <li><a href="series.php">مسلسلات</a></li>
                <li><a href="live.php">بث مباشر</a></li>
                <li><a href="anime-series.php">أنمي</a></li>
                <li><a href="free.php">مجاني</a></li>
            </ul>
        </nav>
    </header>

    <div class="movie-wrapper">
        <?php if (!$can_view): ?>
            <!-- عرض شريط الاشتراك إذا لم يكن مسموحاً بالمشاهدة -->
            <?php echo showMembershipRequiredBar($required_level, $_SERVER['REQUEST_URI']); ?>
        <?php endif; ?>

        <!-- الهيرو المتحرك -->
        <div class="movie-hero" data-aos="fade-down">
            <img src="<?php echo $movie['backdrop'] ?? ($movie_details['backdrop_path'] ? 'https://image.tmdb.org/t/p/original' . $movie_details['backdrop_path'] : 'https://image.tmdb.org/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png'); ?>" 
                 class="hero-backdrop" alt="">
            <div class="hero-overlay">
                <div class="hero-content">
                    <h1 class="hero-title"><?php echo strtoupper(htmlspecialchars($movie['title'] ?: ($movie_details['title'] ?? ''))); ?></h1>
                    <div class="hero-badges">
                        <span class="badge"><i class="fas fa-globe"></i> <?php echo $movie['country'] ?? ($movie_details['production_countries'][0]['name'] ?? 'عالمي'); ?></span>
                        <span class="badge"><i class="fas fa-calendar-alt"></i> <?php echo $movie['year'] ?? (isset($movie_details['release_date']) ? substr($movie_details['release_date'], 0, 4) : '2024'); ?></span>
                        <span class="badge"><i class="fas fa-clock"></i> <?php echo $movie['duration'] ?? ($movie_details['runtime'] ?? '120'); ?> دقيقة</span>
                        <?php if (!empty($movie['quality'])): ?>
                        <span class="badge hd"><i class="fas fa-hd"></i> <?php echo $movie['quality']; ?></span>
                        <?php endif; ?>
                        <!-- عرض شارة العضوية في الهيرو -->
                        <?php echo getMembershipBadge($movie['membership_level'] ?? 'basic'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم الوصف -->
        <div class="description-section" data-aos="fade-up">
            <div class="description-content">
                <div class="description-quote">
                    <i class="fas fa-quote-right"></i> قصة الفيلم <i class="fas fa-quote-left"></i>
                </div>
                <div class="description-text">
                    <?php echo nl2br(htmlspecialchars($movie['description'] ?: ($movie_details['overview'] ?? 'لا يوجد وصف متاح لهذا الفيلم'))); ?>
                </div>
                <?php if (!empty($director)): ?>
                <div class="description-meta">
                    <span><i class="fas fa-video"></i> إخراج: <?php echo htmlspecialchars($director); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- المحتوى الرئيسي -->
        <div class="movie-main">
            <div class="movie-poster-section" data-aos="fade-left">
                <img src="<?php echo $movie['poster'] ?? ($movie_details['poster_path'] ? 'https://image.tmdb.org/t/p/w500' . $movie_details['poster_path'] : 'https://via.placeholder.com/300x450?text=No+Poster'); ?>" 
                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                <div class="poster-glow"></div>
            </div>
            
            <div class="movie-info-section" data-aos="fade-right">
                <h1 class="movie-title"><?php echo htmlspecialchars($movie['title'] ?: ($movie_details['title'] ?? '')); ?>
                    <?php echo getMembershipBadge($movie['membership_level'] ?? 'basic'); ?>
                </h1>
                
                <div class="rating-bar" data-aos="zoom-in">
                    <?php 
                    $rating = $movie['imdb_rating'] ?? ($movie_details['vote_average'] ?? 0);
                    if ($rating > 0): 
                    ?>
                    <div class="rating-item">
                        <div class="rating-circle">
                            <span><?php echo number_format($rating, 1); ?></span>
                        </div>
                        <div class="rating-info">
                            <span class="rating-label">التقييم</span>
                            <span class="rating-value"><?php echo number_format($rating, 1); ?>/10</span>
                            <div class="rating-stars">
                                <?php
                                $stars = round($rating / 2);
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $stars) echo '<i class="fas fa-star"></i>';
                                    elseif ($i - 0.5 <= $stars) echo '<i class="fas fa-star-half-alt"></i>';
                                    else echo '<i class="far fa-star"></i>';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="rating-item">
                        <div class="rating-circle">
                            <span><?php echo number_format($movie['views'] ?? 0); ?></span>
                        </div>
                        <div class="rating-info">
                            <span class="rating-label">المشاهدات</span>
                            <span class="rating-value"><?php echo number_format($movie['views'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="info-grid" data-aos="fade-up">
                    <?php if (!empty($movie['genre']) || !empty($movie_details['genres'])): ?>
                    <div class="info-card">
                        <div class="info-icon"><i class="fas fa-tag"></i></div>
                        <div class="info-label">التصنيف</div>
                        <div class="info-value">
                            <?php 
                            if (!empty($movie['genre'])) echo $movie['genre'];
                            elseif (!empty($movie_details['genres'])) {
                                $genres = array_column($movie_details['genres'], 'name');
                                echo implode('، ', $genres);
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                        <div class="info-icon"><i class="fas fa-eye"></i></div>
                        <div class="info-label">المشاهدات</div>
                        <div class="info-value"><?php echo number_format($movie['views'] ?? 0); ?></div>
                    </div>
                </div>
                
                <?php if (!empty($actors) && $can_view): ?>
                <div class="actors-section" data-aos="fade-up">
                    <h2 class="section-title"><i class="fas fa-users"></i> طاقم التمثيل</h2>
                    <div class="actors-grid">
                        <?php foreach ($actors as $actor): ?>
                        <div class="actor-card">
                            <img src="<?php echo isset($actor['profile_path']) ? 'https://image.tmdb.org/t/p/w200' . $actor['profile_path'] : 'https://via.placeholder.com/200x200?text=No+Image'; ?>" 
                                 class="actor-image" alt="<?php echo $actor['name']; ?>">
                            <div class="actor-info">
                                <div class="actor-name"><?php echo htmlspecialchars($actor['name']); ?></div>
                                <div class="actor-character"><?php echo htmlspecialchars($actor['character'] ?? ''); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- أزرار سريعة -->
                <?php if ($can_view): ?>
    <div class="action-bar" data-aos="fade-up">
        <a href="watch.php?id=<?php echo $id; ?>" class="action-btn watch-btn">
            <i class="fas fa-play"></i> مشاهدة الفيلم
        </a>
        
        <?php
        // جلب أول رابط تحميل للفيلم
        $stmt = $pdo->prepare("SELECT download_url FROM download_servers WHERE item_type = 'movie' AND item_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $download_link = $stmt->fetchColumn();
        
        // إذا لم يوجد رابط تحميل، استخدم رابط صفحة التحميل
        if (!$download_link) {
            $download_link = "download.php?id=" . $id;
        }
        ?>
        
        <a href="<?php echo $download_link; ?>" class="action-btn download-main-btn" <?php if (strpos($download_link, 'download.php') === false): ?>target="_blank"<?php endif; ?>>
            <i class="fas fa-download"></i> تحميل
        </a>
        
        <div class="share-menu">
            <button class="action-btn share-btn" onclick="toggleShareMenu()">
                <i class="fas fa-share-alt"></i> مشاركة
            </button>
            
            <div class="share-options" id="shareOptions">
                <?php
                $share_url = urlencode((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/movie-pro.php?id=' . $id);
                ?>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share_url; ?>" target="_blank" class="share-option facebook"><i class="fab fa-facebook-f"></i></a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo $share_url; ?>" target="_blank" class="share-option twitter"><i class="fab fa-twitter"></i></a>
                <a href="https://wa.me/?text=<?php echo $share_url; ?>" target="_blank" class="share-option whatsapp"><i class="fab fa-whatsapp"></i></a>
                <a href="https://t.me/share/url?url=<?php echo $share_url; ?>" target="_blank" class="share-option telegram"><i class="fab fa-telegram-plane"></i></a>
            </div>
        </div>
    </div>
<?php endif; ?>
            </div>
        </div>

        <?php if ($can_view): ?>
        <!-- ===== قسم سيرفرات المشاهدة ===== -->
        

        <!-- ===== أعمال مشابهة ===== -->
        <?php if (!empty($all_similar)): ?>
        <div class="similar-section" data-aos="fade-up">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-film"></i> أعمال مشابهة
                </h2>
                <span class="section-badge"><?php echo min(8, count($all_similar)); ?> فيلم</span>
            </div>
            
            <div class="similar-grid">
                <?php 
                $count = 0;
                foreach ($all_similar as $similar): 
                    if ($count++ >= 8) break;
                    
                    if (isset($similar['id']) && !isset($similar['title'])) {
                        $similar_title = $similar['title'];
                        $similar_poster = $similar['poster'] ?? '';
                        $similar_year = $similar['year'] ?? '';
                        $similar_rating = $similar['imdb_rating'] ?? '';
                        $link = "movie-pro.php?id=" . $similar['id'];
                    } else {
                        $similar_title = $similar['title'] ?? '';
                        $similar_poster = isset($similar['poster_path']) ? 'https://image.tmdb.org/t/p/w300' . $similar['poster_path'] : '';
                        $similar_year = isset($similar['release_date']) ? substr($similar['release_date'], 0, 4) : '';
                        $similar_rating = $similar['vote_average'] ?? '';
                        
                        $stmt = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
                        $stmt->execute([$similar['id'] ?? 0]);
                        $local_movie = $stmt->fetch();
                        $link = $local_movie ? "movie-pro.php?id=" . $local_movie['id'] : "#";
                    }
                ?>
                <a href="<?php echo $link; ?>" class="similar-card">
                    <img src="<?php echo $similar_poster ?: 'https://via.placeholder.com/300x450?text=No+Image'; ?>" 
                         class="similar-poster" alt="<?php echo $similar_title; ?>">
                    <div class="similar-info">
                        <div class="similar-title"><?php echo htmlspecialchars($similar_title); ?></div>
                        <div class="similar-meta">
                            <span><?php echo $similar_year; ?></span>
                            <?php if ($similar_rating): ?>
                            <span class="similar-rating">⭐ <?php echo number_format($similar_rating, 1); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; // end if can_view ?>
    </div>

    <!-- مشغل الفيديو المنبثق -->
    <div class="video-player" id="videoPlayer">
        <div class="player-container">
            <button class="close-player" onclick="hidePlayer()">
                <i class="fas fa-times"></i> إغلاق
            </button>
            <div class="player-frame" id="playerFrame"></div>
        </div>
    </div>

    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script>
        AOS.init({ duration: 1000, once: true });
        
        window.addEventListener('scroll', function() {
            document.getElementById('header').classList.toggle('scrolled', window.scrollY > 100);
        });
        
        function toggleShareMenu() {
            document.getElementById('shareOptions').classList.toggle('active');
        }
        
        // دالة تشغيل الفيديو
        function playVideo(url) {
            console.log('🔍 تم النقر على زر المشاهدة');
            console.log('📌 الرابط المرسل:', url);
            
            if (!url || url === '') {
                alert('❌ عذراً، رابط المشاهدة غير متاح لهذا السيرفر');
                return;
            }
            
            const player = document.getElementById('videoPlayer');
            const frame = document.getElementById('playerFrame');
            
            if (!player || !frame) {
                console.error('❌ عناصر المشغل غير موجودة');
                return;
            }
            
            // تنظيف المحتوى السابق
            frame.innerHTML = '';
            
            try {
                let embedHtml = '';
                
                // تنظيف الرابط من علامات التنصيص الزائدة
                url = url.replace(/^['"]|['"]$/g, '');
                
                // إذا كان الرابط يحتوي على iframe
                if (url.includes('<iframe')) {
                    embedHtml = url;
                }
                // روابط يوتيوب
                else if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    let videoId = '';
                    if (url.includes('youtube.com/watch?v=')) {
                        videoId = url.split('v=')[1].split('&')[0];
                    } else if (url.includes('youtu.be/')) {
                        videoId = url.split('youtu.be/')[1];
                    } else if (url.includes('/embed/')) {
                        videoId = url.split('/embed/')[1];
                    }
                    
                    if (videoId) {
                        embedHtml = '<iframe src="https://www.youtube.com/embed/' + videoId + '" frameborder="0" allowfullscreen></iframe>';
                    } else {
                        embedHtml = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
                    }
                }
                // روابط vidsrc
                else if (url.includes('vidsrc')) {
                    embedHtml = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
                }
                // روابط عادية
                else {
                    embedHtml = '<iframe src="' + url + '" frameborder="0" allowfullscreen></iframe>';
                }
                
                frame.innerHTML = embedHtml;
                player.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                console.log('✅ تم تشغيل الفيديو بنجاح');
                
            } catch (e) {
                console.error('❌ خطأ:', e);
                alert('حدث خطأ في تشغيل الفيديو');
            }
        }
        
        // دالة إخفاء المشغل
        function hidePlayer() {
            const player = document.getElementById('videoPlayer');
            const frame = document.getElementById('playerFrame');
            
            if (player) player.classList.remove('active');
            if (frame) frame.innerHTML = '';
            document.body.style.overflow = 'auto';
        }
        
        // إغلاق بالضغط على ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hidePlayer();
                document.getElementById('shareOptions')?.classList.remove('active');
            }
        });
        
        // إغلاق قائمة المشاركة عند النقر خارجها
        document.addEventListener('click', function(e) {
            const menu = document.getElementById('shareOptions');
            const btn = document.querySelector('.share-btn');
            if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.remove('active');
            }
        });
        
        // تهيئة الأزرار عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ الصفحة جاهزة');
            console.log('🔢 عدد أزرار المشاهدة:', document.querySelectorAll('.server-btn').length);
            
            // التأكد من أن جميع الأزرار تعمل
            document.querySelectorAll('.server-btn').forEach((btn, index) => {
                console.log(`✅ زر ${index + 1} موجود`);
                
                // إضافة حدث مباشر للزر
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log(`🎯 تم النقر على زر ${index + 1}`);
                    
                    // محاولة الحصول على الرابط بعدة طرق
                    let url = '';
                    
                    // 1. من data-url
                    if (this.getAttribute('data-url')) {
                        url = this.getAttribute('data-url');
                    }
                    // 2. من onclick
                    else if (this.getAttribute('onclick')) {
                        const match = this.getAttribute('onclick').match(/'([^']+)'/);
                        if (match && match[1]) {
                            url = match[1];
                        }
                    }
                    // 3. من parent card
                    else {
                        const card = this.closest('.server-card');
                        if (card && card.getAttribute('onclick')) {
                            const match = card.getAttribute('onclick').match(/'([^']+)'/);
                            if (match && match[1]) {
                                url = match[1];
                            }
                        }
                    }
                    
                    console.log('📌 الرابط المستخرج:', url);
                    
                    if (url && url !== '#') {
                        playVideo(url);
                    } else {
                        alert('❌ رابط المشاهدة غير متاح لهذا السيرفر');
                    }
                });
            });
        });



/**
 * إظهار إشعار للمستخدم
 */
function showNotification(message, type = 'info') {
    // التحقق من وجود الإشعارات مسبقاً
    if (typeof window.showNotification === 'function') return;
    
    const notification = document.createElement('div');
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        </div>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e50914' : type === 'warning' ? '#f39c12' : '#3498db'};
        color: white;
        padding: 12px 25px;
        border-radius: 50px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        z-index: 9999;
        animation: slideDown 0.3s ease;
        direction: rtl;
        font-family: 'Tajawal', sans-serif;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideUp 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// إضافة حركات CSS للإشعارات إذا لم تكن موجودة
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translate(-50%, 0); opacity: 1; }
            to { transform: translate(-50%, -100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}
function refreshServers() {
    fetch(`movie.php?ajax=get_servers&id=${movieId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateWatchServers(data.watch_servers);
                updateDownloadServers(data.download_servers);
                console.log('✅ تم تحديث السيرفرات', new Date().toLocaleTimeString());
            }
        })
        .catch(error => console.error('❌ خطأ في تحديث السيرفرات:', error));
}

/**
 * تحديث قسم سيرفرات المشاهدة
 */
function updateWatchServers(servers) {
    const container = document.querySelector('.servers-grid');
    if (!container) return;
    
    if (!servers || servers.length === 0) {
        container.innerHTML = '<p class="no-servers">لا توجد سيرفرات متاحة</p>';
        return;
    }
    
    let html = '';
    servers.forEach((server, index) => {
        const serverName = server.server_name || 'سيرفر مشاهدة';
        const serverUrl = server.server_url || server.embed_code || '#';
        const serverQuality = server.quality || 'HD';
        const serverLang = server.language || 'arabic';
        
        html += `
            <div class="server-card">
                <div class="server-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="server-name">${escapeHtml(serverName)}</div>
                <div class="server-meta">
                    <span class="server-quality">${serverQuality}</span>
                    <span class="server-language">
                        <i class="fas fa-language"></i> 
                        ${serverLang === 'arabic' ? 'عربي' : 'English'}
                    </span>
                </div>
                <button class="server-btn" onclick="playVideo('${escapeHtml(serverUrl)}')">
                    <i class="fas fa-play"></i> مشاهدة الآن
                </button>
            </div>
        `;
    });
    
    container.innerHTML = html;
    
    // تحديث عدد السيرفرات
    const badge = document.querySelector('.section-badge');
    if (badge) {
        badge.textContent = `${servers.length} سيرفر متاح`;
    }
}

/**
 * تحديث قسم سيرفرات التحميل
 */
function updateDownloadServers(servers) {
    const container = document.querySelector('.download-grid');
    if (!container) return;
    
    if (!servers || servers.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    let html = '';
    servers.forEach(server => {
        const serverName = server.server_name || 'سيرفر تحميل';
        const serverUrl = server.download_url || '#';
        const serverQuality = server.quality || 'HD';
        const serverSize = server.size || '';
        
        html += `
            <div class="download-card">
                <div class="download-icon">
                    <i class="fas fa-cloud-download-alt"></i>
                </div>
                <div class="download-name">${escapeHtml(serverName)}</div>
                <div class="download-quality">${serverQuality}</div>
                ${serverSize ? `<div class="download-size">الحجم: ${serverSize}</div>` : ''}
                <a href="download-proxy.php?id=${server.id}" class="download-link" target="_blank">
                    <i class="fas fa-download"></i> تحميل
                </a>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

/**
 * دالة مساعدة لترميز النص
 */
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// تحديث كل 10 ثواني
setInterval(refreshServers, 10000);

// تحديث عند العودة إلى الصفحة
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        refreshServers();
    }
});

// تحديث يدوي عند الطلب
function manualRefresh() {
    refreshServers();
    showNotification('جاري تحديث البيانات...', 'info');
}

// إضافة زر التحديث اليدوي
window.addEventListener('load', function() {
    const header = document.querySelector('.section-header');
    if (header) {
        const refreshBtn = document.createElement('button');
        refreshBtn.className = 'refresh-btn';
        refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> تحديث';
        refreshBtn.onclick = manualRefresh;
        refreshBtn.style.cssText = `
            background: transparent;
            border: 1px solid #e50914;
            color: #e50914;
            padding: 5px 15px;
            border-radius: 30px;
            cursor: pointer;
            margin-right: 10px;
            transition: 0.3s;
        `;
        refreshBtn.addEventListener('mouseenter', () => {
            refreshBtn.style.background = '#e50914';
            refreshBtn.style.color = 'white';
        });
        refreshBtn.addEventListener('mouseleave', () => {
            refreshBtn.style.background = 'transparent';
            refreshBtn.style.color = '#e50914';
        });
        header.appendChild(refreshBtn);
    }
});

    </script>
</body>
</html>