<?php
session_start();
include 'db.php';

// ===== CHECK LOGIN =====
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';

// ===== CHECK AND CREATE TABLE IF NOT EXISTS =====
$tableCheck = $conn->query("SHOW TABLES LIKE 'numbers_list'");
if($tableCheck->num_rows == 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS `numbers_list` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `project_name` varchar(255) DEFAULT NULL,
        `phone_number` varchar(20) NOT NULL,
        `upload_date` datetime DEFAULT CURRENT_TIMESTAMP,
        `extra_info` text DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// ===== FETCH ASSIGNED NUMBERS =====
$stmt = $conn->prepare("
    SELECT id, project_name, phone_number, upload_date
    FROM numbers_list 
    WHERE user_id = ? 
    ORDER BY id DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>📞 My Target Numbers</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

<style>
body {
  background: linear-gradient(135deg, #001845, #00b4d8);
  min-height: 100vh;
  font-family: 'Segoe UI', sans-serif;
}
.container {
  background: #fff;
  border-radius: 15px;
  padding: 30px;
  margin-top: 60px;
  box-shadow: 0 6px 20px rgba(0,0,0,0.15);
}
h3 {
  color: #001845;
  font-weight: bold;
}
.table thead {
  background-color: #00b4d8;
  color: #fff;
}
.btn-logout {
  position: fixed;
  top: 20px;
  right: 30px;
}
.btn-view {
  background-color: #001845;
  color: white;
  border: none;
  transition: 0.3s;
}
.btn-view:hover {
  background-color: #00b4d8;
  color: #fff;
}
.modal-header {
  background-color: #00b4d8;
  color: white;
}
</style>
</head>
<body>

<!-- LOGOUT BUTTON -->
<a href="logout.php" class="btn btn-outline-light btn-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>

<div class="container">
  <h3 class="text-center mb-4"><i class="bi bi-telephone"></i> My Assigned Numbers</h3>
  <h6 class="text-center text-secondary mb-4">Welcome, <?= htmlspecialchars($user_name) ?> 👋</h6>

  <?php if ($result->num_rows > 0): ?>
  <div class="table-responsive">
    <table class="table table-bordered align-middle text-center">
      <thead>
        <tr>
          <th>ID</th>
          <th>Project</th>
          <th>Phone Number</th>
          <th>Uploaded Date</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
      <?php while($row = $result->fetch_assoc()): ?>
        <tr>
          <td><?= $row['id']; ?></td>
          <td><?= htmlspecialchars($row['project_name']); ?></td>
          <td><?= htmlspecialchars($row['phone_number']); ?></td>
          <td><?= htmlspecialchars($row['upload_date']); ?></td>
          <td>
            <button class="btn btn-view btn-sm" 
              data-bs-toggle="modal" 
              data-bs-target="#viewModal" 
              data-id="<?= $row['id']; ?>"
              data-project="<?= htmlspecialchars($row['project_name']); ?>"
              data-number="<?= htmlspecialchars($row['phone_number']); ?>"
              data-date="<?= htmlspecialchars($row['upload_date']); ?>">
              <i class="bi bi-person-lines-fill"></i> View Profile
            </button>
          </td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <div class="alert alert-info text-center mt-3">No numbers assigned to you yet.</div>
  <?php endif; ?>
</div>

<!-- PROFILE VIEW MODAL -->
<div class="modal fade" id="viewModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-person-circle"></i> Number Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p><strong>ID:</strong> <span id="modal-id"></span></p>
        <p><strong>Project:</strong> <span id="modal-project"></span></p>
        <p><strong>Phone Number:</strong> <span id="modal-number"></span></p>
        <p><strong>Uploaded Date:</strong> <span id="modal-date"></span></p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== LOAD MODAL DATA =====
const viewModal = document.getElementById('viewModal');
viewModal.addEventListener('show.bs.modal', function (event) {
  const button = event.relatedTarget;
  document.getElementById('modal-id').innerText = button.getAttribute('data-id');
  document.getElementById('modal-project').innerText = button.getAttribute('data-project');
  document.getElementById('modal-number').innerText = button.getAttribute('data-number');
  document.getElementById('modal-date').innerText = button.getAttribute('data-date');
});
</script>

</body>
</html>
