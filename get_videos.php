<?php
// Database connection settings
$host = 'localhost';
$dbname = 'project';
$username = 'root';
$password = '';

$pdo = null;
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed!']);
    exit;
}

// Handle video deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM videos WHERE id = :id");
    $stmt->bindParam(':id', $_POST['delete_id'], PDO::PARAM_INT);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['error' => 'Failed to delete video.']);
    }
    exit;
}

// Pagination setup
$limit = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Fetch videos with pagination
$stmt = $pdo->prepare("SELECT id, video_url, video_title FROM videos ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
$stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$videos = $stmt->fetchAll();

// Get total video count
$totalStmt = $pdo->query("SELECT COUNT(*) FROM videos");
$totalVideos = (int)$totalStmt->fetchColumn();
$totalPages = ceil($totalVideos / $limit);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Videos</title>
    <script src="https://www.youtube.com/iframe_api"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel='stylesheet' href='styles/get-videos.css'>
</head>
<body>
    <h2>ًں“؛ Your Saved Videos</h2>
    <div class="video-container">
        <?php if (!empty($videos)): ?>
            <?php foreach ($videos as $video): ?>
                <?php 
                    preg_match("/(?:youtube\.com\/(?:[^\/]+\/[^\/]+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/", $video['video_url'], $matches);
                    $videoId = $matches[1] ?? null;
                ?>
                <div class="video-card">
                    <div id="player-<?= htmlspecialchars($video['id']); ?>" class="youtube-player" style="width: 100%; height: 280px;"></div>
                    <div class="video-info">
                        <h3><?= htmlspecialchars($video['video_title']); ?></h3>
                        <button class="delete-btn" onclick="deleteVideo(<?= htmlspecialchars($video['id']); ?>)">ًں—‘ Delete</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No videos found.</p>
        <?php endif; ?>
    </div>

    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i; ?>"><?= $i; ?></a>
        <?php endfor; ?>
    </div>

    <script>
        let youtubePlayers = {};
        function onYouTubeIframeAPIReady() {
            <?php foreach ($videos as $video): ?>
                youtubePlayers["player-<?= $video['id']; ?>"] = new YT.Player("player-<?= $video['id']; ?>", {
                    height: '280',
                    width: '100%',
                    videoId: "<?= $videoId; ?>",
                    playerVars: {
                        'autoplay': 0,
                        'controls': 1,
                        'rel': 0,
                        'modestbranding': 1
                    }
                });
            <?php endforeach; ?>
        }

        function deleteVideo(videoId) {
            Swal.fire({
                title: "Are you sure?",
                text: "You won't be able to recover this video!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "No, cancel!",
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "delete_id=" + encodeURIComponent(videoId)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            Swal.fire("Error", data.error, "error");
                        }
                    })
                    .catch(error => {
                        Swal.fire("Error", "Failed to delete video.", "error");
                    });
                }
            });
        }
    </script>
</body>
</html>

