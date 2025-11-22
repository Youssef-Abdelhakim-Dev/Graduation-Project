<?php
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "project";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Fetch videos only for the selected course
$courseName = isset($_GET["name"]) ? $conn->real_escape_string($_GET["name"]) : '';

$sql = "SELECT * FROM course_videos WHERE course_name = '$courseName' ORDER BY upload_date ASC";
$result = $conn->query($sql);

$videos = [];
$counter = 1;
while ($row = $result->fetch_assoc()) {
    $videos[] = [
        "title" => "Video " . $counter,
        "filename" => $row["filename"],
        "filepath" => $row["filepath"]
    ];
    $counter++;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Videos</title>

    <!-- Swiper.js for Slider -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css">
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>

    <!-- TailwindCSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel='stylesheet' href='styles/courses-videos.css'>
</head>
<body class="bg-gray-100 p-5">
<?php if (empty($videos)): ?>
    <h1 class="text-4xl text-center font-extrabold my-6 relative animate-bounce-in">
    <i class="fas fa-exclamation-triangle text-red-600 animate-spin-slow"></i>  
    <span class="bg-gradient-to-r from-red-600 via-orange-500 to-yellow-500 bg-clip-text text-transparent drop-shadow-lg">
         No videos are available.
    </span>
</h1>
<?php else: ?>
    <h1 class="text-4xl md:text-5xl font-extrabold text-center text-gray-900 dark:text-white 
           mb-8 flex items-center justify-center gap-3 p-4 bg-gradient-to-r from-gray-200 to-gray-100 
           rounded-xl shadow-lg border border-gray-300 dark:border-gray-700">
    <i class="fas fa-video text-blue-500 dark:text-blue-400 animate-pulse"></i> 
    <span class="bg-clip-text bg-gradient-to-r from-blue-500 to-purple-500">
    <?= htmlspecialchars($courseName); ?> - Course Videos
    </span>
</h1>

    <div class="video-slider">
        <div class="swiper-container">
            <div class="swiper-wrapper">
                <?php foreach ($videos as $video): ?>
                    <div class="swiper-slide">
                        <div class="video-container">
                        <h2 class="text-2xl font-extrabold text-red-900 italic underline decoration-wavy decoration-blue-400">
    <?= htmlspecialchars($video["title"]); ?>
</h2>

                            <video class="video-player" data-title="<?= htmlspecialchars($video["title"]); ?>" controls>
                                <source src="<?= htmlspecialchars($video["filepath"]); ?>" type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            <div class="progress-bar"><div class="progress"></div></div>
                            <div class="controls">
                                <button class="control-btn fullscreen-btn" title="Fullscreen">
                                    <i class="fas fa-expand"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="swiper-pagination"></div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
    </div>
<?php endif; ?>


<script>
(() => {
  const videos = document.querySelectorAll('.video-player');
  const SWIPE_THRESHOLD = 30; // px for swipe detection
  let activeVideo = null;

  const debounce = (fn, delay) => {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  };

  function pauseOthers(current) {
    videos.forEach(v => {
      if (v !== current && !v.paused) {
        v.pause();
      }
    });
  }

  function togglePlay(video) {
    if (video.paused) {
      pauseOthers(video);
      video.play();
      activeVideo = video;
    } else {
      video.pause();
      if (activeVideo === video) activeVideo = null;
    }
  }

  function toggleFullscreen(video) {
    if (
      document.fullscreenElement === video ||
      document.webkitFullscreenElement === video ||
      document.mozFullScreenElement === video ||
      document.msFullscreenElement === video
    ) {
      if (document.exitFullscreen) document.exitFullscreen();
      else if (document.webkitExitFullscreen) document.webkitExitFullscreen();
      else if (document.mozCancelFullScreen) document.mozCancelFullScreen();
      else if (document.msExitFullscreen) document.msExitFullscreen();
    } else {
      if (video.requestFullscreen) video.requestFullscreen();
      else if (video.webkitRequestFullscreen) video.webkitRequestFullscreen();
      else if (video.mozRequestFullScreen) video.mozRequestFullScreen();
      else if (video.msRequestFullscreen) video.msRequestFullscreen();
    }
  }

  videos.forEach(video => {
    video.setAttribute('role', 'application');
    video.setAttribute('aria-label', video.dataset.title || 'Video Player');

    const container = video.closest('.video-container');
    const controls = container.querySelector('.controls');

    // Title overlay
    const titleOverlay = document.createElement('div');
    titleOverlay.className = 'video-title-overlay';
    titleOverlay.textContent = video.dataset.title || 'Untitled Video';
    container.appendChild(titleOverlay);

    const debouncedToggle = debounce(() => togglePlay(video), 200);
    video.addEventListener('click', debouncedToggle);
    video.addEventListener('dblclick', () => toggleFullscreen(video));

    container.tabIndex = 0;
    container.addEventListener('keydown', e => {
      switch (e.key) {
        case ' ':
        case 'k':
          e.preventDefault();
          togglePlay(video);
          break;
        case 'ArrowRight':
          video.currentTime = Math.min(video.duration, video.currentTime + 5);
          break;
        case 'ArrowLeft':
          video.currentTime = Math.max(0, video.currentTime - 5);
          break;
        case 'f':
          toggleFullscreen(video);
          break;
        case 'm':
          video.muted = !video.muted;
          break;
      }
    });

    video.addEventListener('timeupdate', () => {
      if (video.duration > 0) {
        let percent = (video.currentTime / video.duration) * 100;
      }
    });

    video.addEventListener('ended', () => {
        activeVideo = null;
        Swal.fire({
            title: video.dataset.title || "Video Ended",
            html: `<p>✓ Video has finished playing.</p><p>Thanks for watching!</p>`,
            icon: "success",
            timer: 2500,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            timerProgressBar: true
        });
    });

    video.addEventListener('error', () => {
      Swal.fire({
        title: "Video Error",
        text: "An error occurred while loading the video.",
        icon: "error",
        confirmButtonText: "OK"
      });
    });
  });

  const swiper = new Swiper('.swiper-container', {
    loop: true,
    autoplay: { delay: 3000, disableOnInteraction: false },
    speed: 600,
    pagination: { el: '.swiper-pagination', clickable: true },
    navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
    keyboard: { enabled: true },
    slidesPerView: 1,
    spaceBetween: 15,
    breakpoints: { 640: { slidesPerView: 1 }, 768: { slidesPerView: 2 }, 1024: { slidesPerView: 3 } },
    effect: 'slide',
    grabCursor: true,
    lazy: { loadPrevNext: true },
  });
})();
</script>
</body>
</html>
