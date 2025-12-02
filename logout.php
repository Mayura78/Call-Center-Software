<?php
session_start();

// Remove ONLY admin session keys
unset($_SESSION['customer_id']);
unset($_SESSION['customer_name']);

// Optional: admin login state reset
// unset($_SESSION['admin_role']);  // if used

// Do NOT use session_destroy() (will logout users also)

// Redirect to admin login page
header("Location: user_login.php");
exit();
?>
