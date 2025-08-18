<?php
require_once 'includes/functions.php';

// Require login for wallet
requireLogin();

$page_title = t('wallet');
$error = '';
$success = '';

$user_id = $_SESSION['user_id'];

// Handle deposit/withdrawal requests
if ($_POST) {
    if (isset($_POST['deposit'])) {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? '';
        $reference = sanitizeInput($_POST['reference'] ?? '');
        $deposit_type = $_POST['deposit_type'] ?? 'normal';
        $tl_amount = (float)($_POST['tl_amount'] ?? 0);
        
        // Handle different deposit types
        if ($deposit_type == 'tl_to_usd') {
            // USD Mode: User pays in TL, gets USD
            if ($tl_amount < MIN_DEPOSIT_AMOUNT) {
                $error = getCurrentLang() == 'tr' ? 
                    'Minimum para yatırma tutarı ' . MIN_DEPOSIT_AMOUNT . ' TL' : 
                    'Minimum deposit amount is ' . MIN_DEPOSIT_AMOUNT . ' TL';
            } elseif ($amount <= 0) {
                $error = getCurrentLang() == 'tr' ? 'Geçersiz dolar tutarı' : 'Invalid USD amount';
            } elseif (!in_array($method, ['iban', 'papara'])) {
                $error = getCurrentLang() == 'tr' ? 'Geçersiz ödeme yöntemi' : 'Invalid payment method';
            } else {
                $database = new Database();
                $db = $database->getConnection();
                
                // Check if new columns exist, if not use basic insert
                try {
                    $query = "INSERT INTO deposits (user_id, amount, method, reference, deposit_type, tl_amount, usd_amount, exchange_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    $exchange_rate = $usd_try_rate;
                    
                    if ($stmt->execute([$user_id, $amount, $method, $reference, 'tl_to_usd', $tl_amount, $amount, $exchange_rate])) {
                        $success = t('deposit_request_sent');
                        logActivity($user_id, 'deposit_request', "TL-to-USD Deposit: $tl_amount TL → $amount USD (Rate: $exchange_rate), Method: $method");
                    } else {
                        $error = getCurrentLang() == 'tr' ? 'Bir hata oluştu' : 'An error occurred';
                    }
                } catch (PDOException $e) {
                    // Fallback to basic insert if new columns don't exist
                    $query = "INSERT INTO deposits (user_id, amount, method, reference) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    $deposit_reference = $reference . " (TL: $tl_amount → USD: $amount, Rate: $usd_try_rate)";
                    
                    if ($stmt->execute([$user_id, $amount, $method, $deposit_reference])) {
                        $success = t('deposit_request_sent');
                        logActivity($user_id, 'deposit_request', "TL-to-USD Deposit: $tl_amount TL → $amount USD (Rate: $usd_try_rate), Method: $method");
                    } else {
                        $error = getCurrentLang() == 'tr' ? 'Bir hata oluştu' : 'An error occurred';
                    }
                }
            }
        } else {
            // Normal TL deposit
            if ($amount < MIN_DEPOSIT_AMOUNT) {
                $error = getCurrentLang() == 'tr' ? 
                    'Minimum para yatırma tutarı ' . MIN_DEPOSIT_AMOUNT . ' TL' : 
                    'Minimum deposit amount is ' . MIN_DEPOSIT_AMOUNT . ' TL';
            } elseif (!in_array($method, ['iban', 'papara'])) {
                $error = getCurrentLang() == 'tr' ? 'Geçersiz ödeme yöntemi' : 'Invalid payment method';
            } else {
                $database = new Database();
                $db = $database->getConnection();
                
                try {
                    $query = "INSERT INTO deposits (user_id, amount, method, reference, deposit_type) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$user_id, $amount, $method, $reference, 'normal'])) {
                        $success = t('deposit_request_sent');
                        logActivity($user_id, 'deposit_request', "Amount: $amount TL, Method: $method");
                    } else {
                        $error = getCurrentLang() == 'tr' ? 'Bir hata oluştu' : 'An error occurred';
                    }
                } catch (PDOException $e) {
                    // Fallback to basic insert if deposit_type column doesn't exist
                    $query = "INSERT INTO deposits (user_id, amount, method, reference) VALUES (?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    
                    if ($stmt->execute([$user_id, $amount, $method, $reference])) {
                        $success = t('deposit_request_sent');
                        logActivity($user_id, 'deposit_request', "Amount: $amount TL, Method: $method");
                    } else {
                        $error = getCurrentLang() == 'tr' ? 'Bir hata oluştu' : 'An error occurred';
                    }
                }
            }
        }
    }
    
    if (isset($_POST['withdraw'])) {
        $amount = (float)($_POST['amount'] ?? 0);
        $method = $_POST['method'] ?? '';
        $iban_info = sanitizeInput($_POST['iban_info'] ?? '');
        $papara_info = sanitizeInput($_POST['papara_info'] ?? '');
        
        $balance_tl = getUserBalance($user_id, 'tl');
        
        if ($amount < MIN_WITHDRAWAL_AMOUNT) {
            $error = getCurrentLang() == 'tr' ? 
                'Minimum para çekme tutarı ' . MIN_WITHDRAWAL_AMOUNT . ' TL' : 
                'Minimum withdrawal amount is ' . MIN_WITHDRAWAL_AMOUNT . ' TL';
        } elseif ($amount > $balance_tl) {
            $error = t('insufficient_balance');
        } elseif (!in_array($method, ['iban', 'papara'])) {
            $error = getCurrentLang() == 'tr' ? 'Geçersiz ödeme yöntemi' : 'Invalid payment method';
        } elseif ($method == 'iban' && empty($iban_info)) {
            $error = getCurrentLang() == 'tr' ? 'IBAN bilgisi gerekli' : 'IBAN information required';
        } elseif ($method == 'papara' && empty($papara_info)) {
            $error = getCurrentLang() == 'tr' ? 'Papara bilgisi gerekli' : 'Papara information required';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "INSERT INTO withdrawals (user_id, amount, method, iban_info, papara_info) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$user_id, $amount, $method, $iban_info, $papara_info])) {
                $success = t('withdrawal_request_sent');
                logActivity($user_id, 'withdrawal_request', "Amount: $amount TL, Method: $method");
            } else {
                $error = getCurrentLang() == 'tr' ? 'Bir hata oluştu' : 'An error occurred';
            }
        }
    }
}

