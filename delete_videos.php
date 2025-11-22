<?php
require 'connection.php'; // Ensure this file contains DB connection details

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['delete_id']) || empty($_POST['delete_id'])) {
        echo json_encode(["success" => false, "error" => "Invalid request. Video ID is missing."]);
        exit;
    }

    $videoId = $_POST['delete_id'];

    try {
        $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
        $stmt->execute([$videoId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["success" => true, "message" => "Video deleted successfully."]);
        } else {
            echo json_encode(["success" => false, "error" => "No video found with this ID."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["success" => false, "error" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "error" => "Invalid request method."]);
}
?>
