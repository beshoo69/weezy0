<?php
// register.php - صفحة تسجيل حساب جديد
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';

// إذا كان المستخدم مسجل دخوله بالفعل
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // التحقق من صحة البيانات
    if (empty($username) || empty($email) || empty($password)) {
        $error = '❌ جميع الحقول مطلوبة';
    } elseif ($password !== $confirm_password) {
        $error = '❌ كلمة المرور غير متطابقة';
    } elseif (strlen($password) < 6) {
        $error = '❌ كلمة المرور يجب أن تكون 6 أحرف على الأقل';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ البريد الإلكتروني غير صحيح';
    } else {
        // التحقق من عدم وجود المستخدم مسبقاً
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->execute([$username, $email]);
        
        if ($check->fetch()) {
            $error = '❌ اسم المستخدم أو البريد الإلكتروني موجود مسبقاً';
        } else {
            // تشفير كلمة المرور
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // إضافة المستخدم الجديد (عضوية عادية - plan_id = 1)
            $stmt = $pdo->prepare("
                INSERT INTO users (
                    username, email, password, role, 
                    membership_plan_id, membership_status,
                    max_devices, ads_free, downloads_allowed,
                    early_access, exclusive_content, created_at
                ) VALUES (
                    ?, ?, ?, 'user',
                    1, 'active',
                    1, 0, 0,
                    0, 0, NOW()
                )
            ");
            
            if ($stmt->execute([$username, $email, $hashed_password])) {
                $success = '✅ تم إنشاء الحساب بنجاح! يمكنك تسجيل الدخول الآن';
                
                // إعادة التوجيه بعد 3 ثواني
                header("refresh:3;url=admin/login.php");
            } else {
                $error = '❌ حدث خطأ في إنشاء الحساب';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء حساب جديد - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .register-container {
            width: 100%;
            max-width: 500px;
        }
        
        .register-box {
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
            font-size: 32px;
            font-weight: 800;
        }
        
        .logo span {
            color: #fff;
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
        /* ===== زر إنشاء حساب الفاخر ===== */
.register-btn {
    background: linear-gradient(135deg, #00b09b, #96c93d);
    color: white;
    padding: 15px 30px;
    border-radius: 50px;
    text-decoration: none;
    font-weight: 800;
    font-size: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    position: relative;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    border: none;
    box-shadow: 0 10px 20px rgba(0, 176, 155, 0.3);
    letter-spacing: 0.5px;
    width: 100%;
    cursor: pointer;
    border: 2px solid rgba(255, 255, 255, 0.2);
}

.register-btn i {
    font-size: 20px;
    transition: all 0.4s ease;
}

.register-btn:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 176, 155, 0.5);
    border-color: rgba(255, 255, 255, 0.5);
}

.register-btn:hover i {
    transform: rotate(360deg) scale(1.2);
}

.register-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.6s ease;
}

.register-btn:hover::before {
    left: 100%;
}

/* === تصميم بديل - توهج نيون === */
.register-btn-neon {
    background: transparent;
    border: 2px solid #0ff;
    color: #0ff;
    box-shadow: 0 0 20px #0ff, inset 0 0 20px #0ff;
    animation: neonPulse 2s infinite;
}

@keyframes neonPulse {
    0% { box-shadow: 0 0 20px #0ff, inset 0 0 10px #0ff; }
    50% { box-shadow: 0 0 40px #0ff, inset 0 0 20px #0ff; }
    100% { box-shadow: 0 0 20px #0ff, inset 0 0 10px #0ff; }
}

.register-btn-neon:hover {
    background: #0ff;
    color: #000;
    box-shadow: 0 0 60px #0ff;
}

/* === تصميم بديل - قوس قزح متحرك === */
.register-btn-rainbow {
    background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #8f00ff);
    background-size: 300% 300%;
    animation: rainbow 5s ease infinite;
    color: white;
    text-shadow: 0 0 10px rgba(0,0,0,0.5);
}

@keyframes rainbow {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* === تصميم بديل - ثلاثي الأبعاد === */
.register-btn-3d {
    background: #00b09b;
    transform-style: preserve-3d;
    transform: perspective(500px) rotateX(0deg);
    box-shadow: 0 10px 0 #008b7a, 0 15px 25px rgba(0,0,0,0.3);
}

.register-btn-3d:hover {
    transform: perspective(500px) rotateX(10deg) translateY(-5px);
    box-shadow: 0 15px 0 #008b7a, 0 20px 30px rgba(0,0,0,0.4);
}

.register-btn-3d:active {
    transform: perspective(500px) rotateX(0deg) translateY(5px);
    box-shadow: 0 5px 0 #008b7a, 0 10px 20px rgba(0,0,0,0.3);
}

/* === تصميم بديل - متوهج مع نبض === */
.register-btn-glow {
    background: linear-gradient(135deg, #667eea, #764ba2);
    position: relative;
    animation: softPulse 2s infinite;
}

@keyframes softPulse {
    0% { box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
    50% { box-shadow: 0 20px 40px rgba(102, 126, 234, 0.7); }
    100% { box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4); }
}

/* === تصميم بديل - زجاجي === */
.register-btn-glass {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.register-btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(15px);
}

/* === تصميم بديل - مع تأثير فقاعات === */
.register-btn-bubbles {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
    position: relative;
    overflow: hidden;
}

.register-btn-bubbles::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 50%);
    opacity: 0;
    transition: opacity 0.3s ease;
    animation: bubble 2s infinite;
}

@keyframes bubble {
    0% { transform: scale(1); opacity: 0; }
    50% { transform: scale(1.2); opacity: 0.3; }
    100% { transform: scale(1); opacity: 0; }
}

.register-btn-bubbles:hover::after {
    opacity: 0.5;
}

/* === تصميم بديل - مع تأثير كتابة === */
.register-btn-type {
    background: transparent;
    border: 2px solid #00b09b;
    color: #00b09b;
    overflow: hidden;
    position: relative;
    z-index: 1;
}

.register-btn-type span {
    position: relative;
    z-index: 2;
}

.register-btn-type::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, #00b09b, #96c93d);
    transition: left 0.4s ease;
    z-index: 1;
}

.register-btn-type:hover {
    color: white;
    border-color: transparent;
}

.register-btn-type:hover::before {
    left: 0;
}

/* === تصميم بديل - مع أيقونة متحركة === */
.register-btn-icon {
    background: linear-gradient(135deg, #f7971e, #ffd200);
    color: #000;
    position: relative;
    overflow: hidden;
}

.register-btn-icon i {
    animation: bounce 1s infinite;
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

.register-btn-icon:hover i {
    animation: spin 0.5s ease;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* === تصميم بديل - مع تأثير الظل المتعدد === */
.register-btn-shadow {
    background: #00b09b;
    box-shadow: 0 5px 0 #008b7a, 0 10px 20px rgba(0, 176, 155, 0.4);
}

.register-btn-shadow:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 0 #008b7a, 0 20px 30px rgba(0, 176, 155, 0.6);
}

/* === تصميم بديل - متدرج متحرك === */
.register-btn-gradient {
    background: linear-gradient(270deg, #00b09b, #96c93d, #00b09b);
    background-size: 200% 200%;
    animation: gradientShift 3s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* === تصميم بديل - مع تأثير نبض سريع === */
.register-btn-fastpulse {
    background: #e50914;
    animation: fastPulse 1s infinite;
}

@keyframes fastPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

/* === تصميم بديل - مع تأثير إطار متوهج === */
.register-btn-border {
    background: transparent;
    border: 3px solid #00b09b;
    color: #00b09b;
    position: relative;
    overflow: hidden;
}

.register-btn-border::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 0;
    height: 0;
    background: rgba(0, 176, 155, 0.2);
    border-radius: 50%;
    transition: all 0.5s ease;
}

.register-btn-border:hover {
    color: white;
    border-color: #96c93d;
}

.register-btn-border:hover::after {
    width: 300px;
    height: 300px;
    background: #00b09b;
    z-index: -1;
}

/* === تصميم بديل - مع تأثير مائي === */
.register-btn-water {
    background: linear-gradient(135deg, #00c6fb, #005bea);
    position: relative;
    overflow: hidden;
}

.register-btn-water::before {
    content: '';
    position: absolute;
    top: -20px;
    left: -20px;
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 50%;
    animation: water 3s infinite;
}

@keyframes water {
    0% { transform: scale(1); opacity: 0.3; }
    50% { transform: scale(3); opacity: 0; }
    100% { transform: scale(1); opacity: 0.3; }
}

/* === تصميم بديل - مع تأثير فتح الباب === */
.register-btn-door {
    background: #e50914;
    position: relative;
    overflow: hidden;
}

.register-btn-door::after {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.3);
    transform: skewX(-30deg);
    transition: left 0.4s ease;
}

.register-btn-door:hover::after {
    left: 100%;
}

/* === تصميم بديل - مع تأثير سباركل === */
.register-btn-sparkle {
    background: linear-gradient(135deg, #ff0080, #ff8c00);
    position: relative;
    overflow: hidden;
}

.register-btn-sparkle::before,
.register-btn-sparkle::after {
    content: '✨';
    position: absolute;
    font-size: 20px;
    opacity: 0;
    transition: all 0.3s ease;
}

.register-btn-sparkle::before {
    top: -10px;
    left: 20%;
}

.register-btn-sparkle::after {
    bottom: -10px;
    right: 20%;
}

.register-btn-sparkle:hover::before {
    top: -20px;
    opacity: 1;
    animation: sparkle 0.5s ease;
}

.register-btn-sparkle:hover::after {
    bottom: -20px;
    opacity: 1;
    animation: sparkle 0.5s ease 0.2s;
}

@keyframes sparkle {
    0% { transform: scale(1); }
    50% { transform: scale(1.5); }
    100% { transform: scale(1); }
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
        
        .password-hint {
            color: #b3b3b3;
            font-size: 12px;
            margin-top: 5px;
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
        
        .btn-register {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, #e50914, #ff4d4d);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(229,9,20,0.4);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #b3b3b3;
        }
        
        .login-link a {
            color: #e50914;
            text-decoration: none;
            font-weight: bold;
            margin-right: 5px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .terms {
            margin-top: 20px;
            color: #b3b3b3;
            font-size: 14px;
            text-align: center;
        }
        
        .terms a {
            color: #e50914;
            text-decoration: none;
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
            font-size: 13px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .membership-note i {
            color: gold;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="logo">
                <i class="fas fa-film" style="color: #e50914; font-size: 48px; margin-bottom: 10px;"></i>
                <h1>ويزي<span>برو</span></h1>
            </div>
            
            <h2>إنشاء حساب جديد</h2>
            
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
                    <label>اسم المستخدم</label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" placeholder="أدخل اسم المستخدم" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>البريد الإلكتروني</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" placeholder="أدخل البريد الإلكتروني" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>كلمة المرور</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" placeholder="أدخل كلمة المرور" required>
                    </div>
                    <div class="password-hint">
                        <i class="fas fa-info-circle"></i> يجب أن تكون 6 أحرف على الأقل
                    </div>
                </div>
                
                <div class="form-group">
                    <label>تأكيد كلمة المرور</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="confirm_password" placeholder="أعد إدخال كلمة المرور" required>
                    </div>
                </div>
                
               <button type="submit" class="register-btn">
    <i class="fas fa-user-plus"></i>
    إنشاء حساب
</button>
            </form>
            
            <div class="login-link">
                لديك حساب بالفعل؟ <a href="admin/login.php">تسجيل الدخول</a>
            </div>
            
            <div class="terms">
                بالتسجيل أنت توافق على <a href="#">شروط الاستخدام</a> و <a href="#">سياسة الخصوصية</a>
            </div>
            
            <div class="membership-note">
                <p>
                    <i class="fas fa-crown"></i>
                    <span>بعد التسجيل، يمكنك ترقية حسابك إلى عضوية مميزة للاستمتاع بمزايا حصرية</span>
                </p>
                <p style="font-size: 12px; margin-bottom: 0;">
                    ✓ مشاهدة بدون إعلانات • ✓ جودة 4K • ✓ تحميل المشاهدة
                </p>
            </div>
        </div>
    </div>
</body>
</html>