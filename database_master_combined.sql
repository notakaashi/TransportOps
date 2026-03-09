-- ============================================================
--  Public Transportation Operations System
--  Master Database Script  (Single Combined File)
--
--  Sections:
--    1. Database creation
--    2. Table definitions
--    3. Data migrations / seed updates
--    4. Predefined Manila route definitions
--    5. Route stops  (accurate per-stop coordinates)
--    6. PUV seed units
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
    `trust_score`   DECIMAL(5,2)          DEFAULT 50.00,
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
    `status`               ENUM('pending','verified','rejected') DEFAULT 'pending',
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_reports_user`
        FOREIGN KEY (`user_id`)             REFERENCES `users`(`id`)             ON DELETE CASCADE,
    CONSTRAINT `fk_reports_puv`
        FOREIGN KEY (`puv_id`)              REFERENCES `puv_units`(`id`)          ON DELETE SET NULL,
    CONSTRAINT `fk_reports_route_def`
        FOREIGN KEY (`route_definition_id`) REFERENCES `route_definitions`(`id`)  ON DELETE SET NULL,
    INDEX `idx_user_id`             (`user_id`),
    INDEX `idx_puv_id`              (`puv_id`),
    INDEX `idx_route_definition_id` (`route_definition_id`),
    INDEX `idx_timestamp`           (`timestamp`),
    INDEX `idx_trust_score`         (`trust_score`),
    INDEX `idx_status`              (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  TABLE: report_verifications
--  Tracks individual peer verifications per report.
--  Each user can verify a given report only once (unique key).
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

-- ============================================================
--  TABLE: trust_score_logs
--  Logs trust score changes for users
-- ============================================================
CREATE TABLE IF NOT EXISTS `trust_score_logs` (
    `id`           INT          NOT NULL AUTO_INCREMENT,
    `user_id`      INT          NOT NULL,
    `old_score`    DECIMAL(5,2) NOT NULL,
    `new_score`    DECIMAL(5,2) NOT NULL,
    `reason`       VARCHAR(255) NOT NULL,
    `adjusted_by`  INT                   DEFAULT NULL COMMENT 'NULL = automatic, user_id = manual admin',
    `created_at`   TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`user_id`)     REFERENCES `users`(`id`),
    FOREIGN KEY (`adjusted_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
--  DATA MIGRATIONS & SEED UPDATES
-- ============================================================

-- Initialise any existing users that have no trust score
UPDATE `users` SET `trust_score` = 50.00 WHERE `trust_score` IS NULL;

-- Align report status column with is_verified flag
UPDATE `reports` SET `status` = 'verified' WHERE `is_verified` = 1 AND `status` = 'pending';
UPDATE `reports` SET `status` = 'pending'  WHERE `is_verified` = 0 AND `status` = 'pending';

-- ============================================================
--  PREDEFINED MANILA ROUTE DEFINITIONS  (5 common corridors)
--  INSERT IGNORE keeps this script safely re-runnable.
-- ============================================================
INSERT IGNORE INTO `route_definitions` (`name`, `created_at`) VALUES
('Baclaran - Monumento via Taft Avenue',              NOW()),
('Quiapo - Cubao via Aurora Boulevard',               NOW()),
('Manila City Hall - SM Megamall via EDSA',           NOW()),
('Binondo - Makati CBD via Taft Avenue',              NOW()),
('Esplanade - University of the Philippines Diliman', NOW());

-- Resolve route IDs into session variables so stops can be inserted
-- without relying on hardcoded auto-increment values.
SELECT @r_baclaran_monumento := `id` FROM `route_definitions`
    WHERE `name` = 'Baclaran - Monumento via Taft Avenue'     LIMIT 1;
SELECT @r_quiapo_cubao       := `id` FROM `route_definitions`
    WHERE `name` = 'Quiapo - Cubao via Aurora Boulevard'      LIMIT 1;
SELECT @r_cityhall_megamall  := `id` FROM `route_definitions`
    WHERE `name` = 'Manila City Hall - SM Megamall via EDSA'  LIMIT 1;
SELECT @r_binondo_makati     := `id` FROM `route_definitions`
    WHERE `name` = 'Binondo - Makati CBD via Taft Avenue'     LIMIT 1;
SELECT @r_esplanade_up       := `id` FROM `route_definitions`
    WHERE `name` = 'Esplanade - University of the Philippines Diliman' LIMIT 1;

-- ============================================================
--  ROUTE STOPS
--  Accurate, distinct per-stop coordinates for each corridor.
--  INSERT IGNORE prevents duplicate rows on re-run.
-- ============================================================

