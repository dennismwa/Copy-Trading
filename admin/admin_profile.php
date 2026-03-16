<?php
/**
 * Admin Profile Management
 * Secure password and email update for system administrators
 */

require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';
$password_error = '';
$email_error = '';

// Get current admin info
$admin_id = $_SESSION['user_id'];
try {
    $stmt = $db->prepare("SELECT id, email, full_name, phone, created_at, updated_at FROM users WHERE id = ? AND is_admin = 1");
    $stmt->execute([$admin_id]);
    $admin_info = $stmt->fetch();
    
    if (!$admin_info) {
        $error = 'Admin information not found.';
    }
} catch (Exception $e) {
    error_log("Admin profile fetch error: " . $e->getMessage());
    $error = 'Failed to load profile information.';
}

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $update_type = $_POST['update_type'] ?? '';
        
        if ($update_type === 'change_email') {
            $new_email = trim($_POST['new_email'] ?? '');
            
            if (empty($new_email)) {
                $email_error = 'Email address is required.';
            } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                $email_error = 'Please enter a valid email address.';
            } else {
                // Check if email already exists
                try {
                    $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$new_email, $admin_id]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $email_error = 'This email is already registered to another account.';
                    } else {
                        $db->beginTransaction();
                        
                        // Update email
                        $stmt = $db->prepare("UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$new_email, $admin_id]);
                        
                        // Update session
                        $_SESSION['email'] = $new_email;
                        
                        // Log action
                        error_log(sprintf(
                            "ADMIN ACTION: Email updated by admin_id=%d, old_email=%s, new_email=%s at %s",
                            $admin_id,
                            $admin_info['email'],
                            $new_email,
                            date('Y-m-d H:i:s')
                        ));
                        
                        $db->commit();
                        $success = 'Email address updated successfully.';
                        
                        // Refresh admin info
                        $stmt = $db->prepare("SELECT email, full_name, phone, updated_at FROM users WHERE id = ?");
                        $stmt->execute([$admin_id]);
                        $admin_info = array_merge($admin_info, $stmt->fetch());
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $email_error = 'Failed to update email: ' . $e->getMessage();
                    error_log("Email update error: " . $e->getMessage());
                }
            }
            
        } elseif ($update_type === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $password_error = 'All password fields are required.';
            } elseif (strlen($new_password) < 8) {
                $password_error = 'Password must be at least 8 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $password_error = 'New passwords do not match.';
            } else {
                try {
                    // Verify current password
                    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$admin_id]);
                    $admin = $stmt->fetch();
                    
                    if (!$admin || !password_verify($current_password, $admin['password'])) {
                        $password_error = 'Current password is incorrect.';
                    } else {
                        $db->beginTransaction();
                        
                        // Hash and update new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$hashed_password, $admin_id]);
                        
                        // Log action
                        error_log(sprintf(
                            "ADMIN ACTION: Password changed by admin_id=%d at %s",
                            $admin_id,
                            date('Y-m-d H:i:s')
                        ));
                        
                        $db->commit();
                        $success = 'Password changed successfully. Please login again with your new password.';
                        
                        // Optional: Force logout (uncomment if you want to require re-login)
                        // session_destroy();
                        // header('Location: /login.php');
                        // exit;
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $password_error = 'Failed to change password: ' . $e->getMessage();
                    error_log("Password change error: " . $e->getMessage());
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        .glass-card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- Admin Header -->
<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-4">
                <div class="w-10 h-10 rounded-full overflow-hidden bg-gradient-to-br from-emerald-500 to-yellow-500">
                    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                </div>
                <div>
                    <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                    <p class="text-xs text-gray-400">Global - Admin Profile</p>
                </div>
            </div>
            <nav class="hidden lg:flex space-x-6">
                <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                <a href="/admin/admin-wallet.php" class="text-gray-300 hover:text-emerald-400 transition">Wallet</a>
                <a href="/admin/settings.php" class="text-gray-300 hover:text-emerald-400 transition">Settings</a>
            </nav>
            <div class="flex items-center space-x-3">
                <a href="/logout.php" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
</header>

<!-- Main Content -->
<main class="container mx-auto px-4 py-8">
    
    <!-- Page Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Admin Profile Management</h1>
        <p class="text-gray-400">Manage your account credentials securely</p>
    </div>

    <!-- Messages -->
    <?php if ($error): ?>
    <div class="mb-6 p-4 bg-red-500/20 border border-red-500/50 rounded-lg flex items-center">
        <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
        <span class="text-red-300"><?php echo htmlspecialchars($error); ?></span>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="mb-6 p-4 bg-emerald-500/20 border border-emerald-500/50 rounded-lg flex items-center">
        <i class="fas fa-check-circle text-emerald-400 mr-2"></i>
        <span class="text-emerald-300"><?php echo htmlspecialchars($success); ?></span>
    </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-2 gap-8">
        
        <!-- Change Email Section -->
        <div class="glass-card rounded-xl p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-envelope text-emerald-400 mr-2"></i>Change Email Address
                </h2>
                <p class="text-gray-400 text-sm">Update your admin email address</p>
            </div>

            <?php if ($email_error): ?>
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300 text-sm">
                <?php echo htmlspecialchars($email_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_type" value="change_email">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Current Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($admin_info['email'] ?? ''); ?>" 
                           disabled class="w-full px-4 py-3 bg-gray-800/50 border border-gray-600 rounded-lg text-gray-400">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">New Email Address *</label>
                    <input type="email" name="new_email" required
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                           placeholder="Enter new email address">
                    <p class="text-xs text-gray-500 mt-2">A valid email address is required</p>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-save mr-2"></i>Update Email
                </button>
            </form>
        </div>

        <!-- Change Password Section -->
        <div class="glass-card rounded-xl p-6">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-lock text-blue-400 mr-2"></i>Change Password
                </h2>
                <p class="text-gray-400 text-sm">Update your account password</p>
            </div>

            <?php if ($password_error): ?>
            <div class="mb-4 p-3 bg-red-500/20 border border-red-500/50 rounded-lg text-red-300 text-sm">
                <?php echo htmlspecialchars($password_error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="update_type" value="change_password">

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Current Password *</label>
                    <input type="password" name="current_password" required
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                           placeholder="Enter current password">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-300 mb-2">New Password *</label>
                    <input type="password" name="new_password" required minlength="8"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                           placeholder="Enter new password (min. 8 characters)">
                    <p class="text-xs text-gray-500 mt-2">Password must be at least 8 characters long</p>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Confirm New Password *</label>
                    <input type="password" name="confirm_password" required minlength="8"
                           class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                           placeholder="Confirm new password">
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-key mr-2"></i>Change Password
                </button>
            </form>
        </div>
    </div>

    <!-- Account Information -->
    <div class="glass-card rounded-xl p-6 mt-8">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fas fa-user-shield text-purple-400 mr-2"></i>Account Information
        </h2>

        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Full Name</p>
                <p class="text-white font-semibold"><?php echo htmlspecialchars($admin_info['full_name'] ?? 'N/A'); ?></p>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Email Address</p>
                <p class="text-white font-semibold"><?php echo htmlspecialchars($admin_info['email'] ?? 'N/A'); ?></p>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Phone Number</p>
                <p class="text-white font-semibold"><?php echo htmlspecialchars($admin_info['phone'] ?? 'N/A'); ?></p>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Account Created</p>
                <p class="text-white font-semibold">
                    <?php echo $admin_info['created_at'] ? date('M j, Y', strtotime($admin_info['created_at'])) : 'N/A'; ?>
                </p>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Last Updated</p>
                <p class="text-white font-semibold">
                    <?php echo $admin_info['updated_at'] ? date('M j, Y g:i A', strtotime($admin_info['updated_at'])) : 'N/A'; ?>
                </p>
            </div>

            <div class="bg-gray-800/50 rounded-lg p-4">
                <p class="text-gray-400 text-sm mb-1">Access Level</p>
                <p class="text-yellow-400 font-semibold flex items-center">
                    <i class="fas fa-crown mr-2"></i>Super Administrator
                </p>
            </div>
        </div>
    </div>

    <!-- Security Information -->
    <div class="glass-card rounded-xl p-6 mt-8">
        <h2 class="text-xl font-bold text-white mb-6 flex items-center">
            <i class="fas fa-shield-alt text-emerald-400 mr-2"></i>Security Features
        </h2>

        <div class="grid md:grid-cols-3 gap-4">
            <div class="bg-gradient-to-br from-emerald-500/10 to-emerald-600/10 border border-emerald-500/30 rounded-lg p-4">
                <i class="fas fa-key text-emerald-400 text-2xl mb-2"></i>
                <h3 class="font-bold text-white mb-1">Password Security</h3>
                <p class="text-sm text-gray-400">BCrypt hashing with secure password verification</p>
            </div>

            <div class="bg-gradient-to-br from-blue-500/10 to-blue-600/10 border border-blue-500/30 rounded-lg p-4">
                <i class="fas fa-user-shield text-blue-400 text-2xl mb-2"></i>
                <h3 class="font-bold text-white mb-1">Access Control</h3>
                <p class="text-sm text-gray-400">Admin-only access with session protection</p>
            </div>

            <div class="bg-gradient-to-br from-purple-500/10 to-purple-600/10 border border-purple-500/30 rounded-lg p-4">
                <i class="fas fa-history text-purple-400 text-2xl mb-2"></i>
                <h3 class="font-bold text-white mb-1">Audit Logging</h3>
                <p class="text-sm text-gray-400">All changes logged for accountability</p>
            </div>
        </div>
    </div>

</main>

<script>
// Password validation
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.querySelector('input[name="new_password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match. Please try again.');
        return false;
    }
    
    if (newPassword.length < 8) {
        e.preventDefault();
        alert('Password must be at least 8 characters long.');
        return false;
    }
    
    // Confirm password change
    if (!confirm('Are you sure you want to change your password? You will need to login again with the new password.')) {
        e.preventDefault();
        return false;
    }
});
</script>

</body>
</html>

