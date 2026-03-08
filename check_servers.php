<?php
require 'includes/config.php';
require 'includes/database.php';

$stmt = $pdo->query('SELECT COUNT(*) as count FROM download_servers');
$result = $stmt->fetch();
echo 'Total download servers: ' . $result['count'] . PHP_EOL;

if ($result['count'] > 0) {
    $stmt = $pdo->query('SELECT id, server_name, download_url FROM download_servers LIMIT 5');
    $servers = $stmt->fetchAll();
    foreach ($servers as $server) {
        echo "ID: {$server['id']}, Name: {$server['server_name']}, URL: " . substr($server['download_url'], 0, 50) . "..." . PHP_EOL;
    }
}
?>