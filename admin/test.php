<?php
echo "PHP çalışıyor - Test 1<br>";

try {
    require_once '../config/database.php';
    echo "Database config yüklendi - Test 2<br>";
    
    $database = new Database();
    echo "Database class oluşturuldu - Test 3<br>";
    
    $db = $database->getConnection();
    echo "Database bağlantısı başarılı - Test 4<br>";
    
    require_once '../includes/functions.php';
    echo "Functions.php yüklendi - Test 5<br>";
    
    echo "Tüm testler başarılı!";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
}
?>
