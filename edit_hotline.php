<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$message = "";
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch hotline data
$result = $conn->query("SELECT * FROM managehotlines WHERE id=$id");
if ($result->num_rows == 0) {
    die("Hotline not found.");
}
$hotline = $result->fetch_assoc();

// Update hotline
if (isset($_POST['update_hotline'])) {
    $project_name = $conn->real_escape_string($_POST['project_name']);
    $hotline_number = $conn->real_escape_string($_POST['hotline_number']);

    $conn->query("UPDATE managehotlines SET project_name='$project_name', hotline_number='$hotline_number' WHERE id=$id");
    $message = "Hotline updated successfully!";
    // Refresh data
    $result = $conn->query("SELECT * FROM managehotlines WHERE id=$id");
    $hotline = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Hotline - CR Call Center</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
<h2>Edit Hotline</h2>
<?php if($message): ?>
<div class="alert alert-success"><?php echo $message; ?></div>
<?php endif; ?>
<form method="POST">
  <div class="mb-3">
    <label>Project Name</label>
    <select name="project_name" class="form-select" required>
      <option value="Deniyaya Lend" <?php if($hotline['project_name']=='Deniyaya Lend') echo 'selected'; ?>>Deniyaya Lend</option>
      <option value="Kuragala Lend" <?php if($hotline['project_name']=='Kuragala Lend') echo 'selected'; ?>>Kuragala Lend</option>
      <option value="Kahawaththa Land" <?php if($hotline['project_name']=='Kahawaththa Land') echo 'selected'; ?>>Kahawaththa Land</option>
    </select>
  </div>
  <div class="mb-3">
    <label>Hotline Number</label>
    <input type="text" name="hotline_number" class="form-control" value="<?php echo htmlspecialchars($hotline['hotline_number']); ?>" required>
  </div>
  <button type="submit" name="update_hotline" class="btn btn-warning">Update Hotline</button>
  <a href="managehotlines.php" class="btn btn-secondary">Back</a>
</form>
</div>
</body>
</html>
