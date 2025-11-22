<?php
require_once 'connect.php';

if (isset($_POST['login_student'])) {
    $email = $_POST['student_email'];
    $password = $_POST['student_password'];

    // التحقق من وجود البريد الإلكتروني
    $stmt = $con->prepare("SELECT * FROM student WHERE student_email = :email");
    $stmt->execute([':email' => $email]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student && password_verify($password, $student['student_password'])) {
        session_start();
        $_SESSION['student_id'] = $student['student_id'];
        $_SESSION['student_name'] = $student['student_name'];

        header("Location: main.php"); // توجيه إلى لوحة التحكم
        exit();
    } else {
        header("Location: error.php?error=invalid");
        exit();
    }
}
?>
