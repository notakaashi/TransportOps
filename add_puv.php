<?php
/**
 * Add PUV Page
 * Allows admin to add new PUV units to the fleet
 */

session_start();
require_once 'db.php';

// Check if user is logged in and is an Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plate_number = trim($_POST['plate_number'] ?? '');
    $vehicle_type = $_POST['vehicle_type'] ?? 'Bus';
    $current_route = trim($_POST['current_route'] ?? '');
    $crowd_status = $_POST['crowd_status'] ?? 'Light';
    
    // Validate input
    if (empty($plate_number) || empty($current_route)) {
        $error = 'Plate number and route are required.';
    } elseif (!in_array($vehicle_type, ['Bus', 'Jeepney', 'Tricycle', 'UV Express', 'Taxi', 'Train', 'Other'])) {
        $error = 'Invalid vehicle type selected.';
    } elseif (!in_array($crowd_status, ['Light', 'Moderate', 'Heavy'])) {
        $error = 'Invalid crowd status selected.';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Check if plate number already exists
            $stmt = $pdo->prepare("SELECT id FROM puv_units WHERE plate_number = ?");
            $stmt->execute([$plate_number]);
            if ($stmt->fetch()) {
                $error = 'Plate number already exists.';
            } else {
                // Insert PUV
                $stmt = $pdo->prepare("INSERT INTO puv_units (plate_number, vehicle_type, current_route, crowd_status) VALUES (?, ?, ?, ?)");
                $stmt->execute([$plate_number, $vehicle_type, $current_route, $crowd_status]);
                
                $success = 'Vehicle added successfully!';
                // Clear form data
                $_POST = [];
                $plate_number = $current_route = '';
                $vehicle_type = 'Bus';
                $crowd_status = 'Light';
            }
        } catch (PDOException $e) {
            error_log("Add PUV error: " . $e->getMessage());
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $error = 'Plate number already exists. Please use a different plate number.';
            } else {
                $error = 'Failed to add PUV. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add PUV - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <aside class="w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="p-6 flex-shrink-0">
                <div class="flex items-center mb-8">
                    <div class="bg-blue-600 p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold">Transport Ops</h1>
                </div>
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Fleet Overview
                    </a>
                    <a href="tracking.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Real-Time Tracking
                    </a>
                    <a href="route_status.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route Status
                    </a>
                    <a href="heatmap.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Crowdsourcing Heatmap
                    </a>
                    <a href="user_management.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        User Management
                    </a>
                    <a href="add_puv.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add PUV
                    </a>
                </nav>
            </div>
            <div class="mt-auto p-6 border-t border-gray-700">
                <div class="bg-gray-700 rounded-lg p-4 mb-4">
                    <p class="text-xs text-gray-400 mb-1">Logged in as</p>
                    <p class="text-sm font-semibold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                    <p class="text-xs text-blue-400 mt-1"><?php echo htmlspecialchars($_SESSION['role']); ?></p>
                </div>
                <a href="logout.php" 
                   class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">
                    Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <div class="p-8">
                <div class="max-w-2xl mx-auto">
                    <div class="bg-white rounded-xl shadow-2xl p-8 border-t-4 border-indigo-500">
                        <div class="flex items-center mb-6">
                            <div class="bg-indigo-100 p-3 rounded-lg mr-4">
                                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-3xl font-bold text-gray-800">Add New Vehicle</h2>
                                <p class="text-gray-600 mt-1">Register a new vehicle to the fleet</p>
                            </div>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                    </svg>
                                    <?php echo htmlspecialchars($success); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="" class="space-y-6" onsubmit="return validateForm()">
                            <div>
                                <label for="plate_number" class="block text-sm font-semibold text-gray-700 mb-2">Plate Number</label>
                                <input type="text" id="plate_number" name="plate_number" required 
                                       value="<?php echo htmlspecialchars($plate_number ?? ''); ?>"
                                       placeholder="ABC-1234"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                            </div>
                            
                            <div>
                                <label for="current_route" class="block text-sm font-semibold text-gray-700 mb-2">Current Route</label>
                                <input type="text" id="current_route" name="current_route" required 
                                       value="<?php echo htmlspecialchars($current_route ?? ''); ?>"
                                       placeholder="Route Name (e.g., EDSA - Cubao)"
                                       class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label for="lat" class="block text-sm font-semibold text-gray-700 mb-2">Latitude (Optional)</label>
                                    <input type="number" step="any" id="lat" name="lat" 
                                           value="<?php echo htmlspecialchars($lat ?? ''); ?>"
                                           placeholder="14.5995"
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                                </div>
                                
                                <div>
                                    <label for="lng" class="block text-sm font-semibold text-gray-700 mb-2">Longitude (Optional)</label>
                                    <input type="number" step="any" id="lng" name="lng" 
                                           value="<?php echo htmlspecialchars($lng ?? ''); ?>"
                                           placeholder="120.9842"
                                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150">
                                </div>
                            </div>
                            
                            <div>
                                <label for="crowd_status" class="block text-sm font-semibold text-gray-700 mb-2">Initial Crowd Status</label>
                                <select id="crowd_status" name="crowd_status" required
                                        class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition duration-150 bg-white">
                                    <option value="Light" <?php echo (($crowd_status ?? 'Light') === 'Light') ? 'selected' : ''; ?>>Light</option>
                                    <option value="Moderate" <?php echo (($crowd_status ?? '') === 'Moderate') ? 'selected' : ''; ?>>Moderate</option>
                                    <option value="Heavy" <?php echo (($crowd_status ?? '') === 'Heavy') ? 'selected' : ''; ?>>Heavy</option>
                                </select>
                            </div>
                            
                            <div class="flex space-x-4 pt-4">
                                <button type="submit" 
                                        class="flex-1 bg-gradient-to-r from-indigo-600 to-indigo-700 text-white py-3 px-6 rounded-lg hover:from-indigo-700 hover:to-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-150 font-semibold shadow-lg">
                                    Add Vehicle
                                </button>
                                <a href="admin_dashboard.php" 
                                   class="flex-1 bg-gray-200 text-gray-700 py-3 px-6 rounded-lg hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition duration-150 font-semibold text-center">
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function validateForm() {
            const plateNumber = document.getElementById('plate_number').value.trim();
            const currentRoute = document.getElementById('current_route').value.trim();
            
            if (!plateNumber) {
                alert('Please enter a plate number.');
                document.getElementById('plate_number').focus();
                return false;
            }
            
            if (!currentRoute) {
                alert('Please enter a current route.');
                document.getElementById('current_route').focus();
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>


