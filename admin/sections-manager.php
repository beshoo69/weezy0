<?php
// admin/sections-manager.php - إدارة أقسام الموقع المتكاملة
define('ALLOW_ACCESS', true);

$base_path = 'C:/xampp/htdocs/fayez-movie';
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// =============================================
// إنشاء جدول الأقسام إذا لم يكن موجوداً
// =============================================
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS site_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_en VARCHAR(100),
            icon VARCHAR(50) DEFAULT 'fa-folder',
            description TEXT,
            section_type VARCHAR(50) NOT NULL COMMENT 'movies, series, anime, etc',
            source_type VARCHAR(50) NOT NULL COMMENT 'tmdb, youtube, database',
            filter_category VARCHAR(100) COMMENT 'category filter like egyptian, indian, etc',
            filter_language VARCHAR(50),
            filter_country VARCHAR(100),
            filter_genre VARCHAR(200),
            filter_year VARCHAR(20),
            custom_query TEXT,
            display_order INT DEFAULT 0,
            items_count INT DEFAULT 12,
            view_type ENUM('grid', 'slider', 'list', 'carousel') DEFAULT 'grid',
            is_active BOOLEAN DEFAULT TRUE,
            show_in_home BOOLEAN DEFAULT TRUE,
            show_in_menu BOOLEAN DEFAULT FALSE,
            menu_icon VARCHAR(50),
            menu_order INT DEFAULT 0,
            background_color VARCHAR(20) DEFAULT '#1a1a1a',
            text_color VARCHAR(20) DEFAULT '#ffffff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_type (section_type),
            INDEX idx_active (is_active),
            INDEX idx_order (display_order),
            INDEX idx_menu (show_in_menu, menu_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // إضافة الأقسام الافتراضية إذا كانت فارغة
    $check = $pdo->query("SELECT COUNT(*) FROM site_sections")->fetchColumn();
    if ($check == 0) {
        $default_sections = [
            // أقسام الأفلام
            ['أفلام عربية', 'Arabic Movies', 'fa-film', 'أحدث الأفلام العربية', 'movies', 'tmdb', 'arabic', 'ar', '', '', '', 1, 12, 'grid', 1, 1, 1, 'fa-film', 1],
            ['أفلام مصرية', 'Egyptian Movies', 'fa-film', 'أحدث الأفلام المصرية', 'movies', 'tmdb', 'egyptian', 'ar', 'مصر', '', '', 2, 12, 'grid', 1, 1, 1, 'fa-film', 2],
            ['أفلام تركية', 'Turkish Movies', 'fa-film', 'أحدث الأفلام التركية', 'movies', 'tmdb', 'turkish', 'tr', 'تركيا', '', '', 3, 12, 'grid', 1, 1, 1, 'fa-film', 3],
            ['أفلام هندية', 'Indian Movies', 'fa-film', 'أحدث الأفلام الهندية', 'movies', 'tmdb', 'indian', 'hi', 'الهند', '', '', 4, 12, 'grid', 1, 1, 1, 'fa-film', 4],
            ['أفلام آسيوية', 'Asian Movies', 'fa-film', 'أحدث الأفلام الآسيوية', 'movies', 'tmdb', 'asian', '', 'آسيا', '', '', 5, 12, 'grid', 1, 1, 1, 'fa-film', 5],
            ['أفلام أجنبية', 'Foreign Movies', 'fa-film', 'أحدث الأفلام الأجنبية', 'movies', 'tmdb', 'foreign', 'en', '', '', '', 6, 12, 'grid', 1, 1, 1, 'fa-film', 6],
            
            // أقسام المسلسلات
            ['مسلسلات عربية', 'Arabic Series', 'fa-tv', 'أحدث المسلسلات العربية', 'series', 'tmdb', 'arabic', 'ar', '', '', '', 7, 12, 'grid', 1, 1, 1, 'fa-tv', 7],
            ['مسلسلات مصرية', 'Egyptian Series', 'fa-tv', 'أحدث المسلسلات المصرية', 'series', 'tmdb', 'egyptian', 'ar', 'مصر', '', '', 8, 12, 'grid', 1, 1, 1, 'fa-tv', 8],
            ['مسلسلات تركية', 'Turkish Series', 'fa-tv', 'أحدث المسلسلات التركية', 'series', 'tmdb', 'turkish', 'tr', 'تركيا', '', '', 9, 12, 'grid', 1, 1, 1, 'fa-tv', 9],
            ['مسلسلات هندية', 'Indian Series', 'fa-tv', 'أحدث المسلسلات الهندية', 'series', 'tmdb', 'indian', 'hi', 'الهند', '', '', 10, 12, 'grid', 1, 1, 1, 'fa-tv', 10],
            ['مسلسلات آسيوية', 'Asian Series', 'fa-tv', 'أحدث المسلسلات الآسيوية', 'series', 'tmdb', 'asian', '', 'آسيا', '', '', 11, 12, 'grid', 1, 1, 1, 'fa-tv', 11],
            ['مسلسلات أجنبية', 'Foreign Series', 'fa-tv', 'أحدث المسلسلات الأجنبية', 'series', 'tmdb', 'foreign', 'en', '', '', '', 12, 12, 'grid', 1, 1, 1, 'fa-tv', 12],
            
            // أقسام الأنمي
            ['أنمي', 'Anime', 'fa-dragon', 'أحدث حلقات الأنمي', 'anime', 'tmdb', 'anime', 'ja', 'اليابان', 'أنمي', '', 13, 12, 'grid', 1, 1, 1, 'fa-dragon', 13],
            ['أنمي مدبلج', 'Dubbed Anime', 'fa-dragon', 'أنمي مدبلج للعربية', 'anime', 'tmdb', 'anime_dubbed', 'ar', 'اليابان', 'أنمي', '', 14, 12, 'grid', 1, 1, 0, 'fa-dragon', 0],
            ['أنمي مترجم', 'Subbed Anime', 'fa-dragon', 'أنمي مترجم', 'anime', 'tmdb', 'anime_subbed', 'ar', 'اليابان', 'أنمي', '', 15, 12, 'grid', 1, 1, 0, 'fa-dragon', 0],
            
            // أقسام يوتيوب
            ['أفلام يوتيوب', 'YouTube Movies', 'fab fa-youtube', 'أفلام مجانية من يوتيوب', 'youtube_movies', 'youtube', 'movies', '', '', '', '', 16, 12, 'grid', 1, 1, 1, 'fab fa-youtube', 14],
            ['مسلسلات يوتيوب', 'YouTube Series', 'fab fa-youtube', 'مسلسلات مجانية من يوتيوب', 'youtube_series', 'youtube', 'series', '', '', '', '', 17, 12, 'grid', 1, 1, 1, 'fab fa-youtube', 15],
            
            // أقسام البث المباشر
            ['قنوات عربية', 'Arabic Channels', 'fa-broadcast-tower', 'قنوات عربية بث مباشر', 'live', 'custom', 'arabic', '', '', '', '', 18, 12, 'grid', 1, 1, 1, 'fa-broadcast-tower', 16],
            ['قنوات أجنبية', 'Foreign Channels', 'fa-broadcast-tower', 'قنوات أجنبية بث مباشر', 'live', 'custom', 'foreign', '', '', '', '', 19, 12, 'grid', 1, 1, 1, 'fa-broadcast-tower', 17]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO site_sections (
                name, name_en, icon, description, section_type, source_type, 
                filter_category, filter_language, filter_country, filter_genre, filter_year,
                display_order, items_count, view_type, is_active, show_in_home, 
                show_in_menu, menu_icon, menu_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($default_sections as $section) {
            $stmt->execute($section);
        }
    }
    
} catch (Exception $e) {
    $error = "خطأ في إنشاء الجدول: " . $e->getMessage();
}

// =============================================
// معالجة العمليات
// =============================================

// حذف قسم
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM site_sections WHERE id = ?");
        $stmt->execute([$id]);
        $message = "✅ تم حذف القسم بنجاح";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ خطأ في الحذف: " . $e->getMessage();
        $message_type = "error";
    }
}

