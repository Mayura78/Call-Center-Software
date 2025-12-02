<?php
session_start();
include 'db.php';

// ===== LOAD SYSTEM THEME =====
if (!isset($_SESSION['theme_mode'])) {
    $themeResult = $conn->query("SELECT setting_value FROM settings WHERE setting_name='theme_mode'");
    $_SESSION['theme_mode'] = ($themeResult->num_rows > 0) ? $themeResult->fetch_assoc()['setting_value'] : 'light';
}
$currentTheme = $_SESSION['theme_mode'];

// ===== CHECK LOGIN =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// ===== ADD COLUMNS IF NOT EXISTS =====
$conn->query("ALTER TABLE call_log ADD COLUMN IF NOT EXISTS visit_status VARCHAR(50) DEFAULT 'Pending'");
$conn->query("ALTER TABLE call_log ADD COLUMN IF NOT EXISTS bonus_amount DECIMAL(10,2) DEFAULT 0");

// ===== FILTER HANDLER =====
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$filter_type = $_GET['filter_type'] ?? 'all';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';

$sql = "
    SELECT 
        id, 
        customer_name, 
        number_called, 
        appointment_reason, 
        appointment_date, 
        visit_status, 
        bonus_amount 
    FROM call_log 
    WHERE user_id = ?
      AND appointment_date IS NOT NULL
";

$params = [$user_id];
$types = "i";

// === FILTER CONDITIONS ===
if ($search !== '') {
    $sql .= " AND customer_name LIKE ?";
    $params[] = "%$search%";
    $types .= "s";
}

