<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: admin_login.php');
    exit;
}
if ($_SESSION['role'] !== 'Admin') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$edit_user_id = null;
$edit_user = null;

// Handle activation/deactivation
if (isset($_GET['toggle_status']) && is_numeric($_GET['toggle_status'])) {
    $toggle_id = (int)$_GET['toggle_status'];
    if ($toggle_id === $_SESSION['user_id']) {
        $error = 'You cannot change your own account status.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$toggle_id]);
            $success = 'User status updated successfully.';
        } catch (PDOException $e) {
            error_log("Toggle user status error: " . $e->getMessage());
            $error = 'Failed to update user status.';
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    if ($delete_id === $_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$delete_id]);
            $success = 'User deleted successfully.';
        } catch (PDOException $e) {
            error_log("Delete user error: " . $e->getMessage());
            $error = 'Failed to delete user.';
        }
    }
}

// Handle edit - load user data
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_user_id = (int)$_GET['edit'];
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ?");
        $stmt->execute([$edit_user_id]);
        $edit_user = $stmt->fetch();
        if (!$edit_user) {
            $error = 'User not found.';
            $edit_user_id = null;
        }
    } catch (PDOException $e) {
        error_log("Load user error: " . $e->getMessage());
        $error = 'Failed to load user data.';
        $edit_user_id = null;
    }
}

