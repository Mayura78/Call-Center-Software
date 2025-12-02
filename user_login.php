<?php
session_start();
include 'db.php';
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, user_name, password FROM manageuser WHERE email=? AND status='Active'");
    $stmt->bind_param("s",$email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        if (password_verify($password,$row['password'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['user_name'];
            header("Location: home.php");
            exit();
        } else {
            $message = "<div class='alert alert-danger text-center py-2'>❌ Invalid password!</div>";
        }
    } else {
        $message = "<div class='alert alert-warning text-center py-2'>⚠ User not found!</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>User Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
    background: linear-gradient(135deg, #3b82f6, #9333ea);
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    font-family: "Poppins", sans-serif;
    margin:0;
}

.login-card {
    width: 100%;
    max-width: 400px;
    background: #fff;
    border-radius: 15px;
    padding: 40px 30px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    animation: fadeIn .6s ease-in-out;
    text-align: center;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* Logo + Address */
.logo-box img {
    width: 100px;
    height: 100px;
    object-fit: contain;
    margin-bottom: 10px;
}
.address {
    font-size: 13px;
    color: #555;
    margin-bottom: 20px;
}

/* Headings */
h4 {
    font-weight: 600;
    margin-bottom: 20px;
}

/* Inputs & Button */
.form-control {
    height: 45px;
    border-radius: 8px;
}
.btn-custom {
    background: #4f46e5;
    color: #fff;
    font-weight: 600;
    height: 45px;
    border-radius: 8px;
    transition: 0.3s;
}
.btn-custom:hover {
    background: #3730a3;
}

/* Links */
a {
    text-decoration: none;
}

/* Toast / Alert spacing */
.alert {
    font-size: 14px;
    margin-bottom: 15px;
}

/* Mobile */
@media (max-width: 576px) {
    .login-card {
        padding: 30px 20px;
    }
    .logo-box img {
        width: 80px;
        height: 80px;
    }
    .address { font-size: 12px; }
}
</style>
</head>
<body>

<div class="login-card">

    <!-- ===== Logo + Address ===== -->
    <div class="logo-box">
        <img src="CRealty.png" alt="Logo">
    </div>
    <div class="address">
        2nd Floor, 132 Avissawella Rd, Maharagama 10280<br>📞 0114 389 900
    </div>

    <h4>Welcome Back</h4>

    <?php echo $message; ?>

    <form method="POST">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" placeholder="Email Address" required>
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" placeholder="Password" required>
        </div>
        <button class="btn btn-custom w-100">Login</button>
    </form>

    <p class="text-center mt-3 mb-0">
        <a href="user_register.php" class="text-primary">Contact admin to register</a>
    </p>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
