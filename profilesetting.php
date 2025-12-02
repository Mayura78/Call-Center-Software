<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];
$message = "";

// ===== UPDATE SETTINGS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['company_name']);
    $address = trim($_POST['company_address']);
    $phone = trim($_POST['company_phone']);
    $logoPath = "";

    if (!empty($_FILES['logo']['name'])) {
        $uploadDir = "uploads/";
        if (!is_dir($uploadDir)) mkdir($uploadDir);
        $fileName = basename($_FILES['logo']['name']);
        $targetPath = $uploadDir . time() . "_" . $fileName;
        move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath);
        $logoPath = $targetPath;

        $stmt = $conn->prepare("UPDATE sidebar_settings SET company_name=?, company_address=?, phone_number=?, logo_path=? WHERE id=1");
        $stmt->bind_param("ssss", $name, $address, $phone, $logoPath);
    } else {
        $stmt = $conn->prepare("UPDATE sidebar_settings SET company_name=?, company_address=?, phone_number=? WHERE id=1");
        $stmt->bind_param("sss", $name, $address, $phone);
    }

    if ($stmt->execute()) {
        $message = "✅ Profile updated successfully!";
    } else {
        $message = "❌ Update failed!";
    }
    $stmt->close();
}

// ===== FETCH CURRENT SETTINGS =====
$res = $conn->query("SELECT * FROM sidebar_settings WHERE id=1");
$setting = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Company Profile Settings | CR Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
body {
    font-family:'Poppins',sans-serif;
    background:#f5f6fa;
    margin:0;
    color:#333;
    min-height:100vh;
    display:flex;
    flex-direction:column;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top:0;
    left:0;
    width:230px;
    height:100vh;
    background:#0b1b58;
    color:#fff;
    padding-top:15px;
    overflow-y:auto;
    transition:all 0.3s ease;
    font-size:14px;
}
.sidebar.collapsed {
    width:75px;
}
.sidebar .brand {
    text-align:center;
    margin-bottom:15px;
    padding:0 10px;
    transition:all 0.3s;
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
    font-size:11px;
    color:#d8dbf7;
    margin-top:6px;
    transition:all 0.3s;
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
    margin:4px 8px;
}
.sidebar ul li a {
    display:flex;
    align-items:center;
    color:#fff;
    text-decoration:none;
    padding:8px 12px;
    border-radius:8px;
    font-weight:500;
    transition:0.3s;
}
.sidebar ul li a i {
    font-size:16px;
    margin-right:10px;
}
.sidebar ul li a:hover {
    background:rgba(255,255,255,0.12);
    transform:translateX(2px);
}
.sidebar ul li ul.submenu {
    list-style:none;
    padding-left:20px;
    display:none;
}
.sidebar ul li.show ul.submenu {
    display:block;
}
.sidebar ul li ul.submenu li a {
    font-size:13px;
    color:#d8dbf7;
    padding:4px 10px;
}
.sidebar ul li ul.submenu li a:hover {
    color:#ffc107;
}

