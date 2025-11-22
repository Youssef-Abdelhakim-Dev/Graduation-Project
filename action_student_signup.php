<?php
require_once 'connect.php';

$wrongpass = array();

if (isset($_POST['signup'])) {
    $uName = mysqli_real_escape_string($connect, $_POST['username']);
    $Email = mysqli_real_escape_string($connect, $_POST['email']);
    $pass = mysqli_real_escape_string($connect, $_POST['password']);
    $cpass = mysqli_real_escape_string($connect, $_POST['cpassword']);
    $CRYPTpass = password_hash($pass, PASSWORD_BCRYPT);

    // معالجة المرفقات
    $attachment = $_FILES['attachment']['name'];
    $attachmenttmp = $_FILES['attachment']['tmp_name'];
    $uploadedfile = 'file/';
    move_uploaded_file($attachmenttmp, $uploadedfile . $attachment);

    // التحقق من تطابق كلمة المرور
    if ($pass !== $cpass) {
        $wrongpass['password'] = "كلمة السر غير مطابقة.";
    }

    // التحقق من وجود البريد الإلكتروني مسبقًا
    $emailCheck = "SELECT * FROM student WHERE student_email = '$Email'";
    $result = mysqli_query($connect, $emailCheck);
    if (mysqli_num_rows($result) > 0) {
        $wrongpass['email'] = "البريد الإلكتروني موجود مسبقًا.";
    }

    // إذا لم يكن هناك أخطاء، يتم إدخال البيانات في قاعدة البيانات
    if (count($wrongpass) === 0) {
        $sql = "INSERT INTO student (student_name, student_email, student_password, attachment) 
                VALUES ('$uName', '$Email', '$CRYPTpass', '$attachment')";
        $result = mysqli_query($connect, $sql);

        if ($result) {
            // تحويل المستخدم إلى صفحة تسجيل الدخول
            header('location:login_student.php?success=registration');
            exit();
        } else {
            echo '<script>alert("حدث خطأ أثناء التسجيل. يرجى المحاولة مرة أخرى.");</script>';
        }
    } else {
        // عرض الأخطاء للمستخدم
        foreach ($wrongpass as $error) {
            echo "<script>alert('$error');</script>";
        }
    }
}
?>
