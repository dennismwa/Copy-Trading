-- ============================================================================
-- REFERRAL TIERS SYSTEM - SQL SETUP SCRIPT
-- ============================================================================
-- Run this SQL in phpMyAdmin or MySQL command line
-- Make sure you're using the correct database
-- ============================================================================

-- Step 1: Create referral_tiers table
CREATE TABLE IF NOT EXISTS `referral_tiers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `tier_name` VARCHAR(50) NOT NULL UNIQUE,
    `tier_level` INT NOT NULL UNIQUE COMMENT '1=Bronze, 2=Silver, 3=Gold',
    `referral_earnings_threshold` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Minimum referral earnings to qualify',
    `daily_withdrawal_limit` DECIMAL(15,2) NOT NULL DEFAULT 50000.00 COMMENT 'Custom daily withdrawal limit for this tier',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_tier_level` (`tier_level`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Create user_tier_assignments table
-- NOTE: If foreign key constraints fail, remove the FOREIGN KEY lines and run again
CREATE TABLE IF NOT EXISTS `user_tier_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tier_id` INT NOT NULL,
    `assignment_type` ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic',
    `assigned_by` INT NULL COMMENT 'Admin user ID if manually assigned',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL COMMENT 'NULL means permanent, or set expiration date',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT COMMENT 'Admin notes for manual assignments',
    UNIQUE KEY `unique_active_user_tier` (`user_id`, `is_active`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`tier_id`) REFERENCES `referral_tiers`(`id`) ON DELETE RESTRICT,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_tier_id` (`tier_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_assignment_type` (`assignment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Insert default tiers
-- Bronze Tier
INSERT INTO `referral_tiers` (`tier_name`, `tier_level`, `referral_earnings_threshold`, `daily_withdrawal_limit`, `description`, `is_active`)
VALUES ('Bronze', 1, 10000.00, 75000.00, 'Bronze tier - Entry level for active referrers', 1)
ON DUPLICATE KEY UPDATE 
    `referral_earnings_threshold` = VALUES(`referral_earnings_threshold`),
    `daily_withdrawal_limit` = VALUES(`daily_withdrawal_limit`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- Silver Tier
INSERT INTO `referral_tiers` (`tier_name`, `tier_level`, `referral_earnings_threshold`, `daily_withdrawal_limit`, `description`, `is_active`)
VALUES ('Silver', 2, 50000.00, 150000.00, 'Silver tier - Mid-level for dedicated referrers', 1)
ON DUPLICATE KEY UPDATE 
    `referral_earnings_threshold` = VALUES(`referral_earnings_threshold`),
    `daily_withdrawal_limit` = VALUES(`daily_withdrawal_limit`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- Gold Tier
INSERT INTO `referral_tiers` (`tier_name`, `tier_level`, `referral_earnings_threshold`, `daily_withdrawal_limit`, `description`, `is_active`)
VALUES ('Gold', 3, 200000.00, 500000.00, 'Gold tier - Premium tier for top referrers', 1)
ON DUPLICATE KEY UPDATE 
    `referral_earnings_threshold` = VALUES(`referral_earnings_threshold`),
    `daily_withdrawal_limit` = VALUES(`daily_withdrawal_limit`),
    `description` = VALUES(`description`),
    `updated_at` = NOW();

-- ============================================================================
-- ALTERNATIVE VERSION (If Foreign Keys Cause Issues)
-- ============================================================================
-- If the FOREIGN KEY constraints fail, use this version without foreign keys:

/*
CREATE TABLE IF NOT EXISTS `user_tier_assignments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `tier_id` INT NOT NULL,
    `assignment_type` ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic',
    `assigned_by` INT NULL COMMENT 'Admin user ID if manually assigned',
    `assigned_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` TIMESTAMP NULL COMMENT 'NULL means permanent, or set expiration date',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `notes` TEXT COMMENT 'Admin notes for manual assignments',
    UNIQUE KEY `unique_active_user_tier` (`user_id`, `is_active`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_tier_id` (`tier_id`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_assignment_type` (`assignment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these to verify the setup:

-- Check if tables exist
-- SHOW TABLES LIKE 'referral_tiers';
-- SHOW TABLES LIKE 'user_tier_assignments';

-- View all tiers
-- SELECT * FROM referral_tiers;

-- View all tier assignments
-- SELECT u.id, u.full_name, u.email, rt.tier_name, rt.daily_withdrawal_limit, uta.assignment_type
-- FROM user_tier_assignments uta
-- INNER JOIN users u ON uta.user_id = u.id
-- INNER JOIN referral_tiers rt ON uta.tier_id = rt.id
-- WHERE uta.is_active = 1;

-- ============================================================================
-- DONE! The referral tiers system is now set up.
-- ============================================================================

