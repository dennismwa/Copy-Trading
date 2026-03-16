<?php
/**
 * COMPREHENSIVE SYSTEM HEALTH CHECK
 * This script verifies all system health calculations are working correctly
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>System Health Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #28a745; font-weight: bold; }
        .error { color: #dc3545; font-weight: bold; }
        .warning { color: #ffc107; font-weight: bold; }
        .info { color: #17a2b8; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .refresh-btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        .refresh-btn:hover { background: #0056b3; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 System Health Comprehensive Check</h1>";
echo "<p><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<button class='refresh-btn' onclick='location.reload()'>🔄 Refresh Check</button>";

$all_good = true;
$issues = [];

try {
    // 1. Check database connection
    echo "<div class='section'>";
    echo "<h2>1. Database Connection</h2>";
    if ($db) {
        echo "<p class='success'>✅ Database connection successful</p>";
    } else {
        echo "<p class='error'>❌ Database connection failed</p>";
        $all_good = false;
        $issues[] = "Database connection failed";
    }
    echo "</div>";

    // 2. Check if admin_stats_overview view exists and has correct structure
    echo "<div class='section'>";
    echo "<h2>2. Database View Structure</h2>";
    
    try {
        $view_exists = $db->query("SHOW TABLES LIKE 'admin_stats_overview'")->fetch();
        if ($view_exists) {
            echo "<p class='success'>✅ admin_stats_overview view exists</p>";
            
            // Check view structure
            $columns = $db->query("DESCRIBE admin_stats_overview")->fetchAll();
            $required_fields = ['active_users', 'total_users', 'total_deposits', 'total_withdrawals', 'total_roi_paid', 'active_packages', 'total_user_balances', 'total_active_investments', 'pending_roi_obligations', 'unpaid_referral_amounts'];
            $existing_fields = array_column($columns, 'Field');
            
            echo "<p><strong>View Fields:</strong> " . implode(', ', $existing_fields) . "</p>";
            
            $missing_fields = array_diff($required_fields, $existing_fields);
            if (empty($missing_fields)) {
                echo "<p class='success'>✅ All required fields present</p>";
            } else {
                echo "<p class='error'>❌ Missing fields: " . implode(', ', $missing_fields) . "</p>";
                $all_good = false;
                $issues[] = "Missing fields in admin_stats_overview view: " . implode(', ', $missing_fields);
            }
        } else {
            echo "<p class='error'>❌ admin_stats_overview view does not exist</p>";
            $all_good = false;
            $issues[] = "admin_stats_overview view does not exist";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error checking view: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error checking view: " . $e->getMessage();
    }
    echo "</div>";

    // 3. Test view data retrieval
    echo "<div class='section'>";
    echo "<h2>3. View Data Retrieval</h2>";
    
    try {
        $view_data = $db->query("SELECT * FROM admin_stats_overview")->fetch();
        if ($view_data) {
            echo "<p class='success'>✅ View data retrieved successfully</p>";
            
            echo "<table>";
            echo "<tr><th>Field</th><th>Value</th><th>Status</th></tr>";
            foreach ($view_data as $key => $value) {
                $status = is_numeric($value) ? "✅" : "⚠️";
                echo "<tr><td><strong>$key</strong></td><td>" . number_format($value, 2) . "</td><td>$status</td></tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ No data returned from view</p>";
            $all_good = false;
            $issues[] = "No data returned from admin_stats_overview view";
        }
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error retrieving view data: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error retrieving view data: " . $e->getMessage();
    }
    echo "</div>";

    // 4. Test direct table queries vs view
    echo "<div class='section'>";
    echo "<h2>4. Direct Table Queries vs View Comparison</h2>";
    
    try {
        // Direct queries
        $direct_queries = [
            'active_users' => "SELECT COUNT(*) FROM users WHERE status = 'active'",
            'total_user_balances' => "SELECT COALESCE(SUM(wallet_balance), 0) FROM users",
            'active_packages_count' => "SELECT COUNT(*) FROM active_packages WHERE status = 'active'",
            'total_active_investments' => "SELECT COALESCE(SUM(investment_amount), 0) FROM active_packages WHERE status = 'active'",
            'pending_roi_obligations' => "SELECT COALESCE(SUM(expected_roi), 0) FROM active_packages WHERE status = 'active'",
            'unpaid_referral_amounts' => "SELECT COALESCE(SUM(referral_earnings), 0) FROM users",
            'total_deposits' => "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'deposit' AND status = 'completed'",
            'total_withdrawals' => "SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE type = 'withdrawal' AND status = 'completed'"
        ];
        
        $direct_data = [];
        foreach ($direct_queries as $key => $query) {
            $result = $db->query($query)->fetchColumn();
            $direct_data[$key] = $result;
        }
        
        echo "<table>";
        echo "<tr><th>Field</th><th>Direct Query</th><th>View Data</th><th>Match</th></tr>";
        
        $matches = 0;
        $total_fields = 0;
        
        foreach ($direct_data as $key => $direct_value) {
            $view_value = $view_data[$key] ?? 0;
            $match = ($direct_value == $view_value) ? "✅" : "❌";
            if ($match == "✅") $matches++;
            $total_fields++;
            
            echo "<tr>";
            echo "<td><strong>$key</strong></td>";
            echo "<td>" . number_format($direct_value, 2) . "</td>";
            echo "<td>" . number_format($view_value, 2) . "</td>";
            echo "<td>$match</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        if ($matches == $total_fields) {
            echo "<p class='success'>✅ All direct queries match view data ($matches/$total_fields)</p>";
        } else {
            echo "<p class='error'>❌ Direct queries don't match view data ($matches/$total_fields)</p>";
            $all_good = false;
            $issues[] = "Direct queries don't match view data";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error in direct queries: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error in direct queries: " . $e->getMessage();
    }
    echo "</div>";

    // 5. Test system health calculations
    echo "<div class='section'>";
    echo "<h2>5. System Health Calculations</h2>";
    
    try {
        // Include completed admin capital injections in platform liquidity calculation
        // This ensures injected capital automatically improves platform liquidity
        $admin_injections = getCompletedAdminInjections($db);
        $platform_liquidity = ($view_data['total_deposits'] - $view_data['total_withdrawals']) + $admin_injections;
        $total_liabilities = $view_data['total_user_balances'] + $view_data['pending_roi_obligations'] + $view_data['total_active_investments'] + $view_data['unpaid_referral_amounts'];
        $coverage_ratio = $total_liabilities > 0 ? $platform_liquidity / $total_liabilities : 1;
        
        echo "<p><strong>Platform Liquidity:</strong> KSh " . number_format($platform_liquidity, 2) . "</p>";
        echo "<p><strong>Total Liabilities:</strong> KSh " . number_format($total_liabilities, 2) . "</p>";
        echo "<p><strong>Coverage Ratio:</strong> " . number_format($coverage_ratio * 100, 2) . "%</p>";
        
        // Breakdown of liabilities
        echo "<h3>Liabilities Breakdown:</h3>";
        echo "<ul>";
        echo "<li>User Balances: KSh " . number_format($view_data['total_user_balances'], 2) . "</li>";
        echo "<li>Pending ROI Obligations: KSh " . number_format($view_data['pending_roi_obligations'], 2) . "</li>";
        echo "<li>Active Investments: KSh " . number_format($view_data['total_active_investments'], 2) . "</li>";
        echo "<li>Unpaid Referral Amounts: KSh " . number_format($view_data['unpaid_referral_amounts'], 2) . "</li>";
        echo "</ul>";
        
        if ($coverage_ratio >= 1) {
            echo "<p class='success'>✅ System is healthy (coverage ratio ≥ 100%)</p>";
        } elseif ($coverage_ratio >= 0.8) {
            echo "<p class='warning'>⚠️ System is at risk (coverage ratio " . number_format($coverage_ratio * 100, 2) . "%)</p>";
        } else {
            echo "<p class='error'>❌ System is in danger (coverage ratio " . number_format($coverage_ratio * 100, 2) . "%)</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error calculating system health: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error calculating system health: " . $e->getMessage();
    }
    echo "</div>";

    // 6. Check active packages
    echo "<div class='section'>";
    echo "<h2>6. Active Packages Check</h2>";
    
    try {
        $active_packages = $db->query("
            SELECT ap.*, u.full_name, p.name as package_name
            FROM active_packages ap
            JOIN users u ON ap.user_id = u.id
            JOIN packages p ON ap.package_id = p.id
            WHERE ap.status = 'active'
            ORDER BY ap.created_at DESC
        ")->fetchAll();
        
        echo "<p><strong>Active Packages Count:</strong> " . count($active_packages) . "</p>";
        
        if (count($active_packages) > 0) {
            echo "<p class='success'>✅ Found " . count($active_packages) . " active package(s)</p>";
            
            $total_investment = array_sum(array_column($active_packages, 'investment_amount'));
            $total_roi = array_sum(array_column($active_packages, 'expected_roi'));
            
            echo "<p><strong>Total Investment:</strong> KSh " . number_format($total_investment, 2) . "</p>";
            echo "<p><strong>Total Expected ROI:</strong> KSh " . number_format($total_roi, 2) . "</p>";
            
            echo "<h3>Recent Active Packages:</h3>";
            echo "<table>";
            echo "<tr><th>User</th><th>Package</th><th>Investment</th><th>Expected ROI</th><th>Created</th></tr>";
            
            foreach (array_slice($active_packages, 0, 5) as $pkg) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($pkg['full_name']) . "</td>";
                echo "<td>" . htmlspecialchars($pkg['package_name']) . "</td>";
                echo "<td>KSh " . number_format($pkg['investment_amount'], 2) . "</td>";
                echo "<td>KSh " . number_format($pkg['expected_roi'], 2) . "</td>";
                echo "<td>" . date('M j, Y g:i A', strtotime($pkg['created_at'])) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ No active packages found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error checking active packages: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error checking active packages: " . $e->getMessage();
    }
    echo "</div>";

    // 7. Test real-time updates
    echo "<div class='section'>";
    echo "<h2>7. Real-Time Update Test</h2>";
    
    try {
        // Get current timestamp
        $current_time = $db->query("SELECT NOW() as current_time")->fetch()['current_time'];
        echo "<p><strong>Database Time:</strong> $current_time</p>";
        
        // Check if there are any recent transactions (last 5 minutes)
        $recent_transactions = $db->query("
            SELECT COUNT(*) as count, MAX(created_at) as latest
            FROM transactions 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
        ")->fetch();
        
        if ($recent_transactions['count'] > 0) {
            echo "<p class='success'>✅ Recent activity detected (" . $recent_transactions['count'] . " transactions in last 5 minutes)</p>";
            echo "<p><strong>Latest Transaction:</strong> " . $recent_transactions['latest'] . "</p>";
        } else {
            echo "<p class='info'>ℹ️ No recent transactions (last 5 minutes)</p>";
        }
        
    } catch (Exception $e) {
        echo "<p class='error'>❌ Error checking real-time updates: " . $e->getMessage() . "</p>";
        $all_good = false;
        $issues[] = "Error checking real-time updates: " . $e->getMessage();
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<h2 class='error'>❌ Critical Error</h2>";
    echo "<p class='error'>" . $e->getMessage() . "</p>";
    echo "</div>";
    $all_good = false;
    $issues[] = "Critical error: " . $e->getMessage();
}

// Final summary
echo "<div class='section'>";
echo "<h2>📊 Final Summary</h2>";

if ($all_good) {
    echo "<p class='success'>✅ ALL SYSTEMS OPERATIONAL!</p>";
    echo "<p>Your system health is working correctly and should update in real-time.</p>";
} else {
    echo "<p class='error'>❌ ISSUES DETECTED</p>";
    echo "<p>The following issues need to be addressed:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='error'>$issue</li>";
    }
    echo "</ul>";
}

echo "<h3>Next Steps:</h3>";
echo "<ul>";
if (!$all_good) {
    echo "<li>Run the fix script: <a href='fix_system_health_now.php'>fix_system_health_now.php</a></li>";
    echo "<li>Or import the SQL file: <a href='FIX_SYSTEM_HEALTH_IMMEDIATELY.sql'>FIX_SYSTEM_HEALTH_IMMEDIATELY.sql</a></li>";
}
echo "<li>Check system health: <a href='admin/system-health.php'>admin/system-health.php</a></li>";
echo "<li>Run diagnostic: <a href='admin/test-realtime-health.php'>admin/test-realtime-health.php</a></li>";
echo "</ul>";

echo "</div>";

echo "</div></body></html>";
?>
