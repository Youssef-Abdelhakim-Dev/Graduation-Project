<?php
header('Content-Type: application/json');
function getDBConnection() {
    $host = 'localhost';
    $db   = 'examdb';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Connection failed: ' . $e->getMessage()]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $examId = $_POST['exam_id'] ?? '';
    $userId = $_POST['user_id'] ?? '';
    $answers = $_POST['answers'] ?? [];
    $timeSpent = isset($_POST['time_spent']) ? intval($_POST['time_spent']) : 0;

    if (empty($examId) || empty($userId)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing exam_id or user_id']);
        exit;
    }

    // Ensure answers is an array
    if (!is_array($answers)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid answers format']);
        exit;
    }

    // Convert answers to JSON string
    $answersJson = json_encode($answers, JSON_UNESCAPED_UNICODE);

    try {
        $pdo = getDBConnection();

        // UPSERT query
        $stmt = $pdo->prepare("
            INSERT INTO exam_autosaves (exam_id, user_id, answers, time_spent)
            VALUES (:exam_id, :user_id, :answers, :time_spent)
            ON DUPLICATE KEY UPDATE
                answers = :answers_update,
                time_spent = :time_spent_update,
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':exam_id' => $examId,
            ':user_id' => $userId,
            ':answers' => $answersJson,
            ':time_spent' => $timeSpent,
            ':answers_update' => $answersJson,
            ':time_spent_update' => $timeSpent
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Auto-saved successfully']);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $e->getMessage()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
