<?php
// index.php
// Redirects to home.php after showing a loading indicator and checking internet status.

header("Content-Type: text/html; charset=UTF-8");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zagzig University</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel='stylesheet' href='styles/welcome.css'>
</head>

<body class="bg-gradient-to-r from-blue-400 to-pink-500 transition-all font-roboto">

    <!-- Cover Image of Zagzig University -->
    <img id="cover-image" src="zagazig.png" alt="Zagzig University Cover" class="cover-image fade-in">

    <script>
        // Check internet connection
        function checkInternetConnection() {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.onload = () => resolve(true);
                img.onerror = () => reject(false);
                img.src = "https://www.google.com/images/branding/googlelogo/1x/googlelogo_color_272x92dp.png"; // Google image to check if internet is available
            });
        }

        // Show SweetAlert loading and wait for internet check
        Swal.fire({
            title: 'Loading...',
            text: 'hello user to zagzig university...',
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
                checkInternetConnection()
                    .then((isOnline) => {
                        const coverImage = document.getElementById("cover-image");
                        coverImage.classList.add("visible"); // Fade in the cover image
                        
                        if (isOnline) {
                            // If internet is connected, redirect after 1 second
                            setTimeout(() => {
                                window.location.href = "index.php";
                            }, 1000);
                        } else {
                            // If no internet, alert the user
                            setTimeout(() => {
                                Swal.fire({
                                    title: 'No Internet Connection!',
                                    text: 'Please check your connection and try again.',
                                    icon: 'error',
                                    confirmButtonText: 'Retry',
                                    allowOutsideClick: false,
                                    showCancelButton: true,
                                    cancelButtonText: 'Exit'
                                }).then(result => {
                                    if (result.isConfirmed) {
                                        window.location.reload(); // Reload to try again
                                    } else {
                                        window.location.href = "error.php"; // Optionally, navigate away after failure
                                    }
                                });
                            }, 1000);
                        }
                    })
                    .catch(() => {
                        // If error occurs during checking
                        setTimeout(() => {
                            Swal.fire({
                                title: 'Error!',
                                text: 'Unable to check internet connection.',
                                icon: 'error',
                                confirmButtonText: 'Retry',
                                allowOutsideClick: false,
                                showCancelButton: true,
                                cancelButtonText: 'Exit'
                            }).then(result => {
                                if (result.isConfirmed) {
                                    window.location.reload(); // Reload to try again
                                } else {
                                    window.location.href = "error.php"; // Optionally, navigate away after failure
                                }
                            });
                        }, 1000);
                    });
            }
        });
    </script>
</body>

</html>

