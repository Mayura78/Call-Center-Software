<?php
session_start();
include 'db.php';

// Get users
$users = [];
$result = $conn->query("SELECT id, user_name FROM manageuser ORDER BY user_name ASC");
while($row = $result->fetch_assoc()){
    $users[$row['id']] = $row['user_name'];
}

// Current month
$start_month = date('Y-m-01'); // first day of month
$end_month = date('Y-m-t');     // last day of month
$month_label = date('F Y');

// Count follow-ups per user
$values = [];
foreach($users as $uid=>$uname){
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM call_log WHERE user_id=? AND followup_date BETWEEN ? AND ?");
    $stmt->bind_param("iss", $uid, $start_month, $end_month);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
    $values[] = (int)$cnt;
    $stmt->close();
}

echo json_encode([
    'users'=>array_values($users),
    'month_label'=>$month_label,
    'values'=>$values
]);
