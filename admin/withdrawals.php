<?php
require_once '../includes/functions.php';
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'Para Çekme Yönetimi - GlobalBorsa Admin';
$success = '';
$error = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_withdrawal') {
        $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
        $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
        
        if ($withdrawal_id > 0) {
            // Get withdrawal details
            $query = "SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'";
            $stmt = $db->prepare($query);
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($withdrawal) {
                // Check if user has sufficient balance
                $user_balance = getUserBalance($withdrawal['user_id'], 'tl');
                
                if ($user_balance >= $withdrawal['amount']) {
                    $db->beginTransaction();
                    try {
                        // Update withdrawal status
                        $query = "UPDATE withdrawals SET status = 'approved', admin_note = ?, processed_at = NOW() WHERE id = ?";
                        $stmt = $db->prepare($query);
                        $stmt->execute([$admin_note, $withdrawal_id]);
                        
                        // Deduct balance from user (money already reserved during request)
                        // No need to deduct again if properly implemented in withdrawal request
                        
                        $db->commit();
                        $success = 'Para çekme onaylandı! Kullanıcıya ödeme yapılabilir.';
                        logAdminActivity('approve_withdrawal', "Approved withdrawal ID: $withdrawal_id, Amount: {$withdrawal['amount']} TL");
                    } catch (Exception $e) {
                        $db->rollback();
                        $error = 'Para çekme onaylanırken hata oluştu!';
                    }
                } else {
                    $error = 'Kullanıcının yeterli bakiyesi yok!';
                }
            } else {
                $error = 'Para çekme talebi bulunamadı veya zaten işlenmiş!';
            }
        }
    }
    
    elseif ($action === 'reject_withdrawal') {
        $withdrawal_id = intval($_POST['withdrawal_id'] ?? 0);
        $admin_note = sanitizeInput($_POST['admin_note'] ?? '');
        
        if ($withdrawal_id > 0 && !empty($admin_note)) {
            // Get withdrawal details
            $query = "SELECT * FROM withdrawals WHERE id = ? AND status = 'pending'";
            $stmt = $db->prepare($query);
            $stmt->execute([$withdrawal_id]);
            $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($withdrawal) {
                $db->beginTransaction();
                try {
                    // Update withdrawal status
                    $query = "UPDATE withdrawals SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$admin_note, $withdrawal_id]);
                    
                    // Refund the reserved amount back to user
                    updateUserBalance($withdrawal['user_id'], 'tl', $withdrawal['amount'], 'add');
                    
                    $db->commit();
                    $success = 'Para çekme talebi reddedildi ve tutar kullanıcıya iade edildi!';
                    logAdminActivity('reject_withdrawal', "Rejected withdrawal ID: $withdrawal_id, Reason: $admin_note");
                } catch (Exception $e) {
                    $db->rollback();
                    $error = 'Para çekme reddedilirken hata oluştu!';
                }
            } else {
                $error = 'Para çekme talebi bulunamadı!';
            }
        } else {
            $error = 'Red sebebi belirtilmelidir!';
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'pending';
$method_filter = $_GET['method'] ?? '';
$user_search = $_GET['user'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($status_filter)) {
    $where_conditions[] = "w.status = ?";
    $params[] = $status_filter;
}

if (!empty($method_filter)) {
    $where_conditions[] = "w.method = ?";
    $params[] = $method_filter;
}

if (!empty($user_search)) {
    $where_conditions[] = "u.username LIKE ?";
    $params[] = "%$user_search%";
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(w.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(w.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get withdrawals with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total withdrawals
$count_query = "SELECT COUNT(*) as total 
                FROM withdrawals w 
                LEFT JOIN users u ON w.user_id = u.id 
                $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_withdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_withdrawals / $per_page);

// Get withdrawals
$query = "SELECT w.*, u.username, u.email 
          FROM withdrawals w 
          LEFT JOIN users u ON w.user_id = u.id 
          $where_clause 
          ORDER BY w.created_at DESC 
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
                COUNT(*) as total_count,
                SUM(amount) as total_amount,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count
                FROM withdrawals w 
                LEFT JOIN users u ON w.user_id = u.id 
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
                <h1 class="h2"><i class="fas fa-arrow-up"></i> Para Çekme Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-primary fs-6"><?php echo number_format($total_withdrawals); ?> talep</span>
                        <?php if ($stats['pending_count'] > 0): ?>
                            <span class="badge bg-warning text-dark fs-6"><?php echo $stats['pending_count']; ?> bekliyor</span>
                        <?php endif; ?>
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
                                Toplam Talep
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['total_count']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Toplam Tutar
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo formatNumber($stats['total_amount']); ?> TL
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Bekleyen
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['pending_count']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Onaylanan
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['approved_count']); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-2 col-md-4 mb-4">
                    <div class="card border-left-secondary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                Reddedilen
                            </div>
                            <div class="h6 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['rejected_count']); ?>
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
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                                <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Onaylandı</option>
                                <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Yöntem</label>
                            <select name="method" class="form-select">
                                <option value="">Tümü</option>
                                <option value="iban" <?php echo $method_filter === 'iban' ? 'selected' : ''; ?>>IBAN</option>
                                <option value="papara" <?php echo $method_filter === 'papara' ? 'selected' : ''; ?>>Papara</option>
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
                                <a href="withdrawals.php" class="btn btn-secondary btn-sm">
                                    <i class="fas fa-times"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Withdrawals Table -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Para Çekme Talepleri
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="withdrawalsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tarih</th>
                                    <th>Kullanıcı</th>
                                    <th>Tutar</th>
                                    <th>Yöntem</th>
                                    <th>Hesap Bilgisi</th>
                                    <th>Durum</th>
                                    <th>Admin Notu</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($withdrawals)): ?>
                                    <?php foreach ($withdrawals as $withdrawal): ?>
                                    <tr class="<?php echo $withdrawal['status'] === 'pending' ? 'table-warning' : ''; ?>">
                                        <td><?php echo $withdrawal['id']; ?></td>
                                        <td>
                                            <small><?php echo date('d.m.Y H:i', strtotime($withdrawal['created_at'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="users.php?search=<?php echo urlencode($withdrawal['username']); ?>" class="text-decoration-none">
                                                <strong><?php echo htmlspecialchars($withdrawal['username']); ?></strong>
                                            </a>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($withdrawal['email']); ?></small>
                                        </td>
                                        <td>
                                            <strong class="text-danger"><?php echo formatNumber($withdrawal['amount']); ?> TL</strong>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $withdrawal['method'] === 'iban' ? 'bg-info' : 'bg-primary'; ?>">
                                                <?php echo strtoupper($withdrawal['method']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($withdrawal['method'] === 'iban' && $withdrawal['iban_info']): ?>
                                                <small><?php echo htmlspecialchars($withdrawal['iban_info']); ?></small>
                                            <?php elseif ($withdrawal['method'] === 'papara' && $withdrawal['papara_info']): ?>
                                                <small><?php echo htmlspecialchars($withdrawal['papara_info']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge 
                                                <?php echo $withdrawal['status'] === 'approved' ? 'bg-success' : 
                                                          ($withdrawal['status'] === 'pending' ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                                                <?php 
                                                switch($withdrawal['status']) {
                                                    case 'approved': echo 'Onaylandı'; break;
                                                    case 'pending': echo 'Bekliyor'; break;
                                                    case 'rejected': echo 'Reddedildi'; break;
                                                    default: echo $withdrawal['status'];
                                                }
                                                ?>
                                            </span>
                                            <?php if ($withdrawal['processed_at']): ?>
                                                <br><small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($withdrawal['processed_at'])); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($withdrawal['admin_note']): ?>
                                                <small><?php echo htmlspecialchars($withdrawal['admin_note']); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($withdrawal['status'] === 'pending'): ?>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-success" 
                                                            onclick="approveWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="rejectWithdrawal(<?php echo $withdrawal['id']; ?>)">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            Para çekme talebi bulunamadı
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Para çekme listesi sayfalama">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Önceki</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo $status_filter; ?>&method=<?php echo $method_filter; ?>&user=<?php echo urlencode($user_search); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">Sonraki</a>
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

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check"></i> Para Çekme Onaylama
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="approve_withdrawal">
                    <input type="hidden" name="withdrawal_id" id="approveWithdrawalId">
                    
                    <div class="alert alert-success">
                        Bu para çekme talebini onaylamak istediğinizden emin misiniz?
                        <strong>Kullanıcıya ödeme yapmanız gerekecektir!</strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Admin Notu (Opsiyonel)</label>
                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Ödeme detayları, referans numarası vs..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check"></i> Onayla ve Ödeme Yap
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-times"></i> Para Çekme Reddetme
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reject_withdrawal">
                    <input type="hidden" name="withdrawal_id" id="rejectWithdrawalId">
                    
                    <div class="alert alert-danger">
                        Bu para çekme talebini reddetmek istediğinizden emin misiniz?
                        <strong>Tutar kullanıcıya iade edilecektir.</strong>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Red Sebebi *</label>
                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Reddetme sebebini açıklayın..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times"></i> Reddet ve İade Et
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function approveWithdrawal(withdrawalId) {
    document.getElementById('approveWithdrawalId').value = withdrawalId;
    const modal = new bootstrap.Modal(document.getElementById('approveModal'));
    modal.show();
}

function rejectWithdrawal(withdrawalId) {
    document.getElementById('rejectWithdrawalId').value = withdrawalId;
    const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    initSearch('searchInput', 'withdrawalsTable');
});
</script>

<?php include 'includes/admin_footer.php'; ?>
