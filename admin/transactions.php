<?php
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'İşlem Yönetimi - GlobalBorsa Admin';
$success = '';
$error = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'cancel_transaction') {
        $transaction_id = intval($_POST['transaction_id'] ?? 0);
        
        if ($transaction_id > 0) {
            // Get transaction details
            $query = "SELECT * FROM transactions WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$transaction_id]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction && $transaction['status'] === 'pending') {
                // Cancel transaction and refund
                $db->beginTransaction();
                try {
                    // Update transaction status
                    $query = "UPDATE transactions SET status = 'cancelled' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$transaction_id]);
                    
                    // Refund user balance
                    if ($transaction['type'] === 'buy') {
                        $currency_field = getCurrencyField();
                        updateUserBalance($transaction['user_id'], $currency_field, $transaction['total'], 'add');
                    }
                    
                    $db->commit();
                    $success = 'İşlem iptal edildi ve bakiye iade edildi!';
                    logAdminActivity('cancel_transaction', "Cancelled transaction ID: $transaction_id");
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'İşlem iptal edilirken hata oluştu!';
                }
            } else {
                $error = 'İşlem bulunamadı veya iptal edilebilir durumda değil!';
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$user_search = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if (!empty($user_search)) {
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%$user_search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get transactions with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Count total transactions
$count_query = "SELECT COUNT(*) as total 
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_transactions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_transactions / $per_page);

// Get transactions
$query = "SELECT t.*, u.username, m.name as symbol_name 
          FROM transactions t 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN markets m ON t.symbol = m.symbol 
          $where_clause 
          ORDER BY t.created_at DESC 
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_count,
                SUM(CASE WHEN type = 'buy' THEN total ELSE 0 END) as total_buy_volume,
                SUM(CASE WHEN type = 'sell' THEN total ELSE 0 END) as total_sell_volume,
                SUM(fee) as total_fees,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                FROM transactions t 
                LEFT JOIN users u ON t.user_id = u.id 
                $where_clause";
$stmt = $db->prepare($stats_query);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-exchange-alt"></i> İşlem Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-primary fs-6"><?php echo number_format($total_transactions); ?> işlem</span>
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

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Toplam İşlem
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_count']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Alım Hacmi
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatNumber($stats['total_buy_volume']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Satım Hacmi
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatNumber($stats['total_sell_volume']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Komisyon
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatNumber($stats['total_fees']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Bekleyen
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending_count']); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter"></i> Filtreler
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Durum</label>
                            <select name="status" class="form-select">
                                <option value="">Tümü</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Bekliyor</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">İşlem Tipi</label>
                            <select name="type" class="form-select">
                                <option value="">Tümü</option>
                                <option value="buy" <?php echo $type_filter === 'buy' ? 'selected' : ''; ?>>Alım</option>
                                <option value="sell" <?php echo $type_filter === 'sell' ? 'selected' : ''; ?>>Satım</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Kullanıcı</label>
                            <input type="text" name="user" class="form-control" placeholder="Kullanıcı ara..." value="<?php echo htmlspecialchars($user_search); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Başlangıç</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Bitiş</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="transactions.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> İşlem Listesi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tarih</th>
                                    <th>Kullanıcı</th>
                                    <th>Tip</th>
                                    <th>Sembol</th>
                                    <th>Miktar</th>
                                    <th>Fiyat</th>
                                    <th>Toplam</th>
                                    <th>Komisyon</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($transactions)): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['id']; ?></td>
                                        <td>
                                            <small><?php echo date('d.m.Y H:i', strtotime($transaction['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="users.php?search=<?php echo urlencode($transaction['username']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($transaction['username']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $transaction['type'] === 'buy' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo strtoupper($transaction['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($transaction['symbol']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($transaction['symbol_name'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td><?php echo formatNumber($transaction['amount'], 6); ?></td>
                                        <td>$<?php echo formatPrice($transaction['price']); ?></td>
                                        <td><?php echo formatNumber($transaction['total']); ?></td>
                                        <td><?php echo formatNumber($transaction['fee']); ?></td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $transaction['status'] === 'completed' ? 'bg-success' : 
                                                          ($transaction['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-secondary'); ?>">
                                                <?php 
                                                switch($transaction['status']) {
                                                    case 'completed': echo 'Tamamlandı'; break;
                                                    case 'pending': echo 'Bekliyor'; break;
                                                    case 'cancelled': echo 'İptal'; break;
                                                    default: echo $transaction['status'];
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($transaction['status'] === 'pending'): ?>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="cancelTransaction(<?php echo $transaction['id']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">
                                            İşlem bulunamadı
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="İşlem listesi sayfalama">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Önceki</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Sonraki</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Hidden Form for Cancellation -->
<form id="cancelForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="cancel_transaction">
    <input type="hidden" name="transaction_id" id="cancelTransactionId">
</form>

<script>
function cancelTransaction(transactionId) {
    if (confirm('Bu işlemi iptal etmek istediğinizden emin misiniz?\n\nKullanıcının bakiyesi iade edilecektir.')) {
        document.getElementById('cancelTransactionId').value = transactionId;
        document.getElementById('cancelForm').submit();
    }
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    initSearch('searchInput', 'transactionsTable');
});
</script>

<?php include 'includes/admin_footer.php'; ?>
