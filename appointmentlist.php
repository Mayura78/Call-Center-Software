<?php
session_start();
include 'db.php';

// ===== CHECK ADMIN LOGIN =====
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// ===== FORMAT PHONE =====
function formatPhone($number) {
    $number = preg_replace('/\D/', '', $number);
    if (substr($number, 0, 2) === "94" && strlen($number) === 11) return "+".$number;
    if (substr($number, 0, 1) === "0" && strlen($number) === 10) return "+94".substr($number, 1);
    if (substr($number, 0, 1) === "8" && strlen($number) === 10) return "+94".substr($number, 1);
    if (substr($number, 0, 4) === "0094") return "+".substr($number, 2);
    return "+94".$number;
}

// ===== FETCH ALL APPOINTMENTS =====
$stmt = $conn->prepare("
    SELECT c.*, u.user_name
    FROM call_log c
    LEFT JOIN manageuser u ON c.user_id = u.id
    WHERE c.appointment_date IS NOT NULL
    ORDER BY c.appointment_date ASC
");
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
$soldCount = $visitedCount = $cancelledCount = $pendingCount = 0;

while($row = $result->fetch_assoc()){
    $row['number_called'] = formatPhone($row['number_called']);
    $appointments[] = $row;

    // Count status
    if($row['visit_status'] === 'Sold') $soldCount++;
    else if($row['visit_status'] === 'Visited') $visitedCount++;
    else if($row['visit_status'] === 'Cancelled') $cancelledCount++;
    else $pendingCount++;
}

// ===== BADGE COLOR =====
function badgeColor($date){
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if($date === $today) return 'primary';
    if($date === $tomorrow) return 'success';
    return 'warning';
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Admin Appointments</title>

<!-- ===== CSS ===== -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; margin:0; }
/* ===== Sidebar ===== */
.sidebar { position: fixed; top:0; left:0; width:230px; height:100vh; background:#0b1b58; color:#fff; padding-top:20px; overflow-y:auto; transition: all 0.3s ease; z-index:999; }
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
.sidebar i {margin-right:8px;}
/* ===== CONTENT ===== */
.content {margin-left:280px; padding:20px;}
.table thead th {background:#0b1b58; color:white;}
.badge-status {font-size:12px;}
.card-status {margin-bottom:20px;}
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; opacity:0; transform:translateY(20px); animation: slideFadeIn 0.8s ease-out forwards; }
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
#toastContainer { position: fixed; top:20px; right:20px; z-index: 1050; }
.toast { min-width: 250px; }
</style>
</head>
<body>

<!-- SIDEBAR -->
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

    <button class="dropdown-btn"><i class="bi bi-calendar-check"></i> Site Appointments <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="appointmentlist.php" class="active">Appointment List</a>
        <a href="appointment_overview.php">Appointment Overview</a>
    </div>

    <a href="reports.php"><i class="bi bi-bar-chart-line"></i> Reports</a>
    <button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="profilesetting.php">Profile Setting</a>
    </div>
    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- CONTENT -->
<div class="content">
    <h3><i class="bi bi-calendar-event"></i> All Appointments</h3>
    <p class="user-guide">“Manage appointment-based earnings efficiently: assign and track income for each appointment to ensure accurate performance monitoring and bonus calculations.”</p>

    <!-- STATUS CARDS -->
    <div class="row">
        <div class="col-md-3">
            <div class="card text-white bg-success card-status">
                <div class="card-body text-center">
                    <h5 class="card-title">Sold</h5>
                    <h3 id="soldCount"><?= $soldCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info card-status">
                <div class="card-body text-center">
                    <h5 class="card-title">Visited</h5>
                    <h3 id="visitedCount"><?= $visitedCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-danger card-status">
                <div class="card-body text-center">
                    <h5 class="card-title">Cancelled</h5>
                    <h3 id="cancelledCount"><?= $cancelledCount ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-secondary card-status">
                <div class="card-body text-center">
                    <h5 class="card-title">Pending</h5>
                    <h3 id="pendingCount"><?= $pendingCount ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- APPOINTMENTS TABLE -->
    <div class="table-responsive">
        <table id="appointmentsTable" class="table table-bordered table-striped text-center">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Project</th>
                    <th>Reason</th>
                    <th>Date</th>
                    <th>User</th>
                    <th>Status</th>
                    <th>Income</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $i=1; foreach($appointments as $app): ?>
                <?php $color = badgeColor($app['appointment_date']); ?>
                <tr id="row-<?= $app['id'] ?>">
                    <td><?= $i++ ?></td>
                    <td><?= $app['customer_name'] ?></td>
                    <td><?= $app['number_called'] ?></td>
                    <td><?= $app['project_name'] ?></td>
                    <td><?= $app['appointment_reason'] ?></td>
                    <td><span class="badge bg-<?= $color ?>"><?= $app['appointment_date'] ?></span></td>
                    <td><?= $app['user_name'] ?></td>
                    <td>
                        <?php if($app['visit_status']): ?>
                            <span class="badge bg-<?php
                                if($app['visit_status']=='Sold') echo 'success';
                                else if($app['visit_status']=='Visited') echo 'info';
                                else if($app['visit_status']=='Cancelled') echo 'danger';
                                else echo 'secondary';
                            ?>"><?= $app['visit_status'] ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Pending</span>
                        <?php endif; ?>
                    </td>
                    <td>Rs. <?= number_format($app['bonus_amount'],2) ?></td>
                    <td>
                        <button class="btn btn-success btn-sm statusBtn" data-id="<?= $app['id'] ?>" data-name="<?= $app['customer_name'] ?>">Update</button>
                        <button class="btn btn-danger btn-sm cancelBtn" data-id="<?= $app['id'] ?>" data-name="<?= $app['customer_name'] ?>">Cancel</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="statusModal">
    <div class="modal-dialog">
        <form id="statusForm" class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Update Appointment Status</h5>
            </div>
            <div class="modal-body">
                <input type="hidden" id="update_id" name="id">
                <label class="form-label">Select Status</label>
                <select class="form-select" name="visit_status" required>
                    <option value="">-- Select --</option>
                    <option value="Visited">Visited (Site Visit)</option>
                    <option value="Sold">Sold (Deal Closed)</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
                <label class="form-label mt-3">Income Amount (Rs.)</label>
                <input type="number" name="bonus_amount" class="form-control" min="0" step="0.01" required>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success">Confirm Update</button>
            </div>
        </form>
    </div>
</div>

<div id="toastContainer"></div>

<!-- ===== JS ===== -->
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
// DATATABLE with Export Buttons
$('#appointmentsTable').DataTable({
    "order": [[5, "asc"]],
    dom: 'Bfrtip',
    buttons: [
        {
            extend: 'excelHtml5',
            text: '<i class="bi bi-file-earmark-excel"></i> Excel',
            className: 'btn btn-success btn-sm'
        },
        {
            extend: 'pdfHtml5',
            text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
            className: 'btn btn-danger btn-sm',
            orientation: 'landscape',
            pageSize: 'A4'
        },
        {
            extend: 'print',
            text: '<i class="bi bi-printer"></i> Print',
            className: 'btn btn-primary btn-sm'
        }
    ]
});

// OPEN MODAL
$('.statusBtn').click(function(){
    $('#update_id').val($(this).data('id'));
    new bootstrap.Modal('#statusModal').show();
});

// SHOW TOAST
function showToast(message, type='success'){
    let toastId = 'toast-' + Date.now();
    let toastHtml = `
    <div id="${toastId}" class="toast align-items-center text-bg-${type} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>`;
    $('#toastContainer').append(toastHtml);
    let toastEl = new bootstrap.Toast(document.getElementById(toastId), { delay: 3000 });
    toastEl.show();
}

// SUBMIT STATUS UPDATE
$('#statusForm').submit(function(e){
    e.preventDefault();
    $.post("update_visit_status.php", $(this).serialize(), function(res){
        $('#statusModal').modal('hide');
        showToast(res, 'success');
        setTimeout(()=>{ location.reload(); }, 1500);
    });
});

// CANCEL BUTTON
$('.cancelBtn').click(function(){
    let id = $(this).data('id');
    let name = $(this).data('name');
    if(confirm(`Are you sure you want to cancel ${name}'s appointment?`)){
        $.post("cancel_appointment.php", {id: id}, function(res){
            showToast(res, 'danger');
            setTimeout(()=>{ location.reload(); }, 1500);
        });
    }
});

// SIDEBAR DROPDOWN
var dropdowns = document.getElementsByClassName("dropdown-btn");
for (var i = 0; i < dropdowns.length; i++) {
    dropdowns[i].addEventListener("click", function() {
        this.classList.toggle("active");
        var container = this.nextElementSibling;
        container.style.display = (container.style.display === "block") ? "none" : "block";
    });
}
</script>
</body>
</html>
