<?php
/**
 * API: Returns all route definitions with their stops (for map display and dropdowns)
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDBConnection();
    $allowedCategories = ["tricycle", "jeepney", "rail"];
    $category = isset($_GET["category"]) ? strtolower(trim((string) $_GET["category"])) : "";
    if (!in_array($category, $allowedCategories, true)) {
        $category = "";
    }

    try {
        if ($category !== "") {
            $stmt = $pdo->prepare("
                SELECT id, name, vehicle_category, created_at
                FROM route_definitions
                WHERE vehicle_category = ?
                ORDER BY name
            ");
            $stmt->execute([$category]);
        } else {
            $stmt = $pdo->query("
                SELECT id, name, vehicle_category, created_at
                FROM route_definitions
                ORDER BY name
            ");
        }
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Backwards compatibility if vehicle_category isn't present yet.
        if ($category !== "") {
            $stmt = $pdo->prepare("
                SELECT id, name, created_at
                FROM route_definitions
                ORDER BY name
            ");
            $stmt->execute();
        } else {
            $stmt = $pdo->query("
                SELECT id, name, created_at
                FROM route_definitions
                ORDER BY name
            ");
        }
        $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($routes as &$r) {
            $r["vehicle_category"] = null;
        }
        unset($r);
    }

    $stmt = $pdo->query("
        SELECT id, route_definition_id, stop_name, latitude, longitude, stop_order
        FROM route_stops
        ORDER BY route_definition_id, stop_order
    ");
    $stops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stopsByRoute = [];
    foreach ($stops as $s) {
        $rid = $s['route_definition_id'];
        if (!isset($stopsByRoute[$rid])) $stopsByRoute[$rid] = [];
        $stopsByRoute[$rid][] = [
            'id' => (int)$s['id'],
            'stop_name' => $s['stop_name'],
            'latitude' => (float)$s['latitude'],
            'longitude' => (float)$s['longitude'],
            'stop_order' => (int)$s['stop_order']
        ];
    }

    foreach ($routes as &$r) {
        $r['id'] = (int)$r['id'];
        $r['vehicle_category'] = $r['vehicle_category'] ?? null;
        $r['stops'] = $stopsByRoute[$r['id']] ?? [];
        usort($r['stops'], function ($a, $b) { return $a['stop_order'] - $b['stop_order']; });
    }

    echo json_encode(['routes' => $routes]);
} catch (PDOException $e) {
    error_log('api_routes_with_stops: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error', 'routes' => []]);
}
