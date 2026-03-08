<?php
// admin/free-manager.php - إدارة محتوى يوتيوب (مع بحث بالرابط)
define('ALLOW_ACCESS', true);

// تحديد المسار الصحيح
$base_path = 'C:/xampp/htdocs/fayez-movie';
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// مفتاح API يوتيوب
$youtube_api_key = 'AIzaSyApqaqvZDto7tpEQEWRYw3QVzguTxfnKcU';

// إنشاء مجلد رفع الصور
$upload_dir = $base_path . '/uploads/youtube/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// التأكد من وجود الجدول
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS youtube_content (
            id INT AUTO_INCREMENT PRIMARY KEY,
            video_id VARCHAR(100) NOT NULL UNIQUE,
            title VARCHAR(500) NOT NULL,
            thumbnail VARCHAR(500),
            channel_title VARCHAR(255),
            category VARCHAR(50) DEFAULT 'general',
            duration VARCHAR(20),
            view_count VARCHAR(50),
            published_at DATETIME,
            local_thumbnail VARCHAR(500),
            added_by VARCHAR(100),
            views INT DEFAULT 0,
            status TINYINT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    die("خطأ في إنشاء الجدول: " . $e->getMessage());
}

// دالة لتحميل الصور
function downloadThumbnail($url, $video_id, $upload_dir) {
    if (empty($url)) return '';
    
    $filename = 'yt_' . $video_id . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200 && !empty($data)) {
        file_put_contents($filepath, $data);
        return 'uploads/youtube/' . $filename;
    }
    
    return $url;
}

// دالة لجلب تفاصيل الفيديو
function getVideoDetails($video_id, $api_key) {
    $url = "https://www.googleapis.com/youtube/v3/videos?part=contentDetails,statistics&id=" . $video_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['items'][0])) {
        $duration = $data['items'][0]['contentDetails']['duration'];
        $viewCount = $data['items'][0]['statistics']['viewCount'] ?? 0;
        
        // تحويل المدة
        $duration = str_replace(['PT', 'H', 'M', 'S'], ['', ':', ':', ''], $duration);
        $parts = explode(':', $duration);
        
        if (count($parts) == 3) {
            $duration = sprintf("%02d:%02d:%02d", (int)$parts[0], (int)$parts[1], (int)$parts[2]);
        } elseif (count($parts) == 2) {
            $duration = sprintf("00:%02d:%02d", (int)$parts[0], (int)$parts[1]);
        } else {
            $duration = sprintf("00:00:%02d", (int)$parts[0]);
        }
        
        return [
            'duration' => $duration,
            'viewCount' => number_format($viewCount)
        ];
    }
    
    return [
        'duration' => '00:00:00',
        'viewCount' => '0'
    ];
}

// دالة لاستخراج video_id من رابط يوتيوب
function extractVideoIdFromUrl($url) {
    $patterns = [
        '/youtube\.com\/watch\?v=([^&]+)/',
        '/youtu\.be\/([^?]+)/',
        '/youtube\.com\/embed\/([^?]+)/',
        '/youtube\.com\/v\/([^?]+)/',
        '/youtube\.com\/shorts\/([^?]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    
    return false;
}

// دالة لاستخراج playlist_id من رابط يوتيوب
function extractPlaylistIdFromUrl($url) {
    $patterns = [
        '/youtube\.com\/playlist\?list=([^&]+)/',
        '/youtube\.com\/watch\?.*list=([^&]+)/',
        '/youtu\.be\/.*\?list=([^&]+)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }
    }
    return false;
}

// دالة لاستخراج channel_id من رابط يوتيوب (يدعم /channel، /user، /c، و@username)
function extractChannelIdFromUrl($url, $api_key) {
    //مباشر
    if (preg_match('/youtube\.com\/channel\/([^\/\?]+)/', $url, $m)) {
        return $m[1];
    }
    // اسم المستخدم القديم
    if (preg_match('/youtube\.com\/user\/([^\/\?]+)/', $url, $m)) {
        $username = $m[1];
        $apiUrl = "https://www.googleapis.com/youtube/v3/channels?part=id&forUsername=" . urlencode($username) . "&key=" . $api_key;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        if (isset($data['items'][0]['id'])) {
            return $data['items'][0]['id'];
        }
    }
    // قنوات مخصصة /c أو @
    if (preg_match('/youtube\.com\/(?:c|@)([^\/\?]+)/', $url, $m)) {
        $identifier = $m[1];
        $searchUrl = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=channel&q=" . urlencode($identifier) . "&key=" . $api_key . "&maxResults=1";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $searchUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $resp = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($resp, true);
        if (isset($data['items'][0]['snippet']['channelId'])) {
            return $data['items'][0]['snippet']['channelId'];
        }
    }
    return false;
}

// دالة لجلب تفاصيل قائمة التشغيل
function getPlaylistDetails($playlist_id, $api_key) {
    $url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet&id=" . $playlist_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['items'][0])) {
        $snippet = $data['items'][0]['snippet'];
        return [
            'title' => $snippet['title'],
            'description' => $snippet['description'] ?? '',
            'thumbnail' => $snippet['thumbnails']['high']['url'] ?? '',
            'channelTitle' => $snippet['channelTitle'],
            'channelId' => $snippet['channelId']
        ];
    }
    
    return [
        'title' => '',
        'description' => '',
        'thumbnail' => '',
        'channelTitle' => '',
        'channelId' => ''
    ];
}

