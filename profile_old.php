<?php
/**
 * User Profile Page
 * Allows logged-in users to view and edit their profile details
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

try {
    $pdo = getDBConnection();

    // Load current user
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = 'User not found.';
    }
} catch (PDOException $e) {
    error_log("Profile load error: " . $e->getMessage());
    $error = 'Failed to load profile.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '') {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($new_password !== '' && strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New password and confirmation do not match.';
    } else {
        try {
            // Check email uniqueness (excluding current user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $error = 'That email is already in use.';
            } else {
                // Build update query
                if ($new_password !== '') {
                    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $hashed, $user_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $user_id]);
                }

                // Update session
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                $success = 'Profile updated successfully.';

                // Refresh local user data
                $user['name'] = $name;
                $user['email'] = $email;
            }
        } catch (PDOException $e) {
            error_log("Profile update error: " . $e->getMessage());
            $error = 'Failed to update profile. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <!-- Navigation Bar -->
    <nav class="bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" id="brandLink" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                        <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                    </div>
                    <div id="mobileMenu" class="md:hidden hidden absolute top-16 left-0 right-0 bg-[#1E3A8A] text-white flex flex-col space-y-1 px-4 py-2 z-20">
                        <a href="user_dashboard.php" class="block px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="routes.php" class="block px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                        <a href="report.php" class="block px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                    </div>
                </div>
                <a href="logout.php" class="bg-red-600 text-white px-3 py-1.5 rounded-md hover:bg-red-700 transition duration-150 font-medium text-sm flex items-center">
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="bg-white rounded-2xl shadow-md p-6 sm:p-8">
            <div class="flex items-center gap-4 mb-6">
                <div class="h-14 w-14 rounded-full bg-[#10B981] flex items-center justify-center text-white text-2xl font-semibold">
                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-semibold text-gray-900">My Profile</h1>
                    <p class="text-sm text-gray-500">Manage your account information</p>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                    <input type="text" id="name" name="name" required
                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#10B981]">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#10B981]">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">
                            New Password <span class="text-gray-400 text-xs">(optional)</span>
                        </label>
                        <input type="password" id="new_password" name="new_password"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#10B981]">
                    </div>
                    <div>
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#10B981]">
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit"
                            class="inline-flex items-center justify-center bg-[#10B981] text-white px-5 py-2.5 rounded-lg text-sm font-medium hover:bg-[#059669] focus:outline-none focus:ring-2 focus:ring-[#10B981] focus:ring-offset-2">
                        Save Changes
                    </button>
                    <a href="user_dashboard.php" class="text-sm text-gray-600 hover:text-gray-800">
                        Cancel and go back
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

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

