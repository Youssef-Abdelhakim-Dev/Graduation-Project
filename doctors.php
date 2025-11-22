<?php

session_start();
$host = "localhost";
$dbname = "project";
$username = "root";
$password = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
// Fetch all doctors from the database
$stmt = $pdo->query("SELECT * FROM doctors");
$doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Fetch all doctors
$doctorStmt = $pdo->query("SELECT COUNT(*) AS total_doctors FROM doctors");
$doctorCount = $doctorStmt->fetch(PDO::FETCH_ASSOC)['total_doctors'];

// Debugging: Check if $doctors has data
if (empty($doctors)) {
    echo "<p class='text-center text-red-500'>No doctors found.</p>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Doctors</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-500 to-purple-600 p-6">
    <section class="w-full max-w-6xl text-center">
        <input type="text" id="searchInput" onkeyup="filterDoctors()" 
            class="w-full max-w-md px-4 py-2 mb-6 text-black rounded-md shadow-md" 
            placeholder="🔍 Search Doctors by Name...">
        
        <h2 class="text-4xl font-extrabold text-white drop-shadow-lg mb-8">📩 Available Doctors</h2>
        
        <div class="mb-4 p-4 bg-blue-100 text-blue-800 font-bold text-lg rounded-lg shadow-md">
            Total Doctors: <?php echo count($doctors); ?>
        </div>
        <!-- Doctor List in Table -->
        <h2 class="text-3xl font-bold text-white mt-10">📋 Doctor Details Table</h2>
        <div class="overflow-x-auto mt-6">
        <table class="w-full table-auto bg-white rounded-lg shadow-md overflow-hidden">
    <thead class="bg-blue-800 text-white">
        <tr>
            <th class="p-3">Doctor Name</th>
            <th class="p-3">Specialization</th>
            <th class="p-3">Phone</th>
            <th class="p-3">Email</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($doctors as $doctor): ?>
            <tr class="border-b hover:bg-gray-100">
                <td class="p-3">👨‍⚕️
                    <a href="doctorDetails.php?name=<?php echo urlencode($doctor['name']); ?>" 
                       class="text-blue-600 underline">
                        <?php echo htmlspecialchars($doctor['name'] ?? 'Unknown'); ?>
                    </a>
                </td>
                <td class="p-3">
    🏥 
    <a href="specialization.php?subject=<?php echo urlencode($doctor['subject'] ?? ''); ?>" 
       class="text-blue-600 underline hover:text-blue-800 transition duration-300">
        <?php echo htmlspecialchars($doctor['subject'] ?? 'Unknown'); ?>
    </a>
</td>

                <td class="p-3">
    📞 
    <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $doctor['phone_number'] ?? ''); ?>" 
       class="text-blue-600 underline hover:text-blue-800 transition duration-300" 
       target="_blank">
        <?php echo htmlspecialchars($doctor['phone_number'] ?? 'Unknown'); ?>
    </a>
</td>

<td class="p-3">
    ✉️ 
    <a href="mailto:<?php echo htmlspecialchars($doctor['email'] ?? ''); ?>" 
       class="text-blue-600 underline hover:text-blue-800 transition duration-300">
        <?php echo htmlspecialchars($doctor['email'] ?? 'Unknown'); ?>
    </a>
</td>

            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

        </div>
    </section>
    
    <script>
        function filterDoctors() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let cards = document.getElementsByClassName("doctor-card");
            let rows = document.querySelectorAll("tbody tr");
            
            for (let i = 0; i < cards.length; i++) {
                let name = cards[i].querySelector("h3").textContent.toLowerCase();
                cards[i].style.display = name.includes(input) ? "block" : "none";
            }
            
            rows.forEach(row => {
                let name = row.children[0].textContent.toLowerCase();
                row.style.display = name.includes(input) ? "table-row" : "none";
            });
        }
    </script>
</body>
</html>