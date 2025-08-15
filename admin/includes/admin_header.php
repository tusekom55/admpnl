<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel - GlobalBorsa'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }
        
        .admin-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-bottom: 3px solid #5a67d8;
        }
        
        .admin-navbar .navbar-brand {
            color: #fff !important;
            font-weight: 700;
            font-size: 1.4rem;
        }
        
        .admin-navbar .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
        }
        
        .admin-navbar .nav-link:hover {
            color: #fff !important;
            background-color: rgba(255,255,255,0.1);
            border-radius: 6px;
        }
        
        .sidebar {
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            min-height: calc(100vh - 76px);
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 0.75rem 1.25rem;
            border-left: 3px solid transparent;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover {
            background-color: #f8f9fa;
            border-left-color: #007bff;
            color: #007bff;
        }
        
        .sidebar .nav-link.active {
            background-color: #e7f3ff;
            border-left-color: #007bff;
            color: #007bff;
            font-weight: 600;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 8px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
        }
        
        .btn {
            border-radius: 8px;
        }
        
        .badge {
            border-radius: 20px;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .dropdown-menu {
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }
        
        /* Success animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in-up {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Loading spinner */
        .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="navbar navbar-expand-lg admin-navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>
                GlobalBorsa Admin
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="adminNavbar">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-bell me-1"></i>
                            Bildirimler
                            <?php
                            // Get notification count
                            $database = new Database();
                            $db = $database->getConnection();
                            $query = "SELECT (SELECT COUNT(*) FROM deposits WHERE status = 'pending') + 
                                            (SELECT COUNT(*) FROM withdrawals WHERE status = 'pending') as total";
                            $stmt = $db->prepare($query);
                            $stmt->execute();
                            $notification_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                            
                            if ($notification_count > 0): ?>
                                <span class="badge bg-warning text-dark"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <?php if ($notification_count > 0): ?>
                                <li><a class="dropdown-item" href="deposits.php?status=pending">
                                    <i class="fas fa-arrow-down text-success me-2"></i>
                                    Bekleyen Para Yatırma
                                </a></li>
                                <li><a class="dropdown-item" href="withdrawals.php?status=pending">
                                    <i class="fas fa-arrow-up text-danger me-2"></i>
                                    Bekleyen Para Çekme
                                </a></li>
                            <?php else: ?>
                                <li><span class="dropdown-item text-muted">Yeni bildirim yok</span></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i>
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user me-2"></i>Profil
                            </a></li>
                            <li><a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cogs me-2"></i>Ayarlar
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../index.php">
                                <i class="fas fa-home me-2"></i>Ana Site
                            </a></li>
                            <li><a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Çıkış Yap
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
