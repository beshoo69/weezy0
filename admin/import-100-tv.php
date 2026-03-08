<?php
// admin/series/import-100-tv.php
// admin/import-100-tv.php - استيراد 100 مسلسل
require_once __DIR__ . '/../includes/config.php';        // ✅ اتصال قاعدة البيانات
require_once __DIR__ . '/../includes/functions.php';     // ✅ الدوال العامة (فيها isLoggedIn)
require_once __DIR__ . '/../includes/tmdb.php';         // ✅ مسار صحيح

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}
$message = '';
$error = '';
$imported = 0;
$skipped = 0;

if (isset($_POST['import']) || isset($_GET['import'])) {
    
    // جلب 5 صفحات = 100 مسلسل
    $all_tv = getPopularTv(5);
    $total = count($all_tv);
    
    foreach ($all_tv as $tv) {
        if (!isset($tv['id'])) continue;
        
        // تحقق من وجود المسلسل
        $check = $pdo->prepare("SELECT id FROM series WHERE tmdb_id = ?");
        $check->execute([$tv['id']]);
        
        if (!$check->fetch()) {
            $title = $tv['name'] ?? 'بدون عنوان';
            $description = $tv['overview'] ?? 'لا يوجد وصف';
            $poster = isset($tv['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $tv['poster_path'] : null;
            $backdrop = isset($tv['backdrop_path']) ? 'https://image.tmdb.org/t/p/original' . $tv['backdrop_path'] : null;
            $year = isset($tv['first_air_date']) ? substr($tv['first_air_date'], 0, 4) : date('Y');
            $rating = $tv['vote_average'] ?? 0;
            
            $sql = "INSERT INTO series (tmdb_id, title, description, poster, backdrop, year, imdb_rating, seasons, status, views) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'ongoing', 0)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tv['id'], $title, $description, $poster, $backdrop, $year, $rating]);
            $imported++;
        } else {
            $skipped++;
        }
    }
    
    $message = "✅ تم استيراد {$imported} مسلسل من أصل {$total}، وتخطي {$skipped} مسلسل موجود";
}

// إحصائيات
$total_series = $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn();
?>
<style>
    /* assets/css/admin.css - تنسيقات لوحة التحكم */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Tajawal', sans-serif;
    background: #0f0f0f;
    color: #fff;
}

.dashboard {
    display: flex;
    min-height: 100vh;
}

/* ===== الشريط الجانبي ===== */
.sidebar {
    width: 280px;
    background: #0a0a0a;
    position: fixed;
    right: 0;
    top: 0;
    bottom: 0;
    padding: 30px 20px;
    border-left: 1px solid #1f1f1f;
    overflow-y: auto;
}

.logo {
    color: #e50914;
    font-size: 28px;
    font-weight: 800;
    text-align: center;
    margin-bottom: 40px;
}

.logo span {
    color: #fff;
}

.nav-section {
    color: #b3b3b3;
    font-size: 14px;
    margin: 20px 0 10px 0;
    padding-right: 10px;
}

.sidebar nav a {
    display: flex;
    align-items: center;
    padding: 12px 15px;
    color: #b3b3b3;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: 0.3s;
    gap: 10px;
}

.sidebar nav a:hover,
.sidebar nav a.active {
    background: #e50914;
    color: white;
}

.sidebar nav a i {
    width: 20px;
    text-align: center;
}

/* ===== المحتوى الرئيسي ===== */
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
    border-radius: 12px;
}

.header h1 {
    color: #e50914;
    font-size: 24px;
}

.date {
    color: #b3b3b3;
}

/* ===== بطاقات الإحصائيات ===== */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}

.stat-card {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    border: 1px solid #333;
    transition: 0.3s;
}

