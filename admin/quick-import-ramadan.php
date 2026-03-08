<?php
// admin/quick-import-ramadan.php - استيراد سريع لمسلسلات رمضان
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// قائمة مسلسلات رمضان 2026 (للاستيراد السريع)
$ramadan_list = [
    ['title' => 'رأس الأفعى', 'country' => 'مصر', 'stars' => 'أمير كرارة'],
    ['title' => 'درش', 'country' => 'مصر', 'stars' => 'مصطفى شعبان'],
    ['title' => 'فن الحرب', 'country' => 'مصر', 'stars' => 'يوسف الشريف'],
    ['title' => 'علي كلاي', 'country' => 'مصر', 'stars' => 'أحمد العوضي'],
    ['title' => 'الكينج', 'country' => 'مصر', 'stars' => 'محمد إمام'],
    ['title' => 'المداح 6', 'country' => 'مصر', 'stars' => 'حمادة هلال'],
    ['title' => 'وننسى اللى كان', 'country' => 'مصر', 'stars' => 'ياسمين عبد العزيز'],
    ['title' => 'صحاب الأرض', 'country' => 'مصر', 'stars' => 'منة شلبي'],
    ['title' => 'كان يا مكان', 'country' => 'مصر', 'stars' => 'ماجد الكدواني'],
    ['title' => 'اتنين غيرنا', 'country' => 'مصر', 'stars' => 'آسر ياسين'],
    ['title' => 'مناعة', 'country' => 'مصر', 'stars' => 'هند صبري'],
    ['title' => 'نرجس', 'country' => 'مصر', 'stars' => 'ريهام عبد الغفور'],
    ['title' => 'مولانا', 'country' => 'سوريا', 'stars' => 'تيم حسن'],
    ['title' => 'شارع الأعشى 2', 'country' => 'السعودية', 'stars' => 'إلهام علي'],
];

$imported = 0;
$skipped = 0;

if (isset($_POST['import'])) {
    foreach ($ramadan_list as $item) {
        $check = $pdo->prepare("SELECT id FROM series WHERE title LIKE ?");
        $check->execute(["%{$item['title']}%"]);
        
        if (!$check->fetch()) {
            $desc = "مسلسل رمضاني 2026 من بطولة {$item['stars']}";
            $sql = "INSERT INTO series (title, description, year, country, status, views) VALUES (?, ?, '2026', ?, 'ongoing', 0)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item['title'], $desc, $item['country']]);
            $imported++;
        } else {
            $skipped++;
        }
    }
    $message = "✅ تم استيراد $imported مسلسل، وتخطي $skipped موجود مسبقاً";
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>استيراد سريع - رمضان 2026</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 40px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: #1a1a1a;
            padding: 30px;
            border-radius: 15px;
        }
        h1 { color: #e50914; margin-bottom: 20px; }
        .btn {
            padding: 12px 25px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .message {
            background: #27ae60;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .list {
            background: #252525;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🌙 استيراد مسلسلات رمضان 2026</h1>
        
        <?php if (isset($message)): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="list">
            <h3 style="color: #e50914; margin-bottom: 15px;">📋 قائمة المسلسلات:</h3>
            <?php foreach ($ramadan_list as $item): ?>
                <div style="display: flex; justify-content: space-between; padding: 8px; border-bottom: 1px solid #333;">
                    <span><?php echo $item['title']; ?></span>
                    <span style="color: #b3b3b3;"><?php echo $item['country']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        
        <form method="POST">
            <button type="submit" name="import" class="btn">📥 استيراد الكل</button>
        </form>
    </div>
</body>
</html>