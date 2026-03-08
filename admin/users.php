<?php
// admin/users.php - إدارة المستخدمين (نسخة مصححة)
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn() || $_SESSION['user_role'] != 'admin') {
    header('Location: login.php');
    exit;
}

// جلب جميع المستخدمين
$users = $pdo->query("
    SELECT u.*, mp.name as plan_name 
    FROM users u 
    LEFT JOIN membership_plans mp ON u.membership_plan_id = mp.id 
    ORDER BY u.id DESC
")->fetchAll();

// تحديث عضوية مستخدم
if (isset($_GET['activate'])) {
    $user_id = (int)$_GET['activate'];
    $plan_id = (int)$_GET['plan'];
    $months = isset($_GET['months']) ? (int)$_GET['months'] : 1;
    
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$months months"));
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET membership_plan_id = ?, membership_start = ?, membership_end = ?, 
            membership_status = 'active', last_payment = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$plan_id, $start_date, $end_date, $user_id]);
    
    // جلب معلومات الخطة
    $plan_stmt = $pdo->prepare("SELECT * FROM membership_plans WHERE id = ?");
    $plan_stmt->execute([$plan_id]);
    $plan = $plan_stmt->fetch();
    
    $amount = $plan ? $plan['price_monthly'] * $months : 0;
    
    // تسجيل في سجل المدفوعات
    $payment = $pdo->prepare("
        INSERT INTO payment_history (user_id, plan_id, amount, payment_method, status, start_date, end_date)
        VALUES (?, ?, ?, 'whatsapp', 'completed', ?, ?)
    ");
    $payment->execute([$user_id, $plan_id, $amount, $start_date, $end_date]);
    
    header('Location: users.php?success=1');
    exit;
}

