<?php
// sidebar.php
include 'db.php';

// Get sidebar info
$stmt = $conn->prepare("SELECT * FROM sidebar_settings WHERE id = 1 LIMIT 1");
$stmt->execute();
$setting = $stmt->get_result()->fetch_assoc();
$stmt->close();
?>

<div class="sidebar" id="sidebar">
  <button class="toggle-btn" id="toggleBtn"><i class="bi bi-list"></i></button>
  <div class="brand">
    <img src="<?= htmlspecialchars($setting['logo_path']) ?>" alt="Logo" class="logo">
    <span class="brand-address"><?= nl2br(htmlspecialchars($setting['company_address'])) ?></span>
  </div>

  <ul>
    <li class="active"><a href="index.php"><i class="bi bi-house"></i><span>Dashboard</span></a></li>

    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-telephone"></i><span>Hotlines</span><i class="bi bi-caret-down ms-auto"></i></a>
      <ul class="submenu">
        <li><a href="managehotlines.php">Manage Hotline</a></li>
        <li><a href="view_hotline.php">View Hotline</a></li>
        <li><a href="hotline_setting.php">Add Project</a></li>
      </ul>
    </li>

    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-person-lines-fill"></i><span>Call Agents</span><i class="bi bi-caret-down ms-auto"></i></a>
      <ul class="submenu">
        <li><a href="manageuser.php">Manage Users</a></li>
        <li><a href="view_users.php">View User Details</a></li>
        <li><a href="usertarget.php">User Targets</a></li>
        <li><a href="viewtargetdetails.php">View User Target</a></li>
      </ul>
    </li>

    <li><a href="call_timer.php"><i class="bi bi-clock-history"></i><span>Call Timer</span></a></li>
    <li><a href="call_log.php"><i class="bi bi-journal-text"></i><span>Call Logs</span></a></li>
    <li><a href="reports.php"><i class="bi bi-bar-chart"></i><span>Reports</span></a></li>

    <li class="dropdown">
      <a href="javascript:void(0);" class="dropdown-toggle"><i class="bi bi-gear"></i><span>Setting</span><i class="bi bi-caret-down ms-auto"></i></a>
      <ul class="submenu">
        <li><a href="profilesetting.php">Profile Setting</a></li>
      </ul>
    </li>

    <li><a href="logout.php"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a></li>
  </ul>
</div>
