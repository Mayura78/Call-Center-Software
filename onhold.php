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

// ===== ADD reviewed_on_hold COLUMN IF NOT EXISTS =====
$columnCheck = $conn->query("SHOW COLUMNS FROM call_log LIKE 'reviewed_on_hold'");
if ($columnCheck->num_rows == 0) {
    $conn->query("ALTER TABLE call_log ADD COLUMN reviewed_on_hold TINYINT(1) DEFAULT 0");
}

// ===== MARK ON HOLD CALL AS REVIEWED =====
if (isset($_GET['review_id'])) {
    $review_id = (int)$_GET['review_id'];
    $stmtMark = $conn->prepare("UPDATE call_log SET reviewed_on_hold=1 WHERE id=? AND user_id=?");
    $stmtMark->bind_param("ii", $review_id, $user_id);
    $stmtMark->execute();
    header("Location: onhold.php");
    exit();
}

// ===== FILTER DATE =====
$filter_date = $_GET['filter_date'] ?? '';
$date_condition = '';
if (!empty($filter_date)) {
    $date_condition = "AND DATE(called_at) = ?";
}

// ===== FETCH ON HOLD CALLS =====
$sql = "SELECT * FROM call_log WHERE user_id=? AND sentiment='On Hold' AND reviewed_on_hold=0 $date_condition ORDER BY called_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($filter_date)) {
    $stmt->bind_param("is", $user_id, $filter_date);
} else {
    $stmt->bind_param("i", $user_id);
}
$stmt->execute();
$result = $stmt->get_result();
$onHoldCalls = [];
while ($row = $result->fetch_assoc()) $onHoldCalls[] = $row;

// ===== FETCH REVIEWED ON HOLD CALLS COUNT =====
$stmt2 = $conn->prepare("SELECT COUNT(*) as total FROM call_log WHERE user_id=? AND sentiment='On Hold' AND reviewed_on_hold=1");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$res2 = $stmt2->get_result()->fetch_assoc();
$totalReviewed = $res2['total'];

$totalOnHold = count($onHoldCalls);
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>On Hold Calls - CR Call Center</title>
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
.table thead th { background:#0d6efd; color:#fff; }
.table-responsive { overflow-x:auto; }
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; margin-bottom:15px; }
.navbar-mobile { display:none; position:fixed; top:0; left:0; right:0; height:60px; background:#00205C; color:white; z-index:1100; display:flex; align-items:center; justify-content:space-between; padding:0 15px; font-weight:600; }
.navbar-mobile i { font-size:1.5rem; cursor:pointer; }
@media (max-width: 992px) { .sidebar { left:-220px; } .sidebar.active { left:0; } .main { margin-left:0; padding-top:70px; } .navbar-mobile { display:flex; } }
</style>
</head>
<body>

<!-- Top Navbar Mobile -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>On Hold Calls</span>
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
    <h3 class="mb-3"><i class="bi bi-hand-index"></i> On Hold Calls</h3>

    <form method="GET" class="mb-3">
        <label for="filter_date">Filter by Date:</label>
        <input type="date" id="filter_date" name="filter_date" class="form-control d-inline-block w-auto" value="<?= htmlspecialchars($filter_date) ?>">
        <button type="submit" class="btn btn-primary btn-sm">Filter</button>
        <?php if(!empty($filter_date)): ?>
            <a href="onhold.php" class="btn btn-secondary btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <?php if ($totalOnHold > 0): ?>
        <div class="alert alert-warning">
            You have <strong><?= $totalOnHold ?></strong> call(s) currently on hold.
        </div>
    <?php else: ?>
        <div class="alert alert-success">🎉 No calls are currently on hold.</div>
    <?php endif; ?>

    <div class="user-guide">Mark calls as reviewed after follow-up so you can track handled On Hold calls separately.</div>

    <div class="card p-3">
        <?php if (empty($onHoldCalls)): ?>
            <div class="alert alert-info text-center">No On Hold calls recorded yet.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped align-middle text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Customer Name</th>
                        <th>Email</th>
                        <th>Project</th>
                        <th>Event</th>
                        <th>Reason</th>
                        <th>Notes</th>
                        <th>Follow-up Date</th>
                        <th>Resume</th>
                        <th>Mark Reviewed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach($onHoldCalls as $call): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($call['number_called']) ?></td>
                            <td><?= htmlspecialchars($call['customer_name']) ?></td>
                            <td><?= htmlspecialchars($call['email']) ?></td>
                            <td><?= htmlspecialchars($call['project_name']) ?></td>
                            <td><?= htmlspecialchars($call['event']) ?></td>
                            <td><?= htmlspecialchars($call['reason']) ?></td>
                            <td><?= nl2br(htmlspecialchars($call['notes'])) ?></td>
                            <td><?= htmlspecialchars($call['followup_date']) ?></td>
                            <td>
                                <a href="add_call.php?target_id=<?= $call['target_id'] ?>&number=<?= urlencode($call['number_called']) ?>" class="btn btn-sm btn-primary">
                                    Resume
                                </a>
                            </td>
                            <td>
                                <a href="?review_id=<?= $call['id'] ?>" class="btn btn-sm btn-success">
                                    Reviewed
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if($totalReviewed>0): ?>
        <div class="mt-4">
            <a href="reviewed_onhold.php" class="btn btn-info">View Reviewed On Hold Calls (<?= $totalReviewed ?>)</a>
        </div>
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
    document.getElementById("sidebar").classList.toggle("active");
});
</script>

</body>
</html>
