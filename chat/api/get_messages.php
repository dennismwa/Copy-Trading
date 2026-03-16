<?php
/**
 * Get Chat Messages
 * GET: Retrieve messages for a chat session
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Get user info
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $is_admin = isAdmin();
    
    // Get session token for guest users
    $session_token = isset($_COOKIE['chat_session_token']) ? $_COOKIE['chat_session_token'] : null;
    
    // Get chat ID
    $chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
    $last_message_id = isset($_GET['last_message_id']) ? (int)$_GET['last_message_id'] : 0;
    
    if (!$chat_id) {
        jsonResponse(['success' => false, 'error' => 'Chat ID is required'], 400);
    }
    
    // Check access
    if (!hasChatAccess($chat_id, $user_id, $session_token, $is_admin)) {
        jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    // Build query to get new messages
    if ($last_message_id > 0) {
        // Get only new messages since last_message_id
        $stmt = $db->prepare("
            SELECT * FROM chat_messages 
            WHERE chat_id = ? AND id > ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$chat_id, $last_message_id]);
    } else {
        // Get all messages (initial load)
        $stmt = $db->prepare("
            SELECT * FROM chat_messages 
            WHERE chat_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$chat_id]);
    }
    
    $messages = $stmt->fetchAll();
    
    // Mark messages as read for current user
    if ($messages && count($messages) > 0) {
        $message_ids = array_column($messages, 'id');
        if (count($message_ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($message_ids), '?'));
            
            // Mark as read if user is viewing (not admin viewing user messages)
            $read_role = $is_admin ? 'user' : 'admin';
            $stmt = $db->prepare("
                UPDATE chat_messages 
                SET is_read = 1 
                WHERE chat_id = ? AND sender_role = ? AND id IN ($placeholders)
            ");
            $params = array_merge([$chat_id, $read_role], $message_ids);
            $stmt->execute($params);
            
            // Update notifications
            $stmt = $db->prepare("
                UPDATE chat_notifications 
                SET is_read = 1 
                WHERE chat_id = ? AND user_type = ? AND message_id IN ($placeholders)
            ");
            $notify_user_type = $is_admin ? 'admin' : 'user';
            $params = array_merge([$chat_id, $notify_user_type], $message_ids);
            $stmt->execute($params);
        }
    }
    
    // Format messages
    $formatted_messages = array_map(function($msg) {
        return [
            'id' => (int)$msg['id'],
            'chat_id' => (int)$msg['chat_id'],
            'sender_role' => $msg['sender_role'],
            'message_text' => $msg['message_text'],
            'attachment_path' => $msg['attachment_path'],
            'attachment_type' => $msg['attachment_type'],
            'attachment_url' => $msg['attachment_path'] ? CHAT_UPLOAD_URL . urlencode($msg['attachment_path']) : null,
            'is_read' => (bool)$msg['is_read'],
            'created_at' => $msg['created_at']
        ];
    }, $messages);
    
    // Get typing indicators
    $stmt = $db->prepare("
        SELECT user_type, user_id 
        FROM chat_typing_indicators 
        WHERE chat_id = ? AND expires_at > NOW()
        GROUP BY user_type
    ");
    $stmt->execute([$chat_id]);
    $typing_indicators = $stmt->fetchAll();
    
    $typing = [];
    foreach ($typing_indicators as $indicator) {
        if ($indicator['user_type'] !== ($is_admin ? 'admin' : 'user')) {
            $typing[] = $indicator['user_type'];
        }
    }
    
    jsonResponse([
        'success' => true,
        'messages' => $formatted_messages,
        'typing' => array_unique($typing),
        'last_message_id' => !empty($messages) ? (int)end($messages)['id'] : $last_message_id
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error (get_messages): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

