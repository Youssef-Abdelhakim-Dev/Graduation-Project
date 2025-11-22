<?php

// ================================

// ERROR REPORTING (hide in production, log to file instead)

// ================================

ini_set('display_errors', 0);

ini_set('display_startup_errors', 0);

error_reporting(E_ALL);

session_start();





// ================================

// DATABASE CONNECTION

// ================================

try {

    $pdo = new PDO("mysql:host=localhost;dbname=project;charset=utf8", "root", "");

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch(PDOException $e) {

    echo json_encode(['status'=>'error','message'=>'DB Connection Failed']);

    exit;

}



// ================================

// CONFIG

// ================================

require_once 'vendor/autoload.php'; // Twilio SDK

use Twilio\Rest\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);

$dotenv->load();



$roles = ['admin','teacher','student'];

$admin_user = $_SESSION['user_role'] ?? 'system';

$wsServer = "ws://localhost:8080"; // WebSocket server



// Twilio Config

$twilioSID = $_ENV['TWILIO_SID'];

$twilioToken = $_ENV['TWILIO_TOKEN'];

$twilioFrom = $_ENV['TWILIO_FROM'];



// ================================

// UTILITIES

// ================================

class Security {

    public static function sanitize($text) {

        return htmlspecialchars(trim($text), ENT_QUOTES,'UTF-8');

    }

    public static function validateFields($fields,$required){

        foreach($required as $r){

            if(!isset($fields[$r]) || trim($fields[$r])==='') return "Missing field: $r";

        }

        return null;

    }

}



class Logger {

    private $pdo;

    public function __construct($pdo){$this->pdo=$pdo;}

    public function log($actor,$action,$table,$id){

        $stmt=$this->pdo->prepare("INSERT INTO actions_log (actor,action,table_name,record_id,timestamp) VALUES (?,?,?,?,NOW())");

        $stmt->execute([$actor,$action,$table,$id]);

    }

}

class Notify {

    private PDO $pdo;

    private string $wsServer;

    private int $wsRetries = 3;

    private int $wsRetryDelay = 500; // milliseconds



    public function __construct(PDO $pdo, string $wsServer) {

        $this->pdo = $pdo;

        $this->wsServer = rtrim($wsServer, '/');

    }



    /**

     * Send a notification

     *

     * @param string $userType

     * @param int $userId

     * @param string $message

     * @param array|null $data

     * @param string $category (info, warning, error)

     */

