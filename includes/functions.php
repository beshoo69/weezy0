<?php
// includes/functions.php - الدوال العامة للمشروع (نسخة نهائية)

// =============================================
// دوال التحقق والصلاحيات
// =============================================

/**
 * التحقق من تسجيل الدخول
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * التحقق من صلاحية الأدمن
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * إعادة توجيه
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

// =============================================
// دوال النصوص والتنسيق
// =============================================

/**
 * إنشاء رابط آمن
 */
function url($path = '') {
    $base = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
    $base .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    return $base . ltrim($path, '/');
}

/**
 * قص النص
 */
function truncate($text, $length = 100) {
    if (mb_strlen($text) <= $length) return $text;
    return mb_substr($text, 0, $length) . '...';
}

/**
 * تنظيف النص
 */
function clean($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * إنشاء رمز عشوائي
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * تنسيق التاريخ
 */
function formatDate($date, $format = 'Y/m/d') {
    if (!$date) return '';
    return date($format, strtotime($date));
}

/**
 * تنسيق المدة
 */
function formatDuration($minutes) {
    if (!$minutes) return 'غير معروف';
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return "{$hours}س {$mins}د";
    }
    return "{$mins}د";
}

// =============================================
// دوال التسجيل والأخطاء
// =============================================

/**
 * تسجيل خطأ
 */
function logError($message) {
    $log_file = __DIR__ . '/../logs/errors.log';
    $log_dir = dirname($log_file);
    
    if (!file_exists($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);
}

// =============================================
// دوال الإعدادات
// =============================================

/**
 * جلب إعدادات الموقع
 */
function getSettings($key = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
        $settings = $stmt->fetch();
        
        if ($key && $settings) {
            return $settings[$key] ?? null;
        }
        
        return $settings;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * تحديث إعدادات الموقع
 */
function updateSettings($data) {
    global $pdo;
    
    try {
        $check = $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
        
        if ($check > 0) {
            $sql = "UPDATE settings SET 
                    site_name = ?, site_description = ?, site_keywords = ? 
                    WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$data['site_name'], $data['site_description'], $data['site_keywords']]);
        } else {
            $sql = "INSERT INTO settings (site_name, site_description, site_keywords) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute([$data['site_name'], $data['site_description'], $data['site_keywords']]);
        }
    } catch (Exception $e) {
        logError("Settings error: " . $e->getMessage());
        return false;
    }
}

// =============================================
// دوال البث المباشر
// =============================================

// =============================================
// دوال بوستر العضوية
// =============================================

/**
 * طباعة شارة العضوية على البوستر داخل بطاقة الفيلم أو المسلسل
 *
 * @param array $item بيانات العنصر ويُتوقع وجود المفتاح 'membership_level'
 * @param string $extraClass أي صنف إضافي لتخصيص الموضع أو المظهر
 */
function membershipBadgeOnPoster(array $item, string $extraClass = '') {
    if (isset($item['membership_level']) && $item['membership_level'] !== '' && $item['membership_level'] !== 'basic') {
        $level = $item['membership_level'];
        $class = htmlspecialchars($level);
        if ($extraClass) {
            $class .= ' ' . htmlspecialchars($extraClass);
        }
        echo '<div class="membership-badge-on-poster ' . $class . '">';
        if ($level == 'vip') {
            echo '<i class="fas fa-crown"></i> VIP';
        } elseif ($level == 'premium') {
            echo '<i class="fas fa-star"></i> مميز';
        }
        echo '</div>';
    }
}

/**
 * استدعاء YouTube API لإحضار فيديوهات بحثية
 * @param string $query نص البحث
 * @param int $maxResults الحد الأقصى للنتائج
 * @return array قائمة بالعناصر تحتوي videoId, title, thumbnail
 */
function fetchYouTubeVideos($query, $maxResults = 20) {
    $key = defined('YOUTUBE_API_KEY') ? YOUTUBE_API_KEY : '';
    if (!$key) return [];
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&type=video&maxResults=" . intval($maxResults) . "&q=" . urlencode($query) . "&key=" . $key;
    $json = @file_get_contents($url);
    if (!$json) return [];
    $data = json_decode($json, true);
    if (empty($data['items'])) return [];
    $results = [];
    foreach ($data['items'] as $item) {
        $results[] = [
            'videoId'   => $item['id']['videoId'] ?? '',
            'title'     => $item['snippet']['title'] ?? '',
            'thumbnail' => $item['snippet']['thumbnails']['medium']['url'] ?? ''
        ];
    }
    return $results;
}

// =============================================
// دوال البث المباشر
// =============================================

/**
 * جلب القنوات المباشرة
 */
function getLiveChannels($category = null, $limit = null) {
    global $pdo;
    
    $sql = "SELECT * FROM live_channels WHERE status = 'live'";
    $params = [];
    
    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY featured DESC, views DESC";
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * جلب الفعاليات المباشرة
 */
function getLiveEvents($status = 'live', $limit = null) {
    global $pdo;
    
    $sql = "SELECT * FROM live_events WHERE status = ? ORDER BY start_time";
    $params = [$status];
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $params[] = $limit;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
?>