<?php
// admin/manual-edit-posters.php - تعديل صور الأفلام والمسلسلات يدوياً
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// التأكد من تسجيل الدخول
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';
$content_type = isset($_GET['type']) ? $_GET['type'] : 'series'; // series أو movie

// =============================================
// معالجة تحديث الصورة
// =============================================
if (isset($_POST['update_poster'])) {
    $item_id = (int)$_POST['item_id'];
    $item_type = $_POST['item_type'];
    $new_poster = isset($_POST['new_poster']) ? trim($_POST['new_poster']) : '';
    $new_backdrop = isset($_POST['new_backdrop']) ? trim($_POST['new_backdrop']) : '';
    
    if ($item_id > 0) {
        try {
            $table = ($item_type == 'movie') ? 'movies' : 'series';
            
            if (!empty($new_poster)) {
                $stmt = $pdo->prepare("UPDATE $table SET poster = ? WHERE id = ?");
                $stmt->execute([$new_poster, $item_id]);
                $message = "✅ تم تحديث البوستر بنجاح";
            }
            
            if (!empty($new_backdrop)) {
                $stmt = $pdo->prepare("UPDATE $table SET backdrop = ? WHERE id = ?");
                $stmt->execute([$new_backdrop, $item_id]);
                $message = "✅ تم تحديث الخلفية بنجاح";
            }
        } catch (Exception $e) {
            $error = "❌ خطأ في التحديث: " . $e->getMessage();
        }
    }
}

// =============================================
// البحث في قاعدة البيانات
// =============================================
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$item_list = [];

