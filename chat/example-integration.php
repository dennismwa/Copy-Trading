<?php
/**
 * Example: How to integrate the chat widget into your pages
 * 
 * This is an example file showing how to add the chat widget to your website.
 * Copy the relevant parts to your actual pages.
 */

// Example 1: Adding to a simple PHP page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Page Title</title>
    <!-- Your other head content -->
</head>
<body>
    <!-- Your page content -->
    
    <!-- Add chat widget before closing body tag -->
    <?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
</body>
</html>

<?php
// Example 2: Adding to a page that already includes database config
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Page Title</title>
</head>
<body>
    <!-- Your page content -->
    
    <!-- Chat widget will work automatically since database.php is already included -->
    <?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
</body>
</html>

<?php
// Example 3: Adding to a page with existing session
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Page Title</title>
</head>
<body>
    <!-- Your page content -->
    
    <!-- Chat widget - works for both logged-in and guest users -->
    <?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
</body>
</html>

<?php
/*
 * NOTES:
 * 
 * 1. The chat widget loader automatically:
 *    - Includes the CSS and JavaScript files
 *    - Generates CSRF tokens
 *    - Works for both logged-in and guest users
 * 
 * 2. For logged-in users:
 *    - Chat is automatically associated with their user account
 *    - No need to enter name/email
 * 
 * 3. For guest users:
 *    - They'll be prompted to enter name (and optional email)
 *    - Session is tracked via cookie
 * 
 * 4. The widget is responsive and works on:
 *    - Desktop browsers
 *    - Mobile devices
 *    - Tablets
 * 
 * 5. To customize appearance:
 *    - Edit chat/widget/chat-widget.css
 *    - Change colors, sizes, positions as needed
 */

