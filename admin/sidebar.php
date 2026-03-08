<?php
// admin/sidebar.php - الشريط الجانبي الموحد مع جميع الروابط
if (!defined('ALLOW_ACCESS')) {
    die('الوصول المباشر غير مسموح');
}

// دالة للتحقق من الصفحة النشطة
function isActive($page) {
    return basename($_SERVER['PHP_SELF']) == $page ? 'active' : '';
}
?>

<!-- الشريط الجانبي -->
<div class="sidebar">
    <!-- ===== الرئيسية ===== -->
    <div class="nav-section">الرئيسية</div>
    <a href="dashboard.php" class="nav-item <?php echo isActive('dashboard.php'); ?>">
        <i class="fas fa-tachometer-alt"></i> لوحة التحكم الرئيسية
    </a>
    <a href="../index.php" target="_blank" class="nav-item">
        <i class="fas fa-globe"></i> زيارة الموقع
    </a>
    
    <!-- ===== إدارة المستخدمين ===== -->
    <div class="nav-section">👥 إدارة المستخدمين</div>
    <a href="users.php" class="nav-item <?php echo isActive('users.php'); ?>">
        <i class="fas fa-users"></i> قائمة المستخدمين
    </a>
    <a href="../membership-plans.php" class="nav-item <?php echo isActive('membership-plans.php'); ?>">
        <i class="fas fa-crown"></i> خطط العضوية
    </a>
    <a href="payment-history.php" class="nav-item <?php echo isActive('payment-history.php'); ?>">
        <i class="fas fa-credit-card"></i> سجل المدفوعات
    </a>
    
    <!-- ===== استيراد من TMDB ===== -->
    <div class="nav-section">🎬 استيراد وتعديل المحتوئ</div>
    <a href="import-movies.php" class="nav-item <?php echo isActive('import-movies.php'); ?>">
        <i class="fas fa-film"></i> استيراد أفلام  
    </a>
    <a href="import-series.php" class="nav-item <?php echo isActive('import-series.php'); ?>">
        <i class="fas fa-tv"></i> استيراد مسلسلات 
    </a>
    <a href="edit-movie.php" class="nav-item <?php echo isActive('edit-movie.php'); ?>">
        <i class="fas fa-edit"></i> تعديل الأفلام
    </a>
    <a href="edit-series.php" class="nav-item <?php echo isActive('edit-series.php'); ?>">
        <i class="fas fa-edit"></i> تعديل المسلسلات
    </a>
    <div class="nav-section">📺 محتوى يوتيوب</div>
    <a href="content-manager.php" class="nav-item <?php echo isActive('youtube-series-manager.php'); ?>">
        <i class="fas fa-list"></i> إدارة محتوئ اليوتيوب
    </a>

    <!-- ===== إدارة الصور ===== -->
    <div class="nav-section">🖼️ إدارة الصور</div>
    <a href="manual-edit-posters.php" class="nav-item <?php echo isActive('manual-edit-posters.php'); ?>">
        <i class="fas fa-edit"></i> تعديل الصور يدوياً
    </a>
    <a href="tmdb-image-finder.php" class="nav-item <?php echo isActive('tmdb-image-finder.php'); ?>">
        <i class="fas fa-search"></i> بحث عن صور 
    </a>

    <!-- ===== حذف محتوى ===== -->
    <div class="nav-section">🗑️ حذف محتوى</div>
    <a href="delete-content.php" class="nav-item <?php echo isActive('delete-content.php'); ?>">
        <i class="fas fa-trash-alt"></i> حذف محتوى
    </a>

    <!-- ===== إدارة المحتوى الرئيسي ===== -->
    <div class="nav-section">📋 إدارة المحتوى</div>
    <a href="movies.php" class="nav-item <?php echo isActive('movies.php'); ?>">
        <i class="fas fa-film"></i> عرض الأفلام
    </a>
    <a href="series.php" class="nav-item <?php echo isActive('series.php'); ?>">
        <i class="fas fa-tv"></i> عرض المسلسلات
    </a>
    <a href="episodes.php" class="nav-item <?php echo isActive('episodes.php'); ?>">
        <i class="fas fa-play-circle"></i> إدارة الحلقات
    </a>
    
    <!-- ===== تعديل المحتوى ===== -->
    <!-- <div class="nav-section">✏️ تعديل المحتوى</div>
    <a href="edit-movie.php" class="nav-item <?php echo isActive('edit-movie.php'); ?>">
        <i class="fas fa-edit"></i> تعديل الأفلام
    </a>
    <a href="edit-series.php" class="nav-item <?php echo isActive('edit-series.php'); ?>">
        <i class="fas fa-edit"></i> تعديل المسلسلات
    </a> -->
    
    <!-- ===== محتوى يوتيوب ===== -->
    
    <!-- =====  إدارة الأقسام ===== -->
     <!-- لبعدين هذا  -->
