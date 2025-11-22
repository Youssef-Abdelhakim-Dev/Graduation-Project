<?php
/********************************************************************
 * ADVANCED COURSE MANAGER BACKEND
 ********************************************************************/

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/error.log");
ob_start();

use WebSocket\Client;

require __DIR__ . '/vendor/autoload.php';
require 'connect.php';

// Helper Functions
function safe($v) {
    return htmlspecialchars(trim($v), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($arr) {
    ob_clean();
    header("Content-Type: application/json");
    echo json_encode($arr, JSON_UNESCAPED_SLASHES);
    exit;
}

function generateFileName($original) {
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    return hash('sha256', uniqid() . $original . microtime(true)) . "." . $ext;
}

// Course Manager
class CourseManager {
    private $conn;
    private $rootUpload = "uploads/";

    public function __construct($db) {
        $this->conn = $db;
        if (!is_dir($this->rootUpload)) mkdir($this->rootUpload, 0777, true);
        if (!class_exists('finfo')) {
            throw new Exception("Fileinfo extension is required. Enable it in php.ini.");
        }
    }

    private function buildUploadPath($courseId = null) {
        $year = date("Y");
        $month = date("m");
        $path = "{$this->rootUpload}{$year}/{$month}/";
        if (!is_dir($path)) mkdir($path, 0777, true);
        if ($courseId) {
            $path .= "course_{$courseId}/";
            if (!is_dir($path)) mkdir($path, 0777, true);
        }
        return $path;
    }

    public function uploadFiles($files, $allowedMime, $maxSize, $courseId) {
        if (!$files || !isset($files['tmp_name'])) return [];

        $uploaded = [];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $uploadDir = $this->buildUploadPath($courseId);

        for ($i = 0; $i < count($files['tmp_name']); $i++) {
            $tmp = $files['tmp_name'][$i] ?? '';
            $original = $files['name'][$i] ?? '';

            if (empty($tmp) || !file_exists($tmp)) continue;

            $mime = $finfo->file($tmp);
            if (!isset($allowedMime[$mime])) continue;
            if (filesize($tmp) > $maxSize) continue;

            $newName = generateFileName($original);
            $target = $uploadDir . $newName;

            if (move_uploaded_file($tmp, $target)) {
                $uploaded[] = [
                    "original" => $original,
                    "saved"    => $newName,
                    "path"     => $target,
                    "mime"     => $mime
                ];
                file_put_contents("logs.txt",
                    "[".date("Y-m-d H:i:s")."] Uploaded: $target ($mime)\n",
                    FILE_APPEND
                );
            }
        }
        return $uploaded;
    }

    public function addCourse($data) {
        $sql = "INSERT INTO Courses (title, department, description, price, start_date, doctor_name, image)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;
        $stmt->bind_param(
            "sssssss",
            $data["title"],
            $data["department"],
            $data["description"],
            $data["price"],
            $data["start_date"],
            $data["doctor_name"],
            $data["image"]
        );
        if (!$stmt->execute()) return false;
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    public function saveVideos($courseId, $uploaded) {
        if (!$uploaded) return;
        $sql = "INSERT INTO course_videos (course_id, filename, filepath, mime_type)
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) throw new Exception("Failed to prepare video insert query");

        foreach ($uploaded as $file) {
            $stmt->bind_param(
                "isss",
                $courseId,
                $file['original'],
                $file['path'],
                $file['mime']
            );
            $stmt->execute();
        }
        $stmt->close();
    }

    public function sendWebSocket($type, $payload) {
        $msg = json_encode(["type"=>$type, "data"=>$payload]);
        $socket = stream_socket_client("tcp://127.0.0.1:8080", $errno, $errstr, 2);
        if ($socket) {
            fwrite($socket, $msg);
            fclose($socket);
        } else {
            error_log("WebSocket connect error: $errstr ($errno)");
        }
    }
    
    public function getCourses() {
        $res = $this->conn->query("SELECT * FROM Courses ORDER BY id DESC");
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    }
}

// TinyMCE uploader
if (isset($_GET['tinymce_upload'])) {
    if (!isset($_FILES['file'])) jsonResponse(["error"=>"No file uploaded"]);
    $manager = new CourseManager($conn);
    $file = $_FILES['file'];
    $uploaded = $manager->uploadFiles(
        ["tmp_name"=>[$file['tmp_name']], "name"=>[$file['name']]],
        ["image/jpeg"=>true,"image/png"=>true,"image/webp"=>true],
        5*1024*1024,
        "tinymce"
    );
    if (!$uploaded) jsonResponse(["error"=>"Upload failed"]);
    jsonResponse(["location"=>"/".$uploaded[0]["path"]]);
}

// Main AJAX handler
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $manager = new CourseManager($conn);

        $data = [
            "title"       => safe($_POST["name"] ?? ""),
            "department"  => safe($_POST["department"] ?? ""),
            "description" => $_POST["description"] ?? "",
            "price"       => safe($_POST["price"] ?? ""),
            "start_date"  => safe($_POST["start_date"] ?? ""),
            "doctor_name" => safe($_GET["name"] ?? "Unknown Instructor"),
            "image"       => json_encode([])
        ];

        $courseId = $manager->addCourse($data);
        if (!$courseId) jsonResponse(["status"=>"error","message"=>"Failed to create course"]);

        // Upload images
      // UPLOAD IMAGES (safe check)
$imageUploads = [];
if (isset($_FILES['course_image'])) {
    $imageUploads = $manager->uploadFiles(
        $_FILES['course_image'],
        ["image/jpeg"=>true, "image/png"=>true, "image/webp"=>true],
        5*1024*1024,
        $courseId
    );
}

// Only save the paths in DB
$imagePaths = [];
foreach ($imageUploads as $img) {
    $imagePaths[] = $img['path']; // keep only path
}

$data["image"] = json_encode($imagePaths);

// Update DB
$conn->query("UPDATE Courses SET image='" . $conn->real_escape_string($data["image"]) . "' WHERE id=$courseId");

        

        // Upload videos
        $videoUploads = [];
        if(isset($_FILES['course_videos'])) {
            $videoUploads = $manager->uploadFiles(
                $_FILES['course_videos'],
                ["video/mp4"=>true,"video/webm"=>true,"video/ogg"=>true],
                500*1024*1024,
                $courseId
            );
            if($videoUploads) $manager->saveVideos($courseId,$videoUploads);
        }

        // Send WebSocket notifications
        $manager->sendWebSocket("new_course",["course_id"=>$courseId]);
        foreach($videoUploads as $v) $manager->sendWebSocket("new_video",$v);

        jsonResponse([
            "status"=>"success",
            "course_id"=>$courseId,
            "images"=>$imageUploads,
            "videos"=>$videoUploads
        ]);

    } catch(Exception $e) {
        error_log($e->getMessage());
        jsonResponse(["status"=>"error","message"=>$e->getMessage()]);
    }
}

