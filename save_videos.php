<?php
// === CONFIGURATION ===
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "project";

// === HEADERS (for CORS and JSON response) ===
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

// === CONNECT TO DATABASE ===
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "DB connection failed: " . $conn->connect_error]);
    exit();
}

// === READ & VALIDATE JSON INPUT ===
$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['videoId'], $data['videoTitle'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Missing required fields."]);
    exit();
}

$videoId = trim($data['videoId']);
$videoTitle = trim($data['videoTitle']);
$videoUrl = "https://www.youtube.com/watch?v=" . $videoId;
$timestamp = date("Y-m-d H:i:s");

// === VALIDATE YOUTUBE VIDEO ID (11-character format) ===
if (!preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
    http_response_code(422);
    echo json_encode(["status" => "error", "message" => "Invalid YouTube video ID."]);
    exit();
}

// === CHECK IF VIDEO ALREADY EXISTS ===
$stmt = $conn->prepare("SELECT id FROM videos WHERE video_url = ?");
$stmt->bind_param("s", $videoUrl);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(["status" => "duplicate", "message" => "This video already exists."]);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// === INSERT NEW VIDEO USING PREPARED STATEMENT ===
$stmt = $conn->prepare("INSERT INTO videos (video_url, video_title, added_at) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $videoUrl, $videoTitle, $timestamp);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Video saved successfully.", "video_id" => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Insert failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