<!-- <div class="nav-section">📋 إدارة المحتوى</div>
<a href="sections-manager.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'sections-manager.php' ? 'active' : ''; ?>">
    <i class="fas fa-layer-group"></i> إدارة الأقسام
     
</a> -->
   
    
    
    
    
    
    <!-- ===== استيرادات متخصصة ===== -->
    <div class="nav-section">🌍 استيرادات متخصصة</div>
    <a href="import-arabic-movies.php" class="nav-item <?php echo isActive('import-arabic-movies.php'); ?>">
        <i class="fas fa-film"></i> أفلام عربية
    </a>
    <a href="import-egyptian-movies-2026.php" class="nav-item <?php echo isActive('import-egyptian-movies-2026.php'); ?>">
        <i class="fas fa-film"></i> أفلام مصرية 2026
    </a>
    <a href="import-egyptian-series-2026.php" class="nav-item <?php echo isActive('import-egyptian-series-2026.php'); ?>">
        <i class="fas fa-tv"></i> مسلسلات مصرية 2026
    </a>
    <a href="import-turkish-movies-2025-2026.php" class="nav-item <?php echo isActive('import-turkish-movies-2025-2026.php'); ?>">
        <i class="fas fa-film"></i> أفلام تركية 2025-26
    </a>
    <a href="import-turkish-series-2025-2026.php" class="nav-item <?php echo isActive('import-turkish-series-2025-2026.php'); ?>">
        <i class="fas fa-tv"></i> مسلسلات تركية 2025-26
    </a>
    <a href="import-indian-movies-2025-2026.php" class="nav-item <?php echo isActive('import-indian-movies-2025-2026.php'); ?>">
        <i class="fas fa-film"></i> أفلام هندية 2025-26
    </a>
    <a href="import-indian-series-2025-2026.php" class="nav-item <?php echo isActive('import-indian-series-2025-2026.php'); ?>">
        <i class="fas fa-tv"></i> مسلسلات هندية 2025-26
    </a>
    <a href="import-asian-movies-2025-2026.php" class="nav-item <?php echo isActive('import-asian-movies-2025-2026.php'); ?>">
        <i class="fas fa-film"></i> أفلام آسيوية 2025-26
    </a>
    <a href="import-asian-series-2025-2026.php" class="nav-item <?php echo isActive('import-asian-series-2025-2026.php'); ?>">
        <i class="fas fa-tv"></i> مسلسلات آسيوية 2025-26
    </a>
    
    <!-- ===== أنمي ===== -->
    <div class="nav-section">🇯🇵 أنمي</div>
    <a href="import-anime.php" class="nav-item <?php echo isActive('import-anime.php'); ?>">
        <i class="fas fa-dragon"></i> استيراد أنمي
    </a>
    <a href="import-all-anime.php" class="nav-item <?php echo isActive('import-all-anime.php'); ?>">
        <i class="fas fa-dragon"></i> جميع الأنمي
    </a>
    <a href="anime-series.php" class="nav-item <?php echo isActive('anime-series.php'); ?>">
        <i class="fas fa-tv"></i> مسلسلات أنمي
    </a>
    
    
    

    
    
    <!-- ===== النظام ===== -->
    <div class="nav-section">⚙️ النظام</div>
    <a href="logout.php" class="nav-item <?php echo isActive('logout.php'); ?>">
        <i class="fas fa-sign-out-alt"></i> تسجيل خروج
    </a>
</div>