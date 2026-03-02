<?php
/**
 * Admin Login Page
 * Admin-only authentication. Only users with Admin role can successfully log in here.
 */

require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

// Redirect if already logged in as admin
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'Admin') {
    header('Location: admin_dashboard.php');
    exit;
}

// If logged in as non-admin, redirect to user area
if (isset($_SESSION['user_id'])) {
    header('Location: user_dashboard.php');
    exit;
}

$error = '';

// Check for deactivated account error
if (isset($_GET['error']) && $_GET['error'] === 'deactivated') {
    $error = 'Your account has been deactivated. Please contact an administrator.';
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, is_active, profile_image FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([trim($email)]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Admin-only: reject non-admin users
                if ($user['role'] !== 'Admin') {
                    $error = 'This login page is for administrators only. Please use the main login page.';
                } elseif (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact an administrator.';
                } else {
                    // Regenerate session to prevent fixation and create fresh session
                    regenerateSession();
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['profile_image'] = $user['profile_image'];
                    header('Location: admin_dashboard.php');
                    exit;
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            error_log("Admin login error: " . $e->getMessage());
            $error = 'Login failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600&display=swap" rel="stylesheet">
    <style>
        .auth-title { font-family: 'Poppins', system-ui, sans-serif; letter-spacing: 0.02em; }
    </style>
</head>
<body class="bg-gray-800 min-h-screen flex items-center justify-center px-4">
    <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-md w-full max-w-md">
        <h2 class="auth-title text-2xl font-semibold text-gray-900 mb-2 text-center">Admin Login</h2>
        <p class="text-sm text-gray-500 text-center mb-6">Administrators only</p>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" class="space-y-4">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" id="email" name="email" required 
                       value="<?php echo htmlspecialchars($email ?? ''); ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600">
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600">
                    <button type="button" onclick="togglePassword()" class="absolute right-3 top-2 text-gray-500 hover:text-gray-700">
                        <svg id="eye-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <svg id="eye-off-icon" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="w-full bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 transition duration-150 font-medium min-h-[48px]">
                Admin Login
            </button>
        </form>
        
        <p class="mt-4 text-center text-sm text-gray-600">
            Not an admin? <a href="login.php" class="text-blue-600 hover:text-blue-800 font-medium">User login</a>
        </p>
    </div>
    
    <script>
        function togglePassword() {
            const pw = document.getElementById('password');
            const eye = document.getElementById('eye-icon');
            const off = document.getElementById('eye-off-icon');
            if (pw.type === 'password') { pw.type = 'text'; eye.classList.add('hidden'); off.classList.remove('hidden'); }
            else { pw.type = 'password'; eye.classList.remove('hidden'); off.classList.add('hidden'); }
        }
    </script>
</body>
</html>
