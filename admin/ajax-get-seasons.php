<?php
// admin/ajax-get-seasons.php - جلب المواسم والحلقات الموجودة
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    exit(json_encode(['error' => 'غير مصرح']));
}

$series_id = isset($_GET['series_id']) ? (int)$_GET['series_id'] : 0;

if (!$series_id) {
    exit(json_encode(['error' => 'معرف المسلسل مطلوب']));
}

$result = [
    'seasons' => [],
    'episodes' => 0
];

// جلب المواسم
$seasons_stmt = $pdo->prepare("SELECT * FROM seasons WHERE series_id = ? ORDER BY season_number");
$seasons_stmt->execute([$series_id]);
$seasons = $seasons_stmt->fetchAll();

foreach ($seasons as $season) {
    // فك تشفير الروابط
    $watch_servers = !empty($season['watch_servers']) ? json_decode($season['watch_servers'], true) : [];
    $download_servers = !empty($season['download_servers']) ? json_decode($season['download_servers'], true) : [];
    
    $season_data = [
        'number' => $season['season_number'],
        'name' => $season['name'],
        'overview' => $season['overview'],
        'poster' => $season['poster'],
        'air_date' => $season['air_date'],
        'watch_servers' => $watch_servers ?: [],
        'download_servers' => $download_servers ?: [],
        'episodes' => []
    ];
    
    // جلب حلقات هذا الموسم
    $episodes_stmt = $pdo->prepare("SELECT * FROM episodes WHERE series_id = ? AND season_number = ? ORDER BY episode_number");
    $episodes_stmt->execute([$series_id, $season['season_number']]);
    $episodes = $episodes_stmt->fetchAll();
    
    foreach ($episodes as $episode) {
        // فك تشفير روابط الحلقة
        $ep_watch = !empty($episode['watch_servers']) ? json_decode($episode['watch_servers'], true) : [];
        $ep_download = !empty($episode['download_servers']) ? json_decode($episode['download_servers'], true) : [];
        
        $season_data['episodes'][] = [
            'number' => $episode['episode_number'],
            'title' => $episode['title'],
            'description' => $episode['description'],
            'duration' => $episode['duration'],
            'still_path' => $episode['still_path'],
            'air_date' => $episode['air_date'],
            'watch_servers' => $ep_watch ?: [],
            'download_servers' => $ep_download ?: []
        ];
        
        $result['episodes']++;
    }
    
    $result['seasons'][] = $season_data;
}

header('Content-Type: application/json');
echo json_encode($result);
?>