<?php
session_start();
include 'db.php';

$admin_id = $_SESSION['admin_id'] ?? null;
if (!$admin_id) { http_response_code(403); exit(); }

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;
$bonus = $_POST['bonus'] ?? null;

if($id && $status){
    $stmt = $conn->prepare("UPDATE call_log SET status=?, bonus=? WHERE id=?");
    $stmt->bind_param("ssi",$status,$bonus,$id);
    $stmt->execute();
    echo 'success';
}
?>
