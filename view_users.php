<?php
session_start();
if(!isset($_SESSION['admin_id'])){
  header("Location: login.php");
  exit();
}
include 'db.php';

// Admin name for sidebar
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Fetch user details
$users = $conn->query("SELECT * FROM manageuser ORDER BY id DESC");

// Count summary
$total_users = $conn->query("SELECT COUNT(*) AS c FROM manageuser")->fetch_assoc()['c'];
$active_users = $conn->query("SELECT COUNT(*) AS c FROM manageuser WHERE status='Active'")->fetch_assoc()['c'];
$inactive_users = $total_users - $active_users;

// AJAX: fetch single user
if(isset($_POST['action']) && $_POST['action']=='get_user'){
    $id = intval($_POST['id']);
    $user = $conn->query("SELECT * FROM manageuser WHERE id=$id")->fetch_assoc();
    echo json_encode($user);
    exit();
}

// AJAX: update user
if(isset($_POST['action']) && $_POST['action']=='update_user'){
    $id = intval($_POST['id']);
    $user_number = $conn->real_escape_string($_POST['user_number']);
    $user_name = $conn->real_escape_string($_POST['user_name']);
    $nic = $conn->real_escape_string($_POST['nic']);
    $email = $conn->real_escape_string($_POST['email']);
    $department = $conn->real_escape_string($_POST['department']);
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE manageuser SET user_number='$user_number', user_name='$user_name', nic='$nic', email='$email', department='$department', status='$status' WHERE id=$id");
    echo json_encode(['success'=>true]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Agent Dashboard - CR Call Center</title>

<!-- Bootstrap & Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<!-- DataTables -->
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">

<style>
body { font-family:'Poppins',sans-serif; background:#f5f7fb; margin:0; }

/* ===== Sidebar ===== */
.sidebar {
    position: fixed; top:0; left:0;
    width:230px; height:100vh;
    background:#0b1b58;
    color:#fff; padding-top:20px;
    overflow-y:auto; transition: all 0.3s ease;
    z-index:999;
}
.sidebar img.logo { width:180px; margin:0 auto 10px; display:block; }
.sidebar .address { font-size:12px; color:rgba(255,255,255,0.7); text-align:center; margin-bottom:10px; }
.sidebar .welcome { font-size:0.9rem; color:#ffc107; text-align:center; font-weight:500; margin-bottom:20px; }
.sidebar a, .sidebar button { display:block; width:100%; padding:10px 20px; color:white; text-decoration:none; font-size:14px; background:none; border:none; text-align:left; border-radius:6px; transition:0.3s; cursor:pointer; }
.sidebar a:hover, .sidebar button:hover { background: rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar a.active { background: rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar .dropdown-container{ display:none; background:rgba(0,0,0,0.05); border-radius:5px; margin-left:10px; }
.sidebar .dropdown-container a{ padding-left:35px; font-size:13px; border-radius:4px; }
.logout-btn{ position:absolute; bottom:20px; width:100%; }
.logout-btn a{ background: rgba(255,255,255,0.1); font-weight:500; color:#ffc107; display:flex; align-items:center; justify-content:center; }

/* Main Content */
.main-content { margin-left:220px; padding:30px; transition:margin-left 0.3s; }
.card { border-radius:15px; box-shadow:0 3px 15px rgba(0,0,0,0.08); background:#fff; padding:20px; }
.table thead th { background:#0167ff; color:white; }

/* Summary Boxes */
.summary-boxes { display:flex; gap:20px; margin-bottom:20px; flex-wrap:wrap; }
.summary-box { flex:1; min-width:200px; background:linear-gradient(90deg,#0167ff,#00c6ff); color:white; padding:10px; border-radius:10px; text-align:center; box-shadow:0 3px 15px rgba(0, 0, 0, 0.49); transition:0.3s; }
.summary-box:hover { transform:translateY(-5px); box-shadow:0 6px 20px rgba(0,0,0,0.15); }
.summary-box h5 { margin:0; font-size:28px; font-weight:700; }
.summary-box p { margin:0; font-size:14px; }

/* Row hover effect */
.table tbody tr { transition: all 0.3s ease; cursor:pointer; }
.table tbody tr:hover { transform: scale(1.03); background: linear-gradient(90deg,#e6f2ff,#cce6ff) !important; box-shadow:0 8px 20px rgba(0, 0, 0, 1); }

/* User photo hover */
.table img { border-radius:50%; width:40px; height:40px; object-fit:cover; transition:0.3s; }
.table img:hover { transform:scale(6); z-index:10; position:relative; }

/* Sidebar toggle button */
.sidebar-toggle { display:none; position:fixed; top:15px; left:15px; font-size:26px; color:#0167ff; cursor:pointer; z-index:1000; }

/* User guide */
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; margin-bottom:20px; opacity:0; transform:translateY(20px); animation: slideFadeIn 0.8s ease-out forwards; }
@keyframes slideFadeIn { from {opacity:0; transform:translateY(20px);} to {opacity:1; transform:translateY(0);} }

/* Responsive */
@media(max-width:992px){
  .sidebar { left:-220px; }
  .sidebar.show { left:0; }
  .main-content { margin-left:0; padding:20px; }
  .sidebar-toggle { display:block; }
  .table img:hover { transform:scale(2); }
}
</style>
</head>
<body>

<!-- Sidebar Toggle -->
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
        <a href="manageuser.php">Agent Administration</a>
        <a href="view_users.php" class="active">Agent Overview</a>
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
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
    <h3 class="fw-bold text-primary"><i class="bi bi-people"></i> Agent Details</h3>
  </div>

  <p class="user-guide">Overview of all agents, their details, and activity. Useful for monitoring performance.</p>

  <div class="summary-boxes">
    <div class="summary-box"><h5 class="counter" data-target="<?= $total_users ?>">0</h5><p>Total Agents</p></div>
    <div class="summary-box"><h5 class="counter" data-target="<?= $active_users ?>">0</h5><p>Active Agents</p></div>
    <div class="summary-box"><h5 class="counter" data-target="<?= $inactive_users ?>">0</h5><p>Inactive Agents</p></div>
  </div>

  <div class="card">
    <table id="usersTable" class="table table-striped table-hover align-middle w-100">
      <thead>
        <tr>
          <th>ID</th><th>Photo</th><th>User Number</th><th>User Name</th><th>NIC</th><th>Email</th><th>Department</th><th>Join Date</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php while($user = $users->fetch_assoc()): ?>
        <tr>
          <td><?= $user['id'] ?></td>
          <td>
            <?php if($user['photo'] && file_exists("uploads/users/".$user['photo'])): ?>
              <img src="uploads/users/<?= htmlspecialchars($user['photo']) ?>" alt="User">
            <?php else: ?>
              <span class="text-muted">No Photo</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($user['user_number']) ?></td>
          <td><?= htmlspecialchars($user['user_name']) ?></td>
          <td><?= htmlspecialchars($user['nic']) ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= htmlspecialchars($user['department']) ?></td>
          <td><?= htmlspecialchars($user['join_date']) ?></td>
          <td>
            <?php if($user['status']=="Active"): ?><span class="badge bg-success">Active</span>
            <?php else: ?><span class="badge bg-secondary">Inactive</span><?php endif; ?>
          </td>
          <td>
            <button class="btn btn-sm btn-warning btn-action edit-btn" data-id="<?= $user['id'] ?>"><i class="bi bi-pencil-square"></i></button>
            <a href="delete_user.php?id=<?= $user['id'] ?>" onclick="return confirm('Are you sure?')" class="btn btn-sm btn-danger btn-action"><i class="bi bi-trash"></i></a>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Edit Agent</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="editUserForm">
          <input type="hidden" name="id" id="editUserId">
          <div class="mb-2"><label>User Number</label><input type="text" class="form-control" name="user_number" id="editUserNumber" required></div>
          <div class="mb-2"><label>User Name</label><input type="text" class="form-control" name="user_name" id="editUserName" required></div>
          <div class="mb-2"><label>NIC</label><input type="text" class="form-control" name="nic" id="editUserNIC"></div>
          <div class="mb-2"><label>Email</label><input type="email" class="form-control" name="email" id="editUserEmail"></div>
          <div class="mb-2"><label>Department</label><input type="text" class="form-control" name="department" id="editUserDept"></div>
          <div class="mb-2"><label>Status</label>
            <select class="form-select" name="status" id="editUserStatus">
              <option value="Active">Active</option>
              <option value="Inactive">Inactive</option>
            </select>
          </div>
          <button type="submit" class="btn btn-primary w-100">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- DataTables Buttons -->
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

<script>
$(document).ready(function(){
  // Counter animation
  $('.counter').each(function(){
    var $this = $(this), target = +$this.attr('data-target');
    $({countNum: 0}).animate({countNum: target},{
      duration:1500, easing:'swing',
      step:function(){ $this.text(Math.floor(this.countNum)); },
      complete:function(){ $this.text(this.countNum); }
    });
  });

  // DataTable with Buttons
  $('#usersTable').DataTable({
    pageLength:8,
    order:[[0,'desc']],
    columnDefs:[{orderable:false, targets:[1,9]}],
    dom: 'Bfrtip',
    buttons: [
      { extend:'excelHtml5', className:'btn btn-success btn-sm', text:'Export Excel' },
      { extend:'pdfHtml5', className:'btn btn-danger btn-sm', text:'Export PDF', orientation:'landscape', pageSize:'A4' },
      { extend:'print', className:'btn btn-info btn-sm', text:'Print' }
    ]
  });

  // Dropdown menus
  $('.dropdown-btn').click(function(){
    $(this).next('.dropdown-container').slideToggle(200);
    $('.dropdown-container').not($(this).next()).slideUp(200);
  });

  // Sidebar toggle
  $('#sidebarToggle').click(function(){ $('#sidebar').toggleClass('show'); });

  // Edit modal
  $('.edit-btn').click(function(){
    var id = $(this).data('id');
    $.post('', {action:'get_user', id:id}, function(data){
      var user = JSON.parse(data);
      $('#editUserId').val(user.id);
      $('#editUserNumber').val(user.user_number);
      $('#editUserName').val(user.user_name);
      $('#editUserNIC').val(user.nic);
      $('#editUserEmail').val(user.email);
      $('#editUserDept').val(user.department);
      $('#editUserStatus').val(user.status);
      new bootstrap.Modal(document.getElementById('editUserModal')).show();
    });
  });

  $('#editUserForm').submit(function(e){
    e.preventDefault();
    $.post('', $(this).serialize()+'&action=update_user', function(data){
      if(JSON.parse(data).success){ location.reload(); }
    });
  });
});
</script>
</body>
</html>
