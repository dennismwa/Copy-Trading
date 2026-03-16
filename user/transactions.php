<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build filter conditions
$where_conditions = ["t.user_id = ?"];
$params = [$user_id];

// FIXED: Proper filter handling for all transaction types
if ($filter !== 'all') {
    $where_conditions[] = "t.type = ?";
    $params[] = $filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM transactions t WHERE $where_clause";
$stmt = $db->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch()['total'];
$total_pages = ceil($total_records / $limit);

// Get transactions with package information
$sql = "
    SELECT t.*, 
           CASE 
               WHEN t.type = 'package_investment' THEN (
                   SELECT p.name 
                   FROM active_packages ap 
                   JOIN packages p ON ap.package_id = p.id 
                   WHERE ap.user_id = t.user_id 
                   AND DATE(ap.created_at) = DATE(t.created_at)
                   LIMIT 1
               )
               ELSE NULL
           END as package_name
    FROM transactions t
    WHERE $where_clause
    ORDER BY t.created_at DESC 
    LIMIT $limit OFFSET $offset
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        COALESCE(SUM(CASE WHEN type = 'deposit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN type = 'withdrawal' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN type = 'roi_payment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_roi,
        COALESCE(SUM(CASE WHEN type = 'package_investment' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_invested,
        COALESCE(SUM(CASE WHEN type = 'referral_commission' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_commissions
    FROM transactions 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Get counts for each filter type
$stmt = $db->prepare("
    SELECT 
        type,
        COUNT(*) as count
    FROM transactions
    WHERE user_id = ?
    GROUP BY type
");
$stmt->execute([$user_id]);
$type_counts = [];
while ($row = $stmt->fetch()) {
    $type_counts[$row['type']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - Ultra Harvest Global</title>
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
        
        .filter-btn {
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: linear-gradient(45deg, #10b981, #34d399);
            color: white;
            border-color: #10b981;
        }
        
        .transaction-card {
            transition: all 0.3s ease;
        }
        
        .transaction-card:hover {
            transform: translateX(5px);
            background: rgba(255, 255, 255, 0.08);
        }

        .badge-count {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            margin-left: 6px;
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

    <!-- Header -->
    <header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Network</a>
                        <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-home text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <i class="fas fa-receipt text-emerald-400 mr-3"></i>
                Transaction History
            </h1>
            <p class="text-xl text-gray-300">Complete record of your financial activities</p>
        </div>

        <!-- Summary Statistics -->
        <section class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Transactions</p>
                        <p class="text-3xl font-bold text-white"><?php echo number_format($stats['total_transactions']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-list text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Deposits</p>
                        <p class="text-2xl font-bold text-emerald-400"><?php echo formatMoney($stats['total_deposits']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-down text-emerald-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Withdrawn</p>
                        <p class="text-2xl font-bold text-red-400"><?php echo formatMoney($stats['total_withdrawals']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total ROI Earned</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo formatMoney($stats['total_roi']); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- FIXED: Filter Tabs with Active State and Counts -->
        <section class="mb-8">
            <div class="glass-card rounded-xl p-6">
                <div class="flex flex-wrap gap-3">
                    <a href="?filter=all" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'all' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-list mr-2"></i>All Transactions
                        <span class="badge-count"><?php echo number_format($stats['total_transactions']); ?></span>
                    </a>
                    <a href="?filter=deposit" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'deposit' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-arrow-down mr-2"></i>Deposits
                        <span class="badge-count"><?php echo number_format($type_counts['deposit'] ?? 0); ?></span>
                    </a>
                    <a href="?filter=withdrawal" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'withdrawal' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-arrow-up mr-2"></i>Withdrawals
                        <span class="badge-count"><?php echo number_format($type_counts['withdrawal'] ?? 0); ?></span>
                    </a>
                    <a href="?filter=package_investment" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'package_investment' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-chart-line mr-2"></i>Investments
                        <span class="badge-count"><?php echo number_format($type_counts['package_investment'] ?? 0); ?></span>
                    </a>
                    <a href="?filter=roi_payment" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'roi_payment' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-coins mr-2"></i>ROI Payments
                        <span class="badge-count"><?php echo number_format($type_counts['roi_payment'] ?? 0); ?></span>
                    </a>
                    <?php if (($type_counts['referral_commission'] ?? 0) > 0): ?>
                    <a href="?filter=referral_commission" class="filter-btn px-6 py-3 rounded-lg font-medium transition <?php echo $filter === 'referral_commission' ? 'active' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                        <i class="fas fa-users mr-2"></i>Commissions
                        <span class="badge-count"><?php echo number_format($type_counts['referral_commission']); ?></span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Transactions List -->
        <section class="glass-card rounded-xl p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white">
                    <?php 
                    $filter_names = [
                        'all' => 'All Transactions',
                        'deposit' => 'Deposits',
                        'withdrawal' => 'Withdrawals',
                        'package_investment' => 'Package Investments',
                        'roi_payment' => 'ROI Payments',
                        'referral_commission' => 'Referral Commissions'
                    ];
                    echo $filter_names[$filter] ?? 'Transactions';
                    ?>
                    <span class="text-gray-400 text-base font-normal ml-2">(<?php echo number_format($total_records); ?> total)</span>
                </h2>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="text-center py-12">
                    <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-400 mb-2">No <?php echo strtolower($filter_names[$filter] ?? 'transactions'); ?> found</h3>
                    <p class="text-gray-500 mb-6">
                        <?php if ($filter === 'all'): ?>
                            You haven't made any transactions yet
                        <?php elseif ($filter === 'deposit'): ?>
                            You haven't made any deposits yet
                        <?php elseif ($filter === 'withdrawal'): ?>
                            You haven't made any withdrawals yet
                        <?php elseif ($filter === 'package_investment'): ?>
                            You haven't made any investments yet
                        <?php elseif ($filter === 'roi_payment'): ?>
                            You haven't received any ROI payments yet
                        <?php else: ?>
                            No transactions of this type found
                        <?php endif; ?>
                    </p>
                    <div class="flex flex-col sm:flex-row gap-3 justify-center">
                        <a href="/user/deposit.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-plus mr-2"></i>Make Deposit
                        </a>
                        <a href="/user/packages.php" class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition">
                            <i class="fas fa-chart-line mr-2"></i>View Packages
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Desktop Table View -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="text-left py-4 text-gray-400">Type</th>
                                <th class="text-right py-4 text-gray-400">Amount</th>
                                <th class="text-center py-4 text-gray-400">Status</th>
                                <th class="text-left py-4 text-gray-400">Description</th>
                                <th class="text-right py-4 text-gray-400">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr class="border-b border-gray-800 hover:bg-gray-800/30 transition">
                                <td class="py-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center
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
                                            ?>"></i>
                                        </div>
                                        <span class="font-medium text-white capitalize">
                                            <?php 
                                            if ($transaction['type'] === 'roi_payment') {
                                                echo 'ROI Earned';
                                            } else {
                                                echo str_replace('_', ' ', $transaction['type']);
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </td>
                                <td class="py-4 text-right">
                                    <?php 
                                    // For ROI payments, show only the ROI amount, not the total
                                    if ($transaction['type'] === 'roi_payment') {
                                        $roi_amount = extractROIFromDescription($transaction['description']);
                                        if ($roi_amount == 0) {
                                            // Fallback: calculate ROI from total amount and description
                                            if (preg_match('/Principal:\s*([0-9,]+\.?[0-9]*)/', $transaction['description'], $matches)) {
                                                $principal = (float)str_replace(',', '', $matches[1]);
                                                $roi_amount = $transaction['amount'] - $principal;
                                            }
                                        }
                                        echo '<span class="text-xl font-bold text-white">' . formatMoney($roi_amount) . '</span>';
                                    } else {
                                        echo '<span class="text-xl font-bold text-white">' . formatMoney($transaction['amount']) . '</span>';
                                    }
                                    ?>
                                    <?php if ($transaction['withdrawal_fee'] && $transaction['withdrawal_fee'] > 0): ?>
                                        <div class="text-xs text-red-400">Fee: <?php echo formatMoney($transaction['withdrawal_fee']); ?></div>
                                    <?php endif; ?>
                                    <?php if ($transaction['phone_number']): ?>
                                        <div class="text-xs text-gray-400"><?php echo $transaction['phone_number']; ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-medium
                                        <?php 
                                        echo match($transaction['status']) {
                                            'completed' => 'bg-emerald-500/20 text-emerald-400',
                                            'pending' => 'bg-yellow-500/20 text-yellow-400',
                                            'processing' => 'bg-blue-500/20 text-blue-400',
                                            'failed' => 'bg-red-500/20 text-red-400',
                                            'cancelled' => 'bg-gray-500/20 text-gray-400',
                                            default => 'bg-gray-500/20 text-gray-400'
                                        };
                                        ?>">
                                        <?php echo ucfirst($transaction['status']); ?>
                                    </span>
                                </td>
                                <td class="py-4">
                                    <div class="max-w-xs">
                                        <p class="text-white text-sm"><?php echo htmlspecialchars($transaction['description'] ?? 'N/A'); ?></p>
                                        <?php if ($transaction['package_name']): ?>
                                            <p class="text-emerald-400 text-xs mt-1">
                                                <i class="fas fa-box mr-1"></i>Package: <?php echo $transaction['package_name']; ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if ($transaction['mpesa_receipt']): ?>
                                            <p class="text-blue-400 text-xs mt-1">
                                                <i class="fas fa-receipt mr-1"></i>Receipt: <?php echo $transaction['mpesa_receipt']; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-4 text-right">
                                    <p class="text-white text-sm"><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></p>
                                    <p class="text-gray-400 text-xs"><?php echo date('g:i A', strtotime($transaction['created_at'])); ?></p>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="md:hidden space-y-4">
                    <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-card bg-gray-800/50 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 rounded-full flex items-center justify-center
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
                                    ?>"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-white capitalize">
                                        <?php 
                                        if ($transaction['type'] === 'roi_payment') {
                                            echo 'ROI Earned';
                                        } else {
                                            echo str_replace('_', ' ', $transaction['type']);
                                        }
                                        ?>
                                    </p>
                                    <p class="text-xs text-gray-400"><?php echo timeAgo($transaction['created_at']); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php 
                                // For ROI payments, show only the ROI amount, not the total
                                if ($transaction['type'] === 'roi_payment') {
                                    $roi_amount = extractROIFromDescription($transaction['description']);
                                    if ($roi_amount == 0) {
                                        // Fallback: calculate ROI from total amount and description
                                        if (preg_match('/Principal:\s*([0-9,]+\.?[0-9]*)/', $transaction['description'], $matches)) {
                                            $principal = (float)str_replace(',', '', $matches[1]);
                                            $roi_amount = $transaction['amount'] - $principal;
                                        }
                                    }
                                    echo '<p class="text-lg font-bold text-white">' . formatMoney($roi_amount) . '</p>';
                                } else {
                                    echo '<p class="text-lg font-bold text-white">' . formatMoney($transaction['amount']) . '</p>';
                                }
                                ?>
                                <?php if ($transaction['withdrawal_fee'] && $transaction['withdrawal_fee'] > 0): ?>
                                    <p class="text-xs text-red-400">Fee: <?php echo formatMoney($transaction['withdrawal_fee']); ?></p>
                                <?php endif; ?>
                                <span class="px-2 py-1 rounded text-xs font-medium
                                    <?php 
                                    echo match($transaction['status']) {
                                        'completed' => 'bg-emerald-500/20 text-emerald-400',
                                        'pending' => 'bg-yellow-500/20 text-yellow-400',
                                        'processing' => 'bg-blue-500/20 text-blue-400',
                                        'failed' => 'bg-red-500/20 text-red-400',
                                        'cancelled' => 'bg-gray-500/20 text-gray-400',
                                        default => 'bg-gray-500/20 text-gray-400'
                                    };
                                    ?>">
                                    <?php echo ucfirst($transaction['status']); ?>
                                </span>
                            </div>
                        </div>
                        <?php if ($transaction['description']): ?>
                            <p class="text-gray-300 text-sm mb-2"><?php echo htmlspecialchars($transaction['description']); ?></p>
                        <?php endif; ?>
                        <?php if ($transaction['package_name']): ?>
                            <p class="text-emerald-400 text-xs">
                                <i class="fas fa-box mr-1"></i>Package: <?php echo $transaction['package_name']; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($transaction['phone_number']): ?>
                            <p class="text-blue-400 text-xs mt-1">
                                <i class="fas fa-phone mr-1"></i><?php echo $transaction['phone_number']; ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($transaction['mpesa_receipt']): ?>
                            <p class="text-green-400 text-xs mt-1">
                                <i class="fas fa-receipt mr-1"></i><?php echo $transaction['mpesa_receipt']; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="flex items-center justify-center mt-8 space-x-2">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page-1; ?>" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" 
                           class="px-4 py-2 rounded-lg transition <?php echo $i === $page ? 'bg-emerald-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?filter=<?php echo $filter; ?>&page=<?php echo $page+1; ?>" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- Export Options -->
        <section class="text-center mt-8">
            <div class="glass-card rounded-xl p-6 inline-block">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-download mr-2"></i>Export Transactions
                </h3>
                <div class="flex flex-wrap gap-3 justify-center">
                    <a href="/user/export.php?type=pdf&filter=<?php echo $filter; ?>" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-file-pdf mr-2"></i>Export PDF
                    </a>
                    <a href="/user/export.php?type=csv&filter=<?php echo $filter; ?>" class="px-6 py-3 bg-green-600 hover:bg-green-700 text-white rounded-lg font-medium transition">
                        <i class="fas fa-file-csv mr-2"></i>Export CSV
                    </a>
                    <a href="?filter=all" class="px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium transition">
                        <i class="fas fa-sync mr-2"></i>Reset Filter
                    </a>
                </div>
            </div>
        </section>
    </main>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-50">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-home text-xl mb-1"></i>
                <span class="text-xs">Home</span>
            </a>
            <a href="/user/packages.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-chart-line text-xl mb-1"></i>
                <span class="text-xs">Trade</span>
            </a>
            <a href="/user/transactions.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-receipt text-xl mb-1"></i>
                <span class="text-xs">History</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/profile.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-user text-xl mb-1"></i>
                <span class="text-xs">Profile</span>
            </a>
        </div>
    </div>
<!-- Live Chat Support Widget -->
<?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>

    <script>
        // Add smooth transitions for filter changes
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    // Show loading state
                    const icon = this.querySelector('i');
                    const originalIcon = icon.className;
                    icon.className = 'fas fa-spinner fa-spin mr-2';
                    
                    // Let the navigation proceed
                    setTimeout(() => {
                        icon.className = originalIcon;
                    }, 500);
                }
            });
        });

        // Highlight current filter on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const currentFilter = urlParams.get('filter') || 'all';
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                const href = btn.getAttribute('href');
                if (href.includes('filter=' + currentFilter) || (currentFilter === 'all' && href === '?filter=all')) {
                    btn.classList.add('active');
                    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                }
            });
        });

        // Add print functionality
        function printTransactions() {
            window.print();
        }

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'A' for All
            if (e.key === 'a' && !e.ctrlKey && !e.metaKey) {
                window.location.href = '?filter=all';
            }
            // Press 'D' for Deposits
            if (e.key === 'd' && !e.ctrlKey && !e.metaKey) {
                window.location.href = '?filter=deposit';
            }
            // Press 'W' for Withdrawals
            if (e.key === 'w' && !e.ctrlKey && !e.metaKey) {
                window.location.href = '?filter=withdrawal';
            }
            // Press 'I' for Investments
            if (e.key === 'i' && !e.ctrlKey && !e.metaKey) {
                window.location.href = '?filter=package_investment';
            }
            // Press 'R' for ROI
            if (e.key === 'r' && !e.ctrlKey && !e.metaKey) {
                window.location.href = '?filter=roi_payment';
            }
        });

        // Smooth scroll for mobile filter buttons
        const filterContainer = document.querySelector('.flex.flex-wrap.gap-3');
        if (filterContainer && window.innerWidth < 768) {
            let isDown = false;
            let startX;
            let scrollLeft;

            filterContainer.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - filterContainer.offsetLeft;
                scrollLeft = filterContainer.scrollLeft;
            });

            filterContainer.addEventListener('mouseleave', () => {
                isDown = false;
            });

            filterContainer.addEventListener('mouseup', () => {
                isDown = false;
            });

            filterContainer.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - filterContainer.offsetLeft;
                const walk = (x - startX) * 2;
                filterContainer.scrollLeft = scrollLeft - walk;
            });
        }

        // Auto-refresh for pending transactions (every 30 seconds)
        <?php if (!empty($transactions)): ?>
        <?php 
        $hasPending = false;
        foreach ($transactions as $t) {
            if (in_array($t['status'], ['pending', 'processing'])) {
                $hasPending = true;
                break;
            }
        }
        ?>
        <?php if ($hasPending): ?>
        let autoRefreshTimer;
        function startAutoRefresh() {
            autoRefreshTimer = setInterval(() => {
                // Show subtle refresh indicator
                const refreshIndicator = document.createElement('div');
                refreshIndicator.className = 'fixed top-20 right-4 bg-blue-600 text-white px-4 py-2 rounded-lg shadow-lg text-sm z-50';
                refreshIndicator.innerHTML = '<i class="fas fa-sync fa-spin mr-2"></i>Checking for updates...';
                document.body.appendChild(refreshIndicator);
                
                // Reload page after 1 second
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }, 30000); // 30 seconds
        }

        // Start auto-refresh if there are pending transactions
        startAutoRefresh();

        // Clear timer on page unload
        window.addEventListener('beforeunload', () => {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
            }
        });
        <?php endif; ?>
        <?php endif; ?>
    </script>

    <!-- Print Styles -->
    <style media="print">
        header, .mobile-nav, .filter-btn, .export-options, footer {
            display: none !important;
        }
        
        body {
            background: white !important;
            color: black !important;
        }
        
        .glass-card {
            background: white !important;
            border: 1px solid #ddd !important;
        }
        
        .transaction-card {
            background: white !important;
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
        }
        
        table {
            page-break-inside: auto;
        }
        
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        @page {
            margin: 1cm;
        }
    </style>
</body>
</html>