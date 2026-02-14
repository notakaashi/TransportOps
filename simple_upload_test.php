<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Upload Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Simple Profile Image Upload Test</h1>
        
        <?php
        session_start();
        require_once 'db.php';
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_image') {
            $user_id = $_SESSION['user_id'];
            
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                
                echo "<div class='bg-green-100 p-4 rounded mb-4'>";
                echo "<h3 class='font-bold text-green-800'>✅ File Received Successfully!</h3>";
                echo "<p><strong>Original Name:</strong> " . htmlspecialchars($file['name']) . "</p>";
                echo "<p><strong>Size:</strong> " . number_format($file['size'] / 1024 / 1024, 2) . " MB</p>";
                echo "<p><strong>Type:</strong> " . htmlspecialchars($file['type']) . "</p>";
                echo "<p><strong>Temp Location:</strong> " . htmlspecialchars($file['tmp_name']) . "</p>";
                echo "</div>";
                
                // Try to move it
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'test_' . time() . '.' . $extension;
                $upload_path = 'uploads/' . $filename;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0755, true);
                }
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    echo "<div class='bg-blue-100 p-4 rounded mb-4'>";
                    echo "<h3 class='font-bold text-blue-800'>✅ File Moved Successfully!</h3>";
                    echo "<p><strong>New Filename:</strong> " . htmlspecialchars($filename) . "</p>";
                    echo "<p><strong>Upload Path:</strong> " . htmlspecialchars($upload_path) . "</p>";
                    echo "<p><strong>File Exists:</strong> " . (file_exists($upload_path) ? 'YES' : 'NO') . "</p>";
                    echo "</div>";
                } else {
                    echo "<div class='bg-red-100 p-4 rounded mb-4'>";
                    echo "<h3 class='font-bold text-red-800'>❌ Failed to Move File!</h3>";
                    echo "<p><strong>Error:</strong> move_uploaded_file() failed</p>";
                    echo "</div>";
                }
            } else {
                echo "<div class='bg-red-100 p-4 rounded mb-4'>";
                echo "<h3 class='font-bold text-red-800'>❌ Upload Error!</h3>";
                echo "<p><strong>Error Code:</strong> " . ($_FILES['profile_image']['error'] ?? 'unknown') . "</p>";
                echo "<p><strong>FILES Array:</strong> <pre>" . print_r($_FILES, true) . "</pre></p>";
                echo "</div>";
            }
        }
        ?>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold text-gray-800 mb-4">Test Upload</h2>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_image">
                
                <div>
                    <label for="file_input" class="block text-sm font-medium text-gray-700 mb-2">
                        Choose Image File:
                    </label>
                    <input type="file" 
                           id="file_input" 
                           name="profile_image" 
                           accept="image/*" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div>
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">
                        Upload File
                    </button>
                </div>
            </form>
            
            <div class="mt-6 space-y-2">
                <h3 class="font-semibold text-gray-800">Server Info:</h3>
                <p><strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?> bytes</p>
                <p><strong>Post Max Size:</strong> <?php echo ini_get('post_max_size'); ?> bytes</p>
                <p><strong>Uploads Directory:</strong> <?php echo is_dir('uploads') ? 'EXISTS' : 'MISSING'; ?></p>
                <p><strong>Uploads Writable:</strong> <?php echo is_writable('uploads') ? 'YES' : 'NO'; ?></p>
            </div>
        </div>
        
        <div class="mt-6">
            <a href="profile.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                ← Back to Profile
            </a>
        </div>
    </div>
</body>
</html>
