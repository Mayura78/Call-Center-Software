<?php
session_start();
include 'db.php';

// ===== Check login =====
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// ===== Fetch all targets assigned only to the logged-in user =====
$stmt = $conn->prepare("
    SELECT t.id, t.project_name, t.start_date, t.end_date, t.total_count, 
           GROUP_CONCAT(tn.number SEPARATOR '\n') AS number_list
    FROM target t
    LEFT JOIN target_numbers tn ON tn.target_id = t.id
    WHERE t.user_id = ?
    GROUP BY t.id
    ORDER BY t.id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$targets = $stmt->get_result();

// ===== If a target is selected, fetch its call logs for this user only =====
$selected_target_id = $_GET['target_id'] ?? null;
$call_logs = [];

if ($selected_target_id) {
    $stmt2 = $conn->prepare("
        SELECT * 
        FROM call_log 
        WHERE target_id=? AND user_id=? 
        ORDER BY called_at DESC
    ");
    $stmt2->bind_param("ii", $selected_target_id, $user_id);
    $stmt2->execute();
    $call_logs = $stmt2->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Targets - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --sidebar-bg: #00205C;
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-body);
    margin: 0;
    overflow-x: hidden;
}

/* ===== MOBILE TOP NAVBAR ===== */
.navbar-mobile {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0;
    height: 60px;
    background: var(--sidebar-bg);
    color: white;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 15px;
    font-weight: 600;
}
.navbar-mobile i { font-size: 1.5rem; cursor: pointer; }

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 220px;
    height: 100vh;
    background: var(--sidebar-bg);
    color: var(--sidebar-color);
    overflow-y: auto;
    padding-top: 20px;
    text-align: center;
    transition: all 0.3s ease;
    z-index: 1050;
}
.sidebar .sidebar-header img.logo {
    width: 180px;
    border-radius: 5px;
    margin-bottom: 5px;
}
.sidebar .sidebar-header p {
    font-size: 0.8rem;
    color: rgba(230,230,230,0.9);
    margin:0 0 10px;
    text-align:center;
}
.sidebar a, .sidebar button {
    display: block;
    color: var(--sidebar-color);
    text-decoration: none;
    padding: 10px 18px;
    text-align: left;
    border: none;
    background: none;
    width: 100%;
    transition: 0.3s;
    font-size: 15px;
}
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
    background: var(--sidebar-hover);
    border-left: 4px solid #ffc107;
}
.sidebar .dropdown-container {
    display: none;
    background: rgba(0,0,0,0.15);
}
.sidebar .dropdown-container a {
    padding-left: 35px;
    font-size: 14px;
}
.logout-btn {
    position: absolute;
    bottom: 15px;
    width: 100%;
}

/* ===== MAIN CONTENT ===== */
.main {
    margin-left: 230px;
    padding: 20px;
    transition: margin-left 0.3s ease;
}
.card { border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.1); }
.table thead th {
    background: #0d6efd;
    color: #fff;
    position: sticky;
    top: 0;
}
.search-bar {
    max-width: 350px;
}
h4.title {
    color: #0d6efd;
    font-weight: 600;
}
.badge-info {
    background-color: #17a2b8;
}
.badge-warning {
    background-color: #ffc107;
    color:#212529;
}
.btn-called {
    background-color: #6c757d;
    color: #fff;
    cursor: default;
}
.user-guide {
    background: #fff8e1;
    border-left: 4px solid #ffc107;
    padding: 12px 15px;
    border-radius: 6px;
    font-size: 14px;
    color: #444;
    opacity: 0;
    transform: translateY(20px);
    animation: slideFadeIn 0.8s ease-out forwards;
}
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }

