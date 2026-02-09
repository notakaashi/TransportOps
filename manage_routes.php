<?php
/**
 * Manage Routes - Define routes with stops for map display
 * Admin can add a route (e.g. Guadalupe - FTI Tenement) and add stops in order; stops are connected on the map.
 */
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$routes_with_stops = [];

try {
    $pdo = getDBConnection();
    $pdo->query("SELECT 1 FROM route_definitions LIMIT 1");
} catch (PDOException $e) {
    $error = 'Route tables not found. Please run database_routes_stops.sql first.';
}

if (!$error) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_route') {
            $name = trim($_POST['route_name'] ?? '');
            if (empty($name)) {
                $error = 'Route name is required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO route_definitions (name) VALUES (?)");
                    $stmt->execute([$name]);
                    $success = 'Route "' . htmlspecialchars($name) . '" created. Add stops below.';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $error = 'A route with this name already exists.';
                    } else {
                        $error = 'Failed to create route.';
                    }
                }
            }
        } elseif ($action === 'add_stop') {
            $route_id = (int)($_POST['route_id'] ?? 0);
            $stop_name = trim($_POST['stop_name'] ?? '');
            $lat = $_POST['latitude'] ?? '';
            $lng = $_POST['longitude'] ?? '';
            $stop_order = (int)($_POST['stop_order'] ?? 0);
            if (!$route_id || $stop_name === '' || $lat === '' || $lng === '') {
                $error = 'Route, stop name, latitude and longitude are required.';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO route_stops (route_definition_id, stop_name, latitude, longitude, stop_order) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$route_id, $stop_name, $lat, $lng, $stop_order]);
                    $success = 'Stop "' . htmlspecialchars($stop_name) . '" added.';
                } catch (PDOException $e) {
                    $error = 'Failed to add stop.';
                }
            }
        } elseif ($action === 'delete_stop') {
            $stop_id = (int)($_POST['stop_id'] ?? 0);
            if ($stop_id) {
                $stmt = $pdo->prepare("DELETE FROM route_stops WHERE id = ?");
                $stmt->execute([$stop_id]);
                $success = 'Stop removed.';
            }
        } elseif ($action === 'delete_route') {
            $route_id = (int)($_POST['route_id'] ?? 0);
            if ($route_id) {
                $stmt = $pdo->prepare("DELETE FROM route_stops WHERE route_definition_id = ?");
                $stmt->execute([$route_id]);
                $stmt = $pdo->prepare("DELETE FROM route_definitions WHERE id = ?");
                $stmt->execute([$route_id]);
                $success = 'Route deleted.';
            }
        } elseif ($action === 'reorder_stops') {
            $route_id = (int)($_POST['route_id'] ?? 0);
            $order = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : [];
            if ($route_id && !empty($order)) {
                try {
                    $stmt = $pdo->prepare("UPDATE route_stops SET stop_order = ? WHERE id = ? AND route_definition_id = ?");
                    foreach ($order as $idx => $stop_id) {
                        $stmt->execute([(int)$idx, (int)$stop_id, $route_id]);
                    }
                    $success = 'Route saved. Stops updated.';
                } catch (PDOException $e) {
                    $error = 'Failed to reorder stops.';
                }
            }
        } elseif ($action === 'save_route') {
            $route_id = (int)($_POST['route_id'] ?? 0);
            $order = isset($_POST['order']) && is_array($_POST['order']) ? $_POST['order'] : [];
            if ($route_id) {
                try {
                    if (!empty($order)) {
                        $stmt = $pdo->prepare("UPDATE route_stops SET stop_order = ? WHERE id = ? AND route_definition_id = ?");
                        foreach ($order as $idx => $stop_id) {
                            $stmt->execute([(int)$idx, (int)$stop_id, $route_id]);
                        }
                    }
                    $success = 'Route saved. It is now in your list and visible to commuters.';
                } catch (PDOException $e) {
                    $error = 'Failed to save route.';
                }
            }
        } elseif ($action === 'edit_route') {
            $route_id = (int)($_POST['route_id'] ?? 0);
            $new_name = trim($_POST['route_name'] ?? '');
            if ($route_id && $new_name !== '') {
                try {
                    $stmt = $pdo->prepare("UPDATE route_definitions SET name = ? WHERE id = ?");
                    $stmt->execute([$new_name, $route_id]);
                    $success = 'Route name updated.';
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        $error = 'A route with this name already exists.';
                    } else {
                        $error = 'Failed to update route name.';
                    }
                }
            } else {
                $error = 'Route and name are required.';
            }
        }
    }

    $stmt = $pdo->query("SELECT id, name, created_at FROM route_definitions ORDER BY name");
    $routes_with_stops = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($routes_with_stops as &$r) {
        $stmt = $pdo->prepare("SELECT id, stop_name, latitude, longitude, stop_order FROM route_stops WHERE route_definition_id = ? ORDER BY stop_order");
        $stmt->execute([$r['id']]);
        $r['stops'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Routes - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="js/osrm-helpers.js"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <aside class="w-full md:w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="px-4 py-4 sm:p-6 flex-shrink-0 border-b border-gray-700 md:border-b-0">
                <div id="adminNavToggle" class="flex items-center justify-between md:justify-start mb-4 md:mb-8 cursor-pointer md:cursor-default">
                    <div class="bg-blue-600 p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold">Transport Ops</h1>
                    <svg class="w-5 h-5 text-gray-300 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <nav id="adminNavLinks" class="space-y-1 md:space-y-2 text-sm sm:text-base hidden md:block">
                    <a href="admin_dashboard.php" class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Fleet Overview
                    </a>
                    <a href="admin_reports.php" class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"></path></svg>
                        Reports
                    </a>
                    <a href="route_status.php" class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        Route Status
                    </a>
                    <a href="manage_routes.php" class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path></svg>
                        Manage Routes
                    </a>
                    <a href="heatmap.php" class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Crowdsourcing Heatmap
                    </a>
                    <a href="user_management.php" class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        User Management
                    </a>
                </nav>
            </div>
            <div id="adminNavFooter" class="mt-auto p-4 sm:p-6 border-t border-gray-700 hidden md:block">
                <div class="bg-gray-700 rounded-lg p-3 sm:p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-xs text-blue-400 mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
                <a href="logout.php" class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">Logout</a>
            </div>
        </aside>

        <main class="flex-1 w-full overflow-y-auto">
            <div class="p-4 sm:p-6 lg:p-8">
                <h2 class="text-3xl font-bold text-gray-800">Manage Routes</h2>
                <p class="text-gray-600 mt-2">Define routes with stops (e.g. Guadalupe → FTI Tenement). Pin the start point, end point, and any waypoints by clicking on the map — no need to enter coordinates. Stops are connected on the map and used in Reports.</p>

                <?php if ($error): ?>
                    <div class="mt-4 bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="mt-4 bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!$error): ?>
                <!-- Create new route -->
                <div class="mt-6 bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Create new route</h3>
                    <form method="POST" class="flex flex-wrap items-end gap-4">
                        <input type="hidden" name="action" value="create_route">
                        <div class="flex-1 min-w-[200px]">
                            <label for="route_name" class="block text-sm font-medium text-gray-700 mb-1">Route name (e.g. Guadalupe - FTI Tenement)</label>
                            <input type="text" id="route_name" name="route_name" required placeholder="Origin - Destination"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700 font-medium">Create route</button>
                    </form>
                </div>

                <!-- List routes and add stops -->
                <?php $highlight_route_id = isset($_GET['highlight']) ? (int)$_GET['highlight'] : 0; ?>
                <div class="mt-8 space-y-6">
                    <?php foreach ($routes_with_stops as $route): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden<?php echo $highlight_route_id === (int)$route['id'] ? ' ring-2 ring-blue-500' : ''; ?>" id="route-<?php echo (int)$route['id']; ?>">
                            <div class="px-6 py-4 bg-gray-50 border-b flex flex-wrap justify-between items-center gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($route['name']); ?></h3>
                                    <form method="POST" class="inline flex items-center gap-1" id="edit-name-form-<?php echo (int)$route['id']; ?>">
                                        <input type="hidden" name="action" value="edit_route">
                                        <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                        <input type="text" name="route_name" value="<?php echo htmlspecialchars($route['name']); ?>" placeholder="Route name" class="px-2 py-1 border border-gray-300 rounded text-sm w-48">
                                        <button type="submit" class="text-sm text-blue-600 hover:text-blue-800 font-medium">Update name</button>
                                    </form>
                                </div>
                                <div class="flex items-center gap-2">
                                    <form method="POST" id="save-route-form-<?php echo (int)$route['id']; ?>" class="inline">
                                        <input type="hidden" name="action" value="save_route">
                                        <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700 text-sm font-medium">Save route</button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this route and all its stops?');" class="inline">
                                        <input type="hidden" name="action" value="delete_route">
                                        <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Delete route</button>
                                    </form>
                                </div>
                            </div>
                            <div class="p-6 flex flex-col lg:flex-row gap-6">
                                <div class="lg:w-1/2 space-y-4">
                                    <p class="text-sm text-gray-600">Drag stops to reorder. Route line follows this order.</p>
                                    <ul id="stops-list-<?php echo (int)$route['id']; ?>" class="space-y-1 text-sm text-gray-700" data-route-id="<?php echo (int)$route['id']; ?>">
                                        <?php foreach ($route['stops'] as $i => $s): ?>
                                            <li draggable="true" data-stop-id="<?php echo (int)$s['id']; ?>" class="flex items-center gap-2 p-2 rounded border border-gray-200 bg-gray-50 hover:bg-gray-100 cursor-move select-none">
                                                <span class="text-gray-400 cursor-move" title="Drag to reorder">⋮⋮</span>
                                                <span class="flex-1"><?php echo htmlspecialchars($s['stop_name']); ?></span>
                                                <form method="POST" onsubmit="return confirm('Remove this stop?');" class="inline">
                                                    <input type="hidden" name="action" value="delete_stop">
                                                    <input type="hidden" name="stop_id" value="<?php echo (int)$s['id']; ?>">
                                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs">Remove</button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if (empty($route['stops'])): ?>
                                        <p class="text-xs text-gray-500">No stops yet. Add one below.</p>
                                    <?php endif; ?>
                                    <form method="POST" id="add-stop-form-<?php echo (int)$route['id']; ?>" class="border-t pt-4 space-y-3">
                                        <input type="hidden" name="action" value="add_stop">
                                        <input type="hidden" name="route_id" value="<?php echo (int)$route['id']; ?>">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Add stop</label>
                                            <input type="text" name="stop_name" required placeholder="Stop name (e.g. Start, Terminal)" class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        </div>
                                        <div>
                                            <label class="block text-sm text-gray-700 mb-1">Set location</label>
                                            <div class="flex gap-2 mb-1">
                                                <input type="text" id="place-search-<?php echo (int)$route['id']; ?>" placeholder="Search place (e.g. Guadalupe Manila)" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm">
                                                <button type="button" id="place-search-btn-<?php echo (int)$route['id']; ?>" class="bg-gray-600 text-white px-3 py-2 rounded-md text-sm font-medium hover:bg-gray-700">Search</button>
                                            </div>
                                            <div id="place-results-<?php echo (int)$route['id']; ?>" class="hidden mt-1 max-h-32 overflow-y-auto border border-gray-200 rounded bg-white shadow text-sm"></div>
                                            <p class="text-xs text-gray-500 mt-1">Or click on the map to pin (snaps to road).</p>
                                            <p id="pin-status-<?php echo (int)$route['id']; ?>" class="text-xs text-gray-500 mt-0.5">No location set.</p>
                                            <input type="hidden" name="latitude" id="lat-<?php echo (int)$route['id']; ?>" required>
                                            <input type="hidden" name="longitude" id="lng-<?php echo (int)$route['id']; ?>" required>
                                        </div>
                                        <input type="hidden" name="stop_order" value="<?php echo count($route['stops']); ?>">
                                        <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 font-medium text-sm">Add stop</button>
                                    </form>
                                    <script>
                                    document.getElementById('add-stop-form-<?php echo (int)$route['id']; ?>').addEventListener('submit', function(e) {
                                        var lat = document.getElementById('lat-<?php echo (int)$route['id']; ?>').value;
                                        var lng = document.getElementById('lng-<?php echo (int)$route['id']; ?>').value;
                                        if (!lat || !lng) {
                                            e.preventDefault();
                                            alert('Please search for a place or click on the map to set this stop\'s location first.');
                                            return false;
                                        }
                                    });
                                    document.getElementById('save-route-form-<?php echo (int)$route['id']; ?>').addEventListener('submit', function() {
                                        var list = document.getElementById('stops-list-<?php echo (int)$route['id']; ?>');
                                        var form = document.getElementById('save-route-form-<?php echo (int)$route['id']; ?>');
                                        form.querySelectorAll('input[name="order[]"]').forEach(function(i) { i.remove(); });
                                        (list ? list.querySelectorAll('li[data-stop-id]') : []).forEach(function(li) {
                                            var inpt = document.createElement('input');
                                            inpt.type = 'hidden';
                                            inpt.name = 'order[]';
                                            inpt.value = li.getAttribute('data-stop-id');
                                            form.appendChild(inpt);
                                        });
                                    });
                                    </script>
                                </div>
                                <div class="lg:w-1/2 h-64 rounded-lg overflow-hidden border border-gray-200" id="map-route-<?php echo (int)$route['id']; ?>"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (empty($routes_with_stops)): ?>
                    <p class="mt-6 text-gray-500">No routes yet. Create one above. Routes appear on the report form and Reports map.</p>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        const routesData = <?php echo json_encode($routes_with_stops); ?>;
        routesData.forEach(function (route) {
            const el = document.getElementById('map-route-' + route.id);
            if (!el) return;
            const map = L.map(el).setView([14.5995, 120.9842], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap' }).addTo(map);
            const stops = route.stops || [];
            let pendingMarker = null;
            let routeLine = null;

            function setStopFromClick(lat, lng) {
                document.getElementById('lat-' + route.id).value = lat;
                document.getElementById('lng-' + route.id).value = lng;
                var statusEl = document.getElementById('pin-status-' + route.id);
                if (statusEl) statusEl.textContent = 'Location set (on road): ' + lat.toFixed(5) + ', ' + lng.toFixed(5);
                if (pendingMarker) map.removeLayer(pendingMarker);
                pendingMarker = L.marker([lat, lng], { opacity: 0.8 })
                    .bindPopup('New stop here')
                    .addTo(map);
            }
            window.setStopForRoute = window.setStopForRoute || {};
            window.setStopForRoute[route.id] = setStopFromClick;

            if (stops.length > 0) {
                var waypoints = stops.map(function (s) { return [parseFloat(s.latitude), parseFloat(s.longitude)]; });
                function drawRoadRoute() {
                    if (typeof getRouteGeometry === 'function') {
                        getRouteGeometry(waypoints, function (roadLatlngs) {
                            var latlngs = roadLatlngs && roadLatlngs.length ? roadLatlngs : waypoints;
                            if (routeLine) map.removeLayer(routeLine);
                            routeLine = L.polyline(latlngs, { color: '#2563eb', weight: 4 }).addTo(map);
                            stops.forEach(function (s, i) {
                                L.marker([parseFloat(s.latitude), parseFloat(s.longitude)])
                                    .bindPopup((i + 1) + '. ' + s.stop_name)
                                    .addTo(map);
                            });
                            map.fitBounds(latlngs, { padding: [20, 20] });
                        });
                    } else {
                        routeLine = L.polyline(waypoints, { color: '#2563eb', weight: 4 }).addTo(map);
                        stops.forEach(function (s, i) {
                            L.marker([parseFloat(s.latitude), parseFloat(s.longitude)])
                                .bindPopup((i + 1) + '. ' + s.stop_name)
                                .addTo(map);
                        });
                        map.fitBounds(waypoints, { padding: [20, 20] });
                    }
                }
                drawRoadRoute();
            }

            map.on('click', function (e) {
                var statusEl = document.getElementById('pin-status-' + route.id);
                if (statusEl) statusEl.textContent = 'Snapping to road…';
                if (typeof snapToRoad === 'function') {
                    snapToRoad(e.latlng.lat, e.latlng.lng, function (lat, lng) {
                        if (lat != null && lng != null) setStopFromClick(lat, lng);
                        else setStopFromClick(e.latlng.lat, e.latlng.lng);
                    });
                } else {
                    setStopFromClick(e.latlng.lat, e.latlng.lng);
                }
            });
        });

        // Drag-and-drop reorder for stops
        document.querySelectorAll('[id^="stops-list-"]').forEach(function (listEl) {
            var routeId = listEl.getAttribute('data-route-id');
            if (!routeId) return;
            var dragged = null;
            listEl.addEventListener('dragstart', function (e) {
                dragged = e.target;
                e.target.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', e.target.innerHTML);
            });
            listEl.addEventListener('dragend', function (e) {
                e.target.style.opacity = '1';
                dragged = null;
            });
            listEl.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            });
            listEl.addEventListener('drop', function (e) {
                e.preventDefault();
                var dropTarget = e.target.closest('li[data-stop-id]');
                if (dropTarget && dragged) {
                    var rect = dropTarget.getBoundingClientRect();
                    var mid = rect.top + rect.height / 2;
                    if (e.clientY < mid) listEl.insertBefore(dragged, dropTarget);
                    else listEl.insertBefore(dragged, dropTarget.nextSibling);
                }
                var stopIds = [];
                listEl.querySelectorAll('li[data-stop-id]').forEach(function (li) {
                    stopIds.push(li.getAttribute('data-stop-id'));
                });
                if (stopIds.length === 0) return;
                var formData = new FormData();
                formData.append('action', 'reorder_stops');
                formData.append('route_id', routeId);
                stopIds.forEach(function (id) { formData.append('order[]', id); });
                fetch('manage_routes.php', { method: 'POST', body: formData, credentials: 'same-origin' })
                    .then(function () { window.location.reload(); })
                    .catch(function () { alert('Failed to save order.'); });
            });
        });

        // Place search (Nominatim)
        function searchPlace(routeId, query, resultsEl, setStopFn) {
            if (!query || !query.trim()) return;
            resultsEl.classList.remove('hidden');
            resultsEl.innerHTML = '<p class="p-2 text-gray-500">Searching…</p>';
            var url = 'https://nominatim.openstreetmap.org/search?q=' + encodeURIComponent(query.trim()) + '&format=json&limit=6';
            fetch(url, { headers: { 'Accept': 'application/json', 'User-Agent': 'TransportOps/1.0 (Route Management)' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data || data.length === 0) {
                        resultsEl.innerHTML = '<p class="p-2 text-gray-500">No places found.</p>';
                        return;
                    }
                    resultsEl.innerHTML = '';
                    data.forEach(function (item) {
                        var lat = parseFloat(item.lat);
                        var lng = parseFloat(item.lon);
                        var name = item.display_name || (lat + ', ' + lng);
                        var div = document.createElement('div');
                        div.className = 'p-2 hover:bg-blue-50 cursor-pointer border-b border-gray-100 last:border-0';
                        div.textContent = name.length > 80 ? name.substring(0, 80) + '…' : name;
                        div.addEventListener('click', function () {
                            resultsEl.classList.add('hidden');
                            resultsEl.innerHTML = '';
                            if (typeof snapToRoad === 'function') {
                                var statusEl = document.getElementById('pin-status-' + routeId);
                                if (statusEl) statusEl.textContent = 'Snapping to road…';
                                snapToRoad(lat, lng, function (snapLat, snapLng) {
                                    if (snapLat != null && snapLng != null && setStopFn) setStopFn(snapLat, snapLng);
                                    else if (setStopFn) setStopFn(lat, lng);
                                });
                            } else if (setStopFn) {
                                setStopFn(lat, lng);
                            }
                        });
                        resultsEl.appendChild(div);
                    });
                })
                .catch(function () {
                    resultsEl.innerHTML = '<p class="p-2 text-red-500">Search failed.</p>';
                });
        }
        routesData.forEach(function (route) {
            var searchInput = document.getElementById('place-search-' + route.id);
            var searchBtn = document.getElementById('place-search-btn-' + route.id);
            var resultsEl = document.getElementById('place-results-' + route.id);
            var setStopFn = window.setStopForRoute && window.setStopForRoute[route.id];
            if (!searchInput || !resultsEl || !setStopFn) return;
            function doSearch() { searchPlace(route.id, searchInput.value, resultsEl, setStopFn); }
            if (searchBtn) searchBtn.addEventListener('click', doSearch);
            searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
            });
        });
        var highlightId = <?php echo $highlight_route_id ? (int)$highlight_route_id : 0; ?>;
        if (highlightId) {
            var el = document.getElementById('route-' + highlightId);
            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        (function () {
            const toggle = document.getElementById('adminNavToggle');
            const links = document.getElementById('adminNavLinks');
            const footer = document.getElementById('adminNavFooter');
            if (!toggle || !links || !footer) return;
            toggle.addEventListener('click', function () {
                if (window.innerWidth >= 768) return;
                links.classList.toggle('hidden');
                footer.classList.toggle('hidden');
            });
        })();
    </script>
</body>
</html>
