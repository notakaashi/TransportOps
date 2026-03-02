<?php
/**
 * Comprehensive Profile Image Test
 * Tests all aspects of profile image functionality
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <style>
        .brand-font {
            font-family: 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            letter-spacing: 0.02em;
        }
        .test-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .test-result {
            padding: 1rem;
            border-radius: 8px;
            font-weight: 500;
        }
        .success { background: #dcfce7; color: #166534; border-color: #bbf7d0; }
        .error { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .info { background: #dbeafe; color: #1e40af; border-color: #60a5fa; }
    </style>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">📸 Profile Image System Test</h1>
        
        <?php if ($user): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">👤 User Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="test-section">
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Basic Info</h3>
                        <div class="space-y-2">
                            <p><strong>User ID:</strong> <?php echo $user['id']; ?></p>
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                    
                    <div class="test-section">
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Profile Image Status</h3>
                        <div class="space-y-2">
                            <p><strong>Has Image:</strong> 
                                <?php echo $user['profile_image'] ? 
                                    '<span class="test-result success">✅ YES</span>' : 
                                    '<span class="test-result error">❌ NO</span>'; ?>
                            </p>
                            <?php if ($user['profile_image']): ?>
                                <p><strong>Filename:</strong> <?php echo htmlspecialchars($user['profile_image']); ?></p>
                                <p><strong>File Path:</strong> uploads/<?php echo htmlspecialchars($user['profile_image']); ?></p>
                                <p><strong>File Exists:</strong> 
                                    <?php echo file_exists('uploads/' . $user['profile_image']) ? 
                                        '<span class="test-result success">✅ YES</span>' : 
                                        '<span class="test-result error">❌ NO</span>'; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="test-section">
                        <h3 class="text-lg font-medium text-gray-700 mb-2">Navigation Display Test</h3>
                        <div class="space-y-2">
                            <p><strong>Current Profile Image:</strong></p>
                            <div class="w-32 h-32 mx-auto border-2 border-gray-300 rounded-lg overflow-hidden bg-gray-50">
                                <?php if ($user['profile_image']): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                         alt="Profile" 
                                         class="w-full h-full object-cover">
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                        <span class="text-4xl font-bold text-gray-400">👤</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-gray-600 mt-2">This is how your profile image appears in navigation bars</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🧪 Upload Test</h2>
                <p class="text-gray-600 mb-4">Test the profile image upload functionality</p>
                
                <div class="test-section">
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Upload Form Test</h3>
                    <form method="POST" enctype="multipart/form-data" class="space-y-4">
                        <input type="hidden" name="action" value="upload_image">
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Select Profile Image (JPG, PNG, GIF - Max 5MB)
                            </label>
                            <input type="file" 
                                   name="profile_image" 
                                   accept="image/*" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex gap-4">
                            <button type="submit" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">
                                📤 Upload Image
                            </button>
                            
                            <?php if ($user['profile_image']): ?>
                                <button type="submit" 
                                        name="action" 
                                        value="delete_image"
                                        class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 font-medium">
                                🗑️ Delete Current Image
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    echo '<div class="test-section">';
                    echo '<h3 class="text-lg font-medium text-gray-700 mb-2">📊 POST Data Received</h3>';
                    echo '<pre class="bg-gray-100 p-4 rounded text-xs overflow-auto">';
                    echo 'FILES: ' . print_r($_FILES, true);
                    echo 'POST: ' . print_r($_POST, true);
                    echo '</pre>';
                    echo '</div>';
                }
                ?>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">🔗 System Integration</h2>
                <div class="test-section">
                    <h3 class="text-lg font-medium text-gray-700 mb-2">Cross-Page Consistency</h3>
                    <div class="space-y-2">
                        <p><strong>Profile Page:</strong> 
                            <a href="profile.php" class="text-blue-600 hover:text-blue-800 underline">Go to Profile Page →</a>
                        </p>
                        <p><strong>User Dashboard:</strong> 
                            <a href="user_dashboard.php" class="text-blue-600 hover:text-blue-800 underline">Go to User Dashboard →</a>
                        </p>
                        <p><strong>Routes Page:</strong> 
                            <a href="routes.php" class="text-blue-600 hover:text-blue-800 underline">Go to Routes Page →</a>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="mt-8 text-center">
                <a href="user_dashboard.php" class="bg-gray-600 text-white px-6 py-3 rounded-lg hover:bg-gray-700 font-medium">
                    ← Back to Dashboard
                </a>
            </div>
        <?php else: ?>
            <div class="bg-red-100 border border-red-400 text-red-700 p-6 rounded-lg">
                <p class="text-center">❌ User not found in database.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
