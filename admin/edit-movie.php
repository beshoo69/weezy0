<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/tmdb.php';
require_once __DIR__ . '/../includes/membership-check.php';
// admin/edit-movie.php - صفحة تعديل الفيلم المتكاملة
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب بيانات الفيلم
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    header('Location: movies-import.php');
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

// جلب طاقم العمل
$cast = $pdo->prepare("SELECT * FROM movie_cast WHERE movie_id = ? ORDER BY order_number");
$cast->execute([$id]);
$cast_data = $cast->fetchAll();

// جلب فريق العمل
$crew = $pdo->prepare("SELECT * FROM movie_crew WHERE movie_id = ?");
$crew->execute([$id]);
$crew_data = $crew->fetchAll();

// جلب روابط المشاهدة
$watch_links = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ?");
$watch_links->execute([$id]);
$watch_links_data = $watch_links->fetchAll();

// جلب روابط التحميل
$download_links = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ?");
$download_links->execute([$id]);
$download_links_data = $download_links->fetchAll();

// جلب الترجمات
$subtitles = $pdo->prepare("SELECT * FROM subtitles WHERE content_type = 'movie' AND content_id = ?");
$subtitles->execute([$id]);
$subtitles_data = $subtitles->fetchAll();

// =============================================
// معالجة تحديث الفيلم
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_movie'])) {
    
    // سجل طلب التحديث
    error_log("========== بدء عملية تحديث الفيلم ID: $id ==========");
    error_log("POST data keys: " . implode(', ', array_keys($_POST)));
    
    // طباعة البيانات المرسلة للتحقق في حالة طلب debug
    if (isset($_GET['debug'])) {
        echo "<pre style='background: #000; color: #0f0; padding: 15px; direction: ltr; overflow-x: auto;'>";
        echo "======== POST DATA DEBUG ========\n";
        print_r($_POST);
        echo "\n======== FILES DATA ========\n";
        print_r($_FILES);
        echo "\n======== ID VALUE ========\n";
        var_dump($id);
        echo "</pre>";
    }
    
    $title = $_POST['title'] ?? '';
    $title_en = $_POST['title_en'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    $country = $_POST['country'] ?? '';
    $language = $_POST['language'] ?? 'ar';
    $genre = $_POST['genre'] ?? '';
    $duration = (int)($_POST['duration'] ?? 0);
    $imdb_rating = (float)($_POST['imdb_rating'] ?? 0);
    $membership_level = $_POST['membership_level'] ?? 'basic';
    $status = $_POST['status'] ?? 'published';
    
    // تحقق من وجود البيانات الأساسية
    if (empty($title) || empty($id)) {
        $error_message = "❌ خطأ: العنوان أو ID الفيلم ناقص. العنوان: '{$title}', ID: {$id}";
        error_log($error_message);
    } else {
        try {
            $pdo->beginTransaction();
            error_log("بدء عملية transaction");
            
            // تحديث بيانات الفيلم الأساسية
            $sql = "UPDATE movies SET 
                    title = ?, title_en = ?, description = ?, year = ?, country = ?, 
                    language = ?, genre = ?, duration = ?, imdb_rating = ?, 
                    membership_level = ?, status = ?, updated_at = NOW()
                    WHERE id = ?";
            
            $stmt = $pdo->prepare($sql);
            
            // سجل البيانات المراد تحديثها
            error_log("Data to update: title=$title, title_en=$title_en, year=$year, country=$country");
            
            $result = $stmt->execute([$title, $title_en, $overview, $year, $country, $language, 
                            $genre, $duration, $imdb_rating, $membership_level, $status, $id]);
            
            $affected_rows = $stmt->rowCount();
            error_log("SQL executed. Rows affected: $affected_rows, Result: " . ($result ? 'true' : 'false'));
        
        // معالجة رفع صورة جديدة إذا وجدت
        if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            error_log("معالجة صورة البوستر الجديدة");
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $ext = pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_poster.' . $ext;
            
            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $upload_dir . $filename)) {
                $poster_path = 'uploads/posters/' . $filename;
                $pdo->prepare("UPDATE movies SET poster = ? WHERE id = ?")->execute([$poster_path, $id]);
                error_log("تم تحميل البوستر بنجاح: $filename");
            } else {
                error_log("فشل تحميل البوستر");
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
                $pdo->prepare("UPDATE movies SET backdrop = ? WHERE id = ?")->execute([$backdrop_path, $id]);
            }
        }
        
        // تحديث طاقم العمل (حذف وإعادة إضافة)
        if (isset($_POST['cast']) && is_array($_POST['cast'])) {
            $pdo->prepare("DELETE FROM movie_cast WHERE movie_id = ?")->execute([$id]);
            
            foreach ($_POST['cast'] as $cast_member) {
                if (!empty($cast_member['name'])) {
                    $cast_sql = "INSERT INTO movie_cast (movie_id, person_id, name, character_name, profile_path, order_number, department, popularity) 
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
            $pdo->prepare("DELETE FROM movie_crew WHERE movie_id = ?")->execute([$id]);
            
            foreach ($_POST['crew'] as $crew_member) {
                if (!empty($crew_member['name'])) {
                    $crew_sql = "INSERT INTO movie_crew (movie_id, person_id, name, job, department, profile_path) 
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
        
        // تحديث روابط المشاهدة
        if (isset($_POST['watch_links']) && is_array($_POST['watch_links'])) {
            $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'movie' AND item_id = ?")->execute([$id]);
            
            foreach ($_POST['watch_links'] as $link) {
                if (!empty($link['url'])) {
                    $link_sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $link_stmt = $pdo->prepare($link_sql);
                    $link_stmt->execute([
                        'movie', $id,
                        $link['name'] ?? 'سيرفر مشاهدة',
                        $link['url'],
                        $link['quality'] ?? 'HD',
                        $link['lang'] ?? 'arabic'
                    ]);
                }
            }
        }
        
        // تحديث روابط التحميل
        if (isset($_POST['download_links']) && is_array($_POST['download_links'])) {
            $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'movie' AND item_id = ?")->execute([$id]);
            
            foreach ($_POST['download_links'] as $link) {
                if (!empty($link['url'])) {
                    $link_sql = "INSERT INTO download_servers (item_type, item_id, server_name, download_url, quality, size) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
                    $link_stmt = $pdo->prepare($link_sql);
                    $link_stmt->execute([
                        'movie', $id,
                        $link['name'] ?? 'سيرفر تحميل',
                        $link['url'],
                        $link['quality'] ?? 'HD',
                        $link['size'] ?? ''
                    ]);
                }
            }
        }
        
        // تحديث الترجمات
        if (isset($_POST['subtitles']) && is_array($_POST['subtitles'])) {
            $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'movie' AND content_id = ?")->execute([$id]);
            
            foreach ($_POST['subtitles'] as $subtitle) {
                if (!empty($subtitle['language_code'])) {
                    $sub_sql = "INSERT INTO subtitles (content_type, content_id, language, language_code, subtitle_url, subtitle_file, is_default) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $sub_stmt = $pdo->prepare($sub_sql);
                    $sub_stmt->execute([
                        'movie', $id,
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
        
        // تحقق من أن البيانات تم تحديثها
        $check_stmt = $pdo->prepare("SELECT title, title_en, description FROM movies WHERE id = ?");
        $check_stmt->execute([$id]);
        $updated_movie = $check_stmt->fetch();
        
        if ($updated_movie && $updated_movie['title'] === $title) {
            error_log("✅ تحديث ناجح - العنوان الجديد: " . $updated_movie['title']);
            $_SESSION['success_message'] = "✅ تم تحديث الفيلم بنجاح! العنوان: " . htmlspecialchars($updated_movie['title']);
        } else {
            error_log("⚠️ حدث خطأ في التحديث - البيانات لم تتغير كما هو متوقع");
            $_SESSION['success_message'] = "⚠️ تم حفظ التعديلات لكن قد تكون هناك مشكلة";
        }
        
        error_log("========== انتهت عملية التحديث بنجاح ==========\n");
        
        header("Location: edit-movie.php?id=" . $id . "&updated=1");
        exit;
        
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "❌ خطأ في التحديث: " . $e->getMessage();
            error_log("❌ حدث استثناء: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            error_log("========== فشلت عملية التحديث ==========\n");
        }
    }
}

// دالة مساعدة لعرض رسائل النجاح
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['success_message']);

// عرض رسائل الخطأ إن وجدت
$error_message = $error_message ?? null;
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ تعديل الفيلم - <?php echo htmlspecialchars($movie['title']); ?></title>
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
        
        .movie-badge {
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
        
        .links-container {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
        }
        
        .link-item {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto;
            gap: 10px;
            margin-bottom: 10px;
            padding: 10px;
            background: #0f0f0f;
            border-radius: 6px;
            border: 1px solid var(--border);
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
        }
        
        .add-link-btn:hover {
            transform: scale(1.05);
        }
        
        .remove-link-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
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
            max-width: 400px;
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
            .preview-grid {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .membership-cards {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>تعديل <span>الفيلم</span></h1>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">الرئيسية</a>
            <a href="movies-import.php" class="nav-link">استيراد أفلام</a>
            <a href="edit-movie.php?id=<?php echo $id; ?>" class="nav-link active">تعديل</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success_message): ?>
        <div class="notification">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="notification error" style="background: #f8d7da; border: 2px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="page-title">
            <h2><i class="fas fa-edit"></i> تعديل الفيلم</h2>
            <span class="movie-badge">ID: <?php echo $id; ?></span>
        </div>
        
        <form method="POST" enctype="multipart/form-data" id="movieForm">
            <input type="hidden" name="update_movie" value="1">
            
            <!-- تبويبات النموذج -->
            <div class="form-tabs">
                <div class="form-tab active" onclick="showTab('basic')">📋 معلومات أساسية</div>
                <div class="form-tab" onclick="showTab('membership')">👑 العضوية</div>
                <div class="form-tab" onclick="showTab('cast')">🎭 طاقم العمل (<?php echo count($cast_data); ?>)</div>
                <div class="form-tab" onclick="showTab('crew')">🎬 فريق الإنتاج (<?php echo count($crew_data); ?>)</div>
                <div class="form-tab" onclick="showTab('watch')">🔗 روابط المشاهدة (<?php echo count($watch_links_data); ?>)</div>
                <div class="form-tab" onclick="showTab('download')">⬇️ روابط التحميل (<?php echo count($download_links_data); ?>)</div>
                <div class="form-tab" onclick="showTab('subtitles')">📝 الترجمات (<?php echo count($subtitles_data); ?>)</div>
            </div>
            
            <!-- تبويب المعلومات الأساسية -->
            <div id="basicTab" class="tab-content active">
                <!-- معاينة -->
                <div class="preview-section">
                    <div class="preview-grid">
                        <div class="poster-preview">
                            <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/300x450?text=No+Poster'; ?>" id="preview-poster">
                            <button type="button" class="change-poster-btn" onclick="showPosterModal()">
                                <i class="fas fa-camera"></i> تغيير الصورة
                            </button>
                        </div>
                        <div>
                            <h3 style="color: var(--primary); margin-bottom: 10px;"><?php echo htmlspecialchars($movie['title']); ?></h3>
                            <p style="color: var(--text-muted); line-height: 1.8;"><?php echo htmlspecialchars($movie['description']); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">العنوان بالعربية</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($movie['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">العنوان الأصلي</label>
                        <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($movie['title_en']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">سنة الإنتاج</label>
                        <input type="number" name="year" class="form-control" value="<?php echo $movie['year']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">بلد الإنتاج</label>
                        <input type="text" name="country" class="form-control" value="<?php echo htmlspecialchars($movie['country']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">اللغة</label>
                        <select name="language" class="form-control">
                            <option value="ar" <?php echo $movie['language'] == 'ar' ? 'selected' : ''; ?>>العربية</option>
                            <option value="en" <?php echo $movie['language'] == 'en' ? 'selected' : ''; ?>>الإنجليزية</option>
                            <option value="tr" <?php echo $movie['language'] == 'tr' ? 'selected' : ''; ?>>التركية</option>
                            <option value="hi" <?php echo $movie['language'] == 'hi' ? 'selected' : ''; ?>>الهندية</option>
                            <option value="ko" <?php echo $movie['language'] == 'ko' ? 'selected' : ''; ?>>الكورية</option>
                            <option value="ja" <?php echo $movie['language'] == 'ja' ? 'selected' : ''; ?>>اليابانية</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">التصنيفات</label>
                        <input type="text" name="genre" class="form-control" value="<?php echo htmlspecialchars($movie['genre']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">المدة (دقائق)</label>
                        <input type="number" name="duration" class="form-control" value="<?php echo $movie['duration']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">تقييم IMDB</label>
                        <input type="number" step="0.1" name="imdb_rating" class="form-control" value="<?php echo $movie['imdb_rating']; ?>">
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">القصة</label>
                        <textarea name="overview" class="form-control"><?php echo htmlspecialchars($movie['description']); ?></textarea>
                    </div>
                </div>
                
                <!-- حالة الفيلم -->
                <div style="margin-top: 20px;">
                    <label class="form-label">حالة الفيلم</label>
                    <div class="status-selector">
                        <div class="status-option <?php echo $movie['status'] == 'published' ? 'selected' : ''; ?>" onclick="selectStatus('published')">
                            <i class="fas fa-check-circle"></i>
                            <div>منشور</div>
                            <input type="radio" name="status" value="published" <?php echo $movie['status'] == 'published' ? 'checked' : ''; ?> style="display: none;">
                        </div>
                        <div class="status-option <?php echo $movie['status'] == 'draft' ? 'selected' : ''; ?>" onclick="selectStatus('draft')">
                            <i class="fas fa-pen"></i>
                            <div>مسودة</div>
                            <input type="radio" name="status" value="draft" <?php echo $movie['status'] == 'draft' ? 'checked' : ''; ?> style="display: none;">
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
                    <div class="membership-card <?php echo $movie['membership_level'] == $key ? 'selected' : ''; ?>" onclick="selectMembership('<?php echo $key; ?>')">
                        <div class="membership-icon"><?php echo $level['icon']; ?></div>
                        <div class="membership-name" style="color: <?php echo $level['color']; ?>"><?php echo $level['name']; ?></div>
                        <div class="membership-desc"><?php echo $level['desc']; ?></div>
                        <div class="membership-badge" style="background: <?php echo $level['color']; ?>; color: <?php echo $key == 'vip' ? '#000' : '#fff'; ?>">
                            <?php echo $level['badge']; ?>
                        </div>
                        <input type="radio" name="membership_level" value="<?php echo $key; ?>" <?php echo $movie['membership_level'] == $key ? 'checked' : ''; ?> style="display: none;">
                    </div>
                    <?php endforeach; ?>
                </div>
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
                        <div style="color: var(--text-muted); font-size: 12px;"><?php echo htmlspecialchars($crew['job']); ?></div>
                        <input type="hidden" name="crew[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($crew['name']); ?>">
                        <input type="hidden" name="crew[<?php echo $index; ?>][job]" value="<?php echo htmlspecialchars($crew['job']); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- تبويب روابط المشاهدة -->
            <div id="watchTab" class="tab-content">
                <div class="links-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--primary);">روابط المشاهدة</h3>
                        <button type="button" class="add-link-btn" onclick="addWatchLink()">➕ إضافة رابط</button>
                    </div>
                    
                    <div id="watch-links-container">
                        <?php if (!empty($watch_links_data)): ?>
                            <?php foreach ($watch_links_data as $index => $link): ?>
                            <div class="link-item" id="watch-link-<?php echo $index; ?>">
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
                            <p style="color: #666; text-align: center; padding: 20px;">لا توجد روابط مشاهدة</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- تبويب روابط التحميل -->
            <div id="downloadTab" class="tab-content">
                <div class="links-container">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h3 style="color: var(--primary);">روابط التحميل</h3>
                        <button type="button" class="add-link-btn" onclick="addDownloadLink()">➕ إضافة رابط</button>
                    </div>
                    
                    <div id="download-links-container">
                        <?php if (!empty($download_links_data)): ?>
                            <?php foreach ($download_links_data as $index => $link): ?>
                            <div class="link-item" id="download-link-<?php echo $index; ?>">
                                <input type="text" name="download_links[<?php echo $index; ?>][name]" class="form-control" value="<?php echo htmlspecialchars($link['server_name']); ?>">
                                <input type="url" name="download_links[<?php echo $index; ?>][url]" class="form-control" value="<?php echo htmlspecialchars($link['download_url']); ?>">
                                <input type="text" name="download_links[<?php echo $index; ?>][quality]" class="form-control" value="<?php echo $link['quality']; ?>">
                                <input type="text" name="download_links[<?php echo $index; ?>][size]" class="form-control" value="<?php echo $link['size']; ?>">
                                <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #666; text-align: center; padding: 20px;">لا توجد روابط تحميل</p>
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
                            <div class="link-item" id="subtitle-<?php echo $index; ?>">
                                <select name="subtitles[<?php echo $index; ?>][language_code]" class="form-control">
                                    <?php foreach ($subtitle_languages as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $sub['language_code'] == $code ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="url" name="subtitles[<?php echo $index; ?>][url]" class="form-control" value="<?php echo $sub['subtitle_url']; ?>" placeholder="رابط الترجمة">
                                <label>
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
                <a href="movies-import.php" class="btn-secondary">
                    <i class="fas fa-times"></i> إلغاء
                </a>
            </div>
        </form>
    </div>
    
    <!-- مودال تغيير البوستر -->
    <div id="posterModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">تغيير صورة الفيلم</h3>
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
        // ========== دوال التبويبات ==========
        function showTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const tabMap = {
                'basic': 'basicTab',
                'membership': 'membershipTab',
                'cast': 'castTab',
                'crew': 'crewTab',
                'watch': 'watchTab',
                'download': 'downloadTab',
                'subtitles': 'subtitlesTab'
            };
            
            document.getElementById(tabMap[tab]).classList.add('active');
            
            document.querySelectorAll('.form-tab').forEach((btn, i) => {
                const tabs = ['basic', 'membership', 'cast', 'crew', 'watch', 'download', 'subtitles'];
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
        
        // ========== دوال إضافة العناصر ==========
        let watchLinkCount = <?php echo count($watch_links_data); ?>;
        let downloadLinkCount = <?php echo count($download_links_data); ?>;
        let subtitleCount = <?php echo count($subtitles_data); ?>;
        let castCount = <?php echo count($cast_data); ?>;
        
        function addWatchLink() {
            const container = document.getElementById('watch-links-container');
            const html = `
                <div class="link-item" id="watch-link-new-${watchLinkCount}">
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
        
        function addDownloadLink() {
            const container = document.getElementById('download-links-container');
            const html = `
                <div class="link-item" id="download-link-new-${downloadLinkCount}">
                    <input type="text" name="download_links[new_${downloadLinkCount}][name]" class="form-control" placeholder="اسم السيرفر">
                    <input type="url" name="download_links[new_${downloadLinkCount}][url]" class="form-control" placeholder="رابط التحميل">
                    <input type="text" name="download_links[new_${downloadLinkCount}][quality]" class="form-control" placeholder="الجودة" value="HD">
                    <input type="text" name="download_links[new_${downloadLinkCount}][size]" class="form-control" placeholder="الحجم">
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">🗑️</button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
            downloadLinkCount++;
        }
        
        function addSubtitle() {
            const container = document.getElementById('subtitles-container');
            const html = `
                <div class="link-item" id="subtitle-new-${subtitleCount}">
                    <select name="subtitles[new_${subtitleCount}][language_code]" class="form-control">
                        <?php foreach ($subtitle_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="subtitles[new_${subtitleCount}][url]" class="form-control" placeholder="رابط الترجمة">
                    <label>
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
                const formData = new FormData();
                formData.append('poster_file', fileInput.files[0]);
                formData.append('update_poster', '1');
                
                // محاكاة رفع الصورة (في الحقيقة سيتم رفعها عند حفظ النموذج)
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview-poster').src = e.target.result;
                    closePosterModal();
                    alert('تم تغيير الصورة مؤقتاً. سيتم الحفظ عند الضغط على "حفظ التغييرات"');
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