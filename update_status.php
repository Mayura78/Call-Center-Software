<?php
include 'db.php';

$id = $_POST['id'];
$status = $_POST['status'];

$sql = "UPDATE appointments SET status='$status' WHERE id='$id' ";

if ($conn->query($sql)) {
    echo "success";
} else {
    echo "error";
}
?>
