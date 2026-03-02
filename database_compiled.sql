-- =====================================================================
-- TransportOps Database - Complete Compiled Script
-- =====================================================================
-- This file contains the complete database schema and all migrations
-- Run this script to create the database from scratch or apply all updates
-- =====================================================================

-- =====================================================================
-- SECTION 1: Database Creation and Core Tables
-- =====================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS `transport_ops` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `transport_ops`;

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('Admin', 'Commuter') NOT NULL DEFAULT 'Commuter',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_email` (`email`),
    INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create puv_units table
CREATE TABLE IF NOT EXISTS `puv_units` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `plate_number` VARCHAR(50) NOT NULL UNIQUE,
    `vehicle_type` ENUM('Bus', 'Jeepney', 'Tricycle', 'UV Express', 'Taxi', 'Train', 'Other') NOT NULL DEFAULT 'Bus',
    `current_route` VARCHAR(255) NOT NULL,
    `crowd_status` ENUM('Light', 'Moderate', 'Heavy') NOT NULL DEFAULT 'Light',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_plate_number` (`plate_number`),
    INDEX `idx_vehicle_type` (`vehicle_type`),
    INDEX `idx_crowd_status` (`crowd_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reports table
CREATE TABLE IF NOT EXISTS `reports` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `puv_id` INT(11) NOT NULL,
    `crowd_level` ENUM('Light', 'Moderate', 'Heavy') NOT NULL,
    `delay_reason` TEXT DEFAULT NULL,
    `latitude` DECIMAL(10, 8) DEFAULT NULL,
    `longitude` DECIMAL(11, 8) DEFAULT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `trust_score` DECIMAL(3, 2) DEFAULT 1.00,
    `is_verified` TINYINT(1) DEFAULT 0,
    `geofence_validated` TINYINT(1) DEFAULT 0,
    `peer_verifications` INT(11) DEFAULT 0,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`puv_id`) REFERENCES `puv_units`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_puv_id` (`puv_id`),
    INDEX `idx_timestamp` (`timestamp`),
    INDEX `idx_trust_score` (`trust_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create report_verifications table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create routes table for schedule management
CREATE TABLE IF NOT EXISTS `routes` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `route_name` VARCHAR(255) NOT NULL,
    `origin` VARCHAR(255) NOT NULL,
    `destination` VARCHAR(255) NOT NULL,
    `scheduled_departure` TIME DEFAULT NULL,
    `estimated_arrival` TIME DEFAULT NULL,
    `status` ENUM('On Time', 'Delayed', 'Cancelled') DEFAULT 'On Time',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_route_name` (`route_name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create delay_analytics table for trend analysis
CREATE TABLE IF NOT EXISTS `delay_analytics` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `route_id` INT(11) DEFAULT NULL,
    `puv_id` INT(11) DEFAULT NULL,
    `delay_duration` INT(11) DEFAULT NULL,
    `delay_reason` VARCHAR(255) DEFAULT NULL,
    `occurred_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_occurred_at` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 2: User Table Enhancements
-- =====================================================================

-- Add user activation/deactivation functionality
ALTER TABLE `users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `role`;

-- Update existing users to be active by default
UPDATE `users` SET `is_active` = 1 WHERE `is_active` IS NULL;

-- Add index for better performance
ALTER TABLE `users` ADD INDEX `idx_is_active` (`is_active`);

-- Add profile image support to users table
ALTER TABLE `users` ADD COLUMN `profile_image` VARCHAR(255) NULL AFTER `email`;

-- Add index for better performance
ALTER TABLE `users` ADD INDEX `idx_profile_image` (`profile_image`);

-- =====================================================================
-- SECTION 3: Route Definitions and Stops
-- =====================================================================

-- Route definition: e.g. "Guadalupe - FTI Tenement"
CREATE TABLE IF NOT EXISTS `route_definitions` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_route_def_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stops along a route (ordered by stop_order); origin/destination can be first/last stop
CREATE TABLE IF NOT EXISTS `route_stops` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `route_definition_id` INT(11) NOT NULL,
    `stop_name` VARCHAR(255) NOT NULL,
    `latitude` DECIMAL(10, 8) NOT NULL,
    `longitude` DECIMAL(11, 8) NOT NULL,
    `stop_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`route_definition_id`) REFERENCES `route_definitions`(`id`) ON DELETE CASCADE,
    INDEX `idx_route_def_stops` (`route_definition_id`),
    INDEX `idx_stop_order` (`stop_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SECTION 4: Reports Table Migration (Route-Only Support)
-- =====================================================================

-- Add route_definition_id to reports (nullable for existing rows)
ALTER TABLE `reports`
ADD COLUMN `route_definition_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD INDEX `idx_route_definition_id` (`route_definition_id`);

-- Allow puv_id to be NULL so new route-only reports don't need a vehicle
ALTER TABLE `reports` MODIFY `puv_id` INT(11) NULL DEFAULT NULL;

-- Add FK to route_definitions (run after route_definitions table exists)
ALTER TABLE `reports`
ADD CONSTRAINT `fk_reports_route_def`
FOREIGN KEY (`route_definition_id`) REFERENCES `route_definitions`(`id`) ON DELETE SET NULL;

-- =====================================================================
-- OPTIONAL MIGRATIONS (Uncomment if needed)
-- =====================================================================

-- Optional: backfill route_definition_id from puv_units for old reports (so route name displays)
-- UPDATE reports r
-- INNER JOIN puv_units p ON r.puv_id = p.id
-- INNER JOIN route_definitions rd ON rd.name = p.current_route
-- SET r.route_definition_id = rd.id
-- WHERE r.route_definition_id IS NULL;

-- Optional: drop vehicle link for clean route-only (run only when ready to drop old vehicle-based reports)
-- ALTER TABLE reports DROP FOREIGN KEY reports_ibfk_2;
-- ALTER TABLE reports DROP COLUMN puv_id;
-- ALTER TABLE reports MODIFY route_definition_id INT(11) NOT NULL;

-- =====================================================================
-- COMPILED MIGRATION COMPLETE
-- =====================================================================
-- All database migrations have been applied successfully
-- Routes are matched to PUVs by name: puv_units.current_route = route_definitions.name
-- =====================================================================
