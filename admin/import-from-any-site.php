<?php
// admin/import-from-any-site.php - نظام متقدم موحد للإضافة والتعديل
// منع التشغيل من سطر الأوامر
if (php_sapi_name() === 'cli') {
    echo "⚠️ هذا الملف يجب تشغيله من المتصفح وليس من سطر الأوامر!\n";
    echo "استخدم الرابط: http://localhost/fayez-movie/admin/import-from-any-site.php\n";
    exit;
}

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/tmdb.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// تحديد الوضع الحالي (إضافة أو تعديل)
$current_mode = isset($_GET['mode']) ? $_GET['mode'] : (isset($_POST['mode']) ? $_POST['mode'] : 'add');
if (!in_array($current_mode, ['add', 'edit'])) {
    $current_mode = 'add';
}

// قائمة اللغات المدعومة للترجمة
$subtitle_languages = [
    'ar' => '🇸🇦 العربية',
    'en' => '🇬🇧 English',
    'fr' => '🇫🇷 Français',
    'de' => '🇩🇪 Deutsch',
    'es' => '🇪🇸 Español',
    'it' => '🇮🇹 Italiano',
    'tr' => '🇹🇷 Türkçe',
    'hi' => '🇮🇳 हिन्दी',
    'ur' => '🇵🇰 اردو',
    'ku' => '🏳️ كوردي',
    'fa' => '🇮🇷 فارسی',
    'ps' => '🇦🇫 پښتو',
    'bn' => '🇧🇩 বাংলা'
];

// قائمة خطط العضوية
$membership_levels = [
    'basic' => 'عادي (مجاني)',
    'premium' => 'مميز ⭐',
    'vip' => 'VIP 👑'
];

// =============================================
// قائمة مصادر المشاهدة والتحميل
// =============================================
$watch_sources = [
    'vidsrc' => [
        'name' => '🎬 Vidsrc.to',
        'url' => 'https://vidsrc.to/embed/',
        'type' => 'stream',
        'quality' => '4K/1080p',
        'color' => '#e50914'
    ],
    '2embed' => [
        'name' => '🎥 2Embed',
        'url' => 'https://www.2embed.cc/embed/',
        'type' => 'stream',
        'quality' => '1080p',
        'color' => '#27ae60'
    ],
    'embedsu' => [
        'name' => '📺 Embed.su',
        'url' => 'https://embed.su/embed/',
        'type' => 'stream',
        'quality' => '1080p',
        'color' => '#f39c12'
    ],
    'vidlink' => [
        'name' => '⚡ VidLink.pro',
        'url' => 'https://vidlink.pro/',
        'type' => 'stream',
        'quality' => '4K',
        'color' => '#3498db'
    ]
];

/**
 * دالة للبحث عن المحتوى المكرر
 */
function findExistingContent($pdo, $type, $tmdb_id, $title) {
    try {
        // التأكد من أن القيم موجودة
        $tmdb_id = $tmdb_id ?? '';
        $title = $title ?? '';
        
        if (!empty($tmdb_id)) {
            if ($type == 'movie') {
                $stmt = $pdo->prepare("SELECT id FROM movies WHERE tmdb_id = ?");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
            }
            $stmt->execute([$tmdb_id]);
            $result = $stmt->fetch();
            if ($result) {
                return ['id' => $result['id'], 'method' => 'tmdb_id'];
            }
        }
        
        if (!empty($title)) {
            if ($type == 'movie') {
                $stmt = $pdo->prepare("SELECT id FROM movies WHERE title = ? OR title_en = ? OR title LIKE ?");
            } else {
                $stmt = $pdo->prepare("SELECT id FROM series WHERE title = ? OR title_en = ? OR title LIKE ?");
            }
            $search = "%{$title}%";
            $stmt->execute([$title, $title, $search]);
            $result = $stmt->fetch();
            if ($result) {
                return ['id' => $result['id'], 'method' => 'title'];
            }
        }
    } catch (Exception $e) {
        error_log("خطأ في findExistingContent: " . $e->getMessage());
    }
    
    return null;
}

/**
 * جلب تفاصيل موسم معين من TMDB
 */
function fetchSeasonDetails($tmdb_id, $season_number) {
    $url = "https://api.themoviedb.org/3/tv/{$tmdb_id}/season/{$season_number}?api_key=" . TMDB_API_KEY . "&language=ar-SA";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        return json_decode($response, true);
    }
    return null;
}

/**
 * دالة إضافة المواسم والحلقات للمسلسلات الجديدة
 */
function addSeasonsAndEpisodes($pdo, $series_id, $seasons_data) {
    $season_count = 0;
    $episode_count = 0;
    $link_count = 0;
    
    if (empty($seasons_data) || !is_array($seasons_data)) {
        return [
            'seasons' => 0,
            'episodes' => 0,
            'links' => 0
        ];
    }
    
    foreach ($seasons_data as $season) {
        if (!isset($season['number']) || empty($season['number'])) continue;
        
        $season_number = (int)$season['number'];
        $season_name = $season['name'] ?? "الموسم {$season_number}";
        $season_overview = $season['overview'] ?? '';
        $season_poster = $season['poster'] ?? '';
        $season_air_date = $season['air_date'] ?? null;
        
        // إضافة الموسم
        $season_sql = "INSERT INTO seasons (series_id, season_number, name, overview, poster, air_date) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $season_stmt = $pdo->prepare($season_sql);
        $season_stmt->execute([$series_id, $season_number, $season_name, $season_overview, $season_poster, $season_air_date]);
        $season_count++;
        
        // إضافة الحلقات
        if (isset($season['episodes']) && is_array($season['episodes'])) {
            foreach ($season['episodes'] as $episode) {
                if (!isset($episode['number']) || empty($episode['number'])) continue;
                
                $episode_number = (int)$episode['number'];
                $episode_title = $episode['title'] ?? "الحلقة {$episode_number}";
                $episode_description = $episode['description'] ?? '';
                $episode_duration = isset($episode['duration']) ? (int)$episode['duration'] : 45;
                $episode_still = $episode['still_path'] ?? '';
                $episode_air_date = $episode['air_date'] ?? null;
                
                // معالجة روابط المشاهدة للحلقة
                $watch_servers = [];
                if (isset($episode['watch_servers']) && is_array($episode['watch_servers'])) {
                    foreach ($episode['watch_servers'] as $ws) {
                        if (!empty($ws['url'])) {
                            $watch_servers[] = [
                                'name' => $ws['name'] ?? 'سيرفر مشاهدة',
                                'url' => $ws['url'],
                                'lang' => $ws['lang'] ?? 'arabic',
                                'quality' => $ws['quality'] ?? 'HD'
                            ];
                            $link_count++;
                        }
                    }
                }
                
                // معالجة روابط التحميل للحلقة
                $download_servers = [];
                if (isset($episode['download_servers']) && is_array($episode['download_servers'])) {
                    foreach ($episode['download_servers'] as $ds) {
                        if (!empty($ds['url'])) {
                            $download_servers[] = [
                                'name' => $ds['name'] ?? 'سيرفر تحميل',
                                'url' => $ds['url'],
                                'lang' => $ds['lang'] ?? 'arabic',
                                'quality' => $ds['quality'] ?? 'HD'
                            ];
                            $link_count++;
                        }
                    }
                }
                
                $watch_json = !empty($watch_servers) ? json_encode($watch_servers, JSON_UNESCAPED_UNICODE) : null;
                $download_json = !empty($download_servers) ? json_encode($download_servers, JSON_UNESCAPED_UNICODE) : null;
                
                // إضافة الحلقة
                $ep_sql = "INSERT INTO episodes 
                          (series_id, season_number, episode_number, title, description, duration, still_path, air_date, watch_servers, download_servers) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $ep_stmt = $pdo->prepare($ep_sql);
                $ep_stmt->execute([
                    $series_id, 
                    $season_number, 
                    $episode_number, 
                    $episode_title, 
                    $episode_description, 
                    $episode_duration, 
                    $episode_still, 
                    $episode_air_date,
                    $watch_json, 
                    $download_json
                ]);
                $episode_count++;
            }
        }
    }
    
    return [
        'seasons' => $season_count,
        'episodes' => $episode_count,
        'links' => $link_count
    ];
}

/**
 * دالة تحديث المواسم والحلقات (تحديث تدريجي - بدون حذف)
 */
function updateSeasonsAndEpisodes($pdo, $series_id, $seasons_data) {
    $season_count = 0;
    $episode_count = 0;
    $link_count = 0;
    
    if (empty($seasons_data) || !is_array($seasons_data)) {
        return [
            'seasons' => 0,
            'episodes' => 0,
            'links' => 0
        ];
    }
    
    foreach ($seasons_data as $season) {
        if (!isset($season['number']) || empty($season['number'])) continue;
        
        $season_number = (int)$season['number'];
        $season_name = $season['name'] ?? "الموسم {$season_number}";
        $season_overview = $season['overview'] ?? '';
        $season_poster = $season['poster'] ?? '';
        $season_air_date = $season['air_date'] ?? null;
        
        // التحقق من وجود الموسم مسبقاً
        $check_season = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? AND season_number = ?");
        $check_season->execute([$series_id, $season_number]);
        $existing_season = $check_season->fetch();
        
        if ($existing_season) {
            // تحديث الموسم الموجود
            $season_sql = "UPDATE seasons SET 
                           name = ?, overview = ?, poster = ?, air_date = ? 
                           WHERE series_id = ? AND season_number = ?";
            $season_stmt = $pdo->prepare($season_sql);
            $season_stmt->execute([
                $season_name, $season_overview, $season_poster, $season_air_date,
                $series_id, $season_number
            ]);
        } else {
            // إضافة موسم جديد
            $season_sql = "INSERT INTO seasons (series_id, season_number, name, overview, poster, air_date) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $season_stmt = $pdo->prepare($season_sql);
            $season_stmt->execute([$series_id, $season_number, $season_name, $season_overview, $season_poster, $season_air_date]);
        }
        $season_count++;
        
        // معالجة الحلقات
        if (isset($season['episodes']) && is_array($season['episodes'])) {
            foreach ($season['episodes'] as $episode) {
                if (!isset($episode['number']) || empty($episode['number'])) continue;
                
                $episode_number = (int)$episode['number'];
                $episode_title = $episode['title'] ?? "الحلقة {$episode_number}";
                $episode_description = $episode['description'] ?? '';
                $episode_duration = isset($episode['duration']) ? (int)$episode['duration'] : 45;
                $episode_still = $episode['still_path'] ?? '';
                $episode_air_date = $episode['air_date'] ?? null;
                
                // معالجة روابط المشاهدة للحلقة
                $watch_servers = [];
                if (isset($episode['watch_servers']) && is_array($episode['watch_servers'])) {
                    foreach ($episode['watch_servers'] as $ws) {
                        if (!empty($ws['url'])) {
                            $watch_servers[] = [
                                'name' => $ws['name'] ?? 'سيرفر مشاهدة',
                                'url' => $ws['url'],
                                'lang' => $ws['lang'] ?? 'arabic',
                                'quality' => $ws['quality'] ?? 'HD'
                            ];
                            $link_count++;
                        }
                    }
                }
                
                // معالجة روابط التحميل للحلقة
                $download_servers = [];
                if (isset($episode['download_servers']) && is_array($episode['download_servers'])) {
                    foreach ($episode['download_servers'] as $ds) {
                        if (!empty($ds['url'])) {
                            $download_servers[] = [
                                'name' => $ds['name'] ?? 'سيرفر تحميل',
                                'url' => $ds['url'],
                                'lang' => $ds['lang'] ?? 'arabic',
                                'quality' => $ds['quality'] ?? 'HD'
                            ];
                            $link_count++;
                        }
                    }
                }
                
                $watch_json = !empty($watch_servers) ? json_encode($watch_servers, JSON_UNESCAPED_UNICODE) : null;
                $download_json = !empty($download_servers) ? json_encode($download_servers, JSON_UNESCAPED_UNICODE) : null;
                
                // التحقق من وجود الحلقة مسبقاً
                $check_episode = $pdo->prepare("SELECT id FROM episodes WHERE series_id = ? AND season_number = ? AND episode_number = ?");
                $check_episode->execute([$series_id, $season_number, $episode_number]);
                $existing_episode = $check_episode->fetch();
                
                if ($existing_episode) {
                    // تحديث الحلقة الموجودة
                    $ep_sql = "UPDATE episodes SET 
                               title = ?, description = ?, duration = ?, still_path = ?, air_date = ?,
                               watch_servers = ?, download_servers = ?
                               WHERE series_id = ? AND season_number = ? AND episode_number = ?";
                    $ep_stmt = $pdo->prepare($ep_sql);
                    $ep_stmt->execute([
                        $episode_title, $episode_description, $episode_duration, $episode_still, $episode_air_date,
                        $watch_json, $download_json,
                        $series_id, $season_number, $episode_number
                    ]);
                } else {
                    // إضافة حلقة جديدة
                    $ep_sql = "INSERT INTO episodes 
                              (series_id, season_number, episode_number, title, description, duration, still_path, air_date, watch_servers, download_servers) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $ep_stmt = $pdo->prepare($ep_sql);
                    $ep_stmt->execute([
                        $series_id, $season_number, $episode_number, 
                        $episode_title, $episode_description, $episode_duration, $episode_still, $episode_air_date,
                        $watch_json, $download_json
                    ]);
                }
                $episode_count++;
            }
        }
    }
    
    return [
        'seasons' => $season_count,
        'episodes' => $episode_count,
        'links' => $link_count
    ];
}

// قوائم البيانات للتعديل (من edit-content.php)
$all_movies = $pdo->query("SELECT id, title, year, poster FROM movies ORDER BY id DESC LIMIT 50")->fetchAll();
$all_series = $pdo->query("SELECT id, title, year, poster FROM series ORDER BY id DESC LIMIT 50")->fetchAll();

// متغيرات وضع التعديل
$item = null;
$seasons_data = [];
$watch_servers = [];
$download_servers = [];
$subtitles = [];
$edit_content_type = $_GET['type'] ?? $_POST['content_type'] ?? 'movie';
$edit_content_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['content_id']) ? (int)$_POST['content_id'] : 0);

