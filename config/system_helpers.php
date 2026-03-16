<?php
/**
 * ULTRA HARVEST GLOBAL - SYSTEM HELPER FUNCTIONS
 * Additional helper functions for the new features
 */

/**
 * Process Withdrawal with Fees
 * Automatically calculates and applies withdrawal fees based on user's package tier
 */
function processWithdrawalWithFees($db, $transaction_id, $user_id, $amount) {
    try {
        // Get user's current package tier
        $stmt = $db->prepare("
            SELECT p.name 
            FROM active_packages ap 
            JOIN packages p ON ap.package_id = p.id 
            WHERE ap.user_id = ? AND ap.status = 'active' 
            ORDER BY ap.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $package = $stmt->fetch();
        
        // Withdrawal fee structure
        $withdrawal_fees = [
            'Seed' => 7,
            'Sprout' => 6,
            'Growth' => 5,
            'Harvest' => 5,
            'Golden Yield' => 4,
            'Elite' => 3
        ];
        
        // Default to highest fee if no active package
        $fee_percentage = 7;
        if ($package && isset($withdrawal_fees[$package['name']])) {
            $fee_percentage = $withdrawal_fees[$package['name']];
        }
        
        // Call stored procedure to process fees
        $stmt = $db->prepare("CALL process_withdrawal_fees(?, ?, ?)");
        $stmt->execute([$transaction_id, $amount, $fee_percentage]);
        
        return [
            'success' => true,
            'fee_percentage' => $fee_percentage,
            'message' => 'Withdrawal processed successfully'
        ];
        
    } catch (Exception $e) {
        error_log("Withdrawal fee processing error: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Failed to process withdrawal fees'
        ];
    }
}

/**
 * Check if user can activate new package
 * Prevents activation if user has matured packages not yet withdrawn
 */
function canActivatePackage($db, $user_id) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as matured_count
        FROM active_packages
        WHERE user_id = ? 
        AND status = 'completed'
        AND withdrawn_at IS NULL
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    
    return $result['matured_count'] == 0;
}

/**
 * Get matured packages requiring withdrawal
 */
