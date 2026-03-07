<?php
/**
 * Public User Profile Page
 * Shows user's trust score, badge, and reporting history
 */

require_once "auth_helper.php";
secureSessionStart();
require_once "db.php";
require_once "trust_helper.php";

// Get user ID from URL parameter
$userId = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

// Check if coming from admin (hide navigation)
$fromAdmin = isset($_GET["admin"]) && $_GET["admin"] == "1";

if ($userId <= 0) {
    header("Location: index.php");
    exit();
}

// Get user's public profile
$profile = getUserPublicProfile($userId);

if (!$profile) {
    header("Location: index.php");
    exit();
}

$user = $profile["user"];
$stats = $profile["stats"];
$recentReports = $profile["recent_reports"];
$badge = $profile["badge"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(
        $user["name"],
    ); ?> - Public Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --transit-primary-route: #22335C;   /* Navy Blue */
            --transit-secondary-route: #5B7B99; /* Slate Blue */
            --transit-info: #FBC061;            /* Gold/Yellow */
            --transit-foundation: #E8E1D8;      /* Light Gray */
        }
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }

        /* Glassmorphism styles */
        .glass-nav {
            background: rgba(34, 51, 92, 0.75);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.35), 0 2px 8px 0 rgba(0,0,0,0.15);
            transition: background 0.3s ease, box-shadow 0.3s ease, top 0.3s ease;
        }
        .glass-nav.scrolled {
            background: rgba(34, 51, 92, 0.92);
            box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.5), 0 4px 12px 0 rgba(0,0,0,0.25);
        }

        /* Nav link: box only shows on hover or when active (current page) */
        .nav-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            border: 1px solid transparent;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            text-decoration: none;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .nav-link.active {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
        .nav-link-mobile {
            display: block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: #e5e7eb;
            border: 1px solid transparent;
            transition: background 0.2s ease, border-color 0.2s ease, color 0.2s ease;
            text-decoration: none;
        }
        .nav-link-mobile:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .nav-link-mobile.active {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
    </style>
</head>
<body class="bg-[var(--transit-foundation)] min-h-screen">
    <!-- Navigation Bar -->
    <?php if (!$fromAdmin): ?>
    <nav id="floatingNav" class="fixed top-4 left-1/2 -translate-x-1/2 z-30 glass-nav text-white rounded-2xl w-[calc(100%-2rem)] max-w-7xl">
        <div class="px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-14">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <?php if (isset($_SESSION["user_id"])): ?>
                            <a href="user_dashboard.php" class="nav-link">Home</a>
                        <?php endif; ?>
                        <a href="about.php" class="nav-link">About</a>
                        <?php if (isset($_SESSION["user_id"])): ?>
                            <a href="report.php" class="nav-link">Submit Report</a>
                            <a href="reports_map.php" class="nav-link">Reports Map</a>
                            <a href="routes.php" class="nav-link">Routes</a>
                        <?php endif; ?>
                    </div>
                    <div id="mobileMenu" class="md:hidden hidden absolute top-full left-0 right-0 mt-2 text-white flex flex-col space-y-1 px-4 py-3 z-20 rounded-2xl" style="background: rgba(34,51,92,0.95); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px 0 rgba(31,38,135,0.4);">
                        <?php if (isset($_SESSION["user_id"])): ?>
                            <a href="user_dashboard.php" class="nav-link-mobile">Home</a>
                        <?php endif; ?>
                        <a href="about.php" class="nav-link-mobile">About</a>
                        <?php if (isset($_SESSION["user_id"])): ?>
                            <a href="report.php" class="nav-link-mobile">Submit Report</a>
                            <a href="reports_map.php" class="nav-link-mobile">Reports Map</a>
                            <a href="routes.php" class="nav-link-mobile">Routes</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="relative flex items-center gap-2 sm:gap-3">
                    <?php if (isset($_SESSION["user_id"])): ?>
                        <button id="profileMenuButton"
                                class="flex items-center gap-2 px-2 py-1.5 rounded-full hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60">
                            <div class="hidden sm:flex flex-col items-end leading-tight">
                                <span class="text-xs sm:text-sm text-white font-medium">
                                    <?php echo htmlspecialchars(
                                        $_SESSION["user_name"],
                                    ); ?>
                                </span>
                                <span class="text-[11px] text-blue-100">
                                    <?php echo htmlspecialchars(
                                        $_SESSION["role"],
                                    ); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-1">
                                <?php if (
                                    !empty($user_profile_data["profile_image"])
                                ): ?>
                                    <img src="uploads/<?php echo htmlspecialchars(
                                        $user_profile_data["profile_image"],
                                    ); ?>"
                                         alt="Profile"
                                         class="h-8 w-8 rounded-full object-cover border-2 border-white">
                                <?php else: ?>
                                    <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                        <?php echo strtoupper(
                                            substr(
                                                $_SESSION["user_name"] ?? "U",
                                                0,
                                                1,
                                            ),
                                        ); ?>
                                    </div>
                                <?php endif; ?>
                                <svg class="w-4 h-4 text-blue-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </button>
                        <div id="profileMenu"
                             class="hidden absolute right-0 top-11 w-48 rounded-lg shadow-lg py-1 z-40"
                             style="background: rgba(34,51,92,0.92); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid rgba(255,255,255,0.15); box-shadow: 0 8px 32px 0 rgba(31,38,135,0.4);">
                            <a href="profile.php" class="block px-3 py-2 text-sm text-white hover:bg-white/10 rounded-sm mx-1">View &amp; Edit Profile</a>
                            <a href="public_profile.php?id=<?php echo $_SESSION[
                                "user_id"
                            ]; ?>" class="block px-3 py-2 text-sm text-white hover:bg-white/10 rounded-sm mx-1 bg-white/20 font-semibold">View Public Profile</a>
                            <div class="my-1 border-t border-white/20"></div>
                            <a href="logout.php" class="block px-3 py-2 text-sm text-red-300 hover:bg-white/10 rounded-sm mx-1">Logout</a>
                        </div>
                    <?php else: ?>
                        <a href="register.php"
                           class="text-white px-3 py-2 rounded-md text-sm font-medium whitespace-nowrap transition duration-150"
                           style="background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.3); box-shadow: 0 4px 16px 0 rgba(31,38,135,0.2);"
                           onmouseover="this.style.background='rgba(255,255,255,0.2)'"
                           onmouseout="this.style.background='rgba(255,255,255,0.1)'">Register</a>
                        <a href="login.php"
                           class="text-white px-4 py-2 rounded-md font-medium whitespace-nowrap transition duration-150"
                           style="background: rgba(16,185,129,0.25); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 1px solid rgba(16,185,129,0.5); box-shadow: 0 4px 16px 0 rgba(16,185,129,0.2);"
                           onmouseover="this.style.background='rgba(16,185,129,0.45)'"
                           onmouseout="this.style.background='rgba(16,185,129,0.25)'">Login</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 <?php echo $fromAdmin ? 'pt-8' : 'pt-24 sm:pt-28'; ?> pb-12">
        <!-- Profile Header -->
        <div class="bg-white rounded-2xl shadow-md p-6 mb-6">
            <div class="flex items-center space-x-6">
                <!-- Profile Image -->
                <div class="flex-shrink-0">
                    <?php if (!empty($user["profile_image"])): ?>
                        <img src="uploads/<?php echo htmlspecialchars(
                            $user["profile_image"],
                        ); ?>"
                             alt="<?php echo htmlspecialchars(
                                 $user["name"],
                             ); ?>"
                             class="h-24 w-24 rounded-full object-cover border-4 border-gray-200">
                    <?php else: ?>
                        <div class="h-24 w-24 rounded-full bg-[#10B981] flex items-center justify-center text-white text-3xl font-bold border-4 border-gray-200">
                            <?php echo strtoupper(
                                substr($user["name"], 0, 1),
                            ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- User Info -->
                <div class="flex-1">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2"><?php echo htmlspecialchars(
                        $user["name"],
                    ); ?></h1>

                    <!-- Trust Badge -->
                    <div class="flex items-center space-x-4 mb-3">
                        <span class="<?php echo $badge[
                            "bg_color"
                        ]; ?> <?php echo $badge[
     "text_color"
 ]; ?> <?php echo $badge[
     "border_color"
 ]; ?> px-3 py-1 rounded-full text-sm font-medium border">
                            <?php echo $badge["label"]; ?>
                        </span>
                        <span class="text-2xl font-bold text-gray-700">
                            <?php echo number_format(
                                $user["trust_score"],
                                1,
                            ); ?>/100
                        </span>
                    </div>

                    <!-- Member Since -->
                    <p class="text-gray-600 text-sm">
                        Member since <?php echo date(
                            "F j, Y",
                            strtotime($user["created_at"]),
                        ); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Total Reports</h3>
                <p class="text-3xl font-bold text-blue-600"><?php echo $stats[
                    "total_reports"
                ]; ?></p>
                <p class="text-gray-600 text-sm mt-1">Reports submitted</p>
            </div>

            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Verified Reports</h3>
                <p class="text-3xl font-bold text-green-600"><?php echo $stats[
                    "verified_reports"
                ]; ?></p>
                <p class="text-gray-600 text-sm mt-1">Peer-verified reports</p>
            </div>

            <div class="bg-white rounded-2xl shadow-md p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Rejected Reports</h3>
                <p class="text-3xl font-bold text-red-600"><?php echo $stats[
                    "rejected_reports"
                ]; ?></p>
                <p class="text-gray-600 text-sm mt-1">Rejected reports</p>
            </div>
        </div>

        <!-- Recent Verified Reports -->
        <div class="bg-white rounded-2xl shadow-md p-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Recent Verified Reports</h2>

            <?php if (empty($recentReports)): ?>
                <div class="text-center py-8">
                    <div class="text-gray-400 text-lg mb-2">No verified reports yet</div>
                    <p class="text-gray-600">This user hasn't had any reports verified by peers yet.</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentReports as $report): ?>
                        <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-semibold text-gray-800">
                                        <?php echo htmlspecialchars(
                                            $report["route_name"] ??
                                                "Unknown Route",
                                        ); ?>
                                    </h4>
                                    <p class="text-gray-600 text-sm mt-1">
                                        Crowd Level:
                                        <span class="font-medium">
                                            <?php echo htmlspecialchars(
                                                $report["crowd_level"],
                                            ); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="flex items-center space-x-2 text-sm text-gray-500">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        <span><?php echo $report[
                                            "verification_count"
                                        ]; ?> verifications</span>
                                    </div>
                                    <p class="text-gray-500 text-xs mt-1">
                                        <?php echo date(
                                            "M j, Y g:i A",
                                            strtotime($report["created_at"]),
                                        ); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-6 text-center">
            <a href="javascript:history.back()" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Go Back
            </a>
        </div>
    </div>

    <script>
        (function () {
            // Floating nav scroll effect
            const floatingNav = document.getElementById('floatingNav');
            if (floatingNav) {
                window.addEventListener('scroll', function () {
                    if (window.scrollY > 20) {
                        floatingNav.classList.add('scrolled');
                        floatingNav.style.top = '0.5rem';
                    } else {
                        floatingNav.classList.remove('scrolled');
                        floatingNav.style.top = '1rem';
                    }
                });
            }

            // Profile menu toggle
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (btn && menu) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    menu.classList.toggle('hidden');
                });
                document.addEventListener('click', function () { menu.classList.add('hidden'); });
            }

            // Mobile menu toggle
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
