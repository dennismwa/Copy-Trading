<?php
require_once 'config/database.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /user/dashboard.php');
    exit;
}

$error = '';
$success = '';
$step = 'email'; // email, verification, or success

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'request_reset') {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Check if email exists
            $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate secure token
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $db->prepare("
                    INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at), used = 0
                ");
                $stmt->execute([$user['id'], $token_hash, $expires_at]);
                
                // Send email with reset link
                $reset_link = SITE_URL . "reset-password.php?token=" . $token;
                
                $to = $user['email'];
                $subject = "Password Reset Request - " . SITE_NAME;
                
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #10b981 0%, #fbbf24 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
                        .button { display: inline-block; background: #10b981; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
                        .warning { background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Password Reset Request</h1>
                        </div>
                        <div class='content'>
                            <p>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                            
                            <p>We received a request to reset your password for your " . SITE_NAME . " account.</p>
                            
                            <p>Click the button below to reset your password:</p>
                            
                            <center>
                                <a href='" . $reset_link . "' class='button'>Reset Password</a>
                            </center>
                            
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all; background: #e5e7eb; padding: 10px; border-radius: 5px;'>" . $reset_link . "</p>
                            
                            <div class='warning'>
                                <strong>⚠️ Security Notice:</strong><br>
                                This link will expire in 1 hour.<br>
                                If you didn't request this password reset, please ignore this email or contact support if you have concerns.
                            </div>
                            
                            <p>For security reasons, this link can only be used once.</p>
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
                
                if (mail($to, $subject, $message, $headers)) {
                    $success = "Password reset instructions have been sent to your email address.";
                    $step = 'success';
                } else {
                    $error = "Failed to send email. Please try again or contact support.";
                }
            } else {
                // For security, show same message even if email doesn't exist
                $success = "If an account exists with this email, password reset instructions have been sent.";
                $step = 'success';
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
    <title>Forgot Password - Ultra Harvest Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        
        .gradient-bg {
            background: linear-gradient(135deg, #10b981 0%, #fbbf24 100%);
        }
        
        .glass-card {
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
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
            
            <?php if ($step === 'email'): ?>
                <!-- Request Reset Form -->
                <div class="text-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-r from-emerald-500 to-yellow-500 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-white text-2xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-2">Forgot Password?</h2>
                    <p class="text-gray-400">Enter your email address and we'll send you instructions to reset your password.</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-500/10 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action" value="request_reset">
                    
                    <div class="mb-6">
                        <label class="block text-gray-300 mb-2 font-medium">Email Address</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input 
                                type="email" 
                                name="email" 
                                required 
                                class="w-full bg-gray-700 text-white border border-gray-600 rounded-lg pl-12 pr-4 py-3 focus:outline-none focus:border-emerald-500 transition"
                                placeholder="Enter your email"
                            >
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-500 to-yellow-500 text-white font-bold py-3 rounded-lg hover:opacity-90 transition mb-4">
                        <i class="fas fa-paper-plane mr-2"></i>Send Reset Instructions
                    </button>
                </form>

            <?php elseif ($step === 'success'): ?>
                <!-- Success Message -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-green-400 text-3xl"></i>
                    </div>
                    <h2 class="text-2xl font-bold text-white mb-3">Check Your Email</h2>
                    <div class="bg-green-500/10 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-info-circle mr-2"></i><?php echo $success; ?>
                    </div>
                    <p class="text-gray-400 mb-6">Please check your email inbox (and spam folder) for password reset instructions.</p>
                    
                    <div class="bg-gray-700/50 rounded-lg p-4 mb-6 text-left">
                        <h3 class="text-white font-semibold mb-2">What to do next:</h3>
                        <ul class="text-gray-300 text-sm space-y-2">
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i>Check your email inbox</li>
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i>Click the reset link (valid for 1 hour)</li>
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i>Create a new password</li>
                            <li><i class="fas fa-check text-emerald-400 mr-2"></i>Login with your new password</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Back to Login -->
            <div class="text-center pt-4 border-t border-gray-700">
                <a href="/login.php" class="text-emerald-400 hover:text-emerald-300 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Login
                </a>
            </div>
        </div>

        <!-- Additional Help -->
        <div class="text-center mt-6">
            <p class="text-gray-400 text-sm">
                Still having trouble? 
                <a href="/user/support.php" class="text-emerald-400 hover:text-emerald-300">Contact Support</a>
            </p>
        </div>
    </div>

</body>
</html>