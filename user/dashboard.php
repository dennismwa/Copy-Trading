<?php
require_once '../config/database.php';
requireLogin();
$user_id = $_SESSION['user_id'];
// Get user data
$stmt = $db->prepare("
    SELECT u.*,
           COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
           COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_withdrawn
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id
    WHERE u.id = ?
    GROUP BY u.id
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get latest ROI earned (extract ROI portion from transaction description)
$stmt = $db->prepare("
    SELECT description, amount
    FROM transactions 
    WHERE user_id = ? AND type = 'roi_payment' AND status = 'completed'
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->execute([$user_id]);
$latest_roi = $stmt->fetch();
$latest_roi_earned = 0;

if ($latest_roi) {
    // Extract ROI amount from description using helper function
    $latest_roi_earned = extractROIFromDescription($latest_roi['description']);
    
    // If extraction failed, try alternative method
    if ($latest_roi_earned == 0) {
        // Try to calculate ROI from transaction amount and description
        $description = $latest_roi['description'];
        $total_amount = $latest_roi['amount'];
        
        // Look for principal amount in description
        if (preg_match('/Principal:\s*([0-9,]+\.?[0-9]*)/', $description, $matches)) {
            $principal = (float)str_replace(',', '', $matches[1]);
            $latest_roi_earned = $total_amount - $principal;
            error_log("ROI calculated as difference: $latest_roi_earned (Total: $total_amount - Principal: $principal)");
        }
    }
}

// Get active package
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name, p.icon
    FROM active_packages ap
    JOIN packages p ON ap.package_id = p.id
    WHERE ap.user_id = ? AND ap.status = 'active'
    ORDER BY ap.created_at DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$active_package = $stmt->fetch();
// MOCK DATA for Live Activity Feed - Using Excel Data Structure
$live_activity = [
    ['full_name' => 'Grace Kamau', 'action_text' => 'withdrew profits', 'amount' => 168147, 'created_at' => date('Y-m-d H:i:s', strtotime('-2 minutes'))],
    ['full_name' => 'Samuel Kiprotich', 'action_text' => 'withdrew profits', 'amount' => 235714, 'created_at' => date('Y-m-d H:i:s', strtotime('-5 minutes'))],
    ['full_name' => 'Stella Kamau', 'action_text' => 'completed copy trade', 'amount' => 199966, 'created_at' => date('Y-m-d H:i:s', strtotime('-8 minutes'))],
    ['full_name' => 'David Barasa', 'action_text' => 'completed copy trade', 'amount' => 236749, 'created_at' => date('Y-m-d H:i:s', strtotime('-12 minutes'))],
    ['full_name' => 'Mary Otieno', 'action_text' => 'deposited', 'amount' => 89347, 'created_at' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
    ['full_name' => 'Joseph Odhiambo', 'action_text' => 'deposited', 'amount' => 74219, 'created_at' => date('Y-m-d H:i:s', strtotime('-18 minutes'))],
    ['full_name' => 'Winnie Wairimu', 'action_text' => 'earned referral bonus', 'amount' => 9434, 'created_at' => date('Y-m-d H:i:s', strtotime('-22 minutes'))],
    ['full_name' => 'Carol Makau', 'action_text' => 'activated trading', 'amount' => 43711, 'created_at' => date('Y-m-d H:i:s', strtotime('-25 minutes'))],
];
// Get total unread count (not limited)
$stmt = $db->prepare("
    SELECT COUNT(*) as unread_count 
    FROM notifications
    WHERE (user_id = ? OR is_global = 1) AND is_read = 0
");
$stmt->execute([$user_id]);
$unread_count_result = $stmt->fetch();
$unread_count = (int)$unread_count_result['unread_count'];

// Get notifications for dropdown (limited to 5)
$stmt = $db->prepare("
    SELECT * FROM notifications
    WHERE (user_id = ? OR is_global = 1) AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
       
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #fbbf24 100%);
            position: relative;
            overflow: hidden;
        }
       
        /* Forex chart background */
        .forex-bg {
            background-image: url('/Trading.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
        }
       
        .forex-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.85) 0%, rgba(251, 191, 36, 0.85) 100%);
        }
       
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
       
        /* Activity Card Styles */
        .activity-card {
            animation: fadeIn 0.5s ease-out;
            transition: all 0.3s ease;
        }
       
        .activity-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
       
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
       
        .pulse-dot {
            animation: pulse-dot 2s infinite;
        }
       
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
       
        .status-green { color: #10b981; }
        .status-yellow { color: #f59e0b; }
        .status-red { color: #ef4444; }
       
        /* FIXED NOTIFICATION DROPDOWN - RESPONSIVE */
        .notification-dropdown {
            animation: slideDown 0.3s ease-out;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        /* Notification container positioning */
        .notification-container {
            position: relative;
            z-index: 1000;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Desktop: Positioned relative to bell icon */
        @media (min-width: 768px) {
            .notification-dropdown {
                position: absolute;
                right: 0;
                top: calc(100% + 0.5rem);
                width: 24rem;
                max-height: 32rem;
            }
        }
        /* Mobile: Full screen overlay - ONLY when not hidden */
        @media (max-width: 767px) {
            .notification-dropdown:not(.hidden) {
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                max-height: 100vh !important;
                z-index: 99999 !important;
                margin: 0 !important;
                padding: 0 !important;
                border-radius: 0 !important;
                animation: slideUp 0.3s ease-out !important;
                background: rgba(31, 41, 55, 1) !important;
                backdrop-filter: none !important;
                overflow: hidden !important;
                display: flex !important;
                flex-direction: column !important;
            }
            
            .notification-dropdown:not(.hidden) .overflow-y-auto {
                flex: 1 !important;
                overflow-y: auto !important;
                -webkit-overflow-scrolling: touch !important;
            }
            
            .notification-dropdown:not(.hidden) .p-4 {
                padding: 1rem !important;
            }
            
            /* Improve text visibility on mobile */
            .notification-dropdown:not(.hidden) .text-white {
                color: #ffffff !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5) !important;
            }
            
            .notification-dropdown:not(.hidden) .text-gray-300 {
                color: #d1d5db !important;
                text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3) !important;
            }
            
            .notification-dropdown:not(.hidden) .text-gray-400 {
                color: #9ca3af !important;
            }
            
            .notification-dropdown:not(.hidden) .bg-gray-800 {
                background-color: rgba(31, 41, 55, 0.95) !important;
            }
            
            .notification-dropdown:not(.hidden) .bg-gray-700\/30 {
                background-color: rgba(55, 65, 81, 0.6) !important;
            }
            
            .notification-dropdown:not(.hidden) .bg-gray-700\/50 {
                background-color: rgba(55, 65, 81, 0.8) !important;
            }
            
            @keyframes slideUp {
                from { 
                    transform: translateY(100%); 
                    opacity: 0;
                }
                to { 
                    transform: translateY(0); 
                    opacity: 1;
                }
            }
        }
        /* Scrollbar styling */
        .notification-dropdown::-webkit-scrollbar {
            width: 6px;
        }
        .notification-dropdown::-webkit-scrollbar-track {
            background: rgba(31, 41, 55, 0.5);
        }
        .notification-dropdown::-webkit-scrollbar-thumb {
            background: rgba(75, 85, 99, 0.8);
            border-radius: 3px;
        }
        /* Overlay for mobile */
        .notification-overlay {
            display: none;
        }
        @media (max-width: 767px) {
            .notification-overlay.active {
                display: block !important;
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                right: 0 !important;
                bottom: 0 !important;
                width: 100vw !important;
                height: 100vh !important;
                background: rgba(0, 0, 0, 0.3) !important;
                z-index: 99998 !important;
                backdrop-filter: none !important;
            }
        }
        /* Badge pulse */
        .notification-badge {
            animation: badgePulse 2s infinite;
        }
        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }
       
        /* Activity Card Carousel */
        .activity-carousel {
            position: relative;
            overflow: hidden;
        }
       
        .activity-slide {
            display: none;
            animation: slideIn 0.5s ease-out;
        }
       
        .activity-slide.active {
            display: block;
        }
       
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
       
        .carousel-dots {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }
       
        .carousel-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s ease;
        }
       
        .carousel-dot.active {
            background: #10b981;
            width: 24px;
            border-radius: 4px;
        }
       
        /* Mobile Bottom Navigation Spacing */
        @media (max-width: 768px) {
            main {
                padding-bottom: 5rem !important;
            }
        }
        /* Mobile Menu Styles */
        .mobile-menu {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(31, 41, 55, 0.95);
            z-index: 10000;
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        .mobile-menu.active {
            transform: translateX(0);
        }
        .mobile-menu .close-menu {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
    </style>
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-Z611VFCG92"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-Z611VFCG92');
    </script>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <!-- Notification Overlay for Mobile -->
    <div id="notificationOverlay" class="notification-overlay"></div>
    <!-- Mobile Menu -->
    <div id="mobileMenu" class="mobile-menu hidden">
        <button id="closeMobileMenu" class="close-menu text-white p-4">
            <i class="fas fa-times text-2xl"></i>
        </button>
        <div class="flex flex-col items-center justify-center h-full space-y-6">
            <a href="/user/dashboard.php" class="text-emerald-400 text-xl font-medium">Home</a>
            <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 text-xl font-medium transition">Trade</a>
            <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 text-xl font-medium transition">Network</a>
            <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 text-xl font-medium transition">Active Trades</a>
            <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 text-xl font-medium transition">Help</a>
            <a href="/user/profile.php" class="text-gray-300 hover:text-emerald-400 text-xl font-medium transition">Profile</a>
            <a href="/logout.php" class="text-red-400 hover:text-red-300 text-xl font-medium transition">Logout</a>
        </div>
    </div>
    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <!-- Logo & Navigation -->
                <div class="flex items-center space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    <!-- Updated Navigation Order: HOME > TRADE > NETWORK > ACTIVE TRADES > HELP -->
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-emerald-400 font-medium">Home</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Network</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>
                <!-- User Info & Actions -->
                <div class="flex items-center space-x-4">
                    <!-- Wallet Balance -->
                    <div class="hidden lg:flex items-center space-x-4 bg-gray-700/50 rounded-full px-4 py-2">
                        <i class="fas fa-wallet text-emerald-400"></i>
                        <span class="text-sm text-gray-300">Balance:</span>
                        <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></span>
                    </div>
                    <!-- Notifications Bell -->
                    <div class="notification-container relative">
                        <button id="notificationBell" class="notification-bell relative p-2 text-gray-400 hover:text-white transition-colors duration-200 z-50">
                            <i class="fas fa-bell text-xl"></i>
                            <span id="notificationBadge" class="notification-badge absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs flex items-center justify-center text-white font-bold <?php echo $unread_count > 0 ? '' : 'hidden'; ?>" data-count="<?php echo $unread_count; ?>">
                                <?php echo $unread_count > 99 ? '99+' : $unread_count; ?>
                            </span>
                        </button>
                        <!-- Notification Dropdown - FIXED RESPONSIVE -->
                        <div id="notificationDropdown" class="notification-dropdown bg-gray-800 rounded-xl shadow-2xl border border-gray-700 hidden z-50">
                            <div class="p-4 border-b border-gray-700 bg-gray-800 sticky top-0 z-10">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-white">Notifications</h3>
                                    <button id="closeNotifications" class="text-gray-400 hover:text-white transition">
                                        <i class="fas fa-times text-xl"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="overflow-y-auto" style="max-height: calc(100vh - 160px);">
                                <?php if (empty($notifications)): ?>
                                    <div class="p-8 text-center">
                                        <i class="fas fa-bell-slash text-3xl text-gray-600 mb-3"></i>
                                        <p class="text-gray-400">No notifications</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($notifications as $notification): ?>
                                    <a href="/user/notifications.php?view=<?php echo $notification['id']; ?>" class="block notification-item" data-notification-id="<?php echo $notification['id']; ?>" data-is-read="<?php echo $notification['is_read'] ? '1' : '0'; ?>">
                                        <div class="p-4 border-b border-gray-700 last:border-b-0 <?php echo !$notification['is_read'] ? 'bg-gray-700/30' : ''; ?> hover:bg-gray-700/50 transition cursor-pointer">
                                            <div class="flex items-start space-x-3">
                                                <i class="fas <?php
                                                echo match($notification['type']) {
                                                    'success' => 'fa-check-circle text-emerald-400',
                                                    'warning' => 'fa-exclamation-triangle text-yellow-400',
                                                    'error' => 'fa-exclamation-circle text-red-400',
                                                    default => 'fa-info-circle text-blue-400'
                                                };
                                                ?> mt-1 text-lg"></i>
                                                <div class="flex-1 min-w-0">
                                                    <h4 class="font-medium text-white"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                    <p class="text-sm text-gray-300 mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <p class="text-xs text-gray-500 mt-2"><?php echo timeAgo($notification['created_at']); ?></p>
                                                </div>
                                                <?php if (!$notification['is_read']): ?>
                                                    <div class="w-2 h-2 bg-emerald-400 rounded-full flex-shrink-0 mt-2"></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 border-t border-gray-700 bg-gray-800">
                                <a href="/user/notifications.php" class="text-emerald-400 hover:text-emerald-300 text-sm flex items-center justify-center">
                                    View All Notifications <i class="fas fa-arrow-right ml-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- User Menu -->
                    <div class="relative group z-50">
                        <button class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-3 py-2 hover:bg-gray-600/50 transition">
                            <div class="w-8 h-8 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span class="hidden md:block text-sm"><?php echo htmlspecialchars($user['full_name']); ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-2 w-48 bg-gray-800 rounded-lg shadow-xl border border-gray-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all">
                            <div class="py-2">
                                <a href="/user/profile.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-user mr-2"></i>Profile & History
                                </a>
                                <a href="/user/settings.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-cog mr-2"></i>Settings
                                </a>
                                <a href="/user/notifications.php" class="block px-4 py-2 text-sm text-gray-300 hover:bg-gray-700 hover:text-white">
                                    <i class="fas fa-bell mr-2"></i>All Notifications
                                </a>
                                <div class="border-t border-gray-700"></div>
                                <a href="/logout.php" class="block px-4 py-2 text-sm text-red-400 hover:bg-gray-700">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                </a>
                            </div>
                        </div>
                    </div>
                    <!-- Mobile Menu Button -->
                    <button id="mobileMenuButton" class="md:hidden p-2 text-gray-400 hover:text-white z-50">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <!-- Hero Section with Forex Background - 20% smaller welcome message -->
        <section class="forex-bg rounded-2xl p-6 lg:p-8 mb-8 relative overflow-hidden">
            <div class="relative z-10">
                <div class="grid lg:grid-cols-3 gap-6 items-center">
                    <!-- Balance Info -->
                    <div class="lg:col-span-2">
                        <!-- Welcome message 20% smaller -->
                        <h1 class="text-2xl lg:text-3xl font-bold text-white mb-2">
                            Welcome back, <?php echo htmlspecialchars(explode(' ', $user['full_name'])[0]); ?>
                        </h1>
                        <div class="grid md:grid-cols-3 gap-4 mt-6">
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">Wallet Balance</p>
                                <p class="text-2xl lg:text-3xl font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                            </div>
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">Latest ROI</p>
                                <p class="text-xl lg:text-2xl font-bold text-white"><?php echo formatMoney($latest_roi_earned); ?></p>
                            </div>
                            <?php if ($active_package): ?>
                            <div class="text-center md:text-left">
                                <p class="text-white/80 text-sm">Active Package</p>
                                <p class="text-lg font-bold text-white">
                                    <?php echo $active_package['icon']; ?> <?php echo $active_package['package_name']; ?>
                                </p>
                                <p class="text-sm text-white/70">ROI: <?php echo $active_package['roi_percentage']; ?>%</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Primary Actions - Updated button colors -->
                    <div class="flex flex-col space-y-3">
                        <!-- Deposit Funds - RED -->
                        <a href="/user/deposit.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition">
                            <i class="fas fa-plus mr-2"></i>Deposit Funds
                        </a>
                        <!-- Withdraw Funds - GREEN -->
                        <a href="/user/withdraw.php" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition">
                            <i class="fas fa-arrow-up mr-2"></i>Withdraw Funds
                        </a>
                        <!-- ACTIVATE TRADE NOW - HOT ORANGE -->
                        <a href="/user/packages.php" class="bg-orange-600 hover:bg-orange-700 text-white px-6 py-3 rounded-lg font-semibold text-center transition pulse-glow">
                            <i class="fas fa-rocket mr-2"></i>ACTIVATE TRADE NOW
                        </a>
                    </div>
                </div>
            </div>
        </section>
        <!-- Top Referrers Leaderboard -->
        <?php
        // Get top 3 referrers by total bonus earned (all time)
        $stmt = $db->prepare("
            SELECT 
                u.id,
                u.full_name,
                COALESCE(SUM(t.amount), 0) as total_bonus_earned
            FROM users u
            LEFT JOIN transactions t ON u.id = t.user_id 
                AND t.type = 'referral_commission' 
                AND t.status = 'completed'
            GROUP BY u.id, u.full_name
            HAVING total_bonus_earned > 0
            ORDER BY total_bonus_earned DESC
            LIMIT 3
        ");
        $stmt->execute();
        $top_referrers = $stmt->fetchAll();
        ?>
        <section class="mb-8">
            <div class="glass-card rounded-xl p-4">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <i class="fas fa-trophy text-yellow-400 text-xl mr-3"></i>
                        <h2 class="text-lg font-bold text-white">Top Referrers</h2>
                    </div>
                    <a href="/user/top-referrers.php" class="text-emerald-400 hover:text-emerald-300 text-sm font-medium flex items-center">
                        See More <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
                <?php if (!empty($top_referrers)): ?>
                    <div class="space-y-3">
                        <?php 
                        $rank = 1;
                        $medal_colors = [
                            1 => 'from-yellow-400 to-yellow-600',
                            2 => 'from-gray-300 to-gray-500',
                            3 => 'from-orange-400 to-orange-600'
                        ];
                        foreach ($top_referrers as $referrer): 
                            $medal_class = $medal_colors[$rank] ?? 'from-gray-400 to-gray-600';
                        ?>
                            <div class="flex items-center justify-between p-3 bg-gray-800/50 rounded-lg border border-gray-700 hover:border-emerald-500/50 transition">
                                <div class="flex items-center space-x-3 flex-1">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-r <?php echo $medal_class; ?> flex items-center justify-center text-white font-bold flex-shrink-0">
                                        <?php echo $rank; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white font-medium truncate"><?php echo htmlspecialchars($referrer['full_name']); ?></p>
                                        <p class="text-gray-400 text-xs">Total Bonus Earned</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-emerald-400 font-bold"><?php echo formatMoney($referrer['total_bonus_earned']); ?></p>
                                </div>
                            </div>
                        <?php 
                            $rank++;
                        endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-trophy text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No referrers yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        <!-- Live Activity Feed - Using Mock Data that rotates every 12 seconds -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-4">
                <div class="flex items-center mb-3">
                    <div class="w-3 h-3 bg-emerald-500 rounded-full pulse-dot mr-3"></div>
                    <h2 class="text-lg font-bold text-white">Live Activity Feed</h2>
                </div>
                <?php if (!empty($live_activity)): ?>
                    <!-- Desktop: Show all activities in grid -->
                    <div id="activityGridDesktop" class="hidden md:grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Activities will be dynamically inserted here -->
                    </div>
                    <!-- Mobile: Show one at a time with carousel -->
                    <div class="md:hidden activity-carousel">
                        <div id="activityCarouselMobile">
                            <!-- Activities will be dynamically inserted here -->
                        </div>
                        <!-- Carousel Dots -->
                        <div class="carousel-dots" id="carouselDots">
                            <!-- Dots will be dynamically inserted here -->
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <i class="fas fa-inbox text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No recent activity to display</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Live Forex Charts Section -->
        <section id="live-charts" class="py-12 sm:py-16 lg:py-20 bg-gray-800">
            <div class="container mx-auto px-4">
                <div class="text-center mb-8 sm:mb-12">
                    <h2 class="text-3xl sm:text-4xl lg:text-5xl font-bold mb-3 sm:mb-4 text-white">
                        Live <span class="text-emerald-400">Forex</span> Markets
                    </h2>
                    <p class="text-base sm:text-lg lg:text-xl text-gray-300">Real-time forex trading data at your fingertips</p>
                </div>

                <!-- TradingView Widget -->
                <div class="max-w-7xl mx-auto">
                    <div class="bg-gray-900 rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl border border-gray-700">
                        <div class="chart-wrapper">
                            <div id="tradingview_chart"></div>
                        </div>
                    </div>
                </div>

                <!-- Chart Info Cards -->
                <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6 mt-8 sm:mt-12 max-w-5xl mx-auto">
                    <div class="feature-card text-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-clock text-xl sm:text-2xl text-white"></i>
                        </div>
                        <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Real-Time Data</h3>
                        <p class="text-sm sm:text-base text-gray-400">Live market updates every second</p>
                    </div>
                    
                    <div class="feature-card text-center">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-globe text-xl sm:text-2xl text-white"></i>
                        </div>
                        <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Global Markets</h3>
                        <p class="text-sm sm:text-base text-gray-400">Trade major currency pairs worldwide</p>
                    </div>
                    
                    <div class="feature-card text-center sm:col-span-2 md:col-span-1">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 mx-auto mb-3 sm:mb-4 bg-emerald-500 rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line text-xl sm:text-2xl text-white"></i>
                        </div>
                        <h3 class="font-bold text-base sm:text-lg mb-2 text-white">Professional Tools</h3>
                        <p class="text-sm sm:text-base text-gray-400">Advanced charting and indicators</p>
                    </div>
                </div>
            </div>
        </section>
<br>
        <!-- Quick Actions -->
        <section class="mb-8">
            <div class="grid md:grid-cols-3 gap-6">
                <a href="/user/referrals.php" class="glass-card rounded-xl p-6 hover:bg-white/10 transition group">
                    <div class="flex items-center justify-between mb-4">
                        <i class="fas fa-users text-3xl text-purple-400 group-hover:scale-110 transition"></i>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-white transition"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Refer & Earn</h3>
                    <p class="text-gray-400 text-sm">Invite friends and earn commission</p>
                    <p class="text-emerald-400 font-bold mt-3">Code: <?php echo $user['referral_code']; ?></p>
                </a>
                <a href="/user/transactions.php" class="glass-card rounded-xl p-6 hover:bg-white/10 transition group">
                    <div class="flex items-center justify-between mb-4">
                        <i class="fas fa-receipt text-3xl text-blue-400 group-hover:scale-110 transition"></i>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-white transition"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Transactions</h3>
                    <p class="text-gray-400 text-sm">View your complete history</p>
                    <p class="text-yellow-400 font-bold mt-3">View All →</p>
                </a>
                <a href="/user/support.php" class="glass-card rounded-xl p-6 hover:bg-white/10 transition group">
                    <div class="flex items-center justify-between mb-4">
                        <i class="fas fa-headset text-3xl text-emerald-400 group-hover:scale-110 transition"></i>
                        <i class="fas fa-arrow-right text-gray-400 group-hover:text-white transition"></i>
                    </div>
                    <h3 class="text-lg font-bold text-white mb-2">Get Support</h3>
                    <p class="text-gray-400 text-sm">24/7 customer support</p>
                    <p class="text-emerald-400 font-bold mt-3">Contact Us →</p>
                </a>
            </div>
        </section>
    </main>
    <!-- Mobile Bottom Navigation - ADDED -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-50">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/referrals.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-users text-xl mb-1"></i>
                <span class="text-xs">Network</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/socials.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-share-alt text-xl mb-1"></i>
                <span class="text-xs">Socials</span>
            </a>
        </div>
    </div>
    <script>
    // MOCK DATA FROM EXCEL - This will rotate through different entries
    const activityDataPool = [
        {name: 'Grace Kamau', action: 'withdrew profits', amount: 168147, type: 'withdrawal'},
        {name: 'Samuel Kiprotich', action: 'withdrew profits', amount: 235714, type: 'withdrawal'},
        {name: 'Daniel Wafula', action: 'withdrew profits', amount: 76460, type: 'withdrawal'},
        {name: 'James Mutua', action: 'withdrew profits', amount: 67989, type: 'withdrawal'},
        {name: 'Cynthia Wambui', action: 'withdrew profits', amount: 248275, type: 'withdrawal'},
        {name: 'Sharon Ochieng', action: 'withdrew profits', amount: 88428, type: 'withdrawal'},
        {name: 'Nicholas Njoroge', action: 'withdrew profits', amount: 225384, type: 'withdrawal'},
        {name: 'Nicholas Wekesa', action: 'withdrew profits', amount: 238658, type: 'withdrawal'},
        {name: 'Esther Nyambura', action: 'withdrew profits', amount: 259285, type: 'withdrawal'},
        {name: 'Samuel Mutiso', action: 'withdrew profits', amount: 275379, type: 'withdrawal'},
        {name: 'Joseph Cherono', action: 'withdrew profits', amount: 289148, type: 'withdrawal'},
        {name: 'Stella Kamau', action: 'completed copy trade', amount: 199966, type: 'trade'},
        {name: 'David Barasa', action: 'completed copy trade', amount: 236749, type: 'trade'},
        {name: 'Daniel Wanjiru', action: 'completed copy trade', amount: 264982, type: 'trade'},
        {name: 'Naomi Naliaka', action: 'completed copy trade', amount: 284539, type: 'trade'},
        {name: 'Mercy Cherono', action: 'completed copy trade', amount: 259853, type: 'trade'},
        {name: 'Faith Wambui', action: 'completed copy trade', amount: 224695, type: 'trade'},
        {name: 'Peter Omondi', action: 'completed copy trade', amount: 156823, type: 'trade'},
        {name: 'Lucy Njeri', action: 'completed copy trade', amount: 189456, type: 'trade'},
        {name: 'Michael Otieno', action: 'completed copy trade', amount: 212390, type: 'trade'},
        {name: 'Mary Otieno', action: 'deposited', amount: 89347, type: 'deposit'},
        {name: 'Joseph Odhiambo', action: 'deposited', amount: 74219, type: 'deposit'},
        {name: 'Mary Odhiambo', action: 'deposited', amount: 62254, type: 'deposit'},
        {name: 'George Obiero', action: 'deposited', amount: 4233, type: 'deposit'},
        {name: 'Brian Wafula', action: 'deposited', amount: 81483, type: 'deposit'},
        {name: 'Jane Achieng', action: 'deposited', amount: 117845, type: 'deposit'},
        {name: 'Grace Wanjiru', action: 'deposited', amount: 123456, type: 'deposit'},
        {name: 'Patrick Kimani', action: 'deposited', amount: 98765, type: 'deposit'},
        {name: 'Susan Achieng', action: 'deposited', amount: 145678, type: 'deposit'},
        {name: 'Winnie Wairimu', action: 'earned referral bonus', amount: 9434, type: 'referral'},
        {name: 'Ann Kiptoo', action: 'earned referral bonus', amount: 2471, type: 'referral'},
        {name: 'George Atieno', action: 'earned referral bonus', amount: 12081, type: 'referral'},
        {name: 'Stella Otieno', action: 'earned referral bonus', amount: 2687, type: 'referral'},
        {name: 'Ann Mworia', action: 'earned referral bonus', amount: 8935, type: 'referral'},
        {name: 'Carol Makau', action: 'activated trading', amount: 43711, type: 'package'},
        {name: 'Mary Mworia', action: 'activated trading', amount: 235914, type: 'package'},
        {name: 'James Njoroge', action: 'activated trading', amount: 218718, type: 'package'},
        {name: 'Sharon Kariuki', action: 'activated trading', amount: 34791, type: 'package'},
        {name: 'Alice Wairimu', action: 'deposited', amount: 54321, type: 'deposit'},
        {name: 'Mark Ndunge', action: 'withdrew profits', amount: 131200, type: 'withdrawal'},
        {name: 'Rebecca Ouma', action: 'earned referral bonus', amount: 4020, type: 'referral'},
        {name: 'Pauline Karanja', action: 'activated trading', amount: 98000, type: 'package'},
        {name: 'Tommy Okello', action: 'completed copy trade', amount: 152900, type: 'trade'},
        {name: 'Irene Njoki', action: 'deposited', amount: 72000, type: 'deposit'},
        {name: 'Samuel Odongo', action: 'earned referral bonus', amount: 5510, type: 'referral'},
        {name: 'Hillary Mugo', action: 'withdrew profits', amount: 203450, type: 'withdrawal'},
        {name: 'Zainab Abdullahi', action: 'activated trading', amount: 124500, type: 'package'},
        {name: 'Eric Sang', action: 'deposited', amount: 46200, type: 'deposit'},
        {name: 'Wambui Chebet', action: 'completed copy trade', amount: 179340, type: 'trade'},
        {name: 'Michael Owuor', action: 'earned referral bonus', amount: 2499, type: 'referral'},
        {name: 'Esther Kibet', action: 'withdrew profits', amount: 88760, type: 'withdrawal'},
        {name: 'Patrick Oduor', action: 'activated trading', amount: 211000, type: 'package'},
        {name: 'Rachael Mwikali', action: 'deposited', amount: 99300, type: 'deposit'},
        {name: 'Omondi Baraza', action: 'completed copy trade', amount: 145670, type: 'trade'},
        {name: 'Christine Kituyi', action: 'earned referral bonus', amount: 6340, type: 'referral'},
        {name: 'Peter Kairo', action: 'withdrew profits', amount: 157420, type: 'withdrawal'},
        {name: 'Susan Njeri', action: 'deposited', amount: 34120, type: 'deposit'},
        {name: 'Felix Kamau', action: 'activated trading', amount: 189999, type: 'package'},
        {name: 'Lorna Auma', action: 'completed copy trade', amount: 122450, type: 'trade'},
        {name: 'Victor Mutua', action: 'earned referral bonus', amount: 8790, type: 'referral'},
        {name: 'Noel Wanyama', action: 'withdrew profits', amount: 212300, type: 'withdrawal'},
        {name: 'Brenda Naliaka', action: 'deposited', amount: 55890, type: 'deposit'},
        {name: 'Karani Chepngeno', action: 'completed copy trade', amount: 198765, type: 'trade'},
        {name: 'Moses Bett', action: 'activated trading', amount: 145200, type: 'package'},
        {name: 'Anne Wepukhulu', action: 'earned referral bonus', amount: 3790, type: 'referral'},
        {name: 'Roland Onyango', action: 'deposited', amount: 77230, type: 'deposit'},
        {name: 'Mercy Kerubo', action: 'withdrew profits', amount: 134900, type: 'withdrawal'},
        {name: 'Abdi Mohamed', action: 'completed copy trade', amount: 203410, type: 'trade'},
        {name: 'Judy Mutheu', action: 'earned referral bonus', amount: 2200, type: 'referral'},
        {name: 'George Kapkory', action: 'deposited', amount: 41560, type: 'deposit'},
        {name: 'Nora Wekesa', action: 'activated trading', amount: 276450, type: 'package'},
        {name: 'Dennis Kariuki', action: 'completed copy trade', amount: 163820, type: 'trade'},
        {name: 'Phyllis Chepngeno', action: 'deposited', amount: 90450, type: 'deposit'},
        {name: 'Barnabas Komen', action: 'earned referral bonus', amount: 5880, type: 'referral'},
        {name: 'Millicent Achieng', action: 'withdrew profits', amount: 101230, type: 'withdrawal'},
        {name: 'Charles Wafula', action: 'deposited', amount: 67300, type: 'deposit'},
        {name: 'Janet Kirui', action: 'completed copy trade', amount: 219450, type: 'trade'},
        {name: 'Osman Farah', action: 'activated trading', amount: 154900, type: 'package'},
        {name: 'Nafula Atieno', action: 'earned referral bonus', amount: 4210, type: 'referral'},
        {name: 'Edwin Langat', action: 'withdrew profits', amount: 187620, type: 'withdrawal'},
        {name: 'Grace Jepkosgei', action: 'deposited', amount: 25000, type: 'deposit'},
        {name: 'Daniela Mwikali', action: 'completed copy trade', amount: 132880, type: 'trade'},
        {name: 'Hussein Omar', action: 'earned referral bonus', amount: 3150, type: 'referral'},
        {name: 'Owen Murungi', action: 'activated trading', amount: 201100, type: 'package'},
        {name: 'Faith Jemutai', action: 'deposited', amount: 118450, type: 'deposit'},
        {name: 'Collins Ndegwa', action: 'withdrew profits', amount: 94050, type: 'withdrawal'},
        {name: 'Becky Anyango', action: 'completed copy trade', amount: 176200, type: 'trade'},
        {name: 'Toni Chepkirui', action: 'earned referral bonus', amount: 4999, type: 'referral'},
        {name: 'Immaculate Nyambura', action: 'activated trading', amount: 128700, type: 'package'},
        {name: 'Ronald Kiptoo', action: 'deposited', amount: 83210, type: 'deposit'},
        {name: 'Brian Mwangi', action: 'deposited', amount: 118564, type: 'deposit'},
    {name: 'Nancy Cherono', action: 'earned referral bonus', amount: 7421, type: 'referral'},
    {name: 'Collins Wekesa', action: 'activated trading', amount: 205784, type: 'package'},
    {name: 'Betty Naliaka', action: 'withdrew profits', amount: 165437, type: 'withdrawal'},
    {name: 'George Otieno', action: 'completed copy trade', amount: 196543, type: 'trade'},
    {name: 'Hannah Njeri', action: 'deposited', amount: 123875, type: 'deposit'},
    {name: 'Isaac Kiplangat', action: 'earned referral bonus', amount: 8569, type: 'referral'},
    {name: 'Naomi Wambua', action: 'activated trading', amount: 257431, type: 'package'},
    {name: 'Fredrick Karanja', action: 'withdrew profits', amount: 184219, type: 'withdrawal'},
    {name: 'Mercy Chepkemoi', action: 'completed copy trade', amount: 223571, type: 'trade'},
    {name: 'Kennedy Waweru', action: 'deposited', amount: 97846, type: 'deposit'},
    {name: 'Diana Achieng', action: 'earned referral bonus', amount: 6794, type: 'referral'},
    {name: 'Caleb Maina', action: 'activated trading', amount: 188231, type: 'package'},
    {name: 'Josphine Mwikali', action: 'withdrew profits', amount: 142965, type: 'withdrawal'},
    {name: 'Peter Kiplagat', action: 'completed copy trade', amount: 237659, type: 'trade'},
    {name: 'Lilian Wairimu', action: 'deposited', amount: 109872, type: 'deposit'},
    {name: 'Victor Kipkoech', action: 'earned referral bonus', amount: 9156, type: 'referral'},
    {name: 'Beatrice Adhiambo', action: 'activated trading', amount: 243781, type: 'package'},
    {name: 'Stephen Njoroge', action: 'withdrew profits', amount: 168947, type: 'withdrawal'},
    {name: 'Miriam Chebet', action: 'completed copy trade', amount: 212469, type: 'trade'},
    {name: 'Johnstone Kimutai', action: 'deposited', amount: 103561, type: 'deposit'},
    {name: 'Alice Wanjiku', action: 'earned referral bonus', amount: 7368, type: 'referral'},
    {name: 'Paul Otieno', action: 'activated trading', amount: 229437, type: 'package'},
    {name: 'Sarah Nyambura', action: 'withdrew profits', amount: 197543, type: 'withdrawal'},
    {name: 'Kelvin Kipruto', action: 'completed copy trade', amount: 183295, type: 'trade'},
    {name: 'Dennis Mutai', action: 'deposited', amount: 120849, type: 'deposit'},
    {name: 'Faith Naliaka', action: 'earned referral bonus', amount: 8475, type: 'referral'},
    {name: 'Ruth Chepngeno', action: 'activated trading', amount: 258639, type: 'package'},
    {name: 'Andrew Otieno', action: 'withdrew profits', amount: 175384, type: 'withdrawal'},
    {name: 'Nancy Wekesa', action: 'completed copy trade', amount: 191647, type: 'trade'},
    {name: 'Kevin Mworia', action: 'deposited', amount: 114678, type: 'deposit'},
    {name: 'Gloria Wairimu', action: 'earned referral bonus', amount: 6523, type: 'referral'},
    {name: 'Jackson Kiptoo', action: 'activated trading', amount: 219874, type: 'package'},
    {name: 'Esther Naliaka', action: 'withdrew profits', amount: 189546, type: 'withdrawal'},
    {name: 'Chris Wanyonyi', action: 'completed copy trade', amount: 203871, type: 'trade'},
    {name: 'Rachael Chebet', action: 'deposited', amount: 134295, type: 'deposit'},
    {name: 'Patrick Kiprotich', action: 'earned referral bonus', amount: 7982, type: 'referral'},
    {name: 'Nancy Njeri', action: 'activated trading', amount: 231589, type: 'package'},
    {name: 'Vincent Mwangi', action: 'withdrew profits', amount: 162879, type: 'withdrawal'},
    {name: 'Mary Aoko', action: 'completed copy trade', amount: 218476, type: 'trade'},
    {name: 'Alex Cheruiyot', action: 'deposited', amount: 119382, type: 'deposit'},
    {name: 'Juliet Wangui', action: 'earned referral bonus', amount: 6874, type: 'referral'},
    {name: 'David Kimeli', action: 'activated trading', amount: 247389, type: 'package'},
    {name: 'Sharon Njeri', action: 'withdrew profits', amount: 176845, type: 'withdrawal'},
    {name: 'Elijah Otieno', action: 'completed copy trade', amount: 195768, type: 'trade'},
    {name: 'Joyce Wambui', action: 'deposited', amount: 128465, type: 'deposit'},
    {name: 'Calvin Kiptoo', action: 'earned referral bonus', amount: 7281, type: 'referral'},
    {name: 'Joseph Wekesa', action: 'activated trading', amount: 265438, type: 'package'},
    {name: 'Angela Chepkirui', action: 'deposited', amount: 112549, type: 'deposit'},
    {name: 'Martin Wanyama', action: 'withdrew profits', amount: 176238, type: 'withdrawal'},
    {name: 'Terry Wanjiku', action: 'earned referral bonus', amount: 9321, type: 'referral'},
    {name: 'Leonard Kipkoech', action: 'completed copy trade', amount: 202675, type: 'trade'},
    {name: 'Christine Achieng', action: 'activated trading', amount: 257864, type: 'package'},
    {name: 'Noel Kamande', action: 'deposited', amount: 98732, type: 'deposit'},
    {name: 'Janet Chepchirchir', action: 'withdrew profits', amount: 187561, type: 'withdrawal'},
    {name: 'George Mworia', action: 'completed copy trade', amount: 213459, type: 'trade'},
    {name: 'Lucy Akoth', action: 'earned referral bonus', amount: 8756, type: 'referral'},
    {name: 'Francis Otieno', action: 'activated trading', amount: 235978, type: 'package'},
    {name: 'Caroline Wairimu', action: 'deposited', amount: 119768, type: 'deposit'},
    {name: 'Peter Kiptoo', action: 'withdrew profits', amount: 169754, type: 'withdrawal'},
    {name: 'Rebecca Chebet', action: 'completed copy trade', amount: 201459, type: 'trade'},
    {name: 'Jackson Njoroge', action: 'earned referral bonus', amount: 7695, type: 'referral'},
    {name: 'Sarah Atieno', action: 'activated trading', amount: 241965, type: 'package'},
    {name: 'Faith Wanjiku', action: 'deposited', amount: 104739, type: 'deposit'},
    {name: 'Simon Kiptoo', action: 'withdrew profits', amount: 192381, type: 'withdrawal'},
    {name: 'Eunice Nyambura', action: 'completed copy trade', amount: 189276, type: 'trade'},
    {name: 'Tom Muli', action: 'earned referral bonus', amount: 8459, type: 'referral'},
    {name: 'Catherine Akinyi', action: 'activated trading', amount: 256713, type: 'package'},
    {name: 'Wilfred Kiprono', action: 'deposited', amount: 117294, type: 'deposit'},
    {name: 'Rose Wairimu', action: 'withdrew profits', amount: 163981, type: 'withdrawal'},
    {name: 'Kevin Barasa', action: 'completed copy trade', amount: 219654, type: 'trade'},
    {name: 'Gloria Chepkemoi', action: 'earned referral bonus', amount: 6931, type: 'referral'},
    {name: 'Dennis Kariuki', action: 'activated trading', amount: 274312, type: 'package'},
    {name: 'Margaret Nyambura', action: 'deposited', amount: 127843, type: 'deposit'},
    {name: 'Paul Kipruto', action: 'withdrew profits', amount: 174965, type: 'withdrawal'},
    {name: 'Vivian Chebet', action: 'completed copy trade', amount: 195837, type: 'trade'},
    {name: 'Stephen Onyango', action: 'earned referral bonus', amount: 8219, type: 'referral'},
    {name: 'Joyce Mumbua', action: 'activated trading', amount: 232491, type: 'package'},
    {name: 'Anthony Kiplangat', action: 'deposited', amount: 113678, type: 'deposit'},
    {name: 'Mercy Naliaka', action: 'withdrew profits', amount: 182973, type: 'withdrawal'},
    {name: 'Collins Wambua', action: 'completed copy trade', amount: 207819, type: 'trade'},
    {name: 'Jane Wairimu', action: 'earned referral bonus', amount: 7698, type: 'referral'},
    {name: 'Brian Kiptoo', action: 'activated trading', amount: 246915, type: 'package'},
    {name: 'Dorcas Achieng', action: 'deposited', amount: 132874, type: 'deposit'},
    {name: 'Josephine Mwende', action: 'withdrew profits', amount: 193784, type: 'withdrawal'},
    {name: 'David Chepkirui', action: 'completed copy trade', amount: 222471, type: 'trade'},
    {name: 'Patricia Wanjiru', action: 'earned referral bonus', amount: 6827, type: 'referral'},
    {name: 'Nicholas Kipruto', action: 'activated trading', amount: 258374, type: 'package'},
    {name: 'Tracy Wekesa', action: 'deposited', amount: 109574, type: 'deposit'},
    {name: 'Benard Otieno', action: 'withdrew profits', amount: 179846, type: 'withdrawal'},
    {name: 'Lydia Chepchumba', action: 'completed copy trade', amount: 194573, type: 'trade'},
    {name: 'Michael Kimutai', action: 'earned referral bonus', amount: 8162, type: 'referral'},
    {name: 'Ann Mwikali', action: 'activated trading', amount: 239756, type: 'package'},
    {name: 'Samuel Wekesa', action: 'deposited', amount: 124975, type: 'deposit'},
    {name: 'Florence Wairimu', action: 'withdrew profits', amount: 185319, type: 'withdrawal'},
    {name: 'George Kiptoo', action: 'completed copy trade', amount: 216749, type: 'trade'},
    {name: 'Abigail Chebet', action: 'earned referral bonus', amount: 8994, type: 'referral'},
    {name: 'Peter Mwangi', action: 'activated trading', amount: 255613, type: 'package'},
        {name: 'David Kiptoo', action: 'activated trading', amount: 18231, type: 'package'}
    ];

        let currentActivitySet = [];
        let currentSlide = 0;
        // Format money
        function formatMoney(amount) {
            return 'KSH ' + amount.toLocaleString('en-KE', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });
        }
        // Get random activities
        function getRandomActivities(count) {
            const shuffled = [...activityDataPool].sort(() => 0.5 - Math.random());
            return shuffled.slice(0, count);
        }
        // Generate time ago text
        function generateTimeAgo() {
            const times = ['4 mins ago', '15 mins ago', '30 mins ago', '40 mins ago', '50 mins ago', '1 hrs ago', '1hrs 15min mins ago', '2 hrs ago'];
            return times[Math.floor(Math.random() * times.length)];
        }
        // Create activity card HTML
        function createActivityCard(activity, isMobile = false) {
            const initials = activity.name.split(' ').map(n => n[0]).join('').toUpperCase();
            const timeAgo = generateTimeAgo();
            if (isMobile) {
                return `
                    <div class="activity-card bg-gray-800/50 rounded-lg p-6 border border-gray-700">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-14 h-14 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center text-lg font-bold flex-shrink-0">
                                ${initials}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-white font-bold text-lg">${activity.name}</p>
                                <p class="text-gray-400 text-sm">${timeAgo}</p>
                            </div>
                        </div>
                        <div class="bg-gray-900/50 rounded-lg p-4">
                            <p class="text-gray-300 text-base mb-3">${activity.action.charAt(0).toUpperCase() + activity.action.slice(1)}</p>
                            <p class="text-emerald-400 font-bold text-2xl">${formatMoney(activity.amount)}</p>
                        </div>
                    </div>
                `;
            } else {
                return `
                    <div class="activity-card bg-gray-800/50 rounded-lg p-4 border border-gray-700 hover:border-emerald-500/50">
                        <div class="flex items-center space-x-3 mb-3">
                            <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">
                                ${initials}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-white font-medium truncate">${activity.name.split(' ')[0]}</p>
                                <p class="text-gray-400 text-xs">${timeAgo}</p>
                            </div>
                        </div>
                        <p class="text-gray-300 text-sm mb-2">${activity.action.charAt(0).toUpperCase() + activity.action.slice(1)}</p>
                        <p class="text-emerald-400 font-bold text-lg">${formatMoney(activity.amount)}</p>
                    </div>
                `;
            }
        }
        // Update desktop grid
        function updateDesktopGrid() {
            const grid = document.getElementById('activityGridDesktop');
            if (!grid) return;
            currentActivitySet = getRandomActivities(8);
            grid.innerHTML = currentActivitySet.map(activity => createActivityCard(activity, false)).join('');
        }
        // Update mobile carousel
        function updateMobileCarousel() {
            const carousel = document.getElementById('activityCarouselMobile');
            const dots = document.getElementById('carouselDots');
            if (!carousel || !dots) return;
            currentActivitySet = getRandomActivities(8);
            // Create slides
            carousel.innerHTML = currentActivitySet.map((activity, index) => `
                <div class="activity-slide ${index === 0 ? 'active' : ''}" data-slide="${index}">
                    ${createActivityCard(activity, true)}
                </div>
            `).join('');
            // Create dots
            dots.innerHTML = currentActivitySet.map((_, index) => `
                <div class="carousel-dot ${index === 0 ? 'active' : ''}" data-slide="${index}"></div>
            `).join('');
            // Add click handlers to dots
            document.querySelectorAll('.carousel-dot').forEach((dot, index) => {
                dot.addEventListener('click', function() {
                    currentSlide = index;
                    showSlide(currentSlide);
                });
            });
            currentSlide = 0;
        }
        // Show specific slide
        function showSlide(index) {
            const slides = document.querySelectorAll('.activity-slide');
            const dots = document.querySelectorAll('.carousel-dot');
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            if (slides[index]) {
                slides[index].classList.add('active');
                dots[index].classList.add('active');
            }
        }
        // Next slide for mobile
        function nextSlide() {
            currentSlide = (currentSlide + 1) % currentActivitySet.length;
            showSlide(currentSlide);
        }
        // Initialize activities
        function initializeActivities() {
            updateDesktopGrid();
            updateMobileCarousel();
        }
        // Auto-advance mobile carousel every 12 seconds
        setInterval(nextSlide, 12000);
        // Rotate all activities every 12 seconds (you can change this to 12 hours = 43200000)
        setInterval(initializeActivities, 12000); // Change to 43200000 for 12 hours
        // Debug function for mobile testing
        function debugNotification() {
            console.log('Notification Bell Clicked');
            console.log('Screen width:', window.innerWidth);
            console.log('Is mobile:', window.innerWidth < 768);
            console.log('Dropdown element:', document.getElementById('notificationDropdown'));
            console.log('Overlay element:', document.getElementById('notificationOverlay'));
        }
        
        // Function to update notification badge count
        function updateNotificationBadge() {
            fetch('/api/get-unread-count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('notificationBadge');
                        const unreadCount = data.count;
                        
                        if (badge) {
                            if (unreadCount > 0) {
                                badge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                                badge.setAttribute('data-count', unreadCount);
                                badge.classList.remove('hidden');
                            } else {
                                badge.classList.add('hidden');
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating notification badge:', error);
                });
        }

        // Function to mark notification as read
        function markNotificationAsRead(notificationId, notificationElement) {
            fetch('/api/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notification_id: parseInt(notificationId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update the notification element
                    if (notificationElement) {
                        notificationElement.classList.remove('bg-gray-700/30');
                        notificationElement.classList.add('bg-gray-800/30');
                        const unreadDot = notificationElement.querySelector('.w-2.h-2.bg-emerald-400');
                        if (unreadDot) {
                            unreadDot.remove();
                        }
                    }
                    // Update badge count
                    updateNotificationBadge();
                }
            })
            .catch(error => {
                console.error('Error marking notification as read:', error);
            });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeActivities();
            
            // Update notification badge every 30 seconds
            updateNotificationBadge();
            setInterval(updateNotificationBadge, 30000);
            
            // Handle notification clicks - just let the link work normally
            // The notifications.php page will handle marking as read
            document.querySelectorAll('.notification-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    // Allow normal link navigation - don't prevent default
                    // The notifications.php page will handle marking as read
                });
            });
            
            // Notification dropdown - Fully Responsive
            const bell = document.getElementById('notificationBell');
            const dropdown = document.getElementById('notificationDropdown');
            const closeBtn = document.getElementById('closeNotifications');
            const overlay = document.getElementById('notificationOverlay');
            function openNotifications() {
                dropdown.classList.remove('hidden');
                overlay.classList.add('active');
                // Prevent body scroll on mobile
                if (window.innerWidth < 768) {
                    document.body.style.overflow = 'hidden';
                    document.body.style.position = 'fixed';
                    document.body.style.width = '100%';
                }
            }
            function closeNotifications() {
                dropdown.classList.add('hidden');
                overlay.classList.remove('active');
                // Restore body scroll on mobile
                if (window.innerWidth < 768) {
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.width = '';
                }
            }
            bell.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                debugNotification(); // Debug call
                if (dropdown.classList.contains('hidden')) {
                    openNotifications();
                } else {
                    closeNotifications();
                }
            });
            bell.addEventListener('touchend', function(e) {
                e.stopPropagation();
                e.preventDefault();
                if (dropdown.classList.contains('hidden')) {
                    openNotifications();
                } else {
                    closeNotifications();
                }
            }, { passive: false }); // Ensure preventDefault works on touch
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                closeNotifications();
            });
            closeBtn.addEventListener('touchend', function(e) {
                e.stopPropagation();
                e.preventDefault();
                closeNotifications();
            }, { passive: false });
            // Close on overlay click (mobile)
            overlay.addEventListener('click', function() {
                closeNotifications();
            });
            overlay.addEventListener('touchend', function(e) {
                e.stopPropagation();
                e.preventDefault();
                closeNotifications();
            }, { passive: false });
            // Close on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !dropdown.classList.contains('hidden')) {
                    closeNotifications();
                }
            });
            // Close when clicking outside (desktop only)
            document.addEventListener('click', function(e) {
                if (window.innerWidth >= 768) {
                    if (!dropdown.contains(e.target) && !bell.contains(e.target)) {
                        closeNotifications();
                    }
                }
            });
            // Mobile Menu Toggle
            const mobileMenuButton = document.getElementById('mobileMenuButton');
            const mobileMenu = document.getElementById('mobileMenu');
            const closeMobileMenu = document.getElementById('closeMobileMenu');
            function toggleMobileMenu() {
                mobileMenu.classList.toggle('active');
                mobileMenu.classList.toggle('hidden');
                if (mobileMenu.classList.contains('active')) {
                    document.body.style.overflow = 'hidden';
                    document.body.style.position = 'fixed';
                    document.body.style.width = '100%';
                } else {
                    document.body.style.overflow = '';
                    document.body.style.position = '';
                    document.body.style.width = '';
                }
            }
            mobileMenuButton.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
            mobileMenuButton.addEventListener('touchstart', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
            closeMobileMenu.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
            closeMobileMenu.addEventListener('touchstart', function(e) {
                e.preventDefault();
                e.stopPropagation();
                toggleMobileMenu();
            });
        });
        function copyReferralCode() {
            const code = '<?php echo $user['referral_code']; ?>';
            navigator.clipboard.writeText(code).then(function() {
                alert('Referral code copied to clipboard!');
            }).catch(function() {
                const textArea = document.createElement('textarea');
                textArea.value = code;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                alert('Referral code copied to clipboard!');
            });
        }
    </script>
    
    <!-- TradingView Widget Scripts -->
    <script type="text/javascript" src="https://s3.tradingview.com/tv.js"></script>
    <script type="text/javascript">
        // Check screen size
        const isMobile = window.innerWidth < 640;
        
        // Advanced Chart Widget
        new TradingView.widget({
            "width": "100%",
            "height": isMobile ? 400 : 600,
            "symbol": "FX:EURUSD",
            "interval": "D",
            "timezone": "Africa/Nairobi",
            "theme": "dark",
            "style": "1",
            "locale": "en",
            "toolbar_bg": "#f1f3f6",
            "enable_publishing": false,
            "allow_symbol_change": true,
            "container_id": "tradingview_chart",
            "hide_top_toolbar": false,
            "hide_legend": false,
            "save_image": false,
            "backgroundColor": "rgba(17, 24, 39, 1)",
            "gridColor": "rgba(55, 65, 81, 0.3)",
            "studies": [
                "MASimple@tv-basicstudies"
            ]
        });
    </script>
    <!-- Live Chat Support Widget -->
    <?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>
</body>
</html>