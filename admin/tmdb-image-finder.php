<?php
// admin/fast-image-finder.php - بحث سريع عن صور TMDB
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$results = [];
$selected_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$type = $_GET['type'] ?? 'tv';

// =============================================
// البحث عن مسلسل/فيلم
// =============================================
if (isset($_POST['search'])) {
    $query = trim($_POST['query']);
    $search_type = $_POST['search_type'] ?? 'tv';
    
    $url = "https://api.themoviedb.org/3/search/" . $search_type . "?api_key=" . TMDB_API_KEY . "&query=" . urlencode($query) . "&language=ar-SA";
    $data = tmdb_request($url);
    
    if ($data && isset($data['results'])) {
        foreach ($data['results'] as $item) {
            $results[] = [
                'id' => $item['id'],
                'title' => $search_type == 'tv' ? ($item['name'] ?? '') : ($item['title'] ?? ''),
                'poster' => isset($item['poster_path']) ? 'https://image.tmdb.org/t/p/w200' . $item['poster_path'] : null,
                'year' => $search_type == 'tv' 
                    ? (isset($item['first_air_date']) ? substr($item['first_air_date'], 0, 4) : 'N/A')
                    : (isset($item['release_date']) ? substr($item['release_date'], 0, 4) : 'N/A'),
                'rating' => $item['vote_average'] ?? 0
            ];
        }
    }
}

// =============================================
// جلب صور لعنصر محدد
// =============================================
$images = [];
$backdrops = [];
if ($selected_id > 0) {
    $image_type = $_GET['image_type'] ?? 'posters';
    
    $url = "https://api.themoviedb.org/3/" . $type . "/" . $selected_id . "/images?api_key=" . TMDB_API_KEY;
    $data = tmdb_request($url);
    
    if ($data) {
        // جلب البوسترز
        if (isset($data['posters'])) {
            foreach ($data['posters'] as $img) {
                $images[] = [
                    'file' => $img['file_path'],
                    'url' => 'https://image.tmdb.org/t/p/w500' . $img['file_path'],
                    'url_original' => 'https://image.tmdb.org/t/p/original' . $img['file_path'],
                    'lang' => $img['iso_639_1'] ?? 'xx',
                    'width' => $img['width'] ?? 500,
                    'height' => $img['height'] ?? 750,
                    'type' => 'poster'
                ];
            }
        }
        
        // جلب صور الخلفية
        if (isset($data['backdrops'])) {
            foreach ($data['backdrops'] as $img) {
                $backdrops[] = [
                    'file' => $img['file_path'],
                    'url' => 'https://image.tmdb.org/t/p/w780' . $img['file_path'],
                    'url_original' => 'https://image.tmdb.org/t/p/original' . $img['file_path'],
                    'lang' => $img['iso_639_1'] ?? 'xx',
                    'width' => $img['width'] ?? 1920,
                    'height' => $img['height'] ?? 1080,
                    'type' => 'backdrop'
                ];
            }
        }
    }
}

