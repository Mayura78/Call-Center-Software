<?php
session_start();
include 'db.php';

// ==================== HANDLE DELETE =====================
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM target WHERE id=?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    header("Location: viewtargetdetails.php");
    exit();
}

// ==================== CSV EXPORT =====================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $res = $conn->query("
        SELECT t.id, m.user_name, t.total_count, t.start_date, t.end_date, t.number_list, t.start_date AS created_at
        FROM target t
        JOIN manageuser m ON t.user_id = m.id
        ORDER BY t.id DESC
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=targets_' . date('Ymd_His') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'User', 'Total Count', 'Start Date', 'End Date', 'Numbers', 'Assigned On']);
    while ($r = $res->fetch_assoc()) {
        fputcsv($output, [
            $r['id'], $r['user_name'], $r['total_count'],
            $r['start_date'], $r['end_date'],
            str_replace(["\r", "\n"], [' ', ' / '], $r['number_list']),
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}

// ==================== FETCH TARGETS =====================
$result = $conn->query("
    SELECT t.id, t.user_id, m.user_name, t.total_count, t.start_date, t.end_date, t.number_list, t.start_date AS created_at
    FROM target t
    JOIN manageuser m ON t.user_id = m.id
    ORDER BY t.start_date DESC
");

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$grouped = [];

while ($row = $result->fetch_assoc()) {
    $assignDate = date('Y-m-d', strtotime($row['created_at']));
    if ($assignDate == $today) {
        $grouped['Today'][] = $row;
    } elseif ($assignDate == $yesterday) {
        $grouped['Yesterday'][] = $row;
    } else {
        $grouped[date('M d, Y', strtotime($assignDate))][] = $row;
    }
}

// Admin name for sidebar
$admin_name = $_SESSION['admin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Target Details - Admin Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<style>
body { font-family: 'Poppins', sans-serif; background: #eef2f7; margin:0; }

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed; top:0; left:0;
    width:230px; height:100vh;
    background:#0b1b58; color:#fff;
    padding-top:20px; overflow-y:auto;
    transition: all 0.3s ease;
    z-index: 1000;
}
.sidebar img.logo { width:180px; margin:0 auto 10px; display:block; }
.sidebar .address { font-size: 12px; color:rgba(255,255,255,0.7); text-align:center; margin-bottom:10px; }
.sidebar .welcome { font-size:0.9rem; color:#ffc107; text-align:center; font-weight:500; margin-bottom:20px; }
.sidebar a, .sidebar button {
    display:block; width:100%; padding:10px 20px;
    color:white; text-decoration:none; font-size:14px;
    background:none; border:none; text-align:left; border-radius:6px; transition:0.3s;
    cursor:pointer;
}
.sidebar a:hover, .sidebar button:hover { background: rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar a.active { background: rgba(255,255,255,0.15); border-left:4px solid #ffc107; }
.sidebar .dropdown-container{ display:none; background:rgba(0,0,0,0.05); border-radius:5px; margin-left:10px; }
.sidebar .dropdown-container a{ padding-left:35px; font-size:13px; border-radius:4px; }
.logout-btn{ position:absolute; bottom:20px; width:100%; }
.logout-btn a{ background: rgba(255,255,255,0.1); font-weight:500; color:#ffc107; display:flex; align-items:center; justify-content:center; }

/* Sidebar toggle button */
.sidebar-toggle {
    display:none; position:fixed; top:15px; left:15px; z-index:1100;
    font-size:28px; color:#0b1b58; cursor:pointer;
}

/* ===== MAIN CONTENT ===== */
.main-content { margin-left:220px; padding:25px; transition: all 0.3s; }
@media(max-width:768px){
    .sidebar { left:-220px; width:200px; }
    .sidebar.show { left:0; }
    .main-content { margin-left:0; padding:15px; }
    .sidebar-toggle { display:block; }
}

/* ===== TABLE ===== */
.table-container { overflow:auto; border-radius:10px; }
.table thead th { position:sticky; top:0; background:#0d6efd; color:white; text-align:center; }
.table tbody tr:hover { background:#e8f0ff !important; transition:0.2s; }
.table td { vertical-align:middle; word-break:break-word; }

/* ===== COLLAPSIBLE SECTION COLORS ===== */
.section-header {
    color:#fff; padding:10px 15px; border-radius:10px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;
}
.section-header.today { background:#28a745; }       
.section-header.yesterday { background:#17a2b8; }   
.section-header.other { background:#6c757d; }       
.section-header:hover { opacity:0.9; }
.section-body { display:none; margin-bottom:15px; }

/* ===== USER GUIDE ===== */
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

/* ===== TOOLBAR ===== */
.toolbar { display:flex; flex-wrap:wrap; gap:10px; justify-content:flex-end; margin-bottom:10px; }
@media(max-width:576px){ .toolbar{ justify-content:flex-start; flex-direction:column; gap:5px; } }
.dropdown-download .dropdown-menu { min-width:160px; }
.dropdown-download .dropdown-item i { margin-right:8px; }

/* Print adjustments */
@media print { .sidebar, .no-print { display:none !important; } .main-content { margin-left:0 !important; padding:0 !important; } }

.rotate { transform: rotate(180deg); transition:0.3s; }
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

    <button class="dropdown-btn"><i class="bi bi-telephone"></i> Hotlines <i class="bi bi-caret-down float-end"></i></button>
    <div class="dropdown-container">
        <a href="managehotlines.php">Manage Hotline</a>
        <a href="view_hotline.php">View Hotline</a>
        <a href="hotline_setting.php">Add Project</a>
    </div>

    <button class="dropdown-btn active"><i class="bi bi-person-lines-fill"></i> Agents Management <i class="bi bi-caret-down float-end"></i></button>
    <div class="dropdown-container">
        <a href="manageuser.php">Agent Administration</a>
        <a href="view_users.php">Agent Overview</a>
    </div>

    <button class="dropdown-btn"><i class="bi bi-telephone-outbound"></i> Target Management <i class="bi bi-caret-down float-end"></i></button>
    <div class="dropdown-container" style="display:block;">
        <a href="usertarget.php">Call Targets</a>
        <a href="viewtargetdetails.php" class="active">Target Overview</a>
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

<!-- MAIN CONTENT -->
<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <h3 class="mb-0 fw-bold"><i class="bi bi-list-ol"></i> Target Details</h3>
        <div class="toolbar no-print">
            <input id="tableSearch" type="search" class="form-control form-control-sm" placeholder="🔍 Search (User / Number / Date)">
            <div class="dropdown dropdown-download">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Downloads
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="?export=csv"><i class="bi bi-file-earmark-excel"></i> Excel</a></li>
                    <li><button class="dropdown-item" id="exportPdf"><i class="bi bi-file-earmark-pdf"></i> PDF</button></li>
                    <li><button class="dropdown-item" id="printBtn"><i class="bi bi-printer"></i> Print</button></li>
                </ul>
            </div>
        </div>
    </div>

    <p class="user-guide">View all assigned targets and track which calls are completed or pending.</p>

    <?php foreach ($grouped as $dateGroup => $rows): 
        $sectionClass = ($dateGroup == 'Today') ? 'today' : (($dateGroup == 'Yesterday') ? 'yesterday' : 'other');
    ?>
    <div class="card mb-3">
        <div class="section-header <?= $sectionClass ?>">
            <span><i class="bi bi-calendar-event"></i> <?= $dateGroup ?></span>
            <i class="bi bi-chevron-down"></i>
        </div>
        <div class="section-body table-container">
            <table class="table table-hover align-middle text-center table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Total Count</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Numbers</th>
                        <th>Assigned On</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['user_name']) ?></td>
                        <td><?= $row['total_count'] ?></td>
                        <td><?= $row['start_date'] ?></td>
                        <td><?= $row['end_date'] ?></td>
                        <td style="white-space:pre-wrap;"><?= nl2br(htmlspecialchars($row['number_list'])) ?></td>
                        <td><small class="text-muted"><?= date('M d, Y', strtotime($row['created_at'])) ?></small></td>
                        <td class="no-print">
                            <a href="?delete_id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this target?');">
                                <i class="bi bi-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <p class="text-muted small mt-2">💡 Tip: Click on each date section to expand/collapse results.</p>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
$('#sidebarToggle').click(()=> $('#sidebar').toggleClass('show'));
$('.sidebar .dropdown-btn').on('click', function(){ $(this).next('.dropdown-container').slideToggle(); });

// Search filter
$('#tableSearch').on('input', function(){
    const q = $(this).val().toLowerCase();
    $('table tbody tr').each(function(){
        const text = $(this).text().toLowerCase();
        $(this).toggle(text.indexOf(q) !== -1);
    });
});

// Collapsible sections
$('.section-header').on('click', function(){
    $(this).next('.section-body').slideToggle();
    $(this).find('i.bi-chevron-down').toggleClass('rotate');
});

// Print
$('#printBtn').click(()=> window.print());

// Export PDF
$('#exportPdf').click(function(){
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'pt', 'a4');
    doc.setFontSize(14);
    doc.text('Target Details Report - ' + new Date().toLocaleString(), 40, 40);
    $('table').each(function(){
        doc.autoTable({
            html: this,
            startY: doc.lastAutoTable ? doc.lastAutoTable.finalY + 30 : 60,
            styles:{ fontSize:9, cellPadding:4 },
            headStyles:{ fillColor:[13,110,253] },
            theme:'striped'
        });
    });
    doc.save('target_details_' + new Date().toISOString().slice(0,19).replace(/[:T]/g,'_') + '.pdf');
});
</script>
</body>
</html>
