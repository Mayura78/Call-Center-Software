<?php
session_start();
include 'db.php';

// ===== CHECK ADMIN LOGIN =====
if(!isset($_SESSION['admin_id'])){
    header("Location: admin_login.php");
    exit();
}
$admin_name = $_SESSION['admin_name'];

// ===== SELECTED OR CURRENT MONTH =====
$selectedMonth = $_GET['month'] ?? date('Y-m');
$monthName = date('F Y', strtotime($selectedMonth . '-01'));

// ===== FETCH CALL DATA =====
$stmt = $conn->prepare("
    SELECT DATE(called_at) as date, COUNT(*) as total_calls,
           SUM(CASE WHEN sentiment = 'Positive' THEN 1 ELSE 0 END) as positive,
           SUM(CASE WHEN sentiment = 'Negative' THEN 1 ELSE 0 END) as negative,
           SUM(CASE WHEN sentiment = 'Neutral' THEN 1 ELSE 0 END) as neutral,
           SUM(CASE WHEN sentiment = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
SUM(CASE WHEN sentiment = 'Won' THEN 1 ELSE 0 END) as won

    FROM call_log
    WHERE DATE_FORMAT(called_at, '%Y-%m') = ?
    GROUP BY DATE(called_at)
    ORDER BY DATE(called_at) ASC
");
$stmt->bind_param("s", $selectedMonth);
$stmt->execute();
$dailyData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ===== FETCH TOTALS =====
$totalStmt = $conn->prepare("
    SELECT COUNT(*) as total_calls,
            SUM(CASE WHEN sentiment = 'Positive' THEN 1 ELSE 0 END) as positive,
            SUM(CASE WHEN sentiment = 'Negative' THEN 1 ELSE 0 END) as negative,
            SUM(CASE WHEN sentiment = 'Neutral' THEN 1 ELSE 0 END) as neutral,
            SUM(CASE WHEN sentiment = 'On Hold' THEN 1 ELSE 0 END) as on_hold,
            SUM(CASE WHEN sentiment = 'Won' THEN 1 ELSE 0 END) as won

    FROM call_log
    WHERE DATE_FORMAT(called_at, '%Y-%m') = ?
");
$totalStmt->bind_param("s", $selectedMonth);
$totalStmt->execute();
$totals = $totalStmt->get_result()->fetch_assoc();
$totalStmt->close();

// ===== FETCH UPCOMING APPOINTMENTS =====
$appStmt = $conn->prepare("
    SELECT c.customer_name, c.number_called, c.project_name, c.appointment_date, c.appointment_reason, m.user_name
    FROM call_log c
    LEFT JOIN manageuser m ON c.user_id = m.id
    WHERE c.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)
    ORDER BY c.appointment_date ASC
");
$appStmt->execute();
$appointments = $appStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$appStmt->close();

// ===== FETCH TODAY'S APPOINTMENTS =====
$todayAppointmentsStmt = $conn->prepare("
    SELECT c.customer_name, c.number_called, c.project_name, c.appointment_date, c.appointment_reason, m.user_name
    FROM call_log c
    LEFT JOIN manageuser m ON c.user_id = m.id
    WHERE c.appointment_date = CURDATE()
    ORDER BY c.appointment_date ASC
");
$todayAppointmentsStmt->execute();
$todayAppointments = $todayAppointmentsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$todayAppointmentsStmt->close();

// ===== FETCH FOLLOW-UPS =====
$followStmt = $conn->prepare("
    SELECT m.user_name, COUNT(*) AS total
    FROM call_log c
    LEFT JOIN manageuser m ON c.user_id = m.id
    WHERE c.followup_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
    GROUP BY c.user_id
    ORDER BY total DESC
");
$followStmt->execute();
$followData = $followStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$followStmt->close();


// ===== FETCH ON HOLD CALLS =====
$onHoldQuery = $conn->query("SELECT COUNT(*) AS on_hold_total FROM call_log WHERE call_status='On Hold'");
$onHoldData = $onHoldQuery->fetch_assoc();
$onHoldTotal = $onHoldData['on_hold_total'] ?? 0;

// ===== FETCH ONGOING CALLS =====
$ongoingQuery = $conn->query("SELECT COUNT(*) AS ongoing_total FROM call_log WHERE call_status='Ongoing'");
$ongoingData = $ongoingQuery->fetch_assoc();
$ongoingTotal = $ongoingData['ongoing_total'] ?? 0;

// ===== FETCH ON HOLD CALLS =====
$onHoldQuery = $conn->query("SELECT COUNT(*) AS on_hold_total FROM call_log WHERE sentiment='On Hold'");
$onHoldData = $onHoldQuery->fetch_assoc();
$onHoldTotal = $onHoldData['on_hold_total'] ?? 0;

// ===== FETCH WON CALLS =====
$wonQuery = $conn->query("SELECT COUNT(*) AS won_total FROM call_log WHERE sentiment='Won'");
$wonData = $wonQuery->fetch_assoc();
$wonTotal = $wonData['won_total'] ?? 0;

// ===== FETCH SOLD CALLS =====
$soldQuery = $conn->query("SELECT COUNT(*) AS sold_total FROM call_log WHERE visit_status='Sold'");
$soldData = $soldQuery->fetch_assoc();
$soldTotal = $soldData['sold_total'] ?? 0;


// ===== MONTH DROPDOWN =====
$months = [];
for ($i = 0; $i < 12; $i++) {
    $monthValue = date('Y-m', strtotime("-$i month"));
    $months[$monthValue] = date('F Y', strtotime($monthValue . '-01'));
}
$currentDate = date('Y-m-d');

// ===== FETCH COMPANY PROFILE FOR SIDEBAR =====
$tableCheck = $conn->query("SHOW TABLES LIKE 'sidebar_settings'");
if ($tableCheck->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `sidebar_settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `company_name` varchar(255) DEFAULT NULL,
        `company_address` text DEFAULT NULL,
        `phone_number` varchar(50) DEFAULT NULL,
        `logo_path` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("INSERT INTO sidebar_settings (id, company_name, company_address, phone_number, logo_path) 
                  VALUES (1, 'Commercial Realty (Pvt) Ltd.', '2nd Floor, 132 Avissawella Rd, Maharagama 10280', '0114 389 900', 'CRealty.png')");
}

// ===== FETCH TOTAL SITE VISITS =====
$visitQuery = $conn->query("SELECT COUNT(*) AS total_visits FROM call_log WHERE visit_status='Visited'");
$visitData = $visitQuery->fetch_assoc();
$totalVisits = $visitData['total_visits'] ?? 0;

$settingRes = $conn->query("SELECT * FROM sidebar_settings WHERE id=1");
$setting = $settingRes && $settingRes->num_rows > 0 ? $settingRes->fetch_assoc() : [
    'company_name' => 'Commercial Realty (Pvt) Ltd.',
    'company_address' => '2nd Floor, 132 Avissawella Rd, Maharagama 10280',
    'phone_number' => '0114 389 900',
    'logo_path' => 'CRealty.png'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CR Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
  font-family:'Poppins',sans-serif;
  background:#f5f6fa;
  margin:0; color:#333;
  min-height:100vh;
  display:flex;
  flex-direction:column;
}

/* Sidebar */
.sidebar {
  position:fixed;
  top:0;
  left:0;
  width:230px;
  height:100vh;
  background:#0b1b58;
  color:#fff;
  padding-top:20px;
  box-shadow:2px 0 10px rgba(0,0,0,0.1);
  transition:all 0.3s ease;
  overflow:auto;
  z-index:1000;
}
.sidebar.collapsed {
  width:75px;
}
.sidebar .brand {
  text-align:center;
  margin-bottom:20px;
  padding:0 10px;
}
.sidebar .brand img {
  width:180px;
  height:auto;
  transition:all 0.3s ease;
  border-radius:6px;
}
.sidebar.collapsed .brand img {
  width:50px;
}
.sidebar .brand .brand-address {
  font-size:12px;
  color:#d8dbf7;
  margin-top:10px;
}
.sidebar.collapsed .brand .brand-address {
  display:none;
}
.sidebar ul {
  list-style:none;
  padding:0;
  margin:0;
}
.sidebar ul li {
  margin:6px 10px;
}
.sidebar ul li a {
  display:flex;
  align-items:center;
  color:#fff;
  text-decoration:none;
  padding:10px 15px;
  border-radius:10px;
  font-weight:500;
  transition:0.3s;
}
.sidebar ul li a i {
  font-size:14px;
  margin-right:12px;
}
.sidebar ul li a:hover {
  background:rgba(255,255,255,0.12);
  transform:translateX(3px);
}
.sidebar ul li.active a {
  background:linear-gradient(90deg,#fd7e14 0%,#ffa94d 100%);
  box-shadow:0 3px 10px rgba(0,0,0,0.2);
}
.sidebar ul li ul.submenu {
  list-style:none;
  padding-left:30px;
  display:none;
}
.sidebar ul li.show ul.submenu {
  display:block;
}
.sidebar ul li ul.submenu li a {
  font-size:14px;
  color:#d8dbf7;
  padding:6px 10px;
}
.sidebar ul li ul.submenu li a:hover {
  color:#ffc107;
}
.toggle-btn {
  position:absolute;
  top:12px;
  right:-17px;
  width:36px;
  height:36px;
  border-radius:50%;
  background:#fd7e14;
  color:white;
  border:none;
  box-shadow:0 2px 5px rgba(0,0,0,0.3);
  cursor:pointer;
  transition:0.3s;
}
.toggle-btn:hover {
  background:#ff922b;
}

/* Main content */
.main-content {
  flex:1;
  margin-left:250px;
  padding:20px;
  transition:margin-left 0.3s ease;
}
.sidebar.collapsed ~ .main-content {
  margin-left:75px;
}
.card {
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
}
.company-header {
  background:#fff;
  border-radius:12px;
  box-shadow:0 2px 8px rgba(0,0,0,0.08);
  padding:20px;
  text-align:center;
  margin-bottom:20px;
}
.company-header h2 {
  font-size:24px;
  font-weight:700;
  margin-bottom:5px;
}
.company-header p {
  margin:0;
  font-size:14px;
  color:#555;
}
#currentDateTime {
  margin-top:10px;
  font-weight:500;
  color:#0d6efd;
}
.table tbody tr {
  transition: all 0.3s ease;
  cursor:pointer;
}
.table tbody tr:hover {
  background: #ff0000ff;
  transform: translateX(60px);
}
.table tbody tr.current-day {
  background: #ff0000ff !important;
}

/* Notification Dropdown */
#notifDropdown {
  border-radius: 2px;
  overflow: hidden;
  max-height: 400px;
  overflow-y: auto;
  transition: all 0.3s ease;
}
#notifDropdown .list-group-item:hover {
  background: #fff3cd;
  cursor: pointer;
  transform: translateX(5px);
  transition: all 0.2s ease;
}
#appointmentNotifBtn {
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s ease;
}
#appointmentNotifBtn:hover {
  transform: scale(1.1);
}
.badge {
  font-size: 0.7rem;
}

/* Footer */
.footer {
  background:#0b1b58;
  color:white;
  text-align:center;
  font-size:10px;
  padding:5px;
}
.footer a {
  color:#ffc107;
  text-decoration:none;
}
.footer a:hover {
  text-decoration:underline;
}

.dashboard-card {
    border-radius: 10px;
    padding: 5px 8px;
    min-height: 30px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    transition: transform 0.3s, box-shadow 0.3s;
    font-weight: 400;
}

.dashboard-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 1);
}

.dashboard-card h6 {
    font-size: 0.95rem;
    margin-bottom: 2px;
}

.dashboard-card h2 {
    font-size: 2rem;
    font-weight: 700;
}

.dashboard-card i {
    font-size: 2rem;
    margin-bottom: 5px;
}


/* Welcome Notification Style */
.welcome-notif {
  position: fixed;
  top: 20px;
  right: 20px;
  background: #0d6efd; /* Bootstrap primary color */
  color: white;
  padding: 15px 20px;
  border-radius: 10px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.2);
  font-weight: 600;
  font-size: 16px;
  z-index: 1050;
  opacity: 0;
  transform: translateY(-20px);
  transition: all 0.5s ease;
}
.welcome-notif.show {
  opacity: 1;
  transform: translateY(0);
}


/* MOBILE RESPONSIVE */
@media(max-width:991px){
  .sidebar { left:-250px; width:230px; }
  .sidebar.active { left:0; }
  .main-content { margin-left:0; }
  .toggle-btn { top:10px; right:10px; z-index:1100; }
}
@media(max-width:576px){ .card p, .card h6 { font-size:12px; } }
</style>
</head>
<body>

<!-- Welcome Notification -->
<div id="welcomeNotif" class="welcome-notif">
  👋 Welcome, <?= htmlspecialchars($admin_name) ?>!
</div>


<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
  <button class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></button>
  <div class="brand">
    <img src="<?= htmlspecialchars($setting['logo_path'] ?: 'CRealty.png') ?>" alt="Logo">
    <div class="brand-address">
      <?= htmlspecialchars($setting['company_address'] ?: '2nd Floor, 132 Avissawella Rd, Maharagama') ?><br>
      📞 <?= htmlspecialchars($setting['phone_number'] ?: '0114 389 900') ?>
    </div>
  </div>
  <ul>
    <li class="active"><a href="index.php"><i class="bi bi-house"></i><span>Dashboard</span></a></li>
    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-telephone"></i><span>Hotlines</span></a>
      <ul class="submenu">
        <li><a href="managehotlines.php">Manage Hotline</a></li>
        <li><a href="view_hotline.php">View Hotline</a></li>
        <li><a href="hotline_setting.php">Add Project</a></li>
      </ul>
    </li>
    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-person-lines-fill"></i><span>Agents Management</span></a>
      <ul class="submenu">
        <li><a href="manageuser.php">Agent Administration</a></li>
        <li><a href="view_users.php">Agent Overview</a></li>
      </ul>
    </li>
    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-telephone-outbound"></i><span>Target Management</span></a>
      <ul class="submenu">
        <li><a href="usertarget.php">Call Targets</a></li>
        <li><a href="viewtargetdetails.php">Target Overview</a></li>
      </ul>
    </li>
    <li><a href="call_timer.php"><i class="bi bi-clock-history"></i><span>Call Timer</span></a></li>
    <li><a href="call_log.php"><i class="bi bi-journal-text"></i><span>Call Logs</span></a></li>
    
    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-calendar-check"></i><span>Site Appointments</span></a>
      <ul class="submenu">
        <li><a href="appointmentlist.php">Appointment List</a></li>
        <li><a href="appointment_overview.php">Appointment Overview</a></li>
      </ul>
    </li>

    <li><a href="reports.php"><i class="bi bi-bar-chart-line"></i><span>Reports</span></a></li>
    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-gear"></i><span>Settings</span></a>
      <ul class="submenu">
        <li><a href="profilesetting.php">Profile Setting</a></li>
      </ul>
    </li>

    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i><span> Logout</span></a></li>
  </ul>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
  <div class="container-fluid">
    <div class="company-header">
      <h2><?= htmlspecialchars($setting['company_name'] ?: 'Commercial Realty (Pvt) Ltd.') ?></h2>
      <p><?= htmlspecialchars($setting['company_address'] ?: '2nd Floor, 132 Avissawella Rd, Maharagama 10280') ?></p>
      <p>📞 <?= htmlspecialchars($setting['phone_number'] ?: '0114 389 900') ?></p>
      <span id="currentDateTime"></span>
    </div>


    <!-- ===== Today appointments notification===== -->
    <div class="d-flex justify-content-between align-items-center mb-4 mt-4 flex-wrap">
      <div class="ms-3 position-relative d-inline-block">
        <button class="btn btn-warning position-relative" id="appointmentNotifBtn" title="Today's Appointments">
          <i class="bi bi-bell-fill"></i>
          <?php if(count($todayAppointments) > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= count($todayAppointments) ?>
              <span class="visually-hidden">appointments</span>
            </span>
          <?php endif; ?>
        </button>
        <div id="notifDropdown" class="card shadow-lg position-absolute p-0" style="display:none; right:0; top:45px; width:350px; z-index:10000;">
          <div class="card-header bg-warning text-dark fw-bold d-flex align-items-center">
            <i class="bi bi-calendar-event me-2"></i> Today's Appointments
          </div>
          <ul class="list-group list-group-flush">
            <?php if(count($todayAppointments) === 0): ?>
              <li class="list-group-item text-muted small">No appointments today.</li>
            <?php else: ?>
              <?php foreach($todayAppointments as $a): ?>
                <li class="list-group-item small d-flex flex-column p-2">
                  <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><?= htmlspecialchars($a['customer_name']) ?></span>
                    <span class="badge bg-primary"><?= htmlspecialchars($a['appointment_date']) ?></span>
                  </div>
                  <div class="text-muted fw-light small mt-1">
                    <?= htmlspecialchars($a['appointment_reason'] ?: '-') ?> | <i class="bi bi-building"></i> <?= htmlspecialchars($a['project_name']) ?>
                  </div>
                </li>
              <?php endforeach; ?>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <h4>👋 Welcome, <?= htmlspecialchars($admin_name) ?></h4>
      <form method="GET" class="d-flex ms-auto">
        <select name="month" class="form-select" onchange="this.form.submit()">
          <?php foreach ($months as $value => $label): ?>
            <option value="<?= htmlspecialchars($value) ?>" <?= ($value === $selectedMonth) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    

    <div class="row text-center mb-4 g-3">
  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#0d6efd; color:white;">
      <i class="bi bi-telephone-fill"></i>
      <h6>Total Calls</h6>
      <h2><?= $totals['total_calls'] ?? 0 ?></h2>
    </div>
  </div>
  
  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#198754; color:white;">
      <i class="bi bi-hand-thumbs-up-fill"></i>
      <h6>Positive Calls</h6>
      <h2><?= $totals['positive'] ?? 0 ?></h2>
    </div>
  </div>
  
  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#dc3545; color:white;">
      <i class="bi bi-hand-thumbs-down-fill"></i>
      <h6>Negative Calls</h6>
      <h2><?= $totals['negative'] ?? 0 ?></h2>
    </div>
  </div>
  
  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#6610f2; color:white;">
      <i class="bi bi-building"></i>
      <h6>Total Site Visits</h6>
      <h2><?= number_format($totalVisits) ?></h2>
    </div>
  </div>

  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#ffc107; color:#212529;">
      <i class="bi bi-pause-circle-fill"></i>
      <h6>On Hold Calls</h6>
      <h2><?= number_format($onHoldTotal) ?></h2>
    </div>
  </div>

  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#0dcaf0; color:#212529;">
      <i class="bi bi-trophy-fill"></i>
      <h6>Won Calls</h6>
      <h2><?= number_format($wonTotal) ?></h2>
    </div>
  </div>

  <div class="col-md-3 col-6">
    <div class="card dashboard-card" style="background-color:#fd7e14; color:white;">
      <i class="bi bi-check2-circle"></i>
      <h6>Sold Appointments</h6>
      <h2><?= number_format($soldTotal) ?></h2>
    </div>
  </div>
</div>

    

    <!-- Monthly Chart -->
    <div class="card p-4 mb-4">
      <h5><i class="bi bi-bar-chart-line"></i> <?= htmlspecialchars($monthName) ?> Performance</h5>
      <canvas id="monthlyChart" height="100"></canvas>
    </div>

    <!-- Follow-up Chart -->
    <div class="card p-4 mb-4">
      <h5><i class="bi bi-graph-up-arrow"></i> Follow-Up Calls (Last 7 Days)</h5>
      <canvas id="followChart" height="100"></canvas>
    </div>

    <!-- Upcoming Appointments -->
    <div class="accordion mb-4" id="appointmentAccordion">
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button collapsed" data-bs-toggle="collapse" data-bs-target="#collapseAppointments"><i class="bi bi-calendar-event me-2"></i> Upcoming Appointments (Next 2 Days)</button>
        </h2>
        <div id="collapseAppointments" class="accordion-collapse collapse">
          <div class="accordion-body">
            <?php if (empty($appointments)): ?>
              <div class="alert alert-info mb-0">No upcoming appointments.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered align-middle text-center">
                  <thead><tr><th>Date</th><th>Reason</th><th>Number</th><th>Customer</th><th>Project</th><th>User</th></tr></thead>
                  <tbody>
                    <?php foreach ($appointments as $a): ?>
                      <tr>
                        <td><span class="badge bg-primary"><?= htmlspecialchars($a['appointment_date']) ?></span></td>
                        <td><?= htmlspecialchars($a['appointment_reason'] ?: '-') ?></td>
                        <td class="fw-bold text-success"><?= htmlspecialchars($a['number_called']) ?></td>
                        <td><?= htmlspecialchars($a['customer_name']) ?></td>
                        <td><?= htmlspecialchars($a['project_name']) ?></td>
                        <td><?= htmlspecialchars($a['user_name']) ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Daily Call Summary -->
    <div class="card p-4 mb-4">
      <h5>📅 Daily Call Summary</h5>
      <div class="table-responsive">
        <table class="table table-striped text-center">
          <thead><tr><th>Date</th><th>Total</th><th>Positive</th><th>Negative</th></tr></thead>
          <tbody>
            <?php if (!empty($dailyData)): foreach ($dailyData as $row): ?>
              <tr class="<?= ($row['date'] === $currentDate) ? 'current-day' : '' ?>">
                <td><?= htmlspecialchars($row['date']) ?></td>
                <td><?= htmlspecialchars($row['total_calls']) ?></td>
                <td><?= htmlspecialchars($row['positive']) ?></td>
                <td><?= htmlspecialchars($row['negative']) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="5" class="text-muted">No data for this month.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Footer -->
<div class="footer">
  &copy; <?= date('Y') ?> <?= htmlspecialchars($setting['company_name'] ?: 'Commercial Realty (Pvt) Ltd.') ?> | Developed by <a href="#">Mayura Lasantha</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>


// Display Welcome Notification on page load
window.addEventListener('load', () => {
  const notif = document.getElementById('welcomeNotif');
  notif.classList.add('show');

  // Hide after 5 seconds
  setTimeout(() => {
    notif.classList.remove('show');
  }, 5000);
});

document.getElementById('welcomeNotif').addEventListener('click', function(){
    this.classList.remove('show');
});



const notifBtn = document.getElementById('appointmentNotifBtn');
const notifDropdown = document.getElementById('notifDropdown');
notifBtn.addEventListener('click', () => {
  if(notifDropdown.style.display === 'none'){
    notifDropdown.style.display = 'block';
    notifDropdown.style.opacity = 0;
    setTimeout(()=>{ notifDropdown.style.opacity = 1; }, 10);
  } else {
    notifDropdown.style.opacity = 0;
    setTimeout(()=>{ notifDropdown.style.display = 'none'; }, 200);
  }
});
document.addEventListener('click', function(event){
  if (!notifBtn.contains(event.target) && !notifDropdown.contains(event.target)) {
    notifDropdown.style.opacity = 0;
    setTimeout(()=>{ notifDropdown.style.display = 'none'; }, 200);
  }
});

document.getElementById('toggleBtn').onclick = ()=>{
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('sidebar').classList.toggle('active'); // for mobile
};
document.querySelectorAll('.dropdown-toggle').forEach(el=>el.onclick=()=>el.parentElement.classList.toggle('show'));

function updateDateTime(){
  const now=new Date();
  document.getElementById('currentDateTime').textContent=now.toLocaleString('en-US',{weekday:'short',year:'numeric',month:'short',day:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'});
}
setInterval(updateDateTime,1000);updateDateTime();

const chartData=<?= json_encode($dailyData) ?>;
if(chartData.length>0){
  new Chart(document.getElementById('monthlyChart'),{
    type:'line',
    data:{labels:chartData.map(r=>r.date),datasets:[
    {label:'Total', data:chartData.map(r=>r.total_calls), borderColor:'#0d6efd', fill:false},
    {label:'Positive', data:chartData.map(r=>r.positive), borderColor:'#198754', fill:false},
    {label:'Negative', data:chartData.map(r=>r.negative), borderColor:'#dc3545', fill:false},
    {label:'On Hold', data:chartData.map(r=>r.on_hold), borderColor:'#fd7e14', fill:false},
    {label:'Won', data:chartData.map(r=>r.won), borderColor:'#000dffff', fill:false}
]},
    options:{plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}
  });
}
const followData=<?= json_encode($followData) ?>;
if(followData.length>0){
  new Chart(document.getElementById('followChart'),{
    type:'bar',
    data:{labels:followData.map(u=>u.user_name),datasets:[{label:'Follow-Up Calls',data:followData.map(u=>u.total),backgroundColor:'rgba(13,110,253,0.7)',borderRadius:8}]},
    options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true},x:{grid:{display:false}}}}
  });
}
</script>
</body>
</html>
