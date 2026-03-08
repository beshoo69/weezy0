    <?php
    // admin/series-import.php - نظام استيراد المسلسلات المتطور مع طاقم العمل والإعلام
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
    // دوال تحديد نوع المسلسل (للتوزيع التلقائي)
    // =============================================
    function determineSeriesCategory($series_data) {
        $country = is_array($series_data) ? ($series_data['country'] ?? '') : '';
        $language = is_array($series_data) ? ($series_data['language'] ?? '') : '';
        $title = is_array($series_data) ? ($series_data['title'] ?? '') : '';
        $genre = is_array($series_data) ? ($series_data['genre'] ?? '') : '';
        
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
            'كوري', 'كورية', 'korean', 'korea', 'seoul', 'k-drama',
            'ياباني', 'يابانية', 'japanese', 'japan', 'tokyo', 'anime',
            'صيني', 'صينية', 'chinese', 'china', 'hong kong', 'تايواني',
            'تايلاندي', 'thai', 'thailand', 'vietnamese', 'indonesian',
            'آسيوي', 'asian', 'شرق آسيوي'
        ];
        $anime_keywords = ['انمي', 'أنمي', 'anime', 'رسوم متحركة', 'كرتون', 'cartoon', 'animation', 'manga'];
        
        // 1. التحقق من الأنمي
        foreach ($anime_keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
                return [
                    'category' => 'anime',
                    'display_name' => 'أنمي',
                    'icon' => '🇯🇵',
                    'color' => '#e50914'
                ];
            }
        }
        
        // 2. التحقق من المصري
        foreach ($egyptian_keywords as $keyword) {
            if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
                return [
                    'category' => 'egyptian',
                    'display_name' => 'مسلسل مصري',
                    'icon' => '🇪🇬',
                    'color' => '#ce1126'
                ];
            }
        }
        
        // 3. التحقق من العربي
        foreach ($arab_countries as $arab_country) {
            if (strpos($country_lower, $arab_country) !== false) {
                return [
                    'category' => 'arabic',
                    'display_name' => 'مسلسل عربي',
                    'icon' => '🌍',
                    'color' => '#0e4620'
                ];
            }
        }
        
        foreach ($arabic_keywords as $keyword) {
            if (strpos($genre_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false) {
                return [
                    'category' => 'arabic',
                    'display_name' => 'مسلسل عربي',
                    'icon' => '🌍',
                    'color' => '#0e4620'
                ];
            }
        }
        
        if ($language_lower == 'ar') {
            return [
                'category' => 'arabic',
                'display_name' => 'مسلسل عربي',
                'icon' => '🌍',
                'color' => '#0e4620'
            ];
        }
        
        // 4. التحقق من التركي
        foreach ($turkish_keywords as $keyword) {
            if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
                return [
                    'category' => 'turkish',
                    'display_name' => 'مسلسل تركي',
                    'icon' => '🇹🇷',
                    'color' => '#9b2c2c'
                ];
            }
        }
        
        if ($language_lower == 'tr') {
            return [
                'category' => 'turkish',
                'display_name' => 'مسلسل تركي',
                'icon' => '🇹🇷',
                'color' => '#9b2c2c'
            ];
        }
        
        // 5. التحقق من الهندي
        foreach ($indian_keywords as $keyword) {
            if (strpos($country_lower, $keyword) !== false || strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
                return [
                    'category' => 'indian',
                    'display_name' => 'مسلسل هندي',
                    'icon' => '🇮🇳',
                    'color' => '#ff9933'
                ];
            }
        }
        
        if (in_array($language_lower, ['hi', 'ur', 'pa', 'bn'])) {
            return [
                'category' => 'indian',
                'display_name' => 'مسلسل هندي',
                'icon' => '🇮🇳',
                'color' => '#ff9933'
            ];
        }
        
        // 6. التحقق من الآسيوي
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
                    'display_name' => 'مسلسل آسيوي',
                    'icon' => '🌏',
                    'color' => '#4a1d6d'
                ];
            }
        }
        
        foreach ($asian_keywords as $keyword) {
            if (strpos($title_lower, $keyword) !== false || strpos($genre_lower, $keyword) !== false) {
                return [
                    'category' => 'asian',
                    'display_name' => 'مسلسل آسيوي',
                    'icon' => '🌏',
                    'color' => '#4a1d6d'
                ];
            }
        }
        
        if (in_array($language_lower, $asian_languages)) {
            return [
                'category' => 'asian',
                'display_name' => 'مسلسل آسيوي',
                'icon' => '🌏',
                'color' => '#4a1d6d'
            ];
        }
        
        // 7. باقي المسلسلات أجنبية
        return [
            'category' => 'foreign',
            'display_name' => 'مسلسل أجنبي',
            'icon' => '🌎',
            'color' => '#1a4b8c'
        ];
    }

    /**
     * إضافة حقول التصنيف إلى جدول series
     */
    function ensureSeriesCategoryColumns($pdo) {
        try {
            $check = $pdo->query("SHOW COLUMNS FROM series LIKE 'category'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE series ADD COLUMN category VARCHAR(50) NULL AFTER genre");
                $pdo->exec("ALTER TABLE series ADD COLUMN category_display VARCHAR(100) NULL AFTER category");
                $pdo->exec("ALTER TABLE series ADD COLUMN category_icon VARCHAR(10) NULL AFTER category_display");
                $pdo->exec("ALTER TABLE series ADD COLUMN category_color VARCHAR(20) NULL AFTER category_icon");
                $pdo->exec("CREATE INDEX idx_category ON series(category)");
            }
        } catch (Exception $e) {}
    }

    /**
     * التأكد من وجود جداول طاقم العمل
     */
    function ensureCastTables($pdo) {
        try {
            // جدول الممثلين
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cast (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    series_id INT NOT NULL,
                    person_id INT NULL,
                    name VARCHAR(255) NOT NULL,
                    character_name VARCHAR(255),
                    profile_path VARCHAR(500),
                    order_number INT DEFAULT 999,
                    department VARCHAR(100),
                    popularity FLOAT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_series (series_id),
                    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // جدول فريق العمل
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS crew (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    series_id INT NOT NULL,
                    person_id INT NULL,
                    name VARCHAR(255) NOT NULL,
                    job VARCHAR(255),
                    department VARCHAR(100),
                    profile_path VARCHAR(500),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_series (series_id),
                    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // جدول صور الممثلين (للملفات المرفوعة محلياً)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS cast_images (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cast_id INT NOT NULL,
                    image_path VARCHAR(500) NOT NULL,
                    is_primary BOOLEAN DEFAULT FALSE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (cast_id) REFERENCES cast(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
        } catch (Exception $e) {}
    }

    /**
     * تأكد من وجود فهرس فريد لحماية الحلقات من التكرار
     */
    function ensureEpisodeUniqueIndex($pdo) {
        try {
            // حذف النسخ المكررة القديمة عند وجودها
            $pdo->exec("\
                DELETE e1 FROM episodes e1
                INNER JOIN episodes e2
                    ON e1.series_id = e2.series_id
                   AND e1.season_number = e2.season_number
                   AND e1.episode_number = e2.episode_number
                   AND e1.id > e2.id
            ");
            $check = $pdo->query("SHOW INDEX FROM episodes WHERE Key_name = 'uniq_series_season_episode'");
            if ($check->rowCount() == 0) {
                $pdo->exec("ALTER TABLE episodes ADD UNIQUE KEY uniq_series_season_episode (series_id, season_number, episode_number)");
            }
        } catch (Exception $e) {
            // ignore if table doesn't exist yet or other error
        }
    }

    // تنفيذ دوال التأكد من الجداول
    ensureSeriesCategoryColumns($pdo);
    ensureCastTables($pdo);
    ensureEpisodeUniqueIndex($pdo);

    // جلب أحدث المسلسلات
    $latest_series = $pdo->query("
        SELECT s.*, 
            (SELECT COUNT(*) FROM cast WHERE series_id = s.id) as cast_count,
            (SELECT COUNT(*) FROM seasons WHERE series_id = s.id) as seasons_count
        FROM series s 
        ORDER BY s.id DESC 
        LIMIT 12
    ")->fetchAll();

    // إحصائيات التصنيفات
    $category_stats = $pdo->query("
        SELECT category, COUNT(*) as count, category_display
        FROM series WHERE category IS NOT NULL GROUP BY category
    ")->fetchAll();

    /**
     * جلب تفاصيل الموسم من TMDB
     */
    function fetchSeasonDetails($tmdb_id, $season_number) {
        $url = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_number}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    /**
     * جلب تفاصيل شخص (ممثل) من TMDB
     */
    function fetchPersonDetails($person_id) {
        $url = "https://api.themoviedb.org/3/person/{$person_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=images,external_ids";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }

    // معالجة جلب التفاصيل من TMDB
    $tmdb_data = null;
    $selected_id = isset($_GET['tmdb_id']) ? $_GET['tmdb_id'] : null;
    $seasons_data = [];
    $cast_data = [];
    $crew_data = [];
    $search_results = [];
    $videos_data = [];
    $images_data = [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_tmdb'])) {
        $query = $_POST['search_query'] ?? '';
        $year = $_POST['search_year'] ?? '';
        
        if (!empty($query)) {
            $search_url = "https://api.themoviedb.org/3/search/tv?api_key=" . TMDB_API_KEY . "&query=" . urlencode($query) . "&language=ar-SA";
            if (!empty($year)) {
                $search_url .= "&first_air_date_year=" . $year;
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
        // جلب بيانات المسلسل الأساسية مع جميع التفاصيل
        $url = "https://api.themoviedb.org/3/tv/{$selected_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,videos,images,external_ids,keywords,content_ratings,aggregate_credits";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $tmdb_data = json_decode($response, true);
            
            // تحديد تصنيف المسلسل
            if ($tmdb_data && is_array($tmdb_data)) {
                $country = $tmdb_data['production_countries'][0]['name'] ?? '';
                $language = $tmdb_data['original_language'] ?? '';
                $genres = array_column($tmdb_data['genres'] ?? [], 'name');
                $genre_str = implode('، ', $genres);
                
                $series_data_for_category = [
                    'country' => $country,
                    'language' => $language,
                    'title' => $tmdb_data['name'] ?? '',
                    'genre' => $genre_str
                ];
                
                $predicted_category = determineSeriesCategory($series_data_for_category);
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
                $important_roles = ['Director', 'Producer', 'Writer', 'Creator', 'Executive Producer', 'Screenplay', 'Novel'];
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
            
            // جلب المواسم والحلقات
            // جلب المواسم والحلقات
if (isset($tmdb_data['seasons'])) {
    foreach ($tmdb_data['seasons'] as $season) {
        if ($season['season_number'] > 0) {
            
            // استخدام عدد الحلقات من بيانات الموسم الأساسية أولاً
            $episode_count = $season['episode_count'] ?? 0;
            $episodes = [];
            
            // محاولة جلب تفاصيل الموسم للحصول على قائمة الحلقات
            $season_details = fetchSeasonDetails($selected_id, $season['season_number']);
            
            if ($season_details && isset($season_details['episodes'])) {
                foreach ($season_details['episodes'] as $ep) {
                    $episodes[] = [
                        'number' => $ep['episode_number'],
                        'name' => $ep['name'],
                        'overview' => $ep['overview'] ?? '',
                        'still_path' => isset($ep['still_path']) ? 'https://image.tmdb.org/t/p/w500' . $ep['still_path'] : '',
                        'air_date' => $ep['air_date'] ?? null,
                        'vote_average' => $ep['vote_average'] ?? 0
                    ];
                }
                // تحديث عدد الحلقات بناءً على التفاصيل المجلوبة
                $episode_count = count($episodes);
            }
            
            $seasons_data[] = [
                'number' => $season['season_number'],
                'name' => $season['name'],
                'overview' => $season['overview'] ?? '',
                'poster' => isset($season['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $season['poster_path'] : '',
                'air_date' => $season['air_date'] ?? null,
                'episodes' => $episodes,
                'episode_count' => $episode_count
            ];
        }
    }
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

    // معالجة إضافة المسلسل
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_series'])) {
        $title = $_POST['title'] ?? '';
        $title_en = $_POST['title_en'] ?? '';
        $overview = $_POST['overview'] ?? '';
        $year = $_POST['year'] ?? date('Y');
        $country = $_POST['country'] ?? '';
        $language = $_POST['language'] ?? 'ar';
        $genre = $_POST['genre'] ?? '';
        $imdb_rating = (float)($_POST['imdb_rating'] ?? 0);
        $membership_level = $_POST['membership_level'] ?? 'basic';
        $tmdb_id = $_POST['tmdb_id'] ?? '';
        $status = $_POST['status'] ?? 'returning';
        $first_air_date = $_POST['first_air_date'] ?? null;
        $last_air_date = $_POST['last_air_date'] ?? null;
        $number_of_seasons = (int)($_POST['number_of_seasons'] ?? 0);
        $number_of_episodes = (int)($_POST['number_of_episodes'] ?? 0);
        $imdb_id = $_POST['imdb_id'] ?? '';
        $homepage = $_POST['homepage'] ?? '';
        $vote_count = (int)($_POST['vote_count'] ?? 0);
        $popularity = (float)($_POST['popularity'] ?? 0);
        
        // تحديد تصنيف المسلسل
        $series_data = [
            'country' => $country,
            'language' => $language,
            'title' => $title,
            'genre' => $genre
        ];
        $category_info = determineSeriesCategory($series_data);
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
            // حفظ البوستر من الرابط
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
        
        try {
            // التحقق من وجود المسلسل مسبقاً
            $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ? OR (title = ? AND year = ?)");
            $check->execute([$tmdb_id, $title, $year]);
            $existing = $check->fetch();
            
            if ($existing) {
                $message = "⚠️ هذا المسلسل موجود مسبقاً!";
                $messageType = "warning";
                $duplicate_id = $existing['id'];
            } else {
                $pdo->beginTransaction();
                
                // إضافة المسلسل
                $sql = "INSERT INTO series (
                    tmdb_id, imdb_id, title, title_en, description, poster, backdrop, 
                    year, country, language, genre, category, category_display, category_icon, category_color,
                    imdb_rating, vote_count, popularity, membership_level, 
                    status, first_air_date, last_air_date, number_of_seasons, 
                    number_of_episodes, homepage, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $tmdb_id, $imdb_id, $title, $title_en, $overview, 
                    $local_poster, $local_backdrop, $year, $country, $language, 
                    $genre, $category, $category_display, $category_icon, $category_color,
                    $imdb_rating, $vote_count, $popularity, $membership_level, 
                    $status, $first_air_date, $last_air_date, $number_of_seasons, 
                    $number_of_episodes, $homepage
                ]);
                
                $series_id = $pdo->lastInsertId();
                
                // إضافة المواسم والحلقات
                if (isset($_POST['seasons']) && is_array($_POST['seasons'])) {
                    foreach ($_POST['seasons'] as $season_data) {
                        if (!empty($season_data['number'])) {
                            // إضافة الموسم
                            $season_sql = "INSERT INTO seasons (
                                series_id, season_number, name, overview, poster, air_date, episode_count
                            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                            $season_stmt = $pdo->prepare($season_sql);
                            $season_stmt->execute([
                                $series_id,
                                $season_data['number'],
                                $season_data['name'] ?? "الموسم {$season_data['number']}",
                                $season_data['overview'] ?? '',
                                $season_data['poster'] ?? '',
                                $season_data['air_date'] ?? null,
                                $season_data['episode_count'] ?? 0
                            ]);
                            
                            // إضافة حلقات الموسم
                            if (isset($season_data['episodes']) && is_array($season_data['episodes'])) {
                                foreach ($season_data['episodes'] as $episode_data) {
                                    if (empty($episode_data['number'])) {
                                        continue;
                                    }

                                    // جمع روابط المشاهدة لهذه الحلقة (إذا كانت موجودة)
                                    $watch_servers = [];
                                    if (isset($episode_data['watch_links']) && is_array($episode_data['watch_links'])) {
                                        foreach ($episode_data['watch_links'] as $link) {
                                            if (!empty($link['url'])) {
                                                $watch_servers[] = [
                                                    'name' => $link['name'] ?? 'سيرفر مشاهدة',
                                                    'url' => $link['url'],
                                                    'lang' => $link['lang'] ?? 'arabic',
                                                    'quality' => $link['quality'] ?? 'HD'
                                                ];
                                            }
                                        }
                                    }

                                    // جمع روابط التحميل لهذه الحلقة (إذا كانت موجودة)
                                    $download_servers = [];
                                    if (isset($episode_data['download_links']) && is_array($episode_data['download_links'])) {
                                        foreach ($episode_data['download_links'] as $link) {
                                            if (!empty($link['url'])) {
                                                $download_servers[] = [
                                                    'name' => $link['name'] ?? 'سيرفر تحميل',
                                                    'url' => $link['url'],
                                                    'quality' => $link['quality'] ?? 'HD',
                                                    'size' => $link['size'] ?? ''
                                                ];
                                            }
                                        }
                                    }

                                    // تحويل المصفوفات إلى JSON
                                    $watch_json = !empty($watch_servers) ? json_encode($watch_servers, JSON_UNESCAPED_UNICODE) : null;
                                    $download_json = !empty($download_servers) ? json_encode($download_servers, JSON_UNESCAPED_UNICODE) : null;

                                    // تحقق مما إذا كانت هذه الحلقة موجودة بالفعل لتجنّب التكرار
                                    $checkEp = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ? LIMIT 1");
                                    $checkEp->execute([$series_id, $season_data['number'], $episode_data['number']]);
                                    $existingEpisode = $checkEp->fetch();

                                    if ($existingEpisode) {
                                        // تحديث السجل إذا كانت هناك بيانات جديدة
                                        $update_sql = "UPDATE episodes SET 
                                            title = ?,
                                            title_en = ?,
                                            description = ?,
                                            still_path = ?,
                                            air_date = ?,
                                            runtime = ?,
                                            vote_average = ?,
                                            watch_servers = ?,
                                            download_servers = ?
                                            WHERE id = ?";
                                        $update_stmt = $pdo->prepare($update_sql);
                                        $update_stmt->execute([
                                            $episode_data['title'] ?? "الحلقة {$episode_data['number']}",
                                            $episode_data['title_en'] ?? '',
                                            $episode_data['overview'] ?? '',
                                            $episode_data['still_path'] ?? '',
                                            $episode_data['air_date'] ?? null,
                                            $episode_data['runtime'] ?? 45,
                                            $episode_data['vote_average'] ?? 0,
                                            $watch_json,
                                            $download_json,
                                            $existingEpisode['id']
                                        ]);
                                    } else {
                                        // إضافة حلقة جديدة
                                        $episode_sql = "INSERT INTO episodes (
                                            series_id, season_number, episode_number, title, 
                                            title_en, description, still_path, air_date, runtime, vote_average,
                                            watch_servers, download_servers
                                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                                        $episode_stmt = $pdo->prepare($episode_sql);
                                        $episode_stmt->execute([
                                            $series_id,
                                            $season_data['number'],
                                            $episode_data['number'],
                                            $episode_data['title'] ?? "الحلقة {$episode_data['number']}",
                                            $episode_data['title_en'] ?? '',
                                            $episode_data['overview'] ?? '',
                                            $episode_data['still_path'] ?? '',
                                            $episode_data['air_date'] ?? null,
                                            $episode_data['runtime'] ?? 45,
                                            $episode_data['vote_average'] ?? 0,
                                            $watch_json,
                                            $download_json
                                        ]);
                                    }
                                }
                            }
                    }
                }
                
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
                            
                            $cast_sql = "INSERT INTO cast (
                                series_id, person_id, name, character_name, profile_path, 
                                order_number, department, popularity
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                            $cast_stmt = $pdo->prepare($cast_sql);
                            $cast_stmt->execute([
                                $series_id,
                                $cast_member['person_id'] ?? null,
                                $cast_member['name'],
                                $cast_member['character'] ?? '',
                                $cast_image,
                                $cast_member['order'] ?? 999,
                                $cast_member['department'] ?? 'Acting',
                                $cast_member['popularity'] ?? 0
                            ]);
                            
                            $cast_db_id = $pdo->lastInsertId();
                            
                            // إذا تم رفع صورة، سجلها في جدول الصور
                            if (!empty($uploaded_image)) {
                                $img_sql = "INSERT INTO cast_images (cast_id, image_path, is_primary) VALUES (?, ?, 1)";
                                $img_stmt = $pdo->prepare($img_sql);
                                $img_stmt->execute([$cast_db_id, $uploaded_image]);
                            }
                        }
                    }
                }
                
                // إضافة فريق العمل
                if (isset($_POST['crew']) && is_array($_POST['crew'])) {
                    foreach ($_POST['crew'] as $crew_member) {
                        if (!empty($crew_member['name'])) {
                            $crew_sql = "INSERT INTO crew (
                                series_id, person_id, name, job, department, profile_path
                            ) VALUES (?, ?, ?, ?, ?, ?)";
                            $crew_stmt = $pdo->prepare($crew_sql);
                            $crew_stmt->execute([
                                $series_id,
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
                                    'series', $series_id, 
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
                                    'series', $series_id,
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
                                'series', $series_id,
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
                
                $message = "✅ تم إضافة المسلسل بنجاح في قسم: {$category_display} {$category_icon} مع " . count($cast_data) . " ممثل و " . count($seasons_data) . " مواسم!";
                $messageType = "success";
                
                header("Location: edit-series.php?id=" . $series_id . "&added=1&category=" . urlencode($category));
                exit;
            }
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
        <title>📺 مستورد المسلسلات المتطور - مع طاقم العمل والإعلام</title>
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
                --anime: #e50914;
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
            .stat-item.anime { border-top: 3px solid var(--anime); }

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

            .series-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
                max-height: 500px;
                overflow-y: auto;
                padding: 5px;
            }

            .series-card {
                background: var(--dark-light);
                border-radius: 10px;
                overflow: hidden;
                cursor: pointer;
                transition: all 0.3s ease;
                border: 1px solid var(--border);
            }

            .series-card:hover {
                transform: translateY(-5px);
                border-color: var(--primary);
                box-shadow: 0 10px 20px rgba(229,9,20,0.2);
            }

            .series-poster {
                width: 100%;
                aspect-ratio: 2/3;
                object-fit: cover;
            }

            .series-info {
                padding: 10px;
            }

            .series-title {
                font-weight: 600;
                margin-bottom: 3px;
                font-size: 14px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .series-year {
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

            .series-details-preview {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .series-title-preview {
                font-size: 28px;
                font-weight: 800;
                color: var(--primary);
            }

            .series-meta-preview {
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

            .seasons-container {
                margin: 20px 0;
                max-height: 500px;
                overflow-y: auto;
            }

            .season-card {
                background: var(--dark-light);
                border-radius: 10px;
                padding: 15px;
                margin-bottom: 15px;
                border: 1px solid var(--border);
            }

            .season-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                cursor: pointer;
            }

            .season-title {
                font-size: 18px;
                font-weight: 700;
                color: var(--primary);
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .episodes-count {
                background: rgba(229,9,20,0.1);
                padding: 3px 10px;
                border-radius: 20px;
                color: var(--primary);
                font-size: 12px;
            }
            .tab-content {
        display: none;
        animation: fadeIn 0.5s ease;
    }

    .tab-content.active {
        display: block !important; /* أضف !important هنا */
    }

            .episodes-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 10px;
                margin-top: 10px;
            }

            .episode-card {
                background: rgba(0,0,0,0.2);
                border-radius: 8px;
                padding: 10px;
                border: 1px solid var(--border);
            }

            .episode-number {
                color: var(--primary);
                font-weight: 600;
                font-size: 12px;
                margin-bottom: 3px;
            }

            .episode-title {
                font-weight: 500;
                font-size: 13px;
                margin-bottom: 3px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .episode-date {
                color: var(--text-muted);
                font-size: 11px;
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
            /* أنماط إضافية للمواسم والحلقات */
    .episode-links-container {
        background: #0f0f0f;
        border-radius: 6px;
        padding: 8px;
        margin-top: 5px;
        max-height: 200px;
        overflow-y: auto;
    }

    .episode-links-container .link-item-small {
        margin-bottom: 6px;
    }

    .season-card {
        transition: all 0.3s ease;
    }

    .season-card:hover {
        border-color: var(--primary);
        box-shadow: 0 5px 20px rgba(229,9,20,0.1);
    }

    .episode-card {
        transition: all 0.3s ease;
    }

    .episode-card:hover {
        border-color: var(--primary);
        box-shadow: 0 3px 10px rgba(229,9,20,0.1);
    }

    .episode-number-badge {
        background: var(--primary);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }

    .small-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s ease;
    }

    .small-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 10px rgba(39,174,96,0.3);
    }

    .small-btn:active {
        transform: translateY(0);
    }

    /* تصميم scrollbar مخصص */
    .seasons-container::-webkit-scrollbar,
    .episode-links-container::-webkit-scrollbar {
        width: 8px;
    }

    .seasons-container::-webkit-scrollbar-track,
    .episode-links-container::-webkit-scrollbar-track {
        background: #1a1a1a;
        border-radius: 10px;
    }

    .seasons-container::-webkit-scrollbar-thumb,
    .episode-links-container::-webkit-scrollbar-thumb {
        background: #333;
        border-radius: 10px;
    }

    .seasons-container::-webkit-scrollbar-thumb:hover,
    .episode-links-container::-webkit-scrollbar-thumb:hover {
        background: var(--primary);
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
                <i class="fas fa-tv logo-icon"></i>
                <h1>مستورد <span>المسلسلات</span></h1>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    الرئيسية
                </a>
                <a href="movies-import.php" class="nav-link">
                    <i class="fas fa-film"></i>
                    الأفلام
                </a>
                <a href="series-import.php" class="nav-link active">
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
                <a href="edit-series.php?id=<?php echo $duplicate_id; ?>" style="color: white; margin-right: 10px; text-decoration: underline;">
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
                    'anime' => ['name' => 'أنمي', 'icon' => '🇯🇵', 'color' => '#e50914'],
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
                    <i class="fas fa-tv stat-icon"></i>
                    <div class="stat-value"><?php echo array_sum(array_column($category_stats, 'count')); ?></div>
                    <div class="stat-label">إجمالي المسلسلات</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-layer-group stat-icon"></i>
                    <div class="stat-value"><?php echo count($seasons_data); ?></div>
                    <div class="stat-label">مواسم حالية</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-users stat-icon"></i>
                    <div class="stat-value"><?php echo count($cast_data); ?></div>
                    <div class="stat-label">طاقم التمثيل</div>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star stat-icon"></i>
                    <div class="stat-value"><?php echo number_format($tmdb_data['vote_average'] ?? 4.8, 1); ?></div>
                    <div class="stat-label">التقييم</div>
                </div>
            </div>

            <!-- قسم البحث المتقدم -->
            <div class="search-section">
                <div class="search-title">
                    <i class="fas fa-search"></i>
                    ابحث عن مسلسل من TMDB
                </div>
                <form method="POST">
                    <input type="hidden" name="search_tmdb" value="1">
                    <div class="search-grid">
                        <input type="text" name="search_query" class="search-input" placeholder="اسم المسلسل..." required>
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
                        <i class="fas fa-tv"></i>
                        نتائج البحث
                    </div>
                    <div class="results-count">
                        <?php echo count($search_results); ?> مسلسل
                    </div>
                </div>
                <div class="series-grid">
                    <?php foreach ($search_results as $series): ?>
                    <div class="series-card" onclick="window.location.href='?tmdb_id=<?php echo $series['id']; ?>'">
                        <img src="https://image.tmdb.org/t/p/w200<?php echo $series['poster_path'] ?? ''; ?>" 
                            class="series-poster" 
                            alt="<?php echo htmlspecialchars($series['name']); ?>"
                            onerror="this.src='https://via.placeholder.com/200x300?text=No+Poster'">
                        <div class="series-info">
                            <div class="series-title"><?php echo htmlspecialchars($series['name']); ?></div>
                            <div class="series-year">
                                <?php echo substr($series['first_air_date'] ?? 'غير معروف', 0, 4); ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tmdb_data): ?>
            <!-- نموذج إضافة المسلسل -->
            <div class="add-form">
                <div class="form-header">
                    <i class="fas fa-plus-circle" style="font-size: 30px; color: var(--primary);"></i>
                    <h2>إضافة مسلسل جديد</h2>
                    <span class="category-badge" style="background: <?php echo $predicted_category['color'] ?? '#333'; ?>;">
                        <?php echo $predicted_category['icon'] ?? ''; ?> <?php echo $predicted_category['display_name'] ?? 'مسلسل'; ?>
                    </span>
                </div>

                <form method="POST" enctype="multipart/form-data" id="seriesForm">
                    <input type="hidden" name="add_series" value="1">
                    <input type="hidden" name="tmdb_id" value="<?php echo $tmdb_data['id'] ?? ''; ?>">
                    <input type="hidden" name="imdb_id" value="<?php echo $tmdb_data['external_ids']['imdb_id'] ?? ($tmdb_data['imdb_id'] ?? ''); ?>">
                    <input type="hidden" name="homepage" value="<?php echo $tmdb_data['homepage'] ?? ''; ?>">
                    <input type="hidden" name="first_air_date" value="<?php echo $tmdb_data['first_air_date'] ?? ''; ?>">
                    <input type="hidden" name="last_air_date" value="<?php echo $tmdb_data['last_air_date'] ?? ''; ?>">
                    <input type="hidden" name="number_of_seasons" value="<?php echo $tmdb_data['number_of_seasons'] ?? 0; ?>">
                    <input type="hidden" name="number_of_episodes" value="<?php echo $tmdb_data['number_of_episodes'] ?? 0; ?>">
                    <input type="hidden" name="vote_count" value="<?php echo $tmdb_data['vote_count'] ?? 0; ?>">
                    <input type="hidden" name="popularity" value="<?php echo $tmdb_data['popularity'] ?? 0; ?>">
                    <input type="hidden" name="poster_url" id="poster_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['poster_path'] ?? ''; ?>">
                    <input type="hidden" name="backdrop_url" id="backdrop_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['backdrop_path'] ?? ''; ?>">

                    <!-- معاينة المسلسل -->
                    <div class="preview-section">
                        <div class="preview-grid">
                            <div class="poster-preview">
                                <img src="https://image.tmdb.org/t/p/w500<?php echo $tmdb_data['poster_path'] ?? ''; ?>" 
                                    id="preview-poster"
                                    alt="<?php echo htmlspecialchars($tmdb_data['name'] ?? ''); ?>">
                                <button type="button" class="change-poster-btn" onclick="showPosterModal()">
                                    <i class="fas fa-camera"></i>
                                    تغيير
                                </button>
                            </div>
                            <div class="series-details-preview">
                                <div class="series-title-preview"><?php echo htmlspecialchars($tmdb_data['name']); ?></div>
                                <div class="series-meta-preview">
                                    <span class="meta-item">
                                        <i class="fas fa-calendar"></i>
                                        <?php echo substr($tmdb_data['first_air_date'] ?? '', 0, 4); ?>
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-layer-group"></i>
                                        <?php echo count($seasons_data); ?> موسم
                                    </span>
                                    <span class="meta-item">
                                        <i class="fas fa-film"></i>
                                        <?php echo $tmdb_data['number_of_episodes'] ?? 0; ?> حلقة
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
                                    سيتم إضافته إلى قسم: <?php echo $predicted_category['display_name'] ?? 'مسلسلات'; ?> <?php echo $predicted_category['icon'] ?? ''; ?>
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
                        <div class="form-tab active" onclick="showTab('basic')">
                            <i class="fas fa-info-circle"></i>
                            معلومات أساسية
                        </div>
                        <div class="form-tab" onclick="showTab('membership')">
                            <i class="fas fa-crown"></i>
                            العضوية
                        </div>
                        <div class="form-tab" onclick="showTab('cast')">
                            <i class="fas fa-users"></i>
                            طاقم العمل (<?php echo count($cast_data); ?>)
                        </div>
                        <div class="form-tab" onclick="showTab('crew')">
                            <i class="fas fa-video"></i>
                            فريق الإنتاج
                        </div>
                        <div class="form-tab" onclick="showTab('seasons')">📺 المواسم والحلقات (<?php echo count($seasons_data); ?> مواسم)</div>
                        <div class="form-tab" onclick="showTab('watch')">
                            <i class="fas fa-play-circle"></i>
                            روابط المشاهدة
                        </div>
                        <div class="form-tab" onclick="showTab('subtitles')">
                            <i class="fas fa-closed-captioning"></i>
                            الترجمات
                        </div>
                        <?php if (!empty($videos_data)): ?>
                        <div class="form-tab" onclick="showTab('videos')">
                            <i class="fab fa-youtube"></i>
                            الإعلانات
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- تبويب المعلومات الأساسية -->
                    <div id="basicTab" class="tab-content active">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">العنوان بالعربية</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($tmdb_data['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">العنوان الأصلي</label>
                                <input type="text" name="title_en" class="form-control" value="<?php echo htmlspecialchars($tmdb_data['original_name']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">سنة الإنتاج</label>
                                <input type="number" name="year" class="form-control" value="<?php echo substr($tmdb_data['first_air_date'] ?? date('Y'), 0, 4); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">بلد الإنتاج</label>
                                <input type="text" name="country" class="form-control" value="<?php 
                                    $countries = array_column($tmdb_data['production_countries'] ?? [], 'name');
                                    echo implode('، ', $countries);
                                ?>">
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
                                <label class="form-label">تقييم TMDB</label>
                                <input type="number" step="0.1" name="imdb_rating" class="form-control" value="<?php echo $tmdb_data['vote_average'] ?? 0; ?>">
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">القصة</label>
                                <textarea name="overview" class="form-control" rows="4"><?php echo htmlspecialchars($tmdb_data['overview']); ?></textarea>
                            </div>
                        </div>

                        <!-- حالة المسلسل -->
                        <div style="margin-top: 20px;">
                            <label class="form-label">حالة المسلسل</label>
                            <div class="status-selector">
                                <div class="status-option selected" onclick="selectStatus('returning')">
                                    <i class="fas fa-sync-alt"></i>
                                    <div style="margin-top: 5px;">يعرض حالياً</div>
                                    <input type="radio" name="status" value="returning" style="display: none;" checked>
                                </div>
                                <div class="status-option" onclick="selectStatus('ended')">
                                    <i class="fas fa-stop"></i>
                                    <div style="margin-top: 5px;">منتهي</div>
                                    <input type="radio" name="status" value="ended" style="display: none;">
                                </div>
                                <div class="status-option" onclick="selectStatus('canceled')">
                                    <i class="fas fa-ban"></i>
                                    <div style="margin-top: 5px;">ملغي</div>
                                    <input type="radio" name="status" value="canceled" style="display: none;">
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

                    <!-- تبويب المواسم والحلقات -->
                    <!-- تبويب المواسم والحلقات المتطور -->
    <div id="seasonsTab" class="tab-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
            <div>
                <h3 style="color: var(--primary); font-size: 22px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-layer-group"></i>
                    إدارة المواسم والحلقات
                </h3>
                <p style="color: var(--text-muted); font-size: 14px; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> يمكنك إضافة وتعديل وحذف المواسم والحلقات وإدارة روابط المشاهدة والتحميل لكل حلقة
                </p>
            </div>
            <button type="button" class="add-link-btn" onclick="addNewSeason()" style="background: #27ae60; padding: 12px 25px;">
                <i class="fas fa-plus-circle"></i>
                إضافة موسم جديد
            </button>
        </div>
        
        <!-- إحصائيات سريعة -->
        <div style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap;">
            <div style="background: #1a1a1a; padding: 10px 20px; border-radius: 30px; border: 1px solid #333;">
                <i class="fas fa-folder-open" style="color: var(--primary);"></i>
                <span style="margin: 0 5px;">إجمالي المواسم:</span>
                <span style="font-weight: 700; color: var(--primary);"><?php echo count($seasons_data); ?></span>
            </div>
            <div style="background: #1a1a1a; padding: 10px 20px; border-radius: 30px; border: 1px solid #333;">
    <i class="fas fa-film" style="color: #27ae60;"></i>
    <span style="margin: 0 5px;">إجمالي الحلقات:</span>
    <span style="font-weight: 700; color: #27ae60;" id="total-episodes-count">
        <?php 
        $total_episodes = 0;
foreach ($seasons_data as $season) {
    // استخدم count($season['episodes']) إذا كانت الحلقات موجودة
    if (!empty($season['episodes'])) {
        $total_episodes += count($season['episodes']);
    } else {
        $total_episodes += $season['episode_count'];
    }
}
echo $total_episodes;
        ?>
    </span>
</div>
        </div>
        
        <!-- حاوية المواسم -->
        <div class="seasons-container" id="seasons-container" style="max-height: 600px; overflow-y: auto; padding: 5px;">
            <?php if (!empty($seasons_data)): ?>
                <?php foreach ($seasons_data as $s_index => $season): ?>
                <div class="season-card" id="season-<?php echo $s_index; ?>" data-season="<?php echo $s_index; ?>" style="background: #1a1a1a; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #333; transition: all 0.3s ease;">
                    <!-- رأس الموسم -->
                    <div class="season-header" onclick="toggleSeason(<?php echo $s_index; ?>)" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                        <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-folder-open" style="color: var(--primary); font-size: 24px;"></i>
                                <span class="season-title" style="font-size: 20px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($season['name']); ?></span>
                            </div>
                            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                                <span class="episodes-count" style="background: rgba(229,9,20,0.1); padding: 5px 15px; border-radius: 20px; color: var(--primary); font-size: 13px;">
                                    <i class="fas fa-film"></i> <?php echo $season['episode_count']; ?> حلقة
                                </span>
                                <span style="background: rgba(52,152,219,0.1); padding: 5px 15px; border-radius: 20px; color: #3498db; font-size: 13px;">
                                    <i class="fas fa-calendar"></i> <?php echo $season['air_date'] ? substr($season['air_date'], 0, 4) : 'تاريخ غير محدد'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 8px; align-items: center;" onclick="event.stopPropagation()">
                            <button type="button" class="season-action-btn" onclick="editSeason(<?php echo $s_index; ?>)" title="تعديل الموسم" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="season-action-btn" onclick="addEpisodeToSeason(<?php echo $s_index; ?>)" title="إضافة حلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-plus-circle"></i>
                            </button>
                            <button type="button" class="season-action-btn delete" onclick="deleteSeason(<?php echo $s_index; ?>)" title="حذف الموسم" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer; transition: all 0.3s ease;">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                            <i class="fas fa-chevron-down" id="arrow-<?php echo $s_index; ?>" style="margin-right: 5px; font-size: 18px; color: var(--text-muted);"></i>
                        </div>
                    </div>
                    
                    <!-- محتوى الموسم (الحلقات) -->
                    <div id="season-content-<?php echo $s_index; ?>" style="display: none; margin-top: 20px;">
                        <!-- بيانات الموسم المخفية -->
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][number]" value="<?php echo $season['number']; ?>" class="season-number">
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][name]" value="<?php echo htmlspecialchars($season['name']); ?>" class="season-name">
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][overview]" value="<?php echo htmlspecialchars($season['overview']); ?>" class="season-overview">
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][air_date]" value="<?php echo $season['air_date']; ?>" class="season-air-date">
                        
                        <!-- وصف الموسم -->
                        <?php if (!empty($season['overview'])): ?>
                        <div style="background: #0f0f0f; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #333;">
                            <p style="color: var(--text-muted); line-height: 1.6;"><?php echo htmlspecialchars($season['overview']); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- روابط الموسم العامة -->
                        <div style="margin-bottom: 25px; background: #0f0f0f; border-radius: 10px; padding: 15px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                <h4 style="color: #27ae60; font-size: 18px; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-link"></i> روابط الموسم العامة
                                </h4>
                                <div style="display: flex; gap: 10px;">
                                    <button type="button" class="small-btn" onclick="addSeasonWatchLink(<?php echo $s_index; ?>)" style="background: #27ae60; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                                        <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                                    </button>
                                    <button type="button" class="small-btn" onclick="addSeasonDownloadLink(<?php echo $s_index; ?>)" style="background: #3498db; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 13px;">
                                        <i class="fas fa-plus"></i> إضافة رابط تحميل
                                    </button>
                                </div>
                            </div>
                            
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <h5 style="color: var(--text-muted); margin-bottom: 10px; font-size: 14px;">🎬 روابط المشاهدة</h5>
                                    <div id="season-<?php echo $s_index; ?>-watch-links" class="season-links-container"></div>
                                    <?php if (empty($season['watch_links'] ?? [])): ?>
                                    <p style="color: #666; font-size: 12px; text-align: center; padding: 10px;">لا توجد روابط مشاهدة للموسم</p>
                                    <?php endif; ?>
                                </div>
                                
                                <div>
                                    <h5 style="color: var(--text-muted); margin-bottom: 10px; font-size: 14px;">⬇️ روابط التحميل</h5>
                                    <div id="season-<?php echo $s_index; ?>-download-links" class="season-links-container"></div>
                                    <?php if (empty($season['download_links'] ?? [])): ?>
                                    <p style="color: #666; font-size: 12px; text-align: center; padding: 10px;">لا توجد روابط تحميل للموسم</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- قائمة الحلقات -->
                        <div style="margin-top: 20px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px;">
                                <h4 style="color: var(--primary); font-size: 20px; display: flex; align-items: center; gap: 10px;">
                                    <i class="fas fa-list"></i> قائمة الحلقات (<?php echo $season['episode_count']; ?>)
                                </h4>
                                <button type="button" class="add-link-btn" onclick="addEpisodeToSeason(<?php echo $s_index; ?>)" style="background: #3498db; padding: 8px 20px;">
                                    <i class="fas fa-plus-circle"></i>
                                    إضافة حلقة جديدة
                                </button>
                            </div>
                            
                            <div class="episodes-grid" id="season-<?php echo $s_index; ?>-episodes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 15px;">
                                <?php foreach ($season['episodes'] as $e_index => $episode): ?>
                                <div class="episode-card" id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>" style="background: #0f0f0f; border-radius: 10px; padding: 15px; border: 1px solid #333;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
                                                <span class="episode-number-badge" style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                                    الحلقة <?php echo $episode['number']; ?>
                                                </span>
                                                <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][number]" 
                                                    value="<?php echo $episode['number']; ?>" 
                                                    placeholder="رقم الحلقة"
                                                    class="episode-input small-input"
                                                    style="width: 70px; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;"
                                                    onchange="updateEpisodeNumber(this, <?php echo $s_index; ?>, <?php echo $e_index; ?>)">
                                            </div>
                                            
                                            <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][title]" 
                                                value="<?php echo htmlspecialchars($episode['name']); ?>" 
                                                placeholder="عنوان الحلقة"
                                                class="episode-input"
                                                style="width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 8px; color: #fff; margin-bottom: 8px;">
                                                
                                            <textarea name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][overview]" 
                                                    placeholder="وصف الحلقة"
                                                    class="episode-input"
                                                    rows="2"
                                                    style="width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 8px; color: #fff; margin-bottom: 8px;"><?php echo htmlspecialchars($episode['overview']); ?></textarea>
                                            
                                            <div style="display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                                                <input type="date" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][air_date]" 
                                                    value="<?php echo $episode['air_date']; ?>"
                                                    class="episode-input small-input"
                                                    style="width: 140px; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;">
                                                <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][still_path]" 
                                                    value="<?php echo $episode['still_path']; ?>"
                                                    placeholder="رابط صورة الحلقة"
                                                    class="episode-input"
                                                    style="flex: 1; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;">
                                            </div>
                                        </div>
                                        
                                        <div style="display: flex; flex-direction: column; gap: 8px;">
                                            <button type="button" class="episode-action-btn" onclick="toggleEpisodeLinks(<?php echo $s_index; ?>, <?php echo $e_index; ?>)" title="إدارة روابط الحلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 32px; height: 32px; border-radius: 6px; cursor: pointer;">
                                                <i class="fas fa-link"></i>
                                            </button>
                                            <button type="button" class="episode-action-btn delete" onclick="deleteEpisode(<?php echo $s_index; ?>, <?php echo $e_index; ?>)" title="حذف الحلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 32px; height: 32px; border-radius: 6px; cursor: pointer;">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- روابط الحلقة (مخفية افتراضياً) -->
                                    <!-- في قسم الحلقات، استبدل جزء روابط المشاهدة بهذا -->

    <!-- روابط الحلقة -->
    <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-links" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #333;">
        
        <!-- روابط المشاهدة -->
        <div style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h5 style="color: #27ae60; font-size: 16px;">
                    <i class="fas fa-play-circle"></i> روابط المشاهدة
                </h5>
                <button type="button" class="small-btn" onclick="addWatchLinkToEpisode(<?php echo $s_index; ?>, <?php echo $e_index; ?>)">
                    <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                </button>
            </div>
            
            <!-- حاوية روابط المشاهدة -->
            <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-watch-links" class="episode-links-container">
                <?php
                // جلب سيرفرات المشاهدة من قاعدة البيانات
                $watch_servers = $pdo->query("SELECT * FROM default_servers WHERE type = 'watch' AND is_active = 1 ORDER BY sort_order")->fetchAll();
                ?>
                <!-- نموذج إضافة رابط مشاهدة -->
                <div class="add-watch-link-template" style="display: none;">
                    <div class="watch-link-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 8px; margin-bottom: 8px; padding: 8px; background: #1a1a1a; border-radius: 6px; border: 1px solid #333;">
                        <select name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][server_id]" class="server-select episode-input" onchange="updateWatchServerInfo(this)">
                            <option value="">اختر سيرفر...</option>
                            <?php foreach ($watch_servers as $server): ?>
                            <option value="<?php echo $server['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($server['name']); ?>"
                                    data-quality="<?php echo $server['quality']; ?>"
                                    data-lang="<?php echo $server['language']; ?>"
                                    data-pattern="<?php echo htmlspecialchars($server['url_pattern']); ?>">
                                <?php echo $server['name']; ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="custom">➕ سيرفر مخصص</option>
                        </select>
                        
                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][custom_name]" 
                            class="episode-input custom-server-name" 
                            placeholder="اسم السيرفر" 
                            style="display: none;">
                        
                        <input type="url" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][url]" 
                            class="episode-input server-url" 
                            placeholder="رابط المشاهدة" 
                            required>
                        
                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][quality]" 
                            class="episode-input server-quality" 
                            placeholder="الجودة" 
                            value="HD">
                        
                        <select name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][lang]" class="episode-input server-lang">
                            <option value="arabic">🇸🇦 عربي</option>
                            <option value="english">🇬🇧 English</option>
                            <option value="turkish">🇹🇷 Türkçe</option>
                        </select>
                        
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][watch_links][INDEX][server_name]" class="server-name-hidden">
                        
                        <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- روابط التحميل -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h5 style="color: #3498db; font-size: 16px;">
                    <i class="fas fa-download"></i> روابط التحميل
                </h5>
                <button type="button" class="small-btn" onclick="addDownloadLinkToEpisode(<?php echo $s_index; ?>, <?php echo $e_index; ?>)" style="background: #3498db;">
                    <i class="fas fa-plus"></i> إضافة رابط تحميل
                </button>
            </div>
            
            <!-- حاوية روابط التحميل -->
            <div id="episode-<?php echo $s_index; ?>-<?php echo $e_index; ?>-download-links" class="episode-links-container">
                <?php
                // جلب سيرفرات التحميل من قاعدة البيانات
                $download_servers = $pdo->query("SELECT * FROM default_servers WHERE type = 'download' AND is_active = 1 ORDER BY sort_order")->fetchAll();
                ?>
                <!-- نموذج إضافة رابط تحميل -->
                <div class="add-download-link-template" style="display: none;">
                    <div class="download-link-item" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 8px; margin-bottom: 8px; padding: 8px; background: #1a1a1a; border-radius: 6px; border: 1px solid #333;">
                        <select name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][server_id]" class="server-select episode-input" onchange="updateDownloadServerInfo(this)">
                            <option value="">اختر سيرفر...</option>
                            <?php foreach ($download_servers as $server): ?>
                            <option value="<?php echo $server['id']; ?>" 
                                    data-name="<?php echo htmlspecialchars($server['name']); ?>"
                                    data-quality="<?php echo $server['quality']; ?>">
                                <?php echo $server['name']; ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="custom">➕ سيرفر مخصص</option>
                        </select>
                        
                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][custom_name]" 
                            class="episode-input custom-server-name" 
                            placeholder="اسم السيرفر" 
                            style="display: none;">
                        
                        <input type="url" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][url]" 
                            class="episode-input server-url" 
                            placeholder="رابط التحميل" 
                            required>
                        
                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][quality]" 
                            class="episode-input server-quality" 
                            placeholder="الجودة" 
                            value="HD">
                        
                        <input type="text" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][size]" 
                            class="episode-input" 
                            placeholder="الحجم">
                        
                        <input type="hidden" name="seasons[<?php echo $s_index; ?>][episodes][<?php echo $e_index; ?>][download_links][INDEX][server_name]" class="server-name-hidden">
                        
                        <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 60px 20px; background: #1a1a1a; border-radius: 15px; border: 2px dashed #333;">
                    <i class="fas fa-folder-open" style="font-size: 60px; color: #444; margin-bottom: 20px;"></i>
                    <h3 style="color: #b3b3b3; margin-bottom: 10px; font-size: 24px;">لا توجد مواسم بعد</h3>
                    <p style="color: #666; margin-bottom: 25px; font-size: 16px;">ابدأ بإضافة موسم جديد للمسلسل</p>
                    <button type="button" class="add-link-btn" onclick="addNewSeason()" style="background: #27ae60; padding: 15px 40px; font-size: 16px;">
                        <i class="fas fa-plus-circle"></i> إضافة موسم جديد
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

            
                        
                    <!-- تبويب الترجمات -->
                    <div id="subtitlesTab" class="tab-content">
                        <div class="subtitles-container">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="color: var(--primary); font-size: 16px;">الترجمات المتاحة</h3>
                                <button type="button" class="add-link-btn" onclick="addSubtitle()">
                                    <i class="fas fa-plus"></i>
                                    إضافة ترجمة
                                </button>
                            </div>
                            <div id="subtitles-container"></div>
                        </div>
                    </div>
                    <!-- تبويب روابط المشاهدة العامة للمسلسل -->
    <div id="watchTab" class="tab-content">
        <div class="links-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: var(--primary); font-size: 18px;">
                    <i class="fas fa-link"></i>
                    روابط المشاهدة العامة للمسلسل
                </h3>
                <button type="button" class="add-link-btn" onclick="addWatchLink()" style="background: #27ae60;">
                    <i class="fas fa-plus"></i>
                    إضافة رابط جديد
                </button>
            </div>
            
            <!-- حاوية الروابط العامة -->
            <div id="watch-links-container" class="servers-list">
                <p style="color: #666; text-align: center; padding: 20px;">لا توجد روابط عامة مضافة بعد.</p>
            </div>
            
            <!-- معلومات مساعدة -->
            <div style="background: #1a1a1a; border-radius: 8px; padding: 15px; margin-top: 20px;">
                <h5 style="color: #b3b3b3; margin-bottom: 10px;">
                    <i class="fas fa-info-circle" style="color: #e50914;"></i>
                    ملاحظة:
                </h5>
                <p style="color: #b3b3b3; font-size: 13px;">
                    هذه الروابط ستكون متاحة لجميع حلقات المسلسل كخيارات إضافية.
                    لإضافة روابط خاصة لكل حلقة، استخدم تبويب "المواسم والحلقات".
                </p>
            </div>
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

                    <div class="action-buttons">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i>
                            حفظ المسلسل
                        </button>
                        <button type="button" class="btn-secondary" onclick="window.location.href='series-import.php'">
                            <i class="fas fa-times"></i>
                            إلغاء
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <!-- أحدث المسلسلات المضافة -->
            
        
        </div>

        <!-- مودال تغيير البوستر الرئيسي -->
        <div id="posterModal" class="modal" style="display: none;">
            <div class="modal-content">
                <h3 class="modal-title">تغيير صورة المسلسل</h3>
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
    // ========== متغيرات عامة ==========
    let watchLinkCount = 0;
    let downloadLinkCount = 0;
    let subtitleCount = 0;
    let castCount = <?php echo count($cast_data); ?>;

    // دوال إضافية لإدارة روابط الحلقات
    function addEpisodeWatchLink(seasonIndex, episodeIndex) {
        const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-watch-links`);
        if (!container) return;
        
        const linkId = Date.now() + Math.floor(Math.random() * 1000);
        const html = `
            <div class="link-item-small" id="episode-watch-${seasonIndex}-${episodeIndex}-${linkId}" style="display: grid; grid-template-columns: 1fr 2fr 1fr auto; gap: 8px; margin-bottom: 8px; padding: 8px; background: #1a1a1a; border-radius: 6px; border: 1px solid #333;">
                <select name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][lang]" class="episode-input" style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                    <option value="arabic">🇸🇦 عربي</option>
                    <option value="english">🇬🇧 English</option>
                    <option value="turkish">🇹🇷 Türkçe</option>
                    <option value="french">🇫🇷 Français</option>
                    <option value="german">🇩🇪 Deutsch</option>
                    <option value="spanish">🇪🇸 Español</option>
                </select>
                <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][name]" 
                    class="episode-input" placeholder="اسم السيرفر" value="سيرفر" style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                <input type="url" name="seasons[${seasonIndex}][episodes][${episodeIndex}][watch_links][${linkId}][url]" 
                    class="episode-input" placeholder="الرابط" required style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()" style="background: #e50914; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    function addEpisodeDownloadLink(seasonIndex, episodeIndex) {
        const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-download-links`);
        if (!container) return;
        
        const linkId = Date.now() + Math.floor(Math.random() * 1000);
        const html = `
            <div class="link-item-small" id="episode-download-${seasonIndex}-${episodeIndex}-${linkId}" style="display: grid; grid-template-columns: 2fr 2fr 1fr auto; gap: 8px; margin-bottom: 8px; padding: 8px; background: #1a1a1a; border-radius: 6px; border: 1px solid #333;">
                <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][name]" 
                    class="episode-input" placeholder="اسم السيرفر" value="سيرفر" style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                <input type="url" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][url]" 
                    class="episode-input" placeholder="رابط التحميل" required style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                <input type="text" name="seasons[${seasonIndex}][episodes][${episodeIndex}][download_links][${linkId}][size]" 
                    class="episode-input" placeholder="الحجم" style="background: #0f0f0f; border: 1px solid #333; border-radius: 4px; padding: 4px; color: #fff;">
                <button type="button" class="remove-link-btn" onclick="this.parentElement.remove()" style="background: #e50914; color: white; border: none; width: 30px; height: 30px; border-radius: 4px; cursor: pointer;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
    }

    // تحديث دالة إضافة موسم جديد
    function addNewSeason() {
        const seasonsContainer = document.getElementById('seasons-container');
        if (!seasonsContainer) return;
        
        const seasonCount = document.querySelectorAll('.season-card').length;
        const newIndex = seasonCount;
        
        // إزالة رسالة "لا توجد مواسم" إذا وجدت
        const noSeasonsMsg = seasonsContainer.querySelector('div[style*="text-align: center"]');
        if (noSeasonsMsg) {
            noSeasonsMsg.remove();
        }
        
        const html = `
            <div class="season-card" id="season-${newIndex}" data-season="${newIndex}" style="background: #1a1a1a; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #333;">
                <div class="season-header" onclick="toggleSeason(${newIndex})" style="display: flex; justify-content: space-between; align-items: center; cursor: pointer;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-folder-open" style="color: var(--primary); font-size: 24px;"></i>
                            <input type="text" name="seasons[${newIndex}][name]" 
                                value="موسم جديد ${newIndex + 1}" 
                                placeholder="اسم الموسم"
                                class="episode-input"
                                style="width: 200px; background: #0f0f0f; border: 1px solid #333; border-radius: 6px; padding: 8px; color: #fff; font-size: 16px; font-weight: 600;"
                                onclick="event.stopPropagation()">
                        </div>
                        <span class="episodes-count" style="background: rgba(229,9,20,0.1); padding: 5px 15px; border-radius: 20px; color: var(--primary); font-size: 13px;">
                            <i class="fas fa-film"></i> 0 حلقة
                        </span>
                    </div>
                    
                    <div style="display: flex; gap: 8px; align-items: center;" onclick="event.stopPropagation()">
                        <button type="button" class="season-action-btn" onclick="editSeason(${newIndex})" title="تعديل الموسم" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" class="season-action-btn" onclick="addEpisodeToSeason(${newIndex})" title="إضافة حلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-plus-circle"></i>
                        </button>
                        <button type="button" class="season-action-btn delete" onclick="deleteSeason(${newIndex})" title="حذف الموسم" style="background: transparent; border: 1px solid #333; color: #fff; width: 36px; height: 36px; border-radius: 8px; cursor: pointer;">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                        <i class="fas fa-chevron-down" id="arrow-${newIndex}" style="margin-right: 5px; font-size: 18px; color: var(--text-muted);"></i>
                    </div>
                </div>
                
                <div id="season-content-${newIndex}" style="display: none; margin-top: 20px;">
                    <input type="hidden" name="seasons[${newIndex}][number]" value="${newIndex + 1}" class="season-number">
                    <input type="hidden" name="seasons[${newIndex}][overview]" value="" class="season-overview">
                    <input type="hidden" name="seasons[${newIndex}][air_date]" value="" class="season-air-date">
                    
                    <!-- روابط الموسم -->
                    <div style="margin-bottom: 25px; background: #0f0f0f; border-radius: 10px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                            <h4 style="color: #27ae60; font-size: 18px;">
                                <i class="fas fa-link"></i> روابط الموسم
                            </h4>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="small-btn" onclick="addSeasonWatchLink(${newIndex})" style="background: #27ae60;">
                                    <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                                </button>
                                <button type="button" class="small-btn" onclick="addSeasonDownloadLink(${newIndex})" style="background: #3498db;">
                                    <i class="fas fa-plus"></i> إضافة رابط تحميل
                                </button>
                            </div>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <div>
                                <h5 style="color: var(--text-muted); margin-bottom: 10px;">🎬 روابط المشاهدة</h5>
                                <div id="season-${newIndex}-watch-links" class="season-links-container"></div>
                            </div>
                            <div>
                                <h5 style="color: var(--text-muted); margin-bottom: 10px;">⬇️ روابط التحميل</h5>
                                <div id="season-${newIndex}-download-links" class="season-links-container"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- الحلقات -->
                    <div style="margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h4 style="color: var(--primary); font-size: 20px;">
                                <i class="fas fa-list"></i> الحلقات
                            </h4>
                            <button type="button" class="add-link-btn" onclick="addEpisodeToSeason(${newIndex})" style="background: #3498db;">
                                <i class="fas fa-plus-circle"></i> إضافة حلقة
                            </button>
                        </div>
                        <div class="episodes-grid" id="season-${newIndex}-episodes" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 15px;"></div>
                    </div>
                </div>
            </div>
        `;
        
        seasonsContainer.insertAdjacentHTML('beforeend', html);
        updateTotalEpisodesCount();
        showNotification('✅ تم إضافة موسم جديد');
    }

    // تحديث دالة إضافة حلقة
    function addEpisodeToSeason(seasonIndex) {
        const episodesGrid = document.getElementById(`season-${seasonIndex}-episodes`);
        if (!episodesGrid) return;
        
        const episodeCount = episodesGrid.children.length;
        const newIndex = episodeCount;
        
        const html = `
            <div class="episode-card" id="episode-${seasonIndex}-${newIndex}" style="background: #0f0f0f; border-radius: 10px; padding: 15px; border: 1px solid #333;">
                <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                            <span class="episode-number-badge" style="background: var(--primary); color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                                ${newIndex + 1}
                            </span>
                            <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][number]" 
                                value="${newIndex + 1}" 
                                placeholder="رقم"
                                class="episode-input small-input"
                                style="width: 70px; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;">
                        </div>
                        
                        <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][title]" 
                            value="حلقة جديدة ${newIndex + 1}" 
                            placeholder="عنوان الحلقة"
                            class="episode-input"
                            style="width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 8px; color: #fff; margin-bottom: 8px;">
                            
                        <textarea name="seasons[${seasonIndex}][episodes][${newIndex}][overview]" 
                                placeholder="وصف الحلقة"
                                class="episode-input"
                                rows="2"
                                style="width: 100%; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 8px; color: #fff; margin-bottom: 8px;"></textarea>
                        
                        <div style="display: flex; gap: 10px; margin-top: 8px; flex-wrap: wrap;">
                            <input type="date" name="seasons[${seasonIndex}][episodes][${newIndex}][air_date]" 
                                class="episode-input small-input"
                                style="width: 140px; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;">
                            <input type="text" name="seasons[${seasonIndex}][episodes][${newIndex}][still_path]" 
                                placeholder="رابط صورة"
                                class="episode-input"
                                style="flex: 1; background: #1a1a1a; border: 1px solid #333; border-radius: 6px; padding: 6px; color: #fff;">
                        </div>
                    </div>
                    
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" class="episode-action-btn" onclick="toggleEpisodeLinks(${seasonIndex}, ${newIndex})" title="إدارة روابط الحلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 32px; height: 32px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-link"></i>
                        </button>
                        <button type="button" class="episode-action-btn delete" onclick="deleteEpisode(${seasonIndex}, ${newIndex})" title="حذف الحلقة" style="background: transparent; border: 1px solid #333; color: #fff; width: 32px; height: 32px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                
                <!-- روابط الحلقة -->
                <div id="episode-${seasonIndex}-${newIndex}-links" style="display: none; margin-top: 20px; padding-top: 20px; border-top: 1px solid #333;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h5 style="color: #27ae60;">🎬 روابط المشاهدة</h5>
                                <button type="button" class="small-btn" onclick="addEpisodeWatchLink(${seasonIndex}, ${newIndex})" style="background: #27ae60;">
                                    <i class="fas fa-plus"></i> إضافة
                                </button>
                            </div>
                            <div id="episode-${seasonIndex}-${newIndex}-watch-links" class="episode-links-container"></div>
                        </div>
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <h5 style="color: #3498db;">⬇️ روابط التحميل</h5>
                                <button type="button" class="small-btn" onclick="addEpisodeDownloadLink(${seasonIndex}, ${newIndex})" style="background: #3498db;">
                                    <i class="fas fa-plus"></i> إضافة
                                </button>
                            </div>
                            <div id="episode-${seasonIndex}-${newIndex}-download-links" class="episode-links-container"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        episodesGrid.insertAdjacentHTML('beforeend', html);
        updateSeasonEpisodeCount(seasonIndex);
        updateTotalEpisodesCount();
        showNotification('✅ تم إضافة حلقة جديدة');
    }

    // تحديث دالة حذف الموسم
    function deleteSeason(seasonIndex) {
        if (confirm('⚠️ هل أنت متأكد من حذف هذا الموسم وجميع حلقاته؟ لا يمكن التراجع عن هذا الإجراء.')) {
            const seasonElement = document.getElementById(`season-${seasonIndex}`);
            if (seasonElement) {
                seasonElement.remove();
                updateTotalEpisodesCount();
                showNotification('🗑️ تم حذف الموسم وجميع حلقاته');
            }
        }
    }

    // تحديث دالة حذف حلقة
    function deleteEpisode(seasonIndex, episodeIndex) {
        if (confirm('هل أنت متأكد من حذف هذه الحلقة؟')) {
            const episodeElement = document.getElementById(`episode-${seasonIndex}-${episodeIndex}`);
            if (episodeElement) {
                episodeElement.remove();
                updateSeasonEpisodeCount(seasonIndex);
                updateTotalEpisodesCount();
                showNotification('🗑️ تم حذف الحلقة');
            }
        }
    }

    // دالة تحديث إجمالي عدد الحلقات
    function updateTotalEpisodesCount() {
        const seasons = document.querySelectorAll('.season-card');
        let total = 0;
        seasons.forEach(season => {
            const episodesGrid = season.querySelector('.episodes-grid');
            if (episodesGrid) {
                total += episodesGrid.children.length;
            }
        });
        
        const totalElement = document.getElementById('total-episodes-count');
        if (totalElement) {
            totalElement.textContent = total;
        }
    }

    // تعديل دالة toggleEpisodeLinks لتكون أكثر وضوحاً
    function toggleEpisodeLinks(seasonIndex, episodeIndex) {
        const linksDiv = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-links`);
        const button = event.currentTarget;
        
        if (linksDiv) {
            if (linksDiv.style.display === 'none' || linksDiv.style.display === '') {
                linksDiv.style.display = 'block';
                button.style.borderColor = '#27ae60';
                button.style.color = '#27ae60';
            } else {
                linksDiv.style.display = 'none';
                button.style.borderColor = '#333';
                button.style.color = '#fff';
            }
        }
    }
    // ========== دوال التبويبات المصححة ==========
    function showTab(tab) {
        console.log('🔄 جاري فتح التبويب: ' + tab);
        
        // قائمة التبويبات المطابقة للأسماء في HTML
        const tabMap = {
            'basic': 'basicTab',
            'membership': 'membershipTab',
            'cast': 'castTab',
            'crew': 'crewTab',
            'seasons': 'seasonsTab',
            'watch': 'watchTab',
            'subtitles': 'subtitlesTab',
            'videos': 'videosTab'
        };
        
        // إخفاء جميع التبويبات
        document.querySelectorAll('.tab-content').forEach(t => {
            t.classList.remove('active');
        });
        
        // إزالة التحديد من جميع أزرار التبويبات
        document.querySelectorAll('.form-tab').forEach(b => {
            b.classList.remove('active');
        });
        
        // إظهار التبويب المطلوب
        const tabId = tabMap[tab];
        if (tabId) {
            const targetTab = document.getElementById(tabId);
            if (targetTab) {
                targetTab.classList.add('active');
                console.log('✅ تم فتح التبويب: ' + tabId);
            } else {
                console.error('❌ لم يتم العثور على التبويب: ' + tabId);
            }
        }
        
        // تفعيل الزر المناسب
        const buttons = document.querySelectorAll('.form-tab');
        const tabs = ['basic', 'membership', 'cast', 'crew', 'seasons', 'watch', 'subtitles', 'videos'];
        const index = tabs.indexOf(tab);
        if (index !== -1 && buttons[index]) {
            buttons[index].classList.add('active');
        }
    }
    // ========== دوال إدارة السيرفرات المخصصة ==========

    let customWatchLinks = [];

    /**
    * إضافة سيرفر جديد من النموذج
    */
    // ========== دوال إضافة روابط المشاهدة والتحميل للحلقات ==========

    /**
    * إضافة رابط مشاهدة للحلقة
    */
    function addWatchLinkToEpisode(seasonIndex, episodeIndex) {
        const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-watch-links`);
        if (!container) return;
        
        // استنساخ القالب
        const template = container.querySelector('.add-watch-link-template');
        if (!template) return;
        
        const newLink = template.cloneNode(true);
        newLink.style.display = 'block';
        newLink.classList.remove('add-watch-link-template');
        
        // تحديث الـ INDEX في الأسماء
        const index = Date.now() + Math.floor(Math.random() * 1000);
        const html = newLink.innerHTML.replace(/INDEX/g, index);
        newLink.innerHTML = html;
        
        container.appendChild(newLink);
    }

    /**
    * إضافة رابط تحميل للحلقة
    */
    function addDownloadLinkToEpisode(seasonIndex, episodeIndex) {
        const container = document.getElementById(`episode-${seasonIndex}-${episodeIndex}-download-links`);
        if (!container) return;
        
        // استنساخ القالب
        const template = container.querySelector('.add-download-link-template');
        if (!template) return;
        
        const newLink = template.cloneNode(true);
        newLink.style.display = 'block';
        newLink.classList.remove('add-download-link-template');
        
        // تحديث الـ INDEX في الأسماء
        const index = Date.now() + Math.floor(Math.random() * 1000);
        const html = newLink.innerHTML.replace(/INDEX/g, index);
        newLink.innerHTML = html;
        
        container.appendChild(newLink);
    }

    /**
    * تحديث معلومات السيرفر عند اختياره (للمشاهدة)
    */
    function updateWatchServerInfo(select) {
        const linkItem = select.closest('.watch-link-item');
        if (!linkItem) return;
        
        const selectedOption = select.options[select.selectedIndex];
        const customNameInput = linkItem.querySelector('.custom-server-name');
        const serverUrlInput = linkItem.querySelector('.server-url');
        const serverQualityInput = linkItem.querySelector('.server-quality');
        const serverLangSelect = linkItem.querySelector('.server-lang');
        const serverNameHidden = linkItem.querySelector('.server-name-hidden');
        
        if (select.value === 'custom') {
            // سيرفر مخصص - أظهر حقل الاسم المخصص
            if (customNameInput) customNameInput.style.display = 'block';
            if (serverNameHidden) serverNameHidden.value = '';
        } else if (select.value) {
            // سيرفر افتراضي - خذ البيانات من الـ data attributes
            const serverName = selectedOption.getAttribute('data-name');
            const serverQuality = selectedOption.getAttribute('data-quality');
            const serverLang = selectedOption.getAttribute('data-lang');
            const urlPattern = selectedOption.getAttribute('data-pattern');
            
            if (customNameInput) customNameInput.style.display = 'none';
            if (serverNameHidden) serverNameHidden.value = serverName;
            if (serverQualityInput) serverQualityInput.value = serverQuality;
            if (serverLangSelect) serverLangSelect.value = serverLang || 'arabic';
            
            // إذا كان هناك نمط رابط، نعرضه كتلميح
            if (urlPattern && serverUrlInput) {
                serverUrlInput.placeholder = urlPattern;
            }
        }
    }

    /**
    * تحديث معلومات السيرفر عند اختياره (للتحميل)
    */
    function updateDownloadServerInfo(select) {
        const linkItem = select.closest('.download-link-item');
        if (!linkItem) return;
        
        const selectedOption = select.options[select.selectedIndex];
        const customNameInput = linkItem.querySelector('.custom-server-name');
        const serverQualityInput = linkItem.querySelector('.server-quality');
        const serverNameHidden = linkItem.querySelector('.server-name-hidden');
        
        if (select.value === 'custom') {
            // سيرفر مخصص
            if (customNameInput) customNameInput.style.display = 'block';
            if (serverNameHidden) serverNameHidden.value = '';
        } else if (select.value) {
            // سيرفر افتراضي
            const serverName = selectedOption.getAttribute('data-name');
            const serverQuality = selectedOption.getAttribute('data-quality');
            
            if (customNameInput) customNameInput.style.display = 'none';
            if (serverNameHidden) serverNameHidden.value = serverName;
            if (serverQualityInput) serverQualityInput.value = serverQuality || 'HD';
        }
    }
    function addCustomServerFromForm() {
        const nameInput = document.getElementById('custom-server-name');
        const urlInput = document.getElementById('custom-server-url');
        const langSelect = document.getElementById('custom-server-lang');
        const qualitySelect = document.getElementById('custom-server-quality');
        
        // التحقق من المدخلات
        if (!urlInput.value || urlInput.value === '') {
            showNotification('❌ الرجاء إدخال رابط المشاهدة', 'error');
            return;
        }
        
        if (!nameInput.value) {
            nameInput.value = 'سيرفر مخصص';
        }
        
        // إنشاء كائن السيرفر
        const server = {
            name: nameInput.value,
            url: urlInput.value,
            lang: langSelect.value,
            quality: qualitySelect.value
        };
        
        // إضافة إلى القائمة
        customWatchLinks.push(server);
        
        // إضافة إلى النموذج كحقل مخفي
        addServerToForm(server);
        
        // تحديث العرض
        displayWatchServers();
        
        // إظهار إشعار
        showNotification('✅ تم إضافة السيرفر بنجاح', 'success');
        
        // تنظيف النموذج
        clearCustomServerForm();
    }

    /**
    * إضافة سيرفر إلى النموذج كحقل مخفي
    */
    function addServerToForm(server) {
        const container = document.getElementById('watch-links-container');
        if (!container) return;
        
        // إزالة رسالة "لا توجد سيرفرات"
        const emptyMsg = container.querySelector('p[style*="text-align: center"]');
        if (emptyMsg) emptyMsg.remove();
        
        // إنشاء عنصر السيرفر
        const serverId = 'server-' + Date.now() + Math.floor(Math.random() * 1000);
        const serverHtml = `
            <div class="server-item" id="${serverId}" style="background: #1a1a1a; border-radius: 8px; padding: 15px; margin-bottom: 10px; border: 1px solid #333; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; flex: 1;">
                    <span style="background: #e50914; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-play"></i>
                    </span>
                    <div>
                        <strong style="color: #e50914;">${escapeHtml(server.name)}</strong>
                        <div style="display: flex; gap: 10px; margin-top: 5px; color: #b3b3b3; font-size: 12px;">
                            <span><i class="fas fa-language"></i> ${server.lang === 'arabic' ? 'عربي' : server.lang}</span>
                            <span><i class="fas fa-hd"></i> ${server.quality}</span>
                        </div>
                    </div>
                    <div style="color: #666; font-size: 11px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        ${escapeHtml(server.url)}
                    </div>
                </div>
                <div>
                    <button type="button" class="remove-link-btn" onclick="removeCustomServer('${serverId}')" style="background: #e50914; color: white; border: none; width: 30px; height: 30px; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', serverHtml);
        
        // إضافة حقول مخفية للنموذج
        const form = document.getElementById('seriesForm');
        if (form) {
            const index = customWatchLinks.length - 1;
            
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = `watch_links[${index}][name]`;
            nameInput.value = server.name;
            form.appendChild(nameInput);
            
            const urlInput = document.createElement('input');
            urlInput.type = 'hidden';
            urlInput.name = `watch_links[${index}][url]`;
            urlInput.value = server.url;
            form.appendChild(urlInput);
            
            const langInput = document.createElement('input');
            langInput.type = 'hidden';
            langInput.name = `watch_links[${index}][lang]`;
            langInput.value = server.lang;
            form.appendChild(langInput);
            
            const qualityInput = document.createElement('input');
            qualityInput.type = 'hidden';
            qualityInput.name = `watch_links[${index}][quality]`;
            qualityInput.value = server.quality;
            form.appendChild(qualityInput);
        }
    }

    /**
    * إزالة سيرفر مخصص
    */
    function removeCustomServer(serverId) {
        if (confirm('هل أنت متأكد من حذف هذا السيرفر؟')) {
            const element = document.getElementById(serverId);
            if (element) element.remove();
            
            // تحديث العداد
            updateWatchCount();
            
            showNotification('🗑️ تم حذف السيرفر', 'info');
        }
    }

    /**
    * تحديث عداد السيرفرات
    */
    function updateWatchCount() {
        const container = document.getElementById('watch-links-container');
        const countBadge = document.getElementById('watch-count');
        
        if (!container || !countBadge) return;
        
        const servers = container.querySelectorAll('.server-item');
        countBadge.textContent = servers.length;
        
        if (servers.length === 0) {
            container.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">لا توجد سيرفرات مضافة بعد. أضف سيرفرك الأول من الأعلى.</p>';
        }
    }

    /**
    * عرض جميع السيرفرات المضافة
    */
    function displayWatchServers() {
        const container = document.getElementById('watch-links-container');
        if (!container) return;
        
        container.innerHTML = '';
        
        if (customWatchLinks.length === 0) {
            container.innerHTML = '<p style="color: #666; text-align: center; padding: 20px;">لا توجد سيرفرات مضافة بعد. أضف سيرفرك الأول من الأعلى.</p>';
            return;
        }
        
        customWatchLinks.forEach((server, index) => {
            const serverId = 'server-display-' + index;
            const serverHtml = `
                <div class="server-item" id="${serverId}" style="background: #1a1a1a; border-radius: 8px; padding: 15px; margin-bottom: 10px; border: 1px solid #333; display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap; flex: 1;">
                        <span style="background: #e50914; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-play"></i>
                        </span>
                        <div>
                            <strong style="color: #e50914;">${escapeHtml(server.name)}</strong>
                            <div style="display: flex; gap: 10px; margin-top: 5px; color: #b3b3b3; font-size: 12px;">
                                <span><i class="fas fa-language"></i> ${server.lang === 'arabic' ? 'عربي' : server.lang}</span>
                                <span><i class="fas fa-hd"></i> ${server.quality}</span>
                            </div>
                        </div>
                        <div style="color: #666; font-size: 11px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            ${escapeHtml(server.url)}
                        </div>
                    </div>
                    <div>
                        <button type="button" class="remove-link-btn" onclick="removeCustomServerFromArray(${index})" style="background: #e50914; color: white; border: none; width: 30px; height: 30px; border-radius: 5px; cursor: pointer;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', serverHtml);
        });
        
        updateWatchCount();
    }

    /**
    * إزالة سيرفر من المصفوفة
    */
    function removeCustomServerFromArray(index) {
        if (confirm('هل أنت متأكد من حذف هذا السيرفر؟')) {
            customWatchLinks.splice(index, 1);
            displayWatchServers();
            showNotification('🗑️ تم حذف السيرفر', 'info');
        }
    }

    /**
    * تنظيف نموذج الإضافة
    */
    function clearCustomServerForm() {
        document.getElementById('custom-server-name').value = 'سيرفر مخصص';
        document.getElementById('custom-server-url').value = '';
        document.getElementById('custom-server-lang').value = 'arabic';
        document.getElementById('custom-server-quality').value = 'HD';
    }

    /**
    * دالة مساعدة لترميز النص
    */
    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    // ========== دالة طي وفرد المواسم ==========
    function toggleSeason(index) {
        console.log('🔄 تبديل الموسم: ' + index);
        const content = document.getElementById(`season-content-${index}`);
        const arrow = document.getElementById(`season-arrow-${index}`);
        
        if (content) {
            if (content.style.display === 'none' || content.style.display === '') {
                content.style.display = 'block';
                if (arrow) arrow.className = 'fas fa-chevron-up';
            } else {
                content.style.display = 'none';
                if (arrow) arrow.className = 'fas fa-chevron-down';
            }
        }
    }

    // ========== دوال العضوية ==========
    function selectMembership(level) {
        document.querySelectorAll('.membership-card').forEach(card => {
            card.classList.remove('selected');
            card.style.borderColor = 'var(--border)';
        });
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('selected');
            event.currentTarget.style.borderColor = 'var(--primary)';
            const radio = event.currentTarget.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }
    }

    function selectStatus(status) {
        document.querySelectorAll('.status-option').forEach(option => {
            option.classList.remove('selected');
            option.style.borderColor = 'var(--border)';
            option.style.background = 'var(--dark-light)';
        });
        if (event && event.currentTarget) {
            event.currentTarget.classList.add('selected');
            event.currentTarget.style.borderColor = 'var(--primary)';
            event.currentTarget.style.background = 'rgba(229,9,20,0.1)';
            const radio = event.currentTarget.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        }
    }

    // ========== دوال الروابط ==========
    function addWatchLink() {
        watchLinkCount++;
        const container = document.getElementById('watch-links-container');
        if (!container) {
            console.error('❌ لم يتم العثور على watch-links-container');
            return;
        }
        
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

    // ========== دوال الممثلين ==========
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

    // ========== دوال البوستر ==========
    function showPosterModal() {
        const modal = document.getElementById('posterModal');
        if (modal) modal.style.display = 'flex';
    }

    function closePosterModal() {
        const modal = document.getElementById('posterModal');
        if (modal) modal.style.display = 'none';
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

    // ========== دوال الإشعارات ==========
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

    // ========== التهيئة عند تحميل الصفحة ==========
    document.addEventListener('DOMContentLoaded', function() {
        console.log('✅ الصفحة جاهزة');
        console.log('📊 عدد المواسم: <?php echo count($seasons_data); ?>');
        console.log('🎭 عدد الممثلين: <?php echo count($cast_data); ?>');
        console.log('🎬 عدد فريق الإنتاج: <?php echo count($crew_data); ?>');
        
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
    // ========== معالجة زر الحفظ ==========
    document.addEventListener('DOMContentLoaded', function() {
        'use strict';
        
        const form = document.querySelector('#seriesForm');
        const saveButton = document.querySelector('button.btn-primary[type="submit"]');
        
        if (form && saveButton) {
            // إضافة معالج submit للنموذج
            form.addEventListener('submit', function(e) {
                const titleInput = form.querySelector('input[name="title"]');
                
                // التحقق من المتطلبات الأساسية
                if (titleInput && !titleInput.value.trim()) {
                    e.preventDefault();
                    alert('❌ الرجاء إدخال عنوان المسلسل');
                    return false;
                }
                
                // السماح بإرسال النموذج بشكل طبيعي
                return true;
            });
            
            // تأكيد أن الزر سيرسل النموذج
            saveButton.addEventListener('click', function() {
                if (form) {
                    form.submit();
                }
            });
        }
    });
    </script>
    </body>
    </html>