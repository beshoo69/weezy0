<?php
// admin/subtitles-list.php - عرض جميع الترجمات
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// حذف ترجمة
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // جلب مسار الملف لحذفه
    $get_file = $pdo->prepare("SELECT subtitle_file FROM subtitles WHERE id = ?");
    $get_file->execute([$id]);
    $file = $get_file->fetch();
    
    if ($file && !empty($file['subtitle_file'])) {
        $file_path = __DIR__ . '/../' . $file['subtitle_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    $delete = $pdo->prepare("DELETE FROM subtitles WHERE id = ?");
    $delete->execute([$id]);
    
    header('Location: subtitles-list.php?deleted=1');
    exit;
}

// جلب الترجمات مع أسماء المحتوى
$subtitles = $pdo->query("
    SELECT s.*, 
           CASE 
               WHEN s.content_type = 'movie' THEN (SELECT title FROM movies WHERE id = s.content_id)
               WHEN s.content_type = 'series' THEN (SELECT title FROM series WHERE id = s.content_id)
               ELSE 'غير معروف'
           END as content_title
    FROM subtitles s 
    ORDER BY s.id DESC 
    LIMIT 50
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>قائمة الترجمات</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #e50914;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
        }
        th {
            background: #252525;
            color: #e50914;
            padding: 15px;
            text-align: center;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #333;
            text-align: center;
        }
        tr:hover {
            background: #252525;
        }
        .btn {
            padding: 5px 10px;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            margin: 0 3px;
        }
        .btn-delete {
            background: #e50914;
        }
        .btn-view {
            background: #3498db;
        }
        .badge {
            background: #27ae60;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-closed-captioning"></i> قائمة الترجمات</h1>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>المحتوى</th>
                    <th>النوع</th>
                    <th>اللغة</th>
                    <th>افتراضي</th>
                    <th>تاريخ الإضافة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($subtitles as $sub): ?>
                <tr>
                    <td><?php echo $sub['id']; ?></td>
                    <td><?php echo htmlspecialchars($sub['content_title']); ?></td>
                    <td><?php echo $sub['content_type'] == 'movie' ? 'فيلم' : 'مسلسل'; ?></td>
                    <td><?php echo htmlspecialchars($sub['language']); ?></td>
                    <td><?php echo $sub['is_default'] ? '✅' : '❌'; ?></td>
                    <td><?php echo date('Y-m-d', strtotime($sub['created_at'])); ?></td>
                    <td>
                        <a href="edit-subtitle.php?id=<?php echo $sub['id']; ?>" class="btn btn-view">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="?delete=<?php echo $sub['id']; ?>" class="btn btn-delete" onclick="return confirm('هل أنت متأكد؟')">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>