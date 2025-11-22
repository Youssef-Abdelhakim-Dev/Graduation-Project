<?php
// teacher_dashboard.php
// Handle AJAX deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['teacher_id'])) {
    header('Content-Type: application/json');
    $teacherId = intval($_POST['teacher_id']);
    $response = ['success' => false, 'message' => 'Invalid request'];

    try {
        $conn = new mysqli('localhost', 'root', '', 'project');
        if ($conn->connect_error) throw new Exception($conn->connect_error);

        $stmt = $conn->prepare("DELETE FROM doctors WHERE id=?");
        if (!$stmt) throw new Exception($conn->error);

        $stmt->bind_param('i', $teacherId);
        if (!$stmt->execute()) throw new Exception($stmt->error);

        $response['success'] = true;
        $response['message'] = "Teacher deleted successfully";

        $stmt->close();
        $conn->close();
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Teacher Dashboard</title>

<!-- TailwindCSS, FontAwesome, Animate.css, SweetAlert2 -->
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css/animate.min.css">

<style>
/* ================= Gradient Background ================= */
#gradient-section {
    position: relative;
    width: 100%;
    height: 90vh;
    background: linear-gradient(120deg,#6EE7B7,#3B82F6,#9333EA);
    background-size: 300% 300%;
    animation: gradientShift 15s ease infinite;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    overflow: hidden;
}
#gradient-section::before {
    content:"";
    position:absolute; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.4);
}
@keyframes gradientShift {
    0%{background-position:0% 50%;}
    50%{background-position:100% 50%;}
    100%{background-position:0% 50%;}
}

/* ================= Buttons ================= */
#button-container a {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(90deg,#38bdf8,#6366f1,#f472b6);
    background-size: 200% 200%;
    color: white;
    font-weight: 600;
    padding: 0.75rem 1.5rem;
    border-radius: 1rem;
    box-shadow: 0 10px 20px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
    transform-style: preserve-3d;
}
#button-container a:hover {
    transform: rotateX(10deg) rotateY(10deg) scale(1.05);
    background-position: 100% 0;
    box-shadow: 0 15px 25px rgba(0,0,0,0.4);
}

/* ================= Circular Progress ================= */
.circular-progress {
    position: fixed;
    bottom: 2rem;
    right: 2rem;
    width: 120px;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 50;
}
.circular-progress svg circle.bg {
    fill: none;
    stroke: #1e293b;
    stroke-width: 12;
}
.circular-progress svg circle.progress {
    fill: none;
    stroke: url(#grad);
    stroke-width: 12;
    stroke-linecap: round;
    transform: rotate(-90deg);
    transform-origin: 50% 50%;
    transition: stroke-dashoffset 0.3s linear;
}
.circular-progress .percentage {
    position: absolute;
    font-size: 1.2rem;
    font-weight: bold;
    color: white;
}

/* ================= Top-right Logo ================= */
#top-right-image {
    position: fixed;
    top: 1rem;
    right: 1rem;
    width: 80px;
    height: 80px;
    border-radius: 50%;
    box-shadow: 0 10px 20px rgba(0,0,0,0.5);
    transition: transform 0.3s ease;
}
#top-right-image:hover {

    transform: scale(1.1) rotate(5deg);
}

.circle-laoder {

    border:6px solid rgba(255,255,255,0.2);
    border-top-color:#38bdf8;
    border-radius:50%;
    margin:auto;
    animation:spin 1s linear infinite;
}

/* ================= Loader Spin ================= */
@keyframes spin {
    100% {
        transform: rotate(360deg);
        }}
</style>
</head>
<body class="bg-gray-900 text-white">
<!-- ================= Gradient Section ================= -->
<section id="gradient-section">
    <!-- Main Heading -->
    <h1 class="text-5xl font-extrabold animate__animated animate__fadeInDown">
        Welcome to Zagazig University
    </h1>

    <!-- Subheading -->
    <p class="text-xl mt-4 animate__animated animate__fadeInUp">
        Faculty of Science – Explore our courses and departments
    </p>

    <!-- Buttons -->
    <div id="button-container" class="mt-10 flex flex-wrap justify-center gap-6">
        <a href="index.php" target="_blank" aria-label="Go to Home Page">
            <i class="fas fa-home"></i> Home
        </a>
        <a onclick="confirmLogout()" aria-label="Log out of Teacher Dashboard">
            <i class="fas fa-user-slash"></i> Log out
        </a>
    </div>

   
</section>

<!-- ================= Logo ================= -->
<img id="top-right-image" src="zagazig.png" alt="Zagazig University Logo" width="80" height="80" loading="lazy">

    
<script>
// ================= Delete Teacher =================
async function confirmLogout(teacherId) {
    try {
        const audio = new Audio('logout.mp3');

        const { isConfirmed } = await Swal.fire({
            title: '<i class="fas fa-user-slash text-red-400"></i> Delete Teacher',
            html: `<progress id="deleteProgress" max="100" value="0" style="width:100%;height:12px;"></progress>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete!',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                const progress = document.getElementById('deleteProgress');
                let val = 0;
                const interval = setInterval(() => {
                    if (val >= 100) clearInterval(interval);
                    val += 2;
                    progress.value = val;
                }, 40);
            }
        });

        if (!isConfirmed) return;

        audio.play();

        // Show loader while processing
        Swal.fire({
            title: 'Deleting Teacher...',
            html: `<div class="circle-loader">/div>`,
            showConfirmButton: false,
            allowOutsideClick: false,
            didOpen: () => {
                const perc = document.querySelector('.percentage');
                let percentage = 0;
                const interval = setInterval(() => {
                    if (percentage >= 100) clearInterval(interval);
                    else { percentage++; perc.textContent = `${percentage}%`; }
                }, 30);
            }
        });

        // AJAX + JSON deletion
        const formData = new URLSearchParams();
        formData.append('teacher_id', teacherId);
        const res = await fetch('', { method: 'POST', body: formData });
        const data = await res.json();

        if (data.success) {
            Swal.fire({ icon: 'success', title: 'Deleted!', text: data.message, timer: 2000, showConfirmButton: false });
            document.querySelector(`#teacher-${teacherId}`)?.remove();
            location.href = 'welcome.php';
        } else {
            Swal.fire({ icon: 'error', title: 'Error!', text: data.message });
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Exception!', text: err.message });
    }
}

// ================= Animate Circular Progress =================
const svgProgress = document.querySelector('.progress');
const svgPercent = document.getElementById('svgPercentage');
const dashOffset = 283;
let percentage = 0;

setInterval(() => {
    if (percentage <= 100) {
        const offset = dashOffset - (dashOffset * percentage / 100);
        svgProgress.style.strokeDashoffset = offset;
        svgPercent.textContent = percentage + '%';
        percentage++;
    }
}, 50);
</script>

</body>
</html>
