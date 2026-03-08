<?php
// includes/test-config.php
require_once 'config.php';

echo "<div style='direction: rtl; font-family: Tajawal; background: #0f0f0f; color: white; padding: 30px;'>";
echo "<h2 style='color: #e50914;'>📦 اختبار اتصال قاعدة البيانات</h2>";

try {
    // اختبار بسيط
    $pdo->query("SELECT 1");
    echo "<p style='color: #4caf50;'>✅ الاتصال بقاعدة البيانات ناجح!</p>";
    
    // عرض المعلومات
    echo "<p>📁 قاعدة البيانات: <strong>" . $dbname . "</strong></p>";
    echo "<p>🔌 المضيف: <strong>" . $host . "</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: #e50914;'>❌ فشل الاتصال: " . $e->getMessage() . "</p>";
}

echo "</div>";
?>