<?php
// Start output buffering to prevent TCPDF header errors
ob_start();

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Database credentials
$host = "localhost";
$dbname = "project";
$username = "root";
$password = "";

// Connect to DB
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    file_put_contents("error.txt", date('Y-m-d H:i:s') . " DB Connection Error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    exit("Database connection failed.");
}

// Get student ID
$studentId = intval($_GET['student_id'] ?? 0);
if ($studentId <= 0) exit("Invalid student ID.");

// Fetch student
try {
    $stmt = $pdo->prepare("SELECT payment_status, email, username FROM students WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$student) exit("Student not found.");

    // Update payment status if not already paid
    if ($student['payment_status'] !== 'paid') {
        $update = $pdo->prepare("UPDATE students SET payment_status = 'paid' WHERE id = :id");
        $update->execute([':id' => $studentId]);
        $student['payment_status'] = 'paid';
    }

    $studentName = htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8');
    $studentEmail = htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8');
} catch (Exception $e) {
    file_put_contents("error.txt", date('Y-m-d H:i:s') . " Payment Success Error: " . $e->getMessage() . "\n", FILE_APPEND);
    exit("Error processing payment.");
}

// ---------------- TCPDF Setup ----------------
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Document info
$pdf->SetCreator('E-learning App');
$pdf->SetAuthor('E-learning App');
$pdf->SetTitle('Payment Receipt');
$pdf->SetSubject('Payment Confirmation');
$pdf->SetKeywords('payment, receipt, student');

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Advanced PDF content with heredoc
$datetime = date('Y-m-d H:i:s');
$html = <<<HTML
<h1 style="color:green; text-align:center;">Payment Receipt</h1>
<hr>
<table cellpadding="5" cellspacing="0" border="1" width="100%">
<tr style="background-color:#d4f0d4;">
    <th>Field</th>
    <th>Details</th>
</tr>
<tr>
    <td><strong>Student Name</strong></td>
    <td>{$studentName}</td>
</tr>
<tr>
    <td><strong>Email</strong></td>
    <td>{$studentEmail}</td>
</tr>
<tr>
    <td><strong>Payment Status</strong></td>
    <td>Paid</td>
</tr>
<tr>
    <td><strong>Date</strong></td>
    <td>{$datetime}</td>
</tr>
</table>
<br>
<p style="text-align:center;">Thank you for your payment!</p>
<br>
<p style="text-align:center;">This receipt can be verified using the QR code below:</p>
HTML;

// Write HTML
$pdf->writeHTML($html, true, false, true, false, '');

// Add a QR code
$style = array(
    'border' => 1,
    'vpadding' => 'auto',
    'hpadding' => 'auto',
    'fgcolor' => array(0,0,0),
    'bgcolor' => false
);
$pdf->write2DBarcode("StudentID:{$studentId};Name:{$studentName};Email:{$studentEmail};Date:{$datetime}", 'QRCODE,H', 75, 120, 60, 60, $style, 'N');

// Save PDF to file
$receiptDir = __DIR__ . "/receipts";
if (!is_dir($receiptDir)) mkdir($receiptDir, 0755, true);
$receiptFile = "{$receiptDir}/receipt_{$studentId}.pdf";
$pdf->Output($receiptFile, 'F'); // Save to file

// Clear output buffer
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Payment successful page for students. Thank you for completing your payment.">
<title>Payment Successful</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="flex items-center justify-center min-h-screen bg-gradient-to-br from-gray-900 to-green-900 text-white">

<main class="bg-gray-900 bg-opacity-80 p-10 rounded-3xl shadow-2xl text-center max-w-lg w-[90%] border border-green-700">
<header>
    <svg class="mx-auto mb-6 text-green-400 animate-bounce" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="90" height="90">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
    </svg>
    <h1 class="text-4xl font-extrabold mb-4 text-green-400 drop-shadow-lg">Payment Successful</h1>
</header>

<section>
    <p class="text-lg mb-3 text-gray-200">Thank you, <span class="text-green-400 font-semibold"><?= $studentName ?></span>!</p>
    <p class="text-gray-300 mb-6">Your payment has been successfully processed.</p>

    <div class="bg-gray-800 p-4 rounded-xl shadow-inner mb-6 border border-gray-700">
        <p class="text-sm text-gray-400">Download your payment receipt:</p>
        <a href="receipts/receipt_<?= $studentId ?>.pdf" class="text-green-400 font-bold underline mt-1 inline-block" target="_blank">Download PDF Receipt</a>
    </div>

    <a href="main.php" class="bg-green-600 hover:bg-green-700 px-8 py-3 rounded-full font-bold text-white shadow-md transition duration-300">
        Return to Dashboard
    </a>
</section>
</main>
</body>
</html>
