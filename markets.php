<?php
require_once 'includes/functions.php';

$page_title = 'GlobalBorsa - Financial Markets';

// Get current category
$category = $_GET['group'] ?? 'us_stocks';
$valid_categories = array_keys(getFinancialCategories());
if (!in_array($category, $valid_categories)) {
    $category = 'us_stocks';
}

// Get market data
$markets = getMarketData($category, 50);

// Get trading currency settings for modals
$trading_currency = getTradingCurrency();
$currency_field = getCurrencyField($trading_currency);
$currency_symbol = getCurrencySymbol($trading_currency);
$usd_try_rate = getUSDTRYRate();

// Get user balances if logged in
$user_balances = [];
if (isLoggedIn()) {
    $user_id = $_SESSION['user_id'];
    $user_balances = [
        'primary' => getUserBalance($user_id, $currency_field),
        'tl' => getUserBalance($user_id, 'tl'),
        'usd' => getUserBalance($user_id, 'usd'),
        'btc' => getUserBalance($user_id, 'btc'),
        'eth' => getUserBalance($user_id, 'eth')
    ];
}

// Clean Simple Trading Form Handler
if ($_POST && isset($_POST['trade_action']) && isLoggedIn()) {
    $trade_action = $_POST['trade_action']; // 'buy' or 'sell'
    $symbol = $_POST['symbol'] ?? '';
    $usd_amount = (float)($_POST['usd_amount'] ?? 0);
    
    // Get market data for this symbol
    $current_market = null;
    foreach ($markets as $market) {
        if ($market['symbol'] === $symbol) {
            $current_market = $market;
            break;
        }
    }
    
    if ($current_market && $usd_amount > 0) {
        $usd_price = (float)$current_market['price'];
        
        // Başlangıç bilgilerini topla
        $debug_info = [];
        $debug_info[] = "User ID: " . $_SESSION['user_id'];
        $debug_info[] = "Symbol: " . $symbol;
        $debug_info[] = "Action: " . $trade_action;
        $debug_info[] = "USD Amount: " . $usd_amount;
        $debug_info[] = "USD Price: " . $usd_price;
        $debug_info[] = "Trading Currency: " . getTradingCurrency();
        $debug_info[] = "TL Balance: " . getUserBalance($_SESSION['user_id'], 'tl');
        $debug_info[] = "USD Balance: " . getUserBalance($_SESSION['user_id'], 'usd');
        
        // Test each function step by step
        $step_results = [];
        
        // Step 1: Test database connection
        $database = new Database();
        $db = $database->getConnection();
        $step_results['database'] = $db ? '✅ SUCCESS' : '❌ FAILED';
        
        // Step 2: Test getTradingCurrency
        $trading_currency = getTradingCurrency();
        $step_results['trading_currency'] = $trading_currency ? "✅ SUCCESS ($trading_currency)" : '❌ FAILED';
        
        // Step 3: Test getUserBalance
        if ($trading_currency == 2) { // USD Mode
            $usd_balance = getUserBalance($_SESSION['user_id'], 'usd');
            $fee_usd = $usd_amount * 0.001;
            $total_usd = $usd_amount + $fee_usd;
            $step_results['balance_check'] = $usd_balance >= $total_usd ? "✅ YETER ($usd_balance >= $total_usd)" : "❌ YETMİYOR ($usd_balance < $total_usd)";
            
            // Step 4: Test updateUserBalance function
            if ($usd_balance >= $total_usd) {
                // Don't actually update, just test the function exists and is callable
                $step_results['update_function'] = function_exists('updateUserBalance') ? '✅ FUNCTION EXISTS' : '❌ FUNCTION MISSING';
                
                // Step 5: Test database transaction
                try {
                    $transaction_test = $db->beginTransaction();
                    $step_results['begin_transaction'] = $transaction_test ? '✅ SUCCESS' : '❌ FAILED';
                    $db->rollback(); // Rollback test transaction
                } catch (Exception $e) {
                    $step_results['begin_transaction'] = '❌ ERROR: ' . $e->getMessage();
                }
            }
        }
        
        // Execute simple trade
        $trade_result = executeSimpleTrade($_SESSION['user_id'], $symbol, $trade_action, $usd_amount, $usd_price);
        
        if ($trade_result) {
            // Clear any existing error messages first
            unset($_SESSION['trade_error']);
            
            // Detailed success message with trade info
            $action_text = $trade_action == 'buy' ? 'ALINDI' : 'SATILDI';
            $detailed_message = "$usd_amount USD $symbol $action_text";
            
            $_SESSION['trade_success'] = $detailed_message;
            header('Location: markets.php?group=' . $category);
            exit();
        } else {
            // Clear any existing success messages first
            unset($_SESSION['trade_success']);
            
            // Debug ekranı göster
            echo "<div style='position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.9); z-index: 999999; color: white; font-family: monospace; font-size: 14px; padding: 20px; overflow-y: auto;'>";
            echo "<h2 style='color: #ff6b6b;'>🔍 TRADE DEBUG - İşlem Başarısız</h2>";
            echo "<button onclick='this.parentElement.style.display=\"none\"' style='position: absolute; top: 10px; right: 10px; background: #ff6b6b; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer;'>KAPAT</button>";
            
            echo "<div style='background: #2d3748; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3 style='color: #63b3ed;'>📊 İşlem Detayları:</h3>";
            foreach($debug_info as $info) {
                echo $info . "<br>";
            }
            echo "</div>";
            
            // NEW: Show step by step test results
            echo "<div style='background: #1a365d; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3 style='color: #63b3ed;'>🧪 Step-by-Step Test Results:</h3>";
            foreach($step_results as $step => $result) {
                echo "<strong>$step:</strong> $result<br>";
            }
            echo "</div>";
            
            // Hesaplama kontrolü
            $trading_currency = getTradingCurrency();
            if ($trading_currency == 2) { // USD Mode
                $fee_usd = $usd_amount * 0.001;
                $total_usd = $usd_amount + $fee_usd;
                $usd_balance = getUserBalance($_SESSION['user_id'], 'usd');
                
                echo "<div style='background: #2d3748; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3 style='color: #68d391;'>💰 USD Mode Hesaplama:</h3>";
                echo "USD Amount: " . $usd_amount . "<br>";
                echo "Fee USD: " . $fee_usd . "<br>";
                echo "Total USD: " . $total_usd . "<br>";
                echo "USD Balance: " . $usd_balance . "<br>";
                echo "Balance Check: " . ($usd_balance >= $total_usd ? "✅ YETER" : "❌ YETMİYOR") . "<br>";
                echo "</div>";
            } else {
                $usd_to_tl_rate = getUSDTRYRate();
                $tl_amount = $usd_amount * $usd_to_tl_rate;
                $fee_tl = $tl_amount * 0.001;
                $total_tl = $tl_amount + $fee_tl;
                $tl_balance = getUserBalance($_SESSION['user_id'], 'tl');
                
                echo "<div style='background: #2d3748; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                echo "<h3 style='color: #68d391;'>💰 TL Mode Hesaplama:</h3>";
                echo "USD/TRY Rate: " . $usd_to_tl_rate . "<br>";
                echo "TL Amount: " . $tl_amount . "<br>";
                echo "Fee TL: " . $fee_tl . "<br>";
                echo "Total TL: " . $total_tl . "<br>";
                echo "TL Balance: " . $tl_balance . "<br>";
                echo "Balance Check: " . ($tl_balance >= $total_tl ? "✅ YETER" : "❌ YETMİYOR") . "<br>";
                echo "</div>";
            }
            
            echo "<div style='background: #1a202c; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3 style='color: #fc8181;'>🎯 executeSimpleTrade() İçindeki Adımlar:</h3>";
            echo "1. Database connection test: " . ($step_results['database'] ?? 'Not tested') . "<br>";
            echo "2. Trading currency check: " . ($step_results['trading_currency'] ?? 'Not tested') . "<br>";
            echo "3. Balance validation: " . ($step_results['balance_check'] ?? 'Not tested') . "<br>";
            echo "4. Update function check: " . ($step_results['update_function'] ?? 'Not tested') . "<br>";
            echo "5. Transaction test: " . ($step_results['begin_transaction'] ?? 'Not tested') . "<br>";
            echo "</div>";
            
            echo "<div style='background: #2d3748; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "<h3 style='color: #ed8936;'>🔧 Sonraki Adım:</h3>";
            echo "Yukarıdaki test sonuçlarına bakarak hangi adımda fail olduğunu görebiliriz.<br>";
            echo "executeSimpleTrade() fonksiyonu bu adımlardan birinde false dönüyor.<br>";
            echo "</div>";
            
            echo "</div>";
            
            // Normal error message'ı set etmeyelim, debug ekranı gösterelim
            exit();
        }
    } else {
        $_SESSION['trade_error'] = getCurrentLang() == 'tr' ? 'Geçersiz işlem parametreleri.' : 'Invalid trade parameters.';
        header('Location: markets.php?group=' . $category);
        exit();
    }
}

// Update market data if it's been more than 10 minutes (to save API quota)
$database = new Database();
$db = $database->getConnection();

$query = "SELECT updated_at FROM markets WHERE category = ? ORDER BY updated_at DESC LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute([$category]);
$last_update = $stmt->fetchColumn();

// OTOMATIK GÜNCELLEME KAPATILDI - Sadece manuel güncelleme
// Auto update disabled - Manual update only via admin panel
// if (!$last_update || (time() - strtotime($last_update)) > 600) {
//     if (TWELVE_DATA_API_KEY !== 'demo') {
//         updateFinancialData($category);
//         $markets = getMarketData($category, 50);
//     }
// }

// Search functionality
$search = $_GET['search'] ?? '';
if ($search) {
    $markets = array_filter($markets, function($market) use ($search) {
        return stripos($market['name'], $search) !== false || 
               stripos($market['symbol'], $search) !== false;
    });
}

include 'includes/header.php';

// Check for session messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['trade_success'])) {
    $success_message = $_SESSION['trade_success'];
    unset($_SESSION['trade_success']);
}

if (isset($_SESSION['trade_error'])) {
    $error_message = $_SESSION['trade_error'];
    unset($_SESSION['trade_error']);
}

// AGGRESSIVE SESSION CLEANING - Tüm muhtemel error mesajlarını temizle
$all_message_keys = [
    'error', 'success', 'message', 'alert', 'notification', 'trade_message', 'status_message',
    'trade_error', 'trade_success', 'balance_error', 'insufficient_balance', 'transaction_error',
    'system_error', 'warning', 'info', 'flash_message', 'user_message', 'temp_message',
    'modal_error', 'form_error', 'validation_error', 'payment_error', 'wallet_error'
];

foreach($all_message_keys as $key) {
    if (isset($_SESSION[$key])) {
        unset($_SESSION[$key]);
    }
}

