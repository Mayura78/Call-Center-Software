<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// DB connection check
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? 'Connection not established'));
}

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Filters
$sentimentFilter = $_GET['sentiment'] ?? '';
$dailyView = isset($_GET['daily']) ? true : false;
$fromDate = $_GET['from_date'] ?? '';
$toDate = $_GET['to_date'] ?? '';

if ($dailyView) {
    $today = date('Y-m-d');
    $fromDate = $today;
    $toDate = $today;
}

// ===== COUNT TOTAL =====
$countSql = "SELECT COUNT(*) as total FROM call_log cl WHERE 1=1";
$countParams = [];
$countTypes = "";
if ($sentimentFilter) { $countSql .= " AND cl.sentiment=?"; $countParams[] = $sentimentFilter; $countTypes .= "s"; }
if ($fromDate) { $countSql .= " AND cl.called_at>=?"; $countParams[] = $fromDate . " 00:00:00"; $countTypes .= "s"; }
if ($toDate) { $countSql .= " AND cl.called_at<=?"; $countParams[] = $toDate . " 23:59:59"; $countTypes .= "s"; }

$countStmt = $conn->prepare($countSql);
if ($countParams) $countStmt->bind_param($countTypes, ...$countParams);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'] ?? 0;
$countStmt->close();
$totalPages = ceil($totalRows / $limit);

// ===== FETCH LOGS =====
$sql = "SELECT cl.*, m.user_name as agent_name FROM call_log cl LEFT JOIN manageuser m ON cl.user_id = m.id WHERE 1=1";
$params = []; $types = "";
if ($sentimentFilter) { $sql .= " AND cl.sentiment=?"; $params[] = $sentimentFilter; $types .= "s"; }
if ($fromDate) { $sql .= " AND cl.called_at>=?"; $params[] = $fromDate . " 00:00:00"; $types .= "s"; }
if ($toDate) { $sql .= " AND cl.called_at<=?"; $params[] = $toDate . " 23:59:59"; $types .= "s"; }

