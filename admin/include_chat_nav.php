<?php
/**
 * Helper function to get unread chat count for admin navigation
 * Include this in admin pages to show unread chat badge
 */
function getUnreadChatCount() {
    global $db;
    try {
        // Count all unread chats regardless of status (not just open)
        $stmt = $db->query("
            SELECT COUNT(DISTINCT cm.chat_id) as unread_count
            FROM chat_messages cm
            JOIN chat_sessions cs ON cm.chat_id = cs.id
            WHERE cm.sender_role = 'user' AND cm.is_read = 0
        ");
        $result = $stmt->fetch();
        return (int)($result['unread_count'] ?? 0);
    } catch (Exception $e) {
        return 0;
    }
}

$unread_chat_count = getUnreadChatCount();
?>

