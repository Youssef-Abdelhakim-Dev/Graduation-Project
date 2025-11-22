<?php
session_start();

/* =============================
   BASIC MEDIUM SECURITY
============================= */
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf'];

if (!isset($_SESSION['fp'])) {
    $_SESSION['fp'] = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

// refresh security (IP/UA mismatch)
if ($_SESSION['fp'] !== hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . ($_SERVER['REMOTE_ADDR'] ?? ''))) {
    die("Security Block: Device changed");
}

// Rate limit
if (!isset($_SESSION['r'])) $_SESSION['r'] = 0;
$_SESSION['r']++;
if ($_SESSION['r'] > 500) die("Too many requests");

/* =============================
   DB CONFIG
============================= */
$DB_HOST = "localhost";
$DB_USER = "root";
$DB_PASS = "";
$DB_EXAMS = "examdb";

/* =============================
   OOP DB CLASS
============================= */
class DB {
    private $conn;
    
    public function __construct($host, $user, $pass, $db) {
        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            die("DB Connection failed: " . $this->conn->connect_error);
        }
    }
    
    public function query($sql, $types = null, $params = null) {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return false;

        if ($types && $params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt;
    }
}

$db = new DB($DB_HOST, $DB_USER, $DB_PASS, $DB_EXAMS);

/* =============================
   FETCH QUESTIONS (AJAX)
============================= */
if (isset($_GET['exam_id'])) {

   
    $exam_id = intval($_GET['exam_id']);
    $stmt = $db->query("SELECT * FROM exam_questions WHERE exam_id = ?", "i", [$exam_id]);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<form id='exam_$exam_id' class='exam-form'>";
        while ($q = $result->fetch_assoc()) {
            echo "<div class='question'>";
            echo "<p>" . htmlspecialchars($q['question']) . "</p>";

            // MULTIPLE CHOICE
            if ($q['type'] == 'multiple' && $q['options']) {
                $options = json_decode($q['options'], true);
                foreach ($options as $key => $option) {
                    echo "<label>
                        <input type='radio' name='answer_{$q['id']}' value='{$key}'> $option
                    </label><br>";
                }
            }

            // TRUE / FALSE
            elseif ($q['type'] == 'truefalse') {
                echo "<label><input type='radio' name='answer_{$q['id']}' value='true'> True</label><br>";
                echo "<label><input type='radio' name='answer_{$q['id']}' value='false'> False</label><br>";
            }

            echo "</div>";
        }

        echo "<input type='hidden' name='csrf' value='{$_SESSION['csrf']}'>";
        echo "<button type='button' onclick='submitExam($exam_id)'>Submit Exam</button>";
        echo "</form>";
    } else {
        echo "<p>No questions found for this exam.</p>";
    }
    exit();
}

/* =============================
   PROCESS SUBMISSION
============================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        die("CSRF FAILED");
    }

    $score = 0;
    $total_questions = 0;
    $response_html = "";

    foreach ($_POST as $key => $value) {
        if (strpos($key, 'answer_') !== false) {

            $question_id = str_replace('answer_', '', $key);
            $selected_option = $value;

            // Fetch question
            $stmt = $db->query("SELECT * FROM exam_questions WHERE id = ?", "i", [$question_id]);
            $result = $stmt->get_result();
            $total_questions++;

            if ($result->num_rows > 0) {
                $correct = $result->fetch_assoc()['correct'];

                if ($correct == $selected_option) {
                    $score++;
                    $response_html .= "<p style='color: green;'>Correct</p>";
                } else {
                    $response_html .= "<p style='color: red;'>Incorrect</p>";
                }
            }
        }
    }

    $responseHtml .= "<div style='padding:15px; margin-top:10px; border:2px solid #4CAF50; border-radius:8px; background-color:#f0fff0; max-width:300px; text-align:center; font-family:Arial, sans-serif;'>
    <h3 style='color:#2e7d32; font-size:20px; margin-bottom:5px;'>Your Score: $score / $totalQuestions</h3>
    <p style='color:#555; font-size:14px;'>Great job! Review incorrect answers to improve.</p>
</div>";


    echo $responseHtml;
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Available Exams</title>
<meta name="description" content="List of available exams you can take online. View duration, time spent, and start exams securely.">
<meta name="robots" content="index, follow">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<script>
window._CSRF = <?= json_encode($_SESSION["_CSRF_TOKEN"]) ?>;
window._FINGERPRINT = "<?= $_SESSION['fingerprint'] ?>";
</script>
<link rel="stylesheet" href="styles/getExams.css">
</head>
<body class="bg-gray-50 text-gray-800">
<main class="container mx-auto py-8">
    <header class="mb-6">
        <h1 class="text-3xl font-extrabold text-center">Available Exams</h1>
        <p class="text-center text-gray-600 mt-2">Select an exam to view questions and start your attempt.</p>
    </header>

    <?php
    $examStmt = $db->query("SELECT * FROM exams");
    $examResult = $examStmt->get_result();
    ?>

    <?php if ($examResult->num_rows > 0): ?>
    <section aria-labelledby="exam-table-heading">
        <h2 id="exam-table-heading" class="sr-only">Exam List</h2>
        <table class="min-w-full border border-gray-200 bg-white rounded-lg shadow-md">
            <thead class="bg-gray-100">
                <tr>
                    <th scope="col" class="p-3 text-left font-semibold">ID</th>
                    <th scope="col" class="p-3 text-left font-semibold">Exam Title</th>
                    <th scope="col" class="p-3 text-left font-semibold">Duration</th>
                    <th scope="col" class="p-3 text-left font-semibold">Timer</th>
                    <th scope="col" class="p-3 text-left font-semibold">Time Spent</th>
                    <th scope="col" class="p-3 text-left font-semibold">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($exam = $examResult->fetch_assoc()): ?>
                <tr class="border-t">
                    <td class="p-3"><?= $exam['id'] ?></td>
                    <td class="p-3 font-medium"><?= htmlspecialchars($exam['title']) ?></td>
                    <td class="p-3"><?= $exam['duration'] ?> min</td>
                    <td id="timer_<?= $exam['id'] ?>" class="p-3 timer">--:--</td>
                    <td id="time_spent_<?= $exam['id'] ?>" class="p-3 time-spent">00:00</td>
                    <td class="p-3">
                        <button class="exam-btn px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition"
                            onclick="startExam(<?= $exam['id'] ?>, <?= $exam['duration'] ?>)">
                            View Questions
                        </button>
                    </td>
                </tr>
                <tr>
                    <td colspan="6" class="p-3">
                        <div id="questions_<?= $exam['id'] ?>" class="question-container p-3 bg-gray-50 rounded shadow-sm" data-loaded="false" data-loading="false">
                            <p class="loading text-gray-500">Click the button to load questions...</p>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </section>
    <?php else: ?>
    <section class="text-center text-gray-600 mt-6">
        <p>No exams found.</p>
    </section>
    <?php endif; ?>

</main>

<script src="/projectGraduation/scripts/get_Exams.js">

</script>

</body>
</html>
