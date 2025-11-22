<?php
session_start();
include 'connect.php';

class StudentAuth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function logout($studentName) {
        if (!$studentName) {
            return ["success" => false, "message" => "No student logged in"];
        }

        $stmt = $this->conn->prepare("DELETE FROM students WHERE username = ?");
        $stmt->bind_param("s", $studentName);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            session_destroy();
            return ["success" => true];
        } else {
            return ["success" => false, "message" => "Failed to remove student"];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if ($data['action'] === "logout") {
        $auth = new StudentAuth($conn);
        $response = $auth->logout($_GET['name'] ?? null);

        echo json_encode($response);
        $conn->close();
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <link rel="icon" href="/zagazig.png" type="image/png">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Fonts & Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="/projectGraduation/styles/sidebar.css">

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
 <!-- Sidebar Toggle Button -->
<button id="sidebarToggle" aria-label="Toggle Sidebar" class="sidebar-toggle">
  <i class="fa fa-bars"></i>
</button>

<!-- Sidebar -->
<aside id="sidebar" class="sidebar" aria-hidden="true">

  <div class="sidebar-content">

    <!-- Profile -->
    <section class="profile">
      <img src="<?php echo $profile_image; ?>" alt="User Profile Picture" class="profile-img">

      <div class="profile-info">
        <h2 class="profile-name">
          <?php echo htmlspecialchars($name); ?>
        </h2>
        <p class="profile-email">
          <?php echo htmlspecialchars($email); ?>
        </p>
      </div>
    </section>

    <!-- Contact Badges -->
    <section class="badges">
      <span class="badge email"><?php echo htmlspecialchars($email); ?></span>
      <span class="badge phone"><?php echo htmlspecialchars($phone); ?></span>
    </section>

    <!-- Dynamic Navigation Links -->
    <nav aria-label="Sidebar Navigation" class="nav-links" id="nav-links"></nav>

  </div>

  <!-- Logout Button -->
  <button id="logoutButton" class="logout-btn">
    <i class="fa fa-sign-out-alt"></i>
    Logout
  </button>

</aside>

<audio id="logoutSound" src="logout.mp3" preload="auto"></audio>
<script>
/* === Student Data (from PHP) === */
const studentName   = "<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>";
const phoneNumber   = "<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>";
const studentEmail  = "<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>";
const BASE_URL      = "";

/* === Dynamic Nav Items === */
const navItems = [
  { href: "lectures_files.php", title: "Files", icon: "fa-flask" },
  { href: "save_lectures.php", title: "Lectures", icon: "fa-chalkboard-teacher" },
  { href: "links.php", title: "Links", icon: "fa-link" },
  { href: "get_videos.php", title: "Saved Videos", icon: "fa-video" },
  { href: "get_Exams.php", title: "Exams", icon: "fa-school" },
  { href: "doctors.php", title: "Doctors", icon: "fa-user-md" },
  { href: "notify.php", title: "Live", icon: "fa-user-notify" },
 
];
const navContainer = document.getElementById("nav-links");

navItems.forEach((item, index) => {
  const link = document.createElement("a");

  // Dynamic href with query parameters
  link.href = studentName
    ? `${BASE_URL}${item.href}?name=${encodeURIComponent(studentName)}&phone=${encodeURIComponent(phoneNumber)}&email=${encodeURIComponent(studentEmail)}`
    : item.href;

  // Inner HTML with icon
  link.innerHTML = `<i class="fa ${item.icon}" style="transition: transform 0.4s ease, color 0.4s ease;"></i> ${item.title}`;

  // Inline styles
  Object.assign(link.style, {
    display: "flex",
    alignItems: "center",
    gap: "0.8rem",
    color: "#e2e8f0",
    textDecoration: "none",
    background: "rgba(255,255,255,0.08)",
    padding: "0.8rem 1rem",
    borderRadius: "12px",
    position: "relative",
    opacity: "0",
    transform: "translateX(-20px)",
    transition: `all 0.4s ease ${0.3 + index*0.15}s`,
    cursor: "pointer",
    overflow: "hidden"
  });

  // Hover effect
  link.addEventListener("mouseenter", () => {
    link.style.background = "rgba(168,24,24,0.2)";
    link.style.transform = "translateX(8px) scale(1.05)";
    link.style.boxShadow = "0 4px 12px rgba(141,19,19,0.4)";
    const icon = link.querySelector("i");
    icon.style.transform = "rotate(15deg) scale(1.25)";
    icon.style.color = "#22d3ee";
  });

  link.addEventListener("mouseleave", () => {
    link.style.background = "rgba(255,255,255,0.08)";
    link.style.transform = "translateX(0) scale(1)";
    link.style.boxShadow = "none";
    const icon = link.querySelector("i");
    icon.style.transform = "rotate(0deg) scale(1)";
    icon.style.color = "#e2e8f0";
  });

  // Append to container
  navContainer.appendChild(link);

  // Animate slide-in
  setTimeout(() => {
    link.style.opacity = "1";
    link.style.transform = "translateX(0)";
  }, 100 + index * 150);
});
const toggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('sidebar');

toggleBtn.addEventListener('click', () => {
    if (sidebar.style.left === '0px') {
        sidebar.style.left = '-300px';
        toggleBtn.querySelector('i').style.transform = 'rotate(0deg)';
    } else {
        sidebar.style.left = '0px';
        toggleBtn.querySelector('i').style.transform = 'rotate(90deg)';
    }
});

// Hover animation for profile image
const profileImg = sidebar.querySelector('.profile img');
profileImg.addEventListener('mouseenter', () => {
    profileImg.style.transform = 'scale(1.1)';
    profileImg.style.boxShadow = '0 6px 18px rgba(0,0,0,0.35)';
});
profileImg.addEventListener('mouseleave', () => {
    profileImg.style.transform = 'scale(1)';
    profileImg.style.boxShadow = '0 4px 12px rgba(0,0,0,0.25)';
});
document.getElementById("logoutButton").addEventListener("click", () => {

  // Show confirmation before logout
  Swal.fire({
    title: "Are you sure?",
    text: "Do you really want to log out?",
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Yes, log me out",
    cancelButtonText: "Cancel",
    reverseButtons: true,
    allowOutsideClick: false
  }).then((result) => {
    if (result.isConfirmed) {
      // Play logout sound
      document.getElementById("logoutSound").play();

      // Show loading Swal
      Swal.fire({
        title: "Logging Out...",
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading()
      });

      // Perform logout request
      fetch(window.location.href, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "logout" })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setTimeout(() => {
            Swal.fire({
              title: "Goodbye!",
              text: "You have successfully logged out.",
              icon: "success",
              showConfirmButton: false,
              timer: 1500,
              willClose: () => window.location.replace("welcome.php")
            });
          }, 1000);
        } else {
          Swal.fire("Error", data.message, "error");
        }
      })
      .catch(err => {
        Swal.fire("Network Error", "There was an issue with your request.", "error");
        console.error(err);
      });
    }
  });
});

// Close on outside click
document.addEventListener("click", (e) => {
  if (sidebar.classList.contains("active") &&
      !sidebar.contains(e.target) &&
      e.target !== toggleBtn) {
    sidebar.classList.remove("active");
  }
});

// Close on ESC key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape" && sidebar.classList.contains("active")) {
    sidebar.classList.remove("active");
  }
});
</script>
</body>
</html>