// Get trading currency settings and USD/TRY rate
$trading_currency = getTradingCurrency();
$currency_field = getCurrencyField($trading_currency);
$currency_symbol = getCurrencySymbol($trading_currency);
$usd_try_rate = getUSDTRYRate();

// Get user balances based on trading currency
$balance_tl = getUserBalance($user_id, 'tl');
$balance_usd = getUserBalance($user_id, 'usd');

// Set primary balance based on trading currency
if ($trading_currency == 1) { // TL Mode
    $primary_balance = $balance_tl;
    $primary_currency = 'TL';
    $secondary_balance = $balance_usd;
    $secondary_currency = 'USD';
} else { // USD Mode  
    $primary_balance = $balance_usd;
    $primary_currency = 'USD';
    $secondary_balance = $balance_tl;
    $secondary_currency = 'TL';
}

// Get recent deposits and withdrawals
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get payment methods from database
$query = "SELECT * FROM payment_methods WHERE is_active = 1 ORDER BY sort_order";
$stmt = $db->prepare($query);
$stmt->execute();
$payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group payment methods by type
$banks = [];
$cryptos = [];
$digital = [];
foreach ($payment_methods as $method) {
    if ($method['type'] == 'bank') {
        $banks[] = $method;
    } elseif ($method['type'] == 'crypto') {
        $cryptos[] = $method;
    } elseif ($method['type'] == 'digital') {
        $digital[] = $method;
    }
}

include 'includes/header.php';
?>

