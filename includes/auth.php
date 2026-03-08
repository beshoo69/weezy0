<?php
// includes/auth.php - نظام تسجيل الدخول

/**
 * تسجيل الدخول
 */
function login($username, $password) {
    global $pdo;
    
    // حساب افتراضي للتجربة
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['user_role'] = 'admin';
        return true;
    }
    
    // التحقق من قاعدة البيانات (للمستقبل)
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            return true;
        }
    } catch (Exception $e) {
        logError("Login error: " . $e->getMessage());
    }
    
    return false;
}

/**
 * تسجيل الخروج
 */
function logout() {
    session_destroy();
    redirect('../admin/login.php');
}

/**
 * التحقق من الصلاحية
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('../admin/login.php');
    }
}

/**
 * التحقق من صلاحية الأدمن
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        die('❌ لا تملك الصلاحية للوصول إلى هذه الصفحة');
    }
}
?>