// Clear any session key that contains 'error', 'message', or 'alert'
foreach($_SESSION as $session_key => $session_value) {
    if (strpos(strtolower($session_key), 'error') !== false || 
        strpos(strtolower($session_key), 'message') !== false || 
        strpos(strtolower($session_key), 'alert') !== false) {
        unset($_SESSION[$session_key]);
    }
}
?>

<div class="container">
    
    <!-- Bootstrap alerts kaldırıldı - Artık sadece popup sistem kullanılıyor -->
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3 mb-0"><?php echo getFinancialCategories()[$category] ?? 'Financial Markets'; ?></h1>
            <p class="text-muted">
                <?php echo getCurrentLang() == 'tr' ? 'Canlı finansal piyasa verileri' : 'Live financial market data'; ?>
            </p>
        </div>
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
                <input type="text" class="form-control" placeholder="<?php echo getCurrentLang() == 'tr' ? 'Enstrüman ara...' : 'Search instruments...'; ?>" 
                       value="<?php echo htmlspecialchars($search); ?>" id="marketSearch">
            </div>
        </div>
    </div>
    
    <!-- Desktop: Financial Categories Grid -->
    <div class="row mb-4 desktop-categories">
        <div class="col-12">
            <h5 class="mb-3 text-secondary">
                <i class="fas fa-layer-group me-2"></i>Piyasa Kategorileri
            </h5>
            <div class="row g-3">
                <?php 
                $categories = getFinancialCategories();
                $icons = getCategoryIcons();
                $descriptions = getCategoryDescriptions();
                foreach ($categories as $cat_key => $cat_name): 
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <a href="?group=<?php echo $cat_key; ?>" class="text-decoration-none">
                        <div class="category-card card h-100 border-0 shadow-sm <?php echo $category == $cat_key ? 'category-active' : ''; ?>">
                            <div class="card-body p-3 text-center">
                                <div class="category-icon mb-2">
                                    <i class="<?php echo $icons[$cat_key] ?? 'fas fa-chart-line'; ?> fa-2x"></i>
                                </div>
                                <h6 class="card-title mb-2 fw-bold"><?php echo $cat_name; ?></h6>
                                <p class="card-text text-muted small mb-0">
                                    <?php echo $descriptions[$cat_key] ?? ''; ?>
                                </p>
                                <?php if ($category == $cat_key): ?>
                                <div class="mt-2">
                                    <span class="badge bg-primary">
                                        <i class="fas fa-check me-1"></i>Aktif
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Mobile: Horizontal Scroll Category Tabs -->
    <div class="mobile-category-tabs sticky-top" style="display: none;">
        <div class="category-tabs-container">
            <?php foreach ($categories as $cat_key => $cat_name): ?>
            <a href="?group=<?php echo $cat_key; ?>" class="category-tab <?php echo $category == $cat_key ? 'active' : ''; ?>">
                <i class="<?php echo $icons[$cat_key] ?? 'fas fa-chart-line'; ?>"></i>
                <span><?php echo $cat_name; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Market Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover market-table mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="border-0 ps-4"><?php echo t('market_name'); ?></th>
                            <th class="border-0 text-end"><?php echo t('last_price'); ?></th>
                            <th class="border-0 text-end"><?php echo t('change'); ?></th>
                            <th class="border-0 text-end"><?php echo t('low_24h'); ?></th>
                            <th class="border-0 text-end"><?php echo t('high_24h'); ?></th>
                            <th class="border-0 text-end"><?php echo t('volume_24h'); ?></th>
                            <th class="border-0 text-center pe-4">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($markets)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php echo getCurrentLang() == 'tr' ? 'Henüz piyasa verisi yok' : 'No market data available'; ?>
                                </p>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($markets as $market): ?>
                        <tr class="market-row" data-symbol="<?php echo $market['symbol']; ?>">
                            <td class="ps-4 py-3">
                                <div class="d-flex align-items-center">
                                    <?php if ($market['logo_url']): ?>
                                    <img src="<?php echo $market['logo_url']; ?>" 
                                         alt="<?php echo $market['name']; ?>" 
                                         class="me-3 rounded-circle" 
                                         width="32" height="32"
                                         onerror="this.outerHTML='<div class=&quot;bg-primary rounded-circle d-flex align-items-center justify-content-center me-3&quot; style=&quot;width: 32px; height: 32px;&quot;><i class=&quot;fas fa-coins text-white&quot;></i></div>';">
                                    <?php else: ?>
                                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                         style="width: 32px; height: 32px;">
                                        <i class="fas fa-coins text-white"></i>
                                    </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?php echo $market['symbol']; ?></div>
                                        <small class="text-muted"><?php echo $market['name']; ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="text-end py-3">
                                <div class="fw-bold price-cell" data-price="<?php echo $market['price']; ?>">
                                    <?php echo formatPrice($market['price']); ?>
                                    <small class="text-muted ms-1">
                                        <?php echo $category == 'crypto_tl' ? 'TL' : ($category == 'crypto_usd' ? 'USDT' : 'USD'); ?>
                                    </small>
                                </div>
                            </td>
                            <td class="text-end py-3">
                                <?php echo formatChange($market['change_24h']); ?>
                            </td>
                            <td class="text-end py-3">
                                <span class="text-muted"><?php echo formatPrice($market['low_24h']); ?></span>
                                <small class="text-muted ms-1">
                                    <?php echo $category == 'crypto_tl' ? 'TL' : ($category == 'crypto_usd' ? 'USDT' : 'USD'); ?>
                                </small>
                            </td>
                            <td class="text-end py-3">
                                <span class="text-muted"><?php echo formatPrice($market['high_24h']); ?></span>
                                <small class="text-muted ms-1">
                                    <?php echo $category == 'crypto_tl' ? 'TL' : ($category == 'crypto_usd' ? 'USDT' : 'USD'); ?>
                                </small>
                            </td>
                            <td class="text-end py-3">
                                <span class="text-muted"><?php echo formatVolume($market['volume_24h']); ?></span>
                                <small class="text-muted ms-1">
                                    <?php 
                                    $symbol_parts = explode('_', $market['symbol']);
                                    echo $symbol_parts[0];
                                    ?>
                                </small>
                            </td>
                            <td class="text-center py-3 pe-4">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-success btn-sm trade-btn" 
                                            data-symbol="<?php echo $market['symbol']; ?>" 
                                            data-name="<?php echo $market['name']; ?>" 
                                            data-price="<?php echo $market['price']; ?>" 
                                            data-action="buy"
                                            data-type="simple"
                                            onclick="event.stopPropagation(); openTradeModal(this);">
                                        <i class="fas fa-shopping-cart me-1"></i>AL
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm trade-btn" 
                                            data-symbol="<?php echo $market['symbol']; ?>" 
                                            data-name="<?php echo $market['name']; ?>" 
                                            data-price="<?php echo $market['price']; ?>" 
                                            data-action="sell"
                                            data-type="simple"
                                            onclick="event.stopPropagation(); openSellModal(this);">
                                        <i class="fas fa-hand-holding-usd me-1"></i>SAT
                                    </button>
                                    <button type="button" class="btn btn-warning btn-sm trade-btn" 
                                            data-symbol="<?php echo $market['symbol']; ?>" 
                                            data-name="<?php echo $market['name']; ?>" 
                                            data-price="<?php echo $market['price']; ?>" 
                                            data-action="leverage"
                                            data-type="leverage"
                                            onclick="event.stopPropagation(); openTradeModal(this);">
                                        <i class="fas fa-bolt me-1"></i>KALDIRAÇ
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Mobile Market Cards (Hidden on Desktop) -->
    <div class="mobile-market-cards" style="display: none;">
        <?php if (empty($markets)): ?>
        <div class="text-center py-5">
            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
            <p class="text-muted">
                <?php echo getCurrentLang() == 'tr' ? 'Henüz piyasa verisi yok' : 'No market data available'; ?>
            </p>
        </div>
        <?php else: ?>
        <?php foreach ($markets as $market): ?>
        <div class="mobile-market-card" data-symbol="<?php echo $market['symbol']; ?>">
            <!-- Market Header -->
            <div class="mobile-market-header">
                <?php if ($market['logo_url']): ?>
                <img src="<?php echo $market['logo_url']; ?>" 
                     alt="<?php echo $market['name']; ?>" 
                     class="mobile-market-logo"
                     onerror="this.outerHTML='<div class=&quot;mobile-market-logo bg-primary d-flex align-items-center justify-content-center&quot;><i class=&quot;fas fa-coins text-white&quot;></i></div>';">
                <?php else: ?>
                <div class="mobile-market-logo bg-primary d-flex align-items-center justify-content-center">
                    <i class="fas fa-coins text-white"></i>
                </div>
                <?php endif; ?>
                
                <div class="mobile-market-info">
                    <h6><?php echo $market['symbol']; ?></h6>
                    <small><?php echo $market['name']; ?></small>
                </div>
                
                <div class="mobile-market-price">
                    <div class="price" data-price="<?php echo $market['price']; ?>">
                        <?php echo formatPrice($market['price']); ?>
                        <small class="text-muted">
                            <?php echo $category == 'crypto_tl' ? 'TL' : ($category == 'crypto_usd' ? 'USDT' : 'USD'); ?>
                        </small>
                    </div>
                    <div class="change">
                        <?php echo formatChange($market['change_24h']); ?>
                    </div>
                </div>
            </div>
            
            <!-- Market Stats Grid -->
            <div class="mobile-market-stats">
                <div class="mobile-stat">
                    <div class="mobile-stat-label">Düşük</div>
                    <div class="mobile-stat-value"><?php echo formatPrice($market['low_24h']); ?></div>
                </div>
                <div class="mobile-stat">
                    <div class="mobile-stat-label">Yüksek</div>
                    <div class="mobile-stat-value"><?php echo formatPrice($market['high_24h']); ?></div>
                </div>
                <div class="mobile-stat">
                    <div class="mobile-stat-label">Hacim</div>
                    <div class="mobile-stat-value"><?php echo formatVolume($market['volume_24h']); ?></div>
                </div>
                <div class="mobile-stat">
                    <div class="mobile-stat-label">Piyasa Değeri</div>
                    <div class="mobile-stat-value"><?php echo formatVolume($market['market_cap']); ?></div>
                </div>
            </div>
            
            <!-- Mobile Trading Buttons -->
            <div class="mobile-trade-buttons">
                <button type="button" class="btn btn-success trade-btn" 
                        data-symbol="<?php echo $market['symbol']; ?>" 
                        data-name="<?php echo $market['name']; ?>" 
                        data-price="<?php echo $market['price']; ?>" 
                        data-action="buy"
                        data-type="simple"
                        onclick="openTradeModal(this);">
                    <i class="fas fa-shopping-cart me-1"></i>AL
                </button>
                <button type="button" class="btn btn-danger trade-btn" 
                        data-symbol="<?php echo $market['symbol']; ?>" 
                        data-name="<?php echo $market['name']; ?>" 
                        data-price="<?php echo $market['price']; ?>" 
                        data-action="sell"
                        data-type="simple"
                        onclick="openTradeModal(this);">
                    <i class="fas fa-hand-holding-usd me-1"></i>SAT
                </button>
                <button type="button" class="btn btn-warning trade-btn" 
                        data-symbol="<?php echo $market['symbol']; ?>" 
                        data-name="<?php echo $market['name']; ?>" 
                        data-price="<?php echo $market['price']; ?>" 
                        data-action="leverage"
                        data-type="leverage"
                        onclick="openTradeModal(this);">
                    <i class="fas fa-bolt me-1"></i>KALDIRAÇ
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Market Stats -->
    <div class="row mt-4 market-stats-mobile">
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h5 class="text-success mb-1"><?php echo count($markets); ?></h5>
                    <small class="text-muted">
                        <?php echo getCurrentLang() == 'tr' ? 'Toplam Piyasa' : 'Total Markets'; ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h5 class="text-primary mb-1">
                        <?php 
                        $gainers = array_filter($markets, function($m) { return $m['change_24h'] > 0; });
                        echo count($gainers);
                        ?>
                    </h5>
                    <small class="text-muted">
                        <?php echo getCurrentLang() == 'tr' ? 'Yükselenler' : 'Gainers'; ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h5 class="text-danger mb-1">
                        <?php 
                        $losers = array_filter($markets, function($m) { return $m['change_24h'] < 0; });
                        echo count($losers);
                        ?>
                    </h5>
                    <small class="text-muted">
                        <?php echo getCurrentLang() == 'tr' ? 'Düşenler' : 'Losers'; ?>
                    </small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 bg-light">
                <div class="card-body text-center">
                    <h5 class="text-info mb-1">
                        <?php 
                        $total_volume = array_sum(array_column($markets, 'volume_24h'));
                        echo formatVolume($total_volume);
                        ?>
                    </h5>
                    <small class="text-muted">
                        <?php echo getCurrentLang() == 'tr' ? '24S Hacim' : '24h Volume'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Success Popup CSS -->
