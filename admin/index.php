<?php
require_once '../config/database.php';
requireAdmin();

// Set default timezone (adjust to your timezone - Kenya is EAT: UTC+3)
date_default_timezone_set('Africa/Nairobi');

// Also set MySQL timezone to match
try {
    $db->exec("SET time_zone = '+03:00'");
} catch (Exception $e) {
    error_log("Failed to set MySQL timezone: " . $e->getMessage());
}

// Get dashboard statistics directly from tables
$stats = [];

// Get user counts
$stmt = $db->query("SELECT COUNT(*) as total_users FROM users");
$stats['total_users'] = $stmt->fetch()['total_users'];

$stmt = $db->query("SELECT COUNT(*) as active_users FROM users WHERE status = 'active'");
$stats['active_users'] = $stmt->fetch()['active_users'];

// Get transaction totals
$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total_deposits FROM transactions WHERE type = 'deposit' AND status = 'completed'");
$stats['total_deposits'] = $stmt->fetch()['total_deposits'];

$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total_withdrawals FROM transactions WHERE type = 'withdrawal' AND status = 'completed'");
$stats['total_withdrawals'] = $stmt->fetch()['total_withdrawals'];

$stmt = $db->query("SELECT COALESCE(SUM(amount), 0) as total_roi_paid FROM transactions WHERE type = 'roi_payment' AND status = 'completed'");
$stats['total_roi_paid'] = $stmt->fetch()['total_roi_paid'];

// Get user balances
$stmt = $db->query("SELECT COALESCE(SUM(wallet_balance), 0) as total_user_balances FROM users");
$stats['total_user_balances'] = $stmt->fetch()['total_user_balances'];

// Get active packages
$stmt = $db->query("SELECT COUNT(*) as active_packages FROM active_packages WHERE status = 'active'");
$stats['active_packages'] = $stmt->fetch()['active_packages'];

$stmt = $db->query("SELECT COALESCE(SUM(investment_amount), 0) as total_active_investments FROM active_packages WHERE status = 'active'");
$stats['total_active_investments'] = $stmt->fetch()['total_active_investments'];

// Get pending ROI obligations
$stmt = $db->query("SELECT COALESCE(SUM(expected_roi), 0) as pending_roi_obligations FROM active_packages WHERE status = 'active'");
$stats['pending_roi_obligations'] = $stmt->fetch()['pending_roi_obligations'];

// Get unpaid referral amounts
$stmt = $db->query("SELECT COALESCE(SUM(referral_earnings), 0) as unpaid_referral_amounts FROM users");
$stats['unpaid_referral_amounts'] = $stmt->fetch()['unpaid_referral_amounts'];

// Calculate system health metrics
// Include completed admin capital injections in platform liquidity calculation
// This ensures injected capital automatically improves platform liquidity
$admin_injections = getCompletedAdminInjections($db);
$platform_liquidity = ($stats['total_deposits'] - $stats['total_withdrawals']) + $admin_injections;
$total_liabilities = $stats['total_user_balances'] + $stats['total_active_investments'] + $stats['pending_roi_obligations'] + $stats['unpaid_referral_amounts'];
$coverage_ratio = $total_liabilities > 0 ? $platform_liquidity / $total_liabilities : 1;

// Get recent transactions
$stmt = $db->query("
    SELECT t.*, u.full_name, u.email 
    FROM transactions t 
    JOIN users u ON t.user_id = u.id 
    ORDER BY t.created_at DESC 
    LIMIT 10
");
$recent_transactions = $stmt->fetchAll();

// Get system health alerts
$alerts = [];
if ($coverage_ratio < 1) {
    $alerts[] = ['type' => 'critical', 'message' => 'System cannot cover all liabilities! Coverage ratio: ' . number_format($coverage_ratio * 100, 2) . '%'];
} elseif ($coverage_ratio < 1.2) {
    $alerts[] = ['type' => 'warning', 'message' => 'Low coverage ratio: ' . number_format($coverage_ratio * 100, 2) . '%'];
}

// Get pending withdrawals count
$stmt = $db->query("SELECT COUNT(*) as pending_withdrawals FROM transactions WHERE type = 'withdrawal' AND status = 'pending'");
$pending_withdrawals = $stmt->fetch()['pending_withdrawals'];

if ($pending_withdrawals > 0) {
    $alerts[] = ['type' => 'info', 'message' => "$pending_withdrawals withdrawal requests pending approval"];
}

// Get monthly revenue data for chart
$stmt = $db->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END) as deposits,
        SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END) as withdrawals
    FROM transactions 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$monthly_data = $stmt->fetchAll();

