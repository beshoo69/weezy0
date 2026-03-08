<?php
// admin/user-stats.php - إحصائيات المستخدمين
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// إحصائيات عامة
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_status = 'active'")->fetchColumn();
$vip_users = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_plan_id = 3")->fetchColumn();
$premium_users = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_plan_id = 2")->fetchColumn();
$new_today = $pdo->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$new_month = $pdo->query("SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURDATE())")->fetchColumn();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إحصائيات المستخدمين - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 20px;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #e50914; margin-bottom: 30px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 25px;
            border: 1px solid #333;
        }
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            color: #e50914;
        }
        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
            margin-top: 5px;
        }
        .stat-icon {
            font-size: 40px;
            margin-bottom: 10px;
            color: #e50914;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>إحصائيات المستخدمين</h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">إجمالي المستخدمين</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-check-circle" style="color: #27ae60;"></i></div>
                <div class="stat-number"><?php echo $active_users; ?></div>
                <div class="stat-label">عضويات نشطة</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-crown" style="color: gold;"></i></div>
                <div class="stat-number"><?php echo $vip_users; ?></div>
                <div class="stat-label">مشتركي VIP</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-star" style="color: #e50914;"></i></div>
                <div class="stat-number"><?php echo $premium_users; ?></div>
                <div class="stat-label">مشتركي مميز</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?php echo $new_today; ?></div>
                <div class="stat-label">جدد اليوم</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                <div class="stat-number"><?php echo $new_month; ?></div>
                <div class="stat-label">جدد هذا الشهر</div>
            </div>
        </div>
    </div>
</body>
</html>