<?php
/**
 * Delete Chat Session
 * POST: Permanently delete a chat session and all related data (Admin only)
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
    
    if (!$chat_id) {
        jsonResponse(['success' => false, 'error' => 'Chat ID is required'], 400);
    }
    
    // Verify chat exists
    $stmt = $db->prepare("SELECT id FROM chat_sessions WHERE id = ?");
    $stmt->execute([$chat_id]);
    $chat = $stmt->fetch();
    
    if (!$chat) {
        jsonResponse(['success' => false, 'error' => 'Chat not found'], 404);
    }
    
    // Get attachment files to delete
    $stmt = $db->prepare("
        SELECT attachment_path 
        FROM chat_messages 
        WHERE chat_id = ? AND attachment_path IS NOT NULL
    ");
    $stmt->execute([$chat_id]);
    $attachments = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Start transaction
    $db->beginTransaction();
    
    try {
        // Delete typing indicators
        $stmt = $db->prepare("DELETE FROM chat_typing_indicators WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        
        // Delete notifications
        $stmt = $db->prepare("DELETE FROM chat_notifications WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        
        // Delete messages (this will also handle attachment cleanup if CASCADE is set)
        $stmt = $db->prepare("DELETE FROM chat_messages WHERE chat_id = ?");
        $stmt->execute([$chat_id]);
        
        // Delete chat session
        $stmt = $db->prepare("DELETE FROM chat_sessions WHERE id = ?");
        $stmt->execute([$chat_id]);
        
        // Commit transaction
        $db->commit();
        
        // Delete attachment files from filesystem
        foreach ($attachments as $attachment_path) {
            if ($attachment_path) {
                $file_path = CHAT_UPLOAD_DIR . basename($attachment_path);
                if (file_exists($file_path)) {
                    @unlink($file_path);
                }
            }
        }
        
        jsonResponse([
            'success' => true,
            'message' => 'Chat deleted successfully'
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Chat API Error (delete_chat): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

