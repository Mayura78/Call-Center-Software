<?php
session_start();
include 'db.php';

// ===== Check admin login =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB check
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
}

// ===== Sentiment filter =====
$filterSentiment = $_GET['sentiment'] ?? 'All';
$filterSentiment = trim($filterSentiment);

// protect against injection for simple insertion into query
$sentFilterSQL = "";
if ($filterSentiment !== 'All') {
    $allowed = ['Positive','Negative','On Hold','Won'];
    if (!in_array($filterSentiment, $allowed)) {
        $filterSentiment = 'All';
    } else {
        $sentFilterSQL = " AND sentiment = '" . $conn->real_escape_string($filterSentiment) . "' ";
    }
}

// ===== Agent-wise data =====
$agentsRes = $conn->query("SELECT id, user_name FROM manageuser ORDER BY user_name ASC");
$agentsData = [];
$allAgentsLast7 = [];
$labels7days = [];
for ($i=6; $i>=0; $i--) $labels7days[] = date('Y-m-d', strtotime("-$i day"));

// prepare month labels (Jan..Dec)
$monthLabels = [];
for ($m=1; $m<=12; $m++){
    $monthLabels[] = date('M', mktime(0,0,0,$m,1));
}

// current year
$year = date('Y');

// ===== Prepare daily call summary per agent =====
$dailySummary = []; // [agentName][date] => ['total'=>x,'Positive'=>x,'Negative'=>x,'On Hold'=>x,'Won'=>x]

while ($agent = $agentsRes->fetch_assoc()) {
    $agentId = (int)$agent['id'];
    $agentName = $agent['user_name'];

    // Total counts
    $totalRes = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='{$agentId}'");
    $total = $totalRes->fetch_assoc()['total'] ?? 0;

    $positive = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='{$agentId}' AND sentiment='Positive'")->fetch_assoc()['total'] ?? 0;
    $negative = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='{$agentId}' AND sentiment='Negative'")->fetch_assoc()['total'] ?? 0;
    $onhold = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='{$agentId}' AND sentiment='On Hold'")->fetch_assoc()['total'] ?? 0;
    $won = $conn->query("SELECT COUNT(*) AS total FROM call_log WHERE user_id='{$agentId}' AND sentiment='Won'")->fetch_assoc()['total'] ?? 0;

    // Last 7 days counts
    $days = [
        'Positive'=>array_fill_keys($labels7days,0),
        'Negative'=>array_fill_keys($labels7days,0),
        'OnHold'=>array_fill_keys($labels7days,0),
        'Won'=>array_fill_keys($labels7days,0)
    ];

    $q = "
        SELECT DATE(called_at) AS day, sentiment, COUNT(*) AS total
        FROM call_log
        WHERE user_id='{$agentId}' AND called_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(called_at), sentiment
    ";
    $stmt = $conn->query($q);
    while($row = $stmt->fetch_assoc()){
        $day = $row['day'];
        $count = (int)$row['total'];
        switch($row['sentiment']){
            case 'Positive': $days['Positive'][$day]=$count; break;
            case 'Negative': $days['Negative'][$day]=$count; break;
            case 'On Hold': $days['OnHold'][$day]=$count; break;
            case 'Won': $days['Won'][$day]=$count; break;
        }
    }

    // Daily summary (all dates from last 7 days)
    foreach($labels7days as $d){
        $dailySummary[$agentName][$d] = [
            'total' => ($days['Positive'][$d]??0)+($days['Negative'][$d]??0)+($days['OnHold'][$d]??0)+($days['Won'][$d]??0),
            'Positive'=> $days['Positive'][$d] ??0,
            'Negative'=> $days['Negative'][$d] ??0,
            'On Hold'=> $days['OnHold'][$d] ??0,
            'Won'=> $days['Won'][$d] ??0
        ];
    }

    // Monthly counts
    $months = array_fill(1,12,0);
    $monthQuery = "
        SELECT MONTH(called_at) AS m, COUNT(*) AS total
        FROM call_log
        WHERE user_id='{$agentId}' AND YEAR(called_at) = {$year} {$sentFilterSQL}
        GROUP BY MONTH(called_at)
    ";
    $mres = $conn->query($monthQuery);
    while($mr = $mres->fetch_assoc()){
        $mi = (int)$mr['m'];
        $months[$mi] = (int)$mr['total'];
    }
    $monthsArr = [];
    for ($mi=1; $mi<=12; $mi++) $monthsArr[] = $months[$mi];

    $agentsData[] = [
        'id'=>$agentId,
        'name'=>$agentName,
        'total'=>$total,
        'positive'=>$positive,
        'negative'=>$negative,
        'onhold'=>$onhold,
        'won'=>$won,
        'last7days'=>$days,
        'months'=>$monthsArr
    ];

    $allAgentsLast7[$agentName] = $days;
}

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 Agent Performance</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
/* ===== Keep your existing styles here ===== */
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