.toggle-btn {
    position:absolute;
    top:12px;
    right:-17px;
    width:32px;
    height:32px;
    border-radius:50%;
    background:#fd7e14;
    color:white;
    border:none;
    box-shadow:0 2px 4px rgba(0,0,0,0.3);
    cursor:pointer;
    transition:0.3s;
}
.toggle-btn:hover { background:#ff922b; }

/* ===== MAIN CONTENT ===== */
.main-content {
    flex:1;
    margin-left:220px;
    padding:20px;
    transition:margin-left 0.3s ease;
}
.sidebar.collapsed ~ .main-content { margin-left:75px; }

.card {
    border-radius:12px;
    box-shadow:0 2px 8px rgba(0,0,0,0.08);
}

/* ===== FORM STYLING ===== */
form .form-label {
    font-weight:500;
    font-size:14px;
}
form input, form textarea, form select {
    font-size:13px;
}

/* ===== FOOTER ===== */
.footer {
    background:#0b1b58;
    color:white;
    text-align:center;
    font-size:12px;
    padding:8px;
    position:fixed;
    bottom:0;
    left:0;
    width:100%;
    z-index:999;
}

/* ===== User Guide ===== */
.user-guide {
    background:#fff8e1;
    border-left:4px solid #ffc107;
    padding:12px 15px;
    border-radius:6px;
    font-size:14px;
    color:#444; opacity:0;
    transform:translateY(20px);
    animation: slideFadeIn 0.8s ease-out forwards;
}
@keyframes slideFadeIn { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }

@media(max-width:768px){
    .main-content { margin-left:0; padding:15px; }
    .sidebar { left:-250px; }
    .sidebar.active { left:0; }
    .footer { font-size:11px; }
}
</style>
</head>
<body>

<!-- ===== SIDEBAR ===== -->
<div class="sidebar" id="sidebar">
    <button class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></button>
    <div class="brand">
        <img src="<?= htmlspecialchars($setting['logo_path']) ?>" alt="Logo" class="logo mb-1">
        <div class="brand-address">
            <strong><?= htmlspecialchars($setting['company_name']) ?></strong><br>
            <?= nl2br(htmlspecialchars($setting['company_address'])) ?><br>
            📞 <?= htmlspecialchars($setting['phone_number']) ?>
        </div>
    </div>

    <ul>
        <li><a href="index.php"><i class="bi bi-house"></i><span>Dashboard</span></a></li>
        <li class="dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-telephone"></i><span>Hotlines</span><i class="bi bi-caret-down ms-auto"></i></a>
            <ul class="submenu">
                <li><a href="managehotlines.php">Manage Hotline</a></li>
                <li><a href="view_hotline.php">View Hotline</a></li>
                <li><a href="hotline_setting.php">Add Project</a></li>
            </ul>
        </li>
        <li class="dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-person-lines-fill"></i><span>Agents Management</span><i class="bi bi-caret-down ms-auto"></i></a>
            <ul class="submenu">
                <li><a href="manageuser.php">Agent Administration</a></li>
                <li><a href="view_users.php">Agent Overview</a></li>
            </ul>
        </li>


        <li class="dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-telephone-outbound"></i><span>Target Managemen</span><i class="bi bi-caret-down ms-auto"></i></a>
            <ul class="submenu">
                <li><a href="usertarget.php">Call Targets</a></li>
                <li><a href="viewtargetdetails.php">Target Overview</a></li>
            </ul>
        </li>

        <li><a href="call_timer.php"><i class="bi bi-clock-history"></i><span>Call Timer</span></a></li>
        <li><a href="call_log.php"><i class="bi bi-journal-text"></i><span>Call Logs</span></a></li>

        <li class="dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-calendar-check"></i><span>Site Appointments</span><i class="bi bi-caret-down ms-auto"></i></a>
            <ul class="submenu">
                <li><a href="appointmentlist.php">Appointment List</a></li>
                <li><a href="appointment_overview.php">Appointment Overview</a></li>
            </ul>
        </li>

        <li><a href="reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span></a></li>
        <li class="active dropdown">
            <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-gear"></i><span>Settings</span><i class="bi bi-caret-down ms-auto"></i></a>
            <ul class="submenu" style="display:block;">
                <li><a href="profilesetting.php" class="text-warning fw-bold" >Profile Setting</a></li>
            </ul>
        </li>
        <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
    </ul>
</div>

<!-- ===== MAIN CONTENT ===== -->
<div class="main-content">
    <div class="container-fluid">
        <div class="card p-3 mb-5" style="max-width:550px; margin:auto;">
            <h4 class="text-center mb-3 text-primary fw-bold"><i class="bi bi-building"></i> Company Profile</h4>
                <p class="user-guide">Update your profile, system preferences, or other configurable options.</p>

            <?php if($message): ?>
                <div class="alert alert-info text-center py-1"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="mb-2">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control form-control-sm" value="<?= htmlspecialchars($setting['company_name']) ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Company Address</label>
                    <textarea name="company_address" class="form-control form-control-sm" rows="2" required><?= htmlspecialchars($setting['company_address']) ?></textarea>
                </div>

                <div class="mb-2">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="company_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($setting['phone_number']) ?>" required>
                </div>

                <div class="mb-2">
                    <label class="form-label">Company Logo</label><br>
                    <?php if(!empty($setting['logo_path'])): ?>
                        <img src="<?= htmlspecialchars($setting['logo_path']) ?>" alt="Logo" style="max-width:100px; margin-bottom:5px;"><br>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control form-control-sm">
                </div>

                <button type="submit" class="btn btn-primary w-100 btn-sm">Update Profile</button>
            </form>
        </div>
    </div>
</div>

<!-- ===== FOOTER ===== -->
<div class="footer">
    &copy; <?= date('Y') ?> Commercial Realty (Pvt) Ltd. | Developed by
    <a href="#" class="text-warning text-decoration-none">Mayura Lasantha</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('toggleBtn').onclick = () => {
    document.getElementById('sidebar').classList.toggle('collapsed');
};
document.querySelectorAll('.dropdown-toggle').forEach(el => {
    el.onclick = () => el.parentElement.classList.toggle('show');
});
</script>
</body>
</html>
