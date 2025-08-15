<?php
echo "PHP çalışıyor - Test 1<br>";

try {
    require_once 'includes/admin_auth.php';
    echo "Admin_auth.php yüklendi - Test 2<br>";
    
    // Test requireAdmin function without calling it
    echo "Admin auth fonksiyonları yüklendi - Test 3<br>";
    
    // Test database connection
    $database = new Database();
    $db = $database->getConnection();
    echo "Database bağlantısı başarılı - Test 4<br>";
    
    // Test basic queries
    $query = "SELECT COUNT(*) as total FROM users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Database sorgusu başarılı - Test 5<br>";
    
    echo "Admin auth ve database testleri başarılı!";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
?>