/* ===== MOBILE RESPONSIVE ===== */
@media(max-width:992px) {
    .navbar-mobile { display: flex; }
    .sidebar { left: -250px; }
    .sidebar.active { left: 0; }
    .main { margin-left: 0 !important; padding-top: 70px; }
}
</style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>My Target</span>
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
    <div class="dropdown-container" style="display:block;">
        <a href="user_target.php" class="active"><i class="bi bi-bullseye"></i> Performance Goals</a>
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

    <button class="dropdown-btn"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manage_reasons.php"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>

    <div class="logout-btn">
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="main">

    <div class="card p-3 mb-4">
        <div class="d-flex justify-content-between align-items-center">
            <h4 class="title"><i class="bi bi-list-task"></i> Your Targets</h4>
        </div>
    </div>

    <p class="user-guide">
        Shows the calls each agent is expected to make. Users can see their assigned targets and track progress.
    </p>

    <div class="card p-3 mb-4">
        <?php if ($targets->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Project / Target</th>
                        <th>Total</th>
                        <th>Pending</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i=1; while($row = $targets->fetch_assoc()): 
                    $numbers = array_filter(array_map('trim', explode("\n", $row['number_list'])));
                    $stmtNum = $conn->prepare("SELECT number_called FROM call_log WHERE target_id=? AND user_id=?");
                    $stmtNum->bind_param("ii", $row['id'], $user_id);
                    $stmtNum->execute();
                    $calledRes = $stmtNum->get_result();
                    $calledNumbers = [];
                    while($cn = $calledRes->fetch_assoc()) $calledNumbers[] = $cn['number_called'];
                    $pendingNumbers = array_diff($numbers, $calledNumbers);
                    $nextNumber = $pendingNumbers ? array_values($pendingNumbers)[0] : null;
                ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['project_name']) ?><br><small>(<?= htmlspecialchars($row['start_date']) ?> → <?= htmlspecialchars($row['end_date']) ?>)</small></td>
                        <td><?= count($numbers) ?></td>
                        <td><span class="badge bg-warning text-dark"><?= count($pendingNumbers) ?></span></td>
                        <td>
                            <a href="?target_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary mb-1"><i class="bi bi-eye"></i> View</a>
                            <?php if($nextNumber): ?>
                                <a href="add_call.php?target_id=<?= $row['id'] ?>&number=<?= urlencode($nextNumber) ?>" class="btn btn-sm btn-success mb-1"><i class="bi bi-telephone-forward"></i> Call</a>
                            <?php else: ?>
                                <span class="badge bg-info">All Called</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php if(!empty($pendingNumbers)): ?>
                    <tr>
                        <td colspan="5">
                            <strong>Pending Numbers:</strong>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <?php foreach($pendingNumbers as $num): ?>
                                    <a href="add_call.php?target_id=<?= $row['id'] ?>&number=<?= urlencode($num) ?>" class="btn btn-sm btn-outline-primary"><?= htmlspecialchars($num) ?> <i class="bi bi-telephone"></i></a>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="alert alert-info">No targets assigned yet.</div>
        <?php endif; ?>
    </div>

    <?php if ($selected_target_id): ?>
    <div class="card p-4 mb-5">
        <h5 class="text-success mb-3"><i class="bi bi-telephone-outbound"></i> Call Logs for Target ID: <?= $selected_target_id ?></h5>
        
        <input type="text" id="searchInput" class="form-control search-bar mb-3" placeholder="Search...">

        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center" id="callTable">
                <thead>
                    <tr>
                        <th>#</th><th>Number</th><th>Name</th><th>Email</th><th>Project</th><th>Event</th>
                        <th>Reason</th><th>Sentiment</th><th>Notes</th><th>Follow-up</th><th>Appointment</th><th>Called At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($call_logs->num_rows > 0): $i=1; while($log = $call_logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td class="text-primary fw-semibold"><?= htmlspecialchars($log['number_called']) ?></td>
                        <td><?= htmlspecialchars($log['customer_name']) ?></td>
                        <td><?= htmlspecialchars($log['email']) ?></td>
                        <td><?= htmlspecialchars($log['project_name']) ?></td>
                        <td><?= htmlspecialchars($log['event']) ?></td>
                        <td><?= htmlspecialchars($log['reason']) ?></td>
                        <td><?= htmlspecialchars($log['sentiment']) ?></td>
                        <td><?= nl2br(htmlspecialchars($log['notes'])) ?></td>
                        <td><?= htmlspecialchars($log['followup_date']) ?></td>
                        <td><?= htmlspecialchars($log['appointment_date']) ?></td>
                        <td><?= htmlspecialchars($log['called_at']) ?></td>
                    </tr>
                    <?php endwhile; else: ?>
                        <tr><td colspan="12" class="text-muted">No call records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.querySelectorAll('.dropdown-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        const content = btn.nextElementSibling;
        content.style.display = content.style.display === 'block' ? 'none' : 'block';
    });
});

// Mobile sidebar toggle
document.getElementById('mobileSidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// Live search for call logs
document.getElementById('searchInput')?.addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('#callTable tbody tr').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>
