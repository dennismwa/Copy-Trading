<?php
/**
 * Adjust All Active Trades Maturity Date
 * Admin page to add hours/minutes to ALL active trades for ALL users
 * SAFE OPERATION - Only updates maturity_date field, does not affect balances
 */

require_once '../config/database.php';
requireAdmin();

$success = '';
$error = '';
$preview_data = null;
$selected_package_ids = [];
$filter_user_id = isset($_GET['filter_user']) ? (int)$_GET['filter_user'] : null;
$filter_package_name = isset($_GET['filter_package']) ? $_GET['filter_package'] : '';

// Handle the adjustment
if ($_POST && isset($_POST['adjust_maturity'])) {
    $hours = isset($_POST['hours']) ? (int)$_POST['hours'] : 0;
    $minutes = isset($_POST['minutes']) ? (int)$_POST['minutes'] : 0;
    $selected_package_ids = isset($_POST['selected_packages']) ? array_map('intval', $_POST['selected_packages']) : [];
    
    // Validate input
    if (empty($selected_package_ids)) {
        $error = "Please select at least one trade to adjust.";
    } elseif ($hours == 0 && $minutes == 0) {
        $error = "Please enter at least 1 hour or 1 minute to add.";
    } elseif ($hours < 0 || $minutes < 0) {
        $error = "Hours and minutes must be positive numbers.";
    } elseif ($minutes >= 60) {
        $error = "Minutes must be less than 60. Use hours instead.";
    } else {
        try {
            // Get selected packages only
            $placeholders = implode(',', array_fill(0, count($selected_package_ids), '?'));
            $stmt = $db->prepare("
                SELECT 
                    ap.id,
                    ap.user_id,
                    ap.maturity_date,
                    ap.investment_amount,
                    ap.expected_roi,
                    u.full_name,
                    u.email,
                    p.name as package_name
                FROM active_packages ap
                JOIN users u ON ap.user_id = u.id
                JOIN packages p ON ap.package_id = p.id
                WHERE ap.status = 'active'
                AND ap.id IN ($placeholders)
                ORDER BY ap.created_at DESC
            ");
            $stmt->execute($selected_package_ids);
            $all_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($all_packages)) {
                $error = "No active trades found to adjust (selected trades may have been deactivated).";
            } else {
                // Calculate total time to add
                $total_minutes = ($hours * 60) + $minutes;
                
                // Update all packages in a transaction
                $db->beginTransaction();
                
                $updated_count = 0;
                $failed_count = 0;
                $updated_packages = [];
                
                foreach ($all_packages as $package) {
                    try {
                        // Calculate new maturity date
                        $current_maturity = new DateTime($package['maturity_date']);
                        $new_maturity = clone $current_maturity;
                        $new_maturity->modify("+{$total_minutes} minutes");
                        $new_maturity_date = $new_maturity->format('Y-m-d H:i:s');
                        
                        // Update this package's maturity date
                        $update_stmt = $db->prepare("
                            UPDATE active_packages 
                            SET maturity_date = ?
                            WHERE id = ? AND user_id = ? AND status = 'active'
                        ");
                        $result = $update_stmt->execute([$new_maturity_date, $package['id'], $package['user_id']]);
                        
                        if ($result && $update_stmt->rowCount() > 0) {
                            $updated_count++;
                            $updated_packages[] = [
                                'id' => $package['id'],
                                'user_id' => $package['user_id'],
                                'user_name' => $package['full_name'],
                                'package_name' => $package['package_name'],
                                'old_maturity' => $package['maturity_date'],
                                'new_maturity' => $new_maturity_date,
                                'investment' => $package['investment_amount'],
                                'roi' => $package['expected_roi']
                            ];
                            
                            // Log the change
                            error_log(sprintf(
                                "ALL TRADES MATURITY ADJUSTED - User ID: %d (%s) | Package ID: %d (%s) | Old Maturity: %s | New Maturity: %s | Added: %d hours, %d minutes | Adjusted by: %s",
                                $package['user_id'],
                                $package['full_name'],
                                $package['id'],
                                $package['package_name'],
                                $package['maturity_date'],
                                $new_maturity_date,
                                $hours,
                                $minutes,
                                $_SESSION['full_name'] ?? 'Admin'
                            ));
                        } else {
                            $failed_count++;
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                        error_log("Error adjusting package ID {$package['id']}: " . $e->getMessage());
                    }
                }
                
                if ($updated_count > 0) {
                    $db->commit();
                    
                    $success = sprintf(
                        "<strong>✅ Successfully adjusted %d active trade(s)!</strong><br><br>" .
                        "<strong>Time Added:</strong> %d hour(s), %d minute(s)<br>" .
                        "<strong>Total Minutes Added:</strong> %d minutes<br><br>" .
                        "<strong>Summary:</strong><br>" .
                        "• Successfully updated: %d trade(s)<br>" .
                        "%s",
                        $updated_count,
                        $hours,
                        $minutes,
                        $total_minutes,
                        $updated_count,
                        $failed_count > 0 ? "• Failed to update: $failed_count trade(s)<br>" : ""
                    );
                    
                    // Store preview data for display
                    $preview_data = [
                        'updated_count' => $updated_count,
                        'failed_count' => $failed_count,
                        'hours' => $hours,
                        'minutes' => $minutes,
                        'total_minutes' => $total_minutes,
                        'packages' => array_slice($updated_packages, 0, 50) // Show first 50 for display
                    ];
                } else {
                    $db->rollBack();
                    $error = "Failed to update any trades. All updates failed.";
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = "Error adjusting maturity dates: " . $e->getMessage();
            error_log("Error in adjust-all-trades-maturity.php: " . $e->getMessage());
        }
    }
}

// Get all users for filter dropdown
$stmt = $db->query("SELECT id, full_name, email FROM users ORDER BY full_name ASC");
$all_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all package names for filter
$stmt = $db->query("SELECT DISTINCT name FROM packages ORDER BY name ASC");
$all_package_names = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Build query with filters
$query_conditions = ["ap.status = 'active'"];
$query_params = [];

if ($filter_user_id) {
    $query_conditions[] = "ap.user_id = ?";
    $query_params[] = $filter_user_id;
}

if ($filter_package_name) {
    $query_conditions[] = "p.name = ?";
    $query_params[] = $filter_package_name;
}

$where_clause = implode(' AND ', $query_conditions);

// Get all active packages for preview (with filters)
$stmt = $db->prepare("
    SELECT 
        ap.id,
        ap.user_id,
        ap.maturity_date,
        ap.investment_amount,
        ap.expected_roi,
        u.full_name,
        u.email,
        p.name as package_name
    FROM active_packages ap
    JOIN users u ON ap.user_id = u.id
    JOIN packages p ON ap.package_id = p.id
    WHERE $where_clause
    ORDER BY u.full_name ASC, ap.created_at DESC
");
$stmt->execute($query_params);
$all_active_packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_active_trades = count($all_active_packages);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adjust All Active Trades Maturity - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .show {
            display: flex !important;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">
    <div class="max-w-7xl mx-auto">
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-xl p-6 mb-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold mb-2">
                        <i class="fas fa-clock-rotate-left text-yellow-300 mr-3"></i>
                        Adjust All Active Trades Maturity
                    </h1>
                    <p class="text-purple-100">Add hours or minutes to maturity dates for all active trades</p>
                </div>
                <a href="/admin/index.php" class="px-4 py-2 bg-white bg-opacity-20 hover:bg-opacity-30 rounded-lg transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Admin
                </a>
            </div>
        </div>

        <!-- Error Message -->
        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo $error; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Success Message -->
        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo $success; ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Active Trades</p>
                        <p class="text-2xl font-bold text-white"><?php echo $total_active_trades; ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-chart-line text-blue-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Users</p>
                        <p class="text-2xl font-bold text-white"><?php echo count(array_unique(array_column($all_active_packages, 'user_id'))); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-users text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total Investment</p>
                        <p class="text-2xl font-bold text-white"><?php echo formatMoney(array_sum(array_column($all_active_packages, 'investment_amount'))); ?></p>
                    </div>
                    <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-coins text-yellow-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl mb-6">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-filter text-blue-400 mr-2"></i>
                Filter Trades
            </h2>
            
            <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-user text-blue-400 mr-1"></i>
                        Filter by User
                    </label>
                    <select 
                        name="filter_user" 
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                        onchange="this.form.submit()"
                    >
                        <option value="">All Users</option>
                        <?php foreach ($all_users as $user): ?>
                        <option value="<?php echo $user['id']; ?>" <?php echo $filter_user_id == $user['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['full_name']); ?> (ID: <?php echo $user['id']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                        <i class="fas fa-box text-green-400 mr-1"></i>
                        Filter by Package
                    </label>
                    <select 
                        name="filter_package" 
                        class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:outline-none"
                        onchange="this.form.submit()"
                    >
                        <option value="">All Packages</option>
                        <?php foreach ($all_package_names as $pkg_name): ?>
                        <option value="<?php echo htmlspecialchars($pkg_name); ?>" <?php echo $filter_package_name == $pkg_name ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pkg_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-end">
                    <a 
                        href="adjust-all-trades-maturity.php" 
                        class="w-full px-4 py-3 bg-gray-700 hover:bg-gray-600 border border-gray-600 rounded-lg text-white text-center transition"
                    >
                        <i class="fas fa-times mr-2"></i>Clear Filters
                    </a>
                </div>
            </form>
        </div>

        <!-- Adjustment Form -->
        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl mb-6">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-sliders-h text-purple-400 mr-2"></i>
                Maturity Date Adjustment
            </h2>
            
            <form method="POST" id="adjustForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-clock text-blue-400 mr-1"></i>
                            Hours to Add
                        </label>
                        <input 
                            type="number" 
                            name="hours" 
                            id="hours"
                            value="0"
                            min="0"
                            max="168"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-blue-500 focus:outline-none"
                            placeholder="0"
                        >
                        <p class="text-xs text-gray-500 mt-1">Maximum: 168 hours (7 days)</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-stopwatch text-green-400 mr-1"></i>
                            Minutes to Add
                        </label>
                        <input 
                            type="number" 
                            name="minutes" 
                            id="minutes"
                            value="0"
                            min="0"
                            max="59"
                            class="w-full px-4 py-3 bg-gray-700 border border-gray-600 rounded-lg text-white focus:border-green-500 focus:outline-none"
                            placeholder="0"
                        >
                        <p class="text-xs text-gray-500 mt-1">Maximum: 59 minutes</p>
                    </div>
                </div>
                
                <!-- Selection Controls -->
                <div class="bg-gray-700/50 rounded-lg p-4 border border-gray-600">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-gray-300">
                            <i class="fas fa-check-square text-yellow-400 mr-1"></i>
                            Selection Controls
                        </span>
                        <div class="space-x-2">
                            <button 
                                type="button" 
                                onclick="selectAll()" 
                                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded transition"
                            >
                                <i class="fas fa-check-double mr-1"></i>Select All
                            </button>
                            <button 
                                type="button" 
                                onclick="deselectAll()" 
                                class="px-3 py-1 bg-gray-600 hover:bg-gray-700 text-white text-sm rounded transition"
                            >
                                <i class="fas fa-times mr-1"></i>Deselect All
                            </button>
                        </div>
                    </div>
                    <p class="text-xs text-gray-400">
                        Selected: <span id="selectedCount" class="font-bold text-yellow-400">0</span> of <?php echo $total_active_trades; ?> trade(s)
                    </p>
                </div>

                <!-- Preview Section -->
                <div id="previewSection" class="hidden bg-gray-700/50 rounded-lg p-4 border border-gray-600">
                    <h3 class="font-bold text-yellow-400 mb-2">
                        <i class="fas fa-eye mr-2"></i>Preview
                    </h3>
                    <div id="previewContent" class="text-sm text-gray-300"></div>
                </div>

                <!-- Warning -->
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                        <div>
                            <p class="text-yellow-200 text-sm">
                                <strong>Warning:</strong> This will adjust the maturity date for <strong id="selectedCountWarning">0</strong> selected trade(s). 
                                This action does NOT affect user balances, investment amounts, or ROI calculations. 
                                Only the maturity_date field will be updated.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Active Trades List -->
                <?php if (!empty($all_active_packages)): ?>
                <div class="bg-gray-700/50 rounded-lg p-4 border border-gray-600 mt-4">
                    <h2 class="text-xl font-bold mb-4">
                        <i class="fas fa-list text-indigo-400 mr-2"></i>
                        Active Trades (<?php echo $total_active_trades; ?>)
                        <?php if ($filter_user_id || $filter_package_name): ?>
                        <span class="text-sm font-normal text-gray-400">
                            (Filtered)
                        </span>
                        <?php endif; ?>
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-gray-700">
                                    <th class="pb-3 text-gray-400 font-semibold w-12">
                                        <input 
                                            type="checkbox" 
                                            id="selectAllCheckbox" 
                                            onchange="toggleAll(this)"
                                            class="w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                                        >
                                    </th>
                                    <th class="pb-3 text-gray-400 font-semibold">User</th>
                                    <th class="pb-3 text-gray-400 font-semibold">Package</th>
                                    <th class="pb-3 text-gray-400 font-semibold">Investment</th>
                                    <th class="pb-3 text-gray-400 font-semibold">Expected ROI</th>
                                    <th class="pb-3 text-gray-400 font-semibold">Current Maturity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_active_packages as $pkg): ?>
                                <tr class="border-b border-gray-700/50 hover:bg-gray-700/30">
                                    <td class="py-3">
                                        <input 
                                            type="checkbox" 
                                            name="selected_packages[]" 
                                            value="<?php echo $pkg['id']; ?>"
                                            class="package-checkbox w-4 h-4 text-blue-600 bg-gray-700 border-gray-600 rounded focus:ring-blue-500"
                                            onchange="updateSelectedCount()"
                                        >
                                    </td>
                                    <td class="py-3">
                                        <div>
                                            <p class="font-medium"><?php echo htmlspecialchars($pkg['full_name']); ?></p>
                                            <p class="text-xs text-gray-500">ID: <?php echo $pkg['user_id']; ?></p>
                                        </div>
                                    </td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 bg-purple-500/20 text-purple-300 rounded text-sm">
                                            <?php echo htmlspecialchars($pkg['package_name']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 text-green-400 font-semibold">
                                        <?php echo formatMoney($pkg['investment_amount']); ?>
                                    </td>
                                    <td class="py-3 text-yellow-400 font-semibold">
                                        <?php echo formatMoney($pkg['expected_roi']); ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="text-gray-300">
                                            <?php echo date('M j, Y g:i A', strtotime($pkg['maturity_date'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <div class="bg-gray-700/50 rounded-lg p-4 border border-gray-600 mt-4 text-center">
                    <i class="fas fa-inbox text-gray-600 text-5xl mb-4"></i>
                    <p class="text-gray-400">No active trades found.</p>
                </div>
                <?php endif; ?>

                <button 
                    type="submit" 
                    name="adjust_maturity" 
                    value="1"
                    class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white font-bold rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg mt-4"
                >
                    <i class="fas fa-check-circle mr-2"></i>Adjust All Active Trades Maturity
                </button>
            </form>
        </div>
    </div>

    <script>
        // Selection functions
        function selectAll() {
            const checkboxes = document.querySelectorAll('.package-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAllCheckbox').checked = true;
            updateSelectedCount();
        }
        
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.package-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelectedCount();
        }
        
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.package-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.package-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('selectedCountWarning').textContent = count;
            
            // Update select all checkbox state
            const allCheckboxes = document.querySelectorAll('.package-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            if (allCheckboxes.length > 0) {
                selectAllCheckbox.checked = (count === allCheckboxes.length);
                selectAllCheckbox.indeterminate = (count > 0 && count < allCheckboxes.length);
            }
        }
        
        // Initialize count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSelectedCount();
        });
        
        // Preview functionality
        const hoursInput = document.getElementById('hours');
        const minutesInput = document.getElementById('minutes');
        const previewSection = document.getElementById('previewSection');
        const previewContent = document.getElementById('previewContent');
        
        function updatePreview() {
            const hours = parseInt(hoursInput.value) || 0;
            const minutes = parseInt(minutesInput.value) || 0;
            const totalMinutes = (hours * 60) + minutes;
            const selectedCount = document.querySelectorAll('.package-checkbox:checked').length;
            
            if (totalMinutes > 0 && selectedCount > 0) {
                previewSection.classList.remove('hidden');
                previewContent.innerHTML = `
                    <p><strong>Time to add:</strong> ${hours} hour(s), ${minutes} minute(s) (${totalMinutes} total minutes)</p>
                    <p class="mt-2"><strong>Affected trades:</strong> ${selectedCount} selected trade(s)</p>
                    <p class="mt-2 text-yellow-300"><i class="fas fa-info-circle mr-1"></i>Selected trades will have their maturity date extended by this amount.</p>
                `;
            } else {
                previewSection.classList.add('hidden');
            }
        }
        
        hoursInput.addEventListener('input', updatePreview);
        minutesInput.addEventListener('input', updatePreview);
        
        // Update preview when selection changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('package-checkbox')) {
                updatePreview();
            }
        });
        
        // Form validation
        document.getElementById('adjustForm').addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.package-checkbox:checked').length;
            const hours = parseInt(hoursInput.value) || 0;
            const minutes = parseInt(minutesInput.value) || 0;
            
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Please select at least one trade to adjust.');
                return false;
            }
            
            if (hours === 0 && minutes === 0) {
                e.preventDefault();
                alert('Please enter at least 1 hour or 1 minute to add.');
                return false;
            }
            
            if (minutes >= 60) {
                e.preventDefault();
                alert('Minutes must be less than 60. Please convert to hours.');
                return false;
            }
            
            if (hours < 0 || minutes < 0) {
                e.preventDefault();
                alert('Hours and minutes must be positive numbers.');
                return false;
            }
            
            // Confirm action
            const totalMinutes = (hours * 60) + minutes;
            const confirmMessage = `Are you sure you want to add ${hours} hour(s) and ${minutes} minute(s) (${totalMinutes} total minutes) to ${selectedCount} selected trade(s)?\n\nThis will update the maturity date for the selected trades.\n\nThis action does NOT affect balances or amounts.`;
            
            if (!confirm(confirmMessage)) {
                e.preventDefault();
                return false;
            }
        });
    </script>
</body>
</html>

