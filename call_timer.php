<?php
session_start();
include 'db.php';

// ===== Check if admin logged in =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// ===== Admin name =====
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
}

// ===== Fetch all users for filter =====
$usersResult = $conn->query("SELECT id, user_name FROM manageuser WHERE status='Active' ORDER BY user_name ASC");
$users = [];
while($u = $usersResult->fetch_assoc()) $users[] = $u;

// ===== Get selected user filter =====
$selectedUser = $_GET['user_id'] ?? '';

// ===== Build query =====
$query = "
    SELECT
        call_log.*,
        manageuser.user_name AS agent_name,
        DATE(call_log.called_at) AS call_date
    FROM call_log
    INNER JOIN manageuser ON call_log.user_id = manageuser.id
";
if ($selectedUser) {
    $query .= " WHERE manageuser.id = " . (int)$selectedUser;
}
$query .= " ORDER BY manageuser.user_name ASC, call_log.called_at DESC";

$result = $conn->query($query);
if (!$result) die("Query failed: " . $conn->error);

// ===== Group logs =====
$logsByUser = [];
$sentimentLogs = ['Positive'=>[], 'Negative'=>[], 'On Hold'=>[], 'Won'=>[]];
$dailyLogs = [];
while ($row = $result->fetch_assoc()) {
    $logsByUser[$row['agent_name']][] = $row;
    $sentiment = $row['sentiment'] ?? '';
    if(isset($sentimentLogs[$sentiment])) $sentimentLogs[$sentiment][] = $row;
    $dailyLogs[$row['call_date']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Call Logs - Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ===== Sidebar ===== */
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:230px;
    height:100vh;
    background:#0b1b58;
    color:#fff;
    padding-top:20px;
    overflow-y:auto;
    transition:all 0.3s ease;
    z-index:999;
}
.sidebar img.logo {
    width:180px;
    display:block;
    margin:0 auto 10px;
}
.sidebar .address {
    font-size:12px;
    color:rgba(255,255,255,0.7);
    text-align:center;
    margin-bottom:10px;
}
.sidebar .welcome {
    font-size:0.9rem;
    color:#ffc107;
    text-align:center;
    font-weight:500;
    margin-bottom:20px;
}
.sidebar a, .sidebar button {
    display:block;
    width:100%;
    padding:10px 20px;
    color:white;
    text-decoration:none;
    background:none;
    border:none;
    font-size:14px;
    text-align:left;
    border-radius:6px;
    transition:0.3s;
    cursor:pointer;
}
.sidebar a:hover, .sidebar button:hover {
    background: rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background: rgba(0,0,0,0.1);
    margin-left:10px;
    border-radius:4px;
}
.sidebar .dropdown-container a {
    padding-left:35px;
    font-size:13px;
}
.logout-btn {
    position:absolute;
    bottom:20px;
    width:100%;
}
.logout-btn a {
    background: rgba(255,255,255,0.1);
    color: #ffc107;
    display:flex;
    justify-content:center;
    align-items:center;
    font-weight:500;
    padding:10px;
    border-radius:6px;
}

/* ===== Main content ===== */
body {
    font-family:'Poppins',sans-serif;
    background:#f4f6f9;
}
.main-content {
    margin-left:230px;
    padding:20px;
    transition:margin-left 0.3s ease;
}
.table thead th {
    background:#0d6efd;
    color:#fff;
    font-size:0.9rem;
}
.table td, .table th {
    font-size:0.85rem;
    vertical-align:middle;
}
.call-card {
    border-radius:8px;
    background:#fff;
    padding:10px;
    margin-bottom:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.05);
}
.search-box {
    display:flex;
    justify-content:center;
    margin-bottom:10px;
    position:relative;
}
.search-box input {
    width:90%;
    max-width:400px;
    border-radius:30px;
    padding:6px 40px;
    border:1px solid #0d6efd;
}
.search-box i {
    position:absolute;
    left:12px;
    top:50%;
    transform:translateY(-50%);
    color:#0d6efd;
}
.user-guide {
    background:#fff8e1;
    border-left:4px solid #ffc107;
    padding:12px 15px;
    border-radius:6px;
    font-size:14px;
    color:#444;
}

/* ===== Sentiment Badges ===== */
.badge-positive {
    background:green;
    color:#fff;
}
.badge-negative {
    background:red;
    color:#fff;
}
.badge-onhold {
    background:orange;
    color:#fff;
}
.badge-won {
    background:blue;
    color:#fff;
}

