<?php
echo "PHP çalışıyor - Test 1<br>";

// İlk önce tüm config dosyalarını yükle
require_once '../config/database.php';
require_once '../config/api_keys.php';
require_once '../config/languages.php';

echo "Config dosyaları yüklendi - Test 2<br>";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

echo "Session başlatıldı - Test 3<br>";

// Şimdi functions.php'nin sadece başlangıç kısımlarını test edelim
try {
    // Check if user is logged in
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    echo "isLoggedIn function tanımlandı - Test 4<br>";

    // Check if user is admin
    function isAdmin() {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
    }
    echo "isAdmin function tanımlandı - Test 5<br>";

    // Format number with Turkish locale
    function formatNumber($number, $decimals = 2) {
        return number_format($number, $decimals, ',', '.');
    }
    echo "formatNumber function tanımlandı - Test 6<br>";

    echo "Temel functions tanımları başarılı!";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
?>
