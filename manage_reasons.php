<?php
session_start();
include 'db.php';

// ===== Check admin login =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Admin';
$message = "";

// ===== Add Reason =====
if (isset($_POST['add_reason'])) {
    $reason = trim($_POST['reason']);
    $type = $_POST['reason_type'] ?? 'Customer';
    if ($reason !== "") {
        $stmt = $conn->prepare("INSERT INTO system_reasons (reason_type, reason) VALUES (?, ?)");
        $stmt->bind_param("ss", $type, $reason);
        if ($stmt->execute()) {
            $message = "✅ $type reason added successfully!";
        } else {
            $message = "❌ Error adding reason: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "⚠️ Reason cannot be empty!";
    }
}

// ===== Delete Reason =====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM system_reasons WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: manage_reasons.php");
    exit();
}

// ===== Fetch Reasons =====
$customerReasons = $conn->query("SELECT * FROM system_reasons WHERE reason_type='Customer' ORDER BY created_at DESC");
$appointmentReasons = $conn->query("SELECT * FROM system_reasons WHERE reason_type='Appointment' ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage System Reasons | Commercial Realty</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C, #0d1f5b);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body {
    font-family:'Poppins',sans-serif;
    background:var(--bg-body);
    margin:0;
}
.sidebar {
    position:fixed;
    top:0; left:0;
    width:220px; height:100vh;
    background:var(--sidebar-bg);
    color:var(--sidebar-color);
    padding-top:20px;
    overflow-y:auto;
    text-align:center;
    transition: transform 0.3s;
}
.sidebar .sidebar-header img {
    width:180px;
    border-radius:5px;
    margin-bottom:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color:rgba(230,230,230,0.9);
    margin:0 0 10px;
    text-align:center;
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
    color:#ffc107;
}
.main {
    margin-left:220px;
    padding:25px;
    transition: margin-left 0.3s;
}
.card {
    border-radius:10px;
    box-shadow:0 3px 10px rgba(0,0,0,0.1);
    background:white;
    margin-bottom:20px;
}
.table thead th {
    background:#00205C;
    color:white;
}
.table td {
    font-size:0.85rem;
}
h4.title {
    color:#0d6efd;
    font-weight:600;
}
.user-guide {
    background:#fff8e1;
    border-left:4px solid #ffc107;
    padding:12px 15px;
    border-radius:6px;
    font-size:14px;
    color:#444;
    opacity:0;
    transform:translateY(20px);
    animation: slideFadeIn 0.8s ease-out forwards;
}

/* Footer */
.footer { background:#0b1b58; color:white; text-align:center; font-size:10px; padding:5px; }
.footer a { color:#ffc107; text-decoration:none; }
.footer a:hover { text-decoration:underline; }

@keyframes slideFadeIn {
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
}

/* Mobile */
@media(max-width:992px){
    .sidebar { transform:translateX(-100%); position:fixed; z-index:1050; }
    .sidebar.active { transform:translateX(0); }
    .main { margin-left:0; padding:15px; }
    .mobile-toggle { display:flex; justify-content:space-between; align-items:center; background:#00205C; color:white; padding:10px 15px; font-weight:600; }
}
</style>
</head>
<body>

<!-- Mobile Toggle -->
<div class="mobile-toggle d-lg-none">
    <span>Manage System Reasons</span>
    <i class="bi bi-list" id="sidebarToggle" style="font-size:1.5rem; cursor:pointer;"></i>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name) ?></p>
    </div>

    <a href="home.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-collection"></i> Call Target <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="user_target.php"><i class="bi bi-bullseye"></i> Performance Goals</a>
        <a href="view_user_calls.php"><i class="bi bi-telephone-outbound"></i> Performance Overview</a>
    </div>

    <a href="missedcalls.php"><i class="bi bi-telephone"></i> Missed Calls</a>

    <button class="dropdown-btn"><i class="bi bi-clock me-2"></i> On Hold Calls <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="onhold.php"><i class="bi bi-bullseye me-2"></i> Hold Calls</a>
        <a href="reviewed_onhold.php"><i class="bi bi-telephone-outbound me-2"></i> Reviewed On Hold</a>
    </div>

    <a href="view_user_appointments.php"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="appointmentlist.php"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>

    <button class="dropdown-btn active"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="manage_reasons.php" class="active"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>

    <div class="logout-btn">
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <h4 class="title mb-3"><i class="bi bi-gear-wide"></i> Manage System Reasons</h4>
    <p class="user-guide">
            Update profile, system preferences, and other configurable options.
    </p>

    <?php if($message): ?>
        <div class="alert alert-info"><?= $message ?></div>
    <?php endif; ?>

    <!-- Add Form -->
    <div class="card p-3 mb-3">
        <h5><i class="bi bi-plus-circle"></i> Add New Reason</h5>
        <form method="POST" class="row g-2 align-items-center">
            <div class="col-md-6 col-sm-12">
                <input type="text" name="reason" class="form-control" placeholder="Enter reason" required>
            </div>
            <div class="col-md-4 col-sm-12">
                <select name="reason_type" class="form-select" required>
                    <option value="Customer">Customer Reason</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-12">
                <button type="submit" name="add_reason" class="btn btn-primary w-100"><i class="bi bi-plus"></i> Add</button>
            </div>
        </form>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="reasonTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="customer-tab" data-bs-toggle="tab" data-bs-target="#customer" type="button" role="tab">Customer Reasons</button>
        </li>
    </ul>

    <div class="tab-content p-3 border bg-white">
        <!-- Customer Reasons -->
        <div class="tab-pane fade show active" id="customer" role="tabpanel">
            <div class="table-responsive">
                <table class="table table-bordered text-center align-middle">
                    <thead><tr><th>ID</th><th>Reason</th><th>Created At</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php while($r = $customerReasons->fetch_assoc()): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['reason']) ?></td>
                            <td><?= $r['created_at'] ?></td>
                            <td><a href="?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this reason?')" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i> Delete</a></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="footer">
  &copy; <?= date('Y'); ?> Commercial Realty (Pvt) Ltd | Developed by <span class="text-warning">Mayura Lasantha</span>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar dropdown
const dropdowns = document.querySelectorAll('.dropdown-btn');
dropdowns.forEach(btn => {
    btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        const content = btn.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    });
});

// Mobile sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function(){
    document.getElementById('sidebar').classList.toggle('active');
});
</script>
</body>
</html>
