<?php
/**
 * Real-time User Balance and Limit API
 * Returns current balance and remaining daily withdrawal limit
 * Used for real-time updates without page refresh
 */

require_once '../config/database.php';
require_once '../config/system_helpers.php';
requireLogin();

// CRITICAL: Prevent caching to ensure fresh data on every request
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'];

try {
    // Get current user balance
    $stmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    // Get daily withdrawal limit from database (always fetch fresh)
    // CRITICAL: This must be fetched fresh every time to get the latest admin-set value
    $daily_withdrawal_limit = (float)getSystemSetting('daily_withdrawal_limit', 50000);
    
    // Calculate remaining daily withdrawal limit
    // CRITICAL: Use the EXACT SAME logic as checkDailyWithdrawalLimit for consistency
    // Force fresh read to see all committed transactions
    // CRITICAL: Force a fresh connection state to see all committed transactions
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
        
        // BACKUP: Use date range query
        // CRITICAL: Also count transactions with NULL or empty status (treat as pending)
        $stmt_backup = $db->prepare("
            SELECT 
                ROUND(COALESCE(SUM(amount), 0), 2) as total_today_backup
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
        $transaction_details_for_log = [];
        foreach ($all_transactions as $txn) {
            // CRITICAL: Handle empty string, NULL, or missing status
            // If status is empty/NULL, treat it as 'pending' (default for new withdrawals)
            $status = trim($txn['status'] ?? '');
            if (empty($status)) {
                $status = 'pending'; // Default status for withdrawals
            }
            
            // Count if status is in counted statuses OR if it's empty (treat as pending)
            $is_counted = in_array($status, $counted_statuses) || empty($txn['status']);
            
            if ($is_counted) {
                $manual_sum += (float)$txn['amount'];
            }
            $transaction_details_for_log[] = [
                'id' => $txn['id'],
                'amount' => (float)$txn['amount'],
                'status' => $txn['status'] ?? 'NULL',
                'status_normalized' => $status,
                'counted' => $is_counted
            ];
        }
        $manual_sum = round($manual_sum, 2);
        
        // CRITICAL: Log ALL transactions found for debugging
        error_log(sprintf(
            "API MANUAL CALCULATION - User ID %d | Found %d transactions | Details: %s | Manual Sum: %.2f",
            $user_id,
            count($all_transactions),
            json_encode($transaction_details_for_log),
            $manual_sum
        ));
        
        // Use MAXIMUM of all three methods (same as checkDailyWithdrawalLimit)
        // This ensures we NEVER miss a transaction
        // CRITICAL: Always use the maximum to ensure we see ALL withdrawals
        $total_withdrawn_today = max($total_withdrawn_today, $total_backup, $manual_sum);
        
        // CRITICAL: Log all three values to debug discrepancies
        error_log(sprintf(
            "API CALCULATION DEBUG - User ID %d | Primary Query: %.2f | Backup Query: %.2f | Manual Sum: %.2f | Final Total: %.2f | Transaction Count: %d | All Transactions: %s",
            $user_id,
            round((float)($result['total_today'] ?? 0), 2),
            $total_backup,
            $manual_sum,
            $total_withdrawn_today,
            count($all_transactions ?? []),
            json_encode($transaction_details_for_log ?? [])
        ));
    } catch (Exception $e) {
        error_log("API LIMIT CALC ERROR: " . $e->getMessage());
        $total_withdrawn_today = 0; // Fallback to 0 on error
    }
    
    // Log if there's a discrepancy
    if (abs($total_withdrawn_today - $manual_sum) > 0.01 || abs($total_withdrawn_today - $total_backup) > 0.01) {
        error_log(sprintf(
            "API LIMIT CALC: Discrepancy detected for User ID %d - Primary: %.2f, Backup: %.2f, Manual: %.2f, Using: %.2f",
            $user_id,
            round((float)($result['total_today'] ?? 0), 2),
            $total_backup,
            $manual_sum,
            $total_withdrawn_today
        ));
    }
    
    // CRITICAL: Calculate remaining limit - MUST be based on ALL withdrawals today
    // Formula: remaining = max(0, limit - total_withdrawn_today)
    // This ensures if user has withdrawn more than limit, remaining is 0 (not negative)
    $remaining_daily_limit = max(0, round($daily_withdrawal_limit - $total_withdrawn_today, 2));
    
    // CRITICAL: Log the calculation for debugging
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
        "API LIMIT CALC: User ID %d | Limit: %.2f | Total Withdrawn: %.2f | Remaining: %.2f | Calculation: %.2f - %.2f = %.2f | Transactions: %d | Details: %s",
        $user_id,
        $daily_withdrawal_limit,
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
            "WARNING: Total withdrawn (%.2f) EXCEEDS daily limit (%.2f) for User ID %d! Remaining set to 0.",
            $total_withdrawn_today,
            $daily_withdrawal_limit,
            $user_id
        ));
        // Ensure remaining is 0 if exceeded
        $remaining_daily_limit = 0;
    }
    
    // CRITICAL: Log the final API response being sent
    error_log(sprintf(
        "API RESPONSE SENT - User ID %d | Balance: %.2f | Limit: %.2f | Total Withdrawn: %.2f | Remaining: %.2f | Limit Exceeded: %s",
        $user_id,
        (float)$user['wallet_balance'],
        $daily_withdrawal_limit,
        $total_withdrawn_today,
        $remaining_daily_limit,
        ($total_withdrawn_today > $daily_withdrawal_limit) ? 'YES' : 'NO'
    ));
    
    echo json_encode([
        'success' => true,
        'balance' => (float)$user['wallet_balance'],
        'daily_withdrawal_limit' => $daily_withdrawal_limit,
        'total_withdrawn_today' => $total_withdrawn_today,
        'remaining_daily_limit' => $remaining_daily_limit,
        'limit_exceeded' => ($total_withdrawn_today > $daily_withdrawal_limit)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch balance: ' . $e->getMessage()
    ]);
}

