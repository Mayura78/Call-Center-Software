<?php
session_start();
if(!isset($_SESSION['user_id'])) exit;
include 'db.php';

// Last 7 days
$dates = [];
for($i=6;$i>=0;$i--){
    $dates[] = date('Y-m-d', strtotime("-$i days"));
}

// Fetch users
$users = [];
$result = $conn->query("SELECT id, user_name FROM manageuser ORDER BY user_name ASC");
while($row = $result->fetch_assoc()){
    $users[$row['id']] = $row['user_name'];
}

// Prepare values array [day][user]
$values = [];
foreach($dates as $d){
    $dayValues = [];
    foreach($users as $id => $name){
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM call_log WHERE user_id=? AND DATE(called_at)=?");
        $stmt->bind_param("is", $id, $d);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $dayValues[] = (int)$res['total'];
    }
    $values[] = $dayValues;
}

echo json_encode([
    'dates' => $dates,
    'users' => array_values($users),
    'values' => $values
]);
