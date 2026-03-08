<?php
// index.php - الصفحة الرئيسية مع جميع الأقسام (نسخة محسنة)
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// =============================================
// جلب البيانات للأقسام المختلفة (محسن)
// =============================================
// جلب بيانات إضافية للمستخدم إذا كان مسجل دخول
$user_email = '';
$user_created = '';
$user_membership = 'غير مشترك';
$user_views = 0;
$user_last_active = 'غير معروف';
$free_movies = [];
$free_series = [];
$free_videos = [];
$free_featured = [];
$free_stats = ['movies' => 0, 'series' => 0, 'videos' => 0];

try {
    // جلب الأفلام
    $free_movies = $pdo->query("
        SELECT 
            id, 
            title, 
            thumbnail,
            local_thumbnail,
            channel_title,
            duration,
            view_count,
            'movie' as content_type,
            video_id
        FROM youtube_movies 
        WHERE status = 1 
        ORDER BY id DESC 
        LIMIT 10
    ")->fetchAll();
    
    // جلب المسلسلات
    $free_series = $pdo->query("
        SELECT 
            s.id, 
            s.title, 
            s.thumbnail,
            s.local_thumbnail,
            s.channel_title,
            'series' as content_type,
            (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as total_episodes
        FROM youtube_series s 
        WHERE s.status = 1 
        ORDER BY s.id DESC 
        LIMIT 10
    ")->fetchAll();
    
    // جلب الفيديوهات
    $free_videos = $pdo->query("
        SELECT 
            id, 
            title, 
            thumbnail,
            local_thumbnail,
            channel_title,
            duration,
            view_count,
            'video' as content_type,
            video_id
        FROM youtube_content 
        WHERE status = 1 
        ORDER BY id DESC 
        LIMIT 10
    ")->fetchAll();
    
    // مزج المحتوى
    $free_featured = array_merge($free_movies, $free_series, $free_videos);
    shuffle($free_featured);
    $free_featured = array_slice($free_featured, 0, 15);
    
    // إحصائيات
    $free_stats = [
        'movies' => count($free_movies),
        'series' => count($free_series),
        'videos' => count($free_videos)
    ];
    
} catch (Exception $e) {
    // في حالة الخطأ، نترك المصفوفات فارغة
    $free_featured = [];
}

// دالة مساعدة لعرض الصورة
function getFreeImageUrl($item) {
    if (!empty($item['local_thumbnail'])) {
        return '/fayez-movie/' . $item['local_thumbnail'];
    } elseif (!empty($item['thumbnail'])) {
        return $item['thumbnail'];
    }
    return 'https://via.placeholder.com/300x169?text=No+Image';
}
if (isset($_SESSION['user_id'])) {
    // جلب بيانات المستخدم من قاعدة البيانات
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $user_email = $user_data['email'];
        $user_created = $user_data['created_at'] ?? date('Y-m-d');
        
        // تحديد حالة العضوية
        if (isset($user_data['membership_plan_id'])) {
            if ($user_data['membership_plan_id'] == 3) {
                $user_membership = 'VIP 👑';
            } elseif ($user_data['membership_plan_id'] == 2) {
                $user_membership = 'مميز ⭐';
            } else {
                $user_membership = 'عادي';
            }
        }
        
        // حساب عدد المشاهدات من جدول المشاهدات في الأفلام والمسلسلات
        try {
            // يمكنك إضافة كود لحساب المشاهدات إذا أردت
            $user_views = 0; // قيمة افتراضية
        } catch (Exception $e) {
            $user_views = 0;
        }
        
        // آخر نشاط - تاريخ تسجيل الدخول الحالي
        $user_last_active = date('Y-m-d H:i');
    }
}

// إذا كان المستخدم غير مسجل، نظهر بيانات محدودة
else {
    // يمكنك إضافة tracking للزوار
    $visitor_id = session_id();
    $visitor_ip = $_SERVER['REMOTE_ADDR'];
}
// أحدث الأفلام (بغض النظر عن النوع)
$latest_movies = $pdo->query("SELECT * FROM movies WHERE status = 'published' ORDER BY id DESC LIMIT 30")->fetchAll();

// أحدث المسلسلات
$latest_series = $pdo->query("SELECT * FROM series ORDER BY id DESC LIMIT 30")->fetchAll();

// فيديوهات مجانية من يوتيوب (سيتم عرضها في قسم "يعرض مجاناً")
$free_items = [];

// جلب المسلسلات المجانية
try {
    $series_query = "SELECT 
        s.*,
        'series' as item_type,
        (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as episode_count
        FROM youtube_series s 
        WHERE s.status = 1 
        ORDER BY s.id DESC 
        LIMIT 20";
    
    $series_items = $pdo->query($series_query)->fetchAll();
    
    foreach ($series_items as $series) {
        $free_items[] = [
            'id' => $series['id'],
            'type' => 'series',
            'title' => $series['title'],
            'thumbnail' => !empty($series['local_thumbnail']) ? $series['local_thumbnail'] : $series['thumbnail'],
            'channel' => $series['channel_title'],
            'duration' => $series['video_count'] . ' حلقة',
            'views' => '-',
            'episode_count' => $series['episode_count'],
            'playlist_id' => $series['playlist_id'],
            'link' => 'youtube-series.php?id=' . $series['id']
        ];
    }
} catch (Exception $e) {
    // تجاهل الخطأ إذا كان الجدول غير موجود
}

// جلب الفيديوهات المنفردة المجانية
try {
    $videos_query = "SELECT 
        *,
        'video' as item_type
        FROM youtube_content 
        WHERE status = 1
        ORDER BY id DESC LIMIT 20";
    
    $video_items = $pdo->query($videos_query)->fetchAll();
    
    foreach ($video_items as $video) {
        $free_items[] = [
            'id' => $video['id'],
            'type' => 'video',
            'video_type' => $video['category'],
            'title' => $video['title'],
            'thumbnail' => !empty($video['local_thumbnail']) ? $video['local_thumbnail'] : $video['thumbnail'],
            'channel' => $video['channel_title'],
            'duration' => $video['duration'],
            'views' => $video['view_count'],
            'video_id' => $video['video_id'],
            'link' => 'https://www.youtube.com/watch?v=' . $video['video_id']
        ];
    }
} catch (Exception $e) {
    // تجاهل الخطأ إذا كان الجدول غير موجود
}

// ترتيب النتائج حسب التاريخ (الأحدث أولاً)
usort($free_items, function($a, $b) {
    return $b['id'] - $a['id'];
});

// الأكثر مشاهدة
$most_viewed = $pdo->query("SELECT * FROM movies WHERE status = 'published' ORDER BY views DESC LIMIT 10")->fetchAll();

// أفضل 10 أعمال في اليمن
$yemen_top = $pdo->query("
    SELECT * FROM movies 
    WHERE (country LIKE '%اليمن%' OR country LIKE '%Yemen%') AND status = 'published'
    ORDER BY views DESC, imdb_rating DESC 
    LIMIT 10
")->fetchAll();

// =============================================
// أقسام الأفلام (مرتبة حسب النوع)
// =============================================

// أفلام عربية - محسنة
$arabic_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        language = 'ar' 
        OR country IN ('مصر', 'السعودية', 'لبنان', 'سوريا', 'الإمارات', 'الكويت', 'المغرب', 'تونس', 'الجزائر', 'العراق', 'الأردن', 'فلسطين', 'اليمن', 'عمان', 'قطر', 'البحرين')
        OR genre LIKE '%عربي%'
        OR genre LIKE '%مصري%'
        OR genre LIKE '%خليجي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// أفلام أجنبية - محسنة
$foreign_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        language = 'en' 
        OR language = 'fr' 
        OR language = 'de' 
        OR language = 'es' 
        OR language = 'it' 
        OR language = 'ru'
        OR country IN ('الولايات المتحدة', 'بريطانيا', 'فرنسا', 'ألمانيا', 'إسبانيا', 'إيطاليا', 'روسيا')
        AND NOT (language = 'ar' OR country IN ('مصر','السعودية','لبنان','سوريا'))
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// أفلام تركية - محسنة
$turkish_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        language = 'tr' 
        OR country = 'تركيا' 
        OR country LIKE '%تركي%'
        OR genre LIKE '%تركي%'
        OR title LIKE '%تركي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// أفلام هندية - محسنة
$indian_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        language = 'hi' 
        OR language = 'ur' 
        OR language = 'pa' 
        OR language = 'bn'
        OR country = 'الهند' 
        OR country LIKE '%هند%'
        OR genre LIKE '%هندي%'
        OR title LIKE '%هندي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// أفلام آسيوية (كورية/يابانية/صينية) - محسنة
$asian_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        language IN ('ko', 'ja', 'zh', 'th', 'vi', 'id', 'ms', 'tl')
        OR country IN ('كوريا', 'اليابان', 'الصين', 'تايوان', 'هونغ كونغ', 'تايلاند', 'فيتنام', 'إندونيسيا', 'ماليزيا', 'الفلبين')
        OR title LIKE '%كوري%' 
        OR title LIKE '%ياباني%' 
        OR title LIKE '%صيني%' 
        OR title LIKE '%تايلاندي%'
        OR genre LIKE '%كوري%'
        OR genre LIKE '%ياباني%'
        OR genre LIKE '%آسيوي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// =============================================
// أقسام المسلسلات (مرتبة حسب النوع)
// =============================================

// مسلسلات عربية - محسنة
$arabic_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        language = 'ar' 
        OR country IN ('مصر', 'السعودية', 'لبنان', 'سوريا', 'الإمارات', 'الكويت', 'المغرب', 'تونس', 'الجزائر', 'العراق', 'الأردن', 'فلسطين', 'اليمن', 'عمان', 'قطر', 'البحرين')
        OR genre LIKE '%عربي%'
        OR genre LIKE '%مصري%'
        OR genre LIKE '%خليجي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات أجنبية - محسنة
$foreign_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        language = 'en' 
        OR language = 'fr' 
        OR language = 'de' 
        OR language = 'es'
        OR country IN ('الولايات المتحدة', 'بريطانيا', 'فرنسا', 'ألمانيا', 'إسبانيا')
        AND NOT (language = 'ar' OR language = 'tr' OR language = 'hi' OR language IN ('ko', 'ja', 'zh'))
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات تركية - محسنة
$turkish_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        language = 'tr' 
        OR country = 'تركيا' 
        OR country LIKE '%تركي%'
        OR genre LIKE '%تركي%'
        OR title LIKE '%تركي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات هندية - محسنة
$indian_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        language = 'hi' 
        OR language = 'ur'
        OR country = 'الهند' 
        OR country LIKE '%هند%'
        OR genre LIKE '%هندي%'
        OR title LIKE '%هندي%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات آسيوية (كورية/يابانية/صينية) - محسنة
$asian_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        language IN ('ko', 'ja', 'zh', 'th')
        OR country IN ('كوريا', 'اليابان', 'الصين', 'تايوان', 'هونغ كونغ', 'تايلاند')
        OR title LIKE '%كوري%' 
        OR title LIKE '%ياباني%' 
        OR title LIKE '%صيني%'
        OR genre LIKE '%كوري%'
        OR genre LIKE '%ياباني%'
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات أنمي - محسنة
$anime_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        genre LIKE '%أنمي%' 
        OR genre LIKE '%Anime%'
        OR title LIKE '%أنمي%'
        OR country = 'اليابان'
        OR (language = 'ja' AND genre LIKE '%رسوم متحركة%')
    )
    ORDER BY imdb_rating DESC, id DESC 
    LIMIT 30
")->fetchAll();

// =============================================
// الأقسام المصرية (مخصصة)
// =============================================

// أفلام مصرية - محسنة
$egyptian_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' 
    AND (
        country = 'مصر' 
        OR country LIKE '%مصري%'
        OR language = 'ar' 
        AND (title LIKE '%مصري%' OR description LIKE '%مصري%' OR genre LIKE '%مصري%')
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// مسلسلات مصرية - محسنة
$egyptian_series = $pdo->query("
    SELECT * FROM series 
    WHERE 1=1
    AND (
        country = 'مصر' 
        OR country LIKE '%مصري%'
        OR language = 'ar' 
        AND (title LIKE '%مصري%' OR description LIKE '%مصري%' OR genre LIKE '%مصري%')
    )
    ORDER BY year DESC, id DESC 
    LIMIT 30
")->fetchAll();

// قنوات البث المباشر
$live_channels = $pdo->query("SELECT * FROM live_channels WHERE status = 'live' LIMIT 10")->fetchAll();


$recommend_threshold = 9.0; // يمكن تعديله بسهولة
$top_series_items = $pdo->query(
    "SELECT * FROM series
     WHERE imdb_rating >= {$recommend_threshold}
     ORDER BY imdb_rating DESC, vote_count DESC, id DESC"
)->fetchAll();

if (!is_array($top_series_items)) {
    $top_series_items = [];
} else {
    // Normalise fields so template can use ['rating'] and ['type']
    foreach ($top_series_items as &$itm) {
        // use imdb_rating when available
        $itm['rating'] = $itm['imdb_rating'] ?? null;
        $itm['type'] = $itm['type'] ?? 'series';
        // remove any leftover keys not needed
        if (isset($itm['imdb_rating'])) {
            // keep, but we can also preserve both
        }
    }
    unset($itm);
}

// إعداد بيانات إحصائية لقسم التوصيات
$recommend_count = count($top_series_items);
$ratings = array_filter(array_column($top_series_items, 'imdb_rating'));
$recommend_min_rating = $ratings ? min($ratings) : 0;
$recommend_max_rating = $ratings ? max($ratings) : 0;

// =============================================
// جلب أفضل 10 للهيرو سلايدر
// =============================================
$hero_movies = $pdo->query("
    SELECT * FROM movies 
    WHERE status = 'published' AND backdrop IS NOT NULL 
    ORDER BY views DESC, id DESC 
    LIMIT 10
")->fetchAll();

$hero_series = $pdo->query("
    SELECT * FROM series 
    WHERE backdrop IS NOT NULL 
    ORDER BY views DESC, id DESC 
    LIMIT 8
")->fetchAll();

$hero_live = $pdo->query("
    SELECT * FROM live_channels 
    WHERE status = 'live' AND featured = 1 
    LIMIT 4
")->fetchAll();

// دمج محتوى الهيرو
$hero_items = [];

foreach ($hero_movies as $movie) {
    $hero_items[] = [
        'id' => $movie['id'],
        'type' => 'movie',
        'title' => $movie['title'],
        'description' => $movie['description'],
        'backdrop' => $movie['backdrop'] ?? 'https://image.tmdb.org/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png',
        'year' => $movie['year'],
        'rating' => $movie['imdb_rating'],
        'quality' => $movie['quality'] ?? 'HD'
    ];
}

foreach ($hero_series as $series) {
    $hero_items[] = [
        'id' => $series['id'],
        'type' => 'series',
        'title' => $series['title'],
        'description' => $series['description'],
        'backdrop' => $series['backdrop'] ?? 'https://image.tmdb.org/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png',
        'year' => $series['year'],
        'rating' => $series['imdb_rating'],
        'seasons' => $series['seasons'] ?? 1
    ];
}

foreach ($hero_live as $live) {
    $hero_items[] = [
        'id' => $live['id'],
        'type' => 'live',
        'title' => $live['name'],
        'description' => $live['description'] ?? 'بث مباشر بجودة عالية',
        'backdrop' => $live['backdrop'] ?? 'https://image.tmdb.org/t/p/original/wwemzKWzjKYJFfCeiB57q3r4Bcm.png',
        'quality' => $live['quality'] ?? 'HD'
    ];
}

// ترتيب عشوائي للهيرو
shuffle($hero_items);
$hero_items = array_slice($hero_items, 0, 12);

// ===== مسلسلات رمضان (معاينة للصفحة الرئيسية) =====
// نستخدم استعلام مشابه لصفحة رمضان الكاملة، لكن نقتصر على عدد أقل لتسريع التحميل
$ramadan_preview = $pdo->query(
    "SELECT * FROM series 
    WHERE year = '2026' 
       OR (title LIKE '%رمضان%' AND year >= '2025')
    ORDER BY id DESC
    LIMIT 12"
)->fetchAll();

// تأكد من أن الناتج مصفوفة حتى لا يحدث خطأ في foreach
if (!is_array($ramadan_preview)) {
    $ramadan_preview = [];
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ويزي برو - أفلام، مسلسلات، أنمي، وبث مباشر</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            line-height: 1.6;
        }

        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --arabic: #0e4620;
            --foreign: #1a4b8c;
            --turkish: #9b2c2c;
            --indian: #ff9933;
            --asian: #4a1d6d;
            --anime: #e50914;
            --egyptian: #ce1126;
            --imdb: gold;
            --dark: #0a0a0a;
            --light: #1a1a1a;
            --lighter: #2a2a2a;
            --text-gray: #b3b3b3;
        }

        /* ===== الهيدر ===== */
        .header {
            background: linear-gradient(to bottom, rgba(10,10,10,0.95), transparent);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 15px 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: 0.3s;
            backdrop-filter: blur(10px);
        }

        .header.scrolled {
            background: rgba(10,10,10,0.98);
            padding: 12px 60px;
            border-bottom: 1px solid rgba(229,9,20,0.3);
        }

        .logo h1 {
            color: var(--primary);
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .logo span {
            color: #fff;
        }

        .nav-list {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .nav-list > li > a {
            color: #fff;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            position: relative;
            padding: 5px 0;
        }

        .nav-list > li > a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: 0.3s;
        }

        .nav-list > li > a:hover::after,
        .nav-list > li > a.active::after {
            width: 100%;
        }

        .nav-list > li > a:hover,
        .nav-list > li > a.active {
            color: var(--primary);
        }

        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .dropdown-toggle i {
            font-size: 12px;
            transition: 0.3s;
        }

        .dropdown:hover .dropdown-toggle i {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #1a1a1a;
            min-width: 250px;
            border-radius: 12px;
            padding: 10px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid #333;
            opacity: 0;
            visibility: hidden;
            transform: translateY(10px);
            transition: 0.3s;
            z-index: 1000;
        }

        .dropdown:hover .dropdown-menu {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-menu li {
            list-style: none;
        }

        .dropdown-menu a {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #b3b3b3;
            text-decoration: none;
            transition: 0.3s;
            font-size: 14px;
        }

        .dropdown-menu a:hover {
            background: var(--primary);
            color: white;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: rgba(255,255,255,0.1);
            border-radius: 30px;
            padding: 5px 15px;
            border: 1px solid rgba(255,255,255,0.1);
            transition: 0.3s;
        }

        .search-box:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(229,9,20,0.5);
        }

        .search-input {
            background: transparent;
            border: none;
            padding: 8px 5px;
            color: #fff;
            width: 200px;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
        }

        .search-input::placeholder {
            color: var(--text-gray);
        }

        .search-btn {
            background: transparent;
            border: none;
            color: #fff;
            cursor: pointer;
            transition: 0.3s;
        }

        .search-btn:hover {
            color: var(--primary);
            transform: scale(1.1);
        }

        .login-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            transition: 0.3s;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .login-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229,9,20,0.4);
        }

        .login-btn i {
            font-size: 16px;
        }

        /* ===== شريط الفئات السريعة ===== */
        .categories-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 20px 60px;
            background: #0a0a0a;
            border-bottom: 1px solid #333;
            margin-top: 80px;
        }

        .category-pill {
            background: #1a1a1a;
            color: #fff;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            transition: 0.3s;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .category-pill:hover {
            background: var(--primary);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .category-pill i {
            color: var(--primary);
            font-size: 14px;
        }

        .category-pill:hover i {
            color: #fff;
        }

        .category-pill.egyptian {
            background: #ce1126;
        }

        .category-pill.egyptian:hover {
            background: #a50e1f;
        }

        .category-pill.asian {
            background: #4a1d6d;
        }

        .category-pill.asian:hover {
            background: #2e1245;
        }

        .category-pill.imdb {
            background: gold;
            color: black;
        }

        .category-pill.imdb:hover {
            background: #e6c200;
        }

        /* ===== هيرو سلايدر ===== */
        .hero-swiper {
            width: 100%;
            height: 70vh;
            position: relative;
        }

        .hero-slide {
            position: relative;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .hero-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            animation: zoomEffect 20s infinite alternate;
        }

        @keyframes zoomEffect {
            0% { transform: scale(1); }
            100% { transform: scale(1.1); }
        }

        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(10,10,10,0.95) 20%, rgba(10,10,10,0.6) 50%, rgba(10,10,10,0.3) 80%);
            display: flex;
            align-items: center;
            padding: 0 80px;
        }

        .hero-content {
            max-width: 700px;
            animation: fadeInUp 1s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-title {
            font-size: 56px;
            font-weight: 900;
            margin-bottom: 20px;
            color: #fff;
            text-shadow: 0 0 30px rgba(0,0,0,0.5);
            line-height: 1.2;
        }

        .hero-meta {
            display: flex;
            gap: 25px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .hero-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-gray);
            font-size: 16px;
        }

        .hero-meta-item i {
            color: var(--primary);
        }

        .hero-description {
            font-size: 18px;
            margin-bottom: 35px;
            color: var(--text-gray);
            line-height: 1.8;
            max-width: 600px;
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
        }

        .btn {
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            transition: 0.4s;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 5px 20px rgba(229,9,20,0.4);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(229,9,20,0.6);
        }

        .btn-outline {
            background: transparent;
            color: #fff;
            border: 2px solid rgba(255,255,255,0.3);
            backdrop-filter: blur(5px);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: #fff;
            background: rgba(229,9,20,0.1);
            transform: translateY(-3px);
        }

        .btn-live {
            background: linear-gradient(135deg, #ff6b6b, #e50914);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(229,9,20,0.7); }
            70% { box-shadow: 0 0 0 15px rgba(229,9,20,0); }
            100% { box-shadow: 0 0 0 0 rgba(229,9,20,0); }
        }
        /* ===== إخفاء نقاط السلايدر تماماً ===== */
.swiper-pagination,
.swiper-pagination-bullet,
.swiper-pagination-bullet-active,
.swiper-pagination-bullet-active-main,
.swiper-pagination-bullet-active-next,
.swiper-pagination-bullet-active-prev {
    display: none !important;
    opacity: 0 !important;
    visibility: hidden !important;
}

        /* ===== أقسام المحتوى ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 0px;
        }

        .section {
            margin-bottom: 60px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            font-size: 28px;
        }

        .section-title.arabic i {
            color: var(--arabic);
        }

        .section-title.foreign i {
            color: var(--foreign);
        }

        .section-title.turkish i {
            color: var(--turkish);
        }

        .section-title.indian i {
            color: var(--indian);
        }

        .section-title.asian i {
            color: var(--asian);
        }

        .section-title.anime i {
            color: var(--anime);
        }

        .section-title.egyptian i {
            color: var(--egyptian);
        }

        .section-title.imdb i {
            color: var(--imdb);
        }

        .view-all {
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            padding: 8px 20px;
            border-radius: 30px;
            background: rgba(255,255,255,0.05);
        }

        .view-all:hover {
            color: #fff;
            background: var(--primary);
        }

        .view-all.egyptian:hover {
            background: var(--egyptian);
        }

        .view-all.asian:hover {
            background: var(--asian);
        }

        .view-all.imdb:hover {
            background: var(--imdb);
            color: black;
        }

        /* ===== بطاقات المحتوى ===== */
        .swiper {
            width: 100%;
            padding: 10px 0;
        }

        .swiper-slide {
            width: 180px;
            height: auto;
            transition: 0.4s;
            border-radius: 50px;
            border: 20px red;
        }

        .movie-card {
            background: var(--light);
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
            display: block;
            height: 100%;
            border: 1px solid #333;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            border: 3px solid #c30e0e;
        }

        .movie-card:hover {
            transform: translateY(-8px);
            border-color: var(--primary);
            box-shadow: 0 10px 25px rgba(229,9,20,0.2);
        }

        .movie-card.arabic-card:hover {
            border-color: var(--arabic);
            box-shadow: 0 10px 25px rgba(14,70,32,0.3);
        }

        .movie-card.foreign-card:hover {
            border-color: var(--foreign);
            box-shadow: 0 10px 25px rgba(26,75,140,0.3);
        }

        .movie-card.turkish-card:hover {
            border-color: var(--turkish);
            box-shadow: 0 10px 25px rgba(155,44,44,0.3);
        }

        .movie-card.indian-card:hover {
            border-color: var(--indian);
            box-shadow: 0 10px 25px rgba(255,153,51,0.3);
        }

        .movie-card.asian-card:hover {
            border-color: var(--asian);
            box-shadow: 0 10px 25px rgba(74,29,109,0.3);
        }

        .movie-card.anime-card:hover {
            border-color: var(--anime);
            box-shadow: 0 10px 25px rgba(229,9,20,0.3);
        }

        .movie-card.egyptian-card:hover {
            border-color: var(--egyptian);
            box-shadow: 0 10px 25px rgba(206,17,38,0.3);
        }

        .movie-card.imdb-card:hover {
            border-color: gold;
            box-shadow: 0 10px 25px rgba(255,215,0,0.3);
        }

        .movie-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }

        .movie-info {
            padding: 15px;
        }

        .movie-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .movie-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-gray);
            font-size: 13px;
        }

        .country-badge {
            background: rgba(14,70,32,0.3);
            color: #fff;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .egyptian-badge {
            background: var(--egyptian);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .asian-badge {
            background: var(--asian);
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .free-badge {
            background: #27ae60;
            color: #fff;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
        }
        .membership-badge-on-poster.youtube {
            background: #e50914;
            color: #fff;
        }

        .membership-badge-on-poster.series {
            background: #9b59b6;
            color: #fff;
        }

        .anime-badge {
            background: var(--anime);
            color: #fff;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }

        .imdb-badge {
            background: gold;
            color: black;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        /* ===== أزرار التحكم ===== */
        .custom-prev,
        .custom-next {
            background: var(--primary);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.3s;
            box-shadow: 0 0 15px rgba(229,9,20,0.3);
            font-size: 18px;
        }
        .movie-card:hover {
    border-color: var(--primary);
    box-shadow: 0 10px 25px rgba(229,9,20,0.3);
}
.movie-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--primary), #ff6b6b);
    opacity: 0;
    transition: 0.3s;
}
.rating-badge {
    background: var(--primary);
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-weight: 700;
    font-size: 14px;
    box-shadow: 0 0 10px rgba(229,9,20,0.5);
}
.rank-badge {
    background: var(--primary);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    z-index: 10;
    box-shadow: 0 0 10px rgba(229,9,20,0.5);
}
.custom-prev, .custom-next {
    background: var(--primary);
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    box-shadow: 0 0 15px rgba(229,9,20,0.3);
}

        .custom-prev:hover,
        .custom-next:hover {
            background: var(--primary-dark);
            transform: scale(1.1);
        }

        .custom-prev.arabic,
        .custom-next.arabic {
            background: var(--arabic);
        }

        .custom-prev.foreign,
        .custom-next.foreign {
            background: var(--foreign);
        }

        .custom-prev.turkish,
        .custom-next.turkish {
            background: var(--turkish);
        }

        .custom-prev.indian,
        .custom-next.indian {
            background: var(--indian);
        }

        .custom-prev.asian,
        .custom-next.asian {
            background: var(--asian);
        }

        .custom-prev.anime,
        .custom-next.anime {
            background: var(--anime);
        }

        .custom-prev.egyptian,
        .custom-next.egyptian {
            background: var(--egyptian);
        }

        .custom-prev.imdb,
        .custom-next.imdb {
            background: gold;
            color: black;
        }

        .custom-prev.egyptian:hover,
        .custom-next.egyptian:hover {
            background: #a50e1f;
        }

        .custom-prev.asian:hover,
        .custom-next.asian:hover {
            background: #2e1245;
        }

        .custom-prev.imdb:hover,
        .custom-next.imdb:hover {
            background: #e6c200;
        }

        .swiper-button-prev,
        .swiper-button-next {
            display: none !important;
        }

        .swiper-pagination-bullet {
            background: var(--text-gray);
            opacity: 0.5;
        }
        /* ===== اقتراحات البحث ===== */
.search-box {
    position: relative;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    right: 0;
    left: 0;
    background: #1a1a1a;
    border: 1px solid #333;
    border-radius: 10px;
    margin-top: 5px;
    z-index: 1000;
    display: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    overflow: hidden;
}

.search-suggestions.active {
    display: block;
}

.suggestion-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 15px;
    text-decoration: none;
    color: #fff;
    transition: 0.3s;
    border-bottom: 1px solid #333;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item:hover {
    background: #e50914;
}

.suggestion-poster {
    width: 40px;
    height: 60px;
    object-fit: cover;
    border-radius: 5px;
}

.suggestion-info {
    flex: 1;
}

.suggestion-title {
    font-weight: 700;
    margin-bottom: 3px;
    font-size: 14px;
}

.suggestion-meta {
    color: #b3b3b3;
    font-size: 12px;
    display: flex;
    gap: 10px;
}

.suggestion-type {
    background: #333;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 10px;
}
/* ===== تنسيقات متقدمة للأزرار ===== */

/* === زر الاشتراك العام === */
.membership-btn {
    background: linear-gradient(45deg, #e50914, #ff4d4d, #e50914);
    background-size: 200% 200%;
    color: white;
    padding: 10px 25px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    overflow: hidden;
    transition: all 0.4s ease;
    box-shadow: 0 5px 20px rgba(229, 9, 20, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.2);
    animation: gradientMove 3s ease infinite;
}

@keyframes gradientMove {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.membership-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 15px 30px rgba(229, 9, 20, 0.7);
    border-color: rgba(255, 255, 255, 0.5);
}

.membership-btn i {
    animation: crownSpin 2s infinite;
}

@keyframes crownSpin {
    0% { transform: rotate(0deg); }
    25% { transform: rotate(10deg); }
    75% { transform: rotate(-10deg); }
    100% { transform: rotate(0deg); }
}

.membership-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
}

.membership-btn:hover::before {
    left: 100%;
}

/* === زر اسم المستخدم === */
.username-link {
    background: rgba(229, 9, 20, 0.1);
    color: #e50914 !important;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 2px solid rgba(229, 9, 20, 0.3);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.username-link:hover {
    background: rgba(229, 9, 20, 0.2);
    border-color: #e50914;
    transform: translateY(-2px);
    box-shadow: 0 5px 20px rgba(229, 9, 20, 0.3);
}

.username-link i {
    animation: userPulse 2s infinite;
}

@keyframes userPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); }
}

/* === زر تسجيل الخروج === */
.logout-btn {
    background: transparent;
    color: #fff;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.logout-btn:hover {
    border-color: #e50914;
    color: #e50914;
    transform: translateY(-2px);
    background: rgba(229, 9, 20, 0.1);
    box-shadow: 0 5px 20px rgba(229, 9, 20, 0.2);
}

.logout-btn:hover i {
    animation: slideLeft 0.5s ease;
}

@keyframes slideLeft {
    0% { transform: translateX(0); }
    50% { transform: translateX(-5px); }
    100% { transform: translateX(0); }
}

/* === زر لوحة التحكم (للمشرفين) === */
.admin-btn {
    background: linear-gradient(45deg, #3498db, #2980b9);
    color: white;
    padding: 8px 20px;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
}

.admin-btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(52, 152, 219, 0.5);
    border-color: rgba(255, 255, 255, 0.5);
}

.admin-btn i {
    animation: cogSpin 3s infinite linear;
}

@keyframes cogSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* === تنسيقات أزرار خطط العضوية === */
.plan-btn {
    display: block;
    text-align: center;
    padding: 15px;
    border-radius: 12px;
    text-decoration: none;
    font-weight: 800;
    font-size: 18px;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    z-index: 1;
}

.plan-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s ease;
    z-index: -1;
}

