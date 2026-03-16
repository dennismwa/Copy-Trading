# Live Chat Support System

A complete, production-ready, self-hosted live chat support system built with PHP and MySQL. This system functions like Tawk.to but is fully self-hosted with no external dependencies.

## Features

- ✅ **Floating Chat Widget** - Responsive chat box visible on all pages
- ✅ **Real-time Messaging** - AJAX polling for instant message updates
- ✅ **Typing Indicators** - See when users or admins are typing
- ✅ **File Attachments** - Support for images, PDFs, and text files
- ✅ **Admin Panel** - Complete chat management interface
- ✅ **Multiple Admins** - Multiple admins can respond simultaneously
- ✅ **Guest & Registered Users** - Works for both guest and logged-in users
- ✅ **Chat History** - Viewable by both users and admins
- ✅ **Secure** - CSRF protection, input validation, XSS prevention
- ✅ **Mobile Responsive** - Works perfectly on mobile and desktop

## Installation

### Step 1: Database Setup

1. Run the SQL script to create the necessary tables:

```bash
mysql -u your_username -p your_database < chat/database_schema.sql
```

Or import it through phpMyAdmin or your preferred database management tool.

### Step 2: File Permissions

Ensure the uploads directory is writable:

```bash
chmod 755 chat/uploads/
```

### Step 3: Include Chat Widget on Your Pages

Add the chat widget loader to any page where you want the chat to appear. Add this line before the closing `</body>` tag:

```php
<?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
```

For example, if you have a common footer file, add it there. Or add it to specific pages like:

- `index.php`
- `home.php`
- `about.php`
- `contact.php`
- etc.

### Step 4: Access Admin Panel

Navigate to the admin chat panel:

```
https://yoursite.com/admin/chat.php
```

You must be logged in as an admin to access this page.

## Configuration

### Chat Settings

Edit `chat/config.php` to customize:

- **Upload Directory**: `CHAT_UPLOAD_DIR` - Where files are stored
- **Max File Size**: `CHAT_MAX_FILE_SIZE` - Default is 5MB
- **Allowed File Types**: `CHAT_ALLOWED_FILE_TYPES` - Modify as needed
- **Poll Interval**: `CHAT_POLL_INTERVAL` - How often to check for new messages (milliseconds)
- **Typing Timeout**: `CHAT_TYPING_TIMEOUT` - How long typing indicator shows (seconds)

### Styling

The chat widget uses CSS that can be customized in `chat/widget/chat-widget.css`. The default theme uses a purple gradient, but you can modify colors to match your site's design.

## Usage

### For Website Visitors

1. Click the chat button (bottom-right corner)
2. Enter name and optional email
3. Start chatting with support

### For Admins

1. Log in to admin panel
2. Navigate to "Live Chat" section
3. View all active chats
4. Click on a chat to open and respond
5. Use "Close" or "Archive" buttons to manage chats

## API Endpoints

The system includes the following API endpoints:

- `POST /chat/api/create_session.php` - Create or get chat session
- `POST /chat/api/send_message.php` - Send a message
- `GET /chat/api/get_messages.php` - Get messages for a chat
- `POST /chat/api/typing.php` - Update typing indicator
- `GET /chat/api/get_chats.php` - Get chat list (admin only)
- `POST /chat/api/close_chat.php` - Close/archive chat (admin only)

## Database Structure

### Tables

- **chat_sessions** - Stores chat sessions
- **chat_messages** - Stores all messages
- **chat_typing_indicators** - Real-time typing indicators
- **chat_notifications** - Message notifications

See `database_schema.sql` for complete table structure.

## Security Features

- ✅ **CSRF Protection** - All forms protected with CSRF tokens
- ✅ **Input Sanitization** - All user input sanitized
- ✅ **XSS Prevention** - HTML entities escaped
- ✅ **SQL Injection Prevention** - Prepared statements used throughout
- ✅ **File Upload Validation** - File type and size validation
- ✅ **Access Control** - Admin-only endpoints protected
- ✅ **Session Management** - Secure session handling

## Troubleshooting

### Chat widget not appearing

1. Check that you've included the loader file
2. Check browser console for JavaScript errors
3. Verify file paths are correct

### Messages not sending

1. Check database connection in `config/database.php`
2. Verify CSRF token is being generated
3. Check PHP error logs

### File uploads not working

1. Verify `chat/uploads/` directory exists and is writable
2. Check file size limits in PHP configuration
3. Verify allowed file types in `chat/config.php`

### Admin panel access denied

1. Ensure you're logged in as admin
2. Check `isAdmin()` function in `config/database.php`
3. Verify session is active

## Customization

### Change Chat Button Position

Edit `chat/widget/chat-widget.css` and modify:

```css
#chat-widget-container {
    bottom: 20px;  /* Change position */
    right: 20px;   /* Change position */
}
```

### Change Colors

The default theme uses purple gradients. To change:

1. Edit `chat/widget/chat-widget.css`
2. Search for color values like `#667eea` and `#764ba2`
3. Replace with your brand colors

### Modify Poll Interval

Edit `chat/config.php`:

```php
define('CHAT_POLL_INTERVAL', 2000); // Change to desired milliseconds
```

## Performance

- Uses efficient AJAX polling (configurable interval)
- Database indexes on frequently queried columns
- Automatic cleanup of expired typing indicators
- Optimized queries with proper joins

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## License

This system is provided as-is for use in your projects.

## Support

For issues or questions, check the code comments or review the implementation in the source files.

## Changelog

### Version 1.0.0
- Initial release
- Full chat functionality
- Admin panel
- File attachments
- Real-time updates
- Typing indicators

