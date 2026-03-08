<?php
// admin/ajax-search.php - معالجة طلبات البحث AJAX
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    exit('غير مصرح');
}

$type = $_GET['type'] ?? '';
$query = $_GET['q'] ?? '';

switch ($type) {
    case 'movies':
        searchMovies($query);
        break;
    case 'series':
        searchSeries($query);
        break;
    case 'episodes':
        searchEpisodes($query);
        break;
    case 'subtitles':
        searchSubtitles($query);
        break;
    case 'users':
        searchUsers($query);
        break;
    case 'plans':
        searchPlans($query);
        break;
}

function searchMovies($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("SELECT id, title, year, genre FROM movies ORDER BY id DESC LIMIT 30");
        $movies = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, title, year, genre FROM movies WHERE title LIKE ? OR year LIKE ? OR genre LIKE ? ORDER BY id DESC LIMIT 50");
        $search = "%$query%";
        $stmt->execute([$search, $search, $search]);
        $movies = $stmt->fetchAll();
    }
    
    if (empty($movies)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>العنوان</th>
                <th>السنة</th>
                <th>التصنيف</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($movies as $movie): ?>
            <tr>
                <td><?php echo $movie['id']; ?></td>
                <td><?php echo htmlspecialchars($movie['title']); ?></td>
                <td><?php echo $movie['year']; ?></td>
                <td><?php echo $movie['genre'] ?: 'غير محدد'; ?></td>
                <td>
                    <a href="#" onclick="confirmDelete('movie', <?php echo $movie['id']; ?>, '<?php echo htmlspecialchars($movie['title']); ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <a href="../movie.php?id=<?php echo $movie['id']; ?>" target="_blank" class="view-btn">
                        <i class="fas fa-eye"></i> عرض
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function searchSeries($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("SELECT id, title, year FROM series ORDER BY id DESC LIMIT 30");
        $series = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, title, year FROM series WHERE title LIKE ? OR year LIKE ? ORDER BY id DESC LIMIT 50");
        $search = "%$query%";
        $stmt->execute([$search, $search]);
        $series = $stmt->fetchAll();
    }
    
    if (empty($series)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>العنوان</th>
                <th>السنة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($series as $item): ?>
            <tr>
                <td><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['title']); ?></td>
                <td><?php echo $item['year']; ?></td>
                <td>
                    <a href="#" onclick="confirmDelete('series', <?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['title']); ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <a href="../series.php?id=<?php echo $item['id']; ?>" target="_blank" class="view-btn">
                        <i class="fas fa-eye"></i> عرض
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function searchEpisodes($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("
            SELECT e.*, s.title as series_title 
            FROM episodes e 
            JOIN series s ON e.series_id = s.id 
            ORDER BY e.id DESC LIMIT 30
        ");
        $episodes = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT e.*, s.title as series_title 
            FROM episodes e 
            JOIN series s ON e.series_id = s.id 
            WHERE e.episode_number LIKE ? OR e.title LIKE ? OR s.title LIKE ?
            ORDER BY e.id DESC LIMIT 50
        ");
        $search = "%$query%";
        $stmt->execute([$search, $search, $search]);
        $episodes = $stmt->fetchAll();
    }
    
    if (empty($episodes)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>المسلسل</th>
                <th>الموسم</th>
                <th>الحلقة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($episodes as $ep): ?>
            <tr>
                <td><?php echo $ep['id']; ?></td>
                <td><?php echo htmlspecialchars($ep['series_title']); ?></td>
                <td><?php echo $ep['season_number']; ?></td>
                <td><?php echo $ep['episode_number']; ?></td>
                <td>
                    <a href="#" onclick="confirmDelete('episode', <?php echo $ep['id']; ?>, 'حلقة <?php echo $ep['episode_number']; ?> من <?php echo htmlspecialchars($ep['series_title']); ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function searchSubtitles($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("
            SELECT s.*, 
                   CASE 
                       WHEN s.content_type = 'movie' THEN (SELECT title FROM movies WHERE id = s.content_id)
                       WHEN s.content_type = 'series' THEN (SELECT title FROM series WHERE id = s.content_id)
                       ELSE 'غير معروف'
                   END as content_title
            FROM subtitles s 
            ORDER BY s.id DESC LIMIT 30
        ");
        $subtitles = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   CASE 
                       WHEN s.content_type = 'movie' THEN (SELECT title FROM movies WHERE id = s.content_id)
                       WHEN s.content_type = 'series' THEN (SELECT title FROM series WHERE id = s.content_id)
                       ELSE 'غير معروف'
                   END as content_title
            FROM subtitles s 
            WHERE s.language LIKE ? OR s.language_code LIKE ? OR content_title LIKE ?
            ORDER BY s.id DESC LIMIT 50
        ");
        $search = "%$query%";
        $stmt->execute([$search, $search, $search]);
        $subtitles = $stmt->fetchAll();
    }
    
    if (empty($subtitles)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>المحتوى</th>
                <th>النوع</th>
                <th>اللغة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subtitles as $sub): ?>
            <tr>
                <td><?php echo $sub['id']; ?></td>
                <td><?php echo htmlspecialchars($sub['content_title']); ?></td>
                <td><?php echo $sub['content_type'] == 'movie' ? 'فيلم' : 'مسلسل'; ?></td>
                <td><?php echo $sub['language']; ?></td>
                <td>
                    <a href="#" onclick="confirmDelete('subtitle', <?php echo $sub['id']; ?>, 'ترجمة <?php echo $sub['language']; ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function searchUsers($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("SELECT id, username, email, role FROM users ORDER BY id DESC LIMIT 30");
        $users = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE username LIKE ? OR email LIKE ? ORDER BY id DESC LIMIT 50");
        $search = "%$query%";
        $stmt->execute([$search, $search]);
        $users = $stmt->fetchAll();
    }
    
    if (empty($users)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>اسم المستخدم</th>
                <th>البريد</th>
                <th>الصلاحية</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo $user['email']; ?></td>
                <td><?php echo $user['role'] == 'admin' ? 'مدير' : 'مستخدم'; ?></td>
                <td>
                    <?php if ($user['role'] != 'admin'): ?>
                    <a href="#" onclick="confirmDelete('user', <?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                    <?php else: ?>
                    <span style="color: #e50914;">مدير</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function searchPlans($query) {
    global $pdo;
    
    if (empty($query)) {
        $stmt = $pdo->query("SELECT * FROM membership_plans ORDER BY id");
        $plans = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE name LIKE ? OR quality LIKE ? ORDER BY id");
        $search = "%$query%";
        $stmt->execute([$search, $search]);
        $plans = $stmt->fetchAll();
    }
    
    if (empty($plans)) {
        echo '<div class="no-data">❌ لا توجد نتائج للبحث</div>';
        return;
    }
    ?>
    <table class="results-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>الخطة</th>
                <th>السعر</th>
                <th>الجودة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($plans as $plan): ?>
            <tr>
                <td><?php echo $plan['id']; ?></td>
                <td><?php echo $plan['name']; ?></td>
                <td><?php echo $plan['price_monthly']; ?> ر.س</td>
                <td><?php echo $plan['quality']; ?></td>
                <td>
                    <a href="#" onclick="confirmDelete('plan', <?php echo $plan['id']; ?>, '<?php echo $plan['name']; ?>')" class="delete-btn">
                        <i class="fas fa-trash"></i> حذف
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}
?>