// تحديث حالة القسم
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $field = $_GET['field'] ?? 'is_active';
    try {
        $current = $pdo->prepare("SELECT $field FROM site_sections WHERE id = ?");
        $current->execute([$id]);
        $status = $current->fetchColumn();
        $new_status = $status ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE site_sections SET $field = ? WHERE id = ?");
        $stmt->execute([$new_status, $id]);
        $message = "✅ تم تحديث الحالة";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $message_type = "error";
    }
}

// تحديث ترتيب الأقسام
if (isset($_POST['update_order'])) {
    $orders = $_POST['order'] ?? [];
    try {
        foreach ($orders as $id => $order) {
            $stmt = $pdo->prepare("UPDATE site_sections SET display_order = ? WHERE id = ?");
            $stmt->execute([$order, $id]);
        }
        $message = "✅ تم تحديث ترتيب الأقسام";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $message_type = "error";
    }
}

// تحديث ترتيب القائمة
if (isset($_POST['update_menu_order'])) {
    $orders = $_POST['menu_order'] ?? [];
    try {
        foreach ($orders as $id => $order) {
            $stmt = $pdo->prepare("UPDATE site_sections SET menu_order = ?, show_in_menu = 1 WHERE id = ?");
            $stmt->execute([$order, $id]);
        }
        $message = "✅ تم تحديث ترتيب القائمة";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $message_type = "error";
    }
}

