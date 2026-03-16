<?php
/**
 * User Data Export Endpoint
 * Exports all user information including deposits and trades to CSV/Excel
 */

require_once '../../config/database.php';
requireAdmin();

$export_type = $_GET['type'] ?? 'csv';
$filter_status = $_GET['status'] ?? 'all';
$include_inactive = isset($_GET['include_inactive']) ? (bool)$_GET['include_inactive'] : false;

// Build query conditions
$where_conditions = ["u.is_admin = 0"];
$params = [];

if ($filter_status !== 'all') {
    $where_conditions[] = "u.status = ?";
    $params[] = $filter_status;
}

if (!$include_inactive) {
    $where_conditions[] = "u.status = 'active'";
}

$where_clause = implode(' AND ', $where_conditions);

// Get all users with their transaction and trading data
try {
    $sql = "
        SELECT 
            u.id,
            u.full_name,
            u.email,
            u.phone,
            u.status,
            u.wallet_balance,
            u.referral_code,
            u.referral_earnings,
            u.created_at as joined_date,
            u.updated_at as last_updated,
            -- Deposit Statistics
            COALESCE(SUM(CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_deposited,
            COUNT(DISTINCT CASE WHEN t.type = 'deposit' AND t.status = 'completed' THEN t.id END) as deposit_count,
            -- Withdrawal Statistics
            COALESCE(SUM(CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_withdrawn,
            COUNT(DISTINCT CASE WHEN t.type = 'withdrawal' AND t.status = 'completed' THEN t.id END) as withdrawal_count,
            -- Trading Statistics
            COALESCE(SUM(CASE WHEN ap.status = 'active' THEN ap.investment_amount ELSE 0 END), 0) as active_investments,
            COALESCE(SUM(CASE WHEN ap.status = 'completed' THEN ap.investment_amount ELSE 0 END), 0) as completed_investments,
            COUNT(DISTINCT CASE WHEN ap.status = 'active' THEN ap.id END) as active_trades_count,
            COUNT(DISTINCT CASE WHEN ap.status = 'completed' THEN ap.id END) as completed_trades_count,
            COALESCE(SUM(CASE WHEN ap.status = 'completed' THEN ap.expected_roi ELSE 0 END), 0) as total_profits_earned,
            -- ROI Statistics
            COALESCE(SUM(CASE WHEN t.type = 'roi_payment' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_roi_received,
            COUNT(DISTINCT CASE WHEN t.type = 'roi_payment' AND t.status = 'completed' THEN t.id END) as roi_payments_count,
            -- Referral Statistics
            COUNT(DISTINCT ref.id) as total_referrals,
            COALESCE(SUM(CASE WHEN t.type = 'referral_commission' AND t.status = 'completed' THEN t.amount ELSE 0 END), 0) as total_referral_earnings
        FROM users u
        LEFT JOIN transactions t ON u.id = t.user_id
        LEFT JOIN active_packages ap ON u.id = ap.user_id
        LEFT JOIN users ref ON u.id = ref.referred_by
        WHERE $where_clause
        GROUP BY u.id, u.full_name, u.email, u.phone, u.status, u.wallet_balance, 
                 u.referral_code, u.referral_earnings, u.created_at, u.updated_at
        ORDER BY u.created_at DESC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate filename
    $filename = 'UltraHarvest_Users_Export_' . date('Y-m-d_H-i-s');
    if ($filter_status !== 'all') {
        $filename .= '_' . $filter_status;
    }

    if ($export_type === 'csv' || $export_type === 'excel') {
        // For CSV/Excel, output as CSV (Excel can open CSV files)
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        // Add BOM for UTF-8 (helps Excel recognize encoding)
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, [
            'ID',
            'Full Name',
            'Email',
            'Phone Number',
            'Status',
            'Wallet Balance (KSh)',
            'Joined Date',
            'Last Updated',
            'Referral Code',
            'Total Deposited (KSh)',
            'Deposit Count',
            'Total Withdrawn (KSh)',
            'Withdrawal Count',
            'Active Investments (KSh)',
            'Active Trades Count',
            'Completed Investments (KSh)',
            'Completed Trades Count',
            'Total Profits Earned (KSh)',
            'Total ROI Received (KSh)',
            'ROI Payments Count',
            'Total Referrals',
            'Referral Earnings (KSh)',
            'Net Balance (KSh)'
        ]);
        
        // CSV Data
        foreach ($users as $user) {
            $net_balance = ($user['total_deposited'] + $user['total_referral_earnings'] + $user['total_roi_received']) 
                         - ($user['total_withdrawn'] + $user['active_investments']);
            
            fputcsv($output, [
                $user['id'],
                $user['full_name'],
                $user['email'],
                $user['phone'] ?? 'N/A',
                ucfirst($user['status']),
                number_format($user['wallet_balance'], 2),
                date('Y-m-d H:i:s', strtotime($user['joined_date'])),
                date('Y-m-d H:i:s', strtotime($user['last_updated'])),
                $user['referral_code'] ?? 'N/A',
                number_format($user['total_deposited'], 2),
                $user['deposit_count'],
                number_format($user['total_withdrawn'], 2),
                $user['withdrawal_count'],
                number_format($user['active_investments'], 2),
                $user['active_trades_count'],
                number_format($user['completed_investments'], 2),
                $user['completed_trades_count'],
                number_format($user['total_profits_earned'], 2),
                number_format($user['total_roi_received'], 2),
                $user['roi_payments_count'],
                $user['total_referrals'],
                number_format($user['total_referral_earnings'], 2),
                number_format($net_balance, 2)
            ]);
        }
        
        fclose($output);
        exit;

    } elseif ($export_type === 'pdf') {
        // For PDF, generate HTML that can be printed/saved as PDF
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>User Data Export - Ultra Harvest Global</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; font-size: 11px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #10b981; padding-bottom: 20px; }
                .header h1 { color: #10b981; margin: 0; }
                .header p { color: #666; margin: 5px 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 10px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #10b981; color: white; font-weight: bold; }
                tr:nth-child(even) { background-color: #f2f2f2; }
                .footer { margin-top: 30px; text-align: center; color: #666; font-size: 10px; }
                .no-print { display: none; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                    table { font-size: 8px; }
                    th, td { padding: 4px; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Ultra Harvest Global</h1>
                <p>User Data Export Report</p>
                <p>Generated: <?php echo date('F j, Y g:i A'); ?></p>
                <p>Total Users: <?php echo count($users); ?></p>
                <?php if ($filter_status !== 'all'): ?>
                <p>Status Filter: <?php echo ucfirst($filter_status); ?></p>
                <?php endif; ?>
            </div>
            
            <button onclick="window.print()" class="no-print" style="padding: 10px 20px; background: #10b981; color: white; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px;">Print/Save as PDF</button>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Balance</th>
                        <th>Deposited</th>
                        <th>Withdrawn</th>
                        <th>Active Trades</th>
                        <th>Completed Trades</th>
                        <th>Total Profits</th>
                        <th>Referrals</th>
                        <th>Joined</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <?php
                    $net_balance = ($user['total_deposited'] + $user['total_referral_earnings'] + $user['total_roi_received']) 
                                 - ($user['total_withdrawn'] + $user['active_investments']);
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo ucfirst($user['status']); ?></td>
                        <td>KSh <?php echo number_format($user['wallet_balance'], 2); ?></td>
                        <td>KSh <?php echo number_format($user['total_deposited'], 2); ?></td>
                        <td>KSh <?php echo number_format($user['total_withdrawn'], 2); ?></td>
                        <td><?php echo $user['active_trades_count']; ?></td>
                        <td><?php echo $user['completed_trades_count']; ?></td>
                        <td>KSh <?php echo number_format($user['total_profits_earned'], 2); ?></td>
                        <td><?php echo $user['total_referrals']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['joined_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if (empty($users)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <p>No users found for the selected criteria.</p>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Ultra Harvest Global - Growing Wealth Together</p>
                <p>This report contains <?php echo count($users); ?> user(s)</p>
                <p>Report generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

} catch (Exception $e) {
    error_log("User export failed: " . $e->getMessage());
    header('Location: /admin/settings.php?error=' . urlencode('Export failed: ' . $e->getMessage()));
    exit;
}

// Fallback
header('Location: /admin/settings.php');
exit;
?>

