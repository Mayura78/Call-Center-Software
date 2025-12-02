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

// ===== FETCH TARGET =====
$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
if (!$target_id) die("Invalid target selected.");

$stmt = $conn->prepare("SELECT * FROM target WHERE id=? AND user_id=?");
$stmt->bind_param("ii", $target_id, $user_id);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();
if (!$target) die("Target not found.");

// ===== ALL NUMBERS =====
$allNumbers = array_filter(array_map('trim', explode("\n", $target['number_list'])));

// ===== CALLED NUMBERS =====
$stmt2 = $conn->prepare("SELECT * FROM call_log WHERE target_id=? AND user_id=? ORDER BY called_at DESC");
$stmt2->bind_param("ii", $target_id, $user_id);
$stmt2->execute();
$calledResult = $stmt2->get_result();
$calledNumbers = [];
while ($row = $calledResult->fetch_assoc()) $calledNumbers[$row['number_called']] = $row;

// ===== NEXT NUMBER =====
$nextNumber = $_GET['number'] ?? null;
$pendingNumbers = [];
foreach ($allNumbers as $num) {
    if (!in_array($num, array_keys($calledNumbers))) {
        $pendingNumbers[] = $num;
        if ($nextNumber === null) $nextNumber = $num;
    }
}

// ===== FETCH PROJECTS =====
$projectResult = $conn->query("SELECT project_name FROM project_list ORDER BY id DESC");
$projects = [];
while ($row = $projectResult->fetch_assoc()) $projects[] = $row['project_name'];

// ===== FETCH SYSTEM REASONS =====
$reasonResult = $conn->query("SELECT reason FROM system_reasons ORDER BY created_at DESC");
$reasons = [];
while ($row = $reasonResult->fetch_assoc()) $reasons[] = $row['reason'];

// ===== STATIC OPTIONS =====
$events = ["CALL_COMPLETED", "ABANDON"];
$sentiments = ["Positive", "On Hold", "Won", "Negative"];
$appointment_reasons = ["Site Visit"];

