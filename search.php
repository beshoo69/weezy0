<?php
// search.php - صفحة نتائج البحث
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/database.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$type = isset($_GET['type']) ? $_GET['type'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$results = [];
$total_results = 0;
$total_pages = 1;

if (!empty($query)) {
    // =============================================
    // البحث في الأفلام
    // =============================================
    if ($type == 'all' || $type == 'movies') {
        // عدّ الأفلام المطابقة
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM movies 
            WHERE title LIKE ? OR title_en LIKE ? OR description LIKE ?
        ");
        $search_term = "%{$query}%";
        $stmt->execute([$search_term, $search_term, $search_term]);
        $movie_count = $stmt->fetchColumn();
        
        // جلب الأفلام المطابقة مع Pagination - ✅ استخدام CAST للأرقام
        $sql = "
            SELECT *, 'movie' as content_type FROM movies 
            WHERE title LIKE ? OR title_en LIKE ? OR description LIKE ?
            ORDER BY 
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN title_en LIKE ? THEN 2
                    ELSE 3
                END,
                views DESC,
                id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $search_term,                    // 1. title LIKE
            $search_term,                    // 2. title_en LIKE
            $search_term,                    // 3. description LIKE
            $query . '%',                    // 4. title LIKE exact
            $query . '%'                     // 5. title_en LIKE exact
        ]);
        
        $movies = $stmt->fetchAll();
        
        if ($type == 'movies') {
            $results = $movies;
            $total_results = $movie_count;
        }
    }
    
    // =============================================
    // البحث في المسلسلات
    // =============================================
    if ($type == 'all' || $type == 'series') {
        // عدّ المسلسلات المطابقة
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM series 
            WHERE title LIKE ? OR title_en LIKE ? OR description LIKE ?
        ");
        $search_term = "%{$query}%";
        $stmt->execute([$search_term, $search_term, $search_term]);
        $series_count = $stmt->fetchColumn();
        
        // جلب المسلسلات المطابقة مع Pagination - ✅ استخدام CAST للأرقام
        $sql = "
            SELECT *, 'series' as content_type FROM series 
            WHERE title LIKE ? OR title_en LIKE ? OR description LIKE ?
            ORDER BY 
                CASE 
                    WHEN title LIKE ? THEN 1
                    WHEN title_en LIKE ? THEN 2
                    ELSE 3
                END,
                views DESC,
                id DESC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $search_term,                    // 1. title LIKE
            $search_term,                    // 2. title_en LIKE
            $search_term,                    // 3. description LIKE
            $query . '%',                    // 4. title LIKE exact
            $query . '%'                     // 5. title_en LIKE exact
        ]);
        
        $series = $stmt->fetchAll();
        
        if ($type == 'series') {
            $results = $series;
            $total_results = $series_count;
        }
    }
    
    // =============================================
    // دمج النتائج (إذا كان البحث شامل)
    // =============================================
    if ($type == 'all') {
        $results = array_merge($movies ?? [], $series ?? []);
        $total_results = ($movie_count ?? 0) + ($series_count ?? 0);
        
        // ترتيب النتائج حسب الصلة (التطابق التام أولاً)
        usort($results, function($a, $b) use ($query) {
            $a_title = strtolower($a['title'] ?? '');
            $b_title = strtolower($b['title'] ?? '');
            $query_low = strtolower($query);
            
            if ($a_title == $query_low && $b_title != $query_low) return -1;
            if ($b_title == $query_low && $a_title != $query_low) return 1;
            
            return ($b['views'] ?? 0) <=> ($a['views'] ?? 0);
        });
        
        // تقليم النتائج حسب الصفحة (لأننا دمجنا يدوياً)
        $results = array_slice($results, $offset, $limit);
    }
    
    $total_pages = ceil($total_results / $limit);
}