.plan-btn:hover::before {
    left: 100%;
}

.plan-btn:hover {
    transform: translateY(-5px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
}

/* === زر الخطة العادية === */
.plan-btn[data-plan="basic"] {
    background: linear-gradient(135deg, #6c757d, #495057);
    box-shadow: 0 10px 20px rgba(108, 117, 125, 0.3);
}

.plan-btn[data-plan="basic"]:hover {
    background: linear-gradient(135deg, #5a6268, #343a40);
}

/* === زر الخطة المميزة === */
.plan-btn[data-plan="premium"] {
    background: linear-gradient(135deg, #e50914, #ff4d4d);
    box-shadow: 0 10px 20px rgba(229, 9, 20, 0.4);
    animation: premiumPulse 2s infinite;
}

@keyframes premiumPulse {
    0% { box-shadow: 0 10px 20px rgba(229, 9, 20, 0.4); }
    50% { box-shadow: 0 20px 40px rgba(229, 9, 20, 0.7); }
    100% { box-shadow: 0 10px 20px rgba(229, 9, 20, 0.4); }
}

/* === زر خطة VIP === */
.plan-btn[data-plan="vip"] {
    background: linear-gradient(135deg, gold, #ffd700, #f1c40f);
    color: black !important;
    box-shadow: 0 10px 20px rgba(255, 215, 0, 0.3);
    position: relative;
}

.plan-btn[data-plan="vip"]::after {
    content: '👑';
    position: absolute;
    top: -10px;
    right: -10px;
    font-size: 20px;
    animation: float 2s infinite ease-in-out;
}

@keyframes float {
    0% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
    100% { transform: translateY(0); }
}

/* === تنسيق البطاقات مع تأثيرات حركية === */
.plan-card {
    position: relative;
    overflow: hidden;
}
/* ===== زر تسجيل الدخول الفاخر ===== */
.login-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 700;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: 2px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
    letter-spacing: 0.5px;
    z-index: 1;
}

.login-btn i {
    font-size: 18px;
    transition: all 0.4s ease;
}

.login-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 20px 40px rgba(102, 126, 234, 0.5);
    border-color: rgba(255, 255, 255, 0.6);
}

.login-btn:hover i {
    transform: rotate(360deg) scale(1.2);
}

.login-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.6s ease;
    z-index: -1;
}

.login-btn:hover::before {
    left: 100%;
}

/* === تصميم بديل - ناري === */
.login-btn-fire {
    background: linear-gradient(45deg, #ff416c, #ff4b2b);
    box-shadow: 0 10px 20px rgba(255, 65, 108, 0.4);
}

.login-btn-fire:hover {
    background: linear-gradient(45deg, #ff4b2b, #ff416c);
    box-shadow: 0 20px 40px rgba(255, 75, 43, 0.6);
}

/* === تصميم بديل - ثلجي === */
.login-btn-ice {
    background: linear-gradient(135deg, #36d1dc, #5b86e5);
    box-shadow: 0 10px 20px rgba(54, 209, 220, 0.3);
}

.login-btn-ice:hover {
    background: linear-gradient(135deg, #5b86e5, #36d1dc);
    box-shadow: 0 20px 40px rgba(91, 134, 229, 0.5);
}

/* === تصميم بديل - ذهبي === */
.login-btn-gold {
    background: linear-gradient(135deg, #f7971e, #ffd200);
    color: #000 !important;
    box-shadow: 0 10px 20px rgba(247, 151, 30, 0.4);
}

.login-btn-gold:hover {
    background: linear-gradient(135deg, #ffd200, #f7971e);
    box-shadow: 0 20px 40px rgba(255, 210, 0, 0.6);
}

/* === تصميم بديل - نيون === */
.login-btn-neon {
    background: transparent;
    border: 2px solid #0ff;
    color: #0ff !important;
    box-shadow: 0 0 20px #0ff;
    animation: neonPulse 1.5s infinite;
}

@keyframes neonPulse {
    0% { box-shadow: 0 0 20px #0ff; }
    50% { box-shadow: 0 0 40px #0ff, 0 0 60px #0ff; }
    100% { box-shadow: 0 0 20px #0ff; }
}

.login-btn-neon:hover {
    background: #0ff;
    color: #000 !important;
    box-shadow: 0 0 40px #0ff, 0 0 80px #0ff;
}

/* === تصميم بديل - زجاجي === */
.login-btn-glass {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
}

.login-btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(15px);
}

/* === تصميم بديل - متوهج === */
.login-btn-glow {
    background: #e50914;
    position: relative;
    animation: glow 2s infinite;
}

@keyframes glow {
    0% { box-shadow: 0 0 20px #e50914; }
    50% { box-shadow: 0 0 40px #e50914, 0 0 60px #e50914; }
    100% { box-shadow: 0 0 20px #e50914; }
}

.login-btn-glow:hover {
    transform: translateY(-3px) scale(1.05);
    animation: none;
    box-shadow: 0 0 50px #e50914;
}

/* === تصميم بديل - مع تأثير فتح الباب === */
.login-btn-door {
    background: #2c3e50;
    position: relative;
    overflow: hidden;
}

.login-btn-door::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transform: translateX(-100%);
}

.login-btn-door:hover::after {
    transform: translateX(100%);
    transition: transform 0.6s ease;
}

/* === تصميم بديل - 3D === */
.login-btn-3d {
    background: #e50914;
    transform-style: preserve-3d;
    transform: perspective(500px) rotateX(0deg);
    box-shadow: 0 10px 0 #b20710;
}

.login-btn-3d:hover {
    transform: perspective(500px) rotateX(10deg) translateY(-5px);
    box-shadow: 0 15px 0 #b20710;
}

.login-btn-3d:active {
    transform: perspective(500px) rotateX(0deg) translateY(5px);
    box-shadow: 0 5px 0 #b20710;
}

/* === تصميم بديل - مع أيقونة متحركة === */
.login-btn-icon {
    background: #e50914;
    position: relative;
}

.login-btn-icon i {
    position: relative;
    animation: slideDoor 2s infinite;
}

@keyframes slideDoor {
    0% { transform: translateX(0); }
    25% { transform: translateX(5px); }
    50% { transform: translateX(0); }
    75% { transform: translateX(-5px); }
    100% { transform: translateX(0); }
}

/* === تصميم بديل - مع تأثير نبض === */
.login-btn-pulse {
    background: #e50914;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* === تصميم بديل - متدرج متحرك === */
.login-btn-gradient {
    background: linear-gradient(270deg, #e50914, #ff6b6b, #e50914);
    background-size: 200% 200%;
    animation: gradientMove 3s ease infinite;
}

@keyframes gradientMove {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* === تصميم بديل - مع تأثير الظل المتعدد === */
.login-btn-shadow {
    background: #e50914;
    box-shadow: 0 5px 0 #b20710, 0 10px 20px rgba(229, 9, 20, 0.4);
}

.login-btn-shadow:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 0 #b20710, 0 20px 30px rgba(229, 9, 20, 0.6);
}

/* === تصميم بديل - مع تأثير الكتابة === */
.login-btn-type {
    background: transparent;
    border: 2px solid #e50914;
    color: #e50914 !important;
    overflow: hidden;
}

.login-btn-type span {
    position: relative;
    z-index: 1;
}

.login-btn-type::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: #e50914;
    transition: left 0.4s ease;
}

.login-btn-type:hover {
    color: white !important;
}

.login-btn-type:hover::before {
    left: 0;
}

.plan-card::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    opacity: 0;
    transition: opacity 0.5s ease;
    pointer-events: none;
}

.plan-card:hover::before {
    opacity: 1;
    animation: rotate 10s linear infinite;
}

@keyframes rotate {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.plan-card.popular {
    animation: borderGlow 2s infinite;
}

@keyframes borderGlow {
    0% { box-shadow: 0 0 20px rgba(229, 9, 20, 0.5); }
    50% { box-shadow: 0 0 40px rgba(229, 9, 20, 0.8); }
    100% { box-shadow: 0 0 20px rgba(229, 9, 20, 0.5); }
}

/* === تنسيق شارة "الأكثر طلباً" === */
.popular-badge {
    position: relative;
    overflow: hidden;
}

.popular-badge::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* === تنسيق روابط تسجيل الدخول العادية === */
.auth-links a:not(.membership-btn):not(.username-link):not(.logout-btn):not(.admin-btn) {
    color: #fff;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 30px;
    transition: all 0.3s ease;
    position: relative;
}

.auth-links a:not(.membership-btn):not(.username-link):not(.logout-btn):not(.admin-btn)::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 2px;
    background: #e50914;
    transition: width 0.3s ease;
}

.auth-links a:not(.membership-btn):not(.username-link):not(.logout-btn):not(.admin-btn):hover {
    color: #e50914;
}

.auth-links a:not(.membership-btn):not(.username-link):not(.logout-btn):not(.admin-btn):hover::after {
    width: 80%;
}
        .swiper-pagination-bullet-active {
            background: var(--primary);
            opacity: 1;
        }

        /* ===== فوتر ===== */
        .footer {
            background: #0a0a0a;
            padding: 60px 60px 30px;
            margin-top: 60px;
            border-top: 1px solid #333;
        }

        .footer-content {
            max-width: 1400px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
        }

        .footer-logo h3 {
            color: var(--primary);
            font-size: 24px;
            margin-bottom: 15px;
        }

        .footer-logo p {
            color: var(--text-gray);
            line-height: 1.8;
        }

        .footer-links h4 {
            color: #fff;
            margin-bottom: 20px;
            font-size: 18px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-links h4::after {
            content: '';
            position: absolute;
            bottom: 0;
            right: 0;
            width: 40px;
            height: 3px;
            background: var(--primary);
        }
        /* ===== شارة العضوية على البوستر ===== */
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

/* تصميم بديل للشارة في الزاوية */
.membership-badge-corner {
    position: absolute;
    top: 0;
    right: 0;
    width: 0;
    height: 0;
    border-style: solid;
    border-width: 0 50px 50px 0;
    z-index: 10;
}

.membership-badge-corner.premium {
    border-color: transparent #e50914 transparent transparent;
}

.membership-badge-corner.vip {
    border-color: transparent gold transparent transparent;
}

.membership-badge-corner span {
    position: absolute;
    top: 5px;
    right: 5px;
    color: white;
    font-size: 10px;
    font-weight: bold;
    transform: rotate(45deg);
    width: 30px;
    text-align: center;
}

.membership-badge-corner.vip span {
    color: black;
}

/* تأثير توهج للشارة */
.membership-badge-on-poster {
    box-shadow: 0 0 15px currentColor;
}

.membership-badge-on-poster.premium {
    box-shadow: 0 0 15px rgba(229, 9, 20, 0.7);
}

.membership-badge-on-poster.vip {
    box-shadow: 0 0 15px rgba(255, 215, 0, 0.7);
}

        .footer-links ul {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 12px;
        }

        .footer-links a {
            color: var(--text-gray);
            text-decoration: none;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .footer-links a:hover {
            color: var(--primary);
            padding-right: 8px;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            margin-top: 30px;
            border-top: 1px solid #333;
            color: var(--text-gray);
            font-size: 14px;
        }

        /* ===== التجاوب ===== */
        @media (max-width: 1200px) {
            .header { padding: 15px 40px; }
            .categories-bar { padding: 20px 40px; }
            .container { padding: 40px; }
        }

        @media (max-width: 992px) {
            .nav-list { display: none; }
            .header { padding: 15px 30px; }
            .categories-bar { padding: 20px 30px; }
            .container { padding: 30px; }
            .footer-content { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 768px) {
            .header { padding: 12px 20px; }
            .search-box { display: none; }
            .login-btn span { display: none; }
            .login-btn { padding: 8px 12px; }
            .categories-bar { padding: 15px 20px; gap: 8px; }
            .category-pill { padding: 6px 15px; font-size: 13px; }
            .hero-swiper { height: 60vh; }
            .hero-overlay { padding: 0 30px; }
            .hero-title { font-size: 36px; }
            .hero-description { font-size: 16px; }
            .hero-buttons { flex-direction: column; }
            .container { padding: 20px; }
            .section-title { font-size: 20px; }
            .custom-prev, .custom-next { width: 35px; height: 35px; }
            .footer { padding: 40px 20px 20px; }
            .footer-content { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- الهيدر -->
    <header class="header" id="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        
        <nav>
            <ul class="nav-list">
                <li><a href="index.php" class="active">الرئيسية</a></li>
                <li><a href="admin/movies.php">أفلام</a></li>
                <li><a href="series.php">مسلسلات</a></li>
                
                <li><a href="anime-series.php">أنمي</a></li>
                <li><a href="free.php">مجاني</a></li>   
             <!-- أضف هذا مع روابط القائمة -->

    <?php if (isset($_SESSION['user_id'])): ?>
        <a href="admin/profile.php" class="username-link">
            <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'مستخدم'); ?>
        </a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin'): ?>
            <a href="admin/dashboard.php" class="admin-btn">
                <i class="fas fa-cog"></i> لوحة التحكم
            </a>
        <?php endif; ?>
        <a href="admin/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> تسجيل خروج
        </a>
    <?php else: ?>
        <a href="admin/login.php" class="login-btn">
    <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
</a>
        <a href="membership-plans.php" class="membership-btn">
            <i class="fas fa-crown"></i> اشترك الآن
        </a>
    <?php endif; ?>

        </nav>
        
        <div class="header-actions">
            <div class="search-box">
    <form action="search.php" method="GET" id="searchForm">
        <input type="text" name="q" class="search-input" 
               placeholder="ابحث عن فيلم أو مسلسل..." 
               id="searchInput"
               autocomplete="off">
        <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
    </form>
    <div class="search-suggestions" id="searchSuggestions"></div>

    </header>

    <!-- شريط الفئات السريعة -->
    <div class="categories-bar">
        <a href="#recommended" class="category-pill imdb"><i class="fas fa-star"></i> أفضل IMDb</a>
        <a href="#free" class="category-pill"><i class="fas fa-gift"></i> مجاناً</a>
        <a href="#trending" class="category-pill"><i class="fas fa-fire"></i> الأكثر انتظاراً</a>
        <a href="#new" class="category-pill"><i class="fas fa-calendar-plus"></i> أفلام جديدة</a>
        <a href="#yemen" class="category-pill"><i class="fas fa-map-marker-alt"></i> أفضل 10 في اليمن</a>
        <a href="#arabic-movies" class="category-pill"><i class="fas fa-film"></i> أفلام عربية</a>
        <a href="#foreign-movies" class="category-pill"><i class="fas fa-film"></i> أفلام أجنبية</a>
        <a href="#turkish-movies" class="category-pill"><i class="fas fa-film"></i> أفلام تركية</a>
        <a href="#indian-movies" class="category-pill"><i class="fas fa-film"></i> أفلام هندية</a>
        <a href="#asian-movies" class="category-pill asian"><i class="fas fa-film"></i> أفلام آسيوية</a>
        <a href="#arabic-series" class="category-pill"><i class="fas fa-tv"></i> مسلسلات عربية</a>
        <a href="#foreign-series" class="category-pill"><i class="fas fa-tv"></i> مسلسلات أجنبية</a>
        <a href="#turkish-series" class="category-pill"><i class="fas fa-tv"></i> مسلسلات تركية</a>
        <a href="#indian-series" class="category-pill"><i class="fas fa-tv"></i> مسلسلات هندية</a>
        <a href="#asian-series" class="category-pill asian"><i class="fas fa-tv"></i> مسلسلات آسيوية</a>
        <a href="#anime-series" class="category-pill"><i class="fas fa-dragon"></i> أنمي</a>
        <a href="#egyptian-movies" class="category-pill egyptian"><i class="fas fa-film"></i> أفلام مصرية</a>
        <a href="#egyptian-series" class="category-pill egyptian"><i class="fas fa-tv"></i> مسلسلات مصرية</a>
    </div>

    <!-- هيرو سلايدر -->
    <div class="hero-swiper swiper heroSwiper">
        <div class="swiper-wrapper">
            <?php foreach ($hero_items as $item): ?>
            <div class="swiper-slide hero-slide">
                <img class="hero-backdrop" src="<?php echo $item['backdrop']; ?>" alt="<?php echo $item['title']; ?>">
                <div class="hero-overlay">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <?php if ($item['type'] == 'movie'): ?>
                                <i class="fas fa-film"></i> فيلم
                            <?php elseif ($item['type'] == 'series'): ?>
                                <i class="fas fa-tv"></i> مسلسل
                            <?php else: ?>
                               
                            <?php endif; ?>
                        </div>
                        
                        <h2 class="hero-title"><?php echo $item['title']; ?></h2>
                        
                        <div class="hero-meta">
                            <?php if (!empty($item['year'])): ?>
                            <span class="hero-meta-item">
                                <i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars($item['year']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['rating'])): ?>
                            <span class="hero-meta-item">
                                <i class="fas fa-star" style="color: gold;"></i> <?php echo htmlspecialchars($item['rating']); ?>
                            </span>
                            <?php endif; ?>
                            
                            <?php if (!empty($item['type']) && $item['type'] == 'series' && !empty($item['seasons'])): ?>
                            <span class="hero-meta-item">
                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($item['seasons']); ?> مواسم
                            </span>
                            <?php endif; ?>
                            
                            
                            
                            <?php if (isset($item['quality'])): ?>
                            <span class="hero-meta-item">
                                <i class="fas fa-hd"></i> <?php echo $item['quality']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="hero-description">
                            <?php 
                            $description = $item['description'] ?? 'استمتع بمشاهدة أفضل المحتوى الحصري بجودة عالية';
                            if (empty($description)) {
                                $description = 'استمتع بمشاهدة أفضل المحتوى الحصري بجودة عالية';
                            }
                            echo mb_substr($description, 0, 180);
                            if (mb_strlen($description) > 180) {
                                echo '...';
                            }
                            ?>
                        </p>
                        
                        <div class="hero-buttons">
                            <?php if ($item['type'] == 'movie'): ?>
                                <a href="movie.php?id=<?php echo $item['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> مشاهدة الآن
                                </a>
                                <a href="movie.php?id=<?php echo $item['id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-info-circle"></i> التفاصيل
                                </a>
                            <?php elseif ($item['type'] == 'series'): ?>
                                <a href="series.php?id=<?php echo $item['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-play"></i> مشاهدة المسلسل
                                </a>
                                <a href="series.php?id=<?php echo $item['id']; ?>" class="btn btn-outline">
                                    <i class="fas fa-list"></i> الحلقات
                                </a>
                            <?php else: ?>
                                
                                
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
    </div>

    <div class="container">
        <!-- ===== قسم التوصيات: أعلى المسلسلات تقييماً ===== -->
        <!-- z -->
<section class="section">
    <div class="section-header">
        <h2 class="section-title">
            <i class="fas fa-moon" style="color: gold;"></i> 
            مسلسلات رمضان 2026
        </h2>
        <a href="ramadan-2026.php" class="view-all">
            عرض الكل <i class="fas fa-arrow-left"></i>
        </a>
    </div>
    
    <div class="swiper ramadanSwiper">
        <div class="swiper-wrapper">
            <?php if (!empty($ramadan_preview)): ?>
                <?php foreach ($ramadan_preview as $series): ?>
                <div class="swiper-slide">
                    <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card">
                        <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/gold?text=' . urlencode($series['title']); ?>" 
                             class="movie-poster" alt="<?php echo $series['title']; ?>">
                        <div class="movie-info">
                            <div class="movie-title"><?php echo $series['title']; ?></div>
                            <div class="movie-meta">
                                <span><?php echo $series['year']; ?></span>
                                <span>🌙 رمضان</span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="swiper-slide" style="text-align:center; padding:40px;">
                    <p style="color:#b3b3b3;">لا توجد مسلسلات رمضان حالياً</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

        <!-- ===== قسم 2: يعرض مجاناً ===== -->
        <!-- قسم يعرض مجاناً - أضف هذا في المكان المناسب -->
<section id="free" class="section">
    <div class="section-header">
        <h2 class="section-title"><i class="fas fa-gift" style="color: #27ae60;"></i> يعرض مجاناً</h2>
        <a href="free.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
    </div>
    
   
    
    <div class="swiper freeSwiper">
        <div class="swiper-wrapper">
            <?php if (!empty($free_featured)): ?>
                <?php foreach ($free_featured as $item): ?>
                    <?php
                    // تحديد نوع المحتوى والرابط المناسب
                    $content_type = $item['content_type'] ?? 'video';
                    $url = '';
                    $target = '';
                    $badge_class = '';
                    $badge_icon = '';
                    $meta_text = '';
                    
                    if ($content_type == 'series') {
                        // مسلسل
                        $url = 'youtube-series.php?id=' . $item['id'];
                        $target = '';
                        $badge_class = 'series';
                        $badge_icon = 'fa-list';
                        $meta_text = $item['total_episodes'] . ' حلقة';
                    } elseif ($content_type == 'movie') {
                        // فيلم
                        $video_id = $item['video_id'] ?? $item['id'];
                        $url = 'https://www.youtube.com/watch?v=' . $video_id;
                        $target = 'target="_blank"';
                        $badge_class = 'youtube';
                        $badge_icon = 'fa-youtube';
                        $meta_text = 'فيلم';
                    } else {
                        // فيديو
                        $video_id = $item['video_id'] ?? $item['id'];
                        $url = 'https://www.youtube.com/watch?v=' . $video_id;
                        $target = 'target="_blank"';
                        $badge_class = 'youtube';
                        $badge_icon = 'fa-youtube';
                        $meta_text = 'فيديو';
                    }
                    
                    // تحديد الصورة
                    $thumb = getFreeImageUrl($item);
                    $title = $item['title'] ?? 'عنوان غير متوفر';
                    ?>
                    <div class="swiper-slide">
                        <a href="<?php echo $url; ?>" <?php echo $target; ?> class="movie-card">
                            <div class="poster-container">
                                <img src="<?php echo $thumb; ?>" 
                                     class="movie-poster" 
                                     alt="<?php echo htmlspecialchars($title); ?>"
                                     onerror="this.src='https://via.placeholder.com/300x169?text=No+Image'; this.onerror=null;">
                                
                                <div class="membership-badge-on-poster <?php echo $badge_class; ?>">
                                    <i class="fab <?php echo $badge_icon; ?>"></i>
                                </div>
                                
                                <?php if ($content_type == 'series'): ?>
                                <div class="episodes-badge">
                                    <i class="fas fa-film"></i> <?php echo $item['total_episodes']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo htmlspecialchars($title); ?></div>
                                <div class="movie-meta">
                                    <span class="free-badge">
                                        <i class="fas fa-gift"></i> مجاناً
                                    </span>
                                    <?php if ($content_type != 'series' && isset($item['duration'])): ?>
                                    <span class="duration-badge">
                                        <i class="far fa-clock"></i> <?php echo $item['duration']; ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <div class="movie-channel">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($item['channel_title'] ?? 'يوتيوب'); ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="swiper-slide">
                    <div style="color:#b3b3b3; padding:40px; text-align:center; background: rgba(255,255,255,0.05); border-radius:15px;">
                        <i class="fas fa-gift" style="font-size: 60px; color: #27ae60; margin-bottom: 20px;"></i>
                        <h3>لا توجد محتويات مجانية متاحة حالياً</h3>
                        <p style="margin-top: 10px;">سيتم إضافة محتوى مجاني قريباً</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="swiper-pagination"></div>
    </div>
</section>

        <!-- ===== قسم 3: أكثر القصص انتظاراً ===== -->
        <section id="trending" class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-fire" style="color: var(--primary);"></i> أكثر القصص انتظاراً</h2>
                <a href="#" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper trendingSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($most_viewed as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <span><i class="fas fa-eye"></i> <?php echo number_format($movie['views'] ?? 0); ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 4: أفلام جديدة كل يوم ===== -->
        <section id="new" class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-calendar-plus" style="color: #3498db;"></i> أفلام جديدة كل يوم</h2>
                <a href="#" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper newSwiper">
                <div class="swiper-wrapper">
                    <?php foreach (array_slice($latest_movies, 0, 20) as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/3498db?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <span><i class="fas fa-calendar"></i> جديد</span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 5: أفضل 10 أعمال في اليمن ===== -->
        <section id="yemen" class="section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-map-marker-alt" style="color: #e50914;"></i> أفضل 10 أعمال في اليمن</h2>
                <a href="#" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper yemenSwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($yemen_top)): ?>
                        <?php foreach ($yemen_top as $movie): ?>
                        <div class="swiper-slide">
                            <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card">
                                <div class="poster-container">
                                    <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($movie['title']); ?>" 
                                         class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                    <?php membershipBadgeOnPoster($movie); ?>
                                </div>
                                <div class="movie-info">
                                    <div class="movie-title"><?php echo $movie['title']; ?></div>
                                    <div class="movie-meta">
                                        <span><?php echo $movie['year']; ?></span>
                                        <span>⭐ <?php echo $movie['imdb_rating'] ?? 'N/A'; ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <div style="padding: 50px; text-align: center; background: #1a1a1a; border-radius: 12px;">
                                <i class="fas fa-map-marker-alt" style="font-size: 40px; color: #e50914; margin-bottom: 10px;"></i>
                                <p>لا توجد أعمال يمنية بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 6: أفلام عربية ===== -->
        <section id="arabic-movies" class="section">
            <div class="section-header">
                <h2 class="section-title arabic"><i class="fas fa-film" style="color: var(--arabic);"></i> أفلام عربية</h2>
                <a href="arabic-movies.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper arabicMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($arabic_movies as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card arabic-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/0e4620?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <?php if ($movie['country']): ?>
                                    <span class="country-badge"><?php echo $movie['country']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 7: أفلام أجنبية ===== -->
        <section id="foreign-movies" class="section">
            <div class="section-header">
                <h2 class="section-title foreign"><i class="fas fa-film" style="color: var(--foreign);"></i> أفلام أجنبية</h2>
                <a href="foreign-movies.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper foreignMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($foreign_movies as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card foreign-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/1a4b8c?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <span>⭐ <?php echo $movie['imdb_rating'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
            
        </section>

        <!-- ===== قسم 8: أفلام تركية ===== -->
        <section id="turkish-movies" class="section">
            <div class="section-header">
                <h2 class="section-title turkish"><i class="fas fa-film" style="color: var(--turkish);"></i> أفلام تركية</h2>
                <a href="turkish-movies.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper turkishMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($turkish_movies)): ?>
                        <?php foreach ($turkish_movies as $movie): ?>
                        <div class="swiper-slide">
                            <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card turkish-card">
                                <div class="poster-container">
                                    <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/9b2c2c?text=' . urlencode($movie['title']); ?>" 
                                         class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                    <?php membershipBadgeOnPoster($movie); ?>
                                </div>
                                <div class="movie-info">
                                    <div class="movie-title"><?php echo $movie['title']; ?></div>
                                    <div class="movie-meta">
                                        <span><?php echo $movie['year']; ?></span>
                                        <span>تركي</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <div style="padding: 50px; text-align: center; background: #1a1a1a; border-radius: 12px;">
                                <i class="fas fa-film" style="font-size: 40px; color: #9b2c2c; margin-bottom: 10px;"></i>
                                <p>لا توجد أفلام تركية بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 9: أفلام هندية ===== -->
        <section id="indian-movies" class="section">
            <div class="section-header">
                <h2 class="section-title indian"><i class="fas fa-film" style="color: var(--indian);"></i> أفلام هندية</h2>
                <a href="indian-movies.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper indianMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($indian_movies)): ?>
                        <?php foreach ($indian_movies as $movie): ?>
                        <div class="swiper-slide">
                            <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card indian-card">
                                <div class="poster-container">
                                    <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/ff9933?text=' . urlencode($movie['title']); ?>" 
                                         class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                    <?php membershipBadgeOnPoster($movie); ?>
                                </div>
                                <div class="movie-info">
                                    <div class="movie-title"><?php echo $movie['title']; ?></div>
                                    <div class="movie-meta">
                                        <span><?php echo $movie['year']; ?></span>
                                        <span>هندي</span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <div style="padding: 50px; text-align: center; background: #1a1a1a; border-radius: 12px;">
                                <i class="fas fa-film" style="font-size: 40px; color: #ff9933; margin-bottom: 10px;"></i>
                                <p>لا توجد أفلام هندية بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 10: أفلام آسيوية ===== -->
        <?php if (!empty($asian_movies)): ?>
        <section id="asian-movies" class="section">
            <div class="section-header">
                <h2 class="section-title asian">
                    <i class="fas fa-film" style="color: #4a1d6d;"></i> 
                    أفلام آسيوية (<?php echo count($asian_movies); ?>)
                </h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="custom-prev asian-movies-prev" style="background: #4a1d6d;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="custom-next asian-movies-next" style="background: #4a1d6d;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <a href="asian-movies.php" class="view-all asian">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="swiper asianMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($asian_movies as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card asian-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/4a1d6d?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <?php if ($movie['country']): ?>
                                    <span class="asian-badge"><?php echo $movie['country']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== قسم 11: مسلسلات عربية ===== -->
        <section id="arabic-series" class="section">
            <div class="section-header">
                <h2 class="section-title arabic"><i class="fas fa-tv" style="color: var(--arabic);"></i> مسلسلات عربية</h2>
                <a href="arabic-series.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper arabicSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($arabic_series as $series): ?>
                    <div class="swiper-slide">
                        <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card arabic-card">
                            <div class="poster-container">
                                <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/0e4620?text=' . urlencode($series['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $series['title']; ?>">
                                <?php membershipBadgeOnPoster($series); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $series['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                                    <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 12: مسلسلات أجنبية ===== -->
        <section id="foreign-series" class="section">
            <div class="section-header">
                <h2 class="section-title foreign"><i class="fas fa-tv" style="color: var(--foreign);"></i> مسلسلات أجنبية</h2>
                <a href="foreign-series.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper foreignSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($foreign_series as $series): ?>
                    <div class="swiper-slide">
                        <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card foreign-card">
                            <div class="poster-container">
                                <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/1a4b8c?text=' . urlencode($series['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $series['title']; ?>">
                                <?php membershipBadgeOnPoster($series); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $series['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                                    <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 13: مسلسلات تركية ===== -->
        <section id="turkish-series" class="section">
            <div class="section-header">
                <h2 class="section-title turkish"><i class="fas fa-tv" style="color: var(--turkish);"></i> مسلسلات تركية</h2>
                <a href="turkish-series.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper turkishSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($turkish_series)): ?>
                        <?php foreach ($turkish_series as $series): ?>
                        <div class="swiper-slide">
                            <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card turkish-card">
                                <div class="poster-container">
                                    <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/9b2c2c?text=' . urlencode($series['title']); ?>" 
                                         class="movie-poster" alt="<?php echo $series['title']; ?>">
                                    <?php membershipBadgeOnPoster($series); ?>
                                </div>
                                <div class="movie-info">
                                    <div class="movie-title"><?php echo $series['title']; ?></div>
                                    <div class="movie-meta">
                                        <span><?php echo $series['year']; ?></span>
                                        <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <div style="padding: 50px; text-align: center; background: #1a1a1a; border-radius: 12px;">
                                <i class="fas fa-tv" style="font-size: 40px; color: #9b2c2c; margin-bottom: 10px;"></i>
                                <p>لا توجد مسلسلات تركية بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 14: مسلسلات هندية ===== -->
        <section id="indian-series" class="section">
            <div class="section-header">
                <h2 class="section-title indian"><i class="fas fa-tv" style="color: var(--indian);"></i> مسلسلات هندية</h2>
                <a href="indian-series.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
            </div>
            <div class="swiper indianSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php if (!empty($indian_series)): ?>
                        <?php foreach ($indian_series as $series): ?>
                        <div class="swiper-slide">
                            <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card indian-card">
                                <div class="poster-container">
                                    <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/ff9933?text=' . urlencode($series['title']); ?>" 
                                         class="movie-poster" alt="<?php echo $series['title']; ?>">
                                    <?php membershipBadgeOnPoster($series); ?>
                                </div>
                                <div class="movie-info">
                                    <div class="movie-title"><?php echo $series['title']; ?></div>
                                    <div class="movie-meta">
                                        <span><?php echo $series['year']; ?></span>
                                        <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="swiper-slide">
                            <div style="padding: 50px; text-align: center; background: #1a1a1a; border-radius: 12px;">
                                <i class="fas fa-tv" style="font-size: 40px; color: #ff9933; margin-bottom: 10px;"></i>
                                <p>لا توجد مسلسلات هندية بعد</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <!-- ===== قسم 15: مسلسلات آسيوية ===== -->
        <?php if (!empty($asian_series)): ?>
        <section id="asian-series" class="section">
            <div class="section-header">
                <h2 class="section-title asian">
                    <i class="fas fa-tv" style="color: #4a1d6d;"></i> 
                    مسلسلات آسيوية (<?php echo count($asian_series); ?>)
                </h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="custom-prev asian-series-prev" style="background: #4a1d6d;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="custom-next asian-series-next" style="background: #4a1d6d;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <a href="asian-series.php" class="view-all asian">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="swiper asianSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($asian_series as $series): ?>
                    <div class="swiper-slide">
                        <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card asian-card">
                            <div class="poster-container">
                                <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/4a1d6d?text=' . urlencode($series['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $series['title']; ?>">
                                <?php membershipBadgeOnPoster($series); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $series['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                                    <span class="asian-badge"><?php echo $series['country'] ?? 'آسيوي'; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px;">
                                    <span><i class="fas fa-star" style="color: gold;"></i> <?php echo $series['imdb_rating'] ?? 'N/A'; ?></span>
                                    <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== قسم 16: مسلسلات أنمي ===== -->
        <?php if (!empty($anime_series)): ?>
        <section id="anime-series" class="section">
            <div class="section-header">
                <h2 class="section-title anime">
                    <i class="fas fa-dragon" style="color: #e50914;"></i> 
                    مسلسلات أنمي (<?php echo count($anime_series); ?>)
                </h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="custom-prev anime-prev" style="background: #e50914;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="custom-next anime-next" style="background: #e50914;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <a href="anime-series.php" class="view-all">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="swiper animeSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($anime_series as $series): ?>
                    <div class="swiper-slide">
                        <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card anime-card">
                            <div class="poster-container">
                                <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($series['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $series['title']; ?>">
                                <?php membershipBadgeOnPoster($series); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $series['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                                    <span><i class="fas fa-star" style="color: gold;"></i> <?php echo $series['imdb_rating'] ?? 'N/A'; ?></span>
                                </div>
                                <?php if ($series['seasons']): ?>
                                <div style="font-size: 12px; color: #e50914; margin-top: 5px;">
                                    <i class="fas fa-layer-group"></i> <?php echo $series['seasons']; ?> مواسم
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== قسم الأفلام المصرية ===== -->
        <?php if (!empty($egyptian_movies)): ?>
        <section id="egyptian-movies" class="section">
            <div class="section-header">
                <h2 class="section-title egyptian">
                    <i class="fas fa-film" style="color: #ce1126;"></i> 
                    أفلام مصرية (<?php echo count($egyptian_movies); ?>)
                </h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="custom-prev egyptian-movies-prev" style="background: #ce1126;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="custom-next egyptian-movies-next" style="background: #ce1126;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <a href="egyptian-movies.php" class="view-all egyptian">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="swiper egyptianMoviesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($egyptian_movies as $movie): ?>
                    <div class="swiper-slide">
                        <a href="movie.php?id=<?php echo $movie['id']; ?>" class="movie-card egyptian-card">
                            <div class="poster-container">
                                <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/ce1126?text=' . urlencode($movie['title']); ?>" 
                                     class="movie-poster" alt="<?php echo $movie['title']; ?>">
                                <?php membershipBadgeOnPoster($movie); ?>
                            </div>
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $movie['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $movie['year']; ?></span>
                                    <span class="egyptian-badge">مصري</span>
                                </div>
                                <?php if ($movie['imdb_rating']): ?>
                                <div style="font-size: 12px; color: gold; margin-top: 5px;">
                                    <i class="fas fa-star"></i> <?php echo $movie['imdb_rating']; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>

        <!-- ===== قسم المسلسلات المصرية ===== -->
        <?php if (!empty($egyptian_series)): ?>
        <section id="egyptian-series" class="section">
            <div class="section-header">
                <h2 class="section-title egyptian">
                    <i class="fas fa-tv" style="color: #ce1126;"></i> 
                    مسلسلات مصرية (<?php echo count($egyptian_series); ?>)
                </h2>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="custom-prev egyptian-series-prev" style="background: #ce1126;">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                    <button class="custom-next egyptian-series-next" style="background: #ce1126;">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <a href="egyptian-series.php" class="view-all egyptian">
                        عرض الكل <i class="fas fa-arrow-left"></i>
                    </a>
                </div>
            </div>
            
            <div class="swiper egyptianSeriesSwiper">
                <div class="swiper-wrapper">
                    <?php foreach ($egyptian_series as $series): ?>
                    <div class="swiper-slide">
                        <a href="series.php?id=<?php echo $series['id']; ?>" class="movie-card egyptian-card">
                            <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/ce1126?text=' . urlencode($series['title']); ?>" 
                                 class="movie-poster" alt="<?php echo $series['title']; ?>">
                            <div class="movie-info">
                                <div class="movie-title"><?php echo $series['title']; ?></div>
                                <div class="movie-meta">
                                    <span><i class="fas fa-calendar-alt"></i> <?php echo $series['year']; ?></span>
                                    <span class="egyptian-badge">مصري</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; margin-top: 5px; font-size: 12px;">
                                    <span><i class="fas fa-star" style="color: gold;"></i> <?php echo $series['imdb_rating'] ?? 'N/A'; ?></span>
                                    <span><i class="fas fa-layer-group"></i> <?php echo $series['seasons'] ?? 1; ?></span>
                                </div>
                            </div>
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>
        <?php endif; ?>
    </div>
<!-- ===== قسم العروض مع رسائل واتساب مفصلة ===== -->
<section class="membership-section" style="background: linear-gradient(135deg, #0a0a0a, #1a1a1a); padding: 60px 0; margin: 40px 0;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 60px;">
        <h2 style="color: #e50914; text-align: center; margin-bottom: 20px; font-size: 32px;">
            <i class="fas fa-crown" style="color: gold;"></i> اشترك في العضوية المميزة
        </h2>
        <p style="text-align: center; color: #b3b3b3; margin-bottom: 40px; font-size: 18px;">
            استمتع بمشاهدة بدون إعلانات وجودة عالية ومحتوى حصري
        </p>
        
        <div class="plans-container" style="display: flex; gap: 20px; justify-content: center; flex-wrap: wrap;">
            
            <!-- ===== الخطة العادية (مجانية) ===== -->
            <div class="plan-card" style="flex: 1; min-width: 280px; max-width: 350px; background: #1a1a1a; border-radius: 15px; padding: 30px; border: 2px solid #6c757d;">
                <h3 style="color: #6c757d; font-size: 24px; text-align: center;">عادي</h3>
                <div style="font-size: 36px; font-weight: 800; text-align: center; margin: 20px 0; color: #fff;">
                    مجاني
                </div>
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> مشاهدة مع إعلانات
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> جودة HD
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-times-circle" style="color: #e50914;"></i> بدون تحميل
                    </li>
                </ul>
                <a href="index.php" style="display: block; text-align: center; padding: 15px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;">
                    <i class="fas fa-play"></i> متابعة مجاناً
                </a>
            </div>
            
            <!-- ===== الخطة المميزة (مع واتساب مفصل) ===== -->
            <div class="plan-card" style="flex: 1; min-width: 280px; max-width: 350px; background: #1a1a1a; border-radius: 15px; padding: 30px; border: 2px solid #e50914; transform: scale(1.05); position: relative; box-shadow: 0 10px 30px rgba(229,9,20,0.3);">
                <div style="position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #e50914; color: white; padding: 5px 20px; border-radius: 30px; font-weight: bold; font-size: 14px;">
                    الأكثر طلباً
                </div>
                
                <h3 style="color: #e50914; font-size: 24px; text-align: center;">مميز</h3>
                <div style="font-size: 36px; font-weight: 800; text-align: center; margin: 20px 0; color: #fff;">
                    29.99 <span style="font-size: 16px;">ر.س</span> <span style="font-size: 14px;">/شهرياً</span>
                </div>
                
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> بدون إعلانات
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> جودة 1080p HD
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> تحميل المشاهدة
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> جهازين
                    </li>
                </ul>
                
                <!-- زر الواتساب للخطة المميزة مع جميع بيانات العميل -->
                <a href="https://wa.me/967776255680?text=<?php echo urlencode(
"🌟 *طلب اشتراك جديد - فايز تڨي* 🌟

━━━━━━━━━━━━━━━━━━━━━━━━
👤 *بيانات العميل*
━━━━━━━━━━━━━━━━━━━━━━━━
🆔 *معرف المستخدم:* " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'غير مسجل') . "
👤 *اسم المستخدم:* " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'زائر') . "
📧 *البريد الإلكتروني:* " . (isset($user_email) ? $user_email : 'غير متوفر') . "
📅 *تاريخ التسجيل:* " . (isset($user_created) ? date('Y-m-d', strtotime($user_created)) : date('Y-m-d')) . "
⏰ *وقت الطلب:* " . date('Y-m-d H:i:s') . "
🌐 *عنوان IP:* " . ($_SERVER['REMOTE_ADDR'] ?? 'غير معروف') . "

━━━━━━━━━━━━━━━━━━━━━━━━
💎 *تفاصيل الاشتراك المطلوب*
━━━━━━━━━━━━━━━━━━━━━━━━
📋 *الخطة:* ⭐ مميزة ⭐
💰 *السعر:* 29.99 ر.س / شهرياً
📺 *الجودة:* 1080p HD
🚫 *إعلانات:* بدون إعلانات
📥 *التحميل:* متاح
📱 *الأجهزة:* جهازين

━━━━━━━━━━━━━━━━━━━━━━━━
📊 *حساب العميل*
━━━━━━━━━━━━━━━━━━━━━━━━
🔹 *حالة العضوية الحالية:* " . (isset($user_membership) ? $user_membership : 'غير مشترك') . "

━━━━━━━━━━━━━━━━━━━━━━━━
✅ *يرجى الرد لتأكيد الاشتراك*
💬 *شكراً لاختيارك فايز تڨي*
━━━━━━━━━━━━━━━━━━━━━━━━"
); ?>" 
                   target="_blank" 
                   style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; background: #25D366; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; transition: all 0.3s;">
                    <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
                    <span style="display: flex; flex-direction: column; line-height: 1.3;">
                        <span style="font-size: 16px;">اشترك عبر واتساب</span>
                        <span style="font-size: 11px; opacity: 0.9;">مع إرسال جميع بياناتك</span>
                    </span>
                </a>
            </div>
            
            <!-- ===== خطة VIP (مع واتساب مفصل) ===== -->
            <div class="plan-card" style="flex: 1; min-width: 280px; max-width: 350px; background: #1a1a1a; border-radius: 15px; padding: 30px; border: 2px solid gold;">
                <h3 style="color: gold; font-size: 24px; text-align: center;">VIP</h3>
                <div style="font-size: 36px; font-weight: 800; text-align: center; margin: 20px 0; color: #fff;">
                    49.99 <span style="font-size: 16px;">ر.س</span> <span style="font-size: 14px;">/شهرياً</span>
                </div>
                
                <ul style="list-style: none; padding: 0; margin: 20px 0;">
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> جميع مميزات المميز
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> جودة 4K UHD
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> محتوى حصري VIP
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> وصول مبكر للحلقات
                    </li>
                    <li style="margin: 10px 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-check-circle" style="color: #27ae60;"></i> 4 أجهزة
                    </li>
                </ul>
                
                <!-- زر الواتساب لخطة VIP مع جميع بيانات العميل -->
                <a href="https://wa.me/967776255680?text=<?php echo urlencode(
"👑 *طلب اشتراك VIP - فايز تڨي* 👑

━━━━━━━━━━━━━━━━━━━━━━━━
👤 *بيانات العميل*
━━━━━━━━━━━━━━━━━━━━━━━━
🆔 *معرف المستخدم:* " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'غير مسجل') . "
👤 *اسم المستخدم:* " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'زائر') . "
📧 *البريد الإلكتروني:* " . (isset($user_email) ? $user_email : 'غير متوفر') . "
📅 *تاريخ التسجيل:* " . (isset($user_created) ? date('Y-m-d', strtotime($user_created)) : date('Y-m-d')) . "
⏰ *وقت الطلب:* " . date('Y-m-d H:i:s') . "
🌐 *عنوان IP:* " . ($_SERVER['REMOTE_ADDR'] ?? 'غير معروف') . "

━━━━━━━━━━━━━━━━━━━━━━━━
💎 *تفاصيل الاشتراك المطلوب*
━━━━━━━━━━━━━━━━━━━━━━━━
📋 *الخطة:* 👑 VIP 👑
💰 *السعر:* 49.99 ر.س / شهرياً
📺 *الجودة:* 4K UHD
🚫 *إعلانات:* بدون إعلانات
📥 *التحميل:* متاح
📱 *الأجهزة:* 4 أجهزة
🎁 *محتوى حصري:* VIP فقط
⚡ *وصول مبكر:* متاح

━━━━━━━━━━━━━━━━━━━━━━━━
📊 *حساب العميل*
━━━━━━━━━━━━━━━━━━━━━━━━
🔹 *حالة العضوية الحالية:* " . (isset($user_membership) ? $user_membership : 'غير مشترك') . "

━━━━━━━━━━━━━━━━━━━━━━━━
✅ *يرجى الرد لتأكيد الاشتراك VIP*
💬 *شكراً لاختيارك عضوية VIP*
━━━━━━━━━━━━━━━━━━━━━━━━"
); ?>" 
                   target="_blank" 
                   style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 15px; background: #25D366; color: white; text-decoration: none; border-radius: 50px; font-weight: bold; margin-top: 20px; transition: all 0.3s;">
                    <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
                    <span style="display: flex; flex-direction: column; line-height: 1.3;">
                        <span style="font-size: 16px;">اشترك VIP عبر واتساب</span>
                        <span style="font-size: 11px; opacity: 0.9;">مع جميع بياناتك</span>
                    </span>
                </a>
            </div>
        </div>
        
        <!-- شريط التواصل السريع مع الدعم -->
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: #1a1a1a; border-radius: 15px; border: 1px solid #25D366;">
            <div style="display: flex; align-items: center; justify-content: center; gap: 15px; flex-wrap: wrap;">
                <i class="fab fa-whatsapp" style="font-size: 40px; color: #25D366;"></i>
                <div>
                    <p style="color: #25D366; font-size: 18px; font-weight: bold;">للتواصل المباشر مع الدعم الفني</p>
                    <p style="color: #b3b3b3; font-size: 14px;">نحن في خدمتك 24/7 للرد على استفساراتك</p>
                </div>
                <a href="https://wa.me/967776255680" target="_blank" style="background: #25D366; color: white; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; display: inline-flex; align-items: center; gap: 10px;">
                    <i class="fab fa-whatsapp"></i> 776255680
                </a>
            </div>
        </div>
    </div>
</section>
    <!-- الفوتر -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>فايز تڨي</h3>
                <p>منصتك الأولى لمشاهدة الأفلام والمسلسلات العربية والأجنبية والتركية والهندية والآسيوية، بالإضافة إلى مسلسلات الأنمي والبث المباشر.</p>
            </div>
            <div class="footer-links">
                <h4>أفلام</h4>
                <ul>
                    <li><a href="arabic-movies.php"><i class="fas fa-chevron-left"></i> أفلام عربية</a></li>
                    <li><a href="foreign-movies.php"><i class="fas fa-chevron-left"></i> أفلام أجنبية</a></li>
                    <li><a href="turkish-movies.php"><i class="fas fa-chevron-left"></i> أفلام تركية</a></li>
                    <li><a href="indian-movies.php"><i class="fas fa-chevron-left"></i> أفلام هندية</a></li>
                    <li><a href="asian-movies.php"><i class="fas fa-chevron-left"></i> أفلام آسيوية</a></li>
                    <li><a href="egyptian-movies.php"><i class="fas fa-chevron-left"></i> أفلام مصرية</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>مسلسلات</h4>
                <ul>
                    <li><a href="arabic-series.php"><i class="fas fa-chevron-left"></i> مسلسلات عربية</a></li>
                    <li><a href="foreign-series.php"><i class="fas fa-chevron-left"></i> مسلسلات أجنبية</a></li>
                    <li><a href="turkish-series.php"><i class="fas fa-chevron-left"></i> مسلسلات تركية</a></li>
                    <li><a href="indian-series.php"><i class="fas fa-chevron-left"></i> مسلسلات هندية</a></li>
                    <li><a href="asian-series.php"><i class="fas fa-chevron-left"></i> مسلسلات آسيوية</a></li>
                    <li><a href="egyptian-series.php"><i class="fas fa-chevron-left"></i> مسلسلات مصرية</a></li>
                    <li><a href="anime-series.php"><i class="fas fa-chevron-left"></i> أنمي</a></li>
                    <li><a href="top-imdb-series.php"><i class="fas fa-chevron-left" style="color: gold;"></i> أفضل IMDb</a></li>
                </ul>
            </div>
            <div class="footer-links">
                <h4>روابط سريعة</h4>
                <ul>
                    <li><a href="index.php"><i class="fas fa-chevron-left"></i> الرئيسية</a></li>
                    <li><a href="admin/login.php"><i class="fas fa-chevron-left"></i> تسجيل الدخول</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© 2024 فايز تڨي - جميع الحقوق محفوظة. تصميم وبرمجة: فايز تڨي</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        8// ===== اقتراحات البحث المباشر =====
const searchInput = document.getElementById('searchInput');
const searchSuggestions = document.getElementById('searchSuggestions');
let searchTimeout;

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value.trim();
    
    if (query.length < 2) {
        searchSuggestions.classList.remove('active');
        return;
    }
    
    searchTimeout = setTimeout(() => {
        fetch(`search.php?ajax=1&q=${encodeURIComponent(query)}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    let html = '';
                    data.forEach(item => {
                        html += `
                            <a href="${item.type === 'movie' ? 'movie.php?id=' + item.id : 'series.php?id=' + item.id}" class="suggestion-item">
                                <img src="${item.poster || 'https://via.placeholder.com/40x60'}" class="suggestion-poster">
                                <div class="suggestion-info">
                                    <div class="suggestion-title">${item.title}</div>
                                    <div class="suggestion-meta">
                                        <span>${item.year}</span>
                                        <span class="suggestion-type">${item.type === 'movie' ? 'فيلم' : 'مسلسل'}</span>
                                    </div>
                                </div>
                            </a>
                        `;
                    });
                    searchSuggestions.innerHTML = html;
                    searchSuggestions.classList.add('active');
                } else {
                    searchSuggestions.classList.remove('active');
                }
            });
    }, 300);
});

// إخفاء الاقتراحات عند النقر خارجها
document.addEventListener('click', function(e) {
    if (!searchInput.contains(e.target) && !searchSuggestions.contains(e.target)) {
        searchSuggestions.classList.remove('active');
    }
});
        // تأثير الهيدر عند التمرير
        window.addEventListener('scroll', function() {
            const header = document.getElementById('header');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // هيرو سلايدر
        const heroSwiper = new Swiper('.heroSwiper', {
            loop: true,
            speed: 1000,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
        });

        // إعدادات السلايدرات الموحدة
        const swiperConfig = {
            slidesPerView: 2,
            spaceBetween: 15,
            loop: true,
            speed: 800,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            breakpoints: {
                640: { slidesPerView: 3, spaceBetween: 20 },
                768: { slidesPerView: 4, spaceBetween: 20 },
                1024: { slidesPerView: 5, spaceBetween: 25 },
                1280: { slidesPerView: 6, spaceBetween: 25 },
            },
        };

        // تفعيل جميع السلايدرات
        new Swiper('.freeSwiper', swiperConfig);
        new Swiper('.trendingSwiper', swiperConfig);
        new Swiper('.newSwiper', swiperConfig);
        new Swiper('.yemenSwiper', swiperConfig);
        new Swiper('.arabicMoviesSwiper', swiperConfig);
        new Swiper('.foreignMoviesSwiper', swiperConfig);
        new Swiper('.turkishMoviesSwiper', swiperConfig);
        new Swiper('.indianMoviesSwiper', swiperConfig);
        
        <?php if (!empty($asian_movies)): ?>
        new Swiper('.asianMoviesSwiper', swiperConfig);
        <?php endif; ?>
        
        new Swiper('.arabicSeriesSwiper', swiperConfig);
        new Swiper('.foreignSeriesSwiper', swiperConfig);
        new Swiper('.turkishSeriesSwiper', swiperConfig);
        new Swiper('.indianSeriesSwiper', swiperConfig);
        
        <?php if (!empty($asian_series)): ?>
        new Swiper('.asianSeriesSwiper', swiperConfig);
        <?php endif; ?>
        
        <?php if (!empty($anime_series)): ?>
        new Swiper('.animeSwiper', swiperConfig);
        <?php endif; ?>

        <?php if (!empty($egyptian_movies)): ?>
        new Swiper('.egyptianMoviesSwiper', swiperConfig);
        <?php endif; ?>

        <?php if (!empty($egyptian_series)): ?>
        new Swiper('.egyptianSeriesSwiper', swiperConfig);
        <?php endif; ?>

        // سلايدر أفضل مسلسلات IMDb
        new Swiper('.imdbTopSwiper', swiperConfig);
        // سلايدر أفضل المسلسلات عالية التقييم

new Swiper('.ramadanSwiper', {
    slidesPerView: 2,
    spaceBetween: 15,
    loop: true,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false,
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    breakpoints: {
        640: { slidesPerView: 3, spaceBetween: 20 },
        768: { slidesPerView: 4, spaceBetween: 20 },
        1024: { slidesPerView: 5, spaceBetween: 25 },
    },
});
new Swiper('.imdbTopSwiper', {
    slidesPerView: 2,
    spaceBetween: 15,
    loop: true,
    speed: 800,
    autoplay: {
        delay: 3000,
        disableOnInteraction: false,
    },
    pagination: {
        el: '.swiper-pagination',
        clickable: true,
    },
    breakpoints: {
        640: { slidesPerView: 3, spaceBetween: 20 },
        768: { slidesPerView: 4, spaceBetween: 20 },
        1024: { slidesPerView: 5, spaceBetween: 25 },
        1280: { slidesPerView: 6, spaceBetween: 25 },
    },
});
    </script>
</body>
</html>