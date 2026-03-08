<?php
// movie-add-servers.php - إضافة سيرفرات لفيلم معين
require_once __DIR__ . '/includes/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id == 0) {
    // عرض قائمة الأفلام
    $movies = $pdo->query("SELECT id, title FROM movies LIMIT 20")->fetchAll();
    ?>
    <h1>اختر فيلماً لإضافة سيرفرات له</h1>
    <ul>
    <?php foreach ($movies as $movie): ?>
        <li><a href="?id=<?php echo $movie['id']; ?>"><?php echo $movie['title']; ?></a></li>
    <?php endforeach; ?>
    </ul>
    <?php
    exit;
}

// جلب الفيلم
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    die("الفيلم غير موجود");
}

// إضافة سيرفرات تجريبية
$servers_added = 0;

// حذف السيرفرات القديمة (اختياري)
// $pdo->prepare("DELETE FROM watch_servers WHERE item_type = 'movie' AND item_id = ?")->execute([$id]);

// إضافة سيرفرات جديدة
$servers = [
    ['سيرفر 1 - مشاهدة مباشرة', 'https://vidsrc.me/embed/movie?tmdb=' . $movie['tmdb_id'], '4K', 'arabic'],
    ['سيرفر 2 - مشاهدة سريعة', 'https://vidsrc.to/embed/movie/' . $movie['tmdb_id'], '1080p', 'arabic'],
    ['سيرفر 3 - جودة عالية', 'https://embed.su/embed/movie/' . $movie['tmdb_id'], '4K', 'english'],
];

foreach ($servers as $server) {
    $check = $pdo->prepare("SELECT id FROM watch_servers WHERE item_type = 'movie' AND item_id = ? AND server_name = ?");
    $check->execute([$id, $server[0]]);
    
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO watch_servers (item_type, item_id, server_name, server_url, quality, language) VALUES ('movie', ?, ?, ?, ?, ?)");
        $stmt->execute([$id, $server[0], $server[1], $server[2], $server[3]]);
        $servers_added++;
    }
}

echo "<h1>✅ تمت إضافة $servers_added سيرفر لفيلم: " . $movie['title'] . "</h1>";
echo "<a href='movie-final.php?id=$id'>عرض الفيلم الآن</a>";
?>