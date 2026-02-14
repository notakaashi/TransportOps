-- Add user activation/deactivation functionality
-- Add is_active column to users table

ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

-- Update existing users to be active by default
UPDATE `users` SET `is_active` = 1 WHERE `is_active` IS NULL;

-- Add index for better performance
ALTER TABLE `users` ADD INDEX `idx_is_active` (`is_active`);
