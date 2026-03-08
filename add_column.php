<?php
require 'includes/config.php';
require 'includes/database.php';

try {
    $pdo->exec('ALTER TABLE download_servers ADD COLUMN is_valid TINYINT(1) DEFAULT 1');
    echo 'Column is_valid added successfully to download_servers table';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>