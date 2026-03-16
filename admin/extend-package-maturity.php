<?php
/**
 * Extend Package Maturity Date
 * Safely extends the maturity date of a user's active package by specified hours
 * This script only updates the maturity_date field and does not affect balances
 */

require_once '../config/database.php';
requireAdmin();

$user_id = 274; // Raphael Kabugua
$extension_hours = 24; // Extend by 24 hours
$success = '';
$error = '';

// Handle the extension
if ($_POST && isset($_POST['extend_maturity'])) {
    $target_user_id = (int)$_POST['user_id'];
    $hours = (int)$_POST['hours'];
    
    try {
        // Get user info
        $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
        $stmt->execute([$target_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = "User not found.";
        } else {
            // Get ALL user's active packages (not just one)
            // EXCLUDE specific packages: 1528 and 1527
            $stmt = $db->prepare("
                SELECT ap.*, p.name as package_name 
                FROM active_packages ap 
                JOIN packages p ON ap.package_id = p.id 
                WHERE ap.user_id = ? AND ap.status = 'active' 
                AND ap.id NOT IN (1528, 1527)
                ORDER BY ap.created_at DESC
            ");
            $stmt->execute([$target_user_id]);
            $active_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Also get excluded packages for display
            $stmt_excluded = $db->prepare("
                SELECT ap.*, p.name as package_name 
                FROM active_packages ap 
                JOIN packages p ON ap.package_id = p.id 
                WHERE ap.user_id = ? AND ap.status = 'active' 
                AND ap.id IN (1528, 1527)
                ORDER BY ap.created_at DESC
            ");
            $stmt_excluded->execute([$target_user_id]);
            $excluded_packages_raw = $stmt_excluded->fetchAll(PDO::FETCH_ASSOC);
            
            // Store excluded packages info for display
            $excluded_packages = [];
            if (!empty($excluded_packages_raw)) {
                foreach ($excluded_packages_raw as $excl) {
                    $excluded_packages[] = [
                        'id' => $excl['id'],
                        'name' => $excl['package_name']
                    ];
                }
            }
            
            if (empty($active_packages)) {
                $error = "No active packages found for user {$user['full_name']} (ID: $target_user_id) to extend. " . 
                         (count($excluded_packages) > 0 ? "Note: Packages 1528 and 1527 are excluded." : "");
            } else {
                // Update maturity date for ALL active packages (NO balance changes, only date extension)
                $db->beginTransaction();
                
                $updated_packages = [];
                $failed_packages = [];
                
                foreach ($active_packages as $active_package) {
                    try {
                        // Calculate new maturity date for this package
                        $current_maturity = new DateTime($active_package['maturity_date']);
                        $new_maturity = clone $current_maturity;
                        $new_maturity->modify("+{$hours} hours");
                        $new_maturity_date = $new_maturity->format('Y-m-d H:i:s');
                        
                        // Update this package's maturity date
                        $update_stmt = $db->prepare("
                            UPDATE active_packages 
                            SET maturity_date = ?
                            WHERE id = ? AND user_id = ? AND status = 'active'
                        ");
                        $update_result = $update_stmt->execute([$new_maturity_date, $active_package['id'], $target_user_id]);
                        
                        if ($update_result && $update_stmt->rowCount() > 0) {
                            // Log the change for each package
                            error_log(sprintf(
                                "PACKAGE MATURITY EXTENDED - User ID: %d (%s) | Package ID: %d (%s) | Old Maturity: %s | New Maturity: %s | Extension: %d hours | Extended by: %s",
                                $target_user_id,
                                $user['full_name'],
                                $active_package['id'],
                                $active_package['package_name'],
                                $active_package['maturity_date'],
                                $new_maturity_date,
                                $hours,
                                $_SESSION['full_name'] ?? 'Admin'
                            ));
                            
                            $updated_packages[] = [
                                'id' => $active_package['id'],
                                'name' => $active_package['package_name'],
                                'old_maturity' => $active_package['maturity_date'],
                                'new_maturity' => $new_maturity_date,
                                'investment' => $active_package['investment_amount'],
                                'roi' => $active_package['expected_roi']
                            ];
                        } else {
                            $failed_packages[] = [
                                'id' => $active_package['id'],
                                'name' => $active_package['package_name']
                            ];
                        }
                    } catch (Exception $e) {
                        $failed_packages[] = [
                            'id' => $active_package['id'],
                            'name' => $active_package['package_name'],
                            'error' => $e->getMessage()
                        ];
                        error_log("Error extending package ID {$active_package['id']}: " . $e->getMessage());
                    }
                }
                
                if (!empty($updated_packages)) {
                    $db->commit();
                    
                    // Build success message with all updated packages
                    $success = sprintf(
                        "<strong>✅ Successfully extended %d package(s) for %s (ID: %d)</strong><br><br>",
                        count($updated_packages),
                        htmlspecialchars($user['full_name']),
                        $target_user_id
                    );
                    
                    $success .= "<div class='space-y-3 mt-4'>";
                    foreach ($updated_packages as $pkg) {
                        $success .= sprintf(
                            "<div class='p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-lg'>" .
                            "<strong>Package:</strong> %s (ID: %d)<br>" .
                            "<strong>Investment:</strong> %s<br>" .
                            "<strong>Expected ROI:</strong> %s<br>" .
                            "<strong>Old Maturity:</strong> <span class='text-yellow-400'>%s</span><br>" .
                            "<strong>New Maturity:</strong> <span class='text-emerald-400 font-bold'>%s</span><br>" .
                            "<strong>Extension:</strong> +%d hours" .
                            "</div>",
                            htmlspecialchars($pkg['name']),
                            $pkg['id'],
                            formatMoney($pkg['investment']),
                            formatMoney($pkg['roi']),
                            date('M j, Y g:i A', strtotime($pkg['old_maturity'])),
                            date('M j, Y g:i A', strtotime($pkg['new_maturity'])),
                            $hours
                        );
                    }
                    $success .= "</div>";
                    
                    if (!empty($failed_packages)) {
                        $success .= "<br><div class='p-3 bg-yellow-500/10 border border-yellow-500/30 rounded-lg mt-4'>";
                        $success .= "<strong>⚠️ Warning:</strong> " . count($failed_packages) . " package(s) could not be updated:<br>";
                        foreach ($failed_packages as $failed) {
                            $success .= "- " . htmlspecialchars($failed['name']) . " (ID: {$failed['id']})";
                            if (isset($failed['error'])) {
                                $success .= " - " . htmlspecialchars($failed['error']);
                            }
                            $success .= "<br>";
                        }
                        $success .= "</div>";
                    }
                    
                    // Show excluded packages in success message
                    if (!empty($excluded_packages)) {
                        $success .= "<br><div class='p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg mt-4'>";
                        $success .= "<strong>ℹ️ Info:</strong> " . count($excluded_packages) . " package(s) were excluded from extension (as requested):<br>";
                        foreach ($excluded_packages as $excluded) {
                            $success .= "- " . htmlspecialchars($excluded['name']) . " (ID: {$excluded['id']})<br>";
                        }
                        $success .= "</div>";
                    }
                } else {
                    $db->rollBack();
                    $error = "Failed to update any packages. All updates failed.";
                }
            }
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error extending maturity: " . $e->getMessage();
        error_log("Error extending package maturity: " . $e->getMessage());
    }
}

// Get user info for display
$stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Get ALL user's active packages info (EXCLUDING packages 1528 and 1527)
$stmt = $db->prepare("
    SELECT ap.*, p.name as package_name 
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    AND ap.id NOT IN (1528, 1527)
    ORDER BY ap.created_at DESC
");
$stmt->execute([$user_id]);
$package_info_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get excluded packages for display
$stmt_excluded = $db->prepare("
    SELECT ap.*, p.name as package_name 
    FROM active_packages ap 
    JOIN packages p ON ap.package_id = p.id 
    WHERE ap.user_id = ? AND ap.status = 'active' 
    AND ap.id IN (1528, 1527)
    ORDER BY ap.created_at DESC
");
$stmt_excluded->execute([$user_id]);
$excluded_packages_list = $stmt_excluded->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Extend Package Maturity - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-gray-800 rounded-xl p-8 shadow-2xl">
            <h1 class="text-3xl font-bold mb-6">
                <i class="fas fa-clock text-yellow-400 mr-3"></i>
                Extend Package Maturity
            </h1>
            
            <?php if ($error): ?>
            <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                    <span class="text-red-300"><?php echo $error; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
            <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                    <span class="text-emerald-300"><?php echo $success; ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($user_info): ?>
            <div class="mb-6 p-4 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                <h3 class="font-bold text-blue-400 mb-2">User Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user_info['full_name']); ?></p>
                <p><strong>User ID:</strong> <?php echo $user_info['id']; ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
            </div>
            
            <?php if (!empty($excluded_packages_list)): ?>
            <div class="mb-6 p-4 bg-yellow-500/10 border border-yellow-500/30 rounded-lg">
                <h3 class="font-bold text-yellow-400 mb-3">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Excluded Packages (<?php echo count($excluded_packages_list); ?> package(s) - Will NOT be extended)
                </h3>
                <div class="space-y-3">
                    <?php foreach ($excluded_packages_list as $package_info): ?>
                    <div class="p-3 bg-gray-700/50 rounded-lg border border-yellow-500/30">
                        <p><strong>Package:</strong> <?php echo htmlspecialchars($package_info['package_name']); ?> (ID: <?php echo $package_info['id']; ?>)</p>
                        <p><strong>Investment Amount:</strong> <?php echo formatMoney($package_info['investment_amount']); ?></p>
                        <p><strong>Expected ROI:</strong> <?php echo formatMoney($package_info['expected_roi']); ?></p>
                        <p><strong>Current Maturity Date:</strong> <span class="text-yellow-400 font-bold"><?php echo date('M j, Y g:i A', strtotime($package_info['maturity_date'])); ?></span></p>
                        <p class="text-yellow-300 text-sm mt-2"><i class="fas fa-info-circle mr-1"></i>This package will remain unchanged.</p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($package_info_list)): ?>
            <div class="mb-6 p-4 bg-purple-500/10 border border-purple-500/30 rounded-lg">
                <h3 class="font-bold text-purple-400 mb-3">Active Packages to Extend (<?php echo count($package_info_list); ?> package(s))</h3>
                <div class="space-y-3">
                    <?php foreach ($package_info_list as $package_info): ?>
                    <div class="p-3 bg-gray-700/50 rounded-lg">
                        <p><strong>Package:</strong> <?php echo htmlspecialchars($package_info['package_name']); ?> (ID: <?php echo $package_info['id']; ?>)</p>
                        <p><strong>Investment Amount:</strong> <?php echo formatMoney($package_info['investment_amount']); ?></p>
                        <p><strong>Expected ROI:</strong> <?php echo formatMoney($package_info['expected_roi']); ?></p>
                        <p><strong>Current Maturity Date:</strong> <span class="text-yellow-400 font-bold"><?php echo date('M j, Y g:i A', strtotime($package_info['maturity_date'])); ?></span></p>
                        <p><strong>New Maturity Date (after +<?php echo $extension_hours; ?> hours):</strong> <span class="text-emerald-400 font-bold"><?php 
                            $new_date = new DateTime($package_info['maturity_date']);
                            $new_date->modify("+{$extension_hours} hours");
                            echo $new_date->format('M j, Y g:i A');
                        ?></span></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="hours" value="<?php echo $extension_hours; ?>">
                
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                        <div>
                            <p class="text-yellow-200 text-sm">
                                <strong>Warning:</strong> This will extend the maturity date by <?php echo $extension_hours; ?> hours for <strong><?php echo count($package_info_list); ?> active package(s)</strong>. 
                                <?php if (!empty($excluded_packages_list)): ?>
                                <strong>Note:</strong> Packages 1528 and 1527 will be excluded from this extension.
                                <?php endif; ?>
                                This action does NOT affect user balances, investment amounts, or ROI calculations. 
                                Only the maturity date(s) will be updated.
                            </p>
                        </div>
                    </div>
                </div>
                
                <button 
                    type="submit" 
                    name="extend_maturity" 
                    value="1"
                    class="w-full py-3 bg-gradient-to-r from-yellow-600 to-yellow-700 hover:from-yellow-700 hover:to-yellow-800 text-white font-bold rounded-lg transition-all duration-200 transform hover:scale-105"
                >
                    <i class="fas fa-clock mr-2"></i>Extend ALL Packages by <?php echo $extension_hours; ?> Hours
                </button>
            </form>
            <?php else: ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                <p class="text-red-300">No active package found for this user.</p>
            </div>
            <?php endif; ?>
            
            <div class="mt-6">
                <a href="/admin/users.php" class="text-blue-400 hover:text-blue-300">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Users
                </a>
            </div>
            <?php else: ?>
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-lg">
                <p class="text-red-300">User not found.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

