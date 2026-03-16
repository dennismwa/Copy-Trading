<?php
/**
 * Create or Get Chat Session
 * POST: Create new session or get existing open session
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Get user info
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $is_admin = isAdmin();
    
    // For guest users, get or create session token
    $session_token = null;
    if (!$user_id) {
        // Check if session token exists in cookie
        if (isset($_COOKIE['chat_session_token'])) {
            $session_token = $_COOKIE['chat_session_token'];
        } else {
            // Generate new token
            $session_token = generateChatSessionToken();
            // Set cookie (30 days)
            setcookie('chat_session_token', $session_token, time() + CHAT_SESSION_TIMEOUT, '/', '', false, true);
        }
    }
    
    // Get guest info from POST if provided
    $guest_name = isset($_POST['guest_name']) ? sanitize($_POST['guest_name']) : null;
    $guest_email = isset($_POST['guest_email']) ? filter_var($_POST['guest_email'], FILTER_SANITIZE_EMAIL) : null;
    
    // Validate email if provided
    if ($guest_email && !filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['success' => false, 'error' => 'Invalid email address'], 400);
    }
    
    // Get or create session
    $session = getOrCreateChatSession($user_id, $guest_name, $guest_email, $session_token);
    
    if (!$session) {
        jsonResponse(['success' => false, 'error' => 'Failed to create chat session'], 500);
    }
    
    // Return session info
    jsonResponse([
        'success' => true,
        'session' => [
            'id' => (int)$session['id'],
            'session_token' => $session['session_token'],
            'status' => $session['status'],
            'created_at' => $session['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Chat API Error (create_session): " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Internal server error'], 500);
}

