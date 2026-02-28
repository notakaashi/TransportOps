<?php
/**
 * About Page
 * Information about the Public Transportation Operations System
 */

session_start();
require_once 'db.php';
$is_logged_in = isset($_SESSION['user_id']);
$user_profile_data = ['profile_image' => null];

// Fetch profile image for logged-in users
if ($is_logged_in && isset($_SESSION['user_id'])) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['profile_image']) {
            $user_profile_data['profile_image'] = $row['profile_image'];
            $_SESSION['profile_image'] = $row['profile_image'];
        }
    } catch (PDOException $e) {
        error_log("About: profile fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Public Transportation Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --transit-primary-route: #ff1744;
            --transit-secondary-route: #4169e1;
            --transit-info: #facc15;
            --transit-foundation: #050505;
        }
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body class="bg-[#F3F4F6]">
    <!-- Navigation Bar -->
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <?php if ($is_logged_in): ?>
                            <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <?php endif; ?>
                        <a href="about.php" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-800">About</a>
                        <?php if ($is_logged_in): ?>
                            <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                            <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                            <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                        <?php endif; ?>
                    </div>
                    <!-- mobile dropdown menu -->
                    <div id="mobileMenu" class="md:hidden hidden absolute top-16 left-0 right-0 bg-[#1E3A8A] text-white flex flex-col space-y-1 px-4 py-2 z-20">
                        <?php if ($is_logged_in): ?>
                            <a href="user_dashboard.php" class="block px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <?php endif; ?>
                        <a href="about.php" class="block px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <?php if ($is_logged_in): ?>
                            <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                            <a href="reports_map.php" class="block px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                            <a href="routes.php" class="block px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <?php if ($is_logged_in): ?>
                        <div class="relative">
                            <button id="profileMenuButton"
                                    class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                                <div class="hidden sm:flex flex-col items-end leading-tight">
                                    <span class="text-xs sm:text-sm text-white font-medium">
                                        <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                                    </span>
                                    <span class="text-[11px] text-blue-100">
                                        <?php echo htmlspecialchars($_SESSION['role'] ?? 'User'); ?>
                                    </span>
                                </div>
                                <div class="flex items-center gap-1">
                                    <?php if ($user_profile_data['profile_image']): ?>
                                        <img src="uploads/<?php echo htmlspecialchars($user_profile_data['profile_image']); ?>"
                                             alt="Profile"
                                             class="h-8 w-8 rounded-full object-cover border-2 border-white">
                                    <?php else: ?>
                                        <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                            <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <svg class="w-4 h-4 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                            <div id="profileMenu"
                                 class="hidden absolute right-0 top-11 w-44 bg-white text-gray-800 rounded-lg shadow-lg border border-gray-100 py-1 z-40">
                                <a href="profile.php" class="block px-3 py-2 text-sm hover:bg-gray-50">
                                    View &amp; Edit Profile
                                </a>
                                <div class="my-1 border-t border-gray-100"></div>
                                <a href="logout.php" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                                    Logout
                                </a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="register.php" class="text-white border border-white/40 hover:bg-white hover:text-[#1E3A8A] px-2 sm:px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap transition duration-150">Register</a>
                        <a href="login.php" class="bg-[#10B981] text-white px-3 sm:px-4 py-2 rounded-md hover:bg-[#059669] transition duration-150 font-medium whitespace-nowrap">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-12">

        <!-- Hero Section -->
        <div class="text-center mb-12">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4 leading-tight tracking-tight">About Our System</h1>
            <p class="text-base sm:text-lg lg:text-xl text-gray-600 max-w-3xl mx-auto">
                A Public Transportation Operations System with Crowding and Delay Monitoring — built to bridge the gap between fleet-level tracking and the real passenger experience in Metro Manila.
            </p>
        </div>

        <!-- Content Sections -->
        <div class="space-y-12">

            <!-- System Overview -->
            <section class="bg-white rounded-2xl shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">System Overview</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    The Transport Operations System lets commuters and transit supervisors work together to capture on-the-ground conditions in real time. Registered users can report crowding levels, wait times, and delay reasons for specific routes or stops; each submission is automatically geofenced and assigned a trust score through peer verification to ensure data accuracy. A centralized web dashboard aggregates these reports alongside live GPS tracking of vehicles.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    With interactive maps, heat‑map analytics, and delay trend reports, operations managers gain actionable insights into congestion hotspots and service disruptions. Historical data can be reviewed to forecast recurring bottlenecks, while live alerts enable quick schedule adjustments and vehicle redeployment ultimately helping to improve reliability, reduce overcrowding, and enhance the commuter experience across the network.
                </p>
            </section>

            <!-- Key Features -->
            <section class="bg-white rounded-2xl shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Key Features</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-5 bg-blue-50 rounded-xl border border-blue-100">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Real-Time Fleet Tracking</h3>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">
                            A centralized monitoring dashboard lets operations managers view the live GPS positions of all active bus units, with automated tracking of departure times and estimated arrivals replacing error-prone manual logs.
                        </p>
                    </div>
                    <div class="p-5 bg-green-50 rounded-xl border border-green-100">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Crowdsourced Data Collection</h3>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">
                            Commuters at terminals and informal roadside stops submit live reports on crowd levels, queue lengths, and service disruptions. GPS-based geofencing and a peer-verification trust scoring system ensure that only credible, location-validated reports are used.
                        </p>
                    </div>
                    <div class="p-5 bg-yellow-50 rounded-xl border border-yellow-100">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Analytics Dashboard</h3>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">
                            Crowding heatmaps and peak-hour analytics highlight the time intervals and route segments with the highest passenger demand. Delay trend analysis tools process historical data to forecast recurring bottlenecks and travel time variances.
                        </p>
                    </div>
                    <div class="p-5 bg-purple-50 rounded-xl border border-purple-100">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h3 class="text-lg font-semibold text-gray-800">Delay Management</h3>
                        </div>
                        <p class="text-gray-600 text-sm leading-relaxed">
                            Commuters can categorize delay causes traffic congestion, mechanical issues, or overcrowding feeding live alerts into a decision support engine that recommends vehicle redeployment and schedule adjustments to supervisors.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Our Mission -->
            <section class="bg-white rounded-2xl shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Our Mission</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Our mission is to digitize and streamline daily public transit operations by integrating real-time crowding and delay data into transport management improving service reliability and enabling data-driven decisions across Metro Manila's urban and provincial routes.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    By combining fleet-level GPS monitoring with ground-level passenger reporting, we aim to reduce commuter uncertainty, minimize missed dispatch windows, and empower both operators and commuters to make better-informed decisions every day. We believe that giving passengers a voice in the system and validating that voice through smart data integrity checks is the key to solving the long-standing operational challenges of urban public transportation.
                </p>
            </section>

            <!-- Technology Stack -->
            <section class="bg-white rounded-2xl shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Technology Stack</h2>
                <p class="text-gray-600 leading-relaxed mb-5">
                    The system is built on reliable, widely-supported web technologies to ensure performance, scalability, and ease of maintenance across all user devices.
                </p>
                <ul class="space-y-3">
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-800">PHP</span> — Server-side application logic, authentication, and report processing</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-800">MySQL / MariaDB</span> — Relational database for users, routes, fleet data, and commuter reports</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-800">GPS Geofencing</span> — Location-based validation to verify that submitted reports originate near the reported route or stop</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-800">Peer-Verification Trust Scoring</span> — Multi-user confirmation algorithm that assigns credibility scores to crowd and delay reports</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="mt-1 w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                        <span class="text-gray-600"><span class="font-medium text-gray-800">Tailwind CSS &amp; Leaflet.js</span> — Responsive UI design and interactive map rendering for the Reports Map</span>
                    </li>
                </ul>
            </section>

        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> Public Transportation Operations System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        (function () {
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (!btn || !menu) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', function () {
                if (!menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
                const brand = document.getElementById('brandLink');
                const mobile = document.getElementById('mobileMenu');
                if (brand && mobile) {
                    brand.addEventListener('click', function (e) {
                        if (window.innerWidth < 768) {
                            e.preventDefault();
                            mobile.classList.toggle('hidden');
                        }
                    });
                    document.addEventListener('click', function (ev) {
                        if (mobile && !mobile.contains(ev.target) && ev.target !== brand) {
                            mobile.classList.add('hidden');
                        }
                    });
                }
        })();
    </script>
</body>
</html>