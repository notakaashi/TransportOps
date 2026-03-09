-- ============================================================
--  Predefined Manila Routes
--  5 Common Transport Routes within Manila
-- ============================================================

USE `transport_ops`;

-- Insert 5 predefined Manila routes
INSERT INTO route_definitions (name, created_at) VALUES
('Baclaran - Monumento via Taft Avenue', NOW()),
('Quiapo - Cubao via Aurora Boulevard', NOW()),
('Manila City Hall - SM Megamall via EDSA', NOW()),
('Binondo - Makati CBD via Taft Avenue', NOW()),
('Esplanade - University of the Philippines Diliman', NOW());

-- Get the route IDs for inserting stops
SELECT @baclaran_monumento := id FROM route_definitions WHERE name = 'Baclaran - Monumento via Taft Avenue';
SELECT @quiapo_cubao := id FROM route_definitions WHERE name = 'Quiapo - Cubao via Aurora Boulevard';
SELECT @cityhall_megamall := id FROM route_definitions WHERE name = 'Manila City Hall - SM Megamall via EDSA';
SELECT @binondo_makati := id FROM route_definitions WHERE name = 'Binondo - Makati CBD via Taft Avenue';
SELECT @esplanade_up := id FROM route_definitions WHERE name = 'Esplanade - University of the Philippines Diliman';

-- Route 1: Baclaran - Monumento via Taft Avenue
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(@baclaran_monumento, 'Baclaran Terminal', 14.5378, 120.9836, 1),
(@baclaran_monumento, 'LRT Baclaran Station', 14.5378, 120.9836, 2),
(@baclaran_monumento, 'Redemptorist Church', 14.5398, 120.9836, 3),
(@baclaran_monumento, 'EDSA Station', 14.5418, 120.9836, 4),
(@baclaran_monumento, 'Libertad Station', 14.5438, 120.9836, 5),
(@baclaran_monumento, 'Gil Puyat Station', 14.5458, 120.9836, 6),
(@baclaran_monumento, 'Vito Cruz Station', 14.5478, 120.9836, 7),
(@baclaran_monumento, 'Quirino Station', 14.5498, 120.9836, 8),
(@baclaran_monumento, 'Pedro Gil Station', 14.5518, 120.9836, 9),
(@baclaran_monumento, 'United Nations Station', 14.5538, 120.9836, 10),
(@baclaran_monumento, 'Central Terminal', 14.5558, 120.9836, 11),
(@baclaran_monumento, 'Carriedo Station', 14.5578, 120.9836, 12),
(@baclaran_monumento, 'Doroteo Jose Station', 14.5598, 120.9836, 13),
(@baclaran_monumento, 'Bambang Station', 14.5618, 120.9836, 14),
(@baclaran_monumento, 'Tayuman Station', 14.5638, 120.9836, 15),
(@baclaran_monumento, 'Blumentritt Station', 14.5658, 120.9836, 16),
(@baclaran_monumento, 'Abad Santos Station', 14.5678, 120.9836, 17),
(@baclaran_monumento, 'R. Papa Station', 14.5698, 120.9836, 18),
(@baclaran_monumento, '5th Avenue Station', 14.5718, 120.9836, 19),
(@baclaran_monumento, 'Monumento Terminal', 14.5738, 120.9836, 20);

-- Route 2: Quiapo - Cubao via Aurora Boulevard
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(@quiapo_cubao, 'Quiapo Church', 14.5995, 120.9842, 1),
(@quiapo_cubao, 'Carriedo Street', 14.5995, 120.9842, 2),
(@quiapo_cubao, 'Rizal Park', 14.5995, 120.9842, 3),
(@quiapo_cubao, 'Lawton Plaza', 14.5995, 120.9842, 4),
(@quiapo_cubao, 'Sta. Cruz Church', 14.5995, 120.9842, 5),
(@quiapo_cubao, 'Avenida Rizal', 14.5995, 120.9842, 6),
(@quiapo_cubao, 'Recto Avenue', 14.5995, 120.9842, 7),
(@quiapo_cubao, 'Gilmore Street', 14.5995, 120.9842, 8),
(@quiapo_cubao, 'New Manila', 14.5995, 120.9842, 9),
(@quiapo_cubao, 'Aurora Boulevard', 14.5995, 120.9842, 10),
(@quiapo_cubao, 'Cubao Araneta Center', 14.5995, 120.9842, 11),
(@quiapo_cubao, 'Ali Mall', 14.5995, 120.9842, 12),
(@quiapo_cubao, 'Farmers Plaza', 14.5995, 120.9842, 13),
(@quiapo_cubao, 'Cubao Terminal', 14.5995, 120.9842, 14);

