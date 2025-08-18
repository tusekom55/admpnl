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
    
    if ($action === 'approve') {
        $deposit_id = intval($_POST['deposit_id'] ?? 0);
        
        if ($deposit_id > 0) {
            // Deposit bilgilerini al
            $query = "SELECT * FROM deposits WHERE id = ? AND status = 'pending'";
            $stmt = $db->prepare($query);
            $stmt->execute([$deposit_id]);
            $deposit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($deposit) {
                // Deposit onaylama ve bakiye ekleme
                $db->beginTransaction();
                try {
                    // Deposit durumunu güncelle
                    $query = "UPDATE deposits SET status = 'approved', processed_at = NOW() WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$deposit_id]);
                    
                    // Kullanıcı bakiyesine ekle
                    $query = "UPDATE users SET balance_tl = balance_tl + ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$deposit['amount'], $deposit['user_id']]);
                    
                    $db->commit();
                    $success = "Para yatırma onaylandı ve kullanıcı bakiyesi güncellendi!";
                } catch (Exception $e) {
                    $db->rollback();
                    $error = "İşlem sırasında hata oluştu!";
                }
            }
        }
    }
    
    if ($action === 'reject') {
        $deposit_id = intval($_POST['deposit_id'] ?? 0);
        
        if ($deposit_id > 0) {
            $query = "UPDATE deposits SET status = 'rejected', processed_at = NOW() WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$deposit_id])) {
                $success = "Para yatırma talebi reddedildi!";
            } else {
                $error = "İşlem sırasında hata oluştu!";
            }
        }
    }
}

// Bekleyen depositleri getir
$query = "SELECT d.*, u.username, u.email 
          FROM deposits d 
          LEFT JOIN users u ON d.user_id = u.id 
          WHERE d.status = 'pending' 
          ORDER BY d.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$pending_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Son işlemleri getir
$query = "SELECT d.*, u.username, u.email 
          FROM deposits d 
          LEFT JOIN users u ON d.user_id = u.id 
          WHERE d.status IN ('approved', 'rejected') 
          ORDER BY d.processed_at DESC 
          LIMIT 20";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Para Yatırma Onayları - Admin Panel</title>
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
        <h1><i class="fas fa-money-bill"></i> Para Yatırma Onayları</h1>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Bekleyen Onaylar -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5><i class="fas fa-clock"></i> Bekleyen Onaylar (<?php echo count($pending_deposits); ?> talep)</h5>
            </div>
            <div class="card-body">
                <?php if (count($pending_deposits) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kullanıcı</th>
                                    <th>Tutar</th>
                                    <th>Yöntem</th>
                                    <th>Referans</th>
                                    <th>Tarih</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_deposits as $deposit): ?>
                                <tr>
                                    <td><?php echo $deposit['id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($deposit['username']); ?></strong><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($deposit['email']); ?></small>
                                    </td>
                                    <td>
                                        <strong class="text-success"><?php echo number_format($deposit['amount'], 2); ?> TL</strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo strtoupper($deposit['method']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo $deposit['reference'] ? htmlspecialchars($deposit['reference']) : '-'; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d.m.Y H:i', strtotime($deposit['created_at'])); ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="deposit_id" value="<?php echo $deposit['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" 
                                                    onclick="return confirm('Bu para yatırma talebini onaylamak istediğinizden emin misiniz?')">
                                                <i class="fas fa-check"></i> Onayla
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="deposit_id" value="<?php echo $deposit['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" 
                                                    onclick="return confirm('Bu para yatırma talebini reddetmek istediğinizden emin misiniz?')">
                                                <i class="fas fa-times"></i> Reddet
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Bekleyen para yatırma talebi bulunmuyor.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Son İşlemler -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Son İşlemler</h5>
            </div>
            <div class="card-body">
                <?php if (count($recent_deposits) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Kullanıcı</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>İşlem Tarihi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_deposits as $deposit): ?>
                                <tr>
                                    <td><?php echo $deposit['id']; ?></td>
                                    <td><?php echo htmlspecialchars($deposit['username']); ?></td>
                                    <td><?php echo number_format($deposit['amount'], 2); ?> TL</td>
                                    <td>
                                        <?php if ($deposit['status'] === 'approved'): ?>
                                            <span class="badge bg-success">Onaylandı</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Reddedildi</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($deposit['processed_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        Henüz işlenmiş talep bulunmuyor.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
