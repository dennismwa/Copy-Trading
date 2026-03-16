<?php
/**
 * Balance Correction API
 * Backend for the balance correction tool
 */

require_once __DIR__ . '/../config/database.php';

// Only allow admin access
requireLogin();
if ($_SESSION['role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'analyze':
            analyzeUsers();
            break;
            
        case 'correct':
            applyCorrection();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Balance correction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

/**
 * Analyze users who need balance corrections
 */
function analyzeUsers() {
    global $db;
    
    // Find all completed packages
    $stmt = $db->query("
        SELECT 
            ap.id as package_id,
            ap.user_id,
            ap.investment_amount,
            ap.expected_roi,
            ap.completed_at,
            u.full_name,
            u.email,
            u.wallet_balance as current_balance,
            p.name as package_name
        FROM active_packages ap
        JOIN users u ON ap.user_id = u.id
        JOIN packages p ON ap.package_id = p.id
        WHERE ap.status = 'completed'
        AND ap.completed_at IS NOT NULL
        ORDER BY ap.user_id, ap.completed_at DESC
    ");
    
    $completed_packages = $stmt->fetchAll();
    
    // Group by user
    $user_corrections = [];
    $total_packages = 0;
    $total_missing = 0;
    
    foreach ($completed_packages as $package) {
        $user_id = $package['user_id'];
        
        if (!isset($user_corrections[$user_id])) {
            $user_corrections[$user_id] = [
                'user_id' => $user_id,
                'full_name' => $package['full_name'],
                'email' => $package['email'],
                'current_balance' => $package['current_balance'],
                'total_missing_principal' => 0,
                'packages' => []
            ];
        }
        
        // Add missing principal
        $user_corrections[$user_id]['total_missing_principal'] += $package['investment_amount'];
        $user_corrections[$user_id]['packages'][] = [
            'package_name' => $package['package_name'],
            'investment' => $package['investment_amount'],
            'roi' => $package['expected_roi'],
            'completed_at' => $package['completed_at']
        ];
        
        $total_packages++;
        $total_missing += $package['investment_amount'];
    }
    
    // Calculate corrected balance for each user
    foreach ($user_corrections as &$user) {
        $user['corrected_balance'] = $user['current_balance'] + $user['total_missing_principal'];
    }
    
    echo json_encode([
        'success' => true,
        'users' => array_values($user_corrections),
        'totalPackages' => $total_packages,
        'totalMissing' => $total_missing
    ]);
}

/**
 * Apply correction for a specific user
 */
function applyCorrection() {
    global $db;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = (int)($input['user_id'] ?? 0);
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    $db->beginTransaction();
    
    try {
        // Get user's completed packages
        $stmt = $db->prepare("
            SELECT 
                ap.id,
                ap.investment_amount,
                ap.expected_roi,
                p.name as package_name
            FROM active_packages ap
            JOIN packages p ON ap.package_id = p.id
            WHERE ap.user_id = ?
            AND ap.status = 'completed'
            AND ap.completed_at IS NOT NULL
        ");
        $stmt->execute([$user_id]);
        $packages = $stmt->fetchAll();
        
        if (empty($packages)) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => 'No packages found for this user']);
            return;
        }
        
        // Calculate total missing principal
        $total_missing = 0;
        foreach ($packages as $pkg) {
            $total_missing += $pkg['investment_amount'];
        }
        
        // Update user balance
        $stmt = $db->prepare("
            UPDATE users 
            SET wallet_balance = wallet_balance + ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$total_missing, $user_id]);
        
        // Create transaction record
        $stmt = $db->prepare("
            INSERT INTO transactions (user_id, type, amount, status, description, created_at) 
            VALUES (?, 'balance_adjustment', ?, 'completed', ?, NOW())
        ");
        $description = "Balance correction: Missing principal from " . count($packages) . 
                      " completed package(s). System adjustment to fix ROI payment bug where only ROI was credited but not the original investment principal.";
        $stmt->execute([$user_id, $total_missing, $description]);
        
        // Send notification
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $notification_message = "Your balance has been corrected by KSh " . number_format($total_missing, 2) . 
                               ". This adjustment returns the principal amounts from your " . count($packages) . 
                               " completed package(s) that were not previously credited due to a system bug. We apologize for any inconvenience.";
        $stmt->execute([
            $user_id,
            'Balance Corrected ✓',
            $notification_message,
            'success'
        ]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'amount_added' => $total_missing,
            'packages_count' => count($packages)
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Balance correction error for user $user_id: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}