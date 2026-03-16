# Quick Start Guide - Live Chat System

## 5-Minute Setup

### Step 1: Database (1 minute)
```bash
mysql -u your_username -p your_database < chat/database_schema.sql
```

### Step 2: Permissions (30 seconds)
```bash
chmod 755 chat/uploads/
```

### Step 3: Add to Pages (2 minutes)
Add this line before `</body>` in your pages:
```php
<?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
```

**Recommended locations:**
- Footer file (if you have one)
- `index.php`
- `home.php`
- Any public page

### Step 4: Test (1 minute)
1. Visit your website
2. Click chat button (bottom-right)
3. Start chatting
4. Log in as admin → `/admin/chat.php`
5. See the chat in admin panel

## Done! 🎉

Your live chat system is now active!

## Configuration (Optional)

Edit `chat/config.php` to customize:
- File upload size
- Polling intervals
- Allowed file types

## Maintenance (Optional)

Set up hourly cleanup:
```bash
# Add to crontab
0 * * * * /usr/bin/php /path/to/chat/cleanup.php
```

## Troubleshooting

**Chat button not showing?**
- Check browser console (F12)
- Verify file paths are correct

**Messages not sending?**
- Check PHP error logs
- Verify database connection

**Admin access denied?**
- Make sure you're logged in as admin
- Check `isAdmin()` function

## Need Help?

See full documentation:
- `README.md` - Complete guide
- `INSTALLATION.md` - Detailed setup
- `TESTING.md` - Testing procedures

