<?php
/**
 * Test Withdrawal API Endpoint
 * Use this to test withdrawal functionality via API
 */

require_once '../config/database.php';
require_once '../config/mpesa.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['phone', 'amount'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$phone = $input['phone'];
$amount = floatval($input['amount']);

// Validate amount
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount must be greater than 0']);
    exit;
}

try {
    // Initialize M-Pesa
    $mpesa = new MpesaIntegration();
    
    // Test B2C payment
    $account_reference = "TEST_" . time();
    $remarks = "Test withdrawal via API";
    
    $result = $mpesa->b2cPayment($phone, $amount, $account_reference, $remarks);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Withdrawal test successful',
            'conversation_id' => $result['ConversationID'] ?? null,
            'originator_conversation_id' => $result['OriginatorConversationID'] ?? null,
            'response_description' => $result['ResponseDescription'] ?? null,
            'simulated' => isset($result['simulated']) ? $result['simulated'] : false
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $result['message']
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
