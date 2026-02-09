<?php
/**
 * About Page
 * Information about the Public Transportation Operations System
 */

session_start();
$is_logged_in = isset($_SESSION['user_id']);
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
            --transit-primary-route: #ff1744;    /* Bright Red */
            --transit-secondary-route: #4169e1;  /* Royal Blue */
            --transit-info: #facc15;             /* Vivid Yellow */
            --transit-foundation: #050505;       /* Matte Black */
        }
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
        .bg-transit-foundation {
            background-color: var(--transit-foundation);
            color: #f9fafb;
        }
        .text-transit-primary {
            color: var(--transit-primary-route);
        }
        .bg-transit-primary {
            background-color: var(--transit-primary-route);
        }
        .border-transit-primary {
            border-color: var(--transit-primary-route);
        }
        .text-transit-secondary {
            color: var(--transit-secondary-route);
        }
        .bg-transit-secondary {
            background-color: var(--transit-secondary-route);
        }
        .border-transit-secondary {
            border-color: var(--transit-secondary-route);
        }
        .bg-transit-info {
            background-color: var(--transit-info);
            color: #1f2933;
        }
    </style>
</head>
<body class="bg-[#F3F4F6]">
    <!-- Navigation Bar -->
    <nav class="bg-[#1E3A8A] text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="index.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-[#10B981]">About</a>
                        <?php if ($is_logged_in): ?>
                            <?php if ($_SESSION['role'] === 'Admin'): ?>
                                <a href="admin_dashboard.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <?php else: ?>
                                <a href="user_dashboard.php" class="text-gray-700 hover:text-gray-900 px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                            <?php endif; ?>
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
                                    <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                        <?php echo strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)); ?>
                                    </div>
                                    <svg class="w-4 h-4 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </button>
                            <div id="profileMenu"
                                 class="hidden absolute right-0 top-11 w-44 bg-white text-gray-800 rounded-lg shadow-lg border border-gray-100 py-1 z-40">
                                <a href="profile.php"
                                   class="block px-3 py-2 text-sm hover:bg-gray-50">
                                    View &amp; Edit Profile
                                </a>
                                <div class="my-1 border-t border-gray-100"></div>
                                <a href="logout.php"
                                   class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">
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
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <h1 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4 leading-tight tracking-tight">About Our System</h1>
            <p class="text-base sm:text-lg lg:text-xl text-gray-600 max-w-3xl mx-auto">
                Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
            </p>
        </div>

        <!-- Content Sections -->
        <div class="space-y-12">
            <!-- Section 1 -->
            <section class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">System Overview</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. 
                    Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. 
                    Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum. 
                    Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.
                </p>
            </section>

            <!-- Section 2 -->
            <section class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Key Features</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Real-Time Tracking</h3>
                        <p class="text-gray-600">
                            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                        </p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Crowdsourced Data</h3>
                        <p class="text-gray-600">
                            Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                        </p>
                    </div>
                    <div class="p-4 bg-yellow-50 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Analytics Dashboard</h3>
                        <p class="text-gray-600">
                            Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.
                        </p>
                    </div>
                    <div class="p-4 bg-purple-50 rounded-lg">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2">Delay Management</h3>
                        <p class="text-gray-600">
                            Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                        </p>
                    </div>
                </div>
            </section>

            <!-- Section 3 -->
            <section class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Our Mission</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. 
                    Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
                <p class="text-gray-600 leading-relaxed">
                    Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. 
                    Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                </p>
            </section>

            <!-- Section 4 -->
            <section class="bg-white rounded-lg shadow-md p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">Technology Stack</h2>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. 
                    Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.
                </p>
                <ul class="list-disc list-inside text-gray-600 space-y-2">
                    <li>Lorem ipsum dolor sit amet, consectetur adipiscing elit</li>
                    <li>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua</li>
                    <li>Ut enim ad minim veniam, quis nostrud exercitation ullamco</li>
                    <li>Duis aute irure dolor in reprehenderit in voluptate velit esse</li>
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
</body>
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
    })();
</script>
</html>


