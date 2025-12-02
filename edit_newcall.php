<?php
session_start();
include 'db.php';

// ===== CHECK LOGIN =====
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// ===== FETCH CALL TO EDIT =====
$call_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$call_id) {
    die("Invalid call selected.");
}

$stmt = $conn->prepare("SELECT * FROM usernewcalls WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $call_id, $user_id);
$stmt->execute();
$call = $stmt->get_result()->fetch_assoc();
if (!$call) die("Call not found.");

// ===== FETCH PROJECT LIST =====
$projectResult = $conn->query("SELECT project_name FROM project_list ORDER BY id DESC");
$projects = [];
while ($row = $projectResult->fetch_assoc()) {
    $projects[] = $row['project_name'];
}

// ===== FORM SUBMIT =====
$message = "";
if (isset($_POST['update_call'])) {
    $customer_phone = trim($_POST['customer_phone']);
    $customer_name = trim($_POST['customer_name']);
    $email = trim($_POST['email']);
    $project_name = trim($_POST['project_name']);
    $event = trim($_POST['event']);
    $customer_reason = trim($_POST['customer_reason']);
    $sentiment = trim($_POST['sentiment']);
    $notes = trim($_POST['notes']);
    $followup_date = trim($_POST['followup_date']);
    $appointment_reason = trim($_POST['appointment_reason']);
    $appointment_date = trim($_POST['appointment_date']);

    if (!empty($customer_phone)) {
        $stmt2 = $conn->prepare("UPDATE usernewcalls SET
            customer_phone=?, customer_name=?, email=?, project_name=?, event=?, customer_reason=?, sentiment=?, notes=?, followup_date=?, appointment_reason=?, appointment_date=?
            WHERE id=? AND user_id=?");
        $stmt2->bind_param("sssssssssssii", $customer_phone, $customer_name, $email, $project_name, $event, $customer_reason, $sentiment, $notes, $followup_date, $appointment_reason, $appointment_date, $call_id, $user_id);
        if ($stmt2->execute()) {
            $message = "<div class='alert alert-success'>✅ Call updated successfully!</div>";
            // Refresh call data
            $stmt->execute();
            $call = $stmt->get_result()->fetch_assoc();
        } else {
            $message = "<div class='alert alert-danger'>❌ Failed to update call.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>⚠️ Customer phone number cannot be empty.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Call - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family: 'Poppins', sans-serif; background-color: #eef2f7; color: #333; }
.navbar { background: linear-gradient(180deg, #0105ffff); color: white; padding: 15px 25px; border-radius: 0 0 15px 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
.navbar .navbar-title { font-weight: 600; font-size: 1.25rem; }
.navbar .back-btn { background: rgba(255,255,255,0.2); color: white; border: none; border-radius: 8px; padding: 6px 15px; transition: 0.3s; }
.navbar .back-btn:hover { background: rgba(255,255,255,0.4); }
.card { border-radius: 12px; border: none; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.section-title { font-weight: 600; color: #fd8d0dff; }
</style>
</head>
<body>

<nav class="navbar d-flex align-items-center justify-content-between">
    <div>
        <a href="view_newcalls.php" class="back-btn"><i class="bi bi-arrow-left"></i> Back</a>
        <span class="navbar-title ms-3"><i class="bi bi-pencil-square"></i> Edit Call</span>
    </div>
    <div>
        <span class="small">Logged in as <strong><?= htmlspecialchars($user_name) ?></strong></span>
    </div>
</nav>

<div class="container mt-4">
    <?= $message ?>

    <div class="card p-4">
        <h5 class="section-title"><i class="bi bi-person-lines-fill"></i> Call Information</h5>
        <hr>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Customer Phone Number :</label>
                    <input type="text" name="customer_phone" class="form-control" value="<?= htmlspecialchars($call['customer_phone']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Customer Name :</label>
                    <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($call['customer_name']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email :</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($call['email']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Project :</label>
                    <select name="project_name" class="form-select">
                        <option value="">Select Project</option>
                        <?php foreach($projects as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= $call['project_name']==$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Event :</label>
                    <select name="event" class="form-select">
                        <option value="CALL_COMPLETED" <?= $call['event']=='CALL_COMPLETED'?'selected':'' ?>>CALL_COMPLETED</option>
                        <option value="ABANDON" <?= $call['event']=='ABANDON'?'selected':'' ?>>ABANDON</option>
                    </select>
                </div>
                <div class="col-md-4" id="customer-reason-container">
                    <label class="form-label">Customer Reason :</label>
                    <select name="customer_reason" class="form-select">
                        <option value="Land too Far" <?= $call['customer_reason']=='Land too Far'?'selected':'' ?>>Land too Far</option>
                        <option value="Not Interested" <?= $call['customer_reason']=='Not Interested'?'selected':'' ?>>Not Interested</option>
                        <option value="Reconsidering Other Land" <?= $call['customer_reason']=='Reconsidering Other Land'?'selected':'' ?>>Reconsidering Other Land</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Sentiment :</label>
                    <select name="sentiment" class="form-select" id="sentiment">
                        <option value="Positive" <?= $call['sentiment']=='Positive'?'selected':'' ?>>Positive</option>
                        <option value="Negative" <?= $call['sentiment']=='Negative'?'selected':'' ?>>Negative</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Notes :</label>
                    <textarea name="notes" class="form-control" rows="3"><?= htmlspecialchars($call['notes']) ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Follow-up Date :</label>
                    <input type="date" name="followup_date" class="form-control" value="<?= htmlspecialchars($call['followup_date']) ?>">
                </div>
                <div class="col-md-6" id="appointment-reason-container">
                    <label class="form-label">Appointment Reason :</label>
                    <input type="text" name="appointment_reason" class="form-control" value="<?= htmlspecialchars($call['appointment_reason']) ?>">
                </div>
                <div class="col-md-6" id="appointment-date-container">
                    <label class="form-label">Appointment Date :</label>
                    <input type="date" name="appointment_date" class="form-control" value="<?= htmlspecialchars($call['appointment_date']) ?>">
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" name="update_call" class="btn btn-success">
                    <i class="bi bi-save2"></i> Update Call
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sentimentSelect = document.getElementById('sentiment');
    const customerReason = document.getElementById('customer-reason-container');
    const appointmentReason = document.getElementById('appointment-reason-container');
    const appointmentDate = document.getElementById('appointment-date-container');

    function toggleFields() {
        if (sentimentSelect.value === 'Positive') {
            appointmentReason.style.display = 'block';
            appointmentDate.style.display = 'block';
            customerReason.style.display = 'none';
        } else {
            appointmentReason.style.display = 'none';
            appointmentDate.style.display = 'none';
            customerReason.style.display = 'block';
        }
    }

    sentimentSelect.addEventListener('change', toggleFields);
    toggleFields();
});
</script>

</body>
</html>
