# Live Chat Support System - Complete Overview

## System Architecture

```
chat/
├── database_schema.sql          # Database tables creation script
├── config.php                   # Chat system configuration
├── README.md                    # Complete documentation
├── INSTALLATION.md              # Quick installation guide
├── FEATURES.md                  # Feature list
├── example-integration.php      # Integration examples
│
├── api/                         # Backend API endpoints
│   ├── create_session.php       # Create/get chat session
│   ├── send_message.php         # Send a message
│   ├── get_messages.php         # Retrieve messages
│   ├── typing.php               # Typing indicator
│   ├── get_chats.php            # Get chat list (admin)
│   └── close_chat.php           # Close/archive chat (admin)
│
├── widget/                      # Frontend chat widget
│   ├── chat-widget.css          # Widget styles
│   ├── chat-widget.js           # Widget functionality
│   └── chat-widget-loader.php   # Widget loader/includer
│
└── uploads/                     # File uploads directory
    ├── .htaccess                # Security rules
    └── index.php                # Secure file serving
```

## File Structure Explained

### Database
- **database_schema.sql**: Creates 4 tables:
  - `chat_sessions` - Chat conversations
  - `chat_messages` - Individual messages
  - `chat_typing_indicators` - Real-time typing status
  - `chat_notifications` - Message notifications

### Configuration
- **config.php**: 
  - Database connection (uses existing config)
  - Chat-specific settings
  - Helper functions
  - Security functions

### API Endpoints
All endpoints return JSON and handle:
- Authentication/authorization
- Input validation
- Error handling
- Security checks

### Frontend Widget
- **chat-widget.css**: Complete styling (responsive, modern)
- **chat-widget.js**: Full functionality (no dependencies)
- **chat-widget-loader.php**: Easy integration

### Admin Panel
- **admin/chat.php**: Complete admin interface
  - Chat list with filters
  - Real-time messaging
  - Statistics dashboard
  - Chat management

## Data Flow

### User Sends Message
1. User types in widget
2. JavaScript sends POST to `send_message.php`
3. API validates and stores in database
4. Response returned to widget
5. Message displayed immediately
6. Admin panel polls for updates

### Admin Responds
1. Admin types in admin panel
2. JavaScript sends POST to `send_message.php`
3. API validates and stores
4. Widget polls and receives new message
5. Message displayed to user

### Real-Time Updates
- Widget polls `get_messages.php` every 2 seconds
- Admin panel polls every 2 seconds
- Typing indicators updated separately
- Unread counts updated on chat list

## Security Measures

1. **CSRF Protection**: All forms use tokens
2. **Input Sanitization**: All user input sanitized
3. **SQL Injection**: Prepared statements only
4. **XSS Prevention**: HTML entities escaped
5. **File Uploads**: Type and size validation
6. **Access Control**: Session-based authorization
7. **File Serving**: Access-controlled file serving

## Integration Points

### With Existing System
- Uses existing `config/database.php`
- Uses existing authentication (`isAdmin()`, `isLoggedIn()`)
- Uses existing CSRF functions
- Uses existing sanitization functions
- Integrates with existing user system

### Adding to Pages
Simply include one line:
```php
<?php include __DIR__ . '/chat/widget/chat-widget-loader.php'; ?>
```

## Configuration Options

All configurable in `chat/config.php`:
- Upload directory path
- Max file size
- Allowed file types
- Poll interval
- Typing timeout
- Session timeout

## Performance Considerations

- Database indexes on frequently queried columns
- Efficient queries with proper joins
- Minimal JavaScript (no frameworks)
- CSS optimized for performance
- Polling interval configurable
- Automatic cleanup of expired data

## Scalability

- Supports multiple concurrent chats
- Multiple admins can respond
- Efficient database structure
- Can handle high message volume
- File storage organized by chat

## Maintenance

- Error logging to PHP error log
- Database cleanup can be automated
- File cleanup for old attachments
- Session cleanup for expired sessions

## Testing Checklist

- [ ] Chat widget appears on pages
- [ ] Guest can start chat
- [ ] Guest can send messages
- [ ] Registered user can chat
- [ ] Admin can view chats
- [ ] Admin can respond
- [ ] File uploads work
- [ ] Typing indicators work
- [ ] Real-time updates work
- [ ] Mobile responsive
- [ ] Security measures active

## Support & Troubleshooting

See `README.md` for:
- Installation instructions
- Configuration guide
- Troubleshooting tips
- Customization options

## License & Usage

This system is provided as-is for use in your projects. Modify as needed for your requirements.

