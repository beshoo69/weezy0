<?php
// admin/delete-content.php - إدارة وحذف المحتوى من الموقع (نسخة موحدة)
// ====================================================

// ========== تهيئة البيئة ==========
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';


// admin/delete-content.php - إضافة معالجة الحذف الشامل
// ... الكود السابق ...

// معالجة الحذف الشامل
if (isset($_POST['delete_all']) && $_POST['delete_all'] == 'yes' && isset($_POST['confirm_password']) && isset($_POST['confirm_text'])) {
    $password = $_POST['confirm_password'];
    $confirm_text = $_POST['confirm_text'];
    
    // التحقق من كلمة المرور
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!password_verify($password, $user['password'])) {
        $message = "❌ كلمة المرور غير صحيحة";
        $messageType = "error";
    } elseif ($confirm_text !== 'حذف نهائي') {
        $message = "❌ نص التأكيد غير صحيح";
        $messageType = "error";
    } else {
        // تنفيذ الحذف الشامل مع الحفاظ على المستخدمين
        $result = deleteAllContent($pdo);
        $message = $result['message'];
        $messageType = $result['type'];
        
        // تحديث الإحصائيات بعد الحذف
        $stats = [
            'movies' => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
            'series' => $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(),
            'episodes' => $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(),
            'subtitles' => $pdo->query("SELECT COUNT(*) FROM subtitles")->fetchColumn(),
            'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
        ];
    }
}

/**
 * دالة الحذف الشامل - تحذف كل المحتوى مع الحفاظ على المستخدمين
 */
/**
 * دالة الحذف الشامل - تحذف كل المحتوى مع الحفاظ على المستخدمين
 */
