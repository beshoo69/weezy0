<?php
// admin/upload-content.php - رفع المحتوى وجلب المعلومات
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// =============================================
// معالجة النموذج عند الإرسال
// =============================================
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_POST['content_type'] ?? 'movie'; // movie أو series
    $title = $_POST['title'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $release_date = $_POST['release_date'] ?? '';
    $imdb_rating = $_POST['imdb_rating'] ?? 0;
    $tmdb_id = $_POST['tmdb_id'] ?? '';
    $language = $_POST['language'] ?? 'ar';
    $genres = $_POST['genres'] ?? '';
    $cast = $_POST['cast'] ?? '';
    $director = $_POST['director'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    
    // معالجة الصور المرفوعة
    $poster_path = '';
    $backdrop_path = '';
    
    // مجلد رفع الصور
    $upload_dir = __DIR__ . '/../uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // رفع صورة البوستر
    if (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {
        $poster_ext = pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION);
        $poster_name = uniqid() . '_poster.' . $poster_ext;
        $poster_path = 'uploads/' . $poster_name;
        
        move_uploaded_file($_FILES['poster']['tmp_name'], $upload_dir . $poster_name);
    }
    
    // رفع صورة الخلفية
    if (isset($_FILES['backdrop']) && $_FILES['backdrop']['error'] === UPLOAD_ERR_OK) {
        $backdrop_ext = pathinfo($_FILES['backdrop']['name'], PATHINFO_EXTENSION);
        $backdrop_name = uniqid() . '_backdrop.' . $backdrop_ext;
        $backdrop_path = 'uploads/' . $backdrop_name;
        
        move_uploaded_file($_FILES['backdrop']['tmp_name'], $upload_dir . $backdrop_name);
    }
    
    // معالجة ملف الفيديو
    $video_path = '';
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $video_ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
        $video_name = uniqid() . '_video.' . $video_ext;
        $video_path = 'uploads/videos/' . $video_name;
        
        $video_dir = __DIR__ . '/../uploads/videos/';
        if (!file_exists($video_dir)) {
            mkdir($video_dir, 0777, true);
        }
        
        move_uploaded_file($_FILES['video_file']['tmp_name'], $video_dir . $video_name);
    }
    
    // رابط خارجي (إذا لم يتم رفع ملف)
    $external_url = $_POST['external_url'] ?? '';
    
    // حفظ في قاعدة البيانات
    try {
        if ($contentType === 'movie') {
            // إدخال فيلم
            $sql = "INSERT INTO movies (title, overview, poster, backdrop, release_date, year, imdb_rating, tmdb_id, language, genres, cast, director, video_url, created_at) 
                    VALUES (:title, :overview, :poster, :backdrop, :release_date, :year, :imdb_rating, :tmdb_id, :language, :genres, :cast, :director, :video_url, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':overview' => $overview,
                ':poster' => $poster_path,
                ':backdrop' => $backdrop_path,
                ':release_date' => $release_date,
                ':year' => $year,
                ':imdb_rating' => $imdb_rating,
                ':tmdb_id' => $tmdb_id,
                ':language' => $language,
                ':genres' => $genres,
                ':cast' => $cast,
                ':director' => $director,
                ':video_url' => $external_url ?: $video_path
            ]);
            
            $message = "تم رفع الفيلم بنجاح!";
            $messageType = "success";
        } else {
            // إدخال مسلسل (للموسم الأول)
            $sql = "INSERT INTO series (title, overview, poster, backdrop, first_air_date, year, imdb_rating, tmdb_id, language, genres, cast, created_at) 
                    VALUES (:title, :overview, :poster, :backdrop, :release_date, :year, :imdb_rating, :tmdb_id, :language, :genres, :cast, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':overview' => $overview,
                ':poster' => $poster_path,
                ':backdrop' => $backdrop_path,
                ':release_date' => $release_date,
                ':year' => $year,
                ':imdb_rating' => $imdb_rating,
                ':tmdb_id' => $tmdb_id,
                ':language' => $language,
                ':genres' => $genres,
                ':cast' => $cast
            ]);
            
            $series_id = $pdo->lastInsertId();
            
            // إضافة الموسم الأول إذا كان موجوداً
            if (isset($_POST['season_number']) && $_POST['season_number'] > 0) {
                $season_sql = "INSERT INTO seasons (series_id, season_number, name, overview, poster, air_date) 
                              VALUES (:series_id, :season_number, :name, :overview, :poster, :air_date)";
                $season_stmt = $pdo->prepare($season_sql);
                $season_stmt->execute([
                    ':series_id' => $series_id,
                    ':season_number' => $_POST['season_number'],
                    ':name' => 'الموسم ' . $_POST['season_number'],
                    ':overview' => $overview,
                    ':poster' => $poster_path,
                    ':air_date' => $release_date
                ]);
                
                $season_id = $pdo->lastInsertId();
                
                // إضافة الحلقة الأولى إذا وجدت
                if (isset($_POST['episode_number']) && $_POST['episode_number'] > 0) {
                    $episode_sql = "INSERT INTO episodes (series_id, season_id, episode_number, name, overview, still_path, air_date, video_url) 
                                   VALUES (:series_id, :season_id, :episode_number, :name, :overview, :still_path, :air_date, :video_url)";
                    $episode_stmt = $pdo->prepare($episode_sql);
                    $episode_stmt->execute([
                        ':series_id' => $series_id,
                        ':season_id' => $season_id,
                        ':episode_number' => $_POST['episode_number'],
                        ':name' => 'الحلقة ' . $_POST['episode_number'],
                        ':overview' => $overview,
                        ':still_path' => $backdrop_path,
                        ':air_date' => $release_date,
                        ':video_url' => $external_url ?: $video_path
                    ]);
                }
            }
            
            $message = "تم رفع المسلسل بنجاح!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "خطأ في الحفظ: " . $e->getMessage();
        $messageType = "error";
    }
}

