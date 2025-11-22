<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "project";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Connection failed: " . $conn->connect_error]));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $exam_title = trim($_POST['exam_title']);
    $duration = intval($_POST['duration']);
    $questions = $_POST['questions'];

    if (empty($exam_title) || $duration <= 0 || empty($questions)) {
        die(json_encode(["status" => "error", "message" => "Please fill all required fields."]));
    }

    // Insert Exam
    $stmt = $conn->prepare("INSERT INTO exams (title, duration) VALUES (?, ?)");
    $stmt->bind_param("si", $exam_title, $duration);

    if ($stmt->execute()) {
        $exam_id = $stmt->insert_id;
    } else {
        die(json_encode(["status" => "error", "message" => "Error inserting exam: " . $stmt->error]));
    }
    $stmt->close();

    // Insert Questions
    $question_stmt = $conn->prepare("INSERT INTO questions (exam_id, question_text, question_type, options, correct_answer) VALUES (?, ?, ?, ?, ?)");

    foreach ($questions as $question) {
        $question_text = trim($question['text']);
        $question_type = $question['type']; // "multiple" or "truefalse"
        
        if ($question_type === "multiple") {
            $options = json_encode($question['options']);
            $correct_answer = intval($question['correct']); // Index of the correct choice
        } elseif ($question_type === "truefalse") {
            $options = json_encode(["True", "False"]); // Store as a standard format
            $correct_answer = trim($question['correct_tf']); // Store the correct answer as text
        } else {
            continue; // Skip invalid question types
        }

        if (!empty($question_text)) {
            $question_stmt->bind_param("issss", $exam_id, $question_text, $question_type, $options, $correct_answer);
            $question_stmt->execute();
        }
    }
    $question_stmt->close();

    echo json_encode(["status" => "success", "message" => "Exam created successfully!"]);
}

$conn->close();
?>