<style>
.success-popup {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 999999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.success-popup.show {
    opacity: 1;
    visibility: visible;
}

.success-popup.closing {
    opacity: 0;
    transform: scale(0.9);
}

.success-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.success-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 16px;
    padding: 2rem;
    text-align: center;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    max-width: 400px;
    width: 90%;
    animation: successSlideIn 0.3s ease-out;
}

/* Mobile Success Popup - Smaller */
@media (max-width: 768px) {
    .success-content {
        max-width: 280px;
        padding: 1.5rem;
        border-radius: 12px;
    }
    
    .success-content h3 {
        font-size: 1.1rem;
        margin-bottom: 0.75rem;
    }
    
    .trade-summary {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }
    
    .trade-amount {
        font-size: 1.2rem;
    }
    
    .trade-symbol {
        font-size: 1rem;
        padding: 0.2rem 0.5rem;
    }
    
    .trade-breakdown {
        padding: 0.75rem;
    }
    
    .breakdown-row {
        padding: 0.4rem 0;
    }
    
    .breakdown-row span:first-child {
        font-size: 0.8rem;
    }
    
    .breakdown-row .value {
        font-size: 0.85rem;
    }
    
    .success-content .btn {
        padding: 0.6rem 1.5rem;
        font-size: 0.9rem;
    }
}

@keyframes successSlideIn {
    from {
        transform: translate(-50%, -60%);
        opacity: 0;
    }
    to {
        transform: translate(-50%, -50%);
        opacity: 1;
    }
}

.success-icon {
    margin-bottom: 1rem;
}

.success-icon i {
    font-size: 4rem;
    color: #28a745;
    animation: successPulse 0.6s ease-out;
}

@keyframes successPulse {
    0% {
        transform: scale(0);
    }
    50% {
        transform: scale(1.1);
    }
    100% {
        transform: scale(1);
    }
}

.success-content h3 {
    color: #28a745;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.success-content p {
    color: #6c757d;
    margin-bottom: 1.5rem;
}

.success-content .btn {
    padding: 0.75rem 2rem;
    border-radius: 8px;
    font-weight: 500;
}

/* Trade Details Styling */
.trade-details {
    margin: 1.5rem 0;
    text-align: left;
}

.trade-summary {
    background: linear-gradient(135deg, #28a745, #20c997);
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: white;
}

.trade-main-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.trade-amount {
    font-size: 1.5rem;
    font-weight: bold;
}

.trade-symbol {
    font-size: 1.2rem;
    font-weight: 600;
    background: rgba(255, 255, 255, 0.2);
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
}

.trade-action {
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    text-transform: uppercase;
}

.action-buy {
    background: rgba(255, 255, 255, 0.9);
    color: #28a745;
}

.action-sell {
    background: rgba(255, 255, 255, 0.9);
    color: #dc3545;
}

.trade-breakdown {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}

.breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.breakdown-row:last-child {
    border-bottom: none;
}

.breakdown-row span:first-child {
    color: #6c757d;
    font-size: 0.9rem;
}

.breakdown-row .value {
    font-weight: 600;
    color: #495057;
}

/* Mobile Optimization Styles */
/* Mobile Modal Responsive Styles */
@media (max-width: 991.98px) {
    #tradeModal .modal-dialog {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        margin: 0 !important;
        max-height: 85vh !important;
        border-radius: 16px 16px 0 0 !important;
        transform: translateY(100%) !important;
        transition: transform 0.3s ease !important;
    }
    
    #tradeModal.show .modal-dialog {
        transform: translateY(0) !important;
    }
    
    #tradeModal .modal-content {
        border-radius: 16px 16px 0 0 !important;
        height: 100%;
        border: none;
    }
    
    .mobile-tab-content {
        height: calc(85vh - 120px);
        overflow-y: auto;
    }
    
    .mobile-chart-tabs .nav-link {
        border-radius: 0;
        border: none;
        border-bottom: 3px solid transparent;
        background: none;
        padding: 1rem 1.5rem;
        font-weight: 600;
        color: #6c757d;
        transition: all 0.3s ease;
    }
    
    .mobile-chart-tabs .nav-link.active {
        background: none;
        color: #007bff;
        border-bottom-color: #007bff;
    }
    
    .mobile-chart-tabs .nav-link:hover {
        background: #f8f9fa;
        color: #495057;
    }
    
    .chart-container {
        border-radius: 8px;
        overflow: hidden;
        background: #fff;
        border: 1px solid #e9ecef;
    }
}

@media (min-width: 992px) {
    .mobile-chart-tabs {
        display: none !important;
    }
}

/* No sticky functionality - categories stay in normal position */

