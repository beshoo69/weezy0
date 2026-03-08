<?php
require_once 'includes/config.php';

if (!isset($_GET['id'])) {
    echo 'لم يتم تحديد ID للفيلم';
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات الفيلم من قاعدة البيانات
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute([$id]);
$movie = $stmt->fetch();

if (!$movie) {
    echo 'الفيلم غير موجود';
    exit;
}

// اختبار التحديث
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    
    error_log("تحديث الفيلم $id بالعنوان: $title");
    
    $sql = "UPDATE movies SET title = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([$title, $id]);
    
    error_log("نتيجة التحديث: " . ($result ? 'نجح' : 'فشل') . " - عدد الصفوف المتأثرة: " . $stmt->rowCount());
    
    // تحقق من التحديث
    $verify_stmt = $pdo->prepare("SELECT title FROM movies WHERE id = ?");
    $verify_stmt->execute([$id]);
    $updated = $verify_stmt->fetch();
    
    echo 'تم التحديث. العنوان الجديد: ' . $updated['title'] . '<br>';
}
?>

<form method="POST">
    <input type="text" name="title" value="<?php echo htmlspecialchars($movie['title']); ?>">
    <button type="submit">تحديث</button>
</form>

العنوان الحالي: <?php echo htmlspecialchars($movie['title']); ?>
