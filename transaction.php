<?php
/**
 * View-Only Transactions Page - Restricted Access
 * Same data as admin/transactions.php but no editing/approving allowed
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

// Get filter parameters
$type_filter = $_GET['type'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = ["1=1"];
$params = [];

if ($type_filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $type_filter;
}

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR t.mpesa_receipt LIKE ? OR t.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get transactions
$transactions = [];
$total_records = 0;
$total_pages = 1;

try {
    // Get total count
    $count_sql = "
        SELECT COUNT(*) as total 
        FROM transactions t 
        JOIN users u ON t.user_id = u.id 
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_records = $result ? $result['total'] : 0;
    $total_pages = ceil($total_records / $limit);

    // Get transactions
    $sql = "
        SELECT t.id, t.user_id, t.type, t.amount, t.status, t.mpesa_receipt, t.mpesa_request_id, 
               t.phone_number, t.description, t.admin_notes, t.created_at, t.updated_at,
               u.full_name, u.email, u.phone,
               admin.full_name as processed_by_name
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN users admin ON t.processed_by = admin.id
        WHERE $where_clause
        ORDER BY t.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load transactions: " . $e->getMessage());
}

// Get summary statistics
$stats = [
    'total_transactions' => 0,
    'pending_transactions' => 0,
    'pending_deposits' => 0,
    'pending_withdrawals' => 0,
    'today_deposits' => 0,
    'today_withdrawals' => 0
];

try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_transactions,
            SUM(CASE WHEN type = 'deposit' AND status = 'pending' THEN 1 ELSE 0 END) as pending_deposits,
            SUM(CASE WHEN type = 'withdrawal' AND status = 'pending' THEN 1 ELSE 0 END) as pending_withdrawals,
            COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' AND DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) as today_deposits,
            COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' AND DATE(created_at) = CURDATE() THEN amount ELSE 0 END), 0) as today_withdrawals
        FROM transactions
    ";
    $stmt = $db->query($stats_sql);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $stats = $result;
    }
} catch (Exception $e) {
    error_log("Failed to get stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions - Ultra Harvest Admin</title>
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
        
        .transaction-row:hover {
            background: rgba(255, 255, 255, 0.05);
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
                        <a href="/active-trade.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <a href="/transaction.php" class="text-emerald-400 font-medium">Transactions</a>
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
        
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Transactions</h1>
                <p class="text-gray-400">View all platform transactions (View-only access)</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_transactions']); ?></p>
                <p class="text-gray-400 text-sm">Total Transactions</p>
            </div>
        </div>

        <!-- Statistics Overview -->
        <section class="grid md:grid-cols-2 lg:grid-cols-6 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-2xl font-bold"><?php echo number_format($stats['pending_transactions']); ?></p>
                    <p class="text-gray-400 text-sm">Pending</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-blue-400 text-2xl font-bold"><?php echo number_format($stats['pending_deposits']); ?></p>
                    <p class="text-gray-400 text-sm">Pending Deposits</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-red-400 text-2xl font-bold"><?php echo number_format($stats['pending_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Pending Withdrawals</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-lg font-bold"><?php echo formatMoney($stats['today_deposits']); ?></p>
                    <p class="text-gray-400 text-sm">Today's Deposits</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-lg font-bold"><?php echo formatMoney($stats['today_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Today's Withdrawals</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-white text-lg font-bold"><?php echo formatMoney($stats['today_deposits'] - $stats['today_withdrawals']); ?></p>
                    <p class="text-gray-400 text-sm">Net Today</p>
                </div>
            </div>
        </section>

        <!-- Filters -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <form method="GET" class="grid md:grid-cols-4 gap-4">
                <!-- Type Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Transaction Type</label>
                    <select name="type" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                        <option value="deposit" <?php echo $type_filter === 'deposit' ? 'selected' : ''; ?>>Deposits</option>
                        <option value="withdrawal" <?php echo $type_filter === 'withdrawal' ? 'selected' : ''; ?>>Withdrawals</option>
                        <option value="package_investment" <?php echo $type_filter === 'package_investment' ? 'selected' : ''; ?>>Investments</option>
                        <option value="roi_payment" <?php echo $type_filter === 'roi_payment' ? 'selected' : ''; ?>>ROI Payments</option>
                        <option value="referral_commission" <?php echo $type_filter === 'referral_commission' ? 'selected' : ''; ?>>Commissions</option>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Search -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
                    <input 
                        type="text" 
                        name="search" 
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="User name, email, receipt..."
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-600 rounded text-white focus:border-emerald-500 focus:outline-none"
                    >
                </div>

                <!-- Submit -->
                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded font-medium transition">
                        <i class="fas fa-search mr-2"></i>Filter
                    </button>
                </div>
            </form>
        </section>

        <!-- Transactions Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <?php if (!empty($transactions)): ?>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400 font-medium">User</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Type</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Amount</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                                <th class="text-left p-4 text-gray-400 font-medium">Details</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="transaction-row border-b border-gray-800">
                                <!-- User Column -->
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-sm font-bold text-white">
                                            <?php echo strtoupper(substr($transaction['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($transaction['full_name']); ?></p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($transaction['email']); ?></p>
                                            <?php if ($transaction['phone']): ?>
                                                <p class="text-xs text-blue-400"><?php echo htmlspecialchars($transaction['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- Type Column -->
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($transaction['type']) {
                                            'deposit' => 'bg-emerald-500/20 text-emerald-400',
                                            'withdrawal' => 'bg-red-500/20 text-red-400',
                                            'roi_payment' => 'bg-yellow-500/20 text-yellow-400',
                                            'package_investment' => 'bg-blue-500/20 text-blue-400',
                                            'referral_commission' => 'bg-purple-500/20 text-purple-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <i class="fas <?php 
                                        echo match($transaction['type']) {
                                            'deposit' => 'fa-arrow-down',
                                            'withdrawal' => 'fa-arrow-up',
                                            'roi_payment' => 'fa-coins',
                                            'package_investment' => 'fa-chart-line',
                                            'referral_commission' => 'fa-users',
                                            default => 'fa-exchange-alt'
                                        };
                                        ?> mr-1"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $transaction['type'])); ?>
                                    </span>
                                </td>
                                
                                <!-- Amount Column -->
                                <td class="p-4 text-right">
                                    <p class="text-xl font-bold text-white"><?php echo formatMoney($transaction['amount']); ?></p>
                                </td>
                                
                                <!-- Status Column -->
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($transaction['status']) {
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'failed' => 'bg-red-500/20 text-red-400',
                                            'cancelled' => 'bg-gray-500/20 text-gray-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                
                                <!-- Details Column -->
                                <td class="p-4">
                                    <div class="max-w-xs">
                                        <p class="text-white text-sm"><?php echo htmlspecialchars(substr($transaction['description'] ?? 'N/A', 0, 50)); ?></p>
                                        <?php if ($transaction['mpesa_receipt']): ?>
                                            <p class="text-green-400 text-xs mt-1">Receipt: <?php echo htmlspecialchars($transaction['mpesa_receipt']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($transaction['processed_by_name']): ?>
                                            <p class="text-blue-400 text-xs mt-1">By: <?php echo htmlspecialchars($transaction['processed_by_name']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <!-- Date Column -->
                                <td class="p-4 text-center text-gray-300 text-sm">
                                    <p><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-6 border-t border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> transactions
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                   class="px-3 py-2 rounded transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?type=<?php echo htmlspecialchars($type_filter); ?>&status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="p-12 text-center">
                    <i class="fas fa-receipt text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No transactions found</h3>
                    <p class="text-gray-500">No transactions match your current filters</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
