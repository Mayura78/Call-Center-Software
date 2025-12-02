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

// ===== FETCH ALL CALLS OF THIS USER =====
$stmt = $conn->prepare("
    SELECT call_log.*, target.id as target_id
    FROM call_log
    LEFT JOIN target ON call_log.target_id = target.id
    WHERE call_log.user_id = ?
    ORDER BY DATE(call_log.called_at) DESC, call_log.called_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$calls_by_date = [];
$projects = [];
while ($row = $result->fetch_assoc()) {
    $date = date('Y-m-d', strtotime($row['called_at']));
    $calls_by_date[$date][] = $row;
    if (!empty($row['project_name'])) $projects[$row['project_name']] = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Daily Call Summary - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C, #001a4d);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #f4f6fa;
}
body {
    font-family: 'Poppins', sans-serif;
    background: var(--bg-body);
    margin:0;
}

/* Sidebar */
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

/* Main Content */
.content {
    margin-left:220px;
    padding:25px;
    transition: all 0.3s;
}
.card {
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    padding:20px;
    margin-bottom:25px;
    background:#fff;
}
.table {
    font-size:14px;
}
.table thead th {
    background:#0167ff;
    color:white;
    font-weight:500;
    vertical-align:middle;
}
.table-positive thead th {
    background:#198754 !important;
}
.table-negative thead th {
    background:#dc3545 !important;
}
.table-onhold thead th {
    background:#ffc107 !important;
    color:#000 !important;
}
.table-won thead th {
    background:#0dcaf0 !important;
    color:#000 !important;
}
.table td {
    vertical-align:middle;
}
.date-header {
    background:#0d6efd;
    color:white;
    padding:6px 12px;
    border-radius:5px;
    font-weight:500;
}
.table-section-title {
    font-weight:600;
    font-size:15px;
    margin:12px 0;
}
.badge {
    font-size:12px;
    margin-left:4px;
}
h4.page-title {
    font-weight:600;
    color:#0167ff;
    display:flex;
    align-items:center;
    gap:8px;
}
.filter-bar {
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-bottom:15px;
}
.filter-bar select, .filter-bar input {
    max-width:250px;
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

@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* Top navbar for mobile */
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

@media (max-width: 992px) {
    .sidebar { left:-220px; }
    .sidebar.active { left:0; }
    .content { margin-left:0; padding-top:70px; }
    .navbar-mobile { display:flex; }
}
</style>
</head>
<body>

<!-- Top Navbar Mobile -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Daily Call Summary</span>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Company Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name); ?></p>
    </div>

    <a href="home.php"><i class="bi bi-house-door"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-collection"></i> Call Target <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
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
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>
    <a href="appointmentlist.php"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <button class="dropdown-btn"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manage_reasons.php"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>
    <div class="logout-btn">
        <a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="content">
    <h4 class="page-title mb-3"><i class="bi bi-bar-chart-line"></i> Daily Call Summary</h4>
    <p class="text-muted mb-3">Review all your call activity, grouped by date and sentiment type.</p>

    <p class="user-guide">
         Enable users to view all calls they handled, with clear differentiation between Positive, On Hold, Won, and Negative outcomes,
         for professional performance tracking and analysis.
    </p>

    <!-- Filters -->
    <div class="filter-bar">
        <select id="projectFilter" class="form-select form-select-sm">
            <option value="">All Projects</option>
            <?php foreach(array_keys($projects) as $p): ?>
                <option value="<?= htmlspecialchars($p) ?>"><?= htmlspecialchars($p) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search by number or name...">
    </div>

    <?php if(empty($calls_by_date)): ?>
        <div class="alert alert-info text-center">No calls logged yet.</div>
    <?php else: ?>
        <?php foreach($calls_by_date as $date => $calls): 
            $positive_calls = array_filter($calls, fn($c) => $c['sentiment']=='Positive');
            $negative_calls = array_filter($calls, fn($c) => $c['sentiment']=='Negative');
            $onhold_calls  = array_filter($calls, fn($c) => $c['sentiment']=='On Hold');
            $won_calls     = array_filter($calls, fn($c) => $c['sentiment']=='Won');
        ?>
        <div class="card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
                <div class="date-header"><i class="bi bi-calendar-event"></i> <?= date('F d, Y', strtotime($date)) ?></div>
                <div>
                    <span class="badge bg-primary">Total: <?= count($calls) ?></span>
                    <span class="badge bg-success">Positive: <?= count($positive_calls) ?></span>
                    <span class="badge bg-warning text-dark">On Hold: <?= count($onhold_calls) ?></span>
                    <span class="badge bg-info text-dark">Won: <?= count($won_calls) ?></span>
                    <span class="badge bg-danger">Negative: <?= count($negative_calls) ?></span>
                </div>
            </div>

            <?php 
            $sentiment_sections = [
                'Positive' => ['data'=>$positive_calls,'class'=>'table-positive','icon'=>'hand-thumbs-up-fill','title'=>'Positive Calls','color'=>'text-success'],
                'On Hold' => ['data'=>$onhold_calls,'class'=>'table-onhold','icon'=>'pause-circle-fill','title'=>'On Hold Calls','color'=>'text-warning'],
                'Won' => ['data'=>$won_calls,'class'=>'table-won','icon'=>'award','title'=>'Won Calls','color'=>'text-info'],
                'Negative' => ['data'=>$negative_calls,'class'=>'table-negative','icon'=>'hand-thumbs-down-fill','title'=>'Negative Calls','color'=>'text-danger']
            ];
            foreach($sentiment_sections as $sent=>$info):
                if(!empty($info['data'])): ?>
                <div class="table-section-title <?= $info['color'] ?>"><i class="bi bi-<?= $info['icon'] ?>"></i> <?= $info['title'] ?></div>
                <div class="table-responsive mb-3">
                    <table class="table table-hover table-sm align-middle text-center callTable <?= $info['class'] ?>">
                        <thead>
                            <tr>
                                <th>#</th><th>Number</th><th>Name</th><th>Project</th><th>Reason</th><th>Notes</th><th>Appointment</th><th>Called At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i=1; foreach($info['data'] as $c): ?>
                            <tr data-project="<?= htmlspecialchars($c['project_name']) ?>" data-number="<?= htmlspecialchars($c['number_called']) ?>">
                                <td><?= $i++ ?></td>
                                <td class="fw-semibold text-primary"><?= htmlspecialchars($c['number_called']) ?></td>
                                <td><?= htmlspecialchars($c['customer_name']) ?: '-' ?></td>
                                <td><?= htmlspecialchars($c['project_name']) ?: '-' ?></td>
                                <td><?= htmlspecialchars($c['reason']) ?: '-' ?></td>
                                <td><?= nl2br(htmlspecialchars($c['notes'])) ?></td>
                                <td><?= htmlspecialchars($c['appointment_date']) ?: '-' ?></td>
                                <td><?= date('h:i A', strtotime($c['called_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="footer">
  &copy; <?= date('Y'); ?> Commercial Realty (Pvt) Ltd | Developed by <span class="text-warning">Mayura Lasantha</span>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// Sidebar dropdown logic
document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const dropdown = btn.nextElementSibling;
        document.querySelectorAll(".dropdown-container").forEach(dc => { if(dc!==dropdown) dc.style.display='none'; });
        dropdown.style.display = dropdown.style.display==='block'?'none':'block';
    });
});

// Mobile sidebar toggle
document.getElementById('mobileSidebarToggle').addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('active');
});

// Project and search filters
$('#projectFilter, #searchInput').on('input change', function(){
    const project = $('#projectFilter').val().toLowerCase();
    const search = $('#searchInput').val().toLowerCase();
    $('.callTable tbody tr').each(function(){
        const proj = $(this).data('project')?.toLowerCase() || '';
        const num = $(this).data('number')?.toLowerCase() || '';
        const name = $(this).find('td:nth-child(3)').text().toLowerCase();
        $(this).toggle((!project || proj.includes(project)) && (!search || num.includes(search) || name.includes(search)));
    });
});
</script>

</body>
</html>
