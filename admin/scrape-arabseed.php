<?php
// admin/scrape-arabseed.php - نسخة محسنة مع معالجة أخطاء أفضل
require_once __DIR__ . '/../includes/config.php';      // ✅ اتصال قاعدة البيانات
require_once __DIR__ . '/../includes/functions.php';   // ✅ ✅✅ الدوال العامة (فيها isLoggedIn)

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

// اختبار الاتصال بالموقع
function testSiteConnection($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    $curl_errno = curl_errno($ch);
    curl_close($ch);
    
    return [
        'success' => ($http_code == 200),
        'http_code' => $http_code,
        'curl_error' => $curl_error,
        'curl_errno' => $curl_errno,
        'response' => $response
    ];
}

// معالجة الطلب
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_connection'])) {
    $url = $_POST['url'] ?? 'https://arabseed.com/movies';
    $result = testSiteConnection($url);
    
    if ($result['success']) {
        $message = "✅ الاتصال ناجح! الموقع يستجيب بشكل طبيعي.";
    } else {
        $error = "❌ فشل الاتصال: ";
        if ($result['curl_errno'] == 6) {
            $error .= "الموقع غير موجود أو محجوب (CURLE_COULDNT_RESOLVE_HOST - 6)";
        } elseif ($result['http_code'] == 403) {
            $error .= "الموقع يرفض الزحف التلقائي (HTTP 403 Forbidden)";
        } elseif ($result['http_code'] == 404) {
            $error .= "الصفحة غير موجودة (HTTP 404)";
        } else {
            $error .= $result['curl_error'] ?: "HTTP Error: " . $result['http_code'];
        }
    }
}

// إذا كان المستخدم مصراً على المحاولة
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['force_scrape'])) {
    $url = $_POST['url'] ?? 'https://arabseed.com/movies';
    $use_proxy = isset($_POST['use_proxy']);
    $proxy_url = $_POST['proxy_url'] ?? '';
    
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // تقليد متصفح حقيقي
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ar,en-US;q=0.9,en;q=0.8',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Sec-Fetch-User: ?1',
        ]);
        
        // استخدام بروكسي إذا طلب
        if ($use_proxy && $proxy_url) {
            curl_setopt($ch, CURLOPT_PROXY, $proxy_url);
        }
        
        // تخطي SSL (للتجربة فقط)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $html = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $curl_errno = curl_errno($ch);
        curl_close($ch);
        
        if ($http_code == 200) {
            // محاولة تحليل الصفحة
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            
            // البحث عن عناوين
            $titles = $xpath->query('//h2 | //h3 | //*[contains(@class, "title")]');
            $found = [];
            foreach ($titles as $title) {
                $found[] = trim($title->nodeValue);
            }
            
            $message = "✅ تم جلب الصفحة بنجاح! (HTTP 200)<br>";
            $message .= "وجدنا " . count($found) . " عنوان محتمل:<br>";
            $message .= implode('<br>', array_slice($found, 0, 10));
            
        } elseif ($http_code == 403) {
            $error = "❌ الموقع يرفض الزحف التلقائي (HTTP 403). يستخدم حماية قوية ضد البوتات.";
        } elseif ($curl_errno == 6) {
            $error = "❌ لا يمكن الوصول للموقع - قد يكون محجوباً في منطقتك. استخدم بروكسي.";
        } else {
            $error = "❌ فشل الاتصال: " . ($curl_error ?: "HTTP $http_code");
        }
        
    } catch (Exception $e) {
        $error = "❌ خطأ: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>اختبار اتصال عرب سيد - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 30px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        h1 { color: #e50914; margin-bottom: 30px; }
        .card {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        .success {
            background: #27ae60;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .error {
            background: #e50914;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .warning {
            background: #f39c12;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #000;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #b3b3b3;
        }
        input, select {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
        }
        .btn {
            padding: 12px 25px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-secondary {
            background: #2a2a2a;
        }
        hr { border-color: #333; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 اختبار الاتصال بموقع عرب سيد</h1>
        
        <div class="warning">
            <strong>⚠️ معلومات مهمة:</strong><br>
            - موقع عرب سيد قد يكون محجوباً في بعض المناطق<br>
            - الموقع يحظر الزحف التلقائي (HTTP 403)<br>
            - استخدام TMDB API هو البديل الأفضل والموثوق
        </div>
        
        <div class="card">
            <h2 style="color: #e50914; margin-bottom: 20px;">🔧 اختبار الاتصال</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>رابط الموقع:</label>
                    <input type="url" name="url" value="https://arabseed.com/movies" required>
                </div>
                
                <button type="submit" name="test_connection" class="btn">🔍 اختبار الاتصال</button>
            </form>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2 style="color: #e50914; margin-bottom: 20px;">⚙️ محاولة متقدمة (مع بروكسي)</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>رابط الموقع:</label>
                    <input type="url" name="url" value="https://arabseed.com/movies" required>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="use_proxy"> استخدام بروكسي
                    </label>
                </div>
                
                <div class="form-group">
                    <label>رابط البروكسي (اختياري):</label>
                    <input type="text" name="proxy_url" placeholder="tcp://proxy-server:port">
                </div>
                
                <button type="submit" name="force_scrape" class="btn">🔄 محاولة الجلب</button>
            </form>
        </div>
        
        <div class="card">
            <h2 style="color: #e50914; margin-bottom: 20px;">✅ البديل الموصى به</h2>
            
            <p style="margin-bottom: 15px;">بدلاً من محاولة جلب موقع محظور، استخدم المصادر الرسمية:</p>
            
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <a href="import-tmdb.php" class="btn">🎬 استيراد من TMDB</a>
                <a href="import-tv.php" class="btn">📺 استيراد مسلسلات</a>
                <a href="import-arabic-movies.php" class="btn">🇸🇦 أفلام عربية</a>
            </div>
        </div>
        
        <div class="card">
            <h2 style="color: #e50914; margin-bottom: 20px;">📊 إحصائيات قاعدة البيانات</h2>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div style="background: #252525; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 36px; color: #e50914;"><?php echo $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(); ?></div>
                    <div style="color: #b3b3b3;">فيلم</div>
                </div>
                <div style="background: #252525; padding: 15px; border-radius: 8px; text-align: center;">
                    <div style="font-size: 36px; color: #e50914;"><?php echo $pdo->query("SELECT COUNT(*) FROM series")->fetchColumn(); ?></div>
                    <div style="color: #b3b3b3;">مسلسل</div>
                </div>
            </div>
            
            <p style="margin-top: 20px; color: #b3b3b3; text-align: center;">
                لديك بالفعل قاعدة بيانات ضخمة. ركز على تحسينها بدلاً من البحث عن مصادر غير موثوقة.
            </p>
        </div>
    </div>
</body>
</html>