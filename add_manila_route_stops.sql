-- ============================================================
--  Add Stops to Existing Manila Routes
--  For routes that already exist but don't have stops
-- ============================================================

USE `transport_ops`;

-- Route 5: Baclaran - Monumento via Taft Avenue (ID = 5)
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(5, 'Baclaran Terminal', 14.5378, 120.9836, 1),
(5, 'LRT Baclaran Station', 14.5398, 120.9836, 2),
(5, 'Redemptorist Church', 14.5418, 120.9836, 3),
(5, 'EDSA Station', 14.5438, 120.9836, 4),
(5, 'Libertad Station', 14.5458, 120.9836, 5),
(5, 'Gil Puyat Station', 14.5478, 120.9836, 6),
(5, 'Vito Cruz Station', 14.5498, 120.9836, 7),
(5, 'Quirino Station', 14.5518, 120.9836, 8),
(5, 'Pedro Gil Station', 14.5538, 120.9836, 9),
(5, 'United Nations Station', 14.5558, 120.9836, 10),
(5, 'Central Terminal', 14.5578, 120.9836, 11),
(5, 'Carriedo Station', 14.5598, 120.9836, 12),
(5, 'Doroteo Jose Station', 14.5618, 120.9836, 13),
(5, 'Bambang Station', 14.5638, 120.9836, 14),
(5, 'Tayuman Station', 14.5658, 120.9836, 15),
(5, 'Blumentritt Station', 14.5678, 120.9836, 16),
(5, 'Abad Santos Station', 14.5698, 120.9836, 17),
(5, 'R. Papa Station', 14.5718, 120.9836, 18),
(5, '5th Avenue Station', 14.5738, 120.9836, 19),
(5, 'Monumento Terminal', 14.5758, 120.9836, 20);

-- Route 6: Quiapo - Cubao via Aurora Boulevard (ID = 6)
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(6, 'Quiapo Church', 14.5995, 120.9842, 1),
(6, 'Carriedo Street', 14.5975, 120.9842, 2),
(6, 'Rizal Park', 14.5955, 120.9842, 3),
(6, 'Lawton Plaza', 14.5935, 120.9842, 4),
(6, 'Sta. Cruz Church', 14.5915, 120.9842, 5),
(6, 'Avenida Rizal', 14.5895, 120.9842, 6),
(6, 'Recto Avenue', 14.5875, 120.9842, 7),
(6, 'Gilmore Street', 14.5855, 120.9842, 8),
(6, 'New Manila', 14.5835, 120.9842, 9),
(6, 'Aurora Boulevard', 14.5815, 120.9842, 10),
(6, 'Cubao Araneta Center', 14.5795, 120.9842, 11),
(6, 'Ali Mall', 14.5775, 120.9842, 12),
(6, 'Farmers Plaza', 14.5755, 120.9842, 13),
(6, 'Cubao Terminal', 14.5735, 120.9842, 14);

-- Route 7: Manila City Hall - SM Megamall via EDSA (ID = 7)
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(7, 'Manila City Hall', 14.5833, 120.9833, 1),
(7, 'National Museum', 14.5853, 120.9833, 2),
(7, 'Rizal Monument', 14.5873, 120.9833, 3),
(7, 'Kalaw Avenue', 14.5893, 120.9833, 4),
(7, 'United Nations Avenue', 14.5913, 120.9833, 5),
(7, 'Taft Avenue', 14.5933, 120.9833, 6),
(7, 'EDSA', 14.5953, 120.9833, 7),
(7, 'Buendia Avenue', 14.5973, 120.9833, 8),
(7, 'Guadalupe Bridge', 14.5993, 120.9833, 9),
(7, 'Guadalupe Station', 14.6013, 120.9833, 10),
(7, 'Pioneer Street', 14.6033, 120.9833, 11),
(7, 'Bonny Serrano Avenue', 14.6053, 120.9833, 12),
(7, 'Shaw Boulevard', 14.6073, 120.9833, 13),
(7, 'SM Megamall', 14.6093, 120.9833, 14);

-- Route 8: Binondo - Makati CBD via Taft Avenue (ID = 8)
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(8, 'Binondo Church', 14.6000, 120.9700, 1),
(8, 'Ongpin Street', 14.5980, 120.9720, 2),
(8, 'Escolta Street', 14.5960, 120.9740, 3),
(8, 'Jones Bridge', 14.5940, 120.9760, 4),
(8, 'Lawton Plaza', 14.5920, 120.9780, 5),
(8, 'Taft Avenue', 14.5900, 120.9800, 6),
(8, 'United Nations Avenue', 14.5880, 120.9820, 7),
(8, 'Pedro Gil Street', 14.5860, 120.9840, 8),
(8, 'Vito Cruz Street', 14.5840, 120.9860, 9),
(8, 'Gil Puyat Street', 14.5820, 120.9880, 10),
(8, 'Chino Roces Avenue', 14.5800, 120.9900, 11),
(8, 'Ayala Avenue', 14.5780, 120.9920, 12),
(8, 'Makati CBD', 14.5760, 120.9940, 13),
(8, 'Ayala Triangle Gardens', 14.5740, 120.9960, 14);

-- Route 9: Esplanade - University of the Philippines Diliman (ID = 9)
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(9, 'Manila Bay Esplanade', 14.5547, 120.9822, 1),
(9, 'Roxas Boulevard', 14.5567, 120.9822, 2),
(9, 'U.N. Avenue', 14.5587, 120.9822, 3),
(9, 'Taft Avenue', 14.5607, 120.9822, 4),
(9, 'España Boulevard', 14.5627, 120.9822, 5),
(9, 'Quezon Boulevard', 14.5647, 120.9822, 6),
(9, 'Welcome Rotonda', 14.5667, 120.9822, 7),
(9, 'Quezon Avenue', 14.5687, 120.9822, 8),
(9, 'Mabuhay Rotonda', 14.5707, 120.9822, 9),
(9, 'Philcoa', 14.5727, 120.9822, 10),
(9, 'Commonwealth Avenue', 14.5747, 120.9822, 11),
(9, 'UP Diliman Gate', 14.5767, 120.9822, 12),
(9, 'UP Palma Hall', 14.5787, 120.9822, 13),
(9, 'UP Diliman Campus', 14.5807, 120.9822, 14);

-- Add PUV units for these routes (if they don't exist)
INSERT IGNORE INTO puv_units (plate_number, vehicle_type, current_route, crowd_status, created_at) VALUES
('ABC-123', 'Jeepney', 'Baclaran - Monumento via Taft Avenue', 'Light', NOW()),
('DEF-456', 'Jeepney', 'Baclaran - Monumento via Taft Avenue', 'Moderate', NOW()),
('GHI-789', 'Jeepney', 'Quiapo - Cubao via Aurora Boulevard', 'Light', NOW()),
('JKL-012', 'Jeepney', 'Quiapo - Cubao via Aurora Boulevard', 'Heavy', NOW()),
('MNO-345', 'Bus', 'Manila City Hall - SM Megamall via EDSA', 'Moderate', NOW()),
('PQR-678', 'Bus', 'Manila City Hall - SM Megamall via EDSA', 'Light', NOW()),
('STU-901', 'Jeepney', 'Binondo - Makati CBD via Taft Avenue', 'Moderate', NOW()),
('VWX-234', 'Jeepney', 'Binondo - Makati CBD via Taft Avenue', 'Light', NOW()),
('YZA-567', 'Bus', 'Esplanade - University of the Philippines Diliman', 'Heavy', NOW()),
('BCD-890', 'Bus', 'Esplanade - University of the Philippines Diliman', 'Moderate', NOW());
