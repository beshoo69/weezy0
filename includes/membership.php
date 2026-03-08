<?php
// includes/membership.php - دوال التحقق من صلاحيات العضوية

/**
 * التحقق من صلاحية المستخدم
 */
function checkMembershipAccess($user_id, $required_level = 'basic') {
    global $pdo;
    
    if (!$user_id) return false;
    
    $stmt = $pdo->prepare("
        SELECT u.*, mp.name as plan_name, mp.quality, mp.max_devices, 
               mp.ads_free, mp.downloads_allowed, mp.early_access, mp.exclusive_content
        FROM users u
        LEFT JOIN membership_plans mp ON u.membership_plan_id = mp.id
        WHERE u.id = ? AND u.membership_status = 'active' 
        AND (u.membership_end IS NULL OR u.membership_end >= CURDATE())
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    // التحقق حسب المستوى المطلوب
    switch ($required_level) {
        case 'basic':
            return true; // الكل مسموح لهم
        case 'premium':
            return ($user['membership_plan_id'] >= 2); // مميز أو VIP
        case 'vip':
            return ($user['membership_plan_id'] == 3); // VIP فقط
        default:
            return false;
    }
}

/**
 * جلب صلاحيات المستخدم
 */
function getUserMembership($user_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, mp.name as plan_name, mp.name_en, mp.price_monthly, 
               mp.quality, mp.max_devices, mp.ads_free, mp.downloads_allowed, 
               mp.early_access, mp.exclusive_content
        FROM users u
        LEFT JOIN membership_plans mp ON u.membership_plan_id = mp.id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * التحقق من عدد الأجهزة المسموح بها
 */
function checkDeviceLimit($user_id) {
    global $pdo;
    
    // جلب صلاحيات المستخدم
    $membership = getUserMembership($user_id);
    if (!$membership) return true;
    
    $max_devices = $membership['max_devices'] ?? 1;
    
    // جلب الأجهزة النشطة
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as device_count 
        FROM user_devices 
        WHERE user_id = ? AND last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_id]);
    $device_count = $stmt->fetchColumn();
    
    return $device_count < $max_devices;
}

/**
 * تسجيل جهاز جديد
 */
function registerDevice($user_id, $device_name, $device_type, $device_id, $ip) {
    global $pdo;
    
    // حذف الأجهزة القديمة
    $stmt = $pdo->prepare("DELETE FROM user_devices WHERE last_active < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    
    // التحقق من وجود الجهاز
    $stmt = $pdo->prepare("SELECT id FROM user_devices WHERE user_id = ? AND device_id = ?");
    $stmt->execute([$user_id, $device_id]);
    
    if ($stmt->fetch()) {
        // تحديث آخر نشاط
        $stmt = $pdo->prepare("UPDATE user_devices SET last_active = NOW(), ip_address = ? WHERE user_id = ? AND device_id = ?");
        $stmt->execute([$ip, $user_id, $device_id]);
        return true;
    } else {
        // تسجيل جهاز جديد
        $stmt = $pdo->prepare("INSERT INTO user_devices (user_id, device_name, device_type, device_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $device_name, $device_type, $device_id, $ip]);
    }
}

/**
 * تجديد العضوية
 */
function renewMembership($user_id, $plan_id, $months = 1) {
    global $pdo;
    
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$months months"));
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET membership_plan_id = ?, membership_start = ?, membership_end = ?, 
            membership_status = 'active', last_payment = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$plan_id, $start_date, $end_date, $user_id]);
}

/**
 * إلغاء العضوية
 */
function cancelMembership($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE users SET membership_status = 'cancelled' WHERE id = ?");
    return $stmt->execute([$user_id]);
}

/**
 * تسجيل دفعة جديدة
 */
function addPayment($user_id, $plan_id, $amount, $method, $transaction_id) {
    global $pdo;
    
    // جلب مدة الخطة (افتراضياً شهر)
    $months = 1;
    
    // حساب تاريخ الانتهاء
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime("+$months months"));
    
    $stmt = $pdo->prepare("
        INSERT INTO payment_history (user_id, plan_id, amount, payment_method, transaction_id, status, start_date, end_date)
        VALUES (?, ?, ?, ?, ?, 'completed', ?, ?)
    ");
    
    return $stmt->execute([$user_id, $plan_id, $amount, $method, $transaction_id, $start_date, $end_date]);
}