// دالة لجلب قائمة تشغيل كاملة
function getPlaylistVideos($playlist_id, $api_key, $max_results = 50) {
    $videos = [];
    $next_page_token = '';
    $total_fetched = 0;
    
    do {
        // جلب عناصر قائمة التشغيل
        $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId=" . $playlist_id . "&key=" . $api_key;
        if (!empty($next_page_token)) {
            $url .= "&pageToken=" . $next_page_token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (!isset($data['items'])) {
            break;
        }
        
        // تجميع IDs الفيديوهات
        $video_ids = [];
        foreach ($data['items'] as $item) {
            if (isset($item['snippet']['resourceId']['videoId'])) {
                $video_ids[] = $item['snippet']['resourceId']['videoId'];
            }
        }
        
        // جلب تفاصيل الفيديوهات (المدة، المشاهدات)
        if (!empty($video_ids)) {
            $ids_string = implode(',', $video_ids);
            $details_url = "https://www.googleapis.com/youtube/v3/videos?part=contentDetails,statistics&id=" . $ids_string . "&key=" . $api_key;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $details_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $details_response = curl_exec($ch);
            curl_close($ch);
            
            $details_data = json_decode($details_response, true);
            $details_map = [];
            
            if (isset($details_data['items'])) {
                foreach ($details_data['items'] as $detail) {
                    $duration = $detail['contentDetails']['duration'];
                    $viewCount = $detail['statistics']['viewCount'] ?? 0;
                    
                    // تحويل المدة
                    $duration = str_replace(['PT', 'H', 'M', 'S'], ['', ':', ':', ''], $duration);
                    $parts = explode(':', $duration);
                    
                    if (count($parts) == 3) {
                        $duration = sprintf("%02d:%02d:%02d", (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                    } elseif (count($parts) == 2) {
                        $duration = sprintf("00:%02d:%02d", (int)$parts[0], (int)$parts[1]);
                    } else {
                        $duration = sprintf("00:00:%02d", (int)$parts[0]);
                    }
                    
                    $details_map[$detail['id']] = [
                        'duration' => $duration,
                        'viewCount' => number_format($viewCount)
                    ];
                }
            }
            
            // تجميع النتائج
            foreach ($data['items'] as $item) {
                $video_id = $item['snippet']['resourceId']['videoId'];
                $snippet = $item['snippet'];
                
                $videos[] = [
                    'videoId' => $video_id,
                    'title' => $snippet['title'],
                    'thumbnail' => $snippet['thumbnails']['high']['url'],
                    'channelTitle' => $snippet['channelTitle'],
                    'publishedAt' => $snippet['publishedAt'],
                    'description' => $snippet['description'] ?? '',
                    'duration' => $details_map[$video_id]['duration'] ?? '00:00:00',
                    'viewCount' => $details_map[$video_id]['viewCount'] ?? '0'
                ];
            }
        }
        
        $total_fetched += count($video_ids);
        $next_page_token = $data['nextPageToken'] ?? '';
    } while (!empty($next_page_token) && $total_fetched < $max_results);
    
    return $videos;
}

// دالة لجلب فيديوهات من قناة كاملة
function getChannelVideos($channel_id, $api_key, $max_results = 50) {
    $videos = [];
    $next_page_token = '';
    $total_fetched = 0;

    do {
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . $channel_id . "&maxResults=50&type=video&order=date&key=" . $api_key;
        if (!empty($next_page_token)) {
            $url .= "&pageToken=" . $next_page_token;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (!isset($data['items'])) break;

        $video_ids = [];
        $snippets = [];
        foreach ($data['items'] as $item) {
            if (isset($item['id']['videoId'])) {
                $id = $item['id']['videoId'];
                $video_ids[] = $id;
                $snippets[$id] = $item['snippet'];
            }
        }

        if (!empty($video_ids)) {
            $ids_string = implode(',', $video_ids);
            $details_url = "https://www.googleapis.com/youtube/v3/videos?part=contentDetails,statistics&id=" . $ids_string . "&key=" . $api_key;
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $details_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $details_response = curl_exec($ch);
            curl_close($ch);
            
            $details_data = json_decode($details_response, true);
            $details_map = [];
            if (isset($details_data['items'])) {
                foreach ($details_data['items'] as $detail) {
                    $duration = str_replace(['PT', 'H', 'M', 'S'], ['', ':', ':', ''], $detail['contentDetails']['duration']);
                    $parts = explode(':', $duration);
                    if (count($parts) == 3) {
                        $duration = sprintf("%02d:%02d:%02d", (int)$parts[0], (int)$parts[1], (int)$parts[2]);
                    } elseif (count($parts) == 2) {
                        $duration = sprintf("00:%02d:%02d", (int)$parts[0], (int)$parts[1]);
                    } else {
                        $duration = sprintf("00:00:%02d", (int)$parts[0]);
                    }
                    $viewCount = $detail['statistics']['viewCount'] ?? 0;
                    $details_map[$detail['id']] = [
                        'duration' => $duration,
                        'viewCount' => number_format($viewCount)
                    ];
                }
            }

            foreach ($video_ids as $vid) {
                $snippet = $snippets[$vid];
                $videos[] = [
                    'videoId' => $vid,
                    'title' => $snippet['title'],
                    'thumbnail' => $snippet['thumbnails']['high']['url'],
                    'channelTitle' => $snippet['channelTitle'],
                    'publishedAt' => $snippet['publishedAt'],
                    'duration' => $details_map[$vid]['duration'] ?? '00:00:00',
                    'viewCount' => $details_map[$vid]['viewCount'] ?? '0'
                ];
            }
        }

        $total_fetched += count($video_ids);
        $next_page_token = $data['nextPageToken'] ?? '';
    } while (!empty($next_page_token) && $total_fetched < $max_results);

    return $videos;
}

// دالة لجلب فيديو واحد بالرابط
function getVideoByUrl($url, $api_key) {
    $video_id = extractVideoIdFromUrl($url);
    if (!$video_id) {
        return ['error' => 'رابط يوتيوب غير صحيح'];
    }
    
    // جلب بيانات الفيديو
    $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id=" . $video_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!isset($data['items'][0])) {
        return ['error' => 'لم يتم العثور على الفيديو'];
    }
    
    $item = $data['items'][0];
    $snippet = $item['snippet'];
    
    // تحويل المدة
    $duration = $item['contentDetails']['duration'];
    $duration = str_replace(['PT', 'H', 'M', 'S'], ['', ':', ':', ''], $duration);
    $parts = explode(':', $duration);
    
    if (count($parts) == 3) {
        $duration = sprintf("%02d:%02d:%02d", (int)$parts[0], (int)$parts[1], (int)$parts[2]);
    } elseif (count($parts) == 2) {
        $duration = sprintf("00:%02d:%02d", (int)$parts[0], (int)$parts[1]);
    } else {
        $duration = sprintf("00:00:%02d", (int)$parts[0]);
    }
    
    return [
        'videoId' => $video_id,
        'title' => $snippet['title'],
        'thumbnail' => $snippet['thumbnails']['high']['url'],
        'channelTitle' => $snippet['channelTitle'],
        'publishedAt' => $snippet['publishedAt'],
        'duration' => $duration,
        'viewCount' => number_format($item['statistics']['viewCount'] ?? 0)
    ];
}

// معالجة البحث
$search_results = [];
$search_query = '';

// معالجة البحث بالكلمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_youtube'])) {
    // إذا كان هناك معلومات قائمة تشغيل مخزنة، أزلها لتجنب التداخل
    unset($_SESSION['playlist_info'], $_SESSION['playlist_videos']);

    $search_query = $_POST['search_query'] ?? '';
    $content_type = $_POST['content_type'] ?? 'all';
    $max_results = (int)($_POST['max_results'] ?? 20);
    
    if (!empty($search_query)) {
        // تحسين كلمات البحث
        if ($content_type == 'movies') {
            $search_query .= ' فيلم';
        } elseif ($content_type == 'series') {
            $search_query .= ' مسلسل حلقة';
        }
        
        // بناء رابط API
        $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=" . urlencode($search_query) . 
               "&type=video&maxResults=" . $max_results . 
               "&relevanceLanguage=ar&videoDuration=long&key=" . $youtube_api_key;
        
        if ($content_type == 'movies') {
            $url .= "&videoCategoryId=1";
        } elseif ($content_type == 'series') {
            $url .= "&videoCategoryId=10";
        }
        
        // تنفيذ الطلب
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            $data = json_decode($response, true);
            
            if (isset($data['items']) && !empty($data['items'])) {
                foreach ($data['items'] as $item) {
                    $video_id = $item['id']['videoId'];
                    $video_details = getVideoDetails($video_id, $youtube_api_key);
                    
                    // تخزين النتائج في الجلسة
                    $_SESSION['search_results'][$video_id] = [
                        'title' => $item['snippet']['title'],
                        'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                        'channelTitle' => $item['snippet']['channelTitle'],
                        'publishedAt' => $item['snippet']['publishedAt'],
                        'duration' => $video_details['duration'],
                        'viewCount' => $video_details['viewCount']
                    ];
                    
                    // التحقق من وجود الفيديو في قاعدة البيانات
                    $check = $pdo->prepare("SELECT id FROM youtube_content WHERE video_id = ?");
                    $check->execute([$video_id]);
                    $exists = $check->fetch();
                    
                    $search_results[] = [
                        'videoId' => $video_id,
                        'title' => $item['snippet']['title'],
                        'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
                        'channelTitle' => $item['snippet']['channelTitle'],
                        'publishedAt' => $item['snippet']['publishedAt'],
                        'duration' => $video_details['duration'],
                        'viewCount' => $video_details['viewCount'],
                        'exists' => $exists ? true : false
                    ];
                }
                $message = "✅ تم العثور على " . count($search_results) . " نتيجة";
                $message_type = "success";
            } else {
                $message = "❌ لا توجد نتائج";
                $message_type = "error";
            }
        } else {
            $message = "❌ خطأ في الاتصال بـ YouTube API";
            $message_type = "error";
        }
    }
}

// معالجة البحث بالرابط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_by_url'])) {
    unset($_SESSION['playlist_info'], $_SESSION['playlist_videos']);
    $video_url = $_POST['video_url'] ?? '';
    
    if (!empty($video_url)) {
        $video_data = getVideoByUrl($video_url, $youtube_api_key);
        
        if (isset($video_data['error'])) {
            $message = "❌ " . $video_data['error'];
            $message_type = "error";
        } else {
            // تخزين في الجلسة
            $_SESSION['search_results'][$video_data['videoId']] = [
                'title' => $video_data['title'],
                'thumbnail' => $video_data['thumbnail'],
                'channelTitle' => $video_data['channelTitle'],
                'publishedAt' => $video_data['publishedAt'],
                'duration' => $video_data['duration'],
                'viewCount' => $video_data['viewCount']
            ];
            
            // التحقق من وجود الفيديو
            $check = $pdo->prepare("SELECT id FROM youtube_content WHERE video_id = ?");
            $check->execute([$video_data['videoId']]);
            $exists = $check->fetch();
            
            $search_results[] = [
                'videoId' => $video_data['videoId'],
                'title' => $video_data['title'],
                'thumbnail' => $video_data['thumbnail'],
                'channelTitle' => $video_data['channelTitle'],
                'publishedAt' => $video_data['publishedAt'],
                'duration' => $video_data['duration'],
                'viewCount' => $video_data['viewCount'],
                'exists' => $exists ? true : false
            ];
            
            $message = "✅ تم العثور على الفيديو";
            $message_type = "success";
        }
    }
}

