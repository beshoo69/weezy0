<?php
// admin/check-movies.php
require_once __DIR__ . '/../includes/config.php';

$count = $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
echo "عدد الأفلام في قاعدة البيانات: " . $count;
?>