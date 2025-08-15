<?php
require_once 'includes/admin_auth.php';

requireAdmin();

$page_title = 'Sembol Yönetimi - GlobalBorsa Admin';
$success = '';
$error = '';

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_symbol') {
        // Add new symbol
        $symbol = strtoupper(sanitizeInput($_POST['symbol'] ?? ''));
        $name = sanitizeInput($_POST['name'] ?? '');
        $category = sanitizeInput($_POST['category'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $logo_url = sanitizeInput($_POST['logo_url'] ?? '');
        
        if (empty($symbol) || empty($name) || empty($category) || $price <= 0) {
            $error = 'Tüm alanları doldurun ve geçerli bir fiyat girin!';
        } else {
            // Check if symbol already exists
            $query = "SELECT id FROM markets WHERE symbol = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$symbol]);
            
            if ($stmt->fetch()) {
                $error = 'Bu sembol zaten mevcut!';
            } else {
                // Insert new symbol
                $query = "INSERT INTO markets (symbol, name, price, change_24h, volume_24h, high_24h, low_24h, market_cap, category, logo_url) 
                          VALUES (?, ?, ?, 0, 0, ?, ?, 0, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$symbol, $name, $price, $price, $price, $category, $logo_url])) {
                    $success = 'Yeni sembol başarıyla eklendi!';
                    logAdminActivity('add_symbol', "Added symbol: $symbol ($name)");
                } else {
                    $error = 'Sembol eklenirken hata oluştu!';
                }
            }
        }
    }
    
    elseif ($action === 'update_price') {
        // Update symbol price
        $symbol_id = intval($_POST['symbol_id'] ?? 0);
        $new_price = floatval($_POST['new_price'] ?? 0);
        
        if ($symbol_id > 0 && $new_price > 0) {
            // Get current price for change calculation
            $query = "SELECT symbol, price FROM markets WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$symbol_id]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($current) {
                $old_price = $current['price'];
                $change_percent = $old_price > 0 ? (($new_price - $old_price) / $old_price) * 100 : 0;
                
                // Update price and related fields
                $query = "UPDATE markets SET 
                          price = ?, 
                          change_24h = ?,
                          high_24h = GREATEST(high_24h, ?),
                          low_24h = LEAST(low_24h, ?),
                          updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$new_price, $change_percent, $new_price, $new_price, $symbol_id])) {
                    $success = 'Fiyat başarıyla güncellendi!';
                    logAdminActivity('update_price', "Updated {$current['symbol']} price from $old_price to $new_price");
                } else {
                    $error = 'Fiyat güncellenirken hata oluştu!';
                }
            }
        }
    }
    
    elseif ($action === 'bulk_price_update') {
        // Bulk price update
        $category = sanitizeInput($_POST['bulk_category'] ?? '');
        $percentage = floatval($_POST['percentage'] ?? 0);
        
        if (!empty($category) && $percentage != 0) {
            $multiplier = 1 + ($percentage / 100);
            
            $query = "UPDATE markets SET 
                      price = price * ?,
                      change_24h = ?,
                      updated_at = CURRENT_TIMESTAMP 
                      WHERE category = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$multiplier, $percentage, $category])) {
                $affected = $stmt->rowCount();
                $success = "$affected sembol fiyatı %$percentage oranında güncellendi!";
                logAdminActivity('bulk_price_update', "Updated $category prices by $percentage%");
            } else {
                $error = 'Toplu fiyat güncellenirken hata oluştu!';
            }
        }
    }
    
    elseif ($action === 'delete_symbol') {
        // Delete symbol
        $symbol_id = intval($_POST['symbol_id'] ?? 0);
        
        if ($symbol_id > 0) {
            // Get symbol info first
            $query = "SELECT symbol, name FROM markets WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$symbol_id]);
            $symbol_info = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($symbol_info) {
                $query = "DELETE FROM markets WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$symbol_id])) {
                    $success = 'Sembol başarıyla silindi!';
                    logAdminActivity('delete_symbol', "Deleted symbol: {$symbol_info['symbol']} ({$symbol_info['name']})");
                } else {
                    $error = 'Sembol silinirken hata oluştu!';
                }
            }
        }
    }
    
    elseif ($action === 'update_logo') {
        // Update symbol logo
        $symbol_id = intval($_POST['symbol_id'] ?? 0);
        $logo_url = sanitizeInput($_POST['logo_url'] ?? '');
        
        if ($symbol_id > 0) {
            $query = "UPDATE markets SET logo_url = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$logo_url, $symbol_id])) {
                $success = 'Logo başarıyla güncellendi!';
                logAdminActivity('update_logo', "Updated logo for symbol ID: $symbol_id");
            } else {
                $error = 'Logo güncellenirken hata oluştu!';
            }
        }
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($category_filter)) {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(symbol LIKE ? OR name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get symbols
$query = "SELECT * FROM markets $where_clause ORDER BY category, symbol";
$stmt = $db->prepare($query);
$stmt->execute($params);
$symbols = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories
$categories = getFinancialCategories();

include 'includes/admin_header.php';
?>

<div class="container-fluid">
    <div class="row">
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-coins"></i> Sembol Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addSymbolModal">
                            <i class="fas fa-plus"></i> Yeni Sembol
                        </button>
                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#bulkUpdateModal">
                            <i class="fas fa-edit"></i> Toplu Güncelleme
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
                            <label class="form-label">Kategori</label>
                            <select name="category" class="form-select">
                                <option value="">Tüm Kategoriler</option>
                                <?php foreach ($categories as $cat_key => $cat_name): ?>
                                    <option value="<?php echo $cat_key; ?>" <?php echo $category_filter === $cat_key ? 'selected' : ''; ?>>
                                        <?php echo $cat_name; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Arama</label>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Sembol veya isim ara..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2 d-md-block">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="symbols.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Temizle
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Symbols Table -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-list"></i> Sembol Listesi (<?php echo count($symbols); ?> sembol)
                    </h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="symbolsTable">
                            <thead>
                                <tr>
                                    <th>Logo</th>
                                    <th>Sembol</th>
                                    <th>İsim</th>
                                    <th>Kategori</th>
                                    <th>Fiyat</th>
                                    <th>Değişim</th>
                                    <th>Son Güncelleme</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($symbols)): ?>
                                    <?php foreach ($symbols as $symbol): ?>
                                    <tr>
                                        <td class="text-center">
                                            <?php if ($symbol['logo_url']): ?>
                                                <img src="<?php echo htmlspecialchars($symbol['logo_url']); ?>" 
                                                     alt="<?php echo htmlspecialchars($symbol['symbol']); ?>" 
                                                     class="symbol-logo" style="width: 32px; height: 32px; border-radius: 50%;"
                                                     onerror="this.style.display='none';">
                                            <?php else: ?>
                                                <i class="fas fa-image text-muted"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($symbol['symbol']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($symbol['name']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo $categories[$symbol['category']] ?? $symbol['category']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="price-display" data-price="<?php echo $symbol['price']; ?>">
                                                $<?php echo formatPrice($symbol['price']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $symbol['change_24h'] >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo ($symbol['change_24h'] >= 0 ? '+' : '') . formatNumber($symbol['change_24h'], 2); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('d.m.Y H:i', strtotime($symbol['updated_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="editPrice(<?php echo $symbol['id']; ?>, '<?php echo htmlspecialchars($symbol['symbol']); ?>', <?php echo $symbol['price']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="editLogo(<?php echo $symbol['id']; ?>, '<?php echo htmlspecialchars($symbol['symbol']); ?>', '<?php echo htmlspecialchars($symbol['logo_url']); ?>')">
                                                    <i class="fas fa-image"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deleteSymbol(<?php echo $symbol['id']; ?>, '<?php echo htmlspecialchars($symbol['symbol']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            Sembol bulunamadı
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Symbol Modal -->
<div class="modal fade" id="addSymbolModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus"></i> Yeni Sembol Ekle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_symbol">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Sembol Kodu *</label>
                                <input type="text" name="symbol" class="form-control" placeholder="AAPL" required maxlength="20">
                                <small class="form-text text-muted">Büyük harflerle yazın</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Kategori *</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Kategori Seçin</option>
                                    <?php foreach ($categories as $cat_key => $cat_name): ?>
                                        <option value="<?php echo $cat_key; ?>"><?php echo $cat_name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tam İsim *</label>
                        <input type="text" name="name" class="form-control" placeholder="Apple Inc." required maxlength="100">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Başlangıç Fiyatı ($) *</label>
                        <input type="number" name="price" class="form-control" step="0.00000001" min="0" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Logo URL</label>
                        <input type="url" name="logo_url" class="form-control" placeholder="https://logo.clearbit.com/apple.com">
                        <small class="form-text text-muted">Opsiyonel - boş bırakabilirsiniz</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-plus"></i> Sembol Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Update Modal -->
<div class="modal fade" id="bulkUpdateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Toplu Fiyat Güncelleme
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="return confirm('Bu işlem seçili kategorideki tüm sembolleri etkileyecek. Devam etmek istediğinizden emin misiniz?') && showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_price_update">
                    
                    <div class="mb-3">
                        <label class="form-label">Kategori</label>
                        <select name="bulk_category" class="form-select" required>
                            <option value="">Kategori Seçin</option>
                            <?php foreach ($categories as $cat_key => $cat_name): ?>
                                <option value="<?php echo $cat_key; ?>"><?php echo $cat_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yüzde Değişim</label>
                        <input type="number" name="percentage" class="form-control" step="0.01" placeholder="+5.00 veya -3.50" required>
                        <small class="form-text text-muted">
                            Pozitif değer artış, negatif değer azalış anlamına gelir<br>
                            Örnek: +5 = %5 artış, -3 = %3 azalış
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-edit"></i> Toplu Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Price Edit Modal -->
<div class="modal fade" id="priceEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> Fiyat Düzenle: <span id="priceEditSymbol"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_price">
                    <input type="hidden" name="symbol_id" id="priceEditId">
                    
                    <div class="mb-3">
                        <label class="form-label">Mevcut Fiyat</label>
                        <input type="text" class="form-control" id="priceEditCurrent" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Yeni Fiyat ($)</label>
                        <input type="number" name="new_price" class="form-control" step="0.00000001" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Fiyatı Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Logo Edit Modal -->
<div class="modal fade" id="logoEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-image"></i> Logo Düzenle: <span id="logoEditSymbol"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" onsubmit="showLoading(this.querySelector('[type=submit]'))">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_logo">
                    <input type="hidden" name="symbol_id" id="logoEditId">
                    
                    <div class="mb-3">
                        <label class="form-label">Logo URL</label>
                        <input type="url" name="logo_url" class="form-control" id="logoEditUrl" placeholder="https://logo.clearbit.com/apple.com">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Önizleme</label>
                        <div class="text-center">
                            <img id="logoPreview" src="" alt="Logo Preview" class="img-thumbnail" 
                                 style="width: 64px; height: 64px; display: none;" 
                                 onerror="this.style.display='none';">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-info">
                        <i class="fas fa-save"></i> Logo Güncelle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Form -->
<form id="deleteForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_symbol">
    <input type="hidden" name="symbol_id" id="deleteSymbolId">
</form>

<script>
// Initialize search functionality
document.addEventListener('DOMContentLoaded', function() {
    initSearch('searchInput', 'symbolsTable');
});

// Edit price function
function editPrice(id, symbol, currentPrice) {
    document.getElementById('priceEditId').value = id;
    document.getElementById('priceEditSymbol').textContent = symbol;
    document.getElementById('priceEditCurrent').value = '$' + formatPrice(currentPrice);
    
    const modal = new bootstrap.Modal(document.getElementById('priceEditModal'));
    modal.show();
}

// Edit logo function
function editLogo(id, symbol, currentUrl) {
    document.getElementById('logoEditId').value = id;
    document.getElementById('logoEditSymbol').textContent = symbol;
    document.getElementById('logoEditUrl').value = currentUrl;
    
    if (currentUrl) {
        document.getElementById('logoPreview').src = currentUrl;
        document.getElementById('logoPreview').style.display = 'block';
    }
    
    const modal = new bootstrap.Modal(document.getElementById('logoEditModal'));
    modal.show();
}

// Delete symbol function
function deleteSymbol(id, symbol) {
    if (confirm(`"${symbol}" sembolünü silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!`)) {
        document.getElementById('deleteSymbolId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Logo preview on URL change
document.getElementById('logoEditUrl').addEventListener('input', function() {
    const url = this.value;
    const preview = document.getElementById('logoPreview');
    
    if (url) {
        preview.src = url;
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

// Symbol input to uppercase
document.querySelector('input[name="symbol"]').addEventListener('input', function() {
    this.value = this.value.toUpperCase();
});
</script>

<?php include 'includes/admin_footer.php'; ?>
