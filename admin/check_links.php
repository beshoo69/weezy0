<?php
// check_links.php - تحقق من صحة روابط التحميل وتحديث حالة الصلاحية
require_once 'includes/config.php';
require_once 'includes/database.php';

echo "🔍 بدء فحص روابط التحميل...\n";

$stmt = $pdo->query("SELECT COUNT(*) as total FROM download_servers");
$total = $stmt->fetch()['total'];
echo "📊 إجمالي الروابط: $total\n";

$checked = 0;
$valid = 0;
$invalid = 0;

$stmt = $pdo->query("SELECT id, download_url FROM download_servers WHERE is_valid = 1 LIMIT 100"); // فحص 100 فقط للبداية
$servers = $stmt->fetchAll();

foreach ($servers as $server) {
    $checked++;
    $url = $server['download_url'];

    // فحص الرابط بـ HEAD request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200) {
        $valid++;
        echo "✅ رابط صالح: ID {$server['id']}\n";
    } else {
        $invalid++;
        // تحديث حالة الرابط إلى غير صالح
        $update_stmt = $pdo->prepare("UPDATE download_servers SET is_valid = 0 WHERE id = ?");
        $update_stmt->execute([$server['id']]);
        echo "❌ رابط منتهي الصلاحية: ID {$server['id']} (HTTP $http_code)\n";
    }

    // تجنب الضغط على السيرفرات
    usleep(100000); // 0.1 ثانية
}

echo "\n📈 النتائج:\n";
echo "تم فحص: $checked رابط\n";
echo "صالح: $valid\n";
echo "منتهي الصلاحية: $invalid\n";
echo "\n✅ انتهى الفحص\n";
?>