// إضافة أو تحديث قسم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_section'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = $_POST['name'] ?? '';
    $name_en = $_POST['name_en'] ?? '';
    $icon = $_POST['icon'] ?? 'fa-folder';
    $description = $_POST['description'] ?? '';
    $section_type = $_POST['section_type'] ?? 'movies';
    $source_type = $_POST['source_type'] ?? 'tmdb';
    $filter_category = $_POST['filter_category'] ?? '';
    $filter_language = $_POST['filter_language'] ?? '';
    $filter_country = $_POST['filter_country'] ?? '';
    $filter_genre = $_POST['filter_genre'] ?? '';
    $filter_year = $_POST['filter_year'] ?? '';
    $custom_query = $_POST['custom_query'] ?? '';
    $display_order = (int)($_POST['display_order'] ?? 0);
    $items_count = (int)($_POST['items_count'] ?? 12);
    $view_type = $_POST['view_type'] ?? 'grid';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_in_home = isset($_POST['show_in_home']) ? 1 : 0;
    $show_in_menu = isset($_POST['show_in_menu']) ? 1 : 0;
    $menu_icon = $_POST['menu_icon'] ?? $icon;
    $menu_order = (int)($_POST['menu_order'] ?? 0);
    $background_color = $_POST['background_color'] ?? '#1a1a1a';
    $text_color = $_POST['text_color'] ?? '#ffffff';
    
    if (empty($name)) {
        $message = "❌ اسم القسم مطلوب";
        $message_type = "error";
    } else {
        try {
            if ($id > 0) {
                // تحديث
                $stmt = $pdo->prepare("
                    UPDATE site_sections SET 
                        name = ?, name_en = ?, icon = ?, description = ?,
                        section_type = ?, source_type = ?, filter_category = ?,
                        filter_language = ?, filter_country = ?, filter_genre = ?,
                        filter_year = ?, custom_query = ?, display_order = ?,
                        items_count = ?, view_type = ?, is_active = ?,
                        show_in_home = ?, show_in_menu = ?, menu_icon = ?,
                        menu_order = ?, background_color = ?, text_color = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $name, $name_en, $icon, $description,
                    $section_type, $source_type, $filter_category,
                    $filter_language, $filter_country, $filter_genre,
                    $filter_year, $custom_query, $display_order,
                    $items_count, $view_type, $is_active,
                    $show_in_home, $show_in_menu, $menu_icon,
                    $menu_order, $background_color, $text_color,
                    $id
                ]);
                $message = "✅ تم تحديث القسم بنجاح";
            } else {
                // إضافة
                $stmt = $pdo->prepare("
                    INSERT INTO site_sections (
                        name, name_en, icon, description, section_type, source_type,
                        filter_category, filter_language, filter_country, filter_genre,
                        filter_year, custom_query, display_order, items_count, view_type,
                        is_active, show_in_home, show_in_menu, menu_icon, menu_order,
                        background_color, text_color
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $name, $name_en, $icon, $description,
                    $section_type, $source_type, $filter_category,
                    $filter_language, $filter_country, $filter_genre,
                    $filter_year, $custom_query, $display_order,
                    $items_count, $view_type, $is_active,
                    $show_in_home, $show_in_menu, $menu_icon,
                    $menu_order, $background_color, $text_color
                ]);
                $message = "✅ تم إضافة القسم بنجاح";
            }
            $message_type = "success";
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// جلب قسم للتعديل
$edit_section = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM site_sections WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $edit_section = $stmt->fetch();
}

