<?php
require_once __DIR__ . "/../db.php";

/**
 * Export specific routes + stops from the current DB
 * into seedable SQL-friendly output.
 *
 * Usage:
 *   php tools/export_triumph_routes.php
 */

$routeNames = [
    "Triumph - Arca South",
    "Triumph - C5 Waterfun",
    "Triumph - FTI Terminal",
    "Triumph - Hagonoy",
    "Triumph - Tenement",
];

$pdo = getDBConnection();

foreach ($routeNames as $name) {
    $stmt = $pdo->prepare("SELECT id, name FROM route_definitions WHERE name = ? LIMIT 1");
    $stmt->execute([$name]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        fwrite(STDERR, "Missing route in DB: {$name}\n");
        continue;
    }

    echo "\n-- {$name}\n";
    echo "SELECT @rid := `id` FROM `route_definitions` WHERE `name` = " . $pdo->quote($name) . " LIMIT 1;\n";

    $stopsStmt = $pdo->prepare(
        "SELECT stop_name, latitude, longitude, stop_order
         FROM route_stops
         WHERE route_definition_id = ?
         ORDER BY stop_order ASC",
    );
    $stopsStmt->execute([(int) $route["id"]]);
    $stops = $stopsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$stops) {
        fwrite(STDERR, "No stops found for route: {$name}\n");
        continue;
    }

    foreach ($stops as $s) {
        echo (int) $s["stop_order"] . "\t" . $s["stop_name"] . "\t" . $s["latitude"] . "\t" . $s["longitude"] . "\n";
    }
}

