<?php
$servername = "localhost";
$username = "root";       // DB username
$password = "";           // DB password
$dbname = "commercial_db";   // DB name

$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>