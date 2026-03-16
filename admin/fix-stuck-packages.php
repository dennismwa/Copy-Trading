<?php
/**
 * Fix Stuck Packages - Recovery Script
 * This script identifies and fixes packages that are matured but still marked as 'active'
 * Run this once to clean up any stuck packages
 */

require_once '../config/database.php';
requireAdmin();

echo "<h2>Fixing Stuck Packages</h2>\n";

try {
    // Find packages that are matured but still marked as active
    $stmt = $db->query("
        SELECT ap.*, u.email, u.full_name, p.name as package_name
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status = 'active' 
        AND ap.maturity_date <= NOW()
        ORDER BY ap.maturity_date ASC
    ");
    
    $stuck_packages = $stmt->fetchAll();
    
    if (empty($stuck_packages)) {
        echo "<p>No stuck packages found. All packages are properly processed.</p>\n";
        exit;
    }
    
    echo "<p>Found " . count($stuck_packages) . " stuck packages that need processing:</p>\n";
    echo "<ul>\n";
    
    $processed_count = 0;
    $failed_count = 0;
    
    foreach ($stuck_packages as $package) {
        try {
            $db->beginTransaction();
            
            // Calculate total payout
            $investment_amount = $package['investment_amount'];
            $roi_amount = $package['expected_roi'];
            $total_payout = $investment_amount + $roi_amount;
            
            // Credit user's wallet
            $stmt = $db->prepare("
                UPDATE users 
                SET wallet_balance = wallet_balance + ?,
                    total_roi_earned = total_roi_earned + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$total_payout, $roi_amount, $package['user_id']]);
            
            // Mark package as completed
            $stmt = $db->prepare("
                UPDATE active_packages 
                SET status = 'completed',
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$package['id']]);
            
            // Create transaction record
            $stmt = $db->prepare("
                INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
                VALUES (?, 'roi_payment', ?, 'completed', ?, NOW())
            ");
            $description = "ROI payment from {$package['package_name']} package (Principal: " . 
                          formatMoney($investment_amount) . " + ROI: " . formatMoney($roi_amount) . ")";
            $stmt->execute([$package['user_id'], $total_payout, $description]);
            
            // Send notification
            sendNotification(
                $package['user_id'],
                'Package Matured! 🎉',
                "Your {$package['package_name']} package has matured! You've received " . 
                formatMoney($total_payout) . " (Principal: " . formatMoney($investment_amount) . 
                " + ROI: " . formatMoney($roi_amount) . ") in your wallet.",
                'success'
            );
            
            $db->commit();
            $processed_count++;
            
            echo "<li>✓ Fixed package #{$package['id']} for {$package['full_name']} - " . formatMoney($total_payout) . "</li>\n";
            
        } catch (Exception $e) {
            $db->rollBack();
            $failed_count++;
            echo "<li>✗ Failed to fix package #{$package['id']}: " . $e->getMessage() . "</li>\n";
            error_log("Stuck package fix error: " . $e->getMessage());
        }
    }
    
    echo "</ul>\n";
    echo "<p><strong>Summary:</strong> Processed {$processed_count} packages, {$failed_count} failed.</p>\n";
    
    if ($processed_count > 0) {
        echo "<p>✅ Stuck packages have been fixed! The system should now show consistent counts.</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>\n";
    error_log("Stuck packages fix error: " . $e->getMessage());
}
?>
