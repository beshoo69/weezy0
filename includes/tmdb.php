<?php
// includes/api/tmdb.php - API موحد لجميع دوال TMDB
define('TMDB_API_KEY', '5dc3e335b09cbf701d8685dd9a766949');
define('TMDB_LANGUAGE', 'ar-SA');

/**
 * تنفيذ طلب API
 */
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
function getArabicMovies($page = 1, $country = null) {
    $arab_countries = [
        'EG', 'SA', 'LB', 'SY', 'AE', 'KW', 'MA', 'TN', 'DZ', 'IQ', 'JO', 'PS', 'YE', 'OM', 'QA', 'BH'
    ];
    
    // إذا تم تحديد بلد معين، استخدمه وإلا استخدم كل الدول العربية
    $countries = $country ? [$country] : $arab_countries;
    
    $all_movies = [];
    
    foreach ($countries as $country_code) {
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
             . "&with_original_language=ar"
             . "&region=" . $country_code
             . "&sort_by=popularity.desc"
             . "&page=" . $page
             . "&language=ar-SA";
        
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            foreach ($data['results'] as $movie) {
                $movie['country'] = $country_code;
                $all_movies[] = $movie;
            }
        }
        
        // تأخير بسيط بين الطلبات
        usleep(250000);
    }
    
    return $all_movies;
}

/**
 * جلب المسلسلات العربية
 */
function getArabicTvShows($page = 1, $country = null) {
    $arab_countries = [
        'EG', 'SA', 'LB', 'SY', 'AE', 'KW', 'MA', 'TN', 'DZ', 'IQ', 'JO', 'PS', 'YE', 'OM', 'QA', 'BH'
    ];
    
    $countries = $country ? [$country] : $arab_countries;
    $all_tv = [];
    
    foreach ($countries as $country_code) {
        $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
             . "&with_original_language=ar"
             . "&region=" . $country_code
             . "&sort_by=popularity.desc"
             . "&page=" . $page
             . "&language=ar-SA";
        
        $data = tmdb_request($url);
        
        if ($data && isset($data['results'])) {
            foreach ($data['results'] as $tv) {
                $tv['country'] = $country_code;
                $all_tv[] = $tv;
            }
        }
        
        usleep(250000);
    }
    
    return $all_tv;
}

/**
 * جلب الأفلام حسب البلد العربي
 */
function getMoviesByArabCountry($country_code, $page = 1) {
    $countries = [
        'EG' => 'مصر',
        'SA' => 'السعودية',
        'LB' => 'لبنان',
        'SY' => 'سوريا',
        'AE' => 'الإمارات',
        'KW' => 'الكويت',
        'MA' => 'المغرب',
        'TN' => 'تونس',
        'DZ' => 'الجزائر',
        'IQ' => 'العراق',
        'JO' => 'الأردن',
        'PS' => 'فلسطين',
        'YE' => 'اليمن',
        'OM' => 'عمان',
        'QA' => 'قطر',
        'BH' => 'البحرين'
    ];
    
    $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
         . "&with_original_language=ar"
         . "&region=" . $country_code
         . "&sort_by=popularity.desc"
         . "&page=" . $page
         . "&language=ar-SA";
    
    $data = tmdb_request($url);
    $movies = $data['results'] ?? [];
    
    return [
        'country_code' => $country_code,
        'country_name' => $countries[$country_code] ?? $country_code,
        'movies' => $movies,
        'total_pages' => $data['total_pages'] ?? 1,
        'total_results' => $data['total_results'] ?? 0
    ];
}

/**
 * جلب المسلسلات حسب البلد العربي
 */
function getTvShowsByArabCountry($country_code, $page = 1) {
    $countries = [
        'EG' => 'مصر',
        'SA' => 'السعودية',
        'LB' => 'لبنان',
        'SY' => 'سوريا',
        'AE' => 'الإمارات',
        'KW' => 'الكويت',
        'MA' => 'المغرب',
        'TN' => 'تونس',
        'DZ' => 'الجزائر',
        'IQ' => 'العراق',
        'JO' => 'الأردن',
        'PS' => 'فلسطين',
        'YE' => 'اليمن',
        'OM' => 'عمان',
        'QA' => 'قطر',
        'BH' => 'البحرين'
    ];
    
    $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
         . "&with_original_language=ar"
         . "&region=" . $country_code
         . "&sort_by=popularity.desc"
         . "&page=" . $page
         . "&language=ar-SA";
    
    $data = tmdb_request($url);
    $tv_shows = $data['results'] ?? [];
    
    return [
        'country_code' => $country_code,
        'country_name' => $countries[$country_code] ?? $country_code,
        'tv_shows' => $tv_shows,
        'total_pages' => $data['total_pages'] ?? 1,
        'total_results' => $data['total_results'] ?? 0
    ];
}

/**
 * جلب أفلام رمضان
 */
function getRamadanSeries($year = null) {
    $year = $year ?? date('Y');
    
    $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
         . "&with_original_language=ar"
         . "&first_air_date_year=" . $year
         . "&sort_by=popularity.desc"
         . "&language=ar-SA";
    
    $data = tmdb_request($url);
    return $data['results'] ?? [];
}

/**
 * جلب أحدث الأفلام العربية
 */
function getLatestArabicMovies($page = 1) {
    $url = "https://api.themoviedb.org/3/discover/movie?api_key=" . TMDB_API_KEY 
         . "&with_original_language=ar"
         . "&sort_by=release_date.desc"
         . "&page=" . $page
         . "&language=ar-SA";
    
    $data = tmdb_request($url);
    return $data['results'] ?? [];
}


/**
 * جلب أحدث المسلسلات العربية
 */
function getLatestArabicTvShows($page = 1) {
    $url = "https://api.themoviedb.org/3/discover/tv?api_key=" . TMDB_API_KEY 
         . "&with_original_language=ar"
         . "&sort_by=first_air_date.desc"
         . "&page=" . $page
         . "&language=ar-SA";
    
    $data = tmdb_request($url);
    return $data['results'] ?? [];
}
/**
 * جلب المسلسلات الرائجة
 */
function getPopularTv($pages = 5) {
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

/**
 * جلب تفاصيل مسلسل
 */
function getTvDetails($tv_id) {
    $url = "https://api.themoviedb.org/3/tv/" . $tv_id . "?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE;
    return tmdb_request($url);
}

/**
 * جلب مواسم المسلسل
 */
function getTvSeasons($tv_id, $season_number) {
    $url = "https://api.themoviedb.org/3/tv/" . $tv_id . "/season/" . $season_number . "?api_key=" . TMDB_API_KEY . "&language=" . TMDB_LANGUAGE;
    return tmdb_request($url);
}

// // ========== دوال الأفلام ==========
// include_once __DIR__ . '/tmdb-movies.php';

// // ========== دوال المسلسلات ==========
// include_once __DIR__ . '/tmdb-tv.php';
?>