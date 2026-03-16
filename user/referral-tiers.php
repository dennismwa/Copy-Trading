<?php
/**
 * Referral Tiers Progress Page
 * Shows user's current tier, progress, and next tier goals
 */

require_once '../config/database.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Get user data with wallet balance
$stmt = $db->prepare("SELECT id, full_name, email, referral_earnings, wallet_balance FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: /login.php');
    exit;
}

// Get all active tiers ordered by level
$stmt = $db->query("SELECT * FROM referral_tiers WHERE is_active = 1 ORDER BY tier_level ASC");
$all_tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's current tier assignment
$current_tier = null;
$current_tier_assignment = null;
$stmt = $db->prepare("
    SELECT rt.*, uta.assignment_type, uta.assigned_at
    FROM user_tier_assignments uta
    INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
    WHERE uta.user_id = ? AND uta.is_active = 1 AND rt.is_active = 1
    LIMIT 1
");
$stmt->execute([$user_id]);
$current_tier_assignment = $stmt->fetch(PDO::FETCH_ASSOC);

if ($current_tier_assignment) {
    $current_tier = $current_tier_assignment;
}

// Get default withdrawal limit (if no tier)
$stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'daily_withdrawal_limit'");
$stmt->execute();
$default_limit = $stmt->fetch(PDO::FETCH_ASSOC);
$default_withdrawal_limit = (float)($default_limit['setting_value'] ?? 50000);

// Calculate current withdrawal limit
$current_withdrawal_limit = $current_tier ? (float)$current_tier['daily_withdrawal_limit'] : $default_withdrawal_limit;

// Find next tier user can unlock
$next_tier = null;
$referral_earnings = (float)($user['referral_earnings'] ?? 0);

foreach ($all_tiers as $tier) {
    $threshold = (float)$tier['referral_earnings_threshold'];
    if ($threshold > $referral_earnings) {
        $next_tier = $tier;
        break;
    }
}

// If user has highest tier, check if there are higher tiers
if (!$next_tier && $current_tier) {
    $current_level = (int)$current_tier['tier_level'];
    foreach ($all_tiers as $tier) {
        if ((int)$tier['tier_level'] > $current_level) {
            $next_tier = $tier;
            break;
        }
    }
}

// Calculate progress to next tier
$progress_data = [
    'current_earnings' => $referral_earnings,
    'next_threshold' => $next_tier ? (float)$next_tier['referral_earnings_threshold'] : 0,
    'needed' => 0,
    'percentage' => 0,
    'has_next_tier' => $next_tier !== null
];

if ($next_tier) {
    $current_threshold = $current_tier ? (float)$current_tier['referral_earnings_threshold'] : 0;
    $next_threshold = (float)$next_tier['referral_earnings_threshold'];
    $progress_data['needed'] = max(0, $next_threshold - $referral_earnings);
    
    if ($next_threshold > $current_threshold) {
        $range = $next_threshold - $current_threshold;
        $progress = $referral_earnings - $current_threshold;
        $progress_data['percentage'] = min(100, max(0, ($progress / $range) * 100));
    }
} else {
    // User has highest tier or no tiers available
    $progress_data['percentage'] = 100;
}

// Get tier icons/colors
$tier_styles = [
    1 => ['icon' => '🥉', 'color' => '#cd7f32', 'gradient' => 'from-amber-600 to-orange-700'],
    2 => ['icon' => '🥈', 'color' => '#c0c0c0', 'gradient' => 'from-gray-400 to-gray-600'],
    3 => ['icon' => '🥇', 'color' => '#ffd700', 'gradient' => 'from-yellow-400 to-yellow-600'],
    4 => ['icon' => '💎', 'color' => '#b9f2ff', 'gradient' => 'from-blue-400 to-cyan-600'],
    5 => ['icon' => '👑', 'color' => '#ff6b6b', 'gradient' => 'from-purple-500 to-pink-600']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Tiers - Ultra Harvest Global</title>
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
        
        .progress-bar {
            position: relative;
            overflow: hidden;
        }
        
        .progress-fill {
            transition: width 1s ease-out;
        }
        
        .tier-card {
            transition: all 0.3s ease;
        }
        
        .tier-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .tier-card.unlocked {
            border: 2px solid rgba(16, 185, 129, 0.5);
            box-shadow: 0 0 20px rgba(16, 185, 129, 0.3);
        }
        
        .tier-card.current {
            border: 3px solid #10b981;
            box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
            animation: pulse-glow 2s infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 0 30px rgba(16, 185, 129, 0.5);
            }
            50% {
                box-shadow: 0 0 40px rgba(16, 185, 129, 0.7);
            }
        }
        
        .tier-card.locked {
            opacity: 0.6;
            filter: grayscale(0.5);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- Header -->
<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700">
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
                    <a href="/user/referral-tiers.php" class="text-yellow-400 font-medium">Tiers</a>
                    <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                    <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                </nav>
            </div>

            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                    <i class="fas fa-wallet text-emerald-400"></i>
                    <span class="text-sm text-gray-300">Balance:</span>
                    <span class="font-bold text-white"><?php echo formatMoney($user['wallet_balance'] ?? 0); ?></span>
                </div>
                <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-8 pb-24 md:pb-8">
    
    <!-- Page Header -->
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold mb-2">
            <i class="fas fa-trophy text-yellow-400 mr-3"></i>
            Referral Tiers
        </h1>
        <p class="text-xl text-gray-300">Unlock higher withdrawal limits by referring more users</p>
    </div>

    <!-- Current Tier & Progress Card -->
    <div class="glass-card rounded-xl p-6 md:p-8 mb-8">
        <div class="grid md:grid-cols-2 gap-6">
            <!-- Current Tier -->
            <div class="bg-gradient-to-br <?php echo $current_tier && isset($tier_styles[$current_tier['tier_level']]) ? $tier_styles[$current_tier['tier_level']]['gradient'] : 'from-gray-600 to-gray-800'; ?> rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm text-gray-200 mb-1">Current Tier</p>
                        <h2 class="text-3xl font-bold text-white">
                            <?php if ($current_tier): ?>
                                <?php echo isset($tier_styles[$current_tier['tier_level']]) ? $tier_styles[$current_tier['tier_level']]['icon'] : '⭐'; ?>
                                <?php echo htmlspecialchars($current_tier['tier_name']); ?>
                            <?php else: ?>
                                <span class="text-gray-300">No Tier</span>
                            <?php endif; ?>
                        </h2>
                    </div>
                </div>
                <div class="space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-200">Daily Withdrawal Limit</span>
                        <span class="font-bold text-white"><?php echo formatMoney($current_withdrawal_limit); ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-200">Referral Earnings</span>
                        <span class="font-bold text-white"><?php echo formatMoney($referral_earnings); ?></span>
                    </div>
                </div>
            </div>

            <!-- Progress to Next Tier -->
            <div class="bg-gray-800/50 rounded-xl p-6">
                <?php if ($next_tier): ?>
                    <div class="mb-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-sm text-gray-300">Progress to Next Tier</p>
                            <span class="text-sm font-bold text-emerald-400"><?php echo number_format($progress_data['percentage'], 1); ?>%</span>
                        </div>
                        <div class="progress-bar w-full h-4 bg-gray-700 rounded-full overflow-hidden">
                            <div class="progress-fill h-full bg-gradient-to-r from-emerald-500 to-green-400 rounded-full" 
                                 style="width: <?php echo $progress_data['percentage']; ?>%">
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <div class="flex items-center space-x-2 mb-3">
                            <span class="text-2xl"><?php echo isset($tier_styles[$next_tier['tier_level']]) ? $tier_styles[$next_tier['tier_level']]['icon'] : '⭐'; ?></span>
                            <div>
                                <p class="text-sm text-gray-400">Next: <?php echo htmlspecialchars($next_tier['tier_name']); ?> Tier</p>
                                <p class="text-lg font-bold text-white"><?php echo formatMoney($next_tier['daily_withdrawal_limit']); ?>/day</p>
                            </div>
                        </div>
                        <div class="bg-gray-700/50 rounded-lg p-3">
                            <p class="text-xs text-gray-400 mb-1">Earnings Needed</p>
                            <p class="text-xl font-bold text-emerald-400"><?php echo formatMoney($progress_data['needed']); ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-crown text-4xl text-yellow-400 mb-3"></i>
                        <p class="text-lg font-bold text-white mb-2">Maximum Tier Reached!</p>
                        <p class="text-sm text-gray-400">You've unlocked the highest tier available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- All Tiers Grid -->
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-layer-group text-purple-400 mr-2"></i>
            All Tiers
        </h2>
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($all_tiers as $tier): ?>
                <?php
                $tier_level = (int)$tier['tier_level'];
                $tier_threshold = (float)$tier['referral_earnings_threshold'];
                $is_unlocked = $referral_earnings >= $tier_threshold;
                $is_current = $current_tier && $current_tier['id'] == $tier['id'];
                $tier_style = $tier_styles[$tier_level] ?? ['icon' => '⭐', 'color' => '#666', 'gradient' => 'from-gray-600 to-gray-800'];
                ?>
                <div class="tier-card glass-card rounded-xl p-6 <?php echo $is_current ? 'current' : ($is_unlocked ? 'unlocked' : 'locked'); ?>">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-3">
                            <span class="text-4xl"><?php echo $tier_style['icon']; ?></span>
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($tier['tier_name']); ?></h3>
                                <p class="text-xs text-gray-400">Level <?php echo $tier_level; ?></p>
                            </div>
                        </div>
                        <?php if ($is_current): ?>
                            <span class="px-3 py-1 bg-emerald-500/20 text-emerald-400 rounded-full text-xs font-bold">CURRENT</span>
                        <?php elseif ($is_unlocked): ?>
                            <span class="px-3 py-1 bg-blue-500/20 text-blue-400 rounded-full text-xs font-bold">UNLOCKED</span>
                        <?php else: ?>
                            <span class="px-3 py-1 bg-gray-500/20 text-gray-400 rounded-full text-xs font-bold">LOCKED</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="space-y-3 mb-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-400">Withdrawal Limit</span>
                            <span class="font-bold text-emerald-400"><?php echo formatMoney($tier['daily_withdrawal_limit']); ?>/day</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm text-gray-400">Required Earnings</span>
                            <span class="font-bold text-white"><?php echo formatMoney($tier_threshold); ?></span>
                        </div>
                        <?php if ($tier['description']): ?>
                            <p class="text-xs text-gray-500 mt-2"><?php echo htmlspecialchars($tier['description']); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$is_unlocked): ?>
                        <div class="mt-4 pt-4 border-t border-gray-700">
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-gray-400">Your Progress</span>
                                <span class="font-bold text-yellow-400">
                                    <?php 
                                    $progress = min(100, ($referral_earnings / $tier_threshold) * 100);
                                    echo number_format($progress, 1); 
                                    ?>%
                                </span>
                            </div>
                            <div class="progress-bar w-full h-2 bg-gray-700 rounded-full mt-2 overflow-hidden">
                                <div class="progress-fill h-full bg-gradient-to-r from-yellow-500 to-orange-400 rounded-full" 
                                     style="width: <?php echo min(100, ($referral_earnings / $tier_threshold) * 100); ?>%">
                                </div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                Need <?php echo formatMoney(max(0, $tier_threshold - $referral_earnings)); ?> more
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- How It Works -->
    <div class="glass-card rounded-xl p-6 md:p-8">
        <h2 class="text-2xl font-bold mb-4">
            <i class="fas fa-info-circle text-blue-400 mr-2"></i>
            How Referral Tiers Work
        </h2>
        <div class="grid md:grid-cols-3 gap-6">
            <div class="text-center">
                <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-user-plus text-blue-400 text-2xl"></i>
                </div>
                <h3 class="font-bold mb-2">Refer Users</h3>
                <p class="text-sm text-gray-400">Share your referral link and earn commissions when your referrals invest.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-emerald-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-chart-line text-emerald-400 text-2xl"></i>
                </div>
                <h3 class="font-bold mb-2">Earn & Progress</h3>
                <p class="text-sm text-gray-400">As your referral earnings grow, you automatically unlock higher tiers.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-unlock text-yellow-400 text-2xl"></i>
                </div>
                <h3 class="font-bold mb-2">Unlock Benefits</h3>
                <p class="text-sm text-gray-400">Higher tiers give you increased daily withdrawal limits for faster access to your earnings.</p>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <?php if ($next_tier): ?>
    <div class="mt-8 text-center">
        <a href="/user/referrals.php" class="inline-flex items-center space-x-2 px-8 py-4 bg-gradient-to-r from-emerald-600 to-green-600 hover:from-emerald-700 hover:to-green-700 text-white rounded-lg font-bold text-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
            <i class="fas fa-share-alt"></i>
            <span>Start Referring to Unlock <?php echo htmlspecialchars($next_tier['tier_name']); ?> Tier</span>
        </a>
    </div>
    <?php endif; ?>

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

<!-- Live Chat Support Widget -->
<?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>

<script>
    // Animate progress bars on page load
    document.addEventListener('DOMContentLoaded', function() {
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    });
</script>

</body>
</html>

