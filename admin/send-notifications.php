<?php
require_once '../config/database.php';
requireAdmin();

$error = '';
$success = '';

// Handle notification sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        
        if ($action === 'send_notification') {
            $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
            $message = trim(isset($_POST['message']) ? $_POST['message'] : '');
            $type = isset($_POST['type']) ? $_POST['type'] : 'info';
            $recipient_type = isset($_POST['recipient_type']) ? $_POST['recipient_type'] : 'all';
            $selected_users = isset($_POST['selected_users']) ? $_POST['selected_users'] : [];
            
            if (empty($title) || empty($message)) {
                $error = 'Please provide both title and message.';
            } else {
                try {
                    $db->beginTransaction();
                    $admin_id = $_SESSION['user_id'];
                    $sent_count = 0;
                    
                    if ($recipient_type === 'all') {
                        // Send to all users (broadcast)
                        $stmt = $db->prepare("
                            INSERT INTO notifications (user_id, title, message, type, is_global, created_at)
                            VALUES (NULL, ?, ?, ?, 1, NOW())
                        ");
                        $stmt->execute(array($title, $message, $type));
                        $sent_count = 'all';
                        
                        error_log(sprintf(
                            "ADMIN ACTION: Broadcast notification sent by admin_id=%d, title=%s, type=%s at %s",
                            $admin_id,
                            $title,
                            $type,
                            date('Y-m-d H:i:s')
                        ));
                    } else {
                        // Send to selected users
                        foreach ($selected_users as $user_id) {
                            $user_id = (int)$user_id;
                            if ($user_id > 0) {
                                $stmt = $db->prepare("
                                    INSERT INTO notifications (user_id, title, message, type, is_global, created_at)
                                    VALUES (?, ?, ?, ?, 0, NOW())
                                ");
                                $stmt->execute(array($user_id, $title, $message, $type));
                                $sent_count++;
                            }
                        }
                        
                        error_log(sprintf(
                            "ADMIN ACTION: Notification sent to %d users by admin_id=%d, title=%s, type=%s at %s",
                            $sent_count,
                            $admin_id,
                            $title,
                            $type,
                            date('Y-m-d H:i:s')
                        ));
                    }
                    
                    $db->commit();
                    
                    if ($sent_count === 'all') {
                        $success = 'Notification sent to all users successfully.';
                    } else {
                        $success = "Notification sent to {$sent_count} user(s) successfully.";
                    }
                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log("Notification sending failed: " . $e->getMessage());
                    $error = 'Failed to send notification: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get all users for selection
$users = [];
try {
    $stmt = $db->query("
        SELECT id, full_name, email, phone, created_at 
        FROM users 
        WHERE is_admin = 0 
        ORDER BY created_at DESC
    ");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load users: " . $e->getMessage());
}

// Get recent notifications for preview
$recent_notifications = [];
try {
    $stmt = $db->query("
        SELECT n.*, u.full_name as recipient_name
        FROM notifications n
        LEFT JOIN users u ON n.user_id = u.id
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Failed to load recent notifications: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - Ultra Harvest Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
        * { font-family: 'Poppins', sans-serif; }
        .glass-card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">

<!-- Reusable Admin Header -->
<header class="bg-gray-800/50 backdrop-blur-md border-b border-gray-700 sticky top-0 z-50">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <div class="flex items-center space-x-8">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-full overflow-hidden" style="background: linear-gradient(45deg, #10b981, #fbbf24);">
                        <img src="/ultra%20Harvest%20Logo.jpg" alt="Ultra Harvest Global" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                    </div>
                    <div>
                        <span class="text-xl font-bold bg-gradient-to-r from-emerald-400 to-yellow-400 bg-clip-text text-transparent">Ultra Harvest</span>
                        <p class="text-xs text-gray-400">Global - Admin</p>
                    </div>
                </div>
                <nav class="hidden lg:flex space-x-6">
                    <a href="/admin/" class="text-gray-300 hover:text-emerald-400 transition">Dashboard</a>
                    <a href="/admin/users.php" class="text-gray-300 hover:text-emerald-400 transition">Users</a>
                    <a href="/admin/packages.php" class="text-gray-300 hover:text-emerald-400 transition">Packages</a>
                    <a href="/admin/active-trades.php" class="text-gray-300 hover:text-emerald-400 transition">Active Trades</a>
                    <a href="/admin/transactions.php" class="text-gray-300 hover:text-emerald-400 transition">Transactions</a>
                    <a href="/admin/settings.php" class="text-emerald-400 transition">Settings</a>
                </nav>
            </div>

            <div class="flex items-center space-x-3">
                <a href="/admin/admin-wallet.php" class="relative group">
                    <div class="flex items-center space-x-2 px-3 sm:px-4 py-2 rounded-lg bg-gradient-to-r from-emerald-500/10 to-yellow-500/10 border border-emerald-500/20 hover:border-emerald-500/40 transition-all duration-300">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                        <span class="hidden sm:inline text-sm font-medium text-emerald-400">Wallet</span>
                    </div>
                </a>
                <a href="/logout.php" class="hidden md:flex items-center space-x-2 px-4 py-2 rounded-lg bg-gradient-to-r from-red-500/10 to-red-600/10 border border-red-500/20 hover:border-red-500/40 transition-all duration-300">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                    </svg>
                    <span class="text-sm font-medium text-red-400">Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

<main class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-white mb-2">Send Notifications</h1>
        <p class="text-gray-400">Send messages to all users or selected individuals</p>
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

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Send Notification Form -->
        <div class="glass-card rounded-xl p-6">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-paper-plane text-emerald-400 mr-2"></i>Create Notification
            </h2>

            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <input type="hidden" name="action" value="send_notification">

                <!-- Recipient Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-3">Recipient Type</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center space-x-3 p-4 bg-gray-800/50 rounded-lg cursor-pointer hover:bg-gray-700/50 transition recipient-type-group" data-type="all">
                            <input type="radio" name="recipient_type" value="all" checked class="text-emerald-600 focus:ring-emerald-500" onchange="toggleUserSelection()">
                            <div>
                                <div class="font-medium text-white">All Users</div>
                                <div class="text-sm text-gray-400">Broadcast message</div>
                            </div>
                        </label>
                        <label class="flex items-center space-x-3 p-4 bg-gray-800/50 rounded-lg cursor-pointer hover:bg-gray-700/50 transition recipient-type-group" data-type="selected">
                            <input type="radio" name="recipient_type" value="selected" class="text-emerald-600 focus:ring-emerald-500" onchange="toggleUserSelection()">
                            <div>
                                <div class="font-medium text-white">Select Users</div>
                                <div class="text-sm text-gray-400">Choose recipients</div>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- User Selection (hidden by default) -->
                <div id="user-selection" class="hidden">
                    <label class="block text-sm font-medium text-gray-300 mb-3">Select Users</label>
                    <div class="bg-gray-800/50 rounded-lg p-4 max-h-60 overflow-y-auto">
                        <?php foreach ($users as $user): ?>
                        <label class="flex items-center space-x-3 p-2 hover:bg-gray-700/50 rounded cursor-pointer">
                            <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="text-emerald-600 focus:ring-emerald-500">
                            <div class="flex-1">
                                <div class="text-white font-medium"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                <div class="text-sm text-gray-400"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Notification Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Notification Type</label>
                    <select name="type" class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none">
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Title *</label>
                    <input 
                        type="text" 
                        name="title" 
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                        placeholder="Enter notification title"
                        required
                    >
                </div>

                <!-- Message -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Message *</label>
                    <textarea 
                        name="message" 
                        rows="6"
                        class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white focus:border-emerald-500 focus:outline-none"
                        placeholder="Enter your message here"
                        required
                    ></textarea>
                </div>

                <button type="submit" class="w-full px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium transition">
                    <i class="fas fa-paper-plane mr-2"></i>Send Notification
                </button>
            </form>
        </div>

        <!-- Recent Notifications -->
        <div class="glass-card rounded-xl p-6">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-history text-blue-400 mr-2"></i>Recent Notifications
            </h2>

            <?php if (empty($recent_notifications)): ?>
                <div class="text-center py-8">
                    <i class="fas fa-inbox text-4xl text-gray-600 mb-4"></i>
                    <p class="text-gray-400">No notifications sent yet</p>
                </div>
            <?php else: ?>
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php foreach ($recent_notifications as $notif): ?>
                    <div class="bg-gray-800/50 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-1 rounded text-xs font-medium <?php 
                                echo match($notif['type']) {
                                    'success' => 'bg-emerald-500/20 text-emerald-400',
                                    'warning' => 'bg-yellow-500/20 text-yellow-400',
                                    'error' => 'bg-red-500/20 text-red-400',
                                    default => 'bg-blue-500/20 text-blue-400'
                                };
                                ?>">
                                    <?php echo ucfirst($notif['type']); ?>
                                </span>
                                <?php if ($notif['is_global']): ?>
                                <span class="px-2 py-1 rounded text-xs font-medium bg-purple-500/20 text-purple-400">
                                    Broadcast
                                </span>
                                <?php endif; ?>
                            </div>
                            <span class="text-xs text-gray-500"><?php echo timeAgo($notif['created_at']); ?></span>
                        </div>
                        <h3 class="font-bold text-white mb-1"><?php echo htmlspecialchars($notif['title']); ?></h3>
                        <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($notif['message']); ?></p>
                        <?php if ($notif['recipient_name']): ?>
                        <p class="text-xs text-gray-500 mt-2">To: <?php echo htmlspecialchars($notif['recipient_name']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
function toggleUserSelection() {
    const selectedType = document.querySelector('input[name="recipient_type"]:checked').value;
    const userSelection = document.getElementById('user-selection');
    
    if (selectedType === 'selected') {
        userSelection.classList.remove('hidden');
    } else {
        userSelection.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleUserSelection();
});
</script>
</body>
</html>

