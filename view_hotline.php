<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

// Fetch username for sidebar
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// ===== Fetch Hotline Details =====
$hotlines = $conn->query("SELECT * FROM managehotlines ORDER BY id DESC");

// ===== Stats =====
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$weekStart = date('Y-m-d', strtotime('monday this week'));

$todayCount = $conn->query("SELECT COUNT(*) AS c FROM managehotlines WHERE DATE(created_at)='$today'")->fetch_assoc()['c'];
$yesterdayCount = $conn->query("SELECT COUNT(*) AS c FROM managehotlines WHERE DATE(created_at)='$yesterday'")->fetch_assoc()['c'];
$weekCount = $conn->query("SELECT COUNT(*) AS c FROM managehotlines WHERE DATE(created_at) >= '$weekStart'")->fetch_assoc()['c'];
$totalCount = $conn->query("SELECT COUNT(*) AS c FROM managehotlines")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>View Hotlines - Dashboard</title>

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">

<style>
body { font-family: 'Poppins', sans-serif; background:#f4f6f9; margin:0; overflow-x:hidden; }

/* ===== Sidebar ===== */
.sidebar {
    position: fixed; top:0; left:0; width:230px; height:100vh;
    background:#0b1b58; color:#fff; padding-top:20px; overflow-y:auto; transition:all 0.3s;
    z-index:1000;
}
.sidebar img.logo { width:180px; margin:0 auto 10px; display:block; }
.sidebar .address { font-size: 12px; color:rgba(255,255,255,0.7); text-align:center; margin-bottom:10px; }
.sidebar .welcome { font-size:0.9rem; color:#ffc107; text-align:center; font-weight:500; margin-bottom:20px; }
.sidebar a, .sidebar button {
    display:block; width:100%; padding:10px 20px; color:white; text-decoration:none;
    font-size:14px; background:none; border:none; text-align:left; border-radius:6px; transition:0.3s; cursor:pointer;
}
.sidebar a:hover, .sidebar button:hover { 
    background: rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar a.active { 
    background: rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container{ 
    display:none; 
    background:rgba(0,0,0,0.05); 
    border-radius:5px; 
    margin-left:10px; 
}
.sidebar .dropdown-container a{
    padding-left:35px; 
    font-size:13px;
    border-radius:4px;
}
.logout-btn{ position:absolute; bottom:20px; width:100%; }
.logout-btn a{ 
    background: rgba(255,255,255,0.1); 
    font-weight:500; 
    color:#ffc107; 
    display:flex; 
    align-items:center; 
    justify-content:center; 
}

/* ===== Main content ===== */
.main-content{ margin-left:220px; padding:20px; transition:margin-left 0.3s;}
.topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;}
.toggle-btn{ cursor:pointer; font-size:20px; color:#0b1b58; }

/* Stat cards */
.stat-card{ background: linear-gradient(135deg,#6a11cb,#2575fc); color:white; border-radius:8px; padding:10px; text-align:center; margin-bottom:10px;}
.stat-card h3{margin:0; font-size:18px; font-weight:600;}
.stat-card p{margin:0; font-size:12px; opacity:0.8;}

/* Table card */
.card{ border-radius:10px; background:white; padding:15px; box-shadow:0 2px 10px rgba(0,0,0,0.1); overflow-x:auto;}
.table thead{ background: #0b1b58; color:white;}
.table tbody tr {
    transition: all 0.3s ease;
    cursor: pointer;
}
.table tbody tr:hover {
    transform: scale(1.02);
    background: linear-gradient(90deg, #e6f2ff, #cce6ff) !important;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 1);
}

/* Badge & buttons */
.badge-project{ background:#0b1b58; color:white; padding:4px 8px; border-radius:12px; font-size:12px;}
.btn-action{ transition:all 0.2s; padding:4px 7px; font-size:12px;}
.btn-action:hover{ transform:scale(1.1);}

/* Download button */
.download-btn{ position:relative; display:inline-block;}
.download-btn .dropdown-menu{ right:0; left:auto; min-width:160px; box-shadow:0 3px 10px rgba(0,0,0,0.15);}
.download-btn button{ border-radius:20px; background:linear-gradient(90deg,#0167ff,#00c6ff); color:white; font-weight:500; border:none; padding:6px 16px; font-size:14px; display:flex; align-items:center; gap:5px;}
.download-btn button:hover{ opacity:0.9;}

/* User guide */
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
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
}

/* ===== Responsive ===== */
@media(max-width:992px){
    .sidebar{ left:-220px; position:fixed; }
    .sidebar.active{ left:0; }
    .main-content{ margin-left:0; padding:15px;}
    .table thead{ display:none; }
    .table tbody td{ display:flex; justify-content:space-between; align-items:center; padding:8px; border-bottom:1px solid #eee; }
    .table tbody td::before{ content: attr(data-label); font-weight:600; flex:1; color:#0b1b58; }
    .download-btn{ margin-top:10px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>

    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="managehotlines.php">Manage Hotlines</a>
        <a href="view_hotline.php" class="active">View Hotlines</a>
        <a href="hotline_setting.php">Add Project</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-person-lines-fill"></i> Agents Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manageuser.php">Manage User</a>
        <a href="view_users.php">Agent Overview</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="usertarget.php">Call Targets</a>
        <a href="viewtargetdetails.php">Target Overview</a>
    </div>

    <a href="call_timer.php"><i class="bi bi-clock-history"></i> Call Timer</a>
    <a href="call_log.php"><i class="bi bi-journal-text"></i> Call Log</a>
    
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

<!-- Main Content -->
<div class="main-content">
    <div class="topbar d-flex justify-content-between align-items-center mb-3">
        <h4>Hotline Overview</h4>
        <div class="datetime" id="datetime"></div>
        <button class="btn btn-sm btn-primary d-lg-none toggle-btn" id="sidebarToggle"><i class="bi bi-list"></i></button>
    </div>

    <p class="user-guide">
        See all the hotline numbers that have been added. Quickly access, edit, or delete entries if needed.
    </p>

    <!-- STAT CARDS -->
    <div class="row g-2 text-center mb-3">
        <div class="col-6 col-md-3"><div class="stat-card"><p>Today</p><h3><?= $todayCount ?></h3></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><p>Yesterday</p><h3><?= $yesterdayCount ?></h3></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><p>This Week</p><h3><?= $weekCount ?></h3></div></div>
        <div class="col-6 col-md-3"><div class="stat-card"><p>Total</p><h3><?= $totalCount ?></h3></div></div>
    </div>

    <!-- HOTLINE TABLE -->
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
            <h5 class="fw-bold text-primary"><i class="bi bi-telephone-fill"></i> Hotline Records</h5>
            <div class="download-btn dropdown">
                <button class="btn btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Download
                </button>
                <ul class="dropdown-menu shadow-sm">
                    <li><a class="dropdown-item" href="#" id="excelExport"><i class="bi bi-file-earmark-excel text-success"></i> Excel</a></li>
                    <li><a class="dropdown-item" href="#" id="pdfExport"><i class="bi bi-file-earmark-pdf text-danger"></i> PDF</a></li>
                    <li><a class="dropdown-item" href="#" id="printTable"><i class="bi bi-printer text-primary"></i> Print</a></li>
                </ul>
            </div>
        </div>

        <table id="hotlineTable" class="table table-hover table-bordered align-middle table-sm w-100">
            <thead>
                <tr><th>#</th><th>Project</th><th>Hotline</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php $i=1; if($hotlines->num_rows>0): while($row=$hotlines->fetch_assoc()): ?>
                <tr>
                    <td data-label="#"> <?= $i++ ?> </td>
                    <td data-label="Project"><span class="badge-project"><?= htmlspecialchars($row['project_name']) ?></span></td>
                    <td data-label="Hotline"><?= htmlspecialchars($row['hotline_number']) ?></td>
                    <td data-label="Created"><?= date('Y-m-d h:i A', strtotime($row['created_at'])) ?></td>
                    <td data-label="Actions">
                        <a href="edit_hotline.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning btn-action"><i class="bi bi-pencil-square"></i></a>
                        <a href="delete_hotline.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger btn-action"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="5" class="text-center text-muted">No hotline records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>

<script>
$(document).ready(function(){
    $('.dropdown-btn').click(function(){
        $(this).next('.dropdown-container').slideToggle();
        $('.dropdown-container').not($(this).next()).slideUp();
    });

    $('#sidebarToggle').click(function(){
        $('#sidebar').toggleClass('active');
    });

    var table = $('#hotlineTable').DataTable({
        pageLength:8,
        lengthMenu:[5,8,20,50],
        order:[[0,'desc']],
        dom:'Bfrtip',
        buttons:[
            { extend:'excelHtml5', title:'Hotline_Records', className:'d-none' },
            { extend:'pdfHtml5', title:'Hotline_Records', className:'d-none' },
            { extend:'print', className:'d-none' }
        ],
        columnDefs:[{orderable:false, targets:[4]}]
    });

    $('#excelExport').click(()=> table.button(0).trigger());
    $('#pdfExport').click(()=> table.button(1).trigger());
    $('#printTable').click(()=> table.button(2).trigger());

    // Live DateTime
    setInterval(()=>{ 
        const now=new Date();
        document.getElementById('datetime').textContent=now.toLocaleString('en-GB',{dateStyle:'medium',timeStyle:'short'});
    },1000);
});
</script>
</body>
</html>
