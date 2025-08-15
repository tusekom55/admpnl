<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
                    <i class="fas fa-users"></i>
                    Kullanıcı Yönetimi
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transactions.php' ? 'active' : ''; ?>" href="transactions.php">
                    <i class="fas fa-exchange-alt"></i>
                    İşlem Yönetimi
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'deposits.php' ? 'active' : ''; ?>" href="deposits.php">
                    <i class="fas fa-arrow-down"></i>
                    Para Yatırma
                    <?php
                    // Show pending deposits count
                    $database = new Database();
                    $db = $database->getConnection();
                    $query = "SELECT COUNT(*) as total FROM deposits WHERE status = 'pending'";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $pending_deposits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    if ($pending_deposits > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $pending_deposits; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'withdrawals.php' ? 'active' : ''; ?>" href="withdrawals.php">
                    <i class="fas fa-arrow-up"></i>
                    Para Çekme
                    <?php
                    // Show pending withdrawals count
                    $query = "SELECT COUNT(*) as total FROM withdrawals WHERE status = 'pending'";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    if ($pending_withdrawals > 0): ?>
                        <span class="badge bg-danger ms-2"><?php echo $pending_withdrawals; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'symbols.php' ? 'active' : ''; ?>" href="symbols.php">
                    <i class="fas fa-coins"></i>
                    Sembol Yönetimi
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="fas fa-cogs"></i>
                    Sistem Ayarları
                </a>
            </li>
        </ul>
        
        <hr class="my-3">
        
        <!-- Quick Stats -->
        <div class="px-3">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Hızlı Bilgiler</span>
            </h6>
            
            <?php
            // Get quick stats
            $query = "SELECT COUNT(*) as total FROM users";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $query = "SELECT COUNT(*) as total FROM markets";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $total_symbols = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            $trading_currency = getTradingCurrency();
            $currency_symbol = getCurrencySymbol($trading_currency);
            ?>
            
            <div class="small text-muted mb-2">
                <i class="fas fa-users text-primary"></i>
                <strong><?php echo number_format($total_users); ?></strong> Kullanıcı
            </div>
            
            <div class="small text-muted mb-2">
                <i class="fas fa-coins text-success"></i>
                <strong><?php echo number_format($total_symbols); ?></strong> Sembol
            </div>
            
            <div class="small text-muted mb-2">
                <i class="fas fa-money-bill text-info"></i>
                Para Birimi: <strong><?php echo $currency_symbol; ?></strong>
            </div>
            
            <div class="small text-success mb-2">
                <i class="fas fa-circle"></i>
                Platform <strong>Aktif</strong>
            </div>
        </div>
        
        <hr class="my-3">
        
        <!-- Quick Actions -->
        <div class="px-3">
            <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
                <span>Hızlı İşlemler</span>
            </h6>
            
            <a href="symbols.php?action=add" class="btn btn-outline-primary btn-sm w-100 mb-2">
                <i class="fas fa-plus"></i> Yeni Sembol
            </a>
            
            <a href="users.php?action=add" class="btn btn-outline-success btn-sm w-100 mb-2">
                <i class="fas fa-user-plus"></i> Yeni Kullanıcı
            </a>
            
            <a href="../admin-data-manager.php" class="btn btn-outline-info btn-sm w-100 mb-2" target="_blank">
                <i class="fas fa-database"></i> Eski Panel
            </a>
        </div>
    </div>
</nav>
