<?php
/**
 * Test Route Independence - Verify routes are created independently
 */
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

// Test route independence
try {
    $pdo = getDBConnection();
    
    // Get all routes with their stops
    $stmt = $pdo->query("
        SELECT rd.id as route_id, rd.name as route_name, rd.created_at,
               rs.id as stop_id, rs.stop_name, rs.latitude, rs.longitude, rs.stop_order
        FROM route_definitions rd
        LEFT JOIN route_stops rs ON rd.id = rs.route_definition_id
        ORDER BY rd.name, rs.stop_order
    ");
    
    $routes_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by route
    $routes = [];
    foreach ($routes_data as $row) {
        $route_id = $row['route_id'];
        if (!isset($routes[$route_id])) {
            $routes[$route_id] = [
                'id' => $row['route_id'],
                'name' => $row['route_name'],
                'created_at' => $row['created_at'],
                'stops' => []
            ];
        }
        
        if ($row['stop_id']) {
            $routes[$route_id]['stops'][] = [
                'id' => $row['stop_id'],
                'stop_name' => $row['stop_name'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'stop_order' => $row['stop_order']
            ];
        }
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
    $routes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Independence Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Route Independence Test</h1>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <?php foreach ($routes as $route): ?>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($route['name']); ?>
                    </h3>
                    <p class="text-sm text-gray-500 mb-4">
                        Route ID: <?php echo $route['id']; ?> • 
                        <?php echo count($route['stops']); ?> stops
                    </p>
                    
                    <?php if (!empty($route['stops'])): ?>
                        <div class="mb-4">
                            <h4 class="font-medium text-gray-700 mb-2">Stops:</h4>
                            <ol class="space-y-1">
                                <?php foreach ($route['stops'] as $stop): ?>
                                    <li class="text-sm text-gray-600">
                                        <?php echo ($stop['stop_order'] + 1); ?>. 
                                        <?php echo htmlspecialchars($stop['stop_name']); ?>
                                        <span class="text-xs text-gray-400">
                                            (<?php echo $stop['latitude']; ?>, <?php echo $stop['longitude']; ?>)
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                        
                        <div class="h-64 rounded-lg overflow-hidden border border-gray-200" id="map-<?php echo $route['id']; ?>"></div>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <p>No stops defined</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-semibold text-gray-800 mb-4">Combined View</h3>
            <div class="h-96 rounded-lg overflow-hidden border border-gray-200" id="combined-map"></div>
        </div>
        
        <div class="mt-8 text-center">
            <a href="manage_routes.php" class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 font-medium">
                ← Back to Manage Routes
            </a>
        </div>
    </div>

    <script>
        const routesData = <?php echo json_encode(array_values($routes)); ?>;
        const routeColors = ['#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6'];
        
        // Initialize individual route maps
        routesData.forEach(function(route, index) {
            if (!route.stops || route.stops.length === 0) return;
            
            const mapId = 'map-' + route.id;
            const mapElement = document.getElementById(mapId);
            if (!mapElement) return;
            
            const waypoints = route.stops.map(function(s) { 
                return [parseFloat(s.latitude), parseFloat(s.longitude)]; 
            });
            
            const map = L.map(mapId).setView(waypoints[0], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
                attribution: '© OpenStreetMap' 
            }).addTo(map);
            
            const color = routeColors[index % routeColors.length];
            
            if (typeof getRouteGeometry === 'function') {
                getRouteGeometry(waypoints, function(roadLatlngs) {
                    const latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                    L.polyline(latlngs, { color: color, weight: 4, opacity: 0.8 }).addTo(map);
                    
                    route.stops.forEach(function(stop, i) {
                        L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)])
                            .bindPopup('<strong>' + (i + 1) + '. ' + stop.stop_name + '</strong>')
                            .addTo(map);
                    });
                    
                    map.fitBounds(latlngs, { padding: [20, 20] });
                });
            } else {
                L.polyline(waypoints, { color: color, weight: 4, opacity: 0.8 }).addTo(map);
                route.stops.forEach(function(stop, i) {
                    L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)])
                        .bindPopup('<strong>' + (i + 1) + '. ' + stop.stop_name + '</strong>')
                        .addTo(map);
                });
                map.fitBounds(waypoints, { padding: [20, 20] });
            }
        });
        
        // Initialize combined map
        const combinedMap = L.map('combined-map').setView([14.5995, 120.9842], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
            attribution: '© OpenStreetMap' 
        }).addTo(combinedMap);
        
        routesData.forEach(function(route, index) {
            if (!route.stops || route.stops.length === 0) return;
            
            const waypoints = route.stops.map(function(s) { 
                return [parseFloat(s.latitude), parseFloat(s.longitude)]; 
            });
            
            const color = routeColors[index % routeColors.length];
            
            if (typeof getRouteGeometry === 'function') {
                getRouteGeometry(waypoints, function(roadLatlngs) {
                    const latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                    L.polyline(latlngs, { color: color, weight: 3, opacity: 0.7 }).addTo(combinedMap);
                    
                    route.stops.forEach(function(stop, i) {
                        L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)])
                            .bindPopup('<strong>' + route.name + '</strong><br>' + 
                                      (i + 1) + '. ' + stop.stop_name)
                            .addTo(combinedMap);
                    });
                });
            } else {
                L.polyline(waypoints, { color: color, weight: 3, opacity: 0.7 }).addTo(combinedMap);
                route.stops.forEach(function(stop, i) {
                    L.marker([parseFloat(stop.latitude), parseFloat(stop.longitude)])
                        .bindPopup('<strong>' + route.name + '</strong><br>' + 
                                  (i + 1) + '. ' + stop.stop_name)
                        .addTo(combinedMap);
                });
            }
        });
        
        // Fit combined map to show all routes
        const allBounds = [];
        routesData.forEach(function(route) {
            if (route.stops && route.stops.length > 0) {
                route.stops.forEach(function(stop) {
                    allBounds.push([parseFloat(stop.latitude), parseFloat(stop.longitude)]);
                });
            }
        });
        
        if (allBounds.length > 0) {
            combinedMap.fitBounds(allBounds, { padding: [40, 40] });
        }
    </script>
</body>
</html>
