-- Database Update Script
-- Run this to update existing database

USE `transport_ops`;

-- Remove Driver role from users table
ALTER TABLE `users` MODIFY COLUMN `role` ENUM('Admin', 'Commuter') NOT NULL DEFAULT 'Commuter';

-- Update existing Driver users to Commuter
UPDATE `users` SET `role` = 'Commuter' WHERE `role` = 'Driver';

-- Remove lat/lng from puv_units and add vehicle_type
ALTER TABLE `puv_units`
DROP COLUMN IF EXISTS `lat`,
DROP COLUMN IF EXISTS `lng`,
ADD COLUMN `vehicle_type` ENUM('Bus', 'Jeepney', 'Tricycle', 'UV Express', 'Taxi', 'Train', 'Other') NOT NULL DEFAULT 'Bus' AFTER `plate_number`,
ADD INDEX `idx_vehicle_type` (`vehicle_type`);
