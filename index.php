<?php
require 'vendor/autoload.php'; // Composer autoload
require 'connect.php'; // Database connection

// Twig Loader & Environment
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false, // Set to 'cache' => 'path/to/cache' in production
]);

// Fetch all specializations from DB
$sql = "SELECT * FROM specializations ORDER BY name ASC";
$result = $conn->query($sql);

$specializations = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $specializations[] = [
            'name' => $row['name'],
            'description' => $row['description'] ?? '' // Assuming you have a description column
        ];
    }
}

// Render the Twig template
echo $twig->render('home.html.twig', [
    'specializations' => $specializations
]);
