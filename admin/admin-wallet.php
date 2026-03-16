<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Get current settings for fee rates
$current_settings = [];
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    error_log("Settings load error: " . $e->getMessage());
}

// Get admin wallet statistics - ALL IN ONE FUNCTION
function getCompleteWalletStats($db) {
    try {
        // Get all fees collected from withdrawals
        $stmt = $db->query("
            SELECT 
                COALESCE(SUM(withdrawal_fee), 0) as total_withdrawal_fees,
                COALESCE(SUM(platform_fee), 0) as total_platform_fees,
                COALESCE(SUM(withdrawal_fee + platform_fee), 0) as total_fees_collected
            FROM transactions
            WHERE type = 'withdrawal' AND status = 'completed'
        ");
        $fees = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get admin wallet transactions
        $stmt = $db->query("
            SELECT 
                COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawn,
                COALESCE(SUM(CASE WHEN type = 'injection' THEN amount ELSE 0 END), 0) as total_injected
            FROM admin_wallet_transactions
        ");
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate available balance
        $available_balance = $fees['total_fees_collected'] + $wallet['total_injected'] - $wallet['total_withdrawn'];
        
        return array(
            'total_withdrawal_fees' => floatval($fees['total_withdrawal_fees']),
            'total_platform_fees' => floatval($fees['total_platform_fees']),
            'total_fees_collected' => floatval($fees['total_fees_collected']),
            'total_withdrawn' => floatval($wallet['total_withdrawn']),
            'total_injected' => floatval($wallet['total_injected']),
            'available_balance' => floatval($available_balance),
            'net_profit' => floatval($fees['total_fees_collected'])
        );
    } catch (Exception $e) {
        error_log("Admin wallet stats error: " . $e->getMessage());
        return array(
            'total_withdrawal_fees' => 0,
            'total_platform_fees' => 0,
            'total_fees_collected' => 0,
            'total_withdrawn' => 0,
            'total_injected' => 0,
            'available_balance' => 0,
            'net_profit' => 0
        );
    }
}

// Handle admin wallet actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'withdraw_to_mpesa') {
            $amount = floatval(isset($_POST['amount']) ? $_POST['amount'] : 0);
            $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
            
            if ($amount > 0 && !empty($phone)) {
                try {
                    $wallet_stats = getCompleteWalletStats($db);
                    $available_balance = $wallet_stats['available_balance'];
                    
                    if ($amount > $available_balance) {
                        $error = 'Insufficient balance. Available: ' . formatMoney($available_balance);
                    } else {
                        $db->beginTransaction();
                        
                        $stmt = $db->prepare("
                            INSERT INTO admin_wallet_transactions (type, amount, description, admin_id, created_at)
                            VALUES ('withdrawal', ?, ?, ?, NOW())
                        ");
                        $admin_id = $_SESSION['user_id'];
                        $description = 'Admin withdrawal to M-Pesa - Phone: ' . $phone;
                        $stmt->execute(array($amount, $description, $admin_id));
                        
                        $db->commit();
                        $success = 'Withdrawal request submitted successfully. Amount: ' . formatMoney($amount);
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error = 'Failed to process withdrawal: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide valid amount and phone number.';
            }
        } elseif ($action === 'inject_funds') {
            $amount = floatval(isset($_POST['amount']) ? $_POST['amount'] : 0);
            $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
            
            if ($amount > 0) {
                try {
                    $db->beginTransaction();
                    
                    // Get admin ID from session
                    $admin_id = $_SESSION['user_id'];
                    
                    // Insert admin wallet transaction
                    $stmt = $db->prepare("
                        INSERT INTO admin_wallet_transactions (type, status, amount, description, admin_id, created_at)
                        VALUES ('injection', 'pending', ?, ?, ?, NOW())
                    ");
                    $desc = !empty($description) ? $description : 'Capital injection to business float';
                    $stmt->execute(array($amount, $desc, $admin_id));
                    
                    // Log admin action
                    error_log(sprintf(
                        "ADMIN ACTION: Capital injection created by admin_id=%d, amount=%s, description=%s at %s (status: pending - must be completed to contribute to liquidity)",
                        $admin_id,
                        $amount,
                        $desc,
                        date('Y-m-d H:i:s')
                    ));
                    
                    // Note: Capital injection is created as 'pending'
                    // Once marked as 'completed', it will automatically be included in platform liquidity calculations
                    // via the getCompletedAdminInjections() function used throughout the system
                    // This ensures proper tracking and auditability - only completed injections contribute to liquidity
                    
                    $db->commit();
                    
                    $success = 'Capital injection created successfully. Amount: ' . formatMoney($amount) . '. Please mark it as completed for it to contribute to platform liquidity.';
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("Capital injection failed: " . $e->getMessage());
                    $error = 'Failed to inject funds: ' . $e->getMessage();
                }
            } else {
                $error = 'Please provide a valid amount.';
            }
        } elseif ($action === 'edit_injection') {
            $injection_id = isset($_POST['injection_id']) ? (int)$_POST['injection_id'] : 0;
            $amount = floatval(isset($_POST['amount']) ? $_POST['amount'] : 0);
            $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
            $action_notes = trim(isset($_POST['action_notes']) ? $_POST['action_notes'] : '');
            
            if ($injection_id > 0 && $amount > 0) {
                try {
                    $db->beginTransaction();
                    $admin_id = $_SESSION['user_id'];
                    
                    // Check if transaction exists and is editable (not completed)
                    $stmt = $db->prepare("
                        SELECT * FROM admin_wallet_transactions 
                        WHERE id = ? AND type = 'injection'
                    ");
                    $stmt->execute([$injection_id]);
                    $injection = $stmt->fetch();
                    
                    if (!$injection) {
                        $error = 'Injection transaction not found.';
                    } elseif ($injection['status'] === 'completed') {
                        $error = 'Cannot edit completed injections.';
                    } else {
                        // Update the transaction
                        $stmt = $db->prepare("
                            UPDATE admin_wallet_transactions 
                            SET amount = ?, description = ?, updated_by = ?, action_notes = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $notes = $action_notes ?: "Edited by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$amount, $description, $admin_id, $notes, $injection_id]);
                        
                        // Log the edit action
                        error_log(sprintf(
                            "ADMIN ACTION: Injection edited - ID=%d by admin_id=%d, old_amount=%s, new_amount=%s, notes=%s at %s",
                            $injection_id,
                            $admin_id,
                            $injection['amount'],
                            $amount,
                            $notes,
                            date('Y-m-d H:i:s')
                        ));
                        
                        $db->commit();
                        $success = 'Injection updated successfully.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("Failed to edit injection: " . $e->getMessage());
                    $error = 'Failed to edit injection: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid injection ID or amount.';
            }
        } elseif ($action === 'cancel_injection') {
            $injection_id = isset($_POST['injection_id']) ? (int)$_POST['injection_id'] : 0;
            $cancellation_reason = trim(isset($_POST['cancellation_reason']) ? $_POST['cancellation_reason'] : '');
            
            if ($injection_id > 0) {
                try {
                    $db->beginTransaction();
                    $admin_id = $_SESSION['user_id'];
                    
                    // Check if transaction exists and is cancellable
                    $stmt = $db->prepare("
                        SELECT * FROM admin_wallet_transactions 
                        WHERE id = ? AND type = 'injection'
                    ");
                    $stmt->execute([$injection_id]);
                    $injection = $stmt->fetch();
                    
                    if (!$injection) {
                        $error = 'Injection transaction not found.';
                    } elseif ($injection['status'] === 'completed') {
                        $error = 'Cannot cancel completed injections.';
                    } else {
                        // Cancel the transaction
                        $stmt = $db->prepare("
                            UPDATE admin_wallet_transactions 
                            SET status = 'cancelled', updated_by = ?, action_notes = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $reason = $cancellation_reason ?: "Cancelled by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$admin_id, $reason, $injection_id]);
                        
                        // Log the cancellation
                        error_log(sprintf(
                            "ADMIN ACTION: Injection cancelled - ID=%d by admin_id=%d, amount=%s, reason=%s at %s",
                            $injection_id,
                            $admin_id,
                            $injection['amount'],
                            $reason,
                            date('Y-m-d H:i:s')
                        ));
                        
                        $db->commit();
                        $success = 'Injection cancelled successfully.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("Failed to cancel injection: " . $e->getMessage());
                    $error = 'Failed to cancel injection: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid injection ID.';
            }
        } elseif ($action === 'complete_injection') {
            $injection_id = isset($_POST['injection_id']) ? (int)$_POST['injection_id'] : 0;
            $completion_notes = trim(isset($_POST['completion_notes']) ? $_POST['completion_notes'] : '');
            
            if ($injection_id > 0) {
                try {
                    $db->beginTransaction();
                    $admin_id = $_SESSION['user_id'];
                    
                    // Check if transaction exists
                    $stmt = $db->prepare("
                        SELECT * FROM admin_wallet_transactions 
                        WHERE id = ? AND type = 'injection'
                    ");
                    $stmt->execute([$injection_id]);
                    $injection = $stmt->fetch();
                    
                    if (!$injection) {
                        $error = 'Injection transaction not found.';
                    } elseif ($injection['status'] === 'completed') {
                        $error = 'Transaction is already completed.';
                    } elseif ($injection['status'] === 'cancelled') {
                        $error = 'Cannot complete a cancelled injection.';
                    } else {
                        // Mark as completed
                        $stmt = $db->prepare("
                            UPDATE admin_wallet_transactions 
                            SET status = 'completed', updated_by = ?, action_notes = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $notes = $completion_notes ?: "Marked as completed by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i:s');
                        $stmt->execute([$admin_id, $notes, $injection_id]);
                        
                        // Log the completion
                        error_log(sprintf(
                            "ADMIN ACTION: Injection completed - ID=%d by admin_id=%d, amount=%s, notes=%s at %s",
                            $injection_id,
                            $admin_id,
                            $injection['amount'],
                            $notes,
                            date('Y-m-d H:i:s')
                        ));
                        
                        $db->commit();
                        $success = 'Injection marked as completed successfully.';
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("Failed to complete injection: " . $e->getMessage());
                    $error = 'Failed to complete injection: ' . $e->getMessage();
                }
            } else {
                $error = 'Invalid injection ID.';
            }
        }
    }
}

// Get admin wallet statistics
$wallet_stats = getCompleteWalletStats($db);

// Get fee breakdown by package
$fee_breakdown = array();
try {
    $stmt = $db->query("
        SELECT p.name, p.icon,
               COUNT(DISTINCT t.id) as transaction_count,
               COALESCE(SUM(t.withdrawal_fee), 0) as total_withdrawal_fees,
               COALESCE(SUM(t.platform_fee), 0) as total_platform_fees,
               COALESCE(SUM(t.withdrawal_fee + t.platform_fee), 0) as total_fees
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN (
            SELECT user_id, package_id, 
                   ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM active_packages
        ) ap ON u.id = ap.user_id AND ap.rn = 1
        LEFT JOIN packages p ON ap.package_id = p.id
        WHERE t.type = 'withdrawal' AND t.status = 'completed'
        AND p.id IS NOT NULL
        GROUP BY p.id, p.name, p.icon
        ORDER BY total_fees DESC
    ");
    $fee_breakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fee breakdown error: " . $e->getMessage());
}

// Get recent admin wallet transactions
$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$wallet_transactions = array();
$total_records = 0;
$total_pages = 1;

try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM admin_wallet_transactions");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_records = intval($result['total']);
    $total_pages = ceil($total_records / $limit);
    
    $stmt = $db->query("
        SELECT * FROM admin_wallet_transactions
        ORDER BY created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $wallet_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Failed to load transactions: ' . $e->getMessage();
}

// Get fee collection trend (last 30 days)
$fee_trend = array();
try {
    $stmt = $db->query("
        SELECT DATE(created_at) as date,
               COALESCE(SUM(withdrawal_fee), 0) as withdrawal_fees,
               COALESCE(SUM(platform_fee), 0) as platform_fees
        FROM transactions
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND type = 'withdrawal'
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $fee_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Fee trend error: " . $e->getMessage());
}

// Get withdrawal fees from system settings for display
$fee_rates = array(
    'Seed' => ($current_settings['withdrawal_fee_seed'] ?? '7') . '%',
    'Sprout' => ($current_settings['withdrawal_fee_sprout'] ?? '6') . '%',
    'Growth' => ($current_settings['withdrawal_fee_growth'] ?? '5') . '%',
    'Harvest' => ($current_settings['withdrawal_fee_harvest'] ?? '5') . '%',
    'Golden Yield' => ($current_settings['withdrawal_fee_golden_yield'] ?? '4') . '%',
    'Elite' => ($current_settings['withdrawal_fee_elite'] ?? '3') . '%'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Wallet - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        .glass-card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); }
        .metric-card { transition: all 0.3s ease; }
        .metric-card:hover { transform: translateY(-5px); }
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
            <h1 class="text-3xl font-bold text-white mb-2">Admin Wallet & Fee Management</h1>
            <p class="text-gray-400">Manage collected fees and business capital</p>
        </div>

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

        <section class="grid md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6 metric-card border-2 border-yellow-500/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Available Balance</p>
                        <p class="text-3xl font-bold text-yellow-400"><?php echo formatMoney($wallet_stats['available_balance']); ?></p>
                        <p class="text-gray-400 text-xs mt-1">Ready to withdraw</p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-wallet text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6 metric-card border-2 border-emerald-500/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Net Profit</p>
                        <p class="text-3xl font-bold text-emerald-400"><?php echo formatMoney($wallet_stats['net_profit']); ?></p>
                        <p class="text-gray-400 text-xs mt-1">Total fees earned</p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Withdrawal Fees</p>
                        <p class="text-2xl font-bold text-blue-400"><?php echo formatMoney($wallet_stats['total_withdrawal_fees']); ?></p>
                        <p class="text-gray-400 text-xs mt-1">3-7% by package</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-percentage text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Platform Fees (1.5%)</p>
                        <p class="text-2xl font-bold text-purple-400"><?php echo formatMoney($wallet_stats['total_platform_fees']); ?></p>
                        <p class="text-gray-400 text-xs mt-1">On withdrawals</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6 metric-card">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Withdrawn</p>
                        <p class="text-2xl font-bold text-red-400"><?php echo formatMoney($wallet_stats['total_withdrawn']); ?></p>
                        <p class="text-gray-400 text-xs mt-1">Admin payouts</p>
                    </div>
                    <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid md:grid-cols-2 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-mobile-alt text-emerald-400 mr-2"></i>Withdraw to M-Pesa</h2>
                <p class="text-gray-400 text-sm mb-6">Transfer available balance to your M-Pesa account</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="withdraw_to_mpesa">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Amount (KSh)</label>
                            <input type="number" name="amount" min="1" max="<?php echo $wallet_stats['available_balance']; ?>" step="0.01" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none" placeholder="Enter amount to withdraw" required>
                            <p class="text-xs text-gray-500 mt-1">Available: <?php echo formatMoney($wallet_stats['available_balance']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">M-Pesa Number</label>
                            <input type="text" name="phone" pattern="254[0-9]{9}" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none" placeholder="254712345678" required>
                            <p class="text-xs text-gray-500 mt-1">Format: 254XXXXXXXXX</p>
                        </div>
                    </div>
                    <button type="submit" class="mt-6 w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition"><i class="fas fa-paper-plane mr-2"></i>Withdraw to M-Pesa</button>
                </form>
            </div>
            <div class="glass-card rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-plus-circle text-blue-400 mr-2"></i>Inject Capital</h2>
                <p class="text-gray-400 text-sm mb-6">Add funds to business float for operations</p>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="action" value="inject_funds">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Amount (KSh)</label>
                            <input type="number" name="amount" min="1" step="0.01" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none" placeholder="Enter amount to inject" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description (Optional)</label>
                            <input type="text" name="description" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none" placeholder="Reason for capital injection">
                        </div>
                    </div>
                    <button type="submit" class="mt-6 w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition"><i class="fas fa-plus mr-2"></i>Inject Funds</button>
                </form>
            </div>
        </section>

        <!--<section class="glass-card rounded-xl p-6 mb-8">
            <h2 class="text-xl font-bold text-white mb-6">Fee Collection Trend (Last 30 Days)</h2>
            <div class="h-80"><canvas id="feeChart"></canvas></div>
        </section>

        <section class="glass-card rounded-xl p-6 mb-8">
            <h2 class="text-xl font-bold text-white mb-6">Withdrawal Fee Statistics by Package</h2>
            <?php if (!empty($fee_breakdown)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="text-left p-4 text-gray-400 font-medium">Package</th>
                            <th class="text-center p-4 text-gray-400 font-medium">Fee Rate</th>
                            <th class="text-center p-4 text-gray-400 font-medium">Transactions</th>
                            <th class="text-right p-4 text-gray-400 font-medium">Withdrawal Fees</th>
                            <th class="text-right p-4 text-gray-400 font-medium">Platform Fees</th>
                            <th class="text-right p-4 text-gray-400 font-medium">Total Fees</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fee_breakdown as $pkg): ?>
                        <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                            <td class="p-4">
                                <div class="flex items-center space-x-3">
                                    <span class="text-2xl"><?php echo isset($pkg['icon']) ? htmlspecialchars($pkg['icon']) : '📦'; ?></span>
                                    <span class="font-medium text-white"><?php echo htmlspecialchars($pkg['name']); ?></span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <span class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded-full text-sm font-medium">
                                    <?php echo isset($fee_rates[$pkg['name']]) ? $fee_rates[$pkg['name']] : 'N/A'; ?>
                                </span>
                            </td>
                            <td class="p-4 text-center"><span class="text-gray-300 font-medium"><?php echo number_format($pkg['transaction_count']); ?></span></td>
                            <td class="p-4 text-right"><p class="text-lg font-bold text-blue-400"><?php echo formatMoney($pkg['total_withdrawal_fees']); ?></p></td>
                            <td class="p-4 text-right"><p class="text-lg font-bold text-purple-400"><?php echo formatMoney($pkg['total_platform_fees']); ?></p></td>
                            <td class="p-4 text-right"><p class="text-xl font-bold text-emerald-400"><?php echo formatMoney($pkg['total_fees']); ?></p></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-center text-gray-400 py-8">No fee data available yet</p>
            <?php endif; ?>
        </section>-->

        <section class="glass-card rounded-xl overflow-hidden">
            <div class="p-6 border-b border-gray-700"><h2 class="text-xl font-bold text-white">Admin Wallet Transaction History</h2></div>
            <?php if (!empty($wallet_transactions)): ?>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800/50">
                        <tr>
                            <th class="text-left p-4 text-gray-400 font-medium">Date</th>
                            <th class="text-center p-4 text-gray-400 font-medium">Type</th>
                            <th class="text-left p-4 text-gray-400 font-medium">Description</th>
                            <th class="text-right p-4 text-gray-400 font-medium">Amount</th>
                            <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                            <th class="text-center p-4 text-gray-400 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($wallet_transactions as $txn): 
                        $type_class = $txn['type'] === 'withdrawal' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400';
                        $status = $txn['status'] ?? 'completed';
                        $status_class = match($status) {
                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                            'cancelled' => 'bg-gray-500/20 text-gray-400',
                            default => 'bg-blue-500/20 text-blue-400'
                        };
                        ?>
                        <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                            <td class="p-4 text-white"><?php echo date('M j, Y g:i A', strtotime($txn['created_at'])); ?></td>
                            <td class="p-4 text-center"><span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $type_class; ?>"><?php echo ucfirst($txn['type']); ?></span></td>
                            <td class="p-4 text-gray-300"><?php echo htmlspecialchars($txn['description']); ?></td>
                            <td class="p-4 text-right"><span class="text-lg font-bold <?php echo $txn['type'] === 'withdrawal' ? 'text-red-400' : 'text-emerald-400'; ?>"><?php echo $txn['type'] === 'withdrawal' ? '-' : '+'; ?><?php echo formatMoney($txn['amount']); ?></span></td>
                            <td class="p-4 text-center"><span class="px-3 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>"><?php echo ucfirst($status); ?></span></td>
                            <td class="p-4">
                                <?php if ($txn['type'] === 'injection'): ?>
                                    <div class="flex items-center justify-center space-x-2">
                                        <?php if ($status !== 'completed' && $status !== 'cancelled'): ?>
                                            <button onclick="editInjection(<?php echo $txn['id']; ?>)" class="p-2 text-blue-400 hover:text-blue-300 hover:bg-blue-500/10 rounded transition" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="cancelInjection(<?php echo $txn['id']; ?>)" class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded transition" title="Cancel">
                                                <i class="fas fa-times-circle"></i>
                                            </button>
                                            <button onclick="completeInjection(<?php echo $txn['id']; ?>)" class="p-2 text-emerald-400 hover:text-emerald-300 hover:bg-emerald-500/10 rounded transition" title="Mark Complete">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-xs">No actions</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="p-12 text-center"><i class="fas fa-wallet text-6xl text-gray-600 mb-4"></i><h3 class="text-xl font-bold text-gray-400 mb-2">No Transactions Yet</h3><p class="text-gray-500">Admin wallet transactions will appear here</p></div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Edit, Cancel, Complete injection modals and handlers
        function editInjection(id) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center';
            modal.innerHTML = `
                <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full m-4">
                    <h3 class="text-xl font-bold text-white mb-4">Edit Injection</h3>
                    <form method="POST" id="editForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="edit_injection">
                        <input type="hidden" name="injection_id" value="${id}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Amount</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="Enter amount">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <input type="text" name="description" required
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="Enter description">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                            <textarea name="action_notes" rows="3"
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="Optional notes about this edit"></textarea>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition">Save Changes</button>
                            <button type="button" onclick="document.body.removeChild(this.closest('.fixed'))" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function cancelInjection(id) {
            if (!confirm('Are you sure you want to cancel this injection? This action cannot be undone.')) return;
            
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center';
            modal.innerHTML = `
                <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full m-4">
                    <h3 class="text-xl font-bold text-white mb-4">Cancel Injection</h3>
                    <form method="POST" id="cancelForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="cancel_injection">
                        <input type="hidden" name="injection_id" value="${id}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Cancellation Reason</label>
                            <textarea name="cancellation_reason" rows="3" required
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="Enter reason for cancellation"></textarea>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">Cancel Injection</button>
                            <button type="button" onclick="document.body.removeChild(this.closest('.fixed'))" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">Back</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }

        function completeInjection(id) {
            if (!confirm('Mark this injection as completed? This will update the status to completed.')) return;
            
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center';
            modal.innerHTML = `
                <div class="bg-gray-800 rounded-xl p-6 max-w-md w-full m-4">
                    <h3 class="text-xl font-bold text-white mb-4">Complete Injection</h3>
                    <form method="POST" id="completeForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <input type="hidden" name="action" value="complete_injection">
                        <input type="hidden" name="injection_id" value="${id}">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Completion Notes</label>
                            <textarea name="completion_notes" rows="3"
                                class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white" placeholder="Optional notes about completion"></textarea>
                        </div>
                        
                        <div class="flex space-x-3">
                            <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg transition">Mark Complete</button>
                            <button type="button" onclick="document.body.removeChild(this.closest('.fixed'))" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition">Cancel</button>
                        </div>
                    </form>
                </div>
            `;
            document.body.appendChild(modal);
        }

        const ctx = document.getElementById('feeChart').getContext('2d');
        const feeData = <?php echo json_encode($fee_trend); ?>;
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: feeData.map(item => new Date(item.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                datasets: [{
                    label: 'Withdrawal Fees (3-7%)',
                    data: feeData.map(item => parseFloat(item.withdrawal_fees)),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Platform Fees (1.5%)',
                    data: feeData.map(item => parseFloat(item.platform_fees)),
                    borderColor: '#a855f7',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { labels: { color: '#ffffff' } } },
                scales: {
                    x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(156, 163, 175, 0.1)' } },
                    y: { ticks: { color: '#9ca3af', callback: function(value) { return 'KSh ' + value.toLocaleString(); } }, grid: { color: 'rgba(156, 163, 175, 0.1)' } }
                }
            }
        });
    </script>
</body>
</html>