// ===== FORM SUBMIT =====
if (isset($_POST['save_call']) && $nextNumber) {
    $event = trim($_POST['event'] ?? '');
    $sentiment = trim($_POST['sentiment'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $project_name = trim($_POST['project_name'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $followup_date = trim($_POST['followup_date'] ?? '');
    $appointment_reason = trim($_POST['appointment_reason'] ?? '');
    $appointment_date = trim($_POST['appointment_date'] ?? '');

    if ($event === 'ABANDON') {
        $sentiment = $reason = $notes = $appointment_reason = $appointment_date = $customer_name = $email = $followup_date = null;

        // Save to missed_calls table
        $saveMissed = $conn->prepare("INSERT INTO missed_calls 
            (user_id, target_id, number, project_name, missed_at)
            VALUES (?,?,?,?,NOW())");
        $saveMissed->bind_param("iiss", $user_id, $target_id, $nextNumber, $target['project_name']);
        $saveMissed->execute();

        header("Location: missedcalls.php?target_id=$target_id");
        exit();
    }

    // If sentiment is On Hold → Save & redirect to onhold.php
    if ($sentiment === 'On Hold') {
        $stmt3 = $conn->prepare("INSERT INTO call_log
            (user_id,target_id,number_called,customer_name,email,project_name,event,reason,sentiment,notes,followup_date,appointment_reason,appointment_date,called_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $stmt3->bind_param("iisssssssssss",
            $user_id, $target_id, $nextNumber, $customer_name, $email,
            $project_name, $event, $reason, $sentiment, $notes,
            $followup_date, $appointment_reason, $appointment_date
        );
        $stmt3->execute();

        header("Location: onhold.php?target_id=$target_id");
        exit();
    }

    if ($sentiment === 'Positive' || $sentiment === 'Won' || $sentiment === 'On Hold') $reason = null;
    if ($sentiment === 'Negative') { $appointment_reason = $appointment_date = null; }

    $stmt3 = $conn->prepare("INSERT INTO call_log
        (user_id,target_id,number_called,customer_name,email,project_name,event,reason,sentiment,notes,followup_date,appointment_reason,appointment_date,called_at)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $stmt3->bind_param("iisssssssssss",
        $user_id, $target_id, $nextNumber, $customer_name, $email,
        $project_name, $event, $reason, $sentiment, $notes,
        $followup_date, $appointment_reason, $appointment_date
    );
    $stmt3->execute();
    header("Location: add_call.php?target_id=$target_id");
    exit();
}

$allCalled = ($nextNumber === null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Call - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C, #001a4d);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-body);
    margin: 0;
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
    text-align:center;
    transition: all 0.3s ease;
    z-index:1000;
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
    color:rgba(220,215,215,1);
    margin:1px 0;
}
.sidebar a, .sidebar button {
    display:block;
    color:var(--sidebar-color);
    text-decoration:none;
    padding:8px 15px;
    text-align:left;
    border:none;
    background:none;
    width:100%;
    transition:0.3s;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
    background: var(--sidebar-hover);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background: rgba(0,0,0,0.15);
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
.main {
    margin-left:220px;
    padding:20px;
    transition: all 0.3s ease;
}
.main.full {
    margin-left:0;
}
.card {
    border-radius:12px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
}
.section-title {
    font-weight:600; color:#fd8d0dff;
}
.table thead th {
    background:#0d6efd;
    color:#fff;
}
.table-responsive {
    overflow-x:auto;
}
.user-guide {
    background:#fff8e1;
    border-left:4px solid #ffc107;
    padding:12px 15px;
    border-radius:6px;
    font-size:14px; color:#444;
    opacity:0;
    transform:translateY(20px);
    animation: slideFadeIn 0.8s ease-out forwards;
}
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); }
}
.navbar-mobile {
    display:none;
    position:fixed;
    top:0;
    left:0;
    right:0;
    height:60px;
    background: var(--sidebar-bg);
    color:white;
    z-index:1100;
    display:flex;
    align-items:center;
    justify-content:space-between;
    padding:0 15px;
    font-weight:600;
}
.navbar-mobile i {
    font-size:1.5rem;
    cursor:pointer;
}

/* Footer */
.footer { background:#0b1b58; color:white; text-align:center; font-size:10px; padding:5px; }
.footer a { color:#ffc107; text-decoration:none; }
.footer a:hover { text-decoration:underline; }

@media (max-width: 992px) { .sidebar { left:-220px; } .sidebar.active { left:0; } .main { margin-left:0; padding-top:70px; } .navbar-mobile { display:flex; } }
</style>
</head>
<body>

<!-- Top Navbar (Mobile) -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Target Number</span>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd, Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name); ?></p>
    </div>
    <a href="home.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-collection"></i> Call Target <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="user_target.php" class="active"><i class="bi bi-bullseye"></i> My Targets</a>
        <a href="view_user_calls.php"><i class="bi bi-telephone-outbound"></i> View Called List</a>
    </div>
    <a href="missedcalls.php"><i class="bi bi-telephone"></i> Missed Calls</a>

    <button class="dropdown-btn"><i class="bi bi-clock me-2"></i> On Hold Calls <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="onhold.php"><i class="bi bi-bullseye me-2"></i> Hold Calls</a>
        <a href="reviewed_onhold.php"><i class="bi bi-telephone-outbound me-2"></i> Reviewed On Hold</a>
    </div>

    <a href="view_user_appointments.php"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>
    <button class="dropdown-btn"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manage_reasons.php"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>
    <div class="logout-btn">
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<div class="main">
    <!-- Target Summary -->
    <div class="card mb-4 p-4">
        <h5 class="section-title mb-3"><i class="bi bi-bar-chart"></i> Target Summary</h5>
        <p>Total Numbers: <span class="badge bg-primary"><?= count($allNumbers) ?></span>
           Called: <span class="badge bg-success"><?= count($calledNumbers) ?></span>
           Pending: <span class="badge bg-warning text-dark"><?= count($pendingNumbers) ?></span>
        </p>
    </div>

    <p class="user-guide">
         Access comprehensive details for each number: view all related information and interactions seamlessly for professional follow-up and management.
    </p>

    <!-- Call Form -->
    <div class="card p-4 mb-4">
        <h5 class="section-title"><i class="bi bi-person-lines-fill"></i> Call Information</h5>
        <hr>
        <?php if ($allCalled): ?>
            <div class="alert alert-info text-center">🎉 All numbers have been called!</div>
        <?php else: ?>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Phone Number :</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($nextNumber) ?>" readonly style="background:#e9ecef;">
                    <small class="text-muted">This is the number to call next.</small>
                </div>

                <div class="col-md-4"><label class="form-label">Event :</label>
                    <select name="event" id="eventSelect" class="form-select">
                        <option value="">-- Select Event --</option>
                        <?php foreach($events as $e): ?>
                            <option value="<?= $e ?>"><?= $e ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6"><label class="form-label">Project :</label>
                    <select name="project_name" class="form-select">
                        <option value="">-- Select Project --</option>
                        <?php foreach($projects as $p): ?>
                            <option value="<?= htmlspecialchars($p) ?>" <?= ($p == $target['project_name']) ? 'selected' : '' ?>><?= htmlspecialchars($p) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6" id="customerNameDiv"><label class="form-label">Customer Name :</label>
                    <input type="text" name="customer_name" class="form-control" placeholder="Enter customer name">
                </div>
                <div class="col-md-6" id="emailDiv"><label class="form-label">Email :</label>
                    <input type="email" name="email" class="form-control" placeholder="Enter customer email (optional)">
                </div>
                
                <div class="col-md-4" id="sentimentDiv"><label class="form-label">Sentiment :</label>
                    <select name="sentiment" id="sentimentSelect" class="form-select">
                        <option value="">-- Select Sentiment --</option>
                        <?php foreach($sentiments as $s): ?>
                            <option value="<?= $s ?>"><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="customerReasonDiv"><label class="form-label">Customer Reason :</label>
                    <select name="reason" class="form-select">
                        <option value="">-- Select Reason --</option>
                        <?php foreach($reasons as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>"><?= htmlspecialchars($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12" id="notesDiv"><label class="form-label">Notes :</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Add any additional notes here..."></textarea>
                </div>
                <div class="col-md-6" id="appointmentReasonDiv"><label class="form-label">Appointment Reason :</label>
                    <select name="appointment_reason" class="form-select">
                        <option value="">-- Select Appointment Reason --</option>
                        <?php foreach($appointment_reasons as $ar): ?>
                            <option value="<?= $ar ?>"><?= $ar ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="appointmentDateDiv"><label class="form-label">Appointment Date :</label>
                    <input type="date" name="appointment_date" class="form-control">
                </div>
                <div class="col-md-6"><label class="form-label">Follow-up Date :</label>
                    <input type="date" name="followup_date" class="form-control">
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" name="save_call" class="btn btn-success"><i class="bi bi-save2"></i> Save Call </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <!-- Pending Numbers -->
    <div class="card p-3 mb-4">
        <h5 class="section-title"><i class="bi bi-hourglass-split"></i> Pending Numbers</h5>
        <?php if (empty($pendingNumbers)): ?>
            <div class="alert alert-info text-center">No pending numbers.</div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($pendingNumbers as $num): ?>
                    <a href="?target_id=<?= $target_id ?>&number=<?= urlencode($num) ?>" 
                       class="btn btn-sm btn-outline-primary"><?= htmlspecialchars($num) ?> <i class="bi bi-telephone-forward"></i></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Called Numbers -->
    <p class="user-guide">Phone numbers you have already called are listed below with all relevant details for your reference.</p>
    <div class="card p-3 mb-5">
        <h5 class="section-title"><i class="bi bi-check2-circle"></i> Called Numbers</h5>
        <?php if (empty($calledNumbers)): ?>
            <div class="alert alert-info">No calls recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered text-center align-middle">
                <thead>
                    <tr>
                        <th>#</th><th>Number</th><th>Name</th><th>Email</th><th>Project</th><th>Event</th><th>Reason</th><th>Sentiment</th><th>Notes</th><th>Follow-up</th><th>Appointment Reason</th><th>Appointment</th><th>Called At</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; foreach ($calledNumbers as $num => $d): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($num) ?></td>
                        <td><?= htmlspecialchars($d['customer_name']) ?></td>
                        <td><?= htmlspecialchars($d['email']) ?></td>
                        <td><?= htmlspecialchars($d['project_name']) ?></td>
                        <td><?= htmlspecialchars($d['event']) ?></td>
                        <td><?= htmlspecialchars($d['reason']) ?></td>
                        <td><?= htmlspecialchars($d['sentiment']) ?></td>
                        <td><?= nl2br(htmlspecialchars($d['notes'])) ?></td>
                        <td><?= htmlspecialchars($d['followup_date']) ?></td>
                        <td><?= htmlspecialchars($d['appointment_reason']) ?></td>
                        <td><?= htmlspecialchars($d['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($d['called_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
  &copy; <?= date('Y'); ?> Commercial Realty (Pvt) Ltd | Developed by <span class="text-warning">Mayura Lasantha</span>
</div>

<script>
// Sidebar dropdown
document.querySelectorAll('.dropdown-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        const content = btn.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    });
});

// Mobile toggle
document.getElementById('mobileSidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// Dynamic field visibility
function toggleFields() {
    const eventVal = document.getElementById('eventSelect').value;
    const sentimentVal = document.getElementById('sentimentSelect').value;
    const divs = ['sentimentDiv','customerReasonDiv','appointmentReasonDiv','appointmentDateDiv','notesDiv','customerNameDiv','emailDiv'];
    divs.forEach(id=>document.getElementById(id).style.display='block');
    if(eventVal==='ABANDON') divs.forEach(id=>document.getElementById(id).style.display='none');
    else if(sentimentVal==='Positive' || sentimentVal==='On Hold' || sentimentVal==='Won') document.getElementById('customerReasonDiv').style.display='none';
    else if(sentimentVal==='Negative'){ document.getElementById('appointmentReasonDiv').style.display='none'; document.getElementById('appointmentDateDiv').style.display='none'; }
}
document.getElementById('eventSelect').addEventListener('change', toggleFields);
document.getElementById('sentimentSelect').addEventListener('change', toggleFields);
window.addEventListener('load', toggleFields);
</script>

</body>
</html>
