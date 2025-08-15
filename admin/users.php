<?php
require_once '../includes/functions.php';
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'Kullanıcı Yönetimi - GlobalBorsa Admin';
$success = '';
$error = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_balance') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $currency = sanitizeInput($_POST['currency'] ?? '');
        $amount = floatval($_POST['amount'] ?? 0);
        $operation = sanitizeInput($_POST['operation'] ?? 'add');
        
        if ($user_id > 0 && in_array($currency, ['tl', 'usd']) && $amount > 0) {
            if (updateUserBalance($user_id, $currency, $amount, $operation)) {
                $op_text = $operation === 'add' ? 'eklendi' : 'çıkarıldı';
                $success = "$amount " . strtoupper($currency) . " bakiyesi $op_text!";
                logAdminActivity('update_balance', "Updated user $user_id balance: $operation $amount $currency");
            } else {
                $error = 'Bakiye güncellenirken hata oluştu!';
            }
        } else {
            $error = 'Geçersiz parametreler!';
        }
    }
    
    elseif ($action === 'update_status') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $status = intval($_POST['status'] ?? 1);
        
        if ($user_id > 0) {
            $query = "UPDATE users SET is_active = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$status, $user_id])) {
                $status_text = $status ? 'aktif' : 'pasif';
                $success = "Kullanıcı durumu $status_text yapıldı!";
                logAdminActivity('update_status', "Set user $user_id status to: $status");
            } else {
                $error = 'Durum güncellenirken hata oluştu!';
            }
        }
    }
    
    elseif ($action === 'make_admin') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $is_admin = intval($_POST['is_admin'] ?? 0);
        
        if ($user_id > 0) {
            $query = "UPDATE users SET is_admin = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$is_admin, $user_id])) {
                $admin_text = $is_admin ? 'admin yapıldı' : 'admin yetkisi kaldırıldı';
                $success = "Kullanıcı $admin_text!";
                logAdminActivity('make_admin', "Set user $user_id admin status to: $is_admin");
            } else {
                $error = 'Admin yetkisi güncellenirken hata oluştu!';
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = intval($status_filter);
}

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with pagination
$page = intval($_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Count total users
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_users = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$query = "SELECT id, username, email, balance_tl, balance_usd, is_admin, is_active, created_at, 
          (SELECT COUNT(*) FROM transactions WHERE user_id = users.id) as transaction_count
          FROM users $where_clause 
          ORDER BY created_at DESC 
          LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-users"></i> Kullanıcı Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <span class="badge bg-primary fs-6"><?php echo number_format($total_users); ?> kullanıcı</span>
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

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-filter"></i> Filtreler
                    </h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Durum</label>
                            <select name="status" class="form-select">
                                <option value="">Tüm Durumlar</option>
                                <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Pasif</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Arama</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Kullanıcı adı veya email ara..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2 d-md-block">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="users.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Kullanıcı Listesi
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kullanıcı Adı</th>
                                    <th>Email</th>
                                    <th>TL Bakiye</th>
                                    <th>USD Bakiye</th>
                                    <th>İşlem Sayısı</th>
                                    <th>Durum</th>
                                    <th>Kayıt Tarihi</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                            <?php if ($user['is_admin']): ?>
                                                <span class="badge bg-danger ms-1">Admin</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo formatNumber($user['balance_tl']); ?> TL</td>
                                        <td><?php echo formatNumber($user['balance_usd']); ?> USD</td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $user['transaction_count']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $user['is_active'] ? 'Aktif' : 'Pasif'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="editBalance(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                    <i class="fas fa-wallet"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-<?php echo $user['is_active'] ? 'warning' : 'info'; ?>" 
                                                        onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                                    <i class="fas fa-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-<?php echo $user['is_admin'] ? 'danger' : 'primary'; ?>" 
                                                        onclick="toggleAdmin(<?php echo $user['id']; ?>, <?php echo $user['is_admin'] ? 0 : 1; ?>)">
                                                    <i class="fas fa-<?php echo $user['is_admin'] ? 'user-times' : 'user-shield'; ?>"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            Kullanıcı bulunamadı
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Kullanıcı listesi sayfalama">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page - 1); ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Önceki</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo ($page + 1); ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>">Sonraki</a>
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

<!-- Balance Edit Modal -->
<div class="modal fade" id="balanceEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-wallet"></i> Bakiye Düzenle: <span id="balanceEditUser"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_balance">
                    <input type="hidden" name="user_id" id="balanceEditId">
                    
                    <div class="mb-3">
                        <label class="form-label">Para Birimi</label>
                        <select name="currency" class="form-select" required>
                            <option value="tl">Türk Lirası (TL)</option>
                            <option value="usd">Amerikan Doları (USD)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Miktar</label>
                        <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">İşlem</label>
                        <select name="operation" class="form-select" required>
                            <option value="add">Bakiye Ekle</option>
                            <option value="subtract">Bakiye Çıkar</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Bakiye Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Forms for Quick Actions -->
<form id="statusForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="update_status">
    <input type="hidden" name="user_id" id="statusUserId">
    <input type="hidden" name="status" id="statusValue">
</form>

<form id="adminForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="make_admin">
    <input type="hidden" name="user_id" id="adminUserId">
    <input type="hidden" name="is_admin" id="adminValue">
</form>

<script>
// Edit balance function
function editBalance(id, username) {
    document.getElementById('balanceEditId').value = id;
    document.getElementById('balanceEditUser').textContent = username;
    
    const modal = new bootstrap.Modal(document.getElementById('balanceEditModal'));
    modal.show();
}

// Toggle user status
function toggleStatus(id, newStatus) {
    const statusText = newStatus ? 'aktif' : 'pasif';
    
    if (confirm(`Kullanıcı durumunu ${statusText} yapmak istediğinizden emin misiniz?`)) {
        document.getElementById('statusUserId').value = id;
        document.getElementById('statusValue').value = newStatus;
        document.getElementById('statusForm').submit();
    }
}

// Toggle admin status
function toggleAdmin(id, newStatus) {
    const adminText = newStatus ? 'admin yapmak' : 'admin yetkisini kaldırmak';
    
    if (confirm(`Bu kullanıcıyı ${adminText} istediğinizden emin misiniz?`)) {
        document.getElementById('adminUserId').value = id;
        document.getElementById('adminValue').value = newStatus;
        document.getElementById('adminForm').submit();
    }
}

// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    initSearch('searchInput', 'usersTable');
});
</script>

<?php include 'includes/admin_footer.php'; ?>