// Handle form submission (add or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'Commuter';
    $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : null;
    
    if (empty($name) || empty($email)) {
        $error = 'Name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (!in_array($role, ['Admin', 'Commuter'])) {
        $error = 'Invalid role selected.';
    } else {
        try {
            $pdo = getDBConnection();
            
            if ($user_id) {
                // Update existing user
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_stmt->execute([$email, $user_id]);
                if ($check_stmt->fetch()) {
                    $error = 'Email already registered to another user.';
                } else {
                    if (!empty($password)) {
                        if (strlen($password) < 6) {
                            $error = 'Password must be at least 6 characters long.';
                        } elseif ($password !== $confirm_password) {
                            $error = 'Passwords do not match.';
                        } else {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ?, role = ? WHERE id = ?");
                            $stmt->execute([$name, $email, $hashed_password, $role, $user_id]);
                            $success = 'User updated successfully!';
                            $edit_user_id = null;
                            $edit_user = null;
                        }
                    } else {
                        // Update without changing password
                        $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                        $check_stmt->execute([$email, $user_id]);
                        if ($check_stmt->fetch()) {
                            $error = 'Email already registered to another user.';
                        } else {
                            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ? WHERE id = ?");
                            $stmt->execute([$name, $email, $role, $user_id]);
                            $success = 'User updated successfully!';
                            $edit_user_id = null;
                            $edit_user = null;
                        }
                    }
                }
            } else {
                // Add new user
                if (empty($password)) {
                    $error = 'Password is required for new users.';
                } elseif (strlen($password) < 6) {
                    $error = 'Password must be at least 6 characters long.';
                } elseif ($password !== $confirm_password) {
                    $error = 'Passwords do not match.';
                } else {
                    $check_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->execute([$email]);
                    if ($check_stmt->fetch()) {
                        $error = 'Email already registered.';
                    } else {
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                        $stmt->execute([$name, $email, $hashed_password, $role]);
                        $success = 'User created successfully!';
                        $_POST = [];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("User management error: " . $e->getMessage());
            $error = 'Operation failed. Please try again.';
        }
    }
}

// Fetch all users
try {
    $pdo = getDBConnection();
    $stmt = $pdo->query("SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Fetch users error: " . $e->getMessage());
    $users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-gray-50 to-blue-50">
    <div class="flex flex-col md:flex-row min-h-screen">
        <aside class="w-full md:w-64 bg-gradient-to-b from-gray-800 to-gray-900 text-white flex flex-col shadow-2xl">
            <div class="px-4 py-4 sm:p-6 flex-shrink-0 border-b border-gray-700 md:border-b-0">
                <div id="adminNavToggle" class="flex items-center justify-between md:justify-start mb-4 md:mb-8 cursor-pointer md:cursor-default">
                    <div class="bg-blue-600 p-2 rounded-lg mr-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-bold">Transport Ops</h1>
                    <svg class="w-5 h-5 text-gray-300 md:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <nav id="adminNavLinks" class="space-y-1 md:space-y-2 text-sm sm:text-base hidden md:block">
                    <a href="admin_dashboard.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Fleet Overview
                    </a>
                    <a href="admin_reports.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 012-2h6m-4-4l4 4-4 4"></path>
                        </svg>
                        Reports
                    </a>
                    <a href="route_status.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Route Status
                    </a>
                    <a href="manage_routes.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Manage Routes
                    </a>
                    <a href="heatmap.php" 
                       class="flex items-center px-4 py-3 hover:bg-gray-700 rounded-lg transition duration-150 group">
                        <svg class="w-5 h-5 mr-3 group-hover:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Crowdsourcing Heatmap
                    </a>
                    <a href="user_management.php" 
                       class="flex items-center px-4 py-3 bg-blue-600 rounded-lg hover:bg-blue-700 transition duration-150 shadow-lg">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
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
                <a href="logout.php" 
                   class="block w-full text-center bg-gradient-to-r from-red-600 to-red-700 text-white py-2 px-4 rounded-md hover:from-red-700 hover:to-red-800 transition duration-150 font-medium shadow-lg">
                    Logout
                </a>
            </div>
        </aside>

        <main class="flex-1 w-full overflow-y-auto">
            <div class="p-4 sm:p-6 lg:p-8">
                <div class="mb-8">
                    <h2 class="text-3xl font-bold text-gray-800">User Management</h2>
                    <p class="text-gray-600 mt-2">Manage system users - add, edit, or delete user accounts</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded mb-6 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-lg shadow-md overflow-hidden">
                            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                                <h3 class="text-xl font-semibold text-gray-800">All Users (<?php echo count($users); ?>)</h3>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="6" class="px-6 py-4 text-center text-gray-500">No users found.</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($user['name']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-600">
                                                            <?php echo htmlspecialchars($user['email']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php
                                                        $roleColors = [
                                                            'Admin' => 'bg-red-100 text-red-800 border-red-300',
                                                            'Commuter' => 'bg-gray-100 text-gray-800 border-gray-300'
                                                        ];
                                                        $roleColor = $roleColors[$user['role']] ?? 'bg-gray-100 text-gray-800 border-gray-300';
                                                        ?>
                                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo $roleColor; ?>">
                                                            <?php echo htmlspecialchars($user['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($user['is_active']): ?>
                                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 border-green-300">
                                                                Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 border-red-300">
                                                                Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <a href="?edit=<?php echo $user['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-2">Edit</a>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <a href="?toggle_status=<?php echo $user['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')"
                                                               class="text-<?php echo $user['is_active'] ? 'orange' : 'green'; ?>-600 hover:text-<?php echo $user['is_active'] ? 'orange' : 'green'; ?>-900 mr-2">
                                                                <?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                            </a>
                                                            <a href="?delete=<?php echo $user['id']; ?>" 
                                                               onclick="return confirm('Are you sure you want to delete this user?')"
                                                               class="text-red-600 hover:text-red-900">Delete</a>
                                                        <?php else: ?>
                                                            <span class="text-gray-400 mr-2">Deactivate</span>
                                                            <span class="text-gray-400">Delete</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-1">
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <h3 class="text-xl font-semibold text-gray-800 mb-4">
                                <?php echo $edit_user ? 'Edit User' : 'Add New User'; ?>
                            </h3>
                            
                            <form method="POST" action="" class="space-y-4">
                                <?php if ($edit_user): ?>
                                    <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                                <?php endif; ?>
                                
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                                    <input type="text" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($edit_user['name'] ?? ($_POST['name'] ?? '')); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <input type="email" id="email" name="email" required 
                                           value="<?php echo htmlspecialchars($edit_user['email'] ?? ($_POST['email'] ?? '')); ?>"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                    <select id="role" name="role" required
                                            class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Commuter" <?php echo (($edit_user['role'] ?? ($_POST['role'] ?? 'Commuter')) === 'Commuter') ? 'selected' : ''; ?>>Commuter</option>
                                        <option value="Admin" <?php echo (($edit_user['role'] ?? ($_POST['role'] ?? '')) === 'Admin') ? 'selected' : ''; ?>>Admin</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                                        Password <?php echo $edit_user ? '(leave blank to keep current)' : ''; ?>
                                    </label>
                                    <div class="relative">
                                        <input type="password" id="password" name="password" 
                                               <?php echo $edit_user ? '' : 'required'; ?> minlength="6"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <button type="button" onclick="togglePassword('password')" 
                                                class="absolute right-3 top-2 text-gray-500 hover:text-gray-700">
                                            <svg id="eye-icon-password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            <svg id="eye-off-icon-password" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if (!$edit_user): ?>
                                <div>
                                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                                    <div class="relative">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <button type="button" onclick="togglePassword('confirm_password')" 
                                                class="absolute right-3 top-2 text-gray-500 hover:text-gray-700">
                                            <svg id="eye-icon-confirm_password" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            <svg id="eye-off-icon-confirm_password" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.066 5.717m0 0L21 21"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex space-x-2 pt-2">
                                    <button type="submit" 
                                            class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 transition duration-150 font-medium">
                                        <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                                    </button>
                                    <?php if ($edit_user): ?>
                                        <a href="user_management.php" 
                                           class="bg-gray-200 text-gray-700 py-2 px-4 rounded-md hover:bg-gray-300 transition duration-150 font-medium">
                                            Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const eyeIcon = document.getElementById('eye-icon-' + fieldId);
            const eyeOffIcon = document.getElementById('eye-off-icon-' + fieldId);
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                eyeIcon.classList.add('hidden');
                eyeOffIcon.classList.remove('hidden');
            } else {
                passwordField.type = 'password';
                eyeIcon.classList.remove('hidden');
                eyeOffIcon.classList.add('hidden');
            }
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
