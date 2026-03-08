<?php
// admin/payment-history.php - سجل المدفوعات
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// جلب سجل المدفوعات
$payments = $pdo->query("
    SELECT p.*, u.username, mp.name as plan_name 
    FROM payment_history p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN membership_plans mp ON p.plan_id = mp.id
    ORDER BY p.created_at DESC
    LIMIT 100
")->fetchAll();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>سجل المدفوعات - ويزي برو</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a1a;
            border-radius: 10px;
            overflow: hidden;
        }
        th {
            background: #252525;
            color: #e50914;
            padding: 15px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #333;
        }
        .status-completed { color: #27ae60; }
        .status-pending { color: #f39c12; }
        .status-failed { color: #e50914; }
    </style>
</head>
<body>
    <div class="container">
        <h1>سجل المدفوعات</h1>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>المستخدم</th>
                    <th>الخطة</th>
                    <th>المبلغ</th>
                    <th>طريقة الدفع</th>
                    <th>الحالة</th>
                    <th>تاريخ البداية</th>
                    <th>تاريخ النهاية</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $p): ?>
                <tr>
                    <td><?php echo $p['id']; ?></td>
                    <td><?php echo htmlspecialchars($p['username']); ?></td>
                    <td><?php echo $p['plan_name']; ?></td>
                    <td><?php echo $p['amount']; ?> ر.س</td>
                    <td><?php echo $p['payment_method']; ?></td>
                    <td class="status-<?php echo $p['status']; ?>"><?php echo $p['status']; ?></td>
                    <td><?php echo $p['start_date']; ?></td>
                    <td><?php echo $p['end_date']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>