<?php
include 'db.php';
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo "<p class='text-danger text-center'>Invalid Agent ID</p>";
    exit;
}

$agent = $conn->query("SELECT * FROM call_agents_pool WHERE id=$id")->fetch_assoc();
if (!$agent) {
    echo "<p class='text-danger text-center'>Agent not found</p>";
    exit;
}

$phones = $conn->query("SELECT phone_number, called FROM agent_phones WHERE agent_id=$id");
?>
<div class="container">
<h4 class="text-primary mb-3">👤 <?= htmlspecialchars($agent['agent_name']) ?></h4>
<ul class="list-group mb-3">
<li class="list-group-item"><b>Agent Number:</b> <?= htmlspecialchars($agent['agent_number']) ?></li>
<li class="list-group-item"><b>Daily Target:</b> <?= $agent['daily_target'] ?></li>
<li class="list-group-item"><b>Calls Taken:</b> <?= $agent['calls_taken'] ?></li>
<li class="list-group-item"><b>Remaining:</b> <?= max($agent['daily_target'] - $agent['calls_taken'], 0) ?></li>
</ul>

<h5>📞 Phone Numbers</h5>
<ul class="list-group">
<?php
if ($phones->num_rows > 0) {
    while ($p = $phones->fetch_assoc()) {
        $cls = $p['called'] ? 'text-muted text-decoration-line-through' : 'text-success fw-bold';
        echo "<li class='list-group-item $cls'>{$p['phone_number']}</li>";
    }
} else {
    echo "<li class='list-group-item text-muted'>No phone numbers found</li>";
}
?>
</ul>
</div>
