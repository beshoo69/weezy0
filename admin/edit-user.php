<?php
// admin/edit-user.php - تعديل بيانات المستخدم
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$messageType = '';

// جلب بيانات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// جلب خطط العضوية
$plans = $pdo->query("SELECT * FROM membership_plans ORDER BY price_monthly ASC")->fetchAll();

// تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $membership_plan_id = $_POST['membership_plan_id'];
    $membership_status = $_POST['membership_status'];
    $membership_end = $_POST['membership_end'];
    
    // تحديث كلمة المرور إذا تم إدخالها
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ?, role = ?, membership_plan_id = ?, membership_status = ?, membership_end = ? WHERE id = ?");
        $stmt->execute([$username, $email, $password, $role, $membership_plan_id, $membership_status, $membership_end, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, role = ?, membership_plan_id = ?, membership_status = ?, membership_end = ? WHERE id = ?");
        $stmt->execute([$username, $email, $role, $membership_plan_id, $membership_status, $membership_end, $id]);
    }
    
    $message = "✅ تم تحديث بيانات المستخدم بنجاح";
    $messageType = "success";
    
    // إعادة جلب البيانات المحدثة
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل المستخدم - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
            color: #fff;
            min-height: 100vh;
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
        
        .back-link {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .back-link:hover { color: #e50914; }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
        }
        
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .form-section {
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #333;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-family: 'Tajawal', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #e50914;
        }
        
        .form-group input[readonly] {
            background: #1a1a1a;
            color: #b3b3b3;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            font-size: 16px;
        }
        
        .btn-primary {
            background: #e50914;
            color: #fff;
            width: 100%;
            padding: 15px;
            margin-top: 20px;
        }
        
        .btn-primary:hover {
            background: #b20710;
            transform: translateY(-2px);
        }
        
        .info-box {
            background: #252525;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border-right: 4px solid #e50914;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 10px 0;
            color: #b3b3b3;
        }
        
        .info-item i {
            color: #e50914;
            width: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <a href="users.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة للمستخدمين
        </a>
    </div>
    
    <div class="container">
        <h1>
            <i class="fas fa-user-edit"></i>
            تعديل بيانات المستخدم
        </h1>
        
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-section">
            <!-- معلومات سريعة -->
            <div class="info-box">
                <div class="info-item">
                    <i class="fas fa-id-card"></i>
                    <span>معرف المستخدم: <strong style="color: #e50914;">#<?php echo $user['id']; ?></strong></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>تاريخ التسجيل: <?php echo date('Y-m-d', strtotime($user['created_at'])); ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-clock"></i>
                    <span>آخر تحديث: <?php echo isset($user['updated_at']) ? date('Y-m-d H:i', strtotime($user['updated_at'])) : 'لم يتم التحديث'; ?></span>
                </div>
            </div>
            
            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>اسم المستخدم</label>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>البريد الإلكتروني</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور الجديدة (اتركها فارغة إذا لم ترد التغيير)</label>
                    <input type="password" name="password" placeholder="أدخل كلمة المرور الجديدة">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>الصلاحية</label>
                        <select name="role">
                            <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>مستخدم عادي</option>
                            <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>مدير</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>حالة العضوية</label>
                        <select name="membership_status">
                            <option value="active" <?php echo ($user['membership_status'] ?? '') == 'active' ? 'selected' : ''; ?>>نشط</option>
                            <option value="expired" <?php echo ($user['membership_status'] ?? '') == 'expired' ? 'selected' : ''; ?>>منتهي</option>
                            <option value="cancelled" <?php echo ($user['membership_status'] ?? '') == 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>خطة العضوية</label>
                        <select name="membership_plan_id">
                            <?php foreach ($plans as $plan): ?>
                            <option value="<?php echo $plan['id']; ?>" <?php echo ($user['membership_plan_id'] ?? '') == $plan['id'] ? 'selected' : ''; ?>>
                                <?php echo $plan['name']; ?> - <?php echo $plan['price_monthly']; ?> ر.س/شهر
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>تاريخ انتهاء العضوية</label>
                        <input type="date" name="membership_end" value="<?php echo isset($user['membership_end']) ? $user['membership_end'] : ''; ?>">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> حفظ التغييرات
                </button>
            </form>
        </div>
    </div>
</body>
</html>