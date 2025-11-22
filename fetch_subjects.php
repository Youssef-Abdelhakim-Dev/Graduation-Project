<?php
$pdo = new PDO("mysql:host=localhost;dbname=project;charset=utf8mb4", "root", "", [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

$query = "SELECT subject FROM teachers WHERE year = :year";
$stmt = $pdo->prepare($query);
$stmt->execute(['year' => $year]);
$subjects = $stmt->fetchAll();

echo json_encode($subjects);
?>
