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
        
        if ($amount < MIN_DEPOSIT_AMOUNT) {
            $error = getCurrentLang() == 'tr' ? 
                'Minimum para yatırma tutarı ' . MIN_DEPOSIT_AMOUNT . ' TL' : 
                'Minimum deposit amount is ' . MIN_DEPOSIT_AMOUNT . ' TL';
        } elseif (!in_array($method, ['iban', 'papara'])) {
            $error = getCurrentLang() == 'tr' ? 'Geçersiz ödeme yöntemi' : 'Invalid payment method';
        } else {
            $database = new Database();
            $db = $database->getConnection();
            
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

// Get user balances
$balance_tl = getUserBalance($user_id, 'tl');
$balance_usd = getUserBalance($user_id, 'usd');
$balance_btc = getUserBalance($user_id, 'btc');
$balance_eth = getUserBalance($user_id, 'eth');

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
                    <div class="row">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-success bg-opacity-10 rounded border border-success">
                                <i class="fas fa-lira-sign fa-2x text-success mb-2"></i>
                                <div class="h4 mb-1 text-success"><?php echo formatNumber($balance_tl); ?></div>
                                <small class="text-success">Türk Lirası</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="fas fa-dollar-sign fa-2x text-success mb-2"></i>
                                <div class="h4 mb-1"><?php echo formatNumber($balance_usd); ?></div>
                                <small class="text-muted">US Dollar</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="fab fa-bitcoin fa-2x text-warning mb-2"></i>
                                <div class="h4 mb-1"><?php echo formatPrice($balance_btc); ?></div>
                                <small class="text-muted">Bitcoin</small>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="text-center p-3 bg-light rounded">
                                <i class="fab fa-ethereum fa-2x text-info mb-2"></i>
                                <div class="h4 mb-1"><?php echo formatPrice($balance_eth); ?></div>
                                <small class="text-muted">Ethereum</small>
                            </div>
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
                                <div class="mb-3">
                                    <label class="form-label"><?php echo getCurrentLang() == 'tr' ? 'Yatırılacak Tutar' : 'Deposit Amount'; ?></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="amount" step="0.01" 
                                               min="<?php echo MIN_DEPOSIT_AMOUNT; ?>" required>
                                        <span class="input-group-text">TL</span>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo getCurrentLang() == 'tr' ? 'Minimum:' : 'Minimum:'; ?> 
                                        <?php echo MIN_DEPOSIT_AMOUNT; ?> TL
                                    </small>
                                </div>
                                
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
                                               step="10" min="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" 
                                               max="<?php echo $balance_tl; ?>" 
                                               placeholder="<?php echo MIN_WITHDRAWAL_AMOUNT; ?>" required>
                                        <span class="input-group-text">TL</span>
                                    </div>
                                    <div class="d-flex justify-content-between mt-1">
                                        <small class="text-muted">
                                            Kullanılabilir: <?php echo formatNumber($balance_tl); ?> TL
                                        </small>
                                        <div>
                                            <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(100)">100</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm me-1" onclick="setAmount(500)">500</button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="setAmount(1000)">1000</button>
                                        </div>
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
                                            <td><?php echo formatNumber($deposit['amount']); ?> TL</td>
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
                                            <td><?php echo formatNumber($withdrawal['amount']); ?> TL</td>
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
// Set amount quickly
function setAmount(amount) {
    const amountInput = document.querySelector('input[name="amount"]');
    if (amountInput) {
        amountInput.value = amount;
    }
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