// إحصائيات سريعة
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$active_members = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_status = 'active'")->fetchColumn();
$vip_count = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_plan_id = 3")->fetchColumn();
$premium_count = $pdo->query("SELECT COUNT(*) FROM users WHERE membership_plan_id = 2")->fetchColumn();
?>
    
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - ويزي برو</title>
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
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        .header {
            background: #0a0a0a;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #e50914;
            margin-bottom: 30px;
        }
        .logo h1 { color: #e50914; font-size: 28px; }
        .logo span { color: #fff; }
        .back-link {
            color: #fff;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        h1 {
            color: #e50914;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #1a1a1a;
            border-radius: 10px;
            padding: 20px;
            border: 1px solid #333;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #e50914;
        }
        .stat-label {
            color: #b3b3b3;
            font-size: 14px;
        }
        .search-box {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .search-box input {
            flex: 1;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
        }
        .search-box button {
            padding: 12px 25px;
            background: #e50914;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
        }
        .table-responsive {
            overflow-x: auto;
        }
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
            font-weight: 700;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #333;
        }
        tr:hover {
            background: #252525;
        }
        .btn {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: #fff;
            font-size: 13px;
            margin: 2px;
            display: inline-block;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-primary {
            background: #3498db;
        }
        .btn-danger {
            background: #e50914;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-active {
            background: #27ae60;
            color: #fff;
        }
        .badge-expired {
            background: #e50914;
            color: #fff;
        }
        .badge-vip {
            background: gold;
            color: #000;
        }
        .badge-premium {
            background: #e50914;
            color: #fff;
        }
        .badge-basic {
            background: #6c757d;
            color: #fff;
        }
        .badge-admin {
            background: #e50914;
            color: #fff;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        .modal.active {
            display: flex;
        }
        .modal-content {
            background: #1a1a1a;
            padding: 40px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            border: 2px solid #e50914;
        }
        .modal-content h2 {
            color: #e50914;
            margin-bottom: 20px;
        }
        .modal-content select {
            width: 100%;
            padding: 12px;
            background: #252525;
            border: 1px solid #333;
            color: #fff;
            border-radius: 5px;
            margin: 10px 0;
        }
        .modal-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .modal-buttons button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }
        .btn-confirm {
            background: #27ae60;
            color: #fff;
        }
        .btn-cancel {
            background: #e50914;
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <h1>ويزي<span>برو</span></h1>
        </div>
        <a href="dashboard-pro.php" class="back-link">
            <i class="fas fa-arrow-right"></i> العودة
        </a>
    </div>
    
    <div class="container">
        <h1>
            <i class="fas fa-users"></i>
            إدارة المستخدمين
        </h1>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_users ?: 0; ?></div>
                <div class="stat-label">إجمالي المستخدمين</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $active_members ?: 0; ?></div>
                <div class="stat-label">عضويات نشطة</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $vip_count ?: 0; ?></div>
                <div class="stat-label">مشتركي VIP</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $premium_count ?: 0; ?></div>
                <div class="stat-label">مشتركي مميز</div>
            </div>
        </div>
        
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="ابحث باسم المستخدم أو البريد الإلكتروني...">
            <button onclick="searchUsers()"><i class="fas fa-search"></i> بحث</button>
        </div>
        
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>اسم المستخدم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الصلاحية</th>
                        <th>العضوية</th>
                        <th>الحالة</th>
                        <th>تاريخ الانتهاء</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 30px;">لا يوجد مستخدمين</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): 
                            $badge_class = 'badge-basic';
                            if ($user['membership_plan_id'] == 3) $badge_class = 'badge-vip';
                            elseif ($user['membership_plan_id'] == 2) $badge_class = 'badge-premium';
                        ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['role'] == 'admin'): ?>
                                    <span class="badge badge-admin">مدير</span>
                                <?php else: ?>
                                    مستخدم
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $user['plan_name'] ?? 'عادي'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['membership_status'] == 'active' && isset($user['membership_end']) && strtotime($user['membership_end']) > time()): ?>
                                    <span class="badge badge-active">نشط</span>
                                <?php else: ?>
                                    <span class="badge badge-expired">منتهي</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo isset($user['membership_end']) ? date('Y-m-d', strtotime($user['membership_end'])) : '--'; ?></td>
                            <td>
                                <button class="btn btn-success" onclick="openActivateModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    <i class="fas fa-crown"></i> تفعيل
                                </button>
                                
                                <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <?php if ($user['role'] != 'admin'): ?>
                                <a href="delete-user.php?id=<?php echo $user['id']; ?>" class="btn btn-danger" onclick="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- نافذة تفعيل العضوية -->
    <div class="modal" id="activateModal">
        <div class="modal-content">
            <h2><i class="fas fa-crown" style="color: gold;"></i> تفعيل عضوية</h2>
            <p id="modalUsername" style="color: #fff; margin-bottom: 20px;"></p>
            
            <form id="activateForm" method="GET" action="users.php">
                <input type="hidden" name="activate" id="modalUserId">
                
                <select name="plan" required>
                    <option value="">-- اختر الخطة --</option>
                    <option value="2">⭐ مميزة - 29.99 ر.س/شهر</option>
                    <option value="3">👑 VIP - 49.99 ر.س/شهر</option>
                </select>
                
                <select name="months" required>
                    <option value="1">شهر واحد</option>
                    <option value="3">3 شهور</option>
                    <option value="6">6 شهور</option>
                    <option value="12">سنة كاملة (وفر أكثر)</option>
                </select>
                
                <div class="modal-buttons">
                    <button type="submit" class="btn-confirm">
                        <i class="fas fa-check"></i> تفعيل
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeModal()">
                        <i class="fas fa-times"></i> إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function openActivateModal(userId, username) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUsername').innerHTML = 'تفعيل عضوية للمستخدم: <strong style="color: #e50914;">' + username + '</strong>';
            document.getElementById('activateModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('activateModal').classList.remove('active');
        }
        
        function searchUsers() {
            var input = document.getElementById('searchInput').value.toLowerCase();
            var rows = document.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                var text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        // إغلاق عند الضغط خارج النافذة
        window.onclick = function(event) {
            var modal = document.getElementById('activateModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
    </script>
</body>
</html>