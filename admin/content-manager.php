<?php
// admin/content-manager.php - مدير محتوى يوتيوب الشامل مع بحث متقدم وحذف شامل
define('ALLOW_ACCESS', true);

$base_path = 'C:/xampp/htdocs/fayez-movie';
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$youtube_api_key = 'AIzaSyApqaqvZDto7tpEQEWRYw3QVzguTxfnKcU';

// تعريف المتغيرات الأساسية
$video_data = null;
$playlist_data = null;
$playlist_videos = [];
$existing_movie = null;
$existing_series = null;
$search_results = [];
$search_query = '';
$search_type = 'videos';
$error = null;
$message = null;
$success = null;
$message_type = 'success';

// إنشاء مجلدات للصور
$upload_dirs = [
    'youtube' => $base_path . '/uploads/youtube/',
    'youtube_cast' => $base_path . '/uploads/youtube_cast/',
    'youtube_series' => $base_path . '/uploads/youtube_series/',
    'youtube_episodes' => $base_path . '/uploads/youtube_episodes/'
];

foreach ($upload_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// =============================================
// دوال مساعدة للبحث والجلب
// =============================================

function extractVideoId($url) {
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

function extractPlaylistId($url) {
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


function searchYouTubeVideos($query, $api_key, $max_results = 20) {
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=" . urlencode($query) . 
           "&type=video&maxResults=" . $max_results . "&relevanceLanguage=ar&videoDuration=long&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $results = [];
    
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            if (isset($item['id']['videoId'])) {
                $results[] = [
                    'videoId' => $item['id']['videoId'],
                    'title' => $item['snippet']['title'] ?? 'عنوان غير متوفر',
                    'description' => $item['snippet']['description'] ?? '',
                    'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? 'https://via.placeholder.com/200x120?text=No+Image',
                    'channelTitle' => $item['snippet']['channelTitle'] ?? 'قناة غير معروفة',
                    'publishedAt' => $item['snippet']['publishedAt'] ?? ''
                ];
            }
        }
    }
    
    return $results;
}

function searchYouTubePlaylists($query, $api_key, $max_results = 10) {
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&q=" . urlencode($query) . 
           "&type=playlist&maxResults=" . $max_results . "&relevanceLanguage=ar&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $results = [];
    
    if (isset($data['items']) && is_array($data['items'])) {
        foreach ($data['items'] as $item) {
            if (isset($item['id']['playlistId'])) {
                $results[] = [
                    'playlistId' => $item['id']['playlistId'],
                    'title' => $item['snippet']['title'] ?? 'عنوان غير متوفر',
                    'description' => $item['snippet']['description'] ?? '',
                    'thumbnail' => $item['snippet']['thumbnails']['high']['url'] ?? 'https://via.placeholder.com/200x120?text=No+Image',
                    'channelTitle' => $item['snippet']['channelTitle'] ?? 'قناة غير معروفة',
                    'publishedAt' => $item['snippet']['publishedAt'] ?? ''
                ];
            }
        }
    }
    
    return $results;
}

function getYouTubeVideoDetails($video_id, $api_key) {
    $url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,contentDetails,statistics&id=" . $video_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (!isset($data['items'][0])) {
        return false;
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
        'description' => $snippet['description'],
        'thumbnail' => $snippet['thumbnails']['maxres']['url'] ?? $snippet['thumbnails']['high']['url'],
        'channelTitle' => $snippet['channelTitle'],
        'channelId' => $snippet['channelId'],
        'publishedAt' => $snippet['publishedAt'],
        'duration' => $duration,
        'viewCount' => number_format($item['statistics']['viewCount'] ?? 0),
        'likeCount' => number_format($item['statistics']['likeCount'] ?? 0),
        'commentCount' => number_format($item['statistics']['commentCount'] ?? 0)
    ];
}

function getPlaylistDetails($playlist_id, $api_key) {
    $url = "https://www.googleapis.com/youtube/v3/playlists?part=snippet,contentDetails&id=" . $playlist_id . "&key=" . $api_key;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['items'][0])) {
        $item = $data['items'][0];
        return [
            'title' => $item['snippet']['title'],
            'description' => $item['snippet']['description'],
            'thumbnail' => $item['snippet']['thumbnails']['high']['url'],
            'channelTitle' => $item['snippet']['channelTitle'],
            'channelId' => $item['snippet']['channelId'],
            'videoCount' => $item['contentDetails']['itemCount'] ?? 0
        ];
    }
    
    return false;
}

