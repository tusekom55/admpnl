<?php
session_start();
require_once 'config/database.php';

// Basit admin kontrolü
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Form işlemleri
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_balance') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $currency = $_POST['currency'] ?? 'tl';
        
        if ($user_id > 0 && $amount > 0) {
            $query = "UPDATE users SET balance_$currency = balance_$currency + ? WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$amount, $user_id])) {
                $success = "Bakiye başarıyla güncellendi!";
            } else {
                $error = "Bakiye güncellenirken hata oluştu!";
            }
        }
    }
}

// Kullanıcıları getir
$query = "SELECT id, username, email, balance_tl, balance_usd, is_admin, created_at FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="admin.php">
                <i class="fas fa-shield-alt"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a href="admin.php" class="btn btn-outline-light btn-sm me-2">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt"></i> Çıkış
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1><i class="fas fa-users"></i> Kullanıcı Yönetimi</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5>Kullanıcı Listesi (<?php echo count($users); ?> kullanıcı)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Kullanıcı Adı</th>
                                <th>Email</th>
                                <th>TL Bakiye</th>
                                <th>USD Bakiye</th>
                                <th>Admin</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo number_format($user['balance_tl'], 2); ?> TL</td>
                                <td><?php echo number_format($user['balance_usd'], 2); ?> USD</td>
                                <td>
                                    <?php if ($user['is_admin']): ?>
                                        <span class="badge bg-danger">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Kullanıcı</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick="showBalanceModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-wallet"></i> Bakiye
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Bakiye Güncelleme Modal -->
    <div class="modal fade" id="balanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bakiye Güncelle: <span id="modalUsername"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_balance">
                        <input type="hidden" name="user_id" id="modalUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">Para Birimi</label>
                            <select name="currency" class="form-select" required>
                                <option value="tl">Türk Lirası (TL)</option>
                                <option value="usd">Amerikan Doları (USD)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Eklenecek Miktar</label>
                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                            <small class="form-text text-muted">Pozitif değer bakiyeye eklenir</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Bakiye Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showBalanceModal(userId, username) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUsername').textContent = username;
            
            const modal = new bootstrap.Modal(document.getElementById('balanceModal'));
            modal.show();
        }
    </script>
</body>
</html>
