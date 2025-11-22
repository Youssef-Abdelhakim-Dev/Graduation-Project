<?php
// Database Connection
$host = "localhost";  // Change if necessary
$username = "root";   // Your DB username
$password = "";       // Your DB password
$database = "project"; // Database name

try {
    $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "<div style='padding: 12px; background-color: #dc3545; color: white; text-align: center; 
                    border-radius: 8px; font-weight: bold; font-size: 18px; box-shadow: 2px 2px 12px rgba(0, 0, 0, 0.2);'>
                    ❌ Connection failed: " . $e->getMessage() . "</div>";
    exit();
}

// Get page number for pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Fetch Students from Database with Pagination
$stmt = $conn->prepare("SELECT id, username, phone, email FROM students LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Total Student Count
$countStmt = $conn->query("SELECT COUNT(*) AS total_students FROM students");
$studentCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total_students'];

// Calculate total pages for pagination
$total_pages = ceil($studentCount / $records_per_page);

$conn = null; // Close the database connection
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students List</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-blue-600 to-purple-700 p-6">

    <!-- 📢 Students Count Display -->
    <div class="mb-6 p-4 bg-white text-blue-800 font-bold text-lg rounded-lg shadow-lg">
        🎓 Total Students: <span class="text-blue-600"><?php echo $studentCount; ?></span>
    </div>

    <!-- 🔍 Search Bar -->
    <input type="text" id="searchInput" onkeyup="filterStudents()" 
        class="w-full max-w-md px-4 py-2 mb-6 text-gray-800 rounded-md shadow-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
        placeholder="🔎 Search Students by Name, Phone, or Email...">

    <!-- 🏫 Student List Table -->
    <div class="w-full max-w-5xl bg-white shadow-2xl rounded-lg overflow-hidden">
        <table id="studentsTable" class="w-full border-collapse">
            <thead class="bg-blue-500 text-white text-lg">
                <tr>
                    <th class="py-3 px-5 text-left cursor-pointer" onclick="sortTable(0)">ID</th>
                    <th class="py-3 px-5 text-left cursor-pointer" onclick="sortTable(1)">Username</th>
                    <th class="py-3 px-5 text-left cursor-pointer" onclick="sortTable(2)">Phone</th>
                    <th class="py-3 px-5 text-left cursor-pointer" onclick="sortTable(3)">Email</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $student): ?>
                        <tr class="border-b hover:bg-blue-50 transition">
                            <td class="py-3 px-5"><?php echo $student['id']; ?></td>
                            <td class="py-3 px-5 font-semibold"><?php echo htmlspecialchars($student['username']); ?></td>
                            <td class="py-3 px-5"><?php echo htmlspecialchars($student['phone']); ?></td>
                            <td class="py-3 px-5"><?php echo htmlspecialchars($student['email']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="py-4 text-center text-gray-500">No students found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 📄 Pagination -->
    <div class="mt-6">
        <nav>
            <ul class="flex space-x-4">
                <li><a href="?page=1" class="text-blue-600">First</a></li>
                <li><a href="?page=<?php echo max(1, $page - 1); ?>" class="text-blue-600">Previous</a></li>
                <li><a href="?page=<?php echo min($total_pages, $page + 1); ?>" class="text-blue-600">Next</a></li>
                <li><a href="?page=<?php echo $total_pages; ?>" class="text-blue-600">Last</a></li>
            </ul>
        </nav>
    </div>

    <script>
        // 🔍 Filter Students in Table
        function filterStudents() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let table = document.getElementById("studentsTable"); 
            let rows = table.getElementsByTagName("tr");

            for (let i = 1; i < rows.length; i++) { // Start from 1 to skip the header
                let cells = rows[i].getElementsByTagName("td");
                if (cells) {
                    let username = cells[1].textContent.toLowerCase();
                    let phone = cells[2].textContent.toLowerCase();
                    let email = cells[3].textContent.toLowerCase();

                    // Check if any cell matches the search query
                    rows[i].style.display = username.includes(input) || phone.includes(input) || email.includes(input) ? "" : "none";
                }
            }
        }

        // Sort Table by Column
        function sortTable(columnIndex) {
            let table = document.getElementById("studentsTable");
            let rows = Array.from(table.getElementsByTagName("tr")).slice(1); // Exclude header row

            rows.sort((a, b) => {
                let cellA = a.getElementsByTagName("td")[columnIndex].textContent.toLowerCase();
                let cellB = b.getElementsByTagName("td")[columnIndex].textContent.toLowerCase();
                return cellA.localeCompare(cellB);
            });

            rows.forEach(row => table.appendChild(row)); // Reattach sorted rows
        }
    </script>

</body>
</html>
