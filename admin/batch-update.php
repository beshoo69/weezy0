<?php
// admin/batch-update.php - تحديث جميع الأفلام والمسلسلات دفعة واحدة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// إعدادات التحديث
$batch_size = 20; // عدد العناصر في كل دفعة
$update_movies = isset($_GET['movies']) ? true : false;
$update_series = isset($_GET['series']) ? true : false;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $batch_size;

// نتائج التحديث
$results = [
    'movies' => ['updated' => 0, 'failed' => 0, 'total' => 0, 'items' => []],
    'series' => ['updated' => 0, 'failed' => 0, 'total' => 0, 'items' => []]
];

// جلب إجمالي عدد الأفلام
if ($update_movies) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM movies");
    $results['movies']['total'] = $stmt->fetchColumn();
}

// جلب إجمالي عدد المسلسلات
if ($update_series) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM series");
    $results['series']['total'] = $stmt->fetchColumn();
}

// =============================================
// تحديث الأفلام
// =============================================
if ($update_movies && isset($_GET['run']) && $_GET['run'] == 'true') {
    
    // جلب دفعة من الأفلام - تم تعديل طريقة الاستعلام
    $sql = "SELECT * FROM movies ORDER BY id ASC LIMIT $offset, $batch_size";
    $stmt = $pdo->query($sql);
    $movies = $stmt->fetchAll();
    
    foreach ($movies as $movie) {
        $movie_id = $movie['id'];
        $title = $movie['title'];
        $year = $movie['year'] ?? null;
        $tmdb_id = $movie['tmdb_id'] ?? $movie['tmbd_id'] ?? null;
        
        $movie_result = [
            'id' => $movie_id,
            'title' => $title,
            'status' => 'pending',
            'message' => '',
            'tmdb_id' => $tmdb_id
        ];
        
        try {
            // إذا لم يكن TMDB ID موجوداً، نحاول البحث عنه
            if (!$tmdb_id) {
                $search_url = "https://api.themoviedb.org/3/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($title);
                if ($year) {
                    $search_url .= "&year=" . $year;
                }
                
                $search_result = tmdb_request($search_url);
                
                if ($search_result && !empty($search_result['results'][0]['id'])) {
                    $tmdb_id = $search_result['results'][0]['id'];
                    
                    // حفظ TMDB ID في قاعدة البيانات
                    $update_stmt = $pdo->prepare("UPDATE movies SET tmdb_id = ? WHERE id = ?");
                    $update_stmt->execute([$tmdb_id, $movie_id]);
                    
                    $movie_result['tmdb_id'] = $tmdb_id;
                }
            }
            
            // إذا لدينا TMDB ID، نقوم بإنشاء روابط المشاهدة
            if ($tmdb_id) {
                // روابط المشاهدة (سنقوم بحفظها في حقل watch_url)
                $watch_url = 'https://vidsrc.to/embed/movie/' . $tmdb_id;
                
                // حفظ الرابط في قاعدة البيانات
                $update_stmt = $pdo->prepare("UPDATE movies SET watch_url = ? WHERE id = ?");
                $update_stmt->execute([$watch_url, $movie_id]);
                
                $movie_result['status'] = 'success';
                $movie_result['message'] = 'تم التحديث بنجاح';
                $results['movies']['updated']++;
                
            } else {
                $movie_result['status'] = 'failed';
                $movie_result['message'] = 'لم يتم العثور على TMDB ID';
                $results['movies']['failed']++;
            }
            
        } catch (Exception $e) {
            $movie_result['status'] = 'failed';
            $movie_result['message'] = 'خطأ: ' . $e->getMessage();
            $results['movies']['failed']++;
        }
        
        $results['movies']['items'][] = $movie_result;
    }
}

