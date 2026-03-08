<?php
// includes/database.php - دوال قاعدة البيانات

/**
 * جلب جميع الأفلام
 */
function getMovies($limit = null, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT * FROM movies WHERE status = 'published' ORDER BY id DESC";
    if ($limit) {
        $sql .= " LIMIT $offset, $limit";
    }
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * جلب فيلم حسب ID
 */
function getMovie($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? AND status = 'published'");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * جلب جميع المسلسلات
 */
function getSeries($limit = null, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT * FROM series ORDER BY id DESC";
    if ($limit) {
        $sql .= " LIMIT $offset, $limit";
    }
    
    return $pdo->query($sql)->fetchAll();
}

/**
 * جلب مسلسل حسب ID
 */
function getSeriesById($id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM series WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * جلب حلقات مسلسل
 */
function getEpisodes($series_id, $season = null) {
    global $pdo;
    
    $sql = "SELECT * FROM episodes WHERE series_id = ?";
    $params = [$series_id];
    
    if ($season) {
        $sql .= " AND season_number = ?";
        $params[] = $season;
    }
    
    $sql .= " ORDER BY season_number, episode_number";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * جلب سيرفرات المشاهدة
 */
function getWatchServers($type, $item_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM watch_servers WHERE item_type = ? AND item_id = ?");
    $stmt->execute([$type, $item_id]);
    return $stmt->fetchAll();
}

/**
 * زيادة عدد المشاهدات
 */
function incrementViews($table, $id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("UPDATE $table SET views = IFNULL(views, 0) + 1 WHERE id = ?");
        $stmt->execute([$id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * البحث في الأفلام والمسلسلات
 */
function search($query) {
    global $pdo;
    
    $results = [];
    
    // بحث في الأفلام
    $stmt = $pdo->prepare("SELECT 'movie' as type, id, title, year, poster, imdb_rating 
                           FROM movies WHERE title LIKE ? OR description LIKE ? LIMIT 10");
    $stmt->execute(["%$query%", "%$query%"]);
    $results = array_merge($results, $stmt->fetchAll());
    
    // بحث في المسلسلات
    $stmt = $pdo->prepare("SELECT 'series' as type, id, title, year, poster, imdb_rating 
                           FROM series WHERE title LIKE ? OR description LIKE ? LIMIT 10");
    $stmt->execute(["%$query%", "%$query%"]);
    $results = array_merge($results, $stmt->fetchAll());
    
    return $results;
}
?>