// =============================================
// جلب معلومات من TMDB (AJAX)
// =============================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'fetch_tmdb') {
    header('Content-Type: application/json');
    
    $tmdb_id = $_GET['tmdb_id'] ?? '';
    $type = $_GET['type'] ?? 'movie';
    
    if (empty($tmdb_id)) {
        echo json_encode(['error' => 'يرجى إدخال رقم TMDB']);
        exit;
    }
    
    $api_key = 'YOUR_TMDB_API_KEY'; // ضع مفتاح API الخاص بك هنا
    
    if ($type === 'movie') {
        $url = "https://api.themoviedb.org/3/movie/{$tmdb_id}?api_key={$api_key}&language=ar";
    } else {
        $url = "https://api.themoviedb.org/3/tv/{$tmdb_id}?api_key={$api_key}&language=ar";
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['success']) && $data['success'] === false) {
        echo json_encode(['error' => 'لم يتم العثور على المحتوى']);
        exit;
    }
    
    // تنسيق البيانات
    $result = [
        'title' => $data['title'] ?? $data['name'] ?? '',
        'overview' => $data['overview'] ?? '',
        'release_date' => $data['release_date'] ?? $data['first_air_date'] ?? '',
        'year' => substr($data['release_date'] ?? $data['first_air_date'] ?? '', 0, 4),
        'imdb_rating' => $data['vote_average'] ?? 0,
        'poster' => $data['poster_path'] ? "https://image.tmdb.org/t/p/w500" . $data['poster_path'] : '',
        'backdrop' => $data['backdrop_path'] ? "https://image.tmdb.org/t/p/original" . $data['backdrop_path'] : '',
        'genres' => implode(', ', array_column($data['genres'] ?? [], 'name')),
        'language' => $data['original_language'] ?? ''
    ];
    
    echo json_encode($result);
    exit;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رفع المحتوى - ويزي برو</title>
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
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        
        /* الشريط العلوي */
        .top-bar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(229, 9, 20, 0.3);
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
        
        .back-link {
            color: #b3b3b3;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        
        .back-link:hover {
            color: #e50914;
        }
        
        /* بطاقة المحتوى */
        .upload-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 30px;
            border: 1px solid #333;
            margin-top: 30px;
        }
        
        .upload-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #333;
            padding-bottom: 20px;
        }
        
        .upload-header h2 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-header h2 i {
            color: #e50914;
        }
        
        .content-type-switch {
            display: flex;
            gap: 10px;
            background: #252525;
            padding: 5px;
            border-radius: 50px;
            border: 1px solid #333;
        }
        
        .type-btn {
            padding: 10px 25px;
            border-radius: 50px;
            cursor: pointer;
            transition: 0.3s;
            font-weight: 500;
        }
        
        .type-btn.active {
            background: #e50914;
            color: white;
        }
        
        .type-btn:not(.active):hover {
            background: #333;
        }
        
        /* نموذج الإدخال */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
            font-weight: 500;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 10px;
            color: white;
            font-family: 'Tajawal', sans-serif;
            font-size: 15px;
            transition: 0.3s;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #e50914;
            box-shadow: 0 0 0 2px rgba(229, 9, 20, 0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* قسم جلب المعلومات */
        .fetch-section {
            background: #252525;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .fetch-title {
            color: #e50914;
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .fetch-input {
            display: flex;
            gap: 10px;
        }
        
        .fetch-input input {
            flex: 1;
        }
        
        .fetch-btn {
            padding: 12px 25px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .fetch-btn:hover {
            background: #b20710;
        }
        
        .fetch-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* معاينة الصور */
        .image-preview {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .preview-box {
            flex: 1;
            background: #252525;
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #333;
        }
        
        .preview-box h4 {
            color: #b3b3b3;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .preview-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            display: none;
        }
        
        .preview-placeholder {
            width: 100%;
            height: 200px;
            background: #1a1a1a;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b3b3b3;
            border: 2px dashed #333;
        }
        
        /* رفع الملفات */
        .file-upload {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-btn {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 2px dashed #333;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .file-upload-btn:hover {
            border-color: #e50914;
            background: #2a2a2a;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-name {
            margin-top: 8px;
            color: #b3b3b3;
            font-size: 13px;
        }
        
        /* خيارات الرابط */
        .url-option {
            margin-top: 20px;
            padding: 20px;
            background: #252525;
            border-radius: 10px;
            border: 1px solid #333;
        }
        
        .or-divider {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        
        .or-divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #333;
        }
        
        .or-divider span {
            background: #1a1a1a;
            padding: 0 15px;
            color: #b3b3b3;
            position: relative;
            font-size: 14px;
        }
        
        /* أزرار الإجراء */
        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #333;
        }
        
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            border: none;
            font-family: 'Tajawal', sans-serif;
            font-size: 16px;
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
        
        .btn-secondary {
            background: #252525;
            color: white;
            border: 1px solid #333;
        }
        
        .btn-secondary:hover {
            background: #333;
        }
        
        /* رسائل التنبيه */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        /* تنسيقات متجاوبة */
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .image-preview {
                flex-direction: column;
            }
            
            .upload-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .fetch-input {
                flex-direction: column;
            }
        }
        
        /* تنسيق شريط التمرير */
        ::-webkit-scrollbar {
            width: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #1a1a1a;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #e50914;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-film" style="color: #e50914; font-size: 32px;"></i>
            <h1>ويزي<span>برو</span></h1>
        </div>
        
        <a href="dashboard-pro.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
        </a>
    </div>
    
    <div class="container">
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="upload-card">
            <div class="upload-header">
                <h2>
                    <i class="fas fa-cloud-upload-alt"></i>
                    رفع محتوى جديد
                </h2>
                
                <div class="content-type-switch" id="contentTypeSwitch">
                    <span class="type-btn active" data-type="movie">🎬 فيلم</span>
                    <span class="type-btn" data-type="series">📺 مسلسل</span>
                </div>
            </div>
            
            <!-- قسم جلب المعلومات -->
            <div class="fetch-section">
                <div class="fetch-title">
                    <i class="fas fa-magic"></i>
                    جلب المعلومات تلقائياً
                </div>
                <div class="fetch-input">
                    <input type="text" id="tmdbInput" placeholder="أدخل رقم TMDB (مثال: 550 for Fight Club)">
                    <select id="tmdbType">
                        <option value="movie">فيلم</option>
                        <option value="tv">مسلسل</option>
                    </select>
                    <button class="fetch-btn" id="fetchTmdbBtn">
                        <i class="fas fa-search"></i>
                        جلب البيانات
                    </button>
                </div>
                <div style="margin-top: 10px; color: #b3b3b3; font-size: 13px;">
                    <i class="fas fa-info-circle"></i>
                    يمكنك الحصول على رقم TMDB من موقع themoviedb.org
                </div>
            </div>
            <!-- في ملف upload-content.php - قسم روابط السحابة -->
<div class="cloud-options" style="margin: 30px 0; padding: 25px; background: linear-gradient(145deg, #1e3c72, #2a5298); border-radius: 20px;">
    <h3 style="color: white; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
        <i class="fab fa-google-drive"></i>
        رفع إلى Google Drive
    </h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <!-- جوجل درايف -->
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px; text-align: center;">
            <i class="fab fa-google-drive" style="font-size: 48px; color: #fff; margin-bottom: 15px;"></i>
            <h4 style="color: #fff; margin-bottom: 10px;">Google Drive</h4>
            <p style="color: #ddd; font-size: 13px; margin-bottom: 15px;">مساحة تخزين 15GB مجاناً</p>
            <a href="https://drive.google.com" target="_blank" class="btn" style="background: #fff; color: #1e3c72; width: 100%;">
                <i class="fas fa-external-link-alt"></i> فتح Google Drive
            </a>
        </div>
        
        <!-- وان درايف -->
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px; text-align: center;">
            <i class="fab fa-microsoft" style="font-size: 48px; color: #fff; margin-bottom: 15px;"></i>
            <h4 style="color: #fff; margin-bottom: 10px;">OneDrive</h4>
            <p style="color: #ddd; font-size: 13px; margin-bottom: 15px;">مساحة تخزين 5GB مجاناً</p>
            <a href="https://onedrive.live.com" target="_blank" class="btn" style="background: #fff; color: #1e3c72; width: 100%;">
                <i class="fas fa-external-link-alt"></i> فتح OneDrive
            </a>
        </div>
        
        <!-- دروب بوكس -->
        <div style="background: rgba(255,255,255,0.1); padding: 20px; border-radius: 15px; text-align: center;">
            <i class="fab fa-dropbox" style="font-size: 48px; color: #fff; margin-bottom: 15px;"></i>
            <h4 style="color: #fff; margin-bottom: 10px;">Dropbox</h4>
            <p style="color: #ddd; font-size: 13px; margin-bottom: 15px;">مساحة تخزين 2GB مجاناً</p>
            <a href="https://dropbox.com" target="_blank" class="btn" style="background: #fff; color: #1e3c72; width: 100%;">
                <i class="fas fa-external-link-alt"></i> فتح Dropbox
            </a>
        </div>
    </div>
    
    <!-- شرح كيفية الحصول على الرابط المباشر -->
    <div style="margin-top: 25px; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 15px;">
        <h4 style="color: #fff; margin-bottom: 15px;">📋 كيفية الحصول على رابط مباشر من Google Drive:</h4>
        <ol style="color: #ddd; line-height: 2; padding-right: 20px;">
            <li>ارفع الفيديو إلى Google Drive</li>
            <li>افتح الفيديو واضغط على "مشاركة" <i class="fas fa-share-alt"></i></li>
            <li>غير الإعدادات إلى "أي شخص لديه الرابط"</li>
            <li>انسخ الرابط والصقه في الحقل أدناه</li>
            <li>استخدم موقع <a href="https://sites.google.com/site/gdocs2direct/" target="_blank" style="color: #ff0;">GDocs2Direct</a> لتحويله لرابط مباشر</li>
        </ol>
    </div>
</div>

<!-- حقل إدخال رابط السحابة -->
<div class="form-group full-width">
    <label style="color: #e50914; font-size: 18px;">
        <i class="fas fa-cloud"></i>
        رابط الفيديو من السحابة (Google Drive - OneDrive - Dropbox)
    </label>
    <input type="url" name="cloud_url" placeholder="https://drive.google.com/your-video-link" style="padding: 15px; background: #252525; border: 2px solid #e50914;">
    <small style="color: #b3b3b3; display: block; margin-top: 5px;">✳️ الصق الرابط المباشر من أي خدمة تخزين سحابي</small>
</div>
            
            <!-- نموذج الرفع -->
            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="content_type" id="contentType" value="movie">
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label>العنوان</label>
                        <input type="text" name="title" id="title" required>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>الوصف</label>
                        <textarea name="overview" id="overview" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>تاريخ الإصدار</label>
                        <input type="date" name="release_date" id="release_date">
                    </div>
                    
                    <div class="form-group">
                        <label>السنة</label>
                        <input type="number" name="year" id="year" value="<?php echo date('Y'); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>تقييم IMDB</label>
                        <input type="number" step="0.1" min="0" max="10" name="imdb_rating" id="imdb_rating">
                    </div>
                    
                    <div class="form-group">
                        <label>اللغة</label>
                        <select name="language" id="language">
                            <option value="ar">العربية</option>
                            <option value="en">الإنجليزية</option>
                            <option value="tr">التركية</option>
                            <option value="hi">الهندية</option>
                            <option value="ko">الكورية</option>
                            <option value="ja">اليابانية</option>
                            <option value="fr">الفرنسية</option>
                            <option value="de">الألمانية</option>
                        </select>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>التصنيفات (افصل بينها بفواصل)</label>
                        <input type="text" name="genres" id="genres" placeholder="مثال: أكشن, دراما, رومانسي">
                    </div>
                    
                    <div class="form-group full-width" id="castField">
                        <label>طاقم التمثيل</label>
                        <input type="text" name="cast" id="cast" placeholder="أسماء الممثلين مفصولة بفواصل">
                    </div>
                    
                    <div class="form-group full-width" id="directorField">
                        <label>المخرج</label>
                        <input type="text" name="director" id="director">
                    </div>
                    
                    <!-- حقول خاصة بالمسلسل (تظهر عند اختيار مسلسل) -->
                    <div class="form-group" id="seasonField" style="display: none;">
                        <label>رقم الموسم</label>
                        <input type="number" name="season_number" value="1" min="1">
                    </div>
                    
                    <div class="form-group" id="episodeField" style="display: none;">
                        <label>رقم الحلقة</label>
                        <input type="number" name="episode_number" value="1" min="1">
                    </div>
                    
                    <div class="form-group">
                        <label>رقم TMDB (اختياري)</label>
                        <input type="text" name="tmdb_id" id="tmdb_id">
                    </div>
                </div>
                
                <!-- معاينة الصور -->
                <div class="image-preview">
                    <div class="preview-box">
                        <h4>صورة البوستر</h4>
                        <img id="posterPreview" class="preview-image" src="" alt="">
                        <div id="posterPlaceholder" class="preview-placeholder">
                            <i class="fas fa-image" style="font-size: 40px; opacity: 0.5;"></i>
                        </div>
                    </div>
                    
                    <div class="preview-box">
                        <h4>صورة الخلفية</h4>
                        <img id="backdropPreview" class="preview-image" src="" alt="">
                        <div id="backdropPlaceholder" class="preview-placeholder">
                            <i class="fas fa-image" style="font-size: 40px; opacity: 0.5;"></i>
                        </div>
                    </div>
                </div>
                
                <!-- رفع الصور -->
                <div class="form-grid">
                    <div class="form-group">
                        <label>رفع صورة البوستر</label>
                        <div class="file-upload">
                            <div class="file-upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i>
                                اختر صورة
                            </div>
                            <input type="file" name="poster" id="poster" accept="image/*">
                        </div>
                        <div class="file-name" id="posterFileName"></div>
                    </div>
                    
                    <div class="form-group">
                        <label>رفع صورة الخلفية</label>
                        <div class="file-upload">
                            <div class="file-upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i>
                                اختر صورة
                            </div>
                            <input type="file" name="backdrop" id="backdrop" accept="image/*">
                        </div>
                        <div class="file-name" id="backdropFileName"></div>
                    </div>
                </div>
                
                <!-- رفع الفيديو أو رابط خارجي -->
                <div class="url-option">
                    <div class="form-group">
                        <label>رفع ملف الفيديو</label>
                        <div class="file-upload">
                            <div class="file-upload-btn">
                                <i class="fas fa-video"></i>
                                اختر ملف فيديو
                            </div>
                            <input type="file" name="video_file" id="videoFile" accept="video/*">
                        </div>
                        <div class="file-name" id="videoFileName"></div>
                    </div>
                    
                    <div class="or-divider">
                        <span>أو</span>
                    </div>
                    
                    <div class="form-group">
                        <label>رابط خارجي (YouTube, Google Drive, إلخ)</label>
                        <input type="url" name="external_url" placeholder="https://example.com/video.mp4">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i>
                        إعادة تعيين
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        حفظ المحتوى
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // تبديل بين فيلم ومسلسل
        const typeBtns = document.querySelectorAll('.type-btn');
        const contentType = document.getElementById('contentType');
        const seasonField = document.getElementById('seasonField');
        const episodeField = document.getElementById('episodeField');
        const castField = document.getElementById('castField');
        const directorField = document.getElementById('directorField');
        const tmdbType = document.getElementById('tmdbType');
        
        typeBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                typeBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const type = this.dataset.type;
                contentType.value = type;
                
                if (type === 'movie') {
                    seasonField.style.display = 'none';
                    episodeField.style.display = 'none';
                    directorField.style.display = 'block';
                    tmdbType.value = 'movie';
                } else {
                    seasonField.style.display = 'block';
                    episodeField.style.display = 'block';
                    directorField.style.display = 'none';
                    tmdbType.value = 'tv';
                }
            });
        });
        
        // جلب المعلومات من TMDB
        document.getElementById('fetchTmdbBtn').addEventListener('click', function() {
            const tmdbId = document.getElementById('tmdbInput').value.trim();
            const type = document.getElementById('tmdbType').value;
            
            if (!tmdbId) {
                alert('الرجاء إدخال رقم TMDB');
                return;
            }
            
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الجلب...';
            
            fetch(`upload-content.php?ajax=fetch_tmdb&tmdb_id=${tmdbId}&type=${type}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    
                    // تعبئة الحقول
                    document.getElementById('title').value = data.title || '';
                    document.getElementById('overview').value = data.overview || '';
                    document.getElementById('release_date').value = data.release_date || '';
                    document.getElementById('year').value = data.year || '';
                    document.getElementById('imdb_rating').value = data.imdb_rating || '';
                    document.getElementById('genres').value = data.genres || '';
                    document.getElementById('tmdb_id').value = tmdbId;
                    
                    // تغيير اللغة حسب رمز اللغة
                    const langSelect = document.getElementById('language');
                    if (data.language === 'ar') langSelect.value = 'ar';
                    else if (data.language === 'en') langSelect.value = 'en';
                    else if (data.language === 'tr') langSelect.value = 'tr';
                    else if (data.language === 'hi') langSelect.value = 'hi';
                    else if (data.language === 'ko') langSelect.value = 'ko';
                    else if (data.language === 'ja') langSelect.value = 'ja';
                    
                    // معاينة الصور (إذا وجدت)
                    if (data.poster) {
                        const posterPreview = document.getElementById('posterPreview');
                        const posterPlaceholder = document.getElementById('posterPlaceholder');
                        posterPreview.src = data.poster;
                        posterPreview.style.display = 'block';
                        posterPlaceholder.style.display = 'none';
                    }
                    
                    if (data.backdrop) {
                        const backdropPreview = document.getElementById('backdropPreview');
                        const backdropPlaceholder = document.getElementById('backdropPlaceholder');
                        backdropPreview.src = data.backdrop;
                        backdropPreview.style.display = 'block';
                        backdropPlaceholder.style.display = 'none';
                    }
                })
                .catch(error => {
                    alert('حدث خطأ في الاتصال');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-search"></i> جلب البيانات';
                });
        });
        
        // معاينة الصور المرفوعة
        document.getElementById('poster').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('posterFileName').textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const posterPreview = document.getElementById('posterPreview');
                    const posterPlaceholder = document.getElementById('posterPlaceholder');
                    posterPreview.src = e.target.result;
                    posterPreview.style.display = 'block';
                    posterPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
        
        document.getElementById('backdrop').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('backdropFileName').textContent = file.name;
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const backdropPreview = document.getElementById('backdropPreview');
                    const backdropPlaceholder = document.getElementById('backdropPlaceholder');
                    backdropPreview.src = e.target.result;
                    backdropPreview.style.display = 'block';
                    backdropPlaceholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
        
        document.getElementById('videoFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                document.getElementById('videoFileName').textContent = file.name;
            }
        });
        
        // إعادة تعيين المعاينة عند النقر على زر إعادة تعيين
        document.querySelector('button[type="reset"]').addEventListener('click', function() {
            document.getElementById('posterPreview').style.display = 'none';
            document.getElementById('posterPlaceholder').style.display = 'flex';
            document.getElementById('backdropPreview').style.display = 'none';
            document.getElementById('backdropPlaceholder').style.display = 'flex';
            document.getElementById('posterFileName').textContent = '';
            document.getElementById('backdropFileName').textContent = '';
            document.getElementById('videoFileName').textContent = '';
        });
    </script>
</body>
</html>