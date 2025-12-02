<?php
session_start();
include 'db.php';

// ====================== CHECK LOGIN ======================
$user_id = $_SESSION['user_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? 'User';

if (!$user_id) {
    header("Location: login.php");
    exit();
}

// ====================== ACTION: DELETE ONLY ======================
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    if ($_POST['action'] === 'delete') {
        $del = $conn->prepare("DELETE FROM missed_calls WHERE id = ? AND user_id = ?");
        $del->bind_param("ii", $id, $user_id);
        $message = $del->execute() ? "Missed call deleted." : "Failed to delete. Try again.";
        $del->close();
    }
}

// ====================== FILTERS ======================
$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
$q = trim($_GET['q'] ?? '');
$project = trim($_GET['project'] ?? '');
$from_date = trim($_GET['from_date'] ?? '');
$to_date = trim($_GET['to_date'] ?? '');

$where = " WHERE user_id = ? ";
$params = [$user_id];
$types = "i";

if ($target_id) { $where .= " AND target_id = ? "; $params[] = $target_id; $types .= "i"; }
if ($q !== '') { $where .= " AND (number LIKE CONCAT('%',?,'%') OR project_name LIKE CONCAT('%',?,'%')) "; $params[] = $q; $params[] = $q; $types .= "ss"; }
if ($project !== '') { $where .= " AND project_name = ? "; $params[] = $project; $types .= "s"; }
if ($from_date !== '') { $where .= " AND DATE(missed_at) >= ? "; $params[] = $from_date; $types .= "s"; }
if ($to_date !== '') { $where .= " AND DATE(missed_at) <= ? "; $params[] = $to_date; $types .= "s"; }

// ====================== PAGINATION ======================
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

// Count rows
$countSql = "SELECT COUNT(*) as cnt FROM missed_calls $where";
$countStmt = $conn->prepare($countSql);
$bind_count = [&$types];
foreach ($params as $k => $v) $bind_count[] = &$params[$k];
call_user_func_array([$countStmt, "bind_param"], $bind_count);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['cnt'];
$totalPages = ceil($totalRows / $limit);

// Fetch rows
$sql = "SELECT id, target_id, number, project_name, missed_at FROM missed_calls $where ORDER BY missed_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params2 = $params; 
$types2 = $types . "ii"; 
$params2[] = $limit; 
$params2[] = $offset;
$bind2 = [&$types2]; 
foreach ($params2 as $k => $v) $bind2[] = &$params2[$k];
call_user_func_array([$stmt, "bind_param"], $bind2);
$stmt->execute();
$result = $stmt->get_result();