// Log system health
logSystemHealth();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-critical { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .status-warning { color: #f59e0b; background: rgba(245, 158, 11, 0.1); }
        .status-info { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
        .status-success { color: #10b981; background: rgba(16, 185, 129, 0.1); }
        
        .metric-card {
            transition: all 0.3s ease;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header -->
<!-- Reusable Admin Header with Wallet & Logout -->
<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <!-- Left: Logo and Navigation -->
            <div class="flex items-center space-x-8">
                <!-- Logo -->
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                        <p class="text-xs text-gray-400">Global - Admin</p>
                    </div>
                </div>
                
                <!-- Desktop Navigation -->
                <nav class="hidden lg:flex space-x-6">
                    <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                    <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                    <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                    <a href="/admin/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                    <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                    <a href="/admin/chat.php" class="text-gray-300 hover:text-emerald-400 transition relative">
                        Live Chat
                        <?php
                        // Show unread chat count badge
                        try {
                            $stmt = $db->query("
                                SELECT COUNT(DISTINCT cm.chat_id) as unread_count
                                FROM chat_messages cm
                                JOIN chat_sessions cs ON cm.chat_id = cs.id
                                WHERE cm.sender_role = 'user' AND cm.is_read = 0 AND cs.status = 'open'
                            ");
                            $unread_result = $stmt->fetch();
                            $unread_chat_count = (int)($unread_result['unread_count'] ?? 0);
                            if ($unread_chat_count > 0) {
                                echo '<span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">' . $unread_chat_count . '</span>';
                            }
                        } catch (Exception $e) {
                            // Silently fail
                        }
                        ?>
                    </a>
                    <a href="/admin/tickets.php" class="text-gray-300 hover:text-emerald-400 transition">Support</a>
                    <a href="/admin/settings.php" class="text-gray-300 hover:text-emerald-400 transition">Settings</a>
                </nav>
            </div>

            <!-- Right: Wallet, Logout & Mobile Menu -->
            <div class="flex items-center space-x-3">
                <!-- Wallet Icon -->
                <a href="/admin/admin-wallet.php" class="relative group">
                    <div class="flex items-center space-x-2 px-3 sm:px-4 py-2 rounded-lg bg-gradient-to-r from-emerald-500/10 to-yellow-500/10 border border-emerald-500/20 hover:border-emerald-500/40 transition-all duration-300 group-hover:shadow-lg group-hover:shadow-emerald-500/20">
                        <!-- Wallet Icon with Online Indicator -->
                        <div class="relative">
                            <svg class="w-5 h-5 text-emerald-400 group-hover:text-emerald-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            <!-- Online Indicator Dot -->
                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                        </div>
                        <!-- Wallet Text (Hidden on Mobile) -->
                        <span class="hidden sm:inline text-sm font-medium text-emerald-400 group-hover:text-emerald-300 transition">Wallet</span>
                    </div>
                </a>

                <!-- Logout Button (Desktop) -->
                <a href="/logout.php" class="hidden md:flex items-center space-x-2 px-4 py-2 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 border border-red-500/20 hover:border-red-500/40 transition-all duration-300 group hover:shadow-lg hover:shadow-red-500/20">
                    <svg class="w-5 h-5 text-red-400 group-hover:text-red-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="text-sm font-medium text-red-400 group-hover:text-red-300 transition">Logout</span>
                </a>

                <!-- Logout Icon (Mobile Only) -->
                <a href="/logout.php" class="md:hidden p-2 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 border border-red-500/20 hover:border-red-500/40 transition-all duration-300">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                </a>

                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="lg:hidden p-2 text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                <!-- Admin Avatar (Desktop Only) -->
                <div class="hidden xl:flex items-center space-x-2">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-emerald-400 to-yellow-400 flex items-center justify-center">
                        <i class="fas fa-user-shield text-gray-900 text-sm"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Navigation Menu -->
    <div id="mobileMenu" class="hidden lg:hidden border-t border-gray-700 bg-gray-800/95 backdrop-blur-md">
        <nav class="container mx-auto px-4 py-4 space-y-2">
            <a href="/admin/" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-home mr-3"></i>Dashboard
            </a>
            <a href="/admin/users.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-users mr-3"></i>Users
            </a>
            <a href="/admin/packages.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-box mr-3"></i>Packages
            </a>
            <a href="/admin/active-trades.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-briefcase mr-3"></i>Active Trades
            </a>
            <a href="/admin/transactions.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-exchange-alt mr-3"></i>Transactions
            </a>
            <a href="/admin/chat.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition relative">
                <i class="fas fa-comments mr-3"></i>Live Chat
                <?php
                // Show unread chat count badge
                try {
                    $stmt = $db->query("
                        SELECT COUNT(DISTINCT cm.chat_id) as unread_count
                        FROM chat_messages cm
                        JOIN chat_sessions cs ON cm.chat_id = cs.id
                        WHERE cm.sender_role = 'user' AND cm.is_read = 0 AND cs.status = 'open'
                    ");
                    $unread_result = $stmt->fetch();
                    $unread_chat_count = (int)($unread_result['unread_count'] ?? 0);
                    if ($unread_chat_count > 0) {
                        echo '<span class="ml-2 bg-red-500 text-white text-xs rounded-full px-2 py-0.5">' . $unread_chat_count . '</span>';
                    }
                } catch (Exception $e) {
                    // Silently fail
                }
                ?>
            </a>
            <a href="/admin/tickets.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-headset mr-3"></i>Support
            </a>
            <a href="/admin/settings.php" class="block px-4 py-3 text-gray-300 hover:text-emerald-400 hover:bg-gray-700/50 rounded-lg transition">
                <i class="fas fa-cog mr-3"></i>Settings
            </a>
            
            <!-- Logout in Mobile Menu -->
            <div class="pt-4 border-t border-gray-700">
                <a href="/logout.php" class="block px-4 py-3 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-3"></i>Logout
                </a>
            </div>
        </nav>
    </div>
</header>

<!-- Mobile Menu Toggle Script -->
<script>
document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
    const menu = document.getElementById('mobileMenu');
    menu.classList.toggle('hidden');
});

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const menu = document.getElementById('mobileMenu');
    const btn = document.getElementById('mobileMenuBtn');
    if (menu && btn && !menu.contains(event.target) && !btn.contains(event.target)) {
        menu.classList.add('hidden');
    }
});

// Optional: Confirm logout
document.querySelectorAll('a[href="/logout.php"]').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to logout?')) {
            e.preventDefault();
        }
    });
});
</script>

