-- =====================================================
-- Live Chat Support System - Database Setup (No Foreign Keys)
-- =====================================================
-- Use this version if you encounter foreign key errors
-- or if your users table structure is different
-- =====================================================

-- Table: chat_sessions
CREATE TABLE IF NOT EXISTS `chat_sessions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) DEFAULT NULL COMMENT 'Registered user ID (NULL for guest users)',
  `guest_name` VARCHAR(255) DEFAULT NULL COMMENT 'Guest user name',
  `guest_email` VARCHAR(255) DEFAULT NULL COMMENT 'Guest user email',
  `session_token` VARCHAR(64) NOT NULL COMMENT 'Unique session token for guest users',
  `status` ENUM('open', 'closed', 'archived') DEFAULT 'open',
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_status` (`status`),
  KEY `idx_last_message_at` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT(11) UNSIGNED NOT NULL,
  `sender_role` ENUM('user', 'admin') NOT NULL,
  `sender_id` INT(11) DEFAULT NULL COMMENT 'User ID or Admin ID',
  `message_text` TEXT NOT NULL,
  `attachment_path` VARCHAR(500) DEFAULT NULL COMMENT 'Path to uploaded file/image',
  `attachment_type` VARCHAR(50) DEFAULT NULL COMMENT 'File type: image, file, etc.',
  `is_read` TINYINT(1) DEFAULT 0 COMMENT 'Whether message has been read',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_sender_role` (`sender_role`),
  KEY `idx_is_read` (`is_read`),
  KEY `fk_chat_messages_session` (`chat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_typing_indicators
CREATE TABLE IF NOT EXISTS `chat_typing_indicators` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT(11) UNSIGNED NOT NULL,
  `user_type` ENUM('user', 'admin') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: chat_notifications
CREATE TABLE IF NOT EXISTS `chat_notifications` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `chat_id` INT(11) UNSIGNED NOT NULL,
  `user_type` ENUM('user', 'admin') NOT NULL,
  `user_id` INT(11) DEFAULT NULL,
  `message_id` INT(11) UNSIGNED DEFAULT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_id` (`chat_id`),
  KEY `idx_user_type` (`user_type`),
  KEY `idx_is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create composite indexes for better performance
-- Note: If these indexes already exist, you may get an error - that's okay, just ignore it
CREATE INDEX idx_chat_sessions_composite ON chat_sessions(status, last_message_at DESC);
CREATE INDEX idx_chat_messages_composite ON chat_messages(chat_id, created_at DESC);

-- =====================================================
-- Setup Complete!
-- =====================================================

