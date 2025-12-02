<?php
session_start();
include 'db.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$message = "";
$numbers = [];
$admin_name = $_SESSION['admin_name'] ?? 'Admin';

// Fetch dropdown data
$users = $conn->query("SELECT id, user_name FROM manageuser WHERE status='Active' ORDER BY user_name ASC");
$projects = $conn->query("SELECT project_name FROM project_list ORDER BY id DESC");

// ================== UPLOAD HANDLER ==================
if (isset($_POST['upload_excel'])) {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $project_name = $_POST['project_name'] ?? '';
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';

    if (!$user_id || !$project_name || !$start_date || !$end_date) {
        $message = "<div class='alert alert-warning mt-3'>⚠️ Please fill all required fields.</div>";
    } elseif (!empty($_FILES['excel_file']['tmp_name'])) {
        $file = $_FILES['excel_file']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));

        try {
            if (in_array($ext, ['xlsx', 'xls'])) {
                if (!class_exists('ZipArchive')) throw new Exception("❌ Enable PHP Zip extension in php.ini");
                $spreadsheet = IOFactory::load($file);
                $sheet = $spreadsheet->getActiveSheet();

                foreach ($sheet->getRowIterator() as $row) {
                    $cellValue = trim((string)$sheet->getCell('A'.$row->getRowIndex())->getValue());
                    if ($cellValue !== '') $numbers[] = $cellValue;
                }

            } elseif (in_array($ext, ['csv', 'txt'])) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line !== '') $numbers[] = $line;
                }
            } else throw new Exception("❌ Invalid file type (.xlsx, .xls, .csv, .txt only)");

            if ($numbers) {
                $total_count = count($numbers);
                $stmt = $conn->prepare("INSERT INTO target (user_id, project_name, start_date, end_date, total_count) VALUES (?,?,?,?,?)");
                $stmt->bind_param("isssi", $user_id, $project_name, $start_date, $end_date, $total_count);

                if ($stmt->execute()) {
                    $target_id = $stmt->insert_id;
                    $stmt2 = $conn->prepare("INSERT INTO target_numbers (target_id, number) VALUES (?,?)");

                    foreach ($numbers as $n) {
                        $stmt2->bind_param("is", $target_id, $n);
                        $stmt2->execute();
                    }

                    $message = "<div class='alert alert-success mt-3'>✅ Uploaded successfully! Total entries: $total_count</div>";
                } else {
                    $message = "<div class='alert alert-danger mt-3'>❌ DB Error: {$conn->error}</div>";
                }
            } else {
                $message = "<div class='alert alert-warning mt-3'>⚠️ No valid data found in file.</div>";
            }

        } catch (Exception $e) {
            $message = "<div class='alert alert-danger mt-3'>{$e->getMessage()}</div>";
        }
    } else {
        $message = "<div class='alert alert-warning mt-3'>⚠️ Please select a file.</div>";
    }
}

// ================== PAGINATION ==================
$limit = 5; 
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Count total targets
$totalTargets = $conn->query("SELECT COUNT(*) AS count FROM target")->fetch_assoc()['count'];
$totalPages = ceil($totalTargets / $limit);

