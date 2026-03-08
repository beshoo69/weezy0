<?php
// includes/config.php
session_start();

define('YOUTUBE_API_KEY', ''); // ضع مفتاح YouTube هنا (مثلاً من Google Developers Console)


$host = '127.0.0.1';  // أو 127.0.0.1:3307
$username = 'u447723745_weezy0';
$password = 'u447723745C452@weezy';
$dbname = 'u447723745_weezy0';
// $host = 'localhost:3306';  // أو 127.0.0.1:3307
// $username = 'root';
// $password = '';
// $dbname = 'fayez_movie';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}


?>
