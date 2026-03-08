<?php
// checkout.php - صفحة إتمام الدفع
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/membership.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$user_id = $_SESSION['user_id'];
$plan_id = isset($_GET['plan']) ? (int)$_GET['plan'] : 0;

// جلب معلومات الخطة
$stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = ? AND is_active = TRUE");
$stmt->execute([$plan_id]);
$plan = $stmt->fetch();

if (!$plan) {
    header('Location: membership-plans.php');
    exit;
}

// معالجة الدفع
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'];
    $billing_period = $_POST['billing_period']; // monthly or yearly
    
    $amount = ($billing_period == 'yearly') ? $plan['price_yearly'] : $plan['price_monthly'];
    $months = ($billing_period == 'yearly') ? 12 : 1;
    
    // هنا يمكنك إضافة بوابات الدفع الفعلية
    // Stripe, PayPal, إلخ
    
    // تجديد العضوية
    if (renewMembership($user_id, $plan_id, $months)) {
        // تسجيل الدفعة
        $transaction_id = 'TXN_' . time() . '_' . $user_id;
        addPayment($user_id, $plan_id, $amount, $payment_method, $transaction_id);
        
        $_SESSION['success_message'] = "تم تفعيل اشتراكك بنجاح!";
        header('Location: profile.php');
        exit;
    } else {
        $message = "حدث خطأ في تفعيل الاشتراك";
        $messageType = "error";
    }
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إتمام الدفع - ويزي برو</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body {
            font-family: 'Tajawal', sans-serif;
            background: #0f0f0f;
            color: #fff;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: #1a1a1a;
            border-radius: 15px;
            padding: 30px;
            border: 1px solid #333;
        }
        h1 {
            color: #e50914;
            margin-bottom: 30px;
        }
        .plan-summary {
            background: #252525;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            border-right: 4px solid <?php echo $plan['color']; ?>;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #b3b3b3;
        }
        select, input {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
        }
        button {
            width: 100%;
            padding: 15px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background: #219a52;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>إتمام الاشتراك</h1>
        
        <div class="plan-summary">
            <h2 style="color: <?php echo $plan['color']; ?>;"><?php echo $plan['name']; ?></h2>
            <p><?php echo $plan['description']; ?></p>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>فترة الاشتراك</label>
                <select name="billing_period" id="billingPeriod" onchange="updatePrice()">
                    <option value="monthly">شهري - <?php echo number_format($plan['price_monthly']); ?> ر.س/شهر</option>
                    <option value="yearly">سنوي - <?php echo number_format($plan['price_yearly']); ?> ر.س/سنة (وفر <?php echo number_format(($plan['price_monthly'] * 12) - $plan['price_yearly']); ?> ر.س)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>طريقة الدفع</label>
                <select name="payment_method">
                    <option value="credit_card">بطاقة ائتمان</option>
                    <option value="paypal">PayPal</option>
                    <option value="bank_transfer">تحويل بنكي</option>
                    <option value="mada">مدى</option>
                </select>
            </div>
            
            <button type="submit">تأكيد الدفع</button>
        </form>
    </div>
    
    <script>
        function updatePrice() {
            // يمكنك إضافة منطق تحديث السعر هنا
        }
    </script>
</body>
</html>