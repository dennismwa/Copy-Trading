-- Migration: Add daily_trade_limit column to packages table
-- Description: Adds a configurable daily trade limit per package
-- Date: 2024

-- Add daily_trade_limit column to packages table
-- NULL or 0 means unlimited trades per day
ALTER TABLE `packages` 
ADD COLUMN `daily_trade_limit` INT(11) DEFAULT NULL COMMENT 'Maximum number of trades allowed per user per day for this package. NULL or 0 means unlimited.' 
AFTER `max_investment`;

-- Add index for better query performance
CREATE INDEX `idx_daily_trade_limit` ON `packages` (`daily_trade_limit`);

