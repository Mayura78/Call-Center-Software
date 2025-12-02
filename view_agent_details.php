<?php
include 'db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<div class='text-danger'>Invalid agent ID</div>";
    exit;
}

$result = $conn->query("SELECT phone_number, called FROM agent_phones WHERE agent_id=$id");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cls = $row['called'] ? 'text-decoration-line-through text-muted' : 'text-dark';
        echo "<div class='$cls'>📞 {$row['phone_number']}</div>";
    }
} else {
    echo "<div class='text-muted'>No customer numbers found.</div>";
}
?>
