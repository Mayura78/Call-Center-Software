<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    $conn->query("DELETE FROM managehotlines WHERE id=$id");
}

header("Location: managehotlines.php");
exit();
