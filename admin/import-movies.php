<?php
// admin/import-movies.php - نظام استيراد الأفلام المتطور مع طاقم العمل والإعلام
if (php_sapi_name() === 'cli') {
    echo "⚠️ هذا الملف يجب تشغيله من المتصفح!\n";
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: login.php');
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
    'ps' => '🇦🇫 پښتو',
    'bn' => '🇧🇩 বাংলা',
    'ko' => '🇰🇷 한국어',
    'ja' => '🇯🇵 日本語',
    'zh' => '🇨🇳 中文',
    'th' => '🇹🇭 ไทย',
    'vi' => '🇻🇳 Tiếng Việt',
    'id' => '🇮🇩 Bahasa Indonesia',
    'ms' => '🇲🇾 Bahasa Melayu',
    'tl' => '🇵🇭 Filipino',
    'ru' => '🇷🇺 Русский',
    'it' => '🇮🇹 Italiano',
    'pt' => '🇵🇹 Português'
];

// قائمة خطط العضوية
$membership_levels = [
    'basic' => ['name' => 'عادي', 'badge' => 'مجاني', 'color' => '#6c757d', 'icon' => '🆓', 'desc' => 'متاح للجميع مجاناً'],
    'premium' => ['name' => 'مميز', 'badge' => '⭐ مميز', 'color' => '#e50914', 'icon' => '⭐', 'desc' => 'جودة عالية بدون إعلانات'],
    'vip' => ['name' => 'VIP', 'badge' => '👑 VIP', 'color' => 'gold', 'icon' => '👑', 'desc' => 'محتوى حصري وجودة 4K']
];

// قائمة السيرفرات المتاحة للمشاهدة والتحميل
$available_servers = [
    // سيرفرات المشاهدة
    'watch' => [
        '🎬 Vidsrc.to - 4K UHD' => ['name' => '🎬 Vidsrc.to - 4K UHD', 'type' => 'watch', 'quality' => '4K UHD', 'language' => 'العربية'],
        '🎥 2Embed - 1080p HD' => ['name' => '🎥 2Embed - 1080p HD', 'type' => 'watch', 'quality' => '1080p HD', 'language' => 'العربية'],
        '📺 Embed.su - 1080p HD' => ['name' => '📺 Embed.su - 1080p HD', 'type' => 'watch', 'quality' => '1080p HD', 'language' => 'English'],
        '⚡ VidLink.pro - 4K UHD' => ['name' => '⚡ VidLink.pro - 4K UHD', 'type' => 'watch', 'quality' => '4K UHD', 'language' => 'العربية'],
        '🌟 MultiEmbed - 1080p HD' => ['name' => '🌟 MultiEmbed - 1080p HD', 'type' => 'watch', 'quality' => '1080p HD', 'language' => 'العربية'],
        '🔥 VidSrc.pro - 4K UHD' => ['name' => '🔥 VidSrc.pro - 4K UHD', 'type' => 'watch', 'quality' => '4K UHD', 'language' => 'العربية'],
        '🎯 SmashyStream - 1080p HD' => ['name' => '🎯 SmashyStream - 1080p HD', 'type' => 'watch', 'quality' => '1080p HD', 'language' => 'العربية'],
        '📱 VidSrc.me - 720p HD' => ['name' => '📱 VidSrc.me - 720p HD', 'type' => 'watch', 'quality' => '720p HD', 'language' => 'العربية'],
        '🌐 Embedsoap - 1080p HD' => ['name' => '🌐 Embedsoap - 1080p HD', 'type' => 'watch', 'quality' => '1080p HD', 'language' => 'English'],
        '🎪 VidSrc.cc - 4K UHD' => ['name' => '🎪 VidSrc.cc - 4K UHD', 'type' => 'watch', 'quality' => '4K UHD', 'language' => 'العربية']
    ],
    // سيرفرات التحميل
    'download' => [
        'ميديا فاير' => ['name' => 'ميديا فاير', 'type' => 'download', 'quality' => '1080p', 'size' => '1.8 GB'],
        'جوجل درايف' => ['name' => 'جوجل درايف', 'type' => 'download', 'quality' => '720p', 'size' => '950 MB'],
        'ميجا' => ['name' => 'ميجا', 'type' => 'download', 'quality' => '4K UHD', 'size' => '3.2 GB'],
        'تورنت' => ['name' => 'تورنت', 'type' => 'download', 'quality' => '1080p', 'size' => '1.5 GB'],
        'أون درايف' => ['name' => 'أون درايف', 'type' => 'download', 'quality' => '720p', 'size' => '800 MB'],
        'درايف مباشر' => ['name' => 'درايف مباشر', 'type' => 'download', 'quality' => '1080p', 'size' => '2.1 GB']
    ]
];

// =============================================
// دوال تحديد نوع الفيلم (للتوزيع التلقائي)
// =============================================
function determineMovieCategory($movie_data) {
    $country = is_array($movie_data) ? ($movie_data['country'] ?? '') : '';
    $language = is_array($movie_data) ? ($movie_data['language'] ?? '') : '';
    $title = is_array($movie_data) ? ($movie_data['title'] ?? '') : '';
    $genre = is_array($movie_data) ? ($movie_data['genre'] ?? '') : '';
    
    $country_lower = strtolower($country);
    $language_lower = strtolower($language);
    $title_lower = strtolower($title);
    $genre_lower = strtolower($genre);
    
    $arab_countries = [
        'مصر', 'السعودية', 'لبنان', 'سوريا', 'الإمارات', 'الكويت', 'المغرب', 
        'تونس', 'الجزائر', 'العراق', 'الأردن', 'فلسطين', 'اليمن', 'عمان', 
        'قطر', 'البحرين', 'ليبيا', 'السودان', 'موريتانيا', 'الصومال', 'جيبوتي',
        'egypt', 'saudi', 'lebanon', 'syria', 'uae', 'kuwait', 'morocco', 
        'tunisia', 'algeria', 'iraq', 'jordan', 'palestine', 'yemen', 'oman', 
        'qatar', 'bahrain', 'libya', 'sudan'
    ];
    
    $arabic_keywords = ['عربي', 'عربية', 'arab', 'arabic', 'الوطن العربي'];
    $egyptian_keywords = ['مصري', 'مصرية', 'egypt', 'egyptian', 'القاهرة', 'الاسكندرية'];
    $turkish_keywords = ['تركي', 'تركية', 'turk', 'turkish', 'turkey', 'istanbul', 'انقرة', 'اسطنبول'];
    $indian_keywords = ['هندي', 'هندية', 'indian', 'india', 'bollywood', 'مومباي', 'دلهي'];
    $asian_keywords = [
        'كوري', 'كورية', 'korean', 'korea', 'seoul',
        'ياباني', 'يابانية', 'japanese', 'japan', 'tokyo',
        'صيني', 'صينية', 'chinese', 'china', 'hong kong', 'تايواني',
        'تايلاندي', 'thai', 'thailand', 'vietnamese', 'indonesian',
        'آسيوي', 'asian', 'شرق آسيوي'
    ];
    
    // 1. التحقق من المصري
    foreach ($egyptian_keywords as $keyword) {
        if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
            return [
                'category' => 'egyptian',
                'display_name' => 'فيلم مصري',
                'icon' => '🇪🇬',
                'color' => '#ce1126'
            ];
        }
    }
    
    // 2. التحقق من العربي
    foreach ($arab_countries as $arab_country) {
        if (strpos($country_lower, $arab_country) !== false) {
            return [
                'category' => 'arabic',
                'display_name' => 'فيلم عربي',
                'icon' => '🌍',
                'color' => '#0e4620'
            ];
        }
    }
    
    foreach ($arabic_keywords as $keyword) {
        if (strpos($genre_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false) {
            return [
                'category' => 'arabic',
                'display_name' => 'فيلم عربي',
                'icon' => '🌍',
                'color' => '#0e4620'
            ];
        }
    }
    
    if ($language_lower == 'ar') {
        return [
            'category' => 'arabic',
            'display_name' => 'فيلم عربي',
            'icon' => '🌍',
            'color' => '#0e4620'
        ];
    }
    
    // 3. التحقق من التركي
    foreach ($turkish_keywords as $keyword) {
        if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
            return [
                'category' => 'turkish',
                'display_name' => 'فيلم تركي',
                'icon' => '🇹🇷',
                'color' => '#9b2c2c'
            ];
        }
    }
    
    if ($language_lower == 'tr') {
        return [
            'category' => 'turkish',
            'display_name' => 'فيلم تركي',
            'icon' => '🇹🇷',
            'color' => '#9b2c2c'
        ];
    }
    
    // 4. التحقق من الهندي
    foreach ($indian_keywords as $keyword) {
        if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
            return [
                'category' => 'indian',
                'display_name' => 'فيلم هندي',
                'icon' => '🇮🇳',
                'color' => '#ff9933'
            ];
        }
    }
    
    if (in_array($language_lower, ['hi', 'ur', 'pa', 'bn'])) {
        return [
            'category' => 'indian',
            'display_name' => 'فيلم هندي',
            'icon' => '🇮🇳',
            'color' => '#ff9933'
        ];
    }
    
    // 5. التحقق من الآسيوي
    $asian_languages = ['ko', 'ja', 'zh', 'th', 'vi', 'id', 'ms', 'tl'];
    $asian_countries = [
        'كوريا', 'korea', 'south korea',
        'اليابان', 'japan',
        'الصين', 'china', 'hong kong', 'taiwan',
        'تايلاند', 'thailand',
        'فيتنام', 'vietnam',
        'إندونيسيا', 'indonesia',
        'ماليزيا', 'malaysia',
        'الفلبين', 'philippines'
    ];
    
    foreach ($asian_countries as $asian_country) {
        if (strpos($country_lower, $asian_country) !== false) {
            return [
                'category' => 'asian',
                'display_name' => 'فيلم آسيوي',
                'icon' => '🌏',
                'color' => '#4a1d6d'
            ];
        }
    }
    
    foreach ($asian_keywords as $keyword) {
        if (strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
            return [
                'category' => 'asian',
                'display_name' => 'فيلم آسيوي',
                'icon' => '🌏',
                'color' => '#4a1d6d'
            ];
        }
    }
    
    if (in_array($language_lower, $asian_languages)) {
        return [
            'category' => 'asian',
            'display_name' => 'فيلم آسيوي',
            'icon' => '🌏',
            'color' => '#4a1d6d'
        ];
    }
    
    // 6. باقي الأفلام أجنبية
    return [
        'category' => 'foreign',
        'display_name' => 'فيلم أجنبي',
        'icon' => '🌎',
        'color' => '#1a4b8c'
    ];
}

