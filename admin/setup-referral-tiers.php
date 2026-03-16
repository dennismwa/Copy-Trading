<?php
/**
 * Setup Referral Tiers System
 * Run this once to create the necessary database tables
 */

require_once '../config/database.php';
requireAdmin();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_tables'])) {
    try {
        $db->beginTransaction();
        
        // Create referral_tiers table
        $db->exec("
            CREATE TABLE IF NOT EXISTS referral_tiers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tier_name VARCHAR(50) NOT NULL UNIQUE,
                tier_level INT NOT NULL UNIQUE COMMENT '1=Bronze, 2=Silver, 3=Gold',
                referral_earnings_threshold DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum referral earnings to qualify',
                daily_withdrawal_limit DECIMAL(15,2) NOT NULL DEFAULT 50000.00 COMMENT 'Custom daily withdrawal limit for this tier',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                description TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_tier_level (tier_level),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Create user_tier_assignments table
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_tier_assignments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                tier_id INT NOT NULL,
                assignment_type ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic',
                assigned_by INT NULL COMMENT 'Admin user ID if manually assigned',
                assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL COMMENT 'NULL means permanent, or set expiration date',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT COMMENT 'Admin notes for manual assignments',
                UNIQUE KEY unique_active_user_tier (user_id, is_active),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (tier_id) REFERENCES referral_tiers(id) ON DELETE RESTRICT,
                INDEX idx_user_id (user_id),
                INDEX idx_tier_id (tier_id),
                INDEX idx_is_active (is_active),
                INDEX idx_assignment_type (assignment_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Insert default tiers if they don't exist
        $tiers = [
            ['Bronze', 1, 10000, 75000, 'Bronze tier - Entry level for active referrers'],
            ['Silver', 2, 50000, 150000, 'Silver tier - Mid-level for dedicated referrers'],
            ['Gold', 3, 200000, 500000, 'Gold tier - Premium tier for top referrers']
        ];
        
        foreach ($tiers as $tier) {
            $stmt = $db->prepare("
                INSERT INTO referral_tiers (tier_name, tier_level, referral_earnings_threshold, daily_withdrawal_limit, description)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    referral_earnings_threshold = VALUES(referral_earnings_threshold),
                    daily_withdrawal_limit = VALUES(daily_withdrawal_limit),
                    description = VALUES(description),
                    updated_at = NOW()
            ");
            $stmt->execute($tier);
        }
        
        $db->commit();
        $success = "Referral tiers system setup completed successfully! Default tiers (Bronze, Silver, Gold) have been created.";
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Setup failed: " . $e->getMessage();
        error_log("Referral tiers setup error: " . $e->getMessage());
    }
}

// Check if tables exist
$tables_exist = false;
try {
    $stmt = $db->query("SHOW TABLES LIKE 'referral_tiers'");
    $tiers_table_exists = $stmt->rowCount() > 0;
    
    $stmt = $db->query("SHOW TABLES LIKE 'user_tier_assignments'");
    $assignments_table_exists = $stmt->rowCount() > 0;
    
    $tables_exist = $tiers_table_exists && $assignments_table_exists;
} catch (Exception $e) {
    $tables_exist = false;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Referral Tiers System - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white min-h-screen p-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 rounded-xl p-6 mb-6 shadow-2xl">
            <h1 class="text-3xl font-bold mb-2">
                <i class="fas fa-trophy text-yellow-300 mr-3"></i>
                Setup Referral Tiers System
            </h1>
            <p class="text-purple-100">Initialize the referral tier reward system database tables</p>
        </div>

        <?php if ($error): ?>
        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
                <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
                <span class="text-emerald-300"><?php echo htmlspecialchars($success); ?></span>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-gray-800 rounded-xl p-6 shadow-2xl">
            <h2 class="text-2xl font-bold mb-4">
                <i class="fas fa-database text-blue-400 mr-2"></i>
                Database Status
            </h2>
            
            <div class="space-y-4 mb-6">
                <div class="flex items-center justify-between p-4 bg-gray-700/50 rounded-lg">
                    <span class="text-gray-300">
                        <i class="fas fa-table mr-2"></i>referral_tiers table
                    </span>
                    <span class="<?php echo $tiers_table_exists ? 'text-emerald-400' : 'text-red-400'; ?>">
                        <?php echo $tiers_table_exists ? '<i class="fas fa-check-circle"></i> Exists' : '<i class="fas fa-times-circle"></i> Missing'; ?>
                    </span>
                </div>
                
                <div class="flex items-center justify-between p-4 bg-gray-700/50 rounded-lg">
                    <span class="text-gray-300">
                        <i class="fas fa-table mr-2"></i>user_tier_assignments table
                    </span>
                    <span class="<?php echo $assignments_table_exists ? 'text-emerald-400' : 'text-red-400'; ?>">
                        <?php echo $assignments_table_exists ? '<i class="fas fa-check-circle"></i> Exists' : '<i class="fas fa-times-circle"></i> Missing'; ?>
                    </span>
                </div>
            </div>

            <?php if (!$tables_exist): ?>
            <form method="POST" class="space-y-4">
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-exclamation-triangle text-yellow-400 mt-1"></i>
                        <div>
                            <p class="text-yellow-200 text-sm">
                                <strong>Note:</strong> This will create the necessary database tables and insert default tiers (Bronze, Silver, Gold). 
                                You can modify tier settings after setup from the Referral Tiers Management page.
                            </p>
                        </div>
                    </div>
                </div>

                <button 
                    type="submit" 
                    name="setup_tables" 
                    value="1"
                    class="w-full py-3 bg-gradient-to-r from-purple-600 to-indigo-700 hover:from-purple-700 hover:to-indigo-800 text-white font-bold rounded-lg transition-all duration-200 transform hover:scale-105 shadow-lg"
                >
                    <i class="fas fa-magic mr-2"></i>Setup Referral Tiers System
                </button>
            </form>
            <?php else: ?>
            <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg p-4">
                <div class="flex items-center space-x-2">
                    <i class="fas fa-check-circle text-emerald-400"></i>
                    <div>
                        <p class="text-emerald-200 text-sm">
                            <strong>System Ready!</strong> The referral tiers system is set up and ready to use. 
                            You can now manage tiers from the <a href="referral-tiers.php" class="underline font-bold">Referral Tiers Management</a> page.
                        </p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="mt-6">
            <a href="settings.php" class="inline-block px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Settings
            </a>
        </div>
    </div>
</body>
</html>

