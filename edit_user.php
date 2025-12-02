<?php
session_start();
if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}
include 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id == 0){
    header("Location: manageuser.php");
    exit();
}

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM manageuser WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$message = "";

// Handle update
if(isset($_POST['update_user'])){
    $user_number = $_POST['user_number'];
    $user_name = $_POST['user_name'];
    $nic = $_POST['nic'];
    $email = $_POST['email'];
    $department = $_POST['department'];
    $join_date = $_POST['join_date'];
    $status = $_POST['status'];

    $photo_name = $user['photo'];
    if(isset($_FILES['photo']) && $_FILES['photo']['name'] != ''){
        $photo_name = time().'_'.basename($_FILES['photo']['name']);
        $target_dir = "uploads/users/";
        if(!is_dir($target_dir)) mkdir($target_dir, 0755, true);
        move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir.$photo_name);
    }

    $stmt = $conn->prepare("UPDATE manageuser SET user_number=?, user_name=?, nic=?, email=?, department=?, join_date=?, status=?, photo=? WHERE id=?");
    $stmt->bind_param("ssssssssi",$user_number,$user_name,$nic,$email,$department,$join_date,$status,$photo_name,$id);

    if($stmt->execute()){
        $message = "User updated successfully!";
        header("Location: manageuser.php");
        exit();
    } else {
        $message = "Error: ".$stmt->error;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="si">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit User</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
  <h2>Edit User</h2>
  <?php if($message!=""): ?>
    <div class="alert alert-success"><?php echo $message; ?></div>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="row g-3">
    <div class="col-md-3"><input type="text" name="user_number" class="form-control" value="<?php echo $user['user_number']; ?>" required></div>
    <div class="col-md-3"><input type="text" name="user_name" class="form-control" value="<?php echo $user['user_name']; ?>" required></div>
    <div class="col-md-3"><input type="text" name="nic" class="form-control" value="<?php echo $user['nic']; ?>" required></div>
    <div class="col-md-3"><input type="email" name="email" class="form-control" value="<?php echo $user['email']; ?>" required></div>
    <div class="col-md-3">
      <select name="department" class="form-select" required>
        <option value="Sales Department" <?php if($user['department']=='Sales Department') echo 'selected'; ?>>Sales Department</option>
        <option value="Marketing Department" <?php if($user['department']=='Marketing Department') echo 'selected'; ?>>Marketing Department</option>
        <option value="Account Department" <?php if($user['department']=='Account Department') echo 'selected'; ?>>Account Department</option>
      </select>
    </div>
    <div class="col-md-3"><input type="date" name="join_date" class="form-control" value="<?php echo $user['join_date']; ?>" required></div>
    <div class="col-md-3">
      <select name="status" class="form-select" required>
        <option value="Active" <?php if($user['status']=='Active') echo 'selected'; ?>>Active</option>
        <option value="Inactive" <?php if($user['status']=='Inactive') echo 'selected'; ?>>Inactive</option>
      </select>
    </div>
    <div class="col-md-3">
      <input type="file" name="photo" class="form-control">
      <?php if($user['photo'] && file_exists("uploads/users/".$user['photo'])): ?>
        <img src="uploads/users/<?php echo $user['photo']; ?>" width="50" class="mt-2">
      <?php endif; ?>
    </div>
    <div class="col-12">
      <button type="submit" name="update_user" class="btn btn-success">Update User</button>
      <a href="manageuser.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
</body>
</html>
