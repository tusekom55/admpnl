<?php
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'Admin Dashboard - GlobalBorsa';

// Get dashboard statistics
$database = new Database();
$db = $database->getConnection();

// Total users
$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total transactions today
$query = "SELECT COUNT(*) as total, SUM(total) as volume FROM transactions WHERE DATE(created_at) = CURDATE()";
$stmt = $db->prepare($query);
$stmt->execute();
$today_stats = $stmt->fetch(PDO::FETCH_ASSOC);
$today_transactions = $today_stats['total'] ?? 0;
$today_volume = $today_stats['volume'] ?? 0;

// Pending deposits
$query = "SELECT COUNT(*) as total FROM deposits WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_deposits = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pending withdrawals
$query = "SELECT COUNT(*) as total FROM withdrawals WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Active symbols
$query = "SELECT COUNT(*) as total FROM markets";
$stmt = $db->prepare($query);
$stmt->execute();
$total_symbols = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Recent activities
$query = "SELECT t.*, u.username, m.name as symbol_name 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN markets m ON t.symbol = m.symbol 
          ORDER BY t.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Trading currency setting
$trading_currency = getTradingCurrency();
$currency_symbol = getCurrencySymbol($trading_currency);

include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="location.reload()">
                            <i class="fas fa-sync"></i> Yenile
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Kullanıcı
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($total_users); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Bugünkü İşlemler
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($today_transactions); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Bugünkü Hacim
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo formatNumber($today_volume); ?> <?php echo $currency_symbol; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Bekleyen Onaylar
                                    </div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo ($pending_deposits + $pending_withdrawals); ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-exclamation-triangle"></i> Bekleyen Onaylar
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="text-center">
                                        <p class="mb-2">Para Yatırma</p>
                                        <h4 class="text-warning"><?php echo $pending_deposits; ?></h4>
                                        <a href="deposits.php?status=pending" class="btn btn-warning btn-sm">
                                            <i class="fas fa-eye"></i> Görüntüle
                                        </a>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="text-center">
                                        <p class="mb-2">Para Çekme</p>
                                        <h4 class="text-danger"><?php echo $pending_withdrawals; ?></h4>
                                        <a href="withdrawals.php?status=pending" class="btn btn-danger btn-sm">
                                            <i class="fas fa-eye"></i> Görüntüle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-cogs"></i> Sistem Durumu
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-sm-4">
                                    <p class="mb-1">Para Birimi</p>
                                    <h5 class="text-success"><?php echo $currency_symbol; ?></h5>
                                    <small class="text-muted">Mod: <?php echo $trading_currency; ?></small>
                                </div>
                                <div class="col-sm-4">
                                    <p class="mb-1">Aktif Sembol</p>
                                    <h5 class="text-info"><?php echo $total_symbols; ?></h5>
                                    <a href="symbols.php" class="btn btn-info btn-sm">Yönet</a>
                                </div>
                                <div class="col-sm-4">
                                    <p class="mb-1">Platform</p>
                                    <h5 class="text-success">Aktif</h5>
                                    <a href="settings.php" class="btn btn-secondary btn-sm">Ayarlar</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Son Aktiviteler
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>Kullanıcı</th>
                                    <th>İşlem</th>
                                    <th>Sembol</th>
                                    <th>Miktar</th>
                                    <th>Toplam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td><?php echo date('d.m.Y H:i', strtotime($activity['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($activity['username']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $activity['type'] == 'buy' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo strtoupper($activity['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($activity['symbol_name'] ?: $activity['symbol']); ?></td>
                                        <td><?php echo formatNumber($activity['amount'], 4); ?></td>
                                        <td><?php echo formatNumber($activity['total']); ?> <?php echo $currency_symbol; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Henüz aktivite bulunmuyor</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="transactions.php" class="btn btn-primary">
                            <i class="fas fa-list"></i> Tüm İşlemleri Görüntüle
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<style>
.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}

.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}

.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}

.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}

.text-xs {
    font-size: 0.7rem;
}

.shadow {
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15) !important;
}
</style>

<?php include 'includes/admin_footer.php'; ?>
