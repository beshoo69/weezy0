<?php
/**
 * تقرير تشخيص ديناميكي لمشكلة عدم تحديث البيانات في edit-movie.php
 * 
 * المشكلة: البيانات لا تتم تحديثها في قاعدة البيانات
 * 
 * الخطوات المتخذة للحل:
 * 1. إضافة سجلات تفصيلية (error_log) لتتبع عملية التحديث
 * 2. إضافة فحص post data عند بدء معالجة النموذج
 * 3. إضافة verification للتحديث بعد التنفيذ
 * 4. إضافة رسائل خطأ واضحة للمستخدم
 */

require_once '../includes/config.php';

// تحقق من الأخطاء الشائعة
$issues = [];

// 1. تحقق من الاتصال بقاعدة البيانات
try {
    $test = $pdo->query("SELECT 1");
    $success_msgs[] = "✅ اتصال قاعدة البيانات يعمل بشكل طبيعي";
} catch (Exception $e) {
    $issues[] = "❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage();
}

// 2. تحقق من وجود جدول movies
try {
    $result = $pdo->query("DESCRIBE movies");
    $columns = $result->fetchAll();
    $success_msgs[] = "✅ جدول movies موجود ويحتوي على " . count($columns) . " عمود";
    
    // تحقق من الأعمدة المطلوبة
    $required_columns = ['id', 'title', 'title_en', 'description', 'year', 'country', 'language', 
                         'genre', 'duration', 'imdb_rating', 'membership_level', 'status'];
    $missing = [];
    $existing_cols = array_column($columns, 'Field');
    
    foreach ($required_columns as $col) {
        if (!in_array($col, $existing_cols)) {
            $missing[] = $col;
        }
    }
    
    if (!empty($missing)) {
        $issues[] = "❌ أعمدة ناقصة: " . implode(', ', $missing);
    } else {
        $success_msgs[] = "✅ جميع الأعمدة المطلوبة موجودة";
    }
    
} catch (Exception $e) {
    $issues[] = "❌ فشل التحقق من جدول movies: " . $e->getMessage();
}

// 3. اختبر عملية التحديث
if (isset($_GET['test_movie_id'])) {
    $test_id = (int)$_GET['test_movie_id'];
    
    // احصل على الفيلم
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ? LIMIT 1");
    $stmt->execute([$test_id]);
    $movie = $stmt->fetch();
    
    if ($movie) {
        $original_title = $movie['title'];
        $test_title = "اختبار_" . date('Ymdhis');
        
        // حاول التحديث
        try {
            $pdo->beginTransaction();
            
            $update_stmt = $pdo->prepare("UPDATE movies SET title = ? WHERE id = ?");
            $result = $update_stmt->execute([$test_title, $test_id]);
            
            if ($result && $update_stmt->rowCount() > 0) {
                // تحقق من التحديث
                $verify = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
                $verify->execute([$test_id]);
                $verified = $verify->fetch();
                
                if ($verified['title'] === $test_title) {
                    $success_msgs[] = "✅ اختبار التحديث: نجح - تم تحديث العنوان من '{$original_title}' إلى '{$test_title}'";
                    
                    // أرجع الفيلم إلى حالته الأصلية
                    $pdo->prepare("UPDATE movies SET title = ? WHERE id = ?")->execute([$original_title, $test_id]);
                    $pdo->commit();
                } else {
                    $issues[] = "❌ التحديث تم لكن الفحص أظهر عدم التغير";
                }
            } else {
                $pdo->rollBack();
                $issues[] = "❌ اختبار التحديث فشل - لم يتم تحديث أي صفوف";
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $issues[] = "❌ خطأ في اختبار التحديث: " . $e->getMessage();
        }
    } else {
        $issues[] = "❌ الفيلم برقم {$test_id} غير موجود";
    }
}

// 4. اختبر أذونات المجلدات
$folders_to_check = [
    '../uploads/posters/',
    '../uploads/backdrops/',
    '../logs/'
];

foreach ($folders_to_check as $folder) {
    if (is_dir($folder)) {
        if (is_writable($folder)) {
            $success_msgs[] = "✅ المجلد '{$folder}' موجود وقابل للكتابة";
        } else {
            $issues[] = "⚠️ المجلد '{$folder}' موجود لكن غير قابل للكتابة";
        }
    } else {
        if (@mkdir($folder, 0777, true)) {
            $success_msgs[] = "✅ تم إنشاء المجلد '{$folder}' بنجاح";
        } else {
            $issues[] = "⚠️ المجلد '{$folder}' غير موجود وفشل إنشاؤه";
        }
    }
}

// 5. فحص ملف السجلات
$log_file = '../logs/php_errors.log';
if (file_exists($log_file)) {
    $success_msgs[] = "✅ ملف السجلات موجود";
} else {
    $issues[] = "⚠️ ملف السجلات غير موجود - قد لا يتم تسجيل الأخطاء";
}

?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير تشخيصي - تحديث الفيلم</title>
    <style>
        body {
            font-family: 'Tajawal', Arial, sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 20px;
            margin: 0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #1a1a1a;
            border-radius: 10px;
            padding: 30px;
            border: 2px solid #333;
        }
        h1 {
            color: #e50914;
            border-bottom: 3px solid #e50914;
            padding-bottom: 15px;
            margin-top: 0;
        }
        h2 {
            color: #fff;
            margin-top: 25px;
            margin-bottom: 10px;
            border-right: 4px solid #e50914;
            padding-right: 10px;
        }
        .success, .issue {
            padding: 12px;
            margin: 8px 0;
            border-radius: 5px;
        }
        .success {
            background: #145a32;
            border-right: 4px solid #27ae60;
        }
        .issue {
            background: #78281f;
            border-right: 4px solid #e74c3c;
        }
        .action-buttons {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #333;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #e50914;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-weight: bold;
        }
        .btn:hover {
            background: #b20710;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 تقرير تشخيصي - مشكلة تحديث الفيلم</h1>
        
        <?php if (!empty($success_msgs)): ?>
        <h2>✅ النقاط الإيجابية:</h2>
        <?php foreach ($success_msgs as $msg): ?>
            <div class="success"><?php echo $msg; ?></div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (!empty($issues)): ?>
        <h2>⚠️ المشاكل المكتشفة:</h2>
        <?php foreach ($issues as $issue): ?>
            <div class="issue"><?php echo $issue; ?></div>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <h2>🧪 الخطوات التالية:</h2>
        <ol>
            <li>تحقق من ملفات السجل (logs) لمعرفة الأخطاء التفصيلية</li>
            <li>استخدم debug mode في edit-movie.php بإضافة &debug=1 إلى URL</li>
            <li>اختبر التحديث باستخدام السكريبت الاختياري أدناه</li>
            <li>تحقق من أن المستخدم لديه أذونات كافية للكتابة في قاعدة البيانات</li>
        </ol>
        
        <div class="action-buttons">
            <a href="edit-movie.php?id=1&debug=1" class="btn">اختبر edit-movie.php مع Debug</a>
            <a href="?test_movie_id=1" class="btn">اختبر التحديث مباشرة</a>
            <a href="movies.php" class="btn">العودة إلى قائمة الأفلام</a>
        </div>
    </div>
</body>
</html>
