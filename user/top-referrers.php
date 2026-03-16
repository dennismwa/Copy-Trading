<?php
require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle date filter
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : 'all';
$start_date = null;
$end_date = null;

// Validate date filter value
if (!in_array($date_filter, ['all', 'today', 'week', 'month', 'custom'])) {
    $date_filter = 'all';
}

if ($date_filter === 'today') {
    $start_date = date('Y-m-d 00:00:00');
    $end_date = date('Y-m-d 23:59:59');
} elseif ($date_filter === 'week') {
    $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
    $end_date = date('Y-m-d 23:59:59');
} elseif ($date_filter === 'month') {
    $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
    $end_date = date('Y-m-d 23:59:59');
} elseif ($date_filter === 'custom') {
    if (isset($_GET['start_date']) && isset($_GET['end_date']) && 
        !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        // Validate date format
        $start = DateTime::createFromFormat('Y-m-d', $_GET['start_date']);
        $end = DateTime::createFromFormat('Y-m-d', $_GET['end_date']);
        
        if ($start && $end && $start <= $end) {
            $start_date = $_GET['start_date'] . ' 00:00:00';
            $end_date = $_GET['end_date'] . ' 23:59:59';
        } else {
            // Invalid dates, fall back to all
            $date_filter = 'all';
        }
    } else {
        // Missing dates, fall back to all
        $date_filter = 'all';
    }
}

// Build query for top referrers
$query = "
    SELECT 
        u.id,
        u.full_name,
        COALESCE(SUM(t.amount), 0) as total_bonus_earned
    FROM users u
    LEFT JOIN transactions t ON u.id = t.user_id 
        AND t.type = 'referral_commission' 
        AND t.status = 'completed'
";

$params = [];

