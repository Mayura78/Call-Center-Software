<?php
session_start();
include 'db.php';

// ===== CHECK ADMIN LOGIN =====
if(!isset($_SESSION['admin_id'])){
    echo "Unauthorized access!";
    exit();
}

// ===== GET APPOINTMENT ID =====
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if($id <= 0){
    echo "Invalid appointment ID!";
    exit();
}

// ===== UPDATE VISIT STATUS TO CANCELLED =====
$stmt = $conn->prepare("UPDATE call_log SET visit_status='Cancelled' WHERE id=?");
$stmt->bind_param("i", $id);

if($stmt->execute()){
    echo "Appointment cancelled successfully!";
}else{
    echo "Error cancelling appointment. Please try again.";
}
$stmt->close();
$conn->close();
?>
