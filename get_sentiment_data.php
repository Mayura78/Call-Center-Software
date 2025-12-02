<?php
session_start();
if (!isset($_SESSION['user_id'])) exit();
include 'db.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT sentiment, COUNT(*) as total
    FROM call_log
    WHERE user_id = ?
    GROUP BY sentiment
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$positive = 0;
$negative = 0;
while($row = $result->fetch_assoc()){
    if($row['sentiment'] === 'Positive') $positive = (int)$row['total'];
    else if($row['sentiment'] === 'Negative') $negative = (int)$row['total'];
}

echo json_encode(['positive'=>$positive,'negative'=>$negative]);
