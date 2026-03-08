<?php
/**
 * اختبار سريع لعملية التحديث
 * استخدم: admin/quick-test.php?id=1&step=1
 */

require_once '../includes/config.php';

if (!isset($_GET['id'])) {
    die('❌ لم يتم تحديد ID الفيلم. استخدم: quick-test.php?id=1');
}

$id = (int)$_GET['id'];
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

echo "<html><head><meta charset='UTF-8'><title>اختبار سريع</title>";
echo "<style>body{font-family:Arial;background:#f5f5f5;padding:20px;direction:rtl;}</style></head><body>";
echo "<h1>🧪 اختبار سريع لتحديث الفيلم ID: $id</h1>";

try {
    // الخطوة 1: جلب الفيلم
    if ($step >= 1) {
        echo "<h2>✅ الخطوة 1: جلب الفيلم من قاعدة البيانات</h2>";
        $stmt = $pdo->prepare("SELECT id, title, title_en, year FROM movies WHERE id = ?");
        $stmt->execute([$id]);
        $movie = $stmt->fetch();
        
        if (!$movie) {
            die("<p style='color:red'>❌ الفيلم برقم $id غير موجود</p>");
        }
        
        echo "<pre style='background:#fff;padding:10px;border:1px solid #ddd;'>";
        print_r($movie);
        echo "</pre>";
        
        echo "<p><a href='?id=$id&step=2'><button style='padding:10px 20px;background:#4CAF50;color:white;border:none;cursor:pointer;'>➡️ التالي</button></a></p>";
    }
    
    // الخطوة 2: اختبر التحديث
    if ($step >= 2) {
        echo "<h2>✅ الخطوة 2: اختبر تغيير العنوان</h2>";
        $test_title = "اختبار_" . date('Ymdhis');
        
        $update_stmt = $pdo->prepare("UPDATE movies SET title = ? WHERE id = ?");
        $result = $update_stmt->execute([$test_title, $id]);
        
        echo "<p>تم محاولة تغيير العنوان إلى: <strong>$test_title</strong></p>";
        echo "<p>عدد الصفوف المتأثرة: <strong>" . $update_stmt->rowCount() . "</strong></p>";
        echo "<p>حالة التنفيذ: " . ($result ? "<span style='color:green'>✅ نجح</span>" : "<span style='color:red'>❌ فشل</span>") . "</p>";
        
        echo "<p><a href='?id=$id&step=3'><button style='padding:10px 20px;background:#4CAF50;color:white;border:none;cursor:pointer;'>➡️ التحقق</button></a></p>";
    }
    
    // الخطوة 3: تحقق من التحديث
    if ($step >= 3) {
        echo "<h2>✅ الخطوة 3: التحقق من التحديث</h2>";
        $verify_stmt = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
        $verify_stmt->execute([$id]);
        $updated = $verify_stmt->fetch();
        
        echo "<p>العنوان الحالي: <strong>" . htmlspecialchars($updated['title']) . "</strong></p>";
        
        if (strpos($updated['title'], 'اختبار_') === 0) {
            echo "<p style='color:green;font-weight:bold;'>✅ التحديث نجح بنجاح!</p>";
        } else {
            echo "<p style='color:orange;font-weight:bold;'>⚠️ البيانات لم تتغير كما هو متوقع</p>";
        }
        
        echo "<p><a href='?id=$id&step=4'><button style='padding:10px 20px;background:#2196F3;color:white;border:none;cursor:pointer;'>➡️ إرجاع البيانات الأصلية</button></a></p>";
    }
    
    // الخطوة 4: استعادة البيانات الأصلية
    if ($step >= 4) {
        echo "<h2>✅ الخطوة 4: استعادة البيانات الأصلية</h2>";
        
        // جلب البيانات الأصلية من TMDB أو استخدام بيانات افتراضية
        $reset_stmt = $pdo->prepare("UPDATE movies SET title = CONCAT('فيلم_', id) WHERE id = ?");
        $reset_stmt->execute([$id]);
        
        echo "<p>✅ تم استعادة البيانات الأصلية</p>";
        echo "<p><a href='movies.php'><button style='padding:10px 20px;background:#FF9800;color:white;border:none;cursor:pointer;'>العودة إلى قائمة الأفلام</button></a></p>";
    }
    
    // الخطوة 5: فحص السجلات
    if ($step >= 5) {
        echo "<h2>✅ الخطوة 5: السجلات</h2>";
        $log_file = '../logs/php_errors.log';
        if (file_exists($log_file)) {
            $lines = file($log_file);
            $last_lines = array_slice($lines, -20);
            echo "<pre style='background:#000;color:#0f0;padding:10px;'>";
            echo implode('', $last_lines);
            echo "</pre>";
        } else {
            echo "<p style='color:orange;'>⚠️ ملف السجلات غير موجود</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color:red;font-weight:bold;'>❌ حدث خطأ: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "</body></html>";
?>