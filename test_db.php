<?php
// test_db.php - اختبر الاتصال بقاعدة البيانات
require_once 'includes/config.php';

echo "<div style='background: #0f0f0f; color: white; padding: 30px; font-family: Tajawal; text-align: center;'>";
echo "<h2 style='color: #e50914;'>🔌 فحص الاتصال بقاعدة البيانات</h2>";

try {
    $pdo->query("SELECT 1");
    echo "<p style='color: #4caf50;'>✅ الاتصال بقاعدة البيانات ناجح!</p>";
    
    // عرض عدد الأفلام
    $stmt = $pdo->query("SELECT COUNT(*) FROM movies");
    $count = $stmt->fetchColumn();
    echo "<p>📽️ عدد الأفلام في قاعدة البيانات: <strong>$count</strong></p>";
    
    // عرض الجداول الموجودة
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "<p>📊 الجداول الموجودة: ";
    foreach($tables as $table) {
        echo $table[0] . " - ";
    }
    echo "</p>";
    
} catch(PDOException $e) {
    echo "<p style='color: #e50914;'>❌ فشل الاتصال: " . $e->getMessage() . "</p>";
}
echo "</div>";
?>