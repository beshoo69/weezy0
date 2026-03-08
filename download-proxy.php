<?php
// download-proxy.php - Proxy for downloading files to handle temporary links
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

$server_id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM download_servers WHERE id = ?");
$stmt->execute([$server_id]);
$server = $stmt->fetch();

if (!$server) {
    http_response_code(404);
    echo "Server not found";
    exit;
}

$download_url = $server['download_url'];

if (empty($download_url)) {
    http_response_code(404);
    echo "Download URL not available";
    exit;
}

// Attempt to fetch the file
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $download_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minutes timeout
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

// Get headers first to determine content type
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

if ($http_code !== 200) {
    // تحديث حالة الرابط إلى غير صالح
    $update_stmt = $pdo->prepare("UPDATE download_servers SET is_valid = 0 WHERE id = ?");
    $update_stmt->execute([$server_id]);
    
    http_response_code(404);
    echo "Failed to fetch file: HTTP $http_code. Link has expired.";
    curl_close($ch);
    exit;
}

// Set headers for download
header('Content-Type: ' . ($content_type ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . ($server['server_name'] . '_' . $server['quality'] . '.mp4') . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Now fetch and output the content
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
curl_exec($ch);
curl_close($ch);
?>