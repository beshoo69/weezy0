<?php
// series.php - صفحة تفاصيل المسلسل الكاملة مع أقسام المشاهدة والتحميل
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/tmdb.php';
require_once __DIR__ . '/includes/membership-check.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات المسلسل
$stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
$stmt->execute([$id]);
$series = $stmt->fetch();

if (!$series) {
    header('Location: 404.php');
    exit;
}

// التحقق من صلاحية المشاهدة
$user_level = getUserMembershipLevel($_SESSION['user_id'] ?? 0);
$required_level = $series['membership_level'] ?? 'basic';
$can_view = canViewContent($user_level, $required_level);

if (!$can_view) {
    $_SESSION['requested_url'] = $_SERVER['REQUEST_URI'];
}

// زيادة عدد المشاهدات (فقط إذا كان مسموح بالمشاهدة)
if ($can_view) {
    $pdo->prepare("UPDATE series SET views = views + 1 WHERE id = ?")->execute([$id]);
}

// =============================================
// جلب بيانات إضافية من TMDB API
// =============================================
$tmdb_id = $series['tmdb_id'] ?? null;
$actors = [];
$crew = [];
$similar_series = [];
$videos = [];
$series_details = [];

// Attempt to look up TMDB ID by title/year if missing
if (!$tmdb_id && !empty($series['title'])) {
    $search_url = "https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY . "&query=" . urlencode($series['title']);
    if (!empty($series['year'])) {
        $search_url .= "&first_air_date_year=" . $series['year'];
    }
    $search_result = tmdb_request($search_url);
    if ($search_result && !empty($search_result['results'][0]['id'])) {
        $tmdb_id = $search_result['results'][0]['id'];
    }
}

