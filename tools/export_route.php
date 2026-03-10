<?php
require_once __DIR__ . "/../db.php";

/**
 * Export a single route + its ordered stops from the current DB.
 *
 * Usage:
 *   php tools/export_route.php "Route Name"
 */

$routeName = $argv[1] ?? "";
if (!$routeName) {
    fwrite(STDERR, "Usage: php tools/export_route.php \"Route Name\"\n");
    exit(2);
}

$pdo = getDBConnection();

$stmt = $pdo->prepare("SELECT id, name FROM route_definitions WHERE name = ? LIMIT 1");
$stmt->execute([$routeName]);
$route = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$route) {
    fwrite(STDERR, "Missing route in DB: {$routeName}\n");
    exit(1);
}

echo "\n-- {$routeName}\n";
echo "SELECT @rid := `id` FROM `route_definitions` WHERE `name` = " . $pdo->quote($routeName) . " LIMIT 1;\n";

$stopsStmt = $pdo->prepare(
    "SELECT stop_name, latitude, longitude, stop_order
     FROM route_stops
     WHERE route_definition_id = ?
     ORDER BY stop_order ASC",
);
$stopsStmt->execute([(int) $route["id"]]);
$stops = $stopsStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$stops) {
    fwrite(STDERR, "No stops found for route: {$routeName}\n");
    exit(3);
}

foreach ($stops as $s) {
    echo (int) $s["stop_order"] . "\t" . $s["stop_name"] . "\t" . $s["latitude"] . "\t" . $s["longitude"] . "\n";
}

