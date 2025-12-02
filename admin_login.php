<?php
session_start();
include 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, full_name, password FROM logins WHERE email=? AND role='admin' AND status='Active'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_name'] = $row['full_name'];
            header("Location: index.php");
            exit();
        } else {
            $message = "<div class='alert alert-danger text-center'>❌ Invalid password!</div>";
        }
    } else {
        $message = "<div class='alert alert-warning text-center'>⚠ Admin not found or inactive!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login | Commercial Realty</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    background: linear-gradient(135deg, #0b1b58, #1835a0);
    font-family: 'Segoe UI', sans-serif;
}
.login-container {
    background: #fff;
    width: 400px;
    border-radius: 18px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    padding: 35px 30px;
    text-align: center;
    position: relative;
}
.logo {
    width: 90px;
    height: 90px;
    object-fit: contain;
    border-radius: 50%;
    margin-bottom: 10px;
}
.company-info {
    text-align: center;
    color: #777;
    font-size: 0.9rem;
    margin-top: 10px;
}
h3 {
    font-weight: 700;
    color: #0b1b58;
    margin-bottom: 20px;
}
.form-control {
    border-radius: 10px;
    padding: 12px;
}
.input-group-text {
    border-radius: 10px;
    cursor: pointer;
    background-color: #e9ecef;
}
.btn-login {
    background-color: #0b1b58;
    color: #fff;
    border-radius: 10px;
    font-weight: 600;
    padding: 12px;
    transition: 0.3s;
}
.btn-login:hover {
    background-color: #ff8c00;
}
.alert {
    border-radius: 10px;
    font-size: 14px;
    padding: 8px;
}
.register-link a {
    color: #0b1b58;
    text-decoration: none;
    font-weight: 500;
}
.register-link a:hover {
    text-decoration: underline;
}
.footer-text {
    font-size: 12px;
    color: #777;
    margin-top: 10px;
}
</style>
</head>
<body>
<div class="login-container">
    <img src="CRealty.png" alt="Logo" class="logo">
    <h3>Admin Login</h3>
    <?php echo $message; ?>
    <form method="POST">
        <input type="email" name="email" class="form-control mb-3" placeholder="Email Address" required>

        <!-- Password Field with Toggle -->
        <div class="input-group mb-3">
            <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
            <span class="input-group-text" id="togglePassword">
                <i class="bi bi-eye-slash" id="eyeIcon"></i>
            </span>
        </div>

        <button type="submit" class="btn btn-login w-100">Login</button>
    </form>

    <div class="register-link mt-3">
        <p>Don't have an account? <a href="admin_register.php">Register Admin</a></p>
    </div>

    <div class="company-info">
        <p><strong>Commercial Realty (PVT) LTD</strong></p>
        <p>2nd Floor, 132 Avissawella Rd, Maharagama 10280</p>
    </div>

    <p class="footer-text">&copy; <?php echo date("Y"); ?> Commercial Realty Admin System</p>
</div>

<!-- Bootstrap Icons & JS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<script>
document.getElementById('togglePassword').addEventListener('click', function () {
    const passwordInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.classList.remove('bi-eye-slash');
        eyeIcon.classList.add('bi-eye');
    } else {
        passwordInput.type = 'password';
        eyeIcon.classList.remove('bi-eye');
        eyeIcon.classList.add('bi-eye-slash');
    }
});
</script>
</body>
</html>
