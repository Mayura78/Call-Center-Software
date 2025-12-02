<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";

// ===== Add New Project =====
if (isset($_POST['add_project'])) {
    $project_name = trim($_POST['project_name']);
    if (!empty($project_name)) {
        $check = $conn->query("SELECT * FROM project_list WHERE project_name='$project_name'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO project_list (project_name) VALUES ('$project_name')");
            $message = "<div class='alert alert-success mt-2'>✅ Project added successfully!</div>";
        } else {
            $message = "<div class='alert alert-warning mt-2'>⚠️ This project already exists!</div>";
        }
    } else {
        $message = "<div class='alert alert-danger mt-2'>Please enter a project name!</div>";
    }
}

// ===== Delete Project =====
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM project_list WHERE id=$delete_id");
    $message = "<div class='alert alert-danger mt-2'>🗑️ Project deleted successfully!</div>";
}

// ===== Fetch all projects =====
$projects = $conn->query("SELECT * FROM project_list ORDER BY id DESC");
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hotline Setting - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; margin:0; overflow-x:hidden; }

/**************** SIDEBAR *****************/
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
    transition: all .3s ease;
    z-index:999;
}
.sidebar img.logo {
    width:180px;
    margin:0 auto 10px;
    display:block;
}
.sidebar .address {
    font-size:12px;
    opacity:.7;
    text-align:center;
    margin-bottom:10px;
}
.sidebar .welcome {
    font-size:.9rem;
    color:#ffc107;
    text-align:center;
    font-weight:500;
    margin-bottom:20px;
}
.sidebar a,.sidebar button {
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
    transition:.3s;
    cursor:pointer;
}
.sidebar a:hover,.sidebar button:hover {
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
.logout-btn { position:absolute;
    bottom:20px;
    width:100%; }

/* COLLAPSED SIDEBAR */
.sidebar.collapsed {
    width:70px;
}
.sidebar.collapsed a span,
.sidebar.collapsed button span,
.sidebar.collapsed .address,
.sidebar.collapsed .welcome {
    display:none;
}
.sidebar.collapsed img.logo {
    width:50px;
}

/**************** MAIN CONTENT *****************/
.main-content {
    margin-left:230px;
    padding:20px;
    transition:0.3s;
}
.main-content.expanded {
    margin-left:80px;
}
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:20px;
}
.toggle-btn {
    cursor:pointer;
    font-size:22px;
    color:#0b1b58;
}

/**************** USER GUIDE *****************/
.user-guide {
    background:#fff8e1;
    border-left:4px solid #ffc107;
    padding:12px 15px;
    border-radius:6px;
    font-size:14px;
    color:#444;
    opacity:0;
    transform:translateY(20px);
    animation:slideFadeIn .8s ease-out forwards;
}
@keyframes slideFadeIn {
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
}

/**************** CARDS *****************/
.card {
    border-radius:10px;
    background:white;
    padding:20px;
    box-shadow:0 2px 10px rgba(0,0,0,0.1);
    margin-bottom:15px;
}
.btn-primary {
    background:linear-gradient(90deg,#004817);
    border:none;
}
.btn-primary:hover {
    opacity:.9;
}
.table thead {
    background:#e9ecef;
}
.table tbody tr:hover {
    background:#f1f7ff;
    transform:scale(1.01);
    transition:.2s;
}
.btn-delete {
    background:#dc3545;
    color:white;
    border:none;
    font-size:13px;
}

/**************** MOBILE RESPONSIVE FIXES *****************/
@media(max-width:768px){

    /* Sidebar hidden by default */
    .sidebar {
        left:-230px;
        width:230px;
        position:fixed;
        height:100%;
    }
    .sidebar.show {
        left:0;
    }

    /* Toggle button */
    #toggleSidebar {
        position:relative;
        z-index:1000;
    }

    /* Main content */
    .main-content {
        margin-left:0;
        padding:10px;
    }

    /* Cards full width */
    .card {
        padding:15px;
    }

    /* Table scroll */
    .table-responsive {
        width:100%;
        overflow-x:auto;
        -webkit-overflow-scrolling:touch;
    }

    /* Logo smaller */
    .sidebar img.logo {
        width:120px;
    }

    .logout-btn {
        position:relative;
        margin-top:20px;
    }
}
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>

    <a href="index.php"><i class="bi bi-house"></i> <span>Dashboard</span></a>

    <button class="dropdown-btn"><i class="bi bi-telephone"></i> <span>Hotlines</span> <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="managehotlines.php">Manage Hotlines</a>
        <a href="view_hotline.php">View Hotlines</a>
        <a href="hotline_setting.php" class="active">Add Project</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-person-lines-fill"></i> <span>Agents Management</span> <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manageuser.php">Agent Administration</a>
        <a href="view_users.php">Agent Overview</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="usertarget.php">Call Targets</a>
        <a href="viewtargetdetails.php">Target Overview</a>
    </div>

    <a href="call_timer.php"><i class="bi bi-clock-history"></i> <span>Call Timer</span></a>
    <a href="call_log.php"><i class="bi bi-journal-text"></i> <span>Call Log</span></a>
    
    <button class="dropdown-btn"><i class="bi bi-calendar-check"></i> Site Appointments <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="appointmentlist.php">Appointment List</a>
        <a href="appointment_overview.php">Appointment Overview</a>
    </div>
    
    <a href="reports.php"><i class="bi bi-bar-chart-line"></i> <span>Reports</span></a>

    <button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="profilesetting.php">Profile Setting</a>
    </div>

    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> <span>Logout</span></a></div>
</div>

<!-- Content -->
<div class="main-content">

    <div class="topbar">
        <i class="bi bi-list toggle-btn" id="toggleSidebar"></i>
        <h4><i class="bi bi-gear-fill"></i> Hotline Setting</h4>
    </div>

    <p class="user-guide">Create a new project and assign hotlines to it. Helps organize contacts by project.</p>

    <div class="container-fluid" style="max-width:650px;">

        <!-- Add Project -->
        <div class="card">
            <h4><i class="bi bi-plus-circle text-primary"></i> Add New Project</h4>
            <form method="POST">
                <div class="mb-2">
                    <label class="form-label">Project Name</label>
                    <input type="text" name=project_name class="form-control form-control-sm" placeholder="Enter hotline number..." required>
                    
                </div>
                <button class="btn btn-primary w-100 btn-sm" name="add_project"><i class="bi bi-plus-circle"></i> Add Project</button>
                <?= $message ?>
            </form>
        </div>

        <!-- Projects Table -->
        <div class="card mt-2">
            <h5><i class="bi bi-list-ul"></i> Existing Projects</h5>

            <div class="table-responsive mt-2">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr><th>#</th><th>Project Name</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while($row=$projects->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= htmlspecialchars($row['project_name']) ?></td>
                            <td>
                                <a href="?delete=<?= $row['id'] ?>" class="btn-delete btn-sm"
                                   onclick="return confirm('Are you sure?');">
                                   <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-center mt-3">
            <a href="managehotlines.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

    </div>
</div>

<script>
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click",()=>{
        btn.classList.toggle("active");
        let container=btn.nextElementSibling;
        container.style.display=container.style.display==="block"?"none":"block";
    });
});

// MOBILE SIDEBAR TOGGLE
document.getElementById('toggleSidebar').addEventListener('click',()=>{
    const sidebar=document.getElementById('sidebar');

    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('show');
    } else {
        sidebar.classList.toggle('collapsed');
        document.querySelector('.main-content').classList.toggle('expanded');
    }
});
</script>

</body>
</html>
