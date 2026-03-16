# Quick Installation Guide

## Step-by-Step Installation

### 1. Database Setup

Import the database schema:

```sql
-- Run this SQL in your database
SOURCE chat/database_schema.sql;
```

Or via command line:
```bash
mysql -u your_username -p your_database < chat/database_schema.sql
```

### 2. Set Permissions

Make sure the uploads directory is writable:

```bash
chmod 755 chat/uploads/
chmod 644 chat/uploads/.htaccess
```

### 3. Add Widget to Your Pages

Add this line to any page where you want the chat widget to appear (usually in your footer or before `</body>`):

```php
<?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
```

**Recommended locations:**
- `footer.php` (if you have a common footer)
- `index.php`
- `home.php`
- Any public-facing page

### 4. Test the Installation

1. Visit your website - you should see a chat button in the bottom-right corner
2. Click it and start a chat
3. Log in as admin and go to `/admin/chat.php`
4. You should see the chat in the admin panel

### 5. Verify Everything Works

- [ ] Chat button appears on your pages
- [ ] Can start a chat as a guest
- [ ] Can send messages
- [ ] Admin panel shows chats
- [ ] Admin can respond to chats
- [ ] File uploads work (if enabled)

## Troubleshooting

### Chat button not showing?

1. Check browser console (F12) for JavaScript errors
2. Verify the loader file path is correct
3. Make sure `chat-widget.css` and `chat-widget.js` are accessible

### Database errors?

1. Verify database connection in `config/database.php`
2. Check that all tables were created
3. Verify user has proper permissions

### Messages not sending?

1. Check PHP error logs
2. Verify CSRF token is being generated
3. Check database connection

### Admin panel access denied?

1. Make sure you're logged in as admin
2. Check `isAdmin()` returns true
3. Verify session is active

## Configuration

Edit `chat/config.php` to customize:

- File upload size limits
- Polling intervals
- Allowed file types
- Upload directory paths

## Next Steps

- Customize the chat widget colors in `chat/widget/chat-widget.css`
- Add the chat widget to all your public pages
- Train your support team on using the admin panel
- Set up notifications (optional)

## Support

For detailed documentation, see `chat/README.md`

