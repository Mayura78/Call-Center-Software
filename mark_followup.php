<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id){ exit("error"); }

$id = $_POST['id'] ?? '';
$followup_date = $_POST['followup_date'] ?? '';

if($id && $followup_date){
    $stmt = $conn->prepare("UPDATE call_log SET followup_date=? WHERE id=? AND user_id=?");
    $stmt->bind_param("sii", $followup_date, $id, $user_id);
    if($stmt->execute()){
        echo "success";
    } else {
        echo "error";
    }
} else {
    echo "error";
}
