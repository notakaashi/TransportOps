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
--  TABLE: route_definitions
--  Named route definitions (e.g. "Guadalupe - FTI Tenement")
--  Must be created before route_stops and reports (FK deps)
-- ============================================================
CREATE TABLE IF NOT EXISTS `route_definitions` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(255) NOT NULL,
    `route_type`  ENUM('road', 'lrt', 'mrt') DEFAULT 'road',
    `vehicle_category` ENUM('tricycle','jeepney','rail') NOT NULL DEFAULT 'jeepney',
    `created_at` TIMESTAMP             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_route_def_name` (`name`),
    INDEX `idx_route_type` (`route_type`),
    INDEX `idx_vehicle_category` (`vehicle_category`)
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
--  TABLE: reports
--  Commuter crowd / delay reports
--  - route_definition_id links to a named route (preferred)
-- ============================================================
CREATE TABLE IF NOT EXISTS `reports` (
    `id`                   INT(11)       NOT NULL AUTO_INCREMENT,
    `user_id`              INT(11)       NOT NULL,
    `route_definition_id`  INT(11)                DEFAULT NULL,
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
    CONSTRAINT `fk_reports_route_def`
        FOREIGN KEY (`route_definition_id`) REFERENCES `route_definitions`(`id`)  ON DELETE SET NULL,
    INDEX `idx_user_id`             (`user_id`),
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

UPDATE `reports` SET `status` = 'verified' WHERE `is_verified` = 1 AND `status` = 'pending';
UPDATE `reports` SET `status` = 'pending'  WHERE `is_verified` = 0 AND `status` = 'pending';

-- Add rejections column to reports table if not exists
-- The following block checks if the column exists before adding it
DELIMITER //
CREATE PROCEDURE add_rejections_column_if_not_exists()
BEGIN
    IF NOT EXISTS (
        SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE table_name = 'reports' AND column_name = 'rejections'
    ) THEN
        ALTER TABLE reports ADD COLUMN rejections INT DEFAULT 0 NOT NULL;
    END IF;
END //
DELIMITER ;
CALL add_rejections_column_if_not_exists();
DROP PROCEDURE IF EXISTS add_rejections_column_if_not_exists;

-- ============================================================
--  CURRENT ACTIVE ROUTES FROM PRODUCTION DATABASE
--  These are the routes currently in use with real data
--  INSERT IGNORE keeps this script safely re-runnable.
-- ============================================================
INSERT IGNORE INTO `route_definitions` (`name`, `created_at`) VALUES
('Bagumbayan - Pasig',                     NOW()),
('Guadalupe - FTI',                        NOW()),
('Pasig - Quiapo',                          NOW()),
('LRT-1 Roosevelt to Baclaran',                        NOW()),
('LRT-2 Recto to Antipolo',                          NOW()),
('MRT-3 North Avenue to Taft Avenue',                   NOW()),
('Triumph - Arca South',                    NOW()),
('Triumph - C5 Waterfun',                   NOW()),
('Triumph - FTI Terminal',                  NOW()),
('Triumph - Hagonoy',                       NOW()),
('Triumph - Tenement',                      NOW()),
('FTI Terminal - MOA',                      NOW());

-- Resolve current route IDs into session variables
SELECT @r1_bagumbayan_pasig := `id` FROM `route_definitions`
    WHERE `name` = 'Bagumbayan - Pasig'      LIMIT 1;
SELECT @r2_guadalupe_fti    := `id` FROM `route_definitions`
    WHERE `name` = 'Guadalupe - FTI'         LIMIT 1;
SELECT @r3_pasig_quiapo    := `id` FROM `route_definitions`
    WHERE `name` = 'Pasig - Quiapo'          LIMIT 1;
SELECT @lrt1_roosevelt_baclaran := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-1 Roosevelt to Baclaran'         LIMIT 1;
SELECT @lrt2_recto_antipolo    := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-2 Recto to Antipolo'           LIMIT 1;
SELECT @mrt3_north_taft       := `id` FROM `route_definitions`
    WHERE `name` = 'MRT-3 North Avenue to Taft Avenue'   LIMIT 1;

-- Triumph routes (predefined road routes)
SELECT @triumph_arca_south   := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Arca South'   LIMIT 1;
SELECT @triumph_c5_waterfun  := `id` FROM `route_definitions` WHERE `name` = 'Triumph - C5 Waterfun'  LIMIT 1;
SELECT @triumph_fti_terminal := `id` FROM `route_definitions` WHERE `name` = 'Triumph - FTI Terminal' LIMIT 1;
SELECT @triumph_hagonoy      := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Hagonoy'      LIMIT 1;
SELECT @triumph_tenement     := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Tenement'     LIMIT 1;
SELECT @fti_terminal_moa     := `id` FROM `route_definitions` WHERE `name` = 'FTI Terminal - MOA'     LIMIT 1;

-- Update route types for LRT/MRT routes (now that table and routes exist)
UPDATE `route_definitions` SET `route_type` = 'lrt' 
WHERE `name` IN ('LRT-1 Roosevelt to Baclaran', 'LRT-2 Recto to Antipolo');

UPDATE `route_definitions` SET `route_type` = 'mrt' 
WHERE `name` = 'MRT-3 North Avenue to Taft Avenue';

-- Ensure Triumph routes are marked as road routes (default, but explicit for clarity)
UPDATE `route_definitions` SET `route_type` = 'road'
WHERE `name` IN (
  'Triumph - Arca South',
  'Triumph - C5 Waterfun',
  'Triumph - FTI Terminal',
  'Triumph - Hagonoy',
  'Triumph - Tenement',
  'FTI Terminal - MOA'
);

-- Vehicle category assignment
UPDATE `route_definitions`
SET `vehicle_category` = 'rail'
WHERE `route_type` IN ('lrt', 'mrt')
   OR `name` LIKE 'LRT-%'
   OR `name` LIKE 'MRT-%';

UPDATE `route_definitions`
SET `vehicle_category` = 'tricycle'
WHERE `name` LIKE 'Triumph - %';

-- Explicit overrides
UPDATE `route_definitions`
SET `vehicle_category` = 'jeepney'
WHERE `name` = 'FTI Terminal - MOA';

-- ----------------------------------------------------------
--  Triumph predefined routes (stops + coordinates)
--  Insert stops only if the route has no stops yet.
-- ----------------------------------------------------------

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_arca_south, 'Triumph',   14.50510100, 121.05254600, 0 UNION ALL
  SELECT @triumph_arca_south, 'Palengke',  14.50189700, 121.04950100, 1 UNION ALL
  SELECT @triumph_arca_south, 'United',    14.50127900, 121.04475700, 2 UNION ALL
  SELECT @triumph_arca_south, 'Arca South',14.50571000, 121.03877900, 3
) AS t
WHERE @triumph_arca_south IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_arca_south LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_c5_waterfun, 'Triumph',             14.50504300, 121.05270900, 0 UNION ALL
  SELECT @triumph_c5_waterfun, 'Brgy. Central Signal',14.51096700, 121.05671800, 1 UNION ALL
  SELECT @triumph_c5_waterfun, 'C5 Waterfun',         14.51577900, 121.05181900, 2
) AS t
WHERE @triumph_c5_waterfun IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_c5_waterfun LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_fti_terminal, 'Triumph',      14.50508500, 121.05255400, 0 UNION ALL
  SELECT @triumph_fti_terminal, 'FTI Terminal', 14.50654400, 121.04076400, 1
) AS t
WHERE @triumph_fti_terminal IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_fti_terminal LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_hagonoy, 'Triumph', 14.50454500, 121.05304200, 0 UNION ALL
  SELECT @triumph_hagonoy, 'Hagonoy', 14.50871200, 121.06565300, 1
) AS t
WHERE @triumph_hagonoy IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_hagonoy LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_tenement, 'Triumph',  14.50507500, 121.05255900, 0 UNION ALL
  SELECT @triumph_tenement, 'Tenement', 14.50735000, 121.03703700, 1
) AS t
WHERE @triumph_tenement IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_tenement LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @fti_terminal_moa, 'FTI Terminal', 14.50700000, 121.04134200, 0 UNION ALL
  SELECT @fti_terminal_moa, 'Rotonda',      14.53772100, 121.00111900, 1 UNION ALL
  SELECT @fti_terminal_moa, 'MOA',          14.53507000, 120.98370100, 2
) AS t
WHERE @fti_terminal_moa IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @fti_terminal_moa LIMIT 1);

-- ----------------------------------------------------------
--  Route 1: Bagumbayan - Pasig (17 reports, 2 verified)
--  Actual stops from production database
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r1_bagumbayan_pasig, 'Pasig',          14.55822700, 121.08491000, 0),
(@r1_bagumbayan_pasig, 'Wawa',           14.52381400, 121.07365200, 1),
(@r1_bagumbayan_pasig, 'Hagonoy',        14.50850800, 121.06617300, 2),
(@r1_bagumbayan_pasig, 'Bethel',         14.49749500, 121.06278500, 3),
(@r1_bagumbayan_pasig, 'Bagumbayan',     14.46632700, 121.05610500, 4);

-- ----------------------------------------------------------
--  Route 2: Guadalupe - FTI (3 reports, 0 verified)
--  Actual stops from production database
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r2_guadalupe_fti, 'FTI',             14.50598200, 121.03743100, 0),
(@r2_guadalupe_fti, 'Tenement',        14.50779300, 121.03510400, 1),
(@r2_guadalupe_fti, 'Sto Nino',        14.51116600, 121.03365700, 2),
(@r2_guadalupe_fti, 'Housing',         14.51668800, 121.04797800, 3),
(@r2_guadalupe_fti, 'Phase 1',         14.52297800, 121.05454900, 4),
(@r2_guadalupe_fti, 'BCDA',            14.53021300, 121.05735700, 5),
(@r2_guadalupe_fti, 'Blueboz',         14.53591400, 121.05760800, 6),
(@r2_guadalupe_fti, 'Mckinley',        14.54069700, 121.05565800, 7),
(@r2_guadalupe_fti, 'Market-Market',   14.54652400, 121.05609300, 8),
(@r2_guadalupe_fti, 'Philplans',       14.56072900, 121.05663600, 9),
(@r2_guadalupe_fti, 'Guadalupe',       14.56791200, 121.04619600, 10);

-- ----------------------------------------------------------
--  Route 3: Pasig - Quiapo (1 report, 0 verified)
--  Actual stops from production database
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@r3_pasig_quiapo, 'Quiapo',          14.59715900, 120.98370700, 0),
(@r3_pasig_quiapo, 'Legarda',         14.60052100, 120.99634900, 1),
(@r3_pasig_quiapo, 'Pasig',           14.55831100, 121.08494100, 2);

-- ----------------------------------------------------------
--  LRT-1: Roosevelt to Baclaran (20 stations)
--  Exact coordinates provided
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@lrt1_roosevelt_baclaran, 'Roosevelt',      14.6576, 121.0211, 1),
(@lrt1_roosevelt_baclaran, 'Balintawak',      14.6574, 121.0037, 2),
(@lrt1_roosevelt_baclaran, 'Monumento',      14.6544, 120.9837, 3),
(@lrt1_roosevelt_baclaran, '5th Avenue',     14.6444, 120.9836, 4),
(@lrt1_roosevelt_baclaran, 'R. Papa',        14.6362, 120.9823, 5),
(@lrt1_roosevelt_baclaran, 'Abad Santos',    14.6306, 120.9814, 6),
(@lrt1_roosevelt_baclaran, 'Blumentritt',    14.6227, 120.9835, 7),
(@lrt1_roosevelt_baclaran, 'Tayuman',       14.6168, 120.9827, 8),
(@lrt1_roosevelt_baclaran, 'Bambang',       14.6111, 120.9825, 9),
(@lrt1_roosevelt_baclaran, 'Doroteo Jose',   14.6053, 120.9820, 10),
(@lrt1_roosevelt_baclaran, 'Carriedo',      14.5991, 120.9814, 11),
(@lrt1_roosevelt_baclaran, 'Central Terminal', 14.5928, 120.9816, 12),
(@lrt1_roosevelt_baclaran, 'United Nations', 14.5826, 120.9846, 13),
(@lrt1_roosevelt_baclaran, 'Pedro Gil',     14.5765, 120.9882, 14),
(@lrt1_roosevelt_baclaran, 'Quirino',       14.5703, 120.9916, 15),
(@lrt1_roosevelt_baclaran, 'Vito Cruz',     14.5633, 120.9949, 16),
(@lrt1_roosevelt_baclaran, 'Gil Puyat',     14.5543, 120.9971, 17),
(@lrt1_roosevelt_baclaran, 'Libertad',      14.5478, 120.9987, 18),
(@lrt1_roosevelt_baclaran, 'EDSA',          14.5389, 121.0006, 19),
(@lrt1_roosevelt_baclaran, 'Baclaran',      14.5342, 120.9983, 20);

-- ----------------------------------------------------------
--  LRT-2: Recto to Antipolo (13 stations)
--  Exact coordinates provided
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@lrt2_recto_antipolo, 'Recto',           14.6035, 120.9831, 1),
(@lrt2_recto_antipolo, 'Legarda',         14.6009, 120.9926, 2),
(@lrt2_recto_antipolo, 'Pureza',         14.6017, 121.0052, 3),
(@lrt2_recto_antipolo, 'V. Mapa',         14.6042, 121.0172, 4),
(@lrt2_recto_antipolo, 'J. Ruiz',         14.6106, 121.0262, 5),
(@lrt2_recto_antipolo, 'Gilmore',         14.6135, 121.0342, 6),
(@lrt2_recto_antipolo, 'Betty Go-Belmonte', 14.6186, 121.0428, 7),
(@lrt2_recto_antipolo, 'Araneta Cubao',   14.6227, 121.0526, 8),
(@lrt2_recto_antipolo, 'Anonas',         14.6279, 121.0647, 9),
(@lrt2_recto_antipolo, 'Katipunan',       14.6311, 121.0725, 10),
(@lrt2_recto_antipolo, 'Santolan',        14.6221, 121.0859, 11),
(@lrt2_recto_antipolo, 'Marikina–Pasig',  14.6204, 121.1003, 12),
(@lrt2_recto_antipolo, 'Antipolo',        14.6250, 121.1214, 13);

-- ----------------------------------------------------------
--  MRT-3: North Avenue to Taft Avenue (13 stations)
--  Exact coordinates provided
-- ----------------------------------------------------------
INSERT IGNORE INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`) VALUES
(@mrt3_north_taft, 'North Avenue',       14.6520, 121.0325, 1),
(@mrt3_north_taft, 'Quezon Avenue',     14.6430, 121.0380, 2),
(@mrt3_north_taft, 'GMA–Kamuning',      14.6352, 121.0433, 3),
(@mrt3_north_taft, 'Araneta Center–Cubao', 14.6195, 121.0511, 4),
(@mrt3_north_taft, 'Santolan–Annapolis', 14.6078, 121.0564, 5),
(@mrt3_north_taft, 'Ortigas',           14.5878, 121.0567, 6),
(@mrt3_north_taft, 'Shaw Boulevard',     14.5812, 121.0536, 7),
(@mrt3_north_taft, 'Boni',              14.5738, 121.0481, 8),
(@mrt3_north_taft, 'Guadalupe',         14.5668, 121.0455, 9),
(@mrt3_north_taft, 'Buendia',           14.5546, 121.0345, 10),
(@mrt3_north_taft, 'Ayala',             14.5490, 121.0283, 11),
(@mrt3_north_taft, 'Magallanes',         14.5420, 121.0195, 12),
(@mrt3_north_taft, 'Taft Avenue',       14.5377, 121.0022, 13);