// معالجة البحث بقائمة تشغيل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_playlist'])) {
    $playlist_url = $_POST['playlist_url'] ?? '';
    if (!empty($playlist_url)) {
        $playlist_id = extractPlaylistIdFromUrl($playlist_url);
        if (!$playlist_id) {
            $message = "❌ رابط قائمة تشغيل غير صحيح";
            $message_type = "error";
        } else {
            // نحصل على بيانات القائمة لعرضها لاحقاً
            $playlist_data = getPlaylistDetails($playlist_id, $youtube_api_key);

            $videos = getPlaylistVideos($playlist_id, $youtube_api_key);
            if (empty($videos)) {
                $message = "❌ لم يتم العثور على مقاطع في قائمة التشغيل";
                $message_type = "error";
            } else {
                // تخزين قائمة المعرفات بالترتيب
                $_SESSION['playlist_info'] = [
                    'playlist_id' => $playlist_id,
                    'title' => $playlist_data['title'] ?? '',
                    'description' => $playlist_data['description'] ?? '',
                    'thumbnail' => $playlist_data['thumbnail'] ?? '',
                    'channelTitle' => $playlist_data['channelTitle'] ?? '',
                    'channelId' => $playlist_data['channelId'] ?? ''
                ];
                $_SESSION['playlist_videos'] = array_map(function($v){ return $v['videoId']; }, $videos);

                foreach ($videos as $video) {
                    // حفظ في الجلسة
                    $_SESSION['search_results'][$video['videoId']] = [
                        'title' => $video['title'],
                        'thumbnail' => $video['thumbnail'],
                        'channelTitle' => $video['channelTitle'],
                        'publishedAt' => $video['publishedAt'],
                        'duration' => $video['duration'],
                        'viewCount' => $video['viewCount'],
                        'description' => $video['description'] ?? ''
                    ];

                    $check = $pdo->prepare("SELECT id FROM youtube_content WHERE video_id = ?");
                    $check->execute([$video['videoId']]);
                    $exists = $check->fetch();

                    $search_results[] = [
                        'videoId' => $video['videoId'],
                        'title' => $video['title'],
                        'thumbnail' => $video['thumbnail'],
                        'channelTitle' => $video['channelTitle'],
                        'publishedAt' => $video['publishedAt'],
                        'duration' => $video['duration'],
                        'viewCount' => $video['viewCount'],
                        'exists' => $exists ? true : false
                    ];
                }
                $message = "✅ تم العثور على " . count($search_results) . " نتيجة";
                $message_type = "success";
            }
        }
    }
}

