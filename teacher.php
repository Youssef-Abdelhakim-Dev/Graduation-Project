<?php
require "teacherHeader.php";

$name = $email = "";
$params = "name=$name&email=$email";

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Advanced Teacher Dashboard</title>

<!-- Tailwind CSS -->
<script src="https://cdn.tailwindcss.com"></script>
<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<!-- GSAP -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
<!-- Tippy.js -->
<script src="https://unpkg.com/@tippyjs/core@6.3.1/dist/tippy-bundle.iife.min.js"></script>
<!-- SweetAlert -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* Custom 3D hover */
  .icon-3d {
    perspective: 1000px;
  }
  .icon-3d-inner {
    transition: transform 0.3s ease;
    transform-style: preserve-3d;
  }
  .icon-3d:hover .icon-3d-inner {
    transform: rotateY(15deg) rotateX(10deg) scale(1.1);
  }
  .gradient-card {
    background: linear-gradient(135deg, #6EE7B7, #3B82F6, #9333EA);
    background-size: 400% 400%;
    animation: gradientShift 10s ease infinite;
  }
  @keyframes gradientShift {
    0% {background-position: 0% 50%;}
    50% {background-position: 100% 50%;}
    100% {background-position: 0% 50%;}
  }
</style>
</head>

<body class="bg-gray-900 text-white font-sans">

<?php 
$name = $_GET['name'] ?? 'Unknown';
$email = $_GET['email'] ?? 'no email';
$phone = $_GET['phone'] ?? 'no phone';
$subject = $_GET['subject'] ?? 'no subject';
$year = $_GET['year'] ?? 'no year';
?>

<!-- Floating Profile & Dashboard -->
<div class="fixed top-4 left-4 z-50">
    <div class="flex items-center gap-4">
        <img src="<?= !empty($image) ? $image : 'person.png'; ?>" alt="Profile"
             class="w-14 h-14 rounded-full border-4 border-green-400 cursor-pointer shadow-lg hover:scale-110 transition-transform"
             onclick="toggleDashboard()">
        <div id="dashboard" class="hidden bg-gray-800 bg-opacity-90 p-5 rounded-xl shadow-2xl w-80 space-y-3">
            <h2 class="text-lg font-bold text-gradient">👤 Name: <span class="text-blue-400"><?= $name; ?></span></h2>
            <p>📧 Email: <span class="text-blue-400"><?= $email; ?></span></p>
            <p>📞 Phone: <span class="text-blue-400"><?= $phone; ?></span></p>
            <p>📆 Year: <span class="text-blue-400"><?= $year; ?></span></p>
            <p>📚 Subject: <span class="text-blue-400"><?= $subject; ?></span></p>
            <button onclick="toggleDashboard()" class="w-full bg-red-500 py-2 rounded hover:bg-red-600 transition-all">Close</button>
        </div>
    </div>
</div>

<!-- Icon Dashboard Grid -->
<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6 p-6 mt-24">

<?php
$links = [
    ['href'=>"add_lecture.php?$params", 'icon'=>'fa-plus-circle', 'text'=>'Add Files'],
    ['href'=>"coursesDescription.php?$params", 'icon'=>'fa-book-open', 'text'=>'Add Courses'],
    ['href'=>"uploadLectures.php?$params", 'icon'=>'fa-chalkboard-teacher', 'text'=>'Add Lectures'],
    ['href'=>"exams.php?$params", 'icon'=>'fa-pencil-alt', 'text'=>'Add Exams'],
    ['href'=>"enrolled.php", 'icon'=>'fa-users', 'text'=>'Students']
];

foreach ($links as $link) {
    echo '<a href="'.$link['href'].'" class="icon-3d">
            <div class="icon-3d-inner gradient-card flex flex-col items-center justify-center p-5 rounded-xl shadow-lg text-center">
                <i class="fas '.$link['icon'].' text-4xl mb-2"></i>
                <span class="font-semibold text-white">'.$link['text'].'</span>
            </div>
          </a>';
}
?>

</div>

<!-- Image Section -->
<div class="flex justify-center mt-16">
    <figure class="overflow-hidden rounded-2xl shadow-2xl transform hover:scale-105 transition duration-500">
        <img src="science.jpg" alt="science" class="w-full max-w-lg h-auto object-cover">
        <figcaption class="text-center mt-2 text-gray-200 font-semibold text-lg">Faculty of Science</figcaption>
    </figure>
</div>

<!-- Footer -->
<footer class="mt-16 bg-gray-800 text-gray-200 py-6 text-center space-y-4">
    <p>© 2025. All Rights Reserved.</p>
    <div class="flex justify-center gap-4 text-xl">
        <a href="#" class="hover:text-blue-500 transition"><i class="fab fa-facebook-f"></i></a>
        <a href="#" class="hover:text-blue-400 transition"><i class="fab fa-linkedin-in"></i></a>
        <a href="mailto:info@zu.edu.eg" class="hover:text-red-500 transition"><i class="fas fa-envelope"></i></a>
        <a href="#" class="hover:text-red-600 transition"><i class="fab fa-youtube"></i></a>
    </div>
</footer>

<script>
function toggleDashboard() {
    document.getElementById('dashboard').classList.toggle('hidden');
}

// Floating effect for icons
gsap.to(".icon-3d-inner", {
    y: 10,
    repeat: -1,
    yoyo: true,
    duration: 2,
    ease: "sine.inOut",
    stagger: 0.1
});

// Fade-in on scroll
gsap.from(".icon-3d-inner", {
    scrollTrigger: {
        trigger: ".icon-3d-inner",
        start: "top 80%",
    },
    opacity: 0,
    y: 50,
    duration: 1,
    stagger: 0.1
});
</script>
</body>
</html>
