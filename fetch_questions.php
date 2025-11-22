<?php
// ================================
// === CONFIG / DB CONNECTION ====
// ================================
class Database {
    private $servername = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "examdb";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli(
            $this->servername,
            $this->username,
            $this->password,
            $this->database
        );
        if ($this->conn->connect_error) {
            die("DB Connection failed: " . $this->conn->connect_error);
        }
    }
}

// ================================
// === EXAM MANAGER ===============
// ================================
class ExamManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    // Fetch all questions for an exam
    public function getQuestions(int $exam_id) {
        $sql = "SELECT id, question, type, options FROM exam_questions WHERE exam_id=?";
        $stmt = $this->db->conn->prepare($sql);
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $questions = [];
        while ($row = $result->fetch_assoc()) {
            $row['options'] = json_decode($row['options'], true) ?: [];
            
            // Shuffle options for multiple choice questions
            if ($row['type'] === 'multiple' && is_array($row['options'])) {
                shuffle($row['options']);
            }
    
            $questions[] = $row;
        }
    
        // Shuffle the questions themselves
        shuffle($questions);
    
        return $questions;
    }

    // Check if exam already taken
    public function hasTakenExam(int $exam_id) {
        $sql = "SELECT score, total_questions, percentage FROM logged_exams WHERE exam_id=? ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->conn->prepare($sql);
        $stmt->bind_param("i", $exam_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return [
                'taken' => true,
                'score' => $row['score'],
                'total' => $row['total_questions'],
                'percentage' => $row['percentage']
            ];
        }
        return ['taken' => false];
    }

    // Submit exam answers
    public function submitExam(array $answers, int $exam_id) {
        $total_score = 0;
        $total_questions = 0;

        foreach ($answers as $qid => $selected) {
            $stmt = $this->db->conn->prepare("SELECT type, options, correct FROM exam_questions WHERE id=? AND exam_id=?");
            $stmt->bind_param("ii", $qid, $exam_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if (!$row) continue;

            $correct = ($row['correct'] === 'true' || $row['correct'] === 1) ? 1 : 0;
            $is_correct = ($selected == $correct) ? 1 : 0;
            $total_score += $is_correct;
            $total_questions++;

            // Update selected answer
            $update = $this->db->conn->prepare("UPDATE exam_questions SET selected_option=?, is_correct=? WHERE id=?");
            $update->bind_param("iii", $selected, $is_correct, $qid);
            $update->execute();
        }

        $percentage = ($total_questions > 0) ? round(($total_score / $total_questions) * 100, 2) : 0;

        // Log the exam attempt
        $log = $this->db->conn->prepare("INSERT INTO logged_exams (exam_id, score, total_questions, percentage) VALUES (?,?,?,?)");
        $log->bind_param("iiii", $exam_id, $total_score, $total_questions, $percentage);
        $log->execute();

        return [
            'score' => $total_score,
            'total' => $total_questions,
            'percentage' => $percentage
        ];
    }

    // Render exam form HTML
    public function renderExamForm(int $exam_id, array $questions) {
        if (empty($questions)) return "<p style='text-align:center;color:red;'>No questions available.</p>";

        ob_start(); 
        ?>
       <?php
// ================================
// === Style Variables ============
// ================================
$formStyles = [
    'container' => "width:100%;margin:auto;background:#fff;padding:25px;border-radius:12px;
                    box-shadow:0px 6px 15px rgba(0,0,0,0.15);font-family:Arial,sans-serif;",
    'heading' => "color:blue;text-align:center;",
    'table' => "width:100%;border-collapse:collapse;margin-top:20px;border-radius:12px;border:1px solid #ddd;font-size:16px;",
    'thead_row' => "background:linear-gradient(90deg,#007bff,#0056b3);color:white;",
    'th' => "padding:14px;text-align:left;",
    'tbody_row' => "background:#f9f9f9;transition:0.3s;",
    'td_question' => "padding:14px;border-bottom:1px solid #ddd;font-weight:bold;color:#333;",
    'td_type' => "padding:14px;border-bottom:1px solid #ddd;font-weight:bold;color:#007bff;",
    'td_options' => "padding:14px;border-bottom:1px solid #ddd;",
    'label' => "display:flex;align-items:center;padding:12px;background:#e9ecef;
                border-radius:8px;margin-bottom:6px;cursor:pointer;transition:0.3s;position:relative;",
    'input_radio' => "margin-right:12px;transform:scale(1.3);accent-color:#007bff;cursor:pointer;",
    'button' => "display:block;width:100%;padding:12px;background:linear-gradient(90deg,#28a745,#218838);
                 color:white;font-size:18px;border:none;border-radius:8px;cursor:pointer;transition:0.3s;margin-top:20px;"
];
?>

<form id="exam_<?= $exam_id ?>" onsubmit="submitExam(<?= $exam_id ?>); return false;" style="<?= $formStyles['container'] ?>">
    <h1 style="<?= $formStyles['heading'] ?>">Total Questions: <?= count($questions) ?></h1>
    <table style="<?= $formStyles['table'] ?>">
        <thead>
            <tr style="<?= $formStyles['thead_row'] ?>">
                <th style="<?= $formStyles['th'] ?>">Question</th>
                <th style="<?= $formStyles['th'] ?>">Type</th>
                <th style="<?= $formStyles['th'] ?>">Options</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($questions as $q):
                $question_id = $q['id'];
                $question_text = htmlspecialchars($q['question'], ENT_QUOTES, 'UTF-8');
                $question_type = ucfirst(htmlspecialchars($q['type'], ENT_QUOTES, 'UTF-8'));
            ?>
            <tr style="<?= $formStyles['tbody_row'] ?>">
                <td style="<?= $formStyles['td_question'] ?>"><?= $question_text ?></td>
                <td style="<?= $formStyles['td_type'] ?>"><?= $question_type ?></td>
                <td style="<?= $formStyles['td_options'] ?>">
                    <?php foreach ($q['options'] as $i => $opt):
                        $opt_safe = htmlspecialchars($opt, ENT_QUOTES, 'UTF-8');
                    ?>
                    <label style="<?= $formStyles['label'] ?>">
                        <input type="radio" name="question_<?= $question_id ?>" value="<?= $i ?>" required style="<?= $formStyles['input_radio'] ?>">
                        <span style="font-weight:500;color:#333;"><?= $opt_safe ?></span>
                    </label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <button type="submit" style="<?= $formStyles['button'] ?>">Submit Answers</button>
</form>

        <?php
        return ob_get_clean();
    }
}

