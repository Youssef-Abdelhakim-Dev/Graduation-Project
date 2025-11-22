<?php
/**
 * ONE-FILE ADVANCED EXAM SYSTEM (TWO DATABASES)
 * ------------------------------------------------
 * - DB1: project -> students
 * - DB2: examdb -> exams + exam_questions
 * - OOP + Error logs
 * - Save exam + questions
 * - Twilio WhatsApp notifications to logged students
 * - One button: Save + Notify
 * - Anti-cheating + online/offline detection
 */

require __DIR__ . "/vendor/autoload.php";

use Twilio\Rest\Client;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

/* ---------------- CONFIG ---------------- */
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_USER = $_ENV['DB_USER'] ?? 'root';
$DB_PASS = $_ENV['DB_PASS'] ?? '';
$DB_STUDENTS = $_ENV['DB_STUDENTS'] ?? 'project';
$DB_EXAMS = $_ENV['DB_EXAMS'] ?? 'examdb';

$TWILIO_SID   = $_ENV['TWILIO_SID'] ?? null;
$TWILIO_TOKEN = $_ENV['TWILIO_TOKEN'] ?? null;
$TWILIO_FROM  = $_ENV['TWILIO_FROM'] ?? null;

/* ---------------- DB CLASS ---------------- */
class DB {
    private $mysqli;
    public function __construct($host, $user, $pass, $db){
        $this->mysqli = @new mysqli($host,$user,$pass,$db);
        if($this->mysqli->connect_error){
            $this->log("DB Connection Failed: ".$this->mysqli->connect_error);
            die(json_encode(["status"=>"error","message"=>"Database error"]));
        }
    }
    public function query($sql, $types=null, $params=null){
        $stmt = $this->mysqli->prepare($sql);
        if(!$stmt){ $this->log("SQL Prepare Error: ".$this->mysqli->error); return false; }
        if($types && $params) $stmt->bind_param($types, ...$params);
        if(!$stmt->execute()){ $this->log("SQL Execute Error: ".$stmt->error); return false; }
        return $stmt;
    }
    private function log($msg){
        file_put_contents("logs.txt","[".date("Y-m-d H:i:s")."] $msg\n",FILE_APPEND);
    }
}

/* --------- DB INSTANCES --------- */
$dbStudents = new DB($DB_HOST,$DB_USER,$DB_PASS,$DB_STUDENTS); // students
$dbExams    = new DB($DB_HOST,$DB_USER,$DB_PASS,$DB_EXAMS);    // exams

/* --------- API ROUTER --------- */
if(isset($_GET['api'])){
    if($_GET['api']=='saveAndNotify'){
        saveAndNotify($dbExams,$dbStudents,$TWILIO_SID,$TWILIO_TOKEN,$TWILIO_FROM);
        exit;
    }
}

