<?php
/**
 * Close Chat Session
 * POST: Close or archive a chat session (Admin only)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Admin only
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid request token'], 403);
    }
    
    // Get chat ID
    $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
    $action = isset($_POST['action']) ? sanitize($_POST['action']) : 'close';
    
    if (!$chat_id) {
        jsonResponse(['success' => false, 'error' => 'Chat ID is required'], 400);
    }
    
    // Validate action
    if (!in_array($action, ['close', 'archive', 'reopen'])) {
        $action = 'close';
    }
    
    $status = $action === 'reopen' ? 'open' : $action;
    
    // Update chat status
    $stmt = $db->prepare("
        UPDATE chat_sessions 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$status, $chat_id]);
    
    jsonResponse([
        'success' => true,
        'message' => "Chat {$action}d successfully"
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error (close_chat): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