// معالجة البحث بالقناة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_channel'])) {
    unset($_SESSION['playlist_info'], $_SESSION['playlist_videos']);
    $channel_url = $_POST['channel_url'] ?? '';
    if (!empty($channel_url)) {
        $channel_id = extractChannelIdFromUrl($channel_url, $youtube_api_key);
        if (!$channel_id) {
            $message = "❌ رابط قناة غير صحيح";
            $message_type = "error";
        } else {
            $videos = getChannelVideos($channel_id, $youtube_api_key);
            if (empty($videos)) {
                $message = "❌ لم يتم العثور على مقاطع من القناة";
                $message_type = "error";
            } else {
                foreach ($videos as $video) {
                    $_SESSION['search_results'][$video['videoId']] = [
                        'title' => $video['title'],
                        'thumbnail' => $video['thumbnail'],
                        'channelTitle' => $video['channelTitle'],
                        'publishedAt' => $video['publishedAt'],
                        'duration' => $video['duration'],
                        'viewCount' => $video['viewCount']
                    ];

                    $check = $pdo->prepare("SELECT id FROM youtube_content WHERE video_id = ?");
                    $check->execute([$video['videoId']]);
                    $exists = $check->fetch();

                    $search_results[] = [
                        'videoId' => $video['videoId'],
                        'title' => $video['title'],
                        'thumbnail' => $video['thumbnail'],
                        'channelTitle' => $video['channelTitle'],
                        'publishedAt' => $video['publishedAt'],
                        'duration' => $video['duration'],
                        'viewCount' => $video['viewCount'],
                        'exists' => $exists ? true : false
                    ];
                }
                $message = "✅ تم العثور على " . count($search_results) . " نتيجة";
                $message_type = "success";
            }
        }
    }
}

