<?php
session_start();
include 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = trim($_POST['full_name']);
    $nic       = trim($_POST['nic']);
    $email     = trim($_POST['email']);
    $password  = trim($_POST['password']);
    $confirm   = trim($_POST['confirm_password']);

    if ($password !== $confirm) {
        $message = "<div class='alert alert-danger'>❌ Passwords do not match!</div>";
    } else {
        $stmt = $conn->prepare("SELECT id FROM logins WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $message = "<div class='alert alert-warning'>⚠ Email already registered!</div>";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $stmt = $conn->prepare("INSERT INTO logins (full_name,nic,email,password,role,status) VALUES (?,?,?,?,?,'Active')");
            $stmt->bind_param("sssss", $full_name, $nic, $email, $hashed_password, $role);
            if ($stmt->execute()) {
                $message = "<div class='alert alert-success'>✅ Admin registered successfully! <a href='admin_login.php'>Login here</a></div>";
            } else {
                $message = "<div class='alert alert-danger'>❌ Error: " . $stmt->error . "</div>";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Registration | Commercial Realty (PVT) LTD</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
body {
    background: linear-gradient(135deg, #0b1b58, #1835a0);
    color: #333;
    font-family: 'Poppins', sans-serif;
    min-height: 100vh;
}
.card {
    background: #fff;
    border: none;
    border-radius: 15px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.2);
    padding: 30px;
}
h3 {
    font-weight: 700;
    color: #0b1b58;
}
.form-control {
    border-radius: 10px;
    border: 1px solid #ccc;
}
.form-control:focus {
    border-color: #0b1b58;
    box-shadow: 0 0 5px rgba(11,27,88,0.5);
}
.btn-primary {
    background: #0b1b58;
    border: none;
    border-radius: 10px;
    transition: 0.3s;
}
.btn-primary:hover {
    background: #ff8c00;
}
.logo {
    display: block;
    margin: 0 auto 15px;
    width: 150px;
}
.company-info {
    text-align: center;
    color: #777;
    font-size: 0.9rem;
    margin-top: 10px;
}
.alert {
    border-radius: 10px;
}
/* Password field styling with eye icon */
.password-wrapper {
    position: relative;
}
.password-wrapper .toggle-password {
    position: absolute;
    top: 50%;
    right: 12px;
    transform: translateY(-50%);
    cursor: pointer;
    color: #0b1b58;
    font-size: 1rem;
}
.password-wrapper .toggle-password:hover {
    color: #ff8c00;
}
</style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh">
    <div class="card" style="width:420px;">
        <img src="CRealty.png" alt="Company Logo" class="logo">
        <h3 class="text-center mb-3">Admin Registration</h3>
        <?php echo $message; ?>
        <form method="POST">
            <div class="mb-3">
                <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-3">
                <input type="text" name="nic" class="form-control" placeholder="NIC" required>
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>

            <div class="mb-3 password-wrapper">
                <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('password', this)"></i>
            </div>

            <div class="mb-3 password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                <i class="fa-solid fa-eye toggle-password" onclick="togglePassword('confirm_password', this)"></i>
            </div>

            <button class="btn btn-primary w-100">Register</button>
        </form>

        <p class="text-center mt-3">
            <a href="admin_login.php" class="text-decoration-none">Already have an account? Login</a>
        </p>

        <div class="company-info">
            <p><strong>Commercial Realty (PVT) LTD</strong></p>
            <p>2nd Floor, 132 Avissawella Rd, Maharagama 10280</p>
        </div>
    </div>
</div>

<script>
function togglePassword(fieldId, icon) {
    const field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        field.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
}
</script>

</body>
</html>
