<?php
/**
 * Create Test Limited Admin User
 * This page creates a test limited admin user you can login with
 */

require_once 'config/database.php';

$email = 'limited@ultraharvest.com';
$password = 'TestAdmin123!';
$full_name = 'Limited Admin Test';

$created = false;
$user_exists = false;
$error = '';
$user_id = null;

// Check if user already exists
try {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch();
    
    if ($existing_user) {
        $user_exists = true;
        $user_id = $existing_user['id'];
    }
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle creation or recreation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    try {
        $db->beginTransaction();
        
        // Delete existing user if any
        if ($user_exists) {
            $stmt = $db->prepare("DELETE FROM admin_access_control WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $db->prepare("DELETE FROM admin_permissions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $db->prepare("DELETE FROM admin_action_logs WHERE target_user = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
        }
        
        // Generate referral code
        $referral_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        while (true) {
            $stmt = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
            $stmt->execute([$referral_code]);
            if (!$stmt->fetch()) break;
            $referral_code = strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Create user
        $stmt = $db->prepare("
            INSERT INTO users (email, password, full_name, phone, referral_code, is_admin, email_verified, status)
            VALUES (?, ?, ?, ?, ?, 0, 1, 'active')
        ");
        $stmt->execute([$email, $hashed_password, $full_name, '+254700111222', $referral_code]);
        $user_id = $db->lastInsertId();
        
        // Create tables if needed
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_access_control (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                can_access TINYINT(1) DEFAULT 0,
                granted_by INT(11) DEFAULT NULL,
                granted_at TIMESTAMP NULL DEFAULT NULL,
                revoked_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user (user_id),
                INDEX idx_user_id (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_permissions (
                id INT(11) NOT NULL AUTO_INCREMENT,
                user_id INT(11) NOT NULL,
                permission_key VARCHAR(50) NOT NULL,
                allowed TINYINT(1) DEFAULT 0,
                updated_by INT(11) DEFAULT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY unique_user_permission (user_id, permission_key),
                INDEX idx_user_id (user_id),
                INDEX idx_permission (permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS admin_action_logs (
                id INT(11) NOT NULL AUTO_INCREMENT,
                admin_id INT(11) NOT NULL,
                action VARCHAR(100) NOT NULL,
                target_user INT(11) DEFAULT NULL,
                details TEXT DEFAULT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_admin_id (admin_id),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Grant access
        $admin_id = 1; // Default admin
        $stmt = $db->prepare("
            INSERT INTO admin_access_control (user_id, can_access, granted_by, granted_at)
            VALUES (?, 1, ?, NOW())
        ");
        $stmt->execute([$user_id, $admin_id]);
        
        // Grant basic permissions
        $basic_permissions = [
            'view_users',
            'view_deposits',
            'view_withdrawals',
            'view_trades',
            'view_transactions',
            'view_reports'
        ];
        
        foreach ($basic_permissions as $perm) {
            $stmt = $db->prepare("
                INSERT INTO admin_permissions (user_id, permission_key, allowed, updated_by)
                VALUES (?, ?, 1, ?)
            ");
            $stmt->execute([$user_id, $perm, $admin_id]);
        }
        
        $db->commit();
        $created = true;
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $error = "Error: " . $e->getMessage();
        error_log("Create admin error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Test Limited Admin - Ultra Harvest</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        .credential-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen py-12">
    <div class="container mx-auto px-4 max-w-3xl">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-white mb-2">Create Test Limited Admin</h1>
            <p class="text-gray-400">Generate a test user with restricted admin access</p>
        </div>

        <?php if ($error): ?>
        <div class="bg-red-500/20 border border-red-500/50 rounded-lg p-4 mb-6 flex items-center">
            <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
            <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($created || $user_exists): ?>
        <!-- Credentials Display -->
        <div class="bg-gray-800 rounded-xl p-8 mb-6 shadow-2xl border-2 border-emerald-500/30">
            <div class="text-center mb-6">
                <div class="w-20 h-20 bg-green-500 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-3xl text-white"></i>
                </div>
                <h2 class="text-2xl font-bold text-white mb-2">Limited Admin Ready!</h2>
                <p class="text-gray-400">Use these credentials to login</p>
            </div>

            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-6 mb-6">
                <div class="space-y-4">
                    <div class="flex items-center justify-between bg-white/10 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-envelope text-blue-300 text-xl"></i>
                            <span class="text-gray-300 font-medium">Email:</span>
                        </div>
                        <span class="font-mono text-white font-bold text-lg"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    
                    <div class="flex items-center justify-between bg-white/10 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-key text-yellow-300 text-xl"></i>
                            <span class="text-gray-300 font-medium">Password:</span>
                        </div>
                        <span class="font-mono text-white font-bold text-lg"><?php echo htmlspecialchars($password); ?></span>
                    </div>
                    
                    <div class="flex items-center justify-between bg-white/10 rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <i class="fas fa-id-card text-purple-300 text-xl"></i>
                            <span class="text-gray-300 font-medium">User ID:</span>
                        </div>
                        <span class="text-white font-bold"><?php echo $user_id; ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-blue-500/10 border border-blue-500/30 rounded-lg p-4 mb-6">
                <p class="text-sm text-blue-300 flex items-start mb-3">
                    <i class="fas fa-info-circle mr-2 mt-1"></i>
                    <span><strong>Note:</strong> These credentials are saved in the database. You can login at <code class="bg-gray-800 px-2 py-1 rounded">/login.php</code> right now!</span>
                </p>
            </div>

            <div class="flex space-x-3">
                <a href="/login.php" class="flex-1 px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-center transition">
                    <i class="fas fa-sign-in-alt mr-2"></i>Go to Login
                </a>
                <a href="/home.php" class="flex-1 px-6 py-3 bg-gray-700 hover:bg-gray-600 text-white rounded-lg font-medium text-center transition">
                    <i class="fas fa-users mr-2"></i>Manage Admins
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Create/Recreate Form -->
        <div class="bg-gray-800 rounded-xl p-8 shadow-2xl">
            <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                <i class="fas fa-user-plus text-emerald-400 mr-2"></i>
                <?php echo $user_exists ? 'Recreate' : 'Create'; ?> Test Limited Admin
            </h3>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="create_user" value="1">
                
                <div class="bg-gray-700/50 rounded-lg p-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Email:</span>
                        <span class="font-mono text-white"><?php echo htmlspecialchars($email); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Password:</span>
                        <span class="font-mono text-white"><?php echo htmlspecialchars($password); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Full Name:</span>
                        <span class="text-white"><?php echo htmlspecialchars($full_name); ?></span>
                    </div>
                </div>

                <?php if ($user_exists && !$created): ?>
                <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-lg p-4">
                    <p class="text-yellow-300 text-sm flex items-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        A user with this email already exists (ID: <?php echo $user_id; ?>). Clicking the button below will delete and recreate it.
                    </p>
                </div>
                <?php endif; ?>

                <button type="submit" class="w-full px-6 py-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition text-lg">
                    <i class="fas fa-magic mr-2"></i>
                    <?php echo $user_exists ? 'Delete & Recreate User' : 'Create Test User'; ?>
                </button>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-700 text-center">
                <a href="/" class="text-gray-400 hover:text-white text-sm">
                    <i class="fas fa-home mr-1"></i>Back to Home
                </a>
            </div>
        </div>

    </div>
</body>
</html>