if (!empty($search_term)) {
    if ($content_type == 'movie') {
        // بحث في الأفلام
        $stmt = $pdo->prepare("
            SELECT * FROM movies 
            WHERE title LIKE ? OR title_en LIKE ? 
            ORDER BY 
                CASE 
                    WHEN title LIKE ? THEN 1 
                    WHEN title_en LIKE ? THEN 2 
                    ELSE 3 
                END
            LIMIT 50
        ");
    } else {
        // بحث في المسلسلات
        $stmt = $pdo->prepare("
            SELECT * FROM series 
            WHERE title LIKE ? OR title_en LIKE ? 
            ORDER BY 
                CASE 
                    WHEN title LIKE ? THEN 1 
                    WHEN title_en LIKE ? THEN 2 
                    ELSE 3 
                END
            LIMIT 50
        ");
    }
    
    $search_param = "%{$search_term}%";
    $exact_param = $search_term . '%';
    $stmt->execute([$search_param, $search_param, $exact_param, $exact_param]);
    $item_list = $stmt->fetchAll();
} else {
    // عرض آخر 50 عنصر
    if ($content_type == 'movie') {
        $stmt = $pdo->query("SELECT * FROM movies ORDER BY id DESC LIMIT 50");
    } else {
        $stmt = $pdo->query("SELECT * FROM series ORDER BY id DESC LIMIT 50");
    }
    $item_list = $stmt->fetchAll();
}

// =============================================
// جلب عنصر محدد للتحرير
// =============================================
$current_item = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    
    if ($content_type == 'movie') {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    }
    
    $stmt->execute([$edit_id]);
    $current_item = $stmt->fetch();
    
    if (!$current_item) {
        $error = "❌ العنصر غير موجود";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل الصور - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            display: flex;
        }
        
        .sidebar {
            width: 280px;
            background: #0a0a0a;
            height: 100vh;
            position: fixed;
            right: 0;
            padding: 30px 20px;
            border-left: 1px solid #1f1f1f;
            overflow-y: auto;
        }
        
        .logo {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #b3b3b3;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            gap: 12px;
            transition: 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #e50914;
            color: white;
        }
        
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #1a1a1a;
            padding: 20px 30px;
            border-radius: 15px;
        }
        
        h1 {
            font-size: 28px;
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .type-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .type-tab {
            padding: 12px 30px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 8px;
            color: #b3b3b3;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
        }
        
        .type-tab:hover {
            border-color: #e50914;
            color: white;
        }
        
        .type-tab.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }
        
        .message {
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error {
            background: #e50914;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        /* ===== البحث ===== */
        .search-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .search-title {
            color: #e50914;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .search-form {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 15px;
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
            display: inline-block;
            padding: 12px 20px;
            background: #2a2a2a;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: 0.3s;
            border: none;
            cursor: pointer;
            font-weight: 500;
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
        
        /* ===== قائمة العناصر ===== */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .item-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: 0.3s;
        }
        
        .item-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }
        
        .item-preview {
            display: flex;
            gap: 15px;
            padding: 15px;
        }
        
        .item-thumb {
            width: 80px;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .item-info {
            flex: 1;
        }
        
        .item-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .item-meta {
            color: #b3b3b3;
            font-size: 13px;
            margin-bottom: 10px;
        }
        
        .badge-success {
            color: #27ae60;
        }
        
        .badge-danger {
            color: #e50914;
        }
        
        /* ===== محرر الصور ===== */
        .editor-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }
        
        .editor-title {
            color: #e50914;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .current-images {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .image-box {
            flex: 1;
            min-width: 300px;
            text-align: center;
        }
        
        .image-box h3 {
            margin-bottom: 15px;
            color: #b3b3b3;
        }
        
        .current-poster {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 2px solid #333;
        }
        
        .current-backdrop {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 2px solid #333;
        }
        
        .image-url-input {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            margin-top: 10px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .image-url-input:focus {
            border-color: #e50914;
            outline: none;
        }
        
        /* ===== روابط سريعة ===== */
        .quick-links {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
            margin-top: 30px;
        }
        
        .quick-links h3 {
            color: #e50914;
            margin-bottom: 15px;
        }
        
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
        }
        
        .quick-item {
            background: #1a1a1a;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #333;
        }
        
        .quick-title {
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .quick-url {
            font-size: 11px;
            color: #b3b3b3;
            word-break: break-all;
            margin-bottom: 10px;
            background: #0a0a0a;
            padding: 5px;
            border-radius: 4px;
        }
        
        .copy-btn {
            width: 100%;
            padding: 8px;
            background: #2a2a2a;
            border: none;
            color: #b3b3b3;
            cursor: pointer;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .copy-btn:hover {
            color: white;
            background: #e50914;
        }
        
        .info-note {
            background: #2a2a2a;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-right: 4px solid #e50914;
            color: #b3b3b3;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-right: 0; }
            .current-images { flex-direction: column; }
            .search-form { flex-direction: column; }
            .type-tabs { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">🎬 فايز<span>تڨي</span></div>
        <nav>
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
            <a href="import-tmdb.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد أفلام</a>
            <a href="import-tv.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد مسلسلات</a>
            <a href="manual-edit-posters.php" class="nav-item active"><i class="fas fa-edit"></i> تعديل الصور</a>
            <a href="fast-image-finder.php" class="nav-item"><i class="fas fa-search"></i> بحث عن صور</a>
            <a href="fix-posters.php" class="nav-item"><i class="fas fa-wrench"></i> إصلاح الصور</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-edit"></i> تعديل الصور</h1>
            <a href="fast-image-finder.php" class="btn btn-primary" target="_blank">
                <i class="fas fa-search"></i> البحث عن صور
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="message">
            <i class="fas fa-check-circle"></i> <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="error">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <!-- معلومات مهمة -->
        <div class="info-note">
            <i class="fas fa-info-circle" style="color: #e50914; margin-left: 10px;"></i>
            <strong>ملاحظة:</strong> استخدم روابط TMDB فقط. يمكنك البحث عن الصور في صفحة "بحث عن صور".
        </div>
        
        <!-- اختيار النوع (أفلام / مسلسلات) -->
        <div class="type-tabs">
            <a href="?type=series<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
               class="type-tab <?php echo $content_type == 'series' ? 'active' : ''; ?>">
                <i class="fas fa-tv"></i> مسلسلات
            </a>
            <a href="?type=movie<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" 
               class="type-tab <?php echo $content_type == 'movie' ? 'active' : ''; ?>">
                <i class="fas fa-film"></i> أفلام
            </a>
        </div>
        
        <!-- قسم البحث -->
        <div class="search-section">
            <h2 class="search-title"><i class="fas fa-search"></i> ابحث عن <?php echo $content_type == 'movie' ? 'فيلم' : 'مسلسل'; ?></h2>
            
            <form method="GET" class="search-form">
                <input type="hidden" name="type" value="<?php echo $content_type; ?>">
                <input type="text" name="search" class="search-input" 
                       placeholder="اكتب الاسم..." 
                       value="<?php echo htmlspecialchars($search_term); ?>">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> بحث
                </button>
                <?php if (!empty($search_term)): ?>
                <a href="?type=<?php echo $content_type; ?>" class="btn">إلغاء</a>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($current_item): ?>
        <!-- ===== محرر الصور للعنصر المحدد ===== -->
        <div class="editor-section">
            <h2 class="editor-title">
                <i class="fas fa-pen"></i>
                تعديل صور: <?php echo htmlspecialchars($current_item['title']); ?>
                <span style="font-size: 16px; color: #b3b3b3; margin-right: 10px;">
                    (<?php echo $content_type == 'movie' ? 'فيلم' : 'مسلسل'; ?>)
                </span>
            </h2>
            
            <div class="current-images">
                <!-- البوستر -->
                <div class="image-box">
                    <h3>البوستر الحالي</h3>
                    <img src="<?php echo !empty($current_item['poster']) ? htmlspecialchars($current_item['poster']) : 'https://via.placeholder.com/300x450?text=No+Poster'; ?>" 
                         class="current-poster" alt="Current poster" id="poster-preview">
                    <form method="POST">
                        <input type="hidden" name="item_id" value="<?php echo $current_item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $content_type; ?>">
                        <input type="url" name="new_poster" class="image-url-input" 
                               placeholder="أدخل رابط الصورة الجديدة" 
                               value="<?php echo htmlspecialchars($current_item['poster'] ?? ''); ?>"
                               id="poster-input">
                        <button type="submit" name="update_poster" class="btn btn-success" style="width:100%; margin-top:10px;">
                            <i class="fas fa-save"></i> تحديث البوستر
                        </button>
                    </form>
                </div>
                
                <!-- الخلفية -->
                <div class="image-box">
                    <h3>صورة الخلفية الحالية</h3>
                    <img src="<?php echo !empty($current_item['backdrop']) ? htmlspecialchars($current_item['backdrop']) : 'https://via.placeholder.com/1280x720?text=No+Backdrop'; ?>" 
                         class="current-backdrop" alt="Current backdrop" id="backdrop-preview">
                    <form method="POST">
                        <input type="hidden" name="item_id" value="<?php echo $current_item['id']; ?>">
                        <input type="hidden" name="item_type" value="<?php echo $content_type; ?>">
                        <input type="url" name="new_backdrop" class="image-url-input" 
                               placeholder="أدخل رابط الصورة الجديدة" 
                               value="<?php echo htmlspecialchars($current_item['backdrop'] ?? ''); ?>"
                               id="backdrop-input">
                        <button type="submit" name="update_poster" class="btn btn-success" style="width:100%; margin-top:10px;">
                            <i class="fas fa-save"></i> تحديث الخلفية
                        </button>
                    </form>
                </div>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <a href="?type=<?php echo $content_type; ?>&search=<?php echo urlencode($search_term); ?>" class="btn">
                    <i class="fas fa-arrow-right"></i> العودة للقائمة
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- ===== قائمة العناصر ===== -->
        <h2 style="margin-bottom: 20px; color: #e50914;">
            <i class="fas fa-list"></i> 
            <?php if (!empty($search_term)): ?>
                نتائج البحث عن "<?php echo htmlspecialchars($search_term); ?>"
            <?php else: ?>
                أحدث 50 <?php echo $content_type == 'movie' ? 'فيلم' : 'مسلسل'; ?>
            <?php endif; ?>
        </h2>
        
        <?php if (empty($item_list)): ?>
        <div style="text-align: center; padding: 60px; background: #1a1a1a; border-radius: 15px;">
            <i class="fas fa-search" style="font-size: 50px; color: #e50914; margin-bottom: 20px;"></i>
            <h3>لا توجد نتائج</h3>
            <p style="color: #b3b3b3;">جرب كلمات بحث أخرى</p>
        </div>
        <?php else: ?>
        <div class="items-grid">
            <?php foreach ($item_list as $item): ?>
            <div class="item-card">
                <div class="item-preview">
                    <img src="<?php echo !empty($item['poster']) ? htmlspecialchars($item['poster']) : 'https://via.placeholder.com/80x120?text=No+Image'; ?>" 
                         class="item-thumb" alt="<?php echo htmlspecialchars($item['title']); ?>">
                    <div class="item-info">
                        <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        <div class="item-meta">
                            <?php if (!empty($item['poster'])): ?>
                            <span class="badge-success"><i class="fas fa-check"></i> بوستر</span>
                            <?php else: ?>
                            <span class="badge-danger"><i class="fas fa-times"></i> لا يوجد بوستر</span>
                            <?php endif; ?>
                            <br>
                            <?php if (!empty($item['backdrop'])): ?>
                            <span class="badge-success"><i class="fas fa-check"></i> خلفية</span>
                            <?php else: ?>
                            <span class="badge-danger"><i class="fas fa-times"></i> لا توجد خلفية</span>
                            <?php endif; ?>
                            <?php if ($content_type == 'movie'): ?>
                            <br><span>🎬 فيلم</span>
                            <?php else: ?>
                            <br><span>📺 مسلسل</span>
                            <?php endif; ?>
                        </div>
                        <a href="?type=<?php echo $content_type; ?>&edit=<?php echo $item['id']; ?>&search=<?php echo urlencode($search_term); ?>" 
                           class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-edit"></i> تعديل الصور
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- روابط سريعة لأشهر الأفلام والمسلسلات -->
        <div class="quick-links">
            <h3><i class="fas fa-star"></i> روابط سريعة</h3>
            <div class="quick-grid">
                <div class="quick-item">
                    <div class="quick-title">صراع العروش (مسلسل)</div>
                    <div class="quick-url">https://image.tmdb.org/t/p/w500/7WUHnWGx5W8wTOx6XJw7s2S2Bc.jpg</div>
                    <button class="copy-btn" onclick="copyToClipboard('https://image.tmdb.org/t/p/w500/7WUHnWGx5W8wTOx6XJw7s2S2Bc.jpg')">
                        <i class="fas fa-copy"></i> نسخ الرابط
                    </button>
                </div>
                <div class="quick-item">
                    <div class="quick-title">بريكنج باد (مسلسل)</div>
                    <div class="quick-url">https://image.tmdb.org/t/p/w500/ggFHVNu6YYI5L9pCfOacjizRGt.jpg</div>
                    <button class="copy-btn" onclick="copyToClipboard('https://image.tmdb.org/t/p/w500/ggFHVNu6YYI5L9pCfOacjizRGt.jpg')">
                        <i class="fas fa-copy"></i> نسخ الرابط
                    </button>
                </div>
                <div class="quick-item">
                    <div class="quick-title">تشيرنوبل (مسلسل)</div>
                    <div class="quick-url">https://image.tmdb.org/t/p/w500/hlLXtK9IShSshS3Fcd7Tk7YKUjQ.jpg</div>
                    <button class="copy-btn" onclick="copyToClipboard('https://image.tmdb.org/t/p/w500/hlLXtK9IShSshS3Fcd7Tk7YKUjQ.jpg')">
                        <i class="fas fa-copy"></i> نسخ الرابط
                    </button>
                </div>
                <div class="quick-item">
                    <div class="quick-title">الخلاصة (فيلم)</div>
                    <div class="quick-url">https://image.tmdb.org/t/p/w500/7Bwt7PBoqJwWz4XJcQv8xQz4XJc.jpg</div>
                    <button class="copy-btn" onclick="copyToClipboard('https://image.tmdb.org/t/p/w500/7Bwt7PBoqJwWz4XJcQv8xQz4XJc.jpg')">
                        <i class="fas fa-copy"></i> نسخ الرابط
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // نسخ الرابط إلى الحافظة
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            alert('✅ تم نسخ الرابط بنجاح');
        }, function() {
            alert('❌ فشل النسخ، يمكنك نسخ الرابط يدوياً');
        });
    }
    
    // معاينة الصور عند تغيير الرابط
    document.getElementById('poster-input')?.addEventListener('input', function(e) {
        document.getElementById('poster-preview').src = e.target.value;
    });
    
    document.getElementById('backdrop-input')?.addEventListener('input', function(e) {
        document.getElementById('backdrop-preview').src = e.target.value;
    });
    </script>
</body>
</html>