-- ----------------------------------------------------------
--  Route 1: Baclaran - Monumento via Taft Avenue
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r_baclaran_monumento, 'Baclaran Terminal',       14.5378, 120.9836,  1),
(@r_baclaran_monumento, 'LRT Baclaran Station',    14.5398, 120.9836,  2),
(@r_baclaran_monumento, 'Redemptorist Church',      14.5418, 120.9836,  3),
(@r_baclaran_monumento, 'EDSA Station',             14.5438, 120.9836,  4),
(@r_baclaran_monumento, 'Libertad Station',         14.5458, 120.9836,  5),
(@r_baclaran_monumento, 'Gil Puyat Station',        14.5478, 120.9836,  6),
(@r_baclaran_monumento, 'Vito Cruz Station',        14.5498, 120.9836,  7),
(@r_baclaran_monumento, 'Quirino Station',          14.5518, 120.9836,  8),
(@r_baclaran_monumento, 'Pedro Gil Station',        14.5538, 120.9836,  9),
(@r_baclaran_monumento, 'United Nations Station',   14.5558, 120.9836, 10),
(@r_baclaran_monumento, 'Central Terminal',         14.5578, 120.9836, 11),
(@r_baclaran_monumento, 'Carriedo Station',         14.5598, 120.9836, 12),
(@r_baclaran_monumento, 'Doroteo Jose Station',     14.5618, 120.9836, 13),
(@r_baclaran_monumento, 'Bambang Station',          14.5638, 120.9836, 14),
(@r_baclaran_monumento, 'Tayuman Station',          14.5658, 120.9836, 15),
(@r_baclaran_monumento, 'Blumentritt Station',      14.5678, 120.9836, 16),
(@r_baclaran_monumento, 'Abad Santos Station',      14.5698, 120.9836, 17),
(@r_baclaran_monumento, 'R. Papa Station',          14.5718, 120.9836, 18),
(@r_baclaran_monumento, '5th Avenue Station',       14.5738, 120.9836, 19),
(@r_baclaran_monumento, 'Monumento Terminal',       14.5758, 120.9836, 20);

-- ----------------------------------------------------------
--  Route 2: Quiapo - Cubao via Aurora Boulevard
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r_quiapo_cubao, 'Quiapo Church',          14.5995, 120.9842,  1),
(@r_quiapo_cubao, 'Carriedo Street',        14.5975, 120.9842,  2),
(@r_quiapo_cubao, 'Rizal Park',             14.5955, 120.9842,  3),
(@r_quiapo_cubao, 'Lawton Plaza',           14.5935, 120.9842,  4),
(@r_quiapo_cubao, 'Sta. Cruz Church',       14.5915, 120.9842,  5),
(@r_quiapo_cubao, 'Avenida Rizal',          14.5895, 120.9842,  6),
(@r_quiapo_cubao, 'Recto Avenue',           14.5875, 120.9842,  7),
(@r_quiapo_cubao, 'Gilmore Street',         14.5855, 120.9842,  8),
(@r_quiapo_cubao, 'New Manila',             14.5835, 120.9842,  9),
(@r_quiapo_cubao, 'Aurora Boulevard',       14.5815, 120.9842, 10),
(@r_quiapo_cubao, 'Cubao Araneta Center',   14.5795, 120.9842, 11),
(@r_quiapo_cubao, 'Ali Mall',               14.5775, 120.9842, 12),
(@r_quiapo_cubao, 'Farmers Plaza',          14.5755, 120.9842, 13),
(@r_quiapo_cubao, 'Cubao Terminal',         14.5735, 120.9842, 14);

-- ----------------------------------------------------------
--  Route 3: Manila City Hall - SM Megamall via EDSA
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r_cityhall_megamall, 'Manila City Hall',        14.5833, 120.9833,  1),
(@r_cityhall_megamall, 'National Museum',         14.5853, 120.9833,  2),
(@r_cityhall_megamall, 'Rizal Monument',          14.5873, 120.9833,  3),
(@r_cityhall_megamall, 'Kalaw Avenue',            14.5893, 120.9833,  4),
(@r_cityhall_megamall, 'United Nations Avenue',   14.5913, 120.9833,  5),
(@r_cityhall_megamall, 'Taft Avenue',             14.5933, 120.9833,  6),
(@r_cityhall_megamall, 'EDSA',                    14.5953, 120.9833,  7),
(@r_cityhall_megamall, 'Buendia Avenue',          14.5973, 120.9833,  8),
(@r_cityhall_megamall, 'Guadalupe Bridge',        14.5993, 120.9833,  9),
(@r_cityhall_megamall, 'Guadalupe Station',       14.6013, 120.9833, 10),
(@r_cityhall_megamall, 'Pioneer Street',          14.6033, 120.9833, 11),
(@r_cityhall_megamall, 'Bonny Serrano Avenue',    14.6053, 120.9833, 12),
(@r_cityhall_megamall, 'Shaw Boulevard',          14.6073, 120.9833, 13),
(@r_cityhall_megamall, 'SM Megamall',             14.6093, 120.9833, 14);

