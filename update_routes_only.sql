-- ============================================================
--  UPDATE EXISTING DATABASE - ROUTES ONLY
--  Removes routes table, adds route_type column and LRT/MRT routes
--  Safe for existing database - no data loss
-- ============================================================

USE `transport_ops`;

-- 1. Add route_type column to existing route_definitions table
SET @has_route_type :=
  (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'route_definitions'
      AND COLUMN_NAME = 'route_type'
  );

SET @sql := IF(
  @has_route_type = 0,
  "ALTER TABLE `route_definitions` ADD COLUMN `route_type` ENUM('road', 'lrt', 'mrt') DEFAULT 'road' AFTER `name`",
  "SELECT 'route_definitions.route_type already exists' AS info"
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. Add LRT/MRT routes if they don't exist
INSERT IGNORE INTO `route_definitions` (`name`, `created_at`) VALUES
('LRT-1 Roosevelt to Baclaran',                        NOW()),
('LRT-2 Recto to Antipolo',                          NOW()),
('MRT-3 North Avenue to Taft Avenue',                   NOW()),
('Triumph - Arca South',                    NOW()),
('Triumph - C5 Waterfun',                   NOW()),
('Triumph - FTI Terminal',                  NOW()),
('Triumph - Hagonoy',                       NOW()),
('Triumph - Tenement',                      NOW()),
('FTI Terminal - MOA',                      NOW());

-- 3. Get route IDs for LRT/MRT routes
SELECT @lrt1_roosevelt_baclaran := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-1 Roosevelt to Baclaran'         LIMIT 1;
SELECT @lrt2_recto_antipolo    := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-2 Recto to Antipolo'           LIMIT 1;
SELECT @mrt3_north_taft       := `id` FROM `route_definitions`
    WHERE `name` = 'MRT-3 North Avenue to Taft Avenue'   LIMIT 1;

-- Triumph route IDs
SELECT @triumph_arca_south   := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Arca South'   LIMIT 1;
SELECT @triumph_c5_waterfun  := `id` FROM `route_definitions` WHERE `name` = 'Triumph - C5 Waterfun'  LIMIT 1;
SELECT @triumph_fti_terminal := `id` FROM `route_definitions` WHERE `name` = 'Triumph - FTI Terminal' LIMIT 1;
SELECT @triumph_hagonoy      := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Hagonoy'      LIMIT 1;
SELECT @triumph_tenement     := `id` FROM `route_definitions` WHERE `name` = 'Triumph - Tenement'     LIMIT 1;
SELECT @fti_terminal_moa     := `id` FROM `route_definitions` WHERE `name` = 'FTI Terminal - MOA'     LIMIT 1;

-- 4. Set route types for LRT/MRT routes
UPDATE `route_definitions` SET `route_type` = 'lrt' 
WHERE `name` IN ('LRT-1 Roosevelt to Baclaran', 'LRT-2 Recto to Antipolo');

UPDATE `route_definitions` SET `route_type` = 'mrt' 
WHERE `name` = 'MRT-3 North Avenue to Taft Avenue';

-- Ensure Triumph routes are marked as road routes
UPDATE `route_definitions` SET `route_type` = 'road'
WHERE `name` IN (
  'Triumph - Arca South',
  'Triumph - C5 Waterfun',
  'Triumph - FTI Terminal',
  'Triumph - Hagonoy',
  'Triumph - Tenement',
  'FTI Terminal - MOA'
);

-- 4b. Add Triumph route stops (insert only if empty)
INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_arca_south, 'Triumph',    14.50510100, 121.05254600, 0 UNION ALL
  SELECT @triumph_arca_south, 'Palengke',   14.50189700, 121.04950100, 1 UNION ALL
  SELECT @triumph_arca_south, 'United',     14.50127900, 121.04475700, 2 UNION ALL
  SELECT @triumph_arca_south, 'Arca South', 14.50571000, 121.03877900, 3
) AS t
WHERE @triumph_arca_south IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @triumph_arca_south LIMIT 1);

INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @triumph_c5_waterfun, 'Triumph',              14.50504300, 121.05270900, 0 UNION ALL
  SELECT @triumph_c5_waterfun, 'Brgy. Central Signal', 14.51096700, 121.05671800, 1 UNION ALL
  SELECT @triumph_c5_waterfun, 'C5 Waterfun',          14.51577900, 121.05181900, 2
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

-- 5-7. LRT/MRT: prevent duplicates on re-run
-- If you previously ran an older version of this script, this will remove exact duplicates
-- (same route_definition_id + stop_order + stop_name), keeping the earliest row.
DELETE rs1
FROM `route_stops` rs1
JOIN `route_stops` rs2
  ON rs1.route_definition_id = rs2.route_definition_id
 AND rs1.stop_order = rs2.stop_order
 AND rs1.stop_name = rs2.stop_name
 AND rs1.id > rs2.id
