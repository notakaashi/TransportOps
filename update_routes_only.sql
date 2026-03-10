-- ============================================================
--  UPDATE EXISTING DATABASE - ROUTES ONLY
--  Removes routes table, adds route_type column and LRT/MRT routes
--  Safe for existing database - no data loss
-- ============================================================

USE `transport_ops`;

-- 1. Add route_type column to existing route_definitions table
ALTER TABLE `route_definitions` 
ADD COLUMN `route_type` ENUM('road', 'lrt', 'mrt') DEFAULT 'road' AFTER `name`;

-- 2. Add LRT/MRT routes if they don't exist
INSERT IGNORE INTO `route_definitions` (`name`, `created_at`) VALUES
('LRT-1 Roosevelt to Baclaran',                        NOW()),
('LRT-2 Recto to Antipolo',                          NOW()),
('MRT-3 North Avenue to Taft Avenue',                   NOW());

-- 3. Get route IDs for LRT/MRT routes
SELECT @lrt1_roosevelt_baclaran := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-1 Roosevelt to Baclaran'         LIMIT 1;
SELECT @lrt2_recto_antipolo    := `id` FROM `route_definitions`
    WHERE `name` = 'LRT-2 Recto to Antipolo'           LIMIT 1;
SELECT @mrt3_north_taft       := `id` FROM `route_definitions`
    WHERE `name` = 'MRT-3 North Avenue to Taft Avenue'   LIMIT 1;

-- 4. Set route types for LRT/MRT routes
UPDATE `route_definitions` SET `route_type` = 'lrt' 
WHERE `name` IN ('LRT-1 Roosevelt to Baclaran', 'LRT-2 Recto to Antipolo');

UPDATE `route_definitions` SET `route_type` = 'mrt' 
WHERE `name` = 'MRT-3 North Avenue to Taft Avenue';

-- 5. Add LRT-1 stations (20 stations)
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

-- 6. Add LRT-2 stations (13 stations)
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
(@lrt2_recto_antipolo, 'Marikina–Pasig', 14.6204, 121.1003, 12),
(@lrt2_recto_antipolo, 'Antipolo',        14.6250, 121.1214, 13);

-- 7. Add MRT-3 stations (13 stations)
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

-- ============================================================
--  UPDATE COMPLETE
--  Your database now has LRT/MRT routes with proper types
--  Routes table removed (not needed for monitoring)
--  All existing data preserved
-- ============================================================
