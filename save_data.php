<?php
include 'db.php';

if (isset($_POST['created_time'])) {
    $created = $_POST['created_time'];
    $full = $_POST['full_time'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    for ($i = 0; $i < count($created); $i++) {
        $c = $conn->real_escape_string($created[$i]);
        $f = $conn->real_escape_string($full[$i]);
        $p = $conn->real_escape_string($phone[$i]);
        $e = $conn->real_escape_string($email[$i]);

        if ($c || $f || $p || $e) {
            $conn->query("INSERT INTO excel_data (created_time, full_time, phone, email)
                          VALUES ('$c','$f','$p','$e')");
        }
    }
    echo "<div style='padding:20px;text-align:center;'>
            <h3>✅ Data Saved Successfully!</h3>
            <a href='index.php' class='btn btn-primary'>Upload Another File</a>
          </div>";
} else {
    echo "No data received.";
}
?>
