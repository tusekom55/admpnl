<?php
echo "PHP çalışıyor - Test 1<br>";

try {
    require_once '../includes/functions_simple.php';
    echo "Functions_simple.php yüklendi - Test 2<br>";
    
    // Test basic functions
    $test_balance = getUserBalance(1, 'tl');
    echo "getUserBalance function çalışıyor - Test 3<br>";
    
    $test_currency = getTradingCurrency();
    echo "getTradingCurrency function çalışıyor - Test 4<br>";
    
    $test_format = formatNumber(1234.56);
    echo "formatNumber function çalışıyor - Test 5<br>";
    
    echo "Functions_simple.php başarıyla yüklendi ve test edildi!";
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage();
} catch (Error $e) {
    echo "Fatal Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
?>