// جلب بيانات العنصر إذا كان معرف موجود (وضع التعديل)
if ($current_mode == 'edit' && $edit_content_id > 0) {
    if ($edit_content_type == 'movie') {
        $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
        $stmt->execute([$edit_content_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$edit_content_id]);
            $watch_servers = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'movie' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$edit_content_id]);
            $download_servers = $stmt->fetchAll();
        }
    } elseif ($edit_content_type == 'series') {
        $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
        $stmt->execute([$edit_content_id]);
        $item = $stmt->fetch();
        
        if ($item) {
            try {
                // محاولة جلب المواسم من جدول seasons
                $stmt = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
                $stmt->execute([$edit_content_id]);
                $db_seasons = $stmt->fetchAll();
                
                if (!empty($db_seasons)) {
                    foreach ($db_seasons as $season) {
                        $stmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? AND season_number = ? ORDER BY episode_number");
                        $stmt->execute([$edit_content_id, $season['season_number']]);
                        $episodes = $stmt->fetchAll();
                        
                        $season_episodes = [];
                        foreach ($episodes as $ep) {
                            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'episode' AND item_id = ?");
                            $stmt->execute([$ep['id']]);
                            $ep_watch = $stmt->fetchAll();
                            
                            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'episode' AND item_id = ?");
                            $stmt->execute([$ep['id']]);
                            $ep_download = $stmt->fetchAll();
                            
                            $season_episodes[] = [
                                'number' => $ep['episode_number'],
                                'title' => $ep['title'],
                                'description' => $ep['description'] ?? '',
                                'duration' => $ep['duration'] ?? 45,
                                'still_path' => $ep['still_path'] ?? '',
                                'air_date' => $ep['air_date'] ?? null,
                                'watch_servers' => $ep_watch,
                                'download_servers' => $ep_download,
                                'id' => $ep['id']
                            ];
                        }
                        
                        $seasons_data[] = [
                            'number' => $season['season_number'],
                            'name' => $season['name'] ?? "الموسم {$season['season_number']}",
                            'overview' => $season['overview'] ?? '',
                            'poster' => $season['poster'] ?? '',
                            'air_date' => $season['air_date'] ?? null,
                            'episodes' => $season_episodes,
                            'id' => $season['id']
                        ];
                    }
                } else {
                    // إذا لم توجد مواسم، محاولة جلبها من TMDB
                    $message = "⚠️ لا توجد مواسم في قاعدة البيانات. يمكنك جلبها من TMDB.";
                    $messageType = "warning";
                }
            } catch (Exception $e) {
                $message = "❌ خطأ في جلب المواسم: " . $e->getMessage();
                $messageType = "error";
            }
            
            $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$edit_content_id]);
            $watch_servers = $stmt->fetchAll();
            
            $stmt = $pdo->prepare("SELECT * FROM download_servers WHERE item_type = 'series' AND item_id = ? ORDER BY quality DESC");
            $stmt->execute([$edit_content_id]);
            $download_servers = $stmt->fetchAll();
        }
    }
    
    if ($edit_content_id > 0 && in_array($edit_content_type, ['movie', 'series', 'episode'])) {
        $stmt = $pdo->prepare("SELECT * FROM subtitles WHERE content_type = ? AND content_id = ? ORDER BY is_default DESC, language");
        $stmt->execute([$edit_content_type, $edit_content_id]);
        $subtitles = $stmt->fetchAll();
    }
}

// معالجة جلب التفاصيل من TMDB (وضع الإضافة)
$tmdb_data = null;
$selected_id = isset($_GET['tmdb_id']) ? $_GET['tmdb_id'] : null;
$selected_type = isset($_GET['type']) ? $_GET['type'] : 'movie';
$seasons_data_tmdb = [];

if ($selected_id && $selected_type == 'tv') {
    // جلب بيانات المسلسل الأساسية
    $url = "https://api.themoviedb.org/3/tv/{$selected_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,videos,images,external_ids";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $tmdb_data = json_decode($response, true);
        
        // جلب المواسم
        if ($tmdb_data && isset($tmdb_data['seasons'])) {
            foreach ($tmdb_data['seasons'] as $season) {
                if ($season['season_number'] > 0) { // تجاهل الموسم الخاص (season 0)
                    // جلب تفاصيل الموسم
                    $season_details = fetchSeasonDetails($selected_id, $season['season_number']);
                    
                    if ($season_details) {
                        $episodes = [];
                        if (isset($season_details['episodes'])) {
                            foreach ($season_details['episodes'] as $ep) {
                                $episodes[] = [
                                    'number' => $ep['episode_number'],
                                    'title' => $ep['name'],
                                    'description' => $ep['overview'] ?? '',
                                    'duration' => $ep['runtime'] ?? 45,
                                    'still_path' => isset($ep['still_path']) ? 'https://image.tmdb.org/t/p/w500' . $ep['still_path'] : '',
                                    'air_date' => $ep['air_date'] ?? null,
                                    'watch_servers' => [],
                                    'download_servers' => []
                                ];
                            }
                        }
                        
                        $seasons_data_tmdb[] = [
                            'number' => $season['season_number'],
                            'name' => $season['name'],
                            'overview' => $season['overview'] ?? '',
                            'poster' => isset($season['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $season['poster_path'] : '',
                            'air_date' => $season['air_date'] ?? null,
                            'episodes' => $episodes,
                            'watch_servers' => [],
                            'download_servers' => []
                        ];
                    }
                }
            }
        }
    }
} elseif ($selected_id && $current_mode == 'add') {
    // جلب بيانات الفيلم
    $url = "https://api.themoviedb.org/3/{$selected_type}/{$selected_id}?api_key=" . TMDB_API_KEY . "&language=ar-SA&append_to_response=credits,videos,images,external_ids";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $tmdb_data = json_decode($response, true);
    }
}


// =============================================
// معالجات التعديل (من edit-content.php)
// =============================================