@media (max-width: 768px) {
    /* Hide desktop categories, show mobile tabs */
    .desktop-categories {
        display: none !important;
    }
    
    .mobile-category-tabs {
        display: block !important;
        background: white;
        z-index: 10001 !important;
        padding: 1rem 0;
        border-bottom: 1px solid #e9ecef;
        margin-bottom: 1rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }
    
    .mobile-category-tabs.sticky-top {
        position: sticky !important;
        top: 0 !important;
        z-index: 10001 !important;
        background: white;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }
    
    .category-tabs-container {
        display: flex;
        overflow-x: auto;
        scroll-behavior: smooth;
        -webkit-overflow-scrolling: touch;
        gap: 0.5rem;
        padding: 0 1rem 0.5rem 1rem;
        scrollbar-width: thin;
        scrollbar-color: #dee2e6 transparent;
    }
    
    .category-tabs-container::-webkit-scrollbar {
        height: 4px;
    }
    
    .category-tabs-container::-webkit-scrollbar-track {
        background: #f8f9fa;
        border-radius: 2px;
    }
    
    .category-tabs-container::-webkit-scrollbar-thumb {
        background: #dee2e6;
        border-radius: 2px;
    }
    
    .category-tab {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-width: 70px;
        height: 70px;
        padding: 0.5rem 0.25rem;
        border-radius: 8px;
        text-decoration: none;
        color: #6c757d;
        background: white;
        border: 1px solid #e9ecef;
        text-align: center;
        font-size: 0.7rem;
        font-weight: 500;
        transition: all 0.15s ease;
        flex-shrink: 0;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .category-tab i {
        font-size: 1rem;
        margin-bottom: 0.2rem;
        transition: transform 0.15s ease;
    }
    
    .category-tab span {
        line-height: 1;
        font-size: 0.65rem;
        max-width: 60px;
        word-wrap: break-word;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    
    .category-tab:hover {
        color: #495057;
        background: #f8f9fa;
        border-color: #dee2e6;
    }
    
    .category-tab.active {
        color: white;
        background: #007bff;
        border-color: #007bff;
        box-shadow: 0 2px 6px rgba(0, 123, 255, 0.4);
    }
    
    .category-tab.active i {
        transform: scale(1.05);
    }
    
    .mobile-category-header {
        display: block !important;
        background: white;
        z-index: 1020;
        padding: 1rem;
        border-bottom: 1px solid #e9ecef;
        margin-bottom: 1rem;
    }
    
    .category-dropdown-btn {
        border: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        border-radius: 8px;
        background: white;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    
    .category-dropdown-menu {
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        border: none;
        padding: 0.5rem 0;
        max-height: 60vh;
        overflow-y: auto;
    }
    
    .category-dropdown-menu .dropdown-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f8f9fa;
    }
    
    .category-dropdown-menu .dropdown-item:last-child {
        border-bottom: none;
    }
    
    .category-dropdown-menu .dropdown-item.active {
        background: linear-gradient(135deg, #007bff, #0056b3);
        color: white;
    }
    
    /* Mobile Category Selector Header */
    .mobile-category-selector {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .category-selector-header {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #e9ecef;
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border-radius: 8px 8px 0 0;
        font-size: 0.9rem;
    }
    
    .mobile-category-selector .dropdown {
        padding: 0.75rem;
    }
    
    .category-dropdown-btn .d-flex {
        text-align: left;
    }
    
    .category-dropdown-btn small {
        font-size: 0.75rem;
        color: #6c757d;
    }
    
    .dropdown-header {
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        font-weight: 600;
        color: #495057;
        background: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
        margin-bottom: 0.25rem;
    }
    
    /* Hide desktop table, show mobile cards */
    .market-table {
        display: none !important;
    }
    
    .mobile-market-cards {
        display: block !important;
    }
    
    /* Mobile Category Cards - Horizontal Scroll */
    .mobile-categories {
        display: flex !important;
        overflow-x: auto;
        scroll-snap-type: x mandatory;
        -webkit-overflow-scrolling: touch;
        gap: 1rem;
        padding: 0 1rem;
        margin: 0 -1rem;
    }
    
    .mobile-categories .category-card {
        min-width: 200px;
        flex-shrink: 0;
        scroll-snap-align: start;
    }
    
    /* Bottom Sheet Modal */
    .modal-dialog {
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        margin: 0 !important;
        max-height: 85vh !important;
        border-radius: 16px 16px 0 0 !important;
        transform: translateY(100%) !important;
        transition: transform 0.3s ease !important;
    }
    
    .modal.show .modal-dialog {
        transform: translateY(0) !important;
    }
    
    .modal-content {
        border-radius: 16px 16px 0 0 !important;
        height: 100%;
    }
    
    /* Mobile Modal Layout */
    .modal-body .row {
        flex-direction: column !important;
    }
    
    .modal-body .col-md-8,
    .modal-body .col-md-4 {
        max-width: 100% !important;
        width: 100% !important;
    }
    
    /* Hide chart on mobile, focus on trading */
    .modal-body .col-md-8 {
        display: none !important;
    }
    
    /* Page Header Mobile */
    .page-header-mobile {
        text-align: center;
        margin-bottom: 1rem;
    }
    
    .page-header-mobile h1 {
        font-size: 1.5rem !important;
        margin-bottom: 0.5rem;
    }
    
    /* Search Bar Mobile */
    .mobile-search {
        margin-bottom: 1rem;
    }
    
    /* Market Stats Mobile */
    .market-stats-mobile .col-md-3 {
        margin-bottom: 0.5rem;
    }
    
    .market-stats-mobile .card {
        padding: 0.5rem;
    }
    
    .market-stats-mobile h5 {
        font-size: 1.2rem;
    }
    
    /* Mobile Trading Buttons */
    .mobile-trade-buttons {
        display: flex;
        gap: 0.25rem;
        justify-content: center;
    }
    
    .mobile-trade-buttons .btn {
        flex: 1;
        font-size: 0.75rem;
        padding: 0.5rem 0.25rem;
        min-height: 44px; /* Touch-friendly */
    }
    
    /* Mobile Market Card */
    .mobile-market-card {
        background: white;
        border-radius: 12px;
        padding: 1rem;
        margin-bottom: 0.75rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        border: 1px solid #e9ecef;
    }
    
    .mobile-market-header {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .mobile-market-logo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        margin-right: 0.75rem;
        flex-shrink: 0;
    }
    
    .mobile-market-info h6 {
        margin: 0;
        font-weight: 600;
        font-size: 1rem;
    }
    
    .mobile-market-info small {
        color: #6c757d;
        font-size: 0.8rem;
    }
    
    .mobile-market-price {
        margin-left: auto;
        text-align: right;
    }
    
    .mobile-market-price .price {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0;
    }
    
    .mobile-market-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .mobile-stat {
        text-align: center;
    }
    
    .mobile-stat-label {
        font-size: 0.7rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
    }
    
    .mobile-stat-value {
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    /* Container padding adjustment for mobile */
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    /* Mobile Page Header */
    .row.mb-4 {
        margin-bottom: 1rem !important;
    }
    
    .row.mb-4 .col-md-8,
    .row.mb-4 .col-md-4 {
        max-width: 100%;
        width: 100%;
        margin-bottom: 1rem;
    }
}

/* Desktop-only styles */
@media (min-width: 769px) {
    .mobile-market-cards {
        display: none !important;
    }
    
    .mobile-categories {
        display: none !important;
    }
}
</style>

<script>
// Parametric system constants
const TRADING_CURRENCY = <?php echo $trading_currency; ?>; // 1=TL, 2=USD
const CURRENCY_SYMBOL = '<?php echo $currency_symbol; ?>';
const USD_TRY_RATE = <?php echo $usd_try_rate; ?>;

// Enhanced Search functionality for both desktop and mobile
document.getElementById('marketSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    
    // Search desktop table rows
    const rows = document.querySelectorAll('.market-row');
    rows.forEach(row => {
        const symbol = row.querySelector('.fw-bold').textContent.toLowerCase();
        const name = row.querySelector('.text-muted').textContent.toLowerCase();
        
        if (symbol.includes(searchTerm) || name.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
    
    // Search mobile cards
    const mobileCards = document.querySelectorAll('.mobile-market-card');
    mobileCards.forEach(card => {
        const symbol = card.querySelector('.mobile-market-info h6').textContent.toLowerCase();
        const name = card.querySelector('.mobile-market-info small').textContent.toLowerCase();
        
        if (symbol.includes(searchTerm) || name.includes(searchTerm)) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
});

// Auto refresh market data
function refreshMarketData() {
    fetch('api/get_market_data.php?category=<?php echo $category; ?>')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    updateMarketTable(data.markets);
                }
            } catch (e) {
                console.error('JSON Parse Error:', e);
                console.error('Response text:', text);
            }
        })
        .catch(error => console.error('Error refreshing market data:', error));
}

function updateMarketTable(markets) {
    markets.forEach(market => {
        const row = document.querySelector(`[data-symbol="${market.symbol}"]`);
        if (row) {
            const priceCell = row.querySelector('.price-cell');
            const oldPrice = parseFloat(priceCell.dataset.price);
            const newPrice = parseFloat(market.price);
            
            // Update price
            priceCell.textContent = formatPrice(newPrice);
            priceCell.dataset.price = newPrice;
            
            // Animate price change
            if (newPrice !== oldPrice) {
                animatePriceChange(priceCell, newPrice > oldPrice);
            }
            
            // Update change percentage
            const changeCell = row.querySelector('.text-success, .text-danger');
            if (changeCell) {
                const sign = market.change_24h >= 0 ? '+' : '';
                changeCell.className = market.change_24h >= 0 ? 'text-success' : 'text-danger';
                changeCell.innerHTML = `<span class="${changeCell.className}">${sign} %${formatTurkishNumber(market.change_24h, 2)}</span>`;
            }
        }
    });
}

function formatPrice(price) {
    if (price >= 1000) {
        return formatTurkishNumber(price, 2);
    } else if (price >= 1) {
        return formatTurkishNumber(price, 4);
    } else {
        return formatTurkishNumber(price, 8);
    }
}

// Turkish number formatting function
function formatTurkishNumber(number, decimals = 2) {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// Price change animation
function animatePriceChange(element, isUp) {
    element.classList.remove('price-up', 'price-down');
    element.classList.add(isUp ? 'price-up' : 'price-down');
    setTimeout(() => {
        element.classList.remove('price-up', 'price-down');
    }, 500);
}
// SAT butonu özel fonksiyonu - Sahiplik kontrolü ile
function openSellModal(button) {
    const symbol = button.dataset.symbol;
    const name = button.dataset.name;
    const price = parseFloat(button.dataset.price);
    
    <?php if (isLoggedIn()): ?>
    // Kullanıcı giriş yapmış - sahiplik kontrolü yap
    console.log('🔍 Checking portfolio holding for:', symbol);
    
    fetch('api/get_portfolio_holding.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            symbol: symbol
        })
    })
    .then(response => {
        console.log('📊 API Response status:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('📈 API Response data:', data);
        
        if (data.success && data.holding) {
            console.log('✅ Holding found:', data.holding);
            // Sahip - portföye yönlendir
            window.location.href = `portfolio.php?symbol=${symbol}`;
        } else {
            console.log('❌ No holding found:', data.error || 'Unknown error');
            // Sahip değil - uyarı göster
            showNotOwnerAlert(symbol, name);
        }
    })
    .catch(error => {
        console.error('🚨 Error checking portfolio holding:', error);
        showNotOwnerAlert(symbol, name);
    });
    <?php else: ?>
    // Giriş yapmamış - login sayfasına yönlendir
    window.location.href = 'login.php';
    <?php endif; ?>
}

// Sahip değilsiniz uyarısı
function showNotOwnerAlert(symbol, name) {
    // Custom alert popup oluştur
    const alertPopup = document.createElement('div');
    alertPopup.className = 'success-popup show';
    alertPopup.innerHTML = `
        <div class="success-overlay" onclick="closeNotOwnerAlert()"></div>
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-exclamation-triangle" style="color: #ffc107;"></i>
            </div>
            <h3 style="color: #ffc107;">Bu Varlığa Sahip Değilsiniz</h3>
            <p class="mb-4">
                <strong>${symbol}</strong> (${name}) satabilmek için önce portföyünüzde bulunması gerekiyor.
            </p>
            <div class="d-grid gap-2">
                <button onclick="buyInstead('${symbol}')" class="btn btn-success">
                    <i class="fas fa-shopping-cart me-2"></i>Önce Satın Al
                </button>
                <button onclick="window.open('portfolio.php', '_blank')" class="btn btn-outline-secondary">
                    <i class="fas fa-chart-pie me-2"></i>Portföyümü Gör
                </button>
                <button onclick="closeNotOwnerAlert()" class="btn btn-outline-dark">
                    <i class="fas fa-times me-2"></i>Kapat
                </button>
            </div>
        </div>
    `;
    
    document.body.appendChild(alertPopup);
}

// Uyarı kapat
function closeNotOwnerAlert() {
    const popup = document.querySelector('.success-popup');
    if (popup) {
        popup.classList.add('closing');
        setTimeout(() => {
            popup.remove();
        }, 300);
    }
}

// Önce satın al - AL modalını aç
function buyInstead(symbol) {
    closeNotOwnerAlert();
    // AL modalını bul ve aç
    const buyButton = document.querySelector(`[data-symbol="${symbol}"][data-action="buy"]`);
    if (buyButton) {
        openTradeModal(buyButton);
    }
}

// Trading modal functions
function openTradeModal(button) {
    const symbol = button.dataset.symbol;
    const name = button.dataset.name;
    const price = parseFloat(button.dataset.price);
    const action = button.dataset.action;
    const type = button.dataset.type; // simple or leverage
    
    // Update modal content
    document.getElementById('modalSymbol').textContent = symbol;
    document.getElementById('modalName').textContent = name;
    document.getElementById('modalPrice').textContent = formatPrice(price);
    document.getElementById('modalChange').textContent = document.querySelector(`[data-symbol="${symbol}"] .text-success, [data-symbol="${symbol}"] .text-danger`).textContent;
    
    // Set hidden fields for forms
    document.getElementById('buySymbol').value = symbol;
    if (document.getElementById('sellSymbol')) {
        document.getElementById('sellSymbol').value = symbol;
    }
    
    // Configure modal based on type
    configureModalForType(type, action);
    
    // Load portfolio holding for this symbol (for sell modal)
    loadPortfolioHolding(symbol, price);
    
    // Update TradingView widget for both desktop and mobile
    updateTradingViewWidget(symbol);
    updateMobileTradingViewWidget(symbol);
    
    // Copy trading content to mobile tab
    copyTradingContentToMobile();
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('tradeModal'));
    modal.show();
}

// Load portfolio holding for symbol
function loadPortfolioHolding(symbol, currentPrice) {
    <?php if (isLoggedIn()): ?>
    // For logged-in users, fetch portfolio data
    fetch('api/get_portfolio_holding.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            symbol: symbol
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.holding) {
            showPortfolioHolding(data.holding, currentPrice);
            enableSellMode(data.holding, currentPrice);
        } else {
            hidePortfolioHolding();
            disableSellMode(symbol);
        }
    })
    .catch(error => {
        console.error('Error loading portfolio holding:', error);
        hidePortfolioHolding();
        disableSellMode(symbol);
    });
    <?php else: ?>
    hidePortfolioHolding();
    disableSellMode(symbol);
    <?php endif; ?>
}

// Show portfolio holding in sell modal
function showPortfolioHolding(holding, currentPrice) {
    const portfolioDiv = document.getElementById('portfolioHolding');
    const quickButtons = document.getElementById('quickSellButtons');
    
    if (!portfolioDiv) return;
    
    // Calculate current value and P&L
    const currentValue = holding.quantity * currentPrice;
    const profitLoss = currentValue - holding.total_invested;
    const profitLossPercent = holding.total_invested > 0 ? (profitLoss / holding.total_invested) * 100 : 0;
    
    // Update display elements
    document.getElementById('holdingQuantity').textContent = formatTurkishNumber(holding.quantity, 6);
    document.getElementById('holdingValue').textContent = '$' + formatTurkishNumber(currentValue, 2);
    document.getElementById('holdingAvgPrice').textContent = '$' + formatTurkishNumber(holding.avg_price, 4);
    
    const pnlElement = document.getElementById('holdingPnL');
    pnlElement.textContent = (profitLoss >= 0 ? '+' : '') + '$' + formatTurkishNumber(Math.abs(profitLoss), 2);
    pnlElement.className = profitLoss >= 0 ? 'text-success' : 'text-danger';
    
    // Store holding data for quick sell buttons
    window.currentHolding = holding;
    window.currentPrice = currentPrice;
    
    // Show portfolio section and quick buttons
    portfolioDiv.style.display = 'block';
    if (quickButtons) quickButtons.style.display = 'block';
}

// Hide portfolio holding display
function hidePortfolioHolding() {
    const portfolioDiv = document.getElementById('portfolioHolding');
    const quickButtons = document.getElementById('quickSellButtons');
    
    if (portfolioDiv) portfolioDiv.style.display = 'none';
    if (quickButtons) quickButtons.style.display = 'none';
    
    window.currentHolding = null;
    window.currentPrice = null;
}

// Set sell percentage based on portfolio holding
function setPortfolioSellPercentage(percentage) {
    if (!window.currentHolding || !window.currentPrice) return;
    
    const holding = window.currentHolding;
    const price = window.currentPrice;
    
    // Calculate USD amount based on percentage of holding
    const sellQuantity = holding.quantity * (percentage / 100);
    const usdAmount = sellQuantity * price;
    
    // Set the USD amount in sell input
    const sellInput = document.getElementById('usd_amount_sell');
    if (sellInput) {
        sellInput.value = usdAmount.toFixed(2);
        
        // Trigger calculation
        calculateSimpleTradeSell();
    }
}

// Enable sell mode when user has portfolio holding
function enableSellMode(holding, currentPrice) {
    // Show portfolio-style sell form
    const sellPane = document.getElementById('sell-pane');
    if (sellPane) {
        // Change sell form to portfolio-style
        setupPortfolioSellForm(holding, currentPrice);
    }
}

// Disable sell mode when user has no portfolio holding
function disableSellMode(symbol) {
    const sellPane = document.getElementById('sell-pane');
    if (sellPane) {
        // Show "no holding" message
        setupNoHoldingMessage(symbol);
    }
}

// Setup portfolio-style sell form (like portfolio.php)
function setupPortfolioSellForm(holding, currentPrice) {
    const sellPane = document.getElementById('sell-pane');
    
    // Create portfolio-style sell form HTML
    const portfolioSellHTML = `
        <!-- Portfolio Holdings Display -->
        <div class="mb-3" id="portfolioSellHolding">
            <div class="card bg-light border-0">
                <div class="card-body p-3">
                    <h6 class="card-title mb-2">💼 Mevcut Portföy</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <div class="fw-bold">${formatTurkishNumber(holding.quantity, 6)} adet</div>
                            <small class="text-muted">Sahip olduğunuz miktar</small>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$${formatTurkishNumber(holding.quantity * currentPrice, 2)}</div>
                            <small class="text-muted">güncel değer</small>
                        </div>
                    </div>
                    <div class="row text-center">
                        <div class="col-6">
                            <small class="text-muted">Ort. Fiyat:</small><br>
                            <span class="fw-bold">$${formatTurkishNumber(holding.avg_price, 4)}</span>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Kar/Zarar:</small><br>
                            <span class="fw-bold ${(holding.quantity * currentPrice - holding.total_invested) >= 0 ? 'text-success' : 'text-danger'}">
                                ${((holding.quantity * currentPrice - holding.total_invested) >= 0 ? '+' : '')}$${formatTurkishNumber(Math.abs(holding.quantity * currentPrice - holding.total_invested), 2)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <form id="portfolioSellForm" method="POST" action="">
            <input type="hidden" name="trade_action" value="sell">
            <input type="hidden" name="symbol" id="sellSymbolHidden" value="">
            
            <div class="mb-3">
                <label class="form-label">Satış Miktarı (USD)</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="portfolioSellAmount" name="usd_amount" 
                           step="0.01" min="0.01" max="${(holding.quantity * currentPrice).toFixed(2)}" 
                           placeholder="0.00" oninput="calculatePortfolioSell()" required>
                    <span class="input-group-text">USD</span>
                </div>
                <small class="text-muted">
                    Maksimum: $${formatTurkishNumber(holding.quantity * currentPrice, 2)} 
                    (${formatTurkishNumber(holding.quantity, 6)} adet)
                </small>
            </div>
            
            <!-- Quick Sell Buttons -->
            <div class="mb-3">
                <small class="text-muted d-block mb-2">Hızlı Seçim:</small>
                <div class="d-grid gap-2">
                    <div class="row g-2">
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="setQuickSellPercentage(25)">%25</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="setQuickSellPercentage(50)">%50</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="setQuickSellPercentage(75)">%75</button>
                        </div>
                        <div class="col-3">
                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="setQuickSellPercentage(100)">Tümü</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sell Summary -->
            <div class="card border-0 bg-light mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Satış Tutarı:</small>
                        <small class="fw-bold" id="portfolioSellTotal">$0.00</small>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted">Satış Miktarı:</small>
                        <small class="fw-bold" id="portfolioSellQuantity">0.000000 adet</small>
                    </div>
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">Alacağınız Tutar:</small>
                        <small class="fw-bold text-success" id="portfolioSellNet">${CURRENCY_SYMBOL} 0.00</small>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-danger w-100" id="portfolioSellButton">
                <i class="fas fa-hand-holding-usd me-2"></i>PORTFÖYDEN SAT
            </button>
        </form>
    `;
    
    // Replace the sell pane content
    sellPane.innerHTML = portfolioSellHTML;
    
    // Store holding data globally
    window.currentPortfolioHolding = holding;
    window.currentPortfolioPrice = currentPrice;
    
    // Set hidden symbol field
    document.getElementById('sellSymbolHidden').value = holding.symbol;
}

// Setup no holding message
function setupNoHoldingMessage(symbol) {
    const sellPane = document.getElementById('sell-pane');
    
    const noHoldingHTML = `
        <div class="text-center py-5">
            <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
            <h5 class="text-muted mb-3">Bu varlığa sahip değilsiniz</h5>
            <p class="text-muted mb-4">
                <strong>${symbol}</strong> satabilmek için önce satın almanız gerekiyor.
            </p>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-success" onclick="switchToBuyTab()">
                    <i class="fas fa-shopping-cart me-2"></i>Önce Satın Al
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.open('portfolio.php', '_blank')">
                    <i class="fas fa-chart-pie me-2"></i>Portföyümü Gör
                </button>
            </div>
        </div>
    `;
    
    sellPane.innerHTML = noHoldingHTML;
}

// Switch to buy tab function
function switchToBuyTab() {
    const buyTab = document.getElementById('buy-tab');
    const sellTab = document.getElementById('sell-tab');
    const buyPane = document.getElementById('buy-pane');
    const sellPane = document.getElementById('sell-pane');
    
    buyTab.classList.add('active');
    sellTab.classList.remove('active');
    buyPane.classList.add('show', 'active');
    sellPane.classList.remove('show', 'active');
}

// Portfolio sell calculation (like portfolio.php)
function calculatePortfolioSell() {
    const usdAmount = parseFloat(document.getElementById('portfolioSellAmount').value) || 0;
    
    if (!window.currentPortfolioHolding || !window.currentPortfolioPrice) {
        return;
    }
    
    const holding = window.currentPortfolioHolding;
    const currentPrice = window.currentPortfolioPrice;
    const maxValue = holding.quantity * currentPrice;
    
    // Validate amount
    if (usdAmount > maxValue) {
        document.getElementById('portfolioSellAmount').value = maxValue.toFixed(2);
        return calculatePortfolioSell(); // Recalculate with corrected value
    }
    
    if (usdAmount <= 0) {
        // Reset displays
        document.getElementById('portfolioSellTotal').textContent = '$0.00';
        document.getElementById('portfolioSellQuantity').textContent = '0.000000 adet';
        document.getElementById('portfolioSellNet').textContent = CURRENCY_SYMBOL + ' 0.00';
        
        // Reset button
        const sellButton = document.getElementById('portfolioSellButton');
        sellButton.disabled = false;
        sellButton.className = 'btn btn-danger w-100';
        sellButton.innerHTML = '<i class="fas fa-hand-holding-usd me-2"></i>PORTFÖYDEN SAT';
        return;
    }
    
    // Calculate sell quantity
    const sellQuantity = usdAmount / currentPrice;
    const fee = 0; // No fee
    const netUSD = usdAmount - fee;
    
    // Update displays
    document.getElementById('portfolioSellTotal').textContent = '$' + formatTurkishNumber(usdAmount, 2);
    document.getElementById('portfolioSellQuantity').textContent = formatTurkishNumber(sellQuantity, 6) + ' adet';
    
    // Convert to trading currency
    if (TRADING_CURRENCY === 1) { // TL mode
        const netTL = netUSD * USD_TRY_RATE;
        document.getElementById('portfolioSellNet').textContent = formatTurkishNumber(netTL, 2) + ' TL';
    } else { // USD mode
        document.getElementById('portfolioSellNet').textContent = '$' + formatTurkishNumber(netUSD, 2);
    }
    
    // Update button
    const sellButton = document.getElementById('portfolioSellButton');
    sellButton.disabled = false;
    sellButton.className = 'btn btn-danger w-100';
    sellButton.innerHTML = '<i class="fas fa-hand-holding-usd me-2"></i>PORTFÖYDEN SAT';
}

// Quick sell percentage function (like portfolio.php)
function setQuickSellPercentage(percentage) {
    if (!window.currentPortfolioHolding || !window.currentPortfolioPrice) {
        return;
    }
    
    const holding = window.currentPortfolioHolding;
    const currentPrice = window.currentPortfolioPrice;
    const maxValue = holding.quantity * currentPrice;
    const sellValue = maxValue * (percentage / 100);
    
    document.getElementById('portfolioSellAmount').value = sellValue.toFixed(2);
    calculatePortfolioSell();
}

// Copy trading forms to mobile tab
function copyTradingContentToMobile() {
    const desktopTradingContent = document.getElementById('tradingTabsContent');
    const mobileContentContainer = document.getElementById('mobile-trading-content');
    
    if (desktopTradingContent && mobileContentContainer) {
        // Clone the desktop trading content
        const clonedContent = desktopTradingContent.cloneNode(true);
        
        // Update IDs to avoid conflicts
        clonedContent.id = 'mobile-trading-tabs-content';
        
        // Update form IDs and input names for mobile
        const forms = clonedContent.querySelectorAll('form');
        forms.forEach((form, index) => {
            form.id = form.id + '-mobile';
            
            // Update input IDs
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.id) {
                    input.id = input.id + '-mobile';
                }
                // Keep the same name attributes for form submission
            });
        });
        
        // Clear mobile container and add cloned content
        mobileContentContainer.innerHTML = '';
        mobileContentContainer.appendChild(clonedContent);
        
        // Reinitialize event listeners for mobile forms
        initializeMobileFormListeners();
    }
}