// =============================================
// تحديث المسلسلات
// =============================================
if ($update_series && isset($_GET['run']) && $_GET['run'] == 'true') {
    
    $sql = "SELECT * FROM series ORDER BY id ASC LIMIT $offset, $batch_size";
    $stmt = $pdo->query($sql);
    $series_list = $stmt->fetchAll();
    
    foreach ($series_list as $series) {
        $series_id = $series['id'];
        $title = $series['title'];
        $year = $series['year'] ?? null;
        $tmdb_id = $series['tmdb_id'] ?? $series['tmbd_id'] ?? null;
        
        $series_result = [
            'id' => $series_id,
            'title' => $title,
            'status' => 'pending',
            'message' => '',
            'tmdb_id' => $tmdb_id
        ];
        
        try {
            if (!$tmdb_id) {
                $search_url = "https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY . "&query=" . urlencode($title);
                if ($year) {
                    $search_url .= "&first_air_date_year=" . $year;
                }
                
                $search_result = tmdb_request($search_url);
                
                if ($search_result && !empty($search_result['results'][0]['id'])) {
                    $tmdb_id = $search_result['results'][0]['id'];
                    
                    $update_stmt = $pdo->prepare("UPDATE series SET tmdb_id = ? WHERE id = ?");
                    $update_stmt->execute([$tmdb_id, $series_id]);
                    
                    $series_result['tmdb_id'] = $tmdb_id;
                }
            }
            
            if ($tmdb_id) {
                $watch_url = 'https://vidsrc.to/embed/tv/' . $tmdb_id;
                
                $update_stmt = $pdo->prepare("UPDATE series SET watch_url = ? WHERE id = ?");
                $update_stmt->execute([$watch_url, $series_id]);
                
                $series_result['status'] = 'success';
                $series_result['message'] = 'تم التحديث بنجاح';
                $results['series']['updated']++;
                
            } else {
                $series_result['status'] = 'failed';
                $series_result['message'] = 'لم يتم العثور على TMDB ID';
                $results['series']['failed']++;
            }
            
        } catch (Exception $e) {
            $series_result['status'] = 'failed';
            $series_result['message'] = 'خطأ: ' . $e->getMessage();
            $results['series']['failed']++;
        }
        
        $results['series']['items'][] = $series_result;
    }
}