    public function send(string $userType, int $userId, string $message, ?array $data = null, string $category = 'info') {

        $notificationId = null;



        try {

            // --- Save notification in DB ---

            $stmt = $this->pdo->prepare("

                INSERT INTO notifications 

                (user_type, user_id, message, data, category, status, created_at) 

                VALUES (?, ?, ?, ?, ?, 'pending', NOW())

            ");

            $stmt->execute([

                $userType,

                $userId,

                $message,

                json_encode($data, JSON_UNESCAPED_UNICODE),

                $category

            ]);



            $notificationId = $this->pdo->lastInsertId();



            // --- Prepare payload for WS ---

            $payload = [

                'id' => $notificationId,

                'type' => 'notification',

                'userType' => $userType,

                'userId' => $userId,

                'message' => $message,

                'category' => $category,

                'payload' => $data

            ];



            // --- Attempt WebSocket send with retry ---

            $sent = $this->sendWebSocket(json_encode($payload));



            // --- Update DB status ---

            $status = $sent ? 'sent' : 'failed';

            $stmt = $this->pdo->prepare("UPDATE notifications SET status = ? WHERE id = ?");

            $stmt->execute([$status, $notificationId]);



        } catch (\Exception $e) {

            if ($notificationId) {

                // mark failed in DB if inserted

                $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'failed' WHERE id = ?");

                $stmt->execute([$notificationId]);

            }

            error_log("Notify Error: " . $e->getMessage());

        }

    }



    /**

     * Send via WebSocket with retry

     */

    private function sendWebSocket(string $jsonPayload): bool {

        $attempt = 0;

        while ($attempt < $this->wsRetries) {

            try {

                $result = @file_get_contents($this->wsServer . "/send?msg=" . urlencode($jsonPayload));

                if ($result !== false) return true;

            } catch (\Exception $e) {

                // ignore and retry

            }

            usleep($this->wsRetryDelay * 1000);

            $attempt++;

        }

        return false;

    }



    /**

     * Mark notification as read

     */

    public function markRead(int $notificationId) {

        $stmt = $this->pdo->prepare("UPDATE notifications SET status = 'read', read_at = NOW() WHERE id = ?");

        $stmt->execute([$notificationId]);

    }



    /**

     * Fetch unread notifications for a user

     */

    public function fetchUnread(string $userType, int $userId, int $limit = 20): array {

        $stmt = $this->pdo->prepare("

            SELECT id, message, data, category, created_at 

            FROM notifications 

            WHERE user_type = ? AND user_id = ? AND status != 'read' 

            ORDER BY created_at DESC 

            LIMIT ?

        ");

        $stmt->execute([$userType, $userId, $limit]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    }

}

class WhatsAppNotifier {

    private $sid;

    private $token;

    private $from;

    private $logFile;



    public function __construct($sid, $token, $from, $logFile = null) {

        $this->sid = $sid;

        $this->token = $token;

        $this->from = $from;

        $this->logFile = $logFile ?? __DIR__ . '/whatsapp.log';

    }



    public function sendMessage($toNumber, $message) {

        $client = new Client($this->sid, $this->token);



        // --- Format number to E.164 ---

        $phone = preg_replace('/\D/', '', $toNumber); // Remove non-digits



        if (strlen($phone) == 11 && $phone[0] === '0') {

            // Egyptian local number: 010XXXXXXXX → +20XXXXXXXXXX

            $phone = '+20' . substr($phone, 1);

        } elseif (substr($phone, 0, 1) !== '+') {

            // Prepend + if missing

            $phone = '+' . $phone;

        }



        try {

            $msg = $client->messages->create(

                "whatsapp:$phone",

                [

                    'from' => $this->from, // Twilio WhatsApp number: whatsapp:+14155238886

                    'body' => $message

                ]

            );



            // Log success

            error_log("WhatsApp sent successfully to $phone, SID: {$msg->sid}\n", 3, $this->logFile);



            return ['status' => 'success', 'sid' => $msg->sid];



        } catch (\Exception $e) {

            // Log error

            error_log("WhatsApp send failed to $phone: " . $e->getMessage() . "\n", 3, $this->logFile);



            return ['status' => 'error', 'message' => $e->getMessage()];

        }

    }

}

// ================================

// USER SYSTEM

// ================================

class UserSystem {

    private $pdo;

    private $logger;

    private $notify;

    private $waNotifier;

    public function __construct($pdo,$logger,$notify,$waNotifier){ 

        $this->pdo=$pdo; 

        $this->logger=$logger; 

        $this->notify=$notify;

        $this->waNotifier=$waNotifier;

    }



    private function isDuplicate($email,$username,$phone,$id=null,$table=null){

        $tables = $table ? [$table] : ['teachers','students'];

        foreach($tables as $tbl){

            $sql = "SELECT COUNT(*) FROM $tbl WHERE (email=? OR username=? OR phone=?)";

            $params = [$email,$username,$phone];

            if($id){$sql.=" AND id<>?";$params[]=$id;}

            $stmt=$this->pdo->prepare($sql);$stmt->execute($params);

            if($stmt->fetchColumn()>0) return true;

        }

        return false;

    }

    public function addUser($table, $data, $admin) {

        $required = ['email', 'username', 'password', 'phone', 'year'];

        if ($table === 'teachers') $required[] = 'subject';

        

        // Validate required fields

        if ($err = Security::validateFields($data, $required)) 

            return ['status'=>'error','message'=>$err];

    

        // Sanitize inputs

        $email = $data['email'];

        $username = $data['username'];

        $phone = $data['phone'];

    

        // Check if student/teacher already exists

        $stmt = $this->pdo->prepare("SELECT * FROM $table WHERE email = ? OR username = ? OR phone = ? LIMIT 1");

        $stmt->execute([$email, $username, $phone]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    

        if ($existing) {

            // If exists, update logged field

            $update = $this->pdo->prepare("UPDATE $table SET logged = 'true' WHERE id = ?");

            $update->execute([$existing['id']]);

            $this->logger->log($admin,'LOGIN_EXISTING',$table,$existing['id']);

            return ['status'=>'exists','message'=>'User already exists, logged in.'];

        }

    

        // Insert new record

        $stmt = $this->pdo->prepare($table === 'teachers' ?

            "INSERT INTO teachers (email, username, password, phone, year, subject, logged) VALUES (?,?,?,?,?,?, 'true')" :

            "INSERT INTO students (email, username, password, phone, year, logged) VALUES (?,?,?,?,?, 'true')"

        );

    

        $params = $table === 'teachers' ?

            [$email, $username, password_hash($data['password'], PASSWORD_DEFAULT), $phone, $data['year'], $data['subject']] :

            [$email, $username, password_hash($data['password'], PASSWORD_DEFAULT), $phone, $data['year']];

    

        $stmt->execute($params);

        $id = $this->pdo->lastInsertId();

        $this->logger->log($admin,'ADD',$table,$id);

    

        // Notification

        $this->notify->send($table=='teachers'?'teacher':'student',$id,"You have been added successfully",$data);



        // WhatsApp

        // Prepare a rich WhatsApp message

$fields = [

    "👋 *Hello {$data['username']}!*",

    "You have been added as a *" . ($table === 'teachers' ? 'Teacher' : 'Student') . "*.",

    "*📧 Email:* {$data['email']}",

    "*📞 Phone:* {$data['phone']}",

    "*🎓 Year:* {$data['year']}",

    "🔒 *Password:* {$data['password']}"

];



// Add subject if teacher

if ($table === 'teachers') {

    $fields[] = "*📚 Subject:* {$data['subject']}";

}



// Combine all lines into a single message

$message = implode("\n", $fields);



// Send WhatsApp message

$this->waNotifier->sendMessage($data['phone'], $message);



        return ['status'=>'success','message'=>ucfirst($table).' added'];

    }



    public function updateUser($table,$data,$id,$admin){

        $required=['username','email','phone','year'];

        if($table=='teachers') $required[]='subject';

        if($err=Security::validateFields($data,$required)) return ['status'=>'error','message'=>$err];

        if($this->isDuplicate($data['email'],$data['username'],$data['phone'],$id,$table)) return ['status'=>'error','message'=>'Duplicate entry'];



        $stmt=$this->pdo->prepare($table=='teachers' ?

            "UPDATE teachers SET username=?,email=?,phone=?,year=?,subject=? WHERE id=?" :

            "UPDATE students SET username=?,email=?,phone=?,year=? WHERE id=?"

        );

        $params=$table=='teachers' ? [$data['username'],$data['email'],$data['phone'],$data['year'],$data['subject'],$id] :

            [$data['username'],$data['email'],$data['phone'],$data['year'],$id];

        $stmt->execute($params);

        $this->logger->log($admin,'UPDATE',$table,$id);



        // Notification

        $this->notify->send($table=='teachers'?'teacher':'student',$id,"Your profile has been updated",$data);



        // WhatsApp

        $message = "Hello {$data['username']}!\nYour profile has been updated.\nEmail: {$data['email']}\nPhone: {$data['phone']}\nYear: {$data['year']}";

        if($table=='teachers') $message .= "\nSubject: {$data['subject']}";

        $this->waNotifier->sendMessage($data['phone'],$message);



        return ['status'=>'success','message'=>ucfirst($table).' updated'];

    }



    public function deleteUser($table,$id,$admin){

        $stmt=$this->pdo->prepare("DELETE FROM $table WHERE id=?");

        $stmt->execute([$id]);

        $this->logger->log($admin,'DELETE',$table,$id);

        return ['status'=>'success','message'=>ucfirst($table).' deleted'];

    }



    public function fetchUsers($table){

        return $this->pdo->query("SELECT * FROM $table ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

    }

}



/* ===========================

    INIT OBJECTS

=========================== */

$logger = new Logger($pdo);

$notify = new Notify($pdo,$wsServer);

$waNotifier = new WhatsAppNotifier($twilioSID,$twilioToken,$twilioFrom);

$userSystem = new UserSystem($pdo,$logger,$notify,$waNotifier);



/* ===========================

    AJAX HANDLER

=========================== */

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['action'])){

    $action = Security::sanitize($_POST['action']);

    $id = $_POST['id'] ?? null;

    $data = array_map('Security::sanitize', $_POST);

    unset($data['action'],$data['id']);



    try {

        switch($action){

            case 'add_teacher': $resp=$userSystem->addUser('teachers',$data,$admin_user); break;

            case 'add_student': $resp=$userSystem->addUser('students',$data,$admin_user); break;

            case 'update_teacher': $resp=$userSystem->updateUser('teachers',$data,$id,$admin_user); break;

            case 'update_student': $resp=$userSystem->updateUser('students',$data,$id,$admin_user); break;

            case 'delete_teacher': $resp=$userSystem->deleteUser('teachers',$id,$admin_user); break;

            case 'delete_student': $resp=$userSystem->deleteUser('students',$id,$admin_user); break;

            default: $resp=['status'=>'error','message'=>'Unknown action'];

        }

    } catch (\Exception $e) {

        $resp = ['status'=>'error','message'=>$e->getMessage()];

    }



    echo json_encode($resp); 

    exit();

}



/* ===========================

    FETCH FOR FRONTEND

=========================== */

$teachers = $userSystem->fetchUsers('teachers');

$students = $userSystem->fetchUsers('students');



// If you want, you can skip HTML output for pure API use and just return JSON here

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link rel="shortcut icon" href="admin.png" type="image/png">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100 min-h-screen">

<header class="bg-white shadow p-4 mb-6">
    <h1 class="text-4xl font-bold text-center text-gray-800 flex items-center justify-center gap-3">
        <i class="fa-solid fa-user-shield"></i> Admin Panel
    </h1>
</header>

<main class="container mx-auto px-4">
    <!-- Control Buttons -->
    <section class="flex justify-center gap-4 mb-6">
        <button id="showStudent" class="bg-green-500 text-white px-4 py-2 rounded shadow hover:scale-105 transition flex items-center gap-2">
            <i class="fa-solid fa-graduation-cap"></i> Students
        </button>
        <button id="showTeacher" class="bg-blue-500 text-white px-4 py-2 rounded shadow hover:scale-105 transition flex items-center gap-2">
            <i class="fa-solid fa-chalkboard-teacher"></i> Teachers
        </button>
    </section>

    <!-- Forms -->
    <section class="max-w-xl mx-auto bg-white p-6 rounded-lg shadow-lg mb-6">
        <!-- Teacher Form -->
        <form id="teacherForm" class="space-y-3 hidden">
            <h2 class="text-xl font-semibold mb-2"><i class="fa-solid fa-chalkboard-teacher"></i> Add Teacher</h2>
            <input type="email" name="email" placeholder="Email" class="w-full border p-2 rounded" required>
            <input type="text" name="username" placeholder="Username" class="w-full border p-2 rounded" required>
            <input type="password" name="password" placeholder="Password" class="w-full border p-2 rounded" required>
            <input type="number" name="phone" placeholder="Phone" class="w-full border p-2 rounded" required>
            <input type="number" name="year" placeholder="Year" class="w-full border p-2 rounded" required>
            <input type="text" name="subject" placeholder="Subject" class="w-full border p-2 rounded">
            <button type="submit" class="bg-blue-500 text-white w-full py-2 rounded shadow hover:bg-blue-600 transition">
                <i class="fa-solid fa-plus"></i> Add Teacher
            </button>
        </form>

        <!-- Student Form -->
        <form id="studentForm" class="space-y-3 hidden">
            <h2 class="text-xl font-semibold mb-2"><i class="fa-solid fa-graduation-cap"></i> Add Student</h2>
            <input type="email" name="email" placeholder="Email" class="w-full border p-2 rounded" required>
            <input type="text" name="username" placeholder="Username" class="w-full border p-2 rounded" required>
            <input type="password" name="password" placeholder="Password" class="w-full border p-2 rounded" required>
            <input type="number" name="phone" placeholder="Phone" class="w-full border p-2 rounded" required>
            <input type="number" name="year" placeholder="Year" class="w-full border p-2 rounded" required>
            <button type="submit" class="bg-green-500 text-white w-full py-2 rounded shadow hover:bg-green-600 transition">
                <i class="fa-solid fa-plus"></i> Add Student
            </button>
        </form>
    </section>

    <!-- Tables -->
    <section id="tables">
    <?php foreach(['teachers'=>'blue','students'=>'green'] as $table=>$color): 
          $count = count($$table);
    ?>
        <article id="<?= $table ?>TableDiv" class="overflow-x-auto mb-6 hidden">
            <header class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3">
                    <h3 class="text-2xl font-bold">
                        <i class="fa-solid <?= $table==='teachers'?'fa-chalkboard-teacher':'fa-graduation-cap' ?>"></i> <?= ucfirst($table) ?>
                    </h3>
                    <span class="text-gray-600 text-sm">Total: <?= $count ?></span>
                </div>
                <input type="text" id="search<?= ucfirst($table) ?>" placeholder="Search <?= ucfirst($table) ?>..." class="border p-2 rounded">
            </header>
            <table class="w-full border-collapse bg-white shadow-lg rounded-lg" id="<?= $table ?>Table">
                <thead class="bg-<?= $color ?>-600 text-white">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Year</th>
                        <?= $table==='teachers'?'<th>Subject</th>':'' ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($$table as $row): ?>
                    <tr class="border hover:bg-<?= $color ?>-100">
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['username'] ?></td>
                        <td><?= $row['email'] ?></td>
                        <td><?= $row['phone'] ?></td>
                        <td><?= $row['year'] ?></td>
                        <?= $table==='teachers'?'<td>'.$row['subject'].'</td>':'' ?>
                        <td class="flex gap-2">
                            <button class="updateBtn bg-yellow-500 text-white px-2 rounded" data-id="<?= $row['id'] ?>" data-type="<?= $table ?>"><i class="fa-solid fa-pen"></i></button>
                            <button class="deleteBtn bg-red-500 text-white px-2 rounded" data-id="<?= $row['id'] ?>" data-type="<?= $table ?>"><i class="fa-solid fa-trash"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </article>
    <?php endforeach; ?>
    </section>

</main>

<footer class="bg-white shadow p-4 mt-6 text-center">
<p>&copy; <?= date('Y') ?> Admin Panel.</p>
</footer>

<script src="/projectGraduation/scripts/admin.js">

</script>
</body>
</html>
