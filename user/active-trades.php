<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// CRITICAL FIX: Auto-process matured packages for this user when they view the page
try {
    // Find matured packages for this user
    $stmt = $db->prepare("
        SELECT ap.*, u.id as user_id, p.name as package_name, p.roi_percentage
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.user_id = ? AND ap.status = 'active' 
        AND ap.maturity_date <= NOW()
    ");
    $stmt->execute([$user_id]);
    $matured_packages = $stmt->fetchAll();
    
    if (!empty($matured_packages)) {
        foreach ($matured_packages as $package) {
            try {
                $db->beginTransaction();
                
                // CORRECTED: Credit investment amount + ROI to wallet
                // The investment was deducted when package was purchased, so we return principal + earnings
                $investment_amount = $package['investment_amount'];
                $roi_amount = $package['expected_roi'];
                $total_return = $investment_amount + $roi_amount;
                
                // Credit user wallet with total return (principal + ROI)
                $stmt = $db->prepare("
                    UPDATE users 
                    SET wallet_balance = wallet_balance + ?,
                        total_roi_earned = total_roi_earned + ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$total_return, $roi_amount, $user_id]);
                
                // Create ROI payment transaction (record total return)
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                    VALUES (?, 'roi_payment', ?, 'completed', ?, NOW())
                ");
                $description = "Package completion: {$package['package_name']} - Principal: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . " = " . formatMoney($total_return);
                $stmt->execute([$user_id, $total_return, $description]);
                
                // Mark package as completed
                $stmt = $db->prepare("
                    UPDATE active_packages 
                    SET status = 'completed', completed_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$package['id']]);
                
                // Send notification
                sendNotification(
                    $user_id,
                    'Package Completed! 🎉',
                    "Your {$package['package_name']} package has matured! Total return: " . formatMoney($total_return) . " (Principal: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . ") has been credited to your wallet.",
                    'success'
                );
                
                $db->commit();
                
                error_log("Auto-processed matured package ID {$package['id']} for user {$user_id}, Total return: {$total_return} (Principal: {$investment_amount} + ROI: {$roi_amount})");
                
            } catch (Exception $e) {
                $db->rollBack();
                error_log("Error auto-processing package ID {$package['id']}: " . $e->getMessage());
            }
        }
        
        // Refresh user balance after processing
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
    
} catch (Exception $e) {
    error_log("Error checking for matured packages: " . $e->getMessage());
}

