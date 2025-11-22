<?php
// ================== doctor_details.php ==================

class Database {
    private $conn;

    public function __construct($host, $user, $pass, $db) {
        $this->conn = new mysqli($host, $user, $pass, $db);
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }

    public function fetchDoctor($name) {
        $stmt = $this->conn->prepare("SELECT * FROM doctors WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();
        $doctor = $result->fetch_assoc();
        $stmt->close();
        return $doctor;
    }

    public function fetchRelatedData($table, $doctorName) {
        $sql = "SELECT *, (SELECT COUNT(*) FROM `$table` WHERE doctor_name = ?) AS total
                FROM `$table` WHERE doctor_name = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $doctorName, $doctorName);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $count = ($result->num_rows > 0) ? $data[0]['total'] : 0;
        $stmt->close();
        return [$data, $count];
    }

    public function close() {
        $this->conn->close();
    }
}

// ================== Initialize DB ==================
$db = new Database("localhost", "root", "", "project");
$doctorName = $_GET['name'] ?? '';
$doctor = $db->fetchDoctor($doctorName);

// Related tables
$tables = ["Courses", "lectures"];
$data = [];
$counts = [];

foreach ($tables as $table) {
    [$data[$table], $counts[$table]] = $db->fetchRelatedData($table, $doctorName);
}

$db->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Doctor Details</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/animate.css/animate.min.css">
</head>
<body class="bg-gray-100 min-h-screen p-6 flex flex-col items-center">

<div class="w-full max-w-6xl bg-white rounded-2xl shadow-xl p-8">

    <!-- Doctor Info -->
    <h1 class="text-4xl font-extrabold text-center text-gray-800 mb-4">
        Dr. <span class="text-blue-600"><?= htmlspecialchars($doctorName) ?></span>
    </h1>

    <?php if ($doctor): ?>
    <p class="text-center text-gray-700 mb-6">Specialization: <strong><?= htmlspecialchars($doctor['subject']) ?></strong></p>
    <div class="flex flex-col md:flex-row justify-center items-center gap-6 mb-6">
        <a href="https://wa.me/<?= preg_replace('/\D/', '', $doctor['phone_number']) ?>" 
           class="flex items-center gap-2 bg-green-500 px-6 py-3 rounded-lg shadow-md hover:bg-green-600 text-white transition" target="_blank">
            <i class="fab fa-whatsapp"></i> <?= htmlspecialchars($doctor['phone_number']) ?>
        </a>
        <a href="mailto:<?= htmlspecialchars($doctor['email']) ?>" 
           class="flex items-center gap-2 bg-blue-500 px-6 py-3 rounded-lg shadow-md hover:bg-blue-600 text-white transition">
            <i class="fas fa-envelope"></i> <?= htmlspecialchars($doctor['email']) ?>
        </a>
    </div>
    <?php else: ?>
        <p class="text-center text-red-500 font-semibold">Doctor details not found.</p>
    <?php endif; ?>

    <hr class="my-6">

    <!-- Toggle Buttons -->
    <div class="flex flex-wrap justify-center gap-4 mb-6">
        <?php foreach ($tables as $table): ?>
            <button class="toggle-btn px-4 py-2 rounded text-white font-semibold transition" 
                    style="background-color: <?= match($table) {
                        'Courses' => '#10b981',
                        'lectures' => '#f59e0b',
                        default => '#6b7280'
                    }; ?>;"
                    data-target="<?= htmlspecialchars($table) ?>">
                <?= ucfirst($table) ?> (<?= $counts[$table] ?? 0 ?>)
            </button>
        <?php endforeach; ?>
    </div>

    <!-- Content Sections -->
    <?php foreach ($data as $key => $items): ?>
    <div id="<?= htmlspecialchars($key) ?>" class="content hidden bg-gray-50 rounded-lg p-6 shadow-md mb-6">
        <h2 class="text-2xl font-bold text-gray-800 mb-4 capitalize"><?= str_replace('_', ' ', $key) ?> (<?= $counts[$key] ?? 0 ?>)</h2>

        <?php if (!empty($items)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse border border-gray-300 rounded-lg shadow-sm">
                <thead>
                    <tr class="bg-gray-700 text-white text-left">
                        <?php 
                        $excludeCols = ['id','name','course_image','total'];
                        $columns = array_keys($items[0]);
                        foreach ($columns as $col):
                            if (in_array(strtolower($col), $excludeCols)) continue;
                        ?>
                        <th class="border px-4 py-2"><?= ucfirst(str_replace('_',' ',$col)) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $item): ?>
                    <tr class="<?= $idx % 2 ? 'bg-gray-100' : 'bg-white' ?> hover:bg-gray-200 transition">
                        <?php foreach ($item as $col => $val):
                            if (in_array(strtolower($col), $excludeCols)) continue; 
                            // Decode JSON if needed
                            if (is_string($val) && json_decode($val, true) !== null) {
                                $decoded = json_decode($val,true);
                                if (is_array($decoded)) $val = implode(', ', $decoded);
                            }
                        ?>
                        <td class="border px-4 py-2 text-gray-800">
                            <?php
                            // ================= Switch Media Handling =================
                            switch (true) {
                                case preg_match('/\.(jpg|jpeg|png|gif)$/i', $val):
                                    echo "<img src='".htmlspecialchars($val)."' alt='Image' class='w-20 h-20 object-cover rounded-md shadow'>";
                                    break;
                                case preg_match('/\.(mp4|webm|ogg)$/i', $val):
                                    echo "<video controls class='w-40 h-24 rounded-md shadow'><source src='".htmlspecialchars($val)."' type='video/mp4'></video>";
                                    break;
                                case preg_match('/\.(mp3|wav)$/i', $val):
                                    echo "<audio controls class='w-40'><source src='".htmlspecialchars($val)."' type='audio/mpeg'></audio>";
                                    break;
                                case preg_match('/\.(pdf|docx?|xls|xlsx)$/i', $val):
                                    echo "<a href='".htmlspecialchars($val)."' download class='text-blue-600 underline'>Download File</a>";
                                    break;
                                default:
                                    echo htmlspecialchars($val);
                                    break;
                            }
                            ?>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-gray-500">No data available.</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="text-center mt-6">
        <a href="doctors.php" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg shadow-md hover:bg-blue-700 transition transform hover:scale-105">
            <i class="fas fa-arrow-left mr-2"></i> Back to Doctors
        </a>
    </div>
</div>

<!-- ================= Toggle Sections Vanilla JS ================= -->
<script>
document.querySelectorAll('.toggle-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const target = btn.dataset.target;
        document.querySelectorAll('.content').forEach(c => {
            if(c.id === target) c.classList.toggle('hidden');
            else c.classList.add('hidden');
        });
    });
});
</script>

</body>
</html>
