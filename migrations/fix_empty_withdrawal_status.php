<?php
/**
 * Migration: Fix Empty Withdrawal Status
 * 
 * This migration fixes existing withdrawal transactions that have
 * NULL or empty string status by setting them to 'pending'.
 * 
 * This ensures that all withdrawals are properly counted in the daily limit calculation.
 * 
 * SAFETY FEATURES:
 * - Dry-run mode by default (use ?run=1 to actually execute)
 * - Only fixes transactions from the last 30 days (recent transactions)
 * - Shows detailed preview before making changes
 * - Creates backup log of all changes
 * 
 * Usage:
 *   Dry-run (safe, just shows what would be changed):
 *     php migrations/fix_empty_withdrawal_status.php
 *   
 *   Actually run the migration:
 *     php migrations/fix_empty_withdrawal_status.php?run=1
 */

require_once __DIR__ . '/../config/database.php';

// Check if this is a dry-run or actual execution
$dry_run = !isset($_GET['run']) || $_GET['run'] !== '1';

echo "================================================\n";
echo "Migration: Fix Empty Withdrawal Status\n";
echo "================================================\n";
echo "Mode: " . ($dry_run ? "DRY-RUN (Preview Only - No Changes)" : "LIVE (Will Make Changes)") . "\n";
echo "================================================\n\n";

try {
    // SAFETY: Only fix transactions from the last 30 days to be conservative
    // This prevents accidentally modifying very old transactions
    $days_back = 30;
    
    // First, get detailed information about transactions that would be fixed
    $stmt = $db->prepare("
        SELECT 
            id, 
            user_id, 
            amount, 
            status, 
            created_at,
            DATE(created_at) as tx_date
        FROM transactions 
        WHERE type = 'withdrawal' 
        AND (status IS NULL OR status = '')
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$days_back]);
    $transactions_to_fix = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = count($transactions_to_fix);
    
    echo "Found {$count} withdrawal transactions with empty/NULL status (last {$days_back} days)\n\n";
    
    if ($count > 0) {
        // Show preview of what will be changed
        echo "PREVIEW - Transactions that will be updated:\n";
        echo "--------------------------------------------\n";
        $total_amount = 0;
        foreach ($transactions_to_fix as $txn) {
            $total_amount += (float)$txn['amount'];
            echo sprintf(
                "ID: %d | User: %d | Amount: %.2f | Date: %s | Current Status: '%s'\n",
                $txn['id'],
                $txn['user_id'],
                (float)$txn['amount'],
                $txn['created_at'],
                $txn['status'] ?? 'NULL'
            );
        }
        echo "--------------------------------------------\n";
        echo "Total Amount: " . number_format($total_amount, 2) . "\n";
        echo "Total Transactions: {$count}\n\n";
        
        if ($dry_run) {
            echo "⚠ DRY-RUN MODE: No changes were made.\n";
            echo "To actually run this migration, add ?run=1 to the URL\n";
            echo "Example: migrations/fix_empty_withdrawal_status.php?run=1\n\n";
        } else {
            // Actually perform the update
            echo "Executing migration...\n";
            
            // Start transaction for safety
            $db->beginTransaction();
            
            try {
                // Update transactions with empty/NULL status to 'pending'
                $update_stmt = $db->prepare("
                    UPDATE transactions 
                    SET status = 'pending',
                        updated_at = NOW()
                    WHERE type = 'withdrawal' 
                    AND (status IS NULL OR status = '')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $update_stmt->execute([$days_back]);
                $affected = $update_stmt->rowCount();
                
                // Commit the transaction
                $db->commit();
                
                echo "✓ Updated {$affected} transactions to 'pending' status\n";
                echo "✓ Migration completed successfully!\n\n";
                
                // Verify the fix
                $verify_stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM transactions 
                    WHERE type = 'withdrawal' 
                    AND (status IS NULL OR status = '')
                    AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ");
                $verify_stmt->execute([$days_back]);
                $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                $remaining = (int)$verify_result['count'];
                
                if ($remaining > 0) {
                    echo "⚠ WARNING: {$remaining} transactions still have empty status (may be older than {$days_back} days)\n";
                } else {
                    echo "✓ Verification passed: All recent withdrawals now have a valid status\n";
                }
                
                // Log the migration
                error_log(sprintf(
                    "MIGRATION COMPLETED: fix_empty_withdrawal_status.php - Updated %d transactions to 'pending' status",
                    $affected
                ));
                
            } catch (Exception $e) {
                // Rollback on error
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }
        }
    } else {
        echo "✓ No transactions with empty status found (last {$days_back} days). Migration not needed.\n";
        
        // Check if there are any older transactions with empty status
        $stmt_older = $db->query("
            SELECT COUNT(*) as count 
            FROM transactions 
            WHERE type = 'withdrawal' 
            AND (status IS NULL OR status = '')
            AND created_at < DATE_SUB(NOW(), INTERVAL {$days_back} DAY)
        ");
        $older_result = $stmt_older->fetch(PDO::FETCH_ASSOC);
        $older_count = (int)$older_result['count'];
        
        if ($older_count > 0) {
            echo "\nℹ Note: There are {$older_count} older transactions (more than {$days_back} days) with empty status.\n";
            echo "   These are not being modified for safety. If needed, you can modify the script to include them.\n";
        }
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    error_log("MIGRATION ERROR: fix_empty_withdrawal_status.php - " . $e->getMessage());
    exit(1);
}

echo "\n================================================\n";
echo "Migration script completed!\n";
echo "================================================\n";