-- Route 3: Manila City Hall - SM Megamall via EDSA
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(@cityhall_megamall, 'Manila City Hall', 14.5833, 120.9833, 1),
(@cityhall_megamall, 'National Museum', 14.5833, 120.9833, 2),
(@cityhall_megamall, 'Rizal Monument', 14.5833, 120.9833, 3),
(@cityhall_megamall, 'Kalaw Avenue', 14.5833, 120.9833, 4),
(@cityhall_megamall, 'United Nations Avenue', 14.5833, 120.9833, 5),
(@cityhall_megamall, 'Taft Avenue', 14.5833, 120.9833, 6),
(@cityhall_megamall, 'EDSA', 14.5833, 120.9833, 7),
(@cityhall_megamall, 'Buendia Avenue', 14.5833, 120.9833, 8),
(@cityhall_megamall, 'Guadalupe Bridge', 14.5833, 120.9833, 9),
(@cityhall_megamall, 'Guadalupe Station', 14.5833, 120.9833, 10),
(@cityhall_megamall, 'Pioneer Street', 14.5833, 120.9833, 11),
(@cityhall_megamall, 'Bonny Serrano Avenue', 14.5833, 120.9833, 12),
(@cityhall_megamall, 'Shaw Boulevard', 14.5833, 120.9833, 13),
(@cityhall_megamall, 'SM Megamall', 14.5833, 120.9833, 14);

-- Route 4: Binondo - Makati CBD via Taft Avenue
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(@binondo_makati, 'Binondo Church', 14.6000, 120.9700, 1),
(@binondo_makati, 'Ongpin Street', 14.6000, 120.9700, 2),
(@binondo_makati, 'Escolta Street', 14.6000, 120.9700, 3),
(@binondo_makati, 'Jones Bridge', 14.6000, 120.9700, 4),
(@binondo_makati, 'Lawton Plaza', 14.6000, 120.9700, 5),
(@binondo_makati, 'Taft Avenue', 14.6000, 120.9700, 6),
(@binondo_makati, 'United Nations Avenue', 14.6000, 120.9700, 7),
(@binondo_makati, 'Pedro Gil Street', 14.6000, 120.9700, 8),
(@binondo_makati, 'Vito Cruz Street', 14.6000, 120.9700, 9),
(@binondo_makati, 'Gil Puyat Street', 14.6000, 120.9700, 10),
(@binondo_makati, 'Chino Roces Avenue', 14.6000, 120.9700, 11),
(@binondo_makati, 'Ayala Avenue', 14.6000, 120.9700, 12),
(@binondo_makati, 'Makati CBD', 14.6000, 120.9700, 13),
(@binondo_makati, 'Ayala Triangle Gardens', 14.6000, 120.9700, 14);

-- Route 5: Esplanade - University of the Philippines Diliman
INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES
(@esplanade_up, 'Manila Bay Esplanade', 14.5547, 120.9822, 1),
(@esplanade_up, 'Roxas Boulevard', 14.5547, 120.9822, 2),
(@esplanade_up, 'U.N. Avenue', 14.5547, 120.9822, 3),
(@esplanade_up, 'Taft Avenue', 14.5547, 120.9822, 4),
(@esplanade_up, 'España Boulevard', 14.5547, 120.9822, 5),
(@esplanade_up, 'Quezon Boulevard', 14.5547, 120.9822, 6),
(@esplanade_up, 'Welcome Rotonda', 14.5547, 120.9822, 7),
(@esplanade_up, 'Quezon Avenue', 14.5547, 120.9822, 8),
(@esplanade_up, 'Mabuhay Rotonda', 14.5547, 120.9822, 9),
(@esplanade_up, 'Philcoa', 14.5547, 120.9822, 10),
(@esplanade_up, 'Commonwealth Avenue', 14.5547, 120.9822, 11),
(@esplanade_up, 'UP Diliman Gate', 14.5547, 120.9822, 12),
(@esplanade_up, 'UP Palma Hall', 14.5547, 120.9822, 13),
(@esplanade_up, 'UP Diliman Campus', 14.5547, 120.9822, 14);

-- Add some PUV units for these routes
INSERT INTO puv_units (plate_number, vehicle_type, current_route, crowd_status, created_at) VALUES
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
