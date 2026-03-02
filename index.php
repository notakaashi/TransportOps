<?php
/**
 * Landing Page
 * Public homepage for non-logged-in users
 */

require_once 'auth_helper.php';
secureSessionStart();

// Redirect logged-in users to appropriate dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_dashboard.php');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Transportation Operations System</title>
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
<body class="bg-[#F3F4F6] min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-[#1E3A8A] text-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="index.php" class="bg-blue-500 text-white px-3 py-2 rounded-md text-sm font-medium border-b-2 border-blue-800">Home</a>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                    </div>
                    <div id="mobileMenu" class="md:hidden hidden absolute top-16 left-0 right-0 bg-[#1E3A8A] text-white flex flex-col space-y-1 px-4 py-2 z-20">
                        <a href="index.php" class="block px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="block px-3 py-2 rounded-md text-sm font-medium">About</a>
                    </div>
                </div>
                <div class="flex items-center gap-2 sm:gap-4">
                    <a href="register.php" 
                       class="text-white border border-white/40 hover:bg-white hover:text-[#1E3A8A] px-2 sm:px-3 py-2 rounded-md text-sm font-medium transition duration-150 whitespace-nowrap">
                        Register
                    </a>
                    <a href="login.php" 
                       class="bg-[#10B981] text-white px-3 sm:px-4 py-2 rounded-md hover:bg-[#059669] focus:outline-none focus:ring-2 focus:ring-white/60 focus:ring-offset-2 focus:ring-offset-[#1E3A8A] transition duration-150 font-medium whitespace-nowrap">
                        Login
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="text-center">
            <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-gray-900 mb-4 sm:mb-6 leading-tight tracking-tight">
                Public Transportation Operations System
            </h1>
            <p class="text-base sm:text-lg lg:text-xl text-gray-600 mb-6 sm:mb-8 max-w-3xl mx-auto">
                Digitizing transit activities and managing fleet operations through real-time crowdsourced data. 
                Monitor PUV units, track crowding levels, and optimize transportation services.
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-3 sm:gap-4 max-w-md sm:max-w-none mx-auto">
                <a href="register.php" 
                   class="bg-blue-600 text-white px-6 sm:px-8 py-3 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 font-medium text-base sm:text-lg w-full sm:w-auto">
                    Get Started
                </a>
                <a href="login.php" 
                   class="bg-white text-blue-600 px-6 sm:px-8 py-3 rounded-lg border-2 border-blue-600 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-150 font-medium text-base sm:text-lg w-full sm:w-auto">
                    Login
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Feature 1 -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-300">
                <div class="bg-blue-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Fleet Management</h3>
                <p class="text-gray-600">
                    Monitor and manage your entire PUV fleet in real-time. Track routes, locations, and operational status.
                </p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-300">
                <div class="bg-green-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Crowdsourcing Heatmap</h3>
                <p class="text-gray-600">
                    Visualize crowdsourced demand data with interactive heatmaps. Identify high-traffic routes and optimize service.
                </p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white rounded-lg shadow-lg p-6 hover:shadow-xl transition duration-300">
                <div class="bg-yellow-100 w-16 h-16 rounded-full flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2">Real-Time Reports</h3>
                <p class="text-gray-600">
                    Collect and analyze real-time reports from commuters and drivers. Track crowding levels and delays.
                </p>
            </div>
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
            const brand = document.getElementById('brandLink');
            const mobile = document.getElementById('mobileMenu');
            if (!brand || !mobile) return;
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
        })();
    </script>
</body>
</html>