// جلب جميع الأقسام
$sections = $pdo->query("
    SELECT * FROM site_sections 
    ORDER BY 
        CASE 
            WHEN section_type = 'movies' THEN 1
            WHEN section_type = 'series' THEN 2
            WHEN section_type = 'anime' THEN 3
            WHEN section_type = 'youtube_movies' THEN 4
            WHEN section_type = 'youtube_series' THEN 5
            WHEN section_type = 'live' THEN 6
            ELSE 7
        END,
        display_order ASC,
        id ASC
")->fetchAll();

// تجميع الأقسام حسب النوع
$grouped_sections = [];
foreach ($sections as $section) {
    $grouped_sections[$section['section_type']][] = $section;
}

// إحصائيات
$stats = [
    'total' => count($sections),
    'active' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE is_active = 1")->fetchColumn(),
    'movies' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE section_type = 'movies'")->fetchColumn(),
    'series' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE section_type = 'series'")->fetchColumn(),
    'anime' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE section_type = 'anime'")->fetchColumn(),
    'youtube' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE source_type = 'youtube'")->fetchColumn(),
    'in_home' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE show_in_home = 1")->fetchColumn(),
    'in_menu' => $pdo->query("SELECT COUNT(*) FROM site_sections WHERE show_in_menu = 1")->fetchColumn()
];

// قوائم التصنيفات
$section_types = [
    'movies' => '🎬 أفلام',
    'series' => '📺 مسلسلات',
    'anime' => '🇯🇵 أنمي',
    'youtube_movies' => '▶️ أفلام يوتيوب',
    'youtube_series' => '📋 مسلسلات يوتيوب',
    'live' => '📡 بث مباشر',
    'custom' => '⚙️ مخصص'
];

$source_types = [
    'tmdb' => 'TMDB',
    'youtube' => 'يوتيوب',
    'database' => 'قاعدة البيانات',
    'custom' => 'مخصص'
];

$view_types = [
    'grid' => 'شبكة',
    'slider' => 'سلايدر',
    'list' => 'قائمة',
    'carousel' => 'كاروسيل'
];

// قوائم الفلترة
$categories = [
    'arabic' => 'عربي',
    'egyptian' => 'مصري',
    'turkish' => 'تركي',
    'indian' => 'هندي',
    'asian' => 'آسيوي',
    'foreign' => 'أجنبي',
    'anime' => 'أنمي',
    'anime_dubbed' => 'أنمي مدبلج',
    'anime_subbed' => 'أنمي مترجم',
    'movies' => 'أفلام',
    'series' => 'مسلسلات',
    'general' => 'عام'
];

$languages = [
    'ar' => 'العربية',
    'en' => 'الإنجليزية',
    'tr' => 'التركية',
    'hi' => 'الهندية',
    'ko' => 'الكورية',
    'ja' => 'اليابانية',
    'zh' => 'الصينية',
    'fr' => 'الفرنسية',
    'de' => 'الألمانية',
    'es' => 'الإسبانية'
];

$icons = [
    'fa-film' => '🎬 فيلم',
    'fa-tv' => '📺 تلفاز',
    'fa-dragon' => '🐉 أنمي',
    'fa-youtube' => '▶️ يوتيوب',
    'fa-broadcast-tower' => '📡 بث مباشر',
    'fa-star' => '⭐ نجمة',
    'fa-fire' => '🔥 نار',
    'fa-heart' => '❤️ قلب',
    'fa-thumbs-up' => '👍 إعجاب',
    'fa-eye' => '👁️ مشاهدة',
    'fa-play-circle' => '▶️ تشغيل',
    'fa-video' => '🎥 فيديو',
    'fa-list' => '📋 قائمة',
    'fa-globe' => '🌐 عالمي',
    'fa-music' => '🎵 موسيقى',
    'fa-gamepad' => '🎮 ألعاب',
    'fa-futbol' => '⚽ رياضة',
    'fa-newspaper' => '📰 أخبار'
];
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📋 إدارة أقسام الموقع - فايز تڨي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
        }

        .top-bar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(229, 9, 20, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo h1 {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
        }

        .logo span {
            color: #fff;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1a1a1a;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #333;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: #e50914;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .dashboard-container {
            display: flex;
            padding: 30px;
            gap: 30px;
            min-height: calc(100vh - 80px);
        }

        .sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid #333;
            position: sticky;
            top: 100px;
            max-height: calc(100vh - 120px);
            overflow-y: auto;
            flex-shrink: 0;
        }

        .nav-section {
            color: #b3b3b3;
            font-size: 12px;
            margin: 25px 0 15px 10px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #b3b3b3;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            gap: 12px;
            transition: all 0.3s;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
        }

        .main-content {
            flex: 1;
            min-width: 0;
        }

        .content-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e50914;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #252525;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #333;
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #e50914;
        }

        .stat-label {
            color: #b3b3b3;
            font-size: 13px;
            margin-top: 5px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
            font-size: 14px;
        }

        .btn-primary {
            background: #e50914;
        }

        .btn-primary:hover {
            background: #b20710;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219a52;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-info {
            background: #3498db;
        }

        .btn-info:hover {
            background: #2980b9;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group.full-width {
            grid-column: span 3;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
            font-weight: 500;
            font-size: 13px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: #e50914;
            outline: none;
        }

        .form-control[type="color"] {
            height: 45px;
            padding: 5px;
        }

        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b3b3b3;
            cursor: pointer;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #e50914;
        }

        .sections-table {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: right;
            padding: 12px;
            color: #b3b3b3;
            font-weight: 600;
            border-bottom: 2px solid #333;
            font-size: 13px;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #333;
            vertical-align: middle;
            font-size: 13px;
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .section-group {
            margin-bottom: 30px;
        }

        .group-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            padding: 10px 15px;
            background: #252525;
            border-radius: 10px;
            border-right: 4px solid #e50914;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-active {
            background: #27ae60;
            color: white;
        }

        .status-inactive {
            background: #dc3545;
            color: white;
        }

        .status-home {
            background: #3498db;
            color: white;
        }

        .status-menu {
            background: #9b59b6;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .order-input {
            width: 60px;
            padding: 5px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
            text-align: center;
        }

        .icon-preview {
            font-size: 18px;
            width: 35px;
            height: 35px;
            background: #252525;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e50914;
        }

        .filter-tags {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .filter-tag {
            background: rgba(229,9,20,0.1);
            color: #e50914;
            padding: 2px 8px;
            border-radius: 15px;
            font-size: 10px;
        }

        .notification {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 50px;
            background: #27ae60;
            color: white;
            z-index: 9999;
            animation: slideDown 0.5s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .notification.error {
            background: #e50914;
        }

        .notification.warning {
            background: #f39c12;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 10px 20px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 30px;
            color: #b3b3b3;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .tab:hover,
        .tab.active {
            background: #e50914;
            color: white;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        @media (max-width: 1200px) {
            .form-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .form-group.full-width {
                grid-column: span 2;
            }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
            }
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-group.full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-film" style="color: #e50914; font-size: 32px;"></i>
            <h1>فايز<span>تڨي</span></h1>
        </div>
        <div class="user-menu">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if (isset($message)): ?>
            <div class="notification <?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- إحصائيات سريعة -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">إجمالي الأقسام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['active']; ?></div>
                    <div class="stat-label">أقسام نشطة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['movies']; ?></div>
                    <div class="stat-label">أفلام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['series']; ?></div>
                    <div class="stat-label">مسلسلات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['anime']; ?></div>
                    <div class="stat-label">أنمي</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['youtube']; ?></div>
                    <div class="stat-label">يوتيوب</div>
                </div>
            </div>

            <!-- تبويبات -->
            <div class="tabs">
                <div class="tab active" onclick="showTab('all')">📋 جميع الأقسام</div>
                <div class="tab" onclick="showTab('add')">➕ إضافة قسم</div>
                <div class="tab" onclick="showTab('menu')">📱 القائمة الرئيسية</div>
            </div>

            <!-- تبويب جميع الأقسام -->
            <div id="tab-all" class="tab-content active">
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-list"></i> جميع أقسام الموقع</h2>
                        <span><?php echo $stats['total']; ?> قسم</span>
                    </div>

                    <?php foreach ($grouped_sections as $type => $type_sections): ?>
                    <div class="section-group">
                        <div class="group-title">
                            <?php echo $section_types[$type] ?? $type; ?> 
                            <span style="color: #e50914; margin-right: 10px;">(<?php echo count($type_sections); ?>)</span>
                        </div>

                        <div class="sections-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الأيقونة</th>
                                        <th>الاسم</th>
                                        <th>التصنيف</th>
                                        <th>الفلترة</th>
                                        <th>الترتيب</th>
                                        <th>الحالة</th>
                                        <th>عرض</th>
                                        <th>الإجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($type_sections as $index => $section): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td>
                                            <div class="icon-preview">
                                                <i class="fas <?php echo $section['icon']; ?>"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($section['name']); ?></strong>
                                            <?php if (!empty($section['name_en'])): ?>
                                            <br><small style="color: #b3b3b3;"><?php echo htmlspecialchars($section['name_en']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $categories[$section['filter_category']] ?? $section['filter_category']; ?>
                                            <div style="font-size: 11px; color: #b3b3b3;">
                                                <?php echo $languages[$section['filter_language']] ?? $section['filter_language']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="filter-tags">
                                                <?php if ($section['filter_country']): ?>
                                                <span class="filter-tag">🌍 <?php echo $section['filter_country']; ?></span>
                                                <?php endif; ?>
                                                <?php if ($section['filter_genre']): ?>
                                                <span class="filter-tag">🎬 <?php echo $section['filter_genre']; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo $section['display_order']; ?></td>
                                        <td>
                                            <span class="status-badge <?php echo $section['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $section['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($section['show_in_home']): ?>
                                            <span class="status-badge status-home" title="يظهر في الرئيسية">
                                                <i class="fas fa-home"></i>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($section['show_in_menu']): ?>
                                            <span class="status-badge status-menu" title="يظهر في القائمة">
                                                <i class="fas fa-bars"></i>
                                            </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="?edit=<?php echo $section['id']; ?>" class="btn btn-warning btn-sm" title="تعديل">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $section['id']; ?>&field=is_active" class="btn btn-info btn-sm" 
                                                   title="<?php echo $section['is_active'] ? 'تعطيل' : 'تفعيل'; ?>">
                                                    <i class="fas fa-<?php echo $section['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="?toggle=<?php echo $section['id']; ?>&field=show_in_home" class="btn btn-primary btn-sm" 
                                                   title="<?php echo $section['show_in_home'] ? 'إخفاء من الرئيسية' : 'إظهار في الرئيسية'; ?>">
                                                    <i class="fas fa-<?php echo $section['show_in_home'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                </a>
                                                <a href="?delete=<?php echo $section['id']; ?>" class="btn btn-danger btn-sm" 
                                                   onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا القسم؟')" title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- تحديث ترتيب المجموعة -->
                        <form method="POST" style="margin-top: 15px;">
                            <input type="hidden" name="update_order" value="1">
                            <div style="display: flex; gap: 10px; align-items: center;">
                                <span style="color: #b3b3b3;">تحديث ترتيب المجموعة:</span>
                                <?php foreach ($type_sections as $section): ?>
                                <input type="number" name="order[<?php echo $section['id']; ?>]" 
                                       class="order-input" value="<?php echo $section['display_order']; ?>" 
                                       placeholder="ID:<?php echo $section['id']; ?>" style="width: 70px;">
                                <?php endforeach; ?>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-save"></i> حفظ
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- تبويب إضافة/تعديل قسم -->
            <div id="tab-add" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas <?php echo $edit_section ? 'fa-edit' : 'fa-plus-circle'; ?>"></i> 
                            <?php echo $edit_section ? 'تعديل القسم: ' . htmlspecialchars($edit_section['name']) : 'إضافة قسم جديد'; ?>
                        </h2>
                        <?php if ($edit_section): ?>
                        <a href="sections-manager.php" class="btn btn-warning btn-sm">
                            <i class="fas fa-times"></i> إلغاء التعديل
                        </a>
                        <?php endif; ?>
                    </div>

                    <form method="POST">
                        <?php if ($edit_section): ?>
                        <input type="hidden" name="id" value="<?php echo $edit_section['id']; ?>">
                        <?php endif; ?>
                        <input type="hidden" name="save_section" value="1">

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">اسم القسم (عربي) *</label>
                                <input type="text" name="name" class="form-control" required 
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['name']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">اسم القسم (إنجليزي)</label>
                                <input type="text" name="name_en" class="form-control" 
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['name_en']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">الأيقونة</label>
                                <select name="icon" class="form-control">
                                    <?php foreach ($icons as $icon => $label): ?>
                                    <option value="<?php echo $icon; ?>" <?php echo ($edit_section && $edit_section['icon'] == $icon) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">نوع القسم</label>
                                <select name="section_type" class="form-control">
                                    <?php foreach ($section_types as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($edit_section && $edit_section['section_type'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">مصدر المحتوى</label>
                                <select name="source_type" class="form-control">
                                    <?php foreach ($source_types as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($edit_section && $edit_section['source_type'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">التصنيف</label>
                                <select name="filter_category" class="form-control">
                                    <option value="">بدون تصنيف</option>
                                    <?php foreach ($categories as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($edit_section && $edit_section['filter_category'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">اللغة</label>
                                <select name="filter_language" class="form-control">
                                    <option value="">كل اللغات</option>
                                    <?php foreach ($languages as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($edit_section && $edit_section['filter_language'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">البلد</label>
                                <input type="text" name="filter_country" class="form-control" 
                                       placeholder="مثال: مصر, تركيا, الهند"
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['filter_country']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">التصنيف (Genre)</label>
                                <input type="text" name="filter_genre" class="form-control" 
                                       placeholder="مثال: أكشن, دراما, كوميديا"
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['filter_genre']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">السنة</label>
                                <input type="text" name="filter_year" class="form-control" 
                                       placeholder="مثال: 2024, 2023-2024"
                                       value="<?php echo $edit_section ? htmlspecialchars($edit_section['filter_year']) : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">ترتيب العرض</label>
                                <input type="number" name="display_order" class="form-control" 
                                       value="<?php echo $edit_section ? $edit_section['display_order'] : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">عدد العناصر</label>
                                <input type="number" name="items_count" class="form-control" 
                                       value="<?php echo $edit_section ? $edit_section['items_count'] : '12'; ?>" min="1" max="50">
                            </div>

                            <div class="form-group">
                                <label class="form-label">نوع العرض</label>
                                <select name="view_type" class="form-control">
                                    <?php foreach ($view_types as $value => $label): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($edit_section && $edit_section['view_type'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">أيقونة القائمة</label>
                                <select name="menu_icon" class="form-control">
                                    <?php foreach ($icons as $icon => $label): ?>
                                    <option value="<?php echo $icon; ?>" <?php echo ($edit_section && $edit_section['menu_icon'] == $icon) ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label class="form-label">ترتيب القائمة</label>
                                <input type="number" name="menu_order" class="form-control" 
                                       value="<?php echo $edit_section ? $edit_section['menu_order'] : '0'; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">لون الخلفية</label>
                                <input type="color" name="background_color" class="form-control" 
                                       value="<?php echo $edit_section ? $edit_section['background_color'] : '#1a1a1a'; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">لون النص</label>
                                <input type="color" name="text_color" class="form-control" 
                                       value="<?php echo $edit_section ? $edit_section['text_color'] : '#ffffff'; ?>">
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">وصف القسم</label>
                                <textarea name="description" class="form-control" rows="2"><?php echo $edit_section ? htmlspecialchars($edit_section['description']) : ''; ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <label class="form-label">استعلام مخصص (للأقسام المخصصة)</label>
                                <textarea name="custom_query" class="form-control" rows="2" placeholder="SELECT * FROM ..."><?php echo $edit_section ? htmlspecialchars($edit_section['custom_query']) : ''; ?></textarea>
                            </div>

                            <div class="form-group full-width">
                                <div class="checkbox-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_active" <?php echo (!$edit_section || $edit_section['is_active']) ? 'checked' : ''; ?>>
                                        <i class="fas fa-check-circle"></i> قسم نشط
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="show_in_home" <?php echo (!$edit_section || $edit_section['show_in_home']) ? 'checked' : ''; ?>>
                                        <i class="fas fa-home"></i> عرض في الصفحة الرئيسية
                                    </label>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="show_in_menu" <?php echo ($edit_section && $edit_section['show_in_menu']) ? 'checked' : ''; ?>>
                                        <i class="fas fa-bars"></i> عرض في القائمة الرئيسية
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> 
                                <?php echo $edit_section ? 'تحديث القسم' : 'إضافة القسم'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- تبويب القائمة الرئيسية -->
            <div id="tab-menu" class="tab-content">
                <div class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bars"></i> تخصيص القائمة الرئيسية</h2>
                        <span><?php echo $stats['in_menu']; ?> عنصر في القائمة</span>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="update_menu_order" value="1">
                        
                        <div class="sections-table">
                            <table>
                                <thead>
                                    <tr>
                                        <th>الترتيب</th>
                                        <th>الأيقونة</th>
                                        <th>اسم القسم</th>
                                        <th>الرابط</th>
                                        <th>الحالة</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $menu_items = array_filter($sections, function($s) { return $s['show_in_menu']; });
                                    usort($menu_items, function($a, $b) {
                                        return $a['menu_order'] - $b['menu_order'];
                                    });
                                    ?>
                                    <?php foreach ($menu_items as $item): ?>
                                    <tr>
                                        <td>
                                            <input type="number" name="menu_order[<?php echo $item['id']; ?>]" 
                                                   class="order-input" value="<?php echo $item['menu_order']; ?>" min="0" max="100">
                                        </td>
                                        <td>
                                            <div class="icon-preview">
                                                <i class="fas <?php echo $item['menu_icon'] ?: $item['icon']; ?>"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $link = '#';
                                            if ($item['section_type'] == 'movies') $link = 'movies.php?type=' . $item['filter_category'];
                                            elseif ($item['section_type'] == 'series') $link = 'series.php?type=' . $item['filter_category'];
                                            elseif ($item['section_type'] == 'anime') $link = 'anime-series.php';
                                            elseif ($item['section_type'] == 'youtube_movies') $link = 'free.php?view=movies';
                                            elseif ($item['section_type'] == 'youtube_series') $link = 'free.php?view=series';
                                            elseif ($item['section_type'] == 'live') $link = 'live.php';
                                            ?>
                                            <small style="color: #b3b3b3;"><?php echo $link; ?></small>
                                        </td>
                                        <td>
                                            <span class="status-badge <?php echo $item['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $item['is_active'] ? 'نشط' : 'غير نشط'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div style="margin-top: 20px;">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> حفظ ترتيب القائمة
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'all') {
                document.querySelectorAll('.tab')[0].classList.add('active');
                document.getElementById('tab-all').classList.add('active');
            } else if (tab === 'add') {
                document.querySelectorAll('.tab')[1].classList.add('active');
                document.getElementById('tab-add').classList.add('active');
            } else if (tab === 'menu') {
                document.querySelectorAll('.tab')[2].classList.add('active');
                document.getElementById('tab-menu').classList.add('active');
            }
        }

        setTimeout(() => {
            const notification = document.querySelector('.notification');
            if (notification) {
                notification.style.animation = 'slideDown 0.5s reverse';
                setTimeout(() => notification.remove(), 500);
            }
        }, 5000);
    </script>
</body>
</html>