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

// ===== GET FILTER INPUTS =====
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ===== BUILD QUERY =====
$sql = "SELECT * FROM call_log WHERE user_id=? AND sentiment='On Hold' AND reviewed_on_hold=1";
$params = [$user_id];
$types = "i";

if ($search !== '') {
    $sql .= " AND (customer_name LIKE ? OR number_called LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= "ss";
}

if ($date_from !== '') {
    $sql .= " AND followup_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $sql .= " AND followup_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

$sql .= " ORDER BY called_at DESC";

// ===== PREPARE AND EXECUTE =====
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$reviewedCalls = [];
while ($row = $result->fetch_assoc()) $reviewedCalls[] = $row;

$totalReviewed = count($reviewedCalls);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reviewed On Hold Calls - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background:#eef2f7; font-family:'Poppins',sans-serif; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:220px; height:100vh; background:linear-gradient(180deg,#00205C,#001a4d); color:white; overflow-y:auto; padding-top:20px; text-align:center; transition:all 0.3s ease; z-index:1000; }
.sidebar .sidebar-header { display:flex; flex-direction:column; align-items:center; margin-bottom:15px; }
.sidebar .sidebar-header img.logo { width:180px; border-radius:5px; margin-bottom:5px; }
.sidebar .sidebar-header p { font-size:0.8rem; color:rgba(220,215,215,1); margin:1px 0; }
.sidebar a, .sidebar button { display:block; color:white; text-decoration:none; padding:8px 15px; text-align:left; border:none; background:none; width:100%; transition:0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sidebar a:hover, .sidebar a.active, .sidebar button:hover { background: rgba(255,255,255,0.2); border-left:4px solid #ffc107; }
.sidebar .dropdown-container { display:none; background: rgba(0,0,0,0.15); }
.sidebar .dropdown-container a { padding-left:30px; font-size:13px; }
.logout-btn { position:absolute; bottom:20px; width:100%; }
.main { margin-left:220px; padding:20px; transition: all 0.3s ease; }
.card { border-radius:12px; box-shadow:0 3px 10px rgba(0,0,0,0.1); margin-bottom:20px; }
.section-title { font-weight:600; color:#fd8d0dff; margin-bottom:15px; }
.table thead th { background:#0d6efd; color:#fff; }
.table-responsive { overflow-x:auto; }
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; margin-bottom:15px; }
.navbar-mobile { display:none; position:fixed; top:0; left:0; right:0; height:60px; background:#00205C; color:white; z-index:1100; display:flex; align-items:center; justify-content:space-between; padding:0 15px; font-weight:600; }
.navbar-mobile i { font-size:1.5rem; cursor:pointer; }
.form-inline input, .form-inline button { margin-right:5px; }
@media (max-width: 992px) { .sidebar { left:-220px; } .sidebar.active { left:0; } .main { margin-left:0; padding-top:70px; } .navbar-mobile { display:flex; } }
</style>
</head>
<body>

<!-- Top Navbar Mobile -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Reviewed On Hold Calls</span>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Company Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name) ?></p>
    </div>

    <a href="home.php" class="<?= $current_page=='home.php'?'active':'' ?>"><i class="bi bi-house-door"></i> Dashboard</a>

    <button class="dropdown-btn <?= in_array($current_page,['user_target.php','view_user_calls.php'])?'active':'' ?>"><i class="bi bi-collection"></i> Call Target <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="<?= in_array($current_page,['user_target.php','view_user_calls.php'])?'display:block':'' ?>">
        <a href="user_target.php" class="<?= $current_page=='user_target.php'?'active':'' ?>"><i class="bi bi-bullseye"></i> Performance Goals</a>
        <a href="view_user_calls.php" class="<?= $current_page=='view_user_calls.php'?'active':'' ?>"><i class="bi bi-telephone-outbound"></i> Performance Overview</a>
    </div>

    <a href="missedcalls.php" class="<?= $current_page=='missedcalls.php'?'active':'' ?>"><i class="bi bi-telephone"></i> Missed Calls</a>

    <button class="dropdown-btn <?= in_array($current_page,['onhold.php','reviewed_onhold.php'])?'active':'' ?>"><i class="bi bi-clock me-2"></i> On Hold Calls <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="<?= in_array($current_page,['onhold.php','reviewed_onhold.php'])?'display:block':'' ?>">
        <a href="onhold.php" class="<?= $current_page=='onhold.php'?'active':'' ?>"><i class="bi bi-bullseye me-2"></i> Hold Calls</a>
        <a href="reviewed_onhold.php" class="<?= $current_page=='reviewed_onhold.php'?'active':'' ?>"><i class="bi bi-check-circle me-2"></i> Reviewed On Hold</a>
    </div>

    <a href="view_user_appointments.php" class="<?= $current_page=='view_user_appointments.php'?'active':'' ?>"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php" class="<?= $current_page=='viewallcalllist.php'?'active':'' ?>"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="appointmentlist.php" class="<?= $current_page=='appointmentlist.php'?'active':'' ?>"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <a href="userreports.php" class="<?= $current_page=='userreports.php'?'active':'' ?>"><i class="bi bi-bar-chart"></i> Reports</a>

    <button class="dropdown-btn <?= in_array($current_page,['manage_reasons.php'])?'active':'' ?>"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="<?= in_array($current_page,['manage_reasons.php'])?'display:block':'' ?>">
        <a href="manage_reasons.php" class="<?= $current_page=='manage_reasons.php'?'active':'' ?>"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>

    <div class="logout-btn">
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>


<div class="main">
    <h3 class="mb-3"><i class="bi bi-check-circle"></i> Reviewed On Hold Calls</h3>

    <div class="card p-3 mb-3">
        <form method="GET" class="form-inline d-flex flex-wrap align-items-center">
            <input type="text" name="search" class="form-control mb-2" placeholder="Search by name or number" value="<?= htmlspecialchars($search) ?>">
            <input type="date" name="date_from" class="form-control mb-2" value="<?= htmlspecialchars($date_from) ?>">
            <input type="date" name="date_to" class="form-control mb-2" value="<?= htmlspecialchars($date_to) ?>">
            <button type="submit" class="btn btn-primary mb-2"><i class="bi bi-search"></i> Filter</button>
            <a href="reviewed_onhold.php" class="btn btn-secondary mb-2 ms-2">Reset</a>
        </form>
    </div>

    <?php if($totalReviewed>0): ?>
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Customer Name</th>
                        <th>Project</th>
                        <th>Notes</th>
                        <th>Follow-up Date</th>
                        <th>Called At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $j=1; foreach($reviewedCalls as $r): ?>
                    <tr>
                        <td><?= $j++ ?></td>
                        <td><?= htmlspecialchars($r['number_called']) ?></td>
                        <td><?= htmlspecialchars($r['customer_name']) ?></td>
                        <td><?= htmlspecialchars($r['project_name']) ?></td>
                        <td><?= nl2br(htmlspecialchars($r['notes'])) ?></td>
                        <td><?= htmlspecialchars($r['followup_date']) ?></td>
                        <td><?= htmlspecialchars($r['called_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php else: ?>
        <div class="alert alert-info">No reviewed On Hold calls found.</div>
    <?php endif; ?>
</div>

<script>
const dropdowns = document.querySelectorAll(".dropdown-btn");
dropdowns.forEach(btn => btn.addEventListener("click", () => {
    btn.classList.toggle("active");
    let container = btn.nextElementSibling;
    container.style.display = container.style.display === "block" ? "none" : "block";
}));

document.getElementById("mobileSidebarToggle").addEventListener("click", () => {
    document.querySelector(".sidebar").classList.toggle("active");
});
</script>

</body>
</html>
