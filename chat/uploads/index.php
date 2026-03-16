<?php
/**
 * Secure File Serving
 * Serves uploaded chat files with proper access control
 */

require_once __DIR__ . '/../config.php';

// Get file name from query string
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';

if (empty($filename)) {
    http_response_code(404);
    die('File not found');
}

$filepath = CHAT_UPLOAD_DIR . $filename;

// Check if file exists
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('File not found');
}

// Verify user has access to this file
// Get chat_id from filename (format: chat_{chat_id}_{hash}.ext)
if (preg_match('/^chat_(\d+)_/', $filename, $matches)) {
    $chat_id = (int)$matches[1];
    
    // Get user info
    $user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
    $is_admin = isAdmin();
    $session_token = isset($_COOKIE['chat_session_token']) ? $_COOKIE['chat_session_token'] : null;
    
    // Check access
    if (!hasChatAccess($chat_id, $user_id, $session_token, $is_admin)) {
        http_response_code(403);
        die('Access denied');
    }
} else {
    // Invalid filename format
    http_response_code(403);
    die('Access denied');
}

// Get file info
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($filepath));
header('Content-Disposition: inline; filename="' . basename($filepath) . '"');
header('Cache-Control: private, max-age=3600');

// Output file
readfile($filepath);
exit;

