<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'project');
if ($conn->connect_error) die("Database connection failed: " . $conn->connect_error);

// Fetch videos
$sql = "SELECT id, filename, filepath, uploaded_at, doctor_name, poster, preview_vtt FROM lectures ORDER BY uploaded_at DESC";
$result = $conn->query($sql);

// Count total videos
$count_sql = "SELECT COUNT(*) as total FROM lectures";
$count_result = $conn->query($count_sql);
$total_videos = $count_result->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title> Lectures</title>

<!-- Tailwind CSS -->
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">

<!-- Plyr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/plyr@3.6.6/dist/plyr.css">

<!-- FontAwesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<!-- QRCode -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Plyr JS -->
<script src="https://cdn.jsdelivr.net/npm/plyr@3.6.6/dist/plyr.min.js"></script>

<!-- HLS.js -->
<script src="https://cdn.jsdelivr.net/npm/hls.js@latest"></script>

<style>
/* Optional: buffered bar style */
.progress-buffer {
    background: rgba(0, 0, 255, 0.3);
    height: 100%;
    position: absolute;
    left: 0;
    top: 0;
}
</style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100">

<h1 class="text-3xl font-bold text-center mb-6 flex items-center justify-center gap-2">
    🎥 <span>Saved lectures</span> <i class="fa fa-folder-open text-blue-500"></i>
</h1>

<p class='text-center text-2xl font-bold text-white bg-green-500 p-4 rounded-lg shadow-lg mb-6'>
    🎥 Total Videos: <span class='text-yellow-300'><?= $total_videos ?></span>
</p>

<!-- Search -->
<div class="flex justify-center mb-6 relative w-full md:w-2/3 lg:w-1/2 mx-auto">
    <i class="fa fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
    <input 
        class="w-full p-3 pl-10 border-2 border-gray-300 dark:border-gray-600 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all duration-300 dark:bg-gray-800 dark:text-gray-200" 
        type="text" 
        id="search" 
        placeholder="Search by video title..." 
        oninput="searchVideos()"
    >
</div>

<!-- Video Container -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 px-4" id="video-container">
<?php if ($result->num_rows > 0): ?>
    <?php while ($row = $result->fetch_assoc()): ?>
    <div class="bg-white dark:bg-gray-900 shadow-lg rounded-lg overflow-hidden transform hover:scale-105 transition-all duration-300 border border-gray-200 dark:border-gray-700">
        <div class="relative">
            <video id="video-<?= $row['id'] ?>" class="w-full h-56 object-cover rounded-t-lg" controls data-plyr="true" <?= !empty($row['poster']) ? "poster='{$row['poster']}'" : "" ?>>
                <source src="<?= htmlspecialchars($row['filepath']) ?>" type="application/x-mpegURL">
            </video>

            <!-- Optional buffered bar -->
            <div class="absolute bottom-0 left-0 w-full h-2 bg-gray-300 dark:bg-gray-700 rounded-full overflow-hidden">
                <div id="buffer-<?= $row['id'] ?>" class="progress-buffer rounded-full" style="width: 0%;"></div>
                <div id="progress-<?= $row['id'] ?>" class="bg-gradient-to-r from-blue-400 to-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%;"></div>
            </div>
        </div>

        <div class="p-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 truncate flex items-center gap-2">
                <i class="fa fa-file-video text-blue-500"></i> <?= htmlspecialchars($row['filename']) ?>
            </h3>

            <p class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-1">
                <i class="fa fa-user-md text-green-500"></i> Uploaded by: <span class="font-medium"><?= !empty($row['doctor_name']) ? htmlspecialchars($row['doctor_name']) : 'Unknown' ?></span>
            </p>

            <p class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-1">
                <i class="fa fa-calendar-alt"></i> Uploaded on: <?= date('F j, Y, g:i A', strtotime($row['uploaded_at'])) ?>
            </p>

            <div class="flex justify-between items-center mt-4">
                <button class="flex items-center bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-lg text-sm transition-all duration-300 shadow-md" title="Share this video" onclick="shareVideo('<?= htmlspecialchars($row['filepath']) ?>')">
                    <i class="fa fa-share-alt mr-2"></i> Share
                </button>
                <a href="<?= htmlspecialchars($row['filepath']) ?>" download class="flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg text-sm transition-all duration-300 shadow-md" title="Download this video">
                    <i class="fa fa-download mr-2"></i> Download
                </a>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
    <p class="text-gray-600 dark:text-gray-400 text-center col-span-3 flex items-center justify-center gap-2">
        ⚠️ <span>No recordings found.</span>
    </p>
