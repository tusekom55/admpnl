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
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    
    <!-- EMERGENCY MODAL FIX - INLINE CSS -->
    <style>
        /* MODAL Z-INDEX AND POSITIONING FIX */
        .modal {
            z-index: 99999 !important;
        }
        
        .modal-backdrop {
            z-index: 99998 !important;
        }
        
        .modal-dialog {
            max-width: 700px !important;
            width: 700px !important;
            margin: 1rem auto !important;
            position: relative !important;
            top: 50px !important;
        }
        
        @media (max-width: 768px) {
            .modal-dialog {
                max-width: 95vw !important;
                width: 95vw !important;
                margin: 0.5rem auto !important;
                top: 20px !important;
            }
            
            /* Leverage modal specific mobile optimizations */
            .modal-dialog.modal-responsive-leverage {
                max-width: 98vw !important;
                width: 98vw !important;
                margin: 0.25rem auto !important;
                top: 10px !important;
            }
        }
        
        .modal-content {
            max-height: 80vh !important;
            overflow: hidden !important;
            border-radius: 12px !important;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important;
        }
        
        .modal-body {
            padding: 0.75rem !important;
            font-size: 0.9rem !important;
            overflow-y: auto !important;
            max-height: calc(80vh - 120px) !important;
        }
        
        .modal-header {
            padding: 0.75rem !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .modal-title {
            font-size: 1rem !important;
        }
        
        .modal .btn {
            font-size: 0.85rem !important;
            padding: 0.5rem 0.75rem !important;
            min-height: 40px !important;
        }
        
        .modal .form-control {
            font-size: 0.9rem !important;
            padding: 0.5rem !important;
            min-height: 40px !important;
        }
        
        .modal .input-group-text {
            font-size: 0.85rem !important;
            padding: 0.5rem !important;
        }
        
        .modal .card-body {
            padding: 0.5rem !important;
        }
        
        .modal .form-label {
            font-size: 0.85rem !important;
            margin-bottom: 0.25rem !important;
        }
        
        .modal small {
            font-size: 0.75rem !important;
        }
        
        /* Center modal properly */
        .modal.show .modal-dialog {
            transform: translate(0, 0) !important;
        }
        
        /* Remove debug borders */
        #tradeModal, #leverageModal {
            border: none !important;
        }
        
        /* MODAL TAB STYLING */
        .modal-tab-nav {
            padding: 0 !important;
            margin: 0 !important;
            background: #f8f9fa !important;
            border-bottom: 1px solid #dee2e6 !important;
        }
        
        .modal-tab-nav .nav-tabs {
            margin-bottom: 0 !important;
            border-bottom: none !important;
        }
        
        .modal-tab-nav .nav-link {
            border: none !important;
            border-radius: 0 !important;
            padding: 0.75rem 1rem !important;
            font-size: 0.85rem !important;
            font-weight: 500 !important;
            color: #6c757d !important;
            background: transparent !important;
            border-bottom: 3px solid transparent !important;
            transition: all 0.2s ease !important;
        }
        
        .modal-tab-nav .nav-link.active {
            color: #007bff !important;
            background: white !important;
            border-bottom-color: #007bff !important;
        }
        
        .modal-tab-nav .nav-link:hover {
            color: #007bff !important;
            background: rgba(0, 123, 255, 0.1) !important;
        }
        
        /* CHART CONTAINER STYLING */
        .chart-container {
            position: relative !important;
            height: 300px !important;
            background: white !important;
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            padding: 1rem !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1) !important;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                height: 250px !important;
                padding: 0.5rem !important;
            }
        }
        
        .chart-controls {
            margin-top: 0.75rem !important;
        }
        
        .chart-controls .btn-group {
            width: 100% !important;
        }
        
        .chart-controls .btn {
            font-size: 0.75rem !important;
            padding: 0.375rem 0.5rem !important;
            border-color: #dee2e6 !important;
        }
        
        .chart-controls .btn.active {
            background-color: #007bff !important;
            border-color: #007bff !important;
            color: white !important;
        }
        
        .chart-controls .btn:not(.active) {
            background-color: white !important;
            color: #6c757d !important;
        }
        
        .chart-controls .btn:not(.active):hover {
            background-color: #f8f9fa !important;
            color: #007bff !important;
        }
        
        /* LEVERAGE MODAL MOBILE OPTIMIZATIONS */
        @media (max-width: 768px) {
            /* Modal size and positioning for mobile */
            .modal-dialog.modal-responsive-leverage {
                max-width: 100vw !important;
                width: 100vw !important;
                margin: 0 !important;
                top: 0 !important;
                height: 100vh !important;
                display: flex !important;
                align-items: stretch !important;
            }
            
            /* Modal content optimization */
            #leverageModal .modal-content {
                max-height: 100vh !important;
                height: 100vh !important;
                border-radius: 0 !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            }
            
            /* Modal header compact */
            #leverageModal .modal-header {
                padding: 0.75rem 1rem !important;
                background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
                color: #000 !important;
                flex-shrink: 0 !important;
                min-height: 60px !important;
            }
            
            #leverageModal .modal-header .h5 {
                font-size: 1rem !important;
                font-weight: 700 !important;
            }
            
            #leverageModal .modal-header .badge {
                font-size: 0.7rem !important;
                padding: 0.25rem 0.5rem !important;
                background: rgba(0,0,0,0.2) !important;
                color: #fff !important;
            }
            
            /* Modal body full scroll */
            #leverageModal .modal-body {
                flex: 1 !important;
                overflow-y: auto !important;
                overflow-x: hidden !important;
                padding: 1rem !important;
                font-size: 0.85rem !important;
                background: #f8f9fa !important;
                max-height: none !important;
                -webkit-overflow-scrolling: touch !important;
            }
            
            /* Form elements compact */
            #leverageModal .form-label {
                font-size: 0.8rem !important;
                font-weight: 600 !important;
                margin-bottom: 0.25rem !important;
                color: #333 !important;
            }
            
            #leverageModal .form-control,
            #leverageModal .form-select {
                font-size: 0.85rem !important;
                padding: 0.6rem !important;
                min-height: 44px !important;
                border-radius: 8px !important;
                border: 2px solid #e9ecef !important;
            }
            
            #leverageModal .form-control:focus,
            #leverageModal .form-select:focus {
                border-color: #ffc107 !important;
                box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
            }
            
            #leverageModal .input-group-text {
                font-size: 0.8rem !important;
                padding: 0.6rem !important;
                background: #ffc107 !important;
                color: #000 !important;
                font-weight: 600 !important;
                border: 2px solid #ffc107 !important;
            }
            
            /* Row and column spacing */
            #leverageModal .row {
                margin-bottom: 1rem !important;
            }
            
            #leverageModal .col-md-6 {
                margin-bottom: 1rem !important;
            }
            
            /* Card optimizations */
            #leverageModal .card {
                margin-bottom: 1rem !important;
                border: none !important;
                border-radius: 12px !important;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            }
            
            #leverageModal .card-header {
                padding: 0.75rem 1rem !important;
                background: linear-gradient(135deg, #007bff, #0056b3) !important;
                color: white !important;
                border-bottom: none !important;
                border-radius: 12px 12px 0 0 !important;
            }
            
            #leverageModal .card-header h6 {
                font-size: 0.9rem !important;
                margin-bottom: 0 !important;
                font-weight: 600 !important;
            }
            
            #leverageModal .card-body {
                padding: 1rem !important;
                background: white !important;
            }
            
            /* Calculation grid optimization */
            #leverageModal .card-body .row.text-center {
                margin: 0 !important;
            }
            
            #leverageModal .card-body .row.text-center .col-6,
            #leverageModal .card-body .row.text-center .col-md-3 {
                margin-bottom: 0.75rem !important;
                padding: 0.5rem !important;
            }
            
            #leverageModal .card-body small {
                font-size: 0.7rem !important;
                line-height: 1.3 !important;
                color: #666 !important;
                font-weight: 500 !important;
            }
            
            #leverageModal .card-body strong {
                font-size: 0.85rem !important;
                display: block !important;
                margin-top: 0.25rem !important;
                font-weight: 700 !important;
            }
            
            /* Special styling for different card types */
            #leverageModal .bg-danger {
                background: linear-gradient(135deg, #dc3545, #c82333) !important;
            }
            
            #leverageModal .bg-success {
                background: linear-gradient(135deg, #28a745, #20c997) !important;
            }
            
            /* Trading buttons - fixed bottom */
            #leverageModal .d-flex.gap-2 {
                position: sticky !important;
                bottom: 0 !important;
                background: white !important;
                padding: 1rem !important;
                margin: 1rem -1rem -1rem -1rem !important;
                border-top: 2px solid #e9ecef !important;
                gap: 0.75rem !important;
                z-index: 10 !important;
                box-shadow: 0 -2px 8px rgba(0,0,0,0.1) !important;
            }
            
            #leverageModal .d-flex.gap-2 .btn {
                font-size: 0.9rem !important;
                padding: 0.8rem 1rem !important;
                min-height: 48px !important;
                font-weight: 700 !important;
                border-radius: 12px !important;
                border: none !important;
                text-transform: uppercase !important;
                letter-spacing: 0.5px !important;
                flex: 1 !important;
            }
            
            #leverageModal .btn-success {
                background: linear-gradient(135deg, #28a745, #20c997) !important;
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3) !important;
            }
            
            #leverageModal .btn-danger {
                background: linear-gradient(135deg, #dc3545, #c82333) !important;
                box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3) !important;
            }
            
            /* Alert box optimization */
            #leverageModal .alert {
                margin: 1rem 0 !important;
                padding: 1rem !important;
                font-size: 0.8rem !important;
                border-radius: 12px !important;
                border: none !important;
                background: linear-gradient(135deg, #fff3cd, #ffeaa7) !important;
                color: #856404 !important;
                line-height: 1.4 !important;
            }
            
            #leverageModal .alert .fas {
                margin-right: 0.5rem !important;
                color: #f39c12 !important;
            }
            
            #leverageModal .alert strong {
                font-size: 0.8rem !important;
                display: inline !important;
                margin-top: 0 !important;
                color: #856404 !important;
            }
            
            /* Close button optimization */
            #leverageModal .btn-close {
                background: rgba(255,255,255,0.2) !important;
                border-radius: 50% !important;
                opacity: 1 !important;
                font-size: 1.2rem !important;
                width: 32px !important;
                height: 32px !important;
            }
            
            /* Smooth scrolling */
            #leverageModal .modal-body::-webkit-scrollbar {
                width: 4px !important;
            }
            
            #leverageModal .modal-body::-webkit-scrollbar-track {
                background: #f1f1f1 !important;
            }
            
            #leverageModal .modal-body::-webkit-scrollbar-thumb {
                background: #ffc107 !important;
                border-radius: 2px !important;
            }
        }
    </style>
    
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
        
        /* MOBILE MENU STYLES */
        .offcanvas {
            width: 320px !important;
            border: none !important;
            box-shadow: 2px 0 20px rgba(0, 0, 0, 0.1) !important;
        }
        
        .offcanvas-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0056b3 100%);
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .offcanvas-title {
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .offcanvas-body {
            padding: 0;
            background: #f8f9fa;
        }
        
        /* User Section */
        .mobile-user-section {
            padding: 1.5rem;
            background: white;
            border-bottom: 1px solid #e9ecef;
        }
        
        .mobile-user-card {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .mobile-user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .mobile-user-info {
            flex: 1;
        }
        
        .mobile-username {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }
        
        .mobile-balance {
            font-size: 0.9rem;
        }
        
        /* Navigation Sections */
        .mobile-nav-section {
            padding: 1.5rem;
            background: white;
            margin-bottom: 0.5rem;
            border-bottom: 1px solid #e9ecef;
        }
        
        .mobile-nav-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
            display: inline-block;
        }
        
        /* Grid Navigation */
        .mobile-nav-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.25rem 0.75rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            min-height: 90px;
            position: relative;
            overflow: hidden;
        }
        
        .mobile-nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, var(--primary-color), #0056b3);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        
        .mobile-nav-item.active::before {
            opacity: 1;
        }
        
        .mobile-nav-item.active {
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 123, 255, 0.3);
        }
        
        .mobile-nav-item:hover:not(.active) {
            background: #e9ecef;
            border-color: var(--primary-color);
            transform: translateY(-2px);
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .mobile-nav-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .mobile-nav-item span {
            font-size: 0.85rem;
            font-weight: 500;
            text-align: center;
            position: relative;
            z-index: 2;
        }
        
        /* Quick Actions */
        .mobile-quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .mobile-quick-btn {
            padding: 0.75rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .mobile-quick-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            text-decoration: none;
        }
        
        /* List Navigation */
        .mobile-nav-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .mobile-nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .mobile-nav-link:hover {
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            transform: translateX(5px);
        }
        
        /* Language Switcher */
        .mobile-language-switcher {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }
        
        .mobile-lang-btn {
            padding: 0.75rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text-primary);
            text-align: center;
            font-weight: 500;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .mobile-lang-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .mobile-lang-btn:hover:not(.active) {
            background: #e9ecef;
            border-color: var(--primary-color);
            color: var(--primary-color);
            text-decoration: none;
        }
        
        /* Logout Button */
        .mobile-logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 1rem;
            background: #dc3545;
            color: white;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .mobile-logout-btn:hover {
            background: #c82333;
            color: white;
            text-decoration: none;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        }
        
        /* Auth Section */
        .mobile-auth-section {
            padding: 2rem 1.5rem;
            background: white;
            text-align: center;
        }
        
        /* Ensure mobile menu doesn't interfere with page categories */
        @media (max-width: 768px) {
            .mobile-category-tabs {
                z-index: 1000 !important;
                position: relative !important;
            }
            
            .offcanvas {
                z-index: 9998 !important;
            }
            
            .offcanvas-backdrop {
                z-index: 9997 !important;
            }
        }
        
        /* Mobile responsiveness */
        @media (max-width: 375px) {
            .offcanvas {
                width: 300px !important;
            }
            
            .mobile-nav-grid {
                grid-template-columns: 1fr;
            }
            
            .mobile-quick-actions,
            .mobile-language-switcher {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Unified Navigation System -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i><?php echo SITE_NAME; ?>
            </a>
            
            <!-- Mobile Menu Toggle -->
            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileMenu" aria-controls="mobileMenu">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <!-- Desktop Navigation -->
            <div class="collapse navbar-collapse d-none d-lg-flex" id="navbarNav">
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
                </ul>
                
                <div class="d-flex align-items-center">
                    <!-- Language Switcher -->
                    <div class="language-switcher me-3">
                        <a href="?lang=tr" class="<?php echo getCurrentLang() == 'tr' ? 'active' : ''; ?>">TR</a>
                        <a href="?lang=en" class="<?php echo getCurrentLang() == 'en' ? 'active' : ''; ?>">EN</a>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <!-- User Balance (Parametric) -->
                        <div class="me-3 d-none d-md-block">
                            <small class="text-muted"><?php echo t('balance'); ?>:</small>
                            <strong class="text-success"><?php echo getFormattedHeaderBalance($_SESSION['user_id']); ?></strong>
                        </div>
                        
                        <!-- User Menu -->
                        <div class="dropdown">
                            <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user me-1"></i><span class="d-none d-md-inline"><?php echo $_SESSION['username']; ?></span>
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
    
    <!-- Mobile Offcanvas Menu -->
    <div class="offcanvas offcanvas-start" tabindex="-1" id="mobileMenu" aria-labelledby="mobileMenuLabel">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title" id="mobileMenuLabel">
                <i class="fas fa-chart-line me-2 text-primary"></i><?php echo SITE_NAME; ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body">
            <?php if (isLoggedIn()): ?>
                <!-- User Info Section -->
                <div class="mobile-user-section">
                    <div class="mobile-user-card">
                        <div class="mobile-user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="mobile-user-info">
                            <div class="mobile-username"><?php echo $_SESSION['username']; ?></div>
                            <div class="mobile-balance">
                                <small class="text-muted"><?php echo t('balance'); ?>:</small>
                                <strong class="text-success"><?php echo getFormattedHeaderBalance($_SESSION['user_id']); ?></strong>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Main Navigation -->
                <div class="mobile-nav-section">
                    <h6 class="mobile-nav-title"><?php echo getCurrentLang() == 'tr' ? 'Ana Menü' : 'Main Menu'; ?></h6>
                    <div class="mobile-nav-grid">
                        <a href="markets.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'markets.php' ? 'active' : ''; ?>" data-bs-dismiss="offcanvas">
                            <div class="mobile-nav-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <span><?php echo getCurrentLang() == 'tr' ? 'Piyasalar' : 'Markets'; ?></span>
                        </a>
                        <a href="portfolio.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'portfolio.php' ? 'active' : ''; ?>" data-bs-dismiss="offcanvas">
                            <div class="mobile-nav-icon">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <span><?php echo getCurrentLang() == 'tr' ? 'Portföy' : 'Portfolio'; ?></span>
                        </a>
                        <a href="wallet.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'wallet.php' ? 'active' : ''; ?>" data-bs-dismiss="offcanvas">
                            <div class="mobile-nav-icon">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <span><?php echo getCurrentLang() == 'tr' ? 'Cüzdan' : 'Wallet'; ?></span>
                        </a>
                        <a href="profile.php" class="mobile-nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" data-bs-dismiss="offcanvas">
                            <div class="mobile-nav-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <span><?php echo getCurrentLang() == 'tr' ? 'Profil' : 'Profile'; ?></span>
                        </a>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="mobile-nav-section">
                    <h6 class="mobile-nav-title"><?php echo getCurrentLang() == 'tr' ? 'Hızlı İşlemler' : 'Quick Actions'; ?></h6>
                    <div class="mobile-quick-actions">
                        <a href="markets.php" class="mobile-quick-btn btn-success" data-bs-dismiss="offcanvas">
                            <i class="fas fa-shopping-cart me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Al/Sat' : 'Trade'; ?>
                        </a>
                        <a href="wallet.php" class="mobile-quick-btn btn-primary" data-bs-dismiss="offcanvas">
                            <i class="fas fa-plus me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Para Yatır' : 'Deposit'; ?>
                        </a>
                    </div>
                </div>
                
                <!-- Logout -->
                <div class="mobile-nav-section">
                    <a href="logout.php" class="mobile-logout-btn">
                        <i class="fas fa-sign-out-alt me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Çıkış Yap' : 'Logout'; ?>
                    </a>
                </div>
            <?php else: ?>
                <!-- Not Logged In State -->
                <div class="mobile-auth-section">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x text-muted mb-3"></i>
                        <h6><?php echo getCurrentLang() == 'tr' ? 'Hesabınıza giriş yapın' : 'Sign in to your account'; ?></h6>
                        <p class="text-muted small"><?php echo getCurrentLang() == 'tr' ? 'Trading ve portföy yönetimi için' : 'For trading and portfolio management'; ?></p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="login.php" class="btn btn-primary" data-bs-dismiss="offcanvas">
                            <i class="fas fa-sign-in-alt me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Giriş Yap' : 'Sign In'; ?>
                        </a>
                        <a href="register.php" class="btn btn-outline-primary" data-bs-dismiss="offcanvas">
                            <i class="fas fa-user-plus me-2"></i><?php echo getCurrentLang() == 'tr' ? 'Kayıt Ol' : 'Sign Up'; ?>
                        </a>
                    </div>
                </div>
                
                <!-- Public Navigation -->
                <div class="mobile-nav-section mt-4">
                    <h6 class="mobile-nav-title"><?php echo getCurrentLang() == 'tr' ? 'Keşfet' : 'Explore'; ?></h6>
                    <div class="mobile-nav-list">
                        <a href="markets.php" class="mobile-nav-link" data-bs-dismiss="offcanvas">
                            <i class="fas fa-chart-line me-3"></i><?php echo getCurrentLang() == 'tr' ? 'Piyasalar' : 'Markets'; ?>
                        </a>
                        <a href="index.php" class="mobile-nav-link" data-bs-dismiss="offcanvas">
                            <i class="fas fa-home me-3"></i><?php echo getCurrentLang() == 'tr' ? 'Ana Sayfa' : 'Home'; ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Language Switcher -->
            <div class="mobile-nav-section mt-auto">
                <h6 class="mobile-nav-title"><?php echo getCurrentLang() == 'tr' ? 'Dil' : 'Language'; ?></h6>
                <div class="mobile-language-switcher">
                    <a href="?lang=tr" class="mobile-lang-btn <?php echo getCurrentLang() == 'tr' ? 'active' : ''; ?>">
                        🇹🇷 Türkçe
                    </a>
                    <a href="?lang=en" class="mobile-lang-btn <?php echo getCurrentLang() == 'en' ? 'active' : ''; ?>">
                        🇺🇸 English
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