// Projects list
$projQ = $conn->prepare("SELECT DISTINCT project_name FROM missed_calls WHERE user_id=? ORDER BY project_name ASC");
$projQ->bind_param("i", $user_id);
$projQ->execute();
$projects = $projQ->get_result()->fetch_all(MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Missed Calls - Commercial Realty</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C, #001a4d);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body { font-family:'Poppins',sans-serif; background:var(--bg-body); margin:0; }
.sidebar { position: fixed; top:0; left:0; width:220px; height:100vh; background:var(--sidebar-bg); color:var(--sidebar-color); overflow-y:auto; padding-top:20px; transition:0.3s; z-index:1000; text-align:center; }
.sidebar .sidebar-header img.logo { width:180px; border-radius:5px; margin-bottom:5px; }
.sidebar .sidebar-header p { font-size:0.8rem; color:rgba(230,230,230,0.9); margin:0 0 10px; }
.sidebar a, .sidebar button { display:block; color:var(--sidebar-color); text-decoration:none; padding:10px 15px; font-size:14px; border:none; background:none; width:100%; text-align:left; transition:0.3s; }
.sidebar a:hover, .sidebar a.active, .sidebar button:hover { background:var(--sidebar-hover); border-left:4px solid #ffc107; }
.sidebar .dropdown-container { display:none; background: rgba(0,0,0,0.15); }
.sidebar .dropdown-container a { padding-left:35px; font-size:13px; }
.logout-btn { position:absolute; bottom:20px; width:100%; }
.main-content { margin-left:220px; padding:20px; transition:0.3s; }
.card { border-radius:12px; box-shadow:0 6px 18px rgba(0,0,0,0.05); }
.header-title { color:#ff8a00; font-weight:700; }
.table-responsive { max-height:60vh; overflow:auto; }
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; opacity:0; transform:translateY(20px); animation: slideFadeIn 0.8s ease-out forwards; }
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* Mobile sidebar toggle */
.navbar-mobile { display:none; position:fixed; top:0; left:0; right:0; height:60px; background: var(--sidebar-bg); color:white; z-index:1100; display:flex; align-items:center; justify-content:space-between; padding:0 15px; font-weight:600; }
.navbar-mobile i { font-size:1.5rem; cursor:pointer; }

@media(max-width:992px){
    .sidebar { left:-220px; }
    .sidebar.active { left:0; }
    .main-content { margin-left:0; padding-top:70px; }
    .navbar-mobile { display:flex; }
}
</style>
</head>
<body>

<!-- Mobile Navbar -->
<div class="navbar-mobile d-lg-none">
    <i class="bi bi-list" id="mobileSidebarToggle"></i>
    <span>Missed Calls</span>
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
        <a href="user_target.php">Performance Goals</a>
        <a href="view_user_calls.php">Performance Overview</a>
    </div>
    <a href="missedcalls.php" class="active"><i class="bi bi-telephone"></i> Missed Calls</a>

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
        <a href="manage_reasons.php">Manage System Settings</a>
    </div>

    <div class="logout-btn"><a href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
</div>

<!-- Main Content -->
<div class="main-content">
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card p-3 mb-3">
        <h4 class="header-title"><i class="bi bi-telephone-x"></i> Missed Calls</h4>
        <small>List of all ABANDON / missed calls.</small>
    </div>

    <p class="user-guide">
        Lists calls that were not answered or completed. Helps agents follow up on pending tasks.
    </p>

    <!-- FILTERS -->
    <div class="card p-3 mb-3">
        <form class="row g-2" method="GET">
            <div class="col-md-4"><input type="text" name="q" value="<?= $q ?>" class="form-control" placeholder="Search number / project"></div>
            <div class="col-md-2">
                <select name="project" class="form-select">
                    <option value="">All Projects</option>
                    <?php foreach($projects as $p): ?>
                        <option value="<?= $p['project_name'] ?>" <?= $project === $p['project_name'] ? 'selected' : '' ?>><?= $p['project_name'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="from_date" value="<?= $from_date ?>" class="form-control"></div>
            <div class="col-md-2"><input type="date" name="to_date" value="<?= $to_date ?>" class="form-control"></div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-50"><i class="bi bi-search"></i></button>
                <a href="missedcalls.php" class="btn btn-secondary w-50"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>

    <!-- TABLE -->
    <div class="card p-3 mb-4">
        <div class="table-responsive">
            <?php if ($result->num_rows == 0): ?>
                <div class="text-center text-muted py-4">No missed calls found.</div>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>#</th><th>Number</th><th>Project</th><th>Date</th><th>Time</th><th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=$offset+1; while($r=$result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= $r['number'] ?></td>
                            <td><?= $r['project_name'] ?></td>
                            <td><?= date("Y-m-d", strtotime($r['missed_at'])) ?></td>
                            <td><?= date("h:i A", strtotime($r['missed_at'])) ?></td>
                            <td class="text-center">
                                <a href="add_call.php?target_id=<?= $r['target_id'] ?>&number=<?= $r['number'] ?>" class="btn btn-success btn-sm"><i class="bi bi-telephone"></i> Call</a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this record?');">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button class="btn btn-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination justify-content-center mt-3">
                <?php for($p=1;$p<=$totalPages;$p++): ?>
                    <li class="page-item <?= $p==$page?'active':'' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
document.querySelectorAll(".dropdown-btn").forEach(btn=>{
    btn.addEventListener("click",()=>{
        btn.classList.toggle("active");
        let drop = btn.nextElementSibling;
        drop.style.display = drop.style.display==="block"?"none":"block";
    });
});
document.getElementById("mobileSidebarToggle").addEventListener("click",()=>{
    document.getElementById("sidebar").classList.toggle("active");
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