// Fetch targets with LIMIT for pagination
$targetData = $conn->query("
    SELECT t.*, m.user_name
    FROM target t
    JOIN manageuser m ON t.user_id = m.id
    ORDER BY t.id DESC
    LIMIT $offset, $limit
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Targets - CR Call Center</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { font-family:'Poppins',sans-serif; background:#f4f6f9; margin:0; }

/* Sidebar */
.sidebar { position: fixed; top:0; left:0; width:230px; height:100vh; background:#0b1b58; color:#fff; padding-top:20px; overflow-y:auto; transition:all 0.3s ease; z-index:1000; }
.sidebar img.logo { width:180px; margin:0 auto 10px; display:block; }
.sidebar .address { font-size: 12px; color:rgba(255,255,255,0.7); text-align:center; margin-bottom:10px; }
.sidebar .welcome { font-size:0.9rem; color:#ffc107; text-align:center; font-weight:500; margin-bottom:20px; }
.sidebar a, .sidebar button { display:block; width:100%; padding:10px 20px; color:white; text-decoration:none; font-size:14px; background:none; border:none; text-align:left; border-radius:6px; transition:0.3s; cursor:pointer; }
.sidebar a:hover, .sidebar button:hover { background:rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar a.active { background:rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar .dropdown-container { display:none; background:rgba(0,0,0,0.05); border-radius:5px; margin-left:10px; }
.sidebar .dropdown-container a { padding-left:35px; font-size:13px; border-radius:4px; }
.logout-btn { position:absolute; bottom:20px; width:100%; }
.logout-btn a { background:rgba(255,255,255,0.1); font-weight:500; color:#ffc107; display:flex; align-items:center; justify-content:center; }

/* Sidebar Toggle */
.sidebar-toggle { display:none; position:fixed; top:15px; left:15px; z-index:1100; font-size:24px; color:#0d6efd; cursor:pointer; }

/* Main Content */
.main-content { margin-left:220px; padding:30px; transition:margin-left 0.3s; }

/* Table */
.table-targets { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 2px 6px rgba(0,0,0,0.05); }
.table-targets th { background:#0d6efd; color:#fff; text-align:center; font-size:0.85rem; }
.table-targets td { vertical-align:middle; font-size:0.82rem; padding:0.4rem 0.5rem; }
.table-targets tbody tr:hover { background-color:#eaf3ff; transform:scale(1.01); box-shadow:0 2px 8px rgba(0, 4, 255, 0.2); transition:all 0.25s ease; }
.numbers-box { max-height:70px; overflow-y:auto; background:#fff8e1; padding:5px; border-radius:4px; font-size:0.82rem; position:relative; }
.copy-btn { position:absolute; top:3px; right:3px; font-size:0.7rem; padding:2px 5px; cursor:pointer; }

/* User guide */
.user-guide { background:#fff8e1; border-left:4px solid #ffc107; padding:12px 15px; border-radius:6px; font-size:14px; color:#444; opacity:0; transform:translateY(20px); animation: slideFadeIn 0.8s ease-out forwards; }
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

/* Pagination */
.pagination { justify-content:center; margin-top:10px; }

/* Responsive */
@media(max-width:768px){
    .sidebar { left:-220px; width:200px; }
    .sidebar.show { left:0; }
    .main-content { margin-left:0; padding:20px; }
    .sidebar-toggle { display:block; }
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
    <div class="dropdown-container">
        <a href="manageuser.php">Agent Administration</a>
        <a href="view_users.php">Agent Overview</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down-fill float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="usertarget.php" class="active">Call Targets</a>
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
    <h3>📂 Upload Target Numbers</h3>
    <p class="user-guide">
        Assign call targets to agents. Shows what calls are expected to be made.
    </p>

    <?= $message; ?>
    <form method="POST" enctype="multipart/form-data" class="mt-3 card p-4 shadow-sm">
        <div class="row g-3">
            <div class="col-md-6">
                <label>User :</label>
                <select name="user_id" class="form-select" required>
                    <option value="">--Select User--</option>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['user_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label>Project</label>
                <select name="project_name" class="form-select" required>
                    <option value="">--Select Project--</option>
                    <?php $projects->data_seek(0); while ($p = $projects->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($p['project_name']) ?>"><?= htmlspecialchars($p['project_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label>Start Date :</label>
                <input type="date" name="start_date" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label>End Date :</label>
                <input type="date" name="end_date" class="form-control" required>
            </div>
            <div class="col-md-12">
                <label>Upload File (.xlsx, .xls, .csv, .txt) :</label>
                <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
            </div>
            <div class="col-12 text-center mt-3">
                <button type="submit" name="upload_excel" class="btn btn-primary px-4">Upload :</button>
            </div>
        </div>
    </form>

    <hr>
    <h4>📋 Existing Targets</h4>
    <div class="table-responsive">
        <table class="table table-targets table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Project</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Total</th>
                    <th>Numbers</th>
                </tr>
            </thead>
            <tbody>
            <?php
            while ($row = $targetData->fetch_assoc()):
                $numbersQuery = $conn->query("SELECT number FROM target_numbers WHERE target_id=".$row['id']);
                $nums = [];
                while($n = $numbersQuery->fetch_assoc()) $nums[] = $n['number'];
                $numbersText = htmlspecialchars(implode("\n",$nums));
            ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['user_name']) ?></td>
                    <td><?= htmlspecialchars($row['project_name']) ?></td>
                    <td><?= $row['start_date'] ?></td>
                    <td><?= $row['end_date'] ?></td>
                    <td><?= $row['total_count'] ?></td>
                    <td>
                        <div class="numbers-box" id="nums_<?= $row['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyNumbers(<?= $row['id'] ?>)">Copy</button>
                            <?= $numbersText ?>
                        </div>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <nav>
        <ul class="pagination">
            <?php for($p=1;$p<=$totalPages;$p++): ?>
                <li class="page-item <?= $p==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('.dropdown-btn').click(function(){
    $(this).next('.dropdown-container').slideToggle(200);
    $('.dropdown-container').not($(this).next()).slideUp(200);
});
$('#sidebarToggle').click(function(){ $('#sidebar').toggleClass('show'); });

function copyNumbers(id){
    const container = document.getElementById('nums_' + id);
    const text = container.innerText.replace("Copy","").trim();
    navigator.clipboard.writeText(text).then(()=>{ alert("Numbers copied to clipboard!"); });
}
</script>
</body>
</html>
