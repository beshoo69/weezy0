<?php
// includes/whatsapp.php - إعدادات واتساب
define('WHATSAPP_NUMBER', '967776255680'); // رقم الواتساب بدون +
define('WHATSAPP_LINK', 'https://wa.me/967776255680');

function getWhatsAppLink($message = '') {
    $base = 'https://wa.me/' . WHATSAPP_NUMBER;
    if (!empty($message)) {
        $base .= '?text=' . urlencode($message);
    }
    return $base;
}
?>