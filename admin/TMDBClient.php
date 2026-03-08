<?php
// admin/TMDBClient.php - فئة التعامل مع TMDB API

class TMDBClient {
    private $api_key;
    private $base_url = 'https://api.themoviedb.org/3/';
    private $image_base = 'https://image.tmdb.org/t/p/';
    
    public function __construct($api_key) {
        $this->api_key = $api_key;
    }
    
    /**
     * تنفيذ طلب API
     */
    private function makeRequest($endpoint, $params = []) {
        $url = $this->base_url . $endpoint . '?api_key=' . $this->api_key . '&language=ar-SA';
        
        foreach ($params as $key => $value) {
            $url .= '&' . $key . '=' . urlencode($value);
        }
        
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
    
    // ===== دوال الأفلام =====
    
    /**
     * جلب الأفلام الشائعة
     */
    public function getPopularMovies($page = 1) {
        return $this->makeRequest('movie/popular', ['page' => $page]);
    }
    
    /**
     * جلب الأفلام المعروضة حالياً في السينما
     */
    public function getNowPlayingMovies($page = 1) {
        return $this->makeRequest('movie/now_playing', ['page' => $page]);
    }
    
    /**
     * جلب الأفلام القادمة
     */
    public function getUpcomingMovies($page = 1) {
        return $this->makeRequest('movie/upcoming', ['page' => $page]);
    }
    
    /**
     * جلب الأفلام الأعلى تقييماً
     */
    public function getTopRatedMovies($page = 1) {
        return $this->makeRequest('movie/top_rated', ['page' => $page]);
    }
    
    /**
     * جلب تفاصيل فيلم
     */
    public function getMovieDetails($id) {
        return $this->makeRequest('movie/' . $id, ['append_to_response' => 'credits,videos,images']);
    }
    
    // ===== دوال المسلسلات =====
    
    /**
     * جلب المسلسلات الشائعة
     */
    public function getPopularTVShows($page = 1) {
        return $this->makeRequest('tv/popular', ['page' => $page]);
    }
    
    /**
     * جلب المسلسلات التي تعرض اليوم
     */
    public function getTVAiringToday($page = 1) {
        return $this->makeRequest('tv/airing_today', ['page' => $page]);
    }
    
    /**
     * جلب المسلسلات التي تعرض حالياً
     */
    public function getTVOnTheAir($page = 1) {
        return $this->makeRequest('tv/on_the_air', ['page' => $page]);
    }
    
    /**
     * جلب المسلسلات الأعلى تقييماً
     */
    public function getTopRatedTVShows($page = 1) {
        return $this->makeRequest('tv/top_rated', ['page' => $page]);
    }
    
    /**
     * جلب تفاصيل مسلسل
     */
    public function getTVShowDetails($id) {
        return $this->makeRequest('tv/' . $id, ['append_to_response' => 'credits,videos,images']);
    }
    
    /**
     * الحصول على روابط الصور
     */
    public function getImageUrl($path, $size = 'w500') {
        if (empty($path)) return '';
        return $this->image_base . $size . $path;
    }
    
    /**
     * الحصول على لغة المحتوى
     */
    private function getLanguageCode($iso) {
        $languages = [
            'ar' => 'العربية',
            'en' => 'الإنجليزية',
            'tr' => 'التركية',
            'hi' => 'الهندية',
            'ko' => 'الكورية',
            'ja' => 'اليابانية',
            'fr' => 'الفرنسية',
            'de' => 'الألمانية',
            'es' => 'الإسبانية',
            'it' => 'الإيطالية',
            'ru' => 'الروسية',
            'zh' => 'الصينية'
        ];
        
        return $languages[$iso] ?? $iso;
    }
}
?>