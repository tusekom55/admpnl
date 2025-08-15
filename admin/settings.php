<?php
require_once '../includes/functions.php';
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'Sistem Ayarları - GlobalBorsa Admin';
$success = '';
$error = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_currency') {
        // Update trading currency
        $currency_mode = intval($_POST['currency_mode'] ?? 1);
        
        if (in_array($currency_mode, [1, 2])) {
            if (setSystemParameter('trading_currency', $currency_mode)) {
                $currency_name = $currency_mode == 1 ? 'TL' : 'USD';
                $success = "Ana para birimi $currency_name olarak güncellendi!";
                logAdminActivity('update_currency', "Changed trading currency to mode: $currency_mode ($currency_name)");
            } else {
                $error = 'Para birimi güncellenirken hata oluştu!';
            }
        } else {
            $error = 'Geçersiz para birimi seçimi!';
        }
    }
    
    elseif ($action === 'update_fees') {
        // Update trading fees and limits
        $trading_fee = floatval($_POST['trading_fee'] ?? 0);
        $min_trade_amount = floatval($_POST['min_trade_amount'] ?? 0);
        
        if ($trading_fee >= 0 && $trading_fee <= 10 && $min_trade_amount > 0) {
            $success_count = 0;
            
            if (setSystemParameter('trading_fee', $trading_fee)) {
                $success_count++;
            }
            
            if (setSystemParameter('min_trade_amount', $min_trade_amount)) {
                $success_count++;
            }
            
            if ($success_count > 0) {
                $success = 'İşlem parametreleri başarıyla güncellendi!';
                logAdminActivity('update_fees', "Updated trading fee to $trading_fee% and min amount to $min_trade_amount");
            } else {
                $error = 'Parametreler güncellenirken hata oluştu!';
            }
        } else {
            $error = 'Geçersiz değerler! Komisyon 0-10% arası, minimum işlem tutarı pozitif olmalı.';
        }
    }
    
    elseif ($action === 'update_exchange_rate') {
        // Manual USD/TRY rate update
        $usd_try_rate = floatval($_POST['usd_try_rate'] ?? 0);
        
        if ($usd_try_rate > 0) {
            if (setSystemParameter('usdtry_rate', $usd_try_rate)) {
                setSystemParameter('rate_last_update', time());
                $success = "USD/TRY kuru $usd_try_rate olarak güncellendi!";
                logAdminActivity('update_exchange_rate', "Manually updated USD/TRY rate to $usd_try_rate");
            } else {
                $error = 'Döviz kuru güncellenirken hata oluştu!';
            }
        } else {
            $error = 'Geçersiz döviz kuru!';
        }
    }
    
    elseif ($action === 'maintenance_mode') {
        // Toggle maintenance mode
        $maintenance = intval($_POST['maintenance'] ?? 0);
        
        if (setSystemParameter('maintenance_mode', $maintenance)) {
            $mode_text = $maintenance ? 'aktif' : 'pasif';
            $success = "Bakım modu $mode_text edildi!";
            logAdminActivity('maintenance_mode', "Set maintenance mode to: $maintenance");
        } else {
            $error = 'Bakım modu güncellenirken hata oluştu!';
        }
    }
    
    elseif ($action === 'clear_cache') {
        // Clear system cache
        $tables_to_clear = ['markets'];
        $cleared = 0;
        
        foreach ($tables_to_clear as $table) {
            $query = "UPDATE $table SET updated_at = '2000-01-01 00:00:00'";
            if ($db->exec($query) !== false) {
                $cleared++;
            }
        }
        
        // Clear exchange rate cache
        setSystemParameter('rate_last_update', '0');
        
        if ($cleared > 0) {
            $success = 'Sistem önbelleği temizlendi!';
            logAdminActivity('clear_cache', "Cleared cache for $cleared tables");
        } else {
            $error = 'Önbellek temizlenirken hata oluştu!';
        }
    }
    
    elseif ($action === 'reset_all_prices') {
        // Reset all prices to default values
        if (isset($_POST['confirm_reset']) && $_POST['confirm_reset'] === 'RESET') {
            $categories = getFinancialCategories();
            $reset_count = 0;
            
            foreach ($categories as $category => $name) {
                $symbols = getCategorySymbols($category);
                
                foreach ($symbols as $symbol) {
                    $base_price = getBasePriceForSymbol($symbol, $category);
                    
                    $query = "UPDATE markets SET price = ?, change_24h = 0, updated_at = CURRENT_TIMESTAMP WHERE symbol = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$base_price, $symbol])) {
                        $reset_count++;
                    }
                }
            }
            
            if ($reset_count > 0) {
                $success = "$reset_count sembol fiyatı varsayılan değerlere sıfırlandı!";
                logAdminActivity('reset_all_prices', "Reset $reset_count symbol prices to default");
            } else {
                $error = 'Fiyatlar sıfırlanırken hata oluştu!';
            }
        } else {
            $error = 'Onay kodu yanlış! "RESET" yazmalısınız.';
        }
    }
}

