<?php
/**
 * View-Only Users Page - Restricted Access
 * Same data as admin/users.php but no editing/deleting allowed
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
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query conditions
$where_conditions = ["u.is_admin = 0"];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "u.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count
$users = [];
$total_records = 0;
$total_pages = 1;

try {
    $count_sql = "SELECT COUNT(*) as total FROM users u WHERE $where_clause";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $total_records = $result ? $result['total'] : 0;
    $total_pages = ceil($total_records / $limit);

    // Get users with transaction statistics
    $sql = "
        SELECT u.id, u.email, u.full_name, u.phone, u.wallet_balance, u.referral_code, 
               u.referred_by, u.referral_earnings, u.status, u.created_at, u.updated_at,
               COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
               COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_withdrawn,
               COUNT(DISTINCT ref.id) as total_referrals
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        LEFT JOIN users ref ON u.id = ref.referred_by
        WHERE $where_clause
        GROUP BY u.id, u.email, u.full_name, u.phone, u.wallet_balance, u.referral_code, 
                 u.referred_by, u.referral_earnings, u.status, u.created_at, u.updated_at
        ORDER BY u.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load users: " . $e->getMessage());
}

// Get summary statistics
$stats = ['total_users' => 0, 'active_users' => 0, 'suspended_users' => 0, 'banned_users' => 0, 'new_users_30d' => 0];
try {
    $stats_sql = "
        SELECT 
            COUNT(*) as total_users,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_users,
            SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users,
            SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_30d
        FROM users 
        WHERE is_admin = 0
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
    <title>Users - Ultra Harvest Admin</title>
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
        
        .user-row {
            transition: all 0.3s ease;
        }
        
        .user-row:hover {
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
                        <a href="/user.php" class="text-emerald-400 font-medium">Users</a>
                        <a href="/active-trade.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
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
        
        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-3xl font-bold text-white">Users</h1>
                <p class="text-gray-400">View all user accounts (View-only access)</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-emerald-400"><?php echo number_format($stats['total_users']); ?></p>
                <p class="text-gray-400 text-sm">Total Users</p>
            </div>
        </div>

        <!-- Statistics Overview -->
        <section class="grid md:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-emerald-400 text-3xl font-bold"><?php echo number_format($stats['active_users']); ?></p>
                    <p class="text-gray-400 text-sm">Active Users</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-yellow-400 text-3xl font-bold"><?php echo number_format($stats['suspended_users']); ?></p>
                    <p class="text-gray-400 text-sm">Suspended</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-red-400 text-3xl font-bold"><?php echo number_format($stats['banned_users']); ?></p>
                    <p class="text-gray-400 text-sm">Banned</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-blue-400 text-3xl font-bold"><?php echo number_format($stats['new_users_30d']); ?></p>
                    <p class="text-gray-400 text-sm">New (30d)</p>
                </div>
            </div>
            <div class="glass-card rounded-xl p-6">
                <div class="text-center">
                    <p class="text-purple-400 text-3xl font-bold"><?php echo number_format($stats['total_users']); ?></p>
                    <p class="text-gray-400 text-sm">Total Users</p>
                </div>
            </div>
        </section>

        <!-- Filters and Search -->
        <section class="glass-card rounded-xl p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <!-- Status Filter -->
                <div class="flex flex-wrap gap-2">
                    <a href="?status=all&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'all' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        All Users
                    </a>
                    <a href="?status=active&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'active' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Active
                    </a>
                    <a href="?status=suspended&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'suspended' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Suspended
                    </a>
                    <a href="?status=banned&search=<?php echo urlencode($search); ?>" 
                       class="px-4 py-2 rounded-lg font-medium transition <?php echo $status_filter === 'banned' ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        Banned
                    </a>
                </div>

                <!-- Search -->
                <form method="GET" class="flex items-center space-x-3">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        <input 
                            type="text" 
                            name="search" 
                            value="<?php echo htmlspecialchars($search); ?>"
                            placeholder="Search users..." 
                            class="pl-10 pr-4 py-2 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                        >
                    </div>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                        Search
                    </button>
                    <?php if ($search): ?>
                    <a href="?status=<?php echo htmlspecialchars($status_filter); ?>" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg font-medium transition">
                        Clear
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- Users Table -->
        <section class="glass-card rounded-xl overflow-hidden">
            <?php if (!empty($users)): ?>
                <!-- Desktop Table -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-800/50">
                            <tr>
                                <th class="text-left p-4 text-gray-400 font-medium">User</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Status</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Balance</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Deposited</th>
                                <th class="text-right p-4 text-gray-400 font-medium">Withdrawn</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Referrals</th>
                                <th class="text-center p-4 text-gray-400 font-medium">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr class="user-row border-b border-gray-800">
                                <td class="p-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-full flex items-center justify-center">
                                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-white"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                            <p class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                            <?php if ($user['phone']): ?>
                                                <p class="text-xs text-blue-400"><?php echo htmlspecialchars($user['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($user['status']) {
                                            'active' => 'bg-emerald-500/20 text-emerald-400',
                                            'suspended' => 'bg-yellow-500/20 text-yellow-400',
                                            'banned' => 'bg-red-500/20 text-red-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <p class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                                </td>
                                <td class="p-4 text-right">
                                    <p class="text-emerald-400 font-medium"><?php echo formatMoney($user['total_deposited']); ?></p>
                                </td>
                                <td class="p-4 text-right">
                                    <p class="text-red-400 font-medium"><?php echo formatMoney($user['total_withdrawn']); ?></p>
                                </td>
                                <td class="p-4 text-center">
                                    <span class="text-purple-400 font-medium"><?php echo number_format($user['total_referrals']); ?></span>
                                </td>
                                <td class="p-4 text-center text-gray-300 text-sm">
                                    <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="lg:hidden p-4">
                    <div class="space-y-4">
                        <?php foreach ($users as $user): ?>
                        <div class="bg-gray-800/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 bg-gradient-to-r from-emerald-500 to-blue-500 rounded-full flex items-center justify-center">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                                    </div>
                                    <div>
                                        <p class="font-medium text-white"><?php echo htmlspecialchars($user['full_name']); ?></p>
                                        <p class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                    </div>
                                </div>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php 
                                    echo match($user['status']) {
                                        'active' => 'bg-emerald-500/20 text-emerald-400',
                                        'suspended' => 'bg-yellow-500/20 text-yellow-400',
                                        'banned' => 'bg-red-500/20 text-red-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400">Balance</p>
                                    <p class="font-bold text-white"><?php echo formatMoney($user['wallet_balance']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Deposited</p>
                                    <p class="font-bold text-emerald-400"><?php echo formatMoney($user['total_deposited']); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Referrals</p>
                                    <p class="font-bold text-purple-400"><?php echo number_format($user['total_referrals']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="p-6 border-t border-gray-800">
                    <div class="flex items-center justify-between">
                        <div class="text-sm text-gray-400">
                            Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo number_format($total_records); ?> users
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if ($page > 1): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page-1; ?>" 
                                   class="px-3 py-2 bg-gray-800 text-gray-300 rounded hover:bg-gray-700 transition">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" 
                                   class="px-3 py-2 rounded transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page+1; ?>" 
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
                    <i class="fas fa-users text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No users found</h3>
                    <p class="text-gray-500">No users match your current filters</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