function deleteAllContent($pdo) {
    try {
        // 1. حذف الترجمات مع ملفاتها أولاً
        $subtitles = $pdo->query("SELECT subtitle_file FROM subtitles")->fetchAll();
        foreach ($subtitles as $sub) {
            if (!empty($sub['subtitle_file'])) {
                $file_path = __DIR__ . '/../' . $sub['subtitle_file'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
        }
        $pdo->exec("DELETE FROM subtitles");
        
        // 2. حذف الحلقات
        $pdo->exec("DELETE FROM episodes");
        
        // 3. حذف الأفلام
        $pdo->exec("DELETE FROM movies");
        
        // 4. حذف المسلسلات
        $pdo->exec("DELETE FROM series");
        
        // 5. حذف سجل المدفوعات (اختياري)
        $pdo->exec("DELETE FROM payment_history");
        
        // 6. حذف أجهزة المستخدمين (اختياري)
        $pdo->exec("DELETE FROM user_devices");
        
        return [
            'message' => '✅ تم حذف جميع الأفلام والمسلسلات والحلقات والترجمات بنجاح. المستخدمين لم يتأثروا.',
            'type' => 'success'
        ];
        
    } catch (Exception $e) {
        return [
            'message' => '❌ خطأ: ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
}

// التحقق من الصلاحيات
if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// ========== معالجة طلبات الحذف ==========
$message = '';
$messageType = '';

if (isset($_GET['type']) && isset($_GET['id'])) {
    $type = $_GET['type'];
    $id = (int)$_GET['id'];
    $confirm = isset($_GET['confirm']) ? $_GET['confirm'] : false;
    
    if ($confirm === 'yes') {
        $result = processDeletion($pdo, $type, $id);
        $message = $result['message'];
        $messageType = $result['type'];
    }
}

// ========== دوال المساعدة ==========

/**
 * معالجة عملية الحذف حسب النوع
 */
function processDeletion($pdo, $type, $id) {
    try {
        switch ($type) {
            case 'movie':
                return deleteMovie($pdo, $id);
            case 'series':
                return deleteSeries($pdo, $id);
            case 'episode':
                return deleteEpisode($pdo, $id);
            case 'subtitle':
                return deleteSubtitle($pdo, $id);
            case 'user':
                return deleteUser($pdo, $id);
            case 'plan':
                return deletePlan($pdo, $id);
            default:
                return [
                    'message' => '❌ نوع غير معروف',
                    'type' => 'error'
                ];
        }
    } catch (Exception $e) {
        return [
            'message' => '❌ خطأ: ' . $e->getMessage(),
            'type' => 'error'
        ];
    }
}

/**
 * حذف فيلم
 */
function deleteMovie($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM movies WHERE id = ?");
    $stmt->execute([$id]);
    return [
        'message' => '✅ تم حذف الفيلم بنجاح',
        'type' => 'success'
    ];
}

/**
 * حذف مسلسل
 */
function deleteSeries($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM series WHERE id = ?");
    $stmt->execute([$id]);
    return [
        'message' => '✅ تم حذف المسلسل بنجاح',
        'type' => 'success'
    ];
}

/**
 * حذف حلقة
 */
function deleteEpisode($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM episodes WHERE id = ?");
    $stmt->execute([$id]);
    return [
        'message' => '✅ تم حذف الحلقة بنجاح',
        'type' => 'success'
    ];
}

/**
 * حذف ترجمة مع ملفها
 */
function deleteSubtitle($pdo, $id) {
    // جلب مسار الملف
    $get_file = $pdo->prepare("SELECT subtitle_file FROM subtitles WHERE id = ?");
    $get_file->execute([$id]);
    $file = $get_file->fetch();
    
    // حذف الملف الفعلي إذا وجد
    if ($file && !empty($file['subtitle_file'])) {
        $file_path = __DIR__ . '/../' . $file['subtitle_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
    
    // حذف سجل الترجمة
    $stmt = $pdo->prepare("DELETE FROM subtitles WHERE id = ?");
    $stmt->execute([$id]);
    
    return [
        'message' => '✅ تم حذف الترجمة بنجاح',
        'type' => 'success'
    ];
}

/**
 * حذف مستخدم (مع حماية المديرين)
 */
function deleteUser($pdo, $id) {
    // التحقق من أن المستخدم ليس مديراً
    $check = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $check->execute([$id]);
    $user = $check->fetch();
    
    if ($user && $user['role'] == 'admin') {
        return [
            'message' => '❌ لا يمكن حذف حساب مدير',
            'type' => 'error'
        ];
    }
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$id]);
    
    return [
        'message' => '✅ تم حذف المستخدم بنجاح',
        'type' => 'success'
    ];
}

/**
 * حذف خطة عضوية
 */
function deletePlan($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM membership_plans WHERE id = ?");
    $stmt->execute([$id]);
    
    return [
        'message' => '✅ تم حذف خطة العضوية بنجاح',
        'type' => 'success'
    ];
}

// ========== جلب الإحصائيات ==========
$stats = [
    'movies' => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
    'series' => $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(),
    'episodes' => $pdo->query("SELECT COUNT(*) FROM episodes")->fetchColumn(),
    'subtitles' => $pdo->query("SELECT COUNT(*) FROM subtitles")->fetchColumn(),
    'users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn()
];
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حذف المحتوى - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* ===== المتغيرات العامة ===== */
        :root {
            --primary: #e50914;
            --primary-dark: #b20710;
            --success: #27ae60;
            --info: #3498db;
            --warning: #f39c12;
            --dark: #0a0a0a;
            --light: #1a1a1a;
            --lighter: #252525;
            --text: #fff;
            --text-gray: #b3b3b3;
            --border: #333;
        }

        /* ===== الأساسيات ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
            color: var(--text);
            min-height: 100vh;
        }

        /* ===== الهيدر ===== */
        .header {
            background: var(--dark);
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--primary);
        }
        
        .logo h1 {
            color: var(--primary);
            font-size: 28px;
        }
        
        .logo span { color: var(--text); }
        
        .back-link {
            color: var(--text);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }
        
        .back-link:hover { color: var(--primary); }

        /* ===== الحاوية الرئيسية ===== */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* ===== العناوين ===== */
        h1 {
            color: var(--primary);
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ===== التنبيهات ===== */
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(229, 9, 20, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
        }

        /* ===== بطاقات الإحصائيات ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--border);
            text-align: center;
            transition: 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
        }
        
        .stat-label {
            color: var(--text-gray);
            margin-top: 5px;
        }

        /* ===== قسم البحث ===== */
        .search-section {
            background: var(--light);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid var(--border);
        }
        
        .search-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background: var(--lighter);
            border: 1px solid var(--border);
            border-radius: 30px;
            color: var(--text-gray);
            cursor: pointer;
            transition: 0.3s;
            font-weight: 500;
        }
        
        .tab-btn:hover {
            border-color: var(--primary);
            color: var(--text);
        }
        
        .tab-btn.active {
            background: var(--primary);
            color: var(--text);
            border-color: var(--primary);
        }
        
        .search-box {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px 15px;
            background: var(--lighter);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text);
            font-size: 16px;
            transition: 0.3s;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .search-box button {
            padding: 12px 25px;
            background: var(--primary);
            color: var(--text);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.3s;
        }
        
        .search-box button:hover {
            background: var(--primary-dark);
        }

        /* ===== جداول النتائج ===== */
        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--lighter);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .results-table th {
            background: #333;
            color: var(--primary);
            padding: 15px;
            font-weight: 700;
        }
        
        .results-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .results-table tr:hover {
            background: #2a2a2a;
        }

        /* ===== أزرار الإجراءات ===== */
        .delete-btn {
            background: var(--primary);
            color: var(--text);
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            transition: 0.3s;
        }
        
        .delete-btn:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .view-btn {
            background: var(--info);
            color: var(--text);
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 13px;
            display: inline-block;
            margin-right: 5px;
            transition: 0.3s;
        }
        
        .view-btn:hover {
            background: #2980b9;
            transform: scale(1.05);
        }

        /* ===== نافذة تأكيد الحذف ===== */
        .confirm-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .confirm-modal.active {
            display: flex;
        }
        
        .confirm-content {
            background: var(--light);
            border-radius: 15px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            border: 2px solid var(--primary);
            text-align: center;
        }
        
        .confirm-content h2 {
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .confirm-content p {
            color: var(--text-gray);
            margin-bottom: 30px;
            line-height: 1.8;
        }
        
        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .confirm-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: 0.3s;
        }
        
        .confirm-btn-yes {
            background: var(--primary);
            color: var(--text);
        }
        
        .confirm-btn-yes:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }
        
        .confirm-btn-no {
            background: var(--border);
            color: var(--text);
        }
        
        .confirm-btn-no:hover {
            background: #444;
            transform: scale(1.05);
        }

        /* ===== حالات التحميل والفراغ ===== */
        .loading {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
        }
        
        .loading i {
            font-size: 40px;
            margin-bottom: 10px;
            color: var(--primary);
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-gray);
            background: var(--lighter);
            border-radius: 10px;
        }

        /* ===== التجاوب مع الشاشات الصغيرة ===== */
        @media (max-width: 768px) {
            .search-tabs {
                flex-direction: column;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .results-table {
                font-size: 14px;
            }
            
            .results-table th,
            .results-table td {
                padding: 8px 5px;
            }
        }
    </style>
</head>
<body>
    <!-- ===== الهيدر ===== -->
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <a href="dashboard-pro.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة للوحة التحكم
        </a>
    </div>
    
    <!-- ===== المحتوى الرئيسي ===== -->
    <div class="container">
        <!-- عنوان الصفحة -->
        <h1>
            <i class="fas fa-trash-alt" style="color: var(--primary);"></i>
            حذف المحتوى من الموقع
        </h1>
        
        <!-- رسائل التنبيه -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <i class="fas <?php echo $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- بطاقات الإحصائيات -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-film" style="font-size: 40px; color: var(--primary); margin-bottom: 10px;"></i>
                <div class="stat-number"><?php echo $stats['movies']; ?></div>
                <div class="stat-label">أفلام</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-tv" style="font-size: 40px; color: var(--primary); margin-bottom: 10px;"></i>
                <div class="stat-number"><?php echo $stats['series']; ?></div>
                <div class="stat-label">مسلسلات</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-play-circle" style="font-size: 40px; color: var(--primary); margin-bottom: 10px;"></i>
                <div class="stat-number"><?php echo $stats['episodes']; ?></div>
                <div class="stat-label">حلقات</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-closed-captioning" style="font-size: 40px; color: var(--primary); margin-bottom: 10px;"></i>
                <div class="stat-number"><?php echo $stats['subtitles']; ?></div>
                <div class="stat-label">ترجمات</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-users" style="font-size: 40px; color: var(--primary); margin-bottom: 10px;"></i>
                <div class="stat-number"><?php echo $stats['users']; ?></div>
                <div class="stat-label">مستخدمين</div>
            </div>
        </div>
        
        <!-- نافذة تأكيد الحذف -->
        <div class="confirm-modal" id="confirmModal">
            <div class="confirm-content">
                <h2><i class="fas fa-exclamation-triangle"></i> تأكيد الحذف</h2>
                <p id="confirmMessage">هل أنت متأكد من حذف هذا العنصر؟</p>
                <div class="confirm-buttons">
                    <a href="#" id="confirmYes" class="confirm-btn confirm-btn-yes">
                        <i class="fas fa-check"></i> نعم، احذف
                    </a>
                    <button onclick="closeConfirmModal()" class="confirm-btn confirm-btn-no">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </div>
        </div>
        
        <!-- قسم البحث والحذف -->
        <div class="search-section">
            <!-- تبويبات أنواع المحتوى -->
            <div class="search-tabs">
                <button class="tab-btn active" onclick="showTab('movies')">🎬 أفلام</button>
                <button class="tab-btn" onclick="showTab('series')">📺 مسلسلات</button>
                <button class="tab-btn" onclick="showTab('episodes')">🎬 حلقات</button>
                <button class="tab-btn" onclick="showTab('subtitles')">📝 ترجمات</button>
                <button class="tab-btn" onclick="showTab('users')">👤 مستخدمين</button>
                <button class="tab-btn" onclick="showTab('plans')">👑 خطط العضوية</button>
            </div>
            
            <!-- تبويب الأفلام -->
            <div id="movies-tab" class="tab-content">
                <div class="search-box">
                    <input type="text" id="movieSearch" placeholder="ابحث عن فيلم بالاسم أو السنة أو التصنيف..." autocomplete="off">
                    <button onclick="searchMovies()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="moviesResults"></div>
            </div>
            
            <!-- تبويب المسلسلات -->
            <div id="series-tab" class="tab-content" style="display: none;">
                <div class="search-box">
                    <input type="text" id="seriesSearch" placeholder="ابحث عن مسلسل بالاسم أو السنة...">
                    <button onclick="searchSeries()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="seriesResults"></div>
            </div>
            
            <!-- تبويب الحلقات -->
            <div id="episodes-tab" class="tab-content" style="display: none;">
                <div class="search-box">
                    <input type="text" id="episodeSearch" placeholder="ابحث عن حلقة برقمها أو اسم المسلسل...">
                    <button onclick="searchEpisodes()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="episodesResults"></div>
            </div>
            
            <!-- تبويب الترجمات -->
            <div id="subtitles-tab" class="tab-content" style="display: none;">
                <div class="search-box">
                    <input type="text" id="subtitleSearch" placeholder="ابحث عن ترجمة بلغة أو اسم المحتوى...">
                    <button onclick="searchSubtitles()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="subtitlesResults"></div>
            </div>
            
            <!-- تبويب المستخدمين -->
            <div id="users-tab" class="tab-content" style="display: none;">
                <div class="search-box">
                    <input type="text" id="userSearch" placeholder="ابحث عن مستخدم بالاسم أو البريد...">
                    <button onclick="searchUsers()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="usersResults"></div>
            </div>
            
            <!-- تبويب خطط العضوية -->
            <div id="plans-tab" class="tab-content" style="display: none;">
                <div class="search-box">
                    <input type="text" id="planSearch" placeholder="ابحث عن خطة بالاسم...">
                    <button onclick="searchPlans()"><i class="fas fa-search"></i> بحث</button>
                </div>
                <div id="plansResults"></div>
            </div>
            <!-- ===== قسم الحذف الشامل (بدون مستخدمين) ===== -->
<div class="danger-zone" style="margin-bottom: 30px; background: #2a1a1a; border: 2px solid #e50914; border-radius: 15px; padding: 25px;">
    <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #e50914;"></i>
        <h2 style="color: #e50914; margin: 0;">⚠️ منطقة الخطر - حذف جميع المحتويات</h2>
    </div>
    
    <p style="color: #b3b3b3; margin-bottom: 20px; line-height: 1.8;">
        هذا الإجراء لا يمكن التراجع عنه. سيتم حذف <strong>جميع الأفلام والمسلسلات والحلقات والترجمات</strong> من الموقع نهائياً.
        <br>
        <span style="color: #27ae60; font-weight: bold;">✅ المستخدمين سيتم الحفاظ عليهم بالكامل.</span>
    </p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; text-align: center;">
            <div style="font-size: 24px; font-weight: 800; color: #e50914;"><?php echo $stats['movies']; ?></div>
            <div style="color: #b3b3b3;">فيلم</div>
        </div>
        <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; text-align: center;">
            <div style="font-size: 24px; font-weight: 800; color: #e50914;"><?php echo $stats['series']; ?></div>
            <div style="color: #b3b3b3;">مسلسل</div>
        </div>
        <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; text-align: center;">
            <div style="font-size: 24px; font-weight: 800; color: #e50914;"><?php echo $stats['episodes']; ?></div>
            <div style="color: #b3b3b3;">حلقة</div>
        </div>
        <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; text-align: center;">
            <div style="font-size: 24px; font-weight: 800; color: #e50914;"><?php echo $stats['subtitles']; ?></div>
            <div style="color: #b3b3b3;">ترجمة</div>
        </div>
        <div style="background: #1a1a1a; padding: 15px; border-radius: 10px; text-align: center; opacity: 0.7;">
            <div style="font-size: 24px; font-weight: 800; color: #27ae60;"><?php echo $stats['users']; ?></div>
            <div style="color: #27ae60;">مستخدم (محفوظ)</div>
        </div>
    </div>
    
    <!-- زر الحذف الشامل الوحيد -->
    <div style="display: flex; justify-content: center;">
        <button onclick="showDeleteAllModal()" class="danger-btn" style="background: #e50914; color: white; padding: 15px 40px; border: none; border-radius: 50px; cursor: pointer; font-weight: bold; font-size: 18px; display: flex; align-items: center; gap: 10px; box-shadow: 0 5px 20px rgba(229,9,20,0.5);">
            <i class="fas fa-trash-alt"></i> حذف كل المحتوى (ما عدا المستخدمين)
        </button>
    </div>
</div>

<!-- نافذة تأكيد الحذف الشامل -->
<div class="confirm-modal" id="deleteAllModal">
    <div class="confirm-content" style="max-width: 500px;">
        <h2 style="color: #e50914; margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i> تأكيد الحذف الشامل
        </h2>
        
        <div id="deleteAllMessage" style="color: #fff; margin-bottom: 25px; line-height: 1.8;">
            <div style="background: #2a1a1a; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                <p style="color: #e50914; font-size: 18px; margin-bottom: 10px;">⚠️ أنت على وشك حذف كل المحتوى</p>
                <p style="color: #fff;">عدد العناصر التي سيتم حذفها:</p>
                <ul style="list-style: none; padding: 0; margin-top: 10px;">
                    <li style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span>🎬 أفلام:</span> <strong style="color: #e50914;"><?php echo $stats['movies']; ?></strong>
                    </li>
                    <li style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span>📺 مسلسلات:</span> <strong style="color: #e50914;"><?php echo $stats['series']; ?></strong>
                    </li>
                    <li style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span>🎬 حلقات:</span> <strong style="color: #e50914;"><?php echo $stats['episodes']; ?></strong>
                    </li>
                    <li style="display: flex; justify-content: space-between; padding: 5px 0;">
                        <span>📝 ترجمات:</span> <strong style="color: #e50914;"><?php echo $stats['subtitles']; ?></strong>
                    </li>
                    <li style="display: flex; justify-content: space-between; padding: 5px 0; border-top: 1px solid #333; margin-top: 5px; padding-top: 10px;">
                        <span style="color: #27ae60;">👤 مستخدمين:</span> <strong style="color: #27ae60;"><?php echo $stats['users']; ?> (سيتم الحفاظ عليهم)</strong>
                    </li>
                </ul>
                <p style="color: #b3b3b3; font-size: 14px; margin-top: 10px;">هذا الإجراء لا يمكن التراجع عنه!</p>
            </div>
        </div>
        
        <!-- حقل إدخال كلمة المرور للتأكيد -->
        <div style="margin-bottom: 25px;">
            <label style="color: #b3b3b3; display: block; margin-bottom: 8px;">🔐 أدخل كلمة المرور لتأكيد العملية:</label>
            <input type="password" id="confirmPassword" placeholder="كلمة المرور" style="width: 100%; padding: 12px; background: #252525; border: 1px solid #333; border-radius: 5px; color: #fff;">
        </div>
        
        <!-- حقل إدخال النص للتأكيد -->
        <div style="margin-bottom: 25px;">
            <label style="color: #b3b3b3; display: block; margin-bottom: 8px;">✍️ اكتب "حذف نهائي" للتأكيد:</label>
            <input type="text" id="confirmText" placeholder="حذف نهائي" style="width: 100%; padding: 12px; background: #252525; border: 1px solid #333; border-radius: 5px; color: #fff;">
        </div>
        
        <div class="confirm-buttons" style="gap: 15px;">
            <button onclick="executeDeleteAll()" class="confirm-btn confirm-btn-yes" style="flex: 1; background: #e50914;">
                <i class="fas fa-check"></i> نعم، احذف كل شيء
            </button>
            <button onclick="closeDeleteAllModal()" class="confirm-btn confirm-btn-no" style="flex: 1;">
                <i class="fas fa-times"></i> إلغاء
            </button>
        </div>
    </div>
</div>
        </div>
    </div>
    
    <!-- ===== جافاسكريبت ===== -->
    <script>
        // ========== المتغيرات العامة ==========
        let currentDeleteType = '';
        let currentDeleteId = 0;

        // ========== دوال التبويبات ==========

        /**
         * تبديل التبويبات
         */
        function showTab(tab) {
            // إخفاء كل التبويبات
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // إظهار التبويب المحدد
            document.getElementById(tab + '-tab').style.display = 'block';
            
            // تحديث حالة الأزرار
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // تحميل البيانات الافتراضية
            loadInitialData(tab);
        }

        /**
         * تحميل البيانات الأولية حسب التبويب
         */
        function loadInitialData(tab) {
            switch(tab) {
                case 'movies': loadInitialMovies(); break;
                case 'series': loadInitialSeries(); break;
                case 'episodes': loadInitialEpisodes(); break;
                case 'subtitles': loadInitialSubtitles(); break;
                case 'users': loadInitialUsers(); break;
                case 'plans': loadInitialPlans(); break;
            }
        }

        // ========== دوال تحميل البيانات الأولية ==========

        function loadInitialMovies() {
            showLoading('moviesResults');
            fetch('ajax-search.php?type=movies&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('moviesResults').innerHTML = data;
                });
        }

        function loadInitialSeries() {
            showLoading('seriesResults');
            fetch('ajax-search.php?type=series&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('seriesResults').innerHTML = data;
                });
        }

        function loadInitialEpisodes() {
            showLoading('episodesResults');
            fetch('ajax-search.php?type=episodes&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('episodesResults').innerHTML = data;
                });
        }

        function loadInitialSubtitles() {
            showLoading('subtitlesResults');
            fetch('ajax-search.php?type=subtitles&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('subtitlesResults').innerHTML = data;
                });
        }

        function loadInitialUsers() {
            showLoading('usersResults');
            fetch('ajax-search.php?type=users&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('usersResults').innerHTML = data;
                });
        }

        function loadInitialPlans() {
            showLoading('plansResults');
            fetch('ajax-search.php?type=plans&q=')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('plansResults').innerHTML = data;
                });
        }

        /**
         * إظهار مؤشر التحميل
         */
        function showLoading(elementId) {
            document.getElementById(elementId).innerHTML = 
                '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>جاري التحميل...</div>';
        }

        // ========== دوال البحث ==========

        function searchMovies() {
            let query = document.getElementById('movieSearch').value;
            showLoading('moviesResults');
            
            fetch('ajax-search.php?type=movies&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('moviesResults').innerHTML = data;
                });
        }

        function searchSeries() {
            let query = document.getElementById('seriesSearch').value;
            showLoading('seriesResults');
            
            fetch('ajax-search.php?type=series&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('seriesResults').innerHTML = data;
                });
        }

        function searchEpisodes() {
            let query = document.getElementById('episodeSearch').value;
            showLoading('episodesResults');
            
            fetch('ajax-search.php?type=episodes&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('episodesResults').innerHTML = data;
                });
        }

        function searchSubtitles() {
            let query = document.getElementById('subtitleSearch').value;
            showLoading('subtitlesResults');
            
            fetch('ajax-search.php?type=subtitles&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('subtitlesResults').innerHTML = data;
                });
        }

        function searchUsers() {
            let query = document.getElementById('userSearch').value;
            showLoading('usersResults');
            
            fetch('ajax-search.php?type=users&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('usersResults').innerHTML = data;
                });
        }

        function searchPlans() {
            let query = document.getElementById('planSearch').value;
            showLoading('plansResults');
            
            fetch('ajax-search.php?type=plans&q=' + encodeURIComponent(query))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('plansResults').innerHTML = data;
                });
        }

        // ========== دوال تأكيد الحذف ==========

        /**
         * فتح نافذة تأكيد الحذف
         */
        function confirmDelete(type, id, name) {
            currentDeleteType = type;
            currentDeleteId = id;
            
            let typeName = '';
            switch(type) {
                case 'movie': typeName = 'الفيلم'; break;
                case 'series': typeName = 'المسلسل'; break;
                case 'episode': typeName = 'الحلقة'; break;
                case 'subtitle': typeName = 'الترجمة'; break;
                case 'user': typeName = 'المستخدم'; break;
                case 'plan': typeName = 'الخطة'; break;
            }
            
            document.getElementById('confirmMessage').innerHTML = 
                `هل أنت متأكد من حذف ${typeName} <strong style="color: #e50914;">${name}</strong>؟`;
            
            document.getElementById('confirmYes').href = 
                `?type=${type}&id=${id}&confirm=yes`;
            
            document.getElementById('confirmModal').classList.add('active');
        }

        /**
         * إغلاق نافذة تأكيد الحذف
         */
        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        // ========== أحداث التحميل ==========

        /**
         * تهيئة الصفحة عند التحميل
         */
        window.onload = function() {
            loadInitialMovies();
        };

        /**
         * إغلاق النافذة عند الضغط خارجها
         */
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        };
        // ========== دوال الحذف الشامل ==========