// Initialize mobile form event listeners
function initializeMobileFormListeners() {
    // Mobile buy total calculation
    const mobileBuyInput = document.getElementById('usd_amount-mobile');
    if (mobileBuyInput) {
        mobileBuyInput.addEventListener('input', function() {
            calculateMobileTrade('buy');
        });
    }
    
    // Mobile sell total calculation  
    const mobileSellInput = document.getElementById('amountSell-mobile');
    if (mobileSellInput) {
        mobileSellInput.addEventListener('input', function() {
            calculateMobileTrade('sell');
        });
    }
}

// Mobile trade calculation
function calculateMobileTrade(type) {
    if (type === 'buy') {
        const usdAmount = parseFloat(document.getElementById('usd_amount-mobile').value) || 0;
        const priceUSD = parseFloat(document.getElementById('modalPrice').textContent.replace(',', '.'));
        const submitBtn = document.querySelector('#buyForm-mobile button[type="submit"]');
        
        if (usdAmount <= 0) {
            // Reset displays
            const totalValue = document.querySelector('#mobile-trading-tabs-content #totalValue');
            const requiredMargin = document.querySelector('#mobile-trading-tabs-content #requiredMargin');
            const tradingFee = document.querySelector('#mobile-trading-tabs-content #tradingFee');
            
            if (totalValue) totalValue.textContent = '$0.00';
            if (requiredMargin) requiredMargin.textContent = '$0.00';
            if (tradingFee) tradingFee.textContent = '$0.00';
            
            // Reset button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-success w-100 btn-lg';
                submitBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>SATIN AL';
            }
            return;
        }
        
    const fee = 0; // No fee
    let currentBalance, totalWithFee, remainingBalance;
        
        if (TRADING_CURRENCY === 1) { // TL Mode
            const totalTL = usdAmount * USD_TRY_RATE;
            const feeTL = fee * USD_TRY_RATE;
            totalWithFee = totalTL + feeTL;
            
            currentBalance = <?php echo isLoggedIn() ? getUserBalance($_SESSION['user_id'], 'tl') : 10000; ?>;
            remainingBalance = currentBalance - totalWithFee;
            
            // Update mobile displays
            const totalValue = document.querySelector('#mobile-trading-tabs-content #totalValue');
            const requiredMargin = document.querySelector('#mobile-trading-tabs-content #requiredMargin');
            const tradingFee = document.querySelector('#mobile-trading-tabs-content #tradingFee');
            
            if (totalValue) totalValue.textContent = formatTurkishNumber(totalTL, 2) + ' TL';
            if (requiredMargin) requiredMargin.textContent = formatTurkishNumber(totalWithFee, 2) + ' TL';
            if (tradingFee) tradingFee.textContent = formatTurkishNumber(remainingBalance, 2) + ' TL';
            
        } else { // USD Mode
            totalWithFee = usdAmount + fee;
            currentBalance = <?php echo isLoggedIn() ? getUserBalance($_SESSION['user_id'], 'usd') : 1000; ?>;
            remainingBalance = currentBalance - totalWithFee;
            
            // Update mobile displays
            const totalValue = document.querySelector('#mobile-trading-tabs-content #totalValue');
            const requiredMargin = document.querySelector('#mobile-trading-tabs-content #requiredMargin');
            const tradingFee = document.querySelector('#mobile-trading-tabs-content #tradingFee');
            
            if (totalValue) totalValue.textContent = formatTurkishNumber(usdAmount, 2) + ' USD';
            if (requiredMargin) requiredMargin.textContent = formatTurkishNumber(totalWithFee, 2) + ' USD';
            if (tradingFee) tradingFee.textContent = formatTurkishNumber(remainingBalance, 2) + ' USD';
        }
        
        // Mobile button control
        if (submitBtn) {
            if (totalWithFee > currentBalance) {
                submitBtn.disabled = true;
                submitBtn.className = 'btn btn-danger w-100 btn-lg';
                submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>YETERSİZ BAKİYE';
            } else {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-success w-100 btn-lg';
                submitBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>SATIN AL';
            }
        }
    }
}