if ($tmdb_id) {
    // جلب تفاصيل إضافية من TMDB
    $details_url = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,similar,videos,reviews,recommendations";
    $details_data = tmdb_request($details_url);
    
    if ($details_data) {
        $series_details = $details_data;
        
        // جلب طاقم العمل (الممثلين)
        if (isset($details_data['credits']['cast'])) {
            $actors = array_slice($details_data['credits']['cast'], 0, 8);
        }
        
        // جلب طاقم العمل (Crew)
        if (isset($details_data['credits']['crew'])) {
            $crew = array_slice($details_data['credits']['crew'], 0, 5);
        }
        
        // جلب مسلسلات مشابهة
        if (isset($details_data['similar']['results'])) {
            $similar_series = array_slice($details_data['similar']['results'], 0, 8);
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

// جلب المواسم والحلقات
$stmt = $pdo->prepare("
    SELECT season_number, COUNT(DISTINCT episode_number) as episodes_count, 
           SUM(duration) as total_duration
    FROM episodes 
    WHERE series_id = ? AND season_number > 0
    GROUP BY season_number 
    ORDER BY season_number
");
$stmt->execute([$id]);
$seasons = $stmt->fetchAll();

// جلب جميع الحلقات (قد تتضمن نسخاً مكررة بسبب مشكلة سابقة)
$stmt = $pdo->prepare("
    SELECT * FROM episodes 
    WHERE series_id = ? AND season_number > 0
    ORDER BY season_number, episode_number, id
");
$stmt->execute([$id]);
$all_episodes = $stmt->fetchAll();

// إزالة التكرارات بناءً على الموسم ورقم الحلقة
$unique = [];
foreach ($all_episodes as $ep) {
    $key = $ep['season_number'] . '_' . $ep['episode_number'];
    if (!isset($unique[$key])) {
        $unique[$key] = $ep;
    }
}
$all_episodes = array_values($unique);

// تجميع الحلقات حسب الموسم
$episodes_by_season = [];
foreach ($all_episodes as $episode) {
    if (!isset($episodes_by_season[$episode['season_number']])) {
        $episodes_by_season[$episode['season_number']] = [];
    }
    $episodes_by_season[$episode['season_number']][] = $episode;
}

// إذا كان لدينا معلومات مواسم من TMDB، أضف أي مواسم مفقودة حتى لو لم يكن لديها حلقات
if (!empty($series_details['seasons']) && is_array($series_details['seasons'])) {
    foreach ($series_details['seasons'] as $s) {
        $num = (int)($s['season_number'] ?? 0);
        if ($num > 0 && !isset($episodes_by_season[$num])) {
            $episodes_by_season[$num] = []; // موسم فارغ
        }
    }
    ksort($episodes_by_season);
}

// =============================================
// جلب سيرفرات المشاهدة من قاعدة البيانات
// =============================================
$stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
$stmt->execute([$id]);
$watch_servers = $stmt->fetchAll();

// إذا لم توجد سيرفرات، نستخدم TMDB ID لإنشاء سيرفرات
if (empty($watch_servers) && $tmdb_id) {
    $watch_servers = [
        [
            'id' => 1,
            'server_name' => '🎬 Vidsrc.to - 4K UHD',
            'server_url' => 'https://vidsrc.to/embed/tv/' . $tmdb_id,
            'embed_code' => '<iframe src="https://vidsrc.to/embed/tv/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
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
            'server_url' => 'https://embed.su/embed/tv/' . $tmdb_id,
            'embed_code' => '<iframe src="https://embed.su/embed/tv/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '1080p HD',
            'language' => 'English',
            'icon' => 'fas fa-play'
        ],
        [
            'id' => 4,
            'server_name' => '⚡ VidLink.pro - 4K UHD',
            'server_url' => 'https://vidlink.pro/tv/' . $tmdb_id,
            'embed_code' => '<iframe src="https://vidlink.pro/tv/' . $tmdb_id . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => '4K UHD',
            'language' => 'العربية',
            'icon' => 'fas fa-bolt'
        ]
    ];
} elseif (empty($watch_servers)) {
    // إضافة سيرفرات بحث عشوائية إذا لم يتوفر TMDB ID
    $watch_servers = [
        [
            'id' => 1,
            'server_name' => '🔍 بحث في Vidsrc.to',
            'server_url' => 'https://vidsrc.to/embed/tv/tt' . rand(1000000, 9999999),
            'embed_code' => '<iframe src="https://vidsrc.to/embed/tv/tt' . rand(1000000, 9999999) . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => 'مباشر',
            'language' => 'العربية',
            'icon' => 'fas fa-search'
        ],
        [
            'id' => 2,
            'server_name' => '🔍 بحث في Embed.su',
            'server_url' => 'https://embed.su/embed/tv/tt' . rand(1000000, 9999999),
            'embed_code' => '<iframe src="https://embed.su/embed/tv/tt' . rand(1000000, 9999999) . '" width="100%" height="100%" frameborder="0" allowfullscreen></iframe>',
            'quality' => 'مباشر',
            'language' => 'English',
            'icon' => 'fas fa-search'
        ]
    ];
}
// =============================================
// جلب سيرفرات التحميل من قاعدة البيانات
// =============================================
$stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
$stmt->execute([$id]);
$download_servers = $stmt->fetchAll();

// إذا لم توجد سيرفرات تحميل، نضيف سيرفرات تجريبية
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
        ],
        [
            'server_name' => 'تيليغرام',
            'download_url' => '#',
            'quality' => '4K',
            'size' => '3.2 GB'
        ],
        [
            'server_name' => 'ميجا',
            'download_url' => '#',
            'quality' => '1080p',
            'size' => '2.1 GB'
        ]
    ];
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
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_seasons') {
    header('Content-Type: application/json');
    
    $series_id = (int)($_GET['id'] ?? 0);
    
    // جلب المواسم
    $seasons = [];
    $stmt = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
    $stmt->execute([$series_id]);
    $seasons = $stmt->fetchAll();
    
    // جلب الحلقات
    $episodes = [];
    $stmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? ORDER BY season_number, episode_number, id");
    $stmt->execute([$series_id]);
    $episodes = $stmt->fetchAll();
    
    // إزالة أي نسخ مكررة
    $uniqueEp = [];
    foreach ($episodes as $ep) {
        $key = $ep['season_number'].'_'.$ep['episode_number'];
        if (!isset($uniqueEp[$key])) {
            $uniqueEp[$key] = $ep;
        }
    }
    $episodes = array_values($uniqueEp);
    
    // تجميع الحلقات حسب الموسم
    $episodes_by_season = [];
    foreach ($episodes as $episode) {
        $episodes_by_season[$episode['season_number']][] = $episode;
    }
    
    echo json_encode([
        'success' => true,
        'seasons' => $seasons,
        'episodes' => $episodes,
        'episodes_by_season' => $episodes_by_season
    ]);
    exit;}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($series['title']); ?> - ويزي برو</title>
    <meta name="description" content="<?php echo htmlspecialchars(mb_substr($series['description'] ?: ($series_details['overview'] ?? ''), 0, 160)); ?>">
    
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
        
        /* ===== حاوية المسلسل ===== */
        .series-wrapper {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        /* ===== الهيرو المتحرك ===== */
        .series-hero {
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
        
        /* ===== المحتوى الرئيسي ===== */
        .series-main {
            display: flex;
            gap: 40px;
            margin-bottom: 50px;
        }
        
        .series-poster-section {
            flex: 0 0 300px;
            position: relative;
            perspective: 1000px;
        }
        
        .series-poster {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
            border: 2px solid transparent;
            transition: var(--transition);
            transform-style: preserve-3d;
            cursor: pointer;
        }
        
        .series-poster:hover {
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
        
        .series-poster:hover + .poster-glow {
            opacity: 1;
        }
        
        .series-info-section {
            flex: 1;
        }
        
        .series-title {
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
        
        .series-title-en {
            font-size: 24px;
            color: var(--text-gray);
            margin-bottom: 25px;
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
            background: conic-gradient(gold <?php echo ($series['imdb_rating'] ?? 0) * 10; ?>deg, #333 0deg);
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
        
        /* ===== قسم المواسم والحلقات ===== */
        .seasons-section {
            margin-top: 50px;
        }
        
        .seasons-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .seasons-title {
            font-size: 28px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .seasons-badge {
            background: #252525;
            padding: 8px 20px;
            border-radius: 30px;
            color: var(--text-gray);
            font-size: 16px;
            border: 1px solid var(--border);
        }
        
        .season-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .season-tab {
            padding: 12px 25px;
            background: transparent;
            border: none;
            color: #b3b3b3;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-radius: 30px;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .season-tab i {
            color: var(--primary);
        }
        
        .season-tab:hover,
        .season-tab.active {
            background: var(--primary);
            color: white;
        }
        
        .season-tab:hover i,
        .season-tab.active i {
            color: white;
        }
        
        .episodes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .episode-card {
            background: #252525;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #333;
            transition: 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .episode-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--primary), #ff6b6b);
            opacity: 0;
            transition: 0.3s;
        }
        
        .episode-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .episode-card:hover::before {
            opacity: 1;
        }
        
        .episode-number {
            color: var(--primary);
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .episode-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .episode-meta {
            display: flex;
            gap: 15px;
            color: var(--text-gray);
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .episode-desc {
            color: var(--text-gray);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .episode-watch-btn {
            display: inline-block;
            background: transparent;
            border: 2px solid var(--primary);
            color: white;
            padding: 8px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .episode-watch-btn:hover {
            background: var(--primary);
            transform: scale(1.05);
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
        
        .servers-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .servers-title {
            font-size: 28px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .servers-badge {
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
        
        .download-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
            z-index: 2;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .download-title {
            font-size: 28px;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .download-badge {
            background: #252525;
            padding: 8px 20px;
            border-radius: 30px;
            color: var(--text-gray);
            font-size: 16px;
            border: 1px solid var(--border);
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
        
        /* ===== أعمال مشابهة ===== */
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
        
        /* ===== مشغل الفيديو المنبثق ===== */
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
            box-shadow: 0 0 30px var(--primary-glow);
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
            background: var(--primary);
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
            background: var(--primary-dark);
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
            .series-main { flex-direction: column; }
            .series-poster-section { width: 200px; margin: 0 auto; }
            .episodes-grid { grid-template-columns: 1fr; }
            .servers-grid { grid-template-columns: 1fr; }
            .download-grid { grid-template-columns: 1fr; }
            .season-tabs { flex-direction: column; }
            .season-tab { width: 100%; text-align: center; }
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

    <div class="series-wrapper">
        <?php if (!$can_view): ?>
            <!-- عرض شريط الاشتراك إذا لم يكن مسموحاً بالمشاهدة -->
            <div class="membership-required-bar <?php echo $required_level; ?>" style="border-color: <?php echo $required_level == 'vip' ? 'gold' : '#e50914'; ?>;">
                <i class="fas <?php echo $required_level == 'vip' ? 'fa-crown' : 'fa-star'; ?>" style="font-size: 40px; color: <?php echo $required_level == 'vip' ? 'gold' : '#e50914'; ?>; margin-bottom: 10px;"></i>
                <h3 style="color: <?php echo $required_level == 'vip' ? 'gold' : '#e50914'; ?>;">
                    هذا المسلسل <?php echo $required_level == 'vip' ? 'VIP 👑' : 'مميز ⭐'; ?>
                </h3>
                <p style="color: #b3b3b3; margin: 10px 0;">اشترك الآن للاستمتاع بهذا المسلسل وجميع المميزات الحصرية</p>
                <a href="membership-plans.php?required=<?php echo $required_level; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" 
                   class="btn btn-primary" 
                   style="background: <?php echo $required_level == 'vip' ? 'gold' : '#e50914'; ?>; color: <?php echo $required_level == 'vip' ? 'black' : 'white'; ?>; padding: 12px 30px; border-radius: 50px; text-decoration: none; display: inline-block; margin-top: 10px;">
                    <i class="fas <?php echo $required_level == 'vip' ? 'fa-crown' : 'fa-star'; ?>"></i> اشترك الآن
                </a>
            </div>
        <?php endif; ?>

        <!-- الهيرو المتحرك -->
        <div class="series-hero" data-aos="fade-down">
            <img src="<?php echo $series['backdrop'] ?? ($series_details['backdrop_path'] ? 'https://image.tmdb.org/t/p/original' . $series_details['backdrop_path'] : 'https://image.tmdb.org/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png'); ?>" 
                 class="hero-backdrop" alt="">
            <div class="hero-overlay">
                <div class="hero-content">
                    <h1 class="hero-title"><?php echo strtoupper(htmlspecialchars($series['title'] ?: ($series_details['name'] ?? ''))); ?></h1>
                    <div class="hero-badges">
                        <span class="badge"><i class="fas fa-globe"></i> <?php echo $series['country'] ?? ($series_details['production_countries'][0]['name'] ?? 'عالمي'); ?></span>
                        <span class="badge"><i class="fas fa-calendar-alt"></i> <?php echo $series['year'] ?? (isset($series_details['first_air_date']) ? substr($series_details['first_air_date'], 0, 4) : '2024'); ?></span>
                        <span class="badge"><i class="fas fa-layer-group"></i> <?php echo count($seasons); ?> مواسم</span>
                        <?php if (!empty($series['quality'])): ?>
                        <span class="badge hd"><i class="fas fa-hd"></i> <?php echo $series['quality']; ?></span>
                        <?php endif; ?>
                        <?php if ($series['membership_level'] != 'basic'): ?>
                            <span class="badge <?php echo $series['membership_level'] == 'vip' ? '' : ''; ?>" style="background: <?php echo $series['membership_level'] == 'vip' ? 'gold' : '#e50914'; ?>; color: <?php echo $series['membership_level'] == 'vip' ? 'black' : 'white'; ?>;">
                                <?php if ($series['membership_level'] == 'vip'): ?>
                                    <i class="fas fa-crown"></i> VIP
                                <?php else: ?>
                                    <i class="fas fa-star"></i> مميز
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- قسم الوصف -->
        <div class="description-section" data-aos="fade-up">
            <div class="description-content">
                <div class="description-quote">
                    <i class="fas fa-quote-right"></i> قصة المسلسل <i class="fas fa-quote-left"></i>
                </div>
                <div class="description-text">
                    <?php echo nl2br(htmlspecialchars($series['description'] ?: ($series_details['overview'] ?? 'لا يوجد وصف متاح لهذا المسلسل'))); ?>
                </div>
                <?php if (!empty($director)): ?>
                <div class="description-meta">
                    <span><i class="fas fa-video"></i> إخراج: <?php echo htmlspecialchars($director); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- المحتوى الرئيسي -->
        <div class="series-main">
            <div class="series-poster-section" data-aos="fade-left">
                <img src="<?php echo $series['poster'] ?? ($series_details['poster_path'] ? 'https://image.tmdb.org/t/p/w500' . $series_details['poster_path'] : 'https://via.placeholder.com/300x450?text=No+Poster'); ?>" 
                     class="series-poster" alt="<?php echo $series['title']; ?>">
                <div class="poster-glow"></div>
            </div>
            
            <div class="series-info-section" data-aos="fade-right">
                <h1 class="series-title">
                    <?php echo htmlspecialchars($series['title'] ?: ($series_details['name'] ?? '')); ?>
                    <?php if ($series['membership_level'] != 'basic'): ?>
                        <span class="membership-badge <?php echo $series['membership_level']; ?>">
                            <?php if ($series['membership_level'] == 'vip'): ?>
                                <i class="fas fa-crown"></i> VIP
                            <?php else: ?>
                                <i class="fas fa-star"></i> مميز
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </h1>
                
                <?php if (!empty($series['title_en'])): ?>
                <div class="series-title-en"><?php echo htmlspecialchars($series['title_en']); ?></div>
                <?php endif; ?>
                
                <div class="rating-bar" data-aos="zoom-in">
                    <?php 
                    $rating = $series['imdb_rating'] ?? ($series_details['vote_average'] ?? 0);
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
                            <span><?php echo number_format($series['views'] ?? 0); ?></span>
                        </div>
                        <div class="rating-info">
                            <span class="rating-label">المشاهدات</span>
                            <span class="rating-value"><?php echo number_format($series['views'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="info-grid" data-aos="fade-up">
                    <?php if (!empty($series['genre']) || !empty($series_details['genres'])): ?>
                    <div class="info-card">
                        <div class="info-icon"><i class="fas fa-tag"></i></div>
                        <div class="info-label">التصنيف</div>
                        <div class="info-value">
                            <?php 
                            if (!empty($series['genre'])) echo $series['genre'];
                            elseif (!empty($series_details['genres'])) {
                                $genres = array_column($series_details['genres'], 'name');
                                echo implode('، ', $genres);
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                        <div class="info-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="info-label">عدد المواسم</div>
                        <div class="info-value"><?php echo count($seasons); ?></div>
                    </div>
                    
                    <div class="info-card">
                        <div class="info-icon"><i class="fas fa-eye"></i></div>
                        <div class="info-label">المشاهدات</div>
                        <div class="info-value"><?php echo number_format($series['views'] ?? 0); ?></div>
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
                    <button class="action-btn watch-btn" onclick="document.querySelector('.servers-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-play"></i> مشاهدة
                    </button>
                    
                    <button class="action-btn download-main-btn" onclick="document.querySelector('.download-section').scrollIntoView({behavior: 'smooth'})">
                        <i class="fas fa-download"></i> تحميل
                    </button>
                    
                    <div class="share-menu">
                        <button class="action-btn share-btn" onclick="toggleShareMenu()">
                            <i class="fas fa-share-alt"></i> مشاركة
                        </button>
                        
                        <div class="share-options" id="shareOptions">
                            <?php
                            $share_url = urlencode((isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/series.php?id=' . $id);
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
        
        <div class="seasons-section" data-aos="fade-up">
            <div class="seasons-header">
                <h2 class="seasons-title">
                    <i class="fas fa-list"></i> المواسم والحلقات
                </h2>
                <div style="display: flex; gap: 15px;">
                    <span class="seasons-badge"><?php echo count($episodes_by_season); ?> مواسم</span>
                    <span class="seasons-badge" style="background: #2c3e50;"><?php echo count($all_episodes); ?> حلقات</span>
                </div>
            </div>
            
            <?php if (!empty($episodes_by_season)): ?>
                <!-- تبويبات المواسم -->
                <div class="season-tabs">
                    <?php foreach (array_keys($episodes_by_season) as $season_num): ?>
                    <button class="season-tab <?php echo $season_num == 1 ? 'active' : ''; ?>" 
                            onclick="showSeason(<?php echo $season_num; ?>)">
                        <i class="fas fa-folder"></i> الموسم <?php echo $season_num; ?>
                        <span style="background: #333; padding: 3px 8px; border-radius: 20px; font-size: 12px;">
                            <?php echo count($episodes_by_season[$season_num]); ?> حلقات
                        </span>
                    </button>
                    <?php endforeach; ?>
                </div>
                
                <!-- الحلقات -->
                <?php foreach ($episodes_by_season as $season_num => $episodes): ?>
                <div id="season-<?php echo $season_num; ?>" class="season-content" 
                     style="display: <?php echo $season_num == 1 ? 'block' : 'none'; ?>;">
                    <div class="episodes-grid">
                        <?php foreach ($episodes as $episode): ?>
                        <div class="episode-card">
                            <div class="episode-number">EP <?php echo str_pad($episode['episode_number'], 2, '0', STR_PAD_LEFT); ?></div>
                            <div class="episode-title"><?php echo htmlspecialchars($episode['title']); ?></div>
                            <div class="episode-meta">
                                <span><i class="far fa-clock"></i> <?php echo $episode['duration'] ?? 45; ?> دقيقة</span>
                                <span><i class="fas fa-eye"></i> <?php echo number_format($episode['views'] ?? 0); ?></span>
                            </div>
                            <div class="episode-desc">
                                <?php echo htmlspecialchars(mb_substr($episode['description'] ?? 'لا يوجد وصف', 0, 100)); ?>...
                            </div>
                            <a href="episode.php?id=<?php echo $episode['id']; ?>" class="episode-watch-btn">
                                <i class="fas fa-play"></i> مشاهدة الحلقة
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
            <?php else: ?>
                <div style="text-align: center; padding: 60px; background: #1a1a1a; border-radius: 15px;">
                    <i class="fas fa-tv" style="font-size: 50px; color: #e50914; margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">لا توجد حلقات بعد</h3>
                    <p style="color: #b3b3b3;">سيتم إضافة حلقات هذا المسلسل قريباً</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ===== مسلسلات مشابهة ===== -->
        <?php if (!empty($similar_series) || !empty($recommendations)): ?>
        <div class="similar-section" data-aos="fade-up">
            <div class="seasons-header">
                <h2 class="seasons-title">
                    <i class="fas fa-tv"></i> مسلسلات مشابهة
                </h2>
                <span class="seasons-badge"><?php echo min(8, count($similar_series ?: $recommendations ?: [])); ?> مسلسل</span>
            </div>
            
            <div class="similar-grid">
                <?php 
                $count = 0;
                $similar_items = !empty($similar_series) ? $similar_series : ($recommendations ?? []);
                foreach ($similar_items as $similar): 
                    if ($count++ >= 8) break;
                    
                    $similar_title = $similar['name'] ?? '';
                    $similar_poster = isset($similar['poster_path']) ? 'https://image.tmdb.org/t/p/w300' . $similar['poster_path'] : '';
                    $similar_year = isset($similar['first_air_date']) ? substr($similar['first_air_date'], 0, 4) : '';
                    $similar_rating = $similar['vote_average'] ?? '';
                    
                    // البحث عن المسلسل في قاعدة البيانات
                    $stmt = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
                    $stmt->execute([$similar['id'] ?? 0]);
                    $local_series = $stmt->fetch();
                    $link = $local_series ? "series.php?id=" . $local_series['id'] : "#";
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
        
        function showSeason(season) {
            // إخفاء كل المواسم
            var seasons = document.getElementsByClassName('season-content');
            for (var i = 0; i < seasons.length; i++) {
                seasons[i].style.display = 'none';
            }
            
            // إظهار الموسم المطلوب
            document.getElementById('season-' + season).style.display = 'block';
            
            // تحديث التبويبات
            var tabs = document.getElementsByClassName('season-tab');
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            event.currentTarget.classList.add('active');
        }
        
        function playVideo(url) {
            console.log('🔍 تشغيل الفيديو:', url);
            
            if (!url || url === '') {
                alert('❌ رابط المشاهدة غير متاح');
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
                
                // تنظيف الرابط من علامات التنصيص
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
                
                console.log('✅ تم تشغيل المشغل بنجاح');
                
            } catch (e) {
                console.error('❌ خطأ:', e);
                alert('حدث خطأ في تشغيل الفيديو');
            }
        }
        
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
        let seriesId = <?php echo $id; ?>;

/**
 * تحديث المواسم والحلقات
 */
function refreshSeasons() {
    fetch(`series.php?ajax=get_seasons&id=${seriesId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateSeasons(data.seasons, data.episodes_by_season);
                console.log('✅ تم تحديث المواسم والحلقات', new Date().toLocaleTimeString());
            }
        })
        .catch(error => console.error('❌ خطأ في تحديث المواسم:', error));
}

/**
 * تحديث واجهة المواسم والحلقات
 */
function updateSeasons(seasons, episodesBySeason) {
    const container = document.querySelector('.seasons-section');
    if (!container) return;
    
    if (!seasons || seasons.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 60px; background: #1a1a1a; border-radius: 15px;">
                <i class="fas fa-tv" style="font-size: 50px; color: #e50914; margin-bottom: 20px;"></i>
                <h3 style="margin-bottom: 10px;">لا توجد مواسم بعد</h3>
                <p style="color: #b3b3b3;">سيتم إضافة مواسم هذا المسلسل قريباً</p>
            </div>
        `;
        return;
    }
    
    // حساب إجمالي الحلقات
    let totalEpisodes = 0;
    Object.keys(episodesBySeason).forEach(seasonNum => {
        totalEpisodes += episodesBySeason[seasonNum].length;
    });
    
    // تحديث تبويبات المواسم
    let tabsHtml = '';
    let contentHtml = '';
    
    Object.keys(episodesBySeason).forEach((seasonNum, index) => {
        const episodes = episodesBySeason[seasonNum];
        const isActive = index === 0;
        
        tabsHtml += `
            <button class="season-tab ${isActive ? 'active' : ''}" 
                    onclick="showSeason(${seasonNum})">
                <i class="fas fa-folder"></i> الموسم ${seasonNum}
                <span style="background: #333; padding: 3px 8px; border-radius: 20px; font-size: 12px;">
                    ${episodes.length} حلقات
                </span>
            </button>
        `;
        
        let episodesHtml = '';
        episodes.forEach(episode => {
            episodesHtml += `
                <div class="episode-card">
                    <div class="episode-number">EP ${String(episode.episode_number).padStart(2, '0')}</div>
                    <div class="episode-title">${escapeHtml(episode.title || `الحلقة ${episode.episode_number}`)}</div>
                    <div class="episode-meta">
                        <span><i class="far fa-clock"></i> ${episode.duration || 45} دقيقة</span>
                        <span><i class="fas fa-eye"></i> ${Number(episode.views || 0).toLocaleString()}</span>
                    </div>
                    <div class="episode-desc">
                        ${escapeHtml((episode.description || 'لا يوجد وصف').substring(0, 100))}...
                    </div>
                    <a href="episode.php?id=${episode.id}" class="episode-watch-btn">
                        <i class="fas fa-play"></i> مشاهدة الحلقة
                    </a>
                </div>
            `;
        });
        
        contentHtml += `
            <div id="season-${seasonNum}" class="season-content" style="display: ${isActive ? 'block' : 'none'};">
                <div class="episodes-grid">
                    ${episodesHtml}
                </div>
            </div>
        `;
    });
    
    container.innerHTML = `
        <div class="seasons-header">
            <h2 class="seasons-title">
                <i class="fas fa-list"></i> المواسم والحلقات
            </h2>
            <div style="display: flex; gap: 15px;">
                <span class="seasons-badge">${Object.keys(episodesBySeason).length} مواسم</span>
                <span class="seasons-badge" style="background: #2c3e50;">${totalEpisodes} حلقات</span>
            </div>
        </div>
        <div class="season-tabs">${tabsHtml}</div>
        ${contentHtml}
    `;
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

/**
 * إظهار موسم معين
 */
function showSeason(season) {
    const seasons = document.getElementsByClassName('season-content');
    for (let i = 0; i < seasons.length; i++) {
        seasons[i].style.display = 'none';
    }
    
    document.getElementById('season-' + season).style.display = 'block';
    
    const tabs = document.getElementsByClassName('season-tab');
    for (let i = 0; i < tabs.length; i++) {
        tabs[i].classList.remove('active');
    }
    event.currentTarget.classList.add('active');
}

// تحديث كل 15 ثانية
setInterval(refreshSeasons, 15000);

// تحديث عند العودة إلى الصفحة
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        refreshSeasons();
    }
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
// تحديث يدوي
function manualRefresh() {
    refreshSeasons();
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