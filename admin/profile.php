<?php
// profile.php - صفحة الملف الشخصي
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/membership.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// جلب معلومات المستخدم والعضوية
$user = getUserMembership($user_id);

// جلب سجل المدفوعات
$payments = $pdo->prepare("
    SELECT p.*, mp.name as plan_name 
    FROM payment_history p
    LEFT JOIN membership_plans mp ON p.plan_id = mp.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$payments->execute([$user_id]);
$payments = $payments->fetchAll();

// جلب الأجهزة المسجلة
$devices = $pdo->prepare("SELECT * FROM user_devices WHERE user_id = ? ORDER BY last_active DESC");
$devices->execute([$user_id]);
$devices = $devices->fetchAll();

$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>ملفي الشخصي - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
        }
        .header {
            background: #0a0a0a;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
        }
        .logo h1 {
            color: #e50914;
            font-size: 28px;
        }
        .logo span { color: #fff; }
        .nav-links a {
            color: #fff;
            text-decoration: none;
            margin: 0 10px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }
        .profile-sidebar {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        .profile-main {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 25px;
            border: 1px solid #333;
        }
        .avatar {
            width: 100px;
            height: 100px;
            background: #e50914;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
        }
        .membership-badge {
            background: <?php echo $user['membership_plan_id'] == 3 ? '#ffd700' : ($user['membership_plan_id'] == 2 ? '#e50914' : '#6c757d'); ?>;
            color: #000;
            padding: 5px 15px;
            border-radius: 30px;
            display: inline-block;
            margin: 10px 0;
            font-weight: bold;
        }
        .info-item {
            margin: 15px 0;
            padding: 10px;
            background: #252525;
            border-radius: 8px;
        }
        .info-label {
            color: #b3b3b3;
            font-size: 12px;
        }
        .info-value {
            font-size: 16px;
            font-weight: bold;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #e50914;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 10px;
        }
        .btn-success {
            background: #27ae60;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: center;
            border-bottom: 1px solid #333;
        }
        th {
            color: #e50914;
        }
        .device-item {
            background: #252525;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
        }
        .device-active {
            color: #27ae60;
        }
        .device-inactive {
            color: #e50914;
        }
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <div class="nav-links">
            <a href="index.php">الرئيسية</a>
            <a href="movies.php">أفلام</a>
            <a href="series.php">مسلسلات</a>
            <a href="membership-plans.php">العضوية</a>
            <a href="profile.php" style="color: #e50914;">حسابي</a>
            <a href="logout.php">تسجيل خروج</a>
        </div>
    </div>
    
    <div class="container">
        <?php if ($success_message): ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <div class="profile-grid">
            <!-- الشريط الجانبي -->
            <div class="profile-sidebar">
                <div class="avatar">
                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                </div>
                
                <h2 style="text-align: center; margin-bottom: 5px;"><?php echo htmlspecialchars($user['username']); ?></h2>
                <p style="text-align: center; color: #b3b3b3; margin-bottom: 20px;"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <div class="membership-badge">
                    <?php echo $user['plan_name'] ?? 'عادي'; ?>
                </div>
                
                <div class="info-item">
                    <div class="info-label">حالة العضوية</div>
                    <div class="info-value">
                        <?php 
                        if ($user['membership_status'] == 'active' && strtotime($user['membership_end']) > time()) {
                            echo '✅ نشطة حتى ' . date('Y-m-d', strtotime($user['membership_end']));
                        } else {
                            echo '❌ غير نشطة';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">الجودة المتاحة</div>
                    <div class="info-value"><?php echo $user['quality'] ?? 'HD'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">الأجهزة المسموحة</div>
                    <div class="info-value"><?php echo count($devices); ?> / <?php echo $user['max_devices'] ?? 1; ?></div>
                </div>
                
                <a href="membership-plans.php" class="btn" style="width: 100%; text-align: center;">
                    <?php echo ($user['membership_plan_id'] == 1 || !$user['membership_plan_id']) ? 'ترقية العضوية' : 'تغيير الخطة'; ?>
                </a>
            </div>
            
            <!-- المحتوى الرئيسي -->
            <div class="profile-main">
                <h2 style="color: #e50914; margin-bottom: 20px;">سجل المدفوعات</h2>
                
                <?php if (empty($payments)): ?>
                <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد مدفوعات سابقة</p>
                <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>الخطة</th>
                            <th>المبلغ</th>
                            <th>طريقة الدفع</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?php echo date('Y-m-d', strtotime($payment['created_at'])); ?></td>
                            <td><?php echo $payment['plan_name'] ?? 'عضوية'; ?></td>
                            <td><?php echo number_format($payment['amount']); ?> ر.س</td>
                            <td><?php echo $payment['payment_method']; ?></td>
                            <td style="color: <?php echo $payment['status'] == 'completed' ? '#27ae60' : '#e50914'; ?>;">
                                <?php echo $payment['status']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <h2 style="color: #e50914; margin: 30px 0 20px;">الأجهزة المسجلة</h2>
                
                <?php if (empty($devices)): ?>
                <p style="color: #b3b3b3; text-align: center; padding: 20px;">لا توجد أجهزة مسجلة</p>
                <?php else: ?>
                <?php foreach ($devices as $device): ?>
                <div class="device-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo htmlspecialchars($device['device_name'] ?: 'جهاز غير معروف'); ?></strong>
                            <span style="color: #b3b3b3; margin-right: 10px;">(<?php echo $device['device_type']; ?>)</span>
                        </div>
                        <div class="<?php echo strtotime($device['last_active']) > strtotime('-30 days') ? 'device-active' : 'device-inactive'; ?>">
                            <?php 
                            $last_active = strtotime($device['last_active']);
                            if ($last_active > strtotime('-30 days')) {
                                echo 'نشط';
                            } else {
                                echo 'غير نشط';
                            }
                            ?>
                        </div>
                    </div>
                    <div style="font-size: 12px; color: #b3b3b3; margin-top: 5px;">
                        آخر نشاط: <?php echo date('Y-m-d H:i', strtotime($device['last_active'])); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>