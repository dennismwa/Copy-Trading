<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Handle package actions
if ($_POST && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'create_package':
                $name = trim($_POST['name'] ?? '');
                $icon = trim($_POST['icon'] ?? '🌱');
                $roi_percentage = floatval($_POST['roi_percentage'] ?? 0);
                $duration_hours = intval($_POST['duration_hours'] ?? 24);
                $min_investment = floatval($_POST['min_investment'] ?? 0);
                $max_investment = !empty($_POST['max_investment']) ? floatval($_POST['max_investment']) : null;
                $daily_trade_limit = !empty($_POST['daily_trade_limit']) ? intval($_POST['daily_trade_limit']) : null;
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                if ($name && $roi_percentage > 0 && $duration_hours > 0 && $min_investment > 0) {
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO packages (name, icon, roi_percentage, duration_hours, min_investment, max_investment, daily_trade_limit, description, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        if ($stmt->execute([$name, $icon, $roi_percentage, $duration_hours, $min_investment, $max_investment, $daily_trade_limit, $description, $status])) {
                            $success = 'Package created successfully.';
                        } else {
                            $error = 'Failed to create package.';
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Please fill in all required fields correctly.';
                }
                break;
                
            case 'update_package':
                $package_id = intval($_POST['package_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $icon = trim($_POST['icon'] ?? '🌱');
                $roi_percentage = floatval($_POST['roi_percentage'] ?? 0);
                $duration_hours = !empty($_POST['custom_duration_hours']) ? 
    intval($_POST['custom_duration_hours']) : 
    intval($_POST['duration_hours'] ?? 24);
                $min_investment = floatval($_POST['min_investment'] ?? 0);
                $max_investment = !empty($_POST['max_investment']) ? floatval($_POST['max_investment']) : null;
                $daily_trade_limit = !empty($_POST['daily_trade_limit']) ? intval($_POST['daily_trade_limit']) : null;
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'active';
                
                if ($package_id && $name && $roi_percentage > 0 && $duration_hours > 0 && $min_investment > 0) {
                    try {
                        $stmt = $db->prepare("
                            UPDATE packages 
                            SET name=?, icon=?, roi_percentage=?, duration_hours=?, min_investment=?, max_investment=?, daily_trade_limit=?, description=?, status=?, updated_at=NOW()
                            WHERE id=?
                        ");
                        if ($stmt->execute([$name, $icon, $roi_percentage, $duration_hours, $min_investment, $max_investment, $daily_trade_limit, $description, $status, $package_id])) {
                            $success = 'Package updated successfully.';
                        } else {
                            $error = 'Failed to update package.';
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid package data provided.';
                }
                break;
                
            case 'toggle_status':
                $package_id = intval($_POST['package_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? '';
                
                if ($package_id && in_array($new_status, ['active', 'inactive'])) {
                    try {
                        $stmt = $db->prepare("UPDATE packages SET status=?, updated_at=NOW() WHERE id=?");
                        if ($stmt->execute([$new_status, $package_id])) {
                            $success = 'Package status updated successfully.';
                        } else {
                            $error = 'Failed to update package status.';
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid status update request.';
                }
                break;
                
            case 'delete_package':
                $package_id = intval($_POST['package_id'] ?? 0);
                
                if ($package_id) {
                    try {
                        // Check if package has active investments
                        $stmt = $db->prepare("SELECT COUNT(*) as active_count FROM active_packages WHERE package_id = ? AND status = 'active'");
                        $stmt->execute([$package_id]);
                        $result = $stmt->fetch();
                        $active_count = $result ? $result['active_count'] : 0;
                        
                        if ($active_count > 0) {
                            $error = 'Cannot delete package with active investments. Please wait for all investments to complete.';
                        } else {
                            $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
                            if ($stmt->execute([$package_id])) {
                                $success = 'Package deleted successfully.';
                            } else {
                                $error = 'Failed to delete package.';
                            }
                        }
                    } catch (Exception $e) {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                } else {
                    $error = 'Invalid package ID.';
                }
                break;
        }
    }
}

// Get packages with statistics
$packages = [];
try {
    $stmt = $db->query("
        SELECT p.*,
               COALESCE(COUNT(ap.id), 0) as total_investments,
               COALESCE(COUNT(CASE WHEN ap.status = 'active' THEN 1 END), 0) as active_investments,
               COALESCE(SUM(CASE WHEN ap.status = 'active' THEN ap.investment_amount ELSE 0 END), 0) as active_amount,
               COALESCE(SUM(ap.investment_amount), 0) as total_invested
        FROM packages p
        LEFT JOIN active_packages ap ON p.id = ap.package_id
        GROUP BY p.id, p.name, p.icon, p.roi_percentage, p.duration_hours, p.min_investment, p.max_investment, p.daily_trade_limit, p.status, p.description, p.created_at, p.updated_at
        ORDER BY p.created_at DESC
    ");
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load packages: ' . $e->getMessage();
}

// Get package statistics
$stats = ['total_packages' => 0, 'active_packages' => 0, 'inactive_packages' => 0];
try {
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total_packages,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_packages,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_packages
        FROM packages
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    // Use default stats if query fails
}

// Common emojis for packages
$emoji_options = ['🌱', '🌿', '🌳', '🌾', '🌟', '💎', '🚀', '💰', '📈', '⚡', '🔥', '💫', '🎯', '🏆', '👑'];

// Helper function to format money
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Management - Ultra Harvest Admin</title>
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
        
        .package-card {
            transition: all 0.3s ease;
        }
        
        .package-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
            z-index: 1000;
        }
        
        .modal.show {
            display: flex !important;
            align-items: center;
            justify-content: center;
        }
        
        .emoji-picker {
            display: none;
        }
        
        .emoji-picker.show {
            display: grid !important;
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
        
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Package Management</h1>
                <p class="text-gray-400">Create and manage trading packages</p>
            </div>
            <div class="flex items-center space-x-4">
                <button onclick="openCreateModal()" class="px-6 py-3 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-lg font-medium transition-all duration-200 transform hover:scale-105 shadow-lg">
                    <i class="fas fa-plus mr-2"></i>Create New Package
                </button>
                <div class="text-right">
                    <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_packages']); ?></p>
                    <p class="text-gray-400 text-sm">Total Packages</p>
                </div>
            </div>
        </div>

        <!-- Error/Success Messages -->
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <section class="grid md:grid-cols-3 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Packages</p>
                        <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_packages']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-box text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Active Packages</p>
                        <p class="text-3xl font-bold text-emerald-400"><?php echo number_format($stats['active_packages']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Inactive Packages</p>
                        <p class="text-3xl font-bold text-gray-400"><?php echo number_format($stats['inactive_packages']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-gray-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-pause-circle text-gray-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Packages Grid -->
        <section class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (!empty($packages)): ?>
                <?php foreach ($packages as $package): ?>
                <div class="package-card glass-card rounded-xl p-6">
                    <!-- Package Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <div class="text-4xl"><?php echo htmlspecialchars($package['icon']); ?></div>
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($package['name']); ?></h3>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php echo $package['status'] === 'active' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-gray-500/20 text-gray-400'; ?>">
                                    <?php echo ucfirst($package['status']); ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button onclick="editPackage(<?php echo htmlspecialchars(json_encode($package)); ?>)" class="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded transition">
                                <i class="fas fa-edit text-sm"></i>
                            </button>
                            <button onclick="togglePackageStatus(<?php echo $package['id']; ?>, '<?php echo $package['status'] === 'active' ? 'inactive' : 'active'; ?>')" 
                                    class="p-2 <?php echo $package['status'] === 'active' ? 'bg-yellow-600 hover:bg-yellow-700' : 'bg-emerald-600 hover:bg-emerald-700'; ?> text-white rounded transition">
                                <i class="fas <?php echo $package['status'] === 'active' ? 'fa-pause' : 'fa-play'; ?> text-sm"></i>
                            </button>
                            <button onclick="deletePackage(<?php echo $package['id']; ?>)" class="p-2 bg-red-600 hover:bg-red-700 text-white rounded transition">
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Package Details -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-400">ROI Percentage</span>
                            <span class="text-emerald-400 font-bold"><?php echo $package['roi_percentage']; ?>%</span>
                        </div>
                        <div class="flex justify-between">
    <span class="text-gray-400">Duration</span>
    <span class="text-white font-medium">
        <?php 
        // FIXED: Show exact hours for clarity
        echo $package['duration_hours'] . ' Hours';
        
        // Also show days if applicable
        if ($package['duration_hours'] >= 24) {
            $days = floor($package['duration_hours'] / 24);
            echo ' (' . $days . ' ' . ($days == 1 ? 'Day' : 'Days') . ')';
        }
        ?>
    </span>
</div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Min Investment</span>
                            <span class="text-white font-medium"><?php echo formatMoney($package['min_investment']); ?></span>
                        </div>
                        <?php if ($package['max_investment']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Max Investment</span>
                            <span class="text-white font-medium"><?php echo formatMoney($package['max_investment']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($package['daily_trade_limit'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Daily Trade Limit</span>
                            <span class="text-yellow-400 font-medium"><?php echo number_format($package['daily_trade_limit']); ?> trades/day</span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Package Statistics -->
                    <div class="bg-gray-800/50 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p class="text-gray-400">Total Investments</p>
                                <p class="text-white font-bold"><?php echo number_format($package['total_investments']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-400">Active Now</p>
                                <p class="text-emerald-400 font-bold"><?php echo number_format($package['active_investments']); ?></p>
                            </div>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-700">
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400">Total Invested</p>
                                    <p class="text-yellow-400 font-bold"><?php echo formatMoney($package['total_invested']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Active Amount</p>
                                    <p class="text-purple-400 font-bold"><?php echo formatMoney($package['active_amount']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if ($package['description']): ?>
                    <div class="text-gray-300 text-sm">
                        <?php echo htmlspecialchars($package['description']); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-box text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No packages created yet</h3>
                    <p class="text-gray-500 mb-6">Create your first trading package to get started</p>
                    <button onclick="openCreateModal()" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-plus mr-2"></i>Create First Package
                    </button>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- Create/Edit Package Modal -->
    <div id="packageModal" class="modal">
        <div class="glass-card rounded-xl p-6 max-w-md w-full max-h-[90vh] overflow-y-auto m-4">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white" id="modalTitle">Create New Package</h3>
                <button type="button" onclick="closeModal()" class="text-gray-400 hover:text-white transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="packageForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" id="formAction" value="create_package">
                <input type="hidden" name="package_id" id="packageId">
                
                <div class="space-y-4">
                    <!-- Package Name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Package Name *</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="packageName"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="e.g., Seed, Growth, Elite"
                            required
                        >
                    </div>

                    <!-- Package Icon -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Icon *</label>
                        <div class="flex items-center space-x-2">
                            <input 
                                type="text" 
                                name="icon" 
                                id="packageIcon"
                                class="flex-1 px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                                placeholder="🌱"
                                required
                            >
                            <button type="button" onclick="toggleEmojiPicker()" class="px-3 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded transition">
                                <i class="fas fa-smile"></i>
                            </button>
                        </div>
                        <!-- Emoji Picker -->
                        <div id="emojiPicker" class="emoji-picker grid grid-cols-8 gap-2 mt-2 p-3 bg-gray-800 rounded-lg border border-gray-600">
                            <?php foreach ($emoji_options as $emoji): ?>
                            <button type="button" onclick="selectEmoji('<?php echo $emoji; ?>')" class="text-2xl hover:bg-gray-700 p-2 rounded transition">
                                <?php echo $emoji; ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ROI Percentage -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">ROI Percentage (%) *</label>
                        <input 
                            type="number" 
                            name="roi_percentage" 
                            id="packageROI"
                            min="0.01" 
                            max="100" 
                            step="0.01"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="e.g., 10.5"
                            required
                        >
                    </div>

                    <!-- Duration -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Duration (Hours) *</label>
                        <select 
    name="duration_hours" 
    id="packageDuration"
    class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
    required
>
    <option value="6">6 Hours</option>
    <option value="12">12 Hours</option>
    <option value="24" selected>24 Hours (1 Day)</option>
    <option value="48">48 Hours (2 Days)</option>
    <option value="72">72 Hours (3 Days)</option>
    <option value="120">120 Hours (5 Days)</option>
    <option value="168">168 Hours (1 Week)</option>
    <option value="336">336 Hours (2 Weeks)</option>
    <option value="504">504 Hours (3 Weeks)</option>
    <option value="720">720 Hours (30 Days)</option>
</select>
                    </div>
<!-- Custom Duration Option -->
<div class="mt-2">
    <label class="flex items-center space-x-2 text-sm text-gray-300">
        <input type="checkbox" id="customDurationToggle" class="rounded" onchange="toggleCustomDuration()">
        <span>Enter custom duration (in hours)</span>
    </label>
    <input 
        type="number" 
        id="customDurationInput"
        name="custom_duration_hours" 
        min="1" 
        max="8760"
        step="1"
        class="hidden w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none mt-2"
        placeholder="Enter hours (max 8760 = 1 year)"
    >
</div>
                    <!-- Minimum Investment -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Minimum Investment (KSh) *</label>
                        <input 
                            type="number" 
                            name="min_investment" 
                            id="packageMinInvestment"
                            min="1" 
                            step="0.01"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="e.g., 1000"
                            required
                        >
                    </div>

                    <!-- Maximum Investment -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Maximum Investment (KSh)</label>
                        <input 
                            type="number" 
                            name="max_investment" 
                            id="packageMaxInvestment"
                            min="1" 
                            step="0.01"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Leave empty for no limit"
                        >
                    </div>

                    <!-- Daily Trade Limit -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Daily Trade Limit (Optional)</label>
                        <input 
                            type="number" 
                            name="daily_trade_limit" 
                            id="packageDailyTradeLimit"
                            min="1" 
                            step="1"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Leave empty for unlimited trades per day"
                        >
                        <p class="text-xs text-gray-500 mt-1">Maximum number of trades a user can make per day for this package. Leave empty for unlimited.</p>
                    </div>

                    <!-- Description -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                        <textarea 
                            name="description" 
                            id="packageDescription"
                            rows="3"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                            placeholder="Package description for users..."
                        ></textarea>
                    </div>

                    <!-- Status -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status *</label>
                        <select 
                            name="status" 
                            id="packageStatus"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                        >
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <!-- ROI Calculation Preview -->
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-gray-300 mb-2">ROI Calculation Preview</h4>
                        <div id="roiPreview" class="text-sm text-gray-400">
                            Enter values to see calculation preview
                        </div>
                    </div>
                </div>

                <div class="flex space-x-3 mt-6">
                    <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-save mr-2"></i><span id="submitText">Create Package</span>
                    </button>
                    <button type="button" onclick="closeModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden Forms for Actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="package_id" id="statusPackageId">
        <input type="hidden" name="new_status" id="newStatus">
    </form>

    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        <input type="hidden" name="action" value="delete_package">
        <input type="hidden" name="package_id" id="deletePackageId">
    </form>

    <script>
        // Global variables
        let currentModal = null;
        
        // Modal functions
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Create New Package';
            document.getElementById('formAction').value = 'create_package';
            document.getElementById('submitText').textContent = 'Create Package';
            document.getElementById('packageId').value = '';
            
            // Reset form
            document.getElementById('packageForm').reset();
            document.getElementById('packageIcon').value = '🌱';
            
            // Hide emoji picker
            document.getElementById('emojiPicker').classList.remove('show');
            
            // Show modal
            document.getElementById('packageModal').classList.add('show');
            currentModal = 'packageModal';
            
            updateROIPreview();
        }

        function editPackage(packageData) {
            console.log('Editing package:', packageData);
            
            document.getElementById('modalTitle').textContent = 'Edit Package';
            document.getElementById('formAction').value = 'update_package';
            document.getElementById('submitText').textContent = 'Update Package';
            document.getElementById('packageId').value = packageData.id;
            
            // Fill form with existing data
            document.getElementById('packageName').value = packageData.name || '';
            document.getElementById('packageIcon').value = packageData.icon || '🌱';
            document.getElementById('packageROI').value = packageData.roi_percentage || '';
            document.getElementById('packageDuration').value = packageData.duration_hours || '';
            document.getElementById('packageMinInvestment').value = packageData.min_investment || '';
            document.getElementById('packageMaxInvestment').value = packageData.max_investment || '';
            document.getElementById('packageDailyTradeLimit').value = packageData.daily_trade_limit || '';
            document.getElementById('packageDescription').value = packageData.description || '';
            document.getElementById('packageStatus').value = packageData.status || 'active';
            
            // Hide emoji picker
            document.getElementById('emojiPicker').classList.remove('show');
            
            // Show modal
            document.getElementById('packageModal').classList.add('show');
            currentModal = 'packageModal';
            
            updateROIPreview();
        }

        function closeModal() {
            if (currentModal) {
                document.getElementById(currentModal).classList.remove('show');
                currentModal = null;
            }
            
            // Also hide emoji picker
            document.getElementById('emojiPicker').classList.remove('show');
        }

        // Emoji picker functions
        function toggleEmojiPicker() {
            const picker = document.getElementById('emojiPicker');
            picker.classList.toggle('show');
        }

        function selectEmoji(emoji) {
            document.getElementById('packageIcon').value = emoji;
            document.getElementById('emojiPicker').classList.remove('show');
        }

        // Package action functions
        function togglePackageStatus(packageId, newStatus) {
            const action = newStatus === 'active' ? 'activate' : 'deactivate';
            if (confirm(`Are you sure you want to ${action} this package?`)) {
                document.getElementById('statusPackageId').value = packageId;
                document.getElementById('newStatus').value = newStatus;
                document.getElementById('statusForm').submit();
            }
        }

        function deletePackage(packageId) {
            if (confirm('Are you sure you want to delete this package? This action cannot be undone.')) {
                document.getElementById('deletePackageId').value = packageId;
                document.getElementById('deleteForm').submit();
            }
        }

        // ROI calculation preview
        function updateROIPreview() {
            const minInvestment = parseFloat(document.getElementById('packageMinInvestment').value) || 0;
            const roiPercentage = parseFloat(document.getElementById('packageROI').value) || 0;
            const duration = parseInt(document.getElementById('packageDuration').value) || 24;
            
            if (minInvestment > 0 && roiPercentage > 0) {
                const roiAmount = (minInvestment * roiPercentage) / 100;
                const totalReturn = minInvestment + roiAmount;
                let durationText;
if (duration < 24) {
    durationText = `${duration} hours`;
} else if (duration < 168) {
    durationText = `${duration/24} day(s)`;
} else if (duration < 720) {
    durationText = `${duration/168} week(s)`;
} else {
    durationText = `${Math.round(duration/24)} day(s)`;
}
                
                document.getElementById('roiPreview').innerHTML = `
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span>Investment:</span>
                            <span class="text-white">KSh ${minInvestment.toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between">
                            <span>ROI (${roiPercentage}%):</span>
                            <span class="text-emerald-400">KSh ${roiAmount.toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between font-medium">
                            <span>Total Return:</span>
                            <span class="text-yellow-400">KSh ${totalReturn.toLocaleString()}</span>
                        </div>
                        <div class="text-xs text-gray-500 mt-2">
                            Returns after ${durationText}
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('roiPreview').textContent = 'Enter values to see calculation preview';
            }
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Real-time ROI preview updates
            const minInvestmentInput = document.getElementById('packageMinInvestment');
            const roiInput = document.getElementById('packageROI');
            const durationSelect = document.getElementById('packageDuration');
            
            if (minInvestmentInput) minInvestmentInput.addEventListener('input', updateROIPreview);
            if (roiInput) roiInput.addEventListener('input', updateROIPreview);
            if (durationSelect) durationSelect.addEventListener('change', updateROIPreview);

            // Close modal when clicking outside
            document.getElementById('packageModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal();
                }
            });

            // Form validation
            document.getElementById('packageForm').addEventListener('submit', function(e) {
                const minInvestment = parseFloat(document.getElementById('packageMinInvestment').value);
                const maxInvestment = parseFloat(document.getElementById('packageMaxInvestment').value);
                
                if (maxInvestment && maxInvestment <= minInvestment) {
                    e.preventDefault();
                    alert('Maximum investment must be greater than minimum investment.');
                    return false;
                }
                
                const roiPercentage = parseFloat(document.getElementById('packageROI').value);
                if (roiPercentage > 50) {
                    if (!confirm('ROI percentage is very high (>50%). Are you sure this is correct?')) {
                        e.preventDefault();
                        return false;
                    }
                }

                // Validate required fields
                const name = document.getElementById('packageName').value.trim();
                const icon = document.getElementById('packageIcon').value.trim();
                const roi = document.getElementById('packageROI').value;
                const duration = document.getElementById('packageDuration').value;
                const minInv = document.getElementById('packageMinInvestment').value;

                if (!name || !icon || !roi || !duration || !minInv) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return false;
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeModal();
                }
            });

            // Initialize ROI preview
            updateROIPreview();
        });

        // Auto-save functionality (optional)
        function saveFormData() {
            const formData = {
                name: document.getElementById('packageName').value,
                icon: document.getElementById('packageIcon').value,
                roi: document.getElementById('packageROI').value,
                duration: document.getElementById('packageDuration').value,
                minInvestment: document.getElementById('packageMinInvestment').value,
                maxInvestment: document.getElementById('packageMaxInvestment').value,
                dailyTradeLimit: document.getElementById('packageDailyTradeLimit').value,
                description: document.getElementById('packageDescription').value,
                status: document.getElementById('packageStatus').value
            };
            localStorage.setItem('packageFormData', JSON.stringify(formData));
        }

        function loadFormData() {
            const savedData = localStorage.getItem('packageFormData');
            if (savedData) {
                const formData = JSON.parse(savedData);
                document.getElementById('packageName').value = formData.name || '';
                document.getElementById('packageIcon').value = formData.icon || '🌱';
                document.getElementById('packageROI').value = formData.roi || '';
                document.getElementById('packageDuration').value = formData.duration || '24';
                document.getElementById('packageMinInvestment').value = formData.minInvestment || '';
                document.getElementById('packageMaxInvestment').value = formData.maxInvestment || '';
                document.getElementById('packageDailyTradeLimit').value = formData.dailyTradeLimit || '';
                document.getElementById('packageDescription').value = formData.description || '';
                document.getElementById('packageStatus').value = formData.status || 'active';
            }
        }

        function clearFormData() {
            localStorage.removeItem('packageFormData');
        }

        // Bind auto-save to form inputs
        document.addEventListener('DOMContentLoaded', function() {
            const formInputs = ['packageName', 'packageIcon', 'packageROI', 'packageDuration', 'packageMinInvestment', 'packageMaxInvestment', 'packageDailyTradeLimit', 'packageDescription', 'packageStatus'];
            
            formInputs.forEach(inputId => {
                const element = document.getElementById(inputId);
                if (element) {
                    element.addEventListener('input', saveFormData);
                    element.addEventListener('change', saveFormData);
                }
            });
        });
        function toggleCustomDuration() {
    const checkbox = document.getElementById('customDurationToggle');
    const customInput = document.getElementById('customDurationInput');
    const selectDropdown = document.getElementById('packageDuration');
    
    if (checkbox.checked) {
        customInput.classList.remove('hidden');
        selectDropdown.disabled = true;
    } else {
        customInput.classList.add('hidden');
        selectDropdown.disabled = false;
        customInput.value = '';
    }
}
    </script>
</body>
</html>