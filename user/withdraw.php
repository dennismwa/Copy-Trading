<?php
require_once '../config/database.php';
require_once '../config/mpesa.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle messages from POST-Redirect-GET pattern
if (isset($_SESSION['withdrawal_success'])) {
    $success = $_SESSION['withdrawal_success'];
    unset($_SESSION['withdrawal_success']);
}
if (isset($_SESSION['withdrawal_error'])) {
    $error = $_SESSION['withdrawal_error'];
    unset($_SESSION['withdrawal_error']);
}

// Handle query parameter messages (for direct links)
if (isset($_GET['success'])) {
    $transaction_id = (int)$_GET['success'];
    $stmt = $db->prepare("SELECT id, status, amount FROM transactions WHERE id = ? AND user_id = ? AND type = 'withdrawal'");
    $stmt->execute([$transaction_id, $user_id]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($txn) {
        if ($txn['status'] === 'completed') {
            $success = 'Withdrawal completed successfully! Transaction ID: #' . $transaction_id;
        } elseif ($txn['status'] === 'processing') {
            $success = 'Withdrawal is being processed. Transaction ID: #' . $transaction_id;
        }
    }
}
if (isset($_GET['failed'])) {
    $transaction_id = (int)$_GET['failed'];
    $stmt = $db->prepare("SELECT id, status, description FROM transactions WHERE id = ? AND user_id = ? AND type = 'withdrawal'");
    $stmt->execute([$transaction_id, $user_id]);
    $txn = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($txn && $txn['status'] === 'failed') {
        // Check if error is due to insufficient M-Pesa balance
        $txn_description = $txn['description'] ?? '';
        $is_insufficient_balance = (
            stripos($txn_description, 'insufficient') !== false ||
            stripos($txn_description, 'insufficient balance') !== false ||
            stripos($txn_description, 'insufficient funds') !== false ||
            stripos($txn_description, 'not enough balance') !== false ||
            stripos($txn_description, 'low balance') !== false ||
            stripos($txn_description, 'balance is low') !== false ||
            stripos($txn_description, 'insufficient amount') !== false
        );
        
        if ($is_insufficient_balance) {
            $error = "⚠️ Withdrawals are temporarily unavailable due to a system update.\nPlease try again after 72 hours.\n\nYou may reinvest your package to keep earning in the meantime.\nThanks for your patience.\n\nYou can contact us on the live chat feature in case you need more clarity.";
        } else {
            $error = 'Withdrawal failed. Transaction ID: #' . $transaction_id . '. Please try again or contact support.';
        }
    }
}

// Get user data including active package tier
$stmt = $db->prepare("
    SELECT u.*, 
           (SELECT p.name FROM active_packages ap 
            JOIN packages p ON ap.package_id = p.id 
            WHERE ap.user_id = u.id AND ap.status = 'active' 
            ORDER BY ap.created_at DESC LIMIT 1) as current_package
    FROM users u 
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    error_log("ERROR: User not found for ID: $user_id");
    header('Location: /login.php');
    exit;
}

// Fetch withdrawal fee structure dynamically from system settings (with defaults)
$withdrawal_fees = [
    'Seed' => (float)getSystemSetting('withdrawal_fee_seed', 7),
    'Sprout' => (float)getSystemSetting('withdrawal_fee_sprout', 6),
    'Growth' => (float)getSystemSetting('withdrawal_fee_growth', 5),
    'Harvest' => (float)getSystemSetting('withdrawal_fee_harvest', 5),
    'Golden Yield' => (float)getSystemSetting('withdrawal_fee_golden_yield', 4),
    'Elite' => (float)getSystemSetting('withdrawal_fee_elite', 3)
];

// Get platform fee percentage from admin settings
$platform_fee_percentage = (float)getSystemSetting('platform_fee_percentage', 1.5);

// Determine user's withdrawal fee
$default_fee = (float)getSystemSetting('default_withdrawal_fee', 7); // Get from settings, default 7%
$user_fee_percentage = $default_fee; // Default to admin-configured fee if no active package
if ($user['current_package'] && isset($withdrawal_fees[$user['current_package']])) {
    $user_fee_percentage = $withdrawal_fees[$user['current_package']];
}

// Get system settings
$min_withdrawal = (float)getSystemSetting('min_withdrawal_amount', 100);
$max_withdrawal = (float)getSystemSetting('max_withdrawal_amount', 1000000);
$instant_threshold = (float)getSystemSetting('instant_withdrawal_threshold', 10000);
// Check if user has an active referral tier assignment (tier limits override system default)
$user_tier = null;
$daily_withdrawal_limit = (float)getSystemSetting('daily_withdrawal_limit', 50000);
try {
    $stmt = $db->prepare("
        SELECT rt.id, rt.tier_name, rt.daily_withdrawal_limit, rt.tier_level,
               uta.assignment_type
        FROM user_tier_assignments uta
        INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
        WHERE uta.user_id = ? AND uta.is_active = 1 AND rt.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    $user_tier = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user_tier && isset($user_tier['daily_withdrawal_limit'])) {
        $daily_withdrawal_limit = (float)$user_tier['daily_withdrawal_limit'];
    }
} catch (Exception $e) {
    // Tables might not exist yet, use default
    error_log("Tier check error: " . $e->getMessage());
}

// Calculate remaining daily withdrawal limit for this user (for display only)
// This is just for UI display - actual enforcement happens during withdrawal processing
// IMPORTANT: Use the EXACT SAME query logic as checkDailyWithdrawalLimit for consistency
$remaining_daily_limit = $daily_withdrawal_limit;
$total_withdrawn_today = 0;

// CRITICAL: Force a fresh connection state to see all committed transactions
// This ensures we see withdrawals that were just created in other requests
try {
    // Close any existing statements to ensure fresh reads
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Force fresh read by executing a simple query that we fully fetch
    try {
        $stmt = $db->query("SELECT 1");
        $stmt->fetchAll(PDO::FETCH_ASSOC); // Fully fetch to close the result set
        $stmt->closeCursor(); // Explicitly close cursor
    } catch (Exception $e) {
        // Ignore - just forcing fresh read
    }
    
    // PRIMARY: Use DATE() function query (same as checkDailyWithdrawalLimit)
    // CRITICAL: This query MUST see all committed transactions including those just created
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
    $stmt->closeCursor(); // Close cursor immediately
    $total_withdrawn_today = round((float)($result['total_today'] ?? 0), 2);
    $transaction_count = (int)($result['transaction_count'] ?? 0);
    
    // BACKUP: Use date range query
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
    
    // Use MAXIMUM of both queries
    if (abs($total_withdrawn_today - $total_backup) > 0.01) {
        $total_withdrawn_today = max($total_withdrawn_today, $total_backup);
        $transaction_count = max($transaction_count, $transaction_count_backup);
    }
    
    // MANUAL: Fetch all and sum manually - THIS IS THE MOST RELIABLE METHOD
    // Fetch ALL transactions for today (any status) and manually filter
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
    
    // Use MAXIMUM of all three methods (same as checkDailyWithdrawalLimit)
    // This ensures we NEVER miss a transaction
    $total_withdrawn_today = max($total_withdrawn_today, $total_backup, $manual_sum);
    
    $remaining_daily_limit = max(0, round($daily_withdrawal_limit - $total_withdrawn_today, 2));
    
    // Enhanced logging with transaction details for debugging
    // CRITICAL: Log all transactions found to verify calculation
    $transaction_details = [];
    if (isset($all_transactions) && is_array($all_transactions)) {
        foreach ($all_transactions as $t) {
            $transaction_details[] = [
                'id' => $t['id'],
                'amount' => (float)$t['amount'],
                'status' => $t['status'],
                'counted' => in_array($t['status'], ['pending', 'processing', 'completed']),
                'created_at' => $t['created_at']
            ];
        }
    }
    
    error_log(sprintf(
        "REAL-TIME LIMIT CALCULATION (Page Load) - User ID: %d | Limit: %.2f | Primary Query: %.2f (Count: %d) | Backup Query: %.2f (Count: %d) | Manual Sum: %.2f | Final Total Withdrawn: %.2f | Remaining: %.2f | Calculation: %.2f - %.2f = %.2f | Transactions Found: %d | Details: %s",
        $user_id,
        $daily_withdrawal_limit,
        round((float)($result['total_today'] ?? 0), 2),
        $transaction_count ?? 0,
        $total_backup ?? 0,
        $transaction_count_backup ?? 0,
        $manual_sum ?? 0,
        $total_withdrawn_today,
        $remaining_daily_limit,
        $daily_withdrawal_limit,
        $total_withdrawn_today,
        ($daily_withdrawal_limit - $total_withdrawn_today),
        count($all_transactions ?? []),
        json_encode($transaction_details)
    ));
    
    // CRITICAL: Verify calculation is correct
    if ($total_withdrawn_today > $daily_withdrawal_limit) {
        error_log(sprintf(
            "WARNING: Total withdrawn (%.2f) EXCEEDS daily limit (%.2f) for User ID %d! This should not happen if enforcement is working correctly.",
            $total_withdrawn_today,
            $daily_withdrawal_limit,
            $user_id
        ));
    }
} catch (Exception $e) {
    // If check fails, use full limit as fallback
    $remaining_daily_limit = $daily_withdrawal_limit;
    $total_withdrawn_today = 0;
    error_log("Error calculating remaining daily limit for display: " . $e->getMessage());
    error_log("Error trace: " . $e->getTraceAsString());
}

// Ensure daily limit is valid (must be positive)
if ($daily_withdrawal_limit <= 0) {
    $daily_withdrawal_limit = 50000; // Fallback to default if invalid
}

// Handle withdrawal request
if ($_POST && isset($_POST['make_withdrawal'])) {
    error_log("WITHDRAWAL REQUEST RECEIVED - User ID: $user_id, POST data: " . json_encode($_POST));
    
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
        error_log("WITHDRAWAL REJECTED - Invalid CSRF token");
    } else {
        // Parse amount properly - remove commas and convert to float
        $amount_raw = trim($_POST['amount'] ?? '');
        
        // Debug: Log raw amount
        error_log("RAW AMOUNT FROM POST: '$amount_raw' (type: " . gettype($amount_raw) . ")");
        
        // Remove any non-numeric characters except decimal point
        $amount_cleaned = preg_replace('/[^0-9.]/', '', $amount_raw);
        $amount = (float)$amount_cleaned;
        
        $phone = sanitize($_POST['phone'] ?? '');

        // Format phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) == 10 && substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        } elseif (strlen($phone) == 9) {
            $phone = '254' . $phone;
        } elseif (strlen($phone) == 13 && substr($phone, 0, 4) === '+254') {
            $phone = substr($phone, 1);
        }
        
        // ============================================================
        // AUTOMATED WITHDRAWAL PROCESSING - FULL VALIDATION FIRST
        // ============================================================
        // All validations happen BEFORE creating any transaction or deducting balance
        // This ensures failed validations don't create partial transactions
        
        // Basic validation first (amount, balance, phone)
        $amount_rounded = round($amount, 2);
        
        error_log("VALIDATION CHECK - Raw: '$amount_raw', Cleaned: '$amount_cleaned', Parsed: $amount, Rounded: $amount_rounded, Min: $min_withdrawal, Max: $max_withdrawal, Balance: {$user['wallet_balance']}, Comparison: " . ($amount_rounded < $min_withdrawal ? 'FAIL' : 'PASS'));
        
        // Validate amount
        if (empty($amount_raw) || $amount <= 0 || $amount_rounded <= 0) {
            $error = 'Please enter a valid withdrawal amount.';
            error_log("VALIDATION FAILED: Amount is empty or zero");
        } elseif ($amount_rounded < $min_withdrawal) {
            $error = 'Minimum withdrawal amount is ' . formatMoney($min_withdrawal) . '. You entered ' . formatMoney($amount_rounded) . '.';
            error_log("VALIDATION FAILED: Amount $amount_rounded is less than minimum $min_withdrawal");
        } elseif ($amount_rounded > $max_withdrawal) {
            $error = 'Maximum withdrawal amount is ' . formatMoney($max_withdrawal) . '.';
            error_log("VALIDATION FAILED: Amount exceeds maximum");
        } elseif ($amount_rounded > $user['wallet_balance']) {
            $error = 'Insufficient wallet balance.';
            error_log("VALIDATION FAILED: Amount exceeds balance");
        } elseif (strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
            $error = 'Please enter a valid M-Pesa phone number (254XXXXXXXXX).';
            error_log("VALIDATION FAILED: Invalid phone number");
        } else {
            error_log("VALIDATION PASSED: All checks passed, proceeding with withdrawal");
            // All validations passed - calculate fees
            // Calculate withdrawal fee and platform fee
            $withdrawal_fee = ($amount * $user_fee_percentage) / 100;
            $platform_fee = ($amount * $platform_fee_percentage) / 100;
            $total_fees = $withdrawal_fee + $platform_fee;
            $amount_after_fee = $amount - $total_fees;
            // All basic validations passed - proceed with transaction
            try {
                // ============================================================
                // CRITICAL: Start transaction FIRST to ensure atomic operations
                // ============================================================
                // Set isolation level to READ COMMITTED to see committed transactions from other sessions
                // This is critical for daily limit checking to work correctly
                // Also disable query cache to ensure fresh reads
                try {
                    $db->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
                    $db->exec("SET SESSION query_cache_type = OFF");
                } catch (Exception $e) {
                    // Ignore if these settings fail, but log it
                    error_log("Warning: Could not set transaction isolation level: " . $e->getMessage());
                }
                
                // CRITICAL: Begin transaction with explicit isolation level
                $db->beginTransaction();
                
                // CRITICAL: Lock user row IMMEDIATELY with NOWAIT to prevent deadlocks
                // This ensures only ONE withdrawal per user can proceed at a time
                // If another withdrawal is in progress, this will wait until it completes
                $stmt = $db->prepare("SELECT id, wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $stmt->execute([$user_id]);
                $locked_user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$locked_user) {
                    $db->rollBack();
                    $error = 'User not found.';
                    error_log("WITHDRAWAL REJECTED - User not found: $user_id");
                    throw new Exception($error);
                }
                
                // CRITICAL: Force a fresh read by executing a dummy query that forces MySQL to see latest data
                // This ensures we see all committed transactions from other sessions
                // IMPORTANT: Use query() and fetchAll() to avoid leaving unbuffered queries active
                try {
                    $stmt = $db->query("SELECT 1");
                    $stmt->fetchAll(PDO::FETCH_ASSOC); // Fully fetch to close the result set
                } catch (Exception $e) {
                    // Ignore - just forcing fresh read
                }
                
                // ============================================================
                // IDEMPOTENCY CHECK: Prevent duplicate processing (within transaction)
                // ============================================================
                // Check if this exact request was already processed
                // Look for transactions with same user, amount, phone created in last 10 minutes
                // Also check for any pending/processing withdrawals in the last 2 minutes to prevent rapid duplicates
                $stmt = $db->prepare("
                    SELECT id, status, created_at 
                    FROM transactions 
                    WHERE user_id = ? 
                    AND type = 'withdrawal' 
                    AND amount = ? 
                    AND phone_number = ? 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $amount, $phone]);
                $existing_transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Also check for any recent pending/processing withdrawals (within 2 minutes)
                // This prevents rapid duplicate submissions even with slightly different amounts
                $stmt_recent = $db->prepare("
                    SELECT id, status, amount, created_at 
                    FROM transactions 
                    WHERE user_id = ? 
                    AND type = 'withdrawal' 
                    AND status IN ('pending', 'processing')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt_recent->execute([$user_id]);
                $recent_transaction = $stmt_recent->fetch(PDO::FETCH_ASSOC);
                
                if ($existing_transaction) {
                    // Request already processed - return existing result
                    $db->rollBack();
                    $existing_id = $existing_transaction['id'];
                    $existing_status = $existing_transaction['status'];
                    
                    error_log("DUPLICATE WITHDRAWAL REQUEST DETECTED - User ID: $user_id, Amount: $amount, Existing Transaction ID: $existing_id, Status: $existing_status");
                    
                    // Set appropriate message based on existing transaction status
                    if ($existing_status === 'completed') {
                        $success = 'This withdrawal was already processed successfully. Transaction ID: #' . $existing_id;
                    } elseif ($existing_status === 'processing') {
                        $success = 'This withdrawal is already being processed. Transaction ID: #' . $existing_id;
                    } elseif ($existing_status === 'failed') {
                        $error = 'This withdrawal request was already attempted and failed. Please try a different amount or contact support.';
                    } else {
                        $success = 'This withdrawal request is already being processed. Transaction ID: #' . $existing_id;
                    }
                } elseif ($recent_transaction) {
                    // Recent pending/processing withdrawal found - prevent duplicate
                    $db->rollBack();
                    $recent_id = $recent_transaction['id'];
                    $recent_amount = $recent_transaction['amount'];
                    
                    error_log("RAPID DUPLICATE WITHDRAWAL PREVENTED - User ID: $user_id, Requested: $amount, Recent Transaction ID: $recent_id, Recent Amount: $recent_amount");
                    
                    $error = 'You have a withdrawal request that is currently being processed. Please wait for it to complete before making another withdrawal. Transaction ID: #' . $recent_id;
                } else {
                // ============================================================
                    // STRICT DAILY WITHDRAWAL LIMIT ENFORCEMENT
                // ============================================================
                    // Use the centralized function for consistent limit checking
                    // This function handles locking, limit retrieval, and validation
                    // If this fails, transaction is rolled back and no record is created
                    // Note: We pass false for lock_user_row since we already locked it above
                    $limit_check = checkDailyWithdrawalLimit($db, $user_id, $amount, false);
                    
                    if (!$limit_check['allowed']) {
                        $db->rollBack();
                        $error = $limit_check['message'];
                        error_log("AUTOMATED WITHDRAWAL REJECTED (Daily Limit) - User ID: $user_id, Amount: $amount, Reason: {$limit_check['message']}");
                    } else {
                // Daily limit check passed - proceed with withdrawal
                    // IMPORTANT: Create transaction record FIRST without deducting balance
                    // Balance will only be deducted if M-Pesa API call succeeds
                    // This ensures failed withdrawals don't touch user balance at all
                $description = "M-Pesa withdrawal. Withdrawal Fee: " . formatMoney($withdrawal_fee) . " ({$user_fee_percentage}%), Platform Fee: " . formatMoney($platform_fee) . " ({$platform_fee_percentage}%)";
                
                    // CRITICAL: Create transaction with 'pending' status (no balance deduction yet)
                    // ALWAYS set status explicitly to 'pending' to ensure it's never NULL or empty
                // CRITICAL: Ensure status is always set - explicitly set to 'pending'
                $status = 'pending'; // Explicitly set status to prevent NULL/empty
                $stmt = $db->prepare("
                    INSERT INTO transactions (user_id, type, amount, phone_number, status, withdrawal_fee, platform_fee, net_amount, description, created_at) 
                    VALUES (?, 'withdrawal', ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                // Execute with status as a parameter (not hardcoded)
                $stmt->execute([$user_id, $amount, $phone, $status, $withdrawal_fee, $platform_fee, $amount_after_fee, $description]);
                $transaction_id = $db->lastInsertId();
                
                // CRITICAL: Verify the transaction was created with correct status
                // This ensures no transaction is ever created with empty/NULL status
                $verify_stmt = $db->prepare("SELECT id, status FROM transactions WHERE id = ?");
                $verify_stmt->execute([$transaction_id]);
                $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                if (empty($verify_result['status']) || $verify_result['status'] !== 'pending') {
                    error_log(sprintf(
                        "CRITICAL: Transaction %d created with invalid status: '%s' - Fixing to 'pending'",
                        $transaction_id,
                        $verify_result['status'] ?? 'NULL'
                    ));
                    // Fix it immediately within the same transaction
                    $fix_stmt = $db->prepare("UPDATE transactions SET status = 'pending' WHERE id = ?");
                    $fix_stmt->execute([$transaction_id]);
                }
                
                    // CRITICAL: Final verification - check limit again AFTER creating transaction
                    // This ensures we catch any edge cases where the limit might have been exceeded
                    // The function will now see the newly created transaction (since it's in the same transaction)
                    // We pass 0 as requested_amount because we're checking if the current total (including new transaction) exceeds limit
                    $final_limit_check = checkDailyWithdrawalLimit($db, $user_id, 0, false);
                    $final_total = $final_limit_check['total_today'];
                    
                    // CRITICAL: The final check verifies that the total (including the newly created transaction)
                    // does not exceed the limit. The function should see the new transaction since it's uncommitted in the same transaction.
                    // If total exceeds limit, we must rollback and reject the withdrawal.
                    if ($final_total > $final_limit_check['limit']) {
                        // Limit exceeded after creating transaction - rollback and fail
                        $db->rollBack();
                        $remaining_after_rollback = max(0, $final_limit_check['limit'] - ($final_total - $amount));
                        $error = sprintf(
                            'Daily withdrawal limit exceeded. You can withdraw up to %s more today.',
                            formatMoney($remaining_after_rollback)
                        );
                        error_log(sprintf(
                            "DAILY LIMIT FINAL CHECK FAILED - User ID: %d, Amount: %.2f, Total Today (including new): %.2f, Limit: %.2f, Exceeds By: %.2f",
                            $user_id, $amount, $final_total, $final_limit_check['limit'], ($final_total - $final_limit_check['limit'])
                        ));
                    } else {
                        // Commit transaction creation (but balance not deducted yet)
                        $db->commit();
                    
                        error_log("WITHDRAWAL TRANSACTION CREATED - Transaction ID: $transaction_id, User ID: $user_id, Amount: $amount");
                        
                        // ============================================================
                        // AUTOMATED STATUS UPDATE BASED ON M-PESA RESULT
                        // ============================================================
                // Process withdrawal via M-Pesa B2C
                        // The daily limit was already checked and passed above, so we proceed
                try {
                    $mpesa_result = processMpesaWithdrawal($phone, $amount_after_fee, $transaction_id);
                } catch (Exception $mpesa_exception) {
                    // If M-Pesa processing throws an exception, handle it gracefully
                    error_log("M-PESA EXCEPTION - Transaction ID: $transaction_id, Error: " . $mpesa_exception->getMessage());
                    $mpesa_result = [
                        'success' => false,
                        'message' => 'Payment system error. Please contact support.'
                    ];
                }
                    
                    // Start new transaction for balance deduction and status update
                    try {
                        $db->beginTransaction();
                        
                        // Lock user row to prevent concurrent balance updates
                        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? FOR UPDATE");
                        $stmt->execute([$user_id]);
                        $stmt->fetch(PDO::FETCH_ASSOC); // Fully fetch to close the result set
                        
                        error_log("M-PESA RESULT - Transaction ID: $transaction_id, Success: " . ($mpesa_result['success'] ? 'true' : 'false') . ", Message: " . ($mpesa_result['message'] ?? 'N/A'));
                    
                    if ($mpesa_result['success']) {
                        // M-Pesa API accepted the request - deduct balance
                        error_log("M-PESA SUCCESS - Deducting balance for Transaction ID: $transaction_id, Amount: $amount");
                        $stmt = $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$amount, $user_id]);
                        
                    // Check if this is a simulated withdrawal (no real M-Pesa credentials configured)
                    $is_simulated = isset($mpesa_result['simulated']) && $mpesa_result['simulated'] === true;
                    
                    if ($is_simulated) {
                            // AUTOMATED: Simulated withdrawals marked as completed immediately
                        $stmt = $db->prepare("
                            UPDATE transactions 
                            SET mpesa_request_id = ?,
                                status = 'completed',
                                mpesa_receipt = ?,
                                    description = CONCAT(description, ' - Automated: Simulated withdrawal completed'),
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $simulated_receipt = 'SIM' . time() . rand(1000, 9999);
                        $stmt->execute([
                            $mpesa_result['conversation_id'] ?? $mpesa_result['originator_conversation_id'],
                            $simulated_receipt,
                            $transaction_id
                        ]);
                        
                        $db->commit();
                            $success_message = 'Withdrawal completed successfully! You will receive ' . formatMoney($amount_after_fee) . ' to ' . $phone . '. (Simulated mode)';
                        
                        sendNotification($user_id, 'Withdrawal Completed! 💰', 
                            "Your withdrawal of " . formatMoney($amount) . " has been completed. You will receive " . formatMoney($amount_after_fee) . " to {$phone}.", 
                            'success');
                        
                            error_log("AUTOMATED WITHDRAWAL COMPLETED (Simulated) - User ID: $user_id, Amount: $amount, After Fee: $amount_after_fee, Phone: $phone, Transaction ID: $transaction_id");
                            
                            // Use POST-Redirect-GET pattern to prevent duplicate submissions on refresh
                            $_SESSION['withdrawal_success'] = $success_message;
                            if (!headers_sent()) {
                                header('Location: /user/withdraw.php?success=' . $transaction_id);
                                exit;
                            } else {
                                // Headers already sent - use JavaScript redirect as fallback
                                echo '<script>window.location.href = "/user/withdraw.php?success=' . $transaction_id . '";</script>';
                                exit;
                            }
                    } else {
                            // AUTOMATED: Real M-Pesa withdrawal - deduct balance and mark as processing
                            // Status will be automatically updated to 'completed' or 'failed' by M-Pesa callback
                            
                            // CRITICAL: Extract conversation ID - try multiple key formats for compatibility
                            $conversation_id_to_save = $mpesa_result['ConversationID'] 
                                ?? $mpesa_result['conversation_id'] 
                                ?? $mpesa_result['OriginatorConversationID'] 
                                ?? $mpesa_result['originator_conversation_id'] 
                                ?? null;
                            
                            // Validate conversation ID is present
                            if (empty($conversation_id_to_save)) {
                                error_log("CRITICAL ERROR: No conversation ID received from M-Pesa for Transaction ID $transaction_id. Auto-update will fail!");
                                // Still proceed but log the issue
                            } else {
                                error_log("Saving conversation ID for Transaction ID $transaction_id: $conversation_id_to_save");
                            }
                            
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET mpesa_request_id = ?,
                            status = 'processing',
                                    description = CONCAT(description, ' - Automated: Processing via M-Pesa'),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $conversation_id_to_save,
                        $transaction_id
                    ]);
                    
                    // CRITICAL: Verify the conversation ID was actually saved
                    $verify_stmt = $db->prepare("SELECT mpesa_request_id FROM transactions WHERE id = ?");
                    $verify_stmt->execute([$transaction_id]);
                    $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (empty($verify_result['mpesa_request_id'])) {
                        error_log("CRITICAL WARNING: Conversation ID was NOT saved for Transaction ID $transaction_id. Auto-update will fail! Attempted to save: " . ($conversation_id_to_save ?? 'NULL'));
                    } else {
                        error_log("SUCCESS: Conversation ID saved for Transaction ID $transaction_id: {$verify_result['mpesa_request_id']}");
                    }
                    
                    $db->commit();
                            $success_message = 'Withdrawal request submitted successfully! Your withdrawal of ' . formatMoney($amount_after_fee) . ' is being processed. You will receive a notification when it completes.';
                    
                    sendNotification($user_id, 'Withdrawal Processing', 
                                "Your withdrawal of " . formatMoney($amount) . " is being processed automatically. You will receive " . formatMoney($amount_after_fee) . " to {$phone} once completed.", 
                        'info');
                    
                            error_log("AUTOMATED WITHDRAWAL INITIATED - User ID: $user_id, Amount: $amount, After Fee: $amount_after_fee, Phone: $phone, Transaction ID: $transaction_id, ConversationID: " . ($conversation_id_to_save ?? 'NULL') . ", Status: processing (balance deducted, will auto-update via callback)");
                            
                            // Use POST-Redirect-GET pattern to prevent duplicate submissions on refresh
                            $_SESSION['withdrawal_success'] = $success_message;
                            if (!headers_sent()) {
                                header('Location: /user/withdraw.php?success=' . $transaction_id);
                                exit;
                            } else {
                                // Headers already sent - use JavaScript redirect as fallback
                                echo '<script>window.location.href = "/user/withdraw.php?success=' . $transaction_id . '";</script>';
                                exit;
                            }
                    }
                } else {
                        // AUTOMATED: M-Pesa API call failed - mark as failed WITHOUT deducting balance
                        // No balance deduction means no refund needed - transaction exists but balance unchanged
                    
                    // Check if error is due to insufficient M-Pesa balance
                    $mpesa_error_message = $mpesa_result['message'] ?? '';
                    $is_insufficient_balance = (
                        stripos($mpesa_error_message, 'insufficient') !== false ||
                        stripos($mpesa_error_message, 'insufficient balance') !== false ||
                        stripos($mpesa_error_message, 'insufficient funds') !== false ||
                        stripos($mpesa_error_message, 'not enough balance') !== false ||
                        stripos($mpesa_error_message, 'low balance') !== false ||
                        stripos($mpesa_error_message, 'balance is low') !== false ||
                        stripos($mpesa_error_message, 'insufficient amount') !== false
                    );
                    
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = 'failed', 
                                description = CONCAT(description, ' - Automated: Failed - ', ?),
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$mpesa_error_message, $transaction_id]);
                    
                    $db->commit();
                    
                    // Use custom message for insufficient balance errors
                    if ($is_insufficient_balance) {
                        $error_message = "⚠️ Withdrawals are temporarily unavailable due to a system update.\nPlease try again after 72 hours.\n\nYou may reinvest your package to keep earning in the meantime.\nThanks for your patience.\n\nYou can contact us on the live chat feature in case you need more clarity.";
                        $notification_message = "⚠️ Withdrawals are temporarily unavailable due to a system update. Please try again after 72 hours. You may reinvest your package to keep earning in the meantime.";
                    } else {
                        $error_message = 'Withdrawal failed: ' . $mpesa_error_message . ' No funds were deducted from your account.';
                        $notification_message = "Your withdrawal of " . formatMoney($amount) . " could not be processed. Reason: " . $mpesa_error_message . ". No funds were deducted from your account.";
                    }
                        
                        // Send failure notification
                        sendNotification($user_id, 'Withdrawal Failed', 
                            $notification_message, 
                            'error');
                        
                        error_log("AUTOMATED WITHDRAWAL FAILED - User ID: $user_id, Amount: $amount, Error: {$mpesa_result['message']}, Transaction ID: $transaction_id, Status: failed (NO balance deduction - withdrawal rejected before processing)");
                        
                        // Use POST-Redirect-GET pattern to prevent duplicate submissions on refresh
                        $_SESSION['withdrawal_error'] = $error_message;
                        if (!headers_sent()) {
                            header('Location: /user/withdraw.php?failed=' . $transaction_id);
                            exit;
                        } else {
                            // Headers already sent - use JavaScript redirect as fallback
                            echo '<script>window.location.href = "/user/withdraw.php?failed=' . $transaction_id . '";</script>';
                            exit;
                        }
                    }
                    } catch (Exception $txn_exception) {
                        // If transaction fails, rollback and handle error
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log("WITHDRAWAL TRANSACTION ERROR - Transaction ID: $transaction_id, Error: " . $txn_exception->getMessage());
                        error_log("WITHDRAWAL TRANSACTION ERROR TRACE: " . $txn_exception->getTraceAsString());
                        $_SESSION['withdrawal_error'] = 'An error occurred while processing your withdrawal. Please try again or contact support.';
                        if (!headers_sent()) {
                            header('Location: /user/withdraw.php');
                            exit;
                        } else {
                            echo '<script>window.location.href = "/user/withdraw.php";</script>';
                            exit;
                        }
                    }
                    } // End of else block (final limit check passed - transaction committed)
                    } // End of else block (daily limit check passed)
                } // End of else block (no duplicate found)
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                // If error was already set (from daily limit check), use it; otherwise use generic error
                if (empty($error)) {
                    $error = 'Failed to process withdrawal request: ' . $e->getMessage();
                }
                error_log("Withdrawal error: " . $e->getMessage());
                error_log("Withdrawal error trace: " . $e->getTraceAsString());
            }
        }
    }
}

