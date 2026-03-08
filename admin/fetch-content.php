<?php
// admin/universal-search.php - بحث موحد في جميع المواقع (سيرفرات + مواقع عربية)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// =============================================
// سيرفرات المشاهدة (جلب تلقائي)
// =============================================
$stream_sites = [
    'vidsrc' => [
        'name' => '🎬 Vidsrc.to',
        'url' => 'https://vidsrc.to/embed/',
        'quality' => '4K/1080p',
        'color' => '#e50914',
        'type' => 'stream'
    ],
    '2embed' => [
        'name' => '🎥 2Embed',
        'url' => 'https://www.2embed.cc/embed/',
        'quality' => '1080p',
        'color' => '#27ae60',
        'type' => 'stream'
    ],
    'embedsu' => [
        'name' => '📺 Embed.su',
        'url' => 'https://embed.su/embed/',
        'quality' => '1080p',
        'color' => '#f39c12',
        'type' => 'stream'
    ],
    'vidlink' => [
        'name' => '⚡ VidLink.pro',
        'url' => 'https://vidlink.pro/',
        'quality' => '4K',
        'color' => '#3498db',
        'type' => 'stream'
    ],
    'autoembed' => [
        'name' => '🎪 AutoEmbed',
        'url' => 'https://autoembed.cc/embed/',
        'quality' => '720p',
        'color' => '#9b59b6',
        'type' => 'stream'
    ],
    'smashystream' => [
        'name' => '🎭 SmashyStream',
        'url' => 'https://embed.smashystream.com/playere.php?',
        'quality' => '1080p',
        'color' => '#e67e22',
        'type' => 'stream'
    ],
    'multiembed' => [
        'name' => '🎯 MultiEmbed',
        'url' => 'https://multiembed.mov/?',
        'quality' => '1080p',
        'color' => '#1abc9c',
        'type' => 'stream'
    ]
];

// =============================================
// المواقع العربية (إضافة يدوية)
// =============================================
$arabic_sites = [
    'akwam' => [
        'name' => '🌊 أكوام',
        'url' => 'https://ak.sv/',
        'search_url' => 'https://ak.sv/search/',
        'color' => '#0e4620',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات عربية'
    ],
    'arabsed' => [
        'name' => '🎬 عرب سيد',
        'url' => 'https://arabsed.online/',
        'search_url' => 'https://arabsed.online/search/',
        'color' => '#9b2c2c',
        'type' => 'arabic',
        'description' => 'أفلام عربية'
    ],
    'mycima' => [
        'name' => '👑 ماي سيما',
        'url' => 'https://mycima.bio/',
        'search_url' => 'https://mycima.bio/search/',
        'color' => '#8e44ad',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ],
    'egybest' => [
        'name' => '🥚 EgyBest',
        'url' => 'https://egybest.icu/',
        'search_url' => 'https://egybest.icu/?s=',
        'color' => '#f39c12',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ],
    'fushaar' => [
        'name' => '🍿 فوشار',
        'url' => 'https://fushaar.com/',
        'search_url' => 'https://fushaar.com/?s=',
        'color' => '#e67e22',
        'type' => 'arabic',
        'description' => 'أفلام'
    ],
    'laroza' => [
        'name' => '🌹 لاروزا',
        'url' => 'https://laroza.store/',
        'search_url' => 'https://laroza.store/search/',
        'color' => '#e84342',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ],
    'shahed4u' => [
        'name' => '👁️ شاهد فور يو',
        'url' => 'https://shahed4u.cam/',
        'search_url' => 'https://shahed4u.cam/search/',
        'color' => '#16a085',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ],
    'arabseed' => [
        'name' => '🌱 عرب سيد',
        'url' => 'https://arabseed.ink/',
        'search_url' => 'https://arabseed.ink/search/',
        'color' => '#27ae60',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ],
    'wannas' => [
        'name' => '😊 وناس',
        'url' => 'https://wn.sc/',
        'search_url' => 'https://wn.sc/search/',
        'color' => '#3498db',
        'type' => 'arabic',
        'description' => 'أفلام ومسلسلات'
    ]
];

