<?php
require_once '../config/database.php';
requireAdmin();

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
        
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-white mb-2">Active Trades Overview</h1>
            <p class="text-gray-400">Monitor all active trading packages across the platform</p>
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
                            // FIXED: Proper time calculation for both active and completed packages
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

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>