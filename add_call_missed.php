<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';
if (!$user_id) { header("Location: login.php"); exit(); }

// ===== CREATE TABLES IF NOT EXIST =====
$conn->query("CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    number VARCHAR(20) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS reason (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ===== Fetch previous call if call_id or number is provided =====
$call_id = $_GET['call_id'] ?? null;
$number = $_GET['number'] ?? null;
$call = null;

if ($call_id) {
    $stmt = $conn->prepare("SELECT * FROM call_log WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $call_id, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $call = $res->fetch_assoc();
} elseif ($number) {
    // Fetch latest call for this number
    $stmt = $conn->prepare("SELECT * FROM call_log WHERE number_called=? AND user_id=? ORDER BY called_at DESC LIMIT 1");
    $stmt->bind_param("si", $number, $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $call = $res->fetch_assoc();
}

// ===== Fetch customer list =====
$customers = $conn->query("SELECT id, name, number, email FROM customers WHERE user_id='$user_id'");

// ===== Fetch reasons =====
$reasons = $conn->query("SELECT reason FROM reason WHERE user_id='$user_id'");

// ===== Form submission =====
$message = "";
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;
    $name = $_POST['name'] ?? '';
    $number = $_POST['number'] ?? '';
    $email = $_POST['email'] ?? '';
    $event = $_POST['event'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $appointment_reason = $_POST['appointment_reason'] ?? '';
    $sentiment = $_POST['sentiment'] ?? '';
    $notes = trim($_POST['notes'] ?? '');
    $followup_date = $_POST['followup_date'] ?? null;
    $appointment_date = $_POST['appointment_date'] ?? null;

    if (!$name || !$number || !$event) {
        $error = "Name, Number, and Event are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO call_log (user_id, customer_name, number_called, email, event, reason, appointment_reason, sentiment, notes, followup_date, appointment_date, called_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issssssssss", $user_id, $name, $number, $email, $event, $reason, $appointment_reason, $sentiment, $notes, $followup_date, $appointment_date);
        if ($stmt->execute()) {
            $message = "✅ Call logged successfully!";
            $call = null;
        } else {
            $error = "❌ Error: " . $stmt->error;
        }
    }
}

// ===== Prepare customer data for auto-fill =====
$customer_list = [];
$customers->data_seek(0);
while ($c = $customers->fetch_assoc()) {
    $customer_list[$c['id']] = $c;
}

// ===== Fetch reasons as array =====
$reason_list = [];
while ($r = $reasons->fetch_assoc()) {
    $reason_list[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Missed Call</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root { --sidebar-bg:#00205C; --sidebar-color:#fff; --sidebar-hover:rgba(255,255,255,0.1); }
body {
    font-family:'Poppins',sans-serif;
    background:#f8f9fa;
    margin:0;
}
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:220px;
    height:100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-color);
    overflow-y:auto;
    padding-top:20px;
}
.sidebar .sidebar-header {
    display:flex;
    flex-direction:column;
    align-items:center;
    margin-bottom:15px;
}
.sidebar .sidebar-header img.logo {
    width:180px;
    border-radius:5px;
    margin-bottom:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color: rgba(220,215,215,1);
    margin:1px 0;
}
.sidebar a, .sidebar button {
    display:block;
    color:var(--sidebar-color);
    text-decoration:none;
    padding:8px 15px;
    font-size:14px;
    border:none;
    background:none;
    width:100%;
    text-align:left;
    transition:0.3s;
}
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
    background:var(--sidebar-hover);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background:rgba(0,0,0,0.15);
}
.sidebar .dropdown-container a {
    padding-left:30px;
    font-size:13px;
}
.logout-btn {
    position:absolute;
    bottom:20px;
    width:100%;
}
.logout-btn a {
    background:rgba(255,255,255,0.2);
    font-weight:500;
    display:block;
    padding:8px 15px;
    text-align:center;
    border-radius:5px;
}
.main-content {
    margin-left:240px;
    padding:20px;
}
.card {
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}
h3 {
    color:#0d6efd;
    }
</style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name) ?></p>
    </div>
    <a href="home.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<div class="main-content">
<h3 class="mb-4"><i class="bi bi-telephone-plus"></i> Add Missed Call</h3>
<?php if($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<?php if($call): ?>
<div class="card mb-3 p-3">
<h5><i class="bi bi-clock-history"></i> Previous Call Details</h5>
<ul class="list-group list-group-flush">
    <li class="list-group-item"><strong>Name:</strong> <?= htmlspecialchars($call['customer_name']) ?></li>
    <li class="list-group-item"><strong>Number:</strong> <?= htmlspecialchars($call['number_called']) ?></li>
    <li class="list-group-item"><strong>Email:</strong> <?= htmlspecialchars($call['email']) ?></li>
    <li class="list-group-item"><strong>Event:</strong> <?= htmlspecialchars($call['event']) ?></li>
    <li class="list-group-item"><strong>Reason:</strong> <?= htmlspecialchars($call['reason']) ?></li>
    <li class="list-group-item"><strong>Appointment:</strong> <?= htmlspecialchars($call['appointment_reason']) ?></li>
    <li class="list-group-item"><strong>Sentiment:</strong> <?= htmlspecialchars($call['sentiment']) ?></li>
    <li class="list-group-item"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($call['notes'])) ?></li>
    <li class="list-group-item"><strong>Follow-up:</strong> <?= htmlspecialchars($call['followup_date']) ?></li>
    <li class="list-group-item"><strong>Appointment Date:</strong> <?= htmlspecialchars($call['appointment_date']) ?></li>
</ul>
</div>
<?php endif; ?>

<div class="card p-4">
<form method="POST" id="callForm">
<div class="row g-3">
<div class="col-md-6">
</div>
<div class="col-md-6">
<label class="form-label">Customer Name <span class="text-danger">*</span></label>
<input type="text" name="name" id="nameInput" class="form-control" required value="<?= $call['customer_name'] ?? '' ?>">
</div>
<div class="col-md-6">
<label class="form-label">Number <span class="text-danger">*</span></label>
<input type="text" name="number" id="numberInput" class="form-control" required value="<?= $call['number_called'] ?? $_GET['number'] ?? '' ?>">
</div>
<div class="col-md-6">
<label class="form-label">Email</label>
<input type="email" name="email" id="emailInput" class="form-control" value="<?= $call['email'] ?? '' ?>">
</div>
<div class="col-md-6">
<label class="form-label">Event <span class="text-danger">*</span></label>
<select name="event" class="form-select" required>
<option value="">-- Select Event --</option>
<option value="CALL_COMPLETED" <?= ($call['event']=='CALL_COMPLETED')?'selected':'' ?>>CALL_COMPLETED</option>
<option value="ABANDON" <?= ($call['event']=='ABANDON')?'selected':'' ?>>ABANDON</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Appointment Reason</label>
<select name="appointment_reason" class="form-select">
<option value="">-- Select Appointment --</option>
<option value="Site Visit" <?= ($call['appointment_reason']=='Site Visit')?'selected':'' ?>>Site Visit</option>
<option value="Call Back" <?= ($call['appointment_reason']=='Call Back')?'selected':'' ?>>Call Back</option>
<option value="Meeting" <?= ($call['appointment_reason']=='Meeting')?'selected':'' ?>>Meeting</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Reason</label>
<select name="reason" class="form-select">
<option value="">-- Select Reason --</option>
<?php foreach($reason_list as $r): ?>
<option value="<?= htmlspecialchars($r['reason']) ?>" <?= ($call && $call['reason']==$r['reason'])?'selected':'' ?>><?= htmlspecialchars($r['reason']) ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Sentiment</label>
<select name="sentiment" class="form-select">
<option value="">-- Select Sentiment --</option>
<option value="Positive" <?= ($call['sentiment']=='Positive')?'selected':'' ?>>Positive</option>
<option value="Negative" <?= ($call['sentiment']=='Negative')?'selected':'' ?>>Negative</option>
<option value="On Hold" <?= ($call['sentiment']=='On Hold')?'selected':'' ?>>On Hold</option>
<option value="Won" <?= ($call['sentiment']=='Won')?'selected':'' ?>>Won</option>
</select>
</div>
<div class="col-md-6">
<label class="form-label">Follow-up Date</label>
<input type="date" name="followup_date" class="form-control" value="<?= $call['followup_date'] ?? '' ?>">
</div>
<div class="col-md-6">
<label class="form-label">Appointment Date</label>
<input type="date" name="appointment_date" class="form-control" value="<?= $call['appointment_date'] ?? '' ?>">
</div>
<div class="col-12">
<label class="form-label">Notes</label>
<textarea name="notes" class="form-control" rows="3"><?= $call['notes'] ?? '' ?></textarea>
</div>
</div>

<div class="mt-4 d-flex gap-2">
<button type="submit" class="btn btn-primary"><i class="bi bi-save2"></i> Save Call</button>
<a href="viewallcalllist.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back</a>
</div>

</form>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar toggle
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click",()=>{ btn.classList.toggle("active"); const container=btn.nextElementSibling; container.style.display = container.style.display==='block'?'none':'block'; });
});

// Auto-fill customer details on select
const customerSelect = document.getElementById('customerSelect');
const nameInput = document.getElementById('nameInput');
const numberInput = document.getElementById('numberInput');
const emailInput = document.getElementById('emailInput');

const customerData = <?= json_encode($customer_list); ?>;

customerSelect.addEventListener('change',()=>{
    const sel = customerSelect.value;
    if(sel && customerData[sel]){
        nameInput.value = customerData[sel].name;
        numberInput.value = customerData[sel].number;
        emailInput.value = customerData[sel].email;
    }
});
</script>
</body>
</html>
