-- Add profile image support to users table
-- This migration adds profile_image column to store user profile pictures

ALTER TABLE `users` ADD COLUMN `profile_image` VARCHAR(255) NULL AFTER `email`;

-- Add index for better performance
ALTER TABLE `users` ADD INDEX `idx_profile_image` (`profile_image`);

-- Update existing users to have NULL profile_image (they'll need to upload one)
-- This is handled by the NULL default above