function getPlaylistVideos($playlist_id, $api_key) {
    $videos = [];
    $next_page_token = '';
    
    do {
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
        
        if (!isset($data['items'])) break;
        
        $video_ids = [];
        foreach ($data['items'] as $item) {
            if (isset($item['snippet']['resourceId']['videoId'])) {
                $video_ids[] = $item['snippet']['resourceId']['videoId'];
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
                    $duration = $detail['contentDetails']['duration'];
                    $viewCount = $detail['statistics']['viewCount'] ?? 0;
                    
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
            
            foreach ($data['items'] as $index => $item) {
                $video_id = $item['snippet']['resourceId']['videoId'];
                $snippet = $item['snippet'];
                
                $videos[] = [
                    'videoId' => $video_id,
                    'title' => $snippet['title'],
                    'description' => $snippet['description'],
                    'thumbnail' => $snippet['thumbnails']['high']['url'],
                    'publishedAt' => $snippet['publishedAt'],
                    'position' => $index + 1,
                    'duration' => $details_map[$video_id]['duration'] ?? '00:00:00',
                    'viewCount' => $details_map[$video_id]['viewCount'] ?? '0'
                ];
            }
        }
        
        $next_page_token = $data['nextPageToken'] ?? '';
        
    } while (!empty($next_page_token));
    
    return $videos;
}

function downloadThumbnail($url, $name, $folder) {
    global $base_path, $upload_dirs;
    
    if (empty($url)) return '';
    
    $upload_dir = $upload_dirs[$folder] ?? $upload_dirs['youtube'];
    $filename = $folder . '_' . $name . '_' . time() . '.jpg';
    $filepath = $upload_dir . $filename;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    
    if (!empty($data)) {
        file_put_contents($filepath, $data);
        return 'uploads/' . $folder . '/' . $filename;
    }
    
    return $url;
}

// =============================================
// معالجة الحذف الشامل
// =============================================

// حذف جميع الأفلام
if (isset($_GET['delete_all_movies']) && $_GET['delete_all_movies'] == 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        try {
            $pdo->beginTransaction();
            
            // حذف جميع الصور المحلية للأفلام
            $movies = $pdo->query("SELECT local_thumbnail FROM youtube_movies WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($movies as $movie) {
                if (!empty($movie['local_thumbnail'])) {
                    $file = $base_path . '/' . $movie['local_thumbnail'];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            
            // حذف البيانات المرتبطة
            $pdo->exec("DELETE FROM youtube_movie_servers");
            $pdo->exec("DELETE FROM youtube_movie_downloads");
            $pdo->exec("DELETE FROM youtube_movie_cast");
            
            // حذف الأفلام
            $count = $pdo->exec("DELETE FROM youtube_movies");
            
            $pdo->commit();
            $message = "✅ تم حذف جميع الأفلام بنجاح ($count فيلم)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "⚠️ لم يتم الحذف. يجب تأكيد العملية";
        $message_type = "warning";
    }
}

// حذف جميع المسلسلات
if (isset($_GET['delete_all_series']) && $_GET['delete_all_series'] == 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        try {
            $pdo->beginTransaction();
            
            // حذف صور الحلقات
            $episodes = $pdo->query("SELECT local_thumbnail FROM youtube_episodes WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($episodes as $ep) {
                if (!empty($ep['local_thumbnail'])) {
                    $file = $base_path . '/' . $ep['local_thumbnail'];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            
            // حذف صور المسلسلات
            $series_list = $pdo->query("SELECT local_thumbnail FROM youtube_series WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($series_list as $ser) {
                if (!empty($ser['local_thumbnail'])) {
                    $file = $base_path . '/' . $ser['local_thumbnail'];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            
            // حذف الحلقات أولاً
            $pdo->exec("DELETE FROM youtube_episodes");
            
            // حذف المسلسلات
            $count = $pdo->exec("DELETE FROM youtube_series");
            
            $pdo->commit();
            $message = "✅ تم حذف جميع المسلسلات بنجاح ($count مسلسل)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "⚠️ لم يتم الحذف. يجب تأكيد العملية";
        $message_type = "warning";
    }
}

// حذف جميع الفيديوهات
if (isset($_GET['delete_all_videos']) && $_GET['delete_all_videos'] == 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        try {
            $pdo->beginTransaction();
            
            // حذف جميع الصور المحلية للفيديوهات
            $videos = $pdo->query("SELECT local_thumbnail FROM youtube_content WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($videos as $video) {
                if (!empty($video['local_thumbnail'])) {
                    $file = $base_path . '/' . $video['local_thumbnail'];
                    if (file_exists($file)) {
                        unlink($file);
                    }
                }
            }
            
            // حذف الفيديوهات
            $count = $pdo->exec("DELETE FROM youtube_content");
            
            $pdo->commit();
            $message = "✅ تم حذف جميع الفيديوهات بنجاح ($count فيديو)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "⚠️ لم يتم الحذف. يجب تأكيد العملية";
        $message_type = "warning";
    }
}

// حذف الكل
if (isset($_GET['delete_all']) && $_GET['delete_all'] == 1) {
    if (isset($_GET['confirm']) && $_GET['confirm'] == 'yes') {
        try {
            $pdo->beginTransaction();
            
            // حذف صور الأفلام
            $movies = $pdo->query("SELECT local_thumbnail FROM youtube_movies WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($movies as $movie) {
                if (!empty($movie['local_thumbnail'])) {
                    $file = $base_path . '/' . $movie['local_thumbnail'];
                    if (file_exists($file)) unlink($file);
                }
            }
            
            // حذف صور الحلقات
            $episodes = $pdo->query("SELECT local_thumbnail FROM youtube_episodes WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($episodes as $ep) {
                if (!empty($ep['local_thumbnail'])) {
                    $file = $base_path . '/' . $ep['local_thumbnail'];
                    if (file_exists($file)) unlink($file);
                }
            }
            
            // حذف صور المسلسلات
            $series_list = $pdo->query("SELECT local_thumbnail FROM youtube_series WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($series_list as $ser) {
                if (!empty($ser['local_thumbnail'])) {
                    $file = $base_path . '/' . $ser['local_thumbnail'];
                    if (file_exists($file)) unlink($file);
                }
            }
            
            // حذف صور الفيديوهات
            $videos = $pdo->query("SELECT local_thumbnail FROM youtube_content WHERE local_thumbnail IS NOT NULL AND local_thumbnail != ''")->fetchAll();
            foreach ($videos as $video) {
                if (!empty($video['local_thumbnail'])) {
                    $file = $base_path . '/' . $video['local_thumbnail'];
                    if (file_exists($file)) unlink($file);
                }
            }
            
            // حذف جميع البيانات
            $pdo->exec("DELETE FROM youtube_movie_servers");
            $pdo->exec("DELETE FROM youtube_movie_downloads");
            $pdo->exec("DELETE FROM youtube_movie_cast");
            $pdo->exec("DELETE FROM youtube_episodes");
            
            $movies_count = $pdo->exec("DELETE FROM youtube_movies");
            $series_count = $pdo->exec("DELETE FROM youtube_series");
            $videos_count = $pdo->exec("DELETE FROM youtube_content");
            
            $pdo->commit();
            $message = "✅ تم حذف كل المحتوى بنجاح ($movies_count فيلم، $series_count مسلسل، $videos_count فيديو)";
            $message_type = "success";
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "⚠️ لم يتم الحذف. يجب تأكيد العملية";
        $message_type = "warning";
    }
}

// =============================================
// معالجة الحذف الفردي
// =============================================

if (isset($_GET['delete'])) {
    $type = $_GET['type'] ?? '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id <= 0) {
        $message = "❌ معرف غير صالح";
        $message_type = "error";
    } else {
        try {
            $pdo->beginTransaction();
            
            switch($type) {
                case 'movie':
                    $check = $pdo->prepare("SELECT id, local_thumbnail FROM youtube_movies WHERE id = ?");
                    $check->execute([$id]);
                    $item = $check->fetch();
                    
                    if ($item) {
                        if (!empty($item['local_thumbnail'])) {
                            $file = $base_path . '/' . $item['local_thumbnail'];
                            if (file_exists($file)) unlink($file);
                        }
                        
                        $pdo->prepare("DELETE FROM youtube_movie_servers WHERE movie_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM youtube_movie_downloads WHERE movie_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM youtube_movie_cast WHERE movie_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM youtube_movies WHERE id = ?")->execute([$id]);
                        
                        $message = "✅ تم حذف الفيلم بنجاح";
                    } else {
                        $message = "❌ الفيلم غير موجود";
                    }
                    break;
                    
                case 'series':
                    $check_series = $pdo->prepare("SELECT id, local_thumbnail FROM youtube_series WHERE id = ?");
                    $check_series->execute([$id]);
                    $series_item = $check_series->fetch();
                    
                    if ($series_item) {
                        $eps = $pdo->prepare("SELECT local_thumbnail FROM youtube_episodes WHERE series_id = ?");
                        $eps->execute([$id]);
                        while ($ep = $eps->fetch()) {
                            if (!empty($ep['local_thumbnail'])) {
                                $file = $base_path . '/' . $ep['local_thumbnail'];
                                if (file_exists($file)) unlink($file);
                            }
                        }
                        
                        if (!empty($series_item['local_thumbnail'])) {
                            $file = $base_path . '/' . $series_item['local_thumbnail'];
                            if (file_exists($file)) unlink($file);
                        }
                        
                        $pdo->prepare("DELETE FROM youtube_episodes WHERE series_id = ?")->execute([$id]);
                        $pdo->prepare("DELETE FROM youtube_series WHERE id = ?")->execute([$id]);
                        
                        $message = "✅ تم حذف المسلسل وجميع حلقاته";
                    } else {
                        $message = "❌ المسلسل غير موجود";
                    }
                    break;
                    
                case 'video':
                    $check_video = $pdo->prepare("SELECT id, local_thumbnail FROM youtube_content WHERE id = ?");
                    $check_video->execute([$id]);
                    $video_item = $check_video->fetch();
                    
                    if ($video_item) {
                        if (!empty($video_item['local_thumbnail'])) {
                            $file = $base_path . '/' . $video_item['local_thumbnail'];
                            if (file_exists($file)) unlink($file);
                        }
                        
                        $pdo->prepare("DELETE FROM youtube_content WHERE id = ?")->execute([$id]);
                        
                        $message = "✅ تم حذف الفيديو بنجاح";
                    } else {
                        $message = "❌ الفيديو غير موجود";
                    }
                    break;
                    
                default:
                    $message = "❌ نوع غير معروف: " . htmlspecialchars($type);
                    $message_type = "error";
                    break;
            }
            
            $pdo->commit();
            $message_type = "success";
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "❌ خطأ: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// معالجة تحديث الحالة
if (isset($_POST['update_status'])) {
    $type = $_POST['item_type'];
    $id = (int)$_POST['item_id'];
    $status = (int)$_POST['status'];
    
    $table = ($type == 'movie') ? 'youtube_movies' : (($type == 'series') ? 'youtube_series' : 'youtube_content');
    
    if ($table) {
        $pdo->prepare("UPDATE $table SET status = ? WHERE id = ?")->execute([$status, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// معالجة تحديث التصنيف
if (isset($_POST['update_category'])) {
    $type = $_POST['item_type'];
    $id = (int)$_POST['item_id'];
    $category = $_POST['category'];
    
    $table = ($type == 'movie') ? 'youtube_movies' : (($type == 'series') ? 'youtube_series' : 'youtube_content');
    
    if ($table) {
        $pdo->prepare("UPDATE $table SET category = ? WHERE id = ?")->execute([$category, $id]);
        echo json_encode(['success' => true]);
        exit;
    }
}

// =============================================
// معالجة عمليات البحث والجلب
// =============================================

// البحث بالكلمات
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_youtube'])) {
    $search_query = $_POST['search_query'] ?? '';
    $search_type = $_POST['search_type'] ?? 'videos';
    $max_results = (int)($_POST['max_results'] ?? 20);
    
    if (!empty($search_query)) {
        if ($search_type == 'videos') {
            $search_results = searchYouTubeVideos($search_query, $youtube_api_key, $max_results);
        } else {
            $search_results = searchYouTubePlaylists($search_query, $youtube_api_key, $max_results);
        }
    }
}

// جلب فيديو بالرابط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_movie'])) {
    $video_url = $_POST['video_url'] ?? '';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($video_url)) {
        $video_id = extractVideoId($video_url);
        
        if (!$video_id) {
            $error = "رابط يوتيوب غير صحيح";
        } else {
            $check = $pdo->prepare("SELECT * FROM youtube_movies WHERE video_id = ?");
            $check->execute([$video_id]);
            $existing_movie = $check->fetch();
            
            $video_details = getYouTubeVideoDetails($video_id, $youtube_api_key);
            
            if ($video_details) {
                $video_data = $video_details;
                $_SESSION['movie_data'] = $video_data;
            } else {
                $error = "لم يتم العثور على الفيديو";
            }
        }
    }
}

// جلب مسلسل بالرابط
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_series'])) {
    $playlist_url = $_POST['playlist_url'] ?? '';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($playlist_url)) {
        $playlist_id = extractPlaylistId($playlist_url);
        
        if (!$playlist_id) {
            $error = "رابط قائمة تشغيل غير صحيح";
        } else {
            $check = $pdo->prepare("SELECT * FROM youtube_series WHERE playlist_id = ?");
            $check->execute([$playlist_id]);
            $existing_series = $check->fetch();
            
            $playlist_details = getPlaylistDetails($playlist_id, $youtube_api_key);
            
            if ($playlist_details) {
                $playlist_data = [
                    'playlist_id' => $playlist_id,
                    'title' => $playlist_details['title'],
                    'description' => $playlist_details['description'],
                    'thumbnail' => $playlist_details['thumbnail'],
                    'channelTitle' => $playlist_details['channelTitle'],
                    'channelId' => $playlist_details['channelId'],
                    'videoCount' => $playlist_details['videoCount']
                ];
                
                $playlist_videos = getPlaylistVideos($playlist_id, $youtube_api_key);
                
                $_SESSION['playlist_data'] = $playlist_data;
                $_SESSION['playlist_videos'] = $playlist_videos;
            } else {
                $error = "لم يتم العثور على القائمة";
            }
        }
    }
}

// اختيار نتيجة من البحث
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_search_result'])) {
    $selected_type = $_POST['selected_type'] ?? '';
    $selected_id = $_POST['selected_id'] ?? '';
    
    if ($selected_type == 'video') {
        $check = $pdo->prepare("SELECT * FROM youtube_movies WHERE video_id = ?");
        $check->execute([$selected_id]);
        $existing_movie = $check->fetch();
        
        $video_details = getYouTubeVideoDetails($selected_id, $youtube_api_key);
        
        if ($video_details) {
            $video_data = $video_details;
            $_SESSION['movie_data'] = $video_data;
        }
    } else {
        $check = $pdo->prepare("SELECT * FROM youtube_series WHERE playlist_id = ?");
        $check->execute([$selected_id]);
        $existing_series = $check->fetch();
        
        $playlist_details = getPlaylistDetails($selected_id, $youtube_api_key);
        
        if ($playlist_details) {
            $playlist_data = [
                'playlist_id' => $selected_id,
                'title' => $playlist_details['title'],
                'description' => $playlist_details['description'],
                'thumbnail' => $playlist_details['thumbnail'],
                'channelTitle' => $playlist_details['channelTitle'],
                'channelId' => $playlist_details['channelId'],
                'videoCount' => $playlist_details['videoCount']
            ];
            
            $playlist_videos = getPlaylistVideos($selected_id, $youtube_api_key);
            
            $_SESSION['playlist_data'] = $playlist_data;
            $_SESSION['playlist_videos'] = $playlist_videos;
        }
    }
}

// حفظ الفيلم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_movie'])) {
    $video_id = $_POST['video_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $title_en = $_POST['title_en'] ?? '';
    $description = $_POST['description'] ?? '';
    $year = $_POST['year'] ?? date('Y');
    $country = $_POST['country'] ?? '';
    $language = $_POST['language'] ?? 'ar';
    $genre = $_POST['genre'] ?? '';
    $imdb_rating = (float)($_POST['imdb_rating'] ?? 0);
    $category = $_POST['category'] ?? 'movies';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($video_id) && isset($_SESSION['movie_data'])) {
        $video_data = $_SESSION['movie_data'];
        
        try {
            $pdo->beginTransaction();
            
            $local_thumbnail = '';
            if ($download_images) {
                $local_thumbnail = downloadThumbnail($video_data['thumbnail'], $video_id, 'youtube');
            }
            
            $check = $pdo->prepare("SELECT id FROM youtube_movies WHERE video_id = ?");
            $check->execute([$video_id]);
            
            if ($check->rowCount() > 0) {
                $update = $pdo->prepare("
                    UPDATE youtube_movies SET 
                        title = ?, title_en = ?, description = ?, thumbnail = ?,
                        channel_title = ?, duration = ?, view_count = ?, published_at = ?,
                        year = ?, country = ?, language = ?, genre = ?, imdb_rating = ?,
                        category = ?, local_thumbnail = ?
                    WHERE video_id = ?
                ");
                
                $update->execute([
                    $title, $title_en, $description, $video_data['thumbnail'],
                    $video_data['channelTitle'], $video_data['duration'], $video_data['viewCount'],
                    date('Y-m-d H:i:s', strtotime($video_data['publishedAt'])),
                    $year, $country, $language, $genre, $imdb_rating,
                    $category, $local_thumbnail, $video_id
                ]);
                
                $message = "✅ تم تحديث الفيلم بنجاح";
            } else {
                $insert = $pdo->prepare("
                    INSERT INTO youtube_movies (
                        video_id, title, title_en, description, thumbnail,
                        channel_title, duration, view_count, published_at,
                        year, country, language, genre, imdb_rating,
                        category, local_thumbnail, added_by
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $insert->execute([
                    $video_id, $title, $title_en, $description, $video_data['thumbnail'],
                    $video_data['channelTitle'], $video_data['duration'], $video_data['viewCount'],
                    date('Y-m-d H:i:s', strtotime($video_data['publishedAt'])),
                    $year, $country, $language, $genre, $imdb_rating,
                    $category, $local_thumbnail, $_SESSION['username'] ?? 'admin'
                ]);
                
                $message = "✅ تم إضافة الفيلم بنجاح";
            }
            
            $pdo->commit();
            unset($_SESSION['movie_data']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

// حفظ المسلسل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_series'])) {
    $playlist_id = $_POST['playlist_id'] ?? '';
    $series_title = $_POST['series_title'] ?? '';
    $series_description = $_POST['series_description'] ?? '';
    $series_category = $_POST['series_category'] ?? 'series';
    $download_images = isset($_POST['download_images']) ? true : false;
    
    if (!empty($playlist_id) && isset($_SESSION['playlist_data']) && isset($_SESSION['playlist_videos'])) {
        $playlist_data = $_SESSION['playlist_data'];
        $playlist_videos = $_SESSION['playlist_videos'];
        
        try {
            $pdo->beginTransaction();
            
            $local_thumbnail = '';
            if ($download_images) {
                $local_thumbnail = downloadThumbnail($playlist_data['thumbnail'], 'series_' . $playlist_id, 'youtube_series');
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
                $playlist_id,
                $series_title ?: $playlist_data['title'],
                $series_description ?: $playlist_data['description'],
                $playlist_data['thumbnail'],
                $playlist_data['channelTitle'],
                $playlist_data['channelId'],
                count($playlist_videos),
                $series_category,
                $local_thumbnail,
                $_SESSION['username'] ?? 'admin'
            ]);
            
            $series_id = $pdo->lastInsertId();
            if (!$series_id) {
                $get_id = $pdo->prepare("SELECT id FROM youtube_series WHERE playlist_id = ?");
                $get_id->execute([$playlist_id]);
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
            foreach ($playlist_videos as $index => $video) {
                $episode_thumbnail = '';
                if ($download_images) {
                    $episode_thumbnail = downloadThumbnail($video['thumbnail'], 'ep_' . $video['videoId'], 'youtube_episodes');
                }
                
                $insert_episode->execute([
                    $series_id,
                    $video['videoId'],
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
            
            $pdo->commit();
            
            $success = "✅ تم حفظ المسلسل بنجاح مع $episode_count حلقة";
            
            unset($_SESSION['playlist_data']);
            unset($_SESSION['playlist_videos']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "❌ خطأ: " . $e->getMessage();
        }
    }
}

// الفلاتر والبحث في المحتوى المحفوظ
$filter_type = $_GET['filter_type'] ?? 'all';
$filter_category = $_GET['filter_category'] ?? 'all';
$search = $_GET['search'] ?? '';

// جلب الأفلام (youtube_movies)
$movies = [];
if ($filter_type == 'all' || $filter_type == 'movies') {
    $sql = "SELECT 
                id, 
                title, 
                thumbnail, 
                local_thumbnail,
                'movie' as content_type, 
                created_at,
                channel_title,
                duration,
                view_count,
                category,
                status
            FROM youtube_movies
            WHERE 1=1";
    
    if ($filter_category != 'all') {
        $sql .= " AND category = '" . $pdo->quote($filter_category) . "'";
    }
    
    if ($search) {
        $sql .= " AND (title LIKE '%$search%' OR channel_title LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY id DESC LIMIT 50";
    $movies = $pdo->query($sql)->fetchAll();
}

// جلب المسلسلات (youtube_series)
$series = [];
if ($filter_type == 'all' || $filter_type == 'series') {
    $sql = "SELECT 
                s.id, 
                s.title, 
                s.thumbnail, 
                s.local_thumbnail,
                'series' as content_type, 
                s.created_at,
                s.channel_title,
                s.category,
                s.status,
                s.video_count,
                (SELECT COUNT(*) FROM youtube_episodes WHERE series_id = s.id) as total_episodes
            FROM youtube_series s
            WHERE 1=1";
    
    if ($filter_category != 'all') {
        $sql .= " AND s.category = '" . $pdo->quote($filter_category) . "'";
    }
    
    if ($search) {
        $sql .= " AND (s.title LIKE '%$search%' OR s.channel_title LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY s.id DESC LIMIT 50";
    $series = $pdo->query($sql)->fetchAll();
}

// جلب الفيديوهات (youtube_content)
$videos = [];
if ($filter_type == 'all' || $filter_type == 'videos') {
    $sql = "SELECT 
                id, 
                title, 
                thumbnail, 
                local_thumbnail,
                'video' as content_type, 
                created_at,
                channel_title,
                duration,
                view_count,
                category,
                status
            FROM youtube_content
            WHERE 1=1";
    
    if ($filter_category != 'all') {
        $sql .= " AND category = '" . $pdo->quote($filter_category) . "'";
    }
    
    if ($search) {
        $sql .= " AND (title LIKE '%$search%' OR channel_title LIKE '%$search%')";
    }
    
    $sql .= " ORDER BY id DESC LIMIT 50";
    $videos = $pdo->query($sql)->fetchAll();
}

// دمج كل المحتوى
$all_items = array_merge($movies, $series, $videos);

// ترتيب حسب التاريخ
usort($all_items, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// إحصائيات
$stats = [
    'movies' => $pdo->query("SELECT COUNT(*) FROM youtube_movies")->fetchColumn() ?: 0,
    'series' => $pdo->query("SELECT COUNT(*) FROM youtube_series")->fetchColumn() ?: 0,
    'videos' => $pdo->query("SELECT COUNT(*) FROM youtube_content")->fetchColumn() ?: 0,
    'episodes' => $pdo->query("SELECT SUM(video_count) FROM youtube_series")->fetchColumn() ?: 0,
];

$categories = [
    'movies' => ['name' => 'أفلام', 'icon' => '🎬', 'color' => '#e50914'],
    'series' => ['name' => 'مسلسلات', 'icon' => '📺', 'color' => '#1a4b8c'],
    'general' => ['name' => 'عام', 'icon' => '🎥', 'color' => '#27ae60']
];

// دالة مساعدة لعرض الصورة
function getImageUrl($item) {
    if (!empty($item['local_thumbnail'])) {
        return '/fayez-movie/' . $item['local_thumbnail'];
    } elseif (!empty($item['thumbnail'])) {
        return $item['thumbnail'];
    }
    return 'https://via.placeholder.com/300x160?text=No+Image';
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📺 مدير محتوى يوتيوب الشامل - ويزي برو</title>
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
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 800;
            color: #e50914;
        }

        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
            margin-top: 5px;
        }

        .search-main-section {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }

        .search-main-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e50914;
        }

        .search-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            flex-wrap: wrap;
        }

        .search-tab {
            padding: 10px 25px;
            border-radius: 30px;
            background: #252525;
            color: #b3b3b3;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .search-tab:hover,
        .search-tab.active {
            background: #e50914;
            color: white;
        }

        .search-content {
            display: none;
        }

        .search-content.active {
            display: block;
        }

        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            padding: 12px 15px;
            background: #252525;
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

        .btn-info {
            background: #3498db;
            color: white;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding: 10px;
            margin-top: 20px;
        }

        .result-card {
            background: #252525;
            border-radius: 10px;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #333;
        }

        .result-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .result-thumb {
            width: 100%;
            height: 120px;
            object-fit: cover;
        }

        .result-info {
            padding: 10px;
        }

        .result-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 5px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .result-channel {
            color: #b3b3b3;
            font-size: 11px;
        }

        .add-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .add-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }

        .add-tab {
            padding: 10px 25px;
            border-radius: 30px;
            background: #252525;
            color: #b3b3b3;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-tab:hover,
        .add-tab.active {
            background: #e50914;
            color: white;
        }

        .add-content {
            display: none;
        }

        .add-content.active {
            display: block;
        }

        .filter-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .filter-tab {
            padding: 8px 20px;
            border-radius: 30px;
            background: #252525;
            color: #b3b3b3;
            text-decoration: none;
            transition: all 0.3s;
            border: 1px solid #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-tab:hover,
        .filter-tab.active {
            background: #e50914;
            color: white;
        }

        .delete-all-section {
            background: linear-gradient(145deg, #2a1a1a, #251515);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #dc3545;
        }

        .delete-all-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #dc3545;
        }

        .delete-all-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .item-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #333;
            transition: all 0.3s;
            position: relative;
        }

        .item-card:hover {
            transform: translateY(-5px);
            border-color: #e50914;
        }

        .item-header {
            position: relative;
        }

        .item-thumb {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #252525;
        }

        .item-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
        }

        .badge-movie {
            background: #ff0000;
            color: white;
        }

        .badge-series {
            background: #9b59b6;
            color: white;
        }
        
        .badge-video {
            background: #27ae60;
            color: white;
        }

        .category-tag {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            z-index: 2;
        }

        .tag-movies {
            background: #e50914;
            color: white;
        }

        .tag-series {
            background: #1a4b8c;
            color: white;
        }

        .tag-general {
            background: #27ae60;
            color: white;
        }

        .item-actions {
            position: absolute;
            bottom: 10px;
            right: 10px;
            display: flex;
            gap: 5px;
            z-index: 2;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .item-card:hover .item-actions {
            opacity: 1;
        }

        .action-btn {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            background: rgba(0,0,0,0.7);
            color: white;
            border: 1px solid #333;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .action-btn:hover {
            background: #e50914;
            border-color: #e50914;
        }

        .action-btn.delete:hover {
            background: #dc3545;
        }

        .item-info {
            padding: 15px;
        }

        .item-title {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 45px;
        }

        .item-meta {
            display: flex;
            gap: 15px;
            color: #b3b3b3;
            font-size: 12px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .status-select {
            background: #252525;
            border: 1px solid #333;
            border-radius: 6px;
            padding: 4px 8px;
            color: #fff;
            font-size: 11px;
            cursor: pointer;
            width: 100px;
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
            z-index: 9999;
            animation: slideDown 0.5s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .notification.error {
            background: #e50914;
        }

        .notification.warning {
            background: #f39c12;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        .preview-section {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #e50914;
        }

        .preview-grid {
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 20px;
        }

        .preview-thumb {
            width: 100%;
            border-radius: 8px;
        }

        .preview-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .preview-title {
            font-size: 20px;
            font-weight: 700;
            color: #e50914;
        }

        .episodes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }

        .episode-card {
            background: #252525;
            border-radius: 8px;
            padding: 10px;
            border: 1px solid #333;
        }

        .warning-box {
            background: rgba(220,53,69,0.1);
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #dc3545;
        }

        .confirm-checkbox {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
            color: #b3b3b3;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                position: static;
            }
            .preview-grid {
                grid-template-columns: 1fr;
            }
            .delete-all-buttons {
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
            <?php if (isset($error)): ?>
            <div class="notification error">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($message)): ?>
            <div class="notification <?php echo $message_type; ?>">
                <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : ($message_type == 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
            <div class="notification">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- إحصائيات -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['movies']; ?></div>
                    <div class="stat-label">🎬 أفلام</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['series']; ?></div>
                    <div class="stat-label">📺 مسلسلات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['videos']; ?></div>
                    <div class="stat-label">▶️ فيديوهات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['episodes']; ?></div>
                    <div class="stat-label">📋 حلقات</div>
                </div>
            </div>

            <!-- قسم الحذف الشامل -->
            <div class="delete-all-section">
                <div class="delete-all-title">
                    <i class="fas fa-exclamation-triangle"></i>
                    أدوات الحذف الشامل
                </div>
                
                <div class="delete-all-buttons">
                    <a href="?delete_all_movies=1&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('⚠️ هل أنت متأكد من حذف جميع الأفلام؟\nلا يمكن التراجع عن هذا الإجراء!')">
                        <i class="fas fa-trash"></i> حذف جميع الأفلام (<?php echo $stats['movies']; ?>)
                    </a>
                    
                    <a href="?delete_all_series=1&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('⚠️ هل أنت متأكد من حذف جميع المسلسلات؟\nلا يمكن التراجع عن هذا الإجراء!')">
                        <i class="fas fa-trash"></i> حذف جميع المسلسلات (<?php echo $stats['series']; ?>)
                    </a>
                    
                    <a href="?delete_all_videos=1&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('⚠️ هل أنت متأكد من حذف جميع الفيديوهات؟\nلا يمكن التراجع عن هذا الإجراء!')">
                        <i class="fas fa-trash"></i> حذف جميع الفيديوهات (<?php echo $stats['videos']; ?>)
                    </a>
                    
                    <a href="?delete_all=1&confirm=yes" class="btn btn-danger" 
                       onclick="return confirm('⚠️ تحذير شديد! هل أنت متأكد من حذف كل المحتوى بالكامل؟\nسيتم حذف جميع الأفلام والمسلسلات والفيديوهات نهائياً!')">
                        <i class="fas fa-trash"></i> حذف الكل (<?php echo $stats['movies'] + $stats['series'] + $stats['videos']; ?>)
                    </a>
                </div>
                
                <div class="warning-box" style="margin-top: 15px;">
                    <i class="fas fa-info-circle"></i>
                    ملاحظة: عمليات الحذف الشامل لا يمكن التراجع عنها. سيتم حذف جميع الصور المحلية أيضاً.
                </div>
            </div>

            <!-- قسم البحث المتقدم -->
            <div class="search-main-section">
                <div class="search-main-title">
                    <i class="fas fa-search"></i>
                    بحث متقدم في يوتيوب
                </div>

                <div class="search-tabs">
                    <div class="search-tab active" onclick="showSearchTab('videos')">🎬 بحث عن أفلام</div>
                    <div class="search-tab" onclick="showSearchTab('playlists')">📺 بحث عن مسلسلات</div>
                    <div class="search-tab" onclick="showSearchTab('url-video')">🔗 رابط فيديو</div>
                    <div class="search-tab" onclick="showSearchTab('url-playlist')">📋 رابط قائمة</div>
                </div>

                <!-- بحث بالكلمات - فيديوهات -->
                <div id="search-videos" class="search-content active">
                    <form method="POST">
                        <input type="hidden" name="search_type" value="videos">
                        <div class="search-box">
                            <input type="text" name="search_query" class="search-input" 
                                   placeholder="ابحث عن فيلم..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                            
                            <input type="number" name="max_results" class="search-input" 
                                   style="width: 100px;" placeholder="العدد" value="20" min="1" max="50">
                                   
                            <button type="submit" name="search_youtube" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>

                <!-- بحث بالكلمات - قوائم تشغيل -->
                <div id="search-playlists" class="search-content">
                    <form method="POST">
                        <input type="hidden" name="search_type" value="playlists">
                        <div class="search-box">
                            <input type="text" name="search_query" class="search-input" 
                                   placeholder="ابحث عن مسلسل..." value="<?php echo htmlspecialchars($search_query); ?>" required>
                            
                            <input type="number" name="max_results" class="search-input" 
                                   style="width: 100px;" placeholder="العدد" value="10" min="1" max="20">
                                   
                            <button type="submit" name="search_youtube" class="btn btn-primary">
                                <i class="fas fa-search"></i> بحث
                            </button>
                        </div>
                    </form>
                </div>

                <!-- رابط فيديو مباشر -->
                <div id="search-url-video" class="search-content">
                    <form method="POST">
                        <div class="search-box">
                            <input type="url" name="video_url" class="search-input" 
                                   placeholder="أدخل رابط فيديو يوتيوب (https://www.youtube.com/watch?v=...)" required>
                            
                            <label style="display: flex; align-items: center; gap: 5px; color: #b3b3b3;">
                                <input type="checkbox" name="download_images" value="1" checked>
                                تحميل الصورة
                            </label>
                            
                            <button type="submit" name="fetch_movie" class="btn btn-info">
                                <i class="fas fa-link"></i> جلب الفيلم
                            </button>
                        </div>
                    </form>
                </div>

                <!-- رابط قائمة تشغيل مباشر -->
                <div id="search-url-playlist" class="search-content">
                    <form method="POST">
                        <div class="search-box">
                            <input type="url" name="playlist_url" class="search-input" 
                                   placeholder="أدخل رابط قائمة تشغيل (https://www.youtube.com/playlist?list=...)" required>
                            
                            <label style="display: flex; align-items: center; gap: 5px; color: #b3b3b3;">
                                <input type="checkbox" name="download_images" value="1" checked>
                                تحميل الصور
                            </label>
                            
                            <button type="submit" name="fetch_series" class="btn btn-info">
                                <i class="fas fa-link"></i> جلب المسلسل
                            </button>
                        </div>
                    </form>
                </div>

                <!-- نتائج البحث -->
                <?php if (!empty($search_results)): ?>
                <div style="margin-top: 30px;">
                    <h3 style="color: #e50914; margin-bottom: 15px;">
                        نتائج البحث (<?php echo count($search_results); ?>)
                    </h3>
                    
                    <form method="POST" id="select-result-form">
                        <input type="hidden" name="select_search_result" value="1">
                        <input type="hidden" name="selected_type" id="selected_type" value="">
                        <input type="hidden" name="selected_id" id="selected_id" value="">
                    </form>
                    
                    <div class="results-grid">
                        <?php foreach ($search_results as $result): 
                            $result_id = '';
                            $result_type = '';
                            
                            if (isset($result['videoId'])) {
                                $result_id = $result['videoId'];
                                $result_type = 'video';
                            } elseif (isset($result['playlistId'])) {
                                $result_id = $result['playlistId'];
                                $result_type = 'playlist';
                            } else {
                                continue;
                            }
                            
                            $result_title = $result['title'] ?? 'عنوان غير متوفر';
                            $result_channel = $result['channelTitle'] ?? 'قناة غير معروفة';
                            $result_thumbnail = $result['thumbnail'] ?? 'https://via.placeholder.com/200x120?text=No+Image';
                        ?>
                        <div class="result-card" onclick="selectResult('<?php echo $result_type; ?>', '<?php echo $result_id; ?>')">
                            <img src="<?php echo $result_thumbnail; ?>" class="result-thumb" alt="">
                            <div class="result-info">
                                <div class="result-title"><?php echo htmlspecialchars($result_title); ?></div>
                                <div class="result-channel"><?php echo htmlspecialchars($result_channel); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- إضافة محتوى جديد (يظهر عند اختيار نتيجة) -->
            <?php if (isset($video_data) || isset($playlist_data)): ?>
            <div class="add-section">
                <div class="add-tabs">
                    <div class="add-tab <?php echo isset($video_data) ? 'active' : ''; ?>">🎬 إضافة فيلم</div>
                    <div class="add-tab <?php echo isset($playlist_data) ? 'active' : ''; ?>">📺 إضافة مسلسل</div>
                </div>

                <?php if (isset($video_data)): ?>
                <div id="add-movie" class="add-content" style="display: <?php echo isset($video_data) ? 'block' : 'none'; ?>;">
                    <div class="preview-section">
                        <form method="POST">
                            <input type="hidden" name="video_id" value="<?php echo $video_data['videoId']; ?>">
                            
                            <div class="preview-grid">
                                <div>
                                    <img src="<?php echo $video_data['thumbnail']; ?>" class="preview-thumb" alt="">
                                </div>
                                <div class="preview-details">
                                    <div class="preview-title"><?php echo htmlspecialchars($video_data['title']); ?></div>
                                    
                                    <div style="display: flex; gap: 15px; color: #b3b3b3; flex-wrap: wrap;">
                                        <span><i class="fas fa-clock"></i> <?php echo $video_data['duration']; ?></span>
                                        <span><i class="fas fa-eye"></i> <?php echo $video_data['viewCount']; ?></span>
                                        <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($video_data['channelTitle']); ?></span>
                                    </div>

                                    <?php if (isset($existing_movie) && $existing_movie): ?>
                                    <div style="background: #ffc107; color: #000; padding: 10px; border-radius: 5px;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        هذا الفيلم موجود مسبقاً! سيتم تحديثه.
                                    </div>
                                    <?php endif; ?>

                                    <div style="margin-top: 15px;">
                                        <label>عنوان الفيلم:</label>
                                        <input type="text" name="title" class="search-input" value="<?php echo htmlspecialchars($video_data['title']); ?>">
                                        
                                        <label style="margin-top: 10px; display: block;">العنوان الأصلي:</label>
                                        <input type="text" name="title_en" class="search-input" value="<?php echo htmlspecialchars($video_data['title']); ?>">
                                        
                                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 10px;">
                                            <div>
                                                <label>سنة الإنتاج:</label>
                                                <input type="number" name="year" class="search-input" value="<?php echo date('Y'); ?>">
                                            </div>
                                            <div>
                                                <label>البلد:</label>
                                                <input type="text" name="country" class="search-input" value="">
                                            </div>
                                            <div>
                                                <label>اللغة:</label>
                                                <select name="language" class="search-input">
                                                    <option value="ar">العربية</option>
                                                    <option value="en">الإنجليزية</option>
                                                    <option value="tr">التركية</option>
                                                    <option value="hi">الهندية</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label>التقييم:</label>
                                                <input type="number" step="0.1" name="imdb_rating" class="search-input" value="0">
                                            </div>
                                        </div>
                                        
                                        <label style="margin-top: 10px; display: block;">التصنيفات (مفصولة بفواصل):</label>
                                        <input type="text" name="genre" class="search-input" placeholder="دراما، أكشن، كوميديا">
                                        
                                        <label style="margin-top: 10px; display: block;">تصنيف المحتوى:</label>
                                        <select name="category" class="search-input" style="width: 200px;">
                                            <option value="movies">🎬 فيلم</option>
                                            <option value="series">📺 مسلسل</option>
                                            <option value="general">🎥 عام</option>
                                        </select>
                                        
                                        <label style="margin-top: 10px; display: flex; align-items: center; gap: 5px;">
                                            <input type="checkbox" name="download_images" value="1" checked>
                                            تحميل الصورة محلياً
                                        </label>
                                    </div>

                                    <button type="submit" name="save_movie" class="btn btn-success" style="margin-top: 20px;">
                                        <i class="fas fa-save"></i> حفظ الفيلم
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (isset($playlist_data) && !empty($playlist_videos)): ?>
                <div id="add-series" class="add-content" style="display: <?php echo isset($playlist_data) ? 'block' : 'none'; ?>;">
                    <div class="preview-section">
                        <form method="POST">
                            <input type="hidden" name="playlist_id" value="<?php echo $playlist_data['playlist_id']; ?>">
                            
                            <div class="preview-grid">
                                <div>
                                    <img src="<?php echo $playlist_data['thumbnail']; ?>" class="preview-thumb" alt="">
                                </div>
                                <div class="preview-details">
                                    <div class="preview-title"><?php echo htmlspecialchars($playlist_data['title']); ?></div>
                                    
                                    <div style="color: #b3b3b3;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($playlist_data['channelTitle']); ?><br>
                                        <i class="fas fa-list"></i> عدد الحلقات: <?php echo count($playlist_videos); ?>
                                    </div>

                                    <?php if (isset($existing_series) && $existing_series): ?>
                                    <div style="background: #ffc107; color: #000; padding: 10px; border-radius: 5px;">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        هذا المسلسل موجود مسبقاً! سيتم تحديثه.
                                    </div>
                                    <?php endif; ?>

                                    <div style="margin-top: 15px;">
                                        <label>عنوان المسلسل:</label>
                                        <input type="text" name="series_title" class="search-input" value="<?php echo htmlspecialchars($playlist_data['title']); ?>">
                                        
                                        <label style="margin-top: 10px; display: block;">التصنيف:</label>
                                        <select name="series_category" class="search-input" style="width: 200px;">
                                            <option value="series">📺 مسلسل</option>
                                            <option value="movies">🎬 أفلام</option>
                                            <option value="general">🎥 عام</option>
                                        </select>
                                        
                                        <label style="margin-top: 10px; display: flex; align-items: center; gap: 5px;">
                                            <input type="checkbox" name="download_images" value="1" checked>
                                            تحميل الصور محلياً
                                        </label>
                                    </div>

                                    <h4 style="margin-top: 20px; color: #e50914;">الحلقات (<?php echo count($playlist_videos); ?>)</h4>
                                    <div class="episodes-grid">
                                        <?php foreach ($playlist_videos as $index => $video): ?>
                                        <div class="episode-card">
                                            <strong>ح<?php echo $index + 1; ?>:</strong> 
                                            <?php echo htmlspecialchars($video['title']); ?>
                                            <div style="font-size: 11px; color: #b3b3b3;">
                                                <?php echo $video['duration']; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <button type="submit" name="save_series" class="btn btn-success" style="margin-top: 20px;">
                                        <i class="fas fa-save"></i> حفظ المسلسل (<?php echo count($playlist_videos); ?> حلقة)
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- فلتر وبحث في المحتوى المحفوظ -->
            <div class="filter-section">
                <div class="filter-tabs">
                    <a href="?filter_type=all&filter_category=<?php echo $filter_category; ?>" class="filter-tab <?php echo $filter_type == 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th-large"></i> الكل (<?php echo count($all_items); ?>)
                    </a>
                    <a href="?filter_type=movies&filter_category=<?php echo $filter_category; ?>" class="filter-tab <?php echo $filter_type == 'movies' ? 'active' : ''; ?>">
                        <i class="fas fa-film"></i> أفلام (<?php echo $stats['movies']; ?>)
                    </a>
                    <a href="?filter_type=series&filter_category=<?php echo $filter_category; ?>" class="filter-tab <?php echo $filter_type == 'series' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> مسلسلات (<?php echo $stats['series']; ?>)
                    </a>
                    <a href="?filter_type=videos&filter_category=<?php echo $filter_category; ?>" class="filter-tab <?php echo $filter_type == 'videos' ? 'active' : ''; ?>">
                        <i class="fas fa-video"></i> فيديوهات (<?php echo $stats['videos']; ?>)
                    </a>
                </div>

                                            

                <form method="GET" class="search-box">
                    <input type="hidden" name="filter_type" value="<?php echo $filter_type; ?>">
                    <input type="hidden" name="filter_category" value="<?php echo $filter_category; ?>">
                    <input type="text" name="search" class="search-input" placeholder="بحث في المحتوى المحفوظ..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> بحث
                    </button>
                </form>
            </div>

            <!-- عرض المحتوى المحفوظ -->
            <?php if (!empty($all_items)): ?>
            <div class="items-grid">
                <?php foreach ($all_items as $item): ?>
                <div class="item-card">
                    <div class="item-header">
                        <img src="<?php echo getImageUrl($item); ?>" 
                             class="item-thumb" 
                             alt="<?php echo htmlspecialchars($item['title']); ?>"
                             onerror="this.src='https://via.placeholder.com/300x160?text=No+Image'; this.onerror=null;">
                        
                        <div class="item-badge 
                            <?php 
                                if ($item['content_type'] == 'movie') echo 'badge-movie';
                                elseif ($item['content_type'] == 'series') echo 'badge-series';
                                else echo 'badge-video';
                            ?>">
                            <?php 
                                if ($item['content_type'] == 'movie') echo '<i class="fas fa-film"></i> فيلم';
                                elseif ($item['content_type'] == 'series') echo '<i class="fas fa-list"></i> مسلسل';
                                else echo '<i class="fas fa-video"></i> فيديو';
                            ?>
                        </div>

                        <div class="category-tag <?php 
                            echo $item['category'] == 'movies' ? 'tag-movies' : 
                                ($item['category'] == 'series' ? 'tag-series' : 'tag-general'); 
                        ?>">
                            <?php 
                            echo $item['category'] == 'movies' ? '🎬 فيلم' : 
                                ($item['category'] == 'series' ? '📺 مسلسل' : '🎥 عام'); 
                            ?>
                        </div>

                        <div class="item-actions">
                            <?php if ($item['content_type'] == 'series'): ?>
                                <a href="youtube-series-episodes.php?id=<?php echo $item['id']; ?>" class="action-btn" title="عرض الحلقات">
                                    <i class="fas fa-list"></i>
                                </a>
                            <?php endif; ?>
                            
                            <a href="?delete=1&type=<?php echo $item['content_type']; ?>&id=<?php echo $item['id']; ?>" 
                               class="action-btn delete" 
                               title="حذف"
                               onclick="return confirm('⚠️ هل أنت متأكد من حذف هذا العنصر؟')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>

                    <div class="item-info">
                        <div class="item-title"><?php echo htmlspecialchars($item['title']); ?></div>
                        
                        <div class="item-meta">
                            <?php if ($item['content_type'] == 'movie' || $item['content_type'] == 'video'): ?>
                                <span><i class="fas fa-clock"></i> <?php echo $item['duration']; ?></span>
                                <span><i class="fas fa-eye"></i> <?php echo $item['view_count']; ?></span>
                            <?php else: ?>
                                <span><i class="fas fa-film"></i> <?php echo $item['total_episodes']; ?> حلقة</span>
                            <?php endif; ?>
                            <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($item['channel_title']); ?></span>
                        </div>

                        <select class="status-select" onchange="updateStatus('<?php echo $item['content_type']; ?>', <?php echo $item['id']; ?>, this.value)">
                            <option value="1" <?php echo ($item['status'] ?? 1) == 1 ? 'selected' : ''; ?>>✅ منشور</option>
                            <option value="0" <?php echo ($item['status'] ?? 1) == 0 ? 'selected' : ''; ?>>📝 مسودة</option>
                        </select>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 60px; background: #1a1a1a; border-radius: 15px;">
                <i class="fab fa-youtube" style="font-size: 60px; color: #444;"></i>
                <h3 style="color: #b3b3b3; margin-top: 20px;">لا يوجد محتوى محفوظ</h3>
                <p style="color: #666;">ابحث عن فيلم أو مسلسل وأضفه من الأعلى</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showSearchTab(tab) {
            document.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.search-content').forEach(c => c.classList.remove('active'));
            
            if (tab === 'videos') {
                document.querySelectorAll('.search-tab')[0].classList.add('active');
                document.getElementById('search-videos').classList.add('active');
            } else if (tab === 'playlists') {
                document.querySelectorAll('.search-tab')[1].classList.add('active');
                document.getElementById('search-playlists').classList.add('active');
            } else if (tab === 'url-video') {
                document.querySelectorAll('.search-tab')[2].classList.add('active');
                document.getElementById('search-url-video').classList.add('active');
            } else if (tab === 'url-playlist') {
                document.querySelectorAll('.search-tab')[3].classList.add('active');
                document.getElementById('search-url-playlist').classList.add('active');
            }
        }

        function selectResult(type, id) {
            document.getElementById('selected_type').value = type;
            document.getElementById('selected_id').value = id;
            document.getElementById('select-result-form').submit();
        }

        function updateStatus(type, id, status) {
            fetch('content-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'update_status=1&item_type=' + type + '&item_id=' + id + '&status=' + status
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✅ تم تحديث الحالة');
                }
            });
        }

        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideDown 0.5s reverse';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

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