// معالجة تحديث البيانات (من edit-content.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_content']) && $current_mode == 'edit') {
    $type = $_POST['content_type'] ?? 'movie';
    $content_id = (int)($_POST['content_id'] ?? 0);
    
    if (!$content_id) {
        $message = "❌ معرف العنصر مطلوب";
        $messageType = "error";
    } else {
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
        $status = $_POST['status'] ?? 'published';
        $quality = $_POST['quality'] ?? 'HD';
        
        $poster_url = $_POST['poster_url'] ?? '';
        $backdrop_url = $_POST['backdrop_url'] ?? '';
        
        $local_poster = null;
        $local_backdrop = null;
        
        // معالجة رفع الصور
        if (isset($_FILES['poster_file']) && $_FILES['poster_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['poster_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_poster.' . $ext;
            if (move_uploaded_file($_FILES['poster_file']['tmp_name'], $upload_dir . $filename)) {
                $local_poster = 'uploads/posters/' . $filename;
            }
        }
        
        if (isset($_FILES['backdrop_file']) && $_FILES['backdrop_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = __DIR__ . '/../uploads/posters/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $ext = pathinfo($_FILES['backdrop_file']['name'], PATHINFO_EXTENSION);
            $filename = time() . '_' . uniqid() . '_backdrop.' . $ext;
            if (move_uploaded_file($_FILES['backdrop_file']['tmp_name'], $upload_dir . $filename)) {
                $local_backdrop = 'uploads/posters/' . $filename;
            }
        }
        
        try {
            if ($type == 'movie') {
                $sql = "UPDATE movies SET 
                        title = ?, 
                        title_en = ?, 
                        description = ?, 
                        year = ?, 
                        country = ?, 
                        language = ?, 
                        genre = ?, 
                        duration = ?, 
                        imdb_rating = ?, 
                        membership_level = ?,
                        status = ?,
                        quality = ?";
                
                $params = [$title, $title_en, $overview, $year, $country, $language, $genre, $duration, $imdb_rating, $membership_level, $status, $quality];
                
                if ($local_poster) {
                    $sql .= ", poster = ?";
                    $params[] = $local_poster;
                }
                
                if ($local_backdrop) {
                    $sql .= ", backdrop = ?";
                    $params[] = $local_backdrop;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $content_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = "✅ تم تحديث الفيلم بنجاح!";
                
            } elseif ($type == 'series') {
                $sql = "UPDATE series SET 
                        title = ?, 
                        title_en = ?, 
                        description = ?, 
                        year = ?, 
                        country = ?, 
                        language = ?, 
                        genre = ?, 
                        imdb_rating = ?, 
                        membership_level = ?,
                        status = ?,
                        quality = ?";
                
                $params = [$title, $title_en, $overview, $year, $country, $language, $genre, $imdb_rating, $membership_level, $status, $quality];
                
                if ($local_poster) {
                    $sql .= ", poster = ?";
                    $params[] = $local_poster;
                }
                
                if ($local_backdrop) {
                    $sql .= ", backdrop = ?";
                    $params[] = $local_backdrop;
                }
                
                $sql .= " WHERE id = ?";
                $params[] = $content_id;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = "✅ تم تحديث المسلسل بنجاح!";
            }
            
            $messageType = "success";
            header("Location: import-from-any-site.php?mode=edit&type=$type&id=$content_id&updated=1");
            exit;
            
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// معالجة إضافة رابط مشاهدة (من edit-content.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_watch_link']) && $current_mode == 'edit') {
    $item_type = $_POST['item_type'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر مشاهدة';
    $server_url = $_POST['server_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $language = $_POST['language'] ?? 'arabic';
    
    if ($item_id && !empty($server_url)) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS watch_servers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    item_type VARCHAR(20) NOT NULL,
                    item_id INT NOT NULL,
                    server_name VARCHAR(255),
                    server_url TEXT,
                    quality VARCHAR(50),
                    language VARCHAR(50),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_item (item_type, item_id)
                )
            ");
            
            $sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item_type, $item_id, $server_name, $server_url, $quality, $language]);
            
            header("Location: import-from-any-site.php?mode=edit&type=$item_type&id=$item_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// معالجة حذف رابط مشاهدة (من edit-content.php)
if (isset($_GET['delete_watch_link']) && $current_mode == 'edit') {
    $link_id = (int)$_GET['delete_watch_link'];
    $type = $_GET['type'] ?? 'movie';
    $id = (int)$_GET['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM watch_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        header("Location: import-from-any-site.php?mode=edit&type=$type&id=$id&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}

// معالجة إضافة رابط تحميل (من edit-content.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_download_link']) && $current_mode == 'edit') {
    $item_type = $_POST['item_type'] ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر تحميل';
    $download_url = $_POST['download_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $size = $_POST['size'] ?? '';
    
    if ($item_id && !empty($download_url)) {
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS download_servers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    item_type VARCHAR(20) NOT NULL,
                    item_id INT NOT NULL,
                    server_name VARCHAR(255),
                    download_url TEXT,
                    quality VARCHAR(50),
                    size VARCHAR(50),
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_item (item_type, item_id)
                )
            ");
            
            $sql = "INSERT INTO download_servers (item_type, item_id, server_name, download_url, quality, size) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item_type, $item_id, $server_name, $download_url, $quality, $size]);
            
            header("Location: import-from-any-site.php?mode=edit&type=$item_type&id=$item_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// معالجة حذف رابط تحميل (من edit-content.php)
if (isset($_GET['delete_download_link']) && $current_mode == 'edit') {
    $link_id = (int)$_GET['delete_download_link'];
    $type = $_GET['type'] ?? 'movie';
    $id = (int)$_GET['id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM download_servers WHERE id = ?");
        $stmt->execute([$link_id]);
        
        header("Location: import-from-any-site.php?mode=edit&type=$type&id=$id&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}

// معالجة حذف حلقة من المسلسل
if (isset($_GET['delete_episode']) && $current_mode == 'edit') {
    $episode_id = (int)$_GET['delete_episode'];
    $series_id = (int)$_GET['series_id'];
    
    try {
        // حذف روابط المشاهدة والتحميل للحلقة أولاً
        $stmt = $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'episode' AND item_id = ?");
        $stmt->execute([$episode_id]);
        
        $stmt = $pdo->prepare("DELETE FROM download_servers WHERE item_type = 'episode' AND item_id = ?");
        $stmt->execute([$episode_id]);
        
        // حذف الترجمات
        $stmt = $pdo->prepare("DELETE FROM subtitles WHERE content_type = 'episode' AND content_id = ?");
        $stmt->execute([$episode_id]);
        
        // حذف الحلقة نفسها
        $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
        $stmt->execute([$episode_id]);
        
        header("Location: import-from-any-site.php?mode=edit&type=series&id=$series_id&deleted=1");
        exit;
    } catch (Exception $e) {
        $message = "❌ خطأ: " . $e->getMessage();
        $messageType = "error";
    }
}

// معالجة إضافة رابط مشاهدة للحلقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_episode_watch_link']) && $current_mode == 'edit') {
    $episode_id = (int)($_POST['episode_id'] ?? 0);
    $series_id = (int)($_POST['series_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر مشاهدة';
    $server_url = $_POST['server_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $language = $_POST['language'] ?? 'arabic';
    
    if ($episode_id && !empty($server_url)) {
        try {
            $sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['episode', $episode_id, $server_name, $server_url, $quality, $language]);
            
            header("Location: import-from-any-site.php?mode=edit&type=series&id=$series_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// معالجة إضافة رابط تحميل للحلقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_episode_download_link']) && $current_mode == 'edit') {
    $episode_id = (int)($_POST['episode_id'] ?? 0);
    $series_id = (int)($_POST['series_id'] ?? 0);
    $server_name = $_POST['server_name'] ?? 'سيرفر تحميل';
    $download_url = $_POST['download_url'] ?? '';
    $quality = $_POST['quality'] ?? 'HD';
    $size = $_POST['size'] ?? '';
    
    if ($episode_id && !empty($download_url)) {
        try {
            $sql = "INSERT INTO download_servers (item_type, item_id, server_name, download_url, quality, size) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['episode', $episode_id, $server_name, $download_url, $quality, $size]);
            
            header("Location: import-from-any-site.php?mode=edit&type=series&id=$series_id&added=1");
            exit;
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// =============================================
// معالجات الإضافة (من import-from-any-site.php)
// =============================================
$message = '';
$messageType = '';

if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && $current_mode == 'add') {
    // تعريف جميع المتغيرات أولاً
    $type = $_POST['content_type'] ?? 'movie';
    // تحويل 'tv' إلى 'series' للتوافق
    if ($type == 'tv') {
        $type = 'series';
    }
    
    $tmdb_id = $_POST['tmdb_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $title_en = $_POST['title_en'] ?? '';
    $overview = $_POST['overview'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    $country = $_POST['country'] ?? '';
    $language = $_POST['language'] ?? 'ar';
    $genre = $_POST['genre'] ?? '';
    $duration = $_POST['duration'] ?? 0;
    $imdb_rating = $_POST['imdb_rating'] ?? 0;
    $poster_url = $_POST['poster_url'] ?? '';
    $backdrop_url = $_POST['backdrop_url'] ?? '';
    $action = $_POST['action'] ?? 'add';
    $membership_level = $_POST['membership_level'] ?? 'basic';
    
    // التحقق من البيانات الأساسية
    if (empty($title)) {
        $message = "❌ عنوان المحتوى مطلوب";
        $messageType = "error";
    } else {
        // =============================================
        // معالجة رفع الملفات اليدوية
        // =============================================
        $uploaded_files = [
            'video' => '',
            'download' => '',
            'subtitle' => []
        ];
        
        // إنشاء المجلدات المطلوبة
        $upload_dirs = [
            'videos' => __DIR__ . '/../uploads/videos/',
            'downloads' => __DIR__ . '/../uploads/downloads/',
            'subtitles' => __DIR__ . '/../uploads/subtitles/'
        ];
        
        foreach ($upload_dirs as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
        }
        
        // معالجة رفع ملف الفيديو
        if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
            $video_ext = pathinfo($_FILES['video_file']['name'], PATHINFO_EXTENSION);
            $allowed_video_ext = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm'];
            
            if (in_array(strtolower($video_ext), $allowed_video_ext)) {
                $video_name = time() . '_' . uniqid() . '_video.' . $video_ext;
                $video_path = $upload_dirs['videos'] . $video_name;
                
                if (move_uploaded_file($_FILES['video_file']['tmp_name'], $video_path)) {
                    $uploaded_files['video'] = 'uploads/videos/' . $video_name;
                }
            } else {
                $message = "❌ صيغة الفيديو غير مدعومة. الصيغ المسموحة: mp4, mkv, avi, mov, wmv, flv, webm";
                $messageType = "error";
            }
        }
        
        // معالجة رفع ملف التحميل
        if (isset($_FILES['download_file']) && $_FILES['download_file']['error'] === UPLOAD_ERR_OK) {
            $download_ext = pathinfo($_FILES['download_file']['name'], PATHINFO_EXTENSION);
            $download_name = time() . '_' . uniqid() . '_download.' . $download_ext;
            $download_path = $upload_dirs['downloads'] . $download_name;
            
            if (move_uploaded_file($_FILES['download_file']['tmp_name'], $download_path)) {
                $uploaded_files['download'] = 'uploads/downloads/' . $download_name;
            }
        }
        
        // معالجة الصور
        $local_poster = '';
        $local_backdrop = '';
        
        if (!empty($poster_url)) {
            $poster_name = time() . '_' . uniqid() . '_poster.jpg';
            $poster_content = @file_get_contents($poster_url);
            if ($poster_content !== false) {
                file_put_contents($upload_dirs['videos'] . $poster_name, $poster_content);
                $local_poster = 'uploads/videos/' . $poster_name;
            }
        }
        
        if (!empty($backdrop_url)) {
            $backdrop_name = time() . '_' . uniqid() . '_backdrop.jpg';
            $backdrop_content = @file_get_contents($backdrop_url);
            if ($backdrop_content !== false) {
                file_put_contents($upload_dirs['videos'] . $backdrop_name, $backdrop_content);
                $local_backdrop = 'uploads/videos/' . $backdrop_name;
            }
        }
        
        try {
            // للتصحيح - عرض البيانات المرسلة
            error_log("POST data: " . print_r($_POST, true));
            error_log("بيانات الفيلم: " . print_r([
                'title' => $title,
                'tmdb_id' => $tmdb_id,
                'membership_level' => $membership_level
            ], true));
            
            // البحث عن المحتوى المكرر - المتغيرات معرفة الآن
            $existing = findExistingContent($pdo, $type, $tmdb_id, $title);
            
            // إحصائيات الروابط والملفات
            $links_count = 0;
            $episodes_count = 0;
            $seasons_count = 0;
            
            if ($type == 'movie') {
                // =============================================
                // معالجة الأفلام
                // =============================================
                if ($existing) {
                    // تحديث فيلم موجود
                    $sql = "UPDATE movies SET 
                            tmdb_id = ?, title = ?, title_en = ?, description = ?, 
                            poster = IF(? != '', ?, poster), 
                            backdrop = IF(? != '', ?, backdrop),
                            year = ?, country = ?, language = ?, genre = ?, 
                            duration = ?, imdb_rating = ?, membership_level = ?,
                            video_url = IF(? != '', ?, video_url),
                            download_url = IF(? != '', ?, download_url),
                            updated_at = NOW()
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $tmdb_id, $title, $title_en, $overview,
                        $local_poster, $local_poster,
                        $local_backdrop, $local_backdrop,
                        $year, $country, $language, $genre, $duration, $imdb_rating,
                        $membership_level,
                        $uploaded_files['video'], $uploaded_files['video'],
                        $uploaded_files['download'], $uploaded_files['download'],
                        $existing['id']
                    ]);
                    
                    if (!$result) {
                        throw new Exception("فشل تحديث الفيلم: " . print_r($stmt->errorInfo(), true));
                    }
                    
                    $content_id = $existing['id'];
                    $message = "✅ تم تحديث الفيلم بنجاح!";
                } else {
                    // إضافة فيلم جديد
                    $sql = "INSERT INTO movies (tmdb_id, title, title_en, description, poster, backdrop, year, country, language, genre, duration, imdb_rating, membership_level, video_url, download_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $tmdb_id, $title, $title_en, $overview, 
                        $local_poster, $local_backdrop, 
                        $year, $country, $language, $genre, $duration, $imdb_rating, 
                        $membership_level,
                        $uploaded_files['video'],
                        $uploaded_files['download']
                    ]);
                    
                    if (!$result) {
                        throw new Exception("فشل إضافة الفيلم: " . print_r($stmt->errorInfo(), true));
                    }
                    
                    $content_id = $pdo->lastInsertId();
                    $message = "✅ تم إضافة الفيلم الجديد بنجاح!";
                }
            } else {
                // =============================================
                // معالجة المسلسلات (تحديث تدريجي - بدون حذف)
                // =============================================
                if ($existing) {
                    // تحديث مسلسل موجود
                    $sql = "UPDATE series SET 
                            tmdb_id = ?, title = ?, title_en = ?, description = ?, 
                            poster = IF(? != '', ?, poster), 
                            backdrop = IF(? != '', ?, backdrop),
                            year = ?, country = ?, language = ?, genre = ?, 
                            imdb_rating = ?, membership_level = ?,
                            updated_at = NOW()
                            WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $tmdb_id, $title, $title_en, $overview,
                        $local_poster, $local_poster,
                        $local_backdrop, $local_backdrop,
                        $year, $country, $language, $genre, $imdb_rating,
                        $membership_level,
                        $existing['id']
                    ]);
                    
                    if (!$result) {
                        throw new Exception("فشل تحديث المسلسل: " . print_r($stmt->errorInfo(), true));
                    }
                    
                    $content_id = $existing['id'];
                    
                    $message = "✅ تم تحديث بيانات المسلسل الأساسية بنجاح!";
                    
                    // معالجة المواسم والحلقات (تحديث تدريجي - بدون حذف)
                    if (isset($_POST['seasons']) && is_array($_POST['seasons']) && count($_POST['seasons']) > 0) {
                        $result = updateSeasonsAndEpisodes($pdo, $content_id, $_POST['seasons']);
                        $seasons_count = $result['seasons'];
                        $episodes_count = $result['episodes'];
                        $links_count += $result['links'];
                        
                        $message .= " وتم تحديث/إضافة $seasons_count مواسم و $episodes_count حلقات و $result[links] رابط";
                    }
                } else {
                    // إضافة مسلسل جديد
                    $sql = "INSERT INTO series (tmdb_id, title, title_en, description, poster, backdrop, year, country, language, genre, imdb_rating, membership_level, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    $stmt = $pdo->prepare($sql);
                    $result = $stmt->execute([
                        $tmdb_id, $title, $title_en, $overview, 
                        $local_poster, $local_backdrop, 
                        $year, $country, $language, $genre, $imdb_rating, 
                        $membership_level
                    ]);
                    
                    if (!$result) {
                        throw new Exception("فشل إضافة المسلسل: " . print_r($stmt->errorInfo(), true));
                    }
                    
                    $content_id = $pdo->lastInsertId();
                    
                    $message = "✅ تم إضافة المسلسل الجديد بنجاح!";
                    
                    // إضافة المواسم والحلقات للمسلسل الجديد
                    if (isset($_POST['seasons']) && is_array($_POST['seasons']) && count($_POST['seasons']) > 0) {
                        $result = addSeasonsAndEpisodes($pdo, $content_id, $_POST['seasons']);
                        $seasons_count = $result['seasons'];
                        $episodes_count = $result['episodes'];
                        $links_count += $result['links'];
                        
                        $message .= " مع $seasons_count مواسم و $episodes_count حلقات و $result[links] رابط";
                    }
                }
            }
            
            // =============================================
            // معالجة الروابط العامة (للمسلسلات والأفلام)
            // =============================================
            
            // إنشاء جدول watch_servers إذا لم يكن موجوداً
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS watch_servers (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        item_type VARCHAR(20) NOT NULL,
                        item_id INT NOT NULL,
                        server_name VARCHAR(255),
                        server_url TEXT,
                        quality VARCHAR(50),
                        language VARCHAR(50),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_item (item_type, item_id)
                    )
                ");
            } catch (Exception $e) {
                error_log("خطأ في إنشاء جدول watch_servers: " . $e->getMessage());
            }
            
            // إضافة الروابط العامة الجديدة (بدون حذف القديمة)
            if (isset($_POST['links']) && is_array($_POST['links'])) {
                foreach ($_POST['links'] as $link) {
                    if (!empty($link['url'])) {
                        $link_sql = "INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) VALUES (?, ?, ?, ?, ?, ?)";
                        $link_stmt = $pdo->prepare($link_sql);
                        $link_stmt->execute([$type, $content_id, $link['name'], $link['url'], $link['quality'], $link['lang']]);
                        $links_count++;
                    }
                }
            }
            
            // =============================================
            // معالجة الترجمات
            // =============================================
            $subtitles_count = 0;
            
            try {
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS subtitles (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        content_type ENUM('movie', 'series', 'episode') NOT NULL,
                        content_id INT NOT NULL,
                        language VARCHAR(50) NOT NULL,
                        language_code VARCHAR(10) NOT NULL,
                        subtitle_url TEXT,
                        subtitle_file VARCHAR(500),
                        is_default BOOLEAN DEFAULT FALSE,
                        downloads INT DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_content (content_type, content_id),
                        INDEX idx_language (language_code)
                    )
                ");
            } catch (Exception $e) {
                error_log("خطأ في إنشاء جدول subtitles: " . $e->getMessage());
            }
            
            if (isset($_POST['subtitles']) && is_array($_POST['subtitles'])) {
                foreach ($_POST['subtitles'] as $index => $subtitle) {
                    if (!empty($subtitle['language_code'])) {
                        $subtitle_file_path = '';
                        
                        if (isset($_FILES['subtitle_files']['name'][$index]) && $_FILES['subtitle_files']['error'][$index] === UPLOAD_ERR_OK) {
                            $file_ext = pathinfo($_FILES['subtitle_files']['name'][$index], PATHINFO_EXTENSION);
                            $allowed_sub_ext = ['srt', 'vtt', 'ass', 'ssa', 'sub'];
                            
                            if (in_array(strtolower($file_ext), $allowed_sub_ext)) {
                                $file_name = time() . '_' . uniqid() . '.' . $file_ext;
                                $file_path = $upload_dirs['subtitles'] . $file_name;
                                
                                if (move_uploaded_file($_FILES['subtitle_files']['tmp_name'][$index], $file_path)) {
                                    $subtitle_file_path = 'uploads/subtitles/' . $file_name;
                                }
                            }
                        }
                        
                        if (isset($subtitle['is_default']) && $subtitle['is_default'] == 1) {
                            $clear_default = $pdo->prepare("UPDATE subtitles SET is_default = FALSE WHERE content_type = ? AND content_id = ?");
                            $clear_default->execute([$type, $content_id]);
                        }
                        
                        $sub_sql = "INSERT INTO subtitles (content_type, content_id, language, language_code, subtitle_url, subtitle_file, is_default, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                        $sub_stmt = $pdo->prepare($sub_sql);
                        $sub_stmt->execute([
                            $type, 
                            $content_id, 
                            $subtitle['language_name'] ?? $subtitle_languages[$subtitle['language_code']] ?? $subtitle['language_code'], 
                            $subtitle['language_code'], 
                            $subtitle['url'] ?? '', 
                            $subtitle_file_path,
                            isset($subtitle['is_default']) ? 1 : 0
                        ]);
                        
                        $subtitles_count++;
                    }
                }
            }
            
            $messageType = "success";
            
            // إضافة إحصائيات للمسلسلات
            if ($type == 'series' && ($seasons_count > 0 || $episodes_count > 0)) {
                // تم إضافة الإحصائيات في الرسالة بالفعل
            } else {
                $message .= " ($links_count رابط عام)";
            }
            
            if (!empty($uploaded_files['video'])) {
                $message .= " + فيديو مرفوع";
            }
            if (!empty($uploaded_files['download'])) {
                $message .= " + ملف تحميل";
            }
            if ($subtitles_count > 0) {
                $message .= " + $subtitles_count ترجمة";
            }
            
        } catch (Exception $e) {
            $message = "❌ خطأ: " . $e->getMessage();
            $messageType = "error";
            error_log("خطأ في استيراد المحتوى: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
}

// جلب قائمة الأفلام والمسلسلات للتعديل
$all_movies = $pdo->query("SELECT id, title, year FROM movies ORDER BY id DESC LIMIT 20")->fetchAll();
$all_series = $pdo->query("SELECT id, title, year FROM series ORDER BY id DESC LIMIT 20")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جلب متقدم مع إدارة المواسم والحلقات - ويزي برو</title>
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
        
        .alert-warning {
            background: rgba(243, 156, 18, 0.1);
            border: 1px solid #f39c12;
            color: #f39c12;
        }
        
        .duplicate-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-update {
            background: #f39c12;
            color: #000;
        }
        
        .btn-view {
            background: #3498db;
            color: #fff;
        }
        
        .btn-cancel {
            background: #e50914;
            color: #fff;
        }
        
        .search-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
            margin-bottom: 30px;
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
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
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
        
        .form-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        
        .form-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 1px solid #333;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }
        
        .form-tab {
            padding: 10px 25px;
            background: transparent;
            border: none;
            color: #b3b3b3;
            font-weight: bold;
            cursor: pointer;
            border-radius: 30px;
            transition: 0.3s;
        }
        
        .form-tab.active {
            background: #e50914;
            color: #fff;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .preview-images {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .preview-box {
            background: #252525;
            border-radius: 10px;
            padding: 10px;
        }
        
        .preview-box img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
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
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #e50914;
        }
        
        /* ===== تنسيقات الرفع اليدوي ===== */
        .upload-section {
            background: #1a2a1a;
            border: 2px dashed #27ae60;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }
        
        .upload-title {
            color: #27ae60;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .upload-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .upload-box {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .upload-icon {
            font-size: 40px;
            color: #27ae60;
            margin-bottom: 10px;
        }
        
        .upload-label {
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .upload-hint {
            color: #b3b3b3;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .file-input {
            width: 100%;
            padding: 10px;
            background: #1a1a1a;
            border: 1px solid #27ae60;
            border-radius: 5px;
            color: #fff;
            margin-top: 10px;
        }
        
        /* ===== تنسيقات خيارات العضوية ===== */
        .membership-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            border: 1px solid #333;
        }
        
        .membership-title {
            color: #e50914;
            font-size: 18px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .membership-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .membership-option {
            background: #252525;
            border: 2px solid;
            border-radius: 10px;
            padding: 15px;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
        }
        
        .membership-option:hover {
            transform: translateY(-3px);
        }
        
        .membership-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .membership-option .checkmark {
            position: absolute;
            top: 10px;
            left: 10px;
            color: #27ae60;
            font-size: 18px;
            display: none;
        }
        
        .membership-option input[type="radio"]:checked ~ .checkmark {
            display: block;
        }
        
        .membership-content {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .membership-name {
            font-size: 18px;
            font-weight: bold;
        }
        
        .membership-desc {
            color: #b3b3b3;
            font-size: 13px;
        }
        
        .membership-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            width: fit-content;
        }
        
        .badge-basic {
            background: #6c757d;
            color: white;
        }
        
        .badge-premium {
            background: #e50914;
            color: white;
        }
        
        .badge-vip {
            background: gold;
            color: black;
        }
        
        /* ===== تنسيقات الروابط ===== */
        .links-section {
            margin: 20px 0;
        }
        /* ===== أزرار الإجراءات ===== */
.action-btn {
    background: transparent;
    border: 1px solid #333;
    color: #fff;
    width: 30px;
    height: 30px;
    border-radius: 5px;
    cursor: pointer;
    transition: 0.3s;
    margin: 0 2px;
}

.action-btn:hover {
    border-color: #3498db;
    color: #3498db;
}

.delete-btn {
    background: transparent;
    border: 1px solid #333;
    color: #fff;
    width: 30px;
    height: 30px;
    border-radius: 5px;
    cursor: pointer;
    transition: 0.3s;
    margin: 0 2px;
}

.delete-btn:hover {
    border-color: #e50914;
    color: #e50914;
}

.episode-actions {
    display: flex;
    gap: 5px;
}

.episode-actions button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
        
        .add-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
        }
        
        .add-btn:hover {
            background: #219a52;
        }
        
        .add-btn.small {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        .link-item {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 1fr 2fr 2fr 1fr auto;
            gap: 10px;
            align-items: center;
        }
        
        .link-item select,
        .link-item input {
            padding: 8px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
        }
        
        .remove-btn {
            background: #e50914;
            color: #fff;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        /* ===== تنسيقات الترجمة ===== */
        .subtitles-section {
            margin: 20px 0;
        }
        
        .subtitle-item {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            display: grid;
            grid-template-columns: 1fr 2fr 1fr auto auto;
            gap: 10px;
            align-items: center;
        }
        
        .subtitle-item select,
        .subtitle-item input {
            padding: 8px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
        }
        
        .subtitle-file-input {
            padding: 5px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
        }
        
        .default-checkbox {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #b3b3b3;
        }
        
        .default-checkbox input {
            width: auto;
        }
        
        /* ===== تنسيقات المواسم والحلقات ===== */
        .seasons-management {
            margin: 20px 0;
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            max-height: 600px;
            overflow-y: auto;
        }
        
        .seasons-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            background: #1a1a1a;
            padding: 10px 0;
            z-index: 10;
        }
        
        .seasons-header h3 {
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .add-season-btn {
            background: #27ae60;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .season-item {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        
        .season-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .season-info {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .season-info input {
            padding: 5px 10px;
            background: #1a1a1a;
            border: 1px solid #333;
            border-radius: 5px;
            color: #fff;
        }
        
        .season-actions {
            display: flex;
            gap: 10px;
        }
        
        .season-actions button {
            background: transparent;
            border: 1px solid #333;
            color: #fff;
            width: 30px;
            height: 30px;
            border-radius: 5px;
            cursor: pointer;
        }
        
        .season-actions button:hover {
            border-color: #e50914;
        }
        
        .season-links {
            margin: 10px 0;
            padding: 10px;
            background: #1a1a1a;
            border-radius: 5px;
        }
        
        .links-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            color: #e50914;
            font-size: 14px;
        }
        
        .episodes-section {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #333;
        }
        
        .episodes-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .add-episode-btn {
            background: #3498db;
            color: #fff;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .episode-item {
            background: #1a1a1a;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .episode-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .episode-info {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .episode-info input {
            padding: 5px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 3px;
            color: #fff;
        }
        
        .episode-info .episode-number {
            width: 60px;
        }
        
        .episode-info .episode-title-input {
            width: 200px;
        }
        
        .episode-info .episode-duration {
            width: 70px;
        }
        
        .episode-actions button {
            background: transparent;
            border: 1px solid #333;
            color: #fff;
            width: 25px;
            height: 25px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .episode-actions button:hover {
            border-color: #e50914;
        }
        
        .episode-links {
            margin: 10px 0 0 20px;
            padding: 10px;
            background: #252525;
            border-radius: 5px;
        }
        
        .delete-option {
            margin: 15px 0;
            padding: 10px;
            background: #2a1a1a;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid #e50914;
        }
        
        .fetch-info {
            background: #252525;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            border-right: 4px solid #e50914;
        }
        
        .fetch-info p {
            color: #b3b3b3;
            margin: 5px 0;
        }
        
        .fetch-info i {
            color: #e50914;
        }
        
        .existing-list {
            margin-top: 30px;
            padding: 20px;
            background: #252525;
            border-radius: 10px;
        }
        
        .existing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        
        .existing-item {
            background: #1a1a1a;
            padding: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .existing-item a {
            color: #3498db;
            text-decoration: none;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #b3b3b3;
        }
        
        /* ===== تبويبات الوضع الرئيسي ===== */
        .mode-tabs {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .mode-tab {
            padding: 12px 25px;
            background: #252525;
            border: 2px solid #333;
            border-radius: 10px;
            color: #b3b3b3;
            font-weight: bold;
            text-decoration: none;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .mode-tab:hover {
            border-color: #e50914;
            color: #e50914;
        }
        
        .mode-tab.active {
            background: #e50914;
            border-color: #e50914;
            color: #fff;
        }
        
        /* ===== تنسيقات قسم التعديل ===== */
        .selector-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .selector-title {
            color: #e50914;
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selector-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .selector-box {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
        }
        
        .selector-box h3 {
            color: #e50914;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .selector-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #333;
            border-radius: 5px;
        }
        
        .selector-item {
            padding: 10px 15px;
            border-bottom: 1px solid #333;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .selector-item:hover {
            background: #333;
        }
        
        .selector-item.selected {
            background: #e50914;
            color: white;
        }
        
        .selector-item-info {
            flex: 1;
            margin: 0 10px;
        }
        
        .selector-item-title {
            font-weight: bold;
        }
        
        .selector-item-year {
            font-size: 11px;
            color: #b3b3b3;
        }
        
        .edit-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .section-header h3 {
            color: #e50914;
        }
        
        .links-table {
            width: 100%;
            border-collapse: collapse;
            background: #252525;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .links-table th {
            background: #333;
            color: #e50914;
            padding: 10px;
        }
        
        .links-table td {
            padding: 10px;
            border-bottom: 1px solid #333;
        }
        
        .links-table tr:hover {
            background: #2a2a2a;
        }
        
        .delete-btn {
            background: #e50914;
            color: #fff;
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 12px;
            border: none;
            cursor: pointer;
        }
        
        .delete-btn:hover {
            background: #b20710;
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
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            background: #219a52;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #b3b3b3;
        }
        
        @media (max-width: 768px) {
            .selector-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .preview-images {
                grid-template-columns: 1fr;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .link-item {
                grid-template-columns: 1fr;
            }
            
            .membership-grid {
                grid-template-columns: 1fr;
            }
            
            .season-header,
            .episode-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .episode-info {
                flex-direction: column;
                align-items: flex-start;
                width: 100%;
            }
            
            .episode-info input {
                width: 100% !important;
            }
        }
        
        /* أنماط المواسم والحلقات */
        .seasons-section {
            padding: 0;
        }
        
        .action-btn {
            background: #e50914;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #c20708;
            transform: scale(1.05);
        }
        
        .action-btn i {
            margin-right: 5px;
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
            <i class="fas fa-magic"></i>
            نظام متقدم للإضافة والتعديل
        </h1>
        
        <!-- تبويبات الوضع الرئيسي -->
        <div class="mode-tabs">
            <a href="?mode=add" class="mode-tab <?php echo ($current_mode == 'add') ? 'active' : ''; ?>">
                <i class="fas fa-plus"></i> إضافة محتوى جديد
            </a>
            <a href="?mode=edit" class="mode-tab <?php echo ($current_mode == 'edit') ? 'active' : ''; ?>">
                <i class="fas fa-edit"></i> تعديل محتوى موجود
            </a>
        </div>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
            
            <?php if ($messageType == 'warning' && isset($duplicate_id)): ?>
            <div class="duplicate-actions">
                <form method="POST" style="display: inline;" enctype="multipart/form-data">
                    <input type="hidden" name="content_type" value="<?php echo $selected_type; ?>">
                    <input type="hidden" name="tmdb_id" value="<?php echo $_POST['tmdb_id'] ?? ''; ?>">
                    <input type="hidden" name="title" value="<?php echo $_POST['title'] ?? ''; ?>">
                    <input type="hidden" name="title_en" value="<?php echo $_POST['title_en'] ?? ''; ?>">
                    <input type="hidden" name="overview" value="<?php echo $_POST['overview'] ?? ''; ?>">
                    <input type="hidden" name="year" value="<?php echo $_POST['year'] ?? ''; ?>">
                    <input type="hidden" name="country" value="<?php echo $_POST['country'] ?? ''; ?>">
                    <input type="hidden" name="language" value="<?php echo $_POST['language'] ?? ''; ?>">
                    <input type="hidden" name="genre" value="<?php echo $_POST['genre'] ?? ''; ?>">
                    <input type="hidden" name="duration" value="<?php echo $_POST['duration'] ?? ''; ?>">
                    <input type="hidden" name="imdb_rating" value="<?php echo $_POST['imdb_rating'] ?? ''; ?>">
                    <input type="hidden" name="poster_url" value="<?php echo $_POST['poster_url'] ?? ''; ?>">
                    <input type="hidden" name="backdrop_url" value="<?php echo $_POST['backdrop_url'] ?? ''; ?>">
                    <input type="hidden" name="membership_level" value="<?php echo $_POST['membership_level'] ?? 'basic'; ?>">
                    <input type="hidden" name="action" value="update">
                    <button type="submit" class="btn btn-update">🔄 تحديث المحتوى الموجود</button>
                </form>
                <a href="import-from-any-site.php?mode=edit&type=<?php echo $selected_type; ?>&id=<?php echo $duplicate_id; ?>" class="btn btn-view">👁️ عرض المحتوى الموجود</a>
                <a href="import-from-any-site.php" class="btn btn-cancel">❌ إلغاء</a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if ($current_mode == 'add'): ?>
        <!-- قسم الإضافة -->
        
        <!-- قسم البحث -->
        <div class="search-section">
            <div class="section-title">
                <i class="fas fa-search"></i>
                ابحث عن المحتوى
            </div>
            
            <div class="type-selector">
                <div class="type-btn active" onclick="setSearchType('movie')" id="searchMovieBtn">🎬 فيلم</div>
                <div class="type-btn" onclick="setSearchType('tv')" id="searchTvBtn">📺 مسلسل</div>
            </div>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="اكتب اسم الفيلم أو المسلسل..." onkeypress="if(event.key==='Enter') searchTMDB()">
                <button onclick="searchTMDB()"><i class="fas fa-search"></i> بحث</button>
            </div>
            
            <div id="searchResults" class="results-grid"></div>
            <div id="searchLoading" class="loading" style="display: none;">جاري البحث...</div>
        </div>
        
        <?php if ($tmdb_data): ?>
        <!-- نموذج الإضافة المتقدم -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-edit"></i>
                إضافة: <?php echo htmlspecialchars($tmdb_data['title'] ?? $tmdb_data['name']); ?>
                <?php if ($selected_type == 'tv' && !empty($seasons_data_tmdb)): ?>
                <span style="background: #e50914; padding: 3px 10px; border-radius: 20px; font-size: 14px; margin-right: 10px;">
                    <i class="fas fa-layer-group"></i> <?php echo count($seasons_data_tmdb); ?> مواسم
                </span>
                <?php endif; ?>
            </div>
            
            <div class="form-tabs">
                <button class="form-tab active" onclick="showFormTab('basic')">📋 معلومات أساسية</button>
                <button class="form-tab" onclick="showFormTab('membership')">👑 مستوى العضوية</button>
                <?php if ($selected_type == 'tv'): ?>
                <button class="form-tab" onclick="showFormTab('seasons')">📺 المواسم والحلقات</button>
                <?php endif; ?>
                <button class="form-tab" onclick="showFormTab('upload')">📁 رفع يدوي</button>
                <button class="form-tab" onclick="showFormTab('links')">🔗 روابط عامة</button>
                <button class="form-tab" onclick="showFormTab('subtitles')">📝 ترجمة</button>
                <button class="form-tab" onclick="showFormTab('advanced')">⚙️ إعدادات متقدمة</button>
            </div>
            
            <form method="POST" id="contentForm" enctype="multipart/form-data">
                <input type="hidden" name="content_type" value="<?php echo $selected_type; ?>">
                <input type="hidden" name="tmdb_id" value="<?php echo $selected_id; ?>">
                <input type="hidden" name="action" value="add">
                
                <!-- تبويب المعلومات الأساسية -->
                <div id="basicTab" class="tab-content active">
                    <div class="preview-images">
                        <div class="preview-box">
                            <label>صورة البوستر</label>
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $tmdb_data['poster_path']; ?>" alt="poster">
                            <input type="hidden" name="poster_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['poster_path']; ?>">
                        </div>
                        <div class="preview-box">
                            <label>صورة الخلفية</label>
                            <img src="https://image.tmdb.org/t/p/w500<?php echo $tmdb_data['backdrop_path']; ?>" alt="backdrop">
                            <input type="hidden" name="backdrop_url" value="https://image.tmdb.org/t/p/original<?php echo $tmdb_data['backdrop_path']; ?>">
                        </div>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>العنوان (عربي)</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($tmdb_data['title'] ?? $tmdb_data['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>العنوان (إنجليزي)</label>
                            <input type="text" name="title_en" value="<?php echo htmlspecialchars($tmdb_data['original_title'] ?? $tmdb_data['original_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>سنة الإنتاج</label>
                            <input type="number" name="year" value="<?php echo substr($tmdb_data['release_date'] ?? $tmdb_data['first_air_date'] ?? date('Y'), 0, 4); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>البلد</label>
                            <input type="text" name="country" value="<?php echo $tmdb_data['production_countries'][0]['name'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>اللغة</label>
                            <select name="language">
                                <option value="ar" <?php echo ($tmdb_data['original_language'] == 'ar') ? 'selected' : ''; ?>>العربية</option>
                                <option value="en" <?php echo ($tmdb_data['original_language'] == 'en') ? 'selected' : ''; ?>>الإنجليزية</option>
                                <option value="tr" <?php echo ($tmdb_data['original_language'] == 'tr') ? 'selected' : ''; ?>>التركية</option>
                                <option value="hi" <?php echo ($tmdb_data['original_language'] == 'hi') ? 'selected' : ''; ?>>الهندية</option>
                                <option value="ko" <?php echo ($tmdb_data['original_language'] == 'ko') ? 'selected' : ''; ?>>الكورية</option>
                                <option value="ja" <?php echo ($tmdb_data['original_language'] == 'ja') ? 'selected' : ''; ?>>اليابانية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>التصنيف</label>
                            <input type="text" name="genre" value="<?php 
                                $genres = array_column($tmdb_data['genres'] ?? [], 'name');
                                echo implode('، ', $genres);
                            ?>">
                        </div>
                        
                        <?php if ($selected_type == 'movie'): ?>
                        <div class="form-group">
                            <label>المدة (دقائق)</label>
                            <input type="number" name="duration" value="<?php echo $tmdb_data['runtime'] ?? 0; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>تقييم IMDB</label>
                            <input type="number" step="0.1" name="imdb_rating" value="<?php echo $tmdb_data['vote_average'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الوصف</label>
                            <textarea name="overview" rows="5"><?php echo htmlspecialchars($tmdb_data['overview']); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب مستوى العضوية -->
                <div id="membershipTab" class="tab-content">
                    <div class="membership-section">
                        <div class="membership-title">
                            <i class="fas fa-crown" style="color: gold;"></i>
                            اختر مستوى العضوية المطلوب للمشاهدة
                        </div>
                        
                        <div class="membership-grid">
                            <!-- الخيار: عادي -->
                            <label class="membership-option" style="border-color: #6c757d;">
                                <input type="radio" name="membership_level" value="basic" checked>
                                <span class="checkmark"><i class="fas fa-check-circle"></i></span>
                                <div class="membership-content">
                                    <span class="membership-name" style="color: #6c757d;">عادي</span>
                                    <span class="membership-badge badge-basic">مجاني</span>
                                    <span class="membership-desc">
                                        <i class="fas fa-check" style="color: #27ae60;"></i> متاح للجميع
                                    </span>
                                </div>
                            </label>
                            
                            <!-- الخيار: مميز -->
                            <label class="membership-option" style="border-color: #e50914;">
                                <input type="radio" name="membership_level" value="premium">
                                <span class="checkmark"><i class="fas fa-check-circle"></i></span>
                                <div class="membership-content">
                                    <span class="membership-name" style="color: #e50914;">مميز ⭐</span>
                                    <span class="membership-badge badge-premium">مشتركين مميز</span>
                                    <span class="membership-desc">
                                        <i class="fas fa-check" style="color: #27ae60;"></i> بدون إعلانات<br>
                                        <i class="fas fa-check" style="color: #27ae60;"></i> جودة 1080p HD
                                    </span>
                                </div>
                            </label>
                            
                            <!-- الخيار: VIP -->
                            <label class="membership-option" style="border-color: gold;">
                                <input type="radio" name="membership_level" value="vip">
                                <span class="checkmark"><i class="fas fa-check-circle"></i></span>
                                <div class="membership-content">
                                    <span class="membership-name" style="color: gold;">VIP 👑</span>
                                    <span class="membership-badge badge-vip">VIP فقط</span>
                                    <span class="membership-desc">
                                        <i class="fas fa-check" style="color: #27ae60;"></i> جودة 4K UHD<br>
                                        <i class="fas fa-check" style="color: #27ae60;"></i> محتوى حصري
                                    </span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب المواسم والحلقات (للمسلسلات فقط) -->
                <?php if ($selected_type == 'tv'): ?>
                <div id="seasonsTab" class="tab-content">
                    <div class="seasons-management">
                        <div class="seasons-header">
                            <h3><i class="fas fa-layer-group"></i> إدارة المواسم والحلقات</h3>
                            <div>
                                <button type="button" class="add-season-btn" onclick="addSeason()">
                                    <i class="fas fa-plus"></i> إضافة موسم جديد
                                </button>
                            </div>
                        </div>
                        
                        <?php if (!empty($seasons_data)): ?>
                        <div class="fetch-info">
                            <p><i class="fas fa-info-circle"></i> تم جلب <?php echo count($seasons_data); ?> موسم من TMDB. يمكنك تعديلها وإضافة روابط خارجية لكل موسم وحلقة.</p>
                        </div>
                        <?php endif; ?>
                        
                        <div id="seasonsContainer">
                            <!-- سيتم إضافة المواسم هنا ديناميكياً -->
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- تبويب الرفع اليدوي -->
                <div id="uploadTab" class="tab-content">
                    <div class="upload-section">
                        <div class="upload-title">
                            <i class="fas fa-cloud-upload-alt"></i>
                            رفع ملفات من جهازك
                        </div>
                        
                        <div class="upload-grid">
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="upload-label">رفع ملف الفيديو</div>
                                <input type="file" name="video_file" class="file-input" accept="video/mp4,video/mkv,video/avi,video/mov,video/wmv,video/flv,video/webm">
                                <div class="upload-hint">الصيغ المسموحة: MP4, MKV, AVI, MOV, WMV, FLV, WebM</div>
                            </div>
                            
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="upload-label">رفع ملف التحميل</div>
                                <input type="file" name="download_file" class="file-input">
                                <div class="upload-hint">يمكنك رفع أي صيغة ملف</div>
                            </div>
                        </div>
                        
                        <div style="background: #252525; border-radius: 10px; padding: 20px; margin-top: 20px;">
                            <h4 style="color: #27ae60; margin-bottom: 10px;">📌 ملاحظات:</h4>
                            <ul style="color: #b3b3b3; font-size: 14px; padding-right: 20px;">
                                <li>الحد الأقصى لحجم الملف: <?php echo ini_get('upload_max_filesize'); ?></li>
                                <li>يمكنك ترك الحقول فارغة إذا كنت تريد استخدام روابط خارجية</li>
                                <li>الملفات المرفوعة ستكون متاحة للمشاهدة والتحميل المباشر</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الروابط العامة -->
                <div id="linksTab" class="tab-content">
                    <div class="links-section">
                        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="color: #e50914;">روابط عامة للمحتوى</h3>
                            <button type="button" class="add-btn" onclick="addGeneralLinkField()">
                                <i class="fas fa-plus"></i> إضافة رابط عام
                            </button>
                        </div>
                        
                        <div id="generalLinksContainer">
                            <!-- سيتم إضافة الروابط العامة هنا -->
                        </div>
                        
                        <div style="margin-top: 20px; background: #252525; border-radius: 8px; padding: 15px;">
                            <p style="color: #b3b3b3; margin-bottom: 10px;"><i class="fas fa-info-circle" style="color: #e50914;"></i> ملاحظة: الروابط العامة تضاف للمحتوى الرئيسي (الفيلم أو المسلسل). للمواسم والحلقات، استخدم تبويب المواسم والحلقات.</p>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الترجمة -->
                <div id="subtitlesTab" class="tab-content">
                    <div class="subtitles-section">
                        <div class="section-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="color: #e50914;">
                                <i class="fas fa-closed-captioning"></i>
                                إضافة ترجمة للمحتوى
                            </h3>
                            <span style="background: #3498db; padding: 5px 10px; border-radius: 20px; font-size: 12px;">
                                <i class="fas fa-info-circle"></i> SRT, VTT, ASS
                            </span>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <button type="button" class="add-btn" onclick="addSubtitleField()">
                                <i class="fas fa-plus"></i> إضافة ترجمة
                            </button>
                        </div>
                        
                        <div id="subtitlesContainer">
                            <!-- سيتم إضافة حقول الترجمة هنا -->
                        </div>
                        
                        <div style="background: #252525; border-radius: 8px; padding: 15px; margin-top: 20px;">
                            <h4 style="color: #b3b3b3; margin-bottom: 10px;">📌 ملاحظات:</h4>
                            <ul style="color: #b3b3b3; font-size: 14px; padding-right: 20px;">
                                <li>يمكنك رفع ملفات الترجمة بصيغة SRT أو VTT أو ASS</li>
                                <li>يمكنك أيضاً إضافة رابط خارجي للترجمة</li>
                                <li>اختر "افتراضي" للترجمة التي تريد ظهورها تلقائياً</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الإعدادات المتقدمة -->
                <div id="advancedTab" class="tab-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>حالة المحتوى</label>
                            <select name="status">
                                <option value="published">منشور</option>
                                <option value="draft">مسودة</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K">4K UHD</option>
                                <option value="1080p">1080p HD</option>
                                <option value="720p">720p HD</option>
                                <option value="480p">480p</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="add-btn" style="width: 100%; padding: 15px; font-size: 18px; margin-top: 20px;">
                    <i class="fas fa-save"></i> حفظ المحتوى
                </button>
            </form>
        </div>
        <?php endif; ?>
        
        <?php endif; // نهاية قسم الإضافة ?>
        
        <?php if ($current_mode == 'edit'): ?>
        <!-- قسم التعديل -->
        <div class="selector-section">
            <div class="selector-title">
                <i class="fas fa-search"></i>
                اختر المحتوى الذي تريد تعديله
            </div>
            
            <div class="selector-grid">
                <!-- اختيار فيلم -->
                <div class="selector-box">
                    <h3><i class="fas fa-film"></i> أفلام</h3>
                    <div class="selector-list">
                        <?php if (empty($all_movies)): ?>
                            <div class="selector-item">لا توجد أفلام</div>
                        <?php else: ?>
                            <?php foreach ($all_movies as $movie): ?>
                            <div class="selector-item <?php echo ($edit_content_type == 'movie' && $edit_content_id == $movie['id']) ? 'selected' : ''; ?>"
                                 onclick="location.href='?mode=edit&type=movie&id=<?php echo $movie['id']; ?>'">
                                <div class="selector-item-info">
                                    <div class="selector-item-title"><?php echo htmlspecialchars($movie['title']); ?></div>
                                    <div class="selector-item-year"><?php echo $movie['year']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- اختيار مسلسل -->
                <div class="selector-box">
                    <h3><i class="fas fa-tv"></i> مسلسلات</h3>
                    <div class="selector-list">
                        <?php if (empty($all_series)): ?>
                            <div class="selector-item">لا توجد مسلسلات</div>
                        <?php else: ?>
                            <?php foreach ($all_series as $series): ?>
                            <div class="selector-item <?php echo ($edit_content_type == 'series' && $edit_content_id == $series['id']) ? 'selected' : ''; ?>"
                                 onclick="location.href='?mode=edit&type=series&id=<?php echo $series['id']; ?>'">
                                <div class="selector-item-info">
                                    <div class="selector-item-title"><?php echo htmlspecialchars($series['title']); ?></div>
                                    <div class="selector-item-year"><?php echo $series['year']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($current_mode == 'edit' && isset($_GET['updated'])): ?>
        <div class="alert alert-success">
            ✅ تم تحديث المحتوى بنجاح
        </div>
        <?php endif; ?>
        
        <?php if ($current_mode == 'edit' && isset($_GET['added'])): ?>
        <div class="alert alert-success">
            ✅ تمت الإضافة بنجاح
        </div>
        <?php endif; ?>
        
        <?php if ($current_mode == 'edit' && isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
            ✅ تم الحذف بنجاح
        </div>
        <?php endif; ?>
        
        <?php if ($item): ?>
        <div class="edit-section">
            <div class="section-title">
                <i class="fas fa-edit" style="color: #e50914;"></i>
                تعديل: <?php echo htmlspecialchars($item['title'] ?? $item['name'] ?? ''); ?>
            </div>
            
            <div class="form-tabs">
                <button class="form-tab active" onclick="showTab('basic')">📋 معلومات أساسية</button>
                <button class="form-tab" onclick="showTab('membership')">👑 مستوى العضوية</button>
                <button class="form-tab" onclick="showTab('links')">🔗 روابط</button>
                <button class="form-tab" onclick="showTab('upload')">📁 رفع يدوي</button>
                <?php if ($edit_content_type == 'series'): ?>
                <button class="form-tab" onclick="showTab('seasons')">📺 المواسم والحلقات</button>
                <?php endif; ?>
            </div>
            
            <form method="POST" id="contentForm" enctype="multipart/form-data">
                <input type="hidden" name="mode" value="edit">
                <input type="hidden" name="content_type" value="<?php echo $edit_content_type; ?>">
                <input type="hidden" name="content_id" value="<?php echo $edit_content_id; ?>">
                <input type="hidden" name="update_content" value="1">
                
                <!-- تبويب المعلومات الأساسية -->
                <div id="basicTab" class="tab-content active">
                    <?php if ($edit_content_type == 'movie' || $edit_content_type == 'series'): ?>
                    <div class="preview-images">
                        <div class="preview-box">
                            <label>صورة البوستر الحالية</label>
                            <img src="<?php echo $item['poster'] ?? 'https://via.placeholder.com/300x450?text=No+Poster'; ?>" id="poster_preview" alt="poster">
                            <input type="file" name="poster_file" accept="image/*" style="margin-top: 10px; width: 100%;">
                        </div>
                        <div class="preview-box">
                            <label>صورة الخلفية الحالية</label>
                            <img src="<?php echo $item['backdrop'] ?? 'https://via.placeholder.com/1280x720?text=No+Backdrop'; ?>" id="backdrop_preview" alt="backdrop">
                            <input type="file" name="backdrop_file" accept="image/*" style="margin-top: 10px; width: 100%;">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>العنوان (عربي)</label>
                            <input type="text" name="title" value="<?php echo htmlspecialchars($item['title'] ?? $item['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>العنوان (إنجليزي)</label>
                            <input type="text" name="title_en" value="<?php echo htmlspecialchars($item['title_en'] ?? $item['original_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>سنة الإنتاج</label>
                            <input type="number" name="year" value="<?php echo $item['year'] ?? date('Y'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>البلد</label>
                            <input type="text" name="country" value="<?php echo htmlspecialchars($item['country'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>اللغة</label>
                            <select name="language">
                                <option value="ar" <?php echo ($item['language'] == 'ar') ? 'selected' : ''; ?>>العربية</option>
                                <option value="en" <?php echo ($item['language'] == 'en') ? 'selected' : ''; ?>>الإنجليزية</option>
                                <option value="tr" <?php echo ($item['language'] == 'tr') ? 'selected' : ''; ?>>التركية</option>
                                <option value="hi" <?php echo ($item['language'] == 'hi') ? 'selected' : ''; ?>>الهندية</option>
                                <option value="ko" <?php echo ($item['language'] == 'ko') ? 'selected' : ''; ?>>الكورية</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>التصنيف</label>
                            <input type="text" name="genre" value="<?php echo htmlspecialchars($item['genre'] ?? ''); ?>">
                        </div>
                        
                        <?php if ($edit_content_type == 'movie'): ?>
                        <div class="form-group">
                            <label>المدة (دقائق)</label>
                            <input type="number" name="duration" value="<?php echo $item['duration'] ?? 0; ?>">
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label>تقييم IMDB</label>
                            <input type="number" step="0.1" name="imdb_rating" value="<?php echo $item['imdb_rating'] ?? 0; ?>">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الوصف</label>
                            <textarea name="overview" rows="5"><?php echo htmlspecialchars($item['description'] ?? $item['overview'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>حالة المحتوى</label>
                            <select name="status">
                                <option value="published" <?php echo ($item['status'] == 'published') ? 'selected' : ''; ?>>منشور</option>
                                <option value="draft" <?php echo ($item['status'] == 'draft') ? 'selected' : ''; ?>>مسودة</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K" <?php echo ($item['quality'] == '4K') ? 'selected' : ''; ?>>4K UHD</option>
                                <option value="1080p" <?php echo ($item['quality'] == '1080p') ? 'selected' : ''; ?>>1080p HD</option>
                                <option value="720p" <?php echo ($item['quality'] == '720p') ? 'selected' : ''; ?>>720p HD</option>
                                <option value="480p" <?php echo ($item['quality'] == '480p') ? 'selected' : ''; ?>>480p</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب مستوى العضوية -->
                <div id="membershipTab" class="tab-content">
                    <div class="membership-section">
                        <div class="membership-title">
                            <i class="fas fa-crown" style="color: gold;"></i>
                            مستوى العضوية المطلوب للمشاهدة
                        </div>
                        
                        <div class="membership-options">
                            <label class="membership-option" style="border-color: #6c757d;">
                                <input type="radio" name="membership_level" value="basic" <?php echo ($item['membership_level'] == 'basic') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: #6c757d;">عادي</div>
                                    <div class="membership-desc">متاح للجميع مجاناً</div>
                                </div>
                            </label>
                            
                            <label class="membership-option" style="border-color: #e50914;">
                                <input type="radio" name="membership_level" value="premium" <?php echo ($item['membership_level'] == 'premium') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: #e50914;">مميز ⭐</div>
                                    <div class="membership-desc">للمشتركين المميزين فقط</div>
                                </div>
                            </label>
                            
                            <label class="membership-option" style="border-color: gold;">
                                <input type="radio" name="membership_level" value="vip" <?php echo ($item['membership_level'] == 'vip') ? 'checked' : ''; ?>>
                                <div>
                                    <div class="membership-name" style="color: gold;">VIP 👑</div>
                                    <div class="membership-desc">حصري لمشتركي VIP</div>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب الروابط -->
                <div id="linksTab" class="tab-content">
                    <div class="links-section">
                        <div class="section-header">
                            <h3>روابط المشاهدة</h3>
                            <button type="button" class="add-btn" onclick="showAddWatchLinkForm()">
                                <i class="fas fa-plus"></i> إضافة رابط
                            </button>
                        </div>
                        
                        <?php if (empty($watch_servers)): ?>
                        <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد روابط مشاهدة</p>
                        <?php else: ?>
                        <table class="links-table">
                            <thead>
                                <tr>
                                    <th>اسم السيرفر</th>
                                    <th>الجودة</th>
                                    <th>اللغة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($watch_servers as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['server_name']); ?></td>
                                    <td><?php echo $link['quality']; ?></td>
                                    <td><?php echo $link['language'] == 'arabic' ? 'عربي' : 'English'; ?></td>
                                    <td>
                                        <a href="?mode=edit&delete_watch_link=<?php echo $link['id']; ?>&type=<?php echo $edit_content_type; ?>&id=<?php echo $edit_content_id; ?>" 
                                           class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا الرابط؟')">
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                    
                    <div class="links-section" style="margin-top: 30px;">
                        <div class="section-header">
                            <h3>روابط التحميل</h3>
                            <button type="button" class="add-btn" onclick="showAddDownloadLinkForm()">
                                <i class="fas fa-plus"></i> إضافة رابط
                            </button>
                        </div>
                        
                        <?php if (empty($download_servers)): ?>
                        <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد روابط تحميل</p>
                        <?php else: ?>
                        <table class="links-table">
                            <thead>
                                <tr>
                                    <th>اسم السيرفر</th>
                                    <th>الجودة</th>
                                    <th>الحجم</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($download_servers as $link): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($link['server_name']); ?></td>
                                    <td><?php echo $link['quality']; ?></td>
                                    <td><?php echo $link['size'] ?? '-'; ?></td>
                                    <td>
                                        <a href="?mode=edit&delete_download_link=<?php echo $link['id']; ?>&type=<?php echo $edit_content_type; ?>&id=<?php echo $edit_content_id; ?>" 
                                           class="delete-btn" onclick="return confirm('هل أنت متأكد من حذف هذا الرابط؟')">
                                            حذف
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- تبويب الرفع اليدوي -->
                <div id="uploadTab" class="tab-content">
                    <div class="upload-section">
                        <div class="upload-title">
                            <i class="fas fa-cloud-upload-alt"></i>
                            رفع ملفات من جهازك
                        </div>
                        
                        <div class="form-grid">
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-video"></i>
                                </div>
                                <div class="upload-label">رفع ملف الفيديو</div>
                                <input type="file" name="video_file" class="file-input" accept="video/*">
                                <div class="upload-hint">MP4, MKV, AVI, MOV</div>
                            </div>
                            
                            <div class="upload-box">
                                <div class="upload-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <div class="upload-label">رفع ملف التحميل</div>
                                <input type="file" name="download_file" class="file-input">
                                <div class="upload-hint">أي صيغة ملف</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- تبويب المواسم والحلقات -->
                <?php if ($edit_content_type == 'series'): ?>
                <div id="seasonsTab" class="tab-content">
                    <div class="seasons-section">
                        <div class="section-header">
                            <h3>📺 المواسم والحلقات</h3>
                        </div>
                        
                        <?php if (empty($seasons_data)): ?>
                        <div style="text-align: center; padding: 30px;">
                            <p style="color: #b3b3b3; margin-bottom: 20px; font-size: 1.1em;">
                                <i class="fas fa-exclamation-circle"></i> لا توجد مواسم أو حلقات
                            </p>
                            <p style="color: #999; margin-bottom: 20px;">
                                يمكنك جلب المواسم والحلقات تلقائياً من TMDB
                            </p>
                            <button type="button" class="add-btn" onclick="fetchSeasonsFromTMDB(<?php echo $edit_content_id; ?>, '<?php echo ($item['tmdb_id'] ?? ''); ?>', '<?php echo TMDB_API_KEY; ?>')" style="padding: 12px 30px; font-size: 1em;">
                                <i class="fas fa-sync-alt"></i> جلب المواسم من TMDB
                            </button>
                        </div>
                        <?php else: ?>
                        
                        <div style="display: grid; gap: 20px;">
                            <?php foreach ($seasons_data as $season): ?>
                            <div style="border: 1px solid #444; border-radius: 8px; padding: 15px; background: #1a1a1a;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                    <h4 style="color: #e50914; margin: 0;">
                                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($season['name'] ?? "الموسم {$season['number']}"); ?>
                                    </h4>
                                </div>
                                
                                <!-- الحلقات -->
                                <div style="margin-left: 20px;">
                                    <?php foreach ($season['episodes'] as $episode): ?>
                                    <div style="border: 1px solid #333; border-radius: 6px; padding: 12px; margin-bottom: 10px; background: #252525;">
                                        <div style="display: flex; justify-content: space-between; align-items: start; gap: 10px;">
                                            <div style="flex: 1;">
                                                <div style="color: #e50914; font-weight: bold; margin-bottom: 5px;">
                                                    الحلقة <?php echo $episode['number']; ?>: <?php echo htmlspecialchars($episode['title']); ?>
                                                </div>
                                                <div style="color: #999; font-size: 0.9em; margin-bottom: 8px;">
                                                    <?php echo htmlspecialchars($episode['description']); ?>
                                                </div>
                                                
                                                <!-- روابط المشاهدة للحلقة -->
                                                <?php if (!empty($episode['watch_servers'])): ?>
                                                <div style="margin-top: 8px;">
                                                    <strong style="color: #27ae60;">روابط المشاهدة:</strong>
                                                    <div style="margin-left: 10px; font-size: 0.85em;">
                                                        <?php foreach ($episode['watch_servers'] as $link): ?>
                                                        <a href="<?php echo $link['server_url']; ?>" target="_blank" class="link-item" style="display: inline-block; margin: 3px 5px 3px 0; padding: 4px 8px; background: #27ae60; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9em;">
                                                            🔗 <?php echo htmlspecialchars($link['server_name']); ?>
                                                        </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <!-- روابط التحميل للحلقة -->
                                                <?php if (!empty($episode['download_servers'])): ?>
                                                <div style="margin-top: 8px;">
                                                    <strong style="color: #3498db;">روابط التحميل:</strong>
                                                    <div style="margin-left: 10px; font-size: 0.85em;">
                                                        <?php foreach ($episode['download_servers'] as $link): ?>
                                                        <a href="<?php echo $link['download_url']; ?>" target="_blank" class="link-item" style="display: inline-block; margin: 3px 5px 3px 0; padding: 4px 8px; background: #3498db; border-radius: 4px; text-decoration: none; color: white; font-size: 0.9em;">
                                                            ⬇️ <?php echo htmlspecialchars($link['server_name']); ?>
                                                        </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div style="display: flex; gap: 5px;">
                                                <button type="button" class="action-btn" onclick="editEpisodeLinks(<?php echo $episode['id']; ?>, '<?php echo $edit_content_type; ?>', <?php echo $edit_content_id; ?>)" title="تعديل الروابط">
                                                    <i class="fas fa-link"></i>
                                                </button>
                                                <button type="button" class="action-btn" onclick="deleteEpisode(<?php echo $episode['id']; ?>, '<?php echo $edit_content_type; ?>', <?php echo $edit_content_id; ?>)" title="حذف">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="submit-btn">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
            
            <!-- نماذج إضافة الروابط -->
            <div id="addWatchLinkForm" style="display: none; margin-top: 20px; padding: 20px; background: #252525; border-radius: 8px;">
                <h4 style="color: #e50914; margin-bottom: 15px;">إضافة رابط مشاهدة جديد</h4>
                <form method="POST">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="item_type" value="<?php echo $edit_content_type; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $edit_content_id; ?>">
                    <input type="hidden" name="add_watch_link" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اللغة</label>
                            <select name="language">
                                <option value="arabic">عربي</option>
                                <option value="english">English</option>
                                <option value="turkish">Türkçe</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>اسم السيرفر</label>
                            <input type="text" name="server_name" placeholder="مثال: سيرفر 1" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K">4K</option>
                                <option value="1080p">1080p</option>
                                <option value="720p">720p</option>
                            </select>
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الرابط</label>
                            <input type="url" name="server_url" placeholder="https://..." required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="add-btn">إضافة</button>
                        <button type="button" class="add-btn" style="background: #e50914;" onclick="hideAddWatchLinkForm()">إلغاء</button>
                    </div>
                </form>
            </div>
            
            <div id="addDownloadLinkForm" style="display: none; margin-top: 20px; padding: 20px; background: #252525; border-radius: 8px;">
                <h4 style="color: #e50914; margin-bottom: 15px;">إضافة رابط تحميل جديد</h4>
                <form method="POST">
                    <input type="hidden" name="mode" value="edit">
                    <input type="hidden" name="item_type" value="<?php echo $edit_content_type; ?>">
                    <input type="hidden" name="item_id" value="<?php echo $edit_content_id; ?>">
                    <input type="hidden" name="add_download_link" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>اسم السيرفر</label>
                            <input type="text" name="server_name" placeholder="مثال: ميديا فاير" required>
                        </div>
                        
                        <div class="form-group">
                            <label>الجودة</label>
                            <select name="quality">
                                <option value="4K">4K</option>
                                <option value="1080p">1080p</option>
                                <option value="720p">720p</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>الحجم</label>
                            <input type="text" name="size" placeholder="مثال: 1.5 GB">
                        </div>
                        
                        <div class="form-group full-width">
                            <label>الرابط</label>
                            <input type="url" name="download_url" placeholder="https://..." required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="add-btn">إضافة</button>
                        <button type="button" class="add-btn" style="background: #e50914;" onclick="hideAddDownloadLinkForm()">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($edit_content_id > 0 && !$item): ?>
        <div class="alert alert-error">
            ❌ لم يتم العثور على المحتوى المطلوب
        </div>
        <?php endif; ?>
        
        <?php endif; // نهاية قسم التعديل ?>
        
        <!-- قائمة المحتوى الموجود -->
        <div class="existing-list">
            <h3 style="color:#e50914; margin-bottom:15px;">📋 أحدث المحتوى المضاف</h3>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div>
                    <h4 style="color:#b3b3b3; margin-bottom:10px;">🎬 أحدث الأفلام</h4>
                    <div class="existing-grid">
                        <?php foreach ($all_movies as $movie): ?>
                        <div class="existing-item">
                            <span><?php echo htmlspecialchars($movie['title']); ?> (<?php echo $movie['year']; ?>)</span>
                            <a href="edit-movie.php?id=<?php echo $movie['id']; ?>"><i class="fas fa-edit"></i></a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <div>
                    <h4 style="color:#b3b3b3; margin-bottom:10px;">📺 أحدث المسلسلات</h4>
                    <div class="existing-grid">
                        <?php foreach ($all_series as $series): ?>
                        <div class="existing-item">
                            <span><?php echo htmlspecialchars($series['title']); ?> (<?php echo $series['year']; ?>)</span>
                            <a href="edit-series.php?id=<?php echo $series['id']; ?>"><i class="fas fa-edit"></i></a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let searchType = 'movie';
        let generalLinkCount = 0;
        let subtitleCount = 0;
        let seasonCount = 0;
        
        // بيانات المواسم المجلوبة من TMDB
        const fetchedSeasons = <?php echo json_encode($seasons_data); ?>;
        
        function setSearchType(type) {
            searchType = type;
            document.getElementById('searchMovieBtn').classList.toggle('active', type === 'movie');
            document.getElementById('searchTvBtn').classList.toggle('active', type === 'tv');
        }
        
        function searchTMDB() {
            const query = document.getElementById('searchInput').value;
            
            if (!query) {
                alert('الرجاء كتابة كلمة البحث');
                return;
            }
            
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('searchLoading').style.display = 'block';
            
            fetch(`https://api.themoviedb.org/3/search/${searchType}?api_key=5dc3e335b09cbf701d8685dd9a766949&query=${encodeURIComponent(query)}&language=ar`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('searchLoading').style.display = 'none';
                    document.getElementById('searchResults').style.display = 'grid';
                    
                    if (data.results && data.results.length > 0) {
                        let html = '';
                        data.results.slice(0, 12).forEach(item => {
                            const title = item.title || item.name;
                            const year = (item.release_date || item.first_air_date || '').substring(0, 4) || 'غير معروف';
                            
                            html += `
                                <div class="result-card" onclick="selectItem('${item.id}', '${title.replace(/'/g, "\\'")}')">
                                    <div class="result-title">${title}</div>
                                    <div class="result-year">${year}</div>
                                </div>
                            `;
                        });
                        document.getElementById('searchResults').innerHTML = html;
                    } else {
                        document.getElementById('searchResults').innerHTML = '<div style="text-align:center; padding:20px;">لا توجد نتائج</div>';
                    }
                });
        }
        
        function selectItem(id, title) {
            window.location.href = `?mode=add&tmdb_id=${id}&type=${searchType}`;
        }
        
        // دوال قسم التعديل (من edit-content.php)
        function showTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const tabs = ['basic', 'membership', 'links', 'upload', 'seasons'];
            const index = tabs.indexOf(tab);
            
            if (index !== -1) {
                document.querySelectorAll('.form-tab')[index].classList.add('active');
                document.getElementById(tab + 'Tab').classList.add('active');
            }
        }
        
        function showAddWatchLinkForm() {
            document.getElementById('addWatchLinkForm').style.display = 'block';
        }
        
        function hideAddWatchLinkForm() {
            document.getElementById('addWatchLinkForm').style.display = 'none';
        }
        
        function showAddDownloadLinkForm() {
            document.getElementById('addDownloadLinkForm').style.display = 'block';
        }
        
        function hideAddDownloadLinkForm() {
            document.getElementById('addDownloadLinkForm').style.display = 'none';
        }
        
        /**
         * جلب المواسم والحلقات من TMDB
         */
        function fetchSeasonsFromTMDB(seriesId, tmdbId, apiKey) {
            if (!tmdbId) {
                showNotification('⚠️ معرف TMDB غير متوفر للمسلسل', 'warning');
                return;
            }
            
            showNotification('⏳ جاري جلب المواسم والحلقات من TMDB...', 'info');
            
            const formData = new FormData();
            formData.append('action', 'fetch_series_seasons');
            formData.append('series_id', seriesId);
            formData.append('tmdb_id', tmdbId);
            formData.append('api_key', apiKey);
            
            fetch('api-content.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ تم جلب المواسم بنجاح! جاري إعادة التحميل...', 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    showNotification('❌ خطأ: ' + (data.message || 'فشل جلب المواسم'), 'error');
                    console.error('❌ خطأ:', data);
                }
            })
            .catch(error => {
                console.error('❌ خطأ في الاتصال:', error);
                showNotification('❌ خطأ في الاتصال بالخادم', 'error');
            });
        }
        function showFormTab(tab) {
            document.querySelectorAll('.form-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            const tabs = ['basic', 'membership', 'seasons', 'upload', 'links', 'subtitles', 'advanced'];
            const index = tabs.indexOf(tab);
            
            if (index !== -1) {
                document.querySelectorAll('.form-tab')[index].classList.add('active');
                document.getElementById(tab + 'Tab').classList.add('active');
            }
            
            // إذا كان التبويب seasons و seasonCount == 0، قم بتحميل المواسم المجلوبة
            if (tab === 'seasons' && seasonCount === 0 && fetchedSeasons.length > 0) {
                loadFetchedSeasons();
            }
        }
        
        function addGeneralLinkField() {
            generalLinkCount++;
            const html = `
                <div class="link-item" id="general-link-${generalLinkCount}">
                    <select name="links[${generalLinkCount}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                        <option value="turkish">🇹🇷 Türkçe</option>
                    </select>
                    <input type="text" name="links[${generalLinkCount}][name]" placeholder="اسم السيرفر" value="سيرفر ${generalLinkCount}">
                    <input type="url" name="links[${generalLinkCount}][url]" placeholder="رابط المشاهدة">
                    <select name="links[${generalLinkCount}][quality]">
                        <option value="4K">4K</option>
                        <option value="1080p">1080p</option>
                        <option value="720p">720p</option>
                    </select>
                    <button type="button" class="remove-btn" onclick="removeGeneralLink(${generalLinkCount})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById('generalLinksContainer').insertAdjacentHTML('beforeend', html);
        }
        
        function removeGeneralLink(id) {
            document.getElementById(`general-link-${id}`).remove();
        }
        
        function addSubtitleField() {
            subtitleCount++;
            const html = `
                <div class="subtitle-item" id="subtitle-${subtitleCount}">
                    <select name="subtitles[${subtitleCount}][language_code]" class="subtitle-language">
                        <?php foreach ($subtitle_languages as $code => $name): ?>
                        <option value="<?php echo $code; ?>"><?php echo $name; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="file" name="subtitle_files[${subtitleCount}]" accept=".srt,.vtt,.ass,.ssa,.sub" class="subtitle-file-input">
                    
                    <input type="url" name="subtitles[${subtitleCount}][url]" placeholder="أو رابط خارجي">
                    
                    <label class="default-checkbox">
                        <input type="checkbox" name="subtitles[${subtitleCount}][is_default]" value="1"> افتراضي
                    </label>
                    
                    <button type="button" class="remove-btn" onclick="removeSubtitle(${subtitleCount})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById('subtitlesContainer').insertAdjacentHTML('beforeend', html);
        }
        
        function removeSubtitle(id) {
            document.getElementById(`subtitle-${id}`).remove();
        }
        
        function loadFetchedSeasons() {
            if (fetchedSeasons && fetchedSeasons.length > 0) {
                fetchedSeasons.forEach(season => {
                    addSeason(season);
                });
            }
        }
        
        function addSeason(seasonData = null) {
            seasonCount++;
            const seasonId = seasonCount;
            
            let seasonNumber = seasonData ? seasonData.number : seasonCount;
            let seasonName = seasonData ? (seasonData.name || `الموسم ${seasonNumber}`) : `الموسم ${seasonNumber}`;
            let seasonOverview = seasonData ? (seasonData.overview || '') : '';
            let seasonPoster = seasonData ? (seasonData.poster || '') : '';
            let seasonAirDate = seasonData ? (seasonData.air_date || '') : '';
            
            const html = `
                <div class="season-item" id="season-${seasonId}" data-season="${seasonId}">
                    <div class="season-header">
                        <div class="season-info">
                            <i class="fas fa-folder" style="color: #e50914;"></i>
                            <input type="number" name="seasons[${seasonId}][number]" placeholder="رقم الموسم" value="${seasonNumber}" min="1" style="width: 80px;" required>
                            <input type="text" name="seasons[${seasonId}][name]" placeholder="اسم الموسم" value="${seasonName}" style="width: 200px;">
                        </div>
                        <div class="season-actions">
                            <button type="button" onclick="toggleSeasonLinks(${seasonId})" title="إدارة روابط الموسم"><i class="fas fa-link"></i></button>
                            <button type="button" onclick="addEpisode(${seasonId})" title="إضافة حلقة"><i class="fas fa-plus"></i></button>
                            <button type="button" onclick="removeSeason(${seasonId})" title="حذف الموسم"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 10px;">
                        <textarea name="seasons[${seasonId}][overview]" placeholder="وصف الموسم" rows="2" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">${seasonOverview}</textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" name="seasons[${seasonId}][poster]" placeholder="رابط صورة الموسم" value="${seasonPoster}" style="flex: 1; padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">
                        <input type="date" name="seasons[${seasonId}][air_date]" value="${seasonAirDate}" style="padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">
                    </div>
                    
                    <!-- روابط الموسم (مخفية افتراضياً) -->
                    <div id="season-${seasonId}-links" class="season-links" style="display: none;">
                        <div class="links-header">
                            <span>روابط المشاهدة للموسم</span>
                            <button type="button" class="add-btn small" onclick="addSeasonWatchLink(${seasonId})">
                                <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                            </button>
                        </div>
                        <div id="season-${seasonId}-watch-links"></div>
                        
                        <div class="links-header" style="margin-top: 15px;">
                            <span>روابط التحميل للموسم</span>
                            <button type="button" class="add-btn small" onclick="addSeasonDownloadLink(${seasonId})">
                                <i class="fas fa-plus"></i> إضافة رابط تحميل
                            </button>
                        </div>
                        <div id="season-${seasonId}-download-links"></div>
                    </div>
                    
                    <div class="episodes-section">
                        <div class="episodes-header">
                            <span>الحلقات</span>
                            <button type="button" class="add-episode-btn" onclick="addEpisode(${seasonId})">
                                <i class="fas fa-plus"></i> إضافة حلقة
                            </button>
                        </div>
                        <div id="season-${seasonId}-episodes">
                            <!-- سيتم إضافة الحلقات هنا -->
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('seasonsContainer').insertAdjacentHTML('beforeend', html);
            
            // إضافة الحلقات إذا وجدت بيانات
            if (seasonData && seasonData.episodes && seasonData.episodes.length > 0) {
                seasonData.episodes.forEach(episode => {
                    addEpisode(seasonId, episode);
                });
            } else {
                // إضافة حلقة افتراضية للموسم الجديد
                addEpisode(seasonId);
            }
        }
        
        function addSeasonWatchLink(seasonId) {
            const linkId = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <div class="link-item" id="season-${seasonId}-watch-${linkId}" style="grid-template-columns: 1fr 2fr 1fr auto; margin-bottom: 5px;">
                    <select name="seasons[${seasonId}][watch_servers][${linkId}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                        <option value="turkish">🇹🇷 Türkçe</option>
                    </select>
                    <input type="text" name="seasons[${seasonId}][watch_servers][${linkId}][name]" placeholder="اسم السيرفر" value="سيرفر مشاهدة">
                    <input type="url" name="seasons[${seasonId}][watch_servers][${linkId}][url]" placeholder="رابط المشاهدة">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById(`season-${seasonId}-watch-links`).insertAdjacentHTML('beforeend', html);
        }
        
        function addSeasonDownloadLink(seasonId) {
            const linkId = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <div class="link-item" id="season-${seasonId}-download-${linkId}" style="grid-template-columns: 1fr 2fr 1fr auto; margin-bottom: 5px;">
                    <select name="seasons[${seasonId}][download_servers][${linkId}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                        <option value="turkish">🇹🇷 Türkçe</option>
                    </select>
                    <input type="text" name="seasons[${seasonId}][download_servers][${linkId}][name]" placeholder="اسم السيرفر" value="سيرفر تحميل">
                    <input type="url" name="seasons[${seasonId}][download_servers][${linkId}][url]" placeholder="رابط التحميل">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById(`season-${seasonId}-download-links`).insertAdjacentHTML('beforeend', html);
        }
        
        function toggleSeasonLinks(seasonId) {
            const linksDiv = document.getElementById(`season-${seasonId}-links`);
            if (linksDiv.style.display === 'none') {
                linksDiv.style.display = 'block';
            } else {
                linksDiv.style.display = 'none';
            }
        }
        
        function addEpisode(seasonId, episodeData = null) {
    const episodeId = Date.now() + Math.floor(Math.random() * 1000);
    const seasonDiv = document.getElementById(`season-${seasonId}-episodes`);
    if (episodeData && episodeData.id) {
    html += `<input type="hidden" name="seasons[${seasonId}][episodes][${episodeId}][id]" class="episode-id-input" value="${episodeData.id}">`;
}
    
    if (!seasonDiv) return;
    
    const episodeCount = seasonDiv.children.length + 1;
    
    let episodeNumber = episodeData ? episodeData.number : episodeCount;
    let episodeTitle = episodeData ? (episodeData.title || `الحلقة ${episodeNumber}`) : `الحلقة ${episodeNumber}`;
    let episodeDesc = episodeData ? (episodeData.description || '') : '';
    let episodeDuration = episodeData ? (episodeData.duration || 45) : 45;
    let episodeStill = episodeData ? (episodeData.still_path || '') : '';
    let episodeAirDate = episodeData ? (episodeData.air_date || '') : '';
    
    const html = `
        <div class="episode-item" id="episode-${episodeId}">
            <div class="episode-header">
                <div class="episode-info">
                    <input type="number" name="seasons[${seasonId}][episodes][${episodeId}][number]" class="episode-number" placeholder="رقم" value="${episodeNumber}" min="1" required>
                    <input type="text" name="seasons[${seasonId}][episodes][${episodeId}][title]" class="episode-title-input" placeholder="عنوان الحلقة" value="${episodeTitle}" required>
                    <input type="number" name="seasons[${seasonId}][episodes][${episodeId}][duration]" class="episode-duration" placeholder="المدة" value="${episodeDuration}" min="1">
                </div>
                <div class="episode-actions">
                    <button type="button" onclick="toggleEpisodeLinks('episode-${episodeId}')" title="إدارة روابط الحلقة" class="action-btn"><i class="fas fa-link"></i></button>
                    <button type="button" onclick="deleteEpisode('episode-${episodeId}')" title="حذف الحلقة" class="delete-btn"><i class="fas fa-trash"></i></button>
                </div>
            </div>
            
            <div style="margin-bottom: 10px;">
                <textarea name="seasons[${seasonId}][episodes][${episodeId}][description]" placeholder="وصف الحلقة" rows="2" style="width: 100%; padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">${episodeDesc}</textarea>
            </div>
            
            <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                <input type="text" name="seasons[${seasonId}][episodes][${episodeId}][still_path]" placeholder="رابط صورة الحلقة" value="${episodeStill}" style="flex: 1; padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">
                <input type="date" name="seasons[${seasonId}][episodes][${episodeId}][air_date]" value="${episodeAirDate}" style="padding: 8px; background: #1a1a1a; border: 1px solid #333; border-radius: 5px; color: #fff;">
            </div>
            
            <!-- روابط الحلقة (مخفية افتراضياً) -->
            <div id="episode-${episodeId}-links" class="episode-links" style="display: none;">
                <div class="links-header">
                    <span>روابط المشاهدة للحلقة</span>
                    <button type="button" class="add-btn small" onclick="addEpisodeWatchLink('episode-${episodeId}', ${seasonId}, '${episodeId}')">
                        <i class="fas fa-plus"></i> إضافة رابط مشاهدة
                    </button>
                </div>
                <div id="episode-${episodeId}-watch-links"></div>
                
                <div class="links-header" style="margin-top: 15px;">
                    <span>روابط التحميل للحلقة</span>
                    <button type="button" class="add-btn small" onclick="addEpisodeDownloadLink('episode-${episodeId}', ${seasonId}, '${episodeId}')">
                        <i class="fas fa-plus"></i> إضافة رابط تحميل
                    </button>
                </div>
                <div id="episode-${episodeId}-download-links"></div>
            </div>
        </div>
    `;
    
    seasonDiv.insertAdjacentHTML('beforeend', html);
}
        
       function addEpisodeWatchLink(episodeId, seasonId, episodeIndex) {
    const linkId = Date.now() + Math.floor(Math.random() * 1000);
    const html = `
        <div class="link-item" id="${episodeId}-watch-${linkId}" style="grid-template-columns: 1fr 2fr 1fr auto; margin-bottom: 5px;">
            <select name="seasons[${seasonId}][episodes][${episodeIndex}][watch_servers][${linkId}][lang]">
                <option value="arabic">🇸🇦 عربي</option>
                <option value="english">🇬🇧 English</option>
                <option value="turkish">🇹🇷 Türkçe</option>
            </select>
            <input type="text" name="seasons[${seasonId}][episodes][${episodeIndex}][watch_servers][${linkId}][name]" placeholder="اسم السيرفر" value="سيرفر مشاهدة">
            <input type="url" name="seasons[${seasonId}][episodes][${episodeIndex}][watch_servers][${linkId}][url]" placeholder="رابط المشاهدة">
            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById(`${episodeId}-watch-links`).insertAdjacentHTML('beforeend', html);
}

function addEpisodeDownloadLink(episodeId, seasonId, episodeIndex) {
    const linkId = Date.now() + Math.floor(Math.random() * 1000);
    const html = `
        <div class="link-item" id="${episodeId}-download-${linkId}" style="grid-template-columns: 1fr 2fr 1fr auto; margin-bottom: 5px;">
            <select name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][lang]">
                <option value="arabic">🇸🇦 عربي</option>
                <option value="english">🇬🇧 English</option>
                <option value="turkish">🇹🇷 Türkçe</option>
            </select>
            <input type="text" name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][name]" placeholder="اسم السيرفر" value="سيرفر تحميل">
            <input type="url" name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][url]" placeholder="رابط التحميل">
            <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById(`${episodeId}-download-links`).insertAdjacentHTML('beforeend', html);
}
        function addEpisodeDownloadLink(episodeId, seasonId, episodeIndex) {
            const linkId = Date.now() + Math.floor(Math.random() * 1000);
            const html = `
                <div class="link-item" id="${episodeId}-download-${linkId}" style="grid-template-columns: 1fr 2fr 1fr auto; margin-bottom: 5px;">
                    <select name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][lang]">
                        <option value="arabic">🇸🇦 عربي</option>
                        <option value="english">🇬🇧 English</option>
                        <option value="turkish">🇹🇷 Türkçe</option>
                    </select>
                    <input type="text" name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][name]" placeholder="اسم السيرفر" value="سيرفر تحميل">
                    <input type="url" name="seasons[${seasonId}][episodes][${episodeIndex}][download_servers][${linkId}][url]" placeholder="رابط التحميل">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            document.getElementById(`${episodeId}-download-links`).insertAdjacentHTML('beforeend', html);
        }
        
        function toggleEpisodeLinks(episodeId) {
            const linksDiv = document.getElementById(`${episodeId}-links`);
            if (linksDiv.style.display === 'none') {
                linksDiv.style.display = 'block';
            } else {
                linksDiv.style.display = 'none';
            }
        }
        
        function removeSeason(seasonId) {
            if (confirm('هل أنت متأكد من حذف هذا الموسم وجميع حلقاته؟')) {
                document.getElementById(`season-${seasonId}`).remove();
            }
        }
        
        function deleteEpisode(episodeId) {
    const episodeElement = document.getElementById(episodeId);
    
    // استخراج معرف الحلقة من DOM إذا كان موجوداً
    // يمكنك إضافة حقل مخفي يحمل معرف الحلقة الحقيقي
    const realEpisodeId = episodeElement?.querySelector('.episode-id-input')?.value || 0;
    
    if (realEpisodeId > 0) {
        // إذا كانت الحلقة موجودة في قاعدة البيانات، استخدم API
        deleteEpisodeFromDB(realEpisodeId, episodeElement);
    } else {
        // إذا كانت حلقة جديدة (لم تحفظ بعد)، فقط احذفها من الواجهة
        if (confirm('هل أنت متأكد من حذف هذه الحلقة؟')) {
            if (episodeElement) {
                episodeElement.remove();
            }
        }
    }
}
// =============================================
// دوال API للتفاعل المباشر مع قاعدة البيانات
// =============================================

/**
 * إضافة حلقة جديدة مباشرة إلى قاعدة البيانات
 */
// =============================================
// دوال API للتفاعل المباشر مع قاعدة البيانات
// =============================================

/**
 * إضافة حلقة جديدة مباشرة إلى قاعدة البيانات
 */
function addEpisodeToDB(seriesId, seasonNumber, episodeData) {
    const formData = new FormData();
    formData.append('action', 'add_episode');
    formData.append('series_id', seriesId);
    formData.append('season_number', seasonNumber);
    formData.append('episode_number', episodeData.number);
    formData.append('title', episodeData.title);
    formData.append('description', episodeData.description || '');
    formData.append('duration', episodeData.duration || 45);
    formData.append('still_path', episodeData.still_path || '');
    formData.append('air_date', episodeData.air_date || '');
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json());
}

/**
 * تحديث حلقة موجودة
 */
function updateEpisodeInDB(episodeId, episodeData) {
    const formData = new FormData();
    formData.append('action', 'update_episode');
    formData.append('episode_id', episodeId);
    formData.append('title', episodeData.title);
    formData.append('description', episodeData.description || '');
    formData.append('duration', episodeData.duration || 45);
    formData.append('still_path', episodeData.still_path || '');
    formData.append('air_date', episodeData.air_date || '');
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json());
}

/**
 * حذف حلقة من قاعدة البيانات مباشرة
 */
function deleteEpisodeFromDB(episodeId, episodeElement) {
    if (!confirm('هل أنت متأكد من حذف هذه الحلقة نهائياً؟\nسيتم حذف جميع روابط المشاهدة والتحميل والترجمات المرتبطة بها.')) {
        return Promise.reject('تم الإلغاء');
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_episode');
    formData.append('episode_id', episodeId);
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الحلقة:', data);
            // إزالة العنصر من الصفحة بعد الحذف الناجح
            if (episodeElement) {
                episodeElement.remove();
            }
            showNotification('تم حذف الحلقة بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
        throw error;
    });
}

/**
 * إضافة رابط مشاهدة مباشرة إلى قاعدة البيانات
 */
function addWatchLinkToDB(itemType, itemId, linkData) {
    const formData = new FormData();
    formData.append('action', 'add_watch_link');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('server_name', linkData.name);
    formData.append('server_url', linkData.url);
    formData.append('quality', linkData.quality || 'HD');
    formData.append('language', linkData.lang || 'arabic');
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم إضافة الرابط:', data);
            showNotification('تم إضافة رابط المشاهدة بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    });
}

/**
 * حذف رابط مشاهدة من قاعدة البيانات
 */
function deleteWatchLinkFromDB(linkId, linkElement) {
    if (!confirm('هل أنت متأكد من حذف هذا الرابط؟')) {
        return Promise.reject('تم الإلغاء');
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_watch_link');
    formData.append('link_id', linkId);
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الرابط:', data);
            if (linkElement) {
                linkElement.remove();
            }
            showNotification('تم حذف الرابط بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
        throw error;
    });
}

/**
 * إضافة رابط تحميل مباشرة إلى قاعدة البيانات
 */
function addDownloadLinkToDB(itemType, itemId, linkData) {
    const formData = new FormData();
    formData.append('action', 'add_download_link');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('server_name', linkData.name);
    formData.append('download_url', linkData.url);
    formData.append('quality', linkData.quality || 'HD');
    formData.append('size', linkData.size || '');
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم إضافة الرابط:', data);
            showNotification('تم إضافة رابط التحميل بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    });
}

/**
 * حذف رابط تحميل من قاعدة البيانات
 */
function deleteDownloadLinkFromDB(linkId, linkElement) {
    if (!confirm('هل أنت متأكد من حذف هذا الرابط؟')) {
        return Promise.reject('تم الإلغاء');
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_download_link');
    formData.append('link_id', linkId);
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الرابط:', data);
            if (linkElement) {
                linkElement.remove();
            }
            showNotification('تم حذف الرابط بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
        throw error;
    });
}

/**
 * رفع ملف فيديو مباشرة
 */
function uploadVideoFile(itemType, itemId, fileInput) {
    if (!fileInput.files || fileInput.files.length === 0) {
        showNotification('الرجاء اختيار ملف', 'warning');
        return Promise.reject('لا يوجد ملف');
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_video');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('video_file', fileInput.files[0]);
    
    // إظهار مؤشر التحميل
    const uploadBtn = fileInput.closest('.upload-box')?.querySelector('.upload-btn');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الرفع...';
    }
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> رفع';
        }
        
        if (data.success) {
            console.log('✅ تم رفع الفيديو:', data);
            showNotification('تم رفع الفيديو بنجاح', 'success');
            
            // تحديث رابط الفيديو في الواجهة
            const videoUrlInput = fileInput.closest('.upload-box')?.querySelector('.video-url-input');
            if (videoUrlInput) {
                videoUrlInput.value = data.video_url;
            }
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    })
    .catch(error => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> رفع';
        }
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
        throw error;
    });
}

/**
 * رفع ملف ترجمة
 */
function uploadSubtitleFile(itemType, itemId, languageCode, languageName, fileInput) {
    if (!fileInput.files || fileInput.files.length === 0) {
        showNotification('الرجاء اختيار ملف', 'warning');
        return Promise.reject('لا يوجد ملف');
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_subtitle');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('language_code', languageCode);
    formData.append('language_name', languageName);
    formData.append('subtitle_file', fileInput.files[0]);
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json());
}

/**
 * إضافة ترجمة برابط خارجي
 */
function addSubtitleLink(itemType, itemId, languageCode, languageName, subtitleUrl, isDefault) {
    const formData = new FormData();
    formData.append('action', 'add_subtitle');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('language_code', languageCode);
    formData.append('language_name', languageName);
    formData.append('subtitle_url', subtitleUrl);
    if (isDefault) {
        formData.append('is_default', '1');
    }
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json());
}

/**
 * حذف ترجمة
 */
function deleteSubtitle(subtitleId, subtitleElement) {
    if (!confirm('هل أنت متأكد من حذف هذه الترجمة؟')) {
        return Promise.reject('تم الإلغاء');
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_subtitle');
    formData.append('subtitle_id', subtitleId);
    
    return fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الترجمة:', data);
            if (subtitleElement) {
                subtitleElement.remove();
            }
            showNotification('تم حذف الترجمة بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
        return data;
    });
}

/**
 * إظهار إشعار للمستخدم
 */
function showNotification(message, type = 'info') {
    // إنشاء عنصر الإشعار
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    
    let icon = 'fa-info-circle';
    if (type === 'success') icon = 'fa-check-circle';
    if (type === 'error') icon = 'fa-exclamation-circle';
    if (type === 'warning') icon = 'fa-exclamation-triangle';
    
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${icon}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // إضافة التنسيقات
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e50914' : type === 'warning' ? '#f39c12' : '#3498db'};
        color: white;
        padding: 12px 25px;
        border-radius: 50px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 9999;
        animation: slideDown 0.3s ease;
        direction: rtl;
        font-family: 'Tajawal', sans-serif;
    `;
    
    document.body.appendChild(notification);
    
    // إخفاء الإشعار بعد 3 ثواني
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// إضافة حركات CSS للإشعارات إذا لم تكن موجودة
if (!document.querySelector('#notification-styles')) {
    const style = document.createElement('style');
    style.id = 'notification-styles';
    style.textContent = `
        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translate(-50%, 0); opacity: 1; }
            to { transform: translate(-50%, -100%); opacity: 0; }
        }
        .notification-close {
            background: transparent;
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            padding: 0 5px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .notification-close:hover {
            opacity: 1;
        }
        .notification-content {
            display: flex;
            align-items: center;
            gap: 10px;
        }
    `;
    document.head.appendChild(style);
}
function addEpisodeToDB(seriesId, seasonNumber, episodeData) {
    const formData = new FormData();
    formData.append('action', 'add_episode');
    formData.append('series_id', seriesId);
    formData.append('season_number', seasonNumber);
    formData.append('episode_number', episodeData.number);
    formData.append('title', episodeData.title);
    formData.append('description', episodeData.description || '');
    formData.append('duration', episodeData.duration || 45);
    formData.append('still_path', episodeData.still_path || '');
    formData.append('air_date', episodeData.air_date || '');
    
    fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم إضافة الحلقة:', data);
            showNotification('تم إضافة الحلقة بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
    });
}


function deleteEpisodeFromDB(episodeId, episodeElement) {
    if (!confirm('هل أنت متأكد من حذف هذه الحلقة نهائياً؟')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_episode');
    formData.append('episode_id', episodeId);
    
    fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الحلقة:', data);
            // إزالة العنصر من الصفحة بعد الحذف الناجح
            if (episodeElement) {
                episodeElement.remove();
            }
            showNotification('تم حذف الحلقة بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
    });
}

/**
 * إضافة رابط مشاهدة مباشرة إلى قاعدة البيانات
 */
function addWatchLinkToDB(itemType, itemId, linkData) {
    const formData = new FormData();
    formData.append('action', 'add_watch_link');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('server_name', linkData.name);
    formData.append('server_url', linkData.url);
    formData.append('quality', linkData.quality || 'HD');
    formData.append('language', linkData.lang || 'arabic');
    
    fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم إضافة الرابط:', data);
            showNotification('تم إضافة رابط المشاهدة بنجاح', 'success');
            return data.link_id;
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
            return null;
        }
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
        return null;
    });
}

/**
 * حذف رابط مشاهدة من قاعدة البيانات
 */
function deleteWatchLinkFromDB(linkId, linkElement) {
    if (!confirm('هل أنت متأكد من حذف هذا الرابط؟')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_watch_link');
    formData.append('link_id', linkId);
    
    fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ تم حذف الرابط:', data);
            if (linkElement) {
                linkElement.remove();
            }
            showNotification('تم حذف الرابط بنجاح', 'success');
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
    });
}

/**
 * رفع ملف فيديو مباشرة
 */
function uploadVideoFile(itemType, itemId, fileInput) {
    if (!fileInput.files || fileInput.files.length === 0) {
        showNotification('الرجاء اختيار ملف', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'upload_video');
    formData.append('item_type', itemType);
    formData.append('item_id', itemId);
    formData.append('video_file', fileInput.files[0]);
    
    // إظهار مؤشر التحميل
    const uploadBtn = fileInput.closest('.upload-box')?.querySelector('.upload-btn');
    if (uploadBtn) {
        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الرفع...';
    }
    
    fetch('api-content.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> رفع';
        }
        
        if (data.success) {
            console.log('✅ تم رفع الفيديو:', data);
            showNotification('تم رفع الفيديو بنجاح', 'success');
            
            // تحديث رابط الفيديو في الواجهة
            const videoUrlInput = fileInput.closest('.upload-box')?.querySelector('.video-url-input');
            if (videoUrlInput) {
                videoUrlInput.value = data.video_url;
            }
        } else {
            console.error('❌ خطأ:', data.message);
            showNotification('خطأ: ' + data.message, 'error');
        }
    })
    .catch(error => {
        if (uploadBtn) {
            uploadBtn.disabled = false;
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> رفع';
        }
        console.error('❌ خطأ في الاتصال:', error);
        showNotification('خطأ في الاتصال بالخادم', 'error');
    });
}

/**
 * إظهار إشعار للمستخدم
 */
function showNotification(message, type = 'info') {
    // إنشاء عنصر الإشعار
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="notification-close" onclick="this.parentElement.remove()">&times;</button>
    `;
    
    // إضافة التنسيقات
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e50914' : '#3498db'};
        color: white;
        padding: 12px 25px;
        border-radius: 50px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        display: flex;
        align-items: center;
        gap: 15px;
        z-index: 9999;
        animation: slideDown 0.3s ease;
        direction: rtl;
    `;
    
    document.body.appendChild(notification);
    
    // إخفاء الإشعار بعد 3 ثواني
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }
    }, 3000);
}

// إضافة حركات CSS للإشعارات
const style = document.createElement('style');
style.textContent = `
    @keyframes slideDown {
        from { transform: translate(-50%, -100%); opacity: 0; }
        to { transform: translate(-50%, 0); opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translate(-50%, 0); opacity: 1; }
        to { transform: translate(-50%, -100%); opacity: 0; }
    }
`;
document.head.appendChild(style);

// تعديل دالة removeEpisode القديمة (إذا كانت موجودة) أو إبقائها
function removeEpisode(episodeId) {
    if (confirm('هل أنت متأكد من حذف هذه الحلقة؟')) {
        const episodeElement = document.getElementById(episodeId);
        if (episodeElement) {
            episodeElement.remove();
        }
    }
}

/**
 * حذف حلقة من المسلسل
 */
function deleteEpisode(episodeId, contentType, seriesId) {
    if (!confirm('⚠️ هل أنت متأكد من حذف هذه الحلقة نهائياً؟ سيتم حذف جميع الروابط والترجمات المرتبطة بها!')) {
        return;
    }
    
    window.location.href = `import-from-any-site.php?mode=edit&delete_episode=${episodeId}&series_id=${seriesId}&type=${contentType}`;
}

/**
 * فتح نموذج تعديل روابط الحلقة
 */
function editEpisodeLinks(episodeId, contentType, seriesId) {
    const modal = document.createElement('div');
    modal.id = 'episodeLinksModal';
    modal.innerHTML = `
        <div style="
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        ">
            <div style="
                background: #1a1a1a;
                border: 2px solid #e50914;
                border-radius: 10px;
                padding: 30px;
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                direction: rtl;
            ">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: #e50914; margin: 0;">🔗 إضافة روابط للحلقة</h2>
                    <button onclick="document.getElementById('episodeLinksModal').remove()" style="
                        background: none;
                        border: none;
                        color: #999;
                        font-size: 24px;
                        cursor: pointer;
                    ">&times;</button>
                </div>
                
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #27ae60; margin-bottom: 10px;">📺 رابط مشاهدة</h4>
                    <form method="POST" style="background: #252525; padding: 15px; border-radius: 6px;">
                        <input type="hidden" name="add_episode_watch_link" value="1">
                        <input type="hidden" name="episode_id" value="${episodeId}">
                        <input type="hidden" name="series_id" value="${seriesId}">
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; color: #999; margin-bottom: 5px;">اسم السيرفر</label>
                            <input type="text" name="server_name" value="سيرفر مشاهدة" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #444;
                                background: #1a1a1a;
                                color: #fff;
                                border-radius: 4px;
                            " required>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; color: #999; margin-bottom: 5px;">رابط المشاهدة</label>
                            <input type="url" name="server_url" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #444;
                                background: #1a1a1a;
                                color: #fff;
                                border-radius: 4px;
                            " required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; color: #999; margin-bottom: 5px;">الجودة</label>
                                <select name="quality" style="
                                    width: 100%;
                                    padding: 8px;
                                    border: 1px solid #444;
                                    background: #1a1a1a;
                                    color: #fff;
                                    border-radius: 4px;
                                ">
                                    <option value="4K">4K UHD</option>
                                    <option value="1080p">1080p HD</option>
                                    <option value="720p" selected>720p HD</option>
                                    <option value="480p">480p</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; color: #999; margin-bottom: 5px;">اللغة</label>
                                <select name="language" style="
                                    width: 100%;
                                    padding: 8px;
                                    border: 1px solid #444;
                                    background: #1a1a1a;
                                    color: #fff;
                                    border-radius: 4px;
                                ">
                                    <option value="arabic" selected>🇸🇦 عربي</option>
                                    <option value="english">🇬🇧 English</option>
                                </select>
                            </div>
                        </div>
                        
                        <button type="submit" style="
                            width: 100%;
                            padding: 10px;
                            background: #27ae60;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: bold;
                        ">+ إضافة رابط مشاهدة</button>
                    </form>
                </div>
                
                <div>
                    <h4 style="color: #3498db; margin-bottom: 10px;">⬇️ رابط تحميل</h4>
                    <form method="POST" style="background: #252525; padding: 15px; border-radius: 6px;">
                        <input type="hidden" name="add_episode_download_link" value="1">
                        <input type="hidden" name="episode_id" value="${episodeId}">
                        <input type="hidden" name="series_id" value="${seriesId}">
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; color: #999; margin-bottom: 5px;">اسم السيرفر</label>
                            <input type="text" name="server_name" value="سيرفر تحميل" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #444;
                                background: #1a1a1a;
                                color: #fff;
                                border-radius: 4px;
                            " required>
                        </div>
                        
                        <div style="margin-bottom: 10px;">
                            <label style="display: block; color: #999; margin-bottom: 5px;">رابط التحميل</label>
                            <input type="url" name="download_url" style="
                                width: 100%;
                                padding: 8px;
                                border: 1px solid #444;
                                background: #1a1a1a;
                                color: #fff;
                                border-radius: 4px;
                            " required>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                            <div>
                                <label style="display: block; color: #999; margin-bottom: 5px;">الجودة</label>
                                <select name="quality" style="
                                    width: 100%;
                                    padding: 8px;
                                    border: 1px solid #444;
                                    background: #1a1a1a;
                                    color: #fff;
                                    border-radius: 4px;
                                ">
                                    <option value="4K">4K UHD</option>
                                    <option value="1080p">1080p HD</option>
                                    <option value="720p" selected>720p HD</option>
                                    <option value="480p">480p</option>
                                </select>
                            </div>
                            
                            <div>
                                <label style="display: block; color: #999; margin-bottom: 5px;">الحجم</label>
                                <input type="text" name="size" placeholder="مثل: 700MB" style="
                                    width: 100%;
                                    padding: 8px;
                                    border: 1px solid #444;
                                    background: #1a1a1a;
                                    color: #fff;
                                    border-radius: 4px;
                                ">
                            </div>
                        </div>
                        
                        <button type="submit" style="
                            width: 100%;
                            padding: 10px;
                            background: #3498db;
                            color: white;
                            border: none;
                            border-radius: 4px;
                            cursor: pointer;
                            font-weight: bold;
                        ">+ إضافة رابط تحميل</button>
                    </form>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // إغلاق بالنقر خارج المودال
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.remove();
        }
    });
}
        
        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            console.log('الصفحة جاهزة');
        });
    </script>
</body>
</html>