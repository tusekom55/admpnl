<?php
echo "PHP çalışıyor - Test 1<br>";

try {
    require_once '../config/database.php';
    echo "Database config yüklendi - Test 2<br>";
    
    require_once '../config/api_keys.php';
    echo "API keys yüklendi - Test 3<br>";
    
    require_once '../config/languages.php';
    echo "Languages yüklendi - Test 4<br>";
    
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    echo "Session başlatıldı - Test 5<br>";
    
    echo "Tüm config dosyaları başarılı!";
    
} catch (Exception $e) {
    echo "Hata: " . $e->getMessage();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage();
}
?>
