<?php
// admin/index.php - نسخة بدون استعلامات غير ضرورية
require_once __DIR__ . '/../includes/config.php';

// إذا مسجل دخول → حول للوحة التحكم
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // حساب افتراضي - غيرها بعدين
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['user_role'] = 'admin';
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
    }
}
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* نفس التصميم السابق */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f 0%, #1a1a1a 100%);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: #1a1a1a;
            padding: 50px 40px;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.5);
            border: 1px solid #333;
        }
        .logo { text-align: center; margin-bottom: 40px; }
        .logo h1 { color: #e50914; font-size: 42px; font-weight: 800; }
        .logo span { color: #fff; }
        .form-group { margin-bottom: 25px; }
        .form-group label { display: block; color: #fff; margin-bottom: 8px; }
        input {
            width: 100%;
            padding: 15px;
            background: #2a2a2a;
            border: 2px solid #333;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
        }
        input:focus {
            border-color: #e50914;
            outline: none;
        }
        .btn-login {
            width: 100%;
            padding: 15px;
            background: #e50914;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-login:hover { background: #b20710; }
        .error-message {
            background: rgba(229,9,20,0.15);
            color: #e50914;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            text-align: center;
        }
        .info-text {
            text-align: center;
            margin-top: 25px;
            color: #b3b3b3;
            padding-top: 25px;
            border-top: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>اسم المستخدم</label>
                <input type="text" name="username" placeholder="أدخل اسم المستخدم" value="admin" required>
            </div>
            <div class="form-group">
                <label>كلمة المرور</label>
                <input type="password" name="password" placeholder="أدخل كلمة المرور" value="admin123" required>
            </div>
            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i>
                تسجيل الدخول
            </button>
        </form>
        
        <div class="info-text">
            <i class="fas fa-shield-alt" style="color: #e50914;"></i>
            دخول تجريبي: <strong style="color: #e50914;">admin</strong> / <strong style="color: #e50914;">admin123</strong>
        </div>
    </div>
</body>
</html>