// Update mobile TradingView widget
function updateMobileTradingViewWidget(symbol) {
    let tvSymbol = symbol;
    
    // Convert symbols to TradingView format
    if (symbol.includes('=X')) {
        tvSymbol = symbol.replace('=X', '');
    } else if (symbol.includes('=F')) {
        tvSymbol = symbol.replace('=F', '');
    } else if (symbol.startsWith('^')) {
        tvSymbol = symbol.replace('^', '');
    }
    
    // Update mobile TradingView iframe
    const mobileIframe = document.getElementById('tradingview-mobile');
    if (mobileIframe) {
        mobileIframe.src = `https://www.tradingview.com/widgetembed/?frameElementId=tradingview_mobile&symbol=${tvSymbol}&interval=1H&hidesidetoolbar=1&hidetoptoolbar=1&symboledit=1&saveimage=1&toolbarbg=F1F3F6&studies=[]&hideideas=1&theme=Light&style=1&timezone=Etc%2FUTC&locale=<?php echo getCurrentLang(); ?>&utm_source=localhost&utm_medium=widget&utm_campaign=chart&utm_term=${tvSymbol}`;
    }
}

function configureModalForType(type, action) {
    const buyTab = document.getElementById('buy-tab');
    const sellTab = document.getElementById('sell-tab');
    const buyPane = document.getElementById('buy-pane');
    const sellPane = document.getElementById('sell-pane');
    
    if (type === 'simple') {
        // Simple buy/sell - hide leverage elements
        setupSimpleTrading(action);
    } else if (type === 'leverage') {
        // Leverage trading - show all elements
        setupLeverageTrading();
    }
    
    // Set active tab based on action
    if (action === 'buy' || action === 'leverage') {
        buyTab.classList.add('active');
        sellTab.classList.remove('active');
        buyPane.classList.add('show', 'active');
        sellPane.classList.remove('show', 'active');
    } else if (action === 'sell') {
        sellTab.classList.add('active');
        buyTab.classList.remove('active');
        sellPane.classList.add('show', 'active');
        buyPane.classList.remove('show', 'active');
    }
}

function setupSimpleTrading(action) {
    // Update tab labels for simple trading - sadece varsa güncelle
    const buyTab = document.getElementById('buy-tab');
    const sellTab = document.getElementById('sell-tab');
    
    if (buyTab) buyTab.innerHTML = '<i class="fas fa-shopping-cart me-1"></i>SATIN AL';
    if (sellTab) sellTab.innerHTML = '<i class="fas fa-hand-holding-usd me-1"></i>SAT';
    
    // Hide leverage controls
    const leverageControls = document.querySelectorAll('.leverage-control');
    leverageControls.forEach(control => {
        control.style.display = 'none';
    });
    
    // Hide stop loss / take profit for simple trading
    const advancedControls = document.querySelectorAll('.advanced-control');
    advancedControls.forEach(control => {
        control.style.display = 'none';
    });
    
    // Update button text - sadece varsa güncelle
    const buyButton = document.querySelector('#buy-pane button[type="submit"]');
    const sellButton = document.querySelector('#sell-pane button[type="submit"]');
    
    if (buyButton) buyButton.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>SATIN AL';
    if (sellButton) sellButton.innerHTML = '<i class="fas fa-hand-holding-usd me-2"></i>SAT';
    
    // Update calculation labels
    updateSimpleCalculationLabels();
}

function setupLeverageTrading() {
    // Update tab labels for leverage trading - sadece varsa güncelle
    const buyTab = document.getElementById('buy-tab');
    const sellTab = document.getElementById('sell-tab');
    
    if (buyTab) buyTab.innerHTML = '<i class="fas fa-arrow-up me-1"></i>LONG';
    if (sellTab) sellTab.innerHTML = '<i class="fas fa-arrow-down me-1"></i>SHORT';
    
    // Show leverage controls
    const leverageControls = document.querySelectorAll('.leverage-control');
    leverageControls.forEach(control => {
        control.style.display = 'block';
    });
    
    // Show advanced controls
    const advancedControls = document.querySelectorAll('.advanced-control');
    advancedControls.forEach(control => {
        control.style.display = 'block';
    });
    
    // Update button text - sadece varsa güncelle
    const buyButton = document.querySelector('#buy-pane button[type="submit"]');
    const sellButton = document.querySelector('#sell-pane button[type="submit"]');
    
    if (buyButton) buyButton.innerHTML = '<i class="fas fa-arrow-up me-2"></i>LONG POZISYON AÇ';
    if (sellButton) sellButton.innerHTML = '<i class="fas fa-arrow-down me-2"></i>SHORT POZISYON AÇ';
    
    // Update calculation labels
    updateLeverageCalculationLabels();
}

function updateSimpleCalculationLabels() {
    // Update calculation display for simple trading
    const labels = document.querySelectorAll('.calculation-label');
    labels.forEach(label => {
        if (label.textContent === 'Gerekli Margin:') {
            label.textContent = 'Ödenecek Tutar:';
        }
    });
}

function updateLeverageCalculationLabels() {
    // Update calculation display for leverage trading
    const labels = document.querySelectorAll('.calculation-label');
    labels.forEach(label => {
        if (label.textContent === 'Ödenecek Tutar:') {
            label.textContent = 'Gerekli Margin:';
        }
    });
}

function updateTradingViewWidget(symbol) {
    // Clean symbol for TradingView format
    let tvSymbol = symbol;
    
    // Convert our symbols to TradingView format
    if (symbol.includes('=X')) {
        tvSymbol = symbol.replace('=X', '');
    } else if (symbol.includes('=F')) {
        tvSymbol = symbol.replace('=F', '');
    } else if (symbol.startsWith('^')) {
        tvSymbol = symbol.replace('^', '');
    }
    
    // Update TradingView iframe src
    const iframe = document.getElementById('tradingview-widget');
    iframe.src = `https://www.tradingview.com/widgetembed/?frameElementId=tradingview_chart&symbol=${tvSymbol}&interval=1D&hidesidetoolbar=1&hidetoptoolbar=1&symboledit=1&saveimage=1&toolbarbg=F1F3F6&studies=[]&hideideas=1&theme=Light&style=1&timezone=Etc%2FUTC&studies_overrides={}&overrides={}&enabled_features=[]&disabled_features=[]&locale=en&utm_source=localhost&utm_medium=widget&utm_campaign=chart&utm_term=${tvSymbol}`;
}

