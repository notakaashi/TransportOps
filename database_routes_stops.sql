-- Route definitions with stops for map display
-- Run this after database.sql to add route-with-stops feature

USE `transport_ops`;

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

-- Routes are matched to PUVs by name: puv_units.current_route = route_definitions.name