/**
 * إضافة حقول التصنيف إلى جدول movies
 */
function ensureCategoryColumns($pdo) {
    try {
        $check = $pdo->query("SHOW COLUMNS FROM movies LIKE 'category'");
        if ($check->rowCount() == 0) {
            $pdo->exec("ALTER TABLE movies ADD COLUMN category VARCHAR(50) NULL AFTER genre");
            $pdo->exec("ALTER TABLE movies ADD COLUMN category_display VARCHAR(100) NULL AFTER category");
            $pdo->exec("ALTER TABLE movies ADD COLUMN category_icon VARCHAR(10) NULL AFTER category_display");
            $pdo->exec("ALTER TABLE movies ADD COLUMN category_color VARCHAR(20) NULL AFTER category_icon");
            $pdo->exec("CREATE INDEX idx_category ON movies(category)");
        }
    } catch (Exception $e) {}
}

/**
 * التأكد من وجود جداول طاقم العمل
 */
function ensureCastTables($pdo) {
    try {
        // جدول الممثلين للأفلام
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS movie_cast (
                id INT AUTO_INCREMENT PRIMARY KEY,
                movie_id INT NOT NULL,
                person_id INT NULL,
                name VARCHAR(255) NOT NULL,
                character_name VARCHAR(255),
                profile_path VARCHAR(500),
                order_number INT DEFAULT 999,
                department VARCHAR(100),
                popularity FLOAT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_movie (movie_id),
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // جدول فريق العمل للأفلام
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS movie_crew (
                id INT AUTO_INCREMENT PRIMARY KEY,
                movie_id INT NOT NULL,
                person_id INT NULL,
                name VARCHAR(255) NOT NULL,
                job VARCHAR(255),
                department VARCHAR(100),
                profile_path VARCHAR(500),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_movie (movie_id),
                FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
    } catch (Exception $e) {}
}

// تنفيذ دوال التأكد من الجداول
ensureCategoryColumns($pdo);
ensureCastTables($pdo);

// جلب أحدث الأفلام
$latest_movies = $pdo->query("
    SELECT m.*, 
           (SELECT COUNT(*) FROM movie_cast WHERE movie_id = m.id) as cast_count
    FROM movies m 
    ORDER BY m.id DESC 
    LIMIT 12
")->fetchAll();

// إحصائيات التصنيفات
$category_stats = $pdo->query("
    SELECT category, COUNT(*) as count, category_display
    FROM movies WHERE category IS NOT NULL GROUP BY category
")->fetchAll();

/**
 * جلب تفاصيل شخص (ممثل) من TMDB
 */
function fetchPersonDetails($person_id) {
    $url = "https://api.themoviedb.org/3/person/{$person_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=images,external_ids";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// معالجة جلب التفاصيل من TMDB
$tmdb_data = null;
$selected_id = isset($_GET['tmdb_id']) ? $_GET['tmdb_id'] : null;
$cast_data = [];
$crew_data = [];
$search_results = [];
$videos_data = [];
$images_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_tmdb'])) {
    $query = $_POST['search_query'] ?? '';
    $year = $_POST['search_year'] ?? '';
    
    if (!empty($query)) {
        $search_url = "https://api.themoviedb.org/3/search/movie?api_key=" . TMDB_API_KEY . "&query=" . urlencode($query) . "&language=ar-SA";
        if (!empty($year)) {
            $search_url .= "&year=" . $year;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $search_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        $search_results = $data['results'] ?? [];
    }
}

if ($selected_id) {
    // جلب بيانات الفيلم الأساسية مع جميع التفاصيل
    $url = "https://api.themoviedb.org/3/movie/{$selected_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,videos,images,external_ids,keywords,release_dates";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $tmdb_data = json_decode($response, true);
        
        // تحديد تصنيف الفيلم
        if ($tmdb_data && is_array($tmdb_data)) {
            $country = $tmdb_data['production_countries'][0]['name'] ?? '';
            $language = $tmdb_data['original_language'] ?? '';
            $genres = array_column($tmdb_data['genres'] ?? [], 'name');
            $genre_str = implode('، ', $genres);
            
            $movie_data_for_category = [
                'country' => $country,
                'language' => $language,
                'title' => $tmdb_data['title'] ?? '',
                'genre' => $genre_str
            ];
            
            $predicted_category = determineMovieCategory($movie_data_for_category);
        }
        
        // جلب طاقم العمل الكامل مع تفاصيل إضافية
        if (isset($tmdb_data['credits']['cast'])) {
            $cast_data = [];
            foreach (array_slice($tmdb_data['credits']['cast'], 0, 30) as $index => $cast) {
                // جلب تفاصيل إضافية للممثل
                $person_details = fetchPersonDetails($cast['id']);
                
                $cast_data[] = [
                    'id' => $cast['id'],
                    'name' => $cast['name'],
                    'character' => $cast['character'],
                    'profile_path' => $cast['profile_path'] ?? null,
                    'order' => $cast['order'],
                    'known_for_department' => $cast['known_for_department'] ?? 'تمثيل',
                    'popularity' => $cast['popularity'] ?? 0,
                    'birthday' => $person_details['birthday'] ?? null,
                    'place_of_birth' => $person_details['place_of_birth'] ?? null,
                    'biography' => $person_details['biography'] ?? null,
                    'imdb_id' => $person_details['imdb_id'] ?? null,
                    'also_known_as' => $person_details['also_known_as'] ?? []
                ];
            }
        }
        
        // جلب فريق العمل (المخرجين، المنتجين، الكتاب)
        if (isset($tmdb_data['credits']['crew'])) {
            $important_roles = ['Director', 'Producer', 'Writer', 'Screenplay', 'Novel', 'Executive Producer'];
            $crew_data = array_filter($tmdb_data['credits']['crew'], function($crew) use ($important_roles) {
                return in_array($crew['job'], $important_roles);
            });
            $crew_data = array_slice($crew_data, 0, 15);
        }
        
        // جلب الفيديوهات (الإعلانات)
        if (isset($tmdb_data['videos']['results'])) {
            $videos_data = array_filter($tmdb_data['videos']['results'], function($video) {
                return $video['site'] == 'YouTube' && in_array($video['type'], ['Trailer', 'Teaser', 'Clip', 'Behind the Scenes']);
            });
        }
        
        // جلب الصور الإضافية
        if (isset($tmdb_data['images']['backdrops'])) {
            $images_data = array_slice($tmdb_data['images']['backdrops'], 0, 15);
        }
    }
}

/**
 * معالجة رفع الصور
 */
function handleImageUpload($file, $type = 'poster', $sub_dir = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    
    $base_dir = __DIR__ . '/../uploads/';
    if (!empty($sub_dir)) {
        $upload_dir = $base_dir . $sub_dir . '/';
    } else {
        $upload_dir = $base_dir . $type . 's/';
    }
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array(strtolower($ext), $allowed)) {
        return null;
    }
    
    $filename = time() . '_' . uniqid() . '_' . $type . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        $path = 'uploads/';
        $path .= !empty($sub_dir) ? $sub_dir . '/' : $type . 's/';
        return $path . $filename;
    }
    
    return null;
}

// معالجة إضافة الفيلم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_movie'])) {
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
    $tmdb_id = $_POST['tmdb_id'] ?? '';
    $status = $_POST['status'] ?? 'published';
    $imdb_id = $_POST['imdb_id'] ?? '';
    $homepage = $_POST['homepage'] ?? '';
    $vote_count = (int)($_POST['vote_count'] ?? 0);
    $popularity = (float)($_POST['popularity'] ?? 0);
    
    // تحديد تصنيف الفيلم
    $movie_data = [
        'country' => $country,
        'language' => $language,
        'title' => $title,
        'genre' => $genre
    ];
    $category_info = determineMovieCategory($movie_data);
    $category = $category_info['category'];
    $category_display = $category_info['display_name'];
    $category_icon = $category_info['icon'];
    $category_color = $category_info['color'];
    
    // معالجة الصور الرئيسية
    $local_poster = null;
    $local_backdrop = null;
    
    // رفع البوستر الجديد إذا وجد
    if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
        $local_poster = handleImageUpload($_FILES['poster_file'], 'poster');
    } elseif (!empty($_POST['poster_url'])) {
        $poster_content = @file_get_contents($_POST['poster_url']);
        if ($poster_content !== false) {
            $poster_name = time() . '_' . uniqid() . '_poster.jpg';
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            file_put_contents($upload_dir . $poster_name, $poster_content);
            $local_poster = 'uploads/posters/' . $poster_name;
        }
    }
    
    // رفع صورة الخلفية الجديدة إذا وجدت
    if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
        $local_backdrop = handleImageUpload($_FILES['backdrop_file'], 'backdrop');
    } elseif (!empty($_POST['backdrop_url'])) {
        $backdrop_content = @file_get_contents($_POST['backdrop_url']);
        if ($backdrop_content !== false) {
            $backdrop_name = time() . '_' . uniqid() . '_backdrop.jpg';
            $upload_dir = __DIR__ . '/../uploads/backdrops/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            file_put_contents($upload_dir . $backdrop_name, $backdrop_content);
            $local_backdrop = 'uploads/backdrops/' . $backdrop_name;
        }
    }
    
    // معالجة ملفات الفيديو والتحميل
    $video_file = '';
    $download_file = '';
    
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $video_name = time() . '_' . uniqid() . '_video.' . pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
        $video_path = __DIR__ . '/../uploads/videos/' . $video_name;
        if (!file_exists(__DIR__ . '/../uploads/videos/')) {
            mkdir(__DIR__ . '/../uploads/videos/', 0777, true);
        }
        if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_path)) {
            $video_file = 'uploads/videos/' . $video_name;
        }
    }
    
    if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
        $download_name = time() . '_' . uniqid() . '_download.' . pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION);
        $download_path = __DIR__ . '/../uploads/downloads/' . $download_name;
        if (!file_exists(__DIR__ . '/../uploads/downloads/')) {
            mkdir(__DIR__ . '/../uploads/downloads/', 0777, true);
        }
        if (move_uploaded_file($_FILES['download_file']['tmp_name'], $download_path)) {
            $download_file = 'uploads/downloads/' . $download_name;
        }
    }
    
    try {
        // التحقق من وجود الفيلم مسبقاً
        $check = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ? OR (title = ? AND year = ?)");
        $check->execute([$tmdb_id, $title, $year]);
        $existing = $check->fetch();
        
        if ($existing) {
            $message = "⚠️ هذا الفيلم موجود مسبقاً!";
            $messageType = "warning";
            $duplicate_id = $existing['id'];
        } else {
            $pdo->beginTransaction();
            
            // إضافة الفيلم
            $sql = "INSERT INTO movies (
                tmdb_id, imdb_id, title, title_en, description, poster, backdrop, 
                year, country, language, genre, category, category_display, category_icon, category_color,
                duration, imdb_rating, vote_count, popularity, membership_level, 
                status, video_url, download_url, homepage, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $tmdb_id, $imdb_id, $title, $title_en, $overview, 
                $local_poster, $local_backdrop, $year, $country, $language, 
                $genre, $category, $category_display, $category_icon, $category_color,
                $duration, $imdb_rating, $vote_count, $popularity, $membership_level, 
                $status, $video_file, $download_file, $homepage
            ]);
            
            $movie_id = $pdo->lastInsertId();
            
            // إضافة طاقم العمل (الممثلين) مع الصور
            if (isset($_POST['cast']) && is_array($_POST['cast'])) {
                foreach ($_POST['cast'] as $index => $cast_member) {
                    if (!empty($cast_member['name'])) {
                        $cast_image = $cast_member['profile_path'] ?? '';
                        
                        // رفع صورة الممثل إذا تم رفعها
                        if (isset($_FILES['cast_images']['name'][$index]) && 
                            $_FILES['cast_images']['error'][$index] === UPLOAD_ERR_OK) {
                            $temp_file = [
                                'name' => $_FILES['cast_images']['name'][$index],
                                'type' => $_FILES['cast_images']['type'][$index],
                                'tmp_name' => $_FILES['cast_images']['tmp_name'][$index],
                                'error' => $_FILES['cast_images']['error'][$index],
                                'size' => $_FILES['cast_images']['size'][$index]
                            ];
                            $uploaded_image = handleImageUpload($temp_file, 'cast', 'cast');
                            if ($uploaded_image) {
                                $cast_image = $uploaded_image;
                            }
                        }
                        
                        $cast_sql = "INSERT INTO movie_cast (
                            movie_id, person_id, name, character_name, profile_path, 
                            order_number, department, popularity
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $cast_stmt = $pdo->prepare($cast_sql);
                        $cast_stmt->execute([
                            $movie_id,
                            $cast_member['person_id'] ?? null,
                            $cast_member['name'],
                            $cast_member['character'] ?? '',
                            $cast_image,
                            $cast_member['order'] ?? 999,
                            $cast_member['department'] ?? 'Acting',
                            $cast_member['popularity'] ?? 0
                        ]);
                    }
                }
            }
            
            // إضافة فريق العمل
            if (isset($_POST['crew']) && is_array($_POST['crew'])) {
                foreach ($_POST['crew'] as $crew_member) {
                    if (!empty($crew_member['name'])) {
                        $crew_sql = "INSERT INTO movie_crew (
                            movie_id, person_id, name, job, department, profile_path
                        ) VALUES (?, ?, ?, ?, ?, ?)";
                        $crew_stmt = $pdo->prepare($crew_sql);
                        $crew_stmt->execute([
                            $movie_id,
                            $crew_member['person_id'] ?? null,
                            $crew_member['name'],
                            $crew_member['job'] ?? '',
                            $crew_member['department'] ?? '',
                            $crew_member['profile_path'] ?? ''
                        ]);
                    }
                }
            }
            
            // إضافة روابط المشاهدة
            if (isset($_POST['watch_links']) && is_array($_POST['watch_links'])) {
                foreach ($_POST['watch_links'] as $link) {
                    if (!empty($link['url']) && !empty($link['server'])) {
                        // الحصول على تفاصيل السيرفر المختار
                        $server_key = $link['server'];
                        $server_details = $available_servers['watch'][$server_key] ?? null;
                        
                        if ($server_details) {
                            $link_sql = "INSERT INTO watch_servers (
                                item_type, item_id, server_name, server_url, quality, language
                            ) VALUES (?, ?, ?, ?, ?, ?)";
                            $link_stmt = $pdo->prepare($link_sql);
                            $link_stmt->execute([
                                'movie', $movie_id, 
                                $server_details['name'], 
                                $link['url'], 
                                $link['quality'] ?? $server_details['quality'], 
                                $link['lang'] ?? 'arabic'
                            ]);
                        }
                    }
                }
            }
            
            // إضافة روابط التحميل
            if (isset($_POST['download_links']) && is_array($_POST['download_links'])) {
                foreach ($_POST['download_links'] as $link) {
                    if (!empty($link['url']) && !empty($link['server'])) {
                        // الحصول على تفاصيل السيرفر المختار
                        $server_key = $link['server'];
                        $server_details = $available_servers['download'][$server_key] ?? null;
                        
                        if ($server_details) {
                            $link_sql = "INSERT INTO download_servers (
                                item_type, item_id, server_name, download_url, quality, size
                            ) VALUES (?, ?, ?, ?, ?, ?)";
                            $link_stmt = $pdo->prepare($link_sql);
                            $link_stmt->execute([
                                'movie', $movie_id,
                                $server_details['name'],
                                $link['url'],
                                $link['quality'] ?? $server_details['quality'],
                                $link['size'] ?? $server_details['size']
                            ]);
                        }
                    }
                }
            }
            
            // إضافة الترجمات
            if (isset($_POST['subtitles']) && is_array($_POST['subtitles'])) {
                foreach ($_POST['subtitles'] as $index => $subtitle) {
                    if (!empty($subtitle['language_code'])) {
                        $subtitle_file = null;
                        
                        if (isset($_FILES['subtitle_files']['name'][$index]) && 
                            $_FILES['subtitle_files']['error'][$index] === UPLOAD_ERR_OK) {
                            $sub_ext = pathinfo($_FILES['subtitle_files']['name'][$index], PATHINFO_EXTENSION);
                            $sub_name = time() . '_' . uniqid() . '_sub.' . $sub_ext;
                            $sub_path = __DIR__ . '/../uploads/subtitles/' . $sub_name;
                            if (!file_exists(__DIR__ . '/../uploads/subtitles/')) {
                                mkdir(__DIR__ . '/../uploads/subtitles/', 0777, true);
                            }
                            if (move_uploaded_file($_FILES['subtitle_files']['tmp_name'][$index], $sub_path)) {
                                $subtitle_file = 'uploads/subtitles/' . $sub_name;
                            }
                        }
                        
                        $sub_sql = "INSERT INTO subtitles (
                            content_type, content_id, language, language_code, 
                            subtitle_url, subtitle_file, is_default
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $sub_stmt = $pdo->prepare($sub_sql);
                        $sub_stmt->execute([
                            'movie', $movie_id,
                            $subtitle['language_name'] ?? $subtitle_languages[$subtitle['language_code']] ?? $subtitle['language_code'],
                            $subtitle['language_code'],
                            $subtitle['url'] ?? '',
                            $subtitle_file,
                            isset($subtitle['is_default']) ? 1 : 0
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            $message = "✅ تم إضافة الفيلم بنجاح في قسم: {$category_display} {$category_icon} مع " . count($cast_data) . " ممثل!";
            $messageType = "success";
            
            header("Location: edit-movie.php?id=" . $movie_id . "&added=1&category=" . urlencode($category));
            exit;
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎬 مستورد الأفلام المتطور - مع طاقم العمل والإعلام</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --arabic: #0e4620;
            --foreign: #1a4b8c;
            --turkish: #9b2c2c;
            --indian: #ff9933;
            --asian: #4a1d6d;
            --egyptian: #ce1126;
            --dark: #0f0f0f;
            --dark-light: #1a1a1a;
            --card-bg: #1f1f1f;
            --text: #ffffff;
            --text-muted: #b3b3b3;
            --success: #27ae60;
            --warning: #f39c12;
            --info: #3498db;
            --border: #333333;
        }

        body {
            font-family: 'Tajawal', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            padding: 20px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid var(--primary);
            box-shadow: 0 4px 20px rgba(229, 9, 20, 0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-icon {
            font-size: 40px;
            color: var(--primary);
            animation: pulse 2s infinite;
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
            color: var(--text);
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.05);
        }

        .nav-link:hover {
            background: var(--primary);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(229, 9, 20, 0.3);
        }

        .nav-link.active {
            background: var(--primary);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .category-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-item {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.arabic { border-top: 3px solid var(--arabic); }
        .stat-item.foreign { border-top: 3px solid var(--foreign); }
        .stat-item.turkish { border-top: 3px solid var(--turkish); }
        .stat-item.indian { border-top: 3px solid var(--indian); }
        .stat-item.asian { border-top: 3px solid var(--asian); }
        .stat-item.egyptian { border-top: 3px solid var(--egyptian); }

        .stat-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .stat-count {
            font-size: 20px;
            font-weight: 700;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 13px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 20px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), transparent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(229, 9, 20, 0.2);
        }

        .stat-icon {
            font-size: 30px;
            color: var(--primary);
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
        }

        .search-section {
            background: linear-gradient(135deg, var(--card-bg), var(--dark-light));
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }

        .search-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--primary);
        }

        .search-grid {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
        }

        .search-input {
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 12px 15px;
            color: var(--text);
            font-size: 15px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(229, 9, 20, 0.2);
        }

        .search-btn {
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            color: white;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.02);
        }

        .results-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--border);
            margin-bottom: 30px;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .results-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .results-count {
            background: rgba(229,9,20,0.1);
            padding: 5px 12px;
            border-radius: 20px;
            color: var(--primary);
            font-size: 14px;
        }

        .movies-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding: 5px;
        }

        .movie-card {
            background: var(--dark-light);
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .movie-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(229,9,20,0.2);
        }

        .movie-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }

        .movie-info {
            padding: 10px;
        }

        .movie-title {
            font-weight: 600;
            margin-bottom: 3px;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .movie-year {
            color: var(--text-muted);
            font-size: 12px;
        }

        .add-form {
            background: linear-gradient(135deg, var(--card-bg), var(--dark-light));
            border-radius: 20px;
            padding: 25px;
            border: 1px solid var(--border);
            margin-top: 30px;
        }

        .form-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .form-header h2 {
            font-size: 24px;
            color: var(--primary);
        }

        .category-badge {
            padding: 5px 15px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: <?php echo $predicted_category['color'] ?? '#333'; ?>;
            color: white;
        }

        .form-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--border);
            padding-bottom: 10px;
            overflow-x: auto;
        }

        .form-tab {
            padding: 10px 20px;
            background: transparent;
            border: 2px solid var(--border);
            border-radius: 30px;
            color: var(--text-muted);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            font-size: 14px;
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
            display: block;
        }

        .preview-section {
            background: var(--dark-light);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
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
            box-shadow: 0 10px 20px rgba(0,0,0,0.5);
            border: 3px solid var(--primary);
        }

        .change-poster-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: 2px solid var(--primary);
            border-radius: 20px;
            padding: 5px 10px;
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .change-poster-btn:hover {
            background: var(--primary);
        }

        .movie-details-preview {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .movie-title-preview {
            font-size: 28px;
            font-weight: 800;
            color: var(--primary);
        }

        .movie-meta-preview {
            display: flex;
            gap: 15px;
            color: var(--text-muted);
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,0.05);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
        }

        .rating-badge {
            background: #f39c12;
            color: #000;
            font-weight: 700;
        }

        .predicted-category {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 30px;
            background: <?php echo $predicted_category['color'] ?? '#333'; ?>;
            color: white;
            font-weight: 600;
            margin-top: 10px;
        }

        .cast-section {
            margin: 20px 0;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .section-title {
            font-size: 18px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
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
            background: var(--dark-light);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
        }

        .cast-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(229,9,20,0.2);
        }

        .cast-image-container {
            position: relative;
            width: 100%;
            aspect-ratio: 1/1;
            overflow: hidden;
        }

        .cast-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .cast-image:hover {
            transform: scale(1.05);
        }

        .change-cast-image-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: 1px solid var(--primary);
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 5;
        }

        .change-cast-image-btn:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        .cast-info {
            padding: 10px;
        }

        .cast-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cast-character {
            color: var(--text-muted);
            font-size: 12px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cast-order {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            margin-top: 5px;
        }

        .remove-cast-btn {
            position: absolute;
            top: 5px;
            left: 5px;
            background: rgba(229,9,20,0.8);
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 5;
        }

        .remove-cast-btn:hover {
            background: var(--primary);
            transform: scale(1.1);
        }

        .crew-section {
            margin: 20px 0;
        }

        .crew-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            padding: 5px;
        }

        .crew-item {
            background: var(--dark-light);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .crew-name {
            font-weight: 600;
            color: var(--primary);
            font-size: 14px;
        }

        .crew-job {
            color: var(--text-muted);
            font-size: 12px;
            margin-top: 3px;
        }

        .crew-department {
            display: inline-block;
            background: rgba(255,255,255,0.05);
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-top: 3px;
        }

        .videos-section {
            margin: 20px 0;
        }

        .videos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding: 5px;
        }

        .video-card {
            background: var(--dark-light);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }

        .video-thumbnail {
            width: 100%;
            aspect-ratio: 16/9;
            object-fit: cover;
        }

        .video-info {
            padding: 10px;
        }

        .video-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 3px;
        }

        .video-type {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
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
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            background: rgba(0,0,0,0.3);
            border: 2px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(229,9,20,0.2);
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .status-selector {
            display: flex;
            gap: 10px;
            margin: 15px 0;
        }

        .status-option {
            flex: 1;
            padding: 10px;
            background: var(--dark-light);
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .status-option.selected {
            border-color: var(--primary);
            background: rgba(229,9,20,0.1);
        }

        .membership-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .membership-card {
            background: var(--dark-light);
            border: 2px solid var(--border);
            border-radius: 15px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .membership-card:hover {
            transform: translateY(-5px);
        }

        .membership-card.selected {
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }

        .membership-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }

        .membership-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .membership-desc {
            color: var(--text-muted);
            font-size: 12px;
            margin-bottom: 10px;
        }

        .membership-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .links-container {
            background: var(--dark-light);
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }

        .link-item {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto;
            gap: 8px;
            margin-bottom: 8px;
            padding: 8px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
        }

        .add-link-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 13px;
        }

        .add-link-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 10px rgba(39,174,96,0.3);
        }

        .remove-link-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .subtitles-container {
            background: var(--dark-light);
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
        }

        .subtitle-item {
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto auto;
            gap: 8px;
            margin-bottom: 8px;
            padding: 8px;
            background: rgba(0,0,0,0.2);
            border-radius: 6px;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-primary {
            flex: 1;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(229,9,20,0.4);
        }

        .btn-secondary {
            flex: 1;
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .notification {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 25px;
            border-radius: 50px;
            background: var(--success);
            color: white;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            z-index: 9999;
            animation: slideDown 0.5s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notification.error {
            background: var(--primary);
        }

        .notification.warning {
            background: var(--warning);
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
            background: var(--dark-light);
            border-radius: 15px;
            padding: 20px;
            max-width: 400px;
            width: 90%;
            border: 2px solid var(--primary);
        }

        .modal-title {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .file-input {
            width: 100%;
            padding: 10px;
            background: var(--dark);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text);
            margin-bottom: 15px;
        }

        .latest-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .latest-item {
            background: var(--dark-light);
            border-radius: 8px;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .latest-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(229,9,20,0.2);
        }

        .latest-poster {
            width: 100%;
            aspect-ratio: 2/3;
            object-fit: cover;
        }

        .latest-info {
            padding: 10px;
        }

        .latest-title {
            font-weight: 600;
            margin-bottom: 3px;
            font-size: 13px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .latest-meta {
            color: var(--text-muted);
            font-size: 11px;
            display: flex;
            justify-content: space-between;
        }

        .category-tag {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: 600;
            color: white;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .header { padding: 15px 20px; }
            .container { padding: 20px; }
            .search-grid { grid-template-columns: 1fr; }
            .preview-grid { grid-template-columns: 1fr; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            .membership-cards { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
            .link-item { grid-template-columns: 1fr; }
            .subtitle-item { grid-template-columns: 1fr; }
            .status-selector { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <i class="fas fa-film logo-icon"></i>
            <h1>مستورد <span>الأفلام</span></h1>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>
                الرئيسية
            </a>
            <a href="movies-import.php" class="nav-link active">
                <i class="fas fa-film"></i>
                الأفلام
            </a>
            <a href="series-import.php" class="nav-link">
                <i class="fas fa-tv"></i>
                المسلسلات
            </a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($message)): ?>
        <div class="notification <?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : ($messageType == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
            <?php echo $message; ?>
            <?php if (isset($duplicate_id)): ?>
            <a href="edit-movie.php?id=<?php echo $duplicate_id; ?>" style="color: white; margin-right: 10px; text-decoration: underline;">
                <i class="fas fa-edit"></i> تعديل
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- إحصائيات التصنيفات -->
        <div class="category-stats">
            <?php
            $all_categories = [
                'arabic' => ['name' => 'عربي', 'icon' => '🌍', 'color' => '#0e4620'],
                'egyptian' => ['name' => 'مصري', 'icon' => '🇪🇬', 'color' => '#ce1126'],
                'turkish' => ['name' => 'تركي', 'icon' => '🇹🇷', 'color' => '#9b2c2c'],
                'indian' => ['name' => 'هندي', 'icon' => '🇮🇳', 'color' => '#ff9933'],
                'asian' => ['name' => 'آسيوي', 'icon' => '🌏', 'color' => '#4a1d6d'],
                'foreign' => ['name' => 'أجنبي', 'icon' => '🌎', 'color' => '#1a4b8c']
            ];
            
            foreach ($all_categories as $cat_key => $cat_info):
                $count = 0;
                foreach ($category_stats as $stat) {
                    if ($stat['category'] == $cat_key) {
                        $count = $stat['count'];
                        break;
                    }
                }
            ?>
            <div class="stat-item <?php echo $cat_key; ?>">
                <div class="stat-icon"><?php echo $cat_info['icon']; ?></div>
                <div class="stat-count"><?php echo $count; ?></div>
                <div class="stat-label"><?php echo $cat_info['name']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-film stat-icon"></i>
                <div class="stat-value"><?php echo array_sum(array_column($category_stats, 'count')); ?></div>
                <div class="stat-label">إجمالي الأفلام</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-star stat-icon"></i>
                <div class="stat-value">4.8</div>
                <div class="stat-label">متوسط التقييم</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value">120</div>
                <div class="stat-label">متوسط المدة</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-tags stat-icon"></i>
                <div class="stat-value">6</div>
                <div class="stat-label">تصنيفات</div>
            </div>
        </div>

        <!-- قسم البحث المتقدم -->
        <div class="search-section">
            <div class="search-title">
                <i class="fas fa-search"></i>
                ابحث عن فيلم من TMDB
            </div>
            <form method="POST">
                <input type="hidden" name="search_tmdb" value="1">
                <div class="search-grid">
                    <input type="text" name="search_query" class="search-input" placeholder="اسم الفيلم..." required>
                    <input type="number" name="search_year" class="search-input" placeholder="السنة" style="width: 120px;">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                        بحث
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($search_results)): ?>
        <!-- نتائج البحث -->
        <div class="results-section">
            <div class="results-header">
                <div class="results-title">
                    <i class="fas fa-film"></i>
                    نتائج البحث
                </div>
                <div class="results-count">
                    <?php echo count($search_results); ?> فيلم
                </div>
            </div>
            <div class="movies-grid">
                <?php foreach ($search_results as $movie): ?>
                <div class="movie-card" onclick="window.location.href='?tmdb_id=<?php echo $movie['id']; ?>'">
                    <img src="https://image.tmdb.org/t/p/w200<?php echo $movie['poster_path'] ?? ''; ?>" 
                         class="movie-poster" 
                         alt="<?php echo htmlspecialchars($movie['title']); ?>"
                         onerror="this.src='https://via.placeholder.com/200x300?text=No+Poster'">
                    <div class="movie-info">
                        <div class="movie-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                        <div class="movie-year">
                            <?php echo substr($movie['release_date'] ?? 'غير معروف', 0, 4); ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($tmdb_data): ?>
        <!-- نموذج إضافة الفيلم -->
        <div class="add-form">
            <div class="form-header">
                <i class="fas fa-plus-circle" style="font-size: 30px; color: var(--primary);"></i>
                <h2>إضافة فيلم جديد</h2>
                <span class="category-badge" style="background: <?php echo $predicted_category['color'] ?? '#333'; ?>;">
                    <?php echo $predicted_category['icon'] ?? ''; ?> <?php echo $predicted_category['display_name'] ?? 'فيلم'; ?>
                </span>
            </div>

            <form method="POST" enctype="multipart/form-data" id="movieForm">
                <input type="hidden" name="add_movie" value="1">
                <input type="hidden" name="tmdb_id" value="<?php echo $tmdb_data['id'] ?? ''; ?>">
                <input type="hidden" name="imdb_id" value="<?php echo $tmdb_data['imdb_id'] ?? ''; ?>">
                <input type="hidden" name="homepage" value="<?php echo $tmdb_data['homepage'] ?? ''; ?>">
                <input type="hidden" name="vote_count" value="<?php echo $tmdb_data['vote_count'] ?? 0; ?>">
                <input type="hidden" name="popularity" value="<?php echo $tmdb_data['popularity'] ?? 0; ?>">
                <input type="hidden" name="poster_url" id="poster_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['poster_path'] ?? ''; ?>">
                <input type="hidden" name="backdrop_url" id="backdrop_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['backdrop_path'] ?? ''; ?>">

                <!-- معاينة الفيلم -->
                <div class="preview-section">
                    <div class="preview-grid">
                        <div class="poster-preview">
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $tmdb_data['poster_path'] ?? ''; ?>" 
                                 id="preview-poster"
                                 alt="<?php echo htmlspecialchars($tmdb_data['title'] ?? ''); ?>">
                            <button type="button" class="change-poster-btn" onclick="showPosterModal()">
                                <i class="fas fa-camera"></i>
                                تغيير
                            </button>
                        </div>
                        <div class="movie-details-preview">
                            <div class="movie-title-preview"><?php echo htmlspecialchars($tmdb_data['title'] ?? ''); ?></div>
                            <div class="movie-meta-preview">
                                <span class="meta-item">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo substr($tmdb_data['release_date'] ?? '', 0, 4); ?>
                                </span>
                                <span class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <?php echo $tmdb_data['runtime'] ?? '?'; ?> دقيقة
                                </span>
                                <span class="meta-item rating-badge">
                                    <i class="fas fa-star"></i>
                                    <?php echo number_format($tmdb_data['vote_average'] ?? 0, 1); ?>
                                </span>
                                <?php if ($tmdb_data['production_countries'][0]['name'] ?? ''): ?>
                                <span class="meta-item">
                                    <i class="fas fa-globe"></i>
                                    <?php echo $tmdb_data['production_countries'][0]['name']; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- التصنيف المتوقع -->
                            <div class="predicted-category" style="background: <?php echo $predicted_category['color'] ?? '#333'; ?>;">
                                <i class="fas fa-tag"></i>
                                سيتم إضافته إلى قسم: <?php echo $predicted_category['display_name'] ?? 'أفلام'; ?> <?php echo $predicted_category['icon'] ?? ''; ?>
                            </div>
                            
                            <p style="color: var(--text-muted); line-height: 1.6; font-size: 14px; margin-top: 10px;">
                                <?php echo htmlspecialchars($tmdb_data['overview']); ?>
                            </p>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-top: 10px;">
                                <?php 
                                $genres = array_column($tmdb_data['genres'] ?? [], 'name');
                                foreach ($genres as $genre): 
                                ?>
                                <span style="background: rgba(229,9,20,0.1); padding: 4px 12px; border-radius: 20px; color: var(--primary); font-size: 12px;">
                                    <?php echo $genre; ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويبات النموذج -->
                <div class="form-tabs">
                    <div class="form-tab active" onclick="showTab('basic')">📋 معلومات أساسية</div>
                    <div class="form-tab" onclick="showTab('membership')">👑 العضوية</div>
                    <div class="form-tab" onclick="showTab('cast')">🎭 طاقم العمل (<?php echo count($cast_data); ?>)</div>
                    <div class="form-tab" onclick="showTab('crew')">🎬 فريق الإنتاج (<?php echo count($crew_data); ?>)</div>
                    <div class="form-tab" onclick="showTab('watch')">🔗 روابط المشاهدة</div>
                    <div class="form-tab" onclick="showTab('download')">⬇️ روابط التحميل</div>
                    <div class="form-tab" onclick="showTab('subtitles')">📝 الترجمات</div>
                    <?php if (!empty($videos_data)): ?>
                    <div class="form-tab" onclick="showTab('videos')">🎬 الإعلانات</div>
                    <?php endif; ?>
                </div>

                <!-- تبويب المعلومات الأساسية -->
                <div id="basicTab" class="tab-content active">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">العنوان بالعربية</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($tmdb_data['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">العنوان الأصلي</label>
                            <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($tmdb_data['original_title']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">سنة الإنتاج</label>
                            <input type="number" name="year" class="form-control" value="<?php echo substr($tmdb_data['release_date'] ?? date('Y'), 0, 4); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">بلد الإنتاج</label>
                            <input type="text" name="country" class="form-control" value="<?php echo $tmdb_data['production_countries'][0]['name'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">اللغة الأصلية</label>
                            <select name="language" class="form-control">
                                <option value="ar" <?php echo ($tmdb_data['original_language'] == 'ar') ? 'selected' : ''; ?>>العربية</option>
                                <option value="en" <?php echo ($tmdb_data['original_language'] == 'en') ? 'selected' : ''; ?>>الإنجليزية</option>
                                <option value="tr" <?php echo ($tmdb_data['original_language'] == 'tr') ? 'selected' : ''; ?>>التركية</option>
                                <option value="hi" <?php echo ($tmdb_data['original_language'] == 'hi') ? 'selected' : ''; ?>>الهندية</option>
                                <option value="ko" <?php echo ($tmdb_data['original_language'] == 'ko') ? 'selected' : ''; ?>>الكورية</option>
                                <option value="ja" <?php echo ($tmdb_data['original_language'] == 'ja') ? 'selected' : ''; ?>>اليابانية</option>
                                <option value="zh" <?php echo ($tmdb_data['original_language'] == 'zh') ? 'selected' : ''; ?>>الصينية</option>
                                <option value="th" <?php echo ($tmdb_data['original_language'] == 'th') ? 'selected' : ''; ?>>التايلاندية</option>
                                <option value="vi" <?php echo ($tmdb_data['original_language'] == 'vi') ? 'selected' : ''; ?>>الفيتنامية</option>
                                <option value="fr" <?php echo ($tmdb_data['original_language'] == 'fr') ? 'selected' : ''; ?>>الفرنسية</option>
                                <option value="de" <?php echo ($tmdb_data['original_language'] == 'de') ? 'selected' : ''; ?>>الألمانية</option>
                                <option value="es" <?php echo ($tmdb_data['original_language'] == 'es') ? 'selected' : ''; ?>>الإسبانية</option>
                                <option value="ru" <?php echo ($tmdb_data['original_language'] == 'ru') ? 'selected' : ''; ?>>الروسية</option>
                                <option value="it" <?php echo ($tmdb_data['original_language'] == 'it') ? 'selected' : ''; ?>>الإيطالية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">التصنيفات</label>
                            <input type="text" name="genre" class="form-control" value="<?php echo implode('، ', array_column($tmdb_data['genres'] ?? [], 'name')); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">المدة (دقائق)</label>
                            <input type="number" name="duration" class="form-control" value="<?php echo $tmdb_data['runtime'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">تقييم TMDB</label>
                            <input type="number" step="0.1" name="imdb_rating" class="form-control" value="<?php echo $tmdb_data['vote_average'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label class="form-label">القصة</label>
                            <textarea name="overview" class="form-control" rows="4"><?php echo htmlspecialchars($tmdb_data['overview']); ?></textarea>
                        </div>
                    </div>

                    <!-- حالة الفيلم -->
                    <div style="margin-top: 20px;">
                        <label class="form-label">حالة الفيلم</label>
                        <div class="status-selector">
                            <div class="status-option selected" onclick="selectStatus('published')">
                                <i class="fas fa-check-circle"></i>
                                <div style="margin-top: 5px;">منشور</div>
                                <input type="radio" name="status" value="published" style="display: none;" checked>
                            </div>
                            <div class="status-option" onclick="selectStatus('draft')">
                                <i class="fas fa-pen"></i>
                                <div style="margin-top: 5px;">مسودة</div>
                                <input type="radio" name="status" value="draft" style="display: none;">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- تبويب العضوية -->
                <div id="membershipTab" class="tab-content">
                    <h3 style="margin-bottom: 15px; color: var(--primary); font-size: 18px;">اختر مستوى العضوية المطلوب للمشاهدة</h3>
                    <div class="membership-cards">
                        <?php foreach ($membership_levels as $key => $level): ?>
                        <div class="membership-card" onclick="selectMembership('<?php echo $key; ?>')">
                            <div class="membership-icon"><?php echo $level['icon']; ?></div>
                            <div class="membership-name" style="color: <?php echo $level['color']; ?>"><?php echo $level['name']; ?></div>
                            <div class="membership-desc"><?php echo $level['desc']; ?></div>
                            <div class="membership-badge" style="background: <?php echo $level['color']; ?>; color: <?php echo $key == 'vip' ? '#000' : '#fff'; ?>">
                                <?php echo $level['badge']; ?>
                            </div>
                            <input type="radio" name="membership_level" value="<?php echo $key; ?>" style="display: none;" <?php echo $key == 'basic' ? 'checked' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- تبويب طاقم العمل -->
                <div id="castTab" class="tab-content">
                    <div class="cast-section">
                        <div class="section-header">
                            <div class="section-title">
                                <i class="fas fa-star"></i>
                                طاقم التمثيل (<?php echo count($cast_data); ?> ممثل)
                            </div>
                            <button type="button" class="add-link-btn" onclick="addCastMember()">
                                <i class="fas fa-plus"></i>
                                إضافة ممثل
                            </button>
                        </div>
                        
                        <div class="cast-grid" id="cast-container">
                            <?php foreach ($cast_data as $index => $cast): ?>
                            <div class="cast-card" id="cast-<?php echo $index; ?>" data-person-id="<?php echo $cast['id']; ?>">
                                <div class="cast-image-container">
                                    <img src="https://image.tmdb.org/t/p/w200<?php echo $cast['profile_path'] ?? ''; ?>" 
                                         class="cast-image" 
                                         id="cast-img-<?php echo $index; ?>"
                                         alt="<?php echo htmlspecialchars($cast['name']); ?>"
                                         onerror="this.src='https://via.placeholder.com/200x200?text=No+Image'">
                                    <div class="change-cast-image-btn" onclick="document.getElementById('cast-file-<?php echo $index; ?>').click()">
                                        <i class="fas fa-camera"></i>
                                    </div>
                                    <div class="remove-cast-btn" onclick="removeCast(<?php echo $index; ?>)">
                                        <i class="fas fa-times"></i>
                                    </div>
                                </div>
                                <div class="cast-info">
                                    <div class="cast-name"><?php echo htmlspecialchars($cast['name']); ?></div>
                                    <div class="cast-character"><?php echo htmlspecialchars($cast['character']); ?></div>
                                    <span class="cast-order">الترتيب <?php echo $cast['order'] + 1; ?></span>
                                </div>
                                <input type="file" id="cast-file-<?php echo $index; ?>" style="display: none;" accept="image/*" onchange="uploadCastImage(this, <?php echo $index; ?>)">
                                <input type="hidden" name="cast[<?php echo $index; ?>][index]" value="<?php echo $index; ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][person_id]" value="<?php echo $cast['id']; ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($cast['name']); ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][character]" value="<?php echo htmlspecialchars($cast['character']); ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][order]" value="<?php echo $cast['order']; ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][department]" value="<?php echo $cast['known_for_department'] ?? 'Acting'; ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][popularity]" value="<?php echo $cast['popularity'] ?? 0; ?>">
                                <input type="hidden" name="cast[<?php echo $index; ?>][profile_path]" id="cast-profile-<?php echo $index; ?>" value="<?php echo $cast['profile_path'] ? 'https://image.tmdb.org/t/p/original' . $cast['profile_path'] : ''; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- تبويب فريق الإنتاج -->
                <div id="crewTab" class="tab-content">
                    <div class="crew-section">
                        <div class="section-title" style="margin-bottom: 15px;">
                            <i class="fas fa-video"></i>
                            فريق الإنتاج (<?php echo count($crew_data); ?>)
                        </div>
                        <div class="crew-grid" id="crew-container">
                            <?php foreach ($crew_data as $index => $crew): ?>
                            <div class="crew-item" id="crew-<?php echo $index; ?>">
                                <div class="crew-name"><?php echo htmlspecialchars($crew['name']); ?></div>
                                <div class="crew-job"><?php echo htmlspecialchars($crew['job']); ?></div>
                                <span class="crew-department"><?php echo htmlspecialchars($crew['department']); ?></span>
                                <input type="hidden" name="crew[<?php echo $index; ?>][person_id]" value="<?php echo $crew['id']; ?>">
                                <input type="hidden" name="crew[<?php echo $index; ?>][name]" value="<?php echo htmlspecialchars($crew['name']); ?>">
                                <input type="hidden" name="crew[<?php echo $index; ?>][job]" value="<?php echo htmlspecialchars($crew['job']); ?>">
                                <input type="hidden" name="crew[<?php echo $index; ?>][department]" value="<?php echo htmlspecialchars($crew['department']); ?>">
                                <input type="hidden" name="crew[<?php echo $index; ?>][profile_path]" value="<?php echo $crew['profile_path'] ? 'https://image.tmdb.org/t/p/original' . $crew['profile_path'] : ''; ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- تبويب روابط المشاهدة -->
                <div id="watchTab" class="tab-content">
                    <div class="links-container">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="color: var(--primary); font-size: 16px;">روابط المشاهدة</h3>
                            <button type="button" class="add-link-btn" onclick="addWatchLink()">
                                <i class="fas fa-plus"></i>
                                إضافة رابط
                            </button>
                        </div>
                        <div id="watch-links-container"></div>
                    </div>
                </div>

                <!-- تبويب روابط التحميل -->
                <div id="downloadTab" class="tab-content">
                    <div class="links-container">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="color: var(--primary); font-size: 16px;">روابط التحميل</h3>
                            <button type="button" class="add-link-btn" onclick="addDownloadLink()">
                                <i class="fas fa-plus"></i>
                                إضافة رابط
                            </button>
                        </div>
                        <div id="download-links-container"></div>
                    </div>
                </div>

                <!-- تبويب الترجمات -->
                <div id="subtitlesTab" class="tab-content">
                    <div class="subtitles-container">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="color: var(--primary); font-size: 16px;">الترجمات</h3>
                            <button type="button" class="add-link-btn" onclick="addSubtitle()">
                                <i class="fas fa-plus"></i>
                                إضافة ترجمة
                            </button>
                        </div>
                        <div id="subtitles-container"></div>
                    </div>
                </div>

                <!-- تبويب الإعلانات -->
                <?php if (!empty($videos_data)): ?>
                <div id="videosTab" class="tab-content">
                    <div class="videos-section">
                        <h3 style="margin-bottom: 15px; color: var(--primary); font-size: 18px;">
                            <i class="fab fa-youtube"></i>
                            الإعلانات (<?php echo count($videos_data); ?>)
                        </h3>
                        <div class="videos-grid">
                            <?php foreach ($videos_data as $video): ?>
                            <div class="video-card">
                                <img src="https://img.youtube.com/vi/<?php echo $video['key']; ?>/hqdefault.jpg" 
                                     class="video-thumbnail" 
                                     alt="<?php echo htmlspecialchars($video['name']); ?>">
                                <div class="video-info">
                                    <div class="video-title"><?php echo htmlspecialchars($video['name']); ?></div>
                                    <span class="video-type"><?php echo $video['type']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- أزرار الإجراءات -->
                <div class="action-buttons">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i>
                        حفظ الفيلم
                    </button>
                    <button type="button" class="btn-secondary" onclick="window.location.href='movies-import.php'">
                        <i class="fas fa-times"></i>
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- أحدث الأفلام المضافة -->
        
    <!-- مودال تغيير البوستر الرئيسي -->
    <div id="posterModal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3 class="modal-title">تغيير صورة الفيلم</h3>
            <form id="posterUploadForm">
                <input type="file" id="posterFile" class="file-input" accept="image/*" onchange="previewPoster(this)">
                <div style="text-align: center; margin-bottom: 15px;">
                    <img id="posterPreview" src="#" style="max-width: 150px; max-height: 200px; display: none; border-radius: 8px;">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-primary" onclick="uploadPoster()" style="flex: 1; padding: 10px;">
                        <i class="fas fa-upload"></i>
                        رفع
                    </button>
                    <button type="button" class="btn-secondary" onclick="closePosterModal()" style="flex: 1; padding: 10px;">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let watchLinkCount = 0;
        let downloadLinkCount = 0;
        let subtitleCount = 0;
        let castCount = <?php echo count($cast_data); ?>;

        function showTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // خريطة أسم التبويب إلى معرف العنصر الفعلي (تحافظ على الحروف الصغيرة)
            const tabMap = {
                'basic': 'basicTab',
                'membership': 'membershipTab',
                'cast': 'castTab',
                'crew': 'crewTab',
                'watch': 'watchTab',
                'download': 'downloadTab',
                'subtitles': 'subtitlesTab',
                'videos': 'videosTab'
            };
            
            const targetId = tabMap[tab];
            if (targetId) {
                const el = document.getElementById(targetId);
                if (el) {
                    el.classList.add('active');
                }
            }
            
            // تفعيل الزر المناسب
            const tabs = Object.keys(tabMap);
            const index = tabs.indexOf(tab);
            if (index !== -1) {
                const btn = document.querySelectorAll('.form-tab')[index];
                if (btn) btn.classList.add('active');
            }
        }

        function selectMembership(level) {
            document.querySelectorAll('.membership-card').forEach(card => {
                card.classList.remove('selected');
                card.style.borderColor = 'var(--border)';
            });
            event.currentTarget.classList.add('selected');
            event.currentTarget.style.borderColor = 'var(--primary)';
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }

        function selectStatus(status) {
            document.querySelectorAll('.status-option').forEach(option => {
                option.classList.remove('selected');
                option.style.borderColor = 'var(--border)';
                option.style.background = 'var(--dark-light)';
            });
            event.currentTarget.classList.add('selected');
            event.currentTarget.style.borderColor = 'var(--primary)';
            event.currentTarget.style.background = 'rgba(229,9,20,0.1)';
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }

        function addWatchLink() {
            watchLinkCount++;
            const container = document.getElementById('watch-links-container');
            if (!container) return;
            
            const html = `
                <div class="link-item" id="watch-link-${watchLinkCount}">
                    <select name="watch_links[${watchLinkCount}][server]" class="form-control" onchange="updateServerDetails(this, 'watch', ${watchLinkCount})">
                        <option value="">اختر سيرفر المشاهدة</option>
                        <?php foreach ($available_servers['watch'] as $key => $server): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" 
                                data-quality="<?php echo htmlspecialchars($server['quality']); ?>" 
                                data-language="<?php echo htmlspecialchars($server['language']); ?>">
                            <?php echo htmlspecialchars($server['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="watch_links[${watchLinkCount}][url]" class="form-control" placeholder="رابط المشاهدة" required>
                    <select name="watch_links[${watchLinkCount}][quality]" class="form-control" id="watch-quality-${watchLinkCount}">
                        <option value="4K">4K UHD</option>
                        <option value="1080p" selected>1080p HD</option>
                        <option value="720p">720p HD</option>
                        <option value="480p">480p</option>
                    </select>
                    <select name="watch_links[${watchLinkCount}][lang]" class="form-control" id="watch-lang-${watchLinkCount}">
                        <option value="arabic" selected>🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                        <option value="turkish">🇹🇷 Türkçe</option>
                        <option value="french">🇫🇷 Français</option>
                        <option value="german">🇩🇪 Deutsch</option>
                        <option value="spanish">🇪🇸 Español</option>
                    </select>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function addDownloadLink() {
            downloadLinkCount++;
            const container = document.getElementById('download-links-container');
            if (!container) return;
            
            const html = `
                <div class="link-item" id="download-link-${downloadLinkCount}">
                    <select name="download_links[${downloadLinkCount}][server]" class="form-control" onchange="updateServerDetails(this, 'download', ${downloadLinkCount})">
                        <option value="">اختر سيرفر التحميل</option>
                        <?php foreach ($available_servers['download'] as $key => $server): ?>
                        <option value="<?php echo htmlspecialchars($key); ?>" 
                                data-quality="<?php echo htmlspecialchars($server['quality']); ?>" 
                                data-size="<?php echo htmlspecialchars($server['size']); ?>">
                            <?php echo htmlspecialchars($server['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="url" name="download_links[${downloadLinkCount}][url]" class="form-control" placeholder="رابط التحميل" required>
                    <select name="download_links[${downloadLinkCount}][quality]" class="form-control" id="download-quality-${downloadLinkCount}">
                        <option value="4K">4K UHD</option>
                        <option value="1080p" selected>1080p HD</option>
                        <option value="720p">720p HD</option>
                        <option value="480p">480p</option>
                    </select>
                    <input type="text" name="download_links[${downloadLinkCount}][size]" class="form-control" placeholder="الحجم" id="download-size-${downloadLinkCount}" value="1.8 GB">
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function updateServerDetails(selectElement, type, index) {
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const quality = selectedOption.getAttribute('data-quality');
            const language = selectedOption.getAttribute('data-language');
            const size = selectedOption.getAttribute('data-size');
            
            if (type === 'watch') {
                // تحديث الجودة
                const qualitySelect = document.getElementById(`watch-quality-${index}`);
                if (qualitySelect && quality) {
                    // تحويل "4K UHD" إلى "4K" و "1080p HD" إلى "1080p"
                    let qualityValue = quality;
                    if (quality.includes('4K')) {
                        qualityValue = '4K';
                    } else if (quality.includes('1080p')) {
                        qualityValue = '1080p';
                    } else if (quality.includes('720p')) {
                        qualityValue = '720p';
                    } else if (quality.includes('480p')) {
                        qualityValue = '480p';
                    }
                    qualitySelect.value = qualityValue;
                }
                
                // تحديث اللغة
                const langSelect = document.getElementById(`watch-lang-${index}`);
                if (langSelect && language) {
                    if (language === 'العربية') {
                        langSelect.value = 'arabic';
                    } else if (language === 'English') {
                        langSelect.value = 'english';
                    }
                }
            } else if (type === 'download') {
                // تحديث الجودة
                const qualitySelect = document.getElementById(`download-quality-${index}`);
                if (qualitySelect && quality) {
                    let qualityValue = quality;
                    if (quality.includes('4K')) {
                        qualityValue = '4K';
                    } else if (quality.includes('1080p')) {
                        qualityValue = '1080p';
                    } else if (quality.includes('720p')) {
                        qualityValue = '720p';
                    } else if (quality.includes('480p')) {
                        qualityValue = '480p';
                    }
                    qualitySelect.value = qualityValue;
                }
                
                // تحديث الحجم
                const sizeInput = document.getElementById(`download-size-${index}`);
                if (sizeInput && size) {
                    sizeInput.value = size;
                }
            }
        }

        function addSubtitle() {
            subtitleCount++;
            const container = document.getElementById('subtitles-container');
            if (!container) return;
            
            const html = `
                <div class="subtitle-item" id="subtitle-${subtitleCount}">
                    <select name="subtitles[${subtitleCount}][language_code]" class="form-control">
                        <?php foreach ($subtitle_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="file" name="subtitle_files[${subtitleCount}]" class="form-control" accept=".srt,.vtt,.ass">
                    <input type="url" name="subtitles[${subtitleCount}][url]" class="form-control" placeholder="أو رابط خارجي">
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="subtitles[${subtitleCount}][is_default]" value="1"> افتراضي
                    </label>
                    <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function addCastMember() {
            castCount++;
            const container = document.getElementById('cast-container');
            if (!container) return;
            
            const html = `
                <div class="cast-card" id="cast-${castCount}">
                    <div class="cast-image-container">
                        <img src="https://via.placeholder.com/200x200?text=New+Cast" 
                             class="cast-image" 
                             id="cast-img-${castCount}"
                             alt="ممثل جديد">
                        <div class="change-cast-image-btn" onclick="document.getElementById('cast-file-${castCount}').click()">
                            <i class="fas fa-camera"></i>
                        </div>
                        <div class="remove-cast-btn" onclick="removeCast(${castCount})">
                            <i class="fas fa-times"></i>
                        </div>
                    </div>
                    <div class="cast-info">
                        <input type="text" name="cast[${castCount}][name]" class="form-control" placeholder="اسم الممثل" style="margin-bottom: 5px;">
                        <input type="text" name="cast[${castCount}][character]" class="form-control" placeholder="اسم الشخصية" style="margin-bottom: 5px;">
                        <input type="number" name="cast[${castCount}][order]" class="form-control" placeholder="الترتيب" value="999">
                        <input type="file" id="cast-file-${castCount}" style="display: none;" accept="image/*" onchange="uploadCastImage(this, ${castCount})">
                        <input type="hidden" name="cast[${castCount}][profile_path]" id="cast-profile-${castCount}" value="">
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);
        }

        function uploadCastImage(input, index) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById(`cast-img-${index}`);
                    if (img) img.src = e.target.result;
                    showNotification('تم اختيار الصورة بنجاح');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removeCast(index) {
            if (confirm('هل أنت متأكد من حذف هذا الممثل؟')) {
                const element = document.getElementById(`cast-${index}`);
                if (element) element.remove();
            }
        }

        // دوال البوستر
        function showPosterModal() {
            document.getElementById('posterModal').style.display = 'flex';
        }

        function closePosterModal() {
            document.getElementById('posterModal').style.display = 'none';
        }

        function previewPoster(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('posterPreview');
                    if (preview) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function uploadPoster() {
            const fileInput = document.getElementById('posterFile');
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewPoster = document.getElementById('preview-poster');
                    if (previewPoster) previewPoster.src = e.target.result;
                    closePosterModal();
                    showNotification('تم تغيير الصورة بنجاح');
                }
                reader.readAsDataURL(fileInput.files[0]);
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

        // التهيئة عند تحميل الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ الصفحة جاهزة');
            console.log('🎭 عدد الممثلين: <?php echo count($cast_data); ?>');
            
            // التأكد من أن التبويب الأول نشط
            const basicTab = document.getElementById('basicTab');
            if (basicTab) {
                basicTab.classList.add('active');
            }
            
            // تفعيل أول زر تبويب
            const firstTabButton = document.querySelector('.form-tab');
            if (firstTabButton) {
                firstTabButton.classList.add('active');
            }
            
            // إغلاق المودال عند النقر خارجها
            window.onclick = function(event) {
                const modal = document.getElementById('posterModal');
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            }
        });

        // إخفاء الإشعارات القديمة
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