// Simple Clean Trading Calculation with Smart Button Control
function calculateSimpleTrade() {
    const usdAmount = parseFloat(document.getElementById('usd_amount').value) || 0;
    const priceUSD = parseFloat(document.getElementById('modalPrice').textContent.replace(',', '.'));
    const submitBtn = document.querySelector('#buyForm button[type="submit"]');
    
    if (usdAmount <= 0) {
        // Reset displays if no amount
        document.getElementById('totalValue').textContent = '$0.00';
        document.getElementById('requiredMargin').textContent = '$0.00';
        document.getElementById('tradingFee').textContent = '$0.00';
        
        // Reset button
        submitBtn.disabled = false;
        submitBtn.className = 'btn btn-success w-100';
        submitBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>SATIN AL';
        return;
    }
    
    const fee = usdAmount * 0.001; // 0.1% fee
    let currentBalance, totalWithFee, remainingBalance;
    
    if (TRADING_CURRENCY === 1) { // TL Mode
        // Convert USD to TL 
        const totalTL = usdAmount * USD_TRY_RATE;
        const feeTL = fee * USD_TRY_RATE;
        totalWithFee = totalTL + feeTL;
        
        // Get current balance
        currentBalance = <?php echo isLoggedIn() ? getUserBalance($_SESSION['user_id'], 'tl') : 10000; ?>;
        remainingBalance = currentBalance - totalWithFee;
        
        // Update display
        document.getElementById('totalValue').textContent = formatTurkishNumber(totalTL, 2) + ' TL';
        document.getElementById('requiredMargin').textContent = formatTurkishNumber(totalWithFee, 2) + ' TL';
        document.getElementById('tradingFee').textContent = formatTurkishNumber(remainingBalance, 2) + ' TL';
        
        // Show exchange rate info
        const exchangeInfo = document.getElementById('exchangeInfo');
        if (exchangeInfo) {
            exchangeInfo.style.display = 'flex';
            exchangeInfo.style.setProperty('display', 'flex', 'important');
        }
        
        // Update label
        const labelElement = document.querySelector('#buy-pane .card-body .d-flex:last-child .text-muted');
        if (labelElement) {
            labelElement.textContent = 'Kalan Bakiye:';
        }
        
    } else { // USD Mode
        totalWithFee = usdAmount + fee;
        
        // Get current balance  
        currentBalance = <?php echo isLoggedIn() ? getUserBalance($_SESSION['user_id'], 'usd') : 1000; ?>;
        remainingBalance = currentBalance - totalWithFee;
        
        // Update display
        document.getElementById('totalValue').textContent = formatTurkishNumber(usdAmount, 2) + ' USD';
        document.getElementById('requiredMargin').textContent = formatTurkishNumber(totalWithFee, 2) + ' USD';
        document.getElementById('tradingFee').textContent = formatTurkishNumber(remainingBalance, 2) + ' USD';
        
        // Hide exchange rate info
        const exchangeInfo = document.getElementById('exchangeInfo');
        if (exchangeInfo) {
            exchangeInfo.style.display = 'none';
        }
        
        // Update label
        const labelElement = document.querySelector('#buy-pane .card-body .d-flex:last-child .text-muted');
        if (labelElement) {
            labelElement.textContent = 'Kalan Bakiye:';
        }
    }
    
    // SMART BUTTON CONTROL - Anlık Bakiye Kontrolü
    if (totalWithFee > currentBalance) {
        // Yetersiz Bakiye - Kırmızı Buton
        submitBtn.disabled = true;
        submitBtn.className = 'btn btn-danger w-100';
        submitBtn.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>YETERSİZ BAKİYE';
    } else {
        // Yeterli Bakiye - Yeşil Buton
        submitBtn.disabled = false;
        submitBtn.className = 'btn btn-success w-100';
        submitBtn.innerHTML = '<i class="fas fa-shopping-cart me-2"></i>SATIN AL';
    }
    
    // Calculate lot equivalent for display
    const lotAmount = usdAmount / priceUSD;
    document.getElementById('lotEquivalent').style.display = 'flex';
    document.getElementById('lotAmount').textContent = formatTurkishNumber(lotAmount, 4) + ' Lot';
}

// Success Popup System with Detailed Trade Info
function showSuccessPopup(message) {
    // Remove any existing popup
    const existingPopup = document.getElementById('successPopup');
    if (existingPopup) {
        existingPopup.remove();
    }
    
    // Parse the message to extract details (format: "10 USD NVDA ALINDI")
    const parts = message.split(' ');
    const amount = parts[0];
    const currency = parts[1];
    const symbol = parts[2];
    const action = parts[3];
    
    // Get current price and calculate details
    const currentPrice = document.getElementById('modalPrice') ? 
        parseFloat(document.getElementById('modalPrice').textContent.replace(',', '.')) : 0;
    
    const usdAmount = parseFloat(amount);
    const fee = usdAmount * 0.001; // 0.1% fee
    const lotAmount = currentPrice > 0 ? usdAmount / currentPrice : 0;
    
    // Create detailed popup HTML
    const popup = document.createElement('div');
    popup.id = 'successPopup';
    popup.className = 'success-popup';
    popup.innerHTML = `
        <div class="success-overlay"></div>
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3>🎉 İşlem Başarılı!</h3>
            
            <div class="trade-details">
                <div class="trade-summary">
                    <div class="trade-main-info">
                        <span class="trade-amount">${amount} ${currency}</span>
                        <span class="trade-symbol">${symbol}</span>
                        <span class="trade-action ${action === 'ALINDI' ? 'action-buy' : 'action-sell'}">${action}</span>
                    </div>
                </div>
                
                <div class="trade-breakdown">
                    <div class="breakdown-row">
                        <span>💰 Miktar:</span>
                        <span class="value">${amount} USD</span>
                    </div>
                    <div class="breakdown-row">
                        <span>📈 Fiyat:</span>
                        <span class="value">$${currentPrice.toFixed(4)}</span>
                    </div>
                    <div class="breakdown-row">
                        <span>📊 Lot:</span>
                        <span class="value">${lotAmount.toFixed(4)} Lot</span>
                    </div>
                    <div class="breakdown-row">
                        <span>💸 Ücret:</span>
                        <span class="value">$${fee.toFixed(2)}</span>
                    </div>
                </div>
            </div>
            
            <button onclick="closeSuccessPopup()" class="btn btn-success">
                <i class="fas fa-check me-2"></i>Tamam
            </button>
        </div>
    `;
    
    // Add to body
    document.body.appendChild(popup);
    
    // Show with animation
    setTimeout(() => {
        popup.classList.add('show');
    }, 10);
    
    // Auto close after 3 seconds (kullanıcı isteği)
    setTimeout(() => {
        closeSuccessPopup();
    }, 3000);
}

function closeSuccessPopup() {
    const popup = document.getElementById('successPopup');
    if (popup) {
        popup.classList.add('closing');
        setTimeout(() => {
            popup.remove();
        }, 300);
    }
}

// Check for success/error messages on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($success_message): ?>
    showSuccessPopup('<?php echo addslashes($success_message); ?>');
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    showErrorPopup('<?php echo addslashes($error_message); ?>');
    <?php endif; ?>
});

// Error Popup System
function showErrorPopup(message) {
    // Remove any existing popup
    const existingPopup = document.getElementById('errorPopup');
    if (existingPopup) {
        existingPopup.remove();
    }
    
    // Create popup HTML
    const popup = document.createElement('div');
    popup.id = 'errorPopup';
    popup.className = 'success-popup'; // Reuse same CSS class
    popup.innerHTML = `
        <div class="success-overlay"></div>
        <div class="success-content">
            <div class="success-icon">
                <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
            </div>
            <h3 style="color: #dc3545;">İşlem Başarısız!</h3>
            <p>${message}</p>
            <button onclick="closeErrorPopup()" class="btn btn-danger">
                <i class="fas fa-times me-2"></i>Tamam
            </button>
        </div>
    `;
    
    // Add to body
    document.body.appendChild(popup);
    
    // Show with animation
    setTimeout(() => {
        popup.classList.add('show');
    }, 10);
    
    // Auto close after 5 seconds
    setTimeout(() => {
        closeErrorPopup();
    }, 5000);
}

function closeErrorPopup() {
    const popup = document.getElementById('errorPopup');
    if (popup) {
        popup.classList.add('closing');
        setTimeout(() => {
            popup.remove();
        }, 300);
    }
}

// Test function to make sure modal opens
function testModal() {
    console.log('Test modal function called');
    const modal = new bootstrap.Modal(document.getElementById('tradeModal'));
    modal.show();
}

// No sticky functionality - categories stay in normal position
function initStickyCategories() {
    // Removed sticky functionality
    console.log('Sticky categories disabled');
}

// Add click event listener when page loads
document.addEventListener('DOMContentLoaded', function() {
    console.log('Page loaded, adding modal test');
    
    // Test if bootstrap is loaded
    console.log('Bootstrap available:', typeof bootstrap !== 'undefined');
    
    // Test if modal exists
    console.log('Modal exists:', document.getElementById('tradeModal') !== null);
    
    // Initialize sticky categories
    initStickyCategories();
    
    // Initialize header balance visibility for mobile
    initMobileHeaderBalance();
});

// Mobile Header Balance Optimization
function initMobileHeaderBalance() {
    const userBalance = document.querySelector('nav .d-flex.align-items-center > div:first-child');
    
    if (userBalance && window.innerWidth <= 768) {
        // Make balance more prominent on mobile
        userBalance.style.order = '1';
        userBalance.style.marginLeft = '12px';
        userBalance.style.fontSize = '0.8rem';
        
        // Add mobile-specific styling
        const balanceSpan = userBalance.querySelector('span');
        const balanceStrong = userBalance.querySelector('strong');
        
        if (balanceSpan) balanceSpan.style.display = 'none'; // Hide "Bakiye:" text on mobile
        if (balanceStrong) {
            balanceStrong.style.color = '#28a745';
            balanceStrong.style.fontWeight = '600';
        }
    }
}
</script>

