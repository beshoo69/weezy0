<?php
// admin/edit-series.php - صفحة تعديل المسلسل مع إدارة متقدمة للمواسم والحلقات
if (php_sapi_name() === 'cli') {
    echo "⚠️ هذا الملف يجب تشغيله من المتصفح!\n";
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

define('TMDB_API_KEY', '5dc3e335b09cbf701d8685dd9a766949');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات المسلسل
$stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
$stmt->execute([$id]);
$series = $stmt->fetch();

if (!$series) {
    header('Location: series-import.php');
    exit;
}

// قائمة اللغات المدعومة للترجمة
$subtitle_languages = [
    'ar' => '🇸🇦 العربية',
    'en' => '🇬🇧 English',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch',
    'es' => '🇪🇸 Español',
    'tr' => '🇹🇷 Türkçe',
    'hi' => '🇮🇳 हिन्दी',
    'ur' => '🇵🇰 اردو',
    'ku' => '🏳️ كوردي',
    'fa' => '🇮🇷 فارسی',
    'ko' => '🇰🇷 한국어',
    'ja' => '🇯🇵 日本語',
    'zh' => '🇨🇳 中文',
    'th' => '🇹🇭 ไทย'
];

// قائمة خطط العضوية
$membership_levels = [
    'basic' => ['name' => 'عادي', 'badge' => 'مجاني', 'color' => '#6c757d', 'icon' => '🆓', 'desc' => 'متاح للجميع مجاناً'],
    'premium' => ['name' => 'مميز', 'badge' => '⭐ مميز', 'color' => '#e50914', 'icon' => '⭐', 'desc' => 'جودة عالية بدون إعلانات'],
    'vip' => ['name' => 'VIP', 'badge' => '👑 VIP', 'color' => 'gold', 'icon' => '👑', 'desc' => 'محتوى حصري وجودة 4K']
];

// جلب المواسم
$seasons = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
$seasons->execute([$id]);
$seasons_data = $seasons->fetchAll();

// جلب الحلقات لكل موسم مع فك تشفير JSON للروابط
$episodes_data = [];
foreach ($seasons_data as $season) {
    $stmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? AND season_number = ? ORDER BY episode_number");
    $stmt->execute([$id, $season['season_number']]);
    $episodes = $stmt->fetchAll();
    
    // فك تشفير JSON للروابط
    foreach ($episodes as &$episode) {
        if (!empty($episode['watch_servers']) && $episode['watch_servers'] != 'null') {
            $episode['watch_servers_array'] = json_decode($episode['watch_servers'], true) ?: [];
        } else {
            $episode['watch_servers_array'] = [];
        }
        
        if (!empty($episode['download_servers']) && $episode['download_servers'] != 'null') {
            $episode['download_servers_array'] = json_decode($episode['download_servers'], true) ?: [];
        } else {
            $episode['download_servers_array'] = [];
        }
    }
    
    $episodes_data[$season['season_number']] = $episodes;
}

// جلب طاقم العمل
$cast = $pdo->prepare("SELECT * FROM cast WHERE series_id = ? ORDER BY order_number");
$cast->execute([$id]);
$cast_data = $cast->fetchAll();

// جلب فريق العمل
$crew = $pdo->prepare("SELECT * FROM crew WHERE series_id = ?");
$crew->execute([$id]);
$crew_data = $crew->fetchAll();

// جلب روابط المشاهدة العامة
$watch_links = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'series' AND item_id = ?");
$watch_links->execute([$id]);
$watch_links_data = $watch_links->fetchAll();

// جلب الترجمات
$subtitles = $pdo->prepare("SELECT * FROM subtitles WHERE content_type = 'series' AND content_id = ?");
$subtitles->execute([$id]);
$subtitles_data = $subtitles->fetchAll();

// =============================================
// معالجة تحديث المسلسل
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_series'])) {
    
    $title = $_POST['title'] ?? '';
    $title_en = $_POST['title_en'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    $country = $_POST['country'] ?? '';
    $language = $_POST['language'] ?? 'ar';
    $genre = $_POST['genre'] ?? '';
    $imdb_rating = (float)($_POST['imdb_rating'] ?? 0);
    $membership_level = $_POST['membership_level'] ?? 'basic';
    $status = $_POST['status'] ?? 'returning';
    
    try {
        $pdo->beginTransaction();
        
        // تحديث بيانات المسلسل الأساسية
        $sql = "UPDATE series SET 
                title = ?, title_en = ?, description = ?, year = ?, country = ?, 
                language = ?, genre = ?, imdb_rating = ?, membership_level = ?, status = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $title_en, $overview, $year, $country, $language, 
                        $genre, $imdb_rating, $membership_level, $status, $id]);
        
        // معالجة رفع صورة جديدة إذا وجدت
        if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_poster.' . $ext;
            
            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $upload_dir . $filename)) {
                $poster_path = 'uploads/posters/' . $filename;
                $pdo->prepare("UPDATE series SET poster = ? WHERE id = ?")->execute([$poster_path, $id]);
            }
        }
        
        // معالجة رفع صورة الخلفية إذا وجدت
        if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/backdrops/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_backdrop.' . $ext;
            
            if (move_uploaded_file($_FILES['backdrop_file']['tmp_name'], $upload_dir . $filename)) {
                $backdrop_path = 'uploads/backdrops/' . $filename;
                $pdo->prepare("UPDATE series SET backdrop = ? WHERE id = ?")->execute([$backdrop_path, $id]);
            }
        }
        
        // =============================================
        // تحديث المواسم والحلقات (حذف وإعادة إضافة)
        // =============================================
        if (isset($_POST['seasons']) && is_array($_POST['seasons'])) {
            
            // حذف المواسم والحلقات القديمة
            $pdo->prepare("DELETE FROM episodes WHERE series_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM seasons WHERE series_id = ?")->execute([$id]);
            
            foreach ($_POST['seasons'] as $season_data) {
                if (!empty($season_data['number'])) {
                    
                    // حساب عدد الحلقات لهذا الموسم
                    $episode_count = 0;
                    if (isset($season_data['episodes']) && is_array($season_data['episodes'])) {
                        $episode_count = count($season_data['episodes']);
                    }
                    
                    // إضافة الموسم
                    $season_sql = "INSERT INTO seasons (
                        series_id, season_number, name, overview, poster, air_date, episode_count
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    
                    $season_stmt = $pdo->prepare($season_sql);
                    $season_stmt->execute([
                        $id,
                        $season_data['number'],
                        $season_data['name'] ?? "الموسم {$season_data['number']}",
                        $season_data['overview'] ?? '',
                        $season_data['poster'] ?? '',
                        $season_data['air_date'] ?? null,
                        $episode_count
                    ]);
                    
                    // إضافة حلقات الموسم
                    if (isset($season_data['episodes']) && is_array($season_data['episodes'])) {
                        foreach ($season_data['episodes'] as $episode_data) {
                            if (!empty($episode_data['number'])) {
                                
                                // جمع روابط المشاهدة لهذه الحلقة
                                $watch_servers = [];
                                if (isset($episode_data['watch_links']) && is_array($episode_data['watch_links'])) {
                                    foreach ($episode_data['watch_links'] as $link) {
                                        if (!empty($link['url'])) {
                                            $watch_servers[] = [
                                                'name' => $link['name'] ?? $link['custom_name'] ?? 'سيرفر مشاهدة',
                                                'url' => $link['url'],
                                                'lang' => $link['lang'] ?? 'arabic',
                                                'quality' => $link['quality'] ?? 'HD'
                                            ];
                                        }
                                    }
                                }
                                
                                // جمع روابط التحميل
                                $download_servers = [];
                                if (isset($episode_data['download_links']) && is_array($episode_data['download_links'])) {
                                    foreach ($episode_data['download_links'] as $link) {
                                        if (!empty($link['url'])) {
                                            $download_servers[] = [
                                                'name' => $link['name'] ?? $link['custom_name'] ?? 'سيرفر تحميل',
                                                'url' => $link['url'],
                                                'quality' => $link['quality'] ?? 'HD',
                                                'size' => $link['size'] ?? ''
                                            ];
                                        }
                                    }
                                }
                                
                                $watch_json = !empty($watch_servers) ? json_encode($watch_servers, JSON_UNESCAPED_UNICODE) : null;
                                $download_json = !empty($download_servers) ? json_encode($download_servers, JSON_UNESCAPED_UNICODE) : null;
                                
                                $episode_sql = "INSERT INTO episodes (
                                    series_id, season_number, episode_number, title, title_en,
                                    description, still_path, air_date, runtime, vote_average,
                                    watch_servers, download_servers
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                
                                $episode_stmt = $pdo->prepare($episode_sql);
                                $episode_stmt->execute([
                                    $id,
                                    $season_data['number'],
                                    $episode_data['number'],
                                    $episode_data['title'] ?? "الحلقة {$episode_data['number']}",
                                    $episode_data['title_en'] ?? '',
                                    $episode_data['overview'] ?? '',
                                    $episode_data['still_path'] ?? '',
                                    $episode_data['air_date'] ?? null,
                                    $episode_data['runtime'] ?? 45,
                                    $episode_data['vote_average'] ?? 0,
                                    $watch_json,
                                    $download_json
                                ]);
                            }
                        }
                    }
                }
            }
        }
        
        // تحديث طاقم العمل
        if (isset($_POST['cast']) && is_array($_POST['cast'])) {
            $pdo->prepare("DELETE FROM cast WHERE series_id = ?")->execute([$id]);
            
            foreach ($_POST['cast'] as $cast_member) {
                if (!empty($cast_member['name'])) {
                    $cast_sql = "INSERT INTO cast (series_id, person_id, name, character_name, profile_path, order_number, department, popularity) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $cast_stmt = $pdo->prepare($cast_sql);
                    $cast_stmt->execute([
                        $id,
                        $cast_member['person_id'] ?? null,
                        $cast_member['name'],
                        $cast_member['character'] ?? '',
                        $cast_member['profile_path'] ?? '',
                        $cast_member['order'] ?? 999,
                        $cast_member['department'] ?? 'Acting',
                        $cast_member['popularity'] ?? 0
                    ]);
                }
            }
        }
        
        // تحديث فريق العمل
        if (isset($_POST['crew']) && is_array($_POST['crew'])) {
            $pdo->prepare("DELETE FROM crew WHERE series_id = ?")->execute([$id]);
            
            foreach ($_POST['crew'] as $crew_member) {
                if (!empty($crew_member['name'])) {
                    $crew_sql = "INSERT INTO crew (series_id, person_id, name, job, department, profile_path) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $crew_stmt = $pdo->prepare($crew_sql);
                    $crew_stmt->execute([
                        $id,
                        $crew_member['person_id'] ?? null,
                        $crew_member['name'],
                        $crew_member['job'] ?? '',
                        $crew_member['department'] ?? '',
                        $crew_member['profile_path'] ?? ''
                    ]);
                }
            }
        }
        
        // تحديث روابط المشاهدة العامة
        if (isset($_POST['watch_links']) && is_array($_POST['watch_links'])) {
            $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'series' AND item_id = ?")->execute([$id]);
            
            foreach ($_POST['watch_links'] as $link) {
                if (!empty($link['url'])) {
                    $link_sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $link_stmt = $pdo->prepare($link_sql);
                    $link_stmt->execute([
                        'series', $id,
                        $link['name'] ?? 'سيرفر مشاهدة',
                        $link['url'],
                        $link['quality'] ?? 'HD',
                        $link['lang'] ?? 'arabic'
                    ]);
                }
            }
        }
        
        // تحديث الترجمات
        if (isset($_POST['subtitles']) && is_array($_POST['subtitles'])) {
            $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'series' AND content_id = ?")->execute([$id]);
            
            foreach ($_POST['subtitles'] as $subtitle) {
                if (!empty($subtitle['language_code'])) {
                    $sub_sql = "INSERT INTO subtitles (content_type, content_id, language, language_code, subtitle_url, subtitle_file, is_default) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $sub_stmt = $pdo->prepare($sub_sql);
                    $sub_stmt->execute([
                        'series', $id,
                        $subtitle['language'] ?? $subtitle_languages[$subtitle['language_code']] ?? $subtitle['language_code'],
                        $subtitle['language_code'],
                        $subtitle['url'] ?? '',
                        $subtitle['file'] ?? null,
                        isset($subtitle['is_default']) ? 1 : 0
                    ]);
                }
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success_message'] = "✅ تم تحديث المسلسل بنجاح";
        header("Location: edit-series.php?id=" . $id . "&updated=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "❌ خطأ: " . $e->getMessage();
    }
}

// دالة مساعدة لعرض رسائل النجاح
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// دوال مساعدة
function getSeasonEpisodesCount($season_number, $episodes_data) {
    return isset($episodes_data[$season_number]) ? count($episodes_data[$season_number]) : 0;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ تعديل المسلسل - <?php echo htmlspecialchars($series['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Tajawal', sans-serif; background: #0f0f0f; color: #fff; }
        
        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --success: #27ae60;
            --warning: #f39c12;
            --info: #3498db;
            --border: #333;
            --text-muted: #b3b3b3;
        }
        
        .header {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--primary);
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo h1 {
            font-size: 32px;
            font-weight: 800;
            background: linear-gradient(45deg, var(--primary), #ff6b6b);
            
            -webkit-text-fill-color: transparent;
        }
        
        .logo span {
            color: var(--text);
            -webkit-text-fill-color: var(--text);
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-link {
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            color: #fff;
            background: rgba(255,255,255,0.05);
            transition: 0.3s;
        }
        
        .nav-link:hover,
        .nav-link.active {
            background: var(--primary);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .page-title h2 {
            font-size: 28px;
            color: var(--primary);
        }
        
        .series-badge {
            background: #1a1a1a;
            padding: 5px 15px;
            border-radius: 30px;
            border: 1px solid var(--border);
            color: var(--text-muted);
        }
        
        .notification {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 50px;
            background: var(--success);
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: slideDown 0.5s ease;
        }
        
        .notification.error {
            background: var(--primary);
        }
        
        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--border);
            padding-bottom: 15px;
            overflow-x: auto;
        }
        
        .form-tab {
            padding: 12px 25px;
            background: transparent;
            border: 2px solid var(--border);
            border-radius: 40px;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }
        
        .form-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .form-tab.active {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }
        
        .tab-content.active {
            display: block !important;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .preview-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
        }
        
        .poster-preview {
            position: relative;
        }
        
        .poster-preview img {
            width: 100%;
            border-radius: 10px;
            border: 3px solid var(--primary);
        }
        
        .change-poster-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: 2px solid var(--primary);
            border-radius: 30px;
            padding: 5px 15px;
            font-size: 13px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .change-poster-btn:hover {
            background: var(--primary);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            background: #0f0f0f;
            border: 2px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
            transition: 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .status-selector {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .status-option {
            flex: 1;
            padding: 15px;
            background: #1a1a1a;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .status-option.selected {
            border-color: var(--primary);
            background: rgba(229,9,20,0.1);
        }
        
        .membership-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 20px 0;
        }
        
        .membership-card {
            background: #1a1a1a;
            border: 2px solid var(--border);
            border-radius: 15px;
            padding: 20px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .membership-card:hover {
            transform: translateY(-5px);
        }
        
        .membership-card.selected {
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }
        
        .membership-icon {
            font-size: 40px;
            margin-bottom: 10px;
        }
        
        .membership-name {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .membership-desc {
            color: var(--text-muted);
            font-size: 13px;
        }
        
        .membership-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            margin-top: 10px;
        }
        
        /* ===== إدارة المواسم والحلقات المتقدمة ===== */
        .seasons-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .seasons-stats {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .stat-badge {
            background: #1a1a1a;
            padding: 8px 20px;
            border-radius: 30px;
            border: 1px solid var(--border);
        }
        
        .stat-badge i {
            color: var(--primary);
            margin-left: 5px;
        }
        
        .stat-badge span {
            font-weight: 700;
            color: var(--primary);
        }
        
        .seasons-container {
            max-height: 600px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .season-card {
            background: #1a1a1a;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .season-card:hover {
            border-color: var(--primary);
        }
        
        .season-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            cursor: pointer;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .season-title-section {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .season-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .season-title i {
            font-size: 24px;
        }
        
        .season-number-badge {
            background: rgba(229,9,20,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            color: var(--primary);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .episodes-count-badge {
            background: rgba(52,152,219,0.1);
            padding: 5px 15px;
            border-radius: 20px;
            color: #3498db;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .season-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .season-action-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: #fff;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .season-action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .season-action-btn.delete:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .season-content {
            margin-top: 20px;
            display: none;
        }
        
        .season-content.active {
            display: block;
        }
        
        .season-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .episodes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .episodes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 15px;
        }
        
        .episode-card {
            background: #0f0f0f;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid var(--border);
            transition: 0.3s;
        }
        
        .episode-card:hover {
            border-color: var(--primary);
        }
        
        .episode-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .episode-number-badge {
            background: var(--primary);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .episode-actions {
            display: flex;
            gap: 5px;
        }
        
        .episode-action-btn {
            background: transparent;
            border: 1px solid var(--border);
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .episode-action-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .episode-action-btn.delete:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .episode-title-input {
            width: 100%;
            background: #1a1a1a;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 10px;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .episode-description-textarea {
            width: 100%;
            background: #1a1a1a;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 6px 10px;
            color: #fff;
            margin-bottom: 8px;
            resize: vertical;
            min-height: 60px;
        }
        
        .episode-meta-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-bottom: 10px;
        }
        
        .episode-links-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }
        
        .links-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .links-header h5 {
            color: var(--text-muted);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .watch-links-container,
        .download-links-container {
            margin-bottom: 10px;
        }
        
        .link-item {
            
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 8px;
            margin-bottom: 8px;
            padding: 8px;
            background: #1a1a1a;
            border-radius: 6px;
            border: 1px solid var(--border);
            align-items: center;
        }
        
        .link-item input,
        .link-item select {
            background: #0f0f0f;
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 4px 8px;
            color: #fff;
            font-size: 12px;
        }
        
        .remove-link-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .small-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .small-btn:hover {
            transform: scale(1.05);
        }
        
        .small-btn.danger {
            background: var(--primary);
        }
        
        .add-link-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .add-link-btn:hover {
            transform: scale(1.02);
        }
        
        /* ===== طاقم العمل ===== */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .cast-card {
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: 0.3s;
        }
        
        .cast-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }
        
        .cast-image {
            width: 100%;
            aspect-ratio: 1/1;
            object-fit: cover;
        }
        
        .cast-info {
            padding: 10px;
        }
        
        .cast-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .cast-character {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        .crew-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .crew-item {
            background: #1a1a1a;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .crew-name {
            font-weight: 600;
            color: var(--primary);
        }
        
        .crew-job {
            color: var(--text-muted);
            font-size: 12px;
        }
        
        /* ===== روابط ===== */
        .links-container {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .link-item-simple {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #0f0f0f;
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-primary {
            flex: 1;
            background: var(--primary);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(229,9,20,0.4);
        }
        
        .btn-secondary {
            flex: 1;
            background: transparent;
            border: 2px solid var(--border);
            color: #fff;
            padding: 15px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .modal-content {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--primary);
        }
        
        .modal-title {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 22px;
        }
        
        .file-input {
            width: 100%;
            padding: 10px;
            background: #0f0f0f;
            border: 1px solid var(--border);
            border-radius: 6px;
            color: #fff;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .preview-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .membership-cards { grid-template-columns: 1fr; }
            .season-header { flex-direction: column; align-items: flex-start; }
            .season-actions { width: 100%; justify-content: flex-start; }
            .episodes-grid { grid-template-columns: 1fr; }
            .link-item { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>تعديل <span>المسلسل</span></h1>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">الرئيسية</a>
            <a href="series-import.php" class="nav-link">استيراد مسلسلات</a>
            <a href="edit-series.php?id=<?php echo $id; ?>" class="nav-link active">تعديل</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success_message): ?>
        <div class="notification">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="notification error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="page-title">
            <h2><i class="fas fa-edit"></i> تعديل المسلسل</h2>
            <span class="series-badge">ID: <?php echo $id; ?> | <?php echo htmlspecialchars($series['title']); ?></span>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="seriesForm">
            <input type="hidden" name="update_series" value="1">
            
            <!-- تبويبات النموذج -->
            <div class="form-tabs">
                <div class="form-tab active" onclick="showTab('basic')">📋 معلومات أساسية</div>
                <div class="form-tab" onclick="showTab('membership')">👑 العضوية</div>
                <div class="form-tab" onclick="showTab('seasons')">📺 المواسم والحلقات (<?php echo count($seasons_data); ?>)</div>
                <div class="form-tab" onclick="showTab('cast')">🎭 طاقم العمل (<?php echo count($cast_data); ?>)</div>
                <div class="form-tab" onclick="showTab('crew')">🎬 فريق الإنتاج (<?php echo count($crew_data); ?>)</div>
                <div class="form-tab" onclick="showTab('watch')">🔗 روابط المشاهدة (<?php echo count($watch_links_data); ?>)</div>
                <div class="form-tab" onclick="showTab('subtitles')">📝 الترجمات (<?php echo count($subtitles_data); ?>)</div>
            </div>
            
            <!-- تبويب المعلومات الأساسية -->
            <div id="basicTab" class="tab-content active">
                <!-- معاينة -->
                <div class="preview-section">
                    <div class="preview-grid">
                        <div class="poster-preview">
                            <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/300x450?text=No+Poster'; ?>" id="preview-poster">
                            <button type="button" class="change-poster-btn" onclick="showPosterModal()">
                                <i class="fas fa-camera"></i> تغيير الصورة
                            </button>
                        </div>
                        <div>
                            <h3 style="color: var(--primary); margin-bottom: 10px;"><?php echo htmlspecialchars($series['title']); ?></h3>
                            <p style="color: var(--text-muted); line-height: 1.8;"><?php echo htmlspecialchars($series['description']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">العنوان بالعربية</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($series['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">العنوان الأصلي</label>
                        <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($series['title_en']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">سنة الإنتاج</label>
                        <input type="number" name="year" class="form-control" value="<?php echo $series['year']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">بلد الإنتاج</label>
                        <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($series['country']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اللغة</label>
                        <select name="language" class="form-control">
                            <option value="ar" <?php echo $series['language'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                            <option value="en" <?php echo $series['language'] == 'en' ? 'selected' : ''; ?>>الإنجليزية</option>
                            <option value="tr" <?php echo $series['language'] == 'tr' ? 'selected' : ''; ?>>التركية</option>
                            <option value="hi" <?php echo $series['language'] == 'hi' ? 'selected' : ''; ?>>الهندية</option>
                            <option value="ko" <?php echo $series['language'] == 'ko' ? 'selected' : ''; ?>>الكورية</option>
                            <option value="ja" <?php echo $series['language'] == 'ja' ? 'selected' : ''; ?>>اليابانية</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيفات</label>
                        <input type="text" name="genre" class="form-control" value="<?php echo htmlspecialchars($series['genre']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تقييم IMDB</label>
                        <input type="number" step="0.1" name="imdb_rating" class="form-control" value="<?php echo $series['imdb_rating']; ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">القصة</label>
                        <textarea name="overview" class="form-control"><?php echo htmlspecialchars($series['description']); ?></textarea>
                    </div>
                </div>
                
                <!-- حالة المسلسل -->
                <div style="margin-top: 20px;">
                    <label class="form-label">حالة المسلسل</label>
                    <div class="status-selector">
                        <div class="status-option <?php echo $series['status'] == 'returning' ? 'selected' : ''; ?>" onclick="selectStatus('returning')">
                            <i class="fas fa-sync-alt"></i>
                            <div>يعرض حالياً</div>
                            <input type="radio" name="status" value="returning" <?php echo $series['status'] == 'returning' ? 'checked' : ''; ?> style="display: none;">
                        </div>
                        <div class="status-option <?php echo $series['status'] == 'ended' ? 'selected' : ''; ?>" onclick="selectStatus('ended')">
                            <i class="fas fa-stop"></i>
                            <div>منتهي</div>
                            <input type="radio" name="status" value="ended" <?php echo $series['status'] == 'ended' ? 'checked' : ''; ?> style="display: none;">
                        </div>
                        <div class="status-option <?php echo $series['status'] == 'canceled' ? 'selected' : ''; ?>" onclick="selectStatus('canceled')">
                            <i class="fas fa-ban"></i>
                            <div>ملغي</div>
                            <input type="radio" name="status" value="canceled" <?php echo $series['status'] == 'canceled' ? 'checked' : ''; ?> style="display: none;">
                        </div>
                    </div>
                </div>
                
                <!-- رفع صورة خلفية جديدة -->
                <div style="margin-top: 20px;">
                    <label class="form-label">تغيير صورة الخلفية</label>
                    <input type="file" name="backdrop_file" class="form-control" accept="image/*">
                </div>
            </div>
            
            <!-- تبويب العضوية -->
            <div id="membershipTab" class="tab-content">
                <h3 style="margin-bottom: 20px; color: var(--primary);">مستوى العضوية المطلوب</h3>
                <div class="membership-cards">
                    <?php foreach ($membership_levels as $key => $level): ?>
                    <div class="membership-card <?php echo $series['membership_level'] == $key ? 'selected' : ''; ?>" onclick="selectMembership('<?php echo $key; ?>')">
                        <div class="membership-icon"><?php echo $level['icon']; ?></div>
                        <div class="membership-name" style="color: <?php echo $level['color']; ?>"><?php echo $level['name']; ?></div>
                        <div class="membership-desc"><?php echo $level['desc']; ?></div>
                        <div class="membership-badge" style="background: <?php echo $level['color']; ?>; color: <?php echo $key == 'vip' ? '#000' : '#fff'; ?>">
                            <?php echo $level['badge']; ?>
                        </div>
                        <input type="radio" name="membership_level" value="<?php echo $key; ?>" <?php echo $series['membership_level'] == $key ? 'checked' : ''; ?> style="display: none;">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- تبويب المواسم والحلقات المتقدم -->
            <div id="seasonsTab" class="tab-content">
                <div class="seasons-header">
                    <div>
                        <h3 style="color: var(--primary); font-size: 22px;">
                            <i class="fas fa-layer-group"></i> إدارة المواسم والحلقات
                        </h3>
                        <p style="color: var(--text-muted); margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> يمكنك إضافة وتعديل وحذف المواسم والحلقات وإدارة روابط المشاهدة والتحميل لكل حلقة
                        </p>
                    </div>
                    <div class="seasons-stats">
                        <div class="stat-badge">
                            <i class="fas fa-folder-open"></i>
                            <span><?php echo count($seasons_data); ?></span> مواسم
                        </div>
                        <div class="stat-badge">
                            <i class="fas fa-film"></i>
                            <span id="total-episodes-count">
                                <?php 
                                $total = 0;
                                foreach ($episodes_data as $eps) {
                                    $total += count($eps);
                                }
                                echo $total;
                                ?>
                            </span> حلقة
                        </div>
                    </div>
                </div>
                
                <div class="seasons-container" id="seasons-container">
                    <?php if (!empty($seasons_data)): ?>
                        <?php foreach ($seasons_data as $s_index => $season): 
                            $season_episodes = $episodes_data[$season['season_number']] ?? [];
                        ?>
                        <div class="season-card" id="season-<?php echo $s_index; ?>" data-season="<?php echo $season['season_number']; ?>">
                            <!-- رأس الموسم -->
                            <div class="season-header" onclick="toggleSeason(<?php echo $s_index; ?>)">
                                <div class="season-title-section">
                                    <div class="season-title">
                                        <i class="fas fa-folder-open"></i>
                                        <input type="text" name="seasons[<?php echo $s_index; ?>][name]" 
                                               value="<?php echo htmlspecialchars($season['name']); ?>" 
                                               placeholder="اسم الموسم"
                                               class="form-control"
                                               style="width: 200px;"
                                               onclick="event.stopPropagation()">
                                    </div>
                                    <span class="season-number-badge">
                                        <i class="fas fa-hashtag"></i> الموسم <?php echo $season['season_number']; ?>
                                    </span>
                                    <span class="episodes-count-badge">
                                        <i class="fas fa-film"></i> <?php echo count($season_episodes); ?> حلقة
                                    </span>
                                </div>
                                
                                <div class="season-actions" onclick="event.stopPropagation()">
                                    <button type="button" class="season-action-btn" onclick="editSeason(<?php echo $s_index; ?>)" title="تعديل الموسم">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="season-action-btn" onclick="addEpisodeToSeason(<?php echo $s_index; ?>)" title="إضافة حلقة">
                                        <i class="fas fa-plus-circle"></i>
                                    </button>
                                    <button type="button" class="season-action-btn delete" onclick="deleteSeason(<?php echo $s_index; ?>)" title="حذف الموسم">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <i class="fas fa-chevron-down" id="arrow-<?php echo $s_index; ?>" style="margin-right: 5px; font-size: 18px; color: var(--text-muted);"></i>
                                </div>
                            </div>
                            
                            <!-- محتوى الموسم -->
                            <div id="season-content-<?php echo $s_index; ?>" class="season-content">
                                <!-- بيانات الموسم المخفية -->
                                <input type="hidden" name="seasons[<?php echo $s_index; ?>][number]" value="<?php echo $season['season_number']; ?>">
                                <input type="hidden" name="seasons[<?php echo $s_index; ?>][overview]" value="<?php echo htmlspecialchars($season['overview']); ?>">
                                <input type="hidden" name="seasons[<?php echo $s_index; ?>][air_date]" value="<?php echo $season['air_date']; ?>">
                                <input type="hidden" name="seasons[<?php echo $s_index; ?>][poster]" value="<?php echo $season['poster']; ?>">
                                
                                <!-- معلومات الموسم الإضافية -->
                                <div class="season-info-grid">
                                    <div class="form-group">
                                        <label class="form-label">وصف الموسم</label>
                                        <textarea name="seasons[<?php echo $s_index; ?>][overview_display]" class="form-control" rows="3" placeholder="وصف الموسم"><?php echo htmlspecialchars($season['overview']); ?></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">تاريخ العرض</label>
                                        <input type="date" name="seasons[<?php echo $s_index; ?>][air_date_display]" class="form-control" value="<?php echo $season['air_date']; ?>">
                                    </div>
                                </div>
                                
                                <!-- روابط الموسم العامة -->
                                <div style="margin-bottom: 20px; background: #0f0f0f; border-radius: 10px; padding: 15px;">
                                    <div class="links-header">
                                        <h5><i class="fas fa-link" style="color: #27ae60;"></i> روابط الموسم العامة</h5>
                                        <div>
                                            <button type="button" class="small-btn" onclick="addSeasonWatchLink(<?php echo $s_index; ?>)">
                                                <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                                            </button>
                                            <button type="button" class="small-btn" style="background: #3498db;" onclick="addSeasonDownloadLink(<?php echo $s_index; ?>)">
                                                <i class="fas fa-plus"></i> إضافة رابط تحميل
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                        <div>
                                            <h6 style="color: #27ae60; margin-bottom: 8px;">🎬 روابط المشاهدة</h6>
                                            <div id="season-<?php echo $s_index; ?>-watch-links"></div>
                                        </div>
                                        <div>
                                            <h6 style="color: #3498db; margin-bottom: 8px;">⬇️ روابط التحميل</h6>
                                            <div id="season-<?php echo $s_index; ?>-download-links"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- قائمة الحلقات -->
                                <div class="episodes-header">
                                    <h4 style="color: var(--primary);">
                                        <i class="fas fa-list"></i> قائمة الحلقات (<?php echo count($season_episodes); ?>)
                                    </h4>
                                    <button type="button" class="add-link-btn" onclick="addEpisodeToSeason(<?php echo $s_index; ?>)" style="background: #3498db;">
                                        <i class="fas fa-plus-circle"></i> إضافة حلقة جديدة
                                    </button>
                                </div>
                                
                                <div class="episodes-grid" id="season-<?php echo $s_index; ?>-episodes">
                                    <?php foreach ($season_episodes as $e_index => $episode): ?>
                                    <div class="episode-card" id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>">
                                        <div class="episode-header">
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span class="episode-number-badge">ح<?php echo $episode['episode_number']; ?></span>
                                                <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][number]" 
                                                       value="<?php echo $episode['episode_number']; ?>" 
                                                       placeholder="رقم"
                                                       class="form-control"
                                                       style="width: 70px;">
                                            </div>
                                            <div class="episode-actions">
                                                <button type="button" class="episode-action-btn" onclick="toggleEpisodeLinks(<?php echo $s_index; ?>, <?php echo $e_index; ?>)" title="إدارة روابط الحلقة">
                                                    <i class="fas fa-link"></i>
                                                </button>
                                                <button type="button" class="episode-action-btn delete" onclick="deleteEpisode(<?php echo $s_index; ?>, <?php echo $e_index; ?>)" title="حذف الحلقة">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][title]" 
                                               value="<?php echo htmlspecialchars($episode['title']); ?>" 
                                               placeholder="عنوان الحلقة"
                                               class="episode-title-input">
                                        
                                        <textarea name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][overview]" 
                                                  placeholder="وصف الحلقة"
                                                  class="episode-description-textarea"><?php echo htmlspecialchars($episode['description']); ?></textarea>
                                        
                                        <div class="episode-meta-grid">
                                            <input type="date" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][air_date]" 
                                                   value="<?php echo $episode['air_date']; ?>"
                                                   class="form-control">
                                            <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][still_path]" 
                                                   value="<?php echo $episode['still_path']; ?>"
                                                   placeholder="رابط صورة"
                                                   class="form-control">
                                        </div>
                                        
                                        <!-- روابط الحلقة -->
                                        <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-links" class="episode-links-section" style="display: none;">
                                            <div class="links-header">
                                                <h5><i class="fas fa-play-circle" style="color: #27ae60;"></i> روابط المشاهدة</h5>
                                                <button type="button" class="small-btn" onclick="addEpisodeWatchLink(<?php echo $s_index; ?>, <?php echo $e_index; ?>)">
                                                    <i class="fas fa-plus"></i> إضافة
                                                </button>
                                            </div>
                                            <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-watch-links" class="watch-links-container">
                                                <?php if (!empty($episode['watch_servers_array'])): ?>
                                                    <?php foreach ($episode['watch_servers_array'] as $link_index => $link): ?>
                                                    <div class="link-item" id="episode-watch-<?php echo $s_index; ?>-<?php echo $e_index; ?>-<?php echo $link_index; ?>">
                                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][<?php echo $link_index; ?>][name]" 
                                                               value="<?php echo htmlspecialchars($link['name']); ?>" 
                                                               placeholder="اسم السيرفر">
                                                        <select name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][<?php echo $link_index; ?>][lang]">
                                                            <option value="arabic" <?php echo ($link['lang'] ?? '') == 'arabic' ? 'selected' : ''; ?>>🇸🇦 عربي</option>
                                                            <option value="english" <?php echo ($link['lang'] ?? '') == 'english' ? 'selected' : ''; ?>>🇬🇧 English</option>
                                                        </select>
                                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][<?php echo $link_index; ?>][quality]" 
                                                               value="<?php echo htmlspecialchars($link['quality']); ?>" 
                                                               placeholder="الجودة">
                                                        <input type="url" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][<?php echo $link_index; ?>][url]" 
                                                               value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                               placeholder="الرابط">
                                                        <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="links-header" style="margin-top: 15px;">
                                                <h5><i class="fas fa-download" style="color: #3498db;"></i> روابط التحميل</h5>
                                                <button type="button" class="small-btn" style="background: #3498db;" onclick="addEpisodeDownloadLink(<?php echo $s_index; ?>, <?php echo $e_index; ?>)">
                                                    <i class="fas fa-plus"></i> إضافة
                                                </button>
                                            </div>
                                            <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-download-links" class="download-links-container">
                                                <?php if (!empty($episode['download_servers_array'])): ?>
                                                    <?php foreach ($episode['download_servers_array'] as $link_index => $link): ?>
                                                    <div class="link-item" id="episode-download-<?php echo $s_index; ?>-<?php echo $e_index; ?>-<?php echo $link_index; ?>">
                                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][<?php echo $link_index; ?>][name]" 
                                                               value="<?php echo htmlspecialchars($link['name']); ?>" 
                                                               placeholder="اسم السيرفر">
                                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][<?php echo $link_index; ?>][quality]" 
                                                               value="<?php echo htmlspecialchars($link['quality']); ?>" 
                                                               placeholder="الجودة">
                                                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][<?php echo $link_index; ?>][size]" 
                                                               value="<?php echo htmlspecialchars($link['size'] ?? ''); ?>" 
                                                               placeholder="الحجم">
                                                        <input type="url" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][<?php echo $link_index; ?>][url]" 
                                                               value="<?php echo htmlspecialchars($link['url']); ?>" 
                                                               placeholder="الرابط">
                                                        <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; background: #1a1a1a; border-radius: 15px;">
                            <i class="fas fa-folder-open" style="font-size: 60px; color: #444; margin-bottom: 20px;"></i>
                            <h3 style="color: #b3b3b3;">لا توجد مواسم</h3>
                            <p style="color: #666; margin: 10px 0 20px;">قم بإضافة موسم جديد</p>
                            <button type="button" class="add-link-btn" onclick="addNewSeason()" style="background: #27ae60;">
                                <i class="fas fa-plus-circle"></i> إضافة موسم جديد
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <button type="button" class="add-link-btn" onclick="addNewSeason()" style="margin-top: 20px; background: #27ae60;">
                    <i class="fas fa-plus-circle"></i> إضافة موسم جديد
                </button>
            </div>
            
            <!-- تبويب طاقم العمل -->
            <div id="castTab" class="tab-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: var(--primary);">طاقم التمثيل</h3>
                    <button type="button" class="add-link-btn" onclick="addCastMember()">➕ إضافة ممثل</button>
                </div>
                
                <div class="cast-grid" id="cast-container">
                    <?php foreach ($cast_data as $index => $cast): ?>
                    <div class="cast-card" id="cast-<?php echo $index; ?>">
                        <img src="<?php echo $cast['profile_path'] ?? 'https://via.placeholder.com/200x200?text=No+Image'; ?>" class="cast-image">
                        <div class="cast-info">
                            <div class="cast-name"><?php echo htmlspecialchars($cast['name']); ?></div>
                            <div class="cast-character"><?php echo htmlspecialchars($cast['character_name']); ?></div>
                        </div>
                        <input type="hidden" name="cast[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($cast['name']); ?>">
                        <input type="hidden" name="cast[<?php echo $index; ?>][character]" value="<?php echo htmlspecialchars($cast['character_name']); ?>">
                        <input type="hidden" name="cast[<?php echo $index; ?>][order]" value="<?php echo $cast['order_number']; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- تبويب فريق الإنتاج -->
            <div id="crewTab" class="tab-content">
                <h3 style="color: var(--primary); margin-bottom: 20px;">فريق الإنتاج</h3>
                <div class="crew-grid" id="crew-container">
                    <?php foreach ($crew_data as $index => $crew): ?>
                    <div class="crew-item">
                        <div class="crew-name"><?php echo htmlspecialchars($crew['name']); ?></div>
                        <div class="crew-job"><?php echo htmlspecialchars($crew['job']); ?></div>
                        <input type="hidden" name="crew[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($crew['name']); ?>">
                        <input type="hidden" name="crew[<?php echo $index; ?>][job]" value="<?php echo htmlspecialchars($crew['job']); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- تبويب روابط المشاهدة العامة -->
            <div id="watchTab" class="tab-content">
                <div class="links-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--primary);">روابط المشاهدة العامة للمسلسل</h3>
                        <button type="button" class="add-link-btn" onclick="addWatchLink()">➕ إضافة رابط</button>
                    </div>
                    
                    <div id="watch-links-container">
                        <?php if (!empty($watch_links_data)): ?>
                            <?php foreach ($watch_links_data as $index => $link): ?>
                            <div class="link-item-simple" id="watch-link-<?php echo $index; ?>">
                                <select name="watch_links[<?php echo $index; ?>][lang]" class="form-control">
                                    <option value="arabic" <?php echo $link['language'] == 'arabic' ? 'selected' : ''; ?>>🇸🇦 عربي</option>
                                    <option value="english" <?php echo $link['language'] == 'english' ? 'selected' : ''; ?>>🇬🇧 English</option>
                                </select>
                                <input type="text" name="watch_links[<?php echo $index; ?>][name]" class="form-control" value="<?php echo htmlspecialchars($link['server_name']); ?>">
                                <input type="url" name="watch_links[<?php echo $index; ?>][url]" class="form-control" value="<?php echo htmlspecialchars($link['server_url']); ?>">
                                <input type="text" name="watch_links[<?php echo $index; ?>][quality]" class="form-control" value="<?php echo $link['quality']; ?>">
                                <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #666; text-align: center; padding: 20px;">لا توجد روابط مشاهدة عامة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- تبويب الترجمات -->
            <div id="subtitlesTab" class="tab-content">
                <div class="links-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--primary);">الترجمات</h3>
                        <button type="button" class="add-link-btn" onclick="addSubtitle()">➕ إضافة ترجمة</button>
                    </div>
                    
                    <div id="subtitles-container">
                        <?php if (!empty($subtitles_data)): ?>
                            <?php foreach ($subtitles_data as $index => $sub): ?>
                            <div class="link-item-simple" id="subtitle-<?php echo $index; ?>">
                                <select name="subtitles[<?php echo $index; ?>][language_code]" class="form-control">
                                    <?php foreach ($subtitle_languages as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $sub['language_code'] == $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="url" name="subtitles[<?php echo $index; ?>][url]" class="form-control" value="<?php echo $sub['subtitle_url']; ?>" placeholder="رابط الترجمة">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="subtitles[<?php echo $index; ?>][is_default]" value="1" <?php echo $sub['is_default'] ? 'checked' : ''; ?>> افتراضي
                                </label>
                                <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #666; text-align: center; padding: 20px;">لا توجد ترجمات</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- أزرار الإجراءات -->
            <div class="action-buttons">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
                <a href="series-import.php" class="btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </form>
    </div>
    
    <!-- مودال تغيير البوستر -->
    <div id="posterModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">تغيير صورة المسلسل</h3>
            <input type="file" id="posterFile" class="file-input" accept="image/*" onchange="previewNewPoster(this)">
            <div style="text-align: center; margin-bottom: 20px;">
                <img id="newPosterPreview" src="#" style="max-width: 200px; max-height: 300px; display: none; border-radius: 8px;">
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="button" class="btn-primary" onclick="uploadNewPoster()" style="flex: 1;">رفع</button>
                <button type="button" class="btn-secondary" onclick="closePosterModal()" style="flex: 1;">إلغاء</button>
            </div>
        </div>
    </div>

    <script>
        // ========== متغيرات عامة ==========
        let seasonCount = <?php echo count($seasons_data); ?>;
        let castCount = <?php echo count($cast_data); ?>;
        let watchLinkCount = <?php echo count($watch_links_data); ?>;
        let subtitleCount = <?php echo count($subtitles_data); ?>;
        
        // ========== دوال التبويبات ==========
        function showTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const tabMap = {
                'basic': 'basicTab',
                'membership': 'membershipTab',
                'seasons': 'seasonsTab',
                'cast': 'castTab',
                'crew': 'crewTab',
                'watch': 'watchTab',
                'subtitles': 'subtitlesTab'
            };
            
            document.getElementById(tabMap[tab]).classList.add('active');
            
            document.querySelectorAll('.form-tab').forEach((btn, i) => {
                const tabs = ['basic', 'membership', 'seasons', 'cast', 'crew', 'watch', 'subtitles'];
                if (tabs[i] === tab) btn.classList.add('active');
            });
        }
        
        // ========== دوال العضوية ==========
        function selectMembership(level) {
            document.querySelectorAll('.membership-card').forEach(c => c.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        function selectStatus(status) {
            document.querySelectorAll('.status-option').forEach(o => o.classList.remove('selected'));
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
        
        // ========== دوال المواسم ==========
        function toggleSeason(index) {
            const content = document.getElementById('season-content-' + index);
            const arrow = document.getElementById('arrow-' + index);
            
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                arrow.className = 'fas fa-chevron-up';
            } else {
                content.style.display = 'none';
                arrow.className = 'fas fa-chevron-down';
            }
        }
        
        function addNewSeason() {
            const container = document.getElementById('seasons-container');
            const newIndex = seasonCount;
            
            // إزالة رسالة "لا توجد مواسم" إذا وجدت
            const noSeasonsMsg = container.querySelector('div[style*="text-align: center"]');
            if (noSeasonsMsg) noSeasonsMsg.remove();
            
            const html = `
                <div class="season-card" id="season-${newIndex}" data-season="${newIndex + 1}">
                    <div class="season-header" onclick="toggleSeason(${newIndex})">
                        <div class="season-title-section">
                            <div class="season-title">
                                <i class="fas fa-folder-open"></i>
                                <input type="text" name="seasons[${newIndex}][name]" 
                                       value="موسم جديد ${newIndex + 1}" 
                                       placeholder="اسم الموسم"
                                       class="form-control"
                                       style="width: 200px;"
                                       onclick="event.stopPropagation()">
                            </div>
                            <span class="season-number-badge">
                                <i class="fas fa-hashtag"></i> الموسم ${newIndex + 1}
                            </span>
                            <span class="episodes-count-badge">
                                <i class="fas fa-film"></i> 0 حلقة
                            </span>
                        </div>
                        
                        <div class="season-actions" onclick="event.stopPropagation()">
                            <button type="button" class="season-action-btn" onclick="editSeason(${newIndex})" title="تعديل الموسم">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="season-action-btn" onclick="addEpisodeToSeason(${newIndex})" title="إضافة حلقة">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                            <button type="button" class="season-action-btn delete" onclick="deleteSeason(${newIndex})" title="حذف الموسم">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <i class="fas fa-chevron-down" id="arrow-${newIndex}" style="margin-right: 5px; font-size: 18px; color: var(--text-muted);"></i>
                        </div>
                    </div>
                    
                    <div id="season-content-${newIndex}" class="season-content" style="display: none;">
                        <input type="hidden" name="seasons[${newIndex}][number]" value="${newIndex + 1}">
                        <input type="hidden" name="seasons[${newIndex}][overview]" value="">
                        <input type="hidden" name="seasons[${newIndex}][air_date]" value="">
                        
                        <div class="season-info-grid">
                            <div class="form-group">
                                <label class="form-label">وصف الموسم</label>
                                <textarea name="seasons[${newIndex}][overview_display]" class="form-control" rows="3" placeholder="وصف الموسم"></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">تاريخ العرض</label>
                                <input type="date" name="seasons[${newIndex}][air_date_display]" class="form-control">
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 20px; background: #0f0f0f; border-radius: 10px; padding: 15px;">
                            <div class="links-header">
                                <h5><i class="fas fa-link" style="color: #27ae60;"></i> روابط الموسم العامة</h5>
                                <div>
                                    <button type="button" class="small-btn" onclick="addSeasonWatchLink(${newIndex})">
                                        <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                                    </button>
                                    <button type="button" class="small-btn" style="background: #3498db;" onclick="addSeasonDownloadLink(${newIndex})">
                                        <i class="fas fa-plus"></i> إضافة رابط تحميل
                                    </button>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <h6 style="color: #27ae60; margin-bottom: 8px;">🎬 روابط المشاهدة</h6>
                                    <div id="season-${newIndex}-watch-links"></div>
                                </div>
                                <div>
                                    <h6 style="color: #3498db; margin-bottom: 8px;">⬇️ روابط التحميل</h6>
                                    <div id="season-${newIndex}-download-links"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="episodes-header">
                            <h4 style="color: var(--primary);">
                                <i class="fas fa-list"></i> قائمة الحلقات (0)
                            </h4>
                            <button type="button" class="add-link-btn" onclick="addEpisodeToSeason(${newIndex})" style="background: #3498db;">
                                <i class="fas fa-plus-circle"></i> إضافة حلقة جديدة
                            </button>
                        </div>
                        
                        <div class="episodes-grid" id="season-${newIndex}-episodes"></div>
                    </div>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', html);
            seasonCount++;
            updateTotalEpisodesCount();
            showNotification('✅ تم إضافة موسم جديد');
        }
        
        function editSeason(index) {
            const seasonCard = document.getElementById(`season-${index}`);
            const nameInput = seasonCard.querySelector('input[name*="[name]"]');
            if (nameInput) {
                const newName = prompt('تعديل اسم الموسم:', nameInput.value);
                if (newName !== null && newName.trim() !== '') {
                    nameInput.value = newName;
                    showNotification('✅ تم تعديل اسم الموسم');
                }
            }
        }
        
        function deleteSeason(index) {
            if (confirm('⚠️ هل أنت متأكد من حذف هذا الموسم وجميع حلقاته؟')) {
                const seasonElement = document.getElementById(`season-${index}`);
                if (seasonElement) {
                    seasonElement.remove();
                    seasonCount--;
                    updateTotalEpisodesCount();
                    showNotification('🗑️ تم حذف الموسم');
                }
            }
        }
        
        // ========== دوال الحلقات ==========
        function addEpisodeToSeason(seasonIndex) {
            const episodesGrid = document.getElementById(`season-${seasonIndex}-episodes`);
            if (!episodesGrid) return;
            
            const episodeCount = episodesGrid.children.length;
            const newIndex = episodeCount;
            
            const html = `
                <div class="episode-card" id="episode-${seasonIndex}-${newIndex}">
                    <div class="episode-header">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span class="episode-number-badge">جديد</span>
                            <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][number]" 
                                   value="${newIndex + 1}" 
                                   placeholder="رقم"
                                   class="form-control"
                                   style="width: 70px;">
                        </div>
                        <div class="episode-actions">
                            <button type="button" class="episode-action-btn" onclick="toggleEpisodeLinks(${seasonIndex}, ${newIndex})" title="إدارة روابط الحلقة">
                                <i class="fas fa-link"></i>
                            </button>
                            <button type="button" class="episode-action-btn delete" onclick="deleteEpisode(${seasonIndex}, ${newIndex})" title="حذف الحلقة">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    
                    <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][title]" 
                           value="حلقة جديدة ${newIndex + 1}" 
                           placeholder="عنوان الحلقة"
                           class="episode-title-input">
                    
                    <textarea name="seasons[${seasonIndex}][episodes][${newIndex}][overview]" 
                              placeholder="وصف الحلقة"
                              class="episode-description-textarea"></textarea>
                    
                    <div class="episode-meta-grid">
                        <input type="date" name="seasons[${seasonIndex}][episodes][${newIndex}][air_date]" class="form-control">
                        <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][still_path]" 
                               placeholder="رابط صورة"
                               class="form-control">
                    </div>
                    
                    <div id="episode-${seasonIndex}-${newIndex}-links" class="episode-links-section" style="display: none;">
                        <div class="links-header">
                            <h5><i class="fas fa-play-circle" style="color: #27ae60;"></i> روابط المشاهدة</h5>
                            <button type="button" class="small-btn" onclick="addEpisodeWatchLink(${seasonIndex}, ${newIndex})">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                        <div id="episode-${seasonIndex}-${newIndex}-watch-links" class="watch-links-container"></div>
                        
                        <div class="links-header" style="margin-top: 15px;">
                            <h5><i class="fas fa-download" style="color: #3498db;"></i> روابط التحميل</h5>
                            <button type="button" class="small-btn" style="background: #3498db;" onclick="addEpisodeDownloadLink(${seasonIndex}, ${newIndex})">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                        </div>
                        <div id="episode-${seasonIndex}-${newIndex}-download-links" class="download-links-container"></div>
                    </div>
                </div>
            `;
            
            episodesGrid.insertAdjacentHTML('beforeend', html);
            updateSeasonEpisodeCount(seasonIndex);
            updateTotalEpisodesCount();
            showNotification('✅ تم إضافة حلقة جديدة');
        }
        
        function deleteEpisode(seasonIndex, episodeIndex) {
            if (confirm('هل أنت متأكد من حذف هذه الحلقة؟')) {
                const episodeElement = document.getElementById(`episode-${seasonIndex}-${episodeIndex}`);
                if (episodeElement) {
                    episodeElement.remove();
                    updateSeasonEpisodeCount(seasonIndex);
                    updateTotalEpisodesCount();
                    showNotification('🗑️ تم حذف الحلقة');
                }
            }
        }
        
        function toggleEpisodeLinks(seasonIndex, episodeIndex) {
            const linksDiv = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-links`);
            if (linksDiv) {
                linksDiv.style.display = linksDiv.style.display === 'none' ? 'block' : 'none';
            }
        }
        
        // ========== دوال روابط الحلقات ==========
        function addEpisodeWatchLink(seasonIndex, episodeIndex) {
            const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-watch-links`);
            if (!container) return;
            
            const linkId = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <div class="link-item" id="episode-watch-${seasonIndex}-${episodeIndex}-${linkId}">
                    <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][name]" 
                           placeholder="اسم السيرفر" value="سيرفر">
                    <select name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                    </select>
                    <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][quality]" 
                           placeholder="الجودة" value="HD">
                    <input type="url" name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][url]" 
                           placeholder="الرابط" required>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addEpisodeDownloadLink(seasonIndex, episodeIndex) {
            const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-download-links`);
            if (!container) return;
            
            const linkId = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <div class="link-item" id="episode-download-${seasonIndex}-${episodeIndex}-${linkId}">
                    <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][name]" 
                           placeholder="اسم السيرفر" value="سيرفر">
                    <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][quality]" 
                           placeholder="الجودة" value="HD">
                    <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][size]" 
                           placeholder="الحجم">
                    <input type="url" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][url]" 
                           placeholder="الرابط" required>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        // ========== دوال روابط المواسم ==========
        function addSeasonWatchLink(seasonIndex) {
            const container = document.getElementById(`season-${seasonIndex}-watch-links`);
            if (!container) return;
            
            const linkId = Date.now();
            const html = `
                <div class="link-item" id="season-watch-${seasonIndex}-${linkId}">
                    <input type="text" name="seasons[${seasonIndex}][watch_links][${linkId}][name]" 
                           placeholder="اسم السيرفر" value="سيرفر">
                    <select name="seasons[${seasonIndex}][watch_links][${linkId}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                    </select>
                    <input type="text" name="seasons[${seasonIndex}][watch_links][${linkId}][quality]" 
                           placeholder="الجودة" value="HD">
                    <input type="url" name="seasons[${seasonIndex}][watch_links][${linkId}][url]" 
                           placeholder="الرابط" required>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        function addSeasonDownloadLink(seasonIndex) {
            const container = document.getElementById(`season-${seasonIndex}-download-links`);
            if (!container) return;
            
            const linkId = Date.now();
            const html = `
                <div class="link-item" id="season-download-${seasonIndex}-${linkId}">
                    <input type="text" name="seasons[${seasonIndex}][download_links][${linkId}][name]" 
                           placeholder="اسم السيرفر" value="سيرفر">
                    <input type="text" name="seasons[${seasonIndex}][download_links][${linkId}][quality]" 
                           placeholder="الجودة" value="HD">
                    <input type="text" name="seasons[${seasonIndex}][download_links][${linkId}][size]" 
                           placeholder="الحجم">
                    <input type="url" name="seasons[${seasonIndex}][download_links][${linkId}][url]" 
                           placeholder="الرابط" required>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }
        
        // ========== دوال الروابط العامة ==========
        function addWatchLink() {
            const container = document.getElementById('watch-links-container');
            const html = `
                <div class="link-item-simple" id="watch-link-new-${watchLinkCount}">
                    <select name="watch_links[new_${watchLinkCount}][lang]" class="form-control">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                    </select>
                    <input type="text" name="watch_links[new_${watchLinkCount}][name]" class="form-control" placeholder="اسم السيرفر">
                    <input type="url" name="watch_links[new_${watchLinkCount}][url]" class="form-control" placeholder="الرابط">
                    <input type="text" name="watch_links[new_${watchLinkCount}][quality]" class="form-control" placeholder="الجودة" value="HD">
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            watchLinkCount++;
        }
        
        function addSubtitle() {
            const container = document.getElementById('subtitles-container');
            const html = `
                <div class="link-item-simple" id="subtitle-new-${subtitleCount}">
                    <select name="subtitles[new_${subtitleCount}][language_code]" class="form-control">
                        <?php foreach ($subtitle_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="subtitles[new_${subtitleCount}][url]" class="form-control" placeholder="رابط الترجمة">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="subtitles[new_${subtitleCount}][is_default]" value="1"> افتراضي
                    </label>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            subtitleCount++;
        }
        
        function addCastMember() {
            const container = document.getElementById('cast-container');
            const html = `
                <div class="cast-card" id="cast-new-${castCount}">
                    <img src="https://via.placeholder.com/200x200?text=New+Cast" class="cast-image">
                    <div class="cast-info">
                        <input type="text" name="cast[new_${castCount}][name]" class="form-control" placeholder="اسم الممثل" style="margin-bottom:5px;">
                        <input type="text" name="cast[new_${castCount}][character]" class="form-control" placeholder="اسم الشخصية">
                        <input type="hidden" name="cast[new_${castCount}][order]" value="999">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            castCount++;
        }
        
        // ========== دوال مساعدة ==========
        function updateSeasonEpisodeCount(seasonIndex) {
            const seasonCard = document.getElementById(`season-${seasonIndex}`);
            const episodesGrid = document.getElementById(`season-${seasonIndex}-episodes`);
            const count = episodesGrid ? episodesGrid.children.length : 0;
            
            const countBadge = seasonCard.querySelector('.episodes-count-badge');
            if (countBadge) {
                countBadge.innerHTML = `<i class="fas fa-film"></i> ${count} حلقة`;
            }
        }
        
        function updateTotalEpisodesCount() {
            const seasons = document.querySelectorAll('.season-card');
            let total = 0;
            
            seasons.forEach(season => {
                const episodesGrid = season.querySelector('.episodes-grid');
                if (episodesGrid) {
                    total += episodesGrid.children.length;
                }
            });
            
            const totalElement = document.getElementById('total-episodes-count');
            if (totalElement) {
                totalElement.textContent = total;
            }
        }
        
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            notification.style.background = type === 'success' ? '#27ae60' : '#e50914';
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideDown 0.5s reverse';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }
        
        // ========== دوال البوستر ==========
        function showPosterModal() {
            document.getElementById('posterModal').style.display = 'flex';
        }
        
        function closePosterModal() {
            document.getElementById('posterModal').style.display = 'none';
        }
        
        function previewNewPoster(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('newPosterPreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function uploadNewPoster() {
            const fileInput = document.getElementById('posterFile');
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-poster').src = e.target.result;
                    closePosterModal();
                    showNotification('تم تغيير الصورة مؤقتاً. سيتم الحفظ عند الضغط على "حفظ التغييرات"');
                }
                reader.readAsDataURL(fileInput.files[0]);
            }
        }
        
        // ========== إخفاء الإشعارات ==========
        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) notification.remove();
        }, 5000);
    </script>
</body>
</html>