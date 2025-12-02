<?php
// create_db.php - creates the database defined in db.php (if missing)
$dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'db.php';
if (file_exists($dbFile)) {
    include $dbFile; // sets $host, $user, $pass, $dbname
} else {
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "callcenterdb";
}

// Connect without specifying a database
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . PHP_EOL);
}

// Safely create DB name (allow only alphanumeric and _ to avoid injection)
if (!preg_match('/^[A-Za-z0-9_]+$/', $dbname)) {
    die("Refusing to create database with unsafe name: $dbname" . PHP_EOL);
}

$sql = "CREATE DATABASE IF NOT EXISTS `" . $conn->real_escape_string($dbname) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
if ($conn->query($sql) === TRUE) {
    echo "Database '$dbname' created or already exists." . PHP_EOL;
} else {
    echo "Error creating database: " . $conn->error . PHP_EOL;
}

$conn->close();
?>