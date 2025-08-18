<?php
// Don't require login for landing pages
$current_page = basename($_SERVER['PHP_SELF']);
$public_pages = ['landing-new.php', 'landing-test.php', 'landing-ornek.html', 'index.php', 'login.php', 'register.php'];

if (!in_array($current_page, $public_pages)) {
    // Only require login for non-public pages
    // requireLogin(); // Commented out to allow access to landing pages
}
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLang(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <!-- Modal Overrides CSS - MUST load after Bootstrap -->
    <link href="assets/css/modal-overrides.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-bg: #ffffff;
            --secondary-bg: #f8f9fa;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --border-color: #dee2e6;
            --hover-bg: #f1f3f4;
            --primary-color: #007bff;
        }
        
        body {
            background-color: var(--secondary-bg);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar {
            background-color: var(--primary-bg) !important;
            border-bottom: 1px solid var(--border-color);
            padding: 0.75rem 0;
            position: fixed !important;
            top: 0 !important;
            left: 0 !important;
            right: 0 !important;
            z-index: 9999 !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color) !important;
        }
        
        .navbar-nav .nav-link {
            color: var(--text-primary) !important;
            font-weight: 500;
            padding: 0.5rem 1rem !important;
            margin: 0 0.25rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .navbar-nav .nav-link:hover {
            background-color: var(--hover-bg);
            color: var(--primary-color) !important;
        }
        
        .navbar-nav .nav-link.active {
            background-color: var(--primary-color);
            color: white !important;
        }
        
        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .language-switcher {
            display: flex;
            gap: 0.5rem;
        }
        
        .language-switcher a {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .language-switcher a:hover,
        .language-switcher a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .main-content {
            background-color: var(--primary-bg);
            min-height: calc(100vh - 76px);
            padding: 2rem 0;
        }
        
        .market-tabs {
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 2rem;
        }
        
        .market-tabs .nav-link {
            border: none;
            color: var(--text-secondary);
            font-weight: 500;
            padding: 1rem 1.5rem;
            border-radius: 0;
            border-bottom: 3px solid transparent;
        }
        
        .market-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            border-bottom-color: var(--primary-color);
        }
        
        .market-tabs .nav-link:hover {
            color: var(--primary-color);
            background-color: var(--hover-bg);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'markets.php' ? 'active' : ''; ?>" href="markets.php">
                            <i class="fas fa-chart-line me-1"></i><?php echo getCurrentLang() == 'tr' ? 'Piyasalar' : 'Markets'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'portfolio.php' ? 'active' : ''; ?>" href="portfolio.php">
                            <i class="fas fa-chart-pie me-1"></i><?php echo getCurrentLang() == 'tr' ? 'Portföy' : 'Portfolio'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>" href="wallet.php">
                            <i class="fas fa-wallet me-1"></i><?php echo getCurrentLang() == 'tr' ? 'Cüzdan' : 'Wallet'; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                            <i class="fas fa-user me-1"></i><?php echo getCurrentLang() == 'tr' ? 'Profil' : 'Profile'; ?>
                        </a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center">
                    <!-- Language Switcher -->
                    <div class="language-switcher me-3">
                        <a href="?lang=tr" class="<?php echo getCurrentLang() == 'tr' ? 'active' : ''; ?>">TR</a>
                        <a href="?lang=en" class="<?php echo getCurrentLang() == 'en' ? 'active' : ''; ?>">EN</a>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <!-- User Balance (Parametric) -->
                        <div class="me-3">
                            <small class="text-muted"><?php echo t('balance'); ?>:</small>
                            <strong class="text-success"><?php echo getFormattedHeaderBalance($_SESSION['user_id']); ?></strong>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><?php echo $_SESSION['username']; ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i><?php echo t('profile'); ?></a></li>
                                <li><a class="dropdown-item" href="wallet.php"><i class="fas fa-wallet me-2"></i><?php echo t('wallet'); ?></a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i><?php echo t('logout'); ?></a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-primary me-2"><?php echo t('login'); ?></a>
                        <a href="register.php" class="btn btn-primary"><?php echo t('register'); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
