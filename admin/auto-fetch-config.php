<?php
// admin/auto-fetch-config.php - إعدادات الجلب التلقائي

define('TMDB_API_KEY', '5dc3e335b09cbf701d8685dd9a766949');
define('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/');

// إعدادات الجلب
$fetch_settings = [
    'movies' => [
        'enabled' => true,
        'categories' => [
            'popular' => 'الأكثر شهرة',
            'now_playing' => 'الآن في السينما',
            'upcoming' => 'القادمة قريباً',
            'top_rated' => 'الأعلى تقييماً'
        ],
        'max_per_category' => 20,
        'auto_approve' => false // false = تحتاج مراجعة قبل النشر
    ],
    'series' => [
        'enabled' => true,
        'categories' => [
            'popular' => 'الأكثر شهرة',
            'airing_today' => 'يعرض اليوم',
            'on_the_air' => 'يعرض حالياً',
            'top_rated' => 'الأعلى تقييماً'
        ],
        'max_per_category' => 20,
        'auto_approve' => false
    ]
];
?>