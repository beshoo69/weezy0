<?php
// admin/auto-import-settings.php - إعدادات الاستيراد التلقائي
require_once __DIR__ . '/../includes/config.php';
$test_tv = getPopularTv(1);
$api_status = !empty($test_tv);
if (!isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $settings = [
        'auto_import_enabled' => isset($_POST['enabled']) ? 1 : 0,
        'auto_import_pages' => (int)$_POST['pages'],
        'auto_import_interval' => (int)$_POST['interval'],
        'auto_import_episodes' => isset($_POST['import_episodes']) ? 1 : 0
    ];
    
    // حفظ في قاعدة البيانات أو ملف
    file_put_contents(__DIR__ . '/../cache/auto-import-settings.json', json_encode($settings));
    
    $message = "✅ تم حفظ الإعدادات بنجاح";
}

// قراءة الإعدادات
$settings_file = __DIR__ . '/../cache/auto-import-settings.json';
$default_settings = [
    'auto_import_enabled' => 1,
    'auto_import_pages' => 3,
    'auto_import_interval' => 60,
    'auto_import_episodes' => 1
];

if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
} else {
    $settings = $default_settings;
}

// جلب الإحصائيات
$total_series = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
$total_episodes = $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn();
$last_import = file_exists(__DIR__ . '/../logs/auto-import.log') 
    ? file_get_contents(__DIR__ . '/../logs/auto-import.log') 
    : 'لا توجد سجلات';
