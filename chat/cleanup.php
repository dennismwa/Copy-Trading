<?php
/**
 * Chat System Cleanup Utility
 * Run this periodically (via cron) to clean up expired data
 * 
 * Recommended: Run every hour via cron
 * Example cron: 0 * * * * /usr/bin/php /path/to/chat/cleanup.php
 */

require_once __DIR__ . '/config.php';

echo "Starting chat system cleanup...\n";

try {
    // Clean up expired typing indicators
    $stmt = $db->prepare("DELETE FROM chat_typing_indicators WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted_typing = $stmt->rowCount();
    echo "Deleted $deleted_typing expired typing indicators\n";
    
    // Clean up old notifications (older than 30 days and read)
    $stmt = $db->prepare("
        DELETE FROM chat_notifications 
        WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $deleted_notifications = $stmt->rowCount();
    echo "Deleted $deleted_notifications old read notifications\n";
    
    // Optional: Archive very old closed chats (older than 90 days)
    // Uncomment if you want to auto-archive old chats
    /*
    $stmt = $db->prepare("
        UPDATE chat_sessions 
        SET status = 'archived' 
        WHERE status = 'closed' 
        AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->execute();
    $archived = $stmt->rowCount();
    echo "Archived $archived old closed chats\n";
    */
    
    echo "Cleanup completed successfully!\n";
    
} catch (Exception $e) {
    error_log("Chat cleanup error: " . $e->getMessage());
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

