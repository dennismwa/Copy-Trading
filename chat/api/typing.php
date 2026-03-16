<?php
/**
 * Update Typing Indicator
 * POST: Set typing indicator when user is typing
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Get user info
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $is_admin = isAdmin();
    $user_type = $is_admin ? 'admin' : 'user';
    
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
    
    // Delete existing typing indicators for this user
    $stmt = $db->prepare("
        DELETE FROM chat_typing_indicators 
        WHERE chat_id = ? AND user_type = ? AND user_id = ?
    ");
    $stmt->execute([$chat_id, $user_type, $user_id]);
    
    // Insert new typing indicator (expires in CHAT_TYPING_TIMEOUT seconds)
    $expires_at = date('Y-m-d H:i:s', time() + CHAT_TYPING_TIMEOUT);
    $stmt = $db->prepare("
        INSERT INTO chat_typing_indicators (chat_id, user_type, user_id, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$chat_id, $user_type, $user_id, $expires_at]);
    
    jsonResponse(['success' => true]);
    
} catch (Exception $e) {
    error_log("Chat API Error (typing): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

