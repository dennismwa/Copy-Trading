<?php
/**
 * Temporary Admin Dashboard Replica - Home Page
 * Login with any email and password: Ultra@H254!
 */

require_once 'config/database.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Simple authentication for this page only
define('HOME_PAGE_PASSWORD', 'Ultra@H254!');

$error = '';
$success = '';
$is_logged_in = isset($_SESSION['home_logged_in']) && $_SESSION['home_logged_in'] === true;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } elseif ($password === HOME_PAGE_PASSWORD) {
        // Valid login - set session
        $_SESSION['home_logged_in'] = true;
        $_SESSION['home_email'] = $email;
        $_SESSION['home_full_name'] = $email; // Use email as name for simplicity
        $is_logged_in = true;
        header('Location: /home.php');
        exit;
    } else {
        $error = 'Invalid email or password.';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['home_logged_in']);
    unset($_SESSION['home_email']);
    unset($_SESSION['home_full_name']);
    $is_logged_in = false;
    header('Location: /home.php');
    exit;
}

// If not logged in, show login form
if (!$is_logged_in) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Admin Dashboard Access</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
            * { font-family: 'Poppins', sans-serif; }
        </style>
    </head>
    <body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full mx-4">
            <div class="bg-gray-800 rounded-xl p-8 shadow-2xl border border-gray-700">
                <div class="text-center mb-6">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Logo" class="w-full h-full object-cover">
                    </div>
                    <h2 class="text-2xl font-bold mb-2">Admin Dashboard Access</h2>
                </div>

                <?php if ($error): ?>
                <div class="mb-4 p-3 bg-red-500/20 border border-red-500/50 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                        <span class="text-red-300 text-sm"><?php echo htmlspecialchars($error); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-envelope mr-2"></i>Email
                        </label>
                        <input 
                            type="email" 
                            name="email" 
                            value="<?php echo htmlspecialchars($_POST['email'] ?? 'info@theultraharvest.com'); ?>"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none" 
                            placeholder="Enter your email"
                            required
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-lock mr-2"></i>Password
                        </label>
                        <input 
                            type="password" 
                            name="password" 
                            value="<?php echo empty($error) ? 'Ultra@H254!' : ''; ?>"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none" 
                            placeholder="Enter password"
                            required
                        >
                    </div>

                    <button 
                        type="submit" 
                        class="w-full py-3 bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-semibold rounded-lg transition"
                    >
                        <i class="fas fa-sign-in-alt mr-2"></i>Login to Dashboard
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// User is logged in - show admin dashboard replica
// Set default timezone
date_default_timezone_set('Africa/Nairobi');
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
$platform_liquidity = $stats['total_deposits'] - $stats['total_withdrawals'];
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