<!-- Trading Modal -->
<div class="modal fade" id="tradeModal" tabindex="-1" aria-labelledby="tradeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <div class="d-flex align-items-center">
                    <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                         style="width: 40px; height: 40px;">
                        <i class="fas fa-chart-line text-white"></i>
                    </div>
                    <div>
                        <h5 class="modal-title mb-0" id="modalSymbol">AAPL</h5>
                        <small class="text-muted" id="modalName">Apple Inc.</small>
                    </div>
                    <div class="ms-auto text-end">
                        <div class="h5 mb-0" id="modalPrice">$175.50</div>
                        <small id="modalChange">+1.25%</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Mobile Tab Navigation -->
                <div class="mobile-chart-tabs d-lg-none">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link active w-100" id="trading-tab-mobile" data-bs-toggle="tab" 
                                    data-bs-target="#trading-pane-mobile" type="button" role="tab">
                                <i class="fas fa-coins me-1"></i><?php echo getCurrentLang() == 'tr' ? 'İşlem' : 'Trading'; ?>
                            </button>
                        </li>
                        <li class="nav-item flex-fill" role="presentation">
                            <button class="nav-link w-100" id="chart-tab-mobile" data-bs-toggle="tab" 
                                    data-bs-target="#chart-pane-mobile" type="button" role="tab">
                                <i class="fas fa-chart-line me-1"></i><?php echo getCurrentLang() == 'tr' ? 'Grafik' : 'Chart'; ?>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content mobile-tab-content">
                        <!-- Mobile Trading Tab -->
                        <div class="tab-pane fade show active" id="trading-pane-mobile" role="tabpanel">
                            <div class="trading-container-mobile">
                                <div class="p-3">
                                    <!-- Mobile Trading Forms (will be populated by JavaScript) -->
                                    <div id="mobile-trading-content">
                                        <!-- Content will be dynamically copied here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mobile Chart Tab -->
                        <div class="tab-pane fade" id="chart-pane-mobile" role="tabpanel">
                            <div class="chart-container-mobile">
                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">📈 <?php echo getCurrentLang() == 'tr' ? 'Fiyat Grafiği' : 'Price Chart'; ?></h6>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary">1D</button>
                                            <button type="button" class="btn btn-outline-secondary active">1H</button>
                                            <button type="button" class="btn btn-outline-secondary">15M</button>
                                        </div>
                                    </div>
                                    <div class="chart-container">
                                        <iframe id="tradingview-mobile" 
                                                src="https://www.tradingview.com/widgetembed/?frameElementId=tradingview_mobile&symbol=AAPL&interval=1H&hidesidetoolbar=1&hidetoptoolbar=1&symboledit=1&saveimage=1&toolbarbg=F1F3F6&studies=[]&hideideas=1&theme=Light&style=1&timezone=Etc%2FUTC&locale=<?php echo getCurrentLang(); ?>&utm_source=localhost&utm_medium=widget&utm_campaign=chart&utm_term=AAPL"
                                                style="width: 100%; height: 400px; border: none;">
                                        </iframe>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Desktop Layout -->
                <div class="row g-0 d-none d-lg-flex">
                    <!-- Chart Section -->
                    <div class="col-md-8 border-end">
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Fiyat Grafiği</h6>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary">1D</button>
                                    <button type="button" class="btn btn-outline-secondary active">1H</button>
                                    <button type="button" class="btn btn-outline-secondary">15M</button>
                                </div>
                            </div>
                            <!-- TradingView Widget -->
                            <div style="height: 400px; border-radius: 8px; overflow: hidden;">
                                <iframe id="tradingview-widget" 
                                        src="https://www.tradingview.com/widgetembed/?frameElementId=tradingview_chart&symbol=AAPL&interval=1D&hidesidetoolbar=1&hidetoptoolbar=1&symboledit=1&saveimage=1&toolbarbg=F1F3F6&studies=[]&hideideas=1&theme=Light&style=1&timezone=Etc%2FUTC&studies_overrides={}&overrides={}&enabled_features=[]&disabled_features=[]&locale=en&utm_source=localhost&utm_medium=widget&utm_campaign=chart&utm_term=AAPL"
                                        style="width: 100%; height: 100%; border: none;">
                                </iframe>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Trading Section -->
                    <div class="col-md-4">
                        <div class="p-3">
                            <!-- Trading Tabs -->
                            <ul class="nav nav-tabs mb-3" id="tradingTabs" role="tablist">
                                <li class="nav-item flex-fill" role="presentation">
                                    <button class="nav-link active w-100" id="buy-tab" data-bs-toggle="tab" 
                                            data-bs-target="#buy-pane" type="button" role="tab">
                                        <i class="fas fa-shopping-cart me-1"></i>SATIN AL
                                    </button>
                                </li>
                                <li class="nav-item flex-fill" role="presentation">
                                    <button class="nav-link w-100" id="sell-tab" data-bs-toggle="tab" 
                                            data-bs-target="#sell-pane" type="button" role="tab">
                                        <i class="fas fa-hand-holding-usd me-1"></i>SAT
                                    </button>
                                </li>
                            </ul>
                            
                            <div class="tab-content" id="tradingTabsContent">
                                <!-- Buy/Long Form -->
                                <div class="tab-pane fade show active" id="buy-pane" role="tabpanel">
                                    <?php if (isset($success)): ?>
                                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (isset($error)): ?>
                                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (isLoggedIn()): ?>
                                    <form id="buyForm" method="POST" action="markets.php?group=<?php echo $category; ?>">
                                        <input type="hidden" name="trade_action" value="buy">
                                        <input type="hidden" name="symbol" id="buySymbol" value="">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">USD Miktar</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="usd_amount" name="usd_amount" step="0.01" min="0.01" 
                                                       placeholder="10.00" oninput="calculateSimpleTrade()" required>
                                                <span class="input-group-text">USD</span>
                                            </div>
                                            <small class="text-muted">Satın almak istediğiniz USD tutarı</small>
                                        </div>
                                        
                                        <div class="mb-3 leverage-control">
                                            <label class="form-label">Kaldıraç <span id="leverageDisplay" class="badge bg-primary">1x</span></label>
                                            <input type="range" class="form-range" id="leverage" name="leverage" min="1" max="100" value="1" 
                                                   oninput="calculateTrade()">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">1x</small>
                                                <small class="text-muted">100x</small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3 advanced-control">
                                            <div class="col-6">
                                                <label class="form-label">Stop Loss</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" step="0.01" placeholder="0.00">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Take Profit</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" step="0.01" placeholder="0.00">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Trade Summary -->
                                        <div class="card border-0 bg-light mb-3">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small class="text-muted">Toplam Değer:</small>
                                                    <small class="fw-bold" id="totalValue">$0.00</small>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1" id="lotEquivalent" style="display: none;">
                                                    <small class="text-muted">Lot Miktarı:</small>
                                                    <small class="fw-bold" id="lotAmount">0.00 Lot</small>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small class="text-muted calculation-label">Gerekli Margin:</small>
                                                    <small class="fw-bold" id="requiredMargin">$0.00</small>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">İşlem Ücreti:</small>
                                                    <small class="fw-bold" id="tradingFee">$0.00</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Compact Exchange Rate Info for TL Mode -->
                                        <div class="compact-exchange-info mb-3" id="exchangeInfo" style="display: none !important;">
                                            <span class="badge bg-info">
                                                💱 1 USD = <?php echo formatTurkishNumber($usd_try_rate, 2); ?> TL
                                            </span>
                                            <small class="text-muted ms-2">TL ile ödeme</small>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-arrow-up me-2"></i>LONG POZISYON AÇ
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-3">
                                            <?php echo getCurrentLang() == 'tr' ? 'İşlem yapmak için giriş yapmanız gerekiyor' : 'Please login to trade'; ?>
                                        </p>
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Giriş Yap' : 'Login'; ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                        <!-- Sell/Short Form -->
                        <div class="tab-pane fade" id="sell-pane" role="tabpanel">
                            <?php if (isLoggedIn()): ?>
                            
                            <!-- Portfolio Holdings for this symbol -->
                            <div class="mb-3" id="portfolioHolding" style="display: none;">
                                <div class="card bg-light border-0">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2">💼 Portföyünüzde</h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold" id="holdingQuantity">0.000000</div>
                                                <small class="text-muted">adet</small>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold" id="holdingValue">$0.00</div>
                                                <small class="text-muted">güncel değer</small>
                                            </div>
                                        </div>
                                        <div class="mt-2">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">Ortalama Fiyat:</small>
                                                <small id="holdingAvgPrice">$0.00</small>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">Kar/Zarar:</small>
                                                <small id="holdingPnL" class="text-success">+$0.00</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <form id="sellForm" method="POST" action="">
                                <input type="hidden" name="trade_action" value="sell">
                                <input type="hidden" name="symbol" id="sellSymbol" value="">
                                
                                <div class="mb-3">
                                    <label class="form-label">USD Miktar</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="usd_amount_sell" name="usd_amount" step="0.01" min="0.01" 
                                               placeholder="10.00" oninput="calculateSimpleTradeSell()" required>
                                        <span class="input-group-text">USD</span>
                                    </div>
                                    <small class="text-muted">Satmak istediğiniz USD tutarı</small>
                                </div>
                                
                                <!-- Quick Amount Buttons for Portfolio -->
                                <div class="mb-3" id="quickSellButtons" style="display: none;">
                                    <small class="text-muted d-block mb-2">Hızlı Seçim:</small>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setPortfolioSellPercentage(25)">%25</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setPortfolioSellPercentage(50)">%50</button>
                                        <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" onclick="setPortfolioSellPercentage(75)">%75</button>
                                        <button type="button" class="btn btn-outline-danger btn-sm flex-fill" onclick="setPortfolioSellPercentage(100)">Tümü</button>
                                    </div>
                                </div>
                                        
                                        <div class="mb-3 leverage-control">
                                            <label class="form-label">Kaldıraç <span class="badge bg-primary">1x</span></label>
                                            <input type="range" class="form-range" min="1" max="100" value="1">
                                            <div class="d-flex justify-content-between">
                                                <small class="text-muted">1x</small>
                                                <small class="text-muted">100x</small>
                                            </div>
                                        </div>
                                        
                                        <div class="row mb-3 advanced-control">
                                            <div class="col-6">
                                                <label class="form-label">Stop Loss</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" step="0.01" placeholder="0.00">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label">Take Profit</label>
                                                <div class="input-group">
                                                    <input type="number" class="form-control" step="0.01" placeholder="0.00">
                                                    <span class="input-group-text">$</span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Trade Summary -->
                                        <div class="card border-0 bg-light mb-3">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small class="text-muted">Toplam Değer:</small>
                                                    <small class="fw-bold">$0.00</small>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <small class="text-muted calculation-label">Gerekli Margin:</small>
                                                    <small class="fw-bold">$0.00</small>
                                                </div>
                                                <div class="d-flex justify-content-between">
                                                    <small class="text-muted">İşlem Ücreti:</small>
                                                    <small class="fw-bold">$0.00</small>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-danger w-100">
                                            <i class="fas fa-arrow-down me-2"></i>SHORT POZISYON AÇ
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                                        <p class="text-muted mb-3">
                                            <?php echo getCurrentLang() == 'tr' ? 'İşlem yapmak için giriş yapmanız gerekiyor' : 'Please login to trade'; ?>
                                        </p>
                                        <a href="login.php" class="btn btn-primary">
                                            <i class="fas fa-sign-in-alt me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Giriş Yap' : 'Login'; ?>
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