// معالجة إضافة المحتوى
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $title = $_POST['title'] ?? '';
    $tmdb_id = $_POST['tmdb_id'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    $selected_sites = $_POST['sites'] ?? [];
    $manual_url = $_POST['manual_url'] ?? '';
    
    if ($type && $title && $tmdb_id) {
        try {
            // حفظ في قاعدة البيانات
            if ($type == 'movie') {
                $stmt = $pdo->prepare("INSERT INTO movies (title, tmdb_id, year, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$title, $tmdb_id, $year]);
                $content_id = $pdo->lastInsertId();
                $content_table = 'movies';
            } else {
                $stmt = $pdo->prepare("INSERT INTO series (title, tmdb_id, year, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$title, $tmdb_id, $year]);
                $content_id = $pdo->lastInsertId();
                $content_table = 'series';
            }
            
            // إنشاء روابط لسيرفرات المشاهدة المختارة
            $links_added = 0;
            foreach ($selected_sites as $site_key) {
                if (isset($stream_sites[$site_key])) {
                    $site = $stream_sites[$site_key];
                    
                    // بناء الرابط حسب نوع المحتوى
                    if ($type == 'movie') {
                        if ($site_key == 'smashystream') {
                            $watch_url = $site['url'] . "tmdb=$tmdb_id";
                        } elseif ($site_key == 'multiembed') {
                            $watch_url = $site['url'] . "video_id=$tmdb_id&tmdb=1";
                        } else {
                            $watch_url = $site['url'] . "movie/$tmdb_id";
                        }
                    } else {
                        if ($site_key == 'smashystream') {
                            $watch_url = $site['url'] . "tmdb=$tmdb_id&type=tv";
                        } elseif ($site_key == 'multiembed') {
                            $watch_url = $site['url'] . "video_id=$tmdb_id&tmdb=1&type=tv";
                        } else {
                            $watch_url = $site['url'] . "tv/$tmdb_id";
                        }
                    }
                    
                    // حفظ في جدول السيرفرات (إذا كان موجوداً)
                    try {
                        $check_table = $pdo->query("SHOW TABLES LIKE 'watch_servers'")->rowCount();
                        if ($check_table > 0) {
                            $server_stmt = $pdo->prepare("INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality) VALUES (?, ?, ?, ?, ?)");
                            $server_stmt->execute([$type, $content_id, $site['name'], $watch_url, $site['quality']]);
                        }
                    } catch (Exception $e) {
                        // تجاهل الأخطاء
                    }
                    
                    $links_added++;
                }
            }
            
            // إضافة الرابط اليدوي إذا وجد
            if ($manual_url) {
                $update_stmt = $pdo->prepare("UPDATE $content_table SET watch_url = ? WHERE id = ?");
                $update_stmt->execute([$manual_url, $content_id]);
            }
            
            $message = "✅ تم إضافة المحتوى بنجاح!";
            if ($links_added > 0) {
                $message .= " ($links_added رابط تلقائي)";
            }
            if ($manual_url) {
                $message .= " + رابط يدوي";
            }
            $messageType = "success";
            
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "❌ الرجاء البحث واختيار المحتوى أولاً";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>البحث الموحد - ويزي برو</title>
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
            max-width: 1400px;
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
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .alert-error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }
        
        .main-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        
        .section-title {
            color: #e50914;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .type-selector {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .type-btn {
            flex: 1;
            padding: 15px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            text-align: center;
            font-weight: bold;
            transition: 0.3s;
        }
        
        .type-btn.active {
            background: #e50914;
            border-color: #e50914;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-box input {
            flex: 1;
            padding: 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 10px;
            color: #fff;
            font-size: 16px;
        }
        
        .search-box button {
            padding: 15px 30px;
            background: #e50914;
            color: #fff;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .search-box button:hover {
            background: #b20710;
        }
        
        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .result-card {
            background: #252525;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #333;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .result-card:hover {
            border-color: #e50914;
            transform: translateY(-2px);
        }
        
        .result-title {
            font-size: 15px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .result-year {
            color: #b3b3b3;
            font-size: 12px;
        }
        
        .result-id {
            background: #1a1a1a;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 10px;
            color: #e50914;
            display: inline-block;
            margin-top: 8px;
        }
        
        .loading, .no-results {
            text-align: center;
            padding: 20px;
            color: #b3b3b3;
        }
        
        .sites-category {
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }
        
        .sites-category h3 {
            color: #e50914;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .sites-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin: 15px 0;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .site-checkbox {
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .site-checkbox:hover {
            border-color: #e50914;
        }
        
        .site-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .site-info {
            flex: 1;
        }
        
        .site-name {
            font-weight: bold;
            font-size: 14px;
        }
        
        .site-quality, .site-desc {
            font-size: 11px;
            color: #b3b3b3;
        }
        
        .arabic-site-card {
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 15px;
            text-decoration: none;
            color: #fff;
            display: block;
            transition: 0.3s;
        }
        
        .arabic-site-card:hover {
            border-color: currentColor;
            transform: translateY(-2px);
        }
        
        .arabic-site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .arabic-site-name {
            font-weight: bold;
            font-size: 16px;
        }
        
        .arabic-site-url {
            color: #b3b3b3;
            font-size: 12px;
            margin-bottom: 5px;
        }
        
        .arabic-site-desc {
            font-size: 13px;
            color: #b3b3b3;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
        }
        
        .selected-info {
            background: #252525;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            border-right: 3px solid #e50914;
        }
        
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background: #219a52;
        }
        
        .manual-url-section {
            background: #1a2a1a;
            border: 1px solid #27ae60;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .arabic-sites-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        @media (max-width: 992px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .sites-grid {
                grid-template-columns: 1fr;
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
            <i class="fas fa-arrow-right"></i> العودة
        </a>
    </div>
    
    <div class="container">
        <h1>
            <i class="fas fa-globe"></i>
            البحث الموحد في جميع المواقع
        </h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="main-grid">
            <!-- قسم البحث في TMDB -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-search"></i>
                    ابحث في TMDB
                </div>
                
                <div class="type-selector">
                    <div class="type-btn active" onclick="setType('movie')" id="typeMovie">🎬 فيلم</div>
                    <div class="type-btn" onclick="setType('tv')" id="typeTv">📺 مسلسل</div>
                </div>
                
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="اكتب اسم الفيلم أو المسلسل..." onkeypress="if(event.key==='Enter') searchTMDB()">
                    <button onclick="searchTMDB()"><i class="fas fa-search"></i> بحث</button>
                </div>
                
                <div id="resultsContainer" class="results-grid"></div>
                <div id="loadingContainer" class="loading" style="display: none;">جاري البحث...</div>
            </div>
            
            <!-- قسم إضافة المحتوى -->
            <div class="section">
                <div class="section-title">
                    <i class="fas fa-plus-circle"></i>
                    إضافة المحتوى
                </div>
                
                <form method="POST" id="addForm">
                    <input type="hidden" name="type" id="formType" value="movie">
                    <input type="hidden" name="tmdb_id" id="formTmdbId">
                    <input type="hidden" name="title" id="formTitle">
                    <input type="hidden" name="year" id="formYear">
                    
                    <div class="selected-info" id="selectedInfo" style="display: none;">
                        <div style="color: #e50914; margin-bottom: 5px;">المحتوى المحدد:</div>
                        <div id="selectedTitle" style="font-weight: bold;"></div>
                        <div id="selectedYear" style="color: #b3b3b3; font-size: 13px;"></div>
                    </div>
                    
                    <!-- سيرفرات المشاهدة (جلب تلقائي) -->
                    <div class="sites-category">
                        <h3><i class="fas fa-server"></i> سيرفرات المشاهدة (جلب تلقائي)</h3>
                    </div>
                    
                    <div class="sites-grid">
                        <?php foreach ($stream_sites as $key => $site): ?>
                        <label class="site-checkbox">
                            <input type="checkbox" name="sites[]" value="<?php echo $key; ?>">
                            <div class="site-info">
                                <div class="site-name" style="color: <?php echo $site['color']; ?>">
                                    <?php echo $site['name']; ?>
                                </div>
                                <div class="site-quality"><?php echo $site['quality']; ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- رابط يدوي للمواقع العربية -->
                    <div class="manual-url-section">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                            <i class="fas fa-hand-pointer" style="color: #27ae60;"></i>
                            <span style="color: #27ae60; font-weight: bold;">رابط يدوي (للمواقع العربية)</span>
                        </div>
                        <input type="url" name="manual_url" placeholder="الصق رابط المشاهدة من الموقع العربي هنا...">
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-cloud-download-alt"></i>
                        إضافة المحتوى
                    </button>
                </form>
            </div>
        </div>
        
        <!-- المواقع العربية (روابط مباشرة) -->
        <div class="section" style="margin-top: 30px;">
            <div class="section-title">
                <i class="fas fa-globe"></i>
                مواقع عربية - ابحث يدوياً
            </div>
            
            <div class="arabic-sites-grid">
                <?php foreach ($arabic_sites as $key => $site): ?>
                <a href="<?php echo $site['search_url']; ?>" target="_blank" class="arabic-site-card" style="border-color: <?php echo $site['color']; ?>">
                    <div class="arabic-site-header">
                        <span class="arabic-site-name" style="color: <?php echo $site['color']; ?>">
                            <?php echo $site['name']; ?>
                        </span>
                        <i class="fas fa-external-link-alt" style="color: <?php echo $site['color']; ?>"></i>
                    </div>
                    <div class="arabic-site-url"><?php echo $site['url']; ?></div>
                    <div class="arabic-site-desc"><?php echo $site['description']; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            
            <div style="background: #252525; border-radius: 10px; padding: 15px; margin-top: 20px;">
                <div style="display: flex; gap: 10px; align-items: center; color: #f39c12;">
                    <i class="fas fa-info-circle"></i>
                    <span>كيفية استخدام المواقع العربية:</span>
                </div>
                <ol style="margin-top: 10px; padding-right: 20px; color: #b3b3b3; line-height: 1.8;">
                    <li>ابحث عن المحتوى في TMDB (القسم الأيمن)</li>
                    <li>اختر المحتوى المناسب</li>
                    <li>افتح الموقع العربي المناسب من القائمة أعلاه</li>
                    <li>ابحث عن نفس المحتوى في الموقع العربي</li>
                    <li>انسخ رابط المشاهدة من الموقع العربي</li>
                    <li>الصق الرابط في حقل "رابط يدوي" قبل إضافة المحتوى</li>
                </ol>
            </div>
        </div>
    </div>
    
    <script>
        let currentType = 'movie';
        
        function setType(type) {
            currentType = type;
            document.getElementById('formType').value = type;
            
            if (type === 'movie') {
                document.getElementById('typeMovie').classList.add('active');
                document.getElementById('typeTv').classList.remove('active');
            } else {
                document.getElementById('typeTv').classList.add('active');
                document.getElementById('typeMovie').classList.remove('active');
            }
        }
        
        function searchTMDB() {
            const query = document.getElementById('searchInput').value;
            
            if (!query) {
                alert('الرجاء كتابة كلمة البحث');
                return;
            }
            
            document.getElementById('resultsContainer').style.display = 'none';
            document.getElementById('loadingContainer').style.display = 'block';
            
            fetch(`https://api.themoviedb.org/3/search/${currentType}?api_key=5dc3e335b09cbf701d8685dd9a766949&query=${encodeURIComponent(query)}&language=ar`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('loadingContainer').style.display = 'none';
                    document.getElementById('resultsContainer').style.display = 'grid';
                    
                    if (data.results && data.results.length > 0) {
                        let html = '';
                        data.results.slice(0, 12).forEach(item => {
                            const title = item.title || item.name;
                            const year = (item.release_date || item.first_air_date || '').substring(0, 4) || 'غير معروف';
                            
                            html += `
                                <div class="result-card" onclick="selectItem('${title.replace(/'/g, "\\'")}', '${item.id}', '${year}')">
                                    <div class="result-title">${title}</div>
                                    <div class="result-year">${year}</div>
                                    <div class="result-id">TMDB: ${item.id}</div>
                                </div>
                            `;
                        });
                        document.getElementById('resultsContainer').innerHTML = html;
                    } else {
                        document.getElementById('resultsContainer').innerHTML = '<div class="no-results">لا توجد نتائج</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('loadingContainer').style.display = 'none';
                    document.getElementById('resultsContainer').style.display = 'grid';
                    document.getElementById('resultsContainer').innerHTML = '<div class="no-results">حدث خطأ في البحث</div>';
                });
        }
        
        function selectItem(title, id, year) {
            document.getElementById('formTmdbId').value = id;
            document.getElementById('formTitle').value = title;
            document.getElementById('formYear').value = year;
            
            document.getElementById('selectedInfo').style.display = 'block';
            document.getElementById('selectedTitle').textContent = title;
            document.getElementById('selectedYear').textContent = year;
            
            document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
        }
    </script>
</body>
</html>