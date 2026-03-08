<?php
// admin/create-admin.php - إنشاء مستخدم admin بضغطة واحدة
require_once '../includes/config.php';

// حذف أي مستخدم admin سابق
$pdo->exec("DELETE FROM users WHERE username = 'admin'");

// إنشاء كلمة مرور جديدة
$password = password_hash('admin123', PASSWORD_DEFAULT);

// إضافة المستخدم الجديد
$sql = "INSERT INTO users (username, email, password, role, membership_plan_id, membership_status, max_devices, ads_free, downloads_allowed, early_access, exclusive_content) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([
    'admin',
    'admin@fayez.com',
    $password,
    'admin',
    3, // VIP plan
    'active',
    5,
    1, // TRUE
    1, // TRUE
    1, // TRUE
    1  // TRUE
]);

if ($result) {
    echo "<h1 style='color: green; font-family: Arial;'>✅ تم إنشاء مستخدم admin بنجاح!</h1>";
    echo "<p style='font-size: 18px;'>اسم المستخدم: <strong>admin</strong></p>";
    echo "<p style='font-size: 18px;'>كلمة المرور: <strong>admin123</strong></p>";
    echo "<p style='font-size: 18px;'>البريد الإلكتروني: <strong>admin@fayez.com</strong></p>";
    echo "<p style='font-size: 18px;'>الصلاحية: <strong style='color: #e50914;'>مدير النظام</strong></p>";
    echo "<p style='font-size: 18px;'>العضوية: <strong style='color: gold;'>VIP</strong></p>";
    echo "<br><a href='login.php' style='background: #e50914; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>🔐 تسجيل الدخول الآن</a>";
} else {
    echo "<h1 style='color: red;'>❌ فشل إنشاء المستخدم</h1>";
}

// عرض جميع المستخدمين للتأكد
$users = $pdo->query("SELECT id, username, email, role FROM users")->fetchAll();
if ($users) {
    echo "<h3 style='margin-top: 30px;'>المستخدمين في النظام:</h3>";
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse; background: #f5f5f5;'>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>