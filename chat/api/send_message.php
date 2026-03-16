<?php
/**
 * Send Chat Message
 * POST: Send a new message in a chat session
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid request token'], 403);
    }
    
    // Get user info
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $is_admin = isAdmin();
    $sender_role = $is_admin ? 'admin' : 'user';
    
    // Get session token for guest users
    $session_token = isset($_COOKIE['chat_session_token']) ? $_COOKIE['chat_session_token'] : null;
    
    // Get chat ID
    $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
    
    if (!$chat_id) {
        jsonResponse(['success' => false, 'error' => 'Chat ID is required'], 400);
    }
    
    // Check access
    if (!hasChatAccess($chat_id, $user_id, $session_token, $is_admin)) {
        jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    // Get message text
    $message_text = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Handle file upload if present
    $attachment_path = null;
    $attachment_type = null;
    $attachment_url = null;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file_result = saveChatFile($_FILES['attachment'], $chat_id);
        if ($file_result['success']) {
            $attachment_path = $file_result['path'];
            $attachment_type = $file_result['type'];
            $attachment_url = $file_result['url'];
        } else {
            jsonResponse(['success' => false, 'error' => $file_result['error']], 400);
        }
    }
    
    // Validate message (must have text or attachment)
    if (empty($message_text) && !$attachment_path) {
        jsonResponse(['success' => false, 'error' => 'Message text or attachment is required'], 400);
    }
    
    // If only attachment, add a default message
    if (empty($message_text) && $attachment_path) {
        $message_text = $attachment_type === 'image' ? '[Image]' : '[File]';
    }
    
    // Validate message length
    if (strlen($message_text) > 5000) {
        jsonResponse(['success' => false, 'error' => 'Message is too long (max 5000 characters)'], 400);
    }
    
    // Sanitize message text
    $message_text = sanitize($message_text);
    
    // Insert message
    $stmt = $db->prepare("
        INSERT INTO chat_messages (chat_id, sender_role, sender_id, message_text, attachment_path, attachment_type, is_read) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $chat_id,
        $sender_role,
        $user_id,
        $message_text,
        $attachment_path,
        $attachment_type,
        $is_admin ? 1 : 0 // Admin messages are auto-read
    ]);
    
    $message_id = $db->lastInsertId();
    
    // Update chat session last_message_at
    $stmt = $db->prepare("
        UPDATE chat_sessions 
        SET last_message_at = NOW(), updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$chat_id]);
    
    // Create notification for the other party
    $session = getChatSession($chat_id);
    if ($session) {
        $notify_user_type = $is_admin ? 'user' : 'admin';
        $stmt = $db->prepare("
            INSERT INTO chat_notifications (chat_id, user_type, user_id, message_id, is_read) 
            VALUES (?, ?, ?, ?, 0)
        ");
        $stmt->execute([$chat_id, $notify_user_type, $session['user_id'], $message_id]);
    }
    
    // Get the created message
    $stmt = $db->prepare("
        SELECT * FROM chat_messages WHERE id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();
    
    // Format response
    $response = [
        'success' => true,
        'message' => [
            'id' => (int)$message['id'],
            'chat_id' => (int)$message['chat_id'],
            'sender_role' => $message['sender_role'],
            'message_text' => $message['message_text'],
            'attachment_path' => $message['attachment_path'],
            'attachment_type' => $message['attachment_type'],
            'attachment_url' => $message['attachment_path'] ? CHAT_UPLOAD_URL . urlencode($message['attachment_path']) : null,
            'created_at' => $message['created_at']
        ]
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    error_log("Chat API Error (send_message): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

