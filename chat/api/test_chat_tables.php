<?php
/**
 * Test Chat Tables - Check if tables exist
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Check if admin
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    $results = [];
    
    // Check chat_sessions table
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM chat_sessions");
        $results['chat_sessions'] = ['exists' => true, 'count' => $stmt->fetch()['count']];
    } catch (PDOException $e) {
        $results['chat_sessions'] = ['exists' => false, 'error' => $e->getMessage()];
    }
    
    // Check chat_messages table
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM chat_messages");
        $results['chat_messages'] = ['exists' => true, 'count' => $stmt->fetch()['count']];
    } catch (PDOException $e) {
        $results['chat_messages'] = ['exists' => false, 'error' => $e->getMessage()];
    }
    
    // Check chat_typing_indicators table
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM chat_typing_indicators");
        $results['chat_typing_indicators'] = ['exists' => true, 'count' => $stmt->fetch()['count']];
    } catch (PDOException $e) {
        $results['chat_typing_indicators'] = ['exists' => false, 'error' => $e->getMessage()];
    }
    
    jsonResponse([
        'success' => true,
        'tables' => $results
    ]);
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}

