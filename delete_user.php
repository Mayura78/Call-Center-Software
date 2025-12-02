<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id > 0){
    // Check if user has related data
    $checkTarget = $conn->query("SELECT COUNT(*) as count FROM target WHERE user_id=$id");
    $targetCount = $checkTarget->fetch_assoc()['count'];
    
    $checkCallLog = $conn->query("SELECT COUNT(*) as count FROM call_log WHERE user_id=$id");
    $callLogCount = $checkCallLog->fetch_assoc()['count'];

    if($targetCount > 0 || $callLogCount > 0){
        // Cannot delete - has related records
        $_SESSION['error'] = "Cannot delete user! This user has $targetCount target(s) and $callLogCount call log(s). Please delete or reassign them first.";
        header("Location: manageuser.php");
        exit();
    }

    // Remove photo file if exists
    $res = $conn->query("SELECT photo FROM manageuser WHERE id=$id");
    if($res && $user = $res->fetch_assoc()){
        if(isset($user['photo']) && $user['photo'] && file_exists("uploads/users/".$user['photo'])){
            unlink("uploads/users/".$user['photo']);
        }
    }

    // Delete user
    $conn->query("DELETE FROM manageuser WHERE id=$id");
    $_SESSION['success'] = "User deleted successfully!";
}

header("Location: manageuser.php");
exit();
?>
