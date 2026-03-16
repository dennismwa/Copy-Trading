<?php
require_once 'config/database.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
$success = '';
$token_valid = false;
$user_data = null;

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $error = 'Invalid password reset link.';
} else {
    $token = $_GET['token'];
    $token_hash = hash('sha256', $token);
    
    // Verify token
    $stmt = $db->prepare("
        SELECT prt.*, u.id as user_id, u.email, u.full_name 
        FROM password_reset_tokens prt
        JOIN users u ON prt.user_id = u.id
        WHERE prt.token_hash = ? 
        AND prt.expires_at > NOW() 
        AND prt.used = 0
    ");
    $stmt->execute([$token_hash]);
    $user_data = $stmt->fetch();
    
    if ($user_data) {
        $token_valid = true;
    } else {
        $error = 'This password reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valid) {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($new_password)) {
        $error = 'Please enter a new password.';
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update user password
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$password_hash, $user_data['user_id']])) {
            // Mark token as used
            $stmt = $db->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token_hash = ?");
            $stmt->execute([$token_hash]);
            
            // Send confirmation email
            $to = $user_data['email'];
            $subject = "Password Changed Successfully - " . SITE_NAME;
            
            $message = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #10b981 0%, #fbbf24 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { display: inline-block; background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                    .info-box { background: #dbeafe; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; }
                    .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>✓ Password Changed Successfully</h1>
                    </div>
                    <div class='content'>
                        <p>Hello " . htmlspecialchars($user_data['full_name']) . ",</p>
                        
                        <p>Your password has been changed successfully!</p>
                        
                        <div class='info-box'>
                            <strong>📧 Email:</strong> " . htmlspecialchars($user_data['email']) . "<br>
                            <strong>🕐 Changed at:</strong> " . date('F j, Y - g:i A (T)') . "
                        </div>
                        
                        <p>You can now login to your account using your new password.</p>
                        
                        <center>
                            <a href='" . SITE_URL . "login.php' class='button'>Login Now</a>
                        </center>
                        
                        <p style='color: #ef4444; font-weight: bold;'>⚠️ Security Notice:</p>
                        <p>If you did not make this change, please contact our support team immediately at support@theultraharvest.com</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: " . SITE_NAME . " <noreply@theultraharvest.com>" . "\r\n";
            
            mail($to, $subject, $message, $headers);
            
            // Log the password change
            sendNotification($user_data['user_id'], 'Password Changed', 'Your account password was changed successfully.', 'info');
            
            $success = true;
        } else {
            $error = 'Failed to update password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .password-strength {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }
    </style>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">
    
    <div class="w-full max-w-md">
        <!-- Logo -->
        <div class="text-center mb-8">
            <a href="/" class="inline-block">
                <div class="w-20 h-20 mx-auto rounded-full overflow-hidden mb-4" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                    <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <h1 class="text-2xl font-bold text-white">Ultra Harvest Global</h1>
            </a>
        </div>

        <!-- Main Card -->
        <div class="bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 p-8">
            
            <?php if ($success): ?>
                <!-- Success State -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-3">Password Changed!</h2>
                    <div class="bg-green-500/10 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check mr-2"></i>Your password has been changed successfully.
                    </div>
                    
                    <p class="text-gray-400 mb-6">You can now login with your new password.</p>
                    
                    <a href="/login.php" class="inline-block w-full bg-gradient-to-r from-emerald-500 to-yellow-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login Now
                    </a>
                    
                    <div class="bg-blue-500/10 border border-blue-500 text-blue-400 px-4 py-3 rounded-lg mt-6 text-sm">
                        <i class="fas fa-envelope mr-2"></i>A confirmation email has been sent to your email address.
                    </div>
                </div>
                
            <?php elseif (!$token_valid): ?>
                <!-- Invalid Token -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-times-circle text-red-400 text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-3">Invalid Link</h2>
                    <div class="bg-red-500/10 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    </div>
                    
                    <p class="text-gray-400 mb-6">Password reset links expire after 1 hour for security reasons.</p>
                    
                    <a href="/forgot-password.php" class="inline-block w-full bg-gradient-to-r from-emerald-500 to-yellow-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition">
                        <i class="fas fa-redo mr-2"></i>Request New Link
                    </a>
                </div>
                
            <?php else: ?>
                <!-- Reset Password Form -->
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-key text-white text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">Create New Password</h2>
                    <p class="text-gray-400">Enter a strong password for your account</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="resetForm">
                    
                    <div class="mb-4">
                        <label class="block text-gray-300 mb-2 font-medium">New Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                required 
                                minlength="8"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg pl-12 pr-12 py-3 focus:outline-none focus:border-emerald-500 transition"
                                placeholder="Enter new password"
                            >
                            <button type="button" onclick="togglePassword('password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye" id="password-eye"></i>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div class="password-strength bg-gray-700" id="strength-bar"></div>
                            <p class="text-xs text-gray-400 mt-1" id="strength-text">Password strength</p>
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2 font-medium">Confirm Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirm_password"
                                required 
                                minlength="8"
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg pl-12 pr-12 py-3 focus:outline-none focus:border-emerald-500 transition"
                                placeholder="Re-enter new password"
                            >
                            <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                                <i class="fas fa-eye" id="confirm_password-eye"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Password Requirements -->
                    <div class="bg-gray-700/50 rounded-lg p-4 mb-6">
                        <p class="text-white font-semibold text-sm mb-2">Password must contain:</p>
                        <ul class="text-gray-300 text-xs space-y-1">
                            <li id="req-length"><i class="fas fa-circle text-gray-500 text-xs mr-2"></i>At least 8 characters</li>
                            <li id="req-uppercase"><i class="fas fa-circle text-gray-500 text-xs mr-2"></i>One uppercase letter</li>
                            <li id="req-lowercase"><i class="fas fa-circle text-gray-500 text-xs mr-2"></i>One lowercase letter</li>
                            <li id="req-number"><i class="fas fa-circle text-gray-500 text-xs mr-2"></i>One number</li>
                        </ul>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-yellow-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition mb-4">
                        <i class="fas fa-check mr-2"></i>Change Password
                    </button>
                </form>
            <?php endif; ?>

            <!-- Back to Login -->
            <div class="text-center pt-4 border-t border-gray-700">
                <a href="/login.php" class="text-emerald-400 hover:text-emerald-300 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Login
                </a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.classList.remove('fa-eye');
                eye.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                eye.classList.remove('fa-eye-slash');
                eye.classList.add('fa-eye');
            }
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                const strengthBar = document.getElementById('strength-bar');
                const strengthText = document.getElementById('strength-text');
                
                // Check requirements
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                
                // Update requirement indicators
                updateRequirement('req-length', hasLength);
                updateRequirement('req-uppercase', hasUppercase);
                updateRequirement('req-lowercase', hasLowercase);
                updateRequirement('req-number', hasNumber);
                
                // Calculate strength
                let strength = 0;
                if (hasLength) strength++;
                if (hasUppercase) strength++;
                if (hasLowercase) strength++;
                if (hasNumber) strength++;
                
                // Update strength bar
                strengthBar.className = 'password-strength';
                if (strength <= 2) {
                    strengthBar.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                    strengthText.className = 'text-xs text-red-400 mt-1';
                } else if (strength === 3) {
                    strengthBar.classList.add('strength-medium');
                    strengthText.textContent = 'Medium password';
                    strengthText.className = 'text-xs text-yellow-400 mt-1';
                } else {
                    strengthBar.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                    strengthText.className = 'text-xs text-green-400 mt-1';
                }
            });
        }
        
        function updateRequirement(id, met) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');
            
            if (met) {
                icon.classList.remove('fa-circle', 'text-gray-500');
                icon.classList.add('fa-check-circle', 'text-green-400');
                element.classList.remove('text-gray-300');
                element.classList.add('text-green-400');
            } else {
                icon.classList.remove('fa-check-circle', 'text-green-400');
                icon.classList.add('fa-circle', 'text-gray-500');
                element.classList.remove('text-green-400');
                element.classList.add('text-gray-300');
            }
        }

        // Form validation
        const form = document.getElementById('resetForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return false;
                }
                
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    return false;
                }
            });
        }
    </script>

</body>
</html>