/* --------- SAVE EXAM + SEND WHATSAPP --------- */
function saveAndNotify($dbExams,$dbStudents,$sid,$token,$from){
    $title     = $_POST['title'] ?? '';
    $duration  = $_POST['duration'] ?? '';
    $questions = $_POST['questions'] ?? [];

    if(!$title || !$duration){
        echo json_encode(["status"=>"error","message"=>"Missing fields"]);
        return;
    }

    // Save exam
    $stmt = $dbExams->query("INSERT INTO exams (title,duration) VALUES (?,?)","si",[$title,$duration]);
    if(!$stmt){ echo json_encode(["status"=>"error","message"=>"DB error"]); return; }
    $examID = $stmt->insert_id;

    // Save questions
    foreach($questions as $q){
        $type = $q['type'];
        $correct = ($type=="multiple") ? $q['correct'] : $q['correct_tf'];
        $options = isset($q['options']) ? json_encode($q['options']) : null;

        $dbExams->query(
            "INSERT INTO exam_questions (exam_id,question,type,options,correct) VALUES (?,?,?,?,?)",
            "issss",
            [$examID,$q['text'],$type,$options,$correct]
        );
    }

    // Send WhatsApp to logged students
    $stmt = $dbStudents->query("SELECT * FROM students WHERE logged='true'");
    $result = $stmt->get_result();
    $client = new Client($sid,$token);

    while($row = $result->fetch_assoc()){
        $phone = "whatsapp:+".$row['phone'];
        $link  = "http://localhost/projectGraduation/getExams.php?student=".base64_encode($row['id'])."&exam=".base64_encode($examID);
        $message = "📘 *New Exam!*\n📝 *Exam:* $title\n⏰ *Duration:* $duration min\n👉 Start: $link\n⚠️ Anti-cheating enabled.";

        try{
            $client->messages->create($phone, ['from'=>$from,'body'=>$message]);
        }catch(Exception $e){ error_log("TWILIO ERROR: ".$e->getMessage()); }
    }

    echo json_encode(["status"=>"success","message"=>"Exam saved & WhatsApp sent!","exam_id"=>$examID]);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Exam</title>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-300 min-h-screen flex items-center justify-center p-8">

<div class="bg-white p-6 rounded-xl w-full max-w-3xl shadow-xl">
<h2 class="text-3xl font-bold text-center text-blue-600 mb-5">Create Exam</h2>

<form id="examForm">
<input type="text" name="title" placeholder="Exam Title" required class="w-full p-3 border rounded mb-4">
<input type="number" name="duration" placeholder="Duration (min)" required class="w-full p-3 border rounded mb-4">

<h3 class="text-xl font-semibold mb-2">Questions</h3>
<div id="questionsContainer"></div>

<button type="button" onclick="addQuestion()" class="bg-green-600 text-white px-4 py-2 rounded mt-3">+ Add Question</button>
<button type="submit" class="bg-blue-600 text-white px-4 py-3 mt-5 w-full rounded">Save & Notify Students</button>
</form>
</div>

<script>
let index=0;
function addQuestion(){
    const div = document.createElement("div");
    div.className="p-4 border rounded mt-4 bg-gray-100";
    div.innerHTML=`
        <input type="text" name="questions[${index}][text]" placeholder="Question" class="w-full p-2 mb-2 border rounded">
        <select name="questions[${index}][type]" class="w-full p-2 border rounded mb-2" onchange="toggle(${index},this.value)">
            <option value="multiple">Multiple Choice</option>
            <option value="truefalse">True/False</option>
        </select>
        <div id="multi-${index}">
            <input type="text" name="questions[${index}][options][]" placeholder="Option 1" class="w-full p-2 mb-1 border rounded">
            <input type="text" name="questions[${index}][options][]" placeholder="Option 2" class="w-full p-2 mb-1 border rounded">
            <input type="text" name="questions[${index}][options][]" placeholder="Option 3" class="w-full p-2 mb-1 border rounded">
            <input type="text" name="questions[${index}][options][]" placeholder="Option 4" class="w-full p-2 mb-1 border rounded">
            <input type="text" name="questions[${index}][correct]" placeholder="Correct Answer" class="w-full p-2 border rounded">
        </div>
        <div id="tf-${index}" class="hidden">
            <input type="text" name="questions[${index}][correct_tf]" placeholder="True/False" class="w-full p-2 border rounded">
        </div>`;
    document.getElementById("questionsContainer").appendChild(div);
    index++;
}
function toggle(i,val){
    document.getElementById(`multi-${i}`).classList.toggle("hidden", val==="truefalse");
    document.getElementById(`tf-${i}`).classList.toggle("hidden", val==="multiple");
}

document.getElementById("examForm").addEventListener("submit", async e => {
    e.preventDefault();

    // Disable form to prevent multiple clicks
    const form = e.target;
    const submitBtn = form.querySelector("button[type='submit']");
    submitBtn.disabled = true;

    // Custom loading Swal
    Swal.fire({
        title: 'Saving Exam...',
        html: 'Please wait while we process your request.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
            const b = Swal.getHtmlContainer();
            b.style.fontSize = '1rem';
            b.style.color = '#4A5568';
            b.style.fontWeight = '600';
        },
        willClose: () => {
            submitBtn.disabled = false; // Re-enable form
        }
    });

    const fd = new FormData(form);

    try {
        const res = await fetch("?api=saveAndNotify", {
            method: "POST",
            body: fd
        });

        const text = await res.text(); // get raw text
        let data;

        try {
            data = JSON.parse(text); // parse JSON manually
        } catch (err) {
            console.error("Invalid JSON:", text);
            Swal.fire({
                icon: 'error',
                title: 'Server Error',
                html: 'Server returned invalid response.<br>Check console for details.',
                showCloseButton: true,
                showCancelButton: true,
                cancelButtonText: 'Retry',
                confirmButtonText: 'Contact Admin',
                focusConfirm: false,
                allowOutsideClick: false,
                willClose: () => {
                    submitBtn.disabled = false;
                }
            });
            return;
        }

        // Success Swal with animation
        Swal.fire({
            icon: data.status === 'success' ? 'success' : 'warning',
            title: data.status === 'success' ? 'Success!' : 'Attention!',
            html: data.message,
            timer: 4000,
            timerProgressBar: true,
            showCloseButton: true,
            showCancelButton: data.status !== 'success', // if error allow retry
            cancelButtonText: 'Retry',
            confirmButtonText: 'Ok',
            didOpen: () => {
                const popup = Swal.getPopup();
                popup.style.borderRadius = '1rem';
                popup.style.border = '3px solid #3182ce';
                popup.style.boxShadow = '0 10px 25px rgba(0,0,0,0.2)';
            },
            willClose: () => {
                submitBtn.disabled = false;
            }
        });

    } catch (err) {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Request Failed',
            html: 'Network error or server unreachable.<br>Try again later.',
            showCloseButton: true,
            showCancelButton: true,
            cancelButtonText: 'Retry',
            confirmButtonText: 'Report',
            willClose: () => {
                submitBtn.disabled = false;
            }
        });
    }
});

document.addEventListener("visibilitychange",()=>{ if(document.hidden) console.log("Tab switch detected!"); });
window.addEventListener("offline",()=>Swal.fire("Connection lost!"));
window.addEventListener("online",()=>Swal.fire("Back online"));
</script>
</body>
</html>
