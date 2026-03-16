<?php
/**
 * Chat System Configuration
 * Live Chat Support System
 */

// Include main database configuration
require_once __DIR__ . '/../config/database.php';

// Chat-specific configuration
define('CHAT_UPLOAD_DIR', __DIR__ . '/uploads/');
define('CHAT_UPLOAD_URL', '/chat/uploads/index.php?file=');
define('CHAT_MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('CHAT_ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf', 'text/plain']);
define('CHAT_POLL_INTERVAL', 2000); // 2 seconds in milliseconds
define('CHAT_TYPING_TIMEOUT', 3); // 3 seconds
define('CHAT_SESSION_TIMEOUT', 30 * 24 * 60 * 60); // 30 days

// Create upload directory if it doesn't exist
if (!file_exists(CHAT_UPLOAD_DIR)) {
    mkdir(CHAT_UPLOAD_DIR, 0755, true);
    // Create .htaccess to prevent direct access
    file_put_contents(CHAT_UPLOAD_DIR . '.htaccess', "deny from all\n");
}

/**
 * Generate unique session token for guest users
 */
function generateChatSessionToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Get or create chat session for user
 */
function getOrCreateChatSession($user_id = null, $guest_name = null, $guest_email = null, $session_token = null) {
    global $db;
    
    try {
        // If user is logged in, try to find existing open session
        if ($user_id) {
            $stmt = $db->prepare("
                SELECT * FROM chat_sessions 
                WHERE user_id = ? AND status = 'open' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $session = $stmt->fetch();
            
            if ($session) {
                return $session;
            }
        } 
        // If guest user with session token, try to find existing session
        elseif ($session_token) {
            $stmt = $db->prepare("
                SELECT * FROM chat_sessions 
                WHERE session_token = ? AND status = 'open' 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$session_token]);
            $session = $stmt->fetch();
            
            if ($session) {
                return $session;
            }
        }
        
        // Create new session
        $new_token = $session_token ?: generateChatSessionToken();
        $stmt = $db->prepare("
            INSERT INTO chat_sessions (user_id, guest_name, guest_email, session_token, status) 
            VALUES (?, ?, ?, ?, 'open')
        ");
        $stmt->execute([$user_id, $guest_name, $guest_email, $new_token]);
        
        $session_id = $db->lastInsertId();
        
        // Fetch the created session
        $stmt = $db->prepare("SELECT * FROM chat_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        return $stmt->fetch();
        
    } catch (Exception $e) {
        error_log("Chat: Failed to get/create session - " . $e->getMessage());
        return false;
    }
}

/**
 * Get chat session by ID
 */
function getChatSession($chat_id) {
    global $db;
    
    try {
        $stmt = $db->prepare("SELECT * FROM chat_sessions WHERE id = ?");
        $stmt->execute([$chat_id]);
        return $stmt->fetch();
    } catch (Exception $e) {
        error_log("Chat: Failed to get session - " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has access to chat session
 */
function hasChatAccess($chat_id, $user_id = null, $session_token = null, $is_admin = false) {
    global $db;
    
    // Admins have access to all chats
    if ($is_admin) {
        return true;
    }
    
    try {
        $session = getChatSession($chat_id);
        if (!$session) {
            return false;
        }
        
        // Check if logged-in user owns this chat
        if ($user_id && $session['user_id'] == $user_id) {
            return true;
        }
        
        // Check if guest session token matches
        if ($session_token && $session['session_token'] == $session_token) {
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Chat: Failed to check access - " . $e->getMessage());
        return false;
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'No file uploaded'];
    }
    
    if ($file['size'] > CHAT_MAX_FILE_SIZE) {
        return ['valid' => false, 'error' => 'File size exceeds maximum allowed size (5MB)'];
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mime_type, CHAT_ALLOWED_FILE_TYPES)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    return ['valid' => true, 'mime_type' => $mime_type];
}

/**
 * Save uploaded file
 */
function saveChatFile($file, $chat_id) {
    $validation = validateFileUpload($file);
    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('chat_' . $chat_id . '_', true) . '.' . $extension;
    $filepath = CHAT_UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true,
            'path' => $filename,
            'url' => CHAT_UPLOAD_URL . $filename,
            'type' => strpos($validation['mime_type'], 'image/') === 0 ? 'image' : 'file',
            'original_name' => $file['name']
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file'];
}

