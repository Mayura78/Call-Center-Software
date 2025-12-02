<?php
include 'db.php';

$id = $_POST['id'];
$status = $_POST['visit_status'];
$bonus = $_POST['bonus_amount'];

$stmt = $conn->prepare("UPDATE call_log SET visit_status=?, bonus_amount=? WHERE id=?");
$stmt->bind_param("sdi", $status, $bonus, $id);

if($stmt->execute()){
    echo "Status updated successfully!";
} else {
    echo "Error updating status.";
}
?>
