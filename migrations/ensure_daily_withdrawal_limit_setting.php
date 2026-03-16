<?php
/**
 * Ensure Daily Withdrawal Limit Setting Exists in Database
 * This script verifies and creates the daily_withdrawal_limit setting if it doesn't exist
 */

require_once '../config/database.php';

echo "Checking for daily_withdrawal_limit setting in system_settings table...\n\n";

try {
    // Check if the setting exists
    $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'daily_withdrawal_limit'");
    $stmt->execute();
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "✅ Setting exists: daily_withdrawal_limit = " . $existing['setting_value'] . "\n";
        echo "✅ Daily withdrawal limit is properly stored in the database.\n";
    } else {
        echo "⚠️  Setting does not exist. Creating it with default value of 50000...\n";
        
        // Insert the setting with default value
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at) 
            VALUES ('daily_withdrawal_limit', '50000', NOW(), NOW())
        ");
        
        if ($stmt->execute()) {
            echo "✅ Successfully created daily_withdrawal_limit setting with value: 50000\n";
            echo "✅ You can update this value in the admin panel at: /admin/settings.php\n";
        } else {
            echo "❌ Failed to create setting. Please check database permissions.\n";
        }
    }
    
    // Verify the table structure
    echo "\nVerifying system_settings table structure...\n";
    $stmt = $db->query("DESCRIBE system_settings");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Table columns: " . implode(', ', array_column($columns, 'Field')) . "\n";
    
    // Show all withdrawal-related settings
    echo "\nAll withdrawal-related settings:\n";
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%withdrawal%' OR setting_key LIKE '%limit%'");
    $stmt->execute();
    $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($settings)) {
        echo "No withdrawal-related settings found.\n";
    } else {
        foreach ($settings as $setting) {
            echo "  - {$setting['setting_key']}: {$setting['setting_value']}\n";
        }
    }
    
    echo "\n✅ Verification complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Error in ensure_daily_withdrawal_limit_setting.php: " . $e->getMessage());
}