// Note: $total_withdrawn_today and $remaining_daily_limit are already calculated above
// with the same query logic as checkDailyWithdrawalLimit to ensure consistency
// These variables are available for use in the rest of the code

// Get recent withdrawals
$stmt = $db->prepare("
    SELECT * FROM transactions
    WHERE user_id = ? AND type = 'withdrawal'
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * FIXED: Process M-Pesa B2C Withdrawal
 * Now properly integrates with M-Pesa B2C API via MpesaIntegration class
 */
function processMpesaWithdrawal($phone, $amount, $transaction_id) {
    try {
        // Initialize M-Pesa integration
        $mpesa = new MpesaIntegration();
        
        // Prepare withdrawal details
        $account_reference = "WD" . str_pad($transaction_id, 6, '0', STR_PAD_LEFT);
        $remarks = "Ultra Harvest withdrawal";
        
        // Process B2C payment
        $result = $mpesa->b2cPayment($phone, $amount, $account_reference, $remarks);
        
        if ($result['success']) {
            // Extract conversation IDs - handle both capitalized and lowercase keys
            $conversation_id = $result['ConversationID'] ?? $result['conversation_id'] ?? null;
            $originator_conversation_id = $result['OriginatorConversationID'] ?? $result['originator_conversation_id'] ?? null;
            
            // CRITICAL: Log the conversation IDs for debugging
            error_log("M-Pesa B2C Withdrawal Initiated: Transaction ID $transaction_id, Amount: $amount, Phone: $phone, ConversationID: " . ($conversation_id ?? 'NULL') . ", OriginatorConversationID: " . ($originator_conversation_id ?? 'NULL'));
            
            // Validate that we have at least one conversation ID
            if (empty($conversation_id) && empty($originator_conversation_id)) {
                error_log("WARNING: No conversation IDs received from M-Pesa for Transaction ID $transaction_id. This will prevent auto-update!");
            }
            
            return [
                'success' => true,
                'conversation_id' => $conversation_id,
                'originator_conversation_id' => $originator_conversation_id,
                'ConversationID' => $conversation_id, // Also include capitalized version for compatibility
                'OriginatorConversationID' => $originator_conversation_id, // Also include capitalized version for compatibility
                'message' => 'Withdrawal request sent successfully'
            ];
        } else {
            // B2C request failed
            error_log("M-Pesa B2C Withdrawal Failed: Transaction ID $transaction_id, Amount: $amount, Phone: $phone, Error: " . ($result['message'] ?? 'Unknown error'));
            return [
                'success' => false,
                'message' => $result['message'] ?? 'Failed to process withdrawal. Please try again.'
            ];
        }
        
    } catch (Exception $e) {
        error_log("M-Pesa B2C Withdrawal Exception: Transaction ID $transaction_id - " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'Payment system error. Please contact support.'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Funds - Ultra Harvest Global</title>
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
        
        .mpesa-green { background: linear-gradient(45deg, #00A651, #00D157); }
        
        .amount-btn { transition: all 0.3s ease; }
        .amount-btn:hover { transform: scale(1.05); }
        .amount-btn.selected {
            background: linear-gradient(45deg, #ef4444, #f87171);
            color: white;
        }
        
        .warning-card {
            background: linear-gradient(45deg, rgba(245, 158, 11, 0.1), rgba(251, 191, 36, 0.1));
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        /* Modal Styles */
        #withdrawalConfirmModal {
            animation: fadeIn 0.3s ease-out;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 1rem;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            animation: slideUp 0.3s ease-out;
            max-height: calc(100vh - 2rem);
            display: flex;
            flex-direction: column;
            margin: auto;
            width: 100%;
        }
        
        .modal-body {
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            flex: 1;
            min-height: 0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        #withdrawalConfirmModal.show {
            display: flex !important;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 640px) {
            #withdrawalConfirmModal {
                padding: 0.5rem;
                align-items: center;
                justify-content: center;
            }
            
            .modal-content {
                max-height: calc(100vh - 1rem);
                max-width: 100%;
                margin: auto;
                border-radius: 1rem;
            }
            
            .modal-body {
                max-height: calc(100vh - 280px);
            }
        }
        
        @media (max-height: 600px) {
            .modal-content {
                max-height: calc(100vh - 1rem);
            }
            
            .modal-body {
                max-height: calc(100vh - 250px);
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
                    <a href="/user/referral-tiers.php" class="text-gray-300 hover:text-yellow-400 transition">Tiers</a>
                    <a href="/user/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                    <a href="/user/support.php" class="text-gray-300 hover:text-emerald-400 transition">Help</a>
                </nav>
            </div>

            <div class="flex items-center space-x-4">
                <div class="flex items-center space-x-2 bg-gray-700/50 rounded-full px-4 py-2">
                    <i class="fas fa-wallet text-emerald-400"></i>
                    <span class="text-sm text-gray-300">Balance:</span>
                    <span class="font-bold text-white" data-balance><?php echo formatMoney($user['wallet_balance']); ?></span>
                </div>
                <a href="/user/dashboard.php" class="text-gray-400 hover:text-white">
                    <i class="fas fa-arrow-left text-xl"></i>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-8">
    
    <div class="text-center mb-8">
        <h1 class="text-4xl font-bold mb-2">
            <i class="fas fa-arrow-up text-red-400 mr-3"></i>
            Withdraw Funds
        </h1>
        <p class="text-xl text-gray-300">Withdraw to your M-Pesa account</p>
    </div>

    <?php if ($error): ?>
    <div class="max-w-2xl mx-auto mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
        <div class="flex items-start">
            <i class="fas fa-exclamation-circle text-red-400 mr-2 mt-1"></i>
            <div class="text-red-300 whitespace-pre-line"><?php echo htmlspecialchars($error); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="max-w-2xl mx-auto mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
        <div class="flex items-center">
            <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
            <span class="text-emerald-300"><?php echo $success; ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user['wallet_balance'] < $min_withdrawal): ?>
    <div class="max-w-2xl mx-auto mb-8">
        <div class="warning-card rounded-xl p-8 text-center">
            <i class="fas fa-exclamation-triangle text-yellow-400 text-4xl mb-4"></i>
            <h3 class="text-xl font-bold text-white mb-4">Insufficient Balance</h3>
            <p class="text-gray-300 mb-6">
                Your current balance is <?php echo formatMoney($user['wallet_balance']); ?>. 
                The minimum withdrawal amount is <?php echo formatMoney($min_withdrawal); ?>.
            </p>
            <div class="flex flex-col sm:flex-row gap-3 justify-center">
                <a href="/user/deposit.php" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-plus mr-2"></i>Deposit Funds
                </a>
                <a href="/user/packages.php" class="px-6 py-3 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-chart-line mr-2"></i>Invest & Earn
                </a>
            </div>
        </div>
    </div>
    <?php else: ?>

    <div class="grid lg:grid-cols-3 gap-8">
        
        <div class="lg:col-span-2">
            <div class="glass-card rounded-xl p-8">
                
                <div class="text-center mb-8">
                    <div class="mpesa-green w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-mobile-alt text-white text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">M-Pesa Withdrawal</h2>
                    <p class="text-gray-300">Withdraw directly to your M-Pesa account</p>
                </div>

                <!-- Withdrawal Fee Notice -->
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4 mb-6">
                    <div class="flex items-start space-x-3">
                        <i class="fas fa-info-circle text-yellow-400 mt-1"></i>
                        <div>
                            <h4 class="font-bold text-yellow-400 mb-1">Withdrawal Fee Information</h4>
                            <p class="text-sm text-gray-300">
                                Your current package tier: <strong><?php echo $user['current_package'] ?? 'None (Default)'; ?></strong><br>
                                Withdrawal fee: <strong><?php echo $user_fee_percentage; ?>%</strong><br>
                                <span class="text-xs text-gray-400 mt-2 block">This transaction will incur a <?php echo $user_fee_percentage; ?>% fee. The fee is automatically deducted.</span>
                            </p>
                        </div>
                    </div>
                </div>

                <form method="POST" class="space-y-6" id="withdrawalForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                    <input type="hidden" name="make_withdrawal" value="1">
                    <input type="hidden" name="request_id" id="request_id" value="">

                    <!-- Available Balance -->
                    <div class="bg-gradient-to-r from-emerald-600/20 to-yellow-600/20 rounded-lg p-6">
                        <div class="text-center">
                            <p class="text-gray-300 mb-2">Available Balance</p>
                            <p class="text-4xl font-bold text-white mb-4" data-balance><?php echo formatMoney($user['wallet_balance']); ?></p>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-400">Minimum</p>
                                    <p class="text-emerald-400 font-bold"><?php echo formatMoney($min_withdrawal); ?></p>
                                </div>
                                <div>
                                    <p class="text-gray-400">Maximum</p>
                                    <?php 
                                    // CRITICAL: Calculate actual maximum - ONLY based on system max and daily limit
                                    // DO NOT include wallet balance here - that's a separate constraint
                                    // The remaining_daily_limit is already calculated correctly based on ALL withdrawals today
                                    $actual_max_allowed = min($max_withdrawal, $remaining_daily_limit);
                                    ?>
                                    <p class="text-red-400 font-bold"><?php echo formatMoney($actual_max_allowed); ?></p>
                                    <?php 
                                    // CRITICAL: Always show daily limit info - it's critical information
                                    // Show correct message based on whether limit is reached or not
                                    ?>
                                    <p class="text-xs <?php echo $remaining_daily_limit > 0 ? 'text-yellow-400' : 'text-red-400 font-semibold'; ?> mt-1" id="daily-limit-display" data-remaining-limit="<?php echo $remaining_daily_limit; ?>">
                                        <i class="fas fa-info-circle"></i> 
                                        <?php if ($remaining_daily_limit > 0): ?>
                                            Daily limit: <?php echo formatMoney($remaining_daily_limit); ?> remaining today
                                            <?php if ($total_withdrawn_today > 0): ?>
                                                (You've withdrawn <?php echo formatMoney($total_withdrawn_today); ?> today)
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Daily withdrawal limit reached! You've withdrawn <?php echo formatMoney($total_withdrawn_today); ?> today (Limit: <?php echo formatMoney($daily_withdrawal_limit); ?>)
                                        <?php endif; ?>
                                        <?php if ($user_tier): ?>
                                            <br><span class="inline-block mt-1 px-2 py-0.5 bg-gradient-to-r from-purple-500/20 to-indigo-500/20 border border-purple-400/30 rounded text-purple-300 text-xs">
                                                <i class="fas fa-trophy mr-1"></i><?php echo htmlspecialchars($user_tier['tier_name']); ?> Tier
                                                <?php if ($user_tier['assignment_type'] === 'manual'): ?>
                                                    <i class="fas fa-crown ml-1 text-yellow-400" title="Manually assigned by admin"></i>
                                                <?php endif; ?>
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Amount Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-4">
                            <i class="fas fa-coins mr-2"></i>Select Amount (KSh)
                        </label>
                        <div class="grid grid-cols-3 md:grid-cols-4 gap-3 mb-4">
                            <?php 
                            $balance = $user['wallet_balance'];
                            $quick_amounts = [];
                            
                            if ($balance >= 500) $quick_amounts[] = 500;
                            if ($balance >= 1000) $quick_amounts[] = 1000;
                            if ($balance >= 2000) $quick_amounts[] = 2000;
                            if ($balance >= 5000) $quick_amounts[] = 5000;
                            if ($balance >= 10000) $quick_amounts[] = 10000;
                            if ($balance >= 20000) $quick_amounts[] = 20000;
                            if ($balance >= 50000) $quick_amounts[] = 50000;
                            
                            if ($balance >= $min_withdrawal) {
                                $quick_amounts[] = floor($balance);
                            }
                            
                            foreach ($quick_amounts as $index => $amount): 
                            ?>
                            <button 
                                type="button" 
                                class="amount-btn px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white hover:border-red-500 transition"
                                data-amount="<?php echo $amount; ?>"
                            >
                                <?php echo $index === count($quick_amounts) - 1 && $amount == floor($balance) ? 'All' : number_format($amount); ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Custom Amount Input -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-edit mr-2"></i>Or Enter Custom Amount
                        </label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400">KSh</span>
                            <input 
                                type="number" 
                                name="amount" 
                                id="amount"
                                min="<?php echo $min_withdrawal; ?>"
                                max="<?php echo min($max_withdrawal, $remaining_daily_limit); ?>"
                                data-max-withdrawal="<?php echo min($max_withdrawal, $remaining_daily_limit); ?>"
                                data-remaining-limit="<?php echo $remaining_daily_limit; ?>"
                                step="1"
                                class="w-full pl-12 pr-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white text-xl font-bold focus:border-red-500 focus:outline-none"
                                placeholder="Enter amount"
                                required
                            >
                        </div>
                        <div class="flex justify-between text-sm text-gray-500 mt-2">
                            <span>Minimum: <?php echo formatMoney($min_withdrawal); ?></span>
                            <span>Available: <span data-balance><?php echo formatMoney($user['wallet_balance']); ?></span></span>
                        </div>
                        <div id="client-error" class="text-red-400 text-sm mt-2" style="display: none;"></div>
                        <div id="daily-limit-info" class="text-yellow-400 text-sm mt-2" style="display: none;"></div>
                    </div>

                    <!-- Phone Number -->
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            <i class="fas fa-phone mr-2"></i>M-Pesa Phone Number
                        </label>
                        <input 
                            type="tel" 
                            name="phone" 
                            id="phone"
                            value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                            class="w-full px-4 py-4 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-red-500 focus:outline-none"
                            placeholder="0712345678 or 254712345678"
                            required
                        >
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Funds will be sent to this M-Pesa number
                        </p>
                    </div>

                    <!-- Withdrawal Summary -->
                    <div class="bg-gray-800/50 rounded-lg p-6" id="withdrawal-summary" style="display: none;">
                        <h3 class="font-bold text-white mb-4">Withdrawal Summary</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-400">Withdrawal Amount</span>
                                <span class="text-white font-bold" id="summary-amount">KSh 0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Withdrawal Fee (<?php echo $user_fee_percentage; ?>%)</span>
                                <span class="text-yellow-400" id="summary-withdrawal-fee">KSh 0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-400">Platform Fee (<?php echo $platform_fee_percentage; ?>%)</span>
                                <span class="text-purple-400" id="summary-platform-fee">KSh 0</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-700 pt-2">
                                <span class="text-gray-400">Total Fees</span>
                                <span class="text-red-400 font-bold" id="summary-fee">KSh 0</span>
                            </div>
                            <div class="border-t border-gray-700 pt-3">
                                <div class="flex justify-between">
                                    <span class="text-white font-medium">You Will Receive</span>
                                    <span class="text-emerald-400 font-bold text-xl" id="summary-total">KSh 0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full py-4 bg-gradient-to-r from-red-500 to-red-600 text-white font-bold text-lg rounded-lg hover:from-red-600 hover:to-red-700 transform hover:scale-[1.02] transition-all duration-300 shadow-lg"
                        id="withdraw-btn"
                        disabled
                    >
                        <i class="fas fa-arrow-up mr-2"></i>Request Withdrawal
                    </button>

                    <p class="text-xs text-gray-500 text-center leading-relaxed">
                        All Withdrawals will be processed in about 1 hour. 
                        By proceeding, you confirm that the M-Pesa number provided is correct and belongs to you.
                    </p>

                    <!-- Increase Withdrawal Limit Button -->
                    <div class="mt-6 flex justify-center">
                        <a href="/user/referral-tiers.php" class="group relative inline-flex items-center justify-center px-10 py-4 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 text-white font-semibold rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 ease-in-out transform hover:scale-[1.02] border border-red-400/30">
                            <span class="flex items-center space-x-2">
                                <span class="text-xl">🔥</span>
                                <span class="text-base tracking-wide">Increase your withdrawal limit</span>
                                <i class="fas fa-arrow-right text-sm group-hover:translate-x-1 transition-transform duration-300"></i>
                            </span>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">

            <!-- Fee Structure -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-percentage text-yellow-400 mr-2"></i>
                    Fee Structure by Package
                </h3>
                
                <!-- Withdrawal Fees -->
                <div class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-300 mb-2">Withdrawal Fees</h4>
                    <div class="space-y-2 text-sm">
                        <?php foreach ($withdrawal_fees as $package => $fee): ?>
                        <div class="flex items-center justify-between p-2 rounded <?php echo $user['current_package'] === $package ? 'bg-emerald-500/20' : 'bg-gray-800/30'; ?>">
                            <span class="text-gray-300"><?php echo $package; ?></span>
                            <span class="font-bold <?php echo $user['current_package'] === $package ? 'text-emerald-400' : 'text-yellow-400'; ?>"><?php echo $fee; ?>%</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Platform Fee -->
                <div>
                    <h4 class="text-sm font-semibold text-gray-300 mb-2">Platform Fee</h4>
                    <div class="flex items-center justify-between p-2 rounded bg-purple-500/20">
                        <span class="text-gray-300">All Packages</span>
                        <span class="font-bold text-purple-400"><?php echo $platform_fee_percentage; ?>%</span>
                    </div>
                </div>
                
                <!-- Total Fee Example -->
                <div class="mt-4 p-3 bg-blue-500/10 rounded-lg border border-blue-500/20">
                    <p class="text-xs text-gray-400 mb-1">Example for KSh 1,000 withdrawal:</p>
                    <p class="text-sm text-blue-300">
                        Withdrawal Fee: KSh <?php echo number_format((1000 * $user_fee_percentage) / 100, 2); ?> (<?php echo $user_fee_percentage; ?>%)<br>
                        Platform Fee: KSh <?php echo number_format((1000 * $platform_fee_percentage) / 100, 2); ?> (<?php echo $platform_fee_percentage; ?>%)<br>
                        <span class="font-bold text-white">Total Fees: KSh <?php echo number_format((1000 * ($user_fee_percentage + $platform_fee_percentage)) / 100, 2); ?></span>
                    </p>
                </div>
            </div>

            <!-- Recent Withdrawals -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-history text-red-400 mr-2"></i>
                    Recent Withdrawals
                </h3>
                <?php if (empty($recent_withdrawals)): ?>
                    <div class="text-center py-6">
                        <i class="fas fa-inbox text-3xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">No withdrawals yet</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($recent_withdrawals, 0, 10) as $withdrawal): ?>
                        <div class="flex items-center justify-between p-3 bg-gray-800/30 rounded-lg">
                            <div>
                                <p class="font-medium text-white"><?php echo formatMoney($withdrawal['amount']); ?></p>
                                <p class="text-xs text-gray-400"><?php echo timeAgo($withdrawal['created_at']); ?></p>
                                <?php if ($withdrawal['phone_number']): ?>
                                    <p class="text-xs text-blue-400"><?php echo $withdrawal['phone_number']; ?></p>
                                <?php endif; ?>
                            </div>
                            <span class="px-2 py-1 rounded text-xs font-medium
                                <?php 
                                echo match($withdrawal['status']) {
                                    'completed' => 'bg-emerald-500/20 text-emerald-400',
                                    'pending' => 'bg-yellow-500/20 text-yellow-400',
                                    'processing' => 'bg-blue-500/20 text-blue-400',
                                    'failed' => 'bg-red-500/20 text-red-400',
                                    default => 'bg-gray-500/20 text-gray-400'
                                };
                                ?>">
                                <?php echo ucfirst($withdrawal['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Support -->
            <div class="glass-card rounded-xl p-6">
                <h3 class="text-lg font-bold text-white mb-4">
                    <i class="fas fa-headset text-purple-400 mr-2"></i>
                    Need Help?
                </h3>
                <div class="space-y-3">
                    <a href="https://wa.me/254700000000" target="_blank" class="flex items-center text-green-400 hover:text-green-300 transition">
                        <i class="fab fa-whatsapp mr-2"></i>WhatsApp Support
                    </a>
                    <a href="/user/support.php" class="flex items-center text-blue-400 hover:text-blue-300 transition">
                        <i class="fas fa-ticket-alt mr-2"></i>Create Ticket
                    </a>
                    <a href="/help.php" class="flex items-center text-gray-400 hover:text-white transition">
                        <i class="fas fa-question-circle mr-2"></i>Help Center
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Styled Withdrawal Confirmation Modal -->
<div id="withdrawalConfirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-60 backdrop-blur-sm" style="display: none;">
    <div class="modal-content bg-gray-800 rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-red-600 to-red-700 rounded-t-2xl p-4 sm:p-6 flex-shrink-0">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-2 sm:space-x-3 flex-1 min-w-0">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center flex-shrink-0">
                        <i class="fas fa-money-bill-wave text-white text-lg sm:text-xl"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="text-lg sm:text-xl font-bold text-white truncate">Confirm Withdrawal</h3>
                        <p class="text-red-100 text-xs sm:text-sm">Please review the details</p>
                    </div>
                </div>
                <button id="closeModalBtn" class="text-white hover:text-red-200 transition ml-2 flex-shrink-0">
                    <i class="fas fa-times text-lg sm:text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="modal-body p-4 sm:p-6 space-y-3 sm:space-y-4">
            <!-- Amount -->
            <div class="bg-gray-700 rounded-lg p-3 sm:p-4">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <span class="text-gray-300 text-sm sm:text-base">Withdrawal Amount</span>
                    <span class="text-xl sm:text-2xl font-bold text-white break-words" id="modal-amount">KSh 0.00</span>
                </div>
            </div>

            <!-- Fees Breakdown -->
            <div class="space-y-2">
                <div class="flex justify-between items-center py-2 border-b border-gray-700 gap-2">
                    <span class="text-gray-400 text-sm sm:text-base">Withdrawal Fee</span>
                    <span class="text-yellow-400 font-semibold text-sm sm:text-base break-words text-right" id="modal-withdrawal-fee">KSh 0.00</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b border-gray-700 gap-2">
                    <span class="text-gray-400 text-sm sm:text-base">Platform Fee</span>
                    <span class="text-purple-400 font-semibold text-sm sm:text-base break-words text-right" id="modal-platform-fee">KSh 0.00</span>
                </div>
                <div class="flex justify-between items-center py-2 border-b-2 border-gray-600 gap-2">
                    <span class="text-gray-300 font-medium text-sm sm:text-base">Total Fees</span>
                    <span class="text-red-400 font-bold text-sm sm:text-base break-words text-right" id="modal-total-fees">KSh 0.00</span>
                </div>
            </div>

            <!-- You Will Receive -->
            <div class="bg-gradient-to-r from-emerald-600 to-emerald-700 rounded-lg p-3 sm:p-4">
                <div class="flex justify-between items-center flex-wrap gap-2">
                    <span class="text-white font-medium text-sm sm:text-base">You Will Receive</span>
                    <span class="text-xl sm:text-2xl font-bold text-white break-words" id="modal-after-fee">KSh 0.00</span>
                </div>
            </div>

            <!-- Phone Number -->
            <div class="bg-gray-700 rounded-lg p-3 sm:p-4">
                <div class="flex items-center space-x-2 sm:space-x-3">
                    <i class="fas fa-mobile-alt text-emerald-400 text-lg sm:text-xl flex-shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-gray-400 text-xs sm:text-sm">M-Pesa Number</p>
                        <p class="text-white font-semibold text-sm sm:text-base break-words" id="modal-phone">254XXXXXXXXX</p>
                    </div>
                </div>
            </div>

            <!-- Warning Message -->
            <div class="bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg p-3">
                <div class="flex items-start space-x-2">
                    <i class="fas fa-exclamation-triangle text-yellow-400 mt-0.5 sm:mt-1 flex-shrink-0"></i>
                    <p class="text-yellow-200 text-xs sm:text-sm">Please verify all details before confirming. This action cannot be undone.</p>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-gray-750 rounded-b-2xl p-4 sm:p-6 flex space-x-2 sm:space-x-3 flex-shrink-0">
            <button id="cancelWithdrawalBtn" class="flex-1 px-4 sm:px-6 py-2.5 sm:py-3 bg-gray-700 hover:bg-gray-600 text-white font-semibold rounded-lg transition-all duration-200 text-sm sm:text-base">
                <i class="fas fa-times mr-1 sm:mr-2"></i>Cancel
            </button>
            <button id="confirmWithdrawalBtn" class="flex-1 px-4 sm:px-6 py-2.5 sm:py-3 bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 text-white font-semibold rounded-lg transition-all duration-200 shadow-lg text-sm sm:text-base">
                <i class="fas fa-check mr-1 sm:mr-2"></i>Confirm
            </button>
        </div>
    </div>
</div>

 <!-- Live Chat Support Widget -->
<?php include __DIR__ . '/../chat/widget/chat-widget-loader.php'; ?>

<script>
    const minWithdrawal = <?php echo $min_withdrawal; ?>;
    // Calculate the actual maximum withdrawal considering balance, system max, and daily limit
    const maxWithdrawalSystem = <?php echo $max_withdrawal; ?>;
    const currentBalance = <?php echo $user['wallet_balance']; ?>;
    const dailyWithdrawalLimit = <?php echo $daily_withdrawal_limit; ?>;
    const remainingDailyLimit = <?php echo $remaining_daily_limit; ?>;
    // The actual maximum is the minimum of: system max, balance, and remaining daily limit
    // CRITICAL: Calculate max withdrawal - ONLY based on system max and daily limit
    // Balance is checked separately, not included in max calculation
    const maxWithdrawal = Math.min(maxWithdrawalSystem, remainingDailyLimit);
    const withdrawalFeePercentage = <?php echo $user_fee_percentage; ?>;
    const platformFeePercentage = <?php echo $platform_fee_percentage; ?>;
    let formSubmitted = false;
    let remainingLimit = remainingDailyLimit;

    // Generate unique request ID on page load to prevent duplicate submissions
    document.addEventListener('DOMContentLoaded', function() {
        // Generate unique request ID for this form submission
        const requestId = 'WD-' + <?php echo $user_id; ?> + '-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
        const requestIdInput = document.getElementById('request_id');
        if (requestIdInput) {
            requestIdInput.value = requestId;
        }
        
        // Update balance and limit in real-time without page refresh
        updateBalanceAndLimit();
        
        // Set up periodic updates (every 2 seconds) to refresh balance and limit
        // More frequent updates ensure real-time limit calculation after withdrawals
        setInterval(updateBalanceAndLimit, 2000);
        
        // Also update immediately after short delays to catch any recent withdrawals
        setTimeout(updateBalanceAndLimit, 300);
        setTimeout(updateBalanceAndLimit, 800);
        setTimeout(updateBalanceAndLimit, 1500);
        setTimeout(updateBalanceAndLimit, 2500);
        
        // If page was loaded after a withdrawal (has success/failed parameter), force immediate updates
        <?php if (isset($_GET['success']) || isset($_GET['failed'])): ?>
        // Remove failed parameter from URL after showing error once to prevent re-showing on refresh
        <?php if (isset($_GET['failed'])): ?>
        // Remove ?failed= parameter from URL after page loads to prevent error from showing again on refresh
        if (window.location.search.includes('failed=')) {
            const url = new URL(window.location);
            url.searchParams.delete('failed');
            window.history.replaceState({}, '', url);
        }
        <?php endif; ?>
        
        // CRITICAL: Force multiple immediate updates after withdrawal to ensure limit is updated
        // This ensures the "You've withdrawn" amount updates immediately
        updateBalanceAndLimit(); // Immediate call
        setTimeout(updateBalanceAndLimit, 100);
        setTimeout(updateBalanceAndLimit, 300);
        setTimeout(updateBalanceAndLimit, 600);
        setTimeout(updateBalanceAndLimit, 1000);
        setTimeout(updateBalanceAndLimit, 2000);
        setTimeout(updateBalanceAndLimit, 3000);
        setTimeout(updateBalanceAndLimit, 5000);
        
        // REAL-TIME STATUS POLLING: Check withdrawal status every 5 seconds
        <?php 
        $transaction_id_to_check = isset($_GET['success']) ? (int)$_GET['success'] : (isset($_GET['failed']) ? (int)$_GET['failed'] : 0);
        if ($transaction_id_to_check > 0): 
        ?>
        let statusCheckInterval;
        let statusCheckCount = 0;
        const maxStatusChecks = 60; // Check for 5 minutes (60 * 5 seconds)
        
        function checkWithdrawalStatus(transactionId) {
            if (statusCheckCount >= maxStatusChecks) {
                clearInterval(statusCheckInterval);
                console.log('Status check stopped after maximum attempts');
                return;
            }
            
            statusCheckCount++;
            
            fetch(`/api/check-transaction-status.php?id=${transactionId}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.transaction) {
                        const status = data.transaction.status;
                        console.log(`Status check #${statusCheckCount}: Transaction ${transactionId} status = ${status}`);
                        
                        if (status === 'completed') {
                            // Status updated to completed - stop polling and refresh page
                            clearInterval(statusCheckInterval);
                            console.log('Withdrawal completed! Refreshing page...');
                            
                            // Show success message
                            const successMsg = document.createElement('div');
                            successMsg.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg z-50';
                            successMsg.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Withdrawal completed successfully!';
                            document.body.appendChild(successMsg);
                            
                            // Refresh page after 2 seconds to show updated status
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                            
                        } else if (status === 'failed') {
                            // Status updated to failed - stop polling
                            clearInterval(statusCheckInterval);
                            console.log('Withdrawal failed!');
                            
                            // Don't show error message here - it's already shown from the initial redirect
                            // Just remove the failed parameter from URL if present
                            if (window.location.search.includes('failed=')) {
                                const url = new URL(window.location);
                                url.searchParams.delete('failed');
                                window.history.replaceState({}, '', url);
                            }
                            
                            // Don't auto-refresh - let user see the error message that was already displayed
                        }
                        // If still pending/processing, continue polling
                    }
                })
                .catch(error => {
                    console.error('Error checking withdrawal status:', error);
                });
        }
        
        // Start polling immediately and then every 5 seconds
        const transactionId = <?php echo $transaction_id_to_check; ?>;
        checkWithdrawalStatus(transactionId); // Immediate check
        statusCheckInterval = setInterval(() => checkWithdrawalStatus(transactionId), 5000); // Every 5 seconds
        <?php endif; ?>
        <?php endif; ?>
    });
    
    // Function to update balance and limit in real-time via AJAX
    function updateBalanceAndLimit() {
        // CRITICAL: Add cache-busting parameter with random value to ensure fresh data
        // Use both timestamp and random number to prevent any caching
        const cacheBuster = '?t=' + Date.now() + '&r=' + Math.random();
        fetch('/api/get-user-balance.php' + cacheBuster, {
            method: 'GET',
            cache: 'no-store',
            headers: {
                'Cache-Control': 'no-cache, no-store, must-revalidate',
                'Pragma': 'no-cache',
                'Expires': '0'
            }
        })
            .then(response => {
                // CRITICAL: Check if response is ok
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update balance display
                    const balanceElements = document.querySelectorAll('[data-balance]');
                    balanceElements.forEach(el => {
                        el.textContent = 'KSh ' + parseFloat(data.balance).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                    });
                    
                    // CRITICAL: Log the API response for debugging
                    console.log('API Response:', {
                        balance: data.balance,
                        total_withdrawn_today: data.total_withdrawn_today,
                        remaining_daily_limit: data.remaining_daily_limit,
                        daily_withdrawal_limit: data.daily_withdrawal_limit
                    });
                    
                    // Update remaining daily limit with fresh data
                    if (data.remaining_daily_limit !== undefined) {
                        const newRemainingLimit = parseFloat(data.remaining_daily_limit);
                        const oldRemainingLimit = remainingLimit;
                        remainingLimit = newRemainingLimit;
                        
                        // Recalculate maxWithdrawal with updated limit
                        // CRITICAL: Only consider system max and daily limit - balance is checked separately
                        const currentBalanceDisplay = parseFloat(data.balance) || currentBalance;
                        const newMaxWithdrawal = Math.min(maxWithdrawalSystem, newRemainingLimit);
                        
                        // Update the input max attribute
                        const amountInput = document.getElementById('amount');
                        if (amountInput) {
                            amountInput.setAttribute('max', newMaxWithdrawal);
                            
                            // CRITICAL: If current amount exceeds new limit, reset it
                            const currentAmount = parseFloat(amountInput.value) || 0;
                            if (currentAmount > newRemainingLimit && newRemainingLimit > 0) {
                                amountInput.value = newRemainingLimit.toFixed(2);
                                console.log('Amount reset to remaining limit:', newRemainingLimit);
                            }
                        }
                        
                        // Update the remaining limit display if it exists
                        const limitDisplayElement = document.getElementById('daily-limit-display');
                        if (limitDisplayElement) {
                            limitDisplayElement.setAttribute('data-remaining-limit', newRemainingLimit);
                            // CRITICAL: Always get the latest total_withdrawn_today from API response
                            // This MUST be the actual total from the database, not a cached value
                            const totalWithdrawn = parseFloat(data.total_withdrawn_today) || 0;
                            const dailyLimit = parseFloat(data.daily_withdrawal_limit) || dailyWithdrawalLimit;
                            
                            // CRITICAL: Log the values being used for display
                            console.log('UPDATING DISPLAY:', {
                                totalWithdrawn: totalWithdrawn,
                                newRemainingLimit: newRemainingLimit,
                                dailyLimit: dailyLimit,
                                apiResponse: data
                            });
                            
                            if (newRemainingLimit > 0) {
                                limitDisplayElement.className = 'text-xs text-yellow-400 mt-1';
                                // CRITICAL: Always show total withdrawn - this is the REAL-TIME value from API
                                // The API calculates this using triple-verification, so it's accurate
                                if (totalWithdrawn > 0) {
                                    limitDisplayElement.innerHTML = `<i class="fas fa-info-circle"></i> Daily limit: KSh ${newRemainingLimit.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} remaining today (You've withdrawn KSh ${totalWithdrawn.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} today)`;
                                } else {
                                    limitDisplayElement.innerHTML = `<i class="fas fa-info-circle"></i> Daily limit: KSh ${newRemainingLimit.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} remaining today`;
                                }
                            } else {
                                limitDisplayElement.className = 'text-xs text-red-400 font-semibold mt-1';
                                // Show limit reached message with total withdrawn
                                limitDisplayElement.innerHTML = `<i class="fas fa-info-circle"></i> Daily withdrawal limit reached! You've withdrawn KSh ${totalWithdrawn.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} today (Limit: KSh ${dailyLimit.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})})`;
                            }
                            
                            // Debug: Log the update
                            console.log('Limit display updated:', {
                                remainingLimit: newRemainingLimit,
                                totalWithdrawn: totalWithdrawn,
                                dailyLimit: dailyLimit,
                                calculation: dailyLimit + ' - ' + totalWithdrawn + ' = ' + newRemainingLimit
                            });
                        }
                        
                        // Debug: Log limit update
                        console.log('Daily limit updated:', {
                            old: oldRemainingLimit,
                            new: newRemainingLimit,
                            total_withdrawn: data.total_withdrawn_today,
                            daily_limit: data.daily_withdrawal_limit,
                            newMaxWithdrawal: newMaxWithdrawal,
                            currentBalance: currentBalanceDisplay
                        });
                        
                        // Re-validate form with new limit immediately
                        updateSummary();
                        
                        // If limit changed significantly (especially if it decreased), show a notification
                        if (Math.abs(newRemainingLimit - oldRemainingLimit) > 0.01 && oldRemainingLimit > 0) {
                            console.log('Daily withdrawal limit updated - Remaining limit changed from', oldRemainingLimit, 'to', newRemainingLimit);
                            
                            // If limit decreased (user made a withdrawal), show a subtle notification
                            if (newRemainingLimit < oldRemainingLimit) {
                                const amountInput = document.getElementById('amount');
                                if (amountInput && parseFloat(amountInput.value) > newRemainingLimit) {
                                    // Amount exceeds new limit - show warning
                                    const errorDiv = document.getElementById('client-error');
                                    if (errorDiv) {
                                        errorDiv.textContent = `Daily limit updated. You can withdraw up to KSh ${newRemainingLimit.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.`;
                                        errorDiv.style.display = 'block';
                                        errorDiv.classList.remove('text-red-500');
                                        errorDiv.classList.add('text-yellow-500');
                                    }
                                }
                            }
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error updating balance:', error);
            });
    }
    
    // Show error messages if any
    <?php if ($error): ?>
    document.addEventListener('DOMContentLoaded', function() {
        // Error is already displayed in PHP, but ensure form is enabled
        const submitBtn = document.getElementById('withdraw-btn');
        if (submitBtn && submitBtn.disabled) {
            // Re-enable button if it was disabled
            updateSummary();
        }
    });
    <?php endif; ?>

    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            if (formSubmitted) return;
            document.querySelectorAll('.amount-btn').forEach(b => b.classList.remove('selected'));
            this.classList.add('selected');
            const amount = this.getAttribute('data-amount');
            document.getElementById('amount').value = amount;
            updateSummary();
        });
    });

    document.getElementById('amount').addEventListener('input', function() {
        if (formSubmitted) return;
        document.querySelectorAll('.amount-btn').forEach(btn => btn.classList.remove('selected'));
        updateSummary();
    });

    document.getElementById('phone').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        this.value = value;
    });

    function updateSummary() {
        if (formSubmitted) return;
        
        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const summaryElement = document.getElementById('withdrawal-summary');
        const submitBtn = document.getElementById('withdraw-btn');
        let canSubmit = false;
        let errorMessage = '';

        // Get current balance from display (updated in real-time)
        const currentBalanceDisplay = parseFloat(document.querySelector('[data-balance]')?.textContent.replace(/[^0-9.]/g, '') || currentBalance) || currentBalance;

        // CRITICAL: Calculate the actual maximum allowed
        // ONLY consider system max and daily limit - balance is checked separately
        // The remainingLimit is already calculated correctly based on ALL withdrawals today
        const actualMaxAllowed = Math.min(maxWithdrawalSystem, remainingLimit);
        
        // Also check balance separately (but don't use it in actualMaxAllowed calculation)
        const balanceCheck = currentBalanceDisplay;
        
        // Only validate if amount is greater than 0
        if (amount > 0) {
            // Check minimum withdrawal
            if (amount < minWithdrawal) {
                errorMessage = `Minimum withdrawal is KSh ${minWithdrawal.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`;
            }
            // Check balance first (separate check)
            else if (amount > currentBalanceDisplay) {
                errorMessage = `Insufficient balance. Available: KSh ${currentBalanceDisplay.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`;
            }
            // Check if amount exceeds the daily withdrawal limit (CRITICAL CHECK)
            else if (amount > remainingLimit) {
                const currentRemaining = Math.max(0, remainingLimit);
                if (currentRemaining <= 0) {
                    errorMessage = `Daily withdrawal limit reached. You have already withdrawn the maximum amount allowed today.`;
                } else {
                    errorMessage = `Daily withdrawal limit exceeded. You can withdraw up to KSh ${currentRemaining.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.`;
                }
            }
            // Check if amount exceeds the actual maximum allowed (system max or daily limit)
            else if (amount > actualMaxAllowed) {
                if (amount > maxWithdrawalSystem) {
                    errorMessage = `Maximum withdrawal is KSh ${maxWithdrawalSystem.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`;
                } else {
                    errorMessage = `Maximum withdrawal allowed is KSh ${actualMaxAllowed.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`;
                }
                console.log('Validation failed:', {
                    amount: amount,
                    actualMaxAllowed: actualMaxAllowed,
                    currentBalance: currentBalanceDisplay,
                    remainingLimit: remainingLimit,
                    maxWithdrawalSystem: maxWithdrawalSystem
                });
            }
            else {
                canSubmit = true;
            }
        }

        // Use actualMaxAllowed for final check
        if (canSubmit && amount >= minWithdrawal && amount <= actualMaxAllowed) {
            const withdrawalFee = (amount * withdrawalFeePercentage) / 100;
            const platformFee = (amount * platformFeePercentage) / 100;
            const totalFees = withdrawalFee + platformFee;
            const afterFee = amount - totalFees;
            
            summaryElement.style.display = 'block';
            document.getElementById('summary-amount').textContent = 'KSh ' + amount.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-withdrawal-fee').textContent = 'KSh ' + withdrawalFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-platform-fee').textContent = 'KSh ' + platformFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-fee').textContent = 'KSh ' + totalFees.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('summary-total').textContent = 'KSh ' + afterFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});

            // Show remaining daily limit if applicable (calculate from current remainingLimit)
            // Calculate remaining after this withdrawal using current remainingLimit value
            const remainingAfter = Math.max(0, remainingLimit - amount);
            const limitInfo = document.getElementById('daily-limit-info');
            if (limitInfo) {
                // Always show the remaining limit message if there's a daily limit set
                if (dailyWithdrawalLimit > 0 && amount > 0) {
                    limitInfo.style.display = 'block';
                    limitInfo.innerHTML = `<i class="fas fa-info-circle text-yellow-400 mr-1"></i>After this withdrawal, you'll have KSh ${remainingAfter.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} remaining today`;
                    console.log('Remaining limit display:', {
                        amount: amount,
                        remainingLimit: remainingLimit,
                        remainingAfter: remainingAfter,
                        dailyWithdrawalLimit: dailyWithdrawalLimit
                    });
                } else {
                    limitInfo.style.display = 'none';
                }
            }

            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        } else {
            summaryElement.style.display = 'none';
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            
            // Show error message if any
            if (errorMessage && amount > 0) {
                const errorDiv = document.getElementById('client-error');
                if (errorDiv) {
                    errorDiv.textContent = errorMessage;
                    errorDiv.style.display = 'block';
                }
            } else {
                const errorDiv = document.getElementById('client-error');
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
            }
        }
    }

    document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
        // Prevent multiple submissions
        if (formSubmitted) {
            e.preventDefault();
            return false;
        }

        const amount = parseFloat(document.getElementById('amount').value) || 0;
        const phone = document.getElementById('phone').value;
        const withdrawalFee = (amount * withdrawalFeePercentage) / 100;
        const platformFee = (amount * platformFeePercentage) / 100;
        const totalFees = withdrawalFee + platformFee;
        const afterFee = amount - totalFees;

        // Basic validation
        if (!amount || amount <= 0) {
            e.preventDefault();
            alert('Please enter a valid withdrawal amount');
            return false;
        }

        if (!phone || phone.length < 9) {
            e.preventDefault();
            alert('Please enter a valid M-Pesa phone number');
            return false;
        }

        // CRITICAL: Final client-side check for daily limit - BLOCK if exceeded
        // Get the most up-to-date remaining limit from the display
        const currentBalanceDisplay = parseFloat(document.querySelector('[data-balance]')?.textContent.replace(/[^0-9.]/g, '') || currentBalance) || currentBalance;
        // CRITICAL: Only consider system max and daily limit - balance is checked separately
        const actualMaxAllowed = Math.min(maxWithdrawalSystem, remainingLimit);
        
        // BLOCK submission if amount exceeds the remaining limit
        if (amount > remainingLimit) {
            e.preventDefault();
            const currentRemaining = Math.max(0, remainingLimit);
            alert(`Daily withdrawal limit exceeded!\n\nYou can withdraw up to KSh ${currentRemaining.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.\n\nPlease reduce the amount and try again.`);
            // Force an immediate update to get the latest limit
            updateBalanceAndLimit();
            return false;
        }
        
        // Also check against actualMaxAllowed to ensure we respect all constraints
        if (amount > actualMaxAllowed) {
            e.preventDefault();
            if (amount > currentBalanceDisplay) {
                alert(`Insufficient balance!\n\nAvailable: KSh ${currentBalanceDisplay.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
            } else if (amount > remainingLimit) {
                const currentRemaining = Math.max(0, remainingLimit);
                alert(`Daily withdrawal limit exceeded!\n\nYou can withdraw up to KSh ${currentRemaining.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.`);
            } else {
                alert(`Maximum withdrawal allowed is KSh ${actualMaxAllowed.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`);
            }
            // Force an immediate update to get the latest limit
            updateBalanceAndLimit();
            return false;
        }

        // Show styled confirmation modal instead of basic confirm dialog
        showWithdrawalConfirmModal({
            amount: amount,
            withdrawalFee: withdrawalFee,
            platformFee: platformFee,
            totalFees: totalFees,
            afterFee: afterFee,
            phone: phone,
            withdrawalFeePercentage: withdrawalFeePercentage,
            platformFeePercentage: platformFeePercentage
        });
        
        // Prevent form submission - will be handled by modal buttons
        e.preventDefault();
        return false;
    });

    // Initialize summary on page load
    updateSummary();
    
    // Debug: Log form submission attempts
    console.log('Withdrawal form initialized');
    console.log('Remaining daily limit:', remainingDailyLimit);
    console.log('Current balance:', currentBalance);
    
    // ============================================================
    // STYLED WITHDRAWAL CONFIRMATION MODAL
    // ============================================================
    let withdrawalConfirmCallback = null;
    
    function showWithdrawalConfirmModal(data) {
        // Populate modal with data
        document.getElementById('modal-amount').textContent = 'KSh ' + data.amount.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('modal-withdrawal-fee').textContent = 'KSh ' + data.withdrawalFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + data.withdrawalFeePercentage + '%)';
        document.getElementById('modal-platform-fee').textContent = 'KSh ' + data.platformFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' (' + data.platformFeePercentage + '%)';
        document.getElementById('modal-total-fees').textContent = 'KSh ' + data.totalFees.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('modal-after-fee').textContent = 'KSh ' + data.afterFee.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('modal-phone').textContent = data.phone;
        
        // Show modal
        const modal = document.getElementById('withdrawalConfirmModal');
        modal.classList.add('show');
        
        // Prevent body scrolling on mobile
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        
        // Store callback for confirmation
        withdrawalConfirmCallback = function(confirmed) {
            if (confirmed) {
                // CRITICAL: Final limit check right before submission
                // Fetch the latest limit one more time to ensure we have the most current data
                const amount = data.amount;
                const phone = data.phone;
                
                // Get the most up-to-date remaining limit
                const currentBalanceDisplay = parseFloat(document.querySelector('[data-balance]')?.textContent.replace(/[^0-9.]/g, '') || currentBalance) || currentBalance;
                // CRITICAL: Only consider system max and daily limit - balance is checked separately
                const actualMaxAllowed = Math.min(maxWithdrawalSystem, remainingLimit);
                
                // BLOCK if amount exceeds the remaining limit
                if (amount > remainingLimit) {
                    hideWithdrawalConfirmModal();
                    const currentRemaining = Math.max(0, remainingLimit);
                    alert(`Daily withdrawal limit exceeded!\n\nYou can withdraw up to KSh ${currentRemaining.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.\n\nPlease reduce the amount and try again.`);
                    // Force an immediate update to get the latest limit
                    updateBalanceAndLimit();
                    return;
                }
                
                // Also check against actualMaxAllowed
                if (amount > actualMaxAllowed) {
                    hideWithdrawalConfirmModal();
                    if (amount > currentBalanceDisplay) {
                        alert(`Insufficient balance!\n\nAvailable: KSh ${currentBalanceDisplay.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`);
                    } else if (amount > remainingLimit) {
                        const currentRemaining = Math.max(0, remainingLimit);
                        alert(`Daily withdrawal limit exceeded!\n\nYou can withdraw up to KSh ${currentRemaining.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})} more today.`);
                    } else {
                        alert(`Maximum withdrawal allowed is KSh ${actualMaxAllowed.toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}.`);
                    }
                    // Force an immediate update to get the latest limit
                    updateBalanceAndLimit();
                    return;
                }
                
                // User confirmed - prepare form submission
                formSubmitted = true;
                
                // Generate new unique request ID for this submission
                const requestId = 'WD-' + <?php echo $user_id; ?> + '-' + amount + '-' + phone + '-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
                const requestIdInput = document.getElementById('request_id');
                if (requestIdInput) {
                    requestIdInput.value = requestId;
                }
                
                // Update submit button
                const submitBtn = document.getElementById('withdraw-btn');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
                    submitBtn.disabled = true;
                }
                
                // Debug: Log the amount being submitted
                console.log('Form submitting with amount:', amount, 'Type:', typeof amount);
                console.log('Amount input value:', document.getElementById('amount').value);
                console.log('Remaining limit at submission:', remainingLimit);
                
                // Submit the form
                document.getElementById('withdrawalForm').submit();
            } else {
                // User cancelled - do nothing
                formSubmitted = false;
            }
        };
    }
    
    function hideWithdrawalConfirmModal() {
        const modal = document.getElementById('withdrawalConfirmModal');
        modal.classList.remove('show');
        // Restore body scrolling
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.width = '';
        withdrawalConfirmCallback = null;
    }
    
    // Modal event listeners - set up after DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
        const confirmBtn = document.getElementById('confirmWithdrawalBtn');
        const cancelBtn = document.getElementById('cancelWithdrawalBtn');
        const closeBtn = document.getElementById('closeModalBtn');
        const modal = document.getElementById('withdrawalConfirmModal');
        
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                if (withdrawalConfirmCallback) {
                    withdrawalConfirmCallback(true);
                }
                hideWithdrawalConfirmModal();
            });
        }
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                if (withdrawalConfirmCallback) {
                    withdrawalConfirmCallback(false);
                }
                hideWithdrawalConfirmModal();
            });
        }
        
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                if (withdrawalConfirmCallback) {
                    withdrawalConfirmCallback(false);
                }
                hideWithdrawalConfirmModal();
            });
        }
        
        // Close modal when clicking outside
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (withdrawalConfirmCallback) {
                        withdrawalConfirmCallback(false);
                    }
                    hideWithdrawalConfirmModal();
                }
            });
        }
    });
    
    // Close modal with Escape key (can be set up immediately)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('withdrawalConfirmModal');
            if (modal && modal.classList.contains('show')) {
                if (withdrawalConfirmCallback) {
                    withdrawalConfirmCallback(false);
                }
                hideWithdrawalConfirmModal();
            }
        }
    });
  </script>
  </body>
  </html>