.stat-card:hover {
    border-color: #e50914;
    transform: translateY(-3px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: rgba(229, 9, 20, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #e50914;
}

.stat-number {
    font-size: 32px;
    font-weight: 800;
    color: #e50914;
    line-height: 1;
}

.stat-label {
    color: #b3b3b3;
    margin-top: 5px;
}

/* ===== إجراءات سريعة ===== */
.quick-actions {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 40px;
}

.quick-actions h2 {
    color: #e50914;
    margin-bottom: 20px;
    font-size: 20px;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
}

.action-card {
    background: #252525;
    padding: 20px;
    border-radius: 10px;
    text-decoration: none;
    color: #fff;
    text-align: center;
    transition: 0.3s;
    border: 1px solid #333;
}

.action-card:hover {
    border-color: #e50914;
    transform: translateY(-3px);
}

.action-card i {
    font-size: 32px;
    color: #e50914;
    margin-bottom: 10px;
}

.action-card h3 {
    font-size: 16px;
    margin-bottom: 5px;
}

.action-card p {
    color: #b3b3b3;
    font-size: 13px;
}

/* ===== الجداول ===== */
.recent-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
}

.recent-movies,
.recent-series {
    background: #1a1a1a;
    border-radius: 12px;
    padding: 25px;
}

.recent-movies h2,
.recent-series h2 {
    color: #e50914;
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: right;
    padding: 12px;
    color: #b3b3b3;
    font-weight: 500;
    border-bottom: 2px solid #333;
}

td {
    padding: 12px;
    border-bottom: 1px solid #333;
}

tr:hover td {
    background: #252525;
}

/* ===== التنبيهات ===== */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #27ae60;
    color: white;
}

.alert-error {
    background: #e50914;
    color: white;
}

.alert-info {
    background: #3498db;
    color: white;
}

/* ===== الأزرار ===== */
.btn {
    display: inline-block;
    padding: 10px 20px;
    background: #2a2a2a;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    transition: 0.3s;
    font-size: 14px;
}

.btn-primary {
    background: #e50914;
}

.btn-primary:hover {
    background: #b20710;
}

.btn-large {
    padding: 15px 30px;
    font-size: 16px;
}

/* ===== بطاقات الاستيراد ===== */
.import-card {
    background: linear-gradient(145deg, #1a1a1a, #151515);
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 30px;
    border: 1px solid #e50914;
}

.import-card h2 {
    color: #e50914;
    margin-bottom: 15px;
}

.import-card p {
    color: #b3b3b3;
    margin-bottom: 25px;
}

.info-box {
    background: #252525;
    border-radius: 8px;
    padding: 20px;
    margin-top: 25px;
}

.info-box h4 {
    color: #e50914;
    margin-bottom: 10px;
}

.info-box ul {
    list-style: none;
    padding-right: 20px;
}

.info-box li {
    color: #b3b3b3;
    margin-bottom: 8px;
}

/* ===== التجاوب ===== */
@media (max-width: 1024px) {
    .recent-section {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sidebar {
        display: none;
    }
    .main-content {
        margin-right: 0;
    }
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>استيراد 100 مسلسل - ويزي برو</title>
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="dashboard">
        
        
        <main class="main-content">
            <div class="header">
                <h1><i class="fas fa-tv"></i> استيراد 100 مسلسل</h1>
                <div>📺 إجمالي المسلسلات: <strong><?php echo $total_series; ?></strong></div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="import-card">
                <h2>استيراد 100 مسلسل رائج من TMDB</h2>
                <p>سيتم جلب أحدث 100 مسلسل من TMDB وإضافتها إلى قاعدة البيانات</p>
                
                <form method="POST">
                    <button type="submit" name="import" class="btn btn-primary btn-large">
                        <i class="fas fa-download"></i> بدء استيراد 100 مسلسل
                    </button>
                </form>
                
                <div class="info-box">
                    <h4>معلومات:</h4>
                    <ul>
                        <li>⏱️ المدة المتوقعة: 10-20 ثانية</li>
                        <li>📦 عدد المسلسلات: 100 مسلسل بالضبط</li>
                        <li>🔄 المسلسلات المكررة تتخطى تلقائياً</li>
                        <li>🎬 يتم استيراد المسلسلات بدون حلقات (للحلقات استخدم استيراد الموسم)</li>
                    </ul>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total_series; ?></div>
                    <div class="stat-label">مسلسل في قاعدة البيانات</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">100</div>
                    <div class="stat-label">مسلسل سيتم استيرادهم</div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>