<?php
/**
 * Route Status Overview
 * Displays routes from route_definitions with report counts. Select a route to edit or delete.
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin_login.php");
    exit();
}
if ($_SESSION["role"] !== "Admin") {
    header("Location: login.php");
    exit();
}

$routes = [];
$delayed_route_ids = [];
$selected_route_id = isset($_GET["route_id"]) ? (int) $_GET["route_id"] : null;

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query(
        "SELECT id, name, created_at FROM route_definitions ORDER BY name",
    );
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routes as &$r) {
        $rid = (int) $r["id"];
        $stmt = $pdo->prepare("
            SELECT
                COUNT(*) as report_count,
                SUM(CASE WHEN crowd_level = 'Light' THEN 1 ELSE 0 END) as light_count,
                SUM(CASE WHEN crowd_level = 'Moderate' THEN 1 ELSE 0 END) as moderate_count,
                SUM(CASE WHEN crowd_level = 'Heavy' THEN 1 ELSE 0 END) as heavy_count
            FROM reports
            WHERE route_definition_id = ?
        ");
        $stmt->execute([$rid]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        $r["report_count"] = (int) ($stats["report_count"] ?? 0);
        $r["light_count"] = (int) ($stats["light_count"] ?? 0);
        $r["moderate_count"] = (int) ($stats["moderate_count"] ?? 0);
        $r["heavy_count"] = (int) ($stats["heavy_count"] ?? 0);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as c FROM reports
            WHERE route_definition_id = ? AND delay_reason IS NOT NULL AND delay_reason != ''
            AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$rid]);
        $r["delayed_count"] = (int) $stmt->fetchColumn();
    }
    unset($r);

    $stmt = $pdo->query("
        SELECT route_definition_id, COUNT(*) as delayed_count
        FROM reports
        WHERE route_definition_id IS NOT NULL AND delay_reason IS NOT NULL AND delay_reason != ''
        AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $delayed_route_ids = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    error_log("Route status error: " . $e->getMessage());
    $delayed_route_ids = [];
}
?>
<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Route Status - Transport Operations System</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <?php include "admin_layout_head.php"; ?>
        </head>
        <body class="bg-[var(--transit-foundation)]">
            <?php include "admin_sidebar.php"; ?>

    <!-- ═══ MAIN CONTENT ════════════════════════════════════ -->
    <main class="main-area">
        <div class="p-4 sm:p-6 lg:p-8">
                <!-- Page Header -->
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-[#1e3a8a]">Route Status Overview</h2>
                    <p class="text-[#475569] mt-2">Monitor routes and report counts. Select a route to edit or delete it.</p>
                </div>

                <!-- Route selector -->
                <div class="mb-6 glass-card rounded-2xl p-4">
                    <label for="routeSelect" class="block text-sm font-medium text-[#1e3a8a] mb-2">Select a route to edit or delete</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <select id="routeSelect" class="px-4 py-2 border border-[#d1d5db] rounded-md focus:ring-[#fbbf24] focus:border-[#fbbf24] text-sm">
                            <option value="">-- Select a route --</option>
                            <?php foreach ($routes as $r): ?>
                                <option value="<?php echo (int) $r[
                                    "id"
                                ]; ?>" <?php echo $selected_route_id ===
(int) $r["id"]
    ? "selected"
    : ""; ?>>
                                    <?php echo htmlspecialchars($r["name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <a id="editRouteBtn" href="#" class="px-4 py-2 bg-[#fbbf24] text-[#1e3a8a] rounded-md hover:bg-[#f59e0b] text-sm font-medium hidden">Edit route</a>
                        <form id="deleteRouteForm" method="POST" action="manage_routes.php" class="inline" onsubmit="return confirm('Delete this route and all its stops? Reports for this route will keep the route name as null.');">
                            <input type="hidden" name="action" value="delete_route">
                            <input type="hidden" name="route_id" id="deleteRouteId" value="">
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm font-medium hidden" id="deleteRouteBtn">Delete route</button>
                        </form>
                    </div>
                </div>

                <!-- Routes Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php if (empty($routes)): ?>
                        <div class="col-span-full glass-card rounded-2xl p-8 text-center">
                            <p class="text-[#475569]">No routes found. <a href="manage_routes.php" class="text-[#1e3a8a] hover:underline">Create routes</a> in Manage Routes.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($routes as $route): ?>
                            <?php
                            $has_delays = !empty($route["delayed_count"]);
                            $delayed_count =
                                (int) ($route["delayed_count"] ?? 0);
                            $status_class = $has_delays
                                ? "border-red-500"
                                : "border-green-500";
                            $is_selected =
                                $selected_route_id === (int) $route["id"];
                            ?>
                            <div class="glass-card rounded-2xl p-6 border-l-4 <?php echo $status_class; ?> <?php echo $is_selected
     ? "ring-2 ring-blue-500"
     : ""; ?>">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-xl font-semibold text-[#1e3a8a]"><?php echo htmlspecialchars(
                                        $route["name"],
                                    ); ?></h3>
                                    <?php if ($has_delays): ?>
                                        <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">Delayed</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">On Time</span>
                                    <?php endif; ?>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-[#475569]">Total Reports</span>
                                        <span class="font-semibold text-[#1e3a8a]"><?php echo $route[
                                            "report_count"
                                        ]; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-[#475569]">Light Crowding</span>
                                        <span class="font-semibold text-green-600"><?php echo $route[
                                            "light_count"
                                        ]; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-[#475569]">Moderate Crowding</span>
                                        <span class="font-semibold text-yellow-600"><?php echo $route[
                                            "moderate_count"
                                        ]; ?></span>
                                    </div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-[#475569]">Heavy Crowding</span>
                                        <span class="font-semibold text-red-600"><?php echo $route[
                                            "heavy_count"
                                        ]; ?></span>
                                    </div>
                                    <?php if ($has_delays): ?>
                                        <div class="pt-3 border-t border-[#e5e7eb]">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm text-red-600 font-medium">Delays (last hour)</span>
                                                <span class="font-semibold text-red-600"><?php echo $delayed_count; ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mt-4 pt-3 border-t border-[#e5e7eb] flex gap-2">
                                    <a href="manage_routes.php?highlight=<?php echo (int) $route[
                                        "id"
                                    ]; ?>" class="text-sm text-[#1e3a8a] hover:text-[#fbbf24] font-medium">Edit</a>
                                    <form method="POST" action="manage_routes.php" class="inline" onsubmit="return confirm('Delete this route and all its stops?');">
                                        <input type="hidden" name="action" value="delete_route">
                                        <input type="hidden" name="route_id" value="<?php echo (int) $route[
                                            "id"
                                        ]; ?>">
                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <script>
        (function() {
            var sel = document.getElementById('routeSelect');
            var editBtn = document.getElementById('editRouteBtn');
            var deleteBtn = document.getElementById('deleteRouteBtn');
            var deleteId = document.getElementById('deleteRouteId');
            if (!sel) return;
            function update() {
                var id = sel.value;
                if (id) {
                    editBtn.href = 'manage_routes.php?highlight=' + id;
                    editBtn.classList.remove('hidden');
                    deleteBtn.classList.remove('hidden');
                    deleteId.value = id;
                } else {
                    editBtn.classList.add('hidden');
                    deleteBtn.classList.add('hidden');
                }
            }
            sel.addEventListener('change', function() {
                if (sel.value) window.location = 'route_status.php?route_id=' + sel.value;
                update();
            });
            update();
        })();

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
    <script src="admin_sidebar_js.php"></script>
</body>
</html>