<div class="container">
    <div class="row">
        <!-- Wallet Overview -->
        <div class="col-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h4 class="mb-0"><?php echo t('wallet'); ?></h4>
                </div>
                <div class="card-body">
                    <div class="row justify-content-center">
                        <!-- Primary Currency (Based on Trading Parameter) -->
                        <div class="col-md-6 col-12 mb-3">
                            <div class="text-center p-4 bg-success bg-opacity-10 rounded border border-success">
                                <i class="fas fa-<?php echo $trading_currency == 1 ? 'lira-sign' : 'dollar-sign'; ?> fa-3x text-success mb-3"></i>
                                <div class="h3 mb-1 text-success"><?php echo formatNumber($primary_balance); ?></div>
                                <div class="h6 text-success">
                                    <?php echo $trading_currency == 1 ? 'Türk Lirası' : 'US Dollar'; ?>
                                </div>
                                <small class="text-success fw-bold">Ana Bakiye</small>
                            </div>
                        </div>
                        
                        <!-- Secondary Currency -->
                        <div class="col-md-6 col-12 mb-3">
                            <div class="text-center p-4 bg-light rounded border">
                                <i class="fas fa-<?php echo $trading_currency == 1 ? 'dollar-sign' : 'lira-sign'; ?> fa-2x text-muted mb-3"></i>
                                <div class="h4 mb-1"><?php echo formatNumber($secondary_balance); ?></div>
                                <div class="h6 text-muted">
                                    <?php echo $trading_currency == 1 ? 'US Dollar' : 'Türk Lirası'; ?>
                                </div>
                                <small class="text-muted">
                                    <?php if ($trading_currency == 1): ?>
                                        ≈ <?php echo formatNumber($secondary_balance * $usd_try_rate); ?> TL
                                    <?php else: ?>
                                        ≈ <?php echo formatNumber($secondary_balance / $usd_try_rate); ?> USD
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Exchange Rate Info -->
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-exchange-alt me-1"></i>
                            1 USD = <?php echo formatNumber($usd_try_rate, 4); ?> TL
                            <span class="ms-2">|</span>
                            <span class="ms-2">1 TL = <?php echo formatNumber(1 / $usd_try_rate, 4); ?> USD</span>
                        </small>
                    </div>
                </div>
                </div>
            </div>
        </div>
        
        <!-- Deposit/Withdraw Forms -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Deposit/Withdraw Tabs -->
                    <ul class="nav nav-pills nav-fill mb-3" id="walletTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="deposit-tab" data-bs-toggle="pill" data-bs-target="#deposit" type="button">
                                <i class="fas fa-plus me-1"></i><?php echo t('deposit'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="withdraw-tab" data-bs-toggle="pill" data-bs-target="#withdraw" type="button">
                                <i class="fas fa-minus me-1"></i><?php echo t('withdraw'); ?>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="walletTabsContent">
                        <!-- Deposit Form -->
                        <div class="tab-pane fade show active" id="deposit" role="tabpanel">
                            <form method="POST" action="">
                                <?php if ($trading_currency == 2): // USD Mode - User pays in TL, gets USD ?>
                                <!-- TL Input for USD Account -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lira-sign me-1 text-success"></i>
                                        Yatırılacak TL Tutarı
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="tl_amount" step="0.01" 
                                               min="<?php echo MIN_DEPOSIT_AMOUNT; ?>" id="tlDepositAmount" 
                                               oninput="calculateUSDConversion()" required>
                                        <span class="input-group-text bg-success text-white">TL</span>
                                    </div>
                                    <small class="text-muted">
                                        Minimum: <?php echo MIN_DEPOSIT_AMOUNT; ?> TL
                                    </small>
                                </div>

                                <!-- USD Equivalent Display -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-dollar-sign me-1 text-primary"></i>
                                        Hesabınıza Geçecek Dolar Miktarı
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control bg-light" id="usdEquivalent" 
                                               step="0.01" readonly placeholder="0.00">
                                        <span class="input-group-text bg-primary text-white">USD</span>
                                    </div>
                                    <small class="text-info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        1 USD = <?php echo formatNumber($usd_try_rate, 4); ?> TL kurunda hesaplanmaktadır
                                    </small>
                                </div>

                                <!-- Hidden field for backend processing -->
                                <input type="hidden" name="amount" id="hiddenUSDAmount" value="">
                                <input type="hidden" name="deposit_type" value="tl_to_usd">

                                <?php else: // TL Mode - Normal TL Deposit ?>
                                <!-- Normal TL Deposit -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-lira-sign me-1 text-success"></i>
                                        Yatırılacak TL Tutarı
                                    </label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="amount" step="0.01" 
                                               min="<?php echo MIN_DEPOSIT_AMOUNT; ?>" required>
                                        <span class="input-group-text bg-success text-white">TL</span>
                                    </div>
                                    <small class="text-muted">
                                        Minimum: <?php echo MIN_DEPOSIT_AMOUNT; ?> TL
                                    </small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label class="form-label"><?php echo getCurrentLang() == 'tr' ? 'Ödeme Yöntemi' : 'Payment Method'; ?></label>
                                    <select class="form-select" name="method" required>
                                        <option value=""><?php echo getCurrentLang() == 'tr' ? 'Seçiniz' : 'Select'; ?></option>
                                        <option value="iban">IBAN (Banka Havalesi)</option>
                                        <option value="papara">Papara</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><?php echo getCurrentLang() == 'tr' ? 'Referans/Açıklama' : 'Reference/Description'; ?></label>
                                    <input type="text" class="form-control" name="reference" 
                                           placeholder="<?php echo getCurrentLang() == 'tr' ? 'İşlem referansı veya açıklama' : 'Transaction reference or description'; ?>">
                                </div>
                                
                                <button type="submit" name="deposit" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-2"></i><?php echo t('deposit'); ?>
                                </button>
                            </form>
                            
                            <!-- Deposit Instructions -->
                            <div class="mt-4 p-3 bg-info bg-opacity-10 border border-info rounded">
                                <h6 class="text-info"><?php echo getCurrentLang() == 'tr' ? 'Para Yatırma Talimatları' : 'Deposit Instructions'; ?></h6>
                                <small class="text-muted">
                                    <strong>IBAN:</strong> TR12 3456 7890 1234 5678 90<br>
                                    <strong>Hesap Adı:</strong> GlobalBorsa Ltd.<br>
                                    <strong>Papara No:</strong> 1234567890<br>
                                    <br>
                                    <?php echo getCurrentLang() == 'tr' ? 
                                        'Havale/EFT açıklama kısmına kullanıcı adınızı yazınız.' : 
                                        'Please include your username in the transfer description.'; ?>
                                </small>
                            </div>
                        </div>
                        
                        <!-- Simple Withdraw Form -->
                        <div class="tab-pane fade" id="withdraw" role="tabpanel">
                            <form method="POST" action="">
                                <!-- Ödeme Yöntemi -->
                                <div class="mb-3">
                                    <label class="form-label">Ödeme Yöntemi</label>
                                    <select class="form-select" name="method" id="withdrawMethod" required>
                                        <option value="">Seçiniz</option>
                                        <option value="iban">🏦 Banka Havalesi</option>
                                        <option value="papara">📱 Papara</option>
                                        <option value="crypto">₿ Kripto Para</option>
                                    </select>
                                </div>

                                <!-- Kullanıcı Bilgileri -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Ad Soyad</label>
                                        <input type="text" class="form-control" readonly value="<?php 
                                        // Get user info from database
                                        $database = new Database();
                                        $db = $database->getConnection();
                                        $query = "SELECT username FROM users WHERE id = ?";
                                        $stmt = $db->prepare($query);
                                        $stmt->execute([$user_id]);
                                        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                                        echo htmlspecialchars($user_data['username'] ?? 'Kullan��cı');
                                        ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">TC Kimlik No</label>
                                        <input type="text" class="form-control" name="tc_number" 
                                               placeholder="12345678901" maxlength="11" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Telefon Numarası</label>
                                    <input type="tel" class="form-control" name="phone" 
                                           placeholder="0555 123 45 67" required>
                                </div>

                                <!-- Tutar -->
                                <div class="mb-3">
                                    <label class="form-label">Çekilecek Tutar</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="amount" 
                                               step="<?php echo $trading_currency == 1 ? '10' : '1'; ?>" 
                                               min="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" 
                                               max="<?php echo $primary_balance; ?>" 
                                               placeholder="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" 
                                               id="withdrawAmount" oninput="calculateWithdrawConversion()" required>
                                        <span class="input-group-text">
                                            <?php echo $trading_currency == 1 ? 'TL' : 'USD'; ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">
                                            Kullanılabilir: <?php echo formatNumber($primary_balance); ?> <?php echo $primary_currency; ?>
                                        </small>
                                        <small class="text-info" id="withdrawConversion"></small>
                                    </div>
                                    <div class="d-flex justify-content-end mt-2">
                                        <?php if ($trading_currency == 1): ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(100)">100</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(500)">500</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(1000)">1000</button>
                                        <?php else: ?>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(10)">10</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(50)">50</button>
                                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(100)">100</button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Banka Bilgileri -->
                                <div id="bankDetails" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Banka Seçiniz</label>
                                        <select class="form-select" name="bank_name">
                                            <option value="">Banka Seçiniz</option>
                                            <?php foreach ($banks as $bank): ?>
                                            <option value="<?php echo $bank['code']; ?>">
                                                <?php echo $bank['icon']; ?> <?php echo $bank['name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">IBAN</label>
                                        <input type="text" class="form-control" name="iban_info" 
                                               placeholder="TR00 0000 0000 0000 0000 0000 00" required>
                                    </div>
                                </div>

                                <!-- Papara Bilgileri -->
                                <div id="paparaDetails" style="display: none;">
                                    <?php foreach ($digital as $method): ?>
                                    <div class="mb-3">
                                        <label class="form-label"><?php echo $method['icon']; ?> <?php echo $method['name']; ?> Hesap No</label>
                                        <input type="text" class="form-control" name="papara_info" 
                                               placeholder="<?php echo $method['name']; ?> hesap numaranız" required>
                                    </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Kripto Bilgileri -->
                                <div id="cryptoDetails" style="display: none;">
                                    <div class="mb-3">
                                        <label class="form-label">Kripto Para Seçiniz</label>
                                        <select class="form-select" name="crypto_type" required>
                                            <option value="">Kripto Para Seçiniz</option>
                                            <?php foreach ($cryptos as $crypto): ?>
                                            <option value="<?php echo $crypto['code']; ?>">
                                                <?php echo $crypto['icon']; ?> <?php echo $crypto['name']; ?> (<?php echo $crypto['code']; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Wallet Adresi</label>
                                        <input type="text" class="form-control" name="crypto_address" 
                                               placeholder="Kripto para cüzdan adresinizi girin" required>
                                    </div>
                                    <div class="alert alert-warning">
                                        <small>
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            Network ücretleri çekim tutarından düşülecektir.
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <small>Para çekme işlemi admin onayı gerektirir. İşlem süresi 1-3 iş günüdür.</small>
                                </div>
                                
                                <button type="submit" name="withdraw" class="btn btn-danger w-100">
                                    <i class="fas fa-arrow-down me-2"></i>Para Çek
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Transaction History -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><?php echo t('transaction_history'); ?></h5>
                </div>
                <div class="card-body">
                    <!-- History Tabs -->
                    <ul class="nav nav-tabs nav-fill mb-3" id="historyTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="deposits-tab" data-bs-toggle="tab" data-bs-target="#deposits" type="button">
                                <?php echo getCurrentLang() == 'tr' ? 'Para Yatırma' : 'Deposits'; ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="withdrawals-tab" data-bs-toggle="tab" data-bs-target="#withdrawals" type="button">
                                <?php echo getCurrentLang() == 'tr' ? 'Para Çekme' : 'Withdrawals'; ?>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="historyTabsContent">
                        <!-- Deposits History -->
                        <div class="tab-pane fade show active" id="deposits" role="tabpanel">
                            <?php if (empty($deposits)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-plus-circle fa-2x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php echo getCurrentLang() == 'tr' ? 'Henüz para yatırma işlemi yok' : 'No deposit history yet'; ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Tarih' : 'Date'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Tutar' : 'Amount'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Yöntem' : 'Method'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Durum' : 'Status'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($deposits as $deposit): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i', strtotime($deposit['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                // Show amount in appropriate currency based on trading parameter and deposit type
                                if (isset($deposit['deposit_type']) && $deposit['deposit_type'] == 'tl_to_usd' && $trading_currency == 2) {
                                    // TL-to-USD deposit - amount field already contains USD value
                                    echo formatNumber($deposit['amount']) . ' USD';
                                    if (isset($deposit['tl_amount']) && $deposit['tl_amount'] > 0) {
                                        echo '<br><small class="text-muted">(' . formatNumber($deposit['tl_amount']) . ' TL)</small>';
                                    }
                                } elseif ($trading_currency == 2) {
                                    // USD Mode - convert TL to USD for display (legacy deposits)
                                    $usd_amount = $deposit['amount'] / $usd_try_rate;
                                    echo formatNumber($usd_amount) . ' USD';
                                    echo '<br><small class="text-muted">(' . formatNumber($deposit['amount']) . ' TL)</small>';
                                } else {
                                    // TL Mode - show TL amount
                                    echo formatNumber($deposit['amount']) . ' TL';
                                }
                                                ?>
                                            </td>
                                            <td><?php echo strtoupper($deposit['method']); ?></td>
                                            <td>
                                                <?php
                                                $status_class = $deposit['status'] == 'approved' ? 'success' : 
                                                              ($deposit['status'] == 'rejected' ? 'danger' : 'warning');
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo t($deposit['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Withdrawals History -->
                        <div class="tab-pane fade" id="withdrawals" role="tabpanel">
                            <?php if (empty($withdrawals)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-minus-circle fa-2x text-muted mb-3"></i>
                                <p class="text-muted">
                                    <?php echo getCurrentLang() == 'tr' ? 'Henüz para çekme işlemi yok' : 'No withdrawal history yet'; ?>
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Tarih' : 'Date'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Tutar' : 'Amount'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Yöntem' : 'Method'; ?></th>
                                            <th><?php echo getCurrentLang() == 'tr' ? 'Durum' : 'Status'; ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($withdrawals as $withdrawal): ?>
                                        <tr>
                                            <td><?php echo date('d.m.Y H:i', strtotime($withdrawal['created_at'])); ?></td>
                                            <td>
                                                <?php 
                                                // Show amount in appropriate currency based on trading parameter
                                                if ($trading_currency == 2) {
                                                    // USD Mode - show as USD
                                                    echo formatNumber($withdrawal['amount']) . ' USD';
                                                } else {
                                                    // TL Mode - show as TL
                                                    echo formatNumber($withdrawal['amount']) . ' TL';
                                                }
                                                ?>
                                            </td>
                                            <td><?php echo strtoupper($withdrawal['method']); ?></td>
                                            <td>
                                                <?php
                                                $status_class = $withdrawal['status'] == 'approved' ? 'success' : 
                                                              ($withdrawal['status'] == 'rejected' ? 'danger' : 'warning');
                                                ?>
                                                <span class="badge bg-<?php echo $status_class; ?>">
                                                    <?php echo t($withdrawal['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modern Wallet Styles -->
<style>
.withdraw-method-card, .bank-option, .crypto-option {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
}

.withdraw-method-card:hover, .bank-option:hover, .crypto-option:hover {
    border-color: #007bff;
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.15);
    transform: translateY(-2px);
}

.withdraw-method-card.active, .bank-option.active, .crypto-option.active {
    border-color: #007bff;
    background: linear-gradient(135deg, #007bff, #0056b3);
    color: white;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
}

.withdraw-method-card.active h6, .withdraw-method-card.active small,
.bank-option.active small, .crypto-option.active small {
    color: white !important;
}

.bank-logo {
    height: 40px;
    width: auto;
    max-width: 80px;
    object-fit: contain;
    margin-bottom: 0.5rem;
}

.bank-option, .crypto-option {
    text-align: center;
    padding: 1rem;
    margin-bottom: 0.5rem;
}

.crypto-logo {
    font-size: 2rem;
    font-weight: bold;
    color: #28a745;
    margin-bottom: 0.5rem;
}

.withdraw-details {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.amount-adjuster {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .withdraw-method-card {
        margin-bottom: 1rem;
    }
    
    .bank-option, .crypto-option {
        padding: 0.75rem;
    }
    
    .bank-logo {
        height: 30px;
    }
}
</style>

<script>
// Trading currency and exchange rate constants from PHP
const TRADING_CURRENCY = <?php echo $trading_currency; ?>;
const USD_TRY_RATE = <?php echo $usd_try_rate; ?>;

// Set amount quickly
function setAmount(amount) {
    const amountInput = document.querySelector('input[name="amount"]');
    if (amountInput) {
        amountInput.value = amount;
        // Trigger conversion calculation
        if (amountInput.id === 'withdrawAmount') {
            calculateWithdrawConversion();
        }
    }
}

// Calculate USD conversion for USD Mode deposits (TL to USD)
function calculateUSDConversion() {
    const tlAmountInput = document.getElementById('tlDepositAmount');
    const usdEquivalentInput = document.getElementById('usdEquivalent');
    const hiddenUSDAmountInput = document.getElementById('hiddenUSDAmount');
    
    if (!tlAmountInput || !usdEquivalentInput || !hiddenUSDAmountInput) return;
    
    const tlAmount = parseFloat(tlAmountInput.value) || 0;
    
    if (tlAmount <= 0) {
        usdEquivalentInput.value = '';
        hiddenUSDAmountInput.value = '';
        return;
    }
    
    // Convert TL to USD
    const usdAmount = tlAmount / USD_TRY_RATE;
    
    // Update display and hidden field
    usdEquivalentInput.value = usdAmount.toFixed(4);
    hiddenUSDAmountInput.value = usdAmount.toFixed(4);
}

// Calculate deposit conversion (for TL Mode)
function calculateDepositConversion() {
    const amountInput = document.getElementById('depositAmount');
    const conversionDisplay = document.getElementById('depositConversion');
    
    if (!amountInput || !conversionDisplay) return;
    
    const amount = parseFloat(amountInput.value) || 0;
    
    if (amount <= 0) {
        conversionDisplay.textContent = '';
        return;
    }
    
    let convertedAmount = 0;
    let conversionText = '';
    
    if (TRADING_CURRENCY === 1) { // TL Mode - show USD equivalent
        convertedAmount = amount / USD_TRY_RATE;
        conversionText = `≈ ${convertedAmount.toFixed(2)} USD`;
    } else { // USD Mode - show TL equivalent
        convertedAmount = amount * USD_TRY_RATE;
        conversionText = `≈ ${convertedAmount.toFixed(2)} TL`;
    }
    
    conversionDisplay.textContent = conversionText;
}

// Calculate withdrawal conversion
function calculateWithdrawConversion() {
    const amountInput = document.getElementById('withdrawAmount');
    const conversionDisplay = document.getElementById('withdrawConversion');
    
    if (!amountInput || !conversionDisplay) return;
    
    const amount = parseFloat(amountInput.value) || 0;
    
    if (amount <= 0) {
        conversionDisplay.textContent = '';
        return;
    }
    
    let convertedAmount = 0;
    let conversionText = '';
    
    if (TRADING_CURRENCY === 1) { // TL Mode - show USD equivalent
        convertedAmount = amount / USD_TRY_RATE;
        conversionText = `≈ ${convertedAmount.toFixed(2)} USD`;
    } else { // USD Mode - show TL equivalent
        convertedAmount = amount * USD_TRY_RATE;
        conversionText = `≈ ${convertedAmount.toFixed(2)} TL`;
    }
    
    conversionDisplay.textContent = conversionText;
}

// Turkish number formatting
function formatTurkishNumber(number, decimals = 2) {
    return new Intl.NumberFormat('tr-TR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    }).format(number);
}

// TC Kimlik validation - sadece sayı
function validateTC() {
    const tcInput = document.querySelector('input[name="tc_number"]');
    if (tcInput) {
        tcInput.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, ''); // Sadece sayılar
            if (this.value.length > 11) {
                this.value = this.value.substring(0, 11);
            }
        });
    }
}

// Show/hide method details
document.getElementById('withdrawMethod').addEventListener('change', function() {
    const method = this.value;
    const bankDetails = document.getElementById('bankDetails');
    const paparaDetails = document.getElementById('paparaDetails'); 
    const cryptoDetails = document.getElementById('cryptoDetails');
    
    // Hide all details
    [bankDetails, paparaDetails, cryptoDetails].forEach(el => {
        if (el) el.style.display = 'none';
    });
    
    // Show relevant details
    if (method === 'iban' && bankDetails) {
        bankDetails.style.display = 'block';
    } else if (method === 'papara' && paparaDetails) {
        paparaDetails.style.display = 'block';
    } else if (method === 'crypto' && cryptoDetails) {
        cryptoDetails.style.display = 'block';
    }
});

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    validateTC();
});
</script>

<?php include 'includes/footer.php'; ?>
