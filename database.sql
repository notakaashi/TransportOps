-- Public Transportation Operations System Database
-- Creates the database and all required tables

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

-- Table to track individual peer verifications per report
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