$last_import_line = explode("\n", $last_import);
$last_import = end($last_import_line);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات الاستيراد التلقائي - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            display: flex;
        }
        
        .sidebar {
            width: 280px;
            background: #0a0a0a;
            height: 100vh;
            position: fixed;
            right: 0;
            padding: 30px 20px;
            border-left: 1px solid #1f1f1f;
        }
        
        .logo {
            color: #e50914;
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #b3b3b3;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 5px;
            gap: 12px;
            transition: 0.3s;
        }
        
        .nav-item:hover, .nav-item.active {
            background: #e50914;
            color: white;
        }
        
        .main-content {
            flex: 1;
            margin-right: 280px;
            padding: 30px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: #1a1a1a;
            padding: 20px 30px;
            border-radius: 15px;
        }
        
        h1 {
            font-size: 28px;
            color: #e50914;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .message {
            background: #27ae60;
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .settings-card {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid #333;
        }
        
        .settings-title {
            color: #e50914;
            font-size: 22px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: #fff;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 16px;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-control:focus {
            border-color: #e50914;
            outline: none;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .btn {
            background: #e50914;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn:hover {
            background: #b20710;
        }
        
        .btn-secondary {
            background: #2a2a2a;
        }
        
        .btn-secondary:hover {
            background: #3a3a3a;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #1a1a1a;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #333;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
        }
        
        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
        }
        
        .log-box {
            background: #0a0a0a;
            padding: 20px;
            border-radius: 10px;
            color: #b3b3b3;
            font-family: monospace;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #333;
        }
        
        .cron-instructions {
            background: #252525;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
            border-right: 4px solid #e50914;
        }
        
        .cron-command {
            background: #0a0a0a;
            padding: 15px;
            border-radius: 8px;
            color: #e50914;
            font-family: monospace;
            margin: 10px 0;
            direction: ltr;
            text-align: left;
        }
        
        @media (max-width: 768px) {
            .sidebar { display: none; }
            .main-content { margin-right: 0; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">🎬 فايز<span>تڨي</span></div>
        <nav>
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> لوحة التحكم</a>
            <a href="import-tmdb.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد أفلام</a>
            <a href="import-tv.php" class="nav-item"><i class="fas fa-cloud-download-alt"></i> استيراد مسلسلات</a>
            <a href="auto-import-settings.php" class="nav-item active"><i class="fas fa-robot"></i> استيراد تلقائي</a>
            <hr style="border-color: #333; margin: 20px 0;">
            <a href="../index.php" class="nav-item" target="_blank"><i class="fas fa-globe"></i> زيارة الموقع</a>
            <a href="logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i> تسجيل خروج</a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="header">
            <h1><i class="fas fa-robot"></i> إعدادات الاستيراد التلقائي</h1>
            <div>🤖 استيراد المسلسلات بشكل تلقائي</div>
        </div>
        
        <?php if (isset($message)): ?>
            <div class="message">
                <i class="fas fa-check-circle"></i> <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_series; ?></div>
                <div class="stat-label">إجمالي المسلسلات</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_episodes; ?></div>
                <div class="stat-label">إجمالي الحلقات</div>
            </div>
            <div class="stat-card">
                <div class="stat-card">
    <div class="stat-number">
        <?php echo $api_status ? '🟢' : '🔴'; ?>
    </div>
    <div class="stat-label">حالة TMDB API</div>
</div>
                <div class="stat-label">حالة الاستيراد</div>
            </div>
        </div>
        
        <div class="settings-card">
            <h2 class="settings-title"><i class="fas fa-sliders-h"></i> إعدادات الاستيراد</h2>
            
            <form method="POST">
                <div class="checkbox-group">
                    <input type="checkbox" name="enabled" id="enabled" <?php echo $settings['auto_import_enabled'] ? 'checked' : ''; ?>>
                    <label for="enabled" style="display: inline; margin-bottom: 0;">تفعيل الاستيراد التلقائي</label>
                </div>
                
                <div class="form-group">
                    <label>عدد المسلسلات في كل عملية استيراد</label>
                    <select name="pages" class="form-control">
                        <option value="1" <?php echo $settings['auto_import_pages'] == 1 ? 'selected' : ''; ?>>20 مسلسل</option>
                        <option value="3" <?php echo $settings['auto_import_pages'] == 3 ? 'selected' : ''; ?>>60 مسلسل</option>
                        <option value="5" <?php echo $settings['auto_import_pages'] == 5 ? 'selected' : ''; ?>>100 مسلسل</option>
                        <option value="10" <?php echo $settings['auto_import_pages'] == 10 ? 'selected' : ''; ?>>200 مسلسل</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>وقت الاستيراد (بالدقائق)</label>
                    <select name="interval" class="form-control">
                        <option value="30" <?php echo $settings['auto_import_interval'] == 30 ? 'selected' : ''; ?>>كل 30 دقيقة</option>
                        <option value="60" <?php echo $settings['auto_import_interval'] == 60 ? 'selected' : ''; ?>>كل ساعة</option>
                        <option value="120" <?php echo $settings['auto_import_interval'] == 120 ? 'selected' : ''; ?>>كل ساعتين</option>
                        <option value="360" <?php echo $settings['auto_import_interval'] == 360 ? 'selected' : ''; ?>>كل 6 ساعات</option>
                        <option value="720" <?php echo $settings['auto_import_interval'] == 720 ? 'selected' : ''; ?>>كل 12 ساعة</option>
                        <option value="1440" <?php echo $settings['auto_import_interval'] == 1440 ? 'selected' : ''; ?>>كل يوم</option>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="import_episodes" id="import_episodes" <?php echo $settings['auto_import_episodes'] ? 'checked' : ''; ?>>
                    <label for="import_episodes" style="display: inline; margin-bottom: 0;">استيراد الحلقات تلقائياً</label>
                </div>
                
                <button type="submit" class="btn">
                    <i class="fas fa-save"></i> حفظ الإعدادات
                </button>
                
                <a href="auto-import-tv.php?force=1" class="btn btn-secondary" style="margin-right: 10px;" onclick="return confirm('تشغيل الاستيراد الآن؟')">
                    <i class="fas fa-play"></i> تشغيل الاستيراد الآن
                </a>
            </form>
        </div>
        
        <div class="settings-card">
            <h2 class="settings-title"><i class="fas fa-clock"></i> جدولة الاستيراد (Cron Job)</h2>
            
            <p style="color: #b3b3b3; margin-bottom: 15px;">
                لإعداد الاستيراد التلقائي بشكل كامل، أضف هذا الأمر إلى Cron Job في لوحة تحكم الاستضافة:
            </p>
            
            <div class="cron-command">
                */<?php echo $settings['auto_import_interval']; ?> * * * * php <?php echo __DIR__; ?>/auto-import-tv.php >/dev/null 2>&1
            </div>
            
            <p style="color: #b3b3b3; margin-bottom: 15px;">
                أو استخدم هذا الرابط في Cron Job:
            </p>
            
            <div class="cron-command">
                wget -q -O /dev/null <?php echo (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']; ?>/fayez-movie/admin/auto-import-tv.php?force=1
            </div>
        </div>
        
        <div class="settings-card">
            <h2 class="settings-title"><i class="fas fa-history"></i> آخر عمليات الاستيراد</h2>
            
            <div class="log-box">
                <?php echo nl2br($last_import); ?>
            </div>
        </div>
    </div>
</body>
</html>