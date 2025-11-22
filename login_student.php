<?php
session_start();

use Twilio\Rest\Client;
use Dotenv\Dotenv;
use Stripe\Stripe;
use Stripe\Checkout\Session;

require __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database config
$host = "localhost";
$dbname = "project";
$username = "root";
$password = "";

// Rate Limiting
$RATE_LIMIT_MAX_REQUESTS = 3;
$RATE_LIMIT_TIME_WINDOW = 60;
$RATE_LIMIT_FILE = __DIR__ . '/rate_limit.json';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$now = time();
$rateData = file_exists($RATE_LIMIT_FILE) ? json_decode(file_get_contents($RATE_LIMIT_FILE), true) : [];

if (!isset($rateData[$clientIP])) {
    $rateData[$clientIP] = ['count' => 1, 'start' => $now];
} else {
    $elapsed = $now - $rateData[$clientIP]['start'];
    if ($elapsed > $RATE_LIMIT_TIME_WINDOW) {
        $rateData[$clientIP] = ['count' => 1, 'start' => $now];
    } else {
        if ($rateData[$clientIP]['count'] >= $RATE_LIMIT_MAX_REQUESTS) {
            header('HTTP/1.1 429 Too Many Requests');
            echo json_encode(["status" => "error", "message" => "Rate limit exceeded. Try again later."]);
            exit;
        }
        $rateData[$clientIP]['count']++;
    }
}
file_put_contents($RATE_LIMIT_FILE, json_encode($rateData, JSON_PRETTY_PRINT));

