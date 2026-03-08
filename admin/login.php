<?php
// admin/login.php - نسخة محدثة تدعم نظام العضوية
require_once '../includes/config.php';
require_once '../includes/membership.php';

// إذا كان المستخدم مسجل دخوله بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // التحقق من وجود المستخدم في قاعدة البيانات
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // التحقق من كلمة المرور (ملاحظة: يجب أن تكون مشفرة في قاعدة البيانات)
        if (password_verify($password, $user['password'])) {
            // تسجيل الدخول ناجح
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            
            // جلب معلومات العضوية
            $membership = getUserMembership($user['id']);
            $_SESSION['membership'] = $membership;
            
            // تسجيل الجهاز
            $device_name = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $device_type = 'web';
            $device_id = md5($device_name . $user['id']);
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            
            registerDevice($user['id'], $device_name, $device_type, $device_id, $ip);
            
            // التحقق من الصلاحيات الإدارية
            if ($user['role'] == 'admin' || $user['role'] == 'super_admin') {
                header('Location: dashboard.php');
            } else {
                // مستخدم عادي - يذهب للصفحة الرئيسية
                header('Location: ../index.php');
            }
            exit;
        } else {
            $error = '❌ كلمة المرور غير صحيحة';
        }
    } else {
        $error = '❌ اسم المستخدم أو البريد الإلكتروني غير موجود';
    }
}

// رسالة نجاح (مثلاً بعد تسجيل حساب جديد)
if (isset($_GET['registered'])) {
    $success = '✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن';
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - ويزي برو</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        
        .login-box {
            background: #1f1f1f;
            padding: 40px;
            border-radius: 20px;
            width: 100%;
            border-top: 4px solid #e50914;
            box-shadow: 0 10px 40px rgba(229,9,20,0.2);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #e50914;
            font-size: 36px;
            font-weight: 800;
        }
        
        .logo span {
            color: #fff;
        }
        
        .logo i {
            font-size: 48px;
            color: #e50914;
            margin-bottom: 10px;
        }
        
        h2 {
            color: #fff;
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
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
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #e50914;
            font-size: 18px;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 45px 15px 15px;
            background: #2a2a2a;
            border: 1px solid #333;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: 0.3s;
        }
        
        .input-group input:focus {
            border-color: #e50914;
            outline: none;
            box-shadow: 0 0 0 2px rgba(229,9,20,0.2);
        }
        
        .input-group input::placeholder {
            color: #666;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #b3b3b3;
        }
        
        .remember-me input {
            width: auto;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #e50914;
            text-decoration: none;
            font-size: 14px;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        button {
            width: 100%;
            padding: 15px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }
        
        button:hover {
            background: #b20710;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229,9,20,0.4);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: rgba(229,9,20,0.1);
            border: 1px solid #e50914;
            color: #e50914;
        }
        
        .alert-success {
            background: rgba(39,174,96,0.1);
            border: 1px solid #27ae60;
            color: #27ae60;
        }
        
        .register-link {
            text-align: center;
            margin-top: 25px;
            color: #b3b3b3;
        }
        
        .register-link a {
            color: #e50914;
            text-decoration: none;
            font-weight: bold;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .membership-note {
            background: #2a2a2a;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            border: 1px solid #333;
        }
        
        .membership-note p {
            color: #b3b3b3;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .membership-note a {
            color: #e50914;
            text-decoration: none;
            font-weight: bold;
        }
        
        @media (max-width: 480px) {
            .login-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <i class="fas fa-film"></i>
                <h1>ويزي<span>برو</span></h1>
            </div>
            
            <h2>تسجيل الدخول</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>اسم المستخدم أو البريد الإلكتروني</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="أدخل اسم المستخدم" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="أدخل كلمة المرور" required>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <label class="remember-me">
                        <input type="checkbox" name="remember"> تذكرني
                    </label>
                    <a href="forgot-password.php" class="forgot-password">نسيت كلمة المرور؟</a>
                </div>
                
                <button type="submit">
                    <i class="fas fa-sign-in-alt"></i>
                    تسجيل الدخول
                </button>
            </form>
            
            
           <a href="../register.php" style="color: #fff; text-decoration: none; padding: 8px 15px;">
    إنشاء حساب
</a>
            
            <div class="membership-note">
                <p><i class="fas fa-crown" style="color: #ffd700;"></i> اشترك في العضوية المميزة واستمتع بمزايا حصرية:</p>
                <p style="font-size: 13px;">
                    ✓ مشاهدة بدون إعلانات • ✓ جودة 4K • ✓ تحميل المشاهدة • ✓ محتوى حصري
                </p>
                <p style="text-align: center; margin-top: 10px;">
                    <a href="../membership-plans.php">عرض خطط العضوية <i class="fas fa-arrow-left"></i></a>
                </p>
            </div>
        </div>
    </div>
</body>
</html