.main-content {margin-left:240px;padding:20px;transition:.3s;}
.toggle-btn {position:fixed;left:10px;top:12px;font-size:1.8rem;cursor:pointer;color:#0b1b58;z-index:1100;}
.card {border-radius:15px;box-shadow:0 8px 25px rgba(0,0,0,.08);padding:20px;margin-bottom:20px;background:white;}
.table th, .table td {vertical-align:middle;text-align:center;font-size:.85rem;}
.chart-container {height:300px;}
.user-guide {background:#fff8e1;border-left:4px solid #ffc107;padding:12px 15px;border-radius:6px;font-size:14px;color:#444;margin-bottom:15px;animation:fadeIn 1s ease-in;}
@keyframes fadeIn { from { opacity:0; transform:translateY(10px);} to { opacity:1; transform:translateY(0);} }
@media(max-width:991px){ .sidebar{transform:translateX(-100%);} .sidebar.show{transform:translateX(0); box-shadow:2px 0 10px rgba(0,0,0,.3);} .main-content{margin-left:0; padding:15px;} .toggle-btn{display:block;} .card,table{font-size:.9rem;} .chart-container{height:220px;} .table-responsive{overflow-x:auto;} }
</style>
</head>
<body>

<!-- ===== Sidebar (keep unchanged) ===== -->
<div class="sidebar" id="sidebar">
<img src="CRealty.png" class="logo" alt="CR Logo">
<div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280<br>📞 0114 389 900</div>
<div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>
<!-- ... Keep sidebar links ... -->
<!-- ===== Sidebar ===== -->
<div class="sidebar" id="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($admin_name) ?></div>

    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>

    <button class="dropdown-btn active"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="managehotlines.php">Manage Hotlines</a>
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
    
    <a href="reports.php" class="active"><i class="bi bi-bar-chart"></i> Reports</a>

    <button class="dropdown-btn"><i class="bi bi-gear"></i> Setting <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="profilesetting.php">Profile Setting</a>
    </div>

    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>
</div>
<i class="bi bi-list toggle-btn" id="toggleSidebar"></i>

<div class="main-content">
<h3 class="text-primary mb-3"><i class="bi bi-graph-up"></i> Agent Performance Dashboard (<?= htmlspecialchars($filterSentiment) ?>)</h3>
<p class="user-guide">Generate analytics and summaries of calls, agent performance, and project activities. Monthly counts show current year (<?= $year ?>) and respect the sentiment filter selected above.</p>

<div class="mb-3">
<a href="?sentiment=All" class="btn btn-outline-primary <?= $filterSentiment==='All'?'active':'' ?>">All</a>
<a href="?sentiment=Positive" class="btn btn-outline-success <?= $filterSentiment==='Positive'?'active':'' ?>">Positive</a>
<a href="?sentiment=Negative" class="btn btn-outline-danger <?= $filterSentiment==='Negative'?'active':'' ?>">Negative</a>
<a href="?sentiment=On Hold" class="btn btn-outline-warning <?= $filterSentiment==='On Hold'?'active':'' ?>">On Hold</a>
<a href="?sentiment=Won" class="btn btn-outline-success <?= $filterSentiment==='Won'?'active':'' ?>">Won</a>
</div>

<!-- ===== Daily Call Summary Table ===== -->
<div class="card mb-4">
<h5 class="mb-3 text-center">📅 Daily Call Summary (Last 7 Days)</h5>
<div class="table-responsive">
<table class="table table-bordered table-hover table-striped">
<thead class="table-primary text-center">
<tr>
<th>Agent</th>
<?php foreach($labels7days as $d): ?>
<th><?= $d ?></th>
<?php endforeach; ?>
</tr>
</thead>
<tbody>
<?php foreach($dailySummary as $agentName => $dates): ?>
<tr>
<td><?= htmlspecialchars($agentName) ?></td>
<?php foreach($labels7days as $d): ?>
<td>
T: <?= $dates[$d]['total'] ?><br>
+<?= $dates[$d]['Positive'] ?> / -<?= $dates[$d]['Negative'] ?> / O: <?= $dates[$d]['On Hold'] ?> / W: <?= $dates[$d]['Won'] ?>
</td>
<?php endforeach; ?>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- ===== 7-day combined chart ===== -->
<div class="card mb-4">
<h5 class="text-center mb-3">📈 Last 7 Days Comparison (<?= htmlspecialchars($filterSentiment) ?>)</h5>
<div class="chart-container"><canvas id="combinedChart"></canvas></div>
</div>

<!-- ===== Monthly Table ===== -->
<div class="card mb-4">
<h5 class="mb-3">📊 Monthly Calls by Agent (<?= $year ?>)</h5>
<div class="table-responsive">
<table class="table table-bordered table-hover table-striped">
<thead class="table-primary text-center">
<tr>
<th>Agent</th>
<?php foreach($monthLabels as $ml): ?><th><?= $ml ?></th><?php endforeach; ?>
<th>Total (<?= htmlspecialchars($filterSentiment) ?>)</th>
</tr>
</thead>
<tbody>
<?php foreach($agentsData as $agent): ?>
<tr>
<td><?= htmlspecialchars($agent['name']) ?></td>
<?php $sum=0; foreach($agent['months'] as $m): $sum+=$m; ?><td><?= $m ?></td><?php endforeach; ?>
<td><strong><?= $sum ?></strong></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<!-- ===== Individual Agent Stats ===== -->
<div class="card">
<h5 class="mb-3">📊 Individual Agent Stats (Last 7 Days)</h5>
<div class="table-responsive">
<table class="table table-bordered table-hover table-striped">
<thead class="table-light text-center">
<tr>
<th>Agent</th><th>Total</th><th>Positive</th><th>Negative</th><th>On Hold</th><th>Won</th><th>Trend (7 days)</th>
</tr>
</thead>
<tbody>
<?php foreach($agentsData as $agent): ?>
<tr>
<td><?= htmlspecialchars($agent['name']) ?></td>
<td><?= $agent['total'] ?></td>
<td><?= $agent['positive'] ?></td>
<td><?= $agent['negative'] ?></td>
<td><?= $agent['onhold'] ?></td>
<td><?= $agent['won'] ?></td>
<td style="width:300px;"><canvas id="agentChart<?= $agent['id'] ?>"></canvas></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Sidebar Toggle =====
document.getElementById('toggleSidebar').addEventListener('click', ()=>{document.getElementById('sidebar').classList.toggle('show');});
document.querySelectorAll('.dropdown-btn').forEach(btn=>{btn.addEventListener('click', ()=>{btn.classList.toggle('active');const container=btn.nextElementSibling;container.style.display=container.style.display==='block'?'none':'block';});});
document.querySelectorAll('.dropdown-container a.active').forEach(link=>{const parent=link.closest('.dropdown-container');if(parent){parent.style.display='block';parent.previousElementSibling.classList.add('active');}});

const agentsData = <?= json_encode($agentsData, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP) ?>;
const labels7days = <?= json_encode($labels7days) ?>;
const monthLabels = <?= json_encode($monthLabels) ?>;
const pageFilter = <?= json_encode($filterSentiment) ?>;

// ===== 7-day Combined Chart =====
let combinedDatasets = [];
if(pageFilter === 'All'){
    const statuses = ['Positive','Negative','On Hold','Won'];
    const colors = ['#0d6efd','#dc3545','#ffc107','#198754'];
    statuses.forEach((status, idx)=>{
        const data = labels7days.map(day=>{
            let sum=0;
            agentsData.forEach(a=>{
                const key = status==='On Hold'?'OnHold':status;
                sum += a.last7days[key][day]??0;
            });
            return sum;
        });
        combinedDatasets.push({label: status,data:data,backgroundColor:colors[idx]});
    });
} else {
    const sel = pageFilter;
    const mapKey = sel==='On Hold'?'OnHold':sel;
    const data = labels7days.map(day=>{
        let sum=0; agentsData.forEach(a=>{sum += a.last7days[mapKey][day]??0;}); return sum;
    });
    const color = sel==='Positive'? '#0d6efd':sel==='Negative'?'#dc3545':sel==='On Hold'?'#ffc107':'#198754';
    combinedDatasets.push({label:sel,data:data,backgroundColor:color});
}

const combinedCtx = document.getElementById('combinedChart').getContext('2d');
new Chart(combinedCtx,{type:'bar',data:{labels:labels7days,datasets:combinedDatasets},options:{responsive:true,interaction:{mode:'index',intersect:false},plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});

// ===== Individual Agent Charts =====
agentsData.forEach(agent=>{
    const ctx = document.getElementById('agentChart'+agent.id).getContext('2d');
    const pos = labels7days.map(d=>agent.last7days['Positive'][d]??0);
    const neg = labels7days.map(d=>agent.last7days['Negative'][d]??0);
    const onh = labels7days.map(d=>agent.last7days['OnHold'][d]??0);
    const won = labels7days.map(d=>agent.last7days['Won'][d]??0);
    new Chart(ctx,{type:'bar',data:{labels:labels7days,datasets:[
        {label:'Positive',data:pos,backgroundColor:'#0d6efd'},
        {label:'Negative',data:neg,backgroundColor:'#dc3545'},
        {label:'On Hold',data:onh,backgroundColor:'#ffc107'},
        {label:'Won',data:won,backgroundColor:'#198754'}
    ]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});
});

// ===== Monthly Summary Chart =====
(function renderMonthlySummary(){
    const container = document.createElement('div');
    container.className='card mb-4';
    container.innerHTML='<h5 class="mb-3 text-center">📈 Monthly Summary (<?= htmlspecialchars($filterSentiment) ?>)</h5><div class="chart-container"><canvas id="monthlySummaryChart"></canvas></div>';
    const ref = document.querySelector('.card.mb-4');
    if(ref && ref.parentNode) ref.parentNode.insertBefore(container,ref);
    const monthsSum = monthLabels.map((lbl,idx)=>{
        let s=0; agentsData.forEach(a=>{s+=a.months[idx]??0;}); return s;
    });
    const mctx = document.getElementById('monthlySummaryChart').getContext('2d');
    new Chart(mctx,{type:'line',data:{labels:monthLabels,datasets:[{label:'Calls (<?= htmlspecialchars($filterSentiment) ?>)',data:monthsSum,fill:true,tension:0.3,backgroundColor:'rgba(13,110,253,0.08)',borderColor:'#0d6efd'}]},options:{responsive:true,plugins:{legend:{position:'bottom'}},scales:{y:{beginAtZero:true}}}});
})();
</script>
</body>
</html>
