<?php
/**
 * DEPOSIT CALCULATION VALIDATION
 * This ensures total_deposited only includes actual deposits
 */

require_once 'config/database.php';

function validateDepositCalculations($db) {
    // Find users with wrong deposit totals
    $stmt = $db->query("
        SELECT 
            u.id,
            u.full_name,
            u.total_deposited,
            COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as actual_deposits
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        WHERE u.is_admin = 0
        GROUP BY u.id, u.full_name, u.total_deposited
        HAVING ABS(u.total_deposited - COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0)) > 0.01
    ");
    $problems = $stmt->fetchAll();
    
    $fixed = 0;
    foreach ($problems as $problem) {
        $stmt = $db->prepare("
            UPDATE users 
            SET total_deposited = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$problem['actual_deposits'], $problem['id']]);
        $fixed++;
    }
    
    return $fixed;
}

// Run validation
$fixed = validateDepositCalculations($db);
echo "Fixed $fixed deposit calculation discrepancies";
?>