if ($start_date && $end_date) {
    $query .= " AND t.created_at >= :start_date AND t.created_at <= :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

$query .= "
    GROUP BY u.id, u.full_name
    HAVING total_bonus_earned > 0
    ORDER BY total_bonus_earned DESC
";

$stmt = $db->prepare($query);

if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$top_referrers = $stmt->fetchAll();

// Get current user's rank
$user_rank = null;
foreach ($top_referrers as $index => $referrer) {
    if ($referrer['id'] == $user_id) {
        $user_rank = $index + 1;
        break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Top Referrers - Ultra Harvest Global</title>
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
        
        .rank-badge {
            transition: all 0.3s ease;
        }
        
        .rank-badge:hover {
            transform: scale(1.1);
        }
        
        .leaderboard-item {
            transition: all 0.3s ease;
        }
        
        .leaderboard-item:hover {
            transform: translateX(5px);
            border-color: rgba(16, 185, 129, 0.5);
        }
        
        .user-highlight {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(251, 191, 36, 0.2));
            border-color: rgba(16, 185, 129, 0.5);
        }

        /* Mobile Responsive Fixes */
        @media (max-width: 768px) {
            .mobile-nav-padding {
                padding-bottom: 80px;
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
        <div class="container mx-auto px-3 sm:px-4">
            <div class="flex items-center justify-between h-14 sm:h-16">
                <div class="flex items-center space-x-3 sm:space-x-8">
                    <a href="/user/dashboard.php" class="flex items-center space-x-2 sm:space-x-3">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
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

                <div class="flex items-center space-x-2 sm:space-x-4">
                    <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                        <i class="fas fa-home text-lg sm:text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-3 sm:px-4 py-4 sm:py-8 mobile-nav-padding">
        <!-- Page Header -->
        <div class="text-center mb-4 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold mb-1 sm:mb-2">
                <i class="fas fa-trophy text-yellow-400 mr-2 sm:mr-3"></i>
                Top Referrers Leaderboard
            </h1>
            <p class="text-sm sm:text-lg md:text-xl text-gray-300">See who's earning the most referral bonuses</p>
        </div>

        <!-- Date Filter Section -->
        <section class="mb-6 sm:mb-8">
            <div class="glass-card rounded-xl p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-calendar-alt text-emerald-400 mr-2"></i>
                    Filter by Date
                </h2>
                
                <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="space-y-4" id="filterForm">
                    <div class="flex flex-wrap gap-2 sm:gap-3">
                        <button type="submit" name="date" value="all" 
                            class="px-4 py-2 rounded-lg font-medium transition <?php echo $date_filter === 'all' ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-clock mr-2"></i>All Time
                        </button>
                        <button type="submit" name="date" value="today" 
                            class="px-4 py-2 rounded-lg font-medium transition <?php echo $date_filter === 'today' ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-calendar-day mr-2"></i>Today
                        </button>
                        <button type="submit" name="date" value="week" 
                            class="px-4 py-2 rounded-lg font-medium transition <?php echo $date_filter === 'week' ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-calendar-week mr-2"></i>Last 7 Days
                        </button>
                        <button type="submit" name="date" value="month" 
                            class="px-4 py-2 rounded-lg font-medium transition <?php echo $date_filter === 'month' ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-calendar-alt mr-2"></i>Last 30 Days
                        </button>
                        <button type="button" onclick="toggleCustomDate()" 
                            class="px-4 py-2 rounded-lg font-medium transition <?php echo $date_filter === 'custom' ? 'bg-emerald-500 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700'; ?>">
                            <i class="fas fa-calendar mr-2"></i>Custom Range
                        </button>
                        <?php if ($date_filter === 'custom'): ?>
                            <button type="submit" name="date" value="all" 
                                class="px-4 py-2 rounded-lg font-medium transition bg-red-600 hover:bg-red-700 text-white">
                                <i class="fas fa-times mr-2"></i>Clear
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div id="customDateRange" class="<?php echo $date_filter === 'custom' ? 'block' : 'hidden'; ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                            <div>
                                <label class="block text-sm text-gray-300 mb-2">Start Date</label>
                                <input type="date" name="start_date" value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : date('Y-m-d', strtotime('-30 days')); ?>" 
                                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-emerald-500" required>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-300 mb-2">End Date</label>
                                <input type="date" name="end_date" value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : date('Y-m-d'); ?>" 
                                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:border-emerald-500" required>
                            </div>
                        </div>
                        <input type="hidden" name="date" value="custom">
                        <button type="submit" class="mt-4 px-6 py-2 bg-emerald-500 hover:bg-emerald-600 text-white rounded-lg font-medium transition">
                            <i class="fas fa-filter mr-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Current User Rank (if applicable) -->
        <?php if ($user_rank): ?>
            <?php 
            $user_data = null;
            foreach ($top_referrers as $referrer) {
                if ($referrer['id'] == $user_id) {
                    $user_data = $referrer;
                    break;
                }
            }
            ?>
            <section class="mb-6 sm:mb-8">
                <div class="glass-card rounded-xl p-4 sm:p-6 user-highlight">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 sm:w-16 sm:h-16 rounded-full bg-gradient-to-r from-emerald-500 to-yellow-500 flex items-center justify-center text-white font-bold text-lg sm:text-2xl">
                                #<?php echo $user_rank; ?>
                            </div>
                            <div>
                                <p class="text-white font-bold text-lg sm:text-xl">Your Rank</p>
                                <p class="text-gray-300 text-sm sm:text-base"><?php echo htmlspecialchars($user_data['full_name']); ?></p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-emerald-400 font-bold text-lg sm:text-2xl"><?php echo formatMoney($user_data['total_bonus_earned']); ?></p>
                            <p class="text-gray-400 text-xs sm:text-sm">Total Bonus</p>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <!-- Leaderboard -->
        <section>
            <div class="glass-card rounded-xl p-4 sm:p-6">
                <div class="flex items-center justify-between mb-4 sm:mb-6">
                    <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                        <i class="fas fa-trophy text-yellow-400 mr-2"></i>
                        Leaderboard
                    </h2>
                    <span class="text-sm text-gray-400"><?php echo count($top_referrers); ?> Referrers</span>
                </div>
                
                <?php if (!empty($top_referrers)): ?>
                    <div class="space-y-3">
                        <?php 
                        $rank = 1;
                        $medal_colors = [
                            1 => 'from-yellow-400 to-yellow-600',
                            2 => 'from-gray-300 to-gray-500',
                            3 => 'from-orange-400 to-orange-600'
                        ];
                        foreach ($top_referrers as $referrer): 
                            $medal_class = $medal_colors[$rank] ?? 'from-gray-400 to-gray-600';
                            $is_current_user = $referrer['id'] == $user_id;
                            $item_class = $is_current_user ? 'leaderboard-item user-highlight' : 'leaderboard-item';
                        ?>
                            <div class="<?php echo $item_class; ?> flex items-center justify-between p-3 sm:p-4 bg-gray-800/50 rounded-lg border border-gray-700">
                                <div class="flex items-center space-x-3 sm:space-x-4 flex-1 min-w-0">
                                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-gradient-to-r <?php echo $medal_class; ?> flex items-center justify-center text-white font-bold flex-shrink-0 rank-badge">
                                        <?php if ($rank <= 3): ?>
                                            <i class="fas fa-medal text-sm sm:text-base"></i>
                                        <?php else: ?>
                                            <?php echo $rank; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white font-medium text-sm sm:text-base truncate">
                                            <?php echo htmlspecialchars($referrer['full_name']); ?>
                                            <?php if ($is_current_user): ?>
                                                <span class="ml-2 text-xs bg-emerald-500/20 text-emerald-400 px-2 py-1 rounded">You</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-gray-400 text-xs">Rank #<?php echo $rank; ?></p>
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-emerald-400 font-bold text-sm sm:text-lg"><?php echo formatMoney($referrer['total_bonus_earned']); ?></p>
                                    <p class="text-gray-400 text-xs">Total Bonus</p>
                                </div>
                            </div>
                        <?php 
                            $rank++;
                        endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-trophy text-5xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400 text-lg">No referrers found for the selected period</p>
                        <p class="text-gray-500 text-sm mt-2">Try selecting a different date range</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main><br><br>

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
            <a href="/user/referrals.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-users text-xl mb-1"></i>
                <span class="text-xs">Network</span>
            </a>
            <a href="/user/active-trades.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-briefcase text-xl mb-1"></i>
                <span class="text-xs">Active</span>
            </a>
            <a href="/user/socials.php" class="flex flex-col items-center py-2 text-gray-400">
                <i class="fas fa-share-alt text-xl mb-1"></i>
                <span class="text-xs">Socials</span>
            </a>
        </div>
    </div>

    <script>
        function toggleCustomDate() {
            const customDateRange = document.getElementById('customDateRange');
            const isHidden = customDateRange.classList.contains('hidden');
            
            if (isHidden) {
                customDateRange.classList.remove('hidden');
                customDateRange.classList.add('block');
                // Focus on start date input
                setTimeout(() => {
                    const startDateInput = customDateRange.querySelector('input[name="start_date"]');
                    if (startDateInput) startDateInput.focus();
                }, 100);
            } else {
                customDateRange.classList.add('hidden');
                customDateRange.classList.remove('block');
            }
        }
        
        // Handle form submission and clean up URL parameters
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('filterForm');
            const filterButtons = document.querySelectorAll('button[type="submit"][name="date"]');
            
            // Clean up URL when non-custom filters are clicked
            filterButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const customDateRange = document.getElementById('customDateRange');
                    const isCustomHidden = customDateRange.classList.contains('hidden');
                    
                    // If submitting a non-custom filter, remove custom date inputs from form
                    if (this.value !== 'custom' && !isCustomHidden) {
                        const startDateInput = form.querySelector('input[name="start_date"]');
                        const endDateInput = form.querySelector('input[name="end_date"]');
                        if (startDateInput) {
                            startDateInput.removeAttribute('name');
                            startDateInput.disabled = true;
                        }
                        if (endDateInput) {
                            endDateInput.removeAttribute('name');
                            endDateInput.disabled = true;
                        }
                    } else if (this.value === 'custom') {
                        // Re-enable date inputs if they were disabled
                        const startDateInput = form.querySelector('input[name="start_date"]');
                        const endDateInput = form.querySelector('input[name="end_date"]');
                        if (startDateInput) {
                            startDateInput.setAttribute('name', 'start_date');
                            startDateInput.disabled = false;
                        }
                        if (endDateInput) {
                            endDateInput.setAttribute('name', 'end_date');
                            endDateInput.disabled = false;
                        }
                    }
                });
            });
            
            // Validate custom date range before submission
            form.addEventListener('submit', function(e) {
                const customDateRange = document.getElementById('customDateRange');
                const isCustomHidden = customDateRange.classList.contains('hidden');
                
                if (!isCustomHidden) {
                    const startDateInput = form.querySelector('input[name="start_date"]');
                    const endDateInput = form.querySelector('input[name="end_date"]');
                    
                    if (startDateInput && endDateInput) {
                        const startDate = startDateInput.value;
                        const endDate = endDateInput.value;
                        
                        if (!startDate || !endDate) {
                            e.preventDefault();
                            alert('Please select both start and end dates for custom range.');
                            return false;
                        }
                        
                        if (new Date(startDate) > new Date(endDate)) {
                            e.preventDefault();
                            alert('Start date must be before or equal to end date.');
                            return false;
                        }
                    }
                }
            });
        });
    </script>
    <!-- Live Chat Support Widget -->
    <?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>
</body>
</html>

