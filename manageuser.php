<?php
session_start();
if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}
include 'db.php';
$message = "";

// Fetch admin name for sidebar
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Handle Add User
if(isset($_POST['add_user'])){
    $user_number = $_POST['user_number'];
    $user_name   = $_POST['user_name'];
    $nic         = $_POST['nic'];
    $email       = $_POST['email'];
    $password    = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department  = $_POST['department'];
    $join_date   = $_POST['join_date'];
    $status      = $_POST['status'];

    // ===== NIC uniqueness check =====
    $checkNic = $conn->prepare("SELECT id FROM manageuser WHERE nic = ?");
    $checkNic->bind_param("s", $nic);
    $checkNic->execute();
    $checkNic->store_result();
    if($checkNic->num_rows > 0){
        $message = "Error: NIC already exists!";
    } else {
        $photo_name = "";
        if(isset($_FILES['photo']) && $_FILES['photo']['name'] != ''){
            $photo_name = time().'_'.basename($_FILES['photo']['name']);
            $target_dir = "uploads/users/";
            if(!is_dir($target_dir)) mkdir($target_dir, 0755, true);
            move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir.$photo_name);
        }

        $stmt = $conn->prepare("INSERT INTO manageuser
            (user_number,user_name,nic,email,password,department,join_date,status,photo)
            VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("sssssssss",$user_number,$user_name,$nic,$email,$password,$department,$join_date,$status,$photo_name);

        if($stmt->execute()){
            $message = "User added successfully!";
        } else {
            $message = "Error: ".$stmt->error;
        }
        $stmt->close();
    }
    $checkNic->close();
}

// Fetch users
$users = $conn->query("SELECT * FROM manageuser ORDER BY id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Users - CR Call Center</title>

<!-- Bootstrap + Icons + DataTables -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; margin:0; }

/* ===== Sidebar ===== */
.sidebar {
    position: fixed; top:0; left:0;
    width:230px; height:100vh;
    background:#0b1b58; color:#fff;
    padding-top:20px; overflow-y:auto;
    transition: all 0.3s ease;
    z-index:999;
}
.sidebar img.logo {
    width:180px;
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
.logout-btn{
    position:absolute;
    bottom:20px;
    width:100%;
}
.logout-btn a{
    background: rgba(255,255,255,0.1);
    font-weight:500;
    color:#ffc107;
    display:flex;
    align-items:center;
    justify-content:center;
}

/* ===== Main Content ===== */
.main-content {
    margin-left:220px;
    padding:30px;
    transition: margin-left 0.3s;
}
.card {
    border-radius:16px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    margin-bottom:20px;
}

/* ===== User Photo Hover Effect ===== */
.table img {
    border-radius:50%;
    width:50px;
    height:50px;
    object-fit:cover;
    transition:transform 0.3s ease, box-shadow 0.3s ease;
    cursor:pointer;
}
.table img:hover {
    transform: scale(2.5);
    z-index:100;
    position:relative;
    box-shadow:0 0 10px rgba(0,0,0,0.3);
}

/* ===== Table Hover Effect ===== */
.table tbody tr {
    transition: all 0.2s ease;
}
.table tbody tr:hover {
    background-color: #0000ffff !important;
    transform: scale(1.01);
    box-shadow: 0 8px 10px rgba(0, 0, 0, 1);
}

/* ===== Sidebar Toggle ===== */
.sidebar-toggle {
    display:none;
    position:fixed;
    top:15px;
    left:15px;
    z-index:1000;
    font-size:24px;
    color:#0d6efd;
    cursor:pointer;
}

/* ===== User Guide ===== */
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

/* ===== Responsive ===== */
@media(max-width:768px){
    .sidebar { left:-220px; }
    .sidebar.show { left:0; }
    .main-content { margin-left:0; padding:20px; }
    .sidebar-toggle { display:block; }
    .table img:hover { transform: scale(1.5); } /* smaller hover on mobile */
    .row.g-3 .col-md-3 { flex:0 0 100%; max-width:100%; }
}
</style>
</head>
<body>

<i class="bi bi-list sidebar-toggle" id="sidebarToggle"></i>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>

    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="managehotlines.php">Manage Hotline</a>
        <a href="view_hotline.php">View Hotline</a>
        <a href="hotline_setting.php">Add Project</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-person-lines-fill"></i> Agents Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="manageuser.php" class="active">Agent Administration</a>
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
    <h2 class="mb-4">Manage Users</h2>
    <?php if($message!=""): ?><div class="alert alert-success"><?php echo $message; ?></div><?php endif; ?>

    <p class="user-guide">Manage agents’ accounts. Add, edit, or remove users who will handle calls.</p>

    <!-- Add User Form -->
    <div class="card p-4 mb-4">
        <h5 class="mb-3">Add New User</h5>
        <form method="post" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-3"><input type="text" name="user_number" class="form-control" placeholder="User Number" required></div>
                <div class="col-md-3"><input type="text" name="user_name" class="form-control" placeholder="User Name" required></div>
                <div class="col-md-3"><input type="text" name="nic" class="form-control" placeholder="NIC" required></div>
                <div class="col-md-3"><input type="email" name="email" class="form-control" placeholder="Email" required></div>
                <div class="col-md-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
                <div class="col-md-3">
                    <select name="department" class="form-select" required>
                        <option value="">Select Department</option>
                        <option value="Sales Department">Sales Department</option>
                        <option value="Marketing Department">Marketing Department</option>
                        <option value="Account Department">Account Department</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="date" name="join_date" class="form-control" required></div>
                <div class="col-md-3">
                    <select name="status" class="form-select" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3"><input type="file" name="photo" class="form-control"></div>
                <div class="col-12"><button type="submit" name="add_user" class="btn btn-primary">Add User</button></div>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card p-3">
        <h5 class="mb-3">Users List</h5>
        <div class="table-responsive">
        <table id="usersTable" class="table table-striped table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Photo</th>
                    <th>User Number</th>
                    <th>User Name</th>
                    <th>NIC</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Join Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($user = $users->fetch_assoc()): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td>
                        <?php if($user['photo'] && file_exists("uploads/users/".$user['photo'])): ?>
                            <img src="uploads/users/<?= $user['photo'] ?>" alt="Photo">
                        <?php else: ?>
                            <span class="text-muted">No Photo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $user['user_number'] ?></td>
                    <td><?= $user['user_name'] ?></td>
                    <td><?= $user['nic'] ?></td>
                    <td><?= $user['email'] ?></td>
                    <td><?= $user['department'] ?></td>
                    <td><?= $user['join_date'] ?></td>
                    <td><?= $user['status'] ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                        <a href="delete_user.php?id=<?= $user['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function(){
    $('#usersTable').DataTable({
        pageLength:10,
        lengthMenu:[5,10,25,50,100],
        order:[[0,'desc']],
        columnDefs:[ { orderable:false, targets:[1,9] } ],
        responsive:true
    });

    $('.dropdown-btn').click(function(){
        $(this).next('.dropdown-container').slideToggle(200);
        $('.dropdown-container').not($(this).next()).slideUp(200);
    });

    $('#sidebarToggle').click(function(){
        $('#sidebar').toggleClass('show');
    });
});
</script>
</body>
</html>
