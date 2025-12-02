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

// ===== FUNCTION TO FORMAT PHONE NUMBER =====
function formatPhone($number) {
    $number = preg_replace('/\D/', '', $number);
    if (substr($number, 0, 2) === "94" && strlen($number) === 11) return "+".$number;
    if (substr($number, 0, 1) === "0" && strlen($number) === 10) return "+94".substr($number, 1);
    if (substr($number, 0, 1) === "8" && strlen($number) === 10) return "+94".substr($number, 1);
    if (substr($number, 0, 4) === "0094") return "+".substr($number, 2);
    return "+94".$number;
}

// ===== FETCH CALLS WITH APPOINTMENTS IN NEXT 2 DAYS =====
$stmt = $conn->prepare("
    SELECT call_log.*, target.id as target_id
    FROM call_log
    LEFT JOIN target ON call_log.target_id = target.id
    WHERE call_log.user_id=?
      AND call_log.appointment_date IS NOT NULL
      AND call_log.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY call_log.appointment_date ASC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
$projects = [];
while ($row = $result->fetch_assoc()) {
    $row['number_called'] = formatPhone($row['number_called']);
    $appointments[] = $row;
    if (!empty($row['project_name'])) $projects[$row['project_name']] = true;
}
$totalAppointments = count($appointments);

// ===== Group appointments by date =====
$calendarAppointments = [];
foreach ($appointments as $a) {
    $date = $a['appointment_date'];
    if (!isset($calendarAppointments[$date])) $calendarAppointments[$date] = [];
    $calendarAppointments[$date][] = $a;
}

// ===== Badge Color Helper =====
function badgeColor($date) {
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    if ($date === $today) return 'primary';
    if ($date === $tomorrow) return 'success';
    return 'warning';
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointments Dashboard - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:#f4f8ff;
    color:#333;
    margin:0;
}

/* Sidebar */
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:220px;
    height:100vh;
    background:#0b1b58;
    color:#fff;
    padding-top:20px;
    overflow-y:auto;
    transition: transform 0.3s ease;
    z-index:1050;
}
.sidebar.collapsed {
    transform: translateX(-100%);
}
.sidebar .sidebar-header img.logo {
    width: 180px;
    display:block;
    margin:0 auto 10px;
    border-radius:10px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color:rgba(255,255,255,0.8);
    margin:0 0 10px;
    text-align:center;
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
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
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

/* Content */
.content {
    margin-left:220px;
    padding:20px;
    transition: margin-left 0.3s;
}
.card {
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.1);
    background:#fff;
}
.table thead th {
    background:#0b1b58;
    color:white;
}
.table tbody tr {
    transition: all 0.2s ease-in-out;
}
.table tbody tr:hover {
    background:#eef3ff !important;
    transform: scale(1.01);
    box-shadow:0 10px 10px rgba(255,149,0,0.8);
    cursor:pointer;
}

/* Calendar */
.calendar {
    display:flex;
    flex-wrap:wrap;
    gap:15px;
}
.calendar .day {
    flex:1;
    min-width:220px;
    background:white;
    padding:15px;
    border-radius:10px;
    box-shadow:0 2px 6px rgba(0,0,0,0.1);
}

/* User Guide */
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
@keyframes slideFadeIn { from {opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* Mobile Navbar */
.navbar-mobile {
    display:none;
    background:#0b1b58;
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

/* Search Input */
#searchInput {
    margin-bottom:15px;
    width:100%;
    padding:8px 12px;
    border-radius:6px;
    border:1px solid #ccc;
}

/* Responsive */
@media(max-width:992px){
    .sidebar {
        transform: translateX(-100%);
        width:220px;
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
    <span>Appointments Dashboard</span>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name) ?></p>
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
        <a href="reviewed_onhold.php"><i class="bi bi-telephone-outbound me-2"></i> Reviewed On Hold</a>
    </div>

    <a href="view_user_appointments.php" class="active"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="appointmentlist.php"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>

    <button class="dropdown-btn"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manage_reasons.php"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>

    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- Content -->
<div class="content">
    <h3 class="mb-3"><i class="bi bi-calendar-event"></i> Upcoming Appointments (Next 2 Days)</h3>
    <span class="badge bg-primary mb-3">Total Appointments: <?= $totalAppointments ?></span>

    <p class="user-guide">
        Displays appointments or site visits planned for the day or week. Keeps agents organized.
    </p>

    <input type="text" id="searchInput" placeholder="Search by Name, Project, or Number...">

    <div class="calendar mb-4">
        <?php foreach ($calendarAppointments as $date => $apps): ?>
            <div class="day">
                <h6><?= date('l, d M Y', strtotime($date)) ?></h6>
                <?php foreach ($apps as $a): ?>
                    <div class="mb-1">
                        <span class="badge bg-<?= badgeColor($a['appointment_date']) ?>">
                            <?= htmlspecialchars($a['customer_name']) ?> (<?= htmlspecialchars($a['project_name']) ?>)
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card p-3">
        <?php if(empty($appointments)): ?>
            <div class="alert alert-info">No appointments in the next 2 days.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered align-middle text-center" id="appointmentsTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Project</th>
                        <th>Reason</th>
                        <th>Appointment Date</th>
                        <th>Follow-Up</th>
                        <th>Actions</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i=1; foreach($appointments as $app): ?>
                        <?php $color = badgeColor($app['appointment_date']); ?>
                        <tr data-project="<?= htmlspecialchars($app['project_name']) ?>" data-number="<?= htmlspecialchars($app['number_called']) ?>">
                            <td><?= $i++ ?></td>
                            <td class="text-primary fw-bold"><?= htmlspecialchars($app['number_called']) ?></td>
                            <td><?= htmlspecialchars($app['customer_name']) ?></td>
                            <td><?= htmlspecialchars($app['project_name']) ?></td>
                            <td><?= htmlspecialchars($app['appointment_reason']) ?: '-' ?></td>
                            <td><span class="badge bg-<?= $color ?>"><?= htmlspecialchars($app['appointment_date']) ?></span></td>
                            <td><?= $app['followup_date'] ?: '-' ?></td>
                            <td>
                                <button class="btn btn-sm btn-success whatsappBtn mt-1"><i class="bi bi-whatsapp"></i> WhatsApp</button>
                            </td>
                            <td><?= nl2br(htmlspecialchars($app['notes'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar dropdowns
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click",()=>{
        const drop=btn.nextElementSibling;
        drop.style.display=drop.style.display==='block'?'none':'block';
    });
});

// Mobile sidebar toggle
document.getElementById('mobileSidebarToggle').addEventListener('click',()=>{
    document.getElementById('sidebar').classList.toggle('active');
});

// WhatsApp button
document.querySelectorAll(".whatsappBtn").forEach(btn=>{
    btn.onclick=function(){
        const row=this.closest("tr");
        const number=row.dataset.number.replace(/\D/g,'');
        const name=row.cells[2].textContent.trim();
        const appointment=row.cells[5].textContent.trim();
        const message=`Hello ${name}, this is from Comemercial Real Estate. Your appointment is on ${appointment}.`;
        const url=`https://api.whatsapp.com/send?phone=${number}&text=${encodeURIComponent(message)}`;
        window.open(url,'_blank');
    };
});

// Search filter
$('#searchInput').on('keyup', function(){
    const value = $(this).val().toLowerCase();
    $('#appointmentsTable tbody tr').filter(function(){
        $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
    });
});
</script>
</body>
</html>
