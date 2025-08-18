<?php
require_once 'includes/functions.php';

echo "<h2>Transaction Types Direct Database Check</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all unique transaction types with counts
    $query = "SELECT type, COUNT(*) as count FROM transactions GROUP BY type ORDER BY count DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Transaction Types in Database:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Count</th></tr>";
    
    foreach ($types as $type_data) {
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>" . htmlspecialchars($type_data['type']) . "</strong></td>";
        echo "<td style='padding: 8px;'>" . $type_data['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show sample transactions
    echo "<h3>Sample Transactions (Last 10):</h3>";
    $query2 = "SELECT id, user_id, type, symbol, amount, created_at FROM transactions ORDER BY created_at DESC LIMIT 10";
    $stmt2 = $db->prepare($query2);
    $stmt2->execute();
    $transactions = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th style='padding: 8px;'>ID</th><th style='padding: 8px;'>User</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Symbol</th><th style='padding: 8px;'>Amount</th><th style='padding: 8px;'>Date</th></tr>";
    
    foreach ($transactions as $tx) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . $tx['id'] . "</td>";
        echo "<td style='padding: 8px;'>" . $tx['user_id'] . "</td>";
        echo "<td style='padding: 8px;'><strong style='color: red;'>" . htmlspecialchars($tx['type']) . "</strong></td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($tx['symbol']) . "</td>";
        echo "<td style='padding: 8px;'>" . $tx['amount'] . "</td>";
        echo "<td style='padding: 8px;'>" . $tx['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