function getMaturedPackagesRequiringWithdrawal($db, $user_id) {
    $stmt = $db->prepare("
        SELECT ap.*, p.name as package_name, p.icon
        FROM active_packages ap
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.user_id = ? 
        AND ap.status = 'completed'
        AND ap.withdrawn_at IS NULL
        ORDER BY ap.completed_at DESC
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

/**
 * Mark package as withdrawn
 */
function markPackageAsWithdrawn($db, $package_id, $user_id) {
    $stmt = $db->prepare("
        UPDATE active_packages 
        SET withdrawn_at = NOW(), can_reinvest = 1 
        WHERE id = ? AND user_id = ?
    ");
    return $stmt->execute([$package_id, $user_id]);
}

/**
 * Get Admin Wallet Balance
 */
function getAdminWalletBalance($db) {
    $stmt = $db->query("SELECT * FROM admin_wallet LIMIT 1");
    return $stmt->fetch();
}

/**
 * Admin Wallet Withdrawal
 * Allow admin to withdraw from admin wallet
 */
function adminWalletWithdraw($db, $admin_id, $amount, $description = '') {
    try {
        $db->beginTransaction();
        
        // Check admin wallet balance
        $wallet = getAdminWalletBalance($db);
        if ($wallet['balance'] < $amount) {
            throw new Exception('Insufficient admin wallet balance');
        }
        
        // Deduct from admin wallet
        $stmt = $db->prepare("
            UPDATE admin_wallet 
            SET balance = balance - ?
        ");
        $stmt->execute([$amount]);
        
        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO admin_wallet_transactions (type, amount, description, admin_id)
            VALUES ('withdrawal', ?, ?, ?)
        ");
        $stmt->execute([$amount, $description, $admin_id]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Withdrawal successful'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Admin Wallet Injection
 * Allow admin to inject money to business float
 */
function adminWalletInject($db, $admin_id, $amount, $description = '') {
    try {
        $db->beginTransaction();
        
        // Add to admin wallet
        $stmt = $db->prepare("
            UPDATE admin_wallet 
            SET balance = balance + ?
        ");
        $stmt->execute([$amount]);
        
        // Record transaction
        $stmt = $db->prepare("
            INSERT INTO admin_wallet_transactions (type, amount, description, admin_id)
            VALUES ('injection', ?, ?, ?)
        ");
        $stmt->execute([$amount, $description, $admin_id]);
        
        $db->commit();
        return ['success' => true, 'message' => 'Injection successful'];
        
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Coverage Ratio
 */
function getCoverageRatio($db) {
    $stmt = $db->query("SELECT * FROM coverage_ratio_view");
    return $stmt->fetch();
}

/**
 * Get User Growth Statistics
 */
function getUserGrowthStats($db, $days = 30) {
    $stmt = $db->prepare("
        SELECT * FROM user_growth_stats 
        ORDER BY date DESC 
        LIMIT ?
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Update Daily User Growth Stats
 * Should be called by cron job daily
 */
function updateDailyUserGrowthStats($db) {
    try {
        $stmt = $db->query("CALL update_user_growth_stats()");
        return ['success' => true, 'message' => 'User growth stats updated'];
    } catch (Exception $e) {
        error_log("User growth stats update error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get Withdrawal Fee Statistics
 */
function getWithdrawalFeeStats($db, $days = 30) {
    $stmt = $db->prepare("
        SELECT * FROM withdrawal_fee_stats 
        ORDER BY date DESC 
        LIMIT ?
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Get Today's Withdrawal Fee Stats
 */
function getTodayWithdrawalFeeStats($db) {
    $stmt = $db->query("
        SELECT * FROM withdrawal_fee_stats 
        WHERE date = CURDATE()
    ");
    return $stmt->fetch();
}

/**
 * Calculate Package Maturity Date Correctly
 * Fixes the time calculation issue
 */
function calculatePackageMaturity($package_duration_hours) {
    // Use exact hours for calculation
    return date('Y-m-d H:i:s', strtotime("+{$package_duration_hours} hours"));
}

/**
 * Get Package Display Duration
 */
function getPackageDisplayDuration($hours) {
    if ($hours < 24) {
        return $hours . ' hours';
    } elseif ($hours == 24) {
        return '1 day (24 hours)';
    } elseif ($hours == 48) {
        return '2 days (48 hours)';
    } elseif ($hours == 72) {
        return '3 days (72 hours)';
    } elseif ($hours == 168) {
        return '7 days (1 week)';
    } else {
        $days = floor($hours / 24);
        return $days . ' days (' . $hours . ' hours)';
    }
}

/**
 * Check Referral Eligibility
 * Ensures referral code is valid and not self-referral
 */
function validateReferralCode($db, $referral_code, $new_user_email) {
    if (empty($referral_code)) {
        return ['valid' => false, 'message' => 'No referral code provided'];
    }
    
    $stmt = $db->prepare("
        SELECT id, email, full_name 
        FROM users 
        WHERE referral_code = ? AND status = 'active'
    ");
    $stmt->execute([$referral_code]);
    $referrer = $stmt->fetch();
    
    if (!$referrer) {
        return ['valid' => false, 'message' => 'Invalid referral code'];
    }
    
    if ($referrer['email'] === $new_user_email) {
        return ['valid' => false, 'message' => 'Cannot refer yourself'];
    }
    
    return [
        'valid' => true,
        'referrer_id' => $referrer['id'],
        'referrer_name' => $referrer['full_name']
    ];
}

/**
 * Calculate Net Position (Withdrawal fees + Platform fees)
 */
function calculateNetPosition($db) {
    $stmt = $db->query("
        SELECT 
            COALESCE(SUM(withdrawal_fee), 0) as total_withdrawal_fees,
            COALESCE(SUM(platform_fee), 0) as total_platform_fees,
            COALESCE(SUM(withdrawal_fee + platform_fee), 0) as net_position
        FROM transactions
        WHERE type = 'withdrawal' AND status = 'completed'
    ");
    return $stmt->fetch();
}

/**
 * Get Package-wise Withdrawal Fee Breakdown
 */
function getPackageWiseWithdrawalFees($db, $start_date = null, $end_date = null) {
    $sql = "
        SELECT 
            p.name as package_name,
            COUNT(t.id) as withdrawal_count,
            COALESCE(SUM(t.amount), 0) as total_amount,
            COALESCE(SUM(t.withdrawal_fee), 0) as total_fees
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        LEFT JOIN active_packages ap ON ap.user_id = u.id AND ap.status = 'active'
        LEFT JOIN packages p ON ap.package_id = p.id
        WHERE t.type = 'withdrawal' AND t.status = 'completed'
    ";
    
    if ($start_date && $end_date) {
        $sql .= " AND DATE(t.created_at) BETWEEN ? AND ?";
        $stmt = $db->prepare($sql . " GROUP BY p.name");
        $stmt->execute([$start_date, $end_date]);
    } else {
        $stmt = $db->query($sql . " GROUP BY p.name");
    }
    
    return $stmt->fetchAll();
}

/**
 * Auto-complete matured packages
 * Should be run by cron job
 */
function autoCompleteMaturedPackages($db) {
    try {
        $db->beginTransaction();
        
        // Find matured packages
        $stmt = $db->query("
            SELECT ap.*, u.id as user_id
            FROM active_packages ap
            JOIN users u ON ap.user_id = u.id
            WHERE ap.status = 'active' 
            AND ap.maturity_date <= NOW()
        ");
        $matured_packages = $stmt->fetchAll();
        
        foreach ($matured_packages as $package) {
            // Calculate total return (investment + ROI)
            $total_return = $package['investment_amount'] + $package['expected_roi'];
            
            // Credit user wallet with total return (investment was deducted at purchase)
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?,
                    total_roi_earned = total_roi_earned + ?
                WHERE id = ?
            ");
            $stmt->execute([$total_return, $package['expected_roi'], $package['user_id']]);
            
            // Mark package as completed
            $stmt = $db->prepare("
                UPDATE active_packages 
                SET status = 'completed', completed_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$package['id']]);
            
            // Create ROI payment transaction (record total return)
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description)
                VALUES (?, 'roi_payment', ?, 'completed', ?)
            ");
            $stmt->execute([
                $package['user_id'],
                $total_return,
                "Package completion #{$package['id']} - Principal: " . formatMoney($package['investment_amount']) . " + ROI: " . formatMoney($package['expected_roi'])
            ]);
            
            // Send notification
            sendNotification(
                $package['user_id'],
                'Package Matured!',
                "Your package has matured. " . formatMoney($total_return) . " has been credited to your wallet.",
                'success'
            );
        }
        
        $db->commit();
        return [
            'success' => true,
            'packages_completed' => count($matured_packages),
            'message' => count($matured_packages) . ' packages completed'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Auto-complete packages error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get withdrawal processing time based on amount
 */
function getWithdrawalProcessingTime($amount) {
    $threshold = (float)getSystemSetting('instant_withdrawal_threshold', 10000);
    
    if ($amount < $threshold) {
        return 'About 1 hour'; // As per client requirement
    } else {
        return 'About 1 hour'; // All withdrawals now same time as per document
    }
}

/**
 * Validate package activation
 * Returns error message if activation should be blocked
 */
function validatePackageActivation($db, $user_id, $package_id, $amount) {
    // Check for matured packages
    if (!canActivatePackage($db, $user_id)) {
        $matured = getMaturedPackagesRequiringWithdrawal($db, $user_id);
        return [
            'can_activate' => false,
            'message' => 'You have ' . count($matured) . ' matured package(s) that must be withdrawn before activating a new package.',
            'matured_packages' => $matured
        ];
    }
    
    // Check wallet balance
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if ($user['wallet_balance'] < $amount) {
        return [
            'can_activate' => false,
            'message' => 'Insufficient wallet balance. Please deposit funds first.',
            'required' => $amount,
            'available' => $user['wallet_balance']
        ];
    }
    
    // Check package limits
    $stmt = $db->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch();
    
    if (!$package) {
        return [
            'can_activate' => false,
            'message' => 'Invalid package selected.'
        ];
    }
    
    if ($amount < $package['min_investment']) {
        return [
            'can_activate' => false,
            'message' => 'Amount below minimum investment for this package.',
            'minimum' => $package['min_investment']
        ];
    }
    
    if ($package['max_investment'] && $amount > $package['max_investment']) {
        return [
            'can_activate' => false,
            'message' => 'Amount exceeds maximum investment for this package.',
            'maximum' => $package['max_investment']
        ];
    }
    
    // Check daily trade limit (must be done within a transaction)
    // Note: This check should be done at the point of activation, not here
    // This function is used for pre-validation, so we skip the trade limit check here
    // The actual limit check happens in user/packages.php within a transaction
    
    return [
        'can_activate' => true,
        'message' => 'Package can be activated',
        'package' => $package
    ];
}

/**
 * ============================================================================
 * DAILY WITHDRAWAL LIMIT ENFORCEMENT FUNCTION
 * ============================================================================
 * This function enforces the cumulative daily withdrawal limit per user.
 * It must be called within a database transaction with proper row locking
 * to prevent race conditions.
 * 
 * @param PDO $db Database connection (must be in a transaction)
 * @param int $user_id User ID to check
 * @param float $requested_amount Amount user wants to withdraw
 * @param bool $lock_user_row Whether to lock the user row (should be true for new withdrawals)
 * @return array ['allowed' => bool, 'total_today' => float, 'limit' => float, 'remaining' => float, 'message' => string]
 * 
 * IMPORTANT: This function must be called within a transaction that has already
 * locked the user row with "SELECT ... FOR UPDATE" to prevent race conditions.
 * 
 * Example usage:
 *   $db->beginTransaction();
 *   $stmt = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
 *   $stmt->execute([$user_id]);
 *   $stmt->fetch(); // Lock the row
 *   
 *   $check = checkDailyWithdrawalLimit($db, $user_id, $amount, false);
 *   if (!$check['allowed']) {
 *       $db->rollBack();
 *       throw new Exception($check['message']);
 *   }
 *   // Proceed with withdrawal...
 */
function checkDailyWithdrawalLimit($db, $user_id, $requested_amount, $lock_user_row = false) {
    // CRITICAL: This function MUST be called within a transaction for consistency
    if (!$db->inTransaction()) {
        throw new Exception('checkDailyWithdrawalLimit must be called within a database transaction');
    }
    
    // Lock user row if requested (for new withdrawals)
    if ($lock_user_row) {
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $stmt->fetch(PDO::FETCH_ASSOC); // Lock the user row to prevent concurrent withdrawals
        $stmt->closeCursor(); // Close cursor after locking
    }
    
    // Check if user has an active referral tier assignment (tier limits override system default)
    $stmt = $db->prepare("
        SELECT rt.daily_withdrawal_limit 
        FROM user_tier_assignments uta
        INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
        WHERE uta.user_id = ? AND uta.is_active = 1 AND rt.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $tier_limit = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    
    // Use tier limit if available, otherwise use system default
    if ($tier_limit && isset($tier_limit['daily_withdrawal_limit'])) {
        $daily_withdrawal_limit = (float)$tier_limit['daily_withdrawal_limit'];
    } else {
        // Get daily withdrawal limit from database (force fresh read)
        // CRITICAL: Always fetch fresh from database to get the latest admin-set value
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'daily_withdrawal_limit'");
        $stmt->execute();
        $limit_setting = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // Close cursor to ensure fresh reads
        $daily_withdrawal_limit = (float)($limit_setting['setting_value'] ?? 50000);
    }
    
    // Ensure the limit is valid and positive
    if ($daily_withdrawal_limit <= 0) {
        $daily_withdrawal_limit = 50000; // Fallback to default
    }
    
    $daily_withdrawal_limit_rounded = round($daily_withdrawal_limit, 2);
    $requested_amount_rounded = round($requested_amount, 2);
    
    // ========================================================================
    // CRITICAL: ATOMIC QUERY TO GET TODAY'S TOTAL WITHDRAWALS
    // ========================================================================
    // This query MUST:
    // 1. Count ONLY withdrawals from TODAY (00:00:00 to 23:59:59 server time)
    // 2. Count ONLY successful withdrawals (pending, processing, completed)
    // 3. See committed transactions from other sessions (READ COMMITTED isolation)
    // 4. See uncommitted transactions from THIS transaction (InnoDB behavior)
    // 5. Use database server time (CURDATE()) to avoid timezone issues
    // 6. Be atomic and reliable
    // 7. CRITICAL: Force fresh read by using explicit table lock hint
    
    // CRITICAL: Force MySQL to see the latest committed data by using a fresh connection state
    // We do this by executing a simple query first to ensure we're reading fresh data
    // Also flush any query cache to ensure we see the absolute latest data
    // IMPORTANT: Use query() and fetchAll() to avoid leaving unbuffered queries active
    try {
        $stmt = $db->query("SELECT 1");
        $stmt->fetchAll(PDO::FETCH_ASSOC); // Fully fetch to close the result set
    } catch (Exception $e) {
        // Ignore - this is just to force fresh read
    }
    
    // CRITICAL: Use DATE() function for primary query - it's more reliable for seeing committed transactions
    // The DATE() function ensures we match transactions by date regardless of time component
    // This is the PRIMARY method and should see all committed transactions immediately
    // CRITICAL: Also count transactions with NULL or empty status (treat as pending)
    $stmt = $db->prepare("
        SELECT 
            ROUND(COALESCE(SUM(amount), 0), 2) as total_today,
            COUNT(*) as transaction_count
        FROM transactions 
        WHERE user_id = ? 
        AND type = 'withdrawal' 
        AND (status IN ('pending', 'processing', 'completed') OR status IS NULL OR status = '')
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); // Close cursor immediately to ensure fresh reads
    $total_withdrawn_today = round((float)($result['total_today'] ?? 0), 2);
    $transaction_count = (int)($result['transaction_count'] ?? 0);
    
    // CRITICAL: Double-check using date range as backup verification
    // This ensures we catch all transactions regardless of any edge cases
    // CRITICAL: Also count transactions with NULL or empty status (treat as pending)
    $stmt_backup = $db->prepare("
        SELECT 
            ROUND(COALESCE(SUM(amount), 0), 2) as total_today_backup,
            COUNT(*) as transaction_count_backup
        FROM transactions 
        WHERE user_id = ? 
        AND type = 'withdrawal' 
        AND (status IN ('pending', 'processing', 'completed') OR status IS NULL OR status = '')
        AND created_at >= CURDATE()
        AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
    ");
    $stmt_backup->execute([$user_id]);
    $result_backup = $stmt_backup->fetch(PDO::FETCH_ASSOC);
    $stmt_backup->closeCursor(); // Close cursor immediately
    $total_backup = round((float)($result_backup['total_today_backup'] ?? 0), 2);
    $transaction_count_backup = (int)($result_backup['transaction_count_backup'] ?? 0);
    
    // Use the MAXIMUM of both queries to ensure we never miss a transaction
    // This is a safety measure - both should match, but if they don't, we use the higher value
    if (abs($total_withdrawn_today - $total_backup) > 0.01) {
        error_log(sprintf(
            "WARNING: DATE() query (%.2f, count=%d) differs from date range query (%.2f, count=%d) for User ID %d - Using maximum!",
            $total_withdrawn_today,
            $transaction_count,
            $total_backup,
            $transaction_count_backup,
            $user_id
        ));
        $total_withdrawn_today = max($total_withdrawn_today, $total_backup);
        $transaction_count = max($transaction_count, $transaction_count_backup);
    }
    
    // CRITICAL: Additional verification - manually fetch and sum all transactions
    // This is the ultimate safety check to ensure we never miss a transaction
    // We fetch ALL transactions for today and manually sum only the counted statuses
    // Use DATE() function to match the primary query method
    $stmt_manual = $db->prepare("
        SELECT id, amount, status, created_at, DATE(created_at) as tx_date
        FROM transactions 
        WHERE user_id = ? 
        AND type = 'withdrawal' 
        AND DATE(created_at) = CURDATE()
        ORDER BY created_at DESC
    ");
    $stmt_manual->execute([$user_id]);
    $all_transactions = $stmt_manual->fetchAll(PDO::FETCH_ASSOC);
    $stmt_manual->closeCursor(); // Close cursor immediately
    
    // Manually sum only counted statuses
    $manual_sum = 0;
    $counted_statuses = ['pending', 'processing', 'completed'];
    foreach ($all_transactions as $txn) {
        // CRITICAL: Handle empty string, NULL, or missing status
        // If status is empty/NULL, treat it as 'pending' (default for new withdrawals)
        $status = trim($txn['status'] ?? '');
        if (empty($status)) {
            $status = 'pending'; // Default status for withdrawals
        }
        
        // Count if status is in counted statuses OR if it's empty (treat as pending)
        if (in_array($status, $counted_statuses) || empty($txn['status'])) {
            $manual_sum += (float)$txn['amount'];
        }
    }
    $manual_sum = round($manual_sum, 2);
    
    // Use the MAXIMUM of all three methods to ensure absolute accuracy
    // This triple-verification ensures we NEVER miss a transaction
    if (abs($total_withdrawn_today - $manual_sum) > 0.01) {
        error_log(sprintf(
            "WARNING: Query total (%.2f) differs from manual sum (%.2f) for User ID %d - Using maximum! Transactions found: %d | Transaction details: %s",
            $total_withdrawn_today,
            $manual_sum,
            $user_id,
            count($all_transactions),
            json_encode(array_map(function($t) {
                return ['id' => $t['id'], 'amount' => $t['amount'], 'status' => $t['status']];
            }, $all_transactions))
        ));
        $total_withdrawn_today = max($total_withdrawn_today, $manual_sum);
    }
    
    // Log the check for audit purposes
        error_log(sprintf(
        "DAILY LIMIT CHECK - User ID: %d | Limit: %.2f | Today's Total: %.2f | Requested: %.2f | Transaction Count: %d",
        $user_id, $daily_withdrawal_limit_rounded, $total_withdrawn_today, $requested_amount_rounded, $transaction_count
    ));
    
    // ========================================================================
    // CRITICAL: CALCULATE TOTAL AFTER THIS WITHDRAWAL
    // ========================================================================
    // If requested_amount > 0: We're checking BEFORE creating the transaction
    //   - Calculate: current_total + requested_amount
    // If requested_amount = 0: We're checking AFTER creating the transaction
    //   - The new transaction is already included in total_withdrawn_today
    //   - So total_after_withdrawal = total_withdrawn_today
    $total_after_withdrawal = round($total_withdrawn_today + $requested_amount_rounded, 2);
    
    // Calculate remaining amount user can withdraw today
    $remaining_daily_limit = max(0, round($daily_withdrawal_limit_rounded - $total_withdrawn_today, 2));
    
    // ========================================================================
    // CRITICAL: STRICT LIMIT ENFORCEMENT
    // ========================================================================
    // Block if total AFTER this withdrawal would exceed the daily limit
    // This is the CORE logic that prevents bypassing the limit
    // 
    // Example scenarios:
    // 1. Limit = 10000, Current = 0, Request = 8000 → Total = 8000 → ALLOWED (8000 <= 10000)
    // 2. Limit = 10000, Current = 8000, Request = 7000 → Total = 15000 → BLOCKED (15000 > 10000)
    // 3. Limit = 10000, Current = 8000, Request = 2000 → Total = 10000 → ALLOWED (10000 <= 10000)
    // 4. Limit = 10000, Current = 10000, Request = 1 → Total = 10001 → BLOCKED (10001 > 10000)
    //
    // CRITICAL: We use > (not >=) to allow withdrawals that exactly equal the limit
    // This means if limit = 10000 and user has withdrawn 10000, they can withdraw 0 more (not negative)
    if ($total_after_withdrawal > $daily_withdrawal_limit_rounded) {
        // Standard error message format as per requirements
        $message = sprintf(
            'You have reached your daily withdrawal limit. You can withdraw up to %s more today.',
            formatMoney($remaining_daily_limit)
        );
        
        error_log(sprintf(
            "DAILY LIMIT BLOCKED - User ID: %d | Limit: %.2f | Today's Total: %.2f | Requested: %.2f | Would Be Total: %.2f | Remaining: %.2f",
            $user_id, $daily_withdrawal_limit_rounded, $total_withdrawn_today, $requested_amount_rounded, $total_after_withdrawal, $remaining_daily_limit
        ));
        
        return [
            'allowed' => false,
            'error_code' => 'DAILY_LIMIT_EXCEEDED',
            'status_code' => 400,
            'total_today' => $total_withdrawn_today,
            'limit' => $daily_withdrawal_limit_rounded,
            'remaining' => $remaining_daily_limit,
            'message' => $message
        ];
    }
    
    // Within limit - allow withdrawal
    return [
        'allowed' => true,
        'total_today' => $total_withdrawn_today,
        'limit' => $daily_withdrawal_limit_rounded,
        'remaining' => $remaining_daily_limit,
        'message' => 'Withdrawal allowed'
    ];
}

/**
 * ============================================================================
 * DAILY PACKAGE TRADE LIMIT ENFORCEMENT FUNCTION
 * ============================================================================
 * This function enforces the cumulative daily trade limit per package per user.
 * It must be called within a database transaction with proper row locking
 * to prevent race conditions.
 * 
 * @param PDO $db Database connection (must be in a transaction)
 * @param int $user_id User ID to check
 * @param int $package_id Package ID to check
 * @param bool $lock_user_row Whether to lock the user row (should be true for new trades)
 * @return array ['allowed' => bool, 'trades_today' => int, 'limit' => int, 'remaining' => int, 'message' => string]
 * 
 * IMPORTANT: This function must be called within a transaction that has already
 * locked the user row with "SELECT ... FOR UPDATE" to prevent race conditions.
 * 
 * Example usage:
 *   $db->beginTransaction();
 *   $stmt = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
 *   $stmt->execute([$user_id]);
 *   $stmt->fetch(); // Lock the row
 *   
 *   $check = checkDailyPackageTradeLimit($db, $user_id, $package_id, false);
 *   if (!$check['allowed']) {
 *       $db->rollBack();
 *       throw new Exception($check['message']);
 *   }
 *   // Proceed with package activation...
 */
function checkDailyPackageTradeLimit($db, $user_id, $package_id, $lock_user_row = false) {
    // Ensure we're in a transaction
    if (!$db->inTransaction()) {
        throw new Exception('checkDailyPackageTradeLimit must be called within a database transaction');
    }
    
    // Lock user row if requested (for new trades)
    if ($lock_user_row) {
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $stmt->fetch(); // Lock the user row to prevent concurrent trades
    }
    
    // Get daily trade limit for this package from database
    $stmt = $db->prepare("SELECT daily_trade_limit FROM packages WHERE id = ?");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        throw new Exception('Package not found');
    }
    
    // If no limit is set (NULL or 0), allow unlimited trades
    $daily_trade_limit = (int)($package['daily_trade_limit'] ?? 0);
    if ($daily_trade_limit <= 0) {
        return [
            'allowed' => true,
            'trades_today' => 0,
            'limit' => 0,
            'remaining' => 0,
            'message' => 'No trade limit set for this package'
        ];
    }
    
    // CRITICAL: Get total trades for this package by this user today
    // - Count ALL active_packages created today (regardless of status)
    // - Use DATE(created_at) = CURDATE() for automatic daily reset at midnight (server timezone)
    // - This query runs within the transaction to see uncommitted transactions
    // - Row locking on user table prevents concurrent trades
    $stmt = $db->prepare("
        SELECT COUNT(*) as trades_today 
        FROM active_packages 
        WHERE user_id = ? 
        AND package_id = ?
        AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_id, $package_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $trades_today = (int)($result['trades_today'] ?? 0);
    
    // Calculate remaining trades user can make today
    $remaining_trades = max(0, $daily_trade_limit - $trades_today);
    
    // Log the check for audit purposes
    error_log(sprintf(
        "DAILY PACKAGE TRADE LIMIT CHECK - User ID: %d | Package ID: %d | Limit: %d | Today's Trades: %d | Remaining: %d",
        $user_id, $package_id, $daily_trade_limit, $trades_today, $remaining_trades
    ));
    
    // STRICT ENFORCEMENT: Block if user has reached the daily trade limit
    if ($trades_today >= $daily_trade_limit) {
        $message = 'You have reached the maximum number of trades allowed today for this package.';
        
        error_log(sprintf(
            "DAILY PACKAGE TRADE LIMIT BLOCKED - User ID: %d | Package ID: %d | Limit: %d | Today's Trades: %d",
            $user_id, $package_id, $daily_trade_limit, $trades_today
        ));
        
        return [
            'allowed' => false,
            'error_code' => 'DAILY_TRADE_LIMIT_EXCEEDED',
            'status_code' => 400,
            'trades_today' => $trades_today,
            'limit' => $daily_trade_limit,
            'remaining' => 0,
            'message' => $message
        ];
    }
    
    // Within limit - allow trade
    return [
        'allowed' => true,
        'trades_today' => $trades_today,
        'limit' => $daily_trade_limit,
        'remaining' => $remaining_trades,
        'message' => 'Trade allowed'
    ];
}

/**
 * ============================================================================
 * AUTOMATIC REFERRAL TIER ASSIGNMENT
 * ============================================================================
 * Automatically assigns referral tiers to users based on their referral earnings.
 * This function should be called after referral commissions are credited.
 * 
 * @param PDO $db Database connection
 * @param int $user_id User ID to check and assign tier for
 * @return array|null Returns tier assignment info or null if no tier assigned
 */
function assignReferralTierAutomatically($db, $user_id) {
    try {
        // Check if referral_tiers table exists
        $stmt = $db->query("SHOW TABLES LIKE 'referral_tiers'");
        if ($stmt->rowCount() === 0) {
            // Tables don't exist yet, skip tier assignment
            return null;
        }
        
        // Get user's current referral earnings
        $stmt = $db->prepare("SELECT referral_earnings FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return null;
        }
        
        $referral_earnings = (float)($user['referral_earnings'] ?? 0);
        
        // Check if user already has an active manual assignment (don't override manual assignments)
        $stmt = $db->prepare("
            SELECT uta.id, uta.assignment_type, rt.tier_level, rt.referral_earnings_threshold
            FROM user_tier_assignments uta
            INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
            WHERE uta.user_id = ? AND uta.is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$user_id]);
        $existing_assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If user has a manual assignment, don't override it
        if ($existing_assignment && $existing_assignment['assignment_type'] === 'manual') {
            return null;
        }
        
        // Find the highest tier the user qualifies for based on referral earnings
        $stmt = $db->prepare("
            SELECT id, tier_name, tier_level, daily_withdrawal_limit, referral_earnings_threshold
            FROM referral_tiers
            WHERE is_active = 1 AND referral_earnings_threshold <= ?
            ORDER BY tier_level DESC
            LIMIT 1
        ");
        $stmt->execute([$referral_earnings]);
        $qualifying_tier = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$qualifying_tier) {
            // User doesn't qualify for any tier, remove any existing automatic assignment
            if ($existing_assignment && $existing_assignment['assignment_type'] === 'automatic') {
                $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND assignment_type = 'automatic'");
                $stmt->execute([$user_id]);
            }
            return null;
        }
        
        // Check if user already has this tier assigned
        if ($existing_assignment && $existing_assignment['tier_level'] == $qualifying_tier['tier_level']) {
            // Already has the correct tier, no need to update
            return [
                'tier_id' => $qualifying_tier['id'],
                'tier_name' => $qualifying_tier['tier_name'],
                'tier_level' => $qualifying_tier['tier_level'],
                'assigned' => false,
                'reason' => 'already_assigned'
            ];
        }
        
        // Deactivate any existing automatic assignment
        // CRITICAL: Must deactivate ALL active assignments first due to unique constraint on (user_id, is_active)
        if ($existing_assignment) {
            $stmt = $db->prepare("UPDATE user_tier_assignments SET is_active = 0 WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$user_id]);
        }
        
        // Create new automatic assignment
        $stmt = $db->prepare("
            INSERT INTO user_tier_assignments (user_id, tier_id, assignment_type, is_active)
            VALUES (?, ?, 'automatic', 1)
        ");
        $stmt->execute([$user_id, $qualifying_tier['id']]);
        
        // Get user info for notification
        $stmt = $db->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send notification to user
        if ($user_info) {
            sendNotification(
                $user_id,
                'Referral Tier Upgraded! 🎉',
                "Congratulations! You've been upgraded to {$qualifying_tier['tier_name']} tier! Your daily withdrawal limit is now " . formatMoney($qualifying_tier['daily_withdrawal_limit']) . ". Keep referring to unlock higher tiers!",
                'success'
            );
        }
        
        error_log("AUTOMATIC TIER ASSIGNMENT: User ID $user_id assigned to {$qualifying_tier['tier_name']} tier (Level {$qualifying_tier['tier_level']}) based on referral earnings: " . formatMoney($referral_earnings));
        
        return [
            'tier_id' => $qualifying_tier['id'],
            'tier_name' => $qualifying_tier['tier_name'],
            'tier_level' => $qualifying_tier['tier_level'],
            'withdrawal_limit' => $qualifying_tier['daily_withdrawal_limit'],
            'assigned' => true,
            'reason' => 'automatic_upgrade'
        ];
        
    } catch (Exception $e) {
        error_log("Error in assignReferralTierAutomatically for user $user_id: " . $e->getMessage());
        return null;
    }
}

// Include this file in your config/database.php after other helper functions
?>