// =============================================
// اقتراحات البحث (للاستخدام في AJAX)
// =============================================
if (isset($_GET['ajax']) && !empty($query)) {
    header('Content-Type: application/json');
    
    $suggestions = [];
    
    // اقتراحات من الأفلام
    $stmt = $pdo->prepare("
        SELECT id, title, 'movie' as type, poster, year 
        FROM movies 
        WHERE title LIKE ? 
        LIMIT 5
    ");
    $stmt->execute(["%{$query}%"]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll());
    
    // اقتراحات من المسلسلات
    $stmt = $pdo->prepare("
        SELECT id, title, 'series' as type, poster, year 
        FROM series 
        WHERE title LIKE ? 
        LIMIT 5
    ");
    $stmt->execute(["%{$query}%"]);
    $suggestions = array_merge($suggestions, $stmt->fetchAll());
    
    echo json_encode($suggestions);
    exit;
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بحث: <?php echo htmlspecialchars($query); ?> - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
        }
        
        .header {
            background: #0a0a0a;
            padding: 15px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
        }
        
        .logo span {
            color: #fff;
        }
        
        .nav-list {
            display: flex;
            gap: 25px;
            list-style: none;
        }
        
        .nav-list a {
            color: #fff;
            text-decoration: none;
            transition: 0.3s;
        }
        
        .nav-list a:hover,
        .nav-list a.active {
            color: #e50914;
        }
        
        .search-header {
            background: #1a1a1a;
            padding: 30px 40px;
            margin-bottom: 30px;
        }
        
        .search-container {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .search-title {
            font-size: 28px;
            font-weight: 800;
            color: #e50914;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-box {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            padding: 15px 20px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 50px;
            color: #fff;
            font-size: 16px;
            transition: 0.3s;
        }
        
        .search-input:focus {
            border-color: #e50914;
            outline: none;
        }
        
        .search-btn {
            padding: 0 30px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            background: #b20710;
            transform: scale(1.05);
        }
        
        .search-filters {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 10px 25px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 50px;
            color: #b3b3b3;
            text-decoration: none;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-btn:hover {
            border-color: #e50914;
            color: white;
        }
        
        .filter-btn.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }
        
        .search-stats {
            padding: 20px 40px;
            color: #b3b3b3;
            font-size: 16px;
            border-bottom: 1px solid #333;
        }
        
        .search-stats strong {
            color: #e50914;
            font-size: 20px;
        }
        
        .results-container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 40px;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 30px;
        }
        
        .result-card {
            background: #1a1a1a;
            border-radius: 15px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
            color: #fff;
            border: 1px solid #333;
            position: relative;
        }
        
        .result-card:hover {
            transform: translateY(-10px);
            border-color: #e50914;
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }
        
        .result-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #e50914, #ff6b6b);
            opacity: 0;
            transition: 0.3s;
        }
        
        .result-card:hover::before {
            opacity: 1;
        }
        
        .result-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        
        .result-info {
            padding: 20px;
        }
        
        .result-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .result-meta {
            display: flex;
            justify-content: space-between;
            color: #b3b3b3;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .result-type {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(229,9,20,0.9);
            color: white;
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 700;
            z-index: 10;
        }
        
        .result-rating {
            color: gold;
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .no-results {
            text-align: center;
            padding: 100px 0;
            background: #1a1a1a;
            border-radius: 20px;
            border: 1px solid #333;
        }
        
        .no-results i {
            font-size: 60px;
            color: #e50914;
            margin-bottom: 20px;
        }
        
        .no-results h2 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .no-results p {
            color: #b3b3b3;
            margin-bottom: 30px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 50px;
        }
        
        .page-link {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a1a1a;
            color: #fff;
            text-decoration: none;
            border-radius: 50%;
            border: 1px solid #333;
            transition: 0.3s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: #e50914;
            border-color: #e50914;
        }
        
        .footer {
            background: #0a0a0a;
            padding: 40px;
            text-align: center;
            color: #b3b3b3;
            margin-top: 60px;
        }
        
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
            }
            .nav-list {
                display: none;
            }
            .search-box {
                flex-direction: column;
            }
            .results-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">
            <a href="index.php" style="text-decoration: none;">
                <h1>ويزي<span>برو</span></h1>
            </a>
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

    <div class="search-header">
        <div class="search-container">
            <h1 class="search-title">
                <i class="fas fa-search"></i>
                ابحث عن فيلم أو مسلسل
            </h1>
            
            <form action="search.php" method="GET" class="search-box">
                <input type="text" name="q" class="search-input" 
                       placeholder="اكتب اسم الفيلم أو المسلسل..." 
                       value="<?php echo htmlspecialchars($query); ?>" autofocus>
                <button type="submit" class="search-btn">
                    <i class="fas fa-search"></i>
                    بحث
                </button>
            </form>
            
            <div class="search-filters">
                <a href="?q=<?php echo urlencode($query); ?>&type=all" 
                   class="filter-btn <?php echo $type == 'all' ? 'active' : ''; ?>">
                    <i class="fas fa-globe"></i> الكل
                </a>
                <a href="?q=<?php echo urlencode($query); ?>&type=movies" 
                   class="filter-btn <?php echo $type == 'movies' ? 'active' : ''; ?>">
                    <i class="fas fa-film"></i> أفلام
                </a>
                <a href="?q=<?php echo urlencode($query); ?>&type=series" 
                   class="filter-btn <?php echo $type == 'series' ? 'active' : ''; ?>">
                    <i class="fas fa-tv"></i> مسلسلات
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($query)): ?>
    <div class="search-stats">
        <i class="fas fa-search"></i> نتائج البحث عن "<strong><?php echo htmlspecialchars($query); ?></strong>" 
        - تم العثور على <strong><?php echo number_format($total_results); ?></strong> نتيجة
    </div>
    <?php endif; ?>

    <div class="results-container">
        <?php if (empty($query)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h2>ابدأ البحث</h2>
                <p>اكتب اسم الفيلم أو المسلسل الذي تبحث عنه</p>
            </div>
        <?php elseif (empty($results)): ?>
            <div class="no-results">
                <i class="fas fa-film"></i>
                <h2>لا توجد نتائج</h2>
                <p>لم نتمكن من العثور على أي نتائج تطابق "<?php echo htmlspecialchars($query); ?>"</p>
                <p style="font-size: 14px;">جرب كلمات بحث أخرى أو تحقق من الإملاء</p>
            </div>
        <?php else: ?>
            <div class="results-grid">
                <?php foreach ($results as $item): ?>
                <a href="<?php echo $item['content_type'] == 'movie' ? 'movie.php?id=' . $item['id'] : 'series.php?id=' . $item['id']; ?>" 
                   class="result-card">
                    <div class="result-type">
                        <?php echo $item['content_type'] == 'movie' ? 'فيلم' : 'مسلسل'; ?>
                    </div>
                    <img src="<?php echo $item['poster'] ?? 'https://via.placeholder.com/300x450/1a1a1a/e50914?text=' . urlencode($item['title']); ?>" 
                         class="result-poster" alt="<?php echo $item['title']; ?>">
                    <div class="result-info">
                        <div class="result-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="result-meta">
                            <span><?php echo $item['year']; ?></span>
                            <?php if (!empty($item['imdb_rating'])): ?>
                            <span class="result-rating">
                                <i class="fas fa-star"></i> <?php echo $item['imdb_rating']; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if ($item['content_type'] == 'series' && !empty($item['seasons'])): ?>
                        <div style="color: #e50914; font-size: 13px; margin-top: 5px;">
                            <i class="fas fa-layer-group"></i> <?php echo $item['seasons']; ?> مواسم
                        </div>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                <a href="?q=<?php echo urlencode($query); ?>&type=<?php echo $type; ?>&page=<?php echo $page - 1; ?>" 
                   class="page-link">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end = min($total_pages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                <a href="?q=<?php echo urlencode($query); ?>&type=<?php echo $type; ?>&page=<?php echo $i; ?>" 
                   class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="?q=<?php echo urlencode($query); ?>&type=<?php echo $type; ?>&page=<?php echo $page + 1; ?>" 
                   class="page-link">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>