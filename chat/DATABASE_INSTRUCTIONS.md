# Database Setup Instructions

## Quick Setup

### Option 1: Using phpMyAdmin

1. Log in to phpMyAdmin
2. Select your database
3. Click on the "SQL" tab
4. Copy and paste the contents of `DATABASE_SETUP.sql`
5. Click "Go" to execute

### Option 2: Using MySQL Command Line

```bash
mysql -u your_username -p your_database_name < chat/DATABASE_SETUP.sql
```

Or:

```bash
mysql -u your_username -p
USE your_database_name;
SOURCE chat/DATABASE_SETUP.sql;
```

### Option 3: Manual Copy-Paste

1. Open `chat/DATABASE_SETUP.sql` in a text editor
2. Copy all the SQL code
3. Paste into your database management tool
4. Execute

## If You Get Foreign Key Errors

If you encounter errors about foreign keys (especially if your `users` table structure is different), use the alternative version:

**Use `DATABASE_SETUP_NO_FK.sql` instead**

This version creates the same tables but without foreign key constraints.

## Verify Installation

After running the SQL, verify the tables were created:

```sql
SHOW TABLES LIKE 'chat_%';
```

You should see:
- `chat_sessions`
- `chat_messages`
- `chat_typing_indicators`
- `chat_notifications`

## Check Table Structure

```sql
DESCRIBE chat_sessions;
DESCRIBE chat_messages;
DESCRIBE chat_typing_indicators;
DESCRIBE chat_notifications;
```

## Troubleshooting

### Error: "Table already exists"
- The script uses `CREATE TABLE IF NOT EXISTS`, so this shouldn't happen
- If it does, the tables are already created - you're good to go!

### Error: "Foreign key constraint fails"
- Use `DATABASE_SETUP_NO_FK.sql` instead
- Or check that your `users` table exists and has an `id` column

### Error: "Unknown collation: utf8mb4_unicode_ci"
- Your MySQL version might be old
- Change `utf8mb4_unicode_ci` to `utf8_general_ci` in the SQL
- Or upgrade MySQL to 5.5.3 or later

## After Setup

Once the tables are created:
1. Set file permissions: `chmod 755 chat/uploads/`
2. Add widget to your pages
3. Test the chat system

## Need Help?

Check the main documentation:
- `README.md` - Complete guide
- `INSTALLATION.md` - Setup instructions
- `QUICK_START.md` - Quick setup guide

