<?php
// membership-plans.php - صفحة خطط العضوية (للمستخدمين العاديين)
require_once __DIR__ . '/includes/config.php';  // المسار الصحيح للمجلد الرئيسي
require_once __DIR__ . '/includes/functions.php';

$required_level = isset($_GET['required']) ? $_GET['required'] : '';
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';

// رسالة توضح سبب التوجيه
$message = '';
if ($required_level == 'vip') {
    $message = "هذا المحتوى حصري لمشتركي VIP 👑";
} elseif ($required_level == 'premium') {
    $message = "هذا المحتوى متاح فقط للمشتركين المميزين ⭐";
}
// جلب خطط العضوية المتاحة
$plans = $pdo->query("SELECT * FROM membership_plans WHERE is_active = TRUE ORDER BY price_monthly ASC")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خطط العضوية - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* نفس التنسيقات السابقة */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Tajawal', sans-serif;
            background: linear-gradient(135deg, #0f0f0f, #1a1a1a);
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
        .logo h1 { color: #e50914; font-size: 28px; }
        .logo span { color: #fff; }
        .nav-links a {
            color: #fff;
            text-decoration: none;
            margin: 0 15px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        h1 {
            color: #e50914;
            text-align: center;
            margin-bottom: 10px;
            font-size: 36px;
        }
        .subtitle {
            text-align: center;
            color: #b3b3b3;
            margin-bottom: 50px;
        }
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }
        .plan-card {
            background: linear-gradient(145deg, #1a1a1a, #151515);
            border-radius: 20px;
            padding: 30px;
            border: 2px solid #333;
            transition: 0.3s;
        }
        .plan-card:hover {
            transform: translateY(-10px);
            border-color: currentColor;
        }
        .plan-card.popular {
            border-color: #e50914;
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(229,9,20,0.3);
        }
        .popular-badge {
            background: #e50914;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .plan-price {
            font-size: 36px;
            font-weight: 800;
            margin: 20px 0;
        }
        .plan-features {
            list-style: none;
            margin: 20px 0;
        }
        .plan-features li {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .whatsapp-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 15px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 800;
            font-size: 18px;
            transition: all 0.4s;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-top: 20px;
            background: #25D366;
            color: white;
        }
        .whatsapp-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(37, 211, 102, 0.4);
        }
        .footer {
            background: #0a0a0a;
            padding: 30px;
            text-align: center;
            margin-top: 60px;
            color: #b3b3b3;
        }
        @media (max-width: 768px) {
            .plan-card.popular { transform: scale(1); }
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
            <a href="membership-plans.php" style="color: #e50914;">العضوية</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php">حسابي</a>
                <a href="logout.php">تسجيل خروج</a>
            <?php else: ?>
                <a href="admin/login.php">تسجيل دخول</a>
                <a href="register.php" style="background: #e50914; padding: 8px 15px; border-radius: 5px;">إنشاء حساب</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="container">
        <h1>خطط العضوية</h1>
        <div class="subtitle">اختر خطتك المفضلة واشترك عبر واتساب</div>
        
        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
            <div class="plan-card <?php echo $plan['is_popular'] ? 'popular' : ''; ?>" 
                 style="border-color: <?php echo $plan['color']; ?>">
                
                <?php if ($plan['is_popular']): ?>
                <div class="popular-badge">الأكثر طلباً</div>
                <?php endif; ?>
                
                <h2 style="color: <?php echo $plan['color']; ?>;"><?php echo $plan['name']; ?></h2>
                <p style="color: #b3b3b3; margin: 10px 0;"><?php echo $plan['description']; ?></p>
                
                <div class="plan-price">
                    <?php if ($plan['price_monthly'] == 0): ?>
                        مجاني
                    <?php else: ?>
                        <?php echo number_format($plan['price_monthly']); ?> ر.س <span style="font-size: 16px;">/ شهرياً</span>
                    <?php endif; ?>
                </div>
                
                <ul class="plan-features">
                    <li><i class="fas fa-check-circle" style="color: #27ae60;"></i> جودة <?php echo $plan['quality']; ?></li>
                    <li><i class="fas fa-<?php echo $plan['ads_free'] ? 'check-circle' : 'times-circle'; ?>" 
                           style="color: <?php echo $plan['ads_free'] ? '#27ae60' : '#e50914'; ?>;"></i> 
                        <?php echo $plan['ads_free'] ? 'بدون إعلانات' : 'مع إعلانات'; ?></li>
                    <li><i class="fas fa-<?php echo $plan['downloads_allowed'] ? 'check-circle' : 'times-circle'; ?>" 
                           style="color: <?php echo $plan['downloads_allowed'] ? '#27ae60' : '#e50914'; ?>;"></i> 
                        <?php echo $plan['downloads_allowed'] ? 'تحميل المشاهدة' : 'بدون تحميل'; ?></li>
                    <li><i class="fas fa-<?php echo $plan['early_access'] ? 'check-circle' : 'times-circle'; ?>" 
                           style="color: <?php echo $plan['early_access'] ? '#27ae60' : '#e50914'; ?>;"></i> 
                        <?php echo $plan['early_access'] ? 'وصول مبكر' : 'وصول عادي'; ?></li>
                </ul>
                
                <?php if ($plan['price_monthly'] > 0): ?>
                    <?php
                    $whatsapp_message = "السلام عليكم، أرغب في الاشتراك في الباقة " . $plan['name'] . ":\n";
                    $whatsapp_message .= "- الخطة: " . $plan['name'] . "\n";
                    $whatsapp_message .= "- السعر: " . number_format($plan['price_monthly']) . " ر.س/شهرياً\n";
                    $whatsapp_message .= "- المميزات:\n";
                    if ($plan['ads_free']) $whatsapp_message .= "  • بدون إعلانات\n";
                    if ($plan['quality'] == '4K') $whatsapp_message .= "  • جودة 4K UHD\n";
                    if ($plan['quality'] == '1080p') $whatsapp_message .= "  • جودة 1080p HD\n";
                    if ($plan['downloads_allowed']) $whatsapp_message .= "  • تحميل المشاهدة\n";
                    if ($plan['early_access']) $whatsapp_message .= "  • وصول مبكر\n";
                    $whatsapp_message .= "\nاسم المستخدم: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'زائر');
                    ?>
                    
                    <a href="https://wa.me/967776255680?text=<?php echo urlencode($whatsapp_message); ?>" 
                       target="_blank" 
                       class="whatsapp-btn">
                        <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
                        اشترك عبر واتساب
                    </a>
                <?php else: ?>
                    <a href="index.php" class="whatsapp-btn" style="background: #6c757d;">
                        <i class="fas fa-play"></i>
                        متابعة مجاناً
                    </a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div style="text-align: center; margin-top: 40px; padding: 20px; background: #1a1a1a; border-radius: 15px;">
            <p style="color: #25D366; font-size: 18px; margin-bottom: 10px;">
                <i class="fab fa-whatsapp" style="font-size: 24px;"></i>
                للاستفسار والدعم الفني عبر واتساب
            </p>
            <a href="https://wa.me/967776255680" target="_blank" style="color: #25D366; text-decoration: none; font-size: 24px; font-weight: bold;">
                776255680
            </a>
        </div>
    </div>
    
    <footer class="footer">
        <p>© 2024 ويزي برو - جميع الحقوق محفوظة</p>
    </footer>
</body>
</html>