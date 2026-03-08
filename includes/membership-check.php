<?php
// includes/membership-check.php - دوال التحقق من صلاحيات المشاهدة
// ============================================================

/**
 * التحقق من إمكانية مشاهدة المحتوى حسب مستوى العضوية
 */
function canViewContent($user_level, $required_level) {
    if ($required_level == 'basic') return true; // الجميع يمكنهم المشاهدة
    
    if ($required_level == 'premium') {
        return in_array($user_level, ['premium', 'vip']);
    }
    
    if ($required_level == 'vip') {
        return $user_level == 'vip';
    }
    
    return false;
}

/**
 * الحصول على مستوى عضوية المستخدم
 */
function getUserMembershipLevel($user_id) {
    global $pdo;
    
    if (!$user_id) return 'basic'; // زائر
    
    try {
        $stmt = $pdo->prepare("SELECT membership_plan_id, membership_status FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || $user['membership_status'] != 'active') {
            return 'basic';
        }
        
        // تحويل رقم الخطة إلى مستوى
        switch ($user['membership_plan_id']) {
            case 3: return 'vip';
            case 2: return 'premium';
            default: return 'basic';
        }
    } catch (Exception $e) {
        return 'basic';
    }
}

/**
 * عرض شارة العضوية
 */
function getMembershipBadge($level) {
    switch ($level) {
        case 'vip':
            return '<span class="membership-badge vip" title="محتوى VIP حصري"><i class="fas fa-crown"></i> VIP</span>';
        case 'premium':
            return '<span class="membership-badge premium" title="محتوى للمشتركين المميزين"><i class="fas fa-star"></i> مميز</span>';
        default:
            return '';
    }
}

/**
 * الحصول على لون مستوى العضوية
 */
function getMembershipColor($level) {
    switch ($level) {
        case 'vip': return 'gold';
        case 'premium': return '#e50914';
        default: return '#6c757d';
    }
}

/**
 * عرض شريط الاشتراك المطلوب
 */
function showMembershipRequiredBar($required_level, $redirect_url) {
    if ($required_level == 'basic') return '';
    
    $icon = $required_level == 'vip' ? 'fa-crown' : 'fa-star';
    $color = $required_level == 'vip' ? 'gold' : '#e50914';
    $title = $required_level == 'vip' ? 'VIP 👑' : 'مميز ⭐';
    
    $html = '
    <div class="membership-required-bar ' . $required_level . '" style="border-color: ' . $color . ';">
        <i class="fas ' . $icon . '" style="font-size: 40px; color: ' . $color . '; margin-bottom: 10px;"></i>
        <h3 style="color: ' . $color . ';">هذا المحتوى ' . $title . '</h3>
        <p style="color: #b3b3b3; margin: 10px 0;">اشترك الآن للاستمتاع بهذا المحتوى وجميع المميزات الحصرية</p>
        <a href="membership-plans.php?required=' . $required_level . '&redirect=' . urlencode($redirect_url) . '" 
           class="btn btn-primary" 
           style="background: ' . $color . '; color: ' . ($required_level == 'vip' ? 'black' : 'white') . '; padding: 12px 30px; border-radius: 50px; text-decoration: none; display: inline-block; margin-top: 10px;">
            <i class="fas ' . $icon . '"></i> اشترك الآن
        </a>
    </div>';
    
    return $html;
}
?>