<?php
/**
 * Get Chat List (Admin Only)
 * GET: Retrieve list of all chats for admin panel
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    // Admin only
    if (!isAdmin()) {
        jsonResponse(['success' => false, 'error' => 'Access denied'], 403);
    }
    
    // Check if tables exist
    try {
        $db->query("SELECT 1 FROM chat_sessions LIMIT 1");
        $db->query("SELECT 1 FROM chat_messages LIMIT 1");
    } catch (PDOException $e) {
        error_log("Chat tables may not exist: " . $e->getMessage());
        jsonResponse([
            'success' => false, 
            'error' => 'Chat system not initialized. Please run database setup.',
            'debug' => 'Tables may not exist'
        ], 500);
    }
    
    // Get filter parameters
    $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
    // Increased limit to show all chats - use a very high limit instead of restricting
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10000;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    // Validate status
    if (!in_array($status, ['open', 'closed', 'archived', 'all'])) {
        $status = 'all';
    }
    
    // Sanitize status to prevent SQL injection
    $status = preg_replace('/[^a-z_]/', '', $status);
    
    // Build query - only show chats that have at least one message
    $where = "EXISTS (SELECT 1 FROM chat_messages cm WHERE cm.chat_id = cs.id)";
    $params = [];
    
    if ($status !== 'all') {
        $where .= " AND cs.status = ?";
        $params[] = $status;
    }
    
    // Fast query - get chats first, then enrich with data
    try {
        $stmt = $db->prepare("
            SELECT 
                cs.*,
                u.full_name as user_full_name,
                u.email as user_email
            FROM chat_sessions cs
            LEFT JOIN users u ON cs.user_id = u.id
            WHERE $where
            ORDER BY COALESCE(cs.last_message_at, cs.created_at) DESC
            LIMIT ? OFFSET ?
        ");
    
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);
        $chats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Chat API Error (get_chats - main query): " . $e->getMessage());
        error_log("Chat API Error (get_chats - SQL): " . $e->getTraceAsString());
        // Return empty result instead of throwing
        $chats = [];
    }
    
    // Enrich chats with unread counts and last messages (batch queries)
    if (!empty($chats)) {
        $chat_ids = array_column($chats, 'id');
        
        if (!empty($chat_ids)) {
            $placeholders = implode(',', array_fill(0, count($chat_ids), '?'));
            
            // Get unread counts
            try {
                $stmt = $db->prepare("
                    SELECT chat_id, COUNT(*) as unread_count
                    FROM chat_messages
                    WHERE chat_id IN ($placeholders) AND sender_role = 'user' AND is_read = 0
                    GROUP BY chat_id
                ");
                $stmt->execute($chat_ids);
                $unread_counts = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $unread_counts[$row['chat_id']] = (int)$row['unread_count'];
                }
            } catch (PDOException $e) {
                error_log("Chat API Error (get_chats - unread counts): " . $e->getMessage());
                $unread_counts = [];
            }
            
            // Get last messages - simplified query
            try {
                $stmt = $db->prepare("
                    SELECT cm1.chat_id, cm1.message_text, cm1.created_at
                    FROM chat_messages cm1
                    INNER JOIN (
                        SELECT chat_id, MAX(created_at) as max_created_at
                        FROM chat_messages
                        WHERE chat_id IN ($placeholders)
                        GROUP BY chat_id
                    ) cm2 ON cm1.chat_id = cm2.chat_id AND cm1.created_at = cm2.max_created_at
                ");
                $stmt->execute($chat_ids);
                $last_messages = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $last_messages[$row['chat_id']] = [
                        'message_text' => $row['message_text'],
                        'created_at' => $row['created_at']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Chat API Error (get_chats - last messages): " . $e->getMessage());
                $last_messages = [];
            }
        } else {
            $unread_counts = [];
            $last_messages = [];
        }
        
        // Merge data
        foreach ($chats as &$chat) {
            $chat['unread_count'] = $unread_counts[$chat['id']] ?? 0;
            $last_msg = $last_messages[$chat['id']] ?? null;
            $chat['last_message'] = $last_msg ? $last_msg['message_text'] : null;
            $chat['last_message_time'] = $last_msg ? $last_msg['created_at'] : null;
        }
        unset($chat);
    }
    
    // Format chats and filter out chats with no messages
    $formatted_chats = array_map(function($chat) {
        // Determine display name and email
        $display_name = $chat['user_full_name'] ?: $chat['guest_name'] ?: 'Guest User';
        $display_email = $chat['user_email'] ?: $chat['guest_email'] ?: null;
        
        return [
            'id' => (int)$chat['id'],
            'user_id' => $chat['user_id'] ? (int)$chat['user_id'] : null,
            'guest_name' => $chat['guest_name'] ?? null,
            'guest_email' => $chat['guest_email'] ?? null,
            'user_full_name' => $chat['user_full_name'] ?? null,
            'user_email' => $chat['user_email'] ?? null,
            'display_name' => $display_name,
            'display_email' => $display_email,
            'status' => $chat['status'] ?? 'open',
            'unread_count' => isset($chat['unread_count']) ? (int)$chat['unread_count'] : 0,
            'last_message' => $chat['last_message'] ?? null,
            'last_message_time' => $chat['last_message_time'] ?? null,
            'created_at' => $chat['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $chat['updated_at'] ?? date('Y-m-d H:i:s')
        ];
    }, $chats);
    
    // Filter out chats that have no messages (double check)
    $formatted_chats = array_filter($formatted_chats, function($chat) {
        return $chat['last_message'] !== null;
    });
    
    // Re-index array
    $formatted_chats = array_values($formatted_chats);
    
    // Get total count - only count chats with messages
    try {
        $count_where = "EXISTS (SELECT 1 FROM chat_messages cm WHERE cm.chat_id = chat_sessions.id)";
        $count_params = [];
        
        if ($status !== 'all') {
            $count_where .= " AND status = ?";
            $count_params[] = $status;
        }
        
        $count_sql = "SELECT COUNT(*) FROM chat_sessions WHERE $count_where";
        $stmt = $db->prepare($count_sql);
        $stmt->execute($count_params);
        $total = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Chat API Error (get_chats - count): " . $e->getMessage());
        $total = count($formatted_chats); // Fallback to array count
    }
    
    jsonResponse([
        'success' => true,
        'chats' => $formatted_chats,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (PDOException $e) {
    error_log("Chat API Error (get_chats - PDO): " . $e->getMessage());
    error_log("Chat API Error (get_chats - SQL State): " . $e->getCode());
    error_log("Chat API Error (get_chats - File): " . $e->getFile() . " Line: " . $e->getLine());
    
    // Return error with more details for debugging
    $error_msg = $e->getMessage();
    if (strpos($error_msg, "doesn't exist") !== false || strpos($error_msg, "Unknown table") !== false) {
        jsonResponse([
            'success' => false, 
            'error' => 'Chat tables not found. Please run database setup script.',
            'debug' => $error_msg
        ], 500);
    } else {
        jsonResponse([
            'success' => false, 
            'error' => 'Database error occurred',
            'debug' => $error_msg
        ], 500);
    }
} catch (Exception $e) {
    error_log("Chat API Error (get_chats): " . $e->getMessage());
    error_log("Chat API Error (get_chats - Stack): " . $e->getTraceAsString());
    jsonResponse([
        'success' => false, 
        'error' => 'Internal server error',
        'debug' => $e->getMessage()
    ], 500);
}

