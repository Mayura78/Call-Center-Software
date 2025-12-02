<?php
include 'db.php';

$agent = $_POST['agent'] ?? '';
$caller = $_POST['caller'] ?? '';
$customer_name = $_POST['customer_name'] ?? '';
$customer_number = $_POST['customer_number'] ?? '';
$project_name = $_POST['project_name'] ?? '';
$event = $_POST['event'] ?? '';
$reason = $_POST['reason'] ?? '';
$sentiment = $_POST['sentiment'] ?? '';
$start_time = $_POST['start_time'] ?? '';
$end_time = $_POST['end_time'] ?? '';
$total_time = $_POST['total_time'] ?? '';

$stmt = $conn->prepare("INSERT INTO call_logs (agent_name, caller_id, customer_name, customer_number, project_name, event_status, customer_reason, sentiment, start_time, end_time, total_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssssss", $agent, $caller, $customer_name, $customer_number, $project_name, $event, $reason, $sentiment, $start_time, $end_time, $total_time);

if ($stmt->execute()) {
    echo "success";
} else {
    echo "error";
}
$stmt->close();
$conn->close();
?>
