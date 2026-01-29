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

-- Create report_verifications table if it does not exist
CREATE TABLE IF NOT EXISTS `report_verifications` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `report_id` INT(11) NOT NULL,
    `verifier_user_id` INT(11) NOT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `distance_km` DECIMAL(5, 2) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`report_id`) REFERENCES `reports`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`verifier_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `uniq_report_verifier` (`report_id`, `verifier_user_id`),
    INDEX `idx_report_id` (`report_id`)
);
