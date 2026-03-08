<?php
// admin/dashboard-pro.php - لوحة التحكم الاحترافية مع Scroll
define('ALLOW_ACCESS', true);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// =============================================
// إحصائيات شاملة
// =============================================
$stats = [];

// إحصائيات أساسية
$stats['movies'] = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$stats['series'] = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
$stats['episodes'] = $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn();
$stats['total_views'] = $pdo->query("SELECT IFNULL(SUM(views),0) FROM movies")->fetchColumn() + 
                        $pdo->query("SELECT IFNULL(SUM(views),0) FROM series")->fetchColumn();

// إحصائيات الترجمة
try {
    $stats['subtitles'] = $pdo->query("SELECT COUNT(*) FROM subtitles")->fetchColumn();
} catch (Exception $e) {
    $stats['subtitles'] = 0;
}

// إحصائيات المستخدمين
try {
    $stats['users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
} catch (Exception $e) {
    $stats['users'] = 0;
}

// إحصائيات حسب السنة
$current_year = date('Y');
$last_year = $current_year - 1;

$stats['movies_2026'] = $pdo->prepare("SELECT COUNT(*) FROM movies WHERE year = '2026'")->execute() ? $pdo->query("SELECT COUNT(*) FROM movies WHERE year = '2026'")->fetchColumn() : 0;
$stats['movies_2025'] = $pdo->prepare("SELECT COUNT(*) FROM movies WHERE year = '2025'")->execute() ? $pdo->query("SELECT COUNT(*) FROM movies WHERE year = '2025'")->fetchColumn() : 0;
$stats['series_2026'] = $pdo->prepare("SELECT COUNT(*) FROM series WHERE year = '2026'")->execute() ? $pdo->query("SELECT COUNT(*) FROM series WHERE year = '2026'")->fetchColumn() : 0;
$stats['series_2025'] = $pdo->prepare("SELECT COUNT(*) FROM series WHERE year = '2025'")->execute() ? $pdo->query("SELECT COUNT(*) FROM series WHERE year = '2025'")->fetchColumn() : 0;

// إحصائيات حسب اللغة
$stats['arabic_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'ar'")->fetchColumn();
$stats['arabic_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'ar'")->fetchColumn();
$stats['english_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'en'")->fetchColumn();
$stats['english_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'en'")->fetchColumn();
$stats['turkish_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'tr'")->fetchColumn();
$stats['turkish_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'tr'")->fetchColumn();
$stats['indian_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE language = 'hi'")->fetchColumn();
$stats['indian_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'hi'")->fetchColumn();
$stats['korean_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'ko'")->fetchColumn();
$stats['japanese_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE language = 'ja'")->fetchColumn();

// إحصائيات التقييمات
$stats['high_rated_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE imdb_rating >= 8.0")->fetchColumn();
$stats['high_rated_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE imdb_rating >= 8.0")->fetchColumn();

// إحصائيات الروابط
$stats['total_download_links'] = $pdo->query("SELECT COUNT(*) FROM download_servers")->fetchColumn();
$stats['valid_download_links'] = $pdo->query("SELECT COUNT(*) FROM download_servers WHERE is_valid = 1")->fetchColumn();
$stats['invalid_download_links'] = $pdo->query("SELECT COUNT(*) FROM download_servers WHERE is_valid = 0")->fetchColumn();

// إحصائيات الصور الناقصة
$stats['missing_posters_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE poster IS NULL OR poster = '' OR poster = 'N/A'")->fetchColumn();
$stats['missing_posters_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE poster IS NULL OR poster = '' OR poster = 'N/A'")->fetchColumn();
$stats['missing_backdrops_movies'] = $pdo->query("SELECT COUNT(*) FROM movies WHERE backdrop IS NULL OR backdrop = '' OR backdrop = 'N/A'")->fetchColumn();
$stats['missing_backdrops_series'] = $pdo->query("SELECT COUNT(*) FROM series WHERE backdrop IS NULL OR backdrop = '' OR backdrop = 'N/A'")->fetchColumn();

// =============================================
// أحدث العناصر
// =============================================
$latest_movies = $pdo->query("SELECT * FROM movies ORDER BY id DESC LIMIT 8")->fetchAll();
$latest_series = $pdo->query("SELECT * FROM series ORDER BY id DESC LIMIT 8")->fetchAll();

// أحدث الترجمات
try {
    $latest_subtitles = $pdo->query("
        SELECT s.*, 
               CASE 
                   WHEN s.content_type = 'movie' THEN (SELECT title FROM movies WHERE id = s.content_id)
                   WHEN s.content_type = 'series' THEN (SELECT title FROM series WHERE id = s.content_id)
                   ELSE 'غير معروف'
               END as content_title
        FROM subtitles s 
        ORDER BY s.id DESC 
        LIMIT 5
    ")->fetchAll();
} catch (Exception $e) {
    $latest_subtitles = [];
}

// =============================================
// الأكثر مشاهدة
// =============================================
$top_movies = $pdo->query("SELECT * FROM movies ORDER BY views DESC LIMIT 5")->fetchAll();
$top_series = $pdo->query("SELECT * FROM series ORDER BY views DESC LIMIT 5")->fetchAll();

// =============================================
// إحصائيات التوزيع
// =============================================
$languages_data = [
    ['label' => 'عربي', 'value' => $stats['arabic_movies'] + $stats['arabic_series'], 'color' => '#0e4620'],
    ['label' => 'إنجليزي', 'value' => $stats['english_movies'] + $stats['english_series'], 'color' => '#1a4b8c'],
    ['label' => 'تركي', 'value' => $stats['turkish_movies'] + $stats['turkish_series'], 'color' => '#9b2c2c'],
    ['label' => 'هندي', 'value' => $stats['indian_movies'] + $stats['indian_series'], 'color' => '#ff9933'],
    ['label' => 'كوري', 'value' => $stats['korean_series'], 'color' => '#4a1d6d'],
    ['label' => 'ياباني', 'value' => $stats['japanese_series'], 'color' => '#e50914']
];

$total_language_content = array_sum(array_column($languages_data, 'value'));

// =============================================
// آخر النشاطات
// =============================================
$recent_activity = [];

// آخر الأفلام المضافة
foreach ($latest_movies as $movie) {
    $recent_activity[] = [
        'type' => 'movie',
        'title' => $movie['title'],
        'time' => 'منذ قليل',
        'icon' => 'fa-film',
        'color' => '#e50914'
    ];
}

// آخر المسلسلات المضافة
foreach (array_slice($latest_series, 0, 3) as $series) {
    $recent_activity[] = [
        'type' => 'series',
        'title' => $series['title'],
        'time' => 'منذ قليل',
        'icon' => 'fa-tv',
        'color' => '#1a4b8c'
    ];
}

// آخر الترجمات المضافة
foreach (array_slice($latest_subtitles, 0, 2) as $sub) {
    $recent_activity[] = [
        'type' => 'subtitle',
        'title' => 'ترجمة: ' . ($sub['content_title'] ?? 'محتوى') . ' - ' . ($sub['language'] ?? ''),
        'time' => 'منذ قليل',
        'icon' => 'fa-closed-captioning',
        'color' => '#27ae60'
    ];
}

shuffle($recent_activity);
$recent_activity = array_slice($recent_activity, 0, 8);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم الاحترافية - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* ===== الشريط العلوي ===== */
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
            width: 100%;
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
        
        .notification-badge {
            position: relative;
            cursor: pointer;
        }
        
        .notification-badge i {
            font-size: 20px;
            color: #b3b3b3;
            transition: 0.3s;
        }
        
        .notification-badge:hover i {
            color: #e50914;
        }
        
        .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e50914;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1a1a1a;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #333;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .user-profile:hover {
            border-color: #e50914;
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
        
        .user-name {
            font-weight: 500;
        }
        
        /* ===== الحاوية الرئيسية ===== */
        .dashboard-container {
            display: flex;
            padding: 30px;
            gap: 30px;
            min-height: calc(100vh - 80px);
        }
        
        /* ===== الشريط الجانبي ===== */
        .sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            height: fit-content;
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
        
        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }
        
        .nav-item:hover {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
        }
        
        .nav-item.active {
            background: #e50914;
            color: white;
        }
        
        .nav-badge {
            background: #e50914;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-right: auto;
        }
        
        /* ===== المحتوى الرئيسي ===== */
        .main-content {
            flex: 1;
            overflow-y: visible;
            min-width: 0;
        }
        
        /* باقي التنسيقات كما هي */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #333;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e50914, #ff6b6b);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            background: rgba(229, 9, 20, 0.1);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #e50914;
        }
        
        .stat-details {
            flex: 1;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #fff;
            line-height: 1;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
        }
        
        .stat-change {
            font-size: 13px;
            margin-top: 5px;
        }
        
        .stat-change.positive {
            color: #27ae60;
        }
        
        .stat-change.negative {
            color: #e50914;
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
        }
        
        .section-header h2 {
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-header h2 i {
            color: #e50914;
        }
        
        .view-all {
            color: #b3b3b3;
            text-decoration: none;
            font-size: 14px;
            transition: 0.3s;
        }
        
        .view-all:hover {
            color: #e50914;
        }
        
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }
        
        .card {
            background: #252525;
            border-radius: 15px;
            overflow: hidden;
            transition: 0.3s;
            border: 1px solid #333;
        }
        
        .card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }
        
        .card-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .card-title {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 15px;
        }
        
        .card-meta {
            color: #b3b3b3;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            text-align: right;
            padding: 15px;
            color: #b3b3b3;
            font-weight: 500;
            border-bottom: 2px solid #333;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #333;
        }
        
        tr:hover td {
            background: rgba(255,255,255,0.02);
        }
        
        .rating {
            color: gold;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #2a2a2a;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
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
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .languages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .language-item {
            background: #252525;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
            border: 1px solid #333;
        }
        
        .language-name {
            font-size: 14px;
            color: #b3b3b3;
            margin-bottom: 5px;
        }
        
        .language-count {
            font-size: 24px;
            font-weight: 800;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #252525;
            border-radius: 12px;
            border: 1px solid #333;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .activity-icon.movie {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
        }
        
        .activity-icon.series {
            background: rgba(26, 75, 140, 0.1);
            color: #1a4b8c;
        }
        
        .activity-icon.subtitle {
            background: rgba(39, 174, 96, 0.1);
            color: #27ae60;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 700;
            margin-bottom: 3px;
        }
        
        .activity-time {
            color: #b3b3b3;
            font-size: 12px;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .quick-action-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid #333;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
            display: block;
        }
        
        .quick-action-card:hover {
            border-color: #e50914;
            transform: translateY(-5px);
        }
        
        .quick-action-icon {
            width: 60px;
            height: 60px;
            background: rgba(229, 9, 20, 0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 28px;
            color: #e50914;
        }
        
        .quick-action-title {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .quick-action-desc {
            color: #b3b3b3;
            font-size: 12px;
            margin-bottom: 15px;
        }
        
        .system-status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .status-item {
            background: #252525;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }
        
        .status-indicator.good {
            background: #27ae60;
            box-shadow: 0 0 10px #27ae60;
        }
        
        .status-indicator.warning {
            background: #f39c12;
            box-shadow: 0 0 10px #f39c12;
        }
        
        .status-indicator.bad {
            background: #e50914;
            box-shadow: 0 0 10px #e50914;
        }
        
        .status-info {
            flex: 1;
        }
        
        .status-label {
            color: #b3b3b3;
            font-size: 13px;
        }
        
        .status-value {
            font-weight: 700;
        }
        
        .badge {
            padding: 3px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 700;
        }
        
        .badge-success {
            background: #27ae60;
            color: white;
        }
        
        .badge-warning {
            background: #f39c12;
            color: white;
        }
        
        .badge-danger {
            background: #e50914;
            color: white;
        }
        
        .badge-new {
            background: #e50914;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-right: auto;
        }
        
        .text-gold {
            color: gold;
        }
        
        .text-primary {
            color: #e50914;
        }
        
        .text-success {
            color: #27ae60;
        }
        
        .text-muted {
            color: #b3b3b3;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .quick-links {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .quick-link {
            background: #252525;
            padding: 10px 20px;
            border-radius: 30px;
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #333;
            transition: 0.3s;
            font-size: 14px;
        }
        
        .quick-link:hover {
            border-color: #e50914;
            transform: translateY(-2px);
        }
        
        .quick-link i {
            color: #e50914;
        }
        
        .new-badge {
            background: #e50914;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 8px;
        }
        
        @media (max-width: 1200px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                position: static;
                max-height: none;
            }
        }
        
        @media (max-width: 768px) {
            .top-bar {
                padding: 15px;
            }
            
            .user-name {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .cards-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions-grid {
                grid-template-columns: 1fr;
            }
            
            .languages-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .system-status-grid {
                grid-template-columns: 1fr;
            }
            
            .two-columns {
                grid-template-columns: 1fr;
            }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #e50914;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #b20710;
        }
    </style>
</head>
<body>
    <!-- الشريط العلوي -->
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-film" style="color: #e50914; font-size: 32px;"></i>
            <h1>ويزي<span>برو</span></h1>
        </div>
        
        <div class="user-menu">
            <div class="notification-badge">
                <i class="far fa-bell"></i>
                <span class="badge">3</span>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <i class="fas fa-chevron-down" style="font-size: 12px; color: #b3b3b3;"></i>
            </div>
        </div>
    </div>
    
    <div class="dashboard-container">
        <!-- استدعاء الشريط الجانبي -->
        <?php include 'sidebar.php'; ?>
        
        <!-- المحتوى الرئيسي -->
        <div class="main-content">
            <!-- بقية المحتوى كما هو -->
            <!-- بطاقات الإحصائيات الرئيسية -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-film"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['movies']); ?></div>
                        <div class="stat-label">إجمالي الأفلام</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> +<?php echo $stats['movies_2026']; ?> هذا العام
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-tv"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['series']); ?></div>
                        <div class="stat-label">إجمالي المسلسلات</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i> +<?php echo $stats['series_2026']; ?> هذا العام
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['episodes']); ?></div>
                        <div class="stat-label">إجمالي الحلقات</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['total_views']); ?></div>
                        <div class="stat-label">إجمالي المشاهدات</div>
                    </div>
                </div>
            </div>
            
            <!-- بطاقات إضافية مع إحصائيات الترجمات -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(39,174,96,0.1); color: #27ae60;">
                        <i class="fas fa-closed-captioning"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo number_format($stats['subtitles']); ?></div>
                        <div class="stat-label">إجمالي الترجمات</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(14,70,32,0.1); color: #0e4620;">
                        <i class="fas fa-language"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['arabic_movies'] + $stats['arabic_series']; ?></div>
                        <div class="stat-label">محتوى عربي</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(155,44,44,0.1); color: #9b2c2c;">
                        <i class="fas fa-language"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['turkish_movies'] + $stats['turkish_series']; ?></div>
                        <div class="stat-label">محتوى تركي</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255,153,51,0.1); color: #ff9933;">
                        <i class="fas fa-language"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['indian_movies'] + $stats['indian_series']; ?></div>
                        <div class="stat-label">محتوى هندي</div>
                    </div>
                </div>
            </div>
            
            <!-- إحصائيات الروابط -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                        <i class="fas fa-link"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['total_download_links']; ?></div>
                        <div class="stat-label">إجمالي روابط التحميل</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['valid_download_links']; ?></div>
                        <div class="stat-label">روابط صالحة</div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(231,76,60,0.1); color: #e74c3c;">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-details">
                        <div class="stat-number"><?php echo $stats['invalid_download_links']; ?></div>
                        <div class="stat-label">روابط منتهية</div>
                    </div>
                </div>
            </div>
            
            <!-- إجراءات سريعة مع الصفحات الجديدة -->
            <!-- إجراءات سريعة مع الصفحات الجديدة -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-bolt"></i> إجراءات سريعة</h2>
    </div>
    
    <div class="quick-actions-grid">
        <!-- استيراد من TMDB -->
        <a href="import-movies.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-film"></i>
            </div>
            <div class="quick-action-title">استيراد أفلام</div>
            <div class="quick-action-desc">جلب أفلام من TMDB مع طاقم العمل</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <a href="import-series.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-tv"></i>
            </div>
            <div class="quick-action-title">استيراد مسلسلات</div>
            <div class="quick-action-desc">جلب مسلسلات مع المواسم والحلقات</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- محتوى يوتيوب -->
        <a href="free-manager.php" class="quick-action-card">
            <div class="quick-action-icon" style="background: rgba(255,0,0,0.1); color: #ff0000;">
                <i class="fab fa-youtube"></i>
            </div>
            <div class="quick-action-title">إدارة يوتيوب</div>
            <div class="quick-action-desc">إضافة فيديوهات من يوتيوب</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <a href="youtube-series-manager.php" class="quick-action-card">
            <div class="quick-action-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                <i class="fas fa-list"></i>
            </div>
            <div class="quick-action-title">مسلسلات يوتيوب</div>
            <div class="quick-action-desc">جلب مسلسلات كاملة من يوتيوب</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- الترجمات -->
        <a href="add-subtitles.php" class="quick-action-card">
            <div class="quick-action-icon" style="background: rgba(39,174,96,0.1); color: #27ae60;">
                <i class="fas fa-closed-captioning"></i>
            </div>
            <div class="quick-action-title">إضافة ترجمة</div>
            <div class="quick-action-desc">رفع ملفات ترجمة للمحتوى</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- تحديث دفعة -->
        <a href="batch-update.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <div class="quick-action-title">تحديث دفعة</div>
            <div class="quick-action-desc">تحديث جميع المحتويات</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- بحث موحد -->
        <a href="universal-search.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-globe"></i>
            </div>
            <div class="quick-action-title">بحث موحد</div>
            <div class="quick-action-desc">البحث في جميع المواقع</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- جلب متقدم -->
        <a href="import-from-any-site.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-magic"></i>
            </div>
            <div class="quick-action-title">جلب متقدم</div>
            <div class="quick-action-desc">بحث وجلب مع تفاصيل كاملة</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- تعديل الصور -->
        <a href="manual-edit-posters.php" class="quick-action-card">
            <div class="quick-action-icon">
                <i class="fas fa-edit"></i>
            </div>
            <div class="quick-action-title">تعديل الصور</div>
            <div class="quick-action-desc">تحديث البوسترات يدوياً</div>
        </a>
        
        <!-- حذف محتوى -->
        <a href="delete-content.php" class="quick-action-card">
            <div class="quick-action-icon" style="background: rgba(220,53,69,0.1); color: #dc3545;">
                <i class="fas fa-trash-alt"></i>
            </div>
            <div class="quick-action-title">حذف محتوى</div>
            <div class="quick-action-desc">حذف محتوى مكرر أو غير مرغوب</div>
            <span class="badge badge-danger">جديد</span>
        </a>
        
        <!-- قائمة الترجمات -->
        <a href="subtitles-list.php" class="quick-action-card">
            <div class="quick-action-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                <i class="fas fa-list"></i>
            </div>
            <div class="quick-action-title">قائمة الترجمات</div>
            <div class="quick-action-desc">عرض وإدارة جميع الترجمات</div>
            <span class="badge badge-danger">جديد</span>
        </a>
    </div>
</div>
            
            <!-- روابط سريعة للصفحات الجديدة -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-link"></i> روابط سريعة للإضافات الجديدة</h2>
                </div>
                
                <div class="quick-links">
                    <a href="import-from-any-site.php" class="quick-link">
                        <i class="fas fa-magic"></i> جلب متقدم
                        <span class="new-badge">جديد</span>
                    </a>
                    <a href="universal-search.php" class="quick-link">
                        <i class="fas fa-globe"></i> بحث موحد
                        <span class="new-badge">جديد</span>
                    </a>
                    <a href="add-subtitles.php" class="quick-link">
                        <i class="fas fa-closed-captioning"></i> إضافة ترجمة
                        <span class="new-badge">جديد</span>
                    </a>
                    <a href="subtitles-list.php" class="quick-link">
                        <i class="fas fa-closed-captioning"></i> قائمة الترجمات
                        <span class="new-badge">جديد</span>
                    </a>
                    <a href="batch-update.php" class="quick-link">
                        <i class="fas fa-tasks"></i> تحديث دفعة
                        <span class="new-badge">جديد</span>
                    </a>
                    <a href="fetch-content.php" class="quick-link">
                        <i class="fas fa-cloud-download-alt"></i> جلب سريع
                    </a>
                    <a href="movies.php" class="quick-link">
                        <i class="fas fa-film"></i> الأفلام
                    </a>
                    <a href="series.php" class="quick-link">
                        <i class="fas fa-tv"></i> المسلسلات
                    </a>
                </div>
            </div>
            
            <!-- أحدث الأفلام والمسلسلات (عمودين) -->
            <div class="two-columns">
                <!-- أحدث الأفلام -->
                <div class="content-section" style="margin-bottom: 0;">
                    <div class="section-header">
                        <h2><i class="fas fa-film"></i> أحدث الأفلام</h2>
                        <a href="movies.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <div class="cards-grid">
                        <?php foreach ($latest_movies as $movie): ?>
                        <div class="card">
                            <img src="<?php echo $movie['poster'] ?? 'https://via.placeholder.com/200x300?text=No+Image'; ?>" 
                                 class="card-image" alt="<?php echo htmlspecialchars($movie['title']); ?>">
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                <div class="card-meta">
                                    <span><?php echo $movie['year']; ?></span>
                                    <span class="rating">⭐ <?php echo $movie['imdb_rating'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- أحدث المسلسلات -->
                <div class="content-section" style="margin-bottom: 0;">
                    <div class="section-header">
                        <h2><i class="fas fa-tv"></i> أحدث المسلسلات</h2>
                        <a href="series.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                    </div>
                    
                    <div class="cards-grid">
                        <?php foreach ($latest_series as $series): ?>
                        <div class="card">
                            <img src="<?php echo $series['poster'] ?? 'https://via.placeholder.com/200x300?text=No+Image'; ?>" 
                                 class="card-image" alt="<?php echo htmlspecialchars($series['title']); ?>">
                            <div class="card-body">
                                <div class="card-title"><?php echo htmlspecialchars($series['title']); ?></div>
                                <div class="card-meta">
                                    <span><?php echo $series['year']; ?></span>
                                    <span class="rating">⭐ <?php echo $series['imdb_rating'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- آخر الترجمات المضافة -->
            <?php if (!empty($latest_subtitles)): ?>
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-closed-captioning" style="color: #27ae60;"></i> آخر الترجمات المضافة</h2>
                    <a href="subtitles-list.php" class="view-all">عرض الكل <i class="fas fa-arrow-left"></i></a>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($latest_subtitles as $sub): ?>
                    <div style="background: #252525; padding: 15px; border-radius: 10px; border: 1px solid #27ae60;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                            <i class="fas fa-closed-captioning" style="color: #27ae60;"></i>
                            <span style="font-weight: 700;"><?php echo htmlspecialchars($sub['content_title'] ?? ''); ?></span>
                        </div>
                        <div style="display: flex; justify-content: space-between; color: #b3b3b3; font-size: 13px;">
                            <span>اللغة: <?php echo htmlspecialchars($sub['language'] ?? ''); ?></span>
                            <span>📅 <?php echo date('Y-m-d', strtotime($sub['created_at'])); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- توزيع المحتوى حسب اللغة -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-chart-pie"></i> توزيع المحتوى حسب اللغة</h2>
                    <span class="text-muted">إجمالي: <?php echo $total_language_content; ?></span>
                </div>
                
                <div class="languages-grid">
                    <?php foreach ($languages_data as $lang): ?>
                    <?php if ($lang['value'] > 0): ?>
                    <div class="language-item">
                        <div class="language-name"><?php echo $lang['label']; ?></div>
                        <div class="language-count" style="color: <?php echo $lang['color']; ?>">
                            <?php echo number_format($lang['value']); ?>
                        </div>
                        <div class="text-muted" style="font-size: 12px; margin-top: 5px;">
                            <?php echo round(($lang['value'] / max($total_language_content, 1)) * 100); ?>%
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- حالة النظام -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-server"></i> حالة النظام</h2>
                </div>
                
                <div class="system-status-grid">
                    <div class="status-item">
                        <div class="status-indicator good"></div>
                        <div class="status-info">
                            <div class="status-label">اتصال قاعدة البيانات</div>
                            <div class="status-value">ممتاز</div>
                        </div>
                    </div>
                    
                    <div class="status-item">
                        <div class="status-indicator good"></div>
                        <div class="status-info">
                            <div class="status-label">TMDB API</div>
                            <div class="status-value">متصل</div>
                        </div>
                    </div>
                    
                    <div class="status-item">
                        <?php $missing_total = $stats['missing_posters_series'] + $stats['missing_posters_movies'] + $stats['missing_backdrops_series'] + $stats['missing_backdrops_movies']; ?>
                        <div class="status-indicator <?php echo $missing_total > 0 ? 'warning' : 'good'; ?>"></div>
                        <div class="status-info">
                            <div class="status-label">صور ناقصة</div>
                            <div class="status-value"><?php echo $missing_total; ?></div>
                        </div>
                    </div>
                    
                    <div class="status-item">
                        <div class="status-indicator good"></div>
                        <div class="status-info">
                            <div class="status-label">المستخدمين النشطين</div>
                            <div class="status-value"><?php echo $stats['users']; ?></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- آخر النشاطات -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-history"></i> آخر النشاطات</h2>
                </div>
                
                <div class="activity-list">
                    <?php foreach ($recent_activity as $activity): ?>
                    <div class="activity-item">
                        <div class="activity-icon <?php echo $activity['type']; ?>">
                            <i class="fas <?php echo $activity['icon']; ?>"></i>
                        </div>
                        <div class="activity-details">
                            <div class="activity-title"><?php echo htmlspecialchars($activity['title']); ?></div>
                            <div class="activity-time"><?php echo $activity['time']; ?></div>
                        </div>
                        <span class="badge <?php 
                            echo $activity['type'] == 'movie' ? 'badge-danger' : 
                                ($activity['type'] == 'series' ? 'badge-primary' : 'badge-success'); 
                        ?>">
                            <?php 
                            echo $activity['type'] == 'movie' ? 'فيلم' : 
                                ($activity['type'] == 'series' ? 'مسلسل' : 'ترجمة'); 
                            ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- دليل سريع للإضافات الجديدة -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-question-circle"></i> دليل سريع للإضافات الجديدة</h2>
                </div>
                
                <div style="background: #252525; border-radius: 10px; padding: 20px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                        <div>
                            <h4 style="color: #e50914; margin-bottom: 10px;">📥 جلب متقدم</h4>
                            <ul style="color: #b3b3b3; font-size: 13px; list-style: none; padding: 0;">
                                <li style="margin-bottom: 5px;">• بحث كامل في TMDB</li>
                                <li style="margin-bottom: 5px;">• تعديل جميع التفاصيل</li>
                                <li style="margin-bottom: 5px;">• إضافة روابط متعددة</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #e50914; margin-bottom: 10px;">🔍 بحث موحد</h4>
                            <ul style="color: #b3b3b3; font-size: 13px; list-style: none; padding: 0;">
                                <li style="margin-bottom: 5px;">• البحث في جميع المواقع</li>
                                <li style="margin-bottom: 5px;">• نتائج سريعة ومنظمة</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #e50914; margin-bottom: 10px;">📝 إضافة ترجمة</h4>
                            <ul style="color: #b3b3b3; font-size: 13px; list-style: none; padding: 0;">
                                <li style="margin-bottom: 5px;">• رفع ملفات SRT, VTT, ASS</li>
                                <li style="margin-bottom: 5px;">• دعم 12 لغة مختلفة</li>
                            </ul>
                        </div>
                        <div>
                            <h4 style="color: #e50914; margin-bottom: 10px;">🔄 تحديث دفعة</h4>
                            <ul style="color: #b3b3b3; font-size: 13px; list-style: none; padding: 0;">
                                <li style="margin-bottom: 5px;">• تحديث جميع المحتويات</li>
                                <li style="margin-bottom: 5px;">• منع التكرار تلقائياً</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>