-- Migration: Reports by route only (no vehicle tracking)
-- Run this after database.sql and database_routes_stops.sql
-- Adds route_definition_id to reports so reports are tied to routes, not vehicles.

USE `transport_ops`;

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
