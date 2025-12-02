<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$message = "";

// ===== Add Hotline =====
if (isset($_POST['add_hotline'])) {
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $hotline_number = $conn->real_escape_string($_POST['hotline_number']);

    if ($project_name && $hotline_number) {
        $conn->query("INSERT INTO managehotlines (project_name, hotline_number, created_at)
                      VALUES ('$project_name', '$hotline_number', NOW())");
        $message = "<div class='alert alert-success mt-2'>✅ Hotline added successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger mt-2'>⚠️ Please fill in all fields!</div>";
    }
}

// ===== Fetch Hotlines =====
$hotlines = $conn->query("SELECT * FROM managehotlines ORDER BY id DESC");

// ===== Fetch Projects =====
$projects = $conn->query("SELECT * FROM project_list ORDER BY project_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Hotlines - Commercial Realty (Pvt) Ltd</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; margin:0; }


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
    z-index:1000;
}
.sidebar img.logo {
    width: 180px;
    margin:0 auto 10px;
    display:block;
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
    font-size:14px;
    background:none;
    border:none;
    text-align:left;
    border-radius:6px;
    transition:0.3s;
    cursor:pointer;
}
.sidebar a:hover, .sidebar button:hover {
    background:rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar a.active {
    background:rgba(255,255,255,0.15);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background:rgba(0,0,0,0.05);
    border-radius:5px;
    margin-left:10px;
}
.sidebar .dropdown-container a {
    padding-left:35px;
    font-size:13px;
    border-radius:4px;
}
.logout-btn {
    position:absolute;
    bottom:20px;
    width:100%;
}
.logout-btn a {
    background:rgba(36, 26, 174, 0.1);
    font-weight:500;
    color:#ffc107;
    display:flex;
    align-items:center;
    justify-content:center;
}

.sidebar-toggle {
    display:none;
    position:fixed;
    top:15px;
    left:15px;
    z-index:1100;
    font-size:28px;
    color:#0d6efd;
    cursor:pointer;
}

/* ===== Main Content ===== */
.main-content {
    margin-left:220px;
    padding:30px;
    transition:margin-left 0.3s ease;
}
.card {
    border-radius:12px;
    box-shadow:0 3px 12px rgba(0,0,0,0.05);
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
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* See More link */
.see-more-container {
    text-align:center;
    margin-top:10px;
}
.see-more {
    display:inline-block;
    color:#0b1b58;
    font-weight:500;
    position:relative;
    text-decoration:none;
    transition:color 0.3s;
}
.see-more::after {
    content:'';
    position:absolute;
    width:0%;
    height:2px;
    bottom:-2px;
    left:0;
    background-color:#0b1b58;
    transition:width 0.3s;
}
.see-more:hover {
    color:#122c7a;
}
.see-more:hover::after {
    width:100%;
}

/* Table hover */
#hotlinesTable tbody tr {
    transition: all 0.2s ease-in-out;
}
#hotlinesTable tbody tr:hover {
    background-color:#eef2ff !important;
    box-shadow:0 5px 8px rgba(1, 1, 1, 1);
    transform:scale(1.01);
    cursor:pointer;
}

/* Fade animation */
.fadeInSection {
    animation: fadeIn 0.6s ease forwards;
}
@keyframes fadeIn { from { opacity:0; transform:translateY(15px); } to { opacity:1; transform:translateY(0); } }

/* ===== Responsive ===== */
@media(max-width:992px){
    .sidebar { left:-220px; }
    .sidebar.show { left:0; }
    .main-content { margin-left:0; padding:20px; }
    .sidebar-toggle { display:block; }
}
@media(max-width:576px){
    .sidebar img.logo { width:140px; }
    .sidebar .address { font-size:11px; }
    .sidebar .welcome { font-size:0.8rem; }
    .main-content { padding:15px; }
}
</style>
</head>
<body>

<i class="bi bi-list sidebar-toggle" id="sidebarToggle"></i>

<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>

    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>

    <button class="dropdown-btn active"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="managehotlines.php" class="active">Manage Hotlines</a>
        <a href="view_hotline.php">View Hotlines</a>
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
    <a href="call_log.php"><i class="bi bi-journal-text"></i> Call Logs</a>
    
    <button class="dropdown-btn"><i class="bi bi-calendar-check"></i> Site Appointments <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="appointmentlist.php">Appointment List</a>
        <a href="appointment_overview.php">Appointment Overview</a>
    </div>
    
    <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>

    <button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="profilesetting.php">Profile Setting</a>
    </div>

    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- ===== Main Content ===== -->
<div class="main-content">
    <h3 class="mb-3"><i class="bi bi-telephone-fill"></i> Manage Hotlines</h3>
    <p class="user-guide">Add, edit, or remove hotline numbers for different projects. Keep contact numbers accurate and up to date.</p>
    <?= $message; ?>

    <!-- Add Hotline Form -->
    <div class="card p-4 mb-3 shadow-sm">
        <h5><i class="bi bi-plus-circle"></i> Add New Hotline</h5>
        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Project Name:</label>
                    <select name="project_name" class="form-select" required>
                        <option value="">-- Select Project --</option>
                        <?php while($p = $projects->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($p['project_name']) ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Hotline Number:</label>
                    <input type="text" name="hotline_number" class="form-control" placeholder="Enter hotline number..." required>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" name="add_hotline" class="btn btn-primary px-4"><i class="bi bi-plus-circle"></i> Add Hotline</button>
            </div>
        </form>

        <!-- See More Link -->
        <div class="see-more-container">
            <a href="#" id="seeMoreLink" class="see-more"><i class="bi bi-eye-fill"></i> See More...</a>
        </div>
    </div>

    <!-- Hotline Table -->
    <div id="hotlinesSection" style="display:none;">
        <div class="card p-4 shadow-sm fadeInSection">
            <h5><i class="bi bi-list-ul"></i> Existing Hotlines</h5>
            <div class="table-responsive mt-3">
                <table id="hotlinesTable" class="table table-bordered table-striped align-middle text-center">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Project Name</th>
                            <th>Hotline Number</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while($row = $hotlines->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['project_name']); ?></td>
                            <td><?= htmlspecialchars($row['hotline_number']); ?></td>
                            <td><?= htmlspecialchars($row['created_at']); ?></td>
                            <td>
                                <a href="edit_hotline.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil-square"></i></a>
                                <a href="delete_hotline.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ===== Scripts ===== -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    $('.dropdown-btn').click(function(){
        $(this).next('.dropdown-container').slideToggle(200);
        $('.dropdown-container').not($(this).next()).slideUp(200);
    });
    $('#sidebarToggle').click(function(){ $('#sidebar').toggleClass('show'); });
    $('#hotlinesTable').DataTable({ pageLength:10, order:[[0,'desc']] });

    // See More toggle
    $('#seeMoreLink').click(function(e){
        e.preventDefault();
        $('#hotlinesSection').slideToggle(400).toggleClass('fadeInSection');
        $(this).html($('#hotlinesSection').is(':visible') ? '<i class="bi bi-eye-slash-fill"></i> See Less' : '<i class="bi bi-eye-fill"></i> See More...');
    });
});
</script>
</body>
</html>
