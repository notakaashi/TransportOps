<?php
/**
 * Test Profile Image Functionality
 */
require_once 'auth_helper.php';
secureSessionStart();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get user profile info
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, email, profile_image FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $user = null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Image Test - Transport Operations System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-6">Profile Image Test</h1>
        
        <?php if ($user): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Current User Info</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-medium text-gray-700 mb-2">User Details</h3>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                    </div>
                    <div>
                        <h3 class="font-medium text-gray-700 mb-2">Profile Image Status</h3>
                        <?php if ($user['profile_image']): ?>
                            <div class="space-y-2">
                                <p><strong>✅ Profile Image:</strong> Uploaded</p>
                                <p><strong>Filename:</strong> <?php echo htmlspecialchars($user['profile_image']); ?></p>
                                <div class="flex items-center space-x-4">
                                    <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         alt="Profile" 
                                         class="w-20 h-20 rounded-full object-cover border-4 border-gray-200">
                                    <div>
                                        <p class="text-sm text-gray-600">File exists:</p>
                                        <p class="text-sm font-mono">
                                            <?php echo file_exists('uploads/' . $user['profile_image']) ? '✅ YES' : '❌ NO'; ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="space-y-2">
                                <p><strong>❌ Profile Image:</strong> Not uploaded</p>
                                <p class="text-sm text-gray-600">User has not uploaded a profile picture</p>
                                <div class="w-20 h-20 rounded-full bg-gray-300 flex items-center justify-center text-white text-2xl font-bold">
                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Test Navigation Display</h2>
                <div class="flex items-center space-x-4 p-4 bg-gray-50 rounded-lg">
                    <div class="text-sm">
                        <p class="font-medium">Navigation Preview:</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if ($user['profile_image']): ?>
                            <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                 alt="Profile" 
                                 class="h-8 w-8 rounded-full object-cover border-2 border-white">
                        <?php else: ?>
                            <div class="h-8 w-8 rounded-full bg-[#10B981] flex items-center justify-center text-white text-sm font-semibold">
                                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 flex space-x-4">
                <a href="profile.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Go to Profile Page
                </a>
                <a href="user_dashboard.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 p-4 rounded-lg">
                <p>User not found in database.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