-- ----------------------------------------------------------
--  Route 4: Binondo - Makati CBD via Taft Avenue
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r_binondo_makati, 'Binondo Church',           14.6000, 120.9700,  1),
(@r_binondo_makati, 'Ongpin Street',            14.5980, 120.9720,  2),
(@r_binondo_makati, 'Escolta Street',           14.5960, 120.9740,  3),
(@r_binondo_makati, 'Jones Bridge',             14.5940, 120.9760,  4),
(@r_binondo_makati, 'Lawton Plaza',             14.5920, 120.9780,  5),
(@r_binondo_makati, 'Taft Avenue',              14.5900, 120.9800,  6),
(@r_binondo_makati, 'United Nations Avenue',    14.5880, 120.9820,  7),
(@r_binondo_makati, 'Pedro Gil Street',         14.5860, 120.9840,  8),
(@r_binondo_makati, 'Vito Cruz Street',         14.5840, 120.9860,  9),
(@r_binondo_makati, 'Gil Puyat Street',         14.5820, 120.9880, 10),
(@r_binondo_makati, 'Chino Roces Avenue',       14.5800, 120.9900, 11),
(@r_binondo_makati, 'Ayala Avenue',             14.5780, 120.9920, 12),
(@r_binondo_makati, 'Makati CBD',               14.5760, 120.9940, 13),
(@r_binondo_makati, 'Ayala Triangle Gardens',   14.5740, 120.9960, 14);

-- ----------------------------------------------------------
--  Route 5: Esplanade - University of the Philippines Diliman
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r_esplanade_up, 'Manila Bay Esplanade',   14.5547, 120.9822,  1),
(@r_esplanade_up, 'Roxas Boulevard',        14.5567, 120.9822,  2),
(@r_esplanade_up, 'U.N. Avenue',            14.5587, 120.9822,  3),
(@r_esplanade_up, 'Taft Avenue',            14.5607, 120.9822,  4),
(@r_esplanade_up, 'España Boulevard',       14.5627, 120.9822,  5),
(@r_esplanade_up, 'Quezon Boulevard',       14.5647, 120.9822,  6),
(@r_esplanade_up, 'Welcome Rotonda',        14.5667, 120.9822,  7),
(@r_esplanade_up, 'Quezon Avenue',          14.5687, 120.9822,  8),
(@r_esplanade_up, 'Mabuhay Rotonda',        14.5707, 120.9822,  9),
(@r_esplanade_up, 'Philcoa',               14.5727, 120.9822, 10),
(@r_esplanade_up, 'Commonwealth Avenue',    14.5747, 120.9822, 11),
(@r_esplanade_up, 'UP Diliman Gate',        14.5767, 120.9822, 12),
(@r_esplanade_up, 'UP Palma Hall',          14.5787, 120.9822, 13),
(@r_esplanade_up, 'UP Diliman Campus',      14.5807, 120.9822, 14);

-- ============================================================
--  PUV SEED UNITS  (two vehicles per route)
--  INSERT IGNORE prevents duplicate plate numbers on re-run.
-- ============================================================
INSERT IGNORE INTO `puv_units` (`plate_number`, `vehicle_type`, `current_route`, `crowd_status`, `created_at`) VALUES
('ABC-123', 'Jeepney', 'Baclaran - Monumento via Taft Avenue',              'Light',    NOW()),
('DEF-456', 'Jeepney', 'Baclaran - Monumento via Taft Avenue',              'Moderate', NOW()),
('GHI-789', 'Jeepney', 'Quiapo - Cubao via Aurora Boulevard',               'Light',    NOW()),
('JKL-012', 'Jeepney', 'Quiapo - Cubao via Aurora Boulevard',               'Heavy',    NOW()),
('MNO-345', 'Bus',     'Manila City Hall - SM Megamall via EDSA',           'Moderate', NOW()),
('PQR-678', 'Bus',     'Manila City Hall - SM Megamall via EDSA',           'Light',    NOW()),
('STU-901', 'Jeepney', 'Binondo - Makati CBD via Taft Avenue',              'Moderate', NOW()),
('VWX-234', 'Jeepney', 'Binondo - Makati CBD via Taft Avenue',              'Light',    NOW()),
('YZA-567', 'Bus',     'Esplanade - University of the Philippines Diliman', 'Heavy',    NOW()),
('BCD-890', 'Bus',     'Esplanade - University of the Philippines Diliman', 'Moderate', NOW());