<style>
/* Wallet & Logout Icon Animations */
@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 5px rgba(16, 185, 129, 0.2);
    }
    50% {
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.4);
    }
}

/* Smooth transitions */
header a, header button {
    transition: all 0.3s ease;
}

/* Hover effects for action buttons */
header a:hover svg {
    transform: scale(1.1);
}
</style>
    <main class="container mx-auto px-4 py-8">
        
        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
        <section class="mb-8">
            <div class="space-y-3">
                <?php foreach ($alerts as $alert): ?>
                <div class="status-<?php echo $alert['type']; ?> border border-current rounded-lg p-4">
                    <div class="flex items-center">
                        <i class="fas <?php 
                        echo match($alert['type']) {
                            'critical' => 'fa-exclamation-triangle',
                            'warning' => 'fa-exclamation-circle',
                            'info' => 'fa-info-circle',
                            default => 'fa-check-circle'
                        };
                        ?> mr-3"></i>
                        <span class="font-medium"><?php echo $alert['message']; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Key Metrics -->
        <section class="mb-8">
            <h1 class="text-3xl font-bold mb-6">Dashboard Overview</h1>
            
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Total Users -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Users</p>
                            <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_users']); ?></p>
                            <p class="text-emerald-400 text-sm">
                                <i class="fas fa-user-check mr-1"></i><?php echo number_format($stats['active_users']); ?> Active
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-users text-emerald-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Deposits -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Deposits</p>
                            <p class="text-3xl font-bold text-white"><?php echo formatMoney($stats['total_deposits']); ?></p>
                            <p class="text-blue-400 text-sm">
                                <i class="fas fa-arrow-down mr-1"></i>Platform Income
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-down text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Total Withdrawals -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Total Withdrawals</p>
                            <p class="text-3xl font-bold text-white"><?php echo formatMoney($stats['total_withdrawals']); ?></p>
                            <p class="text-red-400 text-sm">
                                <i class="fas fa-arrow-up mr-1"></i>Platform Outflow
                            </p>
                        </div>
                        <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <!-- Platform Profit/Loss -->
                <div class="glass-card rounded-xl p-6 metric-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm">Net Position</p>
                            <p class="text-3xl font-bold <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?>">
                                <?php echo formatMoney($platform_liquidity); ?>
                            </p>
                            <p class="<?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> text-sm">
                                <i class="fas <?php echo $platform_liquidity >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'; ?> mr-1"></i>
                                <?php echo $platform_liquidity >= 0 ? 'Profit' : 'Loss'; ?>
                            </p>
                        </div>
                        <div class="w-12 h-12 <?php echo $platform_liquidity >= 0 ? 'bg-emerald-500/20' : 'bg-red-500/20'; ?> rounded-full flex items-center justify-center">
                            <i class="fas fa-chart-line <?php echo $platform_liquidity >= 0 ? 'text-emerald-400' : 'text-red-400'; ?> text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- System Health & Financial Overview -->
        <section class="mb-8">
            <div class="grid lg:grid-cols-2 gap-8">
                
                <!-- System Health -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">System Health Monitor</h2>
                    
                    <div class="space-y-4">
                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Platform Liquidity</p>
                                <p class="font-bold text-white"><?php echo formatMoney($platform_liquidity); ?></p>
                            </div>
                            <div class="text-right">
                                <div class="w-4 h-4 rounded-full <?php echo $platform_liquidity > 0 ? 'bg-emerald-500' : 'bg-red-500'; ?>"></div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Total User Balances</p>
                                <p class="font-bold text-white"><?php echo formatMoney($stats['total_user_balances']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-400">Liability</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between p-4 bg-gray-800/50 rounded-lg">
                            <div>
                                <p class="text-gray-400">Pending ROI Obligations</p>
                                <p class="font-bold text-white"><?php echo formatMoney($stats['pending_roi_obligations']); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-400">Future Payout</p>
                            </div>
                        </div>

                        <!-- Coverage Ratio -->
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-gray-400">Coverage Ratio</p>
                                <p class="font-bold <?php 
                                echo $coverage_ratio >= 1.2 ? 'text-emerald-400' : 
                                     ($coverage_ratio >= 1 ? 'text-yellow-400' : 'text-red-400'); 
                                ?>">
                                    <?php echo number_format($coverage_ratio * 100, 1); ?>%
                                </p>
                            </div>
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="<?php 
                                echo $coverage_ratio >= 1.2 ? 'bg-emerald-500' : 
                                     ($coverage_ratio >= 1 ? 'bg-yellow-500' : 'bg-red-500'); 
                                ?> h-2 rounded-full transition-all" 
                                style="width: <?php echo min(100, $coverage_ratio * 100); ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php 
                                if ($coverage_ratio >= 1.2) echo 'Healthy - System can cover all liabilities';
                                elseif ($coverage_ratio >= 1) echo 'Warning - Low coverage ratio';
                                else echo 'Critical - Cannot cover all liabilities';
                                ?>
                            </p>
                        </div>

                        <a href="/admin/system-health.php" class="block w-full text-center py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-chart-area mr-2"></i>View Detailed Health Report
                        </a>
                    </div>
                </div>

                <!-- Revenue Chart -->
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">Revenue Trend (Last 12 Months)</h2>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- Recent Activity & Quick Actions -->
        <section class="grid lg:grid-cols-3 gap-8">
            
            <!-- Recent Transactions -->
            <div class="lg:col-span-2 glass-card rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Recent Transactions</h2>
                    <a href="/admin/transactions.php" class="text-emerald-400 hover:text-emerald-300 text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-2 text-gray-400">User</th>
                                <th class="text-center py-2 text-gray-400">Type</th>
                                <th class="text-right py-2 text-gray-400">Amount</th>
                                <th class="text-center py-2 text-gray-400">Status</th>
                                <th class="text-center py-2 text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr class="border-b border-gray-800">
                                <td class="py-3">
                                    <div>
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($transaction['full_name']); ?></p>
                                        <p class="text-xs text-gray-400"><?php echo htmlspecialchars($transaction['email']); ?></p>
                                    </div>
                                </td>
                                <td class="text-center py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        echo match($transaction['type']) {
                                            'deposit' => 'bg-blue-500/20 text-blue-400',
                                            'withdrawal' => 'bg-red-500/20 text-red-400',
                                            'roi_payment' => 'bg-yellow-500/20 text-yellow-400',
                                            'package_investment' => 'bg-emerald-500/20 text-emerald-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                <td class="text-right py-3 font-medium text-white">
                                    <?php echo formatMoney($transaction['amount']); ?>
                                </td>
                                <td class="text-center py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium
                                        <?php 
                                        echo match($transaction['status']) {
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'failed' => 'bg-red-500/20 text-red-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center py-3 text-gray-400 text-xs">
                                    <?php echo timeAgo($transaction['created_at']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">Quick Actions</h2>
                
                <div class="space-y-3">
                    <a href="/admin/users.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-users text-emerald-400"></i>
                            <span class="text-white">Manage Users</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/packages.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-box text-yellow-400"></i>
                            <span class="text-white">Manage Packages</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/transactions.php?filter=pending" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-clock text-blue-400"></i>
                            <div>
                                <span class="text-white block">Pending Approvals</span>
                                <?php if ($pending_withdrawals > 0): ?>
                                <span class="text-xs text-blue-400"><?php echo $pending_withdrawals; ?> withdrawals</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/reports.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-chart-bar text-purple-400"></i>
                            <span class="text-white">Generate Reports</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>

                    <a href="/admin/settings.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-cog text-gray-400"></i>
                            <span class="text-white">System Settings</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>
                </div>

                <!-- Package Status Overview -->
                <div class="mt-6 pt-6 border-t border-gray-700">
                    <h3 class="font-semibold text-white mb-4">Active Packages</h3>
                    <div class="text-center">
                        <p class="text-3xl font-bold text-emerald-400"><?php echo number_format($stats['active_packages']); ?></p>
                        <p class="text-sm text-gray-400">Currently Running</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Revenue Chart
        const ctx = document.getElementById('revenueChart').getContext('2d');
        const monthlyData = <?php echo json_encode($monthly_data); ?>;
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: monthlyData.map(item => {
                    const date = new Date(item.month + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }),
                datasets: [{
                    label: 'Deposits',
                    data: monthlyData.map(item => parseFloat(item.deposits)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Withdrawals',
                    data: monthlyData.map(item => parseFloat(item.withdrawals)),
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#ffffff'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#9ca3af'
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    },
                    y: {
                        ticks: {
                            color: '#9ca3af',
                            callback: function(value) {
                                return 'KSh ' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: 'rgba(156, 163, 175, 0.1)'
                        }
                    }
                }
            }
        });

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);

        // Real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            
            // You can add a clock element if needed
        }

        updateClock();
        setInterval(updateClock, 1000);
    </script>
</body>
</html>