$sql .= " ORDER BY cl.id DESC LIMIT ? OFFSET ?";
$params[] = $limit; $params[] = $offset; $types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>📊 Admin Call Logs</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family:'Segoe UI',sans-serif;
    background:#f4f8ff;
    margin:0;
}
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
    transition:width 0.3s;
}
.sidebar img.logo {
    width:180px;
    margin:0 auto 10px;
    display:block;
    border-radius:10px;
}
.sidebar .address {
    font-size: 12px;
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
.main-content {
    margin-left: 200px;
    padding: 20px;
    transition: margin-left 0.3s;
}
.toggle-btn {
    position: fixed;
    left: 210px;
    top: 10px;
    font-size: 1.6rem;
    cursor: pointer;
    color: #0b1b58;
    transition: left 0.3s;
}
.container-box {
    background:#fff;
    padding:20px;
    border-radius:15px;
    box-shadow:0 8px 25px rgba(0,0,0,0.1);
    overflow-x:auto;
}
.table th, .table td {
    vertical-align:middle;
    font-size:0.85rem;
    white-space:nowrap;
}

/* Badges */
.badge-positive {
    background:green;
    color:#fff;
}
.badge-negative {
    background:red;
    color:#fff;
}
.badge-onhold {
    background:orange;
    color:#fff;
}
.badge-won {
    background:blue;
    color:#fff;
}
.badge-call-completed {
    background:#0d6efd;
    color:#fff;
    font-weight:500;
}
.badge-abandon {
    background:#ffc107;
    color:#000;
    font-weight:500;
}

/* Hover effect for rows */
.table tbody tr {
    transition: all 0.3s ease;
    cursor: pointer;
}
.table tbody tr:hover {
    background: #e9f5ff;
    transform: translateX(3px);
}

/* Tooltip for notes */
.notes-tooltip {
    position: relative;
    display: inline-block;
}
.notes-tooltip .notes-text {
    visibility: hidden;
    width: 300px;
    background-color: rgba(0,0,0,0.85);
    color: #fff;
    text-align: left;
    border-radius: 6px;
    padding: 10px;
    position: absolute;
    z-index: 10;
    top: -5px;
    left: 105%;
    font-size: 0.85rem;
    white-space: normal;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}
.notes-tooltip:hover .notes-text {
    visibility: visible;
    opacity: 1;
    transition: 0.3s;
}

.pagination li a {
    min-width:35px;
    text-align:center;
}
.filter-btns .btn {
    min-width:80px;
    margin-right:5px;
    font-size:0.85rem;
}
[data-bs-toggle="tooltip"] { cursor:pointer; }

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

@media(max-width:768px){ .sidebar{width:0;} .main-content{margin-left:0; padding:10px;} .toggle-btn{left:10px;} .container-box{padding:10px;} }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <img src="CRealty.png" class="logo" alt="CR Logo">
    <div class="address">2nd Floor, 132 Avissawella Rd, Maharagama 10280 <br> 📞 0114 389 900</div>
    <div class="welcome">Welcome, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></div>
    <a href="index.php"><i class="bi bi-house"></i> Dashboard</a>
    <button class="dropdown-btn"><i class="bi bi-telephone"></i> Hotline <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <a href="managehotlines.php"><i class="bi bi-gear"></i> Manage Hotline</a>
        <a href="view_hotline.php"><i class="bi bi-eye"></i> View Hotline</a>
        <a href="hotline_settings.php"><i class="bi bi-sliders"></i> Add Project</a>
    </div>
    <button class="dropdown-btn"><i class="bi bi-person-lines-fill"></i> Agents Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <li><a href="manageuser.php">Agent Administration</a></li>
        <li><a href="view_users.php">Agent Overview</a></li>
    </div>
    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container">
        <li><a href="usertarget.php">Call Targets</a></li>
        <li><a href="viewtargetdetails.php">Target Overview</a></li>
    </div>
    <a href="call_timer.php"><i class="bi bi-clock-history"></i> Call Timer</a>
    <a href="call_log.php" class="active"><i class="bi bi-card-list"></i> Call Logs</a>
    
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

<i class="bi bi-list toggle-btn" id="toggleSidebar"></i>

<div class="main-content">
<div class="container-box">
    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap">
        <h3><i class="bi bi-card-list"></i> Admin Call Logs</h3>
    </div>
    <hr>
    <p class="user-guide">View records of all calls made, including date, time, and agent information.</p>

    <!-- Filter Buttons -->
    <div class="filter-btns mb-2 flex-wrap">
        <a href="?sentiment=&daily=<?php echo $dailyView ? 1 : 0; ?>" class="btn btn-outline-primary <?php if($sentimentFilter=='') echo 'active'; ?>">All</a>
        <a href="?sentiment=Positive&daily=<?php echo $dailyView ? 1 : 0; ?>" class="btn btn-outline-success <?php if($sentimentFilter=='Positive') echo 'active'; ?>">Positive</a>
        <a href="?sentiment=Negative&daily=<?php echo $dailyView ? 1 : 0; ?>" class="btn btn-outline-danger <?php if($sentimentFilter=='Negative') echo 'active'; ?>">Negative</a>
        <a href="?sentiment=On Hold&daily=<?php echo $dailyView ? 1 : 0; ?>" class="btn btn-outline-warning <?php if($sentimentFilter=='On Hold') echo 'active'; ?>">On Hold</a>
        <a href="?sentiment=Won&daily=<?php echo $dailyView ? 1 : 0; ?>" class="btn btn-outline-primary <?php if($sentimentFilter=='Won') echo 'active'; ?>">Won</a>
        <a href="?daily=1" class="btn btn-outline-info <?php if($dailyView) echo 'active'; ?>">Today</a>
    </div>

    <!-- Date range filter -->
    <form class="mb-2 d-flex flex-wrap gap-2 align-items-center" method="GET">
        <input type="hidden" name="sentiment" value="<?= htmlspecialchars($sentimentFilter); ?>">
        <input type="hidden" name="daily" value="<?= $dailyView?1:0; ?>">
        <input type="text" id="searchInput" class="form-control" style="max-width:180px" placeholder="Search...">
        <input type="date" name="from_date" class="form-control" value="<?php echo $fromDate; ?>">
        <input type="date" name="to_date" class="form-control" value="<?php echo $toDate; ?>">
        <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Apply</button>
    </form>

    <!-- Table -->
    <div style="overflow-x:auto; max-height:70vh;">
    <table class="table table-bordered table-hover table-sm" id="callLogsTable">
        <thead class="table-primary">
            <tr>
                <th>#</th><th>Agent</th><th>Number</th><th>Customer</th><th>Email</th><th>Project</th><th>Event</th>
                <th>Reason</th><th>Sentiment</th><th>Follow-up</th><th>Appointment</th><th>Appointment Reason</th><th>Notes</th><th>Called At</th>
            </tr>
        </thead>
        <tbody>
        <?php
        if ($result->num_rows > 0) {
            $i = $offset + 1;
            while ($row = $result->fetch_assoc()) {

                $sentimentBadge = '';
                switch($row['sentiment']){
                    case 'Positive': $sentimentBadge="<span class='badge badge-positive' data-bs-toggle='tooltip' title='Customer was positive'>Positive</span>"; break;
                    case 'Negative': $sentimentBadge="<span class='badge badge-negative' data-bs-toggle='tooltip' title='Customer was negative'>Negative</span>"; break;
                    case 'On Hold': $sentimentBadge="<span class='badge badge-onhold' data-bs-toggle='tooltip' title='Call is On Hold'>On Hold</span>"; break;
                    case 'Won': $sentimentBadge="<span class='badge badge-won' data-bs-toggle='tooltip' title='Call Won'>Won</span>"; break;
                    default: $sentimentBadge=htmlspecialchars($row['sentiment']); break;
                }

                $eventBadge='';
                if ($row['event'] === 'CALL_COMPLETED') $eventBadge="<span class='badge badge-call-completed' data-bs-toggle='tooltip' title='Call Completed Successfully'>CALL_COMPLETED</span>";
                elseif ($row['event'] === 'ABANDON') $eventBadge="<span class='badge badge-abandon' data-bs-toggle='tooltip' title='Call Abandoned'>ABANDON</span>";
                else $eventBadge=htmlspecialchars($row['event']);

                $notesText=htmlspecialchars($row['notes']);
                $notesTooltip="<div class='notes-tooltip'>".mb_strimwidth($notesText,0,30,'...')."<span class='notes-text'>{$notesText}</span></div>";

                echo "<tr>
                    <td>{$i}</td>
                    <td>".htmlspecialchars($row['agent_name'] ?? 'N/A')."</td>
                    <td>".htmlspecialchars($row['number_called'])."</td>
                    <td>".htmlspecialchars($row['customer_name'])."</td>
                    <td>".htmlspecialchars($row['email'])."</td>
                    <td>".htmlspecialchars($row['project_name'])."</td>
                    <td>{$eventBadge}</td>
                    <td>".htmlspecialchars($row['reason'])."</td>
                    <td>{$sentimentBadge}</td>
                    <td>".htmlspecialchars($row['followup_date'] ?? '-')."</td>
                    <td>".htmlspecialchars($row['appointment_date'] ?? '-')."</td>
                    <td>".htmlspecialchars($row['appointment_reason'] ?? '-')."</td>
                    <td>{$notesTooltip}</td>
                    <td>".htmlspecialchars($row['called_at'])."</td>
                </tr>";
                $i++;
            }
        } else {
            echo "<tr><td colspan='14' class='text-center text-muted'>No call logs found.</td></tr>";
        }
        ?>
        </tbody>
    </table>
    </div>

    <!-- Pagination -->
    <?php if($totalPages>1): ?>
    <nav>
        <ul class="pagination justify-content-center">
        <?php for($p=1;$p<=$totalPages;$p++): ?>
            <li class="page-item <?php if($p==$page) echo 'active'; ?>">
                <a class="page-link" href="?page=<?php echo $p; ?>&sentiment=<?php echo $sentimentFilter; ?>&from_date=<?php echo $fromDate; ?>&to_date=<?php echo $toDate; ?>&daily=<?php echo $dailyView?1:0; ?>"><?php echo $p; ?></a>
            </li>
        <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleSidebar').addEventListener('click', ()=>{
    const sidebar=document.querySelector('.sidebar');
    const main=document.querySelector('.main-content');
    const btn=document.getElementById('toggleSidebar');
    if(sidebar.style.width==='0px'||sidebar.style.width===''){
        sidebar.style.width='200px'; main.style.marginLeft='200px'; btn.style.left='210px';
    } else {
        sidebar.style.width='0'; main.style.marginLeft='0'; btn.style.left='10px';
    }
});

document.querySelectorAll('.dropdown-btn').forEach(btn=>{
    btn.addEventListener('click',()=>{
        btn.classList.toggle('active');
        let container=btn.nextElementSibling;
        container.style.display=(container.style.display==='block')?'none':'block';
    });
});

// Table search
document.getElementById('searchInput').addEventListener('input',function(){
    let filter=this.value.toLowerCase();
    document.querySelectorAll('#callLogsTable tbody tr').forEach(tr=>{
        tr.style.display=[...tr.cells].some(td=>td.innerText.toLowerCase().includes(filter))?'':'none';
    });
});

// Tooltip init
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
</script>
</body>
</html>
