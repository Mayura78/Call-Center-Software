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

// ===== DATE FILTER =====
$selectedDate = $_GET['date'] ?? date('Y-m-d'); // default today

// Fetch calls for the selected date
$sql = "SELECT call_log.*, target.project_name AS target_project
        FROM call_log
        LEFT JOIN target ON call_log.target_id = target.id
        WHERE call_log.user_id = ? AND DATE(called_at) = ?
        ORDER BY called_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $user_id, $selectedDate);
$stmt->execute();
$result = $stmt->get_result();

$calls = [];
$eventTotals = ['CALL_COMPLETED'=>0, 'ABANDON'=>0];
while ($row = $result->fetch_assoc()) {
    $calls[] = $row;
    if ($row['event'] === 'CALL_COMPLETED') $eventTotals['CALL_COMPLETED']++;
    elseif ($row['event'] === 'ABANDON') $eventTotals['ABANDON']++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Calls - <?= htmlspecialchars($selectedDate) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family:'Poppins', sans-serif;
    background:#f4f6fa;
    margin:0;
}

/* ===== Sidebar ===== */
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:220px;
    height:100vh;
    background: linear-gradient(180deg, #00205C);
    color:#fff;
    padding-top:20px;
    overflow-y:auto;
    transition: transform 0.3s ease;
    z-index:1050;
}
.sidebar .sidebar-header {
    text-align:center;
    margin-bottom:20px;
}
.sidebar .sidebar-header img {
    width:180px;
    border-radius:6px;
    margin-bottom:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color:rgba(255,255,255,0.8);
    margin:0 0 10px;
    text-align:center;
}

.sidebar a, .sidebar button {
    display:block;
    color:#fff;
    padding:10px 20px;
    text-decoration:none;
    font-size:14px;
    border:none;
    background:none;
    width:100%;
    text-align:left;
    transition:0.3s;
    cursor:pointer;
}
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
    background: rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background: rgba(0,0,0,0.1);
}
.sidebar .dropdown-container a {
    padding-left:40px;
    font-size:13px;
}
.sidebar .logout-btn {
    position:absolute;
    bottom:20px;
    width:100%;
}

/* ===== Content ===== */
.content {
    margin-left:240px;
    padding:25px;
    transition: all 0.3s;
}
.card {
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    margin-bottom:25px;
    background:#fff;
    padding:20px;
}
.table thead th {
    background:#0167ff;
    color:white;
    font-weight:500;
}
.badge-call-completed {
    background:#198754;
}
.badge-abandon {
    background:#dc3545;
}
.event-card {
    text-align:center;
    padding:10px;
    border-radius:12px;
    color:#fff;
    margin-bottom:15px;
}
.event-card.completed {
    background:#198754;
}
.event-card.abandon {
    background:#dc3545;
}
.event-card h3 {
    margin:0;
    font-size:2rem;
}
.event-card p {
    margin:0;
    font-weight:500;
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
@keyframes slideFadeIn {
    from { opacity:0;
        transform:translateY(20px);
    }
    to {
        opacity:1;
        transform:translateY(0);
    }
}

/* Mobile Navbar */
.navbar-mobile {
    display:none;
    background:#00205C;
    color:white;
    padding:10px 15px;
    align-items:center;
    justify-content:space-between;
}
.navbar-mobile i {
    font-size:1.5rem;
    cursor:pointer;
}
.navbar-mobile span {
    font-weight:600;
    font-size:1rem;
}

/* Responsive */
@media (max-width:992px){
    .sidebar {
        transform: translateX(-100%);
        width:220px;
        position:fixed;
    }
    .sidebar.active {
        transform: translateX(0);
    }
    .content {
        margin-left:0;
        padding:15px;
    }
    .navbar-mobile {
        display:flex;
    }
}
</style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Call List - <?= date('d M Y', strtotime($selectedDate)) ?></span>
</div>

<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Company Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name); ?></p>
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
        <a href="reviewed_onhold.php"><i class="bi bi-telephone-outbound me-2"></i> View Called List</a>
    </div>

    <a href="view_user_appointments.php"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php" class="active"><i class="bi bi-journal-text"></i> View All Call List</a>
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

<!-- ===== Main Content ===== -->
<div class="content">
    <h4 class="mb-3"><i class="bi bi-list-task"></i> Calls on <?= date('F d, Y', strtotime($selectedDate)) ?></h4>

    <p class="user-guide">
         Full record of all calls made by agents. Useful for reviewing past activity and performance.
    </p>

    <!-- Date selector -->
    <div class="mb-4">
        <input type="date" id="dateSelect" class="form-control form-control-sm" value="<?= htmlspecialchars($selectedDate) ?>" style="max-width:200px;">
    </div>

    <!-- Event totals cards -->
    <div class="row mb-4">
        <div class="col-md-3 col-6">
            <div class="event-card completed">
                <h3><?= $eventTotals['CALL_COMPLETED'] ?></h3>
                <p>CALL_COMPLETED</p>
            </div>
        </div>
    </div>

    <!-- Call table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-bordered table-sm align-middle text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Project</th>
                        <th>Event</th>
                        <th>Reason</th>
                        <th>Sentiment</th>
                        <th>Notes</th>
                        <th>Follow-up</th>
                        <th>Appointment</th>
                        <th>Called At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach($calls as $c): 
                        $badgeClass = $c['event']==='CALL_COMPLETED'?'badge-call-completed':($c['event']==='ABANDON'?'badge-abandon':'badge-secondary'); ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($c['number_called']) ?></td>
                        <td><?= htmlspecialchars($c['customer_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['email'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['target_project'] ?: '-') ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($c['event'] ?: '-') ?></span></td>
                        <td><?= htmlspecialchars($c['reason'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['sentiment'] ?: '-') ?></td>
                        <td><?= nl2br(htmlspecialchars($c['notes'] ?: '-')) ?></td>
                        <td><?= htmlspecialchars($c['followup_date'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($c['appointment_date'] ?: '-') ?></td>
                        <td><?= date('h:i A', strtotime($c['called_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($calls)): ?>
                    <tr><td colspan="12">No calls found for this date.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    // Sidebar dropdown
    document.querySelectorAll(".dropdown-btn").forEach(btn=>{
        btn.addEventListener("click",()=>{ 
            const dropdown = btn.nextElementSibling;
            dropdown.style.display = (dropdown.style.display==='block')?'none':'block';
        });
    });

    // Mobile sidebar toggle
    $('#mobileSidebarToggle').on('click', function(){
        $('#sidebar').toggleClass('active');
    });

    // Date change
    $('#dateSelect').on('change', function(){
        const val = $(this).val();
        if(val) window.location.href = "?date="+val;
    });
</script>

</body>
</html>