// حفظ الفيديوهات في قاعدة البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_selected'])) {
    $selected_videos = $_POST['selected_videos'] ?? [];
    $category = $_POST['category'] ?? 'general';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (isset($_SESSION['playlist_info'])) {
        // حفظ كامل القائمة كسلسلة
        $info = $_SESSION['playlist_info'];
        $playlist_ids = $_SESSION['playlist_videos'] ?? [];

        $series_title = $_POST['series_title'] ?? $info['title'];
        $series_description = $_POST['series_description'] ?? $info['description'];

        if (!empty($selected_videos)) {
            $episode_ids = array_values(array_intersect($playlist_ids, $selected_videos));
        } else {
            $episode_ids = $playlist_ids;
        }

        $local_thumbnail = '';
        if ($download_images && !empty($info['thumbnail'])) {
            $local_thumbnail = downloadThumbnail($info['thumbnail'], 'series_' . $info['playlist_id'], $upload_dir);
        }

        $insert_series = $pdo->prepare("
            INSERT INTO youtube_series (
                playlist_id, title, description, thumbnail, channel_title, channel_id,
                video_count, category, local_thumbnail, added_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                title = VALUES(title),
                description = VALUES(description),
                thumbnail = VALUES(thumbnail),
                video_count = VALUES(video_count),
                local_thumbnail = VALUES(local_thumbnail)
        ");

        $insert_series->execute([
            $info['playlist_id'],
            $series_title,
            $series_description,
            $info['thumbnail'],
            $info['channelTitle'],
            $info['channelId'],
            count($episode_ids),
            $category,
            $local_thumbnail,
            $_SESSION['username'] ?? 'admin'
        ]);

        $series_id = $pdo->lastInsertId();
        if (!$series_id) {
            $get_id = $pdo->prepare("SELECT id FROM youtube_series WHERE playlist_id = ?");
            $get_id->execute([$info['playlist_id']]);
            $series_id = $get_id->fetchColumn();
        }

        $delete_episodes = $pdo->prepare("DELETE FROM youtube_episodes WHERE series_id = ?");
        $delete_episodes->execute([$series_id]);

        $insert_episode = $pdo->prepare("
            INSERT INTO youtube_episodes (
                series_id, video_id, title, description, thumbnail,
                episode_number, duration, view_count, published_at, local_thumbnail
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $episode_count = 0;
        foreach ($episode_ids as $index => $vid) {
            if (!isset($_SESSION['search_results'][$vid])) continue;
            $video = $_SESSION['search_results'][$vid];

            $episode_thumbnail = '';
            if ($download_images) {
                $episode_thumbnail = downloadThumbnail($video['thumbnail'], 'ep_' . $vid, $upload_dir);
            }

            $insert_episode->execute([
                $series_id,
                $vid,
                $video['title'],
                $video['description'] ?? '',
                $video['thumbnail'],
                $index + 1,
                $video['duration'],
                $video['viewCount'],
                date('Y-m-d H:i:s', strtotime($video['publishedAt'])),
                $episode_thumbnail
            ]);
            $episode_count++;
        }

        $message = "✅ تم حفظ السلسلة بنجاح!";
        $message .= "<br>عدد الحلقات: " . $episode_count;
        if (!empty($series_id)) {
            $message .= "<br><a href=\"youtube-series-episodes.php?id=$series_id\" style=\"color:#a5d6a7;\">عرض الحلقات</a>";
        }
        $message_type = "success";

        unset($_SESSION['search_results'], $_SESSION['playlist_info'], $_SESSION['playlist_videos']);
    } else {
        if (empty($selected_videos)) {
            $message = "❌ لم يتم تحديد أي فيديوهات";
            $message_type = "error";
        } else {
            $saved_count = 0;
            $updated_count = 0;
            
            foreach ($selected_videos as $video_id) {
                // البحث عن الفيديو في الجلسة
                if (isset($_SESSION['search_results'][$video_id])) {
                    $video = $_SESSION['search_results'][$video_id];
                    
                    // تحميل الصورة إذا مطلوب
                    $local_thumbnail = '';
                    if ($download_images) {
                        $local_thumbnail = downloadThumbnail($video['thumbnail'], $video_id, $upload_dir);
                    }
                    
                    // التحقق من وجود الفيديو
                    $check = $pdo->prepare("SELECT id FROM youtube_content WHERE video_id = ?");
                    $check->execute([$video_id]);
                    
                    if ($check->rowCount() > 0) {
                        // تحديث
                        $update = $pdo->prepare("
                            UPDATE youtube_content SET 
                                title = ?,
                                thumbnail = ?,
                                channel_title = ?,
                                category = ?,
                                duration = ?,
                                view_count = ?,
                                published_at = ?,
                                local_thumbnail = ?
                            WHERE video_id = ?
                        ");
                        
                        $update->execute([
                            $video['title'],
                            $video['thumbnail'],
                            $video['channelTitle'],
                            $category,
                            $video['duration'],
                            $video['viewCount'],
                            date('Y-m-d H:i:s', strtotime($video['publishedAt'])),
                            $local_thumbnail,
                            $video_id
                        ]);
                        $updated_count++;
                    } else {
                        // إضافة جديد
                        $insert = $pdo->prepare("
                            INSERT INTO youtube_content (
                                video_id, title, thumbnail, channel_title, 
                                category, duration, view_count, published_at, local_thumbnail, added_by
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $insert->execute([
                            $video_id,
                            $video['title'],
                            $video['thumbnail'],
                            $video['channelTitle'],
                            $category,
                            $video['duration'],
                            $video['viewCount'],
                            date('Y-m-d H:i:s', strtotime($video['publishedAt'])),
                            $local_thumbnail,
                            $_SESSION['username'] ?? 'admin'
                        ]);
                        $saved_count++;
                    }
                }
            }
            
            $message = "✅ تم إضافة $saved_count فيديو جديد وتحديث $updated_count فيديو بنجاح!";
            $message_type = "success";
            
            // مسح نتائج البحث من الجلسة
            unset($_SESSION['search_results']);
        }
    }
}

// جلب المحتوى المحفوظ
$saved_content = [];
$total_count = 0;
$movies_count = 0;
$series_count = 0;

try {
    // إحصائيات
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN category = 'movies' THEN 1 ELSE 0 END) as movies,
            SUM(CASE WHEN category = 'series' THEN 1 ELSE 0 END) as series
        FROM youtube_content
    ")->fetch();
    
    $total_count = $stats['total'] ?? 0;
    $movies_count = $stats['movies'] ?? 0;
    $series_count = $stats['series'] ?? 0;
    
    // جلب المحتوى
    $filter = $_GET['filter'] ?? 'all';
    
    if ($filter == 'movies') {
        $stmt = $pdo->query("SELECT * FROM youtube_content WHERE category = 'movies' ORDER BY id DESC LIMIT 50");
    } elseif ($filter == 'series') {
        $stmt = $pdo->query("SELECT * FROM youtube_content WHERE category = 'series' ORDER BY id DESC LIMIT 50");
    } else {
        $stmt = $pdo->query("SELECT * FROM youtube_content ORDER BY id DESC LIMIT 50");
    }
    
    $saved_content = $stmt->fetchAll();
    
} catch (Exception $e) {
    $saved_content = [];
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة محتوى يوتيوب - بحث بالرابط</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
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
        }

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

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #1a1a1a;
            padding: 8px 15px;
            border-radius: 30px;
            border: 1px solid #333;
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

        .dashboard-container {
            display: flex;
            padding: 30px;
            gap: 30px;
            min-height: calc(100vh - 80px);
        }

        .sidebar {
            width: 280px;
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
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

        .nav-item:hover,
        .nav-item.active {
            background: rgba(229, 9, 20, 0.1);
            color: #e50914;
        }

        .main-content {
            flex: 1;
            min-width: 0;
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
            color: #e50914;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #252525;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #333;
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
        }

        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
            margin-top: 5px;
        }

        .search-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }

        .search-tab {
            padding: 10px 25px;
            border-radius: 30px;
            background: #252525;
            color: #b3b3b3;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #333;
        }

        .search-tab:hover,
        .search-tab.active {
            background: #e50914;
            color: white;
            border-color: #e50914;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            background: rgba(0,0,0,0.3);
            border: 2px solid #333;
            border-radius: 10px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
        }

        .search-input:focus {
            border-color: #e50914;
            outline: none;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Tajawal', sans-serif;
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

        .btn-warning {
            background: #f39c12;
            color: white;
        }

        .btn-info {
            background: #3498db;
            color: white;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            max-height: 600px;
            overflow-y: auto;
            padding: 10px;
            margin-top: 20px;
        }

        .video-card {
            background: #252525;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: all 0.3s;
            position: relative;
            cursor: pointer;
        }

        .video-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .video-card.selected {
            border-color: #27ae60;
            box-shadow: 0 0 20px rgba(39, 174, 96, 0.3);
        }

        .video-card.exists {
            border-color: #ffc107;
            opacity: 0.8;
        }

        .exists-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            z-index: 2;
        }

        .video-thumbnail {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .video-info {
            padding: 12px;
        }

        .video-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .video-channel {
            color: #b3b3b3;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .duration-badge {
            background: rgba(255,255,255,0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            display: inline-block;
            margin-top: 5px;
        }

        .video-select {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
            z-index: 2;
        }

        .video-select:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .saved-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 20px;
        }

        .saved-item {
            background: #252525;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: all 0.3s;
            position: relative;
        }

        .saved-item:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .saved-thumb {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .saved-info {
            padding: 12px;
        }

        .saved-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: -webkit-box;
            
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .category-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            background: #e50914;
            color: white;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 20px;
            border-radius: 30px;
            background: #252525;
            color: #b3b3b3;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s;
            border: 1px solid #333;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: #e50914;
            color: white;
        }

        .notification {
            position: fixed;
            top: 100px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 50px;
            background: #27ae60;
            color: white;
            font-weight: 600;
            z-index: 9999;
            animation: slideDown 0.5s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .notification.error {
            background: #e50914;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
            }
            .search-box {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="logo">
            <i class="fas fa-film" style="color: #e50914; font-size: 32px;"></i>
            <h1>ويزي<span>برو</span></h1>
        </div>
        <div class="user-menu">
            <div class="user-profile">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                </div>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <?php include 'sidebar.php'; ?>

        <div class="main-content">
            <?php if (isset($message)): ?>
            <div class="notification <?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- الإحصائيات -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $total_count; ?></div>
                    <div class="stat-label">إجمالي المحتوى</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $movies_count; ?></div>
                    <div class="stat-label">🎬 أفلام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $series_count; ?></div>
                    <div class="stat-label">📺 مسلسلات</div>
                </div>
            </div>

            <!-- البحث -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fab fa-youtube" style="color: #ff0000;"></i> البحث في يوتيوب</h2>
                </div>

                <!-- تبويبات البحث -->
                <div class="search-tabs">
                    <span class="search-tab active" onclick="showSearchTab('keyword')">🔍 بحث بالكلمات</span>
                    <span class="search-tab" onclick="showSearchTab('url')">🔗 رابط فيديو/قناة</span>
                    <span class="search-tab" onclick="showSearchTab('playlist')">📂 قائمة تشغيل</span>
                    <span class="search-tab" onclick="showSearchTab('channel')">👤 قناة</span>
                </div>

                <!-- بحث بالكلمات -->
                <div id="keyword-search" style="display: block;">
                    <form method="POST">
                        <div class="search-box">
                            <input type="text" name="search_query" class="search-input" 
                                   placeholder="ابحث عن فيلم أو مسلسل..." 
                                   value="<?php echo htmlspecialchars($search_query); ?>" required>
                            
                            <select name="content_type" class="search-input" style="width: 150px;">
                                <option value="all">الكل</option>
                                <option value="movies">🎬 أفلام</option>
                                <option value="series">📺 مسلسلات</option>
                            </select>
                            
                            <input type="number" name="max_results" class="search-input" 
                                   style="width: 100px;" placeholder="العدد" value="20" min="1" max="50">
                                   
                            <button type="submit" name="search_youtube" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>

                <!-- بحث بالرابط -->
                <div id="url-search" style="display: none;">
                    <form method="POST">
                        <div class="search-box">
                            <input type="url" name="video_url" class="search-input" 
                                   placeholder="أدخل رابط يوتيوب (مثال: https://www.youtube.com/watch?v=XXXXX)" 
                                   required>
                            
                            <button type="submit" name="search_by_url" class="btn btn-info">
                                <i class="fas fa-link"></i> جلب الفيديو
                            </button>
                        </div>
                        <p style="color: #b3b3b3; font-size: 13px; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i>
                            يدعم الروابط: youtube.com/watch?v=, youtu.be/, youtube.com/embed/, youtube.com/shorts/
                        </p>
                    </form>
                </div>

                <!-- بحث بقائمة تشغيل -->
                <div id="playlist-search" style="display: none;">
                    <form method="POST">
                        <div class="search-box">
                            <input type="url" name="playlist_url" class="search-input"
                                   placeholder="رابط قائمة التشغيل" required>
                            <button type="submit" name="search_playlist" class="btn btn-primary">
                                <i class="fas fa-list"></i> جلب القائمة
                            </button>
                        </div>
                    </form>
                </div>

                <!-- بحث بالقناة -->
                <div id="channel-search" style="display: none;">
                    <form method="POST">
                        <div class="search-box">
                            <input type="url" name="channel_url" class="search-input"
                                   placeholder="رابط القناة" required>
                            <button type="submit" name="search_channel" class="btn btn-primary">
                                <i class="fas fa-user"></i> جلب القناة
                            </button>
                        </div>
                    </form>
                </div>

                <?php if (!empty($search_results)): ?>
                <form method="POST">
                    <?php if (isset($_SESSION['playlist_info'])): ?>
                        <p style="margin-top:10px; color:#e50914; font-weight:600;">
                            📂 قائمة تشغيل: <?php echo htmlspecialchars($_SESSION['playlist_info']['title']); ?>
                            (<?php echo count($_SESSION['playlist_videos'] ?? []); ?> فيديو)
                        </p>
                        <div style="margin-top: 10px; display: flex; gap: 10px; flex-wrap: wrap;">
                            <input type="text" name="series_title" class="search-input" style="flex:2; min-width:200px;" 
                                   placeholder="عنوان المسلسل" value="<?php echo htmlspecialchars($_SESSION['playlist_info']['title']); ?>">
                            <input type="text" name="series_description" class="search-input" style="flex:3; min-width:200px;" 
                                   placeholder="وصف المسلسل" value="<?php echo htmlspecialchars($_SESSION['playlist_info']['description']); ?>">
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <select name="category" class="search-input" style="width: 200px;">
                            <option value="movies"<?php echo isset($_SESSION['playlist_info']) ? '' : ''; ?>>🎬 أفلام</option>
                            <option value="series"<?php echo isset($_SESSION['playlist_info']) ? ' selected' : ''; ?>>📺 مسلسلات</option>
                        </select>
                        
                        <label style="display: flex; align-items: center; gap: 5px; color: #b3b3b3;">
                            <input type="checkbox" name="download_images" value="1" checked>
                            تحميل الصور محلياً
                        </label>
                        
                        <button type="submit" name="save_selected" class="btn btn-success">
                            <i class="fas fa-save"></i> حفظ المحدد
                        </button>
                        
                        <button type="button" class="btn btn-warning" onclick="selectAll()">
                            <i class="fas fa-check-double"></i> تحديد الكل
                        </button>
                        
                        <span style="color: #b3b3b3;">
                            <i class="fas fa-info-circle"></i>
                            تم العثور على <?php echo count($search_results); ?> نتيجة
                        </span>
                    </div>

                    <div class="results-grid">
                        <?php foreach ($search_results as $video): ?>
                        <div class="video-card <?php echo $video['exists'] ? 'exists' : ''; ?>" onclick="toggleSelect(this)">
                            <?php if ($video['exists']): ?>
                            <div class="exists-badge">موجود</div>
                            <?php endif; ?>
                            
                            <img src="<?php echo $video['thumbnail']; ?>" class="video-thumbnail" alt="">
                            
                            <div class="video-info">
                                <div class="video-title"><?php echo htmlspecialchars($video['title']); ?></div>
                                <div class="video-channel"><?php echo htmlspecialchars($video['channelTitle']); ?></div>
                                <span class="duration-badge"><?php echo $video['duration']; ?></span>
                            </div>
                            
                            <input type="checkbox" name="selected_videos[]" 
                                   value="<?php echo $video['videoId']; ?>" 
                                   class="video-select"
                                   <?php echo $video['exists'] ? 'disabled' : ''; ?>>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </form>
                <?php endif; ?>
            </div>

            <!-- المحتوى المحفوظ -->
            <div class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-database"></i> المحتوى المحفوظ</h2>
                    <a href="../free.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt"></i> عرض الصفحة العامة
                    </a>
                </div>

                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo ($_GET['filter'] ?? 'all') == 'all' ? 'active' : ''; ?>">الكل</a>
                    <a href="?filter=movies" class="filter-tab <?php echo ($_GET['filter'] ?? '') == 'movies' ? 'active' : ''; ?>">🎬 أفلام</a>
                    <a href="?filter=series" class="filter-tab <?php echo ($_GET['filter'] ?? '') == 'series' ? 'active' : ''; ?>">📺 مسلسلات</a>
                </div>

                <?php if (!empty($saved_content)): ?>
                <div class="saved-grid">
                    <?php foreach ($saved_content as $item): ?>
                    <div class="saved-item">
                        <img src="<?php echo !empty($item['local_thumbnail']) ? '../' . $item['local_thumbnail'] : $item['thumbnail']; ?>" 
                             class="saved-thumb" alt="">
                        <div class="saved-info">
                            <div class="saved-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span class="category-badge">
                                    <?php echo $item['category'] == 'movies' ? 'فيلم' : 'مسلسل'; ?>
                                </span>
                                <small style="color: #b3b3b3;"><?php echo $item['duration']; ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: #b3b3b3; padding: 40px;">
                    لا يوجد محتوى محفوظ بعد
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // تبديل بين تبويبات البحث
    function showSearchTab(tab) {
        // hide all sections
        ['keyword','url','playlist','channel'].forEach(function(t){
            var el = document.getElementById(t + '-search');
            if (el) el.style.display = 'none';
        });
        // show selected
        var activeSection = document.getElementById(tab + '-search');
        if (activeSection) activeSection.style.display = 'block';

        // update tab classes
        var tabs = document.querySelectorAll('.search-tab');
        tabs.forEach(function(el){
            el.classList.remove('active');
            if (el.getAttribute('onclick') && el.getAttribute('onclick').includes("'" + tab + "'")) {
                el.classList.add('active');
            }
        });
    }

    function toggleSelect(card) {
        let checkbox = card.querySelector('input[type="checkbox"]');
        if (checkbox && !checkbox.disabled) {
            checkbox.checked = !checkbox.checked;
            card.classList.toggle('selected', checkbox.checked);
        }
    }

    function selectAll() {
        let checkboxes = document.querySelectorAll('input[name="selected_videos[]"]:not(:disabled)');
        let cards = document.querySelectorAll('.video-card:not(.exists)');
        
        checkboxes.forEach((checkbox, index) => {
            checkbox.checked = true;
            if (cards[index]) cards[index].classList.add('selected');
        });
    }

    setTimeout(() => {
        let notification = document.querySelector('.notification');
        if (notification) {
            setTimeout(() => notification.remove(), 5000);
        }
    }, 100);
    </script>
    
</body>
</html>