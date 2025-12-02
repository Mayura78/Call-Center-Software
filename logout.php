<?php
session_start();

// Remove ONLY admin session keys
unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);

// Optional: admin login state reset
// unset($_SESSION['admin_role']);  // if used

// Do NOT use session_destroy() (will logout users also)

// Redirect to admin login page
header("Location: admin_login.php");
exit();
?>
