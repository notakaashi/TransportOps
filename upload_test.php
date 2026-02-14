<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Profile Image Upload Test</h1>
        
        <div class="bg-white rounded-lg shadow-md p-6">
            <form method="POST" action="profile.php" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_image">
                
                <div>
                    <label for="test_upload" class="block text-sm font-medium text-gray-700 mb-2">
                        Select Profile Image (JPG, PNG, GIF - Max 5MB)
                    </label>
                    <input type="file" 
                           id="test_upload" 
                           name="profile_image" 
                           accept="image/*" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                
                <div class="flex items-center gap-4">
                    <button type="submit" 
                            class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 font-medium">
                        Upload Image
                    </button>
                    <a href="profile.php" class="text-gray-600 hover:text-gray-800">
                        Back to Profile
                    </a>
                </div>
            </form>
        </div>
        
        <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-800 mb-2">Instructions:</h3>
            <ol class="list-decimal list-inside space-y-2 text-sm text-blue-700">
                <li>Click "Choose File" or drag and drop an image</li>
                <li>Select a JPG, PNG, or GIF file (max 5MB)</li>
                <li>Click "Upload Image" to upload</li>
                <li>You'll be redirected back to your profile page</li>
            </ol>
        </div>
    </div>
</body>
</html>
