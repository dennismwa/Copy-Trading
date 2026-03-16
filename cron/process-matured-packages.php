<?php
/**
 * Cron Job: Process Matured Packages
 * Run this every 5-10 minutes to process matured investment packages
 * 
 * FIXED: Now returns BOTH investment principal AND ROI to user's wallet
 * FIXED: Handles missing updated_at column gracefully
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/system_helpers.php';

// Ensure we don't redeclare functions
if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        return 'KSh ' . number_format($amount, 2);
    }
}

// Simple, clean processing without database schema checks

try {
    // Log script start
    error_log("CRON_START: Matured packages processing started at " . date('Y-m-d H:i:s'));
    
    // Get all matured packages that haven't been processed
    // Use FOR UPDATE to prevent race conditions
    $stmt = $db->query("
        SELECT ap.*, u.email, u.full_name, p.name as package_name
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status = 'active' 
        AND ap.maturity_date <= NOW()
        ORDER BY ap.maturity_date ASC
        FOR UPDATE
    ");
    
    $matured_packages = $stmt->fetchAll();
    
    
    if (empty($matured_packages)) {
        echo date('Y-m-d H:i:s') . " - No matured packages to process\n";
        error_log("CRON_INFO: No matured packages found to process");
        exit;
    }
    
    echo date('Y-m-d H:i:s') . " - Found " . count($matured_packages) . " matured packages to process\n";
    error_log("CRON_INFO: Found " . count($matured_packages) . " matured packages to process");
    
    foreach ($matured_packages as $package) {
        try {
            $db->beginTransaction();
            
            // Double-check package hasn't been processed by another script
            $check_stmt = $db->prepare("SELECT status FROM active_packages WHERE id = ? FOR UPDATE");
            $check_stmt->execute([$package['id']]);
            $current_status = $check_stmt->fetchColumn();
            
            if ($current_status !== 'active') {
                $db->rollBack();
                echo date('Y-m-d H:i:s') . " - Package #{$package['id']} already processed by another script, skipping\n";
                continue;
            }
            
            // CORRECTED: Calculate total payout = investment amount + ROI
            $investment_amount = $package['investment_amount'];
            $roi_amount = $package['expected_roi'];
            $total_payout = $investment_amount + $roi_amount;
            
            // Update user's wallet with TOTAL PAYOUT (investment was deducted at purchase)
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?,
                    total_roi_earned = total_roi_earned + ?
                WHERE id = ?
            ");
            $stmt->execute([$total_payout, $roi_amount, $package['user_id']]);
            
            // Mark package as completed
            $stmt = $db->prepare("
                UPDATE active_packages 
                SET status = 'completed',
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$package['id']]);
            
            // Create ROI payment transaction record
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                VALUES (?, 'roi_payment', ?, 'completed', ?, NOW())
            ");
            $description = "ROI payment from {$package['package_name']} package (Principal: " . 
                          formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . ")";
            $stmt->execute([$package['user_id'], $total_payout, $description]);
            
            // Send notification to user
            sendNotification(
                $package['user_id'],
                'Package Matured! 🎉',
                "Your {$package['package_name']} package has matured! You've received " . 
                formatMoney($total_payout) . " (Principal: " . formatMoney($investment_amount) . 
                " + ROI: " . formatMoney($roi_amount) . ") in your wallet.",
                'success'
            );
            
            $db->commit();
            
            // Log successful processing
            error_log("ROI_PROCESSING_SUCCESS: Package #{$package['id']} completed for user #{$package['user_id']} ({$package['full_name']}) - Total: " . formatMoney($total_payout) . " (Investment: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . ")");
            
            echo date('Y-m-d H:i:s') . " - ✓ Processed package #{$package['id']} for user {$package['full_name']} " .
                 "(#{$package['user_id']}): Total payout = " . formatMoney($total_payout) . 
                 " (Investment: " . formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . ")\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            echo date('Y-m-d H:i:s') . " - ✗ Error processing package #{$package['id']}: " . $e->getMessage() . "\n";
            error_log("Matured package processing error: " . $e->getMessage());
        }
    }
    
    echo date('Y-m-d H:i:s') . " - Completed processing matured packages\n";
    error_log("CRON_SUCCESS: Matured packages processing completed successfully at " . date('Y-m-d H:i:s'));
    
} catch (Exception $e) {
    echo date('Y-m-d H:i:s') . " - Fatal error: " . $e->getMessage() . "\n";
    error_log("CRON_FATAL_ERROR: Matured package cron fatal error at " . date('Y-m-d H:i:s') . " - " . $e->getMessage());
}
