<?php
/**
 * Transaction Status Checker API
 * Allows users to check the status of their transactions
 */

require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user_id = $_SESSION['user_id'];
$transaction_id = (int)($_GET['id'] ?? 0);

if (!$transaction_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit;
}

try {
    // Get transaction details
    $stmt = $db->prepare("
        SELECT id, type, amount, status, mpesa_receipt, mpesa_request_id, created_at, updated_at
        FROM transactions 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$transaction_id, $user_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    // ENHANCED: Check status for both pending and processing transactions
    // For withdrawals, also check 'processing' status since that's what we set when M-Pesa is called
    if (in_array($transaction['status'], ['pending', 'processing', '']) && !empty($transaction['mpesa_request_id'])) {
        // Check if it's been more than 2 minutes since creation (faster check for real-time updates)
        $created_time = strtotime($transaction['created_at']);
        $current_time = time();
        $time_diff = $current_time - $created_time;
        
        // NOTE: M-Pesa B2C (withdrawals) doesn't have a query API - only callbacks
        // So we can only query STK Push (deposits) directly
        if ($time_diff > 120 && $transaction['type'] === 'deposit') { // 2 minutes - check sooner for real-time updates
            // Query M-Pesa for status (only works for STK Push/deposits)
            require_once '../config/mpesa.php';
            $mpesa = new MpesaIntegration();
            
            error_log("REAL-TIME STATUS CHECK: Transaction ID $transaction_id, Type: {$transaction['type']}, Current Status: {$transaction['status']}, M-Pesa ID: {$transaction['mpesa_request_id']}");
            
            $query_result = $mpesa->queryTransaction($transaction['mpesa_request_id']);
            
            if (isset($query_result['ResultCode'])) {
                if ($query_result['ResultCode'] == '0') {
                    // Payment was successful, but callback might have failed
                    // Update transaction status
                    $db->beginTransaction();
                    
                    $receipt = $query_result['ReceiptNumber'] ?? ('MP' . time() . rand(1000, 9999));
                    
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = 'completed', 
                            mpesa_receipt = ?,
                            description = CONCAT(COALESCE(description, ''), ' - Real-time status check: Completed'),
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$receipt, $transaction_id]);
                    
                    // Update user wallet if it's a deposit
                    if ($transaction['type'] === 'deposit') {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET wallet_balance = wallet_balance + ?, 
                                total_deposited = total_deposited + ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$transaction['amount'], $transaction['amount'], $user_id]);
                        
                        // Send notification
                        sendNotification(
                            $user_id,
                            'Deposit Confirmed ',
                            "Your deposit of " . formatMoney($transaction['amount']) . " has been confirmed and credited to your wallet.",
                            'success'
                        );
                    } elseif ($transaction['type'] === 'withdrawal') {
                        // For withdrawals, send completion notification
                        sendNotification(
                            $user_id,
                            'Withdrawal Completed! 💰',
                            "Your withdrawal of " . formatMoney($transaction['amount']) . " has been completed successfully. Receipt: $receipt",
                            'success'
                        );
                    }
                    
                    $db->commit();
                    
                    error_log("REAL-TIME STATUS UPDATE: Transaction ID $transaction_id marked as completed via status check");
                    
                    $transaction['status'] = 'completed';
                    $transaction['mpesa_receipt'] = $receipt;
                    
                } elseif (in_array($query_result['ResultCode'], ['1032', '1037', '1', '1001'])) {
                    // Payment was cancelled or failed
                    $db->beginTransaction();
                    
                    $stmt = $db->prepare("
                        UPDATE transactions 
                        SET status = 'failed', 
                            description = CONCAT(COALESCE(description, ''), ' - Real-time status check: Failed - ', ?),
                            updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $error_desc = $query_result['ResultDesc'] ?? 'Transaction failed';
                    $stmt->execute([$error_desc, $transaction_id]);
                    
                    // Refund wallet for withdrawals
                    if ($transaction['type'] === 'withdrawal') {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET wallet_balance = wallet_balance + ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt->execute([$transaction['amount'], $user_id]);
                        
                        sendNotification(
                            $user_id,
                            'Withdrawal Failed',
                            "Your withdrawal of " . formatMoney($transaction['amount']) . " could not be processed. Funds have been returned to your wallet.",
                            'error'
                        );
                    }
                    
                    $db->commit();
                    
                    error_log("REAL-TIME STATUS UPDATE: Transaction ID $transaction_id marked as failed via status check");
                    
                    $transaction['status'] = 'failed';
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'transaction' => [
            'id' => $transaction['id'],
            'type' => $transaction['type'],
            'amount' => $transaction['amount'],
            'status' => $transaction['status'],
            'mpesa_receipt' => $transaction['mpesa_receipt'],
            'created_at' => $transaction['created_at'],
            'updated_at' => $transaction['updated_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Transaction status check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
?>