/* ===== Mobile adjustments ===== */
@media (max-width: 992px) {
.sidebar {
    left: -230px;
}
.sidebar.active {
    left:0;
}
.main-content {
    margin-left:0;
    padding-top:60px;
}
.toggle-btn {
    display:inline-block;
    position:fixed;
    top:10px;
    left:10px;
    z-index:1001;
    background:#0d6efd;
    color:#fff;
    border:none;
    border-radius:6px;
    padding:8px 12px;
    font-size:1.2rem;
}
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280<br>📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>
    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="managehotlines.php">Manage Hotline</a>
        <a href="view_hotline.php">View Hotline</a>
        <a href="hotline_setting.php">Add Project</a>
    </div>
    <button class="dropdown-btn"><i class="bi bi-person-lines-fill"></i> Agents Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manageuser.php">Agent Administration</a>
        <a href="view_users.php">Agent Overview</a>
    </div>
    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="usertarget.php">Call Targets</a>
        <a href="viewtargetdetails.php">Target Overview</a>
    </div>
    <a href="call_timer.php"><i class="bi bi-clock-history"></i> Call Timer</a>
    <a href="call_log.php" class="active"><i class="bi bi-journal-text"></i> Call Log</a>
    
    <button class="dropdown-btn"><i class="bi bi-calendar-check"></i> Site Appointments <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="appointmentlist.php">Appointment List</a>
        <a href="appointment_overview.php">Appointment Overview</a>
    </div>
    
    <a href="reports.php"><i class="bi bi-bar-chart-line"></i> Reports</a>
    <button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="profilesetting.php">Profile Setting</a>
    </div>
    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- Mobile Sidebar Toggle Button -->
<button class="toggle-btn" id="toggle-btn"><i class="bi bi-list"></i></button>

<!-- Main Content -->
<div class="main-content">
    <p class="user-guide">View call logs filtered by user, sentiment, or daily view. Sentiments are color-coded for quick recognition.</p>

    <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" id="searchInput" placeholder="Search calls... (Number, Customer, Project)">
    </div>

    <form method="GET" class="mb-3 d-flex flex-wrap align-items-center gap-2">
        <label for="userFilter" class="fw-bold">Filter by User:</label>
        <select name="user_id" id="userFilter" class="form-select w-auto">
            <option value="">All Users</option>
            <?php foreach($users as $u): ?>
                <option value="<?= $u['id'] ?>" <?= ($selectedUser==$u['id'])?'selected':'' ?>><?= htmlspecialchars($u['user_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary">Filter</button>
    </form>

    <ul class="nav nav-tabs" id="callTabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#all">All Calls</button></li>
        <?php foreach($sentimentLogs as $key=>$logs): ?>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#<?= strtolower(str_replace(' ','',$key)) ?>"><?= $key ?> (<?= count($logs) ?>)</button></li>
        <?php endforeach; ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#daily">Daily View</button></li>
    </ul>

    <div class="tab-content mt-3">
        <!-- === All Calls === -->
        <div class="tab-pane fade show active" id="all">
            <?php if(empty($logsByUser)): ?>
                <div class="alert alert-info">No calls logged.</div>
            <?php else: foreach($logsByUser as $agent => $logs): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap">
                        <h6 class="mb-0"><?= htmlspecialchars($agent) ?></h6>
                        <span class="badge bg-primary">Total: <?= count($logs) ?></span>
                    </div>
                    <div class="card-body table-responsive">
                        <table class="table table-bordered align-middle text-center callTable">
                            <thead>
                                <tr>
                                    <th>#</th><th>Number</th><th>Customer</th><th>Email</th><th>Project</th>
                                    <th>Event</th><th>Reason</th><th>Sentiment</th><th>Notes</th>
                                    <th>Follow-up</th><th>Appointment</th><th>Reason</th><th>Called At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i=1; foreach($logs as $log): 
                                    // Sentiment class
                                    if ($log['sentiment'] === 'Positive') {
                                        $sentimentClass = 'badge-positive';
                                    } elseif ($log['sentiment'] === 'Negative') {
                                        $sentimentClass = 'badge-negative';
                                    } elseif ($log['sentiment'] === 'On Hold') {
                                        $sentimentClass = 'badge-onhold';
                                    } elseif ($log['sentiment'] === 'Won') {
                                        $sentimentClass = 'badge-won';
                                    } else {
                                        $sentimentClass = 'badge-secondary';
                                    }
                                ?>
                                <tr>
                                    <td><?= $i++ ?></td>
                                    <td class="fw-bold text-primary"><?= htmlspecialchars($log['number_called']) ?></td>
                                    <td><?= htmlspecialchars($log['customer_name']) ?></td>
                                    <td><?= htmlspecialchars($log['email']) ?></td>
                                    <td><?= htmlspecialchars($log['project_name']) ?></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($log['event']) ?></span></td>
                                    <td><?= htmlspecialchars($log['reason']) ?></td>
                                    <td><span class="badge <?= $sentimentClass ?>"><?= htmlspecialchars($log['sentiment']) ?></span></td>
                                    <td><?= nl2br(htmlspecialchars($log['notes'])) ?></td>
                                    <td><?= $log['followup_date'] ?: '-' ?></td>
                                    <td><?= $log['appointment_date'] ?: '-' ?></td>
                                    <td><?= $log['appointment_reason'] ?: '-' ?></td>
                                    <td><?= $log['called_at'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- === Sentiment Tabs === -->
        <?php foreach($sentimentLogs as $key=>$logs): ?>
            <div class="tab-pane fade" id="<?= strtolower(str_replace(' ','',$key)) ?>">
                <?php if(empty($logs)): ?>
                    <div class="alert alert-info">No <?= $key ?> calls.</div>
                <?php else: foreach($logs as $log): 
                    // Sentiment class
                    if ($log['sentiment'] === 'Positive') {
                        $sentimentClass = 'badge-positive';
                    } elseif ($log['sentiment'] === 'Negative') {
                        $sentimentClass = 'badge-negative';
                    } elseif ($log['sentiment'] === 'On Hold') {
                        $sentimentClass = 'badge-onhold';
                    } elseif ($log['sentiment'] === 'Won') {
                        $sentimentClass = 'badge-won';
                    } else {
                        $sentimentClass = 'badge-secondary';
                    }
                ?>
                <div class="call-card">
                    <strong><?= htmlspecialchars($log['agent_name']) ?></strong> - <?= htmlspecialchars($log['customer_name']) ?> (<?= htmlspecialchars($log['number_called']) ?>)
                    <span class="badge <?= $sentimentClass ?>"><?= $log['sentiment'] ?></span>
                    <br><small><?= htmlspecialchars($log['project_name']) ?> | <?= htmlspecialchars($log['event']) ?> | <?= $log['appointment_reason'] ?: '-' ?> | <?= $log['called_at'] ?></small>
                </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- === Daily Calls === -->
        <div class="tab-pane fade" id="daily">
            <?php if(empty($dailyLogs)): ?>
                <div class="alert alert-info">No calls logged.</div>
            <?php else: foreach($dailyLogs as $date => $logsPerDay): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light"><strong><?= $date ?></strong> | Total: <?= count($logsPerDay) ?></div>
                    <div class="card-body">
                        <?php foreach($logsPerDay as $log): 
                            // Sentiment class
                            if ($log['sentiment'] === 'Positive') {
                                $sentimentClass = 'badge-positive';
                            } elseif ($log['sentiment'] === 'Negative') {
                                $sentimentClass = 'badge-negative';
                            } elseif ($log['sentiment'] === 'On Hold') {
                                $sentimentClass = 'badge-onhold';
                            } elseif ($log['sentiment'] === 'Won') {
                                $sentimentClass = 'badge-won';
                            } else {
                                $sentimentClass = 'badge-secondary';
                            }
                        ?>
                        <div class="mb-2">
                            <strong><?= htmlspecialchars($log['agent_name']) ?></strong> - <?= htmlspecialchars($log['customer_name']) ?> | <?= htmlspecialchars($log['number_called']) ?>
                            <span class="badge <?= $sentimentClass ?>"><?= $log['sentiment'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar dropdown toggle
document.querySelectorAll('.sidebar .dropdown-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        btn.classList.toggle('active');
        const container = btn.nextElementSibling;
        container.style.display = container.style.display === "block" ? "none" : "block";
    });
});
// Mobile sidebar toggle
document.getElementById('toggle-btn').addEventListener('click', function(){
    document.getElementById('sidebar').classList.toggle('active');
});
// Search filter
document.getElementById("searchInput").addEventListener("keyup", function(){
    const val = this.value.toLowerCase();
    document.querySelectorAll(".callTable tbody tr, .call-card").forEach(el => {
        el.style.display = el.textContent.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>
</body>
</html>