// ============================================================================
// PERMISSION CONTROLS - RESTRICTED ACCESS (VIEW ONLY)
// ============================================================================
// 
// This dashboard only allows access to:
// - Dashboard (home page)
// - Users (view only)
// - Active Trades (view only)
// - Transactions (view only)
// 
// ALL EDIT/DELETE/ACTION OPERATIONS ARE DISABLED
//
// ============================================================================
$permissions = [
    // Navigation & Menu Access (ONLY ALLOWED PAGES)
    'view_users' => true,              // Show "Users" link in navigation (VIEW ONLY)
    'view_transactions' => true,       // Show "Transactions" link in navigation (VIEW ONLY)
    'view_trades' => true,             // Show "Active Trades" link in navigation (VIEW ONLY)
    
    // DISABLED PAGES - NOT ACCESSIBLE
    'view_packages' => false,          // HIDDEN - Packages page
    'view_reports' => false,           // HIDDEN - Reports page
    'view_settings' => false,          // HIDDEN - Settings page
    'view_support' => false,           // HIDDEN - Support page
    
    // Dashboard Metrics Cards (Top Section)
    'view_metric_users' => true,       // Show "Total Users" metric card
    'view_metric_deposits' => true,    // Show "Total Deposits" metric card
    'view_metric_withdrawals' => true, // Show "Total Withdrawals" metric card
    'view_metric_profit' => true,      // Show "Net Position" metric card
    
    // System Health Section - COMPLETELY REMOVED
    'view_system_health' => false,      // HIDDEN - System Health Monitor section
    'view_platform_liquidity' => false, // HIDDEN
    'view_user_balances' => false,      // HIDDEN
    'view_roi_obligations' => false,    // HIDDEN
    'view_coverage_ratio' => false,     // HIDDEN
    'view_health_report_link' => false, // HIDDEN - No system health links
    
    // Charts & Analytics
    'view_revenue_chart' => true,      // Show "Revenue Trend" chart
    
    // Recent Transactions Table
    'view_recent_transactions' => true,// Show "Recent Transactions" table section
    'view_transactions_table' => true, // Show the actual transactions table
    
    // Quick Actions Section (VIEW ONLY LINKS)
    'view_quick_actions' => true,      // Show "Quick Actions" sidebar (view-only links)
    'view_active_packages_count' => false, // HIDDEN - Active Packages count
    
    // Alerts & Notifications
    'view_alerts' => true,             // Show system alerts/notifications banner
    
    // Action Permissions - ALL DISABLED (VIEW ONLY MODE)
    'edit_users' => false,             // DISABLED - No editing users allowed
    'approve_transactions' => false,   // DISABLED - No approving transactions allowed
    'delete_users' => false,           // DISABLED - No deleting users allowed
    'delete_transactions' => false,    // DISABLED - No deleting transactions allowed
];

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
                            <p class="text-xs text-gray-400">Global - Admin Dashboard</p>
                        </div>
                    </div>
                    
                    <!-- Desktop Navigation - RESTRICTED ACCESS (VIEW ONLY) -->
                    <nav class="hidden lg:flex space-x-6">
                        <a href="/home.php" class="text-emerald-400 font-medium">Dashboard</a>
                        <?php if ($permissions['view_users']): ?>
                        <a href="/user.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                        <?php endif; ?>
                        <?php if ($permissions['view_trades']): ?>
                        <a href="/active-trade.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <?php endif; ?>
                        <?php if ($permissions['view_transactions']): ?>
                        <a href="/transaction.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                        <?php endif; ?>
                    </nav>
                </div>

                <!-- Right: Logout -->
                <div class="flex items-center space-x-3">
                    <a href="/home.php?logout=1" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition">
                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Alerts Section -->
        <?php if ($permissions['view_alerts'] && !empty($alerts)): ?>
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
                <?php if ($permissions['view_metric_users']): ?>
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
                <?php endif; ?>

                <!-- Total Deposits -->
                <?php if ($permissions['view_metric_deposits']): ?>
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
                <?php endif; ?>

                <!-- Total Withdrawals -->
                <?php if ($permissions['view_metric_withdrawals']): ?>
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
                <?php endif; ?>

                <!-- Platform Profit/Loss -->
                <?php if ($permissions['view_metric_profit']): ?>
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
                <?php endif; ?>
            </div>
        </section>

        <!-- Revenue Chart Section -->
        <?php if ($permissions['view_revenue_chart']): ?>
        <section class="mb-8">
            <div class="grid lg:grid-cols-1 gap-8">
                <!-- Revenue Chart -->
                <?php if ($permissions['view_revenue_chart']): ?>
                <div class="glass-card rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-6">Revenue Trend (Last 12 Months)</h2>
                    <div class="h-80">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Recent Activity & Quick Actions -->
        <?php if ($permissions['view_recent_transactions'] || $permissions['view_quick_actions']): ?>
        <section class="grid lg:grid-cols-3 gap-8">
            
            <!-- Recent Transactions -->
            <?php if ($permissions['view_recent_transactions']): ?>
            <div class="lg:col-span-2 glass-card rounded-xl p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">Recent Transactions</h2>
                    <?php if ($permissions['view_transactions']): ?>
                    <a href="/transaction.php" class="text-emerald-400 hover:text-emerald-300 text-sm">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($permissions['view_transactions_table']): ?>
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
                <?php else: ?>
                <p class="text-gray-400 text-center py-8">Transaction data is hidden</p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions - VIEW ONLY -->
            <?php if ($permissions['view_quick_actions']): ?>
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-6">Quick Access</h2>
                
                <div class="space-y-3">
                    <?php if ($permissions['view_users']): ?>
                    <a href="/user.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-users text-emerald-400"></i>
                            <span class="text-white">View Users</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($permissions['view_trades']): ?>
                    <a href="/active-trade.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-briefcase text-yellow-400"></i>
                            <span class="text-white">View Active Trades</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>
                    <?php endif; ?>

                    <?php if ($permissions['view_transactions']): ?>
                    <a href="/transaction.php" class="flex items-center justify-between p-4 bg-gray-800/50 hover:bg-gray-700/50 rounded-lg transition group">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-exchange-alt text-blue-400"></i>
                            <span class="text-white">View Transactions</span>
                        </div>
                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-white transition"></i>
                    </a>
                    <?php endif; ?>
                </div>

            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </main>

    <script>
        // Revenue Chart
        <?php if ($permissions['view_revenue_chart']): ?>
        const ctx = document.getElementById('revenueChart');
        if (ctx) {
            const monthlyData = <?php echo json_encode($monthly_data); ?>;
            
            const chart = new Chart(ctx.getContext('2d'), {
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
        }
        <?php endif; ?>

        // Auto-refresh every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
<?php
?>