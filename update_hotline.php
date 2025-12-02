<?php
include 'db.php';

$id = $_GET['id'] ?? 0;

// Get existing hotline data
$result = $conn->query("SELECT * FROM hotlines WHERE id='$id'");
$row = $result->fetch_assoc();

$success_msg = "";
$error_msg = "";

// Update hotline
if (isset($_POST['update_hotline'])) {
    $hotline_number = trim($_POST['hotline_number']);
    $hotline_name   = trim($_POST['hotline_name']);
    $department     = trim($_POST['department']);
    $status         = $_POST['status'];
    $notes          = trim($_POST['notes']);

    $sql = "UPDATE hotlines 
            SET hotline_number='$hotline_number',
                hotline_name='$hotline_name',
                department='$department',
                status='$status',
                notes='$notes'
            WHERE id='$id'";

    if ($conn->query($sql)) {
        $success_msg = "✅ Hotline updated successfully!";
        // Refresh row data
        $result = $conn->query("SELECT * FROM hotlines WHERE id='$id'");
        $row = $result->fetch_assoc();
    } else {
        $error_msg = "❌ Update failed: {$conn->error}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Update Hotline</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
  <div class="card shadow-sm p-4">
    <h3 class="text-center mb-3 text-primary">✏️ Update Hotline</h3>

    <!-- Success/Error Messages -->
    <?php if($success_msg != ""): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $success_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    <?php if($error_msg != ""): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error_msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div class="row g-3">
        <div class="col-md-3">
          <label>Hotline Number</label>
          <input type="text" name="hotline_number" value="<?= $row['hotline_number'] ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label>Hotline Name</label>
          <input type="text" name="hotline_name" value="<?= $row['hotline_name'] ?>" class="form-control" required>
        </div>
        <div class="col-md-3">
          <label>Department</label>
          <input type="text" name="department" value="<?= $row['department'] ?>" class="form-control">
        </div>
        <div class="col-md-3">
          <label>Status</label>
          <select name="status" class="form-select">
            <option value="active" <?= $row['status']=='active'?'selected':'' ?>>Active</option>
            <option value="inactive" <?= $row['status']=='inactive'?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <div class="col-12">
          <label>Notes / Description</label>
          <textarea name="notes" class="form-control" rows="2"><?= $row['notes'] ?></textarea>
        </div>
      </div>

      <div class="mt-4 text-center">
        <button type="submit" name="update_hotline" class="btn btn-success">💾 Update Hotline</button>
        <a href="hotlines.php" class="btn btn-secondary">⬅ Back</a>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
