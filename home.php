<?php
session_start();
include 'db.php';

// ===== LOAD SYSTEM THEME =====
if (!isset($_SESSION['theme_mode'])) {
    $tableCheck = $conn->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $themeResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name='theme_mode'");
        $_SESSION['theme_mode'] = ($themeResult && $themeResult->num_rows > 0) ? $themeResult->fetch_assoc()['setting_value'] : 'light';
    } else {
        $_SESSION['theme_mode'] = 'light';
    }
}
$currentTheme = $_SESSION['theme_mode'];

// ===== CHECK LOGIN =====
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';
if (!$user_id) {
    header("Location: login.php");
    exit();
}

// ===== COMPANY PROFILE =====
$settingResult = $conn->query("SELECT * FROM company_profile LIMIT 1");
$setting = ($settingResult && $settingResult->num_rows > 0) ? $settingResult->fetch_assoc() : ['company_name' => 'Commercial Realty (Pvt) Ltd.'];

// ===== TODAY’S CALL SUMMARY =====
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT sentiment, COUNT(*) AS count FROM call_log WHERE user_id = ? AND DATE(called_at)=? GROUP BY sentiment");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$res = $stmt->get_result();

// initialize counts
$sentiments = [
    'Positive' => 0,
    'Negative' => 0,
    'On Hold'  => 0,
    'Won'      => 0
];

while ($r = $res->fetch_assoc()) {
    $key = trim($r['sentiment']);
    // normalize possible variations
    $lower = strtolower($key);
    if ($lower === 'positive') $sentiments['Positive'] = (int)$r['count'];
    elseif ($lower === 'negative') $sentiments['Negative'] = (int)$r['count'];
    elseif ($lower === 'on hold' || $lower === 'onhold' || $lower === 'on_hold') $sentiments['On Hold'] = (int)$r['count'];
    elseif ($lower === 'won') $sentiments['Won'] = (int)$r['count'];
    else {
        // if unknown, try to infer (safeguard)
        if (stripos($key,'hold') !== false) $sentiments['On Hold'] += (int)$r['count'];
        elseif (stripos($key,'win') !== false) $sentiments['Won'] += (int)$r['count'];
        elseif (stripos($key,'pos') !== false) $sentiments['Positive'] += (int)$r['count'];
        elseif (stripos($key,'neg') !== false) $sentiments['Negative'] += (int)$r['count'];
    }
}
$total_calls = array_sum($sentiments);

// ===== TOTAL BONUS =====
$bonusStmt = $conn->prepare("SELECT SUM(bonus_amount) AS total_bonus FROM call_log WHERE user_id=?");
$bonusStmt->bind_param("i", $user_id);
$bonusStmt->execute();
$total_bonus = $bonusStmt->get_result()->fetch_assoc()['total_bonus'] ?? 0;

// ===== TODAY’S CALLS =====
$stmt2 = $conn->prepare("SELECT * FROM call_log WHERE user_id=? AND DATE(called_at)=? ORDER BY called_at DESC");
$stmt2->bind_param("is", $user_id, $today);
$stmt2->execute();
$today_calls = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== UPCOMING APPOINTMENTS =====
$stmt3 = $conn->prepare("SELECT * FROM call_log WHERE user_id=? AND appointment_date IS NOT NULL ORDER BY appointment_date ASC");
$stmt3->bind_param("i", $user_id);
$stmt3->execute();
$appointments = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