// ================================
// === INIT =======================
// ================================
$db = new Database();
$examManager = new ExamManager($db);

// ================================
// === HANDLE REQUEST =============
// ================================
header('Content-Type: text/html; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['exam_id'])) {
    $exam_id = intval($_POST['exam_id']);
    $answers = [];
    foreach ($_POST as $k => $v) {
        if (strpos($k,'question_')===0) $answers[intval(str_replace('question_','',$k))] = intval($v);
    }
    $result = $examManager->submitExam($answers, $exam_id);
    $feedbackStyles = [
        'success_box' => "padding:15px;border-radius:8px;background:#d4edda;color:#155724;margin-bottom:10px;font-weight:bold;",
        'score_box'   => "margin-top:10px;font-size:18px;font-weight:bold;color:#28a745;",
        'error_box'   => "padding:15px;border-radius:8px;background:#f8d7da;color:#721c24;margin-bottom:10px;font-weight:bold;"
    ];
    
    // ================================
    // === Feedback Icons =============
    // ================================
    $feedbackIcons = [
        'success' => "✅",
        'error'   => "❌",
        'warning' => "⚠️",
        'score'   => "🎯"
    ];
    
    // ================================
    // === Render Exam Result =========
    // ================================
    function renderExamResult(array $result, array $styles, array $icons) {
        $html  = "<div style='{$styles['success_box']}'>{$icons['success']} Exam submitted successfully!</div>";
        $html .= "<div style='{$styles['score_box']}'>"
               . "{$icons['score']} Your Score: {$result['score']} / {$result['total']} ({$result['percentage']}%)"
               . "</div>";
        return $html;
    }
    
    // ================================
    // Example usage after submission
    // ================================
    echo renderExamResult($result, $feedbackStyles, $feedbackIcons);
    exit;
}

// Handle check for already taken exam
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['exam_id']) && isset($_GET['check'])) {
    $exam_id = intval($_GET['exam_id']);
    $taken = $examManager->hasTakenExam($exam_id);
    header('Content-Type: application/json');
    echo json_encode($taken);
    exit;
}

// Handle fetching exam questions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['exam_id'])) {
    $exam_id = intval($_GET['exam_id']);
    $questions = $examManager->getQuestions($exam_id);
    echo $examManager->renderExamForm($exam_id, $questions);
}

$db->conn->close();
?>
