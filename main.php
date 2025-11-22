<?php
// ---------------------------
// 🔹 Error Reporting & Logging
// ---------------------------
ini_set('display_errors', 'Off');
ini_set('log_errors', 'On');
ini_set('error_log', __DIR__ . '/logs/php_errors.log');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// ---------------------------
// 🔹 Load Dependencies
// ---------------------------
require __DIR__ . '/vendor/autoload.php';
require "connect.php"; // Database connection
require "sidebar.php"; // Database connection

use WebSocket\Client;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

// ---------------------------
// 🔹 Twig Setup
// ---------------------------
$loader = new FilesystemLoader(__DIR__ . '/templates');
$twig = new Environment($loader, ['cache' => false]);
$twig->addFilter(new \Twig\TwigFilter('json_decode', fn($s) => (json_last_error() === JSON_ERROR_NONE ? json_decode($s,true) : [])));

// ---------------------------
// 🔹 Handle AJAX Course Add
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='add_course') {
    function safe($v) { return htmlspecialchars(trim($v), ENT_QUOTES); }
    $title = safe($_POST['name'] ?? '');
    $department = safe($_POST['department'] ?? '');
    $description = $_POST['description'] ?? '';
    $price = safe($_POST['price'] ?? '');
    $start_date = safe($_POST['start_date'] ?? '');
    $doctor_name = safe($_POST['doctor_name'] ?? 'Unknown');

    $stmt = $conn->prepare("INSERT INTO Courses (title, department, description, price, start_date, doctor_name, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $emptyImage = json_encode([]);
    $stmt->bind_param("sssssss", $title, $department, $description, $price, $start_date, $doctor_name, $emptyImage);
    $stmt->execute();
    $course_id = $stmt->insert_id;
    $stmt->close();

    // WebSocket notification
    try {
        $ws = new Client("ws://localhost:8080");
        $ws->send(json_encode([
            'type' => 'new_course',
            'data' => ['course_id'=>$course_id,'title'=>$title,'department'=>$department]
        ]));
        $ws->close();
    } catch(Exception $e){
        error_log("WebSocket Error: ".$e->getMessage());
    }

    echo json_encode(['status'=>'success','course_id'=>$course_id]);
    exit;
}

// ---------------------------
// 🔹 Input Handling
// ---------------------------
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? 'title';
$direction = strtoupper($_GET['direction'] ?? 'ASC');
$itemsPerPage = 6;
$offset = ($page-1)*$itemsPerPage;
$allowedSort = ['title','price','start_date'];
$sort = in_array($sort,$allowedSort)?$sort:'title';
$direction = in_array($direction,['ASC','DESC'])?$direction:'ASC';

// ---------------------------
// 🔹 Fetch Courses
// ---------------------------
$query = "SELECT * FROM Courses WHERE title LIKE ? ORDER BY $sort $direction LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);
$searchLike="%{$search}%";
$stmt->bind_param("sii",$searchLike,$itemsPerPage,$offset);
$stmt->execute();
$result = $stmt->get_result();
$courses=[];
while($row=$result->fetch_assoc()){
    if(!empty($row['image'])){
        $decoded=json_decode($row['image'],true);
        $row['image']=(json_last_error()===JSON_ERROR_NONE && is_array($decoded))?$decoded:[$row['image']];
    }else $row['image']=['default.jpg'];
    $courses[]=$row;
}
$stmt->close();

// ---------------------------
// 🔹 Count Total Courses
// ---------------------------
$countQuery="SELECT COUNT(*) AS total FROM Courses WHERE title LIKE ?";
$countStmt=$conn->prepare($countQuery);
$countStmt->bind_param("s",$searchLike);
$countStmt->execute();
$totalCourses=$countStmt->get_result()->fetch_assoc()['total'];
$totalPages=ceil($totalCourses/$itemsPerPage);
$countStmt->close();

// ---------------------------
// 🔹 Render Twig Template
// ---------------------------
echo $twig->render('courses_list.html.twig',[
    'courses'=>$courses,
    'page'=>$page,
    'totalPages'=>$totalPages,
    'search'=>$search,
    'sort'=>$sort
]);

// ---------------------------
// 🔹 WebSocket Frontend Script
// ---------------------------
echo '<script>
(function() {
    // --- Configuration ---
    const WS_URL = "ws://localhost:8080";
    const NOTIF_DURATION = 4000; // in milliseconds
    const DEFAULT_BG = "#28a745";

    // --- Create Notification Container ---
    const notificationContainer = document.createElement("div");
    Object.assign(notificationContainer.style, {
        position: "fixed",
        bottom: "15px",
        right: "15px",
        zIndex: 9999,
        display: "flex",
        flexDirection: "column",
        gap: "10px"
    });
    document.body.appendChild(notificationContainer);

    // --- Function to Show Notification ---
    function showNotification(message, bg = DEFAULT_BG) {
        const box = document.createElement("div");

        Object.assign(box.style, {
            background: bg,
            color: "white",
            padding: "12px 18px",
            borderRadius: "10px",
            fontWeight: "bold",
            boxShadow: "0 2px 6px rgba(0,0,0,0.3)",
            opacity: 0,
            transition: "opacity 0.3s ease"
        });

        box.textContent = message;
        notificationContainer.appendChild(box);

        // Fade-in
        requestAnimationFrame(() => box.style.opacity = 1);

        // Remove after duration
        setTimeout(() => {
            box.style.opacity = 0;
            setTimeout(() => box.remove(), 300); // wait for fade-out
        }, NOTIF_DURATION);
    }

    // --- Connect to WebSocket ---
    function connectWebSocket() {
        const ws = new WebSocket(WS_URL);

        ws.onopen = () => console.log("✅ Connected to WebSocket server");

        ws.onmessage = event => {
            try {
                const data = JSON.parse(event.data);
                if (data.type === "new_course") {
                    showNotification(`🎓 New course: ${data.data.title}`);
                } else if (data.type === "new_video") {
                    showNotification(`🎬 New video: ${data.data.filename}`);
                }
            } catch (err) {
                console.error("❌ Error parsing WebSocket message:", err);
            }
        };

        ws.onerror = err => console.error("❌ WebSocket Error:", err);

        ws.onclose = () => {
            console.warn("⚠️ WebSocket disconnected, retrying in 5s...");
            setTimeout(connectWebSocket, 5000); // reconnect
        };
    }

    // --- Initialize WebSocket ---
    connectWebSocket();
})();
</script>
';


$conn->close();
?>
