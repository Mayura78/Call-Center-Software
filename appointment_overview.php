<?php
session_start();
include 'db.php';

// ===== Check admin login =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB check
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
}

// ===== Agent-wise data =====
$agentsRes = $conn->query("SELECT id, user_name FROM manageuser ORDER BY user_name ASC");
$agentsData = [];

while ($agent = $agentsRes->fetch_assoc()) {
    $agentId = $agent['id'];
    $agentName = $agent['user_name'];

    $totalCalls = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='$agentId'")->fetch_assoc()['total'] ?? 0;
    $positive = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='$agentId' AND sentiment='Positive'")->fetch_assoc()['total'] ?? 0;
    $negative = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='$agentId' AND sentiment='Negative'")->fetch_assoc()['total'] ?? 0;
    $onhold = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='$agentId' AND sentiment='On Hold'")->fetch_assoc()['total'] ?? 0;
    $won = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='$agentId' AND sentiment='Won'")->fetch_assoc()['total'] ?? 0;

    $soldIncome = $conn->query("SELECT SUM(bonus_amount) AS total FROM call_log WHERE user_id='$agentId' AND visit_status='Sold'")->fetch_assoc()['total'] ?? 0;
    $visitedIncome = $conn->query("SELECT SUM(bonus_amount) AS total FROM call_log WHERE user_id='$agentId' AND visit_status='Visited'")->fetch_assoc()['total'] ?? 0;

    $agentsData[] = [
        'id'=>$agentId,
        'name'=>$agentName,
        'totalCalls'=>$totalCalls,
        'positive'=>$positive,
        'negative'=>$negative,
        'onhold'=>$onhold,
        'won'=>$won,
        'soldIncome'=>$soldIncome,
        'visitedIncome'=>$visitedIncome
    ];
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 Agent Performance & Sales</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f8ff; margin:0; }
.sidebar { position:fixed; top:0; left:0; width:230px; height:100%; background:#0b1b58; color:#fff; padding-top:20px; overflow-y:auto; transition:.3s; z-index:1050; }
.sidebar img.logo { width:180px; margin:0 auto 10px; display:block; border-radius:10px; }
.sidebar .address { font-size:12px; color:rgba(255,255,255,.7); text-align:center; margin-bottom:10px; }
.sidebar .welcome { font-size:.9rem; color:#ffc107; text-align:center; font-weight:500; margin-bottom:20px; }
.sidebar a, .sidebar button { display:block; width:100%; padding:10px 20px; color:white; text-decoration:none; font-size:14px; background:none; border:none; text-align:left; border-radius:6px; transition:.3s; cursor:pointer; }
.sidebar a:hover, .sidebar button:hover { background: rgba(255,255,255,.15); border-left:4px solid #ffc107; }
.sidebar a.active { background: rgba(255,255,255,.15); border-left:4px solid #ffc107; }
.sidebar .dropdown-container { display:none; background: rgba(0,0,0,.05); border-radius:5px; margin-left:10px; }
.sidebar .dropdown-container a { padding-left:35px; font-size:13px; border-radius:4px; }
.logout-btn { position:absolute; bottom:20px; width:100%; }
.logout-btn a { background: rgba(255,255,255,.1); font-weight:500; color:#ffc107; display:flex; align-items:center; justify-content:center; }
.main-content { margin-left:240px; padding:20px; transition:.3s; }
.toggle-btn { position:fixed; left:10px; top:12px; font-size:1.8rem; cursor:pointer; color:#0b1b58; z-index:1100; }
.card { border-radius:15px; box-shadow:0 8px 25px rgba(0,0,0,.08); padding:20px; margin-bottom:20px; background:white; }
.table th, .table td { vertical-align:middle; text-align:center; font-size:.85rem; }
.table-hover tbody tr:hover { background:#e2f0ff; }
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; margin-bottom:15px; animation:fadeIn 1s ease-in; }
@keyframes fadeIn { from { opacity:0; transform:translateY(10px);} to { opacity:1; transform:translateY(0);} }
@media(max-width:991px){ .sidebar{transform:translateX(-100%);} .sidebar.show{transform:translateX(0); box-shadow:2px 0 10px rgba(0,0,0,.3);} .main-content{margin-left:0; padding:15px;} .toggle-btn{display:block;} .card,table{font-size:.9rem;} .table-responsive{overflow-x:auto;} }
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
<a href="call_log.php"><i class="bi bi-journal-text"></i> Call Log</a>

<button class="dropdown-btn"><i class="bi bi-calendar-check"></i> Site Appointment <i class="bi bi-caret-down-fill float-end"></i></button>
<div class="dropdown-container" style="display:block;">
<a href="appointmentlist.php">Appointment List</a>
<a href="appointment_overview.php" class="active">Appointment Overview</a>
</div>

<a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>

<button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
<div class="dropdown-container">
<a href="profilesetting.php">Profile Setting</a>
</div>

<div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<i class="bi bi-list toggle-btn" id="toggleSidebar"></i>

<div class="main-content">
<h3 class="text-primary mb-3"><i class="bi bi-graph-up"></i> Agent Performance & Sales</h3>
<p class="user-guide">View agent performance, call sentiment, and financial stats (Sold & Visited amounts). Exportable table included.</p>

<div class="card mb-4">
<div class="table-responsive">
<table class="table table-bordered table-hover table-striped" id="agentTable">
<thead class="table-primary">
<tr>
<th>Agent</th>
<th>Total Calls</th>
<th>Positive</th>
<th>Negative</th>
<th>On Hold</th>
<th>Won</th>
<th>Sold Income</th>
<th>Visited Income</th>
</tr>
</thead>
<tbody>
<?php foreach($agentsData as $agent): ?>
<tr>
<td><?= htmlspecialchars($agent['name']) ?></td>
<td><?= $agent['totalCalls'] ?></td>
<td><?= $agent['positive'] ?></td>
<td><?= $agent['negative'] ?></td>
<td><?= $agent['onhold'] ?></td>
<td><?= $agent['won'] ?></td>
<td>Rs. <?= number_format($agent['soldIncome'],2) ?></td>
<td>Rs. <?= number_format($agent['visitedIncome'],2) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Sidebar toggle
document.getElementById('toggleSidebar').addEventListener('click', ()=>{
    document.getElementById('sidebar').classList.toggle('show');
});
document.querySelectorAll('.dropdown-btn').forEach(btn=>{
    btn.addEventListener('click', ()=>{
        btn.classList.toggle('active');
        const container = btn.nextElementSibling;
        container.style.display = container.style.display==='block'?'none':'block';
    });
});
document.querySelectorAll('.dropdown-container a.active').forEach(link=>{
    const parent = link.closest('.dropdown-container');
    if(parent){ parent.style.display='block'; parent.previousElementSibling.classList.add('active'); }
});

// Initialize DataTable with export buttons
$('#agentTable').DataTable({
    dom: 'Bfrtip',
    buttons: [
        { extend: 'excelHtml5', text: '<i class="bi bi-file-earmark-excel"></i> Excel', className: 'btn btn-success btn-sm' },
        { extend: 'pdfHtml5', text: '<i class="bi bi-file-earmark-pdf"></i> PDF', className: 'btn btn-danger btn-sm', orientation:'landscape', pageSize:'A4' },
        { extend: 'print', text: '<i class="bi bi-printer"></i> Print', className: 'btn btn-primary btn-sm' }
    ]
});
</script>
</body>
</html>