<?php
// =======================================================
// ✅ BASIC CONFIGURATION
// =======================================================
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 'Off');
include 'connect.php';

// Check DB connection
if (!$conn) {
    error_log("DB connection failed: " . mysqli_connect_error(), 3, "error_log.txt");
    die("<div style='padding:12px;background:#dc3545;color:#fff;border-radius:8px;text-align:center;'>
        ❌ Database connection error. Please try again later.
    </div>");
}

// Sanitize GET parameter
$doctor_name = isset($_GET['name']) ? htmlspecialchars(urldecode($_GET['name']), ENT_QUOTES, 'UTF-8') : "Unknown Doctor";

// =======================================================
// ✅ HANDLE FORM SUBMISSION
// =======================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate and sanitize input
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($name)) {
        die("<div style='color:red;text-align:center;'>❌ Name is required.</div>");
    }

    // Limit input length
    $name = substr($name, 0, 100);
    $description = substr($description, 0, 500);

    $file_path = null;

    // Handle File Upload securely
    if (!empty($_FILES['file']['name'])) {

        // Ensure uploads directory exists
        $upload_dir = __DIR__ . '/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

        $file_tmp = $_FILES['file']['tmp_name'];
        $file_name = basename($_FILES['file']['name']);

        // Only allow certain file types (prevent PHP uploads)
        $allowed_ext = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'mp4', 'webm'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            die("<div style='color:red;text-align:center;'>❌ Invalid file type.</div>");
        }

        // Generate unique file name to avoid overwriting
        $file_name_safe = uniqid() . '_' . preg_replace("/[^a-zA-Z0-9_\-\.]/", "_", $file_name);
        $file_path = 'uploads/' . $file_name_safe;

        if (!move_uploaded_file($file_tmp, $file_path)) {
            die("<div style='color:red;text-align:center;'>❌ Failed to upload file.</div>");
        }
    }

    // Use prepared statements to prevent SQL Injection
    $stmt = $conn->prepare("INSERT INTO files_lectures (name, description, file_path, doctor_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $description, $file_path, $doctor_name);

    if ($stmt->execute()) {
        echo "<div style='color:green;text-align:center;'>✅ Course added successfully!</div>";
    } else {
        error_log("DB Insert Error: " . $stmt->error, 3, "logs.txt");
        echo "<div style='color:red;text-align:center;'>❌ Failed to add course. Please try again.</div>";
    }

    $stmt->close();
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add New Lecture</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://apis.google.com/js/api.js"></script>
</head>

<body class="flex justify-center items-center min-h-screen bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500">
<section class="bg-white shadow-2xl rounded-2xl p-8 w-full max-w-lg mx-auto">
    <header class="mb-6 text-center">
        <h1 class="text-3xl font-extrabold text-gray-800">📚 Add New Lecture</h1>
        <p class="text-gray-600 mt-1">Fill out the details below to add a new lecture</p>
    </header>

    <form method="POST" enctype="multipart/form-data" onsubmit="handleFileUpload(event)" class="space-y-6" aria-label="Add New Lecture Form">
        <fieldset class="space-y-4 border-0 p-0">
            <legend class="sr-only">Lecture Details</legend>

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">📖 Lecture Name:</label>
                <input type="text" name="name" id="name" required
                    class="block w-full p-3 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500" 
                    placeholder="Enter lecture name" aria-required="true">
            </div>

            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">📝 Lecture Description:</label>
                <textarea name="description" id="description" required
                    class="block w-full p-3 border-2 border-gray-300 rounded-lg shadow-md focus:ring-2 focus:ring-blue-500 resize-y"
                    placeholder="Enter lecture description" rows="4" aria-required="true"></textarea>
            </div>

            <div class="text-center">
                <label for="file" class="sr-only">Lecture File</label>
                <button type="button" onclick="triggerFileInput()"
                    class="block w-full py-3 px-5 bg-blue-600 text-white font-semibold rounded-lg shadow-lg hover:bg-blue-700 transition">
                    📤 Upload Lecture File
                </button>
                <input type="file" name="file" id="file" class="hidden" onchange="handleFileSelection()" aria-describedby="file-info">
                <div id="file-info" class="mt-2 text-gray-600 hidden">
                    <span id="file-name"></span>
                    <button type="button" onclick="removeFile()" class="ml-2 text-red-500 font-semibold hover:text-red-700">✖ Remove File</button>
                </div>
            </div>
        </fieldset>

        <button type="submit"
            class="w-full py-3 px-5 bg-purple-600 text-white font-bold rounded-lg shadow-lg hover:bg-purple-700 transition">
            ➕ Add Lecture
        </button>
    </form>

    <section aria-label="Upload Progress" class="mt-6">
        <div class="relative w-full bg-gray-300 rounded-full h-4 overflow-hidden shadow-lg">
            <div id="progress-bar" class="h-4 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-full transition-all duration-500 ease-in-out relative" style="width: 0%;">
                <div class="absolute inset-0 bg-white opacity-20 blur-lg animate-pulse"></div>
            </div>
        </div>
        <p id="progress-text" class="text-center mt-3 text-gray-800 font-bold text-lg" aria-live="polite"></p>
    </section>
</section>


<script>

function updateProgress(event) {
    if (event.lengthComputable) {
        let percent = (event.loaded / event.total) * 100;
        const progressBar = document.getElementById('progress-bar');
        progressBar.style.width = percent + '%';
        progressBar.textContent = Math.round(percent) + '%';
    }
}


function handleFileUpload(event) {
    event.preventDefault();
    const form = document.querySelector('form');
    const data = new FormData(form);
    const xhr = new XMLHttpRequest();

    xhr.open('POST', form.action, true);
    xhr.upload.addEventListener('progress', updateProgress);
    xhr.onload = async function() {
        if (xhr.status === 200) {
            const file = document.getElementById('file').files[0];

            Swal.fire({ icon: 'success', title: 'File uploaded successfully!' });
        } else {
            Swal.fire({ icon: 'error', title: 'Upload Failed', text: 'An error occurred while uploading the file.' });
        }
    };
    xhr.send(data);
}

function triggerFileInput() {
    document.getElementById('file').click();
}
function handleFileSelection() {
    const fileInput = document.getElementById('file');
    const file = fileInput.files[0];
    const fileInfo = document.getElementById('file-info');
    const fileNameSpan = document.getElementById('file-name');

    if (!file) return;

    const fileTypes = {
        'pdf': '📄',
        'docx': '📝',
        'pptx': '📊',
        'mp4': '🎬',
        'jpg': '🖼️',
        'png': '🖼️'
    };

    const ext = file.name.split('.').pop().toLowerCase();
    const icon = fileTypes[ext] || '📁';
    
    fileNameSpan.textContent = `${icon} ${file.name}`;
    fileInfo.classList.remove('hidden');
    
}


function removeFile() {
    const fileInput = document.getElementById('file');
    fileInput.value = '';
    document.getElementById('file-info').classList.add('hidden');
}

</script>
</body>
</html>
