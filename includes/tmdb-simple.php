<?php
// includes/tmdb-simple.php - نسخة متطورة لجلب كل المسلسلات
define('TMDB_API_KEY', '5dc3e335b09cbf701d8685dd9a766949');
define('TMDB_LANGUAGE', 'ar-SA');

function tmdb_request($url) {
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

// ========== دالة جلب كل المسلسلات من جميع الصفحات ==========
function getAllPopularTv($max_pages = 500) {
    $all_tv = [];
    
    // أولاً: جلب الصفحة الأولى لمعرفة عدد الصفحات الكلي
    $first_page = tmdb_request("https://api.themoviedb.org/3/tv/popular?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE . "&page=1");
    
    if (!$first_page || !isset($first_page['total_pages'])) {
        return [];
    }
    
    $total_pages = min($first_page['total_pages'], $max_pages); // حد أقصى 500 صفحة = 10,000 مسلسل
    
    // إضافة نتائج الصفحة الأولى
    if (isset($first_page['results'])) {
        $all_tv = array_merge($all_tv, $first_page['results']);
    }
    
    // جلب باقي الصفحات
    for ($page = 2; $page <= $total_pages; $page++) {
        $url = "https://api.themoviedb.org/3/tv/popular?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE . "&page=" . $page;
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            $all_tv = array_merge($all_tv, $data['results']);
        }
        
        // تأخير بسيط بين الطلبات
        usleep(250000); // 0.25 ثانية
    }
    
    return $all_tv;
}

// ========== جلب مسلسلات حسب النوع ==========
function getTvByGenre($genre_id, $max_pages = 100) {
    $all_tv = [];
    
    for ($page = 1; $page <= $max_pages; $page++) {
        $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY . 
               "&language=" . TMDB_LANGUAGE . 
               "&with_genres=" . $genre_id . 
               "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            $all_tv = array_merge($all_tv, $data['results']);
        }
        
        if ($data && $page >= ($data['total_pages'] ?? 0)) {
            break;
        }
        
        usleep(250000);
    }
    
    return $all_tv;
}

// ========== جلب مسلسلات حسب السنة ==========
function getTvByYear($year, $max_pages = 50) {
    $all_tv = [];
    
    for ($page = 1; $page <= $max_pages; $page++) {
        $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY . 
               "&language=" . TMDB_LANGUAGE . 
               "&first_air_date_year=" . $year . 
               "&page=" . $page;
        
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            $all_tv = array_merge($all_tv, $data['results']);
        }
        
        if ($data && $page >= ($data['total_pages'] ?? 0)) {
            break;
        }
        
        usleep(250000);
    }
    
    return $all_tv;
}

// ========== جلب مسلسلات الأعلى تقييماً ==========
function getTopRatedTv($max_pages = 100) {
    $all_tv = [];
    
    for ($page = 1; $page <= $max_pages; $page++) {
        $url = "https://api.themoviedb.org/3/tv/top_rated?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE . "&page=" . $page;
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            $all_tv = array_merge($all_tv, $data['results']);
        }
        
        if ($data && $page >= ($data['total_pages'] ?? 0)) {
            break;
        }
        
        usleep(250000);
    }
    
    return $all_tv;
}

// ========== دالة getPopularTv القديمة (للتوافق) ==========
function getPopularTv($pages = 3) {
    $all_tv = [];
    
    for ($page = 1; $page <= $pages; $page++) {
        $url = "https://api.themoviedb.org/3/tv/popular?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE . "&page=" . $page;
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            $all_tv = array_merge($all_tv, $data['results']);
        }
    }
    
    return $all_tv;
}

function getTvDetails($tv_id) {
    $url = "https://api.themoviedb.org/3/tv/" . $tv_id . "?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE;
    return tmdb_request($url);
}

function getTvSeasons($tv_id, $season_number) {
    $url = "https://api.themoviedb.org/3/tv/" . $tv_id . "/season/" . $season_number . "?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE;
    return tmdb_request($url);
}
?>