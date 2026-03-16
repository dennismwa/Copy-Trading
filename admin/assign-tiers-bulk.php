<?php
/**
 * Bulk Assign Referral Tiers
 * Assigns tiers to all users who qualify based on their current referral earnings
 */

require_once '../config/database.php';
requireAdmin();

$success = '';
$error = '';
$results = [
    'processed' => 0,
    'assigned' => 0,
    'upgraded' => 0,
    'skipped' => 0,
    'errors' => 0,
    'details' => []
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_tiers'])) {
    try {
        // Check if tables exist
        $stmt = $db->query("SHOW TABLES LIKE 'referral_tiers'");
        if ($stmt->rowCount() === 0) {
            $error = "Referral tiers tables do not exist. Please run the setup first.";
        } else {
            $db->beginTransaction();
            
            // Get all active tiers ordered by level (highest first)
            $stmt = $db->query("
                SELECT id, tier_name, tier_level, referral_earnings_threshold, daily_withdrawal_limit
                FROM referral_tiers
                WHERE is_active = 1
                ORDER BY tier_level DESC
            ");
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($tiers)) {
                $error = "No active tiers found. Please create tiers first.";
            } else {
                // Get all non-admin users with referral earnings
                $stmt = $db->query("
                    SELECT id, full_name, email, referral_earnings
                    FROM users
                    WHERE is_admin = 0 AND referral_earnings > 0
                    ORDER BY referral_earnings DESC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($users as $user) {
                    $results['processed']++;
                    $user_id = $user['id'];
                    $referral_earnings = (float)$user['referral_earnings'];
                    
                    try {
                        // Check if user has a manual assignment (don't override)
                        $stmt = $db->prepare("
                            SELECT uta.id, uta.assignment_type, rt.tier_level, rt.tier_name
                            FROM user_tier_assignments uta
                            INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
                            WHERE uta.user_id = ? AND uta.is_active = 1
                            LIMIT 1
                        ");
                        $stmt->execute([$user_id]);
                        $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Skip if manual assignment
                        if ($existing_assignment && $existing_assignment['assignment_type'] === 'manual') {
                            $results['skipped']++;
                            $results['details'][] = [
                                'user' => $user['full_name'],
                                'action' => 'skipped',
                                'reason' => 'Has manual assignment: ' . $existing_assignment['tier_name']
                            ];
                            continue;
                        }
                        
                        // Find the highest tier user qualifies for
                        $qualifying_tier = null;
                        foreach ($tiers as $tier) {
                            if ($referral_earnings >= (float)$tier['referral_earnings_threshold']) {
                                $qualifying_tier = $tier;
                                break;
                            }
                        }
                        
                        if (!$qualifying_tier) {
                            // User doesn't qualify for any tier
                            if ($existing_assignment && $existing_assignment['assignment_type'] === 'automatic') {
                                // Remove existing automatic assignment
                                $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND assignment_type = 'automatic'");
                                $stmt->execute([$user_id]);
                            }
                            $results['skipped']++;
                            $results['details'][] = [
                                'user' => $user['full_name'],
                                'action' => 'skipped',
                                'reason' => 'Does not meet minimum threshold'
                            ];
                            continue;
                        }
                        
                        // Check if user already has this tier
                        if ($existing_assignment && $existing_assignment['tier_level'] == $qualifying_tier['tier_level']) {
                            $results['skipped']++;
                            $results['details'][] = [
                                'user' => $user['full_name'],
                                'action' => 'skipped',
                                'reason' => 'Already has ' . $qualifying_tier['tier_name'] . ' tier'
                            ];
                            continue;
                        }
                        
                        // Deactivate any existing automatic assignment
                        if ($existing_assignment && $existing_assignment['assignment_type'] === 'automatic') {
                            $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND assignment_type = 'automatic'");
                            $stmt->execute([$user_id]);
                            
                            // Check if upgrading
                            if ($qualifying_tier['tier_level'] > $existing_assignment['tier_level']) {
                                $results['upgraded']++;
                            }
                        }
                        
                        // Create new automatic assignment
                        $stmt = $db->prepare("
                            INSERT INTO user_tier_assignments (user_id, tier_id, assignment_type, is_active)
                            VALUES (?, ?, 'automatic', 1)
                        ");
                        $stmt->execute([$user_id, $qualifying_tier['id']]);
                        
                        // Send notification
                        sendNotification(
                            $user_id,
                            'Referral Tier Assigned! 🎉',
                            "Congratulations! You've been assigned to {$qualifying_tier['tier_name']} tier based on your referral earnings of " . formatMoney($referral_earnings) . ". Your daily withdrawal limit is now " . formatMoney($qualifying_tier['daily_withdrawal_limit']) . "!",
                            'success'
                        );
                        
                        $results['assigned']++;
                        $results['details'][] = [
                            'user' => $user['full_name'],
                            'action' => 'assigned',
                            'tier' => $qualifying_tier['tier_name'],
                            'earnings' => formatMoney($referral_earnings)
                        ];
                        
                    } catch (Exception $e) {
                        $results['errors']++;
                        $results['details'][] = [
                            'user' => $user['full_name'],
                            'action' => 'error',
                            'error' => $e->getMessage()
                        ];
                        error_log("Error assigning tier to user {$user_id}: " . $e->getMessage());
                    }
                }
                
                $db->commit();
                
                $success = sprintf(
                    "Bulk tier assignment completed! Processed: %d users | Assigned/Upgraded: %d | Skipped: %d | Errors: %d",
                    $results['processed'],
                    $results['assigned'] + $results['upgraded'],
                    $results['skipped'],
                    $results['errors']
                );
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Bulk assignment failed: " . $e->getMessage();
        error_log("Bulk tier assignment error: " . $e->getMessage());
    }
}

// Get statistics
$stats = [
    'total_users' => 0,
    'users_with_earnings' => 0,
    'users_with_tiers' => 0,
    'users_qualifying' => 0
];

try {
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0");
    $stats['total_users'] = $stmt->fetchColumn();
    
    $stmt = $db->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND referral_earnings > 0");
    $stats['users_with_earnings'] = $stmt->fetchColumn();
    
    $stmt = $db->query("
        SELECT COUNT(DISTINCT uta.user_id) 
        FROM user_tier_assignments uta
        WHERE uta.is_active = 1
    ");
    $stats['users_with_tiers'] = $stmt->fetchColumn();
    
    // Count users who qualify but don't have tiers
    $stmt = $db->query("
        SELECT MIN(referral_earnings_threshold) as min_threshold
        FROM referral_tiers
        WHERE is_active = 1
    ");
    $min_threshold = $stmt->fetchColumn();
    
    if ($min_threshold) {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM users u
            WHERE u.is_admin = 0 
            AND u.referral_earnings >= ?
            AND NOT EXISTS (
                SELECT 1 FROM user_tier_assignments uta 
                WHERE uta.user_id = u.id AND uta.is_active = 1
            )
        ");
        $stmt->execute([$min_threshold]);
        $stats['users_qualifying'] = $stmt->fetchColumn();
    }
} catch (Exception $e) {
    // Stats might fail if tables don't exist
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Assign Referral Tiers - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-xl p-6 mb-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        <i class="fas fa-users-cog text-yellow-300 mr-3"></i>
                        Bulk Assign Referral Tiers
                    </h1>
                    <p class="text-purple-100">Assign tiers to all users who qualify based on their referral earnings</p>
                </div>
                <a href="referral-tiers.php" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Tiers
                </a>
            </div>
        </div>

        <!-- Messages -->
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

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Users</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['total_users']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">With Referral Earnings</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['users_with_earnings']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Already Have Tiers</p>
                        <p class="text-2xl font-bold text-white"><?php echo $stats['users_with_tiers']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-trophy text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Qualifying (No Tier)</p>
                        <p class="text-2xl font-bold text-yellow-400"><?php echo $stats['users_qualifying']; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bulk Assignment Form -->
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl mb-6">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-magic text-purple-400 mr-2"></i>
                Run Bulk Assignment
            </h2>
            
            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 mb-6">
                <div class="flex items-start space-x-2">
                    <i class="fas fa-info-circle text-yellow-400 mt-1"></i>
                    <div>
                        <p class="text-yellow-200 text-sm">
                            <strong>What this does:</strong> This will scan all users and assign them the appropriate tier based on their current referral earnings. 
                            Users with manual tier assignments will be skipped. Users will receive notifications when assigned.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" onsubmit="return confirm('Are you sure you want to assign tiers to all qualifying users? This may send notifications to many users.');">
                <button 
                    type="submit" 
                    name="assign_tiers" 
                    value="1"
                    class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white font-bold rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg"
                >
                    <i class="fas fa-play-circle mr-2"></i>Run Bulk Tier Assignment
                </button>
            </form>
        </div>

        <!-- Results Details -->
        <?php if (!empty($results['details'])): ?>
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-list text-indigo-400 mr-2"></i>
                Assignment Results
            </h2>
            
            <div class="overflow-x-auto max-h-96 overflow-y-auto">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 bg-gray-900">
                        <tr class="border-b border-gray-700">
                            <th class="pb-3 text-gray-400 font-semibold">User</th>
                            <th class="pb-3 text-gray-400 font-semibold">Action</th>
                            <th class="pb-3 text-gray-400 font-semibold">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results['details'] as $detail): ?>
                        <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                            <td class="py-2"><?php echo htmlspecialchars($detail['user']); ?></td>
                            <td class="py-2">
                                <?php if ($detail['action'] === 'assigned'): ?>
                                    <span class="px-2 py-1 bg-emerald-500/20 text-emerald-400 rounded text-xs">Assigned</span>
                                <?php elseif ($detail['action'] === 'skipped'): ?>
                                    <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 rounded text-xs">Skipped</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 bg-red-500/20 text-red-400 rounded text-xs">Error</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-2 text-gray-400 text-xs">
                                <?php 
                                if (isset($detail['tier'])) {
                                    echo "Tier: " . htmlspecialchars($detail['tier']);
                                    if (isset($detail['earnings'])) {
                                        echo " | Earnings: " . htmlspecialchars($detail['earnings']);
                                    }
                                } elseif (isset($detail['reason'])) {
                                    echo htmlspecialchars($detail['reason']);
                                } elseif (isset($detail['error'])) {
                                    echo htmlspecialchars($detail['error']);
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>

