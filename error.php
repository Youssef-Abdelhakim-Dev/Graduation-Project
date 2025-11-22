<?php
// error.php
// Displays an error message when something goes wrong

header("Content-Type: text/html; charset=UTF-8");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Zagzig University</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel='stylesheet' href='styles/error.css'>
</head>

<body>
<div class="flex items-center justify-center min-h-screen bg-gray-100">
    <div class="bg-white p-6 md:p-8 rounded-lg shadow-lg max-w-md text-center border border-red-300 animate-fade-in">
        <!-- Error Icon -->
        <div class="text-red-500 text-6xl mb-4 animate-bounce">
            âڑ ï¸ڈ
        </div>

        <!-- Error Title -->
        <h2 class="text-2xl font-bold text-red-600 mb-2">Oops! Something Went Wrong</h2>

        <!-- Error Message -->
        <p class="text-gray-700 mb-6">
            We're unable to load the page due to a connection issue.<br> 
            Please check your internet connection and try again.
        </p>

        <!-- Retry Button -->
        <button onclick="window.location.reload();"
            class="px-6 py-2 bg-red-500 text-white rounded-lg shadow-md font-semibold hover:bg-red-600 transition-all duration-300 transform hover:scale-105">
            ًں”„ Retry
        </button>
    </div>
</div>


    <script>
        // SweetAlert2 error popup for better user experience
        Swal.fire({
            title: 'Connection Error',
            text: 'We could not detect an internet connection. Please check your connection and try again.',
            icon: 'error',
            confirmButtonText: 'Reload Page',
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.reload();
            }
        });
    </script>
</body>

</html>

