<?php
/**
 * Extend Package Maturity for User 127 (Charles Ngunjiri)
 * Extends maturity date by 24 hours for ALL active packages - SAFE OPERATION (no balance changes)
 */

require_once '../config/database.php';
requireAdmin();

$user_id = 127; // Charles Ngunjiri
$extension_hours = 24;

try {
    // Get user info first
    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die("ERROR: User ID $user_id not found.\n");
    }
    
    // Get ALL active packages for this user
    $stmt = $db->prepare("
        SELECT 
            ap.id as package_id,
            ap.maturity_date,
            ap.investment_amount,
            ap.expected_roi,
            p.name as package_name
        FROM active_packages ap
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.user_id = ? AND ap.status = 'active'
        ORDER BY ap.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($packages)) {
        die("ERROR: No active packages found for User ID $user_id ({$user['full_name']}).\n");
    }
    
    echo "=== PACKAGE MATURITY EXTENSION ===\n\n";
    echo "User: {$user['full_name']} (ID: $user_id)\n";
    echo "Email: {$user['email']}\n";
    echo "Active Packages: " . count($packages) . "\n";
    echo "\n";
    echo "--- Packages to Extend ---\n";
    foreach ($packages as $pkg) {
        echo "Package: {$pkg['package_name']} (ID: {$pkg['package_id']})\n";
        echo "  Investment: " . formatMoney($pkg['investment_amount']) . "\n";
        echo "  Expected ROI: " . formatMoney($pkg['expected_roi']) . "\n";
        echo "  Current Maturity: {$pkg['maturity_date']}\n";
        $new_date = new DateTime($pkg['maturity_date']);
        $new_date->modify("+{$extension_hours} hours");
        echo "  New Maturity: " . $new_date->format('Y-m-d H:i:s') . "\n";
        echo "\n";
    }
    
    // Update ALL packages
    $db->beginTransaction();
    
    $updated_count = 0;
    $failed_count = 0;
    $updated_packages = [];
    
    foreach ($packages as $package) {
        try {
            // Calculate new maturity date
            $current_maturity = new DateTime($package['maturity_date']);
            $new_maturity = clone $current_maturity;
            $new_maturity->modify("+{$extension_hours} hours");
            $new_maturity_date = $new_maturity->format('Y-m-d H:i:s');
            
            // Update this package's maturity date
            $update_stmt = $db->prepare("
                UPDATE active_packages 
                SET maturity_date = ?
                WHERE id = ? AND user_id = ? AND status = 'active'
            ");
            $result = $update_stmt->execute([$new_maturity_date, $package['package_id'], $user_id]);
            
            if ($result && $update_stmt->rowCount() > 0) {
                $updated_count++;
                $updated_packages[] = [
                    'id' => $package['package_id'],
                    'name' => $package['package_name'],
                    'old' => $package['maturity_date'],
                    'new' => $new_maturity_date
                ];
                
                // Log the change
                error_log(sprintf(
                    "PACKAGE MATURITY EXTENDED - User ID: %d (%s) | Package ID: %d (%s) | Old Maturity: %s | New Maturity: %s | Extension: %d hours",
                    $user_id,
                    $user['full_name'],
                    $package['package_id'],
                    $package['package_name'],
                    $package['maturity_date'],
                    $new_maturity_date,
                    $extension_hours
                ));
            } else {
                $failed_count++;
                echo "⚠️  WARNING: Failed to update Package ID {$package['package_id']} ({$package['package_name']})\n";
            }
        } catch (Exception $e) {
            $failed_count++;
            echo "⚠️  ERROR updating Package ID {$package['package_id']}: " . $e->getMessage() . "\n";
            error_log("Error extending package ID {$package['package_id']}: " . $e->getMessage());
        }
    }
    
    if ($updated_count > 0) {
        $db->commit();
        
        echo "\n";
        echo "✅ SUCCESS: Extended $updated_count package(s) successfully!\n";
        echo "\n";
        echo "--- Updated Packages ---\n";
        foreach ($updated_packages as $pkg) {
            echo "Package: {$pkg['name']} (ID: {$pkg['id']})\n";
            echo "  Old Maturity: {$pkg['old']}\n";
            echo "  New Maturity: {$pkg['new']}\n";
            echo "  Extension: +{$extension_hours} hours\n";
            echo "\n";
        }
        
        if ($failed_count > 0) {
            echo "⚠️  WARNING: $failed_count package(s) could not be updated.\n";
        }
        
        echo "\n";
        echo "⚠️  NOTE: This operation did NOT affect:\n";
        echo "   - User wallet balance\n";
        echo "   - Investment amounts\n";
        echo "   - Expected ROI values\n";
        echo "   - Any other financial data\n";
        echo "\n";
        echo "Only the maturity_date field(s) were updated.\n";
    } else {
        $db->rollBack();
        die("ERROR: Failed to update any packages. All updates failed.\n");
    }
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("ERROR: " . $e->getMessage() . "\n");
}
