<?php
// admin/youtube-series-episodes.php - إدارة حلقات المسلسل
define('ALLOW_ACCESS', true);

$base_path = 'C:/xampp/htdocs/fayez-movie';
require_once $base_path . '/includes/config.php';
require_once $base_path . '/includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$series_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// جلب معلومات المسلسل
$series = $pdo->prepare("SELECT * FROM youtube_series WHERE id = ?");
$series->execute([$series_id]);
$series_data = $series->fetch();

if (!$series_data) {
    header('Location: content-manager.php');
    exit;
}

// معالجة حذف حلقة
if (isset($_GET['delete_episode'])) {
    $episode_id = (int)$_GET['delete_episode'];
    
    try {
        // حذف الصورة المحلية
        $ep = $pdo->prepare("SELECT local_thumbnail FROM youtube_episodes WHERE id = ?");
        $ep->execute([$episode_id]);
        $ep_data = $ep->fetch();
        
        if ($ep_data && !empty($ep_data['local_thumbnail'])) {
            $file_path = $base_path . '/' . $ep_data['local_thumbnail'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $delete = $pdo->prepare("DELETE FROM youtube_episodes WHERE id = ?");
        $delete->execute([$episode_id]);
        
        $success = "✅ تم حذف الحلقة بنجاح!";
    } catch (Exception $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// معالجة تحديث حلقة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_episode'])) {
    $episode_id = (int)$_POST['episode_id'];
    $title = $_POST['title'] ?? '';
    $episode_number = (int)$_POST['episode_number'];
    $season_number = (int)$_POST['season_number'];
    
    try {
        $update = $pdo->prepare("
            UPDATE youtube_episodes SET 
                title = ?,
                episode_number = ?,
                season_number = ?
            WHERE id = ?
        ");
        $update->execute([$title, $episode_number, $season_number, $episode_id]);
        
        $success = "✅ تم تحديث الحلقة بنجاح!";
    } catch (Exception $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}

// جلب الحلقات
$episodes = $pdo->prepare("
    SELECT * FROM youtube_episodes 
    WHERE series_id = ? 
    ORDER BY season_number ASC, episode_number ASC
");
$episodes->execute([$series_id]);
$episodes_data = $episodes->fetchAll();

// تجميع المواسم
$seasons = [];
foreach ($episodes_data as $ep) {
    $seasons[$ep['season_number']][] = $ep;
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة حلقات <?php echo htmlspecialchars($series_data['title']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* نفس التنسيقات */
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
        }

        .main-content {
            flex: 1;
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

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
        }

        .btn-primary {
            background: #e50914;
        }

        .btn-primary:hover {
            background: #b20710;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-warning:hover {
            background: #e67e22;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-info {
            background: #3498db;
        }

        .btn-info:hover {
            background: #2980b9;
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 13px;
        }

        .series-info {
            background: #252525;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
            border: 1px solid #e50914;
        }

        .season-box {
            background: #252525;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .season-title {
            font-size: 18px;
            font-weight: 700;
            color: #e50914;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #333;
        }

        .episodes-table {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: right;
            padding: 12px;
            color: #b3b3b3;
            font-weight: 600;
            border-bottom: 2px solid #333;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #333;
        }

        tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .episode-thumb {
            width: 80px;
            height: 45px;
            object-fit: cover;
            border-radius: 5px;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            border: 1px solid #e50914;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 20px;
            font-weight: 700;
            color: #e50914;
        }

        .close-btn {
            background: none;
            border: none;
            color: #b3b3b3;
            font-size: 24px;
            cursor: pointer;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
        }

        .form-control:focus {
            border-color: #e50914;
            outline: none;
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
        }

        .notification.error {
            background: #e50914;
        }

        @keyframes slideDown {
            from { transform: translate(-50%, -100%); opacity: 0; }
            to { transform: translate(-50%, 0); opacity: 1; }
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #b3b3b3;
            text-decoration: none;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #e50914;
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

            <?php if (isset($success)): ?>
            <div class="notification">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <a href="content-manager.php" class="back-link">
                <i class="fas fa-arrow-right"></i> العودة للمسلسلات
            </a>

            <!-- معلومات المسلسل -->
            <div class="series-info">
                <div style="display: flex; gap: 20px; align-items: center;">
                    <img src="<?php echo !empty($series_data['local_thumbnail']) ? '../' . $series_data['local_thumbnail'] : $series_data['thumbnail']; ?>" 
                         style="width: 150px; height: 90px; object-fit: cover; border-radius: 8px; border: 2px solid #e50914;"
                         onerror="this.src='https://via.placeholder.com/150x90?text=No+Image'">
                    
                    <div>
                        <h1 style="font-size: 24px; color: #e50914;"><?php echo htmlspecialchars($series_data['title']); ?></h1>
                        <p style="color: #b3b3b3;">عدد الحلقات: <?php echo count($episodes_data); ?></p>
                    </div>
                </div>
            </div>

            <!-- الحلقات مقسمة حسب الموسم -->
            <?php foreach ($seasons as $season_num => $season_episodes): ?>
            <div class="season-box">
                <div class="season-title">
                    <i class="fas fa-list"></i> الموسم <?php echo $season_num; ?>
                    <span style="background: #e50914; padding: 3px 10px; border-radius: 20px; font-size: 12px; margin-right: 10px;">
                        <?php echo count($season_episodes); ?> حلقة
                    </span>
                </div>

                <div class="episodes-table">
                    <table>
                        <thead>
                            <tr>
                                <th>الصورة</th>
                                <th>رقم الحلقة</th>
                                <th>العنوان</th>
                                <th>المدة</th>
                                <th>المشاهدات</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($season_episodes as $episode): ?>
                            <tr>
                                <td>
                                    <img src="<?php echo !empty($episode['local_thumbnail']) ? '../' . $episode['local_thumbnail'] : $episode['thumbnail']; ?>" 
                                         class="episode-thumb" 
                                         alt=""
                                         onerror="this.src='https://via.placeholder.com/80x45?text=No+Image'">
                                </td>
                                <td>#<?php echo $episode['episode_number']; ?></td>
                                <td><?php echo htmlspecialchars($episode['title']); ?></td>
                                <td><?php echo $episode['duration']; ?></td>
                                <td><?php echo $episode['view_count']; ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-warning btn-sm" onclick="editEpisode(<?php echo $episode['id']; ?>, '<?php echo htmlspecialchars(addslashes($episode['title'])); ?>', <?php echo $episode['episode_number']; ?>, <?php echo $episode['season_number']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?id=<?php echo $series_id; ?>&delete_episode=<?php echo $episode['id']; ?>" 
                                           class="btn btn-danger btn-sm" 
                                           onclick="return confirm('هل أنت متأكد من حذف هذه الحلقة؟')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- نافذة تعديل الحلقة -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">تعديل بيانات الحلقة</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST">
                <input type="hidden" name="episode_id" id="edit_id">
                <input type="hidden" name="update_episode" value="1">
                
                <div class="form-group">
                    <label class="form-label">عنوان الحلقة</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم الحلقة</label>
                    <input type="number" name="episode_number" id="edit_number" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">رقم الموسم</label>
                    <input type="number" name="season_number" id="edit_season" class="form-control" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">💾 حفظ التعديلات</button>
                    <button type="button" class="btn btn-danger" style="flex: 1;" onclick="closeModal()">❌ إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function editEpisode(id, title, number, season) {
        document.getElementById('edit_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_number').value = number;
        document.getElementById('edit_season').value = season;
        document.getElementById('editModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });

    setTimeout(() => {
        let notification = document.querySelector('.notification');
        if (notification) {
            notification.style.animation = 'slideDown 0.5s reverse';
            setTimeout(() => notification.remove(), 500);
        }
    }, 5000);
    </script>
</body>
</html>