// حساب عدد الدفعات المتبقية
$total_pages_movies = ceil($results['movies']['total'] / $batch_size);
$total_pages_series = ceil($results['series']['total'] / $batch_size);
$next_page = $page + 1;
$has_next_movies = ($update_movies && $next_page <= $total_pages_movies);
$has_next_series = ($update_series && $next_page <= $total_pages_series);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث جميع المحتويات - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            color: #fff;
            min-height: 100vh;
        }
        
        .header {
            background: rgba(10,10,10,0.95);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 28px;
        }
        
        .logo span { color: #fff; }
        
        .back-link {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { color: #e50914; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
            text-align: center;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #e50914;
        }
        
        .stat-label {
            color: #b3b3b3;
            margin-top: 5px;
        }
        
        .progress-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #252525;
            border-radius: 10px;
            margin: 20px 0;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #e50914, #ff4d4d);
            border-radius: 10px;
            transition: width 0.3s;
        }
        
        .progress-info {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            margin-bottom: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 25px;
            background: #252525;
            color: #fff;
            border: 1px solid #333;
            border-radius: 30px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
        }
        
        .btn-primary {
            background: #e50914;
            border-color: #e50914;
        }
        
        .btn-primary:hover {
            background: #b20710;
            transform: scale(1.05);
        }
        
        .btn-success {
            background: #27ae60;
            border-color: #27ae60;
        }
        
        .btn-success:hover {
            background: #219a52;
            transform: scale(1.05);
        }
        
        .btn-warning {
            background: #f39c12;
            border-color: #f39c12;
            color: #000;
        }
        
        .btn-warning:hover {
            background: #e67e22;
            transform: scale(1.05);
        }
        
        .results-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #333;
            margin-top: 30px;
        }
        
        .results-list {
            max-height: 400px;
            overflow-y: auto;
            margin-top: 20px;
        }
        
        .result-item {
            background: #252525;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .result-item.success {
            border-right: 4px solid #27ae60;
        }
        
        .result-item.failed {
            border-right: 4px solid #e50914;
        }
        
        .result-item.pending {
            border-right: 4px solid #f39c12;
        }
        
        .result-title {
            font-weight: 700;
        }
        
        .result-meta {
            font-size: 12px;
            color: #b3b3b3;
            margin-top: 3px;
        }
        
        .result-status {
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .result-status.success { color: #27ae60; }
        .result-status.failed { color: #e50914; }
        .result-status.pending { color: #f39c12; }
        
        .warning-box {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid #f39c12;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: #f39c12;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-group {
                flex-direction: column;
            }
            
            .result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <a href="dashboard-pro.php" class="back-link">
            <i class="fas fa-arrow-right"></i>
            العودة للوحة التحكم
        </a>
    </div>
    
    <div class="container">
        <h1>
            <i class="fas fa-sync-alt"></i>
            تحديث جميع المحتويات دفعة واحدة
        </h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($results['movies']['total']); ?></div>
                <div class="stat-label">إجمالي الأفلام</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($results['series']['total']); ?></div>
                <div class="stat-label">إجمالي المسلسلات</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($results['movies']['updated'] + $results['series']['updated']); ?></div>
                <div class="stat-label">تم التحديث</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($results['movies']['failed'] + $results['series']['failed']); ?></div>
                <div class="stat-label">فشل التحديث</div>
            </div>
        </div>
        
        <?php if (!isset($_GET['run'])): ?>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>تنبيه:</strong> هذه العملية ستقوم بتحديث جميع الأفلام والمسلسلات في قاعدة البيانات.
            قد تستغرق بعض الوقت حسب عدد العناصر. يرجى التأكد من اتصال الإنترنت.
        </div>
        
        <div class="progress-section">
            <h2 style="color: #e50914; margin-bottom: 20px;">اختر نوع التحديث</h2>
            
            <form method="GET" class="action-buttons">
                <input type="hidden" name="page" value="1">
                <input type="hidden" name="run" value="true">
                
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" name="movies" value="1" checked>
                        تحديث الأفلام (<?php echo number_format($results['movies']['total']); ?>)
                    </label>
                    
                    <label>
                        <input type="checkbox" name="series" value="1" checked>
                        تحديث المسلسلات (<?php echo number_format($results['series']['total']); ?>)
                    </label>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play"></i>
                    بدء التحديث
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['run']) && $_GET['run'] == 'true'): ?>
        <div class="progress-section">
            <h2 style="color: #e50914; margin-bottom: 20px;">تقدم التحديث</h2>
            
            <?php if ($update_movies): ?>
            <div class="progress-info">
                <span>الأفلام: <?php echo min($offset + $batch_size, $results['movies']['total']); ?> / <?php echo $results['movies']['total']; ?></span>
                <span>الصفحة <?php echo $page; ?> / <?php echo $total_pages_movies; ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($page / max($total_pages_movies, 1)) * 100; ?>%"></div>
            </div>
            <?php endif; ?>
            
            <?php if ($update_series): ?>
            <div class="progress-info" style="margin-top: 15px;">
                <span>المسلسلات: <?php echo min($offset + $batch_size, $results['series']['total']); ?> / <?php echo $results['series']['total']; ?></span>
                <span>الصفحة <?php echo $page; ?> / <?php echo $total_pages_series; ?></span>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($page / max($total_pages_series, 1)) * 100; ?>%"></div>
            </div>
            <?php endif; ?>
            
            <div class="action-buttons" style="margin-top: 30px;">
                <?php if ($has_next_movies || $has_next_series): ?>
                <a href="?<?php 
                    echo 'run=true&page=' . $next_page;
                    if ($update_movies) echo '&movies=1';
                    if ($update_series) echo '&series=1';
                ?>" class="btn btn-success">
                    <i class="fas fa-step-forward"></i>
                    متابعة الدفعة التالية
                </a>
                <?php endif; ?>
                
                <a href="?" class="btn btn-warning">
                    <i class="fas fa-redo"></i>
                    تحديث جديد
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($results['movies']['items']) || !empty($results['series']['items'])): ?>
        <div class="results-section">
            <h2 style="color: #e50914; margin-bottom: 20px;">نتائج التحديث</h2>
            
            <?php if (!empty($results['movies']['items'])): ?>
            <h3 style="color: #fff; margin: 20px 0 10px;">الأفلام</h3>
            <div class="results-list">
                <?php foreach ($results['movies']['items'] as $item): ?>
                <div class="result-item <?php echo $item['status']; ?>">
                    <div>
                        <div class="result-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="result-meta">
                            ID: <?php echo $item['id']; ?> | 
                            TMDB: <?php echo $item['tmdb_id'] ?: 'غير موجود'; ?>
                        </div>
                    </div>
                    <div class="result-status <?php echo $item['status']; ?>">
                        <i class="fas fa-<?php 
                            echo $item['status'] == 'success' ? 'check-circle' : 
                                ($item['status'] == 'failed' ? 'exclamation-circle' : 'hourglass-half'); 
                        ?>"></i>
                        <?php echo $item['message'] ?: $item['status']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['series']['items'])): ?>
            <h3 style="color: #fff; margin: 30px 0 10px;">المسلسلات</h3>
            <div class="results-list">
                <?php foreach ($results['series']['items'] as $item): ?>
                <div class="result-item <?php echo $item['status']; ?>">
                    <div>
                        <div class="result-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="result-meta">
                            ID: <?php echo $item['id']; ?> | 
                            TMDB: <?php echo $item['tmdb_id'] ?: 'غير موجود'; ?>
                        </div>
                    </div>
                    <div class="result-status <?php echo $item['status']; ?>">
                        <i class="fas fa-<?php 
                            echo $item['status'] == 'success' ? 'check-circle' : 
                                ($item['status'] == 'failed' ? 'exclamation-circle' : 'hourglass-half'); 
                        ?>"></i>
                        <?php echo $item['message'] ?: $item['status']; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Auto-refresh للصفحة التالية (اختياري)
        <?php if (($has_next_movies || $has_next_series) && isset($_GET['run']) && $_GET['run'] == 'true'): ?>
        setTimeout(function() {
            window.location.href = '?<?php 
                echo 'run=true&page=' . $next_page;
                if ($update_movies) echo '&movies=1';
                if ($update_series) echo '&series=1';
            ?>';
        }, 3000); // الانتظار 3 ثواني قبل الانتقال للصفحة التالية
        <?php endif; ?>
    </script>
</body>
</html>