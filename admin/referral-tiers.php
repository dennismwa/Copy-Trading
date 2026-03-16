<?php
/**
 * Referral Tiers Management
 * Admin page to manage referral tiers and user tier assignments
 */

require_once '../config/database.php';
requireAdmin();

$success = '';
$error = '';
$action = $_GET['action'] ?? 'list';

// Handle tier creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_tier'])) {
        $tier_id = isset($_POST['tier_id']) ? (int)$_POST['tier_id'] : 0;
        $tier_name = trim($_POST['tier_name'] ?? '');
        $tier_level = (int)($_POST['tier_level'] ?? 0);
        $threshold = (float)($_POST['referral_earnings_threshold'] ?? 0);
        $limit = (float)($_POST['daily_withdrawal_limit'] ?? 50000);
        $description = trim($_POST['description'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if (empty($tier_name) || $tier_level < 1 || $threshold < 0 || $limit < 0) {
            $error = "Please fill in all required fields with valid values.";
        } else {
            try {
                if ($tier_id > 0) {
                    // Update existing tier
                    $stmt = $db->prepare("
                        UPDATE referral_tiers 
                        SET tier_name = ?, tier_level = ?, referral_earnings_threshold = ?, 
                            daily_withdrawal_limit = ?, description = ?, is_active = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$tier_name, $tier_level, $threshold, $limit, $description, $is_active, $tier_id]);
                    $success = "Tier updated successfully!";
                } else {
                    // Create new tier
                    $stmt = $db->prepare("
                        INSERT INTO referral_tiers (tier_name, tier_level, referral_earnings_threshold, daily_withdrawal_limit, description, is_active)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$tier_name, $tier_level, $threshold, $limit, $description, $is_active]);
                    $success = "Tier created successfully!";
                }
            } catch (Exception $e) {
                $error = "Failed to save tier: " . $e->getMessage();
                error_log("Tier save error: " . $e->getMessage());
            }
        }
    }
    
    // Handle manual tier assignment
    if (isset($_POST['assign_tier'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $tier_id = (int)($_POST['tier_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        
        if ($user_id > 0 && $tier_id > 0) {
            try {
                $db->beginTransaction();
                
                // Deactivate any existing active assignment for this user
                $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND is_active = 1");
                $stmt->execute([$user_id]);
                
                // Create new manual assignment
                $stmt = $db->prepare("
                    INSERT INTO user_tier_assignments (user_id, tier_id, assignment_type, assigned_by, notes, is_active)
                    VALUES (?, ?, 'manual', ?, ?, 1)
                ");
                $stmt->execute([$user_id, $tier_id, $_SESSION['user_id'], $notes]);
                
                // Get user and tier info for notification
                $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                
                $stmt = $db->prepare("SELECT tier_name FROM referral_tiers WHERE id = ?");
                $stmt->execute([$tier_id]);
                $tier = $stmt->fetch();
                
                $db->commit();
                
                // Notify user
                if ($user && $tier) {
                    sendNotification(
                        $user_id,
                        'Referral Tier Upgraded! 🎉',
                        "Congratulations! You have been upgraded to {$tier['tier_name']} tier. You now have access to higher daily withdrawal limits!",
                        'success'
                    );
                }
                
                $success = "Tier assigned successfully! User has been notified.";
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Failed to assign tier: " . $e->getMessage();
                error_log("Tier assignment error: " . $e->getMessage());
            }
        } else {
            $error = "Please select a user and tier.";
        }
    }
    
    // Handle tier removal
    if (isset($_POST['remove_tier'])) {
        $user_id = (int)($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            try {
                $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND is_active = 1");
                $stmt->execute([$user_id]);
                $success = "Tier removed from user successfully!";
            } catch (Exception $e) {
                $error = "Failed to remove tier: " . $e->getMessage();
            }
        }
    }
}

// Get all tiers
$tiers = [];
try {
    $stmt = $db->query("SELECT * FROM referral_tiers ORDER BY tier_level ASC");
    $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Failed to load tiers: " . $e->getMessage();
}

// Get tier for editing
$edit_tier = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $tier_id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM referral_tiers WHERE id = ?");
    $stmt->execute([$tier_id]);
    $edit_tier = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get users with tier assignments
$users_with_tiers = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.full_name, u.email, u.referral_earnings, 
               rt.tier_name, rt.daily_withdrawal_limit,
               uta.assignment_type, uta.assigned_at, uta.notes,
               admin.full_name as assigned_by_name
        FROM users u
        INNER JOIN user_tier_assignments uta ON u.id = uta.user_id
        INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
        LEFT JOIN users admin ON uta.assigned_by = admin.id
        WHERE uta.is_active = 1
        ORDER BY rt.tier_level DESC, u.referral_earnings DESC
    ");
    $users_with_tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist yet
}

// Get all users for manual assignment
$all_users = [];
try {
    $stmt = $db->query("SELECT id, full_name, email, referral_earnings FROM users WHERE is_admin = 0 ORDER BY full_name ASC");
    $all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Error loading users
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Referral Tiers Management - Admin</title>
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
                        <i class="fas fa-trophy text-yellow-300 mr-3"></i>
                        Referral Tiers Management
                    </h1>
                    <p class="text-purple-100">Manage referral reward tiers and user tier assignments</p>
                </div>
                <div class="space-x-2">
                    <a href="settings.php" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Settings
                    </a>
                    <a href="assign-tiers-bulk.php" class="px-4 py-2 bg-green-500 hover:bg-green-600 rounded-lg transition">
                        <i class="fas fa-users-cog mr-2"></i>Bulk Assign
                    </a>
                </div>
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

        <!-- Tabs -->
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl mb-6">
            <div class="flex space-x-4 border-b border-gray-700">
                <button onclick="showTab('tiers')" id="tab-tiers" class="tab-button px-4 py-2 font-semibold border-b-2 border-purple-500 text-purple-400">
                    <i class="fas fa-layer-group mr-2"></i>Tiers
                </button>
                <button onclick="showTab('assignments')" id="tab-assignments" class="tab-button px-4 py-2 font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-300">
                    <i class="fas fa-user-tag mr-2"></i>User Assignments
                </button>
                <button onclick="showTab('assign')" id="tab-assign" class="tab-button px-4 py-2 font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-300">
                    <i class="fas fa-user-plus mr-2"></i>Assign Tier
                </button>
            </div>
        </div>

        <!-- Tiers Tab -->
        <div id="content-tiers" class="tab-content">
            <div class="bg-gray-800 rounded-xl p-6 shadow-2xl mb-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-2xl font-bold">
                        <i class="fas fa-layer-group text-purple-400 mr-2"></i>
                        Referral Tiers
                    </h2>
                    <button onclick="showTierForm()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition">
                        <i class="fas fa-plus mr-2"></i>Add New Tier
                    </button>
                </div>

                <!-- Tier Form -->
                <div id="tier-form" class="hidden mb-6 bg-gray-700/50 rounded-lg p-4 border border-gray-600">
                    <h3 class="text-xl font-bold mb-4">
                        <?php echo $edit_tier ? 'Edit Tier' : 'Create New Tier'; ?>
                    </h3>
                    <form method="POST" class="space-y-4">
                        <?php if ($edit_tier): ?>
                        <input type="hidden" name="tier_id" value="<?php echo $edit_tier['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Tier Name *</label>
                                <input type="text" name="tier_name" value="<?php echo htmlspecialchars($edit_tier['tier_name'] ?? ''); ?>" 
                                       class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Tier Level * (1=Bronze, 2=Silver, 3=Gold)</label>
                                <input type="number" name="tier_level" value="<?php echo $edit_tier['tier_level'] ?? ''; ?>" 
                                       min="1" max="10" class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none" required>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Referral Earnings Threshold (KSh) *</label>
                                <input type="number" name="referral_earnings_threshold" step="0.01" min="0" 
                                       value="<?php echo $edit_tier['referral_earnings_threshold'] ?? '0'; ?>" 
                                       class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none" required>
                                <p class="text-xs text-gray-500 mt-1">Minimum referral earnings to qualify for this tier</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Daily Withdrawal Limit (KSh) *</label>
                                <input type="number" name="daily_withdrawal_limit" step="0.01" min="0" 
                                       value="<?php echo $edit_tier['daily_withdrawal_limit'] ?? '50000'; ?>" 
                                       class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none" required>
                                <p class="text-xs text-gray-500 mt-1">Custom daily withdrawal limit for users in this tier</p>
                            </div>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Description</label>
                            <textarea name="description" rows="3" 
                                      class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-purple-500 focus:outline-none"><?php echo htmlspecialchars($edit_tier['description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="is_active" value="1" 
                                   <?php echo ($edit_tier['is_active'] ?? 1) ? 'checked' : ''; ?> 
                                   class="w-4 h-4 text-purple-600 bg-gray-700 border-gray-600 rounded focus:ring-purple-500">
                            <label for="is_active" class="ml-2 text-sm text-gray-300">Active</label>
                        </div>
                        
                        <div class="flex space-x-2">
                            <button type="submit" name="save_tier" class="px-6 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition">
                                <i class="fas fa-save mr-2"></i>Save Tier
                            </button>
                            <button type="button" onclick="hideTierForm()" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tiers List -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="pb-3 text-gray-400 font-semibold">Tier</th>
                                <th class="pb-3 text-gray-400 font-semibold">Level</th>
                                <th class="pb-3 text-gray-400 font-semibold">Threshold</th>
                                <th class="pb-3 text-gray-400 font-semibold">Withdrawal Limit</th>
                                <th class="pb-3 text-gray-400 font-semibold">Status</th>
                                <th class="pb-3 text-gray-400 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tiers as $tier): ?>
                            <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                <td class="py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($tier['tier_name']); ?></div>
                                    <?php if ($tier['description']): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($tier['description']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3"><?php echo $tier['tier_level']; ?></td>
                                <td class="py-3"><?php echo formatMoney($tier['referral_earnings_threshold']); ?></td>
                                <td class="py-3 text-green-400 font-semibold"><?php echo formatMoney($tier['daily_withdrawal_limit']); ?></td>
                                <td class="py-3">
                                    <span class="px-2 py-1 rounded text-xs <?php echo $tier['is_active'] ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'; ?>">
                                        <?php echo $tier['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td class="py-3">
                                    <a href="?edit=<?php echo $tier['id']; ?>" class="text-blue-400 hover:text-blue-300 mr-3">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assignments Tab -->
        <div id="content-assignments" class="tab-content hidden">
            <div class="bg-gray-800 rounded-xl p-6 shadow-2xl">
                <h2 class="text-2xl font-bold mb-4">
                    <i class="fas fa-user-tag text-indigo-400 mr-2"></i>
                    User Tier Assignments
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-700">
                                <th class="pb-3 text-gray-400 font-semibold">User</th>
                                <th class="pb-3 text-gray-400 font-semibold">Tier</th>
                                <th class="pb-3 text-gray-400 font-semibold">Referral Earnings</th>
                                <th class="pb-3 text-gray-400 font-semibold">Withdrawal Limit</th>
                                <th class="pb-3 text-gray-400 font-semibold">Assignment Type</th>
                                <th class="pb-3 text-gray-400 font-semibold">Assigned By</th>
                                <th class="pb-3 text-gray-400 font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users_with_tiers)): ?>
                            <tr>
                                <td colspan="7" class="py-8 text-center text-gray-500">
                                    <i class="fas fa-inbox text-4xl mb-2 block"></i>
                                    No tier assignments found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users_with_tiers as $assignment): ?>
                            <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                <td class="py-3">
                                    <div class="font-medium"><?php echo htmlspecialchars($assignment['full_name']); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($assignment['email']); ?></div>
                                </td>
                                <td class="py-3">
                                    <span class="px-2 py-1 bg-purple-500/20 text-purple-300 rounded text-sm">
                                        <?php echo htmlspecialchars($assignment['tier_name']); ?>
                                    </span>
                                </td>
                                <td class="py-3"><?php echo formatMoney($assignment['referral_earnings']); ?></td>
                                <td class="py-3 text-green-400 font-semibold"><?php echo formatMoney($assignment['daily_withdrawal_limit']); ?></td>
                                <td class="py-3">
                                    <span class="px-2 py-1 rounded text-xs <?php echo $assignment['assignment_type'] === 'manual' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-blue-500/20 text-blue-400'; ?>">
                                        <?php echo ucfirst($assignment['assignment_type']); ?>
                                    </span>
                                </td>
                                <td class="py-3 text-sm text-gray-400">
                                    <?php echo $assignment['assigned_by_name'] ? htmlspecialchars($assignment['assigned_by_name']) : 'System'; ?>
                                    <div class="text-xs"><?php echo date('M j, Y', strtotime($assignment['assigned_at'])); ?></div>
                                </td>
                                <td class="py-3">
                                    <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to remove this tier assignment?');">
                                        <input type="hidden" name="user_id" value="<?php echo $assignment['id']; ?>">
                                        <button type="submit" name="remove_tier" class="text-red-400 hover:text-red-300">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assign Tier Tab -->
        <div id="content-assign" class="tab-content hidden">
            <div class="bg-gray-800 rounded-xl p-6 shadow-2xl">
                <h2 class="text-2xl font-bold mb-4">
                    <i class="fas fa-user-plus text-green-400 mr-2"></i>
                    Manually Assign Tier to User
                </h2>
                
                <form method="POST" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Select User *</label>
                            <select name="user_id" required class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:outline-none">
                                <option value="">-- Select User --</option>
                                <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>) - 
                                    Earnings: <?php echo formatMoney($user['referral_earnings']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Select Tier *</label>
                            <select name="tier_id" required class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:outline-none">
                                <option value="">-- Select Tier --</option>
                                <?php foreach ($tiers as $tier): ?>
                                <?php if ($tier['is_active']): ?>
                                <option value="<?php echo $tier['id']; ?>">
                                    <?php echo htmlspecialchars($tier['tier_name']); ?> - Limit: <?php echo formatMoney($tier['daily_withdrawal_limit']); ?>
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this assignment..." 
                                  class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:outline-none"></textarea>
                    </div>
                    
                    <button type="submit" name="assign_tier" class="px-6 py-3 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-bold rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg">
                        <i class="fas fa-check-circle mr-2"></i>Assign Tier
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('border-purple-500', 'text-purple-400');
                btn.classList.add('border-transparent', 'text-gray-400');
            });
            
            // Show selected tab
            document.getElementById('content-' + tab).classList.remove('hidden');
            const btn = document.getElementById('tab-' + tab);
            btn.classList.add('border-purple-500', 'text-purple-400');
            btn.classList.remove('border-transparent', 'text-gray-400');
        }
        
        function showTierForm() {
            document.getElementById('tier-form').classList.remove('hidden');
        }
        
        function hideTierForm() {
            document.getElementById('tier-form').classList.add('hidden');
            window.location.href = 'referral-tiers.php';
        }
        
        // Show tier form if editing
        <?php if ($edit_tier): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showTierForm();
        });
        <?php endif; ?>
    </script>
</body>
</html>

