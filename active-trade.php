<?php
/**
 * View-Only Active Trades Page - Restricted Access
 * Same data as admin/active-trades.php but no editing allowed
 */

require_once 'config/database.php';

// Start session if not started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in via home.php authentication
if (!isset($_SESSION['home_logged_in']) || $_SESSION['home_logged_in'] !== true) {
    header('Location: /home.php');
    exit;
}

// Get active trades statistics
$stats = [
    'active_trades' => 0,
    'completed_trades' => 0,
    'all_time_profits' => 0,
    'total_investment' => 0,
    'total_earnings' => 0
];

try {
    $stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_trades,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_trades,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN expected_roi ELSE 0 END), 0) as all_time_profits,
            COALESCE(SUM(CASE WHEN status = 'active' THEN investment_amount ELSE 0 END), 0) as total_investment,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN investment_amount + expected_roi ELSE 0 END), 0) as total_earnings
        FROM active_packages
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    error_log("Failed to get active trades stats: " . $e->getMessage());
}

// Get all packages with user info (both active and recently completed)
$active_packages = [];
try {
    $stmt = $db->query("
        SELECT 
            ap.*,
            u.full_name,
            u.email,
            p.name as package_name,
            p.icon as package_icon,
            p.roi_percentage
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status IN ('active', 'completed')
        ORDER BY 
            CASE WHEN ap.status = 'active' AND ap.maturity_date > NOW() THEN 0 
                 WHEN ap.status = 'active' AND ap.maturity_date <= NOW() THEN 1
                 ELSE 2 END,
            ap.maturity_date ASC
    ");
    $active_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to get active packages: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Trades - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

    <!-- Header - Same as home.php -->
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
                        <a href="/home.php" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                        <a href="/user.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                        <a href="/active-trade.php" class="text-emerald-400 font-medium">Active Trades</a>
                        <a href="/transaction.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
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
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Active Trades</h1>
            <p class="text-gray-400">View all active trading packages (View-only access)</p>
        </div>

        <!-- Statistics Section -->
        <section class="grid md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-blue-400 text-3xl font-bold"><?php echo number_format($stats['active_trades']); ?></p>
                    <p class="text-gray-400 text-sm">Active Trades</p>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-3xl font-bold"><?php echo number_format($stats['completed_trades']); ?></p>
                    <p class="text-gray-400 text-sm">Completed</p>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-2xl font-bold"><?php echo formatMoney($stats['all_time_profits']); ?></p>
                    <p class="text-gray-400 text-sm">All Time Profits</p>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-2xl font-bold"><?php echo formatMoney($stats['total_investment']); ?></p>
                    <p class="text-gray-400 text-sm">Total Investment</p>
                </div>
            </div>
            
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-white text-2xl font-bold"><?php echo formatMoney($stats['total_earnings']); ?></p>
                    <p class="text-gray-400 text-sm">Total Earnings</p>
                </div>
            </div>
        </section>

        <!-- Active Packages Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <div class="p-6 border-b border-gray-700">
                <h2 class="text-xl font-bold text-white">Trading Packages (Active & Recently Completed)</h2>
            </div>
            
            <?php if (!empty($active_packages)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400">User</th>
                                <th class="text-center p-4 text-gray-400">Package</th>
                                <th class="text-right p-4 text-gray-400">Investment</th>
                                <th class="text-right p-4 text-gray-400">Expected ROI</th>
                                <th class="text-right p-4 text-gray-400">Total Return</th>
                                <th class="text-center p-4 text-gray-400">Maturity Date</th>
                                <th class="text-center p-4 text-gray-400">Time Left</th>
                                <th class="text-center p-4 text-gray-400">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_packages as $package): ?>
                            <?php
                            // Proper time calculation for both active and completed packages
                            $maturity = new DateTime($package['maturity_date']);
                            $now = new DateTime();
                            $is_matured = $now >= $maturity || $package['status'] === 'completed';
                            
                            if (!$is_matured) {
                                $interval = $now->diff($maturity);
                                $total_seconds = ($maturity->getTimestamp() - $now->getTimestamp());
                                $days = $interval->days;
                                $hours = $interval->h;
                                $minutes = $interval->i;
                            } else {
                                // For completed packages, show how long ago they matured
                                $interval = $maturity->diff($now);
                                $days = $interval->days;
                                $hours = $interval->h;
                                $minutes = $interval->i;
                            }
                            ?>
                            <tr class="border-b border-gray-800">
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-sm font-bold">
                                            <?php echo strtoupper(substr($package['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($package['full_name']); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($package['email']); ?></p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <span class="text-2xl"><?php echo $package['package_icon']; ?></span>
                                        <div class="text-left">
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($package['package_name']); ?></p>
                                            <p class="text-xs text-emerald-400"><?php echo $package['roi_percentage']; ?>% ROI</p>
                                        </div>
                                    </div>
                                </td>
                                
                                <td class="p-4 text-right">
                                    <p class="font-bold text-white"><?php echo formatMoney($package['investment_amount']); ?></p>
                                </td>
                                
                                <td class="p-4 text-right">
                                    <p class="font-bold text-yellow-400"><?php echo formatMoney($package['expected_roi']); ?></p>
                                </td>
                                
                                <td class="p-4 text-right">
                                    <p class="font-bold text-emerald-400"><?php echo formatMoney($package['investment_amount'] + $package['expected_roi']); ?></p>
                                </td>
                                
                                <td class="p-4 text-center text-gray-300">
                                    <?php echo date('M j, Y g:i A', strtotime($package['maturity_date'])); ?>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <?php if ($is_matured): ?>
                                        <?php if ($package['status'] === 'completed'): ?>
                                            <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs font-medium">
                                                <i class="fas fa-check mr-1"></i>Completed
                                            </span>
                                        <?php else: ?>
                                            <span class="px-3 py-1 bg-yellow-500/20 text-yellow-400 rounded-full text-xs font-medium">
                                                <i class="fas fa-clock mr-1"></i>Matured
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-blue-400 font-medium">
                                            <?php 
                                            if ($days > 0) {
                                                echo $days . 'd ' . $hours . 'h';
                                            } elseif ($hours > 0) {
                                                echo $hours . 'h ' . $minutes . 'm';
                                            } else {
                                                echo $minutes . ' minutes';
                                            }
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="p-4 text-center">
                                    <?php
                                    // Determine the actual status based on maturity date and database status
                                    $actual_status = $package['status'];
                                    if ($is_matured && $package['status'] === 'active') {
                                        $actual_status = 'matured';
                                    }
                                    ?>
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($actual_status) {
                                            'active' => 'bg-blue-500/20 text-blue-400',
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'matured' => 'bg-yellow-500/20 text-yellow-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php 
                                        echo match($actual_status) {
                                            'active' => 'Active',
                                            'completed' => 'Completed',
                                            'matured' => 'Matured',
                                            default => ucfirst($actual_status)
                                        };
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-chart-line text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No Trading Packages</h3>
                    <p class="text-gray-500">There are currently no active or completed trading packages</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