// Default page request
$courses = (new CourseManager($conn))->getCourses();
?>



<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title> Course Manager</title>
<link rel="icon" href="science.jpg" type="image/jpg">

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- TinyMCE -->
<script src="https://cdn.tiny.cloud/1/pj9xerxsaf07wyvwy8ga4ddxsx5estn1k80jjud2dp75c9vg/tinymce/6/tinymce.min.js"></script>

<style>
.fade-in { animation: fadeIn .5s ease-in-out; }
@keyframes fadeIn { from{opacity:0; transform:translateY(10px);} to{opacity:1; transform:translateY(0);} }
.preview-img { width: 90px; height: 80px; object-fit: cover; border-radius: 8px; }
.preview-video { width: 120px; height: 90px; border-radius: 8px; }
</style>
</head>
<body class="bg-gradient-to-br from-slate-100 to-gray-200 min-h-screen p-6">

<div class="max-w-3xl mx-auto bg-white p-8 shadow-xl rounded-2xl fade-in">

<h1 class="text-4xl font-extrabold text-center mb-6 bg-gradient-to-r from-blue-500 to-purple-500 text-transparent bg-clip-text">
    Add New Course
</h1>

<form id="courseForm" enctype="multipart/form-data" class="space-y-5">

<!-- Course Name -->
<input type="text" name="name" placeholder="Course Name"
class="w-full p-3 rounded-lg border focus:ring-4 focus:ring-blue-300" required>

<!-- Department -->
<select name="department"
class="w-full p-3 rounded-lg border focus:ring-4 focus:ring-purple-300" required>
    <option>Physics</option>
    <option>Computer Science</option>
   
</select>

<!-- Description -->
<textarea id="editor" name="description"></textarea>

<!-- Price -->
<input type="number" name="price" min="1" placeholder="Price"
class="w-full p-3 rounded-lg border focus:ring-4 focus:ring-green-300">

<!-- Date -->
<input type="date" name="start_date"
class="w-full p-3 rounded-lg border focus:ring-4 focus:ring-pink-300">

<!-- IMAGE UPLOAD BUTTON -->
<button type="button" id="btnImage"
class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white w-full p-3 rounded-xl shadow-md hover:scale-105 transition flex items-center justify-center gap-2">
    <i class="fa-solid fa-image"></i> Select Course Images
</button>
<input type="file" name="course_image[]" id="course-image" accept="image/*" multiple hidden>

<div id="imagePreview" class="flex flex-wrap gap-2 mt-3"></div>

<!-- VIDEO UPLOAD BUTTON -->
<button type="button" id="btnVideo"
class="bg-gradient-to-r from-purple-500 to-pink-600 text-white w-full p-3 rounded-xl shadow-md hover:scale-105 transition flex items-center justify-center gap-2">
    <i class="fa-solid fa-video"></i> Select Course Videos
</button>
<input type="file" name="course_videos[]" id="course-videos" accept="video/*" multiple hidden>

<div id="videoPreview" class="flex flex-wrap gap-2 mt-3"></div>

<!-- Submit -->
<button class="bg-green-600 hover:bg-green-700 text-white p-3 w-full rounded-xl shadow-lg">
    <i class="fa-solid fa-upload"></i> Add Course
</button>

<div id="loading" class="hidden mx-auto mt-4 text-center text-blue-600">
    <i class="fa-solid fa-spinner fa-spin text-4xl"></i>
</div>

</form>
</div>


<script src="/projectGraduation/scripts/add_course.js">

</script>

</body>
</html>
