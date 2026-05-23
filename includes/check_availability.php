<?php
require_once __DIR__ . '/../includes/auth.php';
$connect = hms_db_connect();


if ($connect->connect_error) {
    die("Connection failed: " . $connect->connect_error);
}
if (!empty($_POST["email"])) {
    $email = $_POST["email"];

    $stmt = $connect->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->num_rows;
    $stmt->close();
    if ($count > 0) {
        echo "<span style='color:red'> email is used .</span>";
        echo "<script>$('#submit').prop('disabled',true);</script>";
    } else {

        echo "<span style='color:green'> email is not used.</span>";
        echo "<script>$('#submit').prop('disabled',false);</script>";
    }
}


        