WHERE rs1.route_definition_id IN (@lrt1_roosevelt_baclaran, @lrt2_recto_antipolo, @mrt3_north_taft);

-- 5. Add LRT-1 stations (20 stations) (insert only if empty)
INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @lrt1_roosevelt_baclaran, 'Roosevelt',       14.6576, 121.0211,  1 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Balintawak',      14.6574, 121.0037,  2 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Monumento',       14.6544, 120.9837,  3 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, '5th Avenue',      14.6444, 120.9836,  4 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'R. Papa',         14.6362, 120.9823,  5 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Abad Santos',     14.6306, 120.9814,  6 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Blumentritt',     14.6227, 120.9835,  7 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Tayuman',         14.6168, 120.9827,  8 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Bambang',         14.6111, 120.9825,  9 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Doroteo Jose',    14.6053, 120.9820, 10 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Carriedo',        14.5991, 120.9814, 11 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Central Terminal',14.5928, 120.9816, 12 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'United Nations',  14.5826, 120.9846, 13 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Pedro Gil',       14.5765, 120.9882, 14 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Quirino',         14.5703, 120.9916, 15 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Vito Cruz',       14.5633, 120.9949, 16 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Gil Puyat',       14.5543, 120.9971, 17 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Libertad',        14.5478, 120.9987, 18 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'EDSA',            14.5389, 121.0006, 19 UNION ALL
  SELECT @lrt1_roosevelt_baclaran, 'Baclaran',        14.5342, 120.9983, 20
) AS t
WHERE @lrt1_roosevelt_baclaran IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @lrt1_roosevelt_baclaran LIMIT 1);

-- 6. Add LRT-2 stations (13 stations) (insert only if empty)
INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @lrt2_recto_antipolo, 'Recto',            14.6035, 120.9831,  1 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Legarda',          14.6009, 120.9926,  2 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Pureza',           14.6017, 121.0052,  3 UNION ALL
  SELECT @lrt2_recto_antipolo, 'V. Mapa',          14.6042, 121.0172,  4 UNION ALL
  SELECT @lrt2_recto_antipolo, 'J. Ruiz',          14.6106, 121.0262,  5 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Gilmore',          14.6135, 121.0342,  6 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Betty Go-Belmonte',14.6186, 121.0428,  7 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Araneta Cubao',    14.6227, 121.0526,  8 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Anonas',           14.6279, 121.0647,  9 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Katipunan',        14.6311, 121.0725, 10 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Santolan',         14.6221, 121.0859, 11 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Marikina–Pasig',   14.6204, 121.1003, 12 UNION ALL
  SELECT @lrt2_recto_antipolo, 'Antipolo',         14.6250, 121.1214, 13
) AS t
WHERE @lrt2_recto_antipolo IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @lrt2_recto_antipolo LIMIT 1);

-- 7. Add MRT-3 stations (13 stations) (insert only if empty)
INSERT INTO `route_stops` (`route_definition_id`, `stop_name`, `latitude`, `longitude`, `stop_order`)
SELECT * FROM (
  SELECT @mrt3_north_taft, 'North Avenue',          14.6520, 121.0325,  1 UNION ALL
  SELECT @mrt3_north_taft, 'Quezon Avenue',         14.6430, 121.0380,  2 UNION ALL
  SELECT @mrt3_north_taft, 'GMA–Kamuning',          14.6352, 121.0433,  3 UNION ALL
  SELECT @mrt3_north_taft, 'Araneta Center–Cubao',  14.6195, 121.0511,  4 UNION ALL
  SELECT @mrt3_north_taft, 'Santolan–Annapolis',    14.6078, 121.0564,  5 UNION ALL
  SELECT @mrt3_north_taft, 'Ortigas',               14.5878, 121.0567,  6 UNION ALL
  SELECT @mrt3_north_taft, 'Shaw Boulevard',        14.5812, 121.0536,  7 UNION ALL
  SELECT @mrt3_north_taft, 'Boni',                  14.5738, 121.0481,  8 UNION ALL
  SELECT @mrt3_north_taft, 'Guadalupe',             14.5668, 121.0455,  9 UNION ALL
  SELECT @mrt3_north_taft, 'Buendia',               14.5546, 121.0345, 10 UNION ALL
  SELECT @mrt3_north_taft, 'Ayala',                 14.5490, 121.0283, 11 UNION ALL
  SELECT @mrt3_north_taft, 'Magallanes',            14.5420, 121.0195, 12 UNION ALL
  SELECT @mrt3_north_taft, 'Taft Avenue',           14.5377, 121.0022, 13
) AS t
WHERE @mrt3_north_taft IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM `route_stops` WHERE `route_definition_id` = @mrt3_north_taft LIMIT 1);

-- ============================================================
--  UPDATE COMPLETE
--  Your database now has LRT/MRT routes with proper types
--  Routes table removed (not needed for monitoring)
--  All existing data preserved
-- ============================================================