// FIXED: Get active packages with proper JOIN and all necessary fields
$stmt = $db->prepare("
    SELECT 
        ap.id,
        ap.user_id,
        ap.package_id,
        ap.investment_amount,
        ap.expected_roi,
        ap.roi_percentage,
        ap.duration_hours,
        ap.maturity_date,
        ap.status,
        ap.created_at,
        ap.completed_at,
        p.name as package_name, 
        p.icon, 
        p.roi_percentage as package_roi
    FROM active_packages ap 
    INNER JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    ORDER BY ap.created_at DESC
");
$stmt->execute([$user_id]);
$active_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug logging
error_log("Fetching active trades for user {$user_id}");
error_log("Active trades count: " . count($active_trades));
if (!empty($active_trades)) {
    error_log("First active trade: " . print_r($active_trades[0], true));
}

// Get completed packages
$stmt = $db->prepare("
    SELECT 
        ap.id,
        ap.user_id,
        ap.package_id,
        ap.investment_amount,
        ap.expected_roi,
        ap.roi_percentage,
        ap.completed_at,
        ap.created_at,
        p.name as package_name, 
        p.icon
    FROM active_packages ap 
    INNER JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'completed' 
    ORDER BY ap.completed_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$completed_trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

error_log("Completed trades count: " . count($completed_trades));

// Calculate statistics
$total_investment = 0;
$total_earnings = 0;
$all_time_profits = 0;

foreach ($active_trades as $trade) {
    $total_investment += $trade['investment_amount'];
    $total_earnings += $trade['expected_roi'];
}

// Get completed stats
$stmt = $db->prepare("
    SELECT 
        COALESCE(SUM(investment_amount), 0) as completed_investment,
        COALESCE(SUM(expected_roi), 0) as completed_earnings
    FROM active_packages 
    WHERE user_id = ? AND status = 'completed'
");
$stmt->execute([$user_id]);
$completed_stats = $stmt->fetch();

$all_time_profits = $completed_stats['completed_earnings'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Trades - Ultra Harvest Global</title>
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
        
        .countdown {
            font-family: 'Courier New', monospace;
        }
        
        .progress-bar {
            transition: width 0.3s ease;
        }
        
        .matured-glow {
            animation: glowPulse 2s infinite;
        }
        
        @keyframes glowPulse {
            0%, 100% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.5); }
            50% { box-shadow: 0 0 40px rgba(16, 185, 129, 0.8); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .trade-card {
            animation: fadeIn 0.5s ease-out;
        }

        /* Mobile spacing */
        @media (max-width: 768px) {
            main {
                padding-bottom: 5rem;
            }
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
                <div class="flex items-center space-x-4 md:space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-2 md:space-x-3">
                        <div class="w-8 h-8 md:w-10 md:h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                            <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        </div>
                    </a>
                    
                    <nav class="hidden md:flex space-x-6">
                        <a href="/user/dashboard.php" class="text-gray-300 hover:text-emerald-400 transition">Home</a>
                        <a href="/user/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Trade</a>
                        <a href="/user/referrals.php" class="text-gray-300 hover:text-emerald-400 transition">Network</a>
                        <a href="/user/active-trades.php" class="text-emerald-400 font-medium">Active Trades</a>
                        <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                    </nav>
                </div>

                <div class="flex items-center space-x-2 md:space-x-4">
                    <div class="flex items-center space-x-1 md:space-x-2 bg-gray-700/50 rounded-full px-2 md:px-4 py-1 md:py-2">
                        <i class="fas fa-wallet text-emerald-400 text-sm md:text-base"></i>
                        <span class="text-xs md:text-sm text-gray-300 hidden sm:inline">Balance:</span>
                        <span class="font-bold text-white text-xs md:text-base"><?php echo formatMoney($user['wallet_balance']); ?></span>
                    </div>
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-home text-lg md:text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6 md:py-8">
        
        <!-- Page Header -->
        <div class="text-center mb-6 md:mb-8">
            <h1 class="text-3xl md:text-4xl font-bold mb-2">
                <i class="fas fa-briefcase text-emerald-400 mr-3"></i>
                Active Trades
            </h1>
            <p class="text-base md:text-xl text-gray-300">Monitor your active trading packages</p>
        </div>

        <!-- Statistics Overview -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-6 mb-6 md:mb-8">
            <div class="glass-card rounded-xl p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs md:text-sm">Active Trades</p>
                        <p class="text-2xl md:text-3xl font-bold text-white"><?php echo count($active_trades); ?></p>
                    </div>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-briefcase text-blue-400 text-lg md:text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs md:text-sm">Completed</p>
                        <p class="text-2xl md:text-3xl font-bold text-emerald-400"><?php echo count($completed_trades); ?></p>
                    </div>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-emerald-400 text-lg md:text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs md:text-sm">All Time Profits</p>
                        <p class="text-lg md:text-2xl font-bold text-yellow-400"><?php echo formatMoney($all_time_profits); ?></p>
                    </div>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-trophy text-yellow-400 text-lg md:text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="glass-card rounded-xl p-4 md:p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-xs md:text-sm">Total Investment</p>
                        <p class="text-lg md:text-2xl font-bold text-purple-400"><?php echo formatMoney($total_investment); ?></p>
                    </div>
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-purple-400 text-lg md:text-xl"></i>
                    </div>
                </div>
            </div>
        </section>

        <!-- Active Trades Section -->
        <section class="mb-6 md:mb-8">
            <div class="glass-card rounded-xl p-4 md:p-6">
                <div class="flex items-center justify-between mb-4 md:mb-6">
                    <h2 class="text-xl md:text-2xl font-bold text-white">
                        <i class="fas fa-chart-line text-emerald-400 mr-2"></i>
                        Your Active Trades
                    </h2>
                    <?php if (!empty($active_trades)): ?>
                    <button onclick="location.reload()" class="text-sm text-gray-400 hover:text-emerald-400 transition">
                        <i class="fas fa-sync-alt mr-1"></i>Refresh
                    </button>
                    <?php endif; ?>
                </div>

                <?php if (empty($active_trades)): ?>
                    <div class="text-center py-8 md:py-12">
                        <i class="fas fa-briefcase text-5xl md:text-6xl text-gray-600 mb-4"></i>
                        <h3 class="text-lg md:text-xl font-bold text-gray-400 mb-2">No Active Trades</h3>
                        <p class="text-sm md:text-base text-gray-500 mb-4 md:mb-6">Start trading by activating a package</p>
                        <a href="/user/packages.php" class="px-4 md:px-6 py-2 md:py-3 bg-orange-500 hover:bg-orange-600 text-white rounded-lg font-medium transition inline-block text-sm md:text-base">
                            <i class="fas fa-chart-line mr-2"></i>Activate Trade Now
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6">
                        <?php foreach ($active_trades as $trade): ?>
                        <?php
                        $start_time = strtotime($trade['created_at']);
                        $end_time = strtotime($trade['maturity_date']);
                        $current_time = time();
                        $is_matured = $current_time >= $end_time;
                        ?>
                        <div class="trade-card bg-gray-800/50 rounded-xl p-4 md:p-6 border border-emerald-500/30 <?php echo $is_matured ? 'matured-glow' : ''; ?>">
                            <?php if ($is_matured): ?>
                            <div class="mb-4 p-3 bg-emerald-500/20 border border-emerald-500/50 rounded-lg text-center">
                                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                                <span class="text-emerald-400 font-bold">MATURED!</span>
                                <p class="text-xs text-gray-300 mt-1">ROI credited to your wallet</p>
                            </div>
                            <?php endif; ?>
                            
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="text-2xl md:text-3xl"><?php echo htmlspecialchars($trade['icon']); ?></div>
                                    <div>
                                        <h3 class="font-bold text-white text-sm md:text-base"><?php echo htmlspecialchars($trade['package_name']); ?></h3>
                                        <p class="text-xs md:text-sm text-emerald-400">
                                            <i class="fas fa-circle text-xs animate-pulse mr-1"></i>Active
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-base md:text-lg font-bold text-white"><?php echo formatMoney($trade['investment_amount']); ?></p>
                                    <p class="text-xs md:text-sm text-gray-400">Invested</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-3 md:gap-4 mb-4">
                                <div>
                                    <p class="text-xs md:text-sm text-gray-400">Expected ROI</p>
                                    <p class="text-sm md:text-base font-bold text-emerald-400"><?php echo formatMoney($trade['expected_roi']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs md:text-sm text-gray-400">ROI Rate</p>
                                    <p class="text-sm md:text-base font-bold text-yellow-400"><?php echo $trade['roi_percentage']; ?>%</p>
                                </div>
                            </div>
                            
                            <?php if (!$is_matured): ?>
                            <!-- Progress Bar -->
                            <?php
                            $total_duration = $end_time - $start_time;
                            $elapsed = $current_time - $start_time;
                            $progress = min(100, max(0, ($elapsed / $total_duration) * 100));
                            ?>
                            <div class="mb-4">
                                <div class="flex justify-between text-xs text-gray-400 mb-1">
                                    <span>Progress</span>
                                    <span><?php echo number_format($progress, 1); ?>%</span>
                                </div>
                                <div class="w-full bg-gray-700 rounded-full h-2">
                                    <div class="progress-bar bg-gradient-to-r from-emerald-500 to-yellow-500 h-2 rounded-full" 
                                         style="width: <?php echo $progress; ?>%"></div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-800/50 rounded-lg p-3">
                                <p class="text-xs md:text-sm text-gray-400 mb-1">Matures in:</p>
                                <div class="countdown text-base md:text-lg font-bold text-white" data-maturity="<?php echo $trade['maturity_date']; ?>">
                                    Calculating...
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="bg-emerald-500/20 rounded-lg p-3 text-center">
                                <p class="text-emerald-400 font-bold text-base md:text-lg">
                                    <i class="fas fa-money-bill-wave mr-2"></i>
                                    Completed!
                                </p>
                                <p class="text-xs md:text-sm text-gray-300 mt-1">Check your wallet balance</p>
                                <a href="/user/withdraw.php" class="mt-3 inline-block px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs md:text-sm font-medium transition">
                                    Withdraw Now
                                </a>
                            </div>
                            <?php endif; ?>

                            <!-- Trade Details -->
                            <div class="mt-3 pt-3 border-t border-gray-700">
                                <div class="flex justify-between text-xs text-gray-500">
                                    <span>Started:</span>
                                    <span><?php echo date('M j, g:i A', strtotime($trade['created_at'])); ?></span>
                                </div>
                                <div class="flex justify-between text-xs text-gray-500 mt-1">
                                    <span>Matures:</span>
                                    <span><?php echo date('M j, g:i A', strtotime($trade['maturity_date'])); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Completed Trades Section -->
        <?php if (!empty($completed_trades)): ?>
        <section>
            <div class="glass-card rounded-xl p-4 md:p-6">
                <h2 class="text-xl md:text-2xl font-bold text-white mb-4 md:mb-6">
                    <i class="fas fa-check-circle text-green-400 mr-2"></i>
                    Recently Completed Trades
                </h2>

                <div class="space-y-3 md:space-y-4">
                    <?php foreach ($completed_trades as $trade): ?>
                    <div class="flex items-center justify-between p-3 md:p-4 bg-gray-800/30 rounded-lg">
                        <div class="flex items-center space-x-3 md:space-x-4">
                            <div class="text-xl md:text-2xl"><?php echo htmlspecialchars($trade['icon']); ?></div>
                            <div>
                                <h4 class="font-bold text-white text-sm md:text-base"><?php echo htmlspecialchars($trade['package_name']); ?></h4>
                                <p class="text-xs md:text-sm text-gray-400">
                                    Completed <?php echo timeAgo($trade['completed_at']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-xs md:text-sm text-gray-400">Invested</p>
                            <p class="font-bold text-white text-sm md:text-base"><?php echo formatMoney($trade['investment_amount']); ?></p>
                            <p class="text-xs md:text-sm text-emerald-400">
                                +<?php echo formatMoney($trade['expected_roi']); ?> ROI
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-gray-800 border-t border-gray-700 md:hidden z-40">
        <div class="grid grid-cols-5 py-2">
            <a href="/user/dashboard.php" class="flex flex-col items-center py-2 text-gray-400">
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
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-emerald-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/socials.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-share-alt text-xl mb-1"></i>
                <span class="text-xs">Socials</span>
            </a>
        </div>
    </div>
<!-- Live Chat Support Widget -->
<?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>

    <script>
        // Countdown timers for active packages
        function updateCountdowns() {
            document.querySelectorAll('.countdown').forEach(element => {
                const maturityDate = new Date(element.getAttribute('data-maturity')).getTime();
                const now = new Date().getTime();
                const distance = maturityDate - now;

                if (distance > 0) {
                    const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                    if (days > 0) {
                        element.innerHTML = `${days}d ${hours}h ${minutes}m`;
                    } else {
                        element.innerHTML = `${hours}h ${minutes}m ${seconds}s`;
                    }
                } else {
                    // Package matured - reload page to process
                    element.innerHTML = 'Processing...';
                    element.classList.add('text-emerald-400');
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                }
            });
        }

        // Only run if there are countdowns
        if (document.querySelectorAll('.countdown').length > 0) {
            updateCountdowns();
            setInterval(updateCountdowns, 1000);
        }

        // Auto-refresh page every 30 seconds if there are active trades
        <?php if (!empty($active_trades)): ?>
        let autoRefreshTimer = setInterval(() => {
            console.log('Auto-refreshing to check for matured packages...');
            location.reload();
        }, 30000); // 30 seconds

        // Clear timer if user is about to leave
        window.addEventListener('beforeunload', () => {
            clearInterval(autoRefreshTimer);
        });
        <?php endif; ?>
    </script>
</body>
</html>