// =============================================
// روابط جاهزة لأشهر المسلسلات (بصيغة TMDB)
// =============================================
$famous_series = [
    [
        'name' => 'صراع العروش',
        'en' => 'Game of Thrones',
        'poster' => 'https://image.tmdb.org/t/p/w500/u3bZgnGQ9T01sWNhyveQz0wH0Hl.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/suopoADq0k8YZr4dQXcU6pToj6s.jpg'
    ],
    [
        'name' => 'بريكنج باد',
        'en' => 'Breaking Bad',
        'poster' => 'https://image.tmdb.org/t/p/w500/ggFHVNu6YYI5L9pCfOacjizRGt.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg'
    ],
    [
        'name' => 'تشيرنوبل',
        'en' => 'Chernobyl',
        'poster' => 'https://image.tmdb.org/t/p/w500/hlLXtK9IShSshS3Fcd7Tk7YKUjQ.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/9cTgWbT5NQgT9yNQs8N9XyqWXqW.jpg'
    ],
    [
        'name' => 'آل سوبرانو',
        'en' => 'The Sopranos',
        'poster' => 'https://image.tmdb.org/t/p/w500/rTc7ZXdroqjkKivFPvCPX0Ru7Uw.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/a49d0GZG9qb2Vb6e8YjAU7cVJW.jpg'
    ],
    [
        'name' => 'فرقة الإخوة',
        'en' => 'Band of Brothers',
        'poster' => 'https://image.tmdb.org/t/p/w500/8hL5SqPR6d9CaBUPnqYVqzP4Hg.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/9K7baRovOB1ywvSc2MQGMnQ7pKV.jpg'
    ],
    [
        'name' => 'شيرلوك',
        'en' => 'Sherlock',
        'poster' => 'https://image.tmdb.org/t/p/w500/7WTsnHkbA0FaUGp7usqZMhqmlNj.jpg',
        'backdrop' => 'https://image.tmdb.org/t/p/original/9Kw7X8fHG4ZzNf8YF9qG5X2mzOl.jpg'
    ]
];
?>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الباحث السريع عن الصور - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            background: #1a1a1a;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #333;
        }
        
        h1 {
            color: #e50914;
            font-size: 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .search-box {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }
        
        .search-title {
            color: #e50914;
            font-size: 22px;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #252525;
            padding: 10px 20px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .search-input {
            width: 100%;
            padding: 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
        }
        
        .search-input:focus {
            border-color: #e50914;
            outline: none;
        }
        
        .btn {
            padding: 12px 25px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: #e50914;
            color: white;
        }
        
        .btn-primary:hover {
            background: #b20710;
        }
        
        .btn-success {
            background: #27ae60;
            color: white;
        }
        
        .btn-success:hover {
            background: #219a52;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .result-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: 0.3s;
        }
        
        .result-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }
        
        .result-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        
        .result-info {
            padding: 15px;
        }
        
        .result-title {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .result-meta {
            color: #b3b3b3;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }
        
        .image-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
        }
        
        .image-card img {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }
        
        .image-info {
            padding: 15px;
        }
        
        .image-url {
            background: #0a0a0a;
            padding: 10px;
            border-radius: 5px;
            font-size: 11px;
            word-break: break-all;
            margin: 10px 0;
            color: #b3b3b3;
        }
        
        .copy-btn {
            width: 100%;
            padding: 8px;
            background: #2a2a2a;
            border: none;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            margin: 2px 0;
        }
        
        .image-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #333;
            padding-bottom: 15px;
        }
        
        .tab-btn {
            background: #252525;
            border: 1px solid #333;
            color: #b3b3b3;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .tab-btn:hover, .tab-btn.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }
        
        .image-type {
            font-weight: bold;
            color: #e50914;
            margin-bottom: 5px;
        }
        
        .image-size {
            font-size: 12px;
            color: #888;
            margin-bottom: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        .famous-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            margin-top: 30px;
        }
        
        .famous-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .famous-card {
            background: #252525;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #333;
        }
        
        .famous-title {
            color: #e50914;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .famous-url {
            background: #0a0a0a;
            padding: 8px;
            border-radius: 5px;
            font-size: 11px;
            word-break: break-all;
            margin: 5px 0;
        }
        
        .note {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-right: 4px solid #e50914;
        }
        
        .note i {
            color: #e50914;
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            body { padding: 15px; }
            .header { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search"></i> الباحث السريع عن الصور</h1>
            <a href="manual-edit-posters.php" class="btn btn-primary">
                <i class="fas fa-arrow-right"></i> العودة لتعديل الصور
            </a>
        </div>
        
        <div class="note">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>لا تستخدم روابط IMDB!</strong> استخدم روابط TMDB فقط. ابحث عن المسلسل الذي تريده، اختر الصورة، ثم انسخ الرابط والصقه في صفحة تعديل الصور.
        </div>
        
        <!-- البحث -->
        <div class="search-box">
            <h2 class="search-title"><i class="fas fa-search"></i> ابحث عن فيلم أو مسلسل</h2>
            
            <form method="POST">
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="search_type" value="tv" checked> 📺 مسلسل
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="search_type" value="movie"> 🎬 فيلم
                    </label>
                </div>
                
                <div class="form-group">
                    <input type="text" name="query" class="search-input" 
                           placeholder="اكتب اسم المسلسل (مثال: Game of Thrones)" 
                           value="<?php echo htmlspecialchars($_POST['query'] ?? ''); ?>">
                </div>
                
                <button type="submit" name="search" class="btn btn-primary">
                    <i class="fas fa-search"></i> بحث
                </button>
            </form>
            
            <?php if (!empty($results)): ?>
            <h3 style="color: #e50914; margin: 30px 0 20px;">نتائج البحث:</h3>
            <div class="results-grid">
                <?php foreach ($results as $item): ?>
                <div class="result-card">
                    <img src="<?php echo $item['poster'] ?? 'https://via.placeholder.com/200x300?text=No+Image'; ?>" 
                         class="result-poster">
                    <div class="result-info">
                        <div class="result-title"><?php echo $item['title']; ?></div>
                        <div class="result-meta">
                            <?php echo $item['year']; ?> • ⭐ <?php echo number_format($item['rating'], 1); ?>
                        </div>
                        <a href="?id=<?php echo $item['id']; ?>&type=<?php echo $_POST['search_type'] ?? 'tv'; ?>" 
                           class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-images"></i> عرض الصور
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- عرض الصور -->
        <?php if (!empty($images) || !empty($backdrops)): ?>
        <div class="search-box">
            <h2 class="search-title"><i class="fas fa-images"></i> الصور المتاحة</h2>
            
            <!-- تبويبات للصور -->
            <div class="image-tabs">
                <button class="tab-btn active" onclick="showTab('posters')">البوسترز (<?php echo count($images); ?>)</button>
                <button class="tab-btn" onclick="showTab('backdrops')">صور الخلفية (<?php echo count($backdrops); ?>)</button>
            </div>
            
            <!-- البوسترز -->
            <div id="posters-tab" class="images-grid">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $img): ?>
                    <div class="image-card">
                        <img src="<?php echo $img['url']; ?>" alt="Poster" style="width:100%; height:250px; object-fit:cover;">
                        <div class="image-info">
                            <div class="image-type">🎭 بوستر</div>
                            <div class="image-size"><?php echo $img['width']; ?>x<?php echo $img['height']; ?> • <?php echo strtoupper($img['lang']); ?></div>
                            <div class="image-url"><?php echo $img['url']; ?></div>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $img['url']; ?>')">
                                <i class="fas fa-copy"></i> نسخ الرابط
                            </button>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $img['url_original']; ?>')">
                                <i class="fas fa-hd"></i> نسخ (جودة عالية)
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-image"></i>
                        <p>لا توجد بوسترز متاحة</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- صور الخلفية -->
            <div id="backdrops-tab" class="images-grid" style="display:none;">
                <?php if (!empty($backdrops)): ?>
                    <?php foreach ($backdrops as $img): ?>
                    <div class="image-card">
                        <img src="<?php echo $img['url']; ?>" alt="Backdrop" style="width:100%; height:200px; object-fit:cover;">
                        <div class="image-info">
                            <div class="image-type">🎬 خلفية</div>
                            <div class="image-size"><?php echo $img['width']; ?>x<?php echo $img['height']; ?> • <?php echo strtoupper($img['lang']); ?></div>
                            <div class="image-url"><?php echo $img['url']; ?></div>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $img['url']; ?>')">
                                <i class="fas fa-copy"></i> نسخ الرابط
                            </button>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo $img['url_original']; ?>')">
                                <i class="fas fa-hd"></i> نسخ (جودة عالية)
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-image"></i>
                        <p>لا توجد صور خلفية متاحة</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- روابط جاهزة لأشهر المسلسلات -->
        <div class="famous-section">
            <h2 class="search-title"><i class="fas fa-star"></i> روابط جاهزة لأشهر المسلسلات</h2>
            
            <div class="famous-grid">
                <?php foreach ($famous_series as $series): ?>
                <div class="famous-card">
                    <div class="famous-title"><?php echo $series['name']; ?></div>
                    <div style="margin-bottom: 10px;">
                        <strong style="color: #e50914;">البوستر:</strong><br>
                        <div class="famous-url"><?php echo $series['poster']; ?></div>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo $series['poster']; ?>')">
                            <i class="fas fa-copy"></i> نسخ رابط البوستر
                        </button>
                    </div>
                    <div>
                        <strong style="color: #e50914;">الخلفية:</strong><br>
                        <div class="famous-url"><?php echo $series['backdrop']; ?></div>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo $series['backdrop']; ?>')">
                            <i class="fas fa-copy"></i> نسخ رابط الخلفية
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ تم نسخ الرابط بنجاح');
        }, function() {
            alert('❌ فشل النسخ، يمكنك نسخ الرابط يدوياً');
        });
    }
    
    function showTab(tabName) {
        // إخفاء جميع التبويبات
        document.getElementById('posters-tab').style.display = 'none';
        document.getElementById('backdrops-tab').style.display = 'none';
        
        // إزالة الفئة النشطة من جميع الأزرار
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        
        // إظهار التبويب المحدد
        document.getElementById(tabName + '-tab').style.display = 'grid';
        
        // إضافة الفئة النشطة للزر المحدد
        event.target.classList.add('active');
    }
    </script>
</body>
</html>