<?php endif; ?>
</div>

<script>
// Search function
function searchVideos() {
    let input = document.getElementById('search').value.toLowerCase();
    document.querySelectorAll('#video-container > div').forEach(card => {
        let title = card.querySelector('h3').innerText.toLowerCase();
        card.style.display = title.includes(input) ? 'block' : 'none';
    });
}

// Share function with fallback QR
function shareVideo(videoUrl) {
    if (navigator.share) {
        navigator.share({ title: '🎥 Video', text: 'Check this video!', url: videoUrl });
    } else {
        let modal = `
            <div id="shareModal" class="fixed inset-0 bg-black bg-opacity-50 flex justify-center items-center">
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-lg max-w-md w-full text-center">
                    <h2 class="text-lg font-bold text-gray-900 dark:text-gray-200">🔗 Share Video</h2>
                    <div class="flex justify-center my-4"><div id="qrcode"></div></div>
                    <div class="relative flex items-center border rounded-lg p-2 bg-gray-200 dark:bg-gray-700">
                        <input id="videoLink" type="text" class="w-full bg-transparent outline-none text-gray-900 dark:text-gray-200" value="${videoUrl}" readonly>
                        <button onclick="copyLink()" class="ml-2 bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded">📋 Copy</button>
                    </div>
                    <button onclick="closeModal()" class="mt-4 bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">❌ Close</button>
                </div>
            </div>`;
        document.body.insertAdjacentHTML("beforeend", modal);
        new QRCode(document.getElementById("qrcode"), { text: videoUrl, width: 128, height: 128 });
    }
}

function copyLink() {
    let input = document.getElementById("videoLink");
    input.select();
    document.execCommand("copy");
    alert("✅ Video link copied!");
}

function closeModal() { document.getElementById("shareModal").remove(); }

// Initialize Plyr & HLS
document.querySelectorAll('video[data-plyr]').forEach(video => {
    const player = new Plyr(video, {
        controls: ['play','progress','current-time','duration','mute','volume','settings','fullscreen'],
        settings: ['quality','speed','loop']
    });

    const src = video.querySelector('source').src;
    const id = video.id.split('-')[1];

    if (Hls.isSupported() && src.endsWith('.m3u8')) {
        const hls = new Hls({ capLevelToPlayerSize: true, maxMaxBufferLength: 30 });
        hls.loadSource(src);
        hls.attachMedia(video);

        hls.on(Hls.Events.LEVEL_LOADED, function(event, data) {
            // Update available quality
            const levels = hls.levels.map((l, i) => ({ label: l.height + 'p', value: i }));
            player.options.quality = { default: levels.length-1, options: levels.map(l=>l.value), forced: true, onChange: (index) => hls.currentLevel = index };
        });

        hls.on(Hls.Events.FRAG_BUFFERED, () => {
            const buffered = video.buffered;
            if(buffered.length) {
                const bufferPercent = (buffered.end(buffered.length-1)/video.duration)*100;
                document.getElementById(`buffer-${id}`).style.width = bufferPercent + '%';
            }
        });
    }

    video.addEventListener('timeupdate', () => {
        const progress = document.getElementById(`progress-${id}`);
        const currentTime = video.currentTime;
        const duration = video.duration;
        if(duration) progress.style.width = (currentTime/duration*100)+'%';
    });
});
</script>
</body>
</html>

<?php $conn->close(); ?>