if ($status_filter !== '') {
    $sql .= " AND visit_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Filter by period
switch ($filter_type) {
    case 'today':
        $sql .= " AND DATE(appointment_date) = CURDATE()";
        break;
    case 'this_week':
        $sql .= " AND YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)";
        break;
    case 'this_month':
        $sql .= " AND MONTH(appointment_date) = MONTH(CURDATE()) AND YEAR(appointment_date) = YEAR(CURDATE())";
        break;
    case 'this_year':
        $sql .= " AND YEAR(appointment_date) = YEAR(CURDATE())";
        break;
    case 'custom':
        if ($from_date && $to_date) {
            $sql .= " AND DATE(appointment_date) BETWEEN ? AND ?";
            $params[] = $from_date;
            $params[] = $to_date;
            $types .= "ss";
        }
        break;
}

$sql .= " ORDER BY appointment_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// ===== BONUS SUMMARY =====
$bonusStmt = $conn->prepare("SELECT SUM(bonus_amount) AS total_bonus FROM call_log WHERE user_id=?");
$bonusStmt->bind_param("i", $user_id);
$bonusStmt->execute();
$total_bonus = $bonusStmt->get_result()->fetch_assoc()['total_bonus'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($currentTheme); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Appointment List | Commercial Realty (Pvt) Ltd</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
:root {
    --sidebar-bg: linear-gradient(180deg, #00205C);
    --sidebar-color: white;
    --sidebar-hover: rgba(255,255,255,0.2);
    --bg-body: #eef2f7;
}
body { font-family:'Poppins',sans-serif; background:var(--bg-body); margin:0; }

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
}
.sidebar .sidebar-header img.logo {
    width:180px;
    border-radius:5px;
    margin-bottom:5px;
}
.sidebar .sidebar-header p {
    font-size:0.8rem;
    color:rgba(230,230,230,0.9);
    margin:2px 0;
}
.sidebar a, .sidebar button {
    display:block;
    color:var(--sidebar-color);
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

/* Content */
.content {
    margin-left:220px;
    padding:30px;
    transition: margin-left 0.3s;
}
.container-box {
    background:white;
    border-radius:12px;
    box-shadow:0 4px 12px rgba(0,0,0,0.08);
    padding:25px;
}
.table thead {
    background:#00205C;
    color:white;
}
.table tbody tr {
    transition: background-color 0.2s ease;
    cursor:pointer;
}
.table tbody tr:hover {
    background-color:#d9e8ff;
}
/* full row highlight */
.status-badge {
    padding:5px 12px;
    border-radius:25px;
    font-size:0.85rem;
}
.status-Pending{background-color:#ffc107;color:#000;}
.status-Confirmed{background-color:#17a2b8;color:#fff;}
.status-Visited{background-color:#28a745;color:#fff;}
.status-Cancelled{background-color:#dc3545;color:#fff;}
.summary-card { background: linear-gradient(135deg, #1500ff96, #00a6ffab); color:white; border-radius:10px; padding:20px; text-align:center; transition: transform 0.2s; }
.summary-card:hover { transform: translateY(-3px); box-shadow:0 6px 15px rgba(0,0,0,0.15); }


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
    from { opacity:0;
        transform:translateY(20px);
    }
    to {
        opacity:1;
        transform:translateY(0);
    }
}

/* Responsive */
@media(max-width:992px){ .sidebar{ position:relative; width:100%; height:auto; } .content{ margin-left:0; padding:20px; } .footer{ left:0; } }

/* Filter enhancements */
.form-label { font-size:0.85rem; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <img src="CRealty.png" alt="Company Logo" class="logo">
        <p>2nd Floor, 132 Avissawella Rd,<br>Maharagama 10280</p>
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
    <a href="userreports.php"><i class="bi bi-bar-chart"></i> Reports</a>
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
    <div class="container-box">
        <h4 class="mb-3 text-primary fw-semibold">Welcome, <?= htmlspecialchars($user_name); ?></h4>
        <p class="user-guide">
        Displays appointments or site visits planned for the day or week. Keeps agents organized.
    </p>

        <div class="summary-card mb-4">
            <h4>Total Income Earned</h4>
            <p style="font-size:1.4rem;">Rs. <?= number_format($total_bonus,2); ?></p>
        </div>

        <!-- Filters -->
        <form method="get" class="row g-3 mb-4 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold">Search Customer</label>
                <input type="text" name="search" class="form-control" placeholder="Enter customer name..." value="<?= htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="Pending" <?= $status_filter=='Pending'?'selected':''; ?>>Pending</option>
                    <option value="Confirmed" <?= $status_filter=='Confirmed'?'selected':''; ?>>Confirmed</option>
                    <option value="Visited" <?= $status_filter=='Visited'?'selected':''; ?>>Visited</option>
                    <option value="Cancelled" <?= $status_filter=='Cancelled'?'selected':''; ?>>Cancelled</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Time Filter</label>
                <select name="filter_type" id="filter_type" class="form-select" onchange="toggleDateRange()">
                    <option value="all" <?= $filter_type=='all'?'selected':''; ?>>All</option>
                    <option value="today" <?= $filter_type=='today'?'selected':''; ?>>Today</option>
                    <option value="this_week" <?= $filter_type=='this_week'?'selected':''; ?>>This Week</option>
                    <option value="this_month" <?= $filter_type=='this_month'?'selected':''; ?>>This Month</option>
                    <option value="this_year" <?= $filter_type=='this_year'?'selected':''; ?>>This Year</option>
                    <option value="custom" <?= $filter_type=='custom'?'selected':''; ?>>Custom Range</option>
                </select>
            </div>
            <div class="col-md-2 custom-date d-none">
                <label class="form-label fw-semibold">From</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date); ?>">
            </div>
            <div class="col-md-2 custom-date d-none">
                <label class="form-label fw-semibold">To</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date); ?>">
            </div>
            <div class="col-md-2 text-end">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
        </form>

        <!-- Appointment Table -->
        <?php if ($result->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Customer</th>
                            <th>Contact</th>
                            <th>Reason</th>
                            <th>Appointment Date</th>
                            <th>Status</th>
                            <th>Income (Rs.)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $count=1; while($row=$result->fetch_assoc()): ?>
                        <tr class="appointment-row"
                            data-customer="<?= htmlspecialchars($row['customer_name']); ?>"
                            data-contact="<?= htmlspecialchars($row['number_called']); ?>"
                            data-reason="<?= htmlspecialchars($row['appointment_reason']); ?>"
                            data-date="<?= htmlspecialchars($row['appointment_date']); ?>"
                            data-status="<?= htmlspecialchars($row['visit_status']); ?>"
                            data-bonus="<?= number_format($row['bonus_amount'],2); ?>"
                        >
                            <td><?= $count++; ?></td>
                            <td><?= htmlspecialchars($row['customer_name']); ?></td>
                            <td><?= htmlspecialchars($row['number_called']); ?></td>
                            <td><?= htmlspecialchars($row['appointment_reason']); ?></td>
                            <td><?= htmlspecialchars($row['appointment_date']); ?></td>
                            <td><span class="status-badge status-<?= htmlspecialchars($row['visit_status']); ?>" data-bs-toggle="tooltip" title="<?= htmlspecialchars($row['visit_status']); ?>"><?= htmlspecialchars($row['visit_status']); ?></span></td>
                            <td class="text-success fw-semibold"><?= number_format($row['bonus_amount'],2); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">
                <strong>No appointments found for this period.</strong><br>Keep up the great work reaching new clients 🚀
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal for Appointment Details -->
<div class="modal fade" id="appointmentModal" tabindex="-1" aria-labelledby="appointmentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="appointmentModalLabel">Appointment Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Customer:</strong> <span id="modalCustomer"></span></p>
        <p><strong>Contact:</strong> <span id="modalContact"></span></p>
        <p><strong>Reason:</strong> <span id="modalReason"></span></p>
        <p><strong>Date:</strong> <span id="modalDate"></span></p>
        <p><strong>Status:</strong> <span id="modalStatus"></span></p>
        <p><strong>Bonus (Rs.):</strong> <span id="modalBonus"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar dropdown
document.querySelectorAll(".dropdown-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const dropdown = btn.nextElementSibling;
        document.querySelectorAll(".dropdown-container").forEach(dc => { if(dc!==dropdown) dc.style.display='none'; });
        dropdown.style.display = dropdown.style.display==='block'?'none':'block';
    });
});

// Toggle custom date range
function toggleDateRange() {
    const filterType = document.getElementById('filter_type').value;
    const dateFields = document.querySelectorAll('.custom-date');
    dateFields.forEach(field => field.classList.toggle('d-none', filterType !== 'custom'));
}
toggleDateRange();

// Enable tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
  return new bootstrap.Tooltip(tooltipTriggerEl)
});

// Modal popup on row click
document.querySelectorAll('.appointment-row').forEach(row => {
    row.addEventListener('click', () => {
        document.getElementById('modalCustomer').textContent = row.dataset.customer;
        document.getElementById('modalContact').textContent = row.dataset.contact;
        document.getElementById('modalReason').textContent = row.dataset.reason;
        document.getElementById('modalDate').textContent = row.dataset.date;
        document.getElementById('modalStatus').textContent = row.dataset.status;
        document.getElementById('modalBonus').textContent = row.dataset.bonus;
        const modal = new bootstrap.Modal(document.getElementById('appointmentModal'));
        modal.show();
    });
});
</script>
</body>
</html>
