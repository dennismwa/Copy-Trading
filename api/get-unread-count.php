<?php
// File: api/get-unread-count.php
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

try {
    // Get total unread count (not limited)
    $stmt = $db->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications
        WHERE (user_id = ? OR is_global = 1) AND is_read = 0
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    $unread_count = (int)$result['unread_count'];
    
    echo json_encode([
        'success' => true,
        'count' => $unread_count
    ]);
} catch (Exception $e) {
    error_log("Error getting unread count: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Error fetching unread count'
    ]);
}

