<?php
/**
 * User Profile Page with Image Upload/Delete
 * Allows logged-in users to view and edit their profile details and profile picture
 */

session_start();
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
    $stmt = $pdo->prepare("SELECT id, name, email, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = 'User not found.';
    }
} catch (PDOException $e) {
    error_log("Profile load error: " . $e->getMessage());
    $error = 'Failed to load profile.';
}

// Handle profile image upload - SIMPLIFIED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug: Log everything for troubleshooting
    error_log("POST received: " . print_r($_POST, true));
    error_log("FILES received: " . print_r($_FILES, true));
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'upload_image') {
            // Check if file was uploaded
            if (!isset($_FILES['profile_image']) || $_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please select a file to upload. Error: ' . ($_FILES['profile_image']['error'] ?? 'unknown');
            } else {
                $file = $_FILES['profile_image'];
                
                // Validate file
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $error = 'Invalid file type. Please upload JPG, PNG, or GIF images.';
                } elseif ($file['size'] > $max_size) {
                    $error = 'File too large. Maximum size is 5MB.';
                } else {
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
                    $upload_path = 'uploads/' . $filename;
                    
                    // Create uploads directory if it doesn't exist
                    if (!is_dir('uploads')) {
                        mkdir('uploads', 0755, true);
                    }
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        // Delete old profile image if exists
                        if ($user['profile_image'] && file_exists('uploads/' . $user['profile_image'])) {
                            unlink('uploads/' . $user['profile_image']);
                        }
                        
                        // Update database
                        $stmt = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                        $stmt->execute([$filename, $user_id]);
                        
                        $user['profile_image'] = $filename;
                        $_SESSION['profile_image'] = $filename;  // Update session for site-wide reflection
                        $success = 'Profile picture updated successfully! Your image is now visible across the site.';
                        
                        // Debug: Log success
                        error_log("Upload successful: filename=$filename, path=$upload_path");
                    } else {
                        $error = 'Failed to upload image. Please try again.';
                        error_log("Upload failed: move_uploaded_file returned false");
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete_image') {
            // Delete profile image
            if ($user['profile_image'] && file_exists('uploads/' . $user['profile_image'])) {
                unlink('uploads/' . $user['profile_image']);
            }
            
            // Update database
            $stmt = $pdo->prepare("UPDATE users SET profile_image = NULL WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $user['profile_image'] = null;
            $_SESSION['profile_image'] = null;  // Update session for site-wide reflection
            $success = 'Profile picture removed successfully!';
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && !isset($_POST['action'])) {
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
        .profile-image-preview {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 50%;
            border: 4px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .profile-image-preview:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        .profile-image-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            background: linear-gradient(135deg, #10B981 0%, #059669 100%);
            border: 4px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .upload-btn {
            transition: all 0.3s ease;
        }
        .upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }
        .delete-btn {
            transition: all 0.3s ease;
        }
        .delete-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
        }
        .profile-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
    </style>
</head>
<body class="bg-[#F3F4F6] min-h-screen">
    <!-- Navigation Bar -->
    <nav class="fixed top-0 inset-x-0 z-30 bg-[#1E3A8A] text-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center space-x-8">
                    <a href="index.php" class="brand-font text-xl sm:text-2xl font-bold text-white whitespace-nowrap">Transport Ops</a>
                    <div class="hidden md:flex space-x-4">
                        <a href="index.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Home</a>
                        <a href="about.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">About</a>
                        <a href="user_dashboard.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Dashboard</a>
                        <a href="report.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Submit Report</a>
                        <a href="reports_map.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Reports Map</a>
                        <a href="routes.php" class="text-gray-100 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Routes</a>
                    </div>
                </div>
                <div class="relative flex items-center gap-2 sm:gap-3">
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
                            <?php if ($user['profile_image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>"
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
                        <a href="profile.php" class="block px-3 py-2 text-sm bg-gray-50 font-medium">View &amp; Edit Profile</a>
                        <div class="my-1 border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-3 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 pt-20 pb-10">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Profile Header -->
            <div class="profile-section">
                <div class="flex flex-col sm:flex-row items-center gap-6 p-6">
                    <!-- Profile Image -->
                    <div class="relative group">
                        <div class="relative">
                            <?php if ($user['profile_image']): ?>
                                <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                     alt="Profile" 
                                     class="profile-image-preview">
                                <div class="absolute inset-0 bg-black bg-opacity-0 rounded-full opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                            <?php else: ?>
                                <div class="profile-image-placeholder">
                                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                                    <div class="absolute inset-0 bg-black bg-opacity-0 rounded-full opacity-0 group-hover:opacity-10 transition-opacity duration-300"></div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Upload Button -->
                            <div class="relative">
                                <form id="upload_form" method="POST" action="" enctype="multipart/form-data" class="hidden">
                                    <input type="hidden" name="action" value="upload_image">
                                    <input type="file" 
                                           id="profile_image" 
                                           name="profile_image" 
                                           accept="image/*" 
                                           class="hidden"
                                           onchange="document.getElementById('upload_form').submit();">
                                </form>
                                <button type="button" 
                                        onclick="document.getElementById('profile_image').click();"
                                        class="absolute bottom-0 right-0 bg-blue-500 hover:bg-blue-600 text-white p-3 rounded-full shadow-lg transition-all duration-300 cursor-pointer hover:scale-105 upload-btn"
                                        title="Upload new picture">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0118.07 6H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full px-2 py-1">NEW</span>
                                </button>
                            </div>
                            <!-- Delete Button (only if image exists) -->
                            <?php if ($user['profile_image']): ?>
                                <form method="POST" onsubmit="return confirm('Remove your profile picture? This action cannot be undone.');" class="inline">
                                    <input type="hidden" name="action" value="delete_image">
                                    <button type="submit" 
                                            class="absolute top-0 right-0 bg-red-500 hover:bg-red-600 text-white p-2 rounded-full shadow-lg transition-all duration-300 cursor-pointer hover:scale-105 delete-btn"
                                            title="Remove profile picture">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- User Info -->
                    <div class="flex-1">
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">My Profile</h1>
                            <p class="text-sm text-gray-600">Manage your account information and profile picture</p>
                        </div>
                        
                        <?php if ($success): ?>
                            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 0 01-2 0l-2 2v6m0 6h6l-2 2v6m0-6h6"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($success); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h8m-4-4h.01M12 8v4m0 4h8m-4-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span><?php echo htmlspecialchars($error); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Profile Form -->
            <div class="profile-section">
                <form method="POST" action="" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Full Name
                            </label>
                            <input type="text" id="name" name="name" required
                                   value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                Email Address
                            </label>
                            <input type="email" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="new_password" class="block text-sm font-medium text-gray-700 mb-2">
                                New Password <span class="text-gray-400 text-xs">(optional)</span>
                            </label>
                            <input type="password" id="new_password" name="new_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">
                                Confirm New Password
                            </label>
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4 pt-4">
                        <button type="submit"
                                class="w-full sm:w-auto bg-blue-600 text-white px-6 py-3 rounded-lg font-medium hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Save Changes
                        </button>
                        <a href="user_dashboard.php" class="w-full sm:w-auto text-center bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-medium hover:bg-gray-300 transition-colors">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>
    <script>
        (function () {
            const btn = document.getElementById('profileMenuButton');
            const menu = document.getElementById('profileMenu');
            if (!btn || !menu) return;
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('hidden');
            });
            document.addEventListener('click', function () { menu.classList.add('hidden'); });
        })();
    </script>
</body>
</html>
