<?php
// =======================================================
// BACKEND SECTION — HANDLE JSON API MODE
// =======================================================

$isApiRequest = isset($_GET['json']);

if ($isApiRequest) {
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: *");
    header("Access-Control-Allow-Methods: GET");
}

include 'connect.php';
$conn->set_charset("utf8mb4");

// Clean SQL (removed unnecessary fields)
$sql = "SELECT id, name, description, doctor_name, file_path FROM `files-lectures` ORDER BY id DESC";
$result = $conn->query($sql);

$data = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $path = $row['file_path'];
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $data[] = [
            'id'          => (int)$row['id'],
            'name'        => $row['name'],
            'description' => $row['description'],
            'doctor'      => $row['doctor_name'],
            'path'        => $path,
            'type'        => $ext,
        ];
    }
}

$total_files = count($data);

$conn->close();

// API MODE → return JSON only
if ($isApiRequest) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lectures files</title>

<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/font-awesome/css/font-awesome.min.css">

<style>
    .skeleton {
        background: linear-gradient(90deg,#cccccc 25%,#e0e0e0 50%,#cccccc 75%);
        background-size: 200% 100%;
        animation: shine 1.2s infinite;
    }
    @keyframes shine {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

</head>

<body class="bg-gray-100 dark:bg-gray-900">

<!-- ================= HEADER ================= -->
<header class="py-10 text-center bg-gradient-to-r from-blue-600 via-indigo-700 to-purple-700 text-white shadow-xl">
    <h1 class="text-5xl font-extrabold mb-2"> Lecutres files</h1>
</header>

<main class="container mx-auto mt-12">

    <!-- Total Files -->
    <section class="text-center mb-8">
        <p class="bg-green-600 text-white font-bold text-xl inline-block px-6 py-3 rounded-lg shadow-lg">
            Total Files: <span class="text-yellow-300"><?= $total_files ?></span>
        </p>
    </section>

    <!-- Search -->
    <section class="text-center mb-10">
        <input 
            id="search-input"
            type="search"
            placeholder="Search for lecture name..."
            class="w-3/4 md:w-1/2 p-3 border rounded-xl shadow focus:ring-4 focus:ring-blue-400"
        >
    </section>

    <!-- lecture List -->
    <section>
        <div id="lecture-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8"></div>
    </section>

    <nav id="pagination" class="flex justify-center gap-2 mt-10"></nav>

</main>

<footer class="bg-gray-900 text-white text-center py-8 mt-12">
    © 2024 University — All rights reserved
</footer>

<!-- ===================== WEB WORKER ===================== -->
<script>
const worker = new Worker("fetch_files.js");
</script>

<!-- ===================== FRONTEND OOP ===================== -->
<script src="/projectGraduation/scripts/lectures_files.js">

</script>

</body>
</html>