// ===== APPOINTMENTS NOTIFICATION =====
$todayAppointments = array_filter($appointments, function($a) use ($today) {
    return $a['appointment_date'] === $today;
});
$totalTodayAppointments = count($todayAppointments);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($currentTheme) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard | <?= htmlspecialchars($setting['company_name']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body {
    font-family:'Poppins',sans-serif;
    background:var(--bg-body);
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
    transition: transform 0.3s ease;
    z-index:1050;
}
.sidebar.collapsed {
    transform: translateX(-100%);
}
.sidebar .sidebar-header img.logo {
    width: 180px;
    border-radius: 5px;
    margin-bottom:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color: rgba(230,230,230,0.9);
    margin:0 0 10px;
    text-align:center;
}
.sidebar a, .sidebar button {
    display:block;
    color: var(--sidebar-color);
    text-decoration:none;
    padding:10px 15px;
    font-size:14px;
    border:none;
    background:none;
    width:100%;
    text-align:left;
    transition:0.3s;
}
.sidebar a:hover, .sidebar button:hover, .sidebar a.active {
    background: var(--sidebar-hover);
    border-left:4px solid #ffc107;
}
.sidebar .dropdown-container {
    display:none;
    background: rgba(0,0,0,0.15);
}
.sidebar .dropdown-container a {
    padding-left:35px;
    font-size:13px;
}
.logout-btn {
    position:absolute;
    bottom:20px;
    width:100%;
}

/* Mobile Navbar */
.navbar-mobile {
    display:none;
    background:#00205C;
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

/* Content */
.content {
    margin-left:220px;
    padding:30px;
    min-height:100vh;
    transition:margin-left 0.3s;
}
.card {
    border:none;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
}
.section-title {
    color:#FD7E14;
    font-weight:700;
}

/* Today's Calls Table Hover */
.table-hover tbody tr:hover {
    background-color: rgba(0,123,255,0.05);
    transform: scale(1.01);
    transition: all 0.15s ease-in-out;
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

/* Sticky Table Header */
.table thead th {
    position: sticky;
    top: 0;
    background:#343a40;
    color:white;
    z-index:5;
}

/* Highlight Today’s Appointments */
.badge-today {
    background: #ffc107;
    color:#000;
}

/* Sentiment badges */
.badge-positive { background:#28a745; color:#fff; }
.badge-negative { background:#dc3545; color:#fff; }
.badge-onhold  { background:#fd7e14; color:#fff; } /* orange */
.badge-won     { background:#0d6efd; color:#fff; } /* blue */

/* Responsive */
@media(max-width:992px){
    .sidebar { transform: translateX(-100%); position: fixed; top:0; left:0; height:100%; width:220px; z-index:1050; }
    .sidebar.active { transform: translateX(0); }
    .content { margin-left:0; padding:20px; }
    .footer { left:0; }
    .navbar-mobile { display:flex; }
}
</style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Dashboard</span>
</div>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name) ?></p>
    </div>
    <a href="home.php" class="active"><i class="bi bi-house-door"></i> Dashboard</a>
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

    <a href="view_user_appointments.php"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="appointmentlist.php"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>
    <button class="dropdown-btn"><i class="bi bi-gear"></i> Settings <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="manage_reasons.php"><i class="bi bi-person-gear"></i> Manage System Settings</a>
    </div>
    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- Main Content -->
<div class="content">
    <h4 class="section-title mb-4"><i class="bi bi-speedometer"></i> Dashboard Overview</h4>

    <!-- APPOINTMENT NOTIFICATION -->
    <?php if($totalTodayAppointments > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <strong>⚠ You have <?= $totalTodayAppointments ?> appointment(s) today!</strong>
            <ul class="mb-0">
                <?php foreach($todayAppointments as $a): ?>
                    <li><?= htmlspecialchars($a['customer_name']) ?> - <?= htmlspecialchars($a['appointment_reason']) ?> at <strong><?= htmlspecialchars($a['appointment_date']) ?></strong></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Bonus Card -->
    <div class="card p-4 mb-4 text-center" style="background: linear-gradient(135deg,#0078d7,#00a3e0); color:white;">
        <h4>Total Income Earned</h4>
        <h2 class="fw-bold mt-2">Rs. <?= number_format($total_bonus, 2) ?></h2>
        <p class="mt-2">🎯 <?= $total_bonus >= 5000 ? "Driven by Results — Earned with Excellence" : "Keep going — every visit adds to your success!" ?></p>
        <a href="appointmentlist.php" class="btn btn-warning btn-sm mt-2 fw-semibold"><i class="bi bi-eye"></i> View All Appointments</a>
    </div>

    <div class="row g-4 mb-4">
        <!-- Sentiment Chart -->
        <div class="col-md-6">
            <div class="card p-3 text-center">
                <h5>Today's Call Sentiment</h5>
                <div style="max-width:300px; margin:0 auto;">
                    <canvas id="sentimentChart" style="height:260px;"></canvas>
                </div>
                <p class="mt-3 text-muted fw-semibold">Total Calls: <?= $total_calls ?></p>
            </div>
        </div>

        <!-- Sentiment Totals (4 cards) -->
        <div class="col-md-6">
            <div class="card p-3">
                <h5>Sentiment Totals</h5>
                <div class="row text-center">
                    <div class="col-6 col-md-6 mb-2">
                        <div class="p-3 rounded" style="background:#e9f7ee;">
                            <h4 class="mb-0 text-success"><?= $sentiments['Positive'] ?></h4>
                            <small class="d-block">Positive</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 mb-2">
                        <div class="p-3 rounded" style="background:#fdecea;">
                            <h4 class="mb-0 text-danger"><?= $sentiments['Negative'] ?></h4>
                            <small class="d-block">Negative</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 mb-2">
                        <div class="p-3 rounded" style="background:#fff4e6;">
                            <h4 class="mb-0 text-warning"><?= $sentiments['On Hold'] ?></h4>
                            <small class="d-block">On Hold</small>
                        </div>
                    </div>
                    <div class="col-6 col-md-6 mb-2">
                        <div class="p-3 rounded" style="background:#e7f1ff;">
                            <h4 class="mb-0 text-primary"><?= $sentiments['Won'] ?></h4>
                            <small class="d-block">Won</small>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <hr>
                <h6 class="mt-3">Upcoming Appointments</h6>
                <?php if(empty($appointments)): ?>
                    <div class="alert alert-info text-center m-2">No upcoming appointments for you.</div>
                <?php else: ?>
                    <ul class="list-group" id="appointmentsList">
                        <?php foreach($appointments as $i => $a): ?>
                            <?php $isToday = ($a['appointment_date']==$today); ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center appointment-item <?= $i >= 5 ? 'd-none' : '' ?>">
                                <div>
                                    <strong><?= htmlspecialchars($a['customer_name']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($a['appointment_reason']) ?></small>
                                </div>
                                <span class="badge rounded-pill <?= $isToday ? 'badge-today' : 'bg-primary' ?>">
                                    <?= date('d M, Y', strtotime($a['appointment_date'])) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if(count($appointments) > 5): ?>
                        <div class="text-center mt-2">
                            <button id="toggleAppointments" class="btn btn-link text-warning fw-semibold">See more...</button>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today's Calls Table -->
    <div class="card p-3 mb-5">
        <h5>Today's Calls</h5>
        <?php if(empty($today_calls)): ?>
            <div class="alert alert-info text-center m-2">No calls logged today.</div>
        <?php else: ?>
        <div class="table-responsive" style="max-height:400px; overflow-y:auto;">
            <table class="table table-hover table-bordered align-middle text-center mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Number</th>
                        <th>Name</th>
                        <th>Project</th>
                        <th>Reason</th>
                        <th>Notes</th>
                        <th>Appointment</th>
                        <th>Time</th>
                        <th>Sentiment</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $c=1; foreach($today_calls as $call): 
                        $sent = trim($call['sentiment'] ?? '');
                        $low = strtolower($sent);
                        if ($low === 'positive') {
                            $badgeClass = 'badge-positive';
                        } elseif ($low === 'negative') {
                            $badgeClass = 'badge-negative';
                        } elseif ($low === 'on hold' || $low==='onhold' || $low==='on_hold') {
                            $badgeClass = 'badge-onhold';
                        } elseif ($low === 'won') {
                            $badgeClass = 'badge-won';
                        } else {
                            $badgeClass = 'badge-secondary';
                        }
                    ?>
                    <tr class="<?= $low==='negative' ? 'table-danger' : '' ?>">
                        <td><?= $c++ ?></td>
                        <td><?= htmlspecialchars($call['number_called']) ?></td>
                        <td><?= htmlspecialchars($call['customer_name']) ?></td>
                        <td><?= htmlspecialchars($call['project_name']) ?></td>
                        <td><?= htmlspecialchars($call['reason']) ?></td>
                        <td><?= nl2br(htmlspecialchars($call['notes'])) ?></td>
                        <td><?= htmlspecialchars($call['appointment_date']) ?: '-' ?></td>
                        <td><?= date('h:i A', strtotime($call['called_at'])) ?></td>
                        <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($call['sentiment'] ?: '-') ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="footer">
  &copy; <?= date('Y'); ?> Commercial Realty (Pvt) Ltd | Developed by <span class="text-warning">Mayura Lasantha</span>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar Dropdowns
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click", ()=>{
        const drop = btn.nextElementSibling;
        document.querySelectorAll(".dropdown-container").forEach(dc=>{ if(dc!==drop) dc.style.display='none'; });
        drop.style.display = drop.style.display==='block' ? 'none' : 'block';
    });
});

// Mobile Sidebar Toggle
document.getElementById('mobileSidebarToggle').addEventListener('click', ()=>{
    document.querySelector('.sidebar').classList.toggle('active');
});

// Sentiment Chart
const ctx = document.getElementById('sentimentChart');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: ['Positive','Negative','On Hold','Won'],
        datasets: [{
            data: [<?= (int)$sentiments['Positive'] ?>, <?= (int)$sentiments['Negative'] ?>, <?= (int)$sentiments['On Hold'] ?>, <?= (int)$sentiments['Won'] ?>],
            backgroundColor: ['#28a745','#dc3545','#fd7e14','#0d6efd'],
            borderColor: '#fff',
            borderWidth: 2
        }]
    },
    options: {
        responsive:true,
        maintainAspectRatio:false,
        plugins:{ legend:{ position:'bottom' } }
    }
});

// Upcoming Appointments Toggle
document.addEventListener('DOMContentLoaded', function(){
    const toggleBtn = document.getElementById('toggleAppointments');
    if(toggleBtn){
        toggleBtn.addEventListener('click', function(){
            const hiddenItems = document.querySelectorAll('.appointment-item.d-none');
            if(hiddenItems.length){
                hiddenItems.forEach(item => item.classList.remove('d-none'));
                this.textContent = 'See less...';
            } else {
                const items = document.querySelectorAll('.appointment-item');
                items.forEach((item, index) => { if(index >= 5) item.classList.add('d-none'); });
                this.textContent = 'See more...';
            }
        });
    }
});
</script>
</body>
</html>
