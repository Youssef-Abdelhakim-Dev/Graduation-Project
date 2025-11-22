<?php
require __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$host = "localhost";
$dbname = "project";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    file_put_contents("error.txt", date('Y-m-d H:i:s') . " DB Connection Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    exit("Database connection failed.");
}

$studentId = intval($_GET['student_id'] ?? 0);
if ($studentId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE students SET payment_status = 'failed' WHERE id = :id");
        $stmt->execute([':id' => $studentId]);
    } catch (Exception $e) {
        file_put_contents("error.txt", date('Y-m-d H:i:s') . " Payment Failed Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="Payment failed page for students. Your payment could not be processed.">
<title>Payment Failed</title>

<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel='stylesheet' href='/projectGraduation/styles/payment_failed.css'>
</head>
<body class="bg-gradient-to-br from-red-900 to-gray-900 flex items-center justify-center min-h-screen text-white">

<main class="bg-gray-800 p-10 rounded-2xl shadow-2xl text-center animate-fadeInDown max-w-lg w-full">
    <header>
        <!-- Red Cross Icon -->
        <svg class="mx-auto mb-4 text-red-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="80" height="80">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <h1 class="text-3xl font-extrabold mb-2">Payment Failed</h1>
    </header>

    <section>
        <p class="text-lg mb-4">We could not complete your payment. Please try again or contact support.</p>
        <p class="text-gray-300 mb-6">If you already made the payment, it may take some time to reflect in the system. Please check your account again later.</p>
    </section>

    <footer>
        <a href="login_student.php" class="bg-red-600 hover:bg-red-700 px-6 py-3 rounded-lg font-bold shadow-md transition">
            Return to Payment
        </a>
    </footer>
</main>

</body>
</html>