/**
 * فتح نافذة الحذف الشامل
 */
function showDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.add('active');
    
    // تفريغ الحقول
    document.getElementById('confirmPassword').value = '';
    document.getElementById('confirmText').value = '';
}

/**
 * إغلاق نافذة الحذف الشامل
 */
function closeDeleteAllModal() {
    document.getElementById('deleteAllModal').classList.remove('active');
}

/**
 * تنفيذ الحذف الشامل
 */
function executeDeleteAll() {
    const password = document.getElementById('confirmPassword').value;
    const confirmText = document.getElementById('confirmText').value;
    
    if (!password) {
        alert('❌ الرجاء إدخال كلمة المرور');
        return;
    }
    
    if (confirmText !== 'حذف نهائي') {
        alert('❌ الرجاء كتابة "حذف نهائي" بشكل صحيح');
        return;
    }
    
    // تأكيد نهائي
    if (!confirm('⚠️ تحذير نهائي: هل أنت متأكد تماماً من حذف كل المحتوى؟ هذا الإجراء لا يمكن التراجع عنه!')) {
        return;
    }
    
    // إنشاء نموذج وإرساله
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const input1 = document.createElement('input');
    input1.type = 'hidden';
    input1.name = 'delete_all';
    input1.value = 'yes';
    
    const input2 = document.createElement('input');
    input2.type = 'hidden';
    input2.name = 'confirm_password';
    input2.value = password;
    
    const input3 = document.createElement('input');
    input3.type = 'hidden';
    input3.name = 'confirm_text';
    input3.value = confirmText;
    
    form.appendChild(input1);
    form.appendChild(input2);
    form.appendChild(input3);
    document.body.appendChild(form);
    form.submit();
}

// تحديث دالة إغلاق النوافذ
window.onclick = function(event) {
    const modal1 = document.getElementById('confirmModal');
    const modal2 = document.getElementById('deleteAllModal');
    
    if (event.target == modal1) {
        modal1.classList.remove('active');
    }
    if (event.target == modal2) {
        modal2.classList.remove('active');
    }
}
    </script>
</body>
</html>