// Get current settings
$trading_currency = getTradingCurrency();
$currency_symbol = getCurrencySymbol($trading_currency);
$trading_fee = getSystemParameter('trading_fee', '0.5');
$min_trade_amount = getSystemParameter('min_trade_amount', '50');
$usd_try_rate = getUSDTRYRate();
$maintenance_mode = getSystemParameter('maintenance_mode', '0');
$rate_last_update = getSystemParameter('rate_last_update', '0');

// Get system stats
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM markets";
$stmt = $db->prepare($query);
$stmt->execute();
$total_symbols = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$query = "SELECT COUNT(*) as total FROM transactions WHERE DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$today_transactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-cogs"></i> Sistem Ayarları</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-outline-info" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Yenile
                        </button>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- System Status -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Para Birimi
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $currency_symbol; ?> (Mod: <?php echo $trading_currency; ?>)
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Platform Durumu
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo $maintenance_mode ? 'Bakımda' : 'Aktif'; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-<?php echo $maintenance_mode ? 'tools' : 'check-circle'; ?> fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        USD/TRY Kuru
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatNumber($usd_try_rate, 4); ?>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo $rate_last_update > 0 ? date('H:i', $rate_last_update) : 'Güncellenmedi'; ?>
                                    </small>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Komisyon
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        %<?php echo formatNumber($trading_fee, 2); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-percentage fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Trading Currency Settings -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-money-bill"></i> Ana Para Birimi Ayarı
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                                <input type="hidden" name="action" value="update_currency">
                                
                                <div class="mb-3">
                                    <label class="form-label">Platformun Ana Para Birimi</label>
                                    <select name="currency_mode" class="form-select" onchange="updateCurrencyInfo(this.value)">
                                        <option value="1" <?php echo $trading_currency == 1 ? 'selected' : ''; ?>>
                                            1 - Türk Lirası (TL) Modu
                                        </option>
                                        <option value="2" <?php echo $trading_currency == 2 ? 'selected' : ''; ?>>
                                            2 - Amerikan Doları (USD) Modu
                                        </option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-info">
                                    <div id="currencyInfo">
                                        <?php if ($trading_currency == 1): ?>
                                            <strong>TL Modu:</strong> Tüm işlemler Türk Lirası cinsinden yapılır. USD fiyatları otomatik olarak TL'ye çevrilir.
                                        <?php else: ?>
                                            <strong>USD Modu:</strong> Tüm işlemler Amerikan Doları cinsinden yapılır. TL bakiyeler USD'ye çevrilir.
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save"></i> Para Birimini Güncelle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Trading Parameters -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-chart-line"></i> İşlem Parametreleri
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                                <input type="hidden" name="action" value="update_fees">
                                
                                <div class="mb-3">
                                    <label class="form-label">İşlem Komisyonu (%)</label>
                                    <input type="number" name="trading_fee" class="form-control" 
                                           value="<?php echo $trading_fee; ?>" step="0.01" min="0" max="10" required>
                                    <small class="form-text text-muted">0-10% arası değer girin</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Minimum İşlem Tutarı (<?php echo $currency_symbol; ?>)</label>
                                    <input type="number" name="min_trade_amount" class="form-control" 
                                           value="<?php echo $min_trade_amount; ?>" step="0.01" min="0" required>
                                    <small class="form-text text-muted">Kullanıcıların yapabileceği minimum işlem tutarı</small>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-save"></i> Parametreleri Güncelle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Exchange Rate Management -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-exchange-alt"></i> Döviz Kuru Yönetimi
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Mevcut USD/TRY Kuru</label>
                                <input type="text" class="form-control" value="<?php echo formatNumber($usd_try_rate, 4); ?>" readonly>
                                <small class="form-text text-muted">
                                    Son güncelleme: <?php echo $rate_last_update > 0 ? date('d.m.Y H:i', $rate_last_update) : 'Hiç güncellenmedi'; ?>
                                </small>
                            </div>
                            
                            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                                <input type="hidden" name="action" value="update_exchange_rate">
                                
                                <div class="mb-3">
                                    <label class="form-label">Yeni Kur (Manuel)</label>
                                    <input type="number" name="usd_try_rate" class="form-control" 
                                           step="0.0001" min="1" placeholder="<?php echo $usd_try_rate; ?>" required>
                                    <small class="form-text text-muted">Manuel olarak döviz kurunu güncelleyin</small>
                                </div>
                                
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-edit"></i> Kuru Manuel Güncelle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Maintenance -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-tools"></i> Sistem Bakımı
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                                <input type="hidden" name="action" value="maintenance_mode">
                                
                                <div class="mb-3">
                                    <label class="form-label">Bakım Modu</label>
                                    <select name="maintenance" class="form-select">
                                        <option value="0" <?php echo $maintenance_mode == 0 ? 'selected' : ''; ?>>
                                            Platform Aktif
                                        </option>
                                        <option value="1" <?php echo $maintenance_mode == 1 ? 'selected' : ''; ?>>
                                            Bakım Modu Aktif
                                        </option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-info w-100 mb-2">
                                    <i class="fas fa-tools"></i> Bakım Modu Güncelle
                                </button>
                            </form>
                            
                            <form method="POST" onsubmit="return confirm('Sistem önbelleğini temizlemek istediğinizden emin misiniz?') && showLoading(this.querySelector('[type=submit]'))">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-secondary w-100">
                                    <i class="fas fa-broom"></i> Önbelleği Temizle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dangerous Operations -->
            <div class="card shadow border-danger mb-4">
                <div class="card-header bg-danger text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-exclamation-triangle"></i> Tehlikeli İşlemler
                    </h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <strong>Uyarı:</strong> Bu işlemler geri alınamaz! Sadece emin olduğunuzda kullanın.
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('TÜM SEMBOL FİYATLARI VARSAYILAN DEĞERLERE SIFIRLANACAK! Bu işlem geri alınamaz!') && showLoading(this.querySelector('[type=submit]'))">
                        <input type="hidden" name="action" value="reset_all_prices">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label class="form-label">Onay için "RESET" yazın</label>
                                <input type="text" name="confirm_reset" class="form-control" placeholder="RESET" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-danger w-100">
                                    <i class="fas fa-undo"></i> Tüm Fiyatları Sıfırla
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- System Information -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-info-circle"></i> Sistem Bilgileri
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Toplam Kullanıcı:</strong><br>
                            <span class="text-muted"><?php echo number_format($total_users); ?> kullanıcı</span>
                        </div>
                        <div class="col-md-4">
                            <strong>Toplam Sembol:</strong><br>
                            <span class="text-muted"><?php echo number_format($total_symbols); ?> sembol</span>
                        </div>
                        <div class="col-md-4">
                            <strong>Bugünkü İşlemler:</strong><br>
                            <span class="text-muted"><?php echo number_format($today_transactions); ?> işlem</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <strong>PHP Sürümü:</strong><br>
                            <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                        </div>
                        <div class="col-md-6">
                            <strong>Sunucu Zamanı:</strong><br>
                            <span class="text-muted"><?php echo date('d.m.Y H:i:s'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
function updateCurrencyInfo(mode) {
    const infoDiv = document.getElementById('currencyInfo');
    
    if (mode == '1') {
        infoDiv.innerHTML = '<strong>TL Modu:</strong> Tüm işlemler Türk Lirası cinsinden yapılır. USD fiyatları otomatik olarak TL\'ye çevrilir.';
    } else {
        infoDiv.innerHTML = '<strong>USD Modu:</strong> Tüm işlemler Amerikan Doları cinsinden yapılır. TL bakiyeler USD\'ye çevrilir.';
    }
}
</script>

<?php include 'includes/admin_footer.php'; ?>