// Secrets
$stripeSecretKey = $_ENV['STRIPE_SECRET_KEY'];
$twilioSid = $_ENV['TWILIO_SID'];
$twilioAuth = $_ENV['TWILIO_TOKEN'];
$twilioNumber = $_ENV['TWILIO_FROM'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $name = trim($_POST['student_name']);
        $email = trim($_POST['student_email']);
        $phone = trim($_POST['student_phone']);
        $year = trim($_POST['student_year']);
        $password = password_hash(trim($_POST['student_password']), PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ? OR username = ? OR phone = ?");
        $stmt->execute([$email, $name, $phone]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "error", "message" => "Student already exists."]);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO students (username, email, phone, year, password, payment_status)
                               VALUES (:username, :email, :phone, :year, :password, 'pending')");
        $stmt->execute([
            ':username' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':year' => $year,
            ':password' => $password
        ]);
        $studentId = $pdo->lastInsertId();

        Stripe::setApiKey($stripeSecretKey);
        $checkout = Session::create([
            'payment_method_types' => ['card'],
            'customer_email' => $email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'egp',
                    'product_data' => ['name' => "Student Registration - $name"],
                    'unit_amount' => 5000,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => "http://localhost/projectGraduation/payment_success.php?student_id=" . urlencode($studentId),
            'cancel_url' => "http://localhost/projectGraduation/payment_failed.php?student_id=" . urlencode($studentId),
        ]);

        try {
            $twilio = new Client($twilioSid, $twilioAuth);
            $whatsappTo = 'whatsapp:+20' . ltrim($phone, '0');
            $twilio->messages->create(
                $whatsappTo,
                [
                    'from' => $twilioNumber,
                    'body' => "👋 Hello $name! Your registration was successful. Complete your payment here: {$checkout->url}"
                ]
            );
        } catch (Exception $e) {
            file_put_contents("error.txt", date('Y-m-d H:i:s') . " - Twilio Error: " . $e->getMessage() . "\n", FILE_APPEND);
        }

        echo json_encode([
            "status" => "success",
            "message" => "Redirecting to payment...",
            "checkout_url" => $checkout->url
        ]);
        exit;
    }
} catch (Exception $e) {
    file_put_contents("error.txt", date('Y-m-d H:i:s') . " - Server Error: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(["status" => "error", "message" => "Server connection failed."]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Login</title>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link href="/projectGraduation/styles/login_student.css" rel="stylesheet">
<link rel="icon" href="student.png" type="image/png">

</head>

<body class="min-h-screen flex items-center justify-center">
<main class="container-login-student bg-white shadow-2xl rounded-2xl p-8 w-full max-w-md" role="main">

    <!-- PROGRESS BAR -->
    <nav class="progress-wrapper" aria-label="Registration Progress">
        <div class="progress-line"></div>

        <div class="progress-step-item">
            <div class="circle active" id="pc1" aria-current="step">1</div>
            <p class="label">Login</p>
        </div>

        <div class="progress-step-item">
            <div class="circle" id="pc2">2</div>
            <p class="label">Details</p>
        </div>

        <div class="progress-step-item">
            <div class="circle" id="pc3">3</div>
            <p class="label">Upload</p>
        </div>

        <div class="progress-step-item">
            <div class="circle" id="pc4">4</div>
            <p class="label">Finish</p>
        </div>
    </nav>

    <h1 class="text-3xl font-bold text-center mb-6 text-purple-600">Student Registration</h1>

    <form id="student-form" method="post" enctype="multipart/form-data" novalidate>

        <!-- STEP 1 -->
        <section class="step active" id="step1" aria-labelledby="step1-title">
            <h2 id="step1-title" class="sr-only">Login Step</h2>
            <div class="mb-3">
                <label for="student_email" class="sr-only">Email</label>
                <input type="email" id="student_email" name="student_email" placeholder="Enter your email" required class="input-field">
            </div>
            <div class="mb-3">
                <label for="student_password" class="sr-only">Password</label>
                <input type="password" id="student_password" name="student_password" placeholder="Enter your password" required class="input-field">
            </div>
            <button type="button" class="btn-submit w-full mt-4 bg-purple-600 text-white py-2 rounded" onclick="nextStep()">Next</button>
        </section>

        <!-- STEP 2 -->
        <section class="step" id="step2" aria-labelledby="step2-title">
            <h2 id="step2-title" class="sr-only">Student Details Step</h2>

            <div class="mb-3">
                <label for="student_name" class="sr-only">Full Name</label>
                <input type="text" id="student_name" name="student_name" placeholder="Enter your full name" required class="input-field">
            </div>
            <div class="mb-3">
                <label for="student_year" class="sr-only">Academic Year</label>
                <input type="number" id="student_year" name="student_year" placeholder="Enter your academic year" required class="input-field">
            </div>
            <div class="mb-3">
                <label for="student_phone" class="sr-only">Phone Number</label>
                <input type="tel" id="student_phone" name="student_phone" placeholder="Enter your phone number" required class="input-field">
            </div>

            <div class="flex justify-between mt-4">
                <button type="button" class="bg-gray-300 px-4 py-2 rounded" onclick="prevStep()">Back</button>
                <button type="button" class="bg-purple-600 text-white px-4 py-2 rounded" onclick="nextStep()">Next</button>
            </div>
        </section>

        <!-- STEP 3 -->
        <section class="step" id="step3" aria-labelledby="step3-title">
            <h2 id="step3-title" class="sr-only">Upload Step</h2>

            <div class="mb-3">
                <label for="student_image" class="upload-btn block cursor-pointer bg-gray-200 py-2 rounded text-center" role="button" aria-label="Upload Image">
                    📸 Upload Image
                </label>
                <input type="file" id="student_image" name="student_image" accept="image/*" onchange="previewImage(event, 'image-preview')" hidden>
                <div id="image-preview" class="mt-2 flex flex-wrap justify-center"></div>
            </div>

            <div class="flex justify-between mt-4">
                <button type="button" class="bg-gray-300 px-4 py-2 rounded" onclick="prevStep()">Back</button>
                <button type="button" class="bg-purple-600 text-white px-4 py-2 rounded" onclick="nextStep()">Next</button>
            </div>
        </section>

        <!-- STEP 4 -->
        <section class="step" id="step4" aria-labelledby="step4-title">
            <h2 id="step4-title" class="sr-only">Finish Step</h2>
            <p class="text-center text-gray-600 mb-4">Review your information and continue to payment.</p>

            <div class="flex justify-between">
                <button type="button" class="bg-gray-300 px-4 py-2 rounded" onclick="prevStep()">Back</button>
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Submit</button>
            </div>
        </section>

    </form>

</main>


<script>
// -------------------------
// Multi-Step Form Logic
// -------------------------
const steps = document.querySelectorAll(".step");
const progressCircles = document.querySelectorAll(".circle");
const progressLine = document.querySelector(".progress-line");
const form = document.getElementById("student-form");
let currentStep = 0;

// Initialize
updateStep();

// Next Step
function nextStep() {
    if (!validateStep(currentStep)) return; // optional validation

    if (currentStep < steps.length - 1) {
        steps[currentStep].classList.remove("active");
        currentStep++;
        steps[currentStep].classList.add("active");
        updateStep();
        scrollToStep();
    } else {
        form.submit(); // submit if last step
    }
}

// Previous Step
function prevStep() {
    if (currentStep > 0) {
        steps[currentStep].classList.remove("active");
        currentStep--;
        steps[currentStep].classList.add("active");
        updateStep();
        scrollToStep();
    }
}

// Update Progress Bar & Circles
function updateStep() {
    progressCircles.forEach((circle, i) => {
        circle.classList.toggle("active", i <= currentStep);
    });
    progressLine.style.setProperty("--step", currentStep + 1);
    
    // Update buttons text
    const nextBtn = document.querySelector("#nextBtn");
    if(nextBtn) nextBtn.textContent = currentStep === steps.length - 1 ? "Submit" : "Next";
}

// Smooth Scroll to Active Step
function scrollToStep() {
    steps[currentStep].scrollIntoView({ behavior: "smooth", block: "center" });
}

// Validate Step (optional)
function validateStep(stepIndex) {
    const inputs = steps[stepIndex].querySelectorAll("input[required], textarea[required], select[required]");
    for (const input of inputs) {
        if (!input.value) {
            input.focus();
            Swal.fire({ icon:'warning', title:'Required', text:'Please fill all fields.'});
            return false;
        }
    }
    return true;
}

// -------------------------
// Image/File Preview
// -------------------------
function previewImage(event, previewId) {
    const output = document.getElementById(previewId);
    const files = event.target.files;
    output.innerHTML = ''; // clear previous

    if (!files.length) return;

    Array.from(files).forEach(file => {
        if(!['image/jpeg','image/png','image/gif','image/webp'].includes(file.type)) {
            alert('Invalid image format: ' + file.name);
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const img = document.createElement('img');
            img.src = reader.result;
            img.style.maxWidth = '100px';
            img.style.marginRight = '8px';
            img.style.borderRadius = '8px';
            output.appendChild(img);
        };
        reader.readAsDataURL(file);
    });
}

// -------------------------
// Submit Form with Fetch
// -------------------------
form.addEventListener("submit", async e => {
    e.preventDefault();
    const formData = new FormData(form);

    try {
        const res = await fetch(form.action || "", { method: "POST", body: formData });
        const data = await res.json();

        if (data.status === "success") {
            Swal.fire({ icon: 'success', title: 'Success', text: data.message }).then(() => {
            
                window.location.href = data.checkout_url;
            });
        } else {
            Swal.fire({ icon:'error', title:'Error', text:data.message });
        }
    } catch (err) {
        Swal.fire({ icon:'error', title:'Error', text:'Failed to connect to server.' });
        console.error(err);
    }
});

// -------------------------
// Keyboard Navigation (Enter Key)
// -------------------------
document.addEventListener("keydown", (e) => {
    if(e.key === "Enter" && currentStep < steps.length) {
        e.preventDefault();
        nextStep();
    }
});

</script>

</body>
</html>
