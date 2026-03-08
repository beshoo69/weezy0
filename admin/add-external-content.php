<!-- admin/add-external-content.php -->
<?php
// قالب لإضافة محتوى من خدمات البث المختلفة
$streaming_services = [
    'netflix' => [
        'name' => 'Netflix',
        'icon' => 'fab fa-netflix',
        'color' => '#E50914',
        'embed_code' => 'https://www.netflix.com/watch/{{ID}}',
        'instructions' => 'افتح الفيلم في Netflix، انسخ الرابط من المتصفح'
    ],
    'shahid' => [
        'name' => 'Shahid VIP',
        'icon' => 'fas fa-tv',
        'color' => '#E7040D',
        'embed_code' => 'https://shahid.mbc.net/ar/watch/{{ID}}',
        'instructions' => 'افتح المحتوى في Shahid وانسخ الرابط'
    ],
    'amazon' => [
        'name' => 'Amazon Prime',
        'icon' => 'fab fa-amazon',
        'color' => '#00A8E1',
        'embed_code' => 'https://www.primevideo.com/detail/{{ID}}',
        'instructions' => 'انسخ الرابط من متصفحك بعد فتح الفيلم'
    ],
    'disney' => [
        'name' => 'Disney+',
        'icon' => 'fab fa-disney',
        'color' => '#113CCF',
        'embed_code' => 'https://www.disneyplus.com/video/{{ID}}',
        'instructions' => 'شارك الرابط من Disney+'
    ],
    'apple' => [
        'name' => 'Apple TV+',
        'icon' => 'fab fa-apple',
        'color' => '#000000',
        'embed_code' => 'https://tv.apple.com/{{ID}}',
        'instructions' => 'انسخ الرابط من Apple TV'
    ],
    'starzplay' => [
        'name' => 'Starzplay',
        'icon' => 'fas fa-star',
        'color' => '#000000',
        'embed_code' => 'https://www.starzplay.com/{{ID}}',
        'instructions' => 'انسخ الرابط من Starzplay'
    ],
    'hulu' => [
        'name' => 'Hulu',
        'icon' => 'fas fa-h',
        'color' => '#1CE783',
        'embed_code' => 'https://www.hulu.com/watch/{{ID}}',
        'instructions' => 'انسخ الرابط من Hulu'
    ]
];
?>

<div class="external-content-section">
    <div class="section-header">
        <h2><i class="fas fa-link"></i> إضافة محتوى من خدمات البث</h2>
        <p class="text-muted">أضف أفلام ومسلسلات من اشتراكاتك الخاصة</p>
    </div>
    
    <!-- اختيار الخدمة -->
    <div class="services-grid">
        <?php foreach ($streaming_services as $key => $service): ?>
        <div class="service-card" onclick="selectService('<?php echo $key; ?>')" style="border-color: <?php echo $service['color']; ?>">
            <div class="service-icon" style="background: <?php echo $service['color']; ?>">
                <i class="<?php echo $service['icon']; ?>"></i>
            </div>
            <h3><?php echo $service['name']; ?></h3>
            <p class="small">اضغط للاختيار</p>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- نموذج الإضافة (يظهر بعد اختيار الخدمة) -->
    <div id="addForm" style="display: none;" class="add-form">
        <form method="POST" action="save-external-content.php">
            <input type="hidden" name="service" id="selectedService">
            
            <div class="form-group">
                <label>رابط المحتوى من الخدمة</label>
                <input type="url" name="content_url" required placeholder="الصق الرابط هنا">
                <small>مثال: https://www.netflix.com/watch/81243987</small>
            </div>
            
            <div class="form-group">
                <label>أو أدخل الرقم التعريفي فقط</label>
                <input type="text" name="content_id" placeholder="مثال: 81243987">
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> إضافة المحتوى
            </button>
        </form>
    </div>
</div>

<style>
.services-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin: 30px 0;
}

.service-card {
    background: #1a1a1a;
    border: 2px solid #333;
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: 0.3s;
}

.service-card:hover {
    transform: translateY(-5px);
    border-color: inherit;
}

.service-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    margin: 0 auto 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.add-form {
    background: #1a1a1a;
    border-radius: 20px;
    padding: 30px;
    margin-top: 30px;
    border: 1px solid #333;
}
</style>

<script>
function selectService(service) {
    document.getElementById('selectedService').value = service;
    document.getElementById('addForm').style.display = 'block';
    
    // تمرير سلس للنموذج
    document.getElementById('addForm').scrollIntoView({ behavior: 'smooth' });
}
</script>