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

// ===== Fetch calls grouped by date and status =====
$timeframe = $_GET['timeframe'] ?? '7days';
$labels = [];
$positiveData = [];
$negativeData = [];
$onHoldData = [];
$wonData = [];

$totalPositive = 0;
$totalNegative = 0;
$totalOnHold = 0;
$totalWon = 0;

// For tables
$dateTotals = [];    // array of ['label' => 'Nov 20', 'total' => 12]
$monthlyTotals = []; // array of ['month' => 1, 'label' => 'January', 'total' => 230]

// ===== Build main dataset (sentiment grouped) =====
switch($timeframe) {
    case 'month':
        $start = date('Y-m-01');
        $stmt = $conn->prepare("
            SELECT DATE(called_at) AS dt,
                SUM(sentiment='Positive') AS positive,
                SUM(sentiment='Negative') AS negative,
                SUM(sentiment='On Hold') AS onhold,
                SUM(sentiment='Won') AS won
            FROM call_log
            WHERE user_id=? AND DATE(called_at) >= ?
            GROUP BY DATE(called_at)
            ORDER BY DATE(called_at)
        ");
        $stmt->bind_param("is", $user_id, $start);
        break;

    case 'year':
        $year = date('Y');
        $stmt = $conn->prepare("
            SELECT MONTH(called_at) AS month,
                SUM(sentiment='Positive') AS positive,
                SUM(sentiment='Negative') AS negative,
                SUM(sentiment='On Hold') AS onhold,
                SUM(sentiment='Won') AS won
            FROM call_log
            WHERE user_id=? AND YEAR(called_at)=?
            GROUP BY MONTH(called_at)
            ORDER BY MONTH(called_at)
        ");
        $stmt->bind_param("ii", $user_id, $year);
        break;

    default:
        // last 7 days (including today)
        $start = date('Y-m-d', strtotime('-6 days'));
        $stmt = $conn->prepare("
            SELECT DATE(called_at) AS dt,
                SUM(sentiment='Positive') AS positive,
                SUM(sentiment='Negative') AS negative,
                SUM(sentiment='On Hold') AS onhold,
                SUM(sentiment='Won') AS won
            FROM call_log
            WHERE user_id=? AND DATE(called_at) >= ?
            GROUP BY DATE(called_at)
            ORDER BY DATE(called_at)
        ");
        $stmt->bind_param("is", $user_id, $start);
}

$stmt->execute();
$result = $stmt->get_result();

while($row = $result->fetch_assoc()){
    if($timeframe=='year'){
        $labels[] = date('F', mktime(0,0,0,$row['month'],1));
        // For dateTotals table in 'year' mode, we will show month totals later (below)
        $positive = (int)($row['positive'] ?? 0);
        $negative = (int)($row['negative'] ?? 0);
        $onhold  = (int)($row['onhold'] ?? 0);
        $won     = (int)($row['won'] ?? 0);

        $positiveData[] = $positive;
        $negativeData[] = $negative;
        $onHoldData[]   = $onhold;
        $wonData[]      = $won;

        $totalPositive += $positive;
        $totalNegative += $negative;
        $totalOnHold   += $onhold;
        $totalWon      += $won;

        $dateTotals[] = [
            'label' => date('F', mktime(0,0,0,$row['month'],1)),
            'total' => $positive + $negative + $onhold + $won
        ];
    } else {
        $labels[] = date('M d', strtotime($row['dt']));

        $positive = (int)($row['positive'] ?? 0);
        $negative = (int)($row['negative'] ?? 0);
        $onhold  = (int)($row['onhold'] ?? 0);
        $won     = (int)($row['won'] ?? 0);

        $positiveData[] = $positive;
        $negativeData[] = $negative;
        $onHoldData[]   = $onhold;
        $wonData[]      = $won;

        $totalPositive += $positive;
        $totalNegative += $negative;
        $totalOnHold   += $onhold;
        $totalWon      += $won;

        $dateTotals[] = [
            'label' => date('Y-m-d', strtotime($row['dt'])),
            'total' => $positive + $negative + $onhold + $won
        ];
    }
}

$totalCalls = $totalPositive + $totalNegative + $totalOnHold + $totalWon;

// ===== Build monthlyTotals for the current year (always show) =====
$year = date('Y');
$mstmt = $conn->prepare("
    SELECT MONTH(called_at) AS month, COUNT(*) AS total
    FROM call_log
    WHERE user_id=? AND YEAR(called_at)=?
    GROUP BY MONTH(called_at)
    ORDER BY MONTH(called_at)
");
$mstmt->bind_param("ii", $user_id, $year);
$mstmt->execute();
$mres = $mstmt->get_result();
$monthIndex = [];
while($mrow = $mres->fetch_assoc()){
    $m = (int)$mrow['month'];
    $t = (int)$mrow['total'];
    $monthlyTotals[] = [
        'month' => $m,
        'label' => date('F', mktime(0,0,0,$m,1)),
        'total' => $t
    ];
    $monthIndex[$m] = $t;
}
// Also ensure months with zero calls in year show 0 — helpful for table completeness
for($m=1;$m<=12;$m++){
    if(!isset($monthIndex[$m])){
        $monthlyTotals[] = [
            'month' => $m,
            'label' => date('F', mktime(0,0,0,$m,1)),
            'total' => 0
        ];
    }
}
// sort monthlyTotals by month ascending
usort($monthlyTotals, function($a,$b){ return $a['month'] - $b['month']; });

// ===== Additional quick daily totals for summary (Today / Yesterday / Last 7 days total) =====
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$summaryStmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN DATE(called_at) = ? THEN 1 ELSE 0 END) AS today_count,
        SUM(CASE WHEN DATE(called_at) = ? THEN 1 ELSE 0 END) AS yesterday_count,
        SUM(CASE WHEN DATE(called_at) >= ? THEN 1 ELSE 0 END) AS last7_count
    FROM call_log
    WHERE user_id=?
");
$sevenStart = date('Y-m-d', strtotime('-6 days'));
$summaryStmt->bind_param("sssi", $today, $yesterday, $sevenStart, $user_id);
$summaryStmt->execute();
$summaryRes = $summaryStmt->get_result();
$summary = $summaryRes->fetch_assoc();
$todayCount = (int)($summary['today_count'] ?? 0);
$yesterdayCount = (int)($summary['yesterday_count'] ?? 0);
$last7Count = (int)($summary['last7_count'] ?? 0);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>User Call Reports - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body {
    font-family: 'Poppins', sans-serif;
    background:#f4f6fa;
    margin:0;
}
/* Sidebar */
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:220px;
    height:100vh;
    background: linear-gradient(180deg,#00205C);
    color:#fff;
    overflow-y:auto;
    padding-top:20px;
    text-align:center;
    transition: transform 0.3s;
}
.sidebar .sidebar-header {
    margin-bottom:15px;
}
.sidebar .sidebar-header img.logo {
    width:180px;
    margin-bottom:5px;
    border-radius:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color: rgba(255,255,255,0.8);
    margin:0 0 10px;
    text-align:center;
}
.sidebar a, .sidebar button {
    display:block;
    color:#fff;
    text-decoration:none;
    padding:8px 15px;
    font-size:14px;
    border:none;
    background:none;
    width:100%;
    text-align:left;
    transition:0.3s;
    cursor:pointer;
}
.sidebar a:hover, .sidebar a.active, .sidebar button:hover {
    background: rgba(255,255,255,0.2);
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
    color:#ffc107;
}
/* Main Content */
.content {
    margin-left:220px;
    padding:25px;
    transition: margin-left 0.3s;
}
.card {
    border:none;
    border-radius:12px;
    box-shadow:0 2px 10px rgba(0,0,0,0.08);
    padding:20px;
    margin-bottom:25px;
    background:#fff;
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
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
}
.filter-bar select {
    max-width:200px;
}
.badges {
    display:flex;
    gap:10px;
    margin-bottom:15px;
    flex-wrap:wrap;
}
.badge {
    font-size:0.9rem;
    padding:0.5em 0.8em;
    border-radius:8px;
}
.badge-success {
    background:#28a745;
    color:#fff;
}
.badge-danger {
    background:#dc3545;
    color:#fff;
}
.badge-primary {
    background:#0167ff;
    color:#fff;
}
.badge-warning {
    background:#ffc107;
    color:#000;
}
.badge-info {
    background:#17a2b8;
    color:#fff;
}

/* Footer */
.footer {
    background:#00205C;
    color:#fff;
    text-align:center;
    font-size:10px;
    padding:4px;
    position:fixed;
    bottom:0;
    left:220px;
    right:0;
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
@keyframes slideFadeIn {
    from { opacity:0; transform:translateY(20px); }
    to { opacity:1; transform:translateY(0); }
}

/* Tables */
.table-summary thead th {
    background: #f1f5f9;
}

/* Mobile */
@media(max-width:992px){ 
    .sidebar{ transform: translateX(-100%); position:fixed; z-index:1050; }
    .sidebar.active { transform: translateX(0); }
    .content{ margin-left:0; padding:15px; }
    .footer{ left:0; }
    .mobile-toggle { display:flex; justify-content:space-between; align-items:center; background:#00205C; color:white; padding:10px 15px; font-weight:600; }
}
</style>
</head>
<body>

<!-- Mobile Toggle -->
<div class="mobile-toggle d-lg-none">
    <span>Call Reports</span>
    <i class="bi bi-list" id="sidebarToggle" style="font-size:1.5rem; cursor:pointer;"></i>
</div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd, Maharagama 10280</p>
        <p>👤 <?= htmlspecialchars($user_name); ?></p>
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

    <a href="view_user_appointments.php"><i class="bi bi-clock-history"></i> Scheduled Visits</a>
    <a href="viewallcalllist.php"><i class="bi bi-journal-text"></i> View All Call List</a>
    <a href="appointmentlist.php"><i class="bi bi-calendar-check"></i> Site Visits</a>
    <a href="userreports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>
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
    <h4 class="page-title mb-3"><i class="bi bi-bar-chart-line"></i> Call Reports</h4>
    <p class="user-guide">
        Generate performance reports, call summaries, and analytics for tracking team efficiency.
    </p>

    <div class="filter-bar mb-3">
        <select onchange="location=this.value;" class="form-select form-select-sm">
            <option value="userreports.php?timeframe=7days" <?= $timeframe=='7days'?'selected':''; ?>>Last 7 Days</option>
            <option value="userreports.php?timeframe=month" <?= $timeframe=='month'?'selected':''; ?>>This Month</option>
            <option value="userreports.php?timeframe=year" <?= $timeframe=='year'?'selected':''; ?>>This Year</option>
        </select>
    </div>

    <div class="badges mb-3">
        <div class="badge badge-primary">Total Calls: <?= $totalCalls ?></div>
        <div class="badge badge-success">Positive: <?= $totalPositive ?></div>
        <div class="badge badge-danger">Negative: <?= $totalNegative ?></div>
        <div class="badge badge-warning">On Hold: <?= $totalOnHold ?></div>
        <div class="badge badge-info">Won: <?= $totalWon ?></div>
    </div>

    <!-- QUICK DAILY SUMMARY (small row above tables) -->
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="small text-muted">Today</div>
                <h5 class="mt-1"><?= $todayCount ?></h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="small text-muted">Yesterday</div>
                <h5 class="mt-1"><?= $yesterdayCount ?></h5>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="small text-muted">Last 7 days total</div>
                <h5 class="mt-1"><?= $last7Count ?></h5>
            </div>
        </div>
    </div>

    <!-- DATE-WISE TOTALS TABLE (depends on timeframe) -->
    <div class="card mb-3">
        <div class="card-body">
            <h5 class="card-title">Date-wise Totals (<?= $timeframe == 'year' ? 'Month wise for this Year' : 'Date wise' ?>)</h5>
            <div class="table-responsive">
                <table class="table table-summary table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= $timeframe == 'year' ? 'Month' : 'Date' ?></th>
                            <th>Total Calls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($dateTotals) === 0): ?>
                            <tr><td colspan="3" class="text-center">No calls found for the selected timeframe.</td></tr>
                        <?php else: ?>
                            <?php $i=1; foreach($dateTotals as $d): ?>
                                <tr>
                                    <td><?= $i++; ?></td>
                                    <td><?= htmlspecialchars($d['label']); ?></td>
                                    <td><?= (int)$d['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MONTHLY TOTALS TABLE (this year) -->
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Monthly Totals (<?= $year ?>)</h5>
            <div class="table-responsive">
                <table class="table table-summary table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Month</th>
                            <th>Total Calls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $j=1; foreach($monthlyTotals as $m): ?>
                            <tr>
                                <td><?= $j++; ?></td>
                                <td><?= htmlspecialchars($m['label']); ?></td>
                                <td><?= (int)$m['total']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="card">
        <canvas id="callChart" height="120"></canvas>
    </div>

</div>

<div class="footer">
  &copy; <?= date('Y'); ?> Commercial Realty (Pvt) Ltd | Developed by <span class="text-warning">Mayura Lasantha</span>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ctx = document.getElementById('callChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels); ?>,
        datasets: [
            { label:'Positive Calls', data: <?= json_encode($positiveData); ?>, backgroundColor:'rgba(40,167,69,0.7)' },
            { label:'Negative Calls', data: <?= json_encode($negativeData); ?>, backgroundColor:'rgba(220,53,69,0.7)' },
            { label:'On Hold', data: <?= json_encode($onHoldData); ?>, backgroundColor:'rgba(255,193,7,0.7)' },
            { label:'Won', data: <?= json_encode($wonData); ?>, backgroundColor:'rgba(0,123,255,0.7)' }
        ]
    },
    options:{
        responsive:true,
        plugins:{ legend:{ position:'top' } },
        scales:{ y:{ beginAtZero:true } }
    }
});

// Sidebar dropdown toggle
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click", function(){
        this.classList.toggle("active");
        const dropdown = this.nextElementSibling;
        dropdown.style.display = (dropdown.style.display==='block') ? 'none' : 'block';
    });
});

// Mobile sidebar toggle
document.getElementById('sidebarToggle').addEventListener('click', function(){
    document.getElementById('sidebar').classList.toggle('active');
});
</script>
</body>
</html>
