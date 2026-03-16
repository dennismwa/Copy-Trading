# Testing Guide

## Pre-Installation Testing

### 1. Database Setup Test
```sql
-- Run the schema
SOURCE chat/database_schema.sql;

-- Verify tables were created
SHOW TABLES LIKE 'chat_%';

-- Should show:
-- chat_sessions
-- chat_messages
-- chat_typing_indicators
-- chat_notifications
```

### 2. File Permissions Test
```bash
# Check uploads directory
ls -la chat/uploads/

# Should be writable
chmod 755 chat/uploads/
```

## Functional Testing

### Test 1: Guest User Chat
1. Open website in incognito/private window
2. Click chat button (bottom-right)
3. Enter name and optional email
4. Send a test message
5. Verify message appears in chat
6. Check admin panel - chat should appear

### Test 2: Logged-In User Chat
1. Log in to your account
2. Click chat button
3. Should NOT show name/email form (auto-starts)
4. Send a message
5. Verify it works

### Test 3: Admin Panel
1. Log in as admin
2. Go to `/admin/chat.php`
3. Verify statistics show correctly
4. Click on a chat
5. Send a reply
6. Verify user receives it

### Test 4: Real-Time Updates
1. Open chat as user in one browser
2. Open admin panel in another browser
3. Send message from user
4. Verify admin sees it within 2-3 seconds
5. Send reply from admin
6. Verify user sees it within 2-3 seconds

### Test 5: Typing Indicators
1. Start typing in chat widget
2. Verify admin sees typing indicator
3. Stop typing for 3+ seconds
4. Verify indicator disappears

### Test 6: File Uploads
1. Click attachment button
2. Select an image
3. Send message
4. Verify image appears in chat
5. Try PDF file
6. Verify download link appears

### Test 7: Multiple Chats
1. Start multiple chats (different browsers/users)
2. Verify admin can see all
3. Switch between chats
4. Verify messages don't mix

### Test 8: Chat Management
1. Close a chat from admin panel
2. Verify it moves to "Closed" filter
3. Archive a chat
4. Verify it moves to "Archived" filter

## Security Testing

### Test 1: CSRF Protection
1. Try to send message without CSRF token
2. Should fail with 403 error

### Test 2: Access Control
1. Try to access admin API endpoints without admin login
2. Should fail with 403 error

### Test 3: File Upload Security
1. Try to upload executable file (.exe, .php)
2. Should be rejected
3. Try to upload file > 5MB
4. Should be rejected

### Test 4: SQL Injection
1. Try to inject SQL in message text
2. Should be sanitized and stored safely

### Test 5: XSS Prevention
1. Try to inject JavaScript in message
2. Should be escaped and displayed as text

## Performance Testing

### Test 1: Load Test
1. Create 10+ chats simultaneously
2. Send messages rapidly
3. Verify system handles it

### Test 2: Database Performance
1. Check query execution time
2. Verify indexes are being used
3. Run EXPLAIN on queries

## Browser Compatibility

Test on:
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Chrome
- [ ] Mobile Safari

## Mobile Testing

1. Open on mobile device
2. Verify chat button is accessible
3. Verify chat window is responsive
4. Test typing and sending
5. Test file uploads

## Error Handling

### Test 1: Network Errors
1. Disconnect internet
2. Try to send message
3. Should show error message
4. Reconnect and retry
5. Should work

### Test 2: Database Errors
1. Temporarily break database connection
2. Try to use chat
3. Should handle gracefully

## Cleanup Testing

1. Run cleanup script:
```bash
php chat/cleanup.php
```

2. Verify expired typing indicators are removed
3. Verify old notifications are cleaned up

## Common Issues & Solutions

### Issue: Chat button not appearing
**Solution**: Check browser console for JavaScript errors, verify file paths

### Issue: Messages not sending
**Solution**: Check PHP error logs, verify CSRF token, check database connection

### Issue: File uploads not working
**Solution**: Check file permissions, verify upload directory exists, check PHP upload limits

### Issue: Real-time updates not working
**Solution**: Check polling interval, verify API endpoints are accessible, check browser console

### Issue: Admin panel access denied
**Solution**: Verify admin login, check session, verify isAdmin() function

## Performance Benchmarks

Expected performance:
- Message send: < 200ms
- Message retrieval: < 100ms
- Chat list load: < 500ms
- File upload (1MB): < 2s

## Load Testing

For production:
- Test with 50+ concurrent chats
- Test with 1000+ messages
- Monitor database performance
- Check server resources

