<?php
require_once 'includes/functions.php';

// Require admin access for this debug script
if (!isLoggedIn() || !isAdmin()) {
    echo "Bu sayfaya erişmek için admin girişi gereklidir.";
    exit();
}

$database = new Database();
$db = $database->getConnection();

echo "<html><head><title>Transaction Types Debug</title></head><body>";
echo "<h2>Transaction Types Analysis</h2>";

try {
    // Get all unique transaction types
    $query = "SELECT type, COUNT(*) as count FROM transactions GROUP BY type ORDER BY count DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Transaction Types in Database:</h3>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th style='padding: 8px;'>Transaction Type</th><th style='padding: 8px;'>Count</th><th style='padding: 8px;'>Status</th></tr>";
    
    $supported_types = ['buy', 'sell', 'LEVERAGE_LONG', 'LEVERAGE_SHORT', 'CLOSE_LONG', 'CLOSE_SHORT'];
    
    foreach ($types as $type_data) {
        $type = $type_data['type'];
        $count = $type_data['count'];
        
        if (in_array($type, $supported_types)) {
            $status = "<span style='color: green;'>✓ Supported</span>";
        } else {
            $status = "<span style='color: red;'>❌ Causes Question Mark</span>";
        }
        
        echo "<tr>";
        echo "<td style='padding: 8px;'><strong>$type</strong></td>";
        echo "<td style='padding: 8px;'>$count</td>";
        echo "<td style='padding: 8px;'>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show recent transactions with unsupported types
    echo "<h3>Recent Transactions with Unsupported Types:</h3>";
    
    $unsupported_types = array_filter(array_column($types, 'type'), function($type) use ($supported_types) {
        return !in_array($type, $supported_types);
    });
    
    if (!empty($unsupported_types)) {
        $placeholders = str_repeat('?,', count($unsupported_types) - 1) . '?';
        $query = "SELECT t.*, u.username FROM transactions t 
                  LEFT JOIN users u ON t.user_id = u.id 
                  WHERE t.type IN ($placeholders) 
                  ORDER BY t.created_at DESC LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute($unsupported_types);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th style='padding: 8px;'>User</th><th style='padding: 8px;'>Type</th><th style='padding: 8px;'>Symbol</th><th style='padding: 8px;'>Amount</th><th style='padding: 8px;'>Date</th></tr>";
        
        foreach ($transactions as $tx) {
            echo "<tr>";
            echo "<td style='padding: 8px;'>" . ($tx['username'] ?? 'Unknown') . "</td>";
            echo "<td style='padding: 8px;'><strong style='color: red;'>" . $tx['type'] . "</strong></td>";
            echo "<td style='padding: 8px;'>" . $tx['symbol'] . "</td>";
            echo "<td style='padding: 8px;'>" . $tx['amount'] . "</td>";
            echo "<td style='padding: 8px;'>" . date('Y-m-d H:i', strtotime($tx['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: green;'>✓ All transaction types are supported!</p>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='portfolio.php'>← Back to Portfolio</a>";
echo "</body></html>";
?>
