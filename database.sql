-- ============================================================
--  Public Transportation Operations System
--  Master Database Script (Combined)
--  Includes: base schema + profile images + user activation
--             + route definitions/stops + route-only reports
-- ============================================================

CREATE DATABASE IF NOT EXISTS `transport_ops`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `transport_ops`;

-- ============================================================
--  TABLE: users
--  Stores all system users (Admins and Commuters)
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `name`          VARCHAR(255) NOT NULL,
    `email`         VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255)          DEFAULT NULL,
    `password`      VARCHAR(255) NOT NULL,
    `role`          ENUM('Admin','Commuter') NOT NULL DEFAULT 'Commuter',
    `is_active`     TINYINT(1)   NOT NULL DEFAULT 1,
    `created_at`    TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY  `uq_email`          (`email`),
    INDEX       `idx_email`         (`email`),
    INDEX       `idx_role`          (`role`),
    INDEX       `idx_is_active`     (`is_active`),
    INDEX       `idx_profile_image` (`profile_image`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: puv_units
--  Stores Public Utility Vehicle fleet information
-- ============================================================
CREATE TABLE IF NOT EXISTS `puv_units` (
    `id`            INT(11)      NOT NULL AUTO_INCREMENT,
    `plate_number`  VARCHAR(50)  NOT NULL,
    `vehicle_type`  ENUM('Bus','Jeepney','Tricycle','UV Express','Taxi','Train','Other')
                                 NOT NULL DEFAULT 'Bus',
    `current_route` VARCHAR(255) NOT NULL,
    `crowd_status`  ENUM('Light','Moderate','Heavy') NOT NULL DEFAULT 'Light',
    `created_at`    TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP             DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY  `uq_plate_number`   (`plate_number`),
    INDEX       `idx_plate_number`  (`plate_number`),
    INDEX       `idx_vehicle_type`  (`vehicle_type`),
    INDEX       `idx_crowd_status`  (`crowd_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: route_definitions
--  Named route definitions (e.g. "Guadalupe - FTI Tenement")
--  Must be created before route_stops and reports (FK deps)
-- ============================================================
CREATE TABLE IF NOT EXISTS `route_definitions` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_route_def_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: route_stops
--  Ordered stops along a route_definition, used for map display
--  and geofence validation of commuter reports
-- ============================================================
CREATE TABLE IF NOT EXISTS `route_stops` (
    `id`                   INT(11)        NOT NULL AUTO_INCREMENT,
    `route_definition_id`  INT(11)        NOT NULL,
    `stop_name`            VARCHAR(255)   NOT NULL,
    `latitude`             DECIMAL(10,8)  NOT NULL,
    `longitude`            DECIMAL(11,8)  NOT NULL,
    `stop_order`           INT(11)        NOT NULL DEFAULT 0,
    `created_at`           TIMESTAMP               DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`route_definition_id`)
        REFERENCES `route_definitions`(`id`) ON DELETE CASCADE,
    INDEX `idx_route_def_stops` (`route_definition_id`),
    INDEX `idx_stop_order`      (`stop_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: routes
--  Schedule-level route records (departure/arrival times)
-- ============================================================
CREATE TABLE IF NOT EXISTS `routes` (
    `id`                  INT(11)      NOT NULL AUTO_INCREMENT,
    `route_name`          VARCHAR(255) NOT NULL,
    `origin`              VARCHAR(255) NOT NULL,
    `destination`         VARCHAR(255) NOT NULL,
    `scheduled_departure` TIME                  DEFAULT NULL,
    `estimated_arrival`   TIME                  DEFAULT NULL,
    `status`              ENUM('On Time','Delayed','Cancelled') DEFAULT 'On Time',
    `created_at`          TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_route_name` (`route_name`),
    INDEX `idx_status`     (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: delay_analytics
--  Aggregated delay trend data for reporting
-- ============================================================
CREATE TABLE IF NOT EXISTS `delay_analytics` (
    `id`             INT(11)      NOT NULL AUTO_INCREMENT,
    `route_id`       INT(11)               DEFAULT NULL,
    `puv_id`         INT(11)               DEFAULT NULL,
    `delay_duration` INT(11)               DEFAULT NULL COMMENT 'minutes',
    `delay_reason`   VARCHAR(255)          DEFAULT NULL,
    `occurred_at`    TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_occurred_at` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: reports
--  Commuter crowd / delay reports
--  - route_definition_id links to a named route (preferred)
--  - puv_id is nullable; legacy reports may still reference a vehicle
-- ============================================================
CREATE TABLE IF NOT EXISTS `reports` (
    `id`                   INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`              INT(11)       NOT NULL,
    `route_definition_id`  INT(11)                DEFAULT NULL,
    `puv_id`               INT(11)                DEFAULT NULL,
    `crowd_level`          ENUM('Light','Moderate','Heavy') NOT NULL,
    `delay_reason`         TEXT                   DEFAULT NULL,
    `latitude`             DECIMAL(10,8)          DEFAULT NULL,
    `longitude`            DECIMAL(11,8)          DEFAULT NULL,
    `timestamp`            TIMESTAMP              DEFAULT CURRENT_TIMESTAMP,
    `trust_score`          DECIMAL(3,2)           DEFAULT 1.00,
    `is_verified`          TINYINT(1)             DEFAULT 0,
    `geofence_validated`   TINYINT(1)             DEFAULT 0,
    `peer_verifications`   INT(11)                DEFAULT 0,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_reports_user`
        FOREIGN KEY (`user_id`)             REFERENCES `users`(`id`)            ON DELETE CASCADE,
    CONSTRAINT `fk_reports_puv`
        FOREIGN KEY (`puv_id`)              REFERENCES `puv_units`(`id`)         ON DELETE SET NULL,
    CONSTRAINT `fk_reports_route_def`
        FOREIGN KEY (`route_definition_id`) REFERENCES `route_definitions`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_id`             (`user_id`),
    INDEX `idx_puv_id`              (`puv_id`),
    INDEX `idx_route_definition_id` (`route_definition_id`),
    INDEX `idx_timestamp`           (`timestamp`),
    INDEX `idx_trust_score`         (`trust_score`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: report_verifications
--  Tracks individual peer verifications per report
--  Each user can verify a given report only once (unique key)
-- ============================================================
CREATE TABLE IF NOT EXISTS `report_verifications` (
    `id`               INT(11)       NOT NULL AUTO_INCREMENT,
    `report_id`        INT(11)       NOT NULL,
    `verifier_user_id` INT(11)       NOT NULL,
    `latitude`         DECIMAL(10,8)          DEFAULT NULL,
    `longitude`        DECIMAL(11,8)          DEFAULT NULL,
    `distance_km`      DECIMAL(5,2)           DEFAULT NULL,
    `created_at`       TIMESTAMP              DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_rv_report`
        FOREIGN KEY (`report_id`)        REFERENCES `reports`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rv_verifier`
        FOREIGN KEY (`verifier_user_id`) REFERENCES `users`(`id`)   ON DELETE CASCADE,
    UNIQUE KEY `uniq_report_verifier` (`report_id`, `verifier_user_id`),
    INDEX `idx_report_id` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;