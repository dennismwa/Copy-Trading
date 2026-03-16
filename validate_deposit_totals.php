<?php
/**
 * DEPOSIT TOTALS VALIDATION
 * Run this periodically to ensure deposit totals stay correct
 */

require_once 'config/database.php';

function validateAllDepositTotals($db) {
    $stmt = $db->query("
        SELECT 
            u.id,
            u.total_deposited,
            COALESCE(SUM(t.amount), 0) as actual_deposits
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id 
            AND t.type = 'deposit' 
            AND t.status = 'completed'
        GROUP BY u.id, u.total_deposited
        HAVING ABS(u.total_deposited - COALESCE(SUM(t.amount), 0)) > 0.01
    ");
    $discrepancies = $stmt->fetchAll();
    
    $fixed = 0;
    foreach ($discrepancies as $user) {
        $stmt = $db->prepare("
            UPDATE users 
            SET total_deposited = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$user['actual_deposits'], $user['id']]);
        $fixed++;
    }
    
    return $fixed;
}

// Run validation
$fixed = validateAllDepositTotals($db);
echo "Fixed $fixed deposit total discrepancies";
?>