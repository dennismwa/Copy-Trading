<?php
/**
 * Chat Widget Loader
 * Include this file in your pages to load the chat widget
 * 
 * Usage: <?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
 */

// Get CSRF token
$csrf_token = generateCSRFToken();

// Check if user is logged in
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
?>
<!-- Live Chat Widget -->
<link rel="stylesheet" href="/chat/widget/chat-widget.css">
<script>
    // Set CSRF token for JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = '<?php echo $csrf_token; ?>';
        document.head.appendChild(meta);
        
        // Set user ID if logged in (for widget to skip form)
        <?php if ($user_id): ?>
        document.body.dataset.userId = '<?php echo $user_id; ?>';
        window.userId = <?php echo $user_id; ?>;
        <?php endif; ?>
    });
</script>
<script src="/chat/widget/chat-widget.js"></script>

