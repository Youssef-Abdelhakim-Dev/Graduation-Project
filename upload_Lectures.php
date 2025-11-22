<?php
// upload_lecture.php
header('Content-Type: application/json');
include 'connect.php'; // your DB connection

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Invalid request']);
    exit;
}

if (!isset($_FILES['video'])) {
    echo json_encode(['success'=>false,'error'=>'No file uploaded']);
    exit;
}

// sanitize filename
$filename = preg_replace("/[^a-zA-Z0-9_\-]/", "_", $_POST['filename'] ?? 'video');


$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$targetPath = $uploadDir . $filename;

if (!move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
    echo json_encode(['success'=>false,'error'=>'Failed to move uploaded file']);
    exit;
}

// save to database
$doctor_name = mysqli_real_escape_string($conn, $_POST['doctor_name'] ?? 'Unknown');
$filenameDB = mysqli_real_escape_string($conn, $filename);
$filepathDB = mysqli_real_escape_string($conn, 'uploads/'.$filename);

$sql = "INSERT INTO lectures (filename, filepath, uploaded_at, doctor_name) 
        VALUES ('$filenameDB', '$filepathDB', NOW(), '$doctor_name')";

if ($conn->query($sql)) {
    echo json_encode(['success'=>true,'file'=>'uploads/'.$filename]);
} else {
    echo json_encode(['success'=>false,'error'=